#!/bin/sh
# Default command for mysqld_exporterset -e

cat > /etc/mysql/.my.cnf <<EOF
[client]
user=${MYSQL_EXPORTER_USER}
password=${MYSQL_EXPORTER_PASS}
host=${MYSQL_EXPORTER_HOST}
EOF

exec /bin/mysqld_exporter \
    --config.my-cnf=/etc/mysql/.my.cnf \
    --collect.global_status \
    --collect.global_variables \
    --collect.info_schema.tables \
    --collect.info_schema.innodb_metrics \
    --collect.info_schema.processlist \
    --collect.slave_status \
    --collect.engine_innodb_status \
    --collect.perf_schema.eventsstatements \
    --collect.perf_schema.eventsstatementssum \
    --collect.perf_schema.eventswaits \
    --collect.perf_schema.tableiowaits \
    --collect.perf_schema.tablelocks \
    --collect.perf_schema.indexiowaits \
    --collect.perf_schema.file_events \
    --collect.perf_schema.file_instances \
    --collect.perf_schema.memory_events \
    --collect.info_schema.innodb_cmpmem \
    --collect.info_schema.query_response_time \
    --collect.info_schema.userstats \
    --collect.info_schema.tablestats \
    --collect.info_schema.schemastats \
    --collect.info_schema.clientstats \
    --web.listen-address=:9104 \
    --web.telemetry-path=/metrics \
    "$@"
