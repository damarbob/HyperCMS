#!/usr/bin/env sh
set -e

DB_PORT="${database_default_port:-3306}"

echo "⏳ Waiting for MySQL at ${database_default_hostname}:${DB_PORT}…"
until mysql \
  --host="$database_default_hostname" \
  --port="$DB_PORT" \
  --user="$database_default_username" \
  --password="$database_default_password" \
  --database="$database_default_database" \
  --silent --execute="SELECT 1" >/dev/null 2>&1
do
  echo "   -> still waiting…"
  sleep 1
done
echo "✅ MySQL is up!"

# Runs one SQL statement and prints the bare result, so a count comes back as a plain number.
db_query() {
  mysql \
    --host="$database_default_hostname" \
    --port="$DB_PORT" \
    --user="$database_default_username" \
    --password="$database_default_password" \
    --database="$database_default_database" \
    --silent --skip-column-names --execute="$1"
}

# The tick container runs only hyper:tick. It skips setup, migration and chown so two containers never run setup at once,
# and it waits until the web container has created StarDust's tables before its first run.
if [ "$HYPER_ROLE" = "tick" ]; then
  SCHEMA_TABLE="${hyperEngine_tablePrefix:-sd_}stardust_schema_version"
  until [ "$(db_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$SCHEMA_TABLE'" 2>/dev/null)" = "1" ]
  do
    echo "   -> waiting for the web container to create $SCHEMA_TABLE…"
    sleep 5
  done
  echo "✅ StarDust tables found, starting the tick loop"
  exec "$@"
fi

# Prints 1 when the database still has the 0.1 or 0.2 layout (a table named entries, entry_data, models or model_data, and no hyper_entries table).
old_layout() {
  db_query "SELECT IF(
    (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('entries', 'entry_data', 'models', 'model_data')) > 0
    AND (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'hyper_entries') = 0,
    1, 0)"
}

# An old layout is converted only by a person running hyper:upgrade, never from here.
if [ "$(old_layout)" = "1" ]; then
  echo "❌ This database still has the old HyperCMS layout (entries, entry_data, models or model_data tables and no hyper_entries)."
  echo "   Take a dump of the database first. This container stops, so run the upgrade in a new one:"
echo "   docker compose run --rm --entrypoint '' app php spark hyper:upgrade"
  echo "   This container never runs the upgrade by itself, so it stops here."
  exit 1
fi

MARKER="/var/www/html/writable/.hyper_setup_done"
if [ ! -f "$MARKER" ]; then
  # Run the initial hyper setup (for docker) only once
  echo "🚀 Running hyper:setup --docker"
  php spark hyper:setup --docker
  
  touch "$MARKER"
else
  echo "ℹ️  hyper:setup already ran"
  # Setup creates the tables on the first start. Every later start brings them up to date, for example when a new image adds tables.
  echo "🚀 Running hyper:migrate --all"
  php spark hyper:migrate --all
fi

# hyper:setup runs as root and creates root-owned files, so hand writable/ to the web server user on every start.
chown -R www-data:www-data /var/www/html/writable || true

exec "$@"