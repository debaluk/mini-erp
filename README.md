cd ~/workspace/mini-erp

cat >> README.md <<'EOF'

---

# Mini ERP — Retail + Produksi Batako + Armada + Akuntansi

## Akses Aplikasi

**URL:**

https://minierp.labku.biz.id

## Login Uji Coba

Password seluruh akun uji coba:

`password`

| Role | Email | Password |
|---|---|---|
| Owner | owner@minierp.local | password |
| Admin | admin@minierp.local | password |
| Kasir | kasir@minierp.local | password |
| Inventori | inventori@minierp.local | password |
| Akuntansi | akuntansi@minierp.local | password |

## Role & Hak Akses

### Owner
Pemilik & kontrol seluruh usaha.

Akses:
- Semua modul
- Semua laporan
- Approval

### Admin
Administrasi & pengelolaan.

Akses:
- Master data
- User
- Konfigurasi

### Kasir
Penjualan retail.

Akses:
- POS
- Pembayaran
- Shift kasir

### Inventori
Seluruh operasional stok.

Akses:
- Pembelian
- Gudang
- Inventory
- Produksi
- Armada

### Akuntansi
Keuangan & laporan.

Akses:
- Accounting
- HPP
- Laporan keuangan

---

# Struktur Modul

## 1. MASTER

- Produk
- Customer
- Supplier
- Gudang
- Satuan
- Tarif

## 2. PENJUALAN / POS

- POS
- Transaksi penjualan
- Pembayaran
- Shift kasir

## 3. PEMBELIAN

- Pembelian
- Supplier
- Penerimaan barang
- Hutang

## 4. INVENTORI

- Stok
- Mutasi stok
- Gudang
- Stock opname

## 5. PRODUKSI BATAKO

- Formula / BOM
- Bahan baku
- Produksi
- Hasil produksi
- Pemakaian bahan
- HPP produksi

## 6. ARMADA

- Kendaraan
- Driver
- Pengiriman
- Operasional armada
- Biaya armada

## 7. AKUNTANSI

- Jurnal
- Buku besar
- Hutang
- Piutang
- Kas & Bank
- HPP
- Laba Rugi
- Neraca
- Arus Kas

---

# Teknologi

- Laravel 13
- PHP 8.3
- MySQL
- Bootstrap 5
- Apache
- PHP-FPM

UI menggunakan **Bootstrap standard**, bukan Tailwind.

---

# Status Pengembangan

## Fondasi

- [x] Laravel project
- [x] Database MySQL
- [x] Users table
- [x] Authentication routes
- [x] Login
- [x] Logout
- [x] Role dasar
- [x] Dashboard route
- [x] Master route

## Berikutnya

- [ ] Middleware role
- [ ] Dashboard berdasarkan role
- [ ] User management
- [ ] Master Produk
- [ ] Master Customer
- [ ] Master Supplier
- [ ] Master Gudang
- [ ] Master Satuan
- [ ] Master Tarif
- [ ] POS
- [ ] Pembelian
- [ ] Inventory
- [ ] Produksi Batako
- [ ] Armada
- [ ] Akuntansi
- [ ] Laporan
- [ ] Approval

---

# Development Commands

Install dependency:

```bash
composer install

---

# Audit & Locked Design

> Audit dilakukan terhadap schema aktual setelah migration baseline dan alignment purchasing. Bagian yang berstatus **locked** tidak diubah tanpa persetujuan eksplisit.

## Final Tahap #1 — Purchasing

### Locked Flow

```
PO
 ↓
Receipt
 ↓
Purchase / Invoice
 ↓
AP
 ↓
Payment
```

### Purchasing Branches

```
PO → Cancellation

Receipt / Purchase → Return

Payment → Supplier Advance

Purchase / Receipt → Additional Cost

Purchase → Correction
```

### Prinsip Purchasing

- **PO** hanya commitment. PO tidak menggerakkan stok, hutang, atau jurnal.
- **Receipt** adalah penerimaan fisik barang dan gateway stock-in.
- **Purchase / Invoice** menjadi dasar hutang supplier dan accounting setelah penerimaan.
- **Payment** digunakan untuk settlement hutang supplier.
- **Receipt ↔ Purchase Item** menggunakan allocation dan mendukung hubungan many-to-many.
- Return, supplier advance, additional cost, dan correction memiliki tabel transaksi masing-masing.
- Struktur tabel purchasing yang sudah diaudit dinyatakan **locked** untuk lanjut ke tahap UI.

### Tabel Purchasing yang Sudah Tersedia

- `purchase_orders`
- `purchase_order_items`
- `purchase_order_cancellations`
- `purchase_order_cancellation_items`
- `receipts`
- `receipt_items`
- `receipt_invoice_allocations`
- `purchases`
- `purchase_items`
- `purchase_returns`
- `purchase_return_items`
- `supplier_payments`
- `supplier_payment_allocations`
- `supplier_advances`
- `supplier_advance_allocations`
- `purchase_additional_costs`
- `purchase_additional_cost_allocations`
- `purchase_corrections`

## Inventory — Core Locked, Mutasi Antar Gudang Ditahan

### Core Inventory

Schema yang sudah tersedia:

- `products`
- `product_business_units`
- `units`
- `warehouses`
- `warehouse_business_units`
- `warehouses_stocks`
- `stock_movements`
- `stock_opnames`
- `stock_opname_items`

### Stock Gateway

Penerimaan purchasing mengikuti:

```
Receipt
 ↓
Receipt Item
 ↓
Stock Movement
 ↓
Warehouse Stock
```

Receipt tetap merupakan gateway penerimaan barang dari purchasing.

### Mutasi Antar Gudang

**Belum dikunci dan belum dibuat.**

Konsep yang sedang dipertimbangkan:

```
Gudang A
   ↓ OUT
Mutasi Antar Gudang
   ↓ IN
Gudang B
```

Mutasi antar gudang **belum dianggap sebagai Receipt purchasing**, karena bukan pembelian dan bukan penerimaan dari supplier.

Tabel `stock_transfers` dan `stock_transfer_items` **belum dibuat dan tidak boleh dibuat sebelum konsep mutasi antar gudang difinalkan**.

## Audit Status Tahap #1

| Area | Status |
|---|---|
| Purchasing flow | 🔒 LOCKED |
| Purchasing schema | 🔒 LOCKED |
| Receipt sebagai stock-in purchasing gateway | 🔒 LOCKED |
| Inventory core schema | 🔒 LOCKED |
| Mutasi antar gudang | ⏸️ OPEN / belum final |
| UI Purchasing | ▶️ Siap dilanjutkan |
| UI Inventory | ⏸️ Menunggu keputusan mutasi antar gudang |

## Catatan Audit

**Schema siap tidak otomatis berarti engine transaksi sudah tervalidasi.**

Tahap berikutnya adalah audit flow dan kesiapan tabel untuk **Produksi**, kemudian dilanjutkan audit engine/controller sebelum implementasi UI.

---

# Locked Business Rules

## Entity & Business Unit

- `Entity` = entitas legal/perusahaan.
- `Business Unit` = konteks operasional/sumber transaksi.
- Business Unit default:
  - `RET` = Retail
  - `PROD` = Produksi
  - `JASA` = Jasa
- Satu user dapat memiliki mapping ke beberapa Business Unit.
- Neraca bersifat consolidated pada entity.
- Laba rugi dapat dipilih berdasarkan Business Unit.

## Item & Unit

Jenis item:

- Barang
- Jasa
- Aset

**Unit/UOM** adalah satuan pengukuran item dan berbeda dari Business Unit.

## HPP

- Retail menggunakan Moving Average / Perpetual.
- Production HPP menggunakan total actual cost BUASO terhadap target produksi.
- BUASO:
  - **B** = Bahan / Material Usage
  - **U** = Upah
  - **A** = Alat
  - **S** = Sewa
  - **O** = Overhead
- BUASO adalah bagian dari engine HPP dan **tidak boleh disamakan dengan COA mapping**.
- COA mapping merupakan source of truth untuk pemetaan akun accounting.
- Reject/damage produksi menggunakan account:
  - `6000402` = Beban Kerusakan Persediaan & Stock Opname

## Production Actual Result

Schema produksi sudah memiliki dukungan untuk target, good output, dan reject, tetapi workflow UI actual result **belum dibuat**.

Prinsip yang dikunci:

- Target produksi dan hasil actual harus dapat dibedakan.
- HPP produksi menggunakan target quantity sebagai denominator sesuai engine yang telah dikunci.
- Good output dan reject harus dapat dicatat sebagai hasil actual.
- Nilai reject tidak boleh dibebankan dua kali.
