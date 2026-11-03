RAPORKU - PHP MYSQL CRUD + CAPTCHA + PANDUAN ONLINE
==================================================

FITUR
- Login admin dan siswa.
- Verifikasi manusia Cloudflare Turnstile pada halaman awal (CAPTCHA sungguhan, bukan checkbox palsu).
- CRUD siswa, kelas, mata pelajaran, tahun ajaran, dan nilai.
- Password di-hash; query menggunakan PDO prepared statements.
- Siswa hanya melihat rapornya sendiri.

INSTALASI LOKAL XAMPP
1. Ekstrak folder raporku_xampp ke C:\xampp\htdocs\raporku_xampp
2. Jalankan Apache dan MySQL.
3. Import database.sql melalui phpMyAdmin.
4. Buka http://localhost/raporku_xampp/setup.php satu kali untuk data demo.
5. Hapus setup.php setelah selesai.
6. Buka http://localhost/raporku_xampp/
7. Pada localhost, CAPTCHA dilewati hanya untuk pengembangan. CAPTCHA wajib dikonfigurasi sebelum website dipublikasikan.

AKUN DEMO
Admin: admin / admin123
Siswa: username NIS (mis. 7001) / password 12345
Segera ganti password demo sebelum penggunaan nyata.

MENGAKTIFKAN CAPTCHA CLOUDFLARE TURNSTILE
1. Daftar/login ke Cloudflare dan buka Turnstile: https://dash.cloudflare.com/
2. Tambahkan widget untuk domain website Anda. Tambahkan domain utama dan www jika dipakai.
3. Pilih mode Managed (disarankan).
4. Salin Site Key dan Secret Key dari dashboard Turnstile.
5. Atur environment variables pada hosting: TURNSTILE_SITE_KEY dan TURNSTILE_SECRET_KEY.
   Jika hosting tidak menyediakan environment variables, isi nilai tersebut di config.php pada variabel $turnstileSiteKey dan $turnstileSecretKey. Jangan pernah menampilkan atau membagikan Secret Key.
6. Pastikan PHP hosting dapat melakukan HTTPS request keluar ke challenges.cloudflare.com.
7. Tes login: tanpa menyelesaikan Turnstile, login harus ditolak.

PUBLISH KE INTERNET (HOSTING PHP + MYSQL)
Website belum otomatis online hanya dengan file ZIP. Anda memerlukan akun hosting yang mendukung PHP dan MySQL serta domain/subdomain (atau alamat sementara dari hosting).
1. Beli/pilih hosting PHP + MySQL dan domain. Pilih versi PHP 8.0 atau lebih baru, ekstensi PDO MySQL, dan HTTPS/SSL.
2. Buat database MySQL dan user database dari panel hosting. Catat host, nama database, username, dan password.
3. Import database.sql melalui phpMyAdmin milik hosting.
4. Upload ISI folder raporku_xampp ke document root domain (sering bernama public_html). Jangan upload folder pembungkus tambahan jika ingin URL domain langsung membuka index.php.
5. Edit config.php sesuai kredensial hosting: DB_HOST, DB_NAME, DB_USER, DB_PASS. Cara terbaik adalah mengatur environment variables di panel hosting. Jika tidak tersedia, ubah nilai default pada config.php.
6. Jalankan setup.php sekali melalui URL domain untuk mengisi data awal. Setelah berhasil, segera hapus setup.php. Jangan jalankan ulang pada database yang sudah berisi data.
7. Buat widget Cloudflare Turnstile untuk domain dan atur Site Key + Secret Key seperti langkah di atas.
8. Aktifkan SSL/HTTPS di panel hosting dan paksa redirect HTTP ke HTTPS.
9. Ganti password admin dan semua password awal siswa. Hapus akun/data demo yang tidak diperlukan.
10. Tes login admin, login siswa, tambah/edit/hapus data, dan tampilan rapor dari perangkat lain.

PENTING UNTUK ONLINE
- Jangan gunakan akun admin123 atau password siswa 12345 pada penggunaan nyata.
- Batasi hak akses database hanya pada database aplikasi; jangan gunakan akun root hosting.
- Simpan config.php dan Secret Key dengan izin file yang aman. Jangan unggah backup database ke folder publik.
- Buat backup database rutin. Tambahkan perlindungan CSRF, pembatasan percobaan login, logging, dan kebijakan privasi sebelum digunakan sekolah sungguhan.
- Pastikan sekolah memiliki dasar izin yang sesuai untuk mempublikasikan data pribadi/nilai siswa. Jangan tampilkan rapor siswa ke publik.
- Pengujian di localhost bukan bukti bahwa aplikasi sudah berhasil dipasang di hosting. Hosting dan domain harus disiapkan pemilik.

CATATAN
CAPTCHA Cloudflare aktif saat Site Key dan Secret Key sudah diisi. Di localhost tanpa kunci, aplikasi menampilkan keterangan mode pengembangan agar pengguna tetap dapat menguji aplikasi. Pada server online tanpa konfigurasi Turnstile, verifikasi gagal dan login ditolak.
