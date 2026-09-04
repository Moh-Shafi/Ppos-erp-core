# Phase 10 — Security Audit Checklist

## Authentication & Authorization
- [ ] Password policy enforced (min 12 chars, mixed case, numbers, symbols)
- [ ] Password history checked (last 5 passwords)
- [ ] Account lockout after 5/10/15 failed attempts
- [ ] Progressive lockout durations (15min/1hr/24hr)
- [ ] Rate limiting on auth endpoints (5/min)
- [ ] Rate limiting on API endpoints (60/min)
- [ ] Rate limiting per tenant (1000/min)
- [ ] Rate limiting per user (200/min)
- [ ] Sanctum tokens properly scoped
- [ ] 2FA available with TOTP
- [ ] 2FA backup codes generated (10 codes)
- [ ] 2FA backup codes are single-use
- [ ] 2FA temp token TTL (300s)
- [ ] Admin can unlock accounts
- [ ] Admin can reset 2FA

## Input Validation & XSS Prevention
- [ ] XSS middleware strips script tags
- [ ] XSS middleware strips event handlers (onclick, onload, etc.)
- [ ] XSS middleware strips javascript: URIs
- [ ] Configurable skip fields for safe HTML
- [ ] All API inputs validated
- [ ] SQL injection prevention (Eloquent parameterized queries)
- [ ] File upload restrictions (type, size)

## CORS Configuration
- [ ] Allowed origins restricted to known domains
- [ ] Allowed methods restricted (GET, POST, PUT, DELETE)
- [ ] Allowed headers explicitly listed
- [ ] Credentials support enabled
- [ ] Max age set (86400)
- [ ] Exposed headers configured

## Audit & Observability
- [ ] Model observers registered for all key models
- [ ] Audit logs capture user, action, entity, old/new values
- [ ] Audit logs capture IP address and user agent
- [ ] Audit logs capture route and HTTP method
- [ ] Sensitive fields redacted in audit logs (password, secret, token)
- [ ] Audit log retention period (90 days)
- [ ] Audit purge command scheduled daily
- [ ] Audit log CSV export available
- [ ] Audit log filtering by entity_type, action, route, method, date

## Data Protection & PDP Compliance
- [ ] User data export endpoint (JSON download)
- [ ] Account deletion with password confirmation
- [ ] Soft delete with 30-day grace period
- [ ] Email anonymization on deletion
- [ ] Consent logging
- [ ] Audit entry on data export
- [ ] Audit entry on account deletion

## Backup & Disaster Recovery
- [ ] Database backup command (mysqldump + gzip)
- [ ] Backup retention (7 daily, 4 weekly, 3 monthly)
- [ ] Database restore command
- [ ] Backups scheduled daily (02:00)
- [ ] Backup files stored in local storage/backups/

## Infrastructure Security
- [ ] HTTPS/TLS enforced
- [ ] Security headers (X-Frame-Options, X-Content-Type-Options, X-XSS-Protection)
- [ ] HSTS header configured
- [ ] Referrer-Policy header set
- [ ] Environment variables not hardcoded
- [ ] .env file not in version control
- [ ] Debug mode disabled in production
- [ ] Error pages don't expose stack traces

## API Security
- [ ] All endpoints behind authentication (except register, login, health)
- [ ] Permission checks on all sensitive endpoints
- [ ] Tenant isolation enforced (BelongsToTenant)
- [ ] OpenAPI spec available at /api/v1/openapi.json
- [ ] API versioning (/api/v1/)

## Health Monitoring
- [ ] Health check endpoint (/api/v1/health)
- [ ] Database connectivity check
- [ ] Storage write check
- [ ] Redis connectivity check
- [ ] Queue worker check
- [ ] Health endpoint rate limited

## CI/CD Security
- [ ] Composer audit in CI pipeline
- [ ] No hardcoded secrets in code
- [ ] GitHub secrets for deployment keys
- [ ] Environment approval gates for production
- [ ] Automated tests run on every PR
