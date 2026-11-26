# LaporKota — lab peserta

Simulasi layanan aduan masyarakat: warga membuat laporan, admin menugaskan, dan petugas dinas menindaklanjuti.

Repositori ini adalah titik awal latihan secure coding. Fork repositori, jalankan secara lokal, temukan masalah, lalu ajukan perbaikan beserta pengujian. Aplikasi sengaja memuat kelemahan untuk latihan; gunakan data dummy dan jalankan hanya di komputer lokal. Port Docker dibatasi ke loopback.

## Menjalankan lokal

Prasyarat: Docker Engine atau Docker Desktop dengan Compose v2, dan Git. Build pertama membutuhkan internet untuk mengunduh image dan dependency.

```sh
git clone <URL-fork-Anda>
cd <folder-repositori>
docker compose up -d
```

Perintah pertama membangun image, menyiapkan database, dan mengisi data dummy secara otomatis. Tunggu hingga `docker compose ps` menampilkan service `web` berstatus `healthy`, lalu buka **http://127.0.0.1:19082**. Akun dan password dummy ada di [akun lab](docs/AKUN-LAB.md).

Data database dan foto unggahan disimpan di folder `data/` pada folder repositori ini. Folder tersebut sudah diabaikan Git; jangan commit `data/`, `.env`, cookie, token, atau hasil tangkapan proxy.

Jika port 19082 sudah digunakan, salin `.env.example` menjadi `.env`, ubah `LAB_PORT`, lalu jalankan kembali `docker compose up -d`.

## Perintah harian

```sh
docker compose ps
docker compose logs -f web
docker compose down
# Jalankan ulang dan bangun ulang image setelah mengubah source:
docker compose up -d --build
```

`down` menghentikan container tanpa menghapus data. Untuk mengembalikan data dummy ke kondisi awal:

```sh
docker compose down
rm -rf data
docker compose up -d
```

Jika `rm -rf data` ditolak karena izin file (umum di Linux), jalankan `docker run --rm -v "$PWD/data:/d" alpine sh -c 'rm -rf /d/*'` lalu hapus folder `data`.

Dockerfile tersedia di `Dockerfile` dan runtime didefinisikan di `compose.yaml`. Service `init` menjalankan migrasi dan seed sekali lalu berhenti; status `exited (0)` untuk service ini normal.

## Pengerjaan dan pengumpulan

1. Baca [panduan peserta](docs/PANDUAN-PESERTA.md) dan jalankan alur aplikasi normal.
2. Buat branch `fix/<topik>` pada fork Anda.
3. Catat masalah dan bukti awal dengan data dummy.
4. Perbaiki source atau konfigurasi, tambahkan pengujian yang membuktikan perbaikan, lalu uji ulang alur sah.
5. Buka pull request di fork Anda sesuai instruksi kelas; isi [template laporan](docs/LAPORAN.md).

Sertakan langkah reproduksi, dampak, alasan perubahan, cara menguji, dan risiko tersisa. Jangan sertakan data sensitif atau folder `data/`.
