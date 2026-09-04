# Phase 10 — Deployment Guide

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Environment Setup](#environment-setup)
3. [Docker Deployment](#docker-deployment)
4. [Manual Deployment](#manual-deployment)
5. [CI/CD Pipeline](#cicd-pipeline)
6. [Post-Deployment Checklist](#post-deployment-checklist)
7. [Rollback Procedure](#rollback-procedure)
8. [Monitoring & Health Checks](#monitoring--health-checks)

---

## Prerequisites

- PHP >= 8.4
- MySQL >= 8.0
- Redis >= 7.0
- Node.js >= 20
- Composer 2.x
- Docker & Docker Compose (for containerized deployment)

---

## Environment Setup

### Backend `.env` Configuration

```env
APP_NAME="POS-SaaS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_saas
DB_USERNAME=your_user
DB_PASSWORD=your_password

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Security
SANCTUM_STATEFUL_DOMAINS=your-domain.com
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# CORS
CORS_ALLOWED_ORIGINS=https://your-domain.com

# 2FA
TWOFA_BACKUP_CODES_COUNT=10
TWOFA_TEMP_TOKEN_TTL=300
```

### Frontend `.env` Configuration

```env
VITE_API_BASE_URL=https://your-domain.com/api/v1
```

---

## Docker Deployment

```bash
# Clone repository
git clone <repo-url> /opt/pos-saas
cd /opt/pos-saas

# Copy environment files
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env

# Edit .env files with production values

# Build and start containers
docker-compose up -d --build

# Run migrations and seeders
docker-compose exec backend php artisan migrate --force
docker-compose exec backend php artisan db:seed --force

# Optimize for production
docker-compose exec backend php artisan optimize
docker-compose exec backend php artisan config:cache
docker-compose exec backend php artisan route:cache
docker-compose exec backend php artisan view:cache

# Set up scheduler cron
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

---

## Manual Deployment

### Backend

```bash
cd backend

# Install dependencies
composer install --no-dev --optimize-autoloader

# Environment setup
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate --force
php artisan db:seed --force

# Cache optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Set up queue worker (supervisor or systemd)
php artisan queue:work --daemon

# Set up scheduler
echo "* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1" | crontab -
```

### Frontend

```bash
cd frontend

# Install dependencies
npm ci

# Build for production
npm run build

# Deploy dist/ to web server
cp -r dist/* /var/www/html/
```

---

## CI/CD Pipeline

The GitHub Actions workflow (`.github/workflows/ci.yml`) automates:

1. **Backend Tests** — Runs PHPUnit tests with MySQL and Redis services
2. **Frontend Build** — Type-checks and builds the frontend
3. **Security Audit** — Runs `composer audit` and checks for hardcoded secrets
4. **Deploy Staging** — Auto-deploys on `develop` branch
5. **Deploy Production** — Auto-deploys on `main` branch (requires environment approval)

### Required GitHub Secrets

- `STAGING_SSH_KEY` — SSH key for staging server
- `STAGING_HOST` — Staging server hostname
- `PRODUCTION_SSH_KEY` — SSH key for production server
- `PRODUCTION_HOST` — Production server hostname

---

## Post-Deployment Checklist

- [ ] Health check endpoint responds: `GET /api/v1/health`
- [ ] All migrations applied successfully
- [ ] Seeders ran without errors
- [ ] Rate limiting configured and active
- [ ] CORS headers present in responses
- [ ] XSS middleware sanitizing inputs
- [ ] 2FA endpoints accessible
- [ ] Audit logs being created on model changes
- [ ] Backup command runs: `php artisan backup:database`
- [ ] Audit purge scheduled: `php artisan audit:purge`
- [ ] Queue worker running
- [ ] Scheduler cron configured
- [ ] SSL/TLS certificates valid
- [ ] Security headers present (X-Frame-Options, X-Content-Type-Options, etc.)

---

## Rollback Procedure

### Database Rollback

```bash
# List available backups
php artisan backup:list

# Restore from backup
php artisan backup:restore backup-2024-01-15-020000.sql.gz

# Or rollback migrations
php artisan migrate:rollback --step=5
```

### Application Rollback

```bash
# Using git
git log --oneline -10
git checkout <previous-stable-commit>
composer install --no-dev --optimize-autoloader
php artisan optimize
php artisan migrate --force
```

### Frontend Rollback

```bash
# Restore previous build
cp -r /var/backups/frontend-dist-previous/* /var/www/html/
```

---

## Monitoring & Health Checks

### Health Endpoint

```bash
curl https://your-domain.com/api/v1/health
```

Response:
```json
{
  "status": "healthy",
  "timestamp": "2024-01-15T02:00:00Z",
  "checks": {
    "database": "ok",
    "storage": "ok",
    "redis": "ok",
    "queue": "ok"
  }
}
```

### Scheduled Tasks

| Task | Schedule | Command |
|------|----------|---------|
| Database backup | Daily 02:00 | `backup:database` |
| Audit log purge | Daily 03:00 | `audit:purge` |

### Log Monitoring

- Application logs: `storage/logs/laravel.log`
- Audit logs: `audit_logs` table (viewable via `/api/v1/audit-logs`)
- CSV export: `GET /api/v1/audit-logs/export`

### Alerting

Set up alerts for:
- Health check returning non-200 status
- Failed queue jobs exceeding threshold
- Rate limit hits exceeding normal patterns
- Account lockouts spiking (potential brute force)
- Backup failures
