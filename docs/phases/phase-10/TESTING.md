# Phase 10 — Production, Security & Monitoring — Testing

**Document Status:** DRAFT  
**Created:** 2026-08-18  
**Phase:** 10

---

## 1. TESTING STRATEGY

### 1.1 Principles

- **No regression:** All 1197 existing tests must pass at every step
- **Additive only:** New tests are added, existing tests are not modified
- **Environment-aware:** Rate limiting and lockout bypassed in testing
- **Isolated:** Each test uses `RefreshDatabase` (existing pattern)
- **Comprehensive:** Every new feature has unit + integration tests
- **E2E:** Full security flows tested end-to-end

### 1.2 Test Categories

| Category | File | Est. Tests |
|----------|------|-----------|
| Password Policy | `tests/Feature/PasswordPolicyTest.php` | 8 |
| Account Lockout | `tests/Feature/AccountLockoutTest.php` | 6 |
| 2FA Authentication | `tests/Feature/TwoFactorAuthTest.php` | 12 |
| XSS Sanitization | `tests/Feature/XssSanitizationTest.php` | 6 |
| CORS Configuration | `tests/Feature/CorsConfigTest.php` | 4 |
| Rate Limiting | `tests/Feature/RateLimitTest.php` | 8 |
| Audit Observers | `tests/Feature/AuditObserverTest.php` | 10 |
| Sentry Integration | `tests/Feature/SentryIntegrationTest.php` | 3 |
| Health Check | `tests/Feature/HealthCheckTest.php` | 4 |
| Backup & Restore | `tests/Feature/BackupRestoreTest.php` | 5 |
| OpenAPI Generation | `tests/Feature/OpenApiSpecTest.php` | 4 |
| Performance | `tests/Feature/PerformanceOptimizationTest.php` | 6 |
| PDP Compliance | `tests/Feature/PdpComplianceTest.php` | 6 |
| E2E Security | `tests/Feature/SecurityE2ETest.php` | 8 |
| **Total** | | **~90** |

---

## 2. DETAILED TEST SPECIFICATIONS

### 2.1 PasswordPolicyTest

```
tests/Feature/PasswordPolicyTest.php
```

| Test | Description |
|------|-------------|
| test_registration_rejects_short_password | Password < 12 chars → 422 |
| test_registration_rejects_no_uppercase | Missing uppercase → 422 |
| test_registration_rejects_no_lowercase | Missing lowercase → 422 |
| test_registration_rejects_no_number | Missing digit → 422 |
| test_registration_rejects_no_symbol | Missing special char → 422 |
| test_registration_accepts_strong_password | 12+ chars, mixed → 201 |
| test_password_change_rejects_reuse | Last 5 passwords blocked → 422 |
| test_password_history_keeps_last_5 | 6th old password allowed |

### 2.2 AccountLockoutTest

```
tests/Feature/AccountLockoutTest.php
```

| Test | Description |
|------|-------------|
| test_lockout_after_5_failed_attempts | 5 fails → 423, 15 min lock |
| test_lockout_after_10_failed_attempts | 10 fails → 423, 1 hr lock |
| test_lockout_after_15_failed_attempts | 15 fails → 423, 24 hr lock |
| test_successful_login_resets_counter | Login after 4 fails → counter reset |
| test_lockout_expires | Wait for expiry → login works |
| test_admin_can_unlock | POST admin/unlock → 200, user unlocked |

### 2.3 TwoFactorAuthTest

```
tests/Feature/TwoFactorAuthTest.php
```

| Test | Description |
|------|-------------|
| test_enable_2fa_returns_qr_code | POST 2fa/enable → 200 with QR + secret |
| test_verify_2fa_with_valid_code | POST 2fa/verify with valid TOTP → 200 |
| test_verify_2fa_with_invalid_code | POST 2fa/verify with bad code → 422 |
| test_disable_2fa_requires_valid_code | POST 2fa/disable with valid → 200, invalid → 422 |
| test_2fa_status_endpoint | GET 2fa/status → enabled, backup_codes_remaining |
| test_backup_codes_work | Use backup code for login → 200, code consumed |
| test_backup_code_single_use | Same backup code twice → 422 |
| test_regenerate_backup_codes | POST 2fa/backup-codes with valid TOTP → new codes |
| test_login_with_2fa_returns_temp_token | Login → 200 with 2fa_required + temp_token |
| test_login_2fa_with_valid_code | POST login-2fa with valid → 200 with token |
| test_login_2fa_with_invalid_code | POST login-2fa with invalid → 422 |
| test_2fa_feature_flag | 2FA disabled for tenant → enable returns 403 |

### 2.4 XssSanitizationTest

```
tests/Feature/XssSanitizationTest.php
```

| Test | Description |
|------|-------------|
| test_script_tag_stripped | `<script>alert(1)</script>` in name → stripped |
| test_onerror_attribute_stripped | `<img src=x onerror=alert(1)>` → onerror removed |
| test_javascript_uri_stripped | `javascript:alert(1)` in URL → stripped |
| test_iframe_tag_stripped | `<iframe>` → stripped |
| test_valid_data_preserved | Product name "Coffee <3" → preserved (no script tag) |
| test_skip_fields_not_sanitized | Rich text field with HTML → preserved |

### 2.5 CorsConfigTest

```
tests/Feature/CorsConfigTest.php
```

| Test | Description |
|------|-------------|
| test_cors_allows_configured_origin | Origin in allowlist → Access-Control-Allow-Origin present |
| test_cors_blocks_unknown_origin | Origin not in allowlist → no ACAO header |
| test_cors_preflight_ok | OPTIONS with allowed method → 200 |
| test_cors_credentials_supported | Request with credentials → ACAO includes origin |

### 2.6 RateLimitTest

```
tests/Feature/RateLimitTest.php
```

| Test | Description |
|------|-------------|
| test_rate_limit_headers_present | Response has X-RateLimit-Limit + Remaining |
| test_rate_limit_429_on_exceed | Exceed limit → 429 with Retry-After |
| test_rate_limit_per_tenant | Tenant A limit doesn't affect Tenant B |
| test_rate_limit_per_user | User A limit doesn't affect User B |
| test_rate_limit_write_endpoints | POST limited to 60/min |
| test_rate_limit_read_endpoints | GET limited to 300/min |
| test_rate_limit_bypassed_in_testing | In testing env → no 429 |
| test_rate_limit_auth_endpoints | Auth endpoint limited to 5/min |

### 2.7 AuditObserverTest

```
tests/Feature/AuditObserverTest.php
```

| Test | Description |
|------|-------------|
| test_product_create_logged | Create product → audit log entry with action=created |
| test_product_update_logged | Update product → audit log with old + new values |
| test_product_delete_logged | Delete product → audit log with action=deleted |
| test_sale_create_logged | Create sale → audit log entry |
| test_payment_create_logged | Create payment → audit log entry |
| test_customer_update_logged | Update customer → audit log entry |
| test_audit_log_includes_route | Audit log has route + method columns |
| test_audit_log_tenant_scoped | Tenant A cannot see Tenant B's audit logs |
| test_audit_log_redacts_secrets | Password field in new_values → [REDACTED] |
| test_audit_log_retention_purge | Logs older than 90 days → deleted by command |

### 2.8 SentryIntegrationTest

```
tests/Feature/SentryIntegrationTest.php
```

| Test | Description |
|------|-------------|
| test_sentry_disabled_without_dsn | No SENTRY_DSN → no Sentry calls |
| test_sentry_captures_exception | Throw exception → Sentry capture called (mocked) |
| test_sentry_no_pii_in_context | Sentry context has no email/name (only id, tenant_id) |

### 2.9 HealthCheckTest

```
tests/Feature/HealthCheckTest.php
```

| Test | Description |
|------|-------------|
| test_health_check_returns_200 | All deps OK → 200 with status=healthy |
| test_health_check_database_failure | DB down → 503 with database=fail |
| test_health_check_no_auth_required | No token → still 200 |
| test_health_check_rate_limited | > 60/min → 429 |

### 2.10 BackupRestoreTest

```
tests/Feature/BackupRestoreTest.php
```

| Test | Description |
|------|-------------|
| test_backup_command_creates_file | Artisan backup:database → .sql.gz in storage |
| test_backup_file_is_valid_gzip | File starts with gzip magic bytes |
| test_backup_retention_cleanup | Old backups deleted per retention policy |
| test_restore_command_imports_data | Restore from backup → data present |
| test_backup_failure_logged | Backup fails → error logged |

### 2.11 OpenApiSpecTest

```
tests/Feature/OpenApiSpecTest.php
```

| Test | Description |
|------|-------------|
| test_openapi_json_generated | GET /api/openapi.json → 200 with valid spec |
| test_openapi_has_info_section | Spec has title, version, description |
| test_openapi_has_security_schemes | Spec has Bearer + ApiKey schemes |
| test_swagger_ui_accessible | GET /api/docs → 200 HTML |

### 2.12 PerformanceOptimizationTest

```
tests/Feature/PerformanceOptimizationTest.php
```

| Test | Description |
|------|-------------|
| test_product_list_no_n_plus_1 | List products → query count < 5 |
| test_sale_list_no_n_plus_1 | List sales with items → query count < 5 |
| test_purchase_list_no_n_plus_1 | List purchases with items → query count < 5 |
| test_cache_hit_for_modules | Second request for modules → cache hit |
| test_cache_invalidated_on_module_change | Toggle module → cache flushed |
| test_indexes_exist_on_hot_tables | Check DB indexes exist on specified columns |

### 2.13 PdpComplianceTest

```
tests/Feature/PdpComplianceTest.php
```

| Test | Description |
|------|-------------|
| test_account_export_returns_user_data | GET account/export → JSON with user data |
| test_account_export_includes_sales | Export contains sales records |
| test_account_deletion_soft_deletes | DELETE account → user soft-deleted |
| test_account_deletion_anonymizes_pii | After deletion → name="Deleted User" |
| test_account_deletion_scheduled_purge | Purge date = 30 days from now |
| test_consent_log_recorded_at_registration | Register → consent log created |

### 2.14 SecurityE2ETest

```
tests/Feature/SecurityE2ETest.php
```

| Test | Description |
|------|-------------|
| test_e2e_full_2fa_flow | Enable 2FA → login → 2FA prompt → verify → token |
| test_e2e_lockout_then_unlock | 5 failed logins → locked → admin unlock → login OK |
| test_e2e_password_change_with_history | Change password → try reuse → blocked |
| test_e2e_audit_trail_complete | Create sale → audit log has full trail |
| test_e2e_xss_prevention | Submit XSS payload → stored clean → no script in response |
| test_e2e_account_export_and_delete | Export → verify data → delete → verify anonymized |
| test_e2e_rate_limit_progressive | Exceed rate → 429 → wait → works again |
| test_e2e_health_check_after_deploy | Health endpoint → all deps OK |

---

## 3. TEST ENVIRONMENT

### 3.1 Configuration

```php
// phpunit.xml (existing, no changes needed)
<env name="APP_ENV" value="testing"/>
<env name="CACHE_STORE" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
```

### 3.2 Bypass Rules

| Feature | Bypass in Testing | How |
|---------|-------------------|-----|
| Rate limiting | Yes | `Limit::none()` when env=testing |
| Account lockout | Yes | Skip lockout check when env=testing |
| Sentry | Yes | No DSN set in testing |
| CORS | Yes | Laravel default permissive in testing |
| 2FA feature flag | No (test with feature enabled) | Enable in seeder |

### 3.3 Mocking Strategy

| Component | Mock? | How |
|-----------|-------|-----|
| Sentry SDK | Yes | Mock `Sentry::captureException()` |
| Redis | No | Use array cache in testing |
| mysqldump | Yes | Mock backup command, test file creation |
| Google2FA | No | Use real library with test secrets |
| HaveIBeenPwned | Yes | Mock `uncompromised()` check |

---

## 4. REGRESSION PLAN

### 4.1 Pre-Implementation Baseline

```
Current: 1197 tests, 3025 assertions, 0 failures
```

### 4.2 Per-Step Regression

After each implementation step:
1. Run new tests for that step
2. Run full regression suite
3. Verify: 1197 + new tests all pass

### 4.3 Final Regression

```
Target: 1197 + ~90 new = ~1287 tests, 0 failures
Command: docker exec pos_saas_backend php artisan test
```

### 4.4 Load Testing (Separate)

Load testing is NOT part of PHPUnit suite. Run separately:

```bash
# k6 load test (100 concurrent users, 60 seconds)
k6 run --vus 100 --duration 60s load-tests/api-load.js
```

**Target metrics:**
- p50 response time: < 200ms
- p95 response time: < 500ms
- p99 response time: < 1000ms
- Error rate: < 0.1%
- Throughput: > 500 req/s

---

## 5. TEST DATA

### 5.1 Test Fixtures

Use existing `E2ESeeder` for most tests. For Phase 10 specific tests:

- Create user with 2FA enabled (in test setup)
- Create lockout records (in test setup)
- Create password history records (in test setup)
- Create audit log entries (via model operations)

### 5.2 TOTP Test Codes

For testing TOTP verification:
- Use fixed secret: `JBSWY3DPEHPK3PXP` (base32 for "Hello!\xde\xad\xbe\xef")
- Generate valid code at current time using Google2FA library
- No need to mock TOTP — library is deterministic with known secret

---

## 6. CI/CD TEST GATES

### 6.1 CI Pipeline (GitHub Actions)

| Gate | Command | Must Pass |
|------|---------|-----------|
| Code style | `./vendor/bin/pint --test` | Yes |
| Static analysis | `./vendor/bin/phpstan analyse` | Yes |
| Unit + Feature tests | `php artisan test` | Yes |
| Frontend build | `npm run build` | Yes |
| TypeScript check | `npx tsc --noEmit` | Yes |

### 6.2 CD Pipeline

| Gate | Command | Must Pass |
|------|---------|-----------|
| Staging smoke tests | `php artisan test --filter=SmokeTest` | Yes |
| Staging health check | `curl /api/v1/health` → 200 | Yes |
| Production smoke tests | Same as staging | Yes |
| Production health check | Same as staging | Yes |

---

*End of Phase 10 Testing*
