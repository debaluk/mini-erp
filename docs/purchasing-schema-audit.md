# Purchasing Schema Audit — Locked Flow Alignment

Date: 2026-09-24
Repository: debaluk/mini-erp
Base: main
Implementation branch: feat/purchasing-schema-lock

## Scope

Audit the actual Laravel schema in the repository against locked Purchasing Audit #1–#11:
- PO -> Penerimaan -> Stock
- Faktur -> Hutang
- Pembayaran -> Pelunasan Hutang
- Retur -> Stock OUT and applicable Hutang/PPN correction
- Receipt <-> Invoice many-to-many allocation
- PPN, discount, additional cost, rounding, price correction
- Supplier advance/overpayment
- End-to-end reconciliation

## Actual schema findings

### Existing and usable
- entities, business_units, users, suppliers, products, units
- warehouses and warehouse_business_units
- chart_of_accounts and business_unit_account_mappings
- stock_movements
- purchases and purchase_items
- purchase_price_histories
- receipts and receipt_items
- journals and journal_entries

### Critical gaps found
1. No purchase_orders / purchase_order_items tables.
2. No PO cancellation transaction tables.
3. Existing receipts had no PO-item allocation field.
4. Existing receipt_items required purchase_item_id, which prevented:
   - unidentified receipts
   - receipt before invoice
   - later invoice allocation.
5. No Receipt <-> Invoice many-to-many allocation table.
6. Existing purchases did not contain a complete posted-invoice/tax lifecycle.
7. Existing supplier invoice number had no supplier+entity uniqueness rule.
8. No purchase return tables.
9. Existing payments are POS/customer-payment oriented and cannot serve supplier payment subledger.
10. No supplier payment allocation table.
11. No supplier advance/overpayment allocation tables.
12. No purchase additional-cost and allocation tables.
13. No post-posting purchase price/value correction table.
14. Stock movements had no direct receipt trace field.
15. Product item_type was limited to barang/jasa while the locked master requires barang/jasa/aset.
16. Supplier master had no tax-status/identity fields.
17. Purchase invoice lacked transaction-level supplier tax-status snapshot.

## Executed schema alignment

### Migration 2026_09_24_110000_align_purchasing_schema_to_locked_flow
Adds:
- purchase_orders
- purchase_order_items
- purchase_order_cancellations
- purchase_order_cancellation_items
- receipt source/verification/approval/cancellation fields
- receipt -> purchase linkage for direct purchase
- receipt item PO-item linkage
- nullable receipt item purchase-item linkage
- receipt valuation fields
- purchase invoice posting lifecycle
- supplier tax-status snapshot
- tax invoice fields
- DPP/PPN/document discount/additional cost/rounding fields
- receipt_invoice_allocations
- purchase_returns
- purchase_return_items
- supplier_payments
- supplier_payment_allocations
- supplier_advances
- supplier_advance_allocations
- purchase_additional_costs
- purchase_additional_cost_allocations
- purchase_corrections
- stock_movements.receipt_id

### Migration 2026_09_24_111000_align_master_item_tax_schema
Adds:
- products.item_type = aset
- products.type = asset
- suppliers.tax_status
- suppliers.npwp
- suppliers.nik

## Important implementation boundary

The schema now has the structural references required by the locked flow, but database structure alone cannot enforce all business invariants such as:
- Receipt qty <= PO outstanding
- Return qty <= received minus prior returns
- Receipt allocation <= receipt qty
- Invoice allocation <= invoice qty
- Payment allocation <= outstanding
- no duplicate stock movement
- atomic GL/subledger posting

Those rules belong in the transaction services/controllers and must be implemented before UAT of purchasing posting.

## HPP boundary

No HPP formula was changed. The purchasing schema only supplies the locked HPP engine with receipt/acquisition inputs.

## Accounting boundary

No hardcoded COA was added. Accounting transactions must resolve accounts through Business Unit Account Mapping.

## Next implementation stage

1. Validate migrations on the actual UAT database.
2. Add Eloquent models/relations for the new tables.
3. Implement transaction services atomically.
4. Implement PO persistence.
5. Implement Receipt persistence and Stock IN gateway.
6. Implement Purchase Invoice posting -> Hutang/GL.
7. Implement Receipt/Invoice allocation.
8. Implement supplier payment/allocation.
9. Implement returns/corrections.
10. Build reconciliation reports and invariant tests.
