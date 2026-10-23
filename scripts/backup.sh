#!/usr/bin/env bash
# Nightly Postgres dump to S3. Needs the AWS CLI on the host and an instance role that can write to the bucket.
# Cron (as the deploy user): 15 2 * * * /home/ubuntu/school_erp/scripts/backup.sh >> /home/ubuntu/backup.log 2>&1
set -euo pipefail
cd "$(dirname "$0")/.."

set -a
. ./.env.deploy
set +a

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"

docker compose -f compose.prod.yaml exec -T pgsql \
    pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner \
    | gzip \
    | aws s3 cp - "s3://${BACKUP_BUCKET}/school_erp-${STAMP}.sql.gz" --sse AES256

echo "backup ${STAMP} uploaded"
