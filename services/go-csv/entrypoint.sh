#!/usr/bin/env bash
set -e

CRON_SCHEDULE="${CRON_SCHEDULE:-*/5 * * * *}"
WRAPPER="/usr/local/bin/legacycsv-run.sh"
CRON_FILE="/etc/cron.d/legacycsv"

cat > "${WRAPPER}" <<'EOF'
#!/usr/bin/env bash
# wrapper generated at container start
# Export selected env vars into this cron execution environment.
EOF

ENV_VARS=(
  PGHOST PGPORT PGUSER PGDATABASE PGPASSWORD
  CSV_OUT_DIR GEN_PERIOD_SEC RUN_ONCE
)

for name in "${ENV_VARS[@]}"; do
  val="$(printenv "${name}" || true)"
  esc="${val//\'/\'\"\'\"\'}"
  echo "export ${name}='${esc}'" >> "${WRAPPER}"
done

cat >> "${WRAPPER}" <<'EOF'
# Execute the binary. Log to stdout/stderr by redirecting.
# We use exec so exit code propagates.
exec /app/legacycsv
EOF

chmod +x "${WRAPPER}"

echo "${CRON_SCHEDULE} root ${WRAPPER} >> /proc/1/fd/1 2>> /proc/1/fd/2" > "${CRON_FILE}"
chmod 0644 "${CRON_FILE}"


touch /var/log/cron.log || true

if [ "${RUN_ONCE}" = "1" ]; then
  echo "RUN_ONCE=1: executing once before starting cron"
  /usr/local/bin/legacycsv-run.sh
fi

echo "Starting cron (foreground)..."
exec cron -f
