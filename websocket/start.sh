#!/bin/sh
# Update Redis password in config from environment
if [ -n "$REDIS_PASSWORD" ]; then
  sed -i "s/\"password\": \".*\"/\"password\": \"$REDIS_PASSWORD\"/" /app/laravel-echo-server.json
fi
# Start Laravel Echo Server
exec laravel-echo-server start
