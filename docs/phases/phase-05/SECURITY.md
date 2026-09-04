# Phase 5 — Payment Infrastructure — Security

**Document Status:** DRAFT  
**Created:** 2026-08-15  
**Depends On:** `PDR.md`, `ARCHITECTURE.md`, `API.md`, Phase 4 security model

---

## 1. THREAT MODEL

| Threat | Risk | Mitigation |
|--------|------|------------|
| **Fake webhooks** | Attacker sends fake Xendit webhooks to confirm payments | `x-callback-token` verification, idempotency, signature/IP validation |
| **Duplicate webhooks** | Xendit resends webhooks, causing double payment confirmation | `payment_webhooks.event_id` unique constraint |
| **Webhook replay** | Attacker replays old webhook | Event ID idempotency + timestamp window validation |
| **API key leakage** | Xendit API key exposed in frontend/logs | Server-side only; never logged; `.env` storage |
| **Tenant data leak** | One tenant sees another's payments | `BelongsToTenant` + `for-user-id` per sub-account |
| **Over-refund** | Refund amount exceeds original payment | Backend validation before gateway call |
| **Race condition** | Concurrent webhook + poll update payment | Row locking in DB transaction |
| **Mass assignment** | User submits `tenant_id` or `gateway_account_id` | Ignore request-level tenant IDs; derive from auth context |
| **Tampered checkout** | User modifies payment status client-side | Backend authoritative; frontend never confirms payment |
| **Cash drawer fraud** | Cashier manipulates closing amount | Manager reconciliation, expected amount calculation |

---

## 2. AUTHENTICATION & AUTHORIZATION

### 2.1 Webhook Endpoint

`POST /api/v1/webhooks/xendit` is **public** (no `auth:sanctum`) by design. Authentication is performed via:

- **Token-based verification:** `x-callback-token` header compared with `config('payments.gateways.xendit.webhook_token')`
- **Idempotency:** `event_id` prevents duplicate processing
- **Payload logging:** All webhooks logged for audit

### 2.2 Protected Endpoints

All other Phase 5 endpoints require:

- `auth:sanctum`
- `throttle:api` rate limiting
- RBAC permission (e.g., `payments.manage`, `payments.refund`)

### 2.3 RBAC Matrix

| Endpoint | Owner | Manager | Cashier | Staff |
|----------|-------|---------|---------|-------|
| `GET /payment-gateway/account` | ✅ | ✅ | ✅ | ❌ |
| `POST /payment-gateway/provision` | ✅ | ❌ | ❌ | ❌ |
| `GET /payment-gateway/settlements` | ✅ | ✅ | ❌ | ❌ |
| `POST /payment-gateway/reconcile` | ✅ | ✅ | ❌ | ❌ |
| `POST /sales/{id}/gateway-charge` | ✅ | ✅ | ✅ | ❌ |
| `GET /sales/{id}/gateway-charge/{chargeId}` | ✅ | ✅ | ✅ | ❌ |
| `POST /payments/{id}/refund` | ✅ | ✅ | ❌ | ❌ |
| `GET /payments` | ✅ | ✅ | ✅ | ❌ |
| `GET /payments/{id}` | ✅ | ✅ | ✅ | ❌ |
| `GET /payments/summary` | ✅ | ✅ | ✅ | ❌ |
| `GET /cash-drawer/sessions` | ✅ | ✅ | ✅ | ❌ |
| `POST /cash-drawer/open` | ✅ | ✅ | ✅ | ❌ |
| `POST /cash-drawer/{id}/close` | ✅ | ✅ | ✅ | ❌ |
| `GET /cash-drawer/{id}` | ✅ | ✅ | ✅ | ❌ |

---

## 3. WEBHOOK SECURITY

### 3.1 Token Verification

```php
public function handle(Request $request)
{
    $token = $request->header('x-callback-token');
    $expected = config('payments.gateways.xendit.webhook_token');

    if (empty($token) || !hash_equals($expected, $token)) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // ... process webhook
}
```

### 3.2 Idempotency

```php
$eventId = $payload['business_id'] . ':' . ($payload['data']['payment_id'] ?? '') . ':' . $payload['event'];

$exists = PaymentWebhook::where('gateway', 'xendit')
    ->where('event_id', $eventId)
    ->exists();

if ($exists) {
    return response()->json(['message' => 'OK']); // already processed
}
```

### 3.3 Payload Integrity

- Store full payload and headers in `payment_webhooks` for audit
- Process asynchronously or with DB lock to avoid race conditions
- Mark `verified` and `processed` status after handling

### 3.4 Time Window Validation (optional hardening)

```php
$created = Carbon::parse($payload['created']);
if ($created->diffInMinutes(now()) > 15) {
    // webhook older than 15 minutes — possible replay
    return response()->json(['message' => 'Expired'], 422);
}
```

---

## 4. API KEY & SECRET MANAGEMENT

### 4.1 Storage

- `XENDIT_API_KEY` in `.env` only
- `XENDIT_WEBHOOK_TOKEN` in `.env` only
- Never in frontend, never in DB, never in logs

### 4.2 Transmission

- Xendit API key sent as HTTP Basic Auth over HTTPS
- `for-user-id` header per tenant — no cross-tenant leakage
- `with-fee-rule` header per tenant — fee rule ID stored server-side in `tenants.xendit_fee_rule_id`

### 4.3 Rotation

- API key rotation requires update in `.env` and container restart
- No hot-swap planned for Phase 5

---

## 5. TENANT ISOLATION

### 5.1 Internal Isolation

| Model | Tenant Scope | Notes |
|-------|--------------|-------|
| `Payment` | ✅ `BelongsToTenant` | Payment records isolated by `tenant_id` |
| `PaymentGatewayAccount` | ✅ `BelongsToTenant` | One active account per tenant |
| `PaymentSettlement` | ✅ `BelongsToTenant` | Settlement records isolated |
| `CashDrawerSession` | ✅ `BelongsToTenant` | Drawer sessions scoped to tenant + store |
| `PaymentWebhook` | ❌ no tenant scope | Tenant resolved from payload data during processing |

### 5.2 Xendit-Level Isolation

- Each tenant has a separate `for-user-id` (sub-account user_id)
- All payment requests, refunds, and settlements include `for-user-id`
- Sub-accounts are `OWNED` type — platform controls API keys

### 5.3 Mass Assignment Prevention

- `Payment` model: `tenant_id` not in `$fillable`
- `PaymentGatewayAccount` model: `tenant_id` not in `$fillable`
- `CashDrawerSession` model: `tenant_id` not in `$fillable`
- All tenant IDs set via `BelongsToTenant` trait or auth context

---

## 6. PAYMENT INTEGRITY

### 6.1 Atomic Updates

All payment status updates are wrapped in `DB::transaction`:

```php
DB::transaction(function () use ($payment, $webhook) {
    $payment = Payment::lockForUpdate()->find($payment->id);
    $payment->status = 'success';
    $payment->gateway_status = 'SUCCEEDED';
    $payment->save();

    $sale = Sale::lockForUpdate()->find($payment->sale_id);
    $sale->paid_amount += $payment->amount;
    if ($sale->paid_amount >= $sale->total) {
        $sale->payment_status = 'paid';
    } else {
        $sale->payment_status = 'partial';
    }
    $sale->save();
});
```

### 6.2 Idempotency in Gateway Charge Creation

- `idempotency_key` provided in checkout / createCharge
- Unique constraint: `(tenant_id, idempotency_key)`
- Xendit `Idempotency-Key` header derived from internal reference

### 6.3 Refund Validation

```php
if ($amount > $payment->amount - $payment->refund_amount) {
    throw new DomainException('Refund amount exceeds payment amount');
}

if ($payment->status !== 'success') {
    throw new DomainException('Can only refund successful payments');
}
```

---

## 7. CASH DRAWER SECURITY

### 7.1 Single Open Session

Only one `open` session per store per tenant:

```php
$existing = CashDrawerSession::where('tenant_id', $tenantId)
    ->where('store_id', $data['store_id'])
    ->where('status', 'open')
    ->exists();

if ($existing) {
    throw new DomainException('Another session is already open');
}
```

### 7.2 Expected Amount Calculation

`expected_amount` calculated from system records, not user input:

```php
$expected = $session->opening_amount
    + $session->cash_sales_total
    - $session->cash_refunds_total;
```

### 7.3 Manager Approval

Closing session does not auto-approve; manager reconciles separately.

---

## 8. AUDIT & LOGGING

### 8.1 Payment Audit Trail

| Action | Logged In |
|--------|-----------|
| Gateway charge created | `payments` + `audit_logs` |
| Webhook received | `payment_webhooks` |
| Payment status updated | `audit_logs` |
| Refund created | `payments` + `audit_logs` |
| Settlement recorded | `payment_settlements` + `audit_logs` |
| Cash drawer open/close | `cash_drawer_sessions` + `audit_logs` |

### 8.2 Sensitive Data Handling

- Card PAN/CVN never stored; passed directly to Xendit tokenization
- QR strings stored in `metadata` (non-sensitive, transient)
- API responses logged as `gateway_response` but no API keys

---

## 9. COMPLIANCE NOTES

### 9.1 Indonesia PDP Law

- All payment data encrypted at rest (database)
- Webhook payloads retained for audit only as needed
- No customer card data stored on platform

### 9.2 PCI-DSS Scope

- PCI-DSS scope minimized by using Xendit for card processing
- Server does not store, process, or transmit raw card data
- Card details collected via Xendit-hosted forms or iFrame (Phase 5 UI may be redirect-based)

---

*End of Phase 5 Security*
