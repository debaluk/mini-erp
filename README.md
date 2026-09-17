# Mini ERP — Sistem Terintegrasi Retail, Produksi, Armada & Akuntansi

Baseline project Laravel 13 + MySQL, arsitektur multi-entitas dengan tahap awal 1 entitas.

## Modul terkunci
1. Master
2. POS Retail
3. Inventori
4. Produksi
5. Armada & Jasa
6. Akuntansi
7. Laporan

## Role utama
Owner, Admin, Kasir, Inventori, Akuntansi. Detail akses memakai permission dan scope entitas/cabang/gudang.

## Prasyarat
PHP 8.2+, Composer, MySQL 8+/MariaDB kompatibel.

## Instalasi
```bash
composer install
cp .env.example .env
php artisan key:generate
# buat database mini_erp di MySQL lalu sesuaikan .env
php artisan migrate --seed
php artisan serve
```

## Catatan
Source ini adalah fondasi siap dikembangkan/UAT: schema inti, multi-entity, master, dashboard, inventory movement, accounting journal, sync log dan seed data sudah disiapkan. Modul transaksi detail POS, purchasing, produksi, fleet, accounting posting, RBAC granular dan sync engine berikutnya dibangun di atas fondasi ini.
