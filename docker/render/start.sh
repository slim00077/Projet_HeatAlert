#!/bin/sh
set -e
sed "s/__PORT__/${PORT:-10000}/" /etc/heatalert/nginx.conf > /etc/nginx/http.d/default.conf
exec supervisord -c /etc/heatalert/supervisord.conf
