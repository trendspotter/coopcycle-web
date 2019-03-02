#!/bin/sh

set -e

# Perform all actions as $POSTGRES_USER
export PGUSER="$POSTGRES_USER"

# Create the 'metabase' db
"${psql[@]}" <<- 'EOSQL'
CREATE DATABASE metabase;
EOSQL
