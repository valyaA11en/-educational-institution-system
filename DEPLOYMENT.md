# Production Deployment Guide

## Pre-deployment Checklist

- [ ] Environment variables configured
- [ ] Database migrations ready
- [ ] Secrets configured (2FA, LDAP, etc.)
- [ ] Backup strategy in place
- [ ] Monitoring configured

## Deployment Steps

1. **Backup current state**
```bash
php artisan backup:database
```

2. **Pull latest code**
```bash
git pull origin main
```

3. **Install dependencies**
```bash
composer install --no-dev --optimize-autoloader
```

4. **Run migrations**
```bash
php artisan migrate --force
```

5. **Run smoke tests**
```bash
php artisan smoke:test
```

6. **Clear caches**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

7. **Restart services**
```bash
# Via supervisor/systemd
sudo systemctl restart php-fpm
sudo systemctl restart nginx
```

## Rollback

```bash
php artisan migrate:rollback-safe
# Or restore from backup
php artisan backup:restore storage/app/backups/backup_YYYYMMDD_HHMMSS.sql.gz
```

## Multi-Tenant Setup

1. Create default tenant:
```bash
php artisan db:seed --class=TenantSeeder
```

2. Configure tenant domains or use headers:
- `X-Tenant-ID: 1`
- `X-Tenant-Slug: default`

## Monitoring

- Health: `/api/health`
- Metrics: `/api/metrics` (Prometheus)
- Logs: `storage/logs/`

## Security

- 2FA enabled for admin users
- Rate limiting: 60 req/min
- Security headers enabled
- Audit logging active


