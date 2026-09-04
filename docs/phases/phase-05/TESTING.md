# Phase 5 — Payment Infrastructure — Testing

**Document Status:** DRAFT  
**Created:** 2026-08-15  
**Depends On:** `PDR.md`, `ARCHITECTURE.md`, `API.md`, `SECURITY.md`

---

## 1. TESTING STRATEGY

Phase 5 testing follows the project-wide rule: **all existing tests must pass (no regressions)**. New tests cover backend feature tests, frontend TypeScript, Vite build, and E2E scenarios.

### 1.1 Test Levels

| Level | Scope | Tools |
|-------|-------|-------|
| Unit | `XenditPayment` methods, `WebhookProcessor`, `CashDrawerService` | PHPUnit + mocking |
| Feature | API endpoints, webhook handling, settlement sync | Laravel PHPUnit + RefreshDatabase |
| Integration | Xendit HTTP client with mocked responses | Guzzle mock handler |
| Frontend | TypeScript, component logic, service calls | `tsc --noEmit`, Vitest (if configured) |
| Build | Vite production build | `vite build` |
| E2E | Full QRIS payment flow, cash drawer | Playwright |

### 1.2 Test Environment

- **Backend:** Docker `pos_saas_backend`, PHP 8.4.24, MySQL 8.0
- **Database:** `pos_saas_testing` with `RefreshDatabase`
- **Xendit:** Test Mode (mocked or sandbox)
- **Webhook simulation:** `POST /api/v1/webhooks/xendit` with test token

---

## 2. BACKEND TESTS

### 2.1 Test Files

| File | Focus |
|------|-------|
| `Phase5PaymentGatewayTest.php` | XenditPayment integration, manual fallback |
| `Phase5WebhookTest.php` | Webhook token verification, idempotency, processing |
| `Phase5QRISPaymentTest.php` | QRIS charge creation, status flow, poll |
| `Phase5RefundTest.php` | Refund validation, gateway refund, partial/full |
| `Phase5SettlementTest.php` | Settlement sync, reconciliation report |
| `Phase5CashDrawerTest.php` | Open/close/reconcile cash drawer |
| `Phase5SubAccountTest.php` | Sub-account provisioning, KYC webhooks |
| `Phase5SecurityTest.php` | RBAC, tenant isolation, fake webhooks |
| `Phase5FinalGateTest.php` | End-to-end: provision → charge → webhook → settle |

### 2.2 Feature Test Cases

#### Payment Gateway

- `test_xendit_gateway_implements_interface` — `XenditPayment` instance of `PaymentGatewayInterface`
- `test_manual_payment_still_works` — cash payments remain synchronous
- `test_gateway_selection_based_on_config` — `PaymentServiceProvider` returns correct gateway
- `test_xendit_create_charge_qris_returns_qr_string` — mock Xendit response
- `test_xendit_create_charge_with_for_user_id` — tenant sub-account header
- `test_xendit_create_charge_with_idempotency_key` — duplicate request returns same result
- `test_xendit_get_status_succeeded` — `getStatus` returns `SUCCEEDED`
- `test_xendit_refund_success` — `refund` returns `success`
- `test_xendit_provision_sub_account` — `provisionSubAccount` returns `pending`

#### Webhooks

- `test_webhook_unauthorized_without_token` — 401 without `x-callback-token`
- `test_webhook_unauthorized_with_invalid_token` — 401 with wrong token
- `test_webhook_payment_capture_updates_payment` — `SUCCEEDED` → payment success
- `test_webhook_payment_failure_updates_payment` — `FAILED` → payment failed
- `test_webhook_duplicate_event_id_ignored` — second identical event 200, no duplicate update
- `test_webhook_updates_sale_payment_status` — sale becomes `paid`
- `test_webhook_unmatched_payment_id_stored_not_processed` — no crash
- `test_webhook_qr_payment_capture` — QRIS-specific `payment.capture`
- `test_webhook_account_activated_updates_sub_account` — KYC/capability webhook

#### QRIS Payment

- `test_qris_charge_creates_pending_payment` — payment status `pending`
- `test_qris_charge_returns_qr_string` — frontend receives QR string
- `test_qris_sale_not_paid_until_webhook` — sale `payment_status` stays `unpaid`
- `test_qris_success_webhook_marks_paid` — sale `payment_status` becomes `paid`
- `test_qris_failed_webhook_marks_failed` — payment failed, sale still unpaid
- `test_qris_expired_status_supported` — `EXPIRED` handled
- `test_qris_canceled_status_supported` — `CANCELED` handled
- `test_poll_endpoint_returns_current_status` — `GET` returns status

#### Refund

- `test_refund_full` — full refund updates payment and sale
- `test_refund_partial` — partial refund updates `refund_amount`
- `test_refund_exceeds_payment_rejected` — 422 if over-refund
- `test_refund_non_success_payment_rejected` — cannot refund pending/failed
- `test_refund_unauthorized_user_rejected` — permission check
- `test_refund_tenant_isolated` — cannot refund another tenant's payment

#### Settlement

- `test_settlement_sync_creates_records` — settlement sync from Xendit data
- `test_settlement_matches_payment` — `payment_settlements.payment_id` populated
- `test_reconciliation_matched` — amounts match
- `test_reconciliation_amount_mismatch` — discrepancy detected
- `test_reconciliation_missing_settlement` — internal payment, no Xendit record
- `test_reconciliation_missing_payment` — Xendit record, no internal payment

#### Cash Drawer

- `test_open_drawer` — create session
- `test_open_second_drawer_rejected` — only one open per store
- `test_close_drawer_calculates_expected` — expected + difference correct
- `test_close_drawer_records_cash_sales` — total from sales
- `test_close_drawer_records_cash_refunds` — refunds subtracted
- `test_close_drawer_unauthorized_user_rejected` — RBAC

#### Sub-account

- `test_provision_sub_account` — `PaymentGatewayAccount` created
- `test_provision_duplicate_rejected` — 422 if already provisioned
- `test_kyc_passed_webhook_updates_status` — `kyc_status: passed`
- `test_capabilities_live_webhook_activates` — status `active`

#### Security / RBAC

- `test_owner_can_provision` — Owner allowed
- `test_manager_cannot_provision` — Manager 403
- `test_cashier_can_create_charge` — Cashier allowed
- `test_staff_cannot_create_charge` — Staff 403
- `test_cannot_access_other_tenant_payment` — IDOR prevention
- `test_cannot_access_other_tenant_settlement` — IDOR prevention
- `test_webhook_fake_event_rejected` — invalid token

---

## 3. XENDIT TEST MODE & MOCKING

### 3.1 Mock Xendit HTTP Client

For unit/feature tests, use Guzzle MockHandler or Laravel HTTP Fake:

```php
Http::fake([
    'api.xendit.co/*' => Http::sequence([
        Http::response(['payment_request_id' => 'pr-test', 'status' => 'REQUIRES_ACTION', 'actions' => [['type' => 'QR_DISPLAY', 'value' => 'qr-test']]], 200),
        Http::response(['status' => 'SUCCEEDED'], 200),
    ]),
]);
```

### 3.2 Webhook Simulation

```php
$this->postJson('/api/v1/webhooks/xendit', [
    'event' => 'payment.capture',
    'business_id' => 'biz-test',
    'created' => now()->toIso8601String(),
    'data' => [
        'payment_id' => 'py-test',
        'business_id' => 'biz-test',
        'status' => 'SUCCEEDED',
        'payment_request_id' => 'pr-test',
        'request_amount' => '50000',
        'reference_id' => 'SALE-20260815-0001',
        'channel_code' => 'QRIS',
        'country' => 'ID',
        'currency' => 'IDR',
    ],
], [
    'x-callback-token' => config('payments.gateways.xendit.webhook_token'),
]);
```

### 3.3 Test Mode API Keys

- Use `XENDIT_API_KEY` and `XENDIT_WEBHOOK_TOKEN` from `.env.testing`
- For CI, use mock instead of real API calls

---

## 4. FRONTEND TESTS

### 4.1 TypeScript Compilation

```bash
cd frontend
npx tsc --noEmit
```

### 4.2 Vite Build

```bash
cd frontend
npm run build
```

### 4.3 New Types

Add to `frontend/src/types/index.ts`:

```typescript
export interface PaymentGatewayAccount {
  id: number
  gateway: string
  status: 'pending' | 'active' | 'suspended' | 'rejected'
  kyc_status: string
  gateway_account_id: string
  capabilities: string[]
  activated_at: string | null
  created_at: string
  updated_at: string
}

export interface PaymentSettlement {
  id: number
  settlement_id: string
  gross_amount: string
  platform_fee: string
  net_amount: string
  settled_at: string | null
  status: string
  payment_id: number
  created_at: string
}

export interface CashDrawerSession {
  id: number
  store_id: number
  user_id: number
  opening_amount: string
  closing_amount: string | null
  expected_amount: string | null
  difference: string | null
  status: 'open' | 'closed' | 'reconciled'
  opened_at: string
  closed_at: string | null
  notes: string | null
}
```

### 4.4 New Service Tests (if using Vitest)

- `paymentService.createCharge` calls correct endpoint
- `paymentService.refund` calls correct endpoint
- `QRISPaymentModal` polls status
- `CashDrawerPage` handles open/close

---

## 5. E2E TESTS

### 5.1 Test File

`frontend/e2e/phase5.spec.ts`

### 5.2 E2E Scenarios

#### Scenario 1: QRIS Payment Flow

```
1. Login as cashier
2. Add product to cart
3. Click checkout
4. Select "QRIS" payment method
5. Click "Create QR Code"
6. Verify QR code is displayed
7. Simulate Xendit webhook (call POST /api/v1/webhooks/xendit from test helper)
8. Verify UI shows "Payment successful"
9. Verify receipt printed / sale completed
```

#### Scenario 2: Cash Drawer Flow

```
1. Login as cashier
2. Open cash drawer with opening amount
3. Make cash sale
4. Close cash drawer with closing amount
5. Verify expected amount and difference shown
6. Login as manager
7. Reconcile drawer
```

#### Scenario 3: Payment Dashboard

```
1. Login as owner
2. Navigate to payment dashboard
3. Verify payment list
4. Verify settlement status
5. Run reconciliation
```

---

## 6. REGRESSION TESTS

### 6.1 Existing Backend Suite

```bash
docker exec pos_saas_backend php artisan test
```

Expected: **1095 passed (or current count), 0 failures**

### 6.2 Frontend

```bash
cd frontend
npx tsc --noEmit
npm run build
```

### 6.3 E2E

```bash
cd frontend
npx playwright test phase5.spec.ts
```

### 6.4 Full Phase 5 Filter

```bash
docker exec pos_saas_backend php artisan test --filter=Phase5
```

---

## 7. TEST DATA SETUP

### 7.1 Seeders

- `PaymentGatewaySeeder` — ensure `PaymentGatewayInterface` binding works
- `XenditTestDataSeeder` (testing only) — create test sub-accounts, mock webhook data

### 7.2 Factories

- `PaymentFactory` — generate payments with various statuses
- `PaymentGatewayAccountFactory` — create test gateway accounts
- `PaymentSettlementFactory` — create test settlements
- `CashDrawerSessionFactory` — create test drawer sessions

---

## 8. ACCEPTANCE CHECKLIST

- [ ] `Phase5PaymentGatewayTest` all green
- [ ] `Phase5WebhookTest` all green
- [ ] `Phase5QRISPaymentTest` all green
- [ ] `Phase5RefundTest` all green
- [ ] `Phase5SettlementTest` all green
- [ ] `Phase5CashDrawerTest` all green
- [ ] `Phase5SubAccountTest` all green
- [ ] `Phase5SecurityTest` all green
- [ ] `Phase5FinalGateTest` all green
- [ ] `php artisan test` (full suite) 0 failures
- [ ] `tsc --noEmit` 0 errors
- [ ] `vite build` successful
- [ ] E2E phase5.spec.ts all pass
- [ ] No High/Critical security defects

---

*End of Phase 5 Testing*
