# Panduan peserta LaporKota

## Menyiapkan lab

Jalankan `docker compose up -d` dari root repositori, tunggu service `web` berstatus `healthy` (`docker compose ps`), lalu buka http://127.0.0.1:19082. Akun dummy tercantum di [AKUN-LAB.md](AKUN-LAB.md). Gunakan `docker compose logs -f web` untuk melihat log aplikasi. Untuk kembali ke data awal: `docker compose down`, hapus folder `data/`, lalu `docker compose up -d`. Setelah mengubah source, jalankan `docker compose up -d --build`.

## Pengerjaan

Jelajahi pendaftaran, login, pencarian, daftar/detail aduan, foto, penugasan, dan tindak lanjut. Warga mengelola aduan sendiri, admin menugaskan, dan petugas bekerja dalam dinasnya. Pertahankan akses sah dan alur status setelah perbaikan.

Mulai dengan memetakan fitur, identitas/peran, objek data, dan hasil yang diharapkan. Cari masalah melalui pengamatan runtime dan pembacaan source, lalu pisahkan bukti yang telah teruji dari dugaan.

Setiap perbaikan harus memiliki reproduksi sebelum perubahan, alasan teknis, dan verifikasi sesudah perubahan. Jangan hanya menyembunyikan tombol atau menonaktifkan seluruh fitur. Pertahankan fungsi bisnis dan uji penolakan tanpa merusak data.

Gunakan instance lokal milik sendiri. Simpan bukti yang sudah diredaksi dan laporkan melalui pull request sesuai arahan kelas.
