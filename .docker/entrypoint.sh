#!/usr/bin/env sh
set -e

echo "⏳ Waiting for MySQL at ${database_default_hostname}:3306…"
until mysql \
  --host="$database_default_hostname" \
  --user="$database_default_username" \
  --password="$database_default_password" \
  --database="$database_default_database" \
  --silent --execute="SELECT 1" &>/dev/null
do
  echo "   -> still waiting…"
  sleep 1
done
echo "✅ MySQL is up!"

MARKER="/var/www/html/writable/.hyper_setup_done"
if [ ! -f "$MARKER" ]; then
  # Run the initial hyper setup (for docker) only once
  echo "🚀 Running hyper:setup --docker"
  php spark hyper:setup --docker
  
  touch "$MARKER"
else
  echo "ℹ️  hyper:setup already ran"
fi

exec "$@"