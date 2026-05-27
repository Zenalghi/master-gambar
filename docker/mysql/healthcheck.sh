#!/bin/sh
# Healthcheck script untuk MySQL
mysqladmin ping -h localhost -u root -p"${MYSQL_ROOT_PASSWORD:-root_anti_ini}" > /dev/null 2>&1