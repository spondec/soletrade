#!/usr/bin/env bash

mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS soletrade_test;
    GRANT ALL PRIVILEGES ON \`soletrade_test%\`.* TO '$MYSQL_USER'@'%';
EOSQL
