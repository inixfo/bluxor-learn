# Queues

Production uses Redis queues with one low-resource worker:

```text
Container: learn-bluxor-queue
Command: php artisan queue:work redis --sleep=3 --tries=3 --timeout=120 --backoff=60 --max-jobs=500
```

Environment:

```env
QUEUE_CONNECTION=redis
REDIS_HOST=learn-bluxor-redis
REDIS_PORT=6379
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=180
```

The Redis container is private to the `learn-internal` network and has no public port.

## Scheduler

The scheduler runs in a separate lightweight container:

```text
Container: learn-bluxor-scheduler
Command: php artisan schedule:work
```

No host cron is required.

## Operations

View logs:

```bash
docker logs learn-bluxor-queue --tail=100
docker logs -f learn-bluxor-queue
docker logs learn-bluxor-scheduler --tail=100
```

Restart:

```bash
docker compose restart queue scheduler
```

Start with one worker on the shared 4 GB VPS. Add workers only after measuring queue latency and memory pressure.
