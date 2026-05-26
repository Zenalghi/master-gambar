#!/bin/sh
# Healthcheck script untuk MySQL
# Membaca password dari environment variable yang di-set oleh docker-compose env_file
mysqladmin ping -h localhost -u root -p"${MYSQL_ROOT_PASSWORD:-root_anti_ini}" > /dev/null 2>&1
