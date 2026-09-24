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


## Catatan

Source ini adalah fondasi siap dikembangkan/UAT: schema inti, multi-entity, master, dashboard, inventory movement, accounting journal, sync log dan seed data sudah disiapkan. Modul transaksi detail POS, purchasing, produksi, fleet, accounting posting, RBAC granular dan sync engine berikutnya dibangun di atas fondasi ini.

---

# Audit & Decision Log

## 2026-09-23 — Audit Penjualan & Gudang

**Status: AUDIT SELESAI**

### Penjualan

* Tabel `sales` menjadi source transaksi penjualan.
* Penjualan memiliki `entity_id` dan `business_unit_id`.
* Detail penjualan disimpan pada `sale_items`.
* UOM transaksi menggunakan `unit_id`, `conversion_factor`, dan `base_qty`.
* Stok dan HPP menggunakan satuan dasar/base unit.
* Stock movement penjualan menggunakan `reference_type = sale` dan `reference_id` untuk traceability.
* Penjualan dilakukan dalam transaction database sehingga transaksi penjualan, stok, dan movement tetap atomic.
* Penjualan tidak menentukan jurnal accounting secara langsung.
* Accounting Engine belum diimplementasikan pada tahap ini.
* HPP Engine tetap menjadi sumber perhitungan HPP dan tidak diubah.
* Validasi Product ↔ Business Unit wajib mengikuti mapping `product_business_units`.
* Penjualan tidak memilih gudang secara manual.

### Gudang

* Master Gudang **tidak memilih Business Unit** pada saat membuat/mengedit data gudang.
* Data Gudang berdiri sebagai master tersendiri.
* Relasi Gudang ↔ Business Unit dilakukan melalui mekanisme **Mapping Gudang ke Unit Bisnis** pada Setup/Mapping.
* Aturan operasional yang dikunci: **1 Business Unit = 1 Gudang aktif**.
* Penjualan mengambil gudang berdasarkan mapping Business Unit.
* Sistem tidak boleh memilih gudang secara arbitrer menggunakan `first()` apabila terdapat lebih dari satu gudang aktif.
* Jika konfigurasi Business Unit memiliki lebih dari satu gudang aktif, transaksi harus ditolak sampai konfigurasi diperbaiki.
* Mutasi antar gudang tetap menggunakan fitur **Mutasi Barang**.

### HPP

* **HPP Engine LOCKED.**
* HPP Engine tidak diubah dalam audit Penjualan dan Gudang.
* Business Unit menentukan tipe/metode HPP melalui `business_type` dan `hpp_method`.
* Akun HPP tidak disimpan langsung pada Business Unit.
* Akun accounting/HPP ditentukan melalui **Business Unit Account Mapping → COA**.
* `business_unit_account_mappings` menjadi source of truth untuk mapping akun.
* Perubahan pada HPP Engine tidak menjadi bagian dari pekerjaan audit ini.

### Accounting

* Struktur dasar `chart_of_accounts`, `business_unit_account_mappings`, `journals`, dan `journal_entries` dinilai sudah memadai sebagai fondasi.
* Accounting Engine belum diimplementasikan pada tahap ini.
* Penjualan, pembelian, pembayaran, produksi, initial setup, stock adjustment, dan stock opname belum dipusatkan melalui Accounting Engine.
* Retur Penjualan sudah memiliki mekanisme jurnal sendiri dan nantinya perlu dinormalisasi melalui Accounting Engine.
* Accounting Engine tidak boleh menggunakan hardcode nomor akun; akun harus diperoleh dari Business Unit Account Mapping.
* Tidak ada perubahan Accounting Engine pada tahap audit ini.

### Keputusan Terkunci

* `[LOCK]` HPP Engine tidak diubah.
* `[LOCK]` Master Gudang tidak memilih Business Unit.
* `[LOCK]` Mapping Gudang ↔ Business Unit dilakukan melalui Setup/Mapping.
* `[LOCK]` 1 Business Unit memiliki 1 Gudang aktif untuk transaksi operasional.
* `[LOCK]` Penjualan tidak memilih gudang secara manual.
* `[LOCK]` Mutasi Barang digunakan untuk perpindahan stok antar gudang.
* `[LOCK]` Business Unit tidak menyimpan `hpp_account_id`.
* `[LOCK]` Business Unit Account Mapping menjadi source of truth akun accounting/HPP.
* `[LOCK]` Accounting Engine belum diimplementasikan pada tahap audit ini.
* `[LOCK]` Tidak mengubah konsep Mini ERP yang sudah disepakati tanpa keputusan/revisi eksplisit.

### Status Audit

| Komponen                       | Status                                         |
| ------------------------------ | ---------------------------------------------- |
| Master Gudang                  | 🟢 Terkunci                                    |
| Mapping Gudang ↔ Business Unit | 🟢 Terkunci                                    |
| Penjualan                      | 🟡 Audit selesai, UAT berikutnya               |
| Product ↔ Business Unit        | 🟡 Wajib divalidasi                            |
| HPP Engine                     | 🟢 Terkunci / tidak diubah                     |
| Business Unit Account Mapping  | 🟢 Terkunci                                    |
| Accounting Engine              | 🔴 Belum diimplementasikan                     |
| Mutasi Barang                  | 🟢 Tetap digunakan untuk transfer antar gudang |

---

## 2026-09-24 — Audit Pembelian: PO & Penerimaan

**Status: AUDIT SELESAI**

### Prinsip Utama Pembelian

Alur normal pembelian dikunci sebagai:

**PO → Penerimaan → Stock Movement IN → Stock**

Setelah proses penerimaan, transaksi pembelian/faktur menjadi dasar pencatatan kewajiban dan accounting sesuai hasil audit modul Pembelian berikutnya.

Untuk pembelian langsung tanpa PO:

**Pembelian Langsung → Penerimaan Otomatis → Stock Movement IN → Stock**

Pembelian langsung **tidak boleh membuat stock movement IN secara langsung**. Sistem tetap membuat record Penerimaan sebagai sumber resmi stock-in.

Untuk barang yang datang tetapi belum dapat diidentifikasi:

**Barang Datang → Penerimaan UNIDENTIFIED (PENDING) → Verifikasi & Approve → Stock Movement IN → Stock**

Penerimaan unidentified merupakan mekanisme exception dan bukan jalur normal penerimaan tanpa PO.

---

### Purchase Order (PO)

* PO wajib memiliki konteks **Business Unit**.
* PO wajib memiliki **Gudang Tujuan**.
* PO tidak menambah stok.
* PO tidak menciptakan hutang/payable.
* PO dapat diterima secara parsial.
* Satu PO dapat memiliki beberapa Penerimaan.
* Kuantitas PO harus dapat ditelusuri melalui Ordered, Received, Cancelled, dan Outstanding.
* Rumus Outstanding dikunci:

  `Outstanding = Ordered - Received - Cancelled`

* Kuantitas historis PO tidak boleh diubah untuk menghilangkan histori penerimaan.
* Jika sisa PO dibatalkan supplier, kuantitas dicatat sebagai **Cancelled**, bukan mengubah Ordered.
* Harga pada PO merupakan **transaction snapshot**.
* Harga invoice/pembelian tidak boleh menimpa harga historis pada PO.
* UOM dan conversion factor harus konsisten sampai ke proses inventory.
* Status PO dan status penerimaan merupakan dua status yang berbeda.
* Approval PO merupakan proses yang berbeda dari proses penerimaan.
* PO yang sudah memiliki penerimaan tidak boleh diedit bebas sehingga menghilangkan histori transaksi.
* Pembatalan PO harus mempertahankan histori penerimaan.
* PO dapat ditutup apabila seluruh quantity telah diterima atau sisa quantity telah dibatalkan.
* PO tidak menghasilkan jurnal accounting.
* Hubungan PO dengan Faktur Pembelian tetap menjadi bagian audit Faktur Pembelian dan belum dikunci pada audit ini.

---

### Penerimaan Barang

* **Penerimaan adalah satu-satunya gateway untuk Stock Movement IN.**
* Tidak ada transaksi lain yang boleh membuat Stock Movement IN secara langsung.
* Setiap Stock Movement `IN` wajib memiliki referensi ke Penerimaan.
* Penerimaan normal dilakukan berdasarkan PO.
* Quantity penerimaan berdasarkan **actual quantity yang diterima**, bukan otomatis seluruh quantity PO.
* Satu PO dapat menghasilkan beberapa Penerimaan.
* Penerimaan parsial tidak boleh mengubah histori quantity pada PO.
* Penerimaan harus tetap dapat ditelusuri kembali ke sumber transaksinya.

---

### Pembelian Langsung Tanpa PO

* Pembelian langsung tanpa PO diperbolehkan sebagai exception transaksi pembelian.
* Pembelian langsung **tidak boleh langsung menghasilkan Stock Movement IN**.
* Sistem harus membuat **Penerimaan otomatis** sebagai sumber stock-in.
* Dengan demikian, secara data tetap berlaku:

  `Pembelian Langsung → Penerimaan → Stock Movement IN → Stock`

* User tidak perlu menjalankan prosedur penerimaan manual terpisah untuk pembelian langsung.
* Penerimaan otomatis tetap menjadi record resmi sumber stock-in.

---

### Penerimaan Barang Tidak Teridentifikasi

* Barang yang secara fisik datang tetapi belum dapat diidentifikasi dapat dicatat sebagai **Penerimaan UNIDENTIFIED**.
* Penerimaan UNIDENTIFIED berstatus **PENDING**.
* Penerimaan UNIDENTIFIED yang masih PENDING **tidak menambah stok**.
* Penerimaan UNIDENTIFIED harus melalui proses verifikasi.
* Setelah diverifikasi dan di-approve, Penerimaan menjadi valid untuk menghasilkan **Stock Movement IN**.
* Stock Movement IN hanya dibuat setelah proses verifikasi/approval tersebut.
* Mekanisme UNIDENTIFIED merupakan exception handling pada Penerimaan, bukan bypass terhadap gateway stock-in.
* Detail mekanisme penyelesaian setelah identifikasi akan diaudit lebih lanjut bersama hubungan Pembelian/Faktur/Retur.

---

### Stock Movement

* Semua Stock Movement `IN` harus berasal dari Penerimaan yang valid.
* `reference_type` dan `reference_id` harus memungkinkan traceability ke Penerimaan.
* Tidak boleh ada jalur:

  `Pembelian → Stock Movement IN`

* Tidak boleh ada jalur:

  `PO → Stock Movement IN`

* Jalur stock-in yang sah adalah:

  `Penerimaan → Stock Movement IN → Stock`

---

### Keputusan Terkunci

* `[LOCK]` Penerimaan adalah **single gateway stock-in**.
* `[LOCK]` Setiap Stock Movement `IN` wajib berasal dari Penerimaan.
* `[LOCK]` PO tidak menambah stok.
* `[LOCK]` PO tidak menciptakan payable.
* `[LOCK]` PO dapat menerima barang secara parsial.
* `[LOCK]` Satu PO dapat memiliki beberapa Penerimaan.
* `[LOCK]` Outstanding menggunakan `Ordered - Received - Cancelled`.
* `[LOCK]` Quantity historis PO tidak boleh ditimpa untuk menghilangkan histori.
* `[LOCK]` Harga PO merupakan transaction snapshot.
* `[LOCK]` Harga invoice tidak menimpa harga PO.
* `[LOCK]` Pembelian langsung tanpa PO tetap menghasilkan Penerimaan otomatis.
* `[LOCK]` Tidak boleh ada Pembelian Langsung → Stock secara langsung.
* `[LOCK]` Penerimaan UNIDENTIFIED adalah exception mechanism.
* `[LOCK]` Penerimaan UNIDENTIFIED berstatus PENDING tidak menambah stok.
* `[LOCK]` Penerimaan UNIDENTIFIED baru menghasilkan stock setelah verifikasi dan approval.
* `[LOCK]` PO dan Penerimaan memiliki status yang berbeda.
* `[LOCK]` Approval PO dan penerimaan merupakan proses yang berbeda.
* `[LOCK]` HPP Engine tidak diubah dalam audit Pembelian.
* `[LOCK]` Konsep Mini ERP yang sudah disepakati tidak diubah tanpa keputusan/revisi eksplisit.

---

### Status Audit

| Komponen | Status |
|---|---|
| Purchase Order (PO) | AUDIT SELESAI |
| Penerimaan Barang | AUDIT SELESAI |
| Pembelian Langsung | AUDIT SELESAI |
| Penerimaan Unidentified | AUDIT SELESAI |
| Stock-in Gateway | AUDIT SELESAI |
| Faktur Pembelian | BELUM DIAUDIT |
| Retur Pembelian | BELUM DIAUDIT |
| Accounting Pembelian | BELUM DIAUDIT |
