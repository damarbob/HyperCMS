#!/usr/bin/env sh
set -e

DB_PORT="${database_default_port:-3306}"
# The table prefix of the site, empty unless database.default.DBPrefix is set.
DB_PREFIX="${database_default_DBPrefix:-}"

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

# Prints 1 while an old database has not finished its upgrade, so nothing should start on it yet. That is a database that
# still holds a table named entries, entry_data, models or model_data and has no finished mark. hyper:upgrade writes the mark
# (the row upgrade_finished in hyper_counters) as its last step. A table called hyper_entries is not enough to tell,
# because the upgrade's first step creates it long before the copy is done.
upgrade_pending() {
  old=$(db_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('entries', 'entry_data', 'models', 'model_data')")
  if [ "$old" = "0" ]; then
    echo 0
    return
  fi

  # Only a counted 1 from every query means finished. A failed query prints nothing, which counts as pending.
  counters=$(db_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '${DB_PREFIX}hyper_counters'")
  finished=0
  if [ "$counters" = "1" ]; then
    finished=$(db_query "SELECT COUNT(*) FROM ${DB_PREFIX}hyper_counters WHERE name = 'upgrade_finished'")
  fi
  if [ "$finished" = "1" ]; then
    echo 0
  else
    echo 1
  fi
}

# The tick container runs only hyper:tick. It skips setup, migration and chown so two containers never run setup at once.
# It waits until the web container has created StarDust's tables and until no upgrade is half done, because the repair
# pass of a tick would otherwise write the entries the upgrade is still copying.
if [ "$HYPER_ROLE" = "tick" ]; then
  SCHEMA_TABLE="${DB_PREFIX}${hyperengine_tablePrefix:-sd_}stardust_schema_version"
  until [ "$(db_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$SCHEMA_TABLE'" 2>/dev/null)" = "1" ] && [ "$(upgrade_pending 2>/dev/null)" = "0" ]
  do
    echo "   -> waiting for the web container to create $SCHEMA_TABLE and for any upgrade to finish…"
    sleep 5
  done
  echo "✅ StarDust tables found, starting the tick loop"
  exec "$@"
fi

# An old layout is converted only by a person running hyper:upgrade, never from here. The container stops until the
# upgrade has finished, so a restart during or after a failed upgrade never boots on a half-copied database.
if [ "$(upgrade_pending)" = "1" ]; then
  echo "❌ This database has the old HyperCMS layout (entries, entry_data, models or model_data tables) and its upgrade is not finished."
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