# Phase 5 — Payment Infrastructure — Flow

**Document Status:** DRAFT  
**Created:** 2026-08-15  
**Depends On:** `PDR.md`, `ARCHITECTURE.md`

---

## 1. QRIS PAYMENT FLOW

### 1.1 Sequence Diagram

```
Customer          POS Frontend          Backend            Xendit
   │                   │                  │                  │
   │ ──select items──► │                  │                  │
   │                   │ ──checkout─────► │                  │
   │                   │                  │ ──create charge──►│
   │                   │                  │ ◄─charge created──│
   │                   │ ◄─qr_string─────│                  │
   │ ◄─display QR───── │                  │                  │
   │                   │                  │                  │
   │ ──scan & pay───── │                  │                  │
   │      via app      │                  │                  │
   │                   │                  │ ◄─webhook─────────│
   │                   │                  │ ──ack────────────►│
   │                   │ ◄─poll success───│                  │
   │ ◄─success screen─ │                  │                  │
```

### 1.2 State Diagram — QRIS Payment

```
┌──────────┐    create charge    ┌───────────────┐
│  START   │────────────────────▶│   CREATED     │
└──────────┘                     └───────┬───────┘
                                         │
            ┌────────────────────────────┤
            │ Xendit returns             │ REQUIRES_ACTION
            │ status                     ▼
            │                    ┌───────────────┐
            │                    │ REQUIRES      │
            │                    │   ACTION      │
            │                    └───────┬───────┘
            │                            │ customer scans QR
            │                            │ and pays
            │                            ▼
            │                    ┌───────────────┐
            │                    │    PENDING    │
            │                    └───────┬───────┘
            │                            │ webhook received
            │                            │
            ├────────────────────────────┤
            │                            ▼
            │                    ┌───────────────┐
            │                    │   SUCCEEDED   │──▶ sale paid
            │                    └───────┬───────┘
            │                            │
            │              ┌─────────────┼─────────────┐
            │              │             │             │
            │              ▼             ▼             ▼
            │       ┌──────────┐  ┌──────────┐  ┌──────────┐
            │       │  FAILED  │  │ CANCELED │  │ EXPIRED  │
            │       └────┬─────┘  └────┬─────┘  └────┬─────┘
            │            │             │             │
            └────────────┴─────────────┴─────────────┘
                         terminal states
```

### 1.3 Step-by-Step Flow

1. **Customer checkout**
   - POS frontend: `POST /api/v1/sales/checkout`
   - Body includes `payments: [{ payment_method: "qris", amount: 50000 }]`

2. **Backend validates**
   - `SaleController::checkout()` validates items, customer, store
   - `SaleService::checkout()` creates `Sale` with status `completed` but `payment_status` stays `unpaid` for pending gateway payments
   - For non-cash methods, sale may be created with `payment_status: unpaid` or held as `pending` depending on configuration

3. **Create gateway charge**
   - `PaymentGatewayController::createCharge()` or `PaymentService` delegates to `XenditPayment::createCharge()`
   - HTTP: `POST {base_url}/payment_requests`
   - Headers: `Authorization` (Basic), `for-user-id`, `Idempotency-Key`, `api-version`
   - Optional: `with-fee-rule` (platform fee)

4. **Xendit response**
   - `payment_request_id` (gateway_transaction_id)
   - `status: REQUIRES_ACTION`
   - `qr_string` (in `actions[].value` where `type: QR_DISPLAY`)

5. **Save internal payment**
   - Payment record: `payment_method: "qris"`, `status: "pending"`, `gateway_status: "REQUIRES_ACTION"`
   - `gateway_transaction_id`, `gateway_response` stored
   - `metadata.qr_string` available

6. **Frontend displays QR**
   - `QRISPaymentModal` renders QR code
   - Poll every 3s: `GET /api/v1/sales/{id}/gateway-charge/{chargeId}`

7. **Customer scans and pays**
   - Customer uses e-wallet / banking app to scan QR
   - Xendit processes payment

8. **Xendit sends webhook**
   - `POST /api/v1/webhooks/xendit`
   - Event: `payment.capture` with `status: SUCCEEDED`
   - Headers include `x-callback-token`

9. **Backend verifies and updates**
   - `XenditWebhookController` checks `x-callback-token`
   - Stores webhook with `event_id` for idempotency
   - `WebhookProcessor` updates Payment to `success`, `gateway_status: SUCCEEDED`
   - Updates Sale `payment_status: paid` (or `partial` if multiple payments)
   - Triggers inventory / loyalty / accounting downstream

10. **Frontend detects success**
    - Poll returns `status: success`
    - POS shows success screen
    - Receipt printed

### 1.4 Error Scenarios

| Scenario | Backend Response | Frontend Behavior |
|----------|------------------|-------------------|
| Xendit API down | 500 + error message | Show "Gateway unavailable. Try cash?" |
| Payment expires | Webhook `EXPIRED` | Show "QR expired. Generate new QR?" |
| Customer cancels | Webhook `CANCELED` | Show "Payment canceled" |
| Payment fails | Webhook `FAILED` | Show "Payment failed. Try again?" |
| Duplicate webhook | Idempotent 200 | No duplicate processing |
| Invalid webhook token | 401 | Webhook ignored |

---

## 2. CASH PAYMENT FLOW

### 2.1 State Diagram

```
┌──────────┐
│  START   │
└────┬─────┘
     │ checkout with cash
     ▼
┌──────────┐
│  SUCCESS │──▶ sale paid
└────┬─────┘
     │
     │ sale cancel
     ▼
┌──────────┐
│ REFUNDED │
└──────────┘
```

### 2.2 Step-by-Step Flow

1. **Customer checkout**
   - POS frontend: `POST /api/v1/sales/checkout`
   - Body: `payments: [{ payment_method: "cash", amount: 100000 }]`

2. **Backend validates and creates**
   - `SaleService::checkout()` creates `Sale` + `Payment`
   - `PaymentService::createForCheckout()` calls `ManualPayment` for cash
   - `Payment`: `status: "success"`
   - `Sale`: `payment_status: "paid"`, `change_amount: 35000` (if total 65000)

3. **Cash drawer tracking**
   - `CashDrawerService::recordCashPayment()` adds amount to active session

4. **End of shift**
   - Cashier: `POST /api/v1/cash-drawer/{id}/close` with `closing_amount`
   - Backend calculates `expected_amount` and `difference`

5. **Manager reconciliation**
   - Manager reviews `GET /api/v1/cash-drawer/{id}`
   - Approves or rejects variance

---

## 3. SUB-ACCOUNT PROVISIONING FLOW

### 3.1 Sequence Diagram

```
Owner          TenantController/Onboarding    SubAccountService    Xendit
  │                       │                        │                │
  │ ──register tenant───► │                        │                │
  │                       │ ──provision───────────►│                │
  │                       │                        │ ──POST /v2/───▶│
  │                       │                        │    accounts    │
  │                       │                        │ ◄─response─────│
  │                       │ ◄─account─────────────│                │
  │ ◄─success─────────────│                        │                │
  │                       │                        │                │
  │                       │                        │ ◄─webhook──────│
  │                       │                        │ ──ack─────────▶│
  │                       │ ◄─activated───────────│                │
```

### 3.2 State Diagram

```
┌──────────┐
│ PENDING  │── provision request
└────┬─────┘
     │
     ▼
┌──────────┐
│  ACTIVE  │── KYC passed, capabilities live
└────┬─────┘
     │
     ▼
┌──────────┐     ┌──────────┐
│ SUSPENDED│ or  │ REJECTED │
└──────────┘     └──────────┘
```

### 3.3 Step-by-Step Flow

1. **Tenant registration / owner requests payment setup**
   - Admin UI: `POST /api/v1/payment-gateway/provision`

2. **SubAccountService provisions**
   - `POST {base_url}/v2/accounts`
   - Body: `{ type: "OWNED", public_profile: { business_name, ... } }`

3. **Xendit response**
   - `id` (user_id for `for-user-id`)
   - `status: PENDING`

4. **Store gateway account**
   - `PaymentGatewayAccount`: `gateway_account_id`, `status: pending`, `kyc_status: none`
   - Update `tenants.xendit_user_id`

5. **KYC / capability webhooks**
   - `account_holder.kyc.status:passed`
   - `account_holder.capabilities.status:live`

6. **Activate**
   - Update `PaymentGatewayAccount.status: active`
   - Enable gateway payments for tenant

---

## 4. REFUND FLOW

### 4.1 Sequence Diagram

```
Manager          Backend              Xendit
  │ ──refund─────▶│                   │
  │               │ ──POST /refunds──▶│
  │               │ ◄─refund created──│
  │               │                   │
  │               │ ◄─webhook─────────│
  │               │ ──ack────────────▶│
  │ ◄─success─────│                   │
```

### 4.2 State Diagram

```
┌──────────┐
│  PAID    │
└────┬─────┘
     │ refund request
     ▼
┌──────────┐
│ REFUND   │── partial or full
│ PENDING  │
└────┬─────┘
     │ webhook
     ▼
┌──────────┐
│  NONE    │── refund rejected
└──────────┘     (no change)

     or

┌──────────┐
│ PARTIAL  │── for partial refund
└──────────┘

     or

┌──────────┐
│  FULL    │── for full refund
└──────────┘
```

### 4.3 Step-by-Step Flow

1. **Manager initiates refund**
   - Frontend: `POST /api/v1/payments/{id}/refund`
   - Body: `{ amount: 25000, reason: "Customer request" }`

2. **Backend validates**
   - User has `payments.refund` permission
   - Payment status is `success`
   - Refund amount ≤ payment amount

3. **Call gateway refund**
   - `XenditPayment::refund(gateway_transaction_id, amount, reason)`
   - HTTP: `POST {base_url}/refunds`
   - Headers: `Authorization`, `for-user-id`, `Idempotency-Key`, `api-version`

4. **Xendit response**
   - `refund_id`, `status`

5. **Update internal payment**
   - `refund_amount` += amount
   - `refund_status` = `partial` or `full`
   - If full → `Payment.status: refunded`

6. **Update sale**
   - `Sale.refund_status: partial` or `full`
   - `Sale.refunded_amount` updated
   - If full refund → `Sale.status: refunded`

7. **Webhook confirmation**
   - Xendit sends refund webhook
   - Backend verifies and finalizes

---

## 5. SETTLEMENT & RECONCILIATION FLOW

### 5.1 Sequence Diagram

```
Scheduler       SettlementService      Xendit        Database
    │                   │                │              │
    │ daily             │                │              │
    │ ──sync───────────▶│                │              │
    │                   │ ──GET txns───▶│              │
    │                   │ ◄─report───────│              │
    │                   │                │              │
    │                   │ ──match──────────────────────▶│
    │                   │                │              │
    │                   │ ◄─updated──────│              │
    │                   │                │              │
    │                   │ ──reconcile──────────────────▶│
    │                   │                │              │
    │ ◄─done────────────│                │              │
```

### 5.2 Step-by-Step Flow

1. **Scheduled sync**
   - Cron/scheduler calls `php artisan settlements:sync`
   - For each active `PaymentGatewayAccount` with `gateway: xendit`

2. **Fetch Xendit data**
   - `GET {base_url}/transactions` or report endpoint
   - Headers: `Authorization`, `for-user-id`
   - Filter by date range

3. **Match transactions**
   - For each Xendit transaction:
     - Find internal `Payment` by `gateway_transaction_id`
     - If matched and settled → create/update `PaymentSettlement`
     - Update `Payment.settled_at`, `settlement_amount`, `platform_fee`, `net_amount`

4. **Reconcile**
   - Compare totals: internal vs Xendit
   - Identify: matched, amount mismatch, missing settlement, missing payment
   - Generate report

5. **Dashboard display**
   - `GET /api/v1/payment-gateway/reconcile`
   - Shows reconciliation status per period

---

## 6. CASH DRAWER FLOW

### 6.1 State Diagram

```
┌──────────┐
│  OPEN    │── cashier opens shift
└────┬─────┘
     │ record cash sales
     │ record cash refunds
     ▼
┌──────────┐
│  CLOSED  │── cashier counts closing cash
└────┬─────┘
     │
     ▼
┌──────────┐
RECONCILED │── manager approves
└──────────┘
```

### 6.2 Step-by-Step Flow

1. **Open drawer**
   - `POST /api/v1/cash-drawer/open`
   - `CashDrawerService::open()` creates session
   - Required: `store_id`, `opening_amount`

2. **Process sales**
   - Every cash sale updates `CashDrawerSession.running_total`
   - Every cash refund decrements running total

3. **Close drawer**
   - `POST /api/v1/cash-drawer/{id}/close`
   - Cashier provides `closing_amount`
   - Backend calculates:
     - `expected_amount = opening_amount + cash_sales - cash_refunds`
     - `difference = closing_amount - expected_amount`
   - Status becomes `closed`

4. **Reconcile**
   - Manager reviews `GET /api/v1/cash-drawer/{id}`
   - If approved: `POST /api/v1/cash-drawer/{id}/reconcile` → status `reconciled`

---

## 7. WEBHOOK PROCESSING FLOW

### 7.1 Sequence Diagram

```
Xendit          XenditWebhookController     WebhookProcessor    Payment
  │ ──POST─────────▶│                         │                   │
  │                 │ ──verify token─────────│                   │
  │                 │                         │                   │
  │                 │ ──check idempotency────│                   │
  │                 │                         │                   │
  │                 │ ──store webhook────────│                   │
  │                 │                         │                   │
  │                 │ ──process─────────────▶│                   │
  │                 │                         │ ──find payment───▶│
  │                 │                         │ ◄─payment─────────│
  │                 │                         │                   │
  │                 │                         │ ──lock + update──▶│
  │                 │                         │ ◄─updated─────────│
  │                 │                         │                   │
  │                 │                         │ ──update sale────▶│
  │                 │ ◄─done─────────────────│                   │
  │ ◄─200 OK────────│                         │                   │
```

### 7.2 Decision Tree

```
Receive webhook
    │
    ├── Token invalid? ──▶ 401 Unauthorized
    │
    ├── event_id exists? ──▶ 200 OK (idempotent)
    │
    ├── event_type = "payment.capture"
    │   ├── status = "SUCCEEDED" ──▶ update payment success → update sale paid
    │   ├── status = "FAILED" ──▶ update payment failed
    │   └── other ──▶ log, no action
    │
    ├── event_type = "payment.failure"
    │   └── update payment failed
    │
    ├── event_type = "payment.authorization"
    │   └── update payment gateway_status = AUTHORIZED
    │
    ├── event_type = "account_holder.kyc.status:passed"
    │   └── update kyc_status = passed
    │
    ├── event_type = "account_holder.capabilities.status:live"
    │   └── update status = active
    │
    └── Other ──▶ store for audit, mark processed
```

---

*End of Phase 5 Flow*
