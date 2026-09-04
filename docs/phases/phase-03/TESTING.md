# Phase 3 — CRM & Purchasing Enhancement — Testing

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-13  
**Phase:** 3 — CRM & Purchasing Enhancement  
**Depends On:** Phase 2 (Inventory Enhancement — CLOSED)

---

## 1. Backend Test Files

| Test File | Coverage |
|-----------|----------|
| `Phase3LoyaltyTest.php` | Points accrual, redemption, expiry, manual adjust, feature flag |
| `Phase3CustomerCreditTest.php` | Credit limit check, debit/credit, manual adjust, feature flag |
| `Phase3SupplierRatingTest.php` | Rating CRUD, average calculation, tenant isolation |
| `Phase3RequisitionTest.php` | Full workflow, SoD, convert to PO, feature flag, tenant isolation |
| `Phase3GrnTest.php` | Create from PO, standalone, receive, partial receipt, cancel, batch/expiry |
| `Phase3InvoiceMatchingTest.php` | 3-way match, tolerance, approve/reject, feature flag |
| `Phase3AutoReorderTest.php` | Low-stock report, generate requisition, edge cases |
| `Phase3MigrationTest.php` | All new migrations, columns, constraints, FKs |

---

## 2. Test Cases

### 2.1 Phase3LoyaltyTest

| # | Test | Description |
|---|------|-------------|
| 1 | `test_earn_points_on_sale` | Points accrue correctly based on earn_rate |
| 2 | `test_redeem_points` | Points redeem and balance updates correctly |
| 3 | `test_cannot_redeem_more_than_balance` | Error when redeeming > balance |
| 4 | `test_manual_adjust_points` | Manager can adjust points manually |
| 5 | `test_points_expiry_sweep` | Expired points deducted correctly |
| 6 | `test_no_expiry_when_disabled` | No expiry when setting is null |
| 7 | `test_loyalty_disabled_without_feature` | 403 when feature disabled |
| 8 | `test_cashier_cannot_adjust_points` | 403 for cashier role |
| 9 | `test_cross_tenant_loyalty_access` | 404 for other tenant's customer |
| 10 | `test_loyalty_transaction_log` | Transactions recorded with balance_after |
| 11 | `test_audit_log_for_loyalty_actions` | Audit entries created |

### 2.2 Phase3CustomerCreditTest

| # | Test | Description |
|---|------|-------------|
| 1 | `test_credit_limit_blocks_sale` | Sale blocked when exceeding limit |
| 2 | `test_credit_limit_allows_within_limit` | Sale allowed within limit |
| 3 | `test_no_limit_allows_any_amount` | Null credit_limit = no restriction |
| 4 | `test_add_debit_on_credit_sale` | Outstanding balance increases |
| 5 | `test_add_credit_on_payment` | Outstanding balance decreases |
| 6 | `test_manual_credit_adjust` | Manager can adjust balance |
| 7 | `test_credit_disabled_without_feature` | 403 when feature disabled |
| 8 | `test_credit_transaction_log` | Transactions recorded with balance_after |
| 9 | `test_cross_tenant_credit_access` | 404 for other tenant's customer |
| 10 | `test_audit_log_for_credit_actions` | Audit entries created |

### 2.3 Phase3SupplierRatingTest

| # | Test | Description |
|---|------|-------------|
| 1 | `test_create_rating` | Create rating for supplier |
| 2 | `test_update_rating` | Update existing rating |
| 3 | `test_delete_rating` | Delete rating |
| 4 | `test_average_rating_calculation` | Average correctly computed |
| 5 | `test_list_ratings_by_supplier` | Filter ratings by supplier |
| 6 | `test_cross_tenant_rating_access` | 404 for other tenant's supplier |
| 7 | `test_staff_cannot_rate_supplier` | 403 for staff role |
| 8 | `test_audit_log_for_rating_actions` | Audit entries created |

### 2.4 Phase3RequisitionTest

| # | Test | Description |
|---|------|-------------|
| 1 | `test_create_requisition` | Create with items |
| 2 | `test_submit_requisition` | Draft → pending |
| 3 | `test_approve_requisition` | Pending → approved |
| 4 | `test_reject_requisition` | Pending → rejected |
| 5 | `test_cancel_requisition` | Draft/pending → cancelled |
| 6 | `test_cannot_approve_own_requisition` | SoD enforcement |
| 7 | `test_convert_to_po` | Approved → PO created with correct items |
| 8 | `test_cannot_convert_non_approved` | Error when not approved |
| 9 | `test_requisition_disabled_without_feature` | 403 when feature disabled |
| 10 | `test_cross_tenant_requisition_access` | 404 for other tenant |
| 11 | `test_cannot_submit_non_draft` | Error when not draft |
| 12 | `test_cannot_delete_non_draft` | Error when not draft |
| 13 | `test_audit_log_for_requisition_actions` | Audit entries for all transitions |

### 2.5 Phase3GrnTest

| # | Test | Description |
|---|------|-------------|
| 1 | `test_create_grn_from_po` | GRN created with PO items pre-filled |
| 2 | `test_create_standalone_grn` | GRN without PO link |
| 3 | `test_receive_grn_full` | Full receipt increases inventory |
| 4 | `test_receive_grn_partial` | Partial receipt (received < ordered) |
| 5 | `test_receive_grn_with_rejection` | Rejection recorded, rejected qty not stocked |
| 6 | `test_receive_grn_with_batch` | Batch/expiry info captured |
| 7 | `test_cancel_draft_grn` | Draft GRN cancelled |
| 8 | `test_cannot_receive_cancelled_grn` | Error on cancelled GRN |
| 9 | `test_cannot_receive_already_received` | Error on received GRN |
| 10 | `test_po_status_updated_on_receive` | Linked PO status → received |
| 11 | `test_cross_tenant_grn_access` | 404 for other tenant |
| 12 | `test_audit_log_for_grn_actions` | Audit entries created |

### 2.6 Phase3InvoiceMatchingTest

| # | Test | Description |
|---|------|-------------|
| 1 | `test_create_invoice` | Create supplier invoice |
| 2 | `test_3way_match_success` | All quantities/prices match → matched |
| 3 | `test_3way_match_quantity_mismatch` | Quantity variance > tolerance → mismatched |
| 4 | `test_3way_match_price_mismatch` | Price variance > tolerance → mismatched |
| 5 | `test_3way_match_within_tolerance` | Small variance within tolerance → matched |
| 6 | `test_approve_invoice` | Matched → approved |
| 7 | `test_reject_invoice` | Matched/mismatched → rejected |
| 8 | `test_cannot_approve_pending_invoice` | Must match first |
| 9 | `test_invoice_matching_disabled_without_feature` | 403 when feature disabled |
| 10 | `test_cross_tenant_invoice_access` | 404 for other tenant |
| 11 | `test_audit_log_for_invoice_actions` | Audit entries created |

### 2.7 Phase3AutoReorderTest

| # | Test | Description |
|---|------|-------------|
| 1 | `test_low_stock_report` | Products at/below minimum listed |
| 2 | `test_suggested_qty_with_maximum` | Suggested = max - current |
| 3 | `test_suggested_qty_without_maximum` | Suggested = min * 2 |
| 4 | `test_generate_requisition_from_report` | Requisition created with suggested quantities |
| 5 | `test_empty_report_when_no_low_stock` | Empty result when all stock above minimum |
| 6 | `test_cross_tenant_auto_reorder` | Only shows own tenant products |

### 2.8 Phase3MigrationTest

| # | Test | Description |
|---|------|-------------|
| 1 | `test_customers_table_has_new_columns` | credit_limit, outstanding_balance, price_list_id |
| 2 | `test_purchases_table_has_new_columns` | requisition_id, grn_id |
| 3 | `test_customer_loyalty_points_table_exists` | Table with correct columns and constraints |
| 4 | `test_customer_loyalty_transactions_table_exists` | Table with correct columns and indexes |
| 5 | `test_customer_credit_transactions_table_exists` | Table with correct columns and indexes |
| 6 | `test_supplier_ratings_table_exists` | Table with correct columns and indexes |
| 7 | `test_purchase_requisitions_table_exists` | Table with correct columns and constraints |
| 8 | `test_purchase_requisition_items_table_exists` | Table with correct columns and constraints |
| 9 | `test_goods_receipt_notes_table_exists` | Table with correct columns and constraints |
| 10 | `test_grn_items_table_exists` | Table with correct columns and constraints |
| 11 | `test_supplier_invoices_table_exists` | Table with correct columns and constraints |
| 12 | `test_all_new_tables_have_tenant_id` | All Phase 3 tables have tenant_id FK |

---

## 3. E2E Test Plan

File: `frontend/e2e/phase3.spec.ts`

| # | Test | Description |
|---|------|-------------|
| 1 | `customer detail shows loyalty points` | Navigate to customer, verify loyalty section |
| 2 | `customer credit balance visible` | Navigate to customer, verify credit section |
| 3 | `supplier rating display` | Navigate to supplier, verify rating display |
| 4 | `requisition list visible` | Navigate to requisitions page |
| 5 | `create requisition` | Fill form and submit, verify creation |
| 6 | `requisition workflow` | Submit → approve → convert to PO |
| 7 | `grn list visible` | Navigate to GRN page |
| 8 | `create grn from po` | Create GRN from existing PO |
| 9 | `receive grn` | Receive GRN with quantities |
| 10 | `supplier invoice list visible` | Navigate to supplier invoices page |
| 11 | `auto-reorder report` | View auto-reorder report |
| 12 | `sidebar navigation - owner` | Owner sees all Phase 3 nav items |
| 13 | `sidebar navigation - cashier` | Cashier sees limited nav items |
| 14 | `feature flag hides loyalty` | When feature disabled, loyalty nav hidden |

---

## 4. Test Execution

### Backend
```bash
docker compose exec -T backend php artisan test --filter=Phase3
```

### Frontend Build
```bash
cd frontend && npx tsc --noEmit && npx vite build
```

### E2E
```bash
cd frontend && npx playwright test e2e/phase3.spec.ts --reporter=line
```

### Full Regression
```bash
docker compose exec -T backend php artisan test
```

---

## 5. Test Data Requirements

- E2ESeeder updated to create:
  - Customer with loyalty points and credit limit
  - Supplier with ratings
  - Purchase order in 'ordered' status (for GRN creation)
  - Approved requisition (for convert test)
  - Products with minimum_quantity set (for auto-reorder)

- Test factories needed for:
  - `CustomerLoyaltyPointsFactory`
  - `CustomerLoyaltyTransactionFactory`
  - `CustomerCreditTransactionFactory`
  - `SupplierRatingFactory`
  - `PurchaseRequisitionFactory`
  - `PurchaseRequisitionItemFactory`
  - `GoodsReceiptNoteFactory`
  - `GrnItemFactory`
  - `SupplierInvoiceFactory`

---

*End of Phase 3 Testing*
