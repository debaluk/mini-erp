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