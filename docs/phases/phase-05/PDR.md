# Phase 5 — Payment Infrastructure (Xendit xenPlatform) — PDR

**Document Status:** APPROVED — Phase 5 CLOSED ✅  
**Created:** 2026-08-15  
**Phase:** 5 — Payment Infrastructure  
**Depends On:** Phase 4 (POS Enhancement), Xendit xenPlatform API documentation  
**Roadmap Reference:** `docs/PDR/02-PHASE_ROADMAP.md` — Phase 5

---

## 1. OBJECTIVE

Build a **multi-tenant payment infrastructure** for the ERP platform using **Xendit xenPlatform** as the first real gateway implementation. The architecture must remain **gateway-agnostic** at the interface level so future gateways can be added without rewriting the payment system.

Phase 5 delivers:
- Xendit xenPlatform integration (tenant sub-accounts)
- QRIS payment via Xendit Payment Request API
- Payment webhook handling (idempotent, token-verified)
- Payment settlement tracking (based on Xendit transaction/report data)
- Platform fee model (configurable per tenant/plan via Xendit fee rules)
- Payment reconciliation (match Xendit reports with internal records)
- Cash payment enhancement (cash drawer management)
- Bank transfer payment (via Xendit Virtual Account)
- Card payment (via Xendit)
- Payment gateway account provisioning (onboarding flow for tenant)
- Payment dashboard (transaction history, settlement status)

---

## 2. DESIGN PRINCIPLES

### 2.1 Gateway Abstraction

The existing `PaymentGatewayInterface` remains the single abstraction point. All payment methods (cash, QRIS, card, bank_transfer, e-wallet) flow through this interface. The gateway implementation handles Xendit-specific API calls.

```
PaymentGatewayInterface (contract)
├── ManualPayment      (Phase 0 — stub for testing)
├── XenditPayment      (Phase 5 — real implementation)
└── FutureGateway      (extensible)
```

### 2.2 Payment Lifecycle — Asynchronous

Payments are **never assumed successful** at creation time. The full lifecycle is:

```
CREATED → REQUIRES_ACTION → PENDING → SUCCEEDED / FAILED / EXPIRED / CANCELED
```

- `createCharge` returns a payment request with `qr_string` or `payment_url`
- Frontend displays QR/redirect to customer
- Customer pays via their app
- Xendit sends webhook → backend verifies → updates payment status
- Sale is confirmed **only after** terminal status `SUCCEEDED`

### 2.3 xenPlatform Sub-account Model

```
Master Account (Platform — POS Restoran)
├── Tenant A Sub-account (for-user-id: xendit_user_id_a)
├── Tenant B Sub-account (for-user-id: xendit_user_id_b)
└── Tenant N Sub-account (for-user-id: xendit_user_id_n)
```

- Each tenant gets a Xendit sub-account at registration (or on-demand)
- All API calls use `for-user-id` header to route to the correct sub-account
- Platform fee deducted via Xendit `with-fee-rule` header
- Sub-account type: `OWNED` (platform controls API keys)

### 2.4 Webhook-First Confirmation

No payment is marked `success` without webhook confirmation. The flow:

1. Backend creates Payment Request via Xendit API
2. Payment record saved with status `pending`
3. Xendit sends webhook (`payment.capture` / `payment.failure`)
4. Backend verifies `x-callback-token` header
5. Backend checks idempotency (webhook event ID)
6. Backend updates payment status atomically
7. If `SUCCEEDED` → update sale payment_status → trigger downstream (inventory, loyalty, etc.)

### 2.5 Settlement — Data-Driven, Not Time-Assumed

Settlement tracking is based on **Xendit transaction/report data**, not a hardcoded T+2 assumption. Xendit provides:
- Transaction monitoring per sub-account
- Balance reports
- Settlement reports

The reconciliation service fetches settlement data from Xendit and matches with internal payment records.

---

## 3. SCOPE

### 3.1 In Scope

| Item | Description |
|------|-------------|
| XenditPayment gateway | Real implementation of `PaymentGatewayInterface` |
| Sub-account provisioning | Auto-provision Xendit sub-account per tenant |
| QRIS payment | Create Payment Request → return QR string → webhook confirm |
| Bank transfer (VA) | Xendit Virtual Account payment |
| Card payment | Xendit card payment (3DS) |
| Webhook handler | `POST /api/v1/webhooks/xendit` — public, token-verified, idempotent |
| Payment lifecycle | Full async lifecycle: pending → succeeded/failed/expired |
| Cash drawer | Open/close/reconcile cash drawer sessions per store |
| Settlement tracking | Record settlement data from Xendit reports |
| Platform fee | Configurable fee rule per tenant/plan |
| Reconciliation | Match Xendit reports with internal payments |
| Payment dashboard | Transaction history, settlement status, fee breakdown |
| Frontend QRIS modal | Show QR code, poll status, confirm payment |
| Frontend cash drawer | Open/close/reconcile UI |
| Frontend payment dashboard | Transaction list, settlement status, filters |

### 3.2 Out of Scope (Future Phases)

| Item | Reason |
|------|--------|
| E-wallet direct integration | Xendit handles via QRIS/Payment Request — no separate integration needed |
| Recurring/tokenized payments | Phase 9 (Subscription & Billing) |
| Disbursement/payout | Future phase |
| Multi-gateway routing | Architecture supports it; implementation deferred |

---

## 4. DATABASE CHANGES

### 4.1 New Tables

#### `payment_gateway_accounts`
Tracks Xendit sub-account per tenant.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| tenant_id | FK → tenants | cascadeOnDelete |
| gateway | varchar(50) | `xendit`, `manual` |
| gateway_account_id | varchar(255) | Xendit user_id for `for-user-id` header |
| status | enum | `pending`, `active`, `suspended`, `rejected` |
| kyc_status | enum | `none`, `pending`, `passed`, `resubmission_required`, `failed` |
| capabilities | json | Enabled payment channels |
| webhook_url | varchar(255) nullable | Per-sub-account webhook URL |
| metadata | json nullable | Raw Xendit response data |
| activated_at | timestamp nullable | When sub-account went live |
| timestamps | | created_at, updated_at |

**Indexes:** `(tenant_id, gateway)` unique, `gateway_account_id`

#### `payment_webhooks`
Idempotent webhook receipt log.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| tenant_id | FK → tenants | nullable — webhook may arrive before tenant resolution |
| gateway | varchar(50) | `xendit` |
| event_id | varchar(255) | Xendit webhook event ID (idempotency key) |
| event_type | varchar(100) | `payment.capture`, `payment.failure`, etc. |
| payload | json | Full webhook body |
| headers | json | Request headers (for audit) |
| verified | boolean | Whether signature/token verified |
| processed | boolean | Whether handler completed |
| processed_at | timestamp nullable | |
| error_message | text nullable | If processing failed |
| timestamps | | created_at, updated_at |

**Indexes:** `(gateway, event_id)` unique, `event_type`, `processed`

#### `payment_settlements`
Settlement records from Xendit reports.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| tenant_id | FK → tenants | cascadeOnDelete |
| payment_id | FK → payments | nullable — settlement may cover multiple payments |
| gateway | varchar(50) | `xendit` |
| settlement_id | varchar(255) | Xendit settlement ID |
| gross_amount | decimal(15,2) | Total payment amount |
| platform_fee | decimal(15,2) | Fee deducted by Xendit |
| net_amount | decimal(15,2) | Amount settled to tenant |
| settled_at | timestamp nullable | When settlement occurred |
| status | enum | `pending`, `settled`, `failed` |
| metadata | json nullable | Raw settlement data |
| timestamps | | created_at, updated_at |

**Indexes:** `(tenant_id, settlement_id)` unique, `payment_id`, `settled_at`

#### `cash_drawer_sessions`
Cash drawer management per store.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| tenant_id | FK → tenants | cascadeOnDelete |
| store_id | FK → stores | cascadeOnDelete |
| user_id | FK → users | Cashier who opened drawer |
| opening_amount | decimal(15,2) | Cash counted at open |
| closing_amount | decimal(15,2) nullable | Cash counted at close |
| expected_amount | decimal(15,2) nullable | System-calculated expected |
| difference | decimal(15,2) nullable | closing - expected |
| status | enum | `open`, `closed`, `reconciled` |
| opened_at | timestamp | |
| closed_at | timestamp nullable | |
| notes | text nullable | |
| timestamps | | created_at, updated_at |

**Indexes:** `(tenant_id, store_id, status)`, `user_id`

### 4.2 Modified Tables

#### `payments` (add gateway columns)

| Column | Type | Notes |
|--------|------|-------|
| gateway_transaction_id | varchar(255) nullable | Xendit payment_request_id / payment_id |
| gateway_status | varchar(50) nullable | `REQUIRES_ACTION`, `PENDING`, `SUCCEEDED`, `FAILED`, `EXPIRED`, `CANCELED` |
| gateway_response | json nullable | Raw Xendit response |
| settlement_amount | decimal(15,2) nullable | Net amount after fee |
| platform_fee | decimal(15,2) nullable | Fee deducted |
| net_amount | decimal(15,2) nullable | Amount to tenant |
| settled_at | timestamp nullable | Settlement date |
| expires_at | timestamp nullable | Payment request expiry |
| gateway_account_id | varchar(255) nullable | Sub-account used for this payment |

**New indexes:** `gateway_transaction_id`, `(tenant_id, gateway_status)`

#### `tenants` (add Xendit fields)

| Column | Type | Notes |
|--------|------|-------|
| xendit_user_id | varchar(255) nullable | Xendit sub-account user_id |
| xendit_fee_rule_id | varchar(255) nullable | Fee rule applied to this tenant |

---

## 5. FEATURE FLAGS

| Flag | Description | Default |
|------|-------------|---------|
| `payment.gateway_qris` | Enable QRIS payment via Xendit | Enabled |
| `payment.gateway_va` | Enable Virtual Account (bank transfer) | Enabled |
| `payment.gateway_card` | Enable card payment | Enabled |
| `payment.cash_drawer` | Enable cash drawer management | Enabled |
| `payment.settlement` | Enable settlement tracking | Enabled |
| `payment.reconciliation` | Enable reconciliation reports | Enabled |

---

## 6. PERMISSIONS (RBAC)

| Permission | Description | Roles |
|------------|-------------|-------|
| `payments.view` | View payments, transaction history | Owner, Manager, Cashier |
| `payments.manage` | Process payments, add payments | Owner, Manager, Cashier |
| `payments.refund` | Refund payments via gateway | Owner, Manager |
| `payments.reconcile` | Run reconciliation, view settlements | Owner, Manager |
| `payments.cash_drawer` | Open/close cash drawer | Owner, Manager, Cashier |
| `payments.gateway_config` | Configure gateway account | Owner |

---

## 7. API ENDPOINTS

### 7.1 Payment Gateway Account

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/payment-gateway/account` | `payments.view` | Get tenant's gateway account status |
| POST | `/payment-gateway/provision` | `payments.gateway_config` | Provision Xendit sub-account |
| GET | `/payment-gateway/settlements` | `payments.reconcile` | List settlement records |
| POST | `/payment-gateway/reconcile` | `payments.reconcile` | Run reconciliation |

### 7.2 QRIS / Gateway Payment

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| POST | `/sales/{id}/gateway-charge` | `payments.manage` | Create gateway charge (QRIS/VA/Card) |
| GET | `/sales/{id}/gateway-charge/{chargeId}` | `payments.view` | Get charge status |
| POST | `/payments/{id}/refund` | `payments.refund` | Refund a gateway payment |

### 7.3 Webhook (Public)

| Method | Route | Middleware | Description |
|--------|-------|------------|-------------|
| POST | `/webhooks/xendit` | none (public) | Receive Xendit webhook |

### 7.4 Cash Drawer

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/cash-drawer/sessions` | `payments.cash_drawer` | List sessions |
| POST | `/cash-drawer/open` | `payments.cash_drawer` | Open drawer session |
| POST | `/cash-drawer/{id}/close` | `payments.cash_drawer` | Close drawer session |
| GET | `/cash-drawer/{id}` | `payments.cash_drawer` | Get session detail |

### 7.5 Payment Dashboard

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/payments` | `payments.view` | List all payments (filtered) |
| GET | `/payments/{id}` | `payments.view` | Get payment detail |
| GET | `/payments/summary` | `payments.view` | Payment summary stats |

---

## 8. PAYMENT LIFECYCLE — DETAILED

### 8.1 QRIS Flow

```
1. POS Frontend → POST /sales/{id}/gateway-charge
   { method: "qris", amount: 50000 }
2. Backend → XenditPayment::createCharge()
   POST /payment_requests (with for-user-id header)
   { reference_id: sale_number, type: "PAY", country: "ID", currency: "IDR",
     request_amount: 50000, channel_code: "QRIS",
     payment_method: { type: "QR_CODE", qr_code: { channel_code: "QRIS" } } }
3. Xendit → returns payment_request_id, qr_string, status: "REQUIRES_ACTION"
4. Backend → save Payment with status: "pending", gateway_transaction_id, qr_string in metadata
5. Frontend → display QR code → poll GET /sales/{id}/gateway-charge/{chargeId}
6. Customer scans QR → pays via e-wallet/banking app
7. Xendit → POST /webhooks/xendit (event: "payment.capture", status: "SUCCEEDED")
8. Backend → verify x-callback-token → check idempotency → update Payment status: "success"
9. Backend → update Sale payment_status → trigger downstream (inventory, loyalty)
10. Frontend → poll detects "success" → show success screen
```

### 8.2 Cash Flow (unchanged with cash drawer)

```
1. POS Frontend → checkout with cash payment (existing flow)
2. PaymentService::createForCheckout() → status: "success" (immediate)
3. Cash drawer session tracks cash in/out
4. At end of shift → close drawer → reconcile
```

### 8.3 Refund Flow

```
1. Authorized user → POST /payments/{id}/refund
   { amount: 25000, reason: "Customer request" }
2. Backend → XenditPayment::refund(gateway_transaction_id, amount, reason)
   POST /refunds (with for-user-id header)
3. Xendit → returns refund_id, status
4. Backend → update Payment refund_amount, refund_status
5. If full refund → update Sale status: "refunded"
```

### 8.4 Settlement Flow

```
1. Scheduled job (daily) → fetch Xendit transaction/report data per sub-account
2. For each settled transaction → create/update payment_settlements record
3. Update payment.settled_at, settlement_amount, platform_fee, net_amount
4. Reconciliation report → match internal payments with Xendit settlements
5. Flag discrepancies for manual review
```

---

## 9. SECURITY CONSIDERATIONS

### 9.1 Webhook Security
- **Token verification:** Xendit sends `x-callback-token` header → compare with stored webhook token
- **Idempotency:** `payment_webhooks.event_id` unique constraint prevents duplicate processing
- **No auth middleware:** Webhook endpoint is public but verified via token
- **Payload logging:** All webhooks logged in `payment_webhooks` for audit trail

### 9.2 API Key Security
- Xendit API key stored in `.env` (never in DB)
- `for-user-id` per tenant stored in `tenants.xendit_user_id`
- Fee rule ID stored in `tenants.xendit_fee_rule_id`
- API key never exposed to frontend

### 9.3 Tenant Isolation
- `payment_gateway_accounts` uses `BelongsToTenant` trait
- `payment_settlements` uses `BelongsToTenant` trait
- `cash_drawer_sessions` uses `BelongsToTenant` trait
- All payment queries scoped by `tenant_id`
- `for-user-id` ensures Xendit-level isolation

### 9.4 Payment Integrity
- All payment status updates wrapped in DB transactions
- Sale status update + payment status update atomic
- Concurrent webhook processing prevented via row locking
- Gateway transaction ID unique per tenant

---

## 10. ACCEPTANCE CRITERIA

- [x] Xendit sub-account provisioning works (provision → KYC → active)
- [x] QRIS payment: create charge → customer pays → webhook → confirm
- [x] Bank transfer (VA): create VA → customer pays → webhook → confirm
- [x] Card payment: create charge → 3DS → webhook → confirm
- [x] Webhook idempotency (duplicate webhooks ignored)
- [x] Webhook token verification (`x-callback-token`)
- [x] Payment lifecycle: pending → succeeded/failed/expired correctly tracked
- [x] Settlement tracking from Xendit report data
- [x] Platform fee deducted and recorded
- [x] Reconciliation report matches internal payments with Xendit settlements
- [x] Cash drawer session (open/close/reconcile)
- [x] Payment dashboard (transaction history, settlement status, fee breakdown)
- [x] Refund via Xendit API (full and partial)
- [x] All existing payment tests pass (regression)
- [x] New tests for gateway, webhook, settlement, reconciliation, cash drawer
- [x] E2E test: QRIS checkout → webhook → confirmed
- [x] E2E test: cash drawer open → sale → close → reconcile

---

## 11. IMPLEMENTATION ORDER

1. Database migrations (4 new tables + 2 modified)
2. Models (PaymentGatewayAccount, PaymentWebhook, PaymentSettlement, CashDrawerSession)
3. XenditPayment gateway implementation (HTTP client for Xendit API)
4. PaymentServiceProvider update (bind XenditPayment when configured)
5. Webhook controller + signature verification + idempotency
6. PaymentService enhancement (async lifecycle, gateway integration)
7. Sub-account provisioning service
8. Settlement service (scheduled job + reconciliation)
9. Cash drawer service + controller
10. Payment dashboard controller
11. Frontend: QRIS payment modal, payment dashboard, cash drawer UI
12. Tests: backend feature tests, E2E tests
13. Documentation: ARCHITECTURE, FLOW, API, SECURITY, TESTING

---

## 12. DEPENDENCIES

- **Phase 4** (POS Enhancement) — Sale/Payment models, checkout flow
- **Xendit xenPlatform** — Master account + sub-account API access
- **Xendit API keys** — Test mode keys for development/testing
- **Xendit Test Mode** — Simulate sub-account creation, payment, webhook

---

## 13. RISKS & MITIGATIONS

| Risk | Mitigation |
|------|------------|
| Xendit API changes | Gateway abstraction layer; versioned API calls |
| Webhook delivery failures | Idempotent processing; manual reconciliation fallback |
| KYC delays for sub-accounts | Test mode for development; async provisioning flow |
| Settlement timing variability | Data-driven settlement tracking, not time-assumed |
| Concurrent webhook + poll | Row locking on payment updates; idempotency keys |
| API key compromise | .env only; never logged; rotate via Xendit dashboard |

---

*End of Phase 5 PDR*
