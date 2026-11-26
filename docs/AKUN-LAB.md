# Akun lokal LaporKota

Semua akun di bawah ini dibuat otomatis saat `docker compose up -d` pertama dan hanya untuk latihan lokal.

| Username | Password | Peran |
|---|---|---|
| admin_kota | `Kota-aman-雪-2026!` | Admin layanan |
| tim1_warga_1, tim1_warga_2, tim1_warga_5, tim1_warga_6, tim1_warga_7 | `Kota-aman-雪-2026!` | Warga |
| tim1_petugas_3 | `Kota-aman-雪-2026!` | Petugas dinas 1 |
| tim1_petugas_4 | `Kota-aman-雪-2026!` | Petugas dinas 2 |

Tim kedua memakai awalan `tim2_` dengan pola yang sama (jumlah tim diatur `FIXTURE_TEAMS`, bawaan 2). Akun baru dapat dibuat melalui halaman pendaftaran.

Database lokal memakai user `root` MySQL tanpa password dan hanya dapat diakses dari jaringan internal Compose.
