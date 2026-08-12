# Backups

Learn by Bluxor uses the existing shared MySQL container `mysql-server` plus Docker volumes for application files.

## MySQL Backup

From `/docker/apps/learn-bluxor`:

```bash
mkdir -p backups
docker exec mysql-server \
  mysqldump \
  -u learn_bluxor \
  -p \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  learn_bluxor > backups/learn_bluxor_$(date +%F).sql
```

Use an interactive password prompt or a protected MySQL option file. Do not commit database passwords.

## MySQL Restore

```bash
cat backups/learn_bluxor_YYYY-MM-DD.sql | \
docker exec -i mysql-server \
mysql -u learn_bluxor -p learn_bluxor
```

## File Volumes

Back up these Learn by Bluxor volumes:

- `learn_bluxor_private_storage`: protected product files and original landing ZIPs.
- `learn_bluxor_public_storage`: public media such as product covers.
- `learn_bluxor_landing_public`: validated published landing assets.
- `learn_bluxor_redis_data`: Redis AOF data for queue/cache/session resilience.

Example volume archive:

```bash
docker run --rm \
  -v learn-bluxor_learn_bluxor_private_storage:/data:ro \
  -v "$PWD/backups:/backup" \
  alpine tar -czf /backup/learn_bluxor_private_storage_$(date +%F).tar.gz -C /data .
```

Repeat for the other file volumes. Confirm the actual volume prefix with:

```bash
docker volume ls | grep learn_bluxor
```

## Restore Files

Stop the stack before restoring volumes:

```bash
docker compose down
```

Restore the archive into the target volume with a temporary Alpine container, then restart:

```bash
docker compose up -d
```
