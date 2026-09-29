# PRODUCT REQUIREMENT DOCUMENT (PRD) — VERSI 2.0

## SISTEM MONITORING KONDISI GWT, KOLAM AIR BERSIH, DAN LAPORAN BULANAN AIR BERBASIS WEB

---

## DAFTAR ISI

**Kelompok A — Informasi Dokumen dan Konteks**
1. Informasi Dokumen
2. Sejarah dan Posisi Dokumen Versi 2.0
3. Latar Belakang dan Konteks Organisasi
4. Permasalahan yang Diselesaikan
5. Tujuan dan Sasaran Sistem

**Kelompok B — Ruang Lingkup, Aktor, dan Proses Bisnis**
6. Ruang Lingkup Sistem Versi 2.0
7. Batasan Sistem Versi 2.0
8. Aktor dan Matriks Hak Akses
9. Persona dan Skenario Kerja
10. Proses Bisnis End-to-End

**Kelompok C — Modul Sistem**
11. Modul Autentikasi
12. Modul Dashboard Monitoring
13. Modul Monitoring Harian
14. Katalog Kondisi
15. Modul Histori dan Filter
16. Modul Detail Pemeriksaan
17. Modul Laporan Bulanan
18. Modul Ekspor PDF Laporan Bulanan
19. Modul Keamanan Foto Bukti
20. Modul Manajemen Lokasi
21. Modul Manajemen Pengguna
22. Sistem Peringatan Kondisi

**Kelompok D — Data, Keamanan, dan Kebutuhan Non-Fungsional**
23. Struktur Database
24. Relasi Data
25. Aturan Validasi Input dan Pesan Galat
26. Model Keamanan Sistem
27. Strategi Penyimpanan Foto Bukti
28. Klasifikasi dan Privasi Data
29. Desain Antarmuka dan Responsivitas
30. Kebutuhan Non-Fungsional

**Kelompok E — Kualitas, Verifikasi, dan Penutup**
31. Strategi Pengujian
32. Acceptance Criteria
33. Keselarasan PRD Versi 1.0 dan Implementasi Versi 2.0
34. Temuan dan Catatan Teknis
35. Backlog Perbaikan yang Direkomendasikan
36. Kesimpulan

---

# KELOMPOK A — INFORMASI DOKUMEN DAN KONTEKS

---

## 1. INFORMASI DOKUMEN

Dokumen ini merupakan Product Requirement Document (PRD) versi 2.0 dari Sistem Monitoring Kondisi GWT, Kolam Air Bersih, dan Laporan Bulanan Air. Berbeda dengan PRD versi 1.0 yang berstatus sebagai dokumen perancangan (*draft*), PRD versi 2.0 ini berstatus **dokumentasi as-built**, yaitu dokumen yang menggambarkan sistem yang benar-benar telah dibangun, diimplementasikan, dan berjalan di dalam basis kode proyek. Dengan demikian, seluruh uraian dalam dokumen ini mengacu pada kondisi sistem yang faktual dan terverifikasi langsung terhadap source code aplikasi, struktur basis data, berkas konfigurasi, serta rangkaian pengujian otomatis yang tersedia di dalam repositori proyek.

| Atribut | Keterangan |
|---|---|
| Nama Produk | Sistem Monitoring Kondisi GWT dan Laporan Bulanan Air |
| Jenis Sistem | Sistem Informasi Berbasis Web |
| Platform | Web Responsif |
| Framework Backend | Laravel 10 (v10.50.3) |
| Bahasa Backend | PHP ^8.1 |
| Basis Data | MySQL (diperlukan versi 8.0 atau lebih baru) |
| Mesin Template | Laravel Blade |
| Kerangka Antarmuka | Bootstrap 5.3.3, Bootstrap Icons 1.11.3 |
| Pustaka Visualisasi | Chart.js 4.4.1 |
| Pustaka Pembuatan PDF | barryvdh/laravel-dompdf ^3.1 (dompdf v3.1.6) |
| Alat Build Frontend | Vite ^4 dengan laravel-vite-plugin ^0.7 |
| Organisasi Pengguna | Unit Pengelola Rumah Susun (UPRS) VI, Perums Jaya Raya |
| Pengguna | Petugas Sarana dan Prasarana dan Admin/Pengelola |
| Versi Dokumen | 2.0 |
| Status Dokumen | As-Built / Implementasi Selesai |

---

## 2. SEJARAH DAN POSISI DOKUMEN VERSI 2.0

PRD versi 1.0 disusun sebagai dokumen perancangan awal dengan ruang lingkup yang hanya mencakup modul monitoring harian kondisi GWT dan kolam air bersih. Pada versi 1.0 tersebut, konsep inti sistem sudah dicantumkan secara lengkap, mulai dari latar belakang, tujuan, aktor, proses bisnis, struktur basis data, sampai acceptance criteria, dan secara eksplisit masih tidak mencakup integrasi sensor IoT maupun fitur lanjutan lainnya.

Selama proses pembangunan, sistem berkembang jauh melampaui batas perancangan awal tersebut. Berdasarkan kondisi kode saat ini, versi 2.0 menambahkan empat kelompok besar kemampuan yang sama sekali belum dibahas di dalam versi 1.0. Kelompok pertama adalah modul Laporan Bulanan, yaitu pencatatan kondisi air untuk keperluan pelaporan keuangan beserta master lokasi bulanan yang terpisah dari master lokasi monitoring harian, dilengkapi kemampuan mengekspor laporan ke format PDF. Kelompok kedua adalah modul Manajemen Pengguna yang lengkap, dengan pembatasan peran (*role based access control*) yang tegas antara admin dan petugas, termasuk berbagai pengaman pada saat penghapusan akun. Kelompok ketiga adalah modul Keamanan Foto Bukti, yaitu pemindahan penyimpanan foto dari disk publik yang dapat diakses siapa saja ke disk privat yang hanya dapat diakses melalui endpoint terautentikasi, disertai perintah migrasi data foto lama. Kelompok keempat adalah perluasan Dashboard Monitoring yang jauh lebih kaya, mencakup filter periode dengan pintasan cepat, enam kartu statistik, dua grafik visualisasi interaktif, tabel status terakhir per lokasi, tabel kepatuhan sesi, dan ringkasan laporan bulanan yang dimuat secara asinkron.

Selain itu, versi 2.0 ini juga merekam berbagai detail teknis yang ditemukan pada implementasi, seperti penambahan kolom foto_disk pada tabel foto, penggunaan fungsi jendela (window function) ROW_NUMBER pada MySQL untuk menghitung status terakhir per lokasi, keberadaan dua katalog kondisi yang berbeda antara modul monitoring dan modul laporan bulanan, serta seluruh aturan validasi dan pesan galat dalam Bahasa Indonesia yang benar-benar diimplementasikan di sisi server. Dokumen versi 2.0 ini dibuat untuk menggantikan fungsi PRD versi 1.0 sebagai rujukan utama, sekaligus mempertahankan kemampuan penelusuran perubahan dari versi 1.0 menuju versi 2.0.

---

## 3. LATAR BELAKANG DAN KONTEKS ORGANISASI

Sistem ini dikembangkan untuk mendukung Unit Pengelola Rumah Susun (UPRS) VI di lingkungan Perums Jaya Raya, sebuah kawasan permukiman yang dilayani oleh infrastruktur air bersih. Pada kawasan tersebut, ketersediaan air bersih merupakan kebutuhan dasar yang sangat vital bagi ratusan rumah susun yang menghuni. Air bersih dipasok melalui dua infrastruktur utama, yaitu Ground Water Tank atau tandon air tanah yang menjadi sumber dan penampungan air, serta kolam air bersih yang berfungsi sebagai reservoir distribusi. Kedua infrastruktur tersebut memerlukan pemantauan kondisi secara berkala agar kualitas dan ketersediaan air selalu dapat dijamin bagi penghuni.

Sebagai petugas Sarana dan Prasarana yang bekerja di lapangan, tugas utamanya adalah melakukan pemeriksaan fisik terhadap GWT dan kolam air bersih sebanyak tiga kali dalam satu hari, yaitu pada sesi pagi, sesi siang, dan sesi sore. Pada setiap pemeriksaan, petugas harus datang langsung ke lokasi, memeriksa kondisi air secara visual, mengambil foto sebagai bukti, kemudian mencatatnya. Pada kondisi tertentu, petugas menemukan kondisi yang tidak normal, seperti debit air yang menurun atau tekanan air yang terasa lebih kecil dibandingkan kondisi normal. Temuan semacam ini wajib ditindaklanjuti agar tidak terjadi gangguan pelayanan air bagi penghuni.

Sebelum sistem ini dibangun, seluruh proses pencatatan hasil pemeriksaan masih dilakukan secara manual, yaitu menggunakan buku catatan atau lembar kertas. Pencatatan manual menimbulkan berbagai kendala yang mengganggu operasional. Pertama, data pemeriksaan sulit ditelusuri kembali apabila diperlukan atau sudah tertinggal lama. Kedua, dokumentasi foto yang diambil petugas tersimpan berantakan di dalam album atau penyimpanan ponsel pribadi sehingga sulit dicari dan berisiko hilang. Ketiga, pengelola atau admin tidak memiliki gambaran ikhtisar yang jelas tentang kondisi air di seluruh lokasi, sehingga tidak tahu lokasi mana yang sedang mengalami gangguan. Keempat, tidak ada perhitungan otomatis tentang seberapa lengkap pemeriksaan yang sudah dilakukan terhadap target tiga kali sehari. Kelima, tidak ada pelaporan bulanan yang terstruktur, sehingga sulit menyusun laporan kondisi air yang dapat dipertanggungjawabkan kepada pihak pengelola maupun instansi yang lebih tinggi.

Berdasarkan seluruh persoalan tersebut, sistem informasi berbasis web ini dibangun untuk mendigitalisasi proses tersebut secara menyeluruh dan terstruktur.

---

## 4. PERMASALAHAN YANG DISELESAIKAN

Sistem ini dirancang untuk menjawab delapan permasalahan utama yang teridentifikasi dalam proses operasional Sarpras.

**Permasalahan pertama** adalah proses pencatatan pemeriksaan yang masih dilakukan manual, sehingga data mudah hilang, tidak terstruktur, dan sulit ditelusuri kembali.

**Permasalahan kedua** adalah dokumentasi foto yang tidak tersimpan terpusat, melainkan tersebar di perangkat pribadi masing-masing petugas, sehingga sulit dipantau oleh pengelola dan berisiko hilang atau tidak terarsip.

**Permasalahan ketiga** adalah data hasil pemeriksaan yang sulit ditelusuri kembali, karena tidak terkelompok berdasarkan tanggal, waktu, lokasi, dan kondisi, melainkan bercampur dalam catatan manual.

**Permasalahan keempat** adalah pengelola yang kesulitan melihat histori kondisi GWT dan kolam air bersih secara keseluruhan, tidak memiliki dashboard yang menampilkan ringkasan kondisi, jumlah pemeriksaan per hari, maupun sebaran kondisi per lokasi.

**Permasalahan kelima** adalah kondisi tidak normal, seperti debit turun atau tekanan air kecil, yang tidak langsung terlihat dan baru ketahuan ketika admin membuka catatan secara manual satu per satu.

**Permasalahan keenam** adalah tidak adanya sistem terpusat yang memungkinkan pencarian data berdasarkan rentang tanggal, lokasi, kondisi, dan petugas, sehingga Proses audit dan penelusuran menjadi sulit.

**Permasalahan ketujuh** adalah belum adanya pelaporan bulanan yang memuat foto, ringkasan kondisi, dan dapat diekspor, sehingga laporan yang disusun untuk keperluan koordinasi dengan pihak atasan menjadi tidak terstruktur dan menyulitkan rekapitulasi.

**Permasalahan kedelapan** adalah foto bukti yang pada implementasi sebelumnya tersimpan di dalam folder publik yang dapat diakses oleh siapa saja tanpa autentikasi, sehingga menimbulkan risiko privasi dan kebocoran data. Aspek ini menjadi alasan utama bagi penyusunan versi 2.0 yang memindahkan seluruh foto bukti ke penyimpanan privat.

---

## 5. TUJUAN DAN SASARAN SISTEM

Sistem ini memiliki enam tujuan utama yang saling melengkapi satu sama lain.

**Tujuan pertama** adalah digitalisasi pencatatan, yaitu mengubah seluruh proses pencatatan hasil pemeriksaan dari manual menjadi digital sehingga data dapat disimpan di dalam basis data dan tidak hilang.

**Tujuan kedua** adalah terdokumentasinya pemeriksaan, yaitu menyediakan fasilitas unggah foto sebagai bukti dan dokumentasi kondisi GWT, kolam air bersih, dan input bulanan beserta keterangan penjelasannya.

**Tujuan ketiga** adalah penyimpanan histori, yaitu menyimpan seluruh hasil pemeriksaan dalam basis data agar dapat ditelusuri kembali berdasarkan tanggal, waktu, lokasi, sesi, kondisi, dan petugas.

**Tujuan keempat** adalah pemantauan terpusat, yaitu menyediakan dashboard yang menampilkan ringkasan kondisi monitoring secara terpusat, mencakup jumlah pemeriksaan, sebaran kondisi, status terakhir per lokasi, grafik visualisasi, serta catatan peringatan.

**Tujuan kelima** adalah identifikasi kondisi tidak normal, yaitu memberikan penanda, peringatan, dan indikator visual apabila kondisi yang ditemukan petugas bukan Normal, sehingga pengelola dapat segera menindaklanjuti.

**Tujuan keenam** adalah pelaporan yang akuntabel, yaitu menyediakan laporan bulanan lengkap dengan foto dan ringkasan kondisi yang dapat diekspor ke format PDF sehingga siap dicetak dan diarsipkan.

---

# KELOMPOK B — RUANG LINGKUP, AKTOR, DAN PROSES BISNIS

---

## 6. RUANG LINGKUP SISTEM VERSI 2.0

Versi 2.0 mencakup sembilan modul utama, yang seluruhnya telah diimplementasikan dan dapat dibuktikan keberadaannya melalui berkas pengendali, berkas model, berkas permintaan formulir, dan berkas tampilan yang berkorelasi dengan masing-masing modul.

**Modul pertama** adalah Autentikasi, yang menangani proses login, pembatasan percobaan login, logout, serta penjagaan sesi pengguna.

**Modul kedua** adalah Dashboard Monitoring, yang menjadi halaman utama setelah login dan menampilkan ringkasan kondisi, grafik, tabel status terakhir per lokasi, tabel kepatuhan sesi, riwayat pemeriksaan terbaru, dan ringkasan laporan bulanan.

**Modul ketiga** adalah Monitoring Harian, yang mencatat hasil pemeriksaan GWT dan kolam air bersih sebanyak tiga kali sehari, lengkap dengan foto, keterangan, dan pemilihan kondisi.

**Modul keempat** adalah Laporan Bulanan, yang mencatat kondisi air untuk keperluan pelaporan keuangan, dilengkapi filter berbasis bulan dan ringkasan kondisi per kategori.

**Modul kelima** adalah Manajemen Lokasi Monitoring, yang mengelola master lokasi pemeriksaan harian beserta status aktif atau nonaktifnya.

**Modul keenam** adalah Manajemen Lokasi Bulanan, yang mengelola master lokasi input bulanan, terpisah dari master lokasi monitoring harian.

**Modul ketujuh** adalah Manajemen Pengguna, yang mengelola akun beserta peran aksesnya, dengan berbagai pengaman pada saat penghapusan akun.

**Modul kedelapan** adalah Keamanan Foto Bukti, yang mengatur penyimpanan foto pada disk privat beserta endpoint penyajian foto yang terautentikasi.

**Modul kesembilan** adalah Ekspor PDF Laporan Bulanan, yang mengubah data laporan bulanan menjadi dokumen PDF siap cetak lengkap dengan foto, ringkasan, dan blok tanda tangan.

---

## 7. BATASAN SISTEM VERSI 2.0

Sebagaimana versi 1.0, versi 2.0 ini tetap tidak menggunakan sensor IoT, ESP32, sensor debit air, sensor tekanan air, maupun sensor level GWT. Sistem tidak melakukan pengukuran debit atau tekanan air secara otomatis. Seluruh kondisi ditentukan berdasarkan hasil pemeriksaan visual yang dilakukan langsung oleh petugas di lapangan, bukan dari pembacaan sensor. Sistem berfungsi sebagai media digital untuk pencatatan, dokumentasi, penyimpanan, dan monitoring hasil pemeriksaan.

Selain itu, versi 2.0 juga secara eksplisit tidak mengirim notifikasi eksternal seperti surel atau pesan instan, tidak melakukan kontrol pompa otomatis, serta tidak menerapkan prediksi berbasis kecerdasan buatan maupun machine learning. Peringatan kondisi pada sistem bersifat pasif, yaitu ditampilkan di dalam antarmuka aplikasi dan tidak dikirimkan ke luar sistem.

---

## 8. AKTOR DAN MATRIKS HAK AKSES

Sistem memiliki dua aktor utama dengan peran yang saling melengkapi.

**Admin atau Pengelola** berperan sebagai pengelola sistem. Admin memiliki akses penuh ke seluruh data monitoring harian milik semua petugas, akses penuh ke data laporan bulanan milik semua petugas, kemampuan melihat dashboard lengkap, melakukan filter data, mengekspor PDF, mengelola master lokasi monitoring, mengelola master lokasi bulanan, serta mengelola pengguna. Admin juga dapat mengubah dan menghapus data pemeriksaan serta data laporan bulanan milik petugas mana pun.

**Petugas Sarpras** berperan sebagai petugas lapangan. Petugas dapat melakukan input pemeriksaan harian, input laporan bulanan, melihat histori miliknya sendiri, melihat dashboard yang otomatis dibatasi pada datanya sendiri, serta mengubah dan menghapus datanya sendiri. Petugas tidak dapat mengelola master lokasi maupun pengguna, dan tidak dapat melihat data milik petugas lain.

| Kemampuan | Admin | Petugas |
|---|---|---|
| Login dan logout | Ya | Ya |
| Dashboard Monitoring | Seluruh data | Data sendiri |
| Input Monitoring Harian | Tidak | Ya |
| Input Laporan Bulanan | Tidak | Ya |
| Lihat Histori Monitoring | Seluruh data | Data sendiri |
| Lihat Riwayat Bulanan | Seluruh data | Data sendiri |
| Ubah data pemeriksaan | Seluruh data | Data sendiri |
| Hapus data pemeriksaan | Seluruh data | Data sendiri |
| Ekspor PDF Laporan Bulanan | Ya | Tidak |
| Kelola Lokasi Monitoring | Ya | Tidak |
| Kelola Lokasi Bulanan | Ya | Tidak |
| Kelola Pengguna | Ya | Tidak |

Sistem menerapkan pembatasan cakupan data atau *data scoping* secara berlapis. **Pembatasan pertama** adalah pembatasan pada tingkat rute melalui middleware peran atau *role* yang membatasi halaman mana yang boleh diakses oleh role tertentu. **Pembatasan kedua** adalah pembatasan pada tingkat kueri, di mana untuk role petugas seluruh kueri otomatis difilter berdasarkan identitas pengguna yang sedang login sehingga petugas secara otomatis hanya melihat data miliknya sendiri. **Pembatasan ketiga** adalah pembatasan pada tingkat aksi, di mana ketika petugas mencoba membuka, mengubah, atau menghapus data milik petugas lain, sistem akan menampilkan galat dengan kode HTTP 403 Forbidden beserta pesan yang menjelaskan alasannya. **Pembatasan keempat** adalah pembatasan pada tingkat berkas, di mana file foto bukti tidak dapat diakses secara langsung melainkan harus melalui endpoint yang memverifikasi hak akses sebelum mengirim berkas.

Uraian di atas secara teknis diwujudkan melalui empat lapis berikut. Lapis pertama adalah pendefinisian rute pada berkas `routes/web.php` yang mengelompokkan setiap rute ke dalam middleware `auth`, `role:admin`, atau `role:petugas`. Lapis kedua adalah implementasi *query scoping* pada pengendali, misalnya `when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id))` yang berlaku seragam di Dashboard, Histori Monitoring, dan Riwayat Bulanan. Lapis ketiga adalah pemeriksaan kepemilikan di dalam method pengendali melalui kondisi `abort(403, ...)` untuk aksi melihat, mengubah, dan menghapus. Lapis keempat adalah `PhotoController` yang menolak permintaan foto bila pengguna bukan admin dan bukan pemilik data.

---

## 9. PERSONA DAN SKENARIO KERJA

**Persona pertama** adalah Petugas Sarana dan Prasarana. Petugas merupakan tenaga lapangan yang bekerja setiap hari keliling ke seluruh lokasi untuk melakukan pemeriksaan. Petugas umumnya menggunakan smartphone. Prioritas utama petugas adalah kemudahan akses, yaitu formulir yang sederhana dan cepat diisi langsung di lokasi dengan kemampuan mengambil foto secara langsung dari kamera perangkat. Petugas juga memerlukan kepastian bahwa input yang sudah tersimpan tidak akan hilang, dan memerlukan tampilan ringkas yang dapat dibaca dengan cepat di layar kecil.

**Persona kedua** adalah Admin atau Pengelola yang bekerja di kantor. Admin memerlukan gambaran menyeluruh tentang kondisi air di seluruh lokasi. Prioritas utama admin adalah kemampuan melihat ringkasan cepat melalui dashboard, menemukan catatan yang perlu perhatian, melakukan filter data, dan menyusun laporan bulanan siap cetak. Admin juga memerlukan kemampuan mengelola data master dan akun pengguna secara mandiri.

### 9.1 Skenario Petugas pada Sesi Pagi

Petugas datang ke lokasi GWT pada pagi hari. Petugas melakukan pemeriksaan visual, menemukan kondisi air normal, mengambil foto menggunakan kamera ponsel, lalu membuka aplikasi, login, memilih menu Input Monitoring, mengisi formulir dengan memilih lokasi GWT, tanggal hari ini, sesi Pagi, kondisi Normal, keterangan, dan mengunggah foto. Petugas menekan tombol Simpan Pemeriksaan. Data beserta foto disimpan ke dalam basis data dan penyimpanan privat. Petugas kemudian dialihkan ke dashboard dan melihat bahwa pemeriksaan pagi sudah tercatat pada kartu Cakupan Sesi.

### 9.2 Skenario Petugas pada Sesi Siang dengan Temuan Tidak Normal

Pada sesi siang, petugas kembali ke lokasi yang sama dan menemukan bahwa debit air terlihat lebih kecil dibandingkan pemeriksaan pagi. Petugas mengisi formulir dengan kondisi Debit Turun dan keterangan yang menjelaskan perbedaannya. Setelah menekan tombol Simpan, sistem menampilkan pesan berhasil disimpan sekaligus peringatan bahwa kondisi terdeteksi dan perlu perhatian. Dashboard kemudian menampilkan catatan tersebut pada panel peringatan berwarna merah beserta tautan untuk melihat detailnya.

### 9.3 Skenario Admin Menyusun Laporan Bulanan

Pada akhir bulan, admin membuka dashboard, memilih bulan yang dikehendaki pada bagian Laporan Bulanan, dan meninjau ringkasan jumlah input beserta sebaran kondisi. Admin kemudian membuka halaman Laporan Bulanan, memastikan filter bulan sudah sesuai, dan menekan tombol Export PDF. Sistem menghasilkan dokumen PDF berorientasi mendatar yang memuat kop unit, ringkasan kondisi, tabel data beserta foto, blok tanda tangan petugas pencatat, dan catatan waktu pencetakan.

---

## 10. PROSES BISNIS END-TO-END

### 10.1 Alur Pemeriksaan Harian

Proses pemeriksaan harian dilakukan sebanyak tiga kali dalam satu hari untuk setiap lokasi. Secara umum, petugas datang ke lokasi, melakukan pemeriksaan visual terhadap kondisi air pada GWT atau kolam air bersih, mengambil foto sebagai bukti dokumentasi, menentukan kondisi apakah Normal, Debit Turun, atau Tekanan Air Kecil, lalu mengisi keterangan penjelasannya. Setelah itu petugas membuka aplikasi melalui smartphone, login ke sistem, memilih menu Input Monitoring, mengisi formulir yang memuat pilihan lokasi, tanggal, sesi, kondisi, keterangan, dan foto, lalu menekan tombol Simpan. Data beserta foto disimpan ke dalam basis data dan penyimpanan privat. Petugas dialihkan ke dashboard, dan dashboard menampilkan ringkasan serta peringatan sesuai kondisi yang dipilih.

### 10.2 Alur Input Laporan Bulanan

Alur input laporan bulanan serupa dengan alur pemeriksaan harian, namun tidak memiliki konsep sesi. Petugas membuka menu Laporan Bulanan, lalu memilih Input Bulanan. Petugas mengisi tanggal, lokasi, kondisi, keterangan, dan foto, lalu menekan tombol Simpan. Data beserta foto disimpan. Petugas dialihkan ke halaman riwayat bulanan dengan bulan yang disesuaikan secara otomatis. Untuk role admin, data bulanan dapat diekspor ke format PDF.

### 10.3 Alur Pengelolaan Data Master

Alur pengelolaan data master hanya dapat dilakukan oleh admin. Admin membuka menu Monitoring Harian, lalu memilih Lokasi Monitoring, untuk menambah, mengubah, atau menghapus lokasi pemeriksaan harian. Admin membuka menu Laporan Bulanan, lalu memilih Lokasi Bulanan, untuk menambah, mengubah, atau menghapus lokasi input bulanan. Admin membuka menu Kelola Pengguna untuk menambah, mengubah, atau menghapus akun pengguna.

### 10.4 Aturan Integritasreferensial pada Alur Penghapusan

Penghapusan data master lokasi tidak dapat dilakukan apabila masih memiliki data turunan. Penghapusan lokasi monitoring ditolak apabila lokasi tersebut masih memiliki data pemeriksaan. Penghapusan lokasi bulanan ditolak apabila lokasi tersebut masih memiliki data laporan bulanan. Penghapusan akun pengguna ditolak apabila akun tersebut merupakan akun sendiri yang sedang digunakan, merupakan admin terakhir dalam sistem, masih memiliki data pemeriksaan, atau masih memiliki data laporan bulanan. Seluruh penolakan tersebut disertai pesan galat dalam Bahasa Indonesia yang jelas dan mudah dipahami.

---

# KELOMPOK C — MODUL SISTEM

---

## 11. MODUL AUTENTIKASI

Modul autentikasi menangani seluruh proses login dan logout. Halaman login menampilkan kelompok logo organisasi, meliputi logo Jaya Raya, logo DPRKP, dan logo UPRS VI yang ditata dalam kelompok logo dengan penataan khusus agar tetap proporsional pada berbagai ukuran layar. Formulir login memuat dua field, yaitu email dan password, keduanya bersifat wajib.

Ketika pengguna menekan tombol masuk, sistem melakukan validasi email dan password. Apabila validasi berhasil, sistem memeriksa peran pengguna. Hanya pengguna dengan peran admin atau petugas yang diizinkan masuk. Jika pengguna memiliki peran lainnya, sistem mengeluarkan pengguna tersebut dari sesi login dan menampilkan pesan bahwa akun tidak memiliki akses ke sistem. Apabila validasi gagal, sistem menampilkan pesan bahwa email atau password salah.

Untuk mencegah serangan *brute force*, sistem menerapkan pembatasan percobaan login. Apabila pengguna melakukan kesalahan login sebanyak lima kali berturut-turut, sistem mengunci percobaan login selama enam puluh detik. Kunci pembatasan ini didasarkan pada kombinasi email dan alamat IP, sehingga pengguna berbeda yang beralamat IP sama tidak saling memengaruhi. Pesan penguncian menampilkan sisa waktu tunggu dalam satuan detik atau menit sesuai durasinya.

Apabila percobaan login berhasil, sistem melakukan regenerasi sesi untuk mencegah serangan *session fixation*. Apabila pengguna logout, sistem menginvalidasi sesi dan memperbarui token CSRF. Halaman beranda aplikasi mengarahkan pengguna yang belum login ke halaman login, dan pengguna yang sudah login mengakses dashboard.

Secara teknis, modul ini diimplementasikan pada `AuthController` yang menerapkan dua konstanta, yaitu jumlah percobaan login maksimal sebanyak lima kali dan durasi penundaan selama enam puluh detik. Validasi kredensial menggunakan aturan standar email dan password, sedangkan penolakan peran dilakukan dengan `in_array($user->role, ['admin', 'petugas'])`. Untuk menjaga ketahanan terhadap input yang tidak berupa teks, pembentuk kunci pembatasan login memeriksa apakah nilai email merupakan string sebelum digunakan, sehingga error 500 yang pernah terjadi pada kasus email non-string dapat dihindari.

---

## 12. MODUL DASHBOARD MONITORING

Dashboard monitoring merupakan halaman utama yang ditampilkan setelah pengguna berhasil login, dan dapat diakses oleh kedua role. Halaman ini memberikan gambaran ringkas mengenai kondisi monitoring air. Seluruh data yang ditampilkan untuk role petugas otomatis dibatasi pada data miliknya sendiri, sedangkan untuk role admin mencakup seluruh data milik semua petugas.

### 12.1 Filter Periode

Dashboard dilengkapi dengan kontrol filter periode yang memungkinkan pengguna memilih rentang tanggal yang ingin dianalisis. Pengguna dapat memilih tanggal mulai dan tanggal akhir secara manual menggunakan kontrol input bertipe tanggal. Selain itu tersedia tiga pintasan periode yang dapat dipilih sekali klik, yaitu Hari Ini, 7 Hari Terakhir, dan Bulan Ini. Rentang tujuh hari terakhir dihitung secara inklusif, yaitu mencakup tujuh hari kalender termasuk hari ini. Rentang bulan ini dihitung dari tanggal satu pada bulan berjalan hingga hari ini. Tanggal akhir yang dipilih tidak dapat melebihi hari ini, dan tanggal mulai tidak dapat melewati tanggal akhir. Seluruh perhitungan statistik berbasis periode, seperti cakupan sesi, persentase normal, dan data grafik, mengikuti rentang tanggal yang dipilih.

### 12.2 Enam Kartu Statistik

Dashboard menampilkan enam kartu statistik.

**Kartu pertama** adalah Total Pemeriksaan, yang menampilkan jumlah keseluruhan pemeriksaan sepanjang masa dari semua periode. Kartu ini tidak terpengaruh oleh filter periode dan menjadi indikator volume aktivitas pemeriksa secara kumulatif.

**Kartu kedua** adalah Pemeriksaan Periode, yang menampilkan jumlah pemeriksaan yang terjadi dalam rentang tanggal yang sedang dipilih. Kartu ini menjadi indikator aktivitas pada periode yang difokuskan.

**Kartu ketiga** adalah Cakupan Sesi, yang menampilkan persentase sesi pemeriksaan yang sudah terisi dibandingkan jumlah sesi yang mungkin terjadi. Perhitungan dilakukan dengan membandingkan jumlah pemeriksaan dalam periode terhadap hasil kali antara jumlah lokasi aktif, jumlah hari dalam periode, dan jumlah sesi per hari. Nilai cakupan sesi dibatasi maksimal seratus persen agar tidak melebihi nilai logis.

**Kartu keempat** adalah Lokasi Aktif, yang menampilkan jumlah lokasi monitoring yang berstatus aktif. Kartu ini menjadi indikator cakupan master data yang siap diperiksa.

**Kartu kelima** adalah Catatan Perlu Perhatian, yang menampilkan jumlah catatan pemeriksaan yang memiliki kondisi bukan Normal. Kartu ini diberi penanda visual warna merah, border merah, dan label ajakan untuk segera diperiksa.

**Kartu keenam** adalah Persentase Normal Periode, yang menampilkan persentase pemeriksaan yang berstatus Normal dibandingkan seluruh pemeriksaan dalam periode yang dipilih. Kartu ini diberi penanda visual warna hijau dan border hijau.

### 12.3 Panel Peringatan

Apabila terdapat catatan pemeriksaan yang kondisinya bukan Normal, dashboard menampilkan panel peringatan berwarna merah di bagian atas halaman. Panel ini menampilkan daftar seluruh catatan yang perlu perhatian beserta nama lokasi, kondisi, tanggal, dan waktunya. Setiap catatan pada panel peringatan dilengkapi tautan Lihat Detail yang mengarahkan pengguna ke halaman detail pemeriksaan. Catatan pada panel peringatan diurutkan berdasarkan tanggal dan waktu secara menaik dari yang paling lama, sehingga temuan tertua terlihat lebih dahulu dan mudah ditindaklanjuti.

### 12.4 Dua Grafik Visualisasi

Dashboard menyediakan dua grafik visualisasi menggunakan pustaka Chart.js.

**Grafik pertama** adalah grafik lingkaran *doughnut* yang menampilkan sebaran kondisi pemeriksaan dalam periode yang dipilih. Grafik ini membagi data ke dalam tiga kategori, yaitu Normal, Debit Turun, dan Tekanan Air Kecil, dengan warna berbeda untuk setiap kategori agar mudah dibedakan. Di bawah grafik ditampilkan ringkasan jumlah untuk setiap kategori, dilengkapi lencana kondisi dengan warna yang sama dengan warna irisan pada grafik.

**Grafik kedua** adalah grafik batang bertumpuk *stacked bar chart* yang menampilkan jumlah pemeriksaan per kondisi untuk setiap lokasi dalam periode yang dipilih. Setiap batang lokasi terdiri atas tiga bagian yang ditumpuk secara vertikal sesuai jumlah pemeriksaan pada masing-masing kondisi. Sumbu vertikal dimulai dari nol dengan nilai bilangan bulat. Grafik ini berguna untuk melihat perbandingan kondisi antar lokasi secara visual dalam satu tampilan.

### 12.5 Tabel Status Terakhir per Lokasi

Dashboard menampilkan tabel Status Terakhir per Lokasi yang menunjukkan kondisi pemeriksaan terakhir yang tercatat untuk setiap lokasi. Tabel ini dihitung menggunakan fungsi jendela `ROW_NUMBER` yang mempartisi data berdasarkan lokasi dan mengurutkan berdasarkan tanggal dan waktu secara menurun, sehingga baris pertama pada setiap partisi lokasi merupakan pemeriksaan terakhir. Tabel ini menampilkan kolom Lokasi, Status, Terakhir, dan Petugas. Kolom Petugas hanya ditampilkan untuk role admin. Bagi lokasi yang belum memiliki data pemeriksaan sama sekali, sistem menampilkan label Belum ada pada kolom Status dan tanda pisah pada kolom waktu.

### 12.6 Tabel Kepatuhan Sesi Lokasi

Dashboard menampilkan tabel Kepatuhan Sesi Lokasi yang berfungsi untuk memonitor seberapa lengkap pemeriksaan yang telah dilakukan untuk setiap lokasi. Tabel ini menampilkan jumlah pemeriksaan yang telah tercatat untuk setiap sesi, yaitu Pagi, Siang, dan Sore, dalam periode yang dipilih. Setiap sel sesi menampilkan format jumlah pemeriksaan terhadap jumlah hari dalam periode. Sel sesi yang sudah lengkap diberi warna hijau, sedangkan sel yang belum lengkap diberi warna kuning dengan teks gelap. Kolom Status pada tabel ini menampilkan label Lengkap apabila seluruh sesi pada lokasi tersebut sudah terisi sesuai target, atau menampilkan label Kurang beserta jumlah sesi yang belum terisi. Kaki tabel memuat penjelasan target, yaitu tiga sesi per hari dikalikan jumlah hari dalam periode untuk setiap lokasi. Tabel ini menjadi alat bantu bagi admin untuk memastikan seluruh lokasi telah diperiksa secara lengkap.

### 12.7 Tabel Riwayat Pemeriksaan Terbaru

Dashboard menampilkan tabel Riwayat Pemeriksaan Terbaru yang berisi sepuluh data pemeriksaan terbaru, diurutkan berdasarkan tanggal, waktu, dan identitas data secara menurun. Tabel ini menampilkan kolom Tanggal, Waktu, Lokasi, Sesi, Kondisi, Keterangan, dan Petugas. Kolom Petugas hanya ditampilkan untuk role admin. Kolom Keterangan ditampilkan secara ringkas dengan pembatasan jumlah karakter agar tabel tidak terlalu lebar. Tabel ini dilengkapi tautan Lihat Semua yang mengarahkan pengguna ke halaman histori.

### 12.8 Bagian Laporan Bulanan pada Dashboard

Bagian bawah dashboard menampilkan ringkasan Laporan Bulanan. Bagian ini dilengkapi dengan pengatur bulan yang dapat dipilih melalui kontrol input bertipe bulan. Ketika pengguna mengubah bulan dan menekan tombol Terapkan, bagian ini dimuat ulang secara asinkron tanpa memuat ulang seluruh halaman. Mekanisme ini dilakukan melalui permintaan parsial ke endpoint khusus yang mengembalikan potongan tampilan, kemudian potongan tersebut menggantikan isi bagian terkait, sementara URL halaman diperbarui agar pilihan bulan tetap tersimpan ketika halaman dimuat ulang. Apabila permintaan gagal, tombol akan dikembalikan ke kondisi semula dan halaman dimuat ulang penuh sebagai cadangan.

Bagian ini menampilkan empat kartu angka, yaitu Total Input dan jumlah untuk setiap kategori kondisi. Untuk role admin tersedia tombol Lihat Laporan dan Export PDF. Untuk role petugas tersedia tombol Input Bulanan dan Riwayat Saya. Bagian ini juga menampilkan tabel lima input terbaru pada bulan yang dipilih, dengan kolom Tanggal, Kondisi, Lokasi, Keterangan, serta kolom Petugas hanya untuk role admin. Apabila belum ada data pada bulan tersebut, sistem menampilkan pesan bahwa belum ada input bulanan pada bulan tersebut.

---

## 13. MODUL MONITORING HARIAN

Modul Monitoring Harian merupakan modul utama dari sistem. Modul ini digunakan untuk mencatat hasil pemeriksaan kondisi GWT dan kolam air bersih. Pemeriksaan dilakukan tiga kali dalam satu hari untuk setiap lokasi. Modul ini hanya dapat diakses untuk melakukan input baru oleh role petugas. Admin tidak memiliki hak untuk melakukan input pemeriksaan baru, namun admin tetap dapat melihat, mengubah, dan menghapus data pemeriksaan yang telah tercatat.

### 13.1 Form Input Monitoring

Form input monitoring memuat lima field wajib dan satu field otomatis. **Field pertama** adalah Lokasi, berupa daftar tarik turun yang hanya menampilkan lokasi berstatus aktif. **Field kedua** adalah Tanggal, berupa kontrol tanggal dengan nilai bawaan berupa tanggal hari ini dan batas maksimal tidak melebihi hari ini. **Field ketiga** adalah Sesi Pemeriksaan, berupa daftar tarik turun yang menampilkan label sesi beserta waktu terkait, misalnya Pagi yang berkaitan dengan pukul 08.00, Siang dengan pukul 12.00, dan Sore dengan pukul 16.00. **Field keempat** adalah Kondisi, berupa daftar tarik bawah dengan tiga pilihan sesuai katalog kondisi. **Field kelima** adalah Keterangan, berupa kotak teks untuk penjelasan hasil pemeriksaan.

**Field foto** berupa kontrol berkas dengan atribut pengambilan langsung yang memungkinkan petugas mengambil foto menggunakan kamera perangkat secara langsung. Bantuannya menyebutkan format JPG, JPEG, atau PNG dengan ukuran maksimal lima megabyte. **Field Petugas** tidak dapat diisi langsung oleh pengguna, melainkan ditampilkan secara otomatis dalam bentuk kotak informasi hanya baca berisi nama pengguna yang sedang login. **Field waktu** tidak ditampilkan sebagai field input karena sistem menentukannya secara otomatis di sisi server berdasarkan sesi yang dipilih, sehingga petugas tidak dapat memanipulasi waktu pemeriksaan secara bebas.

### 13.2 Urutan Eksekusi Penyimpanan

Penyimpanan data pemeriksaan dilakukan dengan urutan yang embroidered terhadap integritas data. Pertama, berkas foto disimpan terlebih dahulu di penyimpanan privat. Kedua, apabila penyimpanan foto gagal, sistem menghentikan proses, mempertahankan input pengguna, dan menampilkan pesan bahwa foto bukti gagal disimpan. Ketiga, baru setelah foto berhasil disimpan, sistem melakukan penyimpanan baris data ke dalam basis data. keempat, apabila penyimpanan basis data gagal, sistem menghapus berkas foto yang baru saja disimpan agar tidak tertiary buta, kemudian meneruskan galat. Dengan urutan ini, sistem tidak pernah meninggalkan berkas foto yatim tanpa baris data yang merujuknya.

### 13.3 Pencegahan Data Duplikat

Sistem mencegah pencatatan pemeriksaan yang sama dua kali atau lebih. Aturan ini ditegakkan pada dua lapisan.

**Lapisan pertama** adalah validasi di tingkat aplikasi yang memeriksa apakah kombinasi lokasi, tanggal, dan sesi sudah pernah tercatat. Jika kombinasi tersebut sudah ada, sistem menolak dan menampilkan pesan bahwa pemeriksaan untuk lokasi dan sesi tersebut sudah dilakukan. Cakupan validasi ini tidak membedakan identitas petugas, sehingga apabila satu petugas telah mengisi suatu slot pemeriksaan, petugas lain tidak dapat mengisi slot yang sama pada lokasi dan tanggal tersebut.

**Lapisan kedua** adalah batasan integritas di tingkat basis data melalui indeks unik yang menggabungkan kolom lokasi, tanggal, dan sesi. Dengan demikian, apabila ada permintaan yang lolos dari validasi aplikasi, basis data akan tetap menolak penyisipan duplikat. Validasi yang terjadi pada tingkat basis data menjadi jaminan terakhir yang memastikan konsistensi data.

### 13.4 Edit dan Hapus Data

Role admin dan petugas dapat melakukan perubahan atas data pemeriksaan. Petugas hanya dapat mengubah data yang merupakan miliknya sendiri, sedangkan admin dapat mengubah seluruh data pemeriksaan. Ketika melakukan perubahan, field foto bersifat opsional. Jika pengguna tidak mengunggah foto baru, maka foto lama akan dipertahankan. Jika pengguna mengunggah foto baru, maka foto baru akan menggantikan foto lama dan foto lama akan dihapus dari penyimpanan. Waktu pemeriksaan akan diperbarui secara otomatis mengikuti sesi baru yang dipilih. Identitas petugas tidak dapat diubah setelah data dibuat. Aksi hapus menghapus data pemeriksaan beserta berkas foto yang terkait, dan menghapus baris basis data terlebih dahulu sebelum menghapus berkas, apabila penghapusan berkas gagal, data tetap dianggap terhapus dan sistem tetap menampilkan pesan berhasil karena penghapusan berkas bersifat usaha terbaik. Untuk menjaga keamanan data, pengguna diminta untuk mengonfirmasi melalui dialog konfirmasi sebelum penghapusan dilakukan.

---

## 14. KATALOG KONDISI

Sistem memiliki dua katalog kondisi yang berbeda dan digunakan pada dua modul yang berbeda. Perbedaan ini disengaja dan penting untuk dipahami, karena kedua modul memiliki konteks penggunaan yang berbeda.

### 14.1 Katalog Kondisi Monitoring Harian

Kondisi pada monitoring harian terdiri dari tiga kategori. **Kondisi pertama** adalah Normal, yang menunjukkan bahwa kondisi air berada dalam keadaan yang baik berdasarkan hasil pemeriksaan. **Kondisi kedua** adalah Debit Turun, yang digunakan apabila petugas menemukan bahwa debit air mengalami penurunan. **Kondisi ketiga** adalah Tekanan Air Kecil, yang digunakan apabila petugas menemukan bahwa tekanan air terasa lebih kecil. Seluruh kondisi pada monitoring harian berasal dari penilaian petugas di lapangan dan bukan dari pembacaan sensor.

Nilai kondisi yang disimpan di dalam basis data untuk monitoring harian adalah `normal`, `debit_turun`, dan `tekanan_air_kecil`. Label yang ditampilkan pada antarmuka adalah Normal, Debit Turun, dan Tekanan Air Kecil. Daftar ini didefinisikan sebagai konstanta pada model `WaterMonitoring` sehingga menjadi satu sumber kebenaran tunggal yang dipakai bersama oleh pengendali, permintaan formulir, dan seluruh berkas tampilan.

### 14.2 Katalog Kondisi Laporan Bulanan

Kondisi pada laporan bulanan juga terdiri dari tiga kategori namun memiliki penamaan yang berbeda. **Kondisi pertama** adalah Normal. **Kondisi kedua** adalah Debit Air Kurang. **Kondisi ketiga** adalah Tekanan PDAM Kurang. Penamaan ini berkaitan dengan konteks laporan yang berkaitan dengan kondisi jaringan PDAM. Daftar ini didefinisikan sebagai konstanta pada model `FinanceReport`.

### 14.3 Catatan Penting tentang Dua Katalog Tersebut

Walaupun kedua katalog memuat jumlah kategori yang sama, keduanya **tidak boleh diperlakukan sebagai satu katalog yang sama**. Perbedaan paling penting terletak pada nilai di dalam basis data. Monitoring Harian menggunakan `debit_turun` dan `tekanan_air_kecil`, sedangkan Laporan Bulanan menggunakan `debit_air_kurang` dan `tekanan_pdam_kurang`. Nilai `debit_turun` tidak pernah muncul di dalam tabel laporan bulanan, dan nilai `debit_air_kurang` tidak pernah muncul di dalam tabel pemeriksaan. Konsekuensinya, statistik dan grafik kedua modul tidak dapat saling dijumlahkan, dan validasi pada satu modul tidak dapat digunakan untuk memvalidasi modul yang lain.

---

## 15. MODUL HISTORI DAN FILTER

Modul histori menyediakan halaman yang menampilkan seluruh data pemeriksaan yang telah tercatat. Halaman ini dapat diakses oleh kedua role. Untuk role petugas, halaman ini menampilkan heading Histori Pemeriksaan Saya, sedangkan untuk role admin halaman ini menampilkan heading Histori Monitoring. Perbedaan heading ini mencerminkan cakupan data yang berbeda. Tombol Input Monitoring hanya ditampilkan untuk role petugas, karena admin tidak melakukan input pemeriksaan baru. Sebaliknya, kolom Petugas pada tabel hanya ditampilkan untuk role admin.

### 15.1 Kolom Tabel Histori

Halaman histori menampilkan tabel yang memuat kolom nomor urut, tanggal, waktu, lokasi, sesi, kondisi, keterangan, foto, petugas, dan aksi. Nomor urut bersifat kontinu lintas halaman, dimulai dari satu. Tanggal ditampilkan dalam format hari, bulan, dan empat digit tahun, waktu ditampilkan dalam format dua digit jam dan menit. Lokasi menampilkan nama lokasi sesuai master data. Sesi menampilkan label sesi beserta nama aslinya apabila nilai sesi di luar katalog. Kondisi ditampilkan menggunakan lencana berwarna yang berbeda untuk setiap kategori, yaitu hijau untuk Normal dan kuning dengan teks gelap untuk kondisi lainnya. Keterangan ditampilkan secara ringkas dengan pembatasan empat puluh karakter agar tabel tidak terlalu lebar. Kolom foto menyediakan tombol Lihat yang menampilkan foto dalam jendela modal, bukan membuka berkas secara langsung. Kolom aksi menyediakan tombol Detail, tombol Edit, dan tombol hapus yang meminta konfirmasi.

### 15.2 Filter Data Histori

Halaman histori dilengkapi dengan dua kontrol filter untuk mempersempit data yang ditampilkan.

**Filter lokasi** menampilkan daftar seluruh lokasi termasuk lokasi yang berstatus nonaktif, agar admin tetap dapat menelusuri histori pemeriksaan pada lokasi yang saat ini sudah dinonaktifkan. Opsi pertama berupa pilihan Semua Lokasi dengan nilai kosong.

**Filter tanggal** menampilkan data pada tanggal tertentu, dengan cara melakukan pencocokan tanggal tanpa memperhitungkan waktu.

Tombol Reset ditampilkan hanya apabila salah satu filter sedang aktif, dan mengembalikan pengguna ke tampilan data tanpa filter. Hasil filter mempertahankan nilai filter pada URL sehingga tampilan dapat dibagikan atau dipulihkan ketika halaman dimuat ulang.

### 15.3 Pengurutan dan Paginasi

Data hasil filter diurutkan berdasarkan tanggal secara menurun dan waktu secara menurun, sehingga pemeriksaan terbaru berada di posisi teratas. Tabel histori menggunakan paginasi dengan jumlah data per halaman sebesar sepuluh baris, dan menampilkan penomoran halaman apabila data melebihi satu halaman. Perilaku penomoran halaman menggunakan komponen paginasi Bootstrap 5, yang disetel secara eksplisit pada penyedia layanan aplikasi agar konsisten dengan kerangka antarmuka yang digunakan.

---

## 16. MODUL DETAIL PEMERIKSAAN

Halaman detail pemeriksaan menampilkan seluruh informasi tentang satu hasil pemeriksaan. Informasi yang ditampilkan meliputi lokasi, tanggal, waktu, sesi beserta jamnya, petugas, kondisi, status, keterangan, dan foto dokumentasi. Foto ditampilkan dalam ukuran penuh pada halaman.

Halaman ini memiliki URL yang dapat dibagikan sehingga alamat halaman ini dapat dikirimkan kepada pihak lain, misalnya kepada pengelola atau comrade. Halaman detail dapat diakses oleh kedua role. Admin dapat membuka seluruh data pemeriksaan. Petugas hanya dapat membuka data yang merupakan miliknya sendiri. Apabila petugas mencoba membuka data milik petugas lain, sistem menampilkan galat dengan kode HTTP 403 dan pesan bahwa pengguna tidak memiliki akses untuk melihat data tersebut.

Halaman detail juga menampilkan tombol Edit yang hanya ditampilkan apabila pengguna memiliki hak akses terhadap data tersebut, yaitu admin atau pemilik data. Tombol Kembali ke Dashboard dan tombol Kembali ke Histori selalu tersedia untuk membantu navigasi.

### 16.1 Indikator Status pada Halaman Detail

Halaman detail menampilkan baris Status yang secara terpisah dari baris Kondisi, agar pengguna dapat langsung membedakan antara apa yang ditemukan petugas dan bagaimana penilaian sistem terhadap temuan tersebut. indikator kondisi Normal dan status NORMAL, sedangkan untuk kondisi Debit Turun maupun Tekanan Air Kecil, status menjadi PERLU PERHATIAN. Kedua nilai tersebut ditampilkan menggunakan lencana berwarna agar mudah dibedakan secara visual.

---

## 17. MODUL LAPORAN BULANAN

Modul Laporan Bulanan digunakan untuk mencatat kondisi air yang akan dilaporkan secara bulanan. Modul ini memiliki karakteristik yang berbeda dari monitoring harian. Pertama, modul ini tidak memiliki konsep sesi. Kedua, modul ini tidak memiliki pembatasan jumlah data per hari. Ketiga, modul ini menggunakan master lokasi yang terpisah dari monitoring harian, dengan tabel dan pengendali sendiri. Pemisahan ini dimaksudkan agar pencatatan untuk keperluan pelaporan keuangan tidak bercampur dengan pencatatan pemeriksaan harian, dan agar kedua jenis data dapat dikelola secara independen.

### 17.1 Form Input Bulanan

Form input bulanan memuat lima field, yaitu tanggal input, lokasi, kondisi, keterangan, dan foto bukti. Seluruh field bersifat wajib. Field tanggal memiliki nilai bawaan berupa tanggal hari ini dan batas maksimal tidak melebihi hari ini. Field lokasi menampilkan daftar lokasi bulanan berstatus aktif. Field kondisi menampilkan tiga pilihan sesuai katalog kondisi laporan bulanan. Field keterangan berupa kotak teks. Field foto memiliki atribut pengambilan langsung dan dibatasi maksimal lima megabyte.

Identitas petugas diisi otomatis berdasarkan pengguna yang sedang login dan ditampilkan dalam bentuk kotak informasi hanya baca. Setelah data berhasil disimpan, pengguna dialihkan ke halaman riwayat bulanan dengan bulan yang disesuaikan secara otomatis mengikuti tanggal input yang baru disimpan, sehingga petugas langsung melihat data yang baru saja diinputnya pada bulan yang benar. Berbeda dengan monitoring harian yang mengarahkan pengguna ke dashboard, modul ini mengarahkan pengguna ke riwayat agar konteks input bulanan langsung terjaga.

### 17.2 Urutan Eksekusi Penyimpanan Bulanan

Sistem menerapkan urutan eksekusi yang sama dengan monitoring harian, yaitu menyimpan berkas foto terlebih dahulu, menghentikan proses bila gagal, baru kemudian menyimpan baris data, dan menghapus berkas foto yang baru disimpan apabila penyimpanan basis data gagal. Dengan demikian tidak ada berkas foto yatim yang tersisa ketika penyimpanan basis data gagal.

### 17.3 Halaman Riwayat Bulanan untuk Petugas

Halaman riwayat bulanan hanya dapat diakses oleh role petugas. Halaman ini menampilkan data input bulanan milik petugas yang sedang login, dibatasi secara otomatis pada kueri. Halaman ini dilengkapi dengan kontrol filter bulan berupa masukan bulan, beserta tombol Cari dan tombol Reset.

Tabel pada halaman ini menampilkan kolom nomor urut, tanggal input, keterangan dropdown yang menandai kondisi, lokasi, keterangan penjelasan, foto, dan aksi. Kolom petugas tidak ditampilkan karena data sudah otomatis dibatasi pada data sendiri. Tabel ini juga menampilkan ringkasan berupa jumlah total data yang diinputofficer pada bulan terpilih beserta jumlah untuk setiap kategori kondisi. Ringkasan dihitung dengan membatasi kueri pada identitas pengguna yang sedang login, sehingga petugas tidak dapat melihat rekapitulasi milik petugas lain.

### 17.4 Halaman Laporan Bulanan untuk Admin

Halaman laporan bulanan hanya dapat diakses oleh role admin. Halaman ini menampilkan seluruh data input bulanan dari semua petugas, tanpa pembatas identitas. Halaman ini dilengkapi dengan kontrol filter bulan yang sama, beserta tombol Cari dan tombol Reset. Tabel pada halaman ini menampilkan kolom nomor urut, tanggal input, kondisi, lokasi, keterangan, foto, petugas, dan aksi. Kolom petugas ditampilkan karena admin perlu mengetahui siapa yang melakukan pencatatan. Pada bagian atas halaman tersedia tombol Export PDF dengan warna hijau yang mengarahkan pengguna ke modul ekspor PDF.

Ringkasan pada halaman ini menampilkan jumlah total data pada bulan terpilih beserta jumlah untuk setiap kategori kondisi, dihitung tanpa pembatas identitas sehingga mencakup seluruh petugas. Ringkasan ini dihitung menggunakan satu kueri pengelompokan berdasarkan kondisi, lebih efisien dibandingkan perhitungan dengan beberapa kueri penghitungan terpisah.

### 17.5 Peran Admin dan Petugas pada Aksi Ubah dan Hapus

Halaman edit dan aksi hapus data bulanan dapat diakses oleh kedua role dengan pembatasan kepemilikan. Petugas hanya dapat mengubah atau menghapus data miliknya sendiri, sedangkan admin dapat mengubah atau menghapus seluruh data. Field foto pada halaman edit bersifat opsional, dengan penjelasan bahwa apabila dikosongkan maka foto saat ini akan dipertahankan. Halaman edit menampilkan pratinjau foto saat ini sebelum pengguna memilih foto pengganti. Pengalihan setelah pembaruan data bergantung pada peran, yaitu admin diarahkan ke halaman laporan bulanan, sedangkan petugas diarahkan ke halaman riwayat miliknya sendiri.

---

## 18. MODUL EKSPOR PDF LAPORAN BULANAN

Sistem menyediakan kemampuan untuk mengekspor laporan bulanan ke format PDF. Fitur ini hanya dapat diakses oleh role admin. Proses pembuatan PDF menggunakan pustaka pembungkus dompdf yang menghasilkan dokumen dari templat tampilan khusus, bukan dari halaman antarmuka aplikasi.

### 18.1 Format dan Ukuran

Dokumen PDF menggunakan ukuran kertas A4 dengan orientasi mendatar. Orientasi mendatar dipilih untuk memberikan ruang yang lebih luas agar kolom foto dapat ditampilkan pada ukuran yang masih terbaca. Nama berkas hasil unduhan mengikuti pola laporan kondisi air diikuti bulan dan tahun, sehingga pengguna mudah mengenali berkas yang diunduh.

### 18.2 Struktur Dokumen PDF

Dokumen PDF terdiri dari lima bagian.

**Bagian pertama** adalah kop dokumen yang memuat nama unit pengelola, judul laporan, serta bulan dan tahun laporan. Kop dibingkai dengan garis atas dan garis bawah Tebal agar memisahkan bagian identitas dokumen dari isi laporan.

**Bagian kedua** adalah baris ringkasan yang menampilkan jumlah data untuk setiap kategori kondisi dalam satu baris yang dipisahkan titik.

**Bagian ketiga** adalah tabel data yang memuat kolom nomor urut, tanggal input, kondisi air, lokasi, keterangan, dan foto. Lebar setiap kolom ditetapkan secara proporsional, dengan kolom keterangan diberi porsi terbesar karena isinya berupa teks bebas, dan kolom foto diberi porsi cukup untuk menampilkan gambar. Kolom foto pada tabel PDF menampilkan gambar secara langsung. Bagi baris yang tidak memiliki foto, sistem menampilkan teks pengganti berwarna redup sebagai pengganti gambar. Kolom nomor, tanggal, kondisi, dan lokasi ditampilkan rata tengah, sedangkan kolom keterangan ditampilkan rata kiri.

**Bagian keempat** adalah blok tanda tangan yang memuat kolom tanda tangan petugas pencatat dengan garis titik-titik sebagai tempat tanda tangan, dan seluruh blok pemPDT positioned sedemikian rupa agar tidak terpotong antar halaman.

**Bagian kelima** adalah catatan kaki yang menampilkan waktu pencetakan dokumen.

### 18.3 Embed Foto pada PDF

Karena pustaka dompdf tidak dapat membaca berkas foto yang tersimpan pada penyimpanan privat secara langsung melalui URL sistem, foto perlu ditanam ke dalam dokumen PDF dalam bentuk data bertipe base64. Proses pembacaan dan konversi foto ini dilakukan pada saat dokumen dibuat di sisi server. Pembacaan berkas foto dilakukan secara aman dengan penanganan kesalahan yang tidak menyebabkan sistem gagal, apabila berkas foto tidak dapat dibaca, dokumen tetap terbentuk dengan kolom foto kosong. Setiap foto dianalisis berdasarkan ekstensi berkasnya untuk menentukan tipe medianya, dengan nilai cadangan apabila ekstensi tidak dikenali.

### 18.4 Perbedaan antara Tabel PDF dan Tabel HTML

Perlu dicatat bahwa tabel pada dokumen PDF tidak memiliki kolom petugas, sedangkan tabel pada halaman antarmuka web untuk admin memiliki kolom petugas. Perbedaan ini disengaja agar lebar tabel PDF tetap proporsional dan foto tetap terbaca jelas pada orientasi mendatar. Admin tetap dapat mengetahui identitas petugas melalui kolom petugas pada halaman antarmuka.

---

## 19. MODUL KEAMANAN FOTO BUKTI

Modul keamanan foto bukti merupakan salah satu peningkatan keamanan yang telah diterapkan pada versi 2.0. Pada implementasi sebelumnya, foto bukti disimpan pada penyimpanan publik yang dapat diakses oleh siapa saja melalui URL. Pada versi 2.0, foto bukti telah dipindahkan ke penyimpanan privat.

### 19.1 Penyimpanan Privat

Foto bukti disimpan pada disk privat yang secara konfigurasi tidak memiliki URL publik. Folder penyimpanan privat ditempatkan di luar folder penyimpanan publik sehingga tidak dapat diakses melalui struktur folder yang dapat dijangkau oleh peramban. Konfigurasi disk privat menggunakan driver lokal dengan visibilitas privat, dan dikonfigurasi agar kegagalan pada sistem berkas diwulkan sebagai galat yang dapat ditangkap, bukan kesalahan senyap. Aturan ini penting karena pemanggil harus selalu menggunakan pembungkus penanganan galat agar kegagalan tidak menyebabkan halaman error.

Untuk membedakan foto yang sudah berada di disk privat dari foto lama yang masih berada di disk publik, kedua tabel foto diberi kolom tambahan bernama `foto_disk` yang mencatat nama disk tempat berkas berada. Nilai default kolom ini adalah `public` untuk menjaga kompatibilitas dengan data lama, sedangkan seluruh foto baru ditulis dengan nilai `evidence`. Pembacaan nama disk diterapkan secara ketat, yaitu hanya nilai persis `evidence` yang dipetakan ke disk privat, seluruh nilai lainnya dipetakan ke disk publik, sehingga data lama yang kolomnya kosong tetap dapat dibaca.

### 19.2 Penyajian Foto melalui Endpoint Terautentikasi

Foto bukti tidak lagi disajikan sebagai berkas statis. Foto bukti disajikan melalui endpoint khusus yang memverifikasi hak akses sebelum mengirim berkas. Endpoint ini memerlukan autentikasi aktif. Ketika permintaan foto diterima, sistem memeriksa apakah pengguna yang melakukan permintaan memiliki hak akses terhadap data pemeriksaan yang terkait. Admin dapat mengakses seluruh foto, sedangkan petugas hanya dapat mengakses foto miliknya sendiri. Apabila pengguna tidak memiliki hak akses, sistem menampilkan galat dengan kode HTTP 403 beserta pesan yang menjelaskan alasannya.

Endpoint foto ini tersedia secara terpisah untuk foto monitoring harian dan foto laporan bulanan, sehingga keduanya menerapkan aturan verifikasi hak akses yang sama.

### 19.3 Konfigurasi Respons Foto

Respons foto dikirim sebagai berkas langsung dengan header yang dikonfigurasi secara ketat. Tipe konten ditentukan berdasarkan ekstensi berkas foto. Header disposisi dituliskan sebagai tampilan langsung di dalam peramban dengan nama berkas yang sama, bukan memaksa mengunduh. Header keamanan tipe konten diwajibkan agar peramban tidak menebak-nebak tipe isi berkas. Header cache diatur agar berkas hanya dapat disimpan cache secara privat selama satu jam, sehingga berkas foto tidak dapat dipakai bersama oleh komputer lain melalui cache bersama.

### 19.4 Sanitasi Path Foto

Sebelum berkas dibaca, sistem melakukan sanitasi terhadap path yang tersimpan di dalam basis data. Sanitasi menolak path yang bukan berupa teks, path kosong, path yang mengandung byte nol, path yang mengandung urutan naik-turun yang dapat keluar dari direktori penyimpanan, serta path yang diawali garis miring atau garis miring terbalik. Dengan demikian, apabila nilai path di dalam basis data dibuat-buat secara sengaja, berkas di luar folder penyimpanan tidak akan terbaca.

### 19.5 Pencegahan Berkas Yatim dan Konsistensi Penyimpanan

Sistem menerapkan beberapa aturan agar penyimpanan foto dan data basis data tetap konsisten. Penyimpanan berkas foto selalu dilakukan sebelum penyimpanan baris data, sehingga tidak ada baris data yang menunjuk berkas yang belum ada. Apabila penyimpanan baris data gagal setelah berkas foto berhasil disimpan, berkas foto tersebut dihapus agar tidak tersisa tanpa rujukan. Apabila terjadi penggantian foto pada proses ubah, berkas foto baru disimpan terlebih dahulu, baru baris data diperbarui, dan baru setelah pembaruan berhasil, berkas foto lama dihapus. Dengan urutan ini, apabila terjadi kegagalan di tengah jalan, kondisi terburuk yang mungkin adalah adanya berkas foto baru yang tidak terpakai, bukan adanya baris data yang menunjuk berkas yang hilang.

Penghapusan data melakukan urutan sebaliknya, yaitu menghapus baris data terlebih dahulu, baru kemudian menghapus berkas foto. Urutan ini dipilih dengan pertimbangan bahwa penghapusan berkas bersifat usaha terbaik, sehingga apabila penghapusan berkas gagal setelah baris data terhapus, sistem tetap menampilkan pesan berhasil kepada pengguna dan tidak menampilkan halaman error yang membingungkan.

### 19.6 Perintah Migrasi Foto Lama

Karena versi sebelumnya menyimpan foto pada disk publik, diperlukan mekanisme pemindahan foto lama ke disk privat. Sistem menyediakan perintah baris perintah yang berfungsi memindahkan foto dari disk publik ke disk privat untuk kedua tabel foto, yaitu tabel pemeriksaan harian dan tabel laporan bulanan. Perintah ini memerlukan penanda eksplisit untuk benar-benar menjalankan perpindahan, sehingga dapat dijalankan dalam mode simulasi terlebih dahulu untuk melihat rencana perpindahan tanpa mengubah apa pun.

Perintah ini memperhitungkan jumlah file yang dipindahkan, jumlah file yang dipulihkan karena sudah ada di tujuan, jumlah file yang akan dipulihkan karena masih tersisa, jumlah file yang hilang, jumlah file yang berkonflik, dan jumlah file yang gagal. Verifikasi keberhasilan perpindahan dilakukan dengan membandingkan ukuran berkas dan mencocokkan sidik jari digital berkas sumber dan berkas salinan, sehingga perpindahan dianggap berhasil hanya bila isi keduanya benar-benar identik. Perintah ini menghasilkan kode keluar berhasil hanya apabila tidak ditemukan file yang hilang, berkonflik, gagal, atau tersisa.

### 19.7 Pengaman pada Migrasi Kolom Disk Foto

Migrasi penambahan kolom `foto_disk` dirancang agar aman dijalankan berulang kali dan aman diputar balik. Migrasi memeriksa keberadaan kolom terlebih dahulu sehingga tidak menyebabkan galat apabila dijalankan dua kali. Apabila migrasi ini diputar balik sementara masih terdapat baris yang menunjuk foto ke disk privat, migrasi menolak untuk melanjutkan dan menampilkan pesan yang menjelaskan penyebabnya, serta memberikan petunjuk cara memaksa rollback apabila rollback memang disengaja. Pengaman ini mencegah hilangnya informasi penanda disk privat akibat rollback yang tidak disengaja.

---

## 20. MODUL MANAJEMEN LOKASI

Sistem memiliki dua master lokasi yang terpisah, yaitu master lokasi monitoring harian dan master lokasi laporan bulanan. Pemisahan ini mengikuti perbedaan konteks penggunaan kedua modul, sehingga admin dapat mengelola keduanya secara independen.
mengelola keduanya secara independen.

### 20.1 Struktur Master Lokasi

Setiap master lokasi memiliki tiga atribut dasar, yaitu nama lokasi, keterangan, dan status. Nama lokasi bersifat wajib dan harus unik di dalam tabelnya masing-masing. Keterangan bersifat opsional dan dapat dikosongkan. Status memiliki dua nilai, yaitu Aktif dan Nonaktif, dengan nilai bawaan Aktif. Lokasi berstatus Nonaktif tidak lagi dapat dipilih pada formulir input baru, namun tetap dapat dipilih pada formulir ubah apabila lokasi tersebut merupakan lokasi yang sedang disunting, agar data lama tidak kehilangan konteks lokasinya. Lokasi yang berstatus Nonaktif juga tetap muncul pada pilihan filter histori, agar admin tetap dapat menelusuri data pemeriksaan pada lokasi yang saat ini sudah dinonaktifkan.

### 20.2 Modul Manajemen Lokasi Monitoring Harian

Modul ini hanya dapat diakses oleh admin. Modul ini menampilkan daftar seluruh lokasi monitoring dengan kolom nomor, nama lokasi, status, jumlah laporan, dan aksi. Jumlah laporan menyatakan banyaknya data pemeriksaan yang merujuk pada lokasi tersebut, sehingga admin dapat mengetahui lokasi mana yang memiliki data dan mana yang belum terpakai. Halaman ini menyediakan tombol tambah lokasi, dan menggunakan paginasi sebesar sepuluh baris.

Penghapusan lokasi monitoring ditolak apabila lokasi tersebut masih memiliki data pemeriksaan. Pengaman ini diterapkan pada dua lapis, yaitu pemeriksaan pada pengendali yang memeriksa keberadaan data pemeriksaan, dan batasan integritas di tingkat basis data yang menolak penghapusan lokasi yang masih dirujuk. Pengaman lapis kedua memastikan konsistensi data tetap terjaga apabila pemeriksaan pada pengendali dilewati.

### 20.3 Modul Manajemen Lokasi Bulanan

Modul ini hanya dapat diakses oleh admin dan mengikuti pola yang sama dengan modul lokasi monitoring harian, dengan perbedaan nama rute dan tabel yang digunakan. Penghapusan lokasi bulanan ditolak apabila lokasi tersebut masih memiliki data laporan bulanan, dengan pengaman yang diterapkan secara sama pada pengendali dan tingkat basis data.

### 20.4 K backsseed Data Awal

Sistem menyediakan data awal untuk dua master lokasi monitoring berupa GWT dengan keterangan tandon air tanah utama dan kolam air bersih dengan keterangan kolam air bersih utama, keduanya berstatus aktif. Data lokasi bulanan menyediakan satu lokasi awal bernama Umum yang otomatis dibuat pada saat migrasi penambahan kolom lokasi dilepas, sebagai lokasi cadangan bagi data yang tercatat sebelum penambahan kolom tersebut. Penyemaan menggunakan pola buat bila belum ada, sehingga aman dijalankan berulang kali.

---

## 21. MODUL MANAJEMEN PENGGUNA

Modul manajemen pengguna hanya dapat diakses oleh admin dan digunakan untuk mengelola akun beserta peran aksesnya.

### 21.1 Atribut Pengguna

Setiap pengguna memiliki nama, surel, kata kunci, dan peran. Surel bersifat unik di dalam basis data. Kata kunci disimpan dalam bentuk terenkripsi dan tidak pernah dikembalikan dalam bentuk teks biasa pada respons apa pun. Peran hanya memiliki dua nilai, yaitu admin dan petugas, dengan nilai bawaan petugas. Peran ini didefinisikan sebagai konstanta pada model pengguna sehingga menjadi satu sumber kebenaran tunggal.

### 21.2 Formulir Tambah dan Ubah Pengguna

Formulir tambah pengguna memuat field nama, surel, peran, kata kunci, dan konfirmasi kata kunci, seluruhnya bersifat wajib. Formulir ubah memuat field yang sama, namun kata kunci tidak lagi menjadi field wajib dan perlu dikosongkan bila tidak diubah. Peran pada formulir ubah tidak dapat diubah setelah pengguna dibuat, dan hal ini dikonfirmasi secara visual kepada admin melalui lencana peran disertai keterangan bahwa peran tidak dapat diubah. Pengaman ini mencegah perubahan peran yang tidak disengaja dan menjaga konsistensi hak akses.

Aturan validasi yang diterapkan meliputi nama wajib berupa teks dengan panjang maksimal dua ratus lima puluh lima karakter, surel wajib berupa alamat surel yang valid dengan panjang maksimal yang sama dan harus unik, kata kunci wajib berupa teks dengan panjang minimal delapan karakter dan harus cocok dengan konfirmasi kata kunci, serta peran harus salah satu dari dua nilai peran yang sah.

### 21.3 Pengaman Penghapusan Akun

Penghapusan akun pengguna ditolak dalam empat kondisi yang diperiksa secara berurutan.

**Kondisi pertama** adalah bila akun yang akan dihapus merupakan akun sendiri yang sedang digunakan, dengan pesan bahwa pengguna tidak dapat menghapus akun sendiri.

**Kondisi kedua** adalah bila akun tersebut merupakan admin terakhir yang tersisa dalam sistem, dengan pesan bahwa admin terakhir tidak dapat dihapus. Pengaman ini memastikan sistem tidak pernah kehilangan seluruh admin, yang akan membuat data pemeriksaan dan data pengguna tidak dapat dikelola lagi.

**Kondisi ketiga** adalah bila akun tersebut masih memiliki data pemeriksaan, dengan pesan bahwa pengguna tidak dapat dihapus karena memiliki data pemeriksaan.

**Kondisi keempat** adalah bila akun tersebut masih memiliki data laporan bulanan, dengan pesan bahwa pengguna tidak dapat dihapus karena memiliki data laporan bulanan.

Seluruh kondisi tersebut memusatkan penghapusan akun pada admin dan,/memastikan keutuhan histori data pemeriksaan dan data laporan bulanan tetap terjaga.

### 21.4 Urutan Daftar Pengguna

Daftar pengguna diurutkan dengan admin terlebih dahulu, kemudian petugas diurutkan secara alfabetis. Pengurutan ini memudahkan admin untuk menemukan akun admin dengan cepat, dan membuat urutan daftar menjadi stabil serta mudah dipindai. Halaman ini tidak menyediakan pencarian atau filter, dan menggunakan paginasi sebesar sepuluh baris.

### 21.5 Data Awal Pengguna

Sistem menyediakan data awal berupa satu akun admin dan satu akun petugas, keduanya dibuat dengan pola buat bila belum ada sehingga aman dijalankan berulang kali. Password akun awal ini sederhana untuk keperluan pengembangan, dan wajib diubah sebelum sistem digunakan secara nyata.

---

## 22. SISTEM PERINGATAN KONDISI

Sistem tidak memiliki modul notifikasi terpisah dan tidak mengirim peringatan keluar dari aplikasi. Seluruh peringatan diturunkan dari nilai kondisi yang tersimpan pada baris data. Secara teknis, sistem menerapkan tiga lapis mekanisme peringatan yang saling melengkapi.

### 22.1 Lapis Pertama, Pesan Setelah Penyimpanan

Apabila kondisi yang dipilih bukan Normal, sistem menampilkan pesan khusus yang menggabungkan dua unsur, yaitu konfirmasi bahwa data berhasil disimpan, dan pernyataan bahwa kondisi tersebut terdeteksi dan perlu perhatian. Pesan ini ditampilkan dengan warna danger. Pesan konfirmasi yang lebih umum, yaitu bahwa pemeriksaan berhasil disimpan, hanya ditampilkan apabila kondisi Normal. Pendekatan ini memastikan petugas selalu memperoleh konfirmasi bahwa input-nya tersimpan, sekaligus tidak dapat luput dari kenyataan bahwa ia menemukan kondisi yang perlu ditindaklanjuti.

### 22.2 Lapis Kedua, Lencana Warna

Pada halaman histori, halaman detail, halaman laporan bulanan, dan bagian ringkasan dashboard, kondisi ditampilkan menggunakan lencana berwarna. Lencana berwarna hijau menandakan kondisi Normal, sedangkan lencana berwarna kuning dengan teks gelap menandakan kondisi yang memerlukan perhatian. Warna yang konsisten di seluruh halaman memungkinkan pengguna mengenali kondisi bermasalah secara sekilas tanpa harus membaca teksnya.

### 22.3 Lapis Ketiga, Panel Peringatan Dashboard

Dashboard menampilkan panel peringatan berwarna merah yang memuat seluruh catatan pemeriksaan dengan kondisi bukan Normal, dilengkapi tautan untuk melihat detail masing-masing catatan. Panel ini menjadi titik masuk utama bagi admin untuk melakukan triage temuan lapangan.

### 22.4 Batas dan Sifat Peringatan

Perlu dipahami bahwa seluruh peringatan pada sistem bersifat historis dan reaktif, bukan preventif. Peringatan muncul setelah petugas mencatat temuan, bukan sebelum atau saat kondisi terjadi. Peringatan tidak dikirimkan melalui surel, pesan instan, atau media lain. Selain itu, panel peringatan pada dashboard menampilkan seluruh catatan bukan Normal sepanjang masa, bukan hanya periode yang sedang dipilih, sehingga admin dapat melihat riwayat temuan yang mungkin telah lama tidak ditangani.

---

# KELOMPOK D — DATA, KEAMANAN, DAN KEBUTUHAN NON-FUNGSIONAL

---

## 23. STRUKTUR DATABASE

Sistem memiliki enam tabel utama, yaitu tabel pengguna, dua tabel master lokasi, dan dua tabel data transaksi dengan satu tabel tambahan pendukung pustaka bawaan framework. Seluruh tabel menggunakan konvensi penamaan jamak dan kolom penanda waktu pembuatan serta pembaruan.

### 23.1 Tabel Pengguna

Tabel pengguna menyimpan data akun. Kolom yang tersedia meliputi pengenal unik, nama, surel yang bersifat unik, waktu verifikasi surel yang dapat dikosongkan, kata kunci terenkripsi, peran dengan nilai bawaan petugas, serta pengenal pengingat yang digunakan framework untuk fitur ingat saya. Kolom surel diberi batasan unik di tingkat basis data, sehingga langsung mencegah email ganda pada tingkat data.

### 23.2 Tabel Lokasi Monitoring

Tabel lokasi monitoring menyimpan master lokasi pemeriksaan harian. Kolom yang tersedia meliputi pengenal unik, nama lokasi yang wajib diisi, keterangan yang dapat dikosongkan, dan status dengan nilai bawaan aktif. Tabel ini pada awalnya memiliki kolom jenis yang menandai apakah lokasi berupa GWT atau kolam air bersih, namun kolom tersebut kemudian dihapus melalui migrasi tersendiri karena jenis dianggap kurang fleksibel dibandingkan dengan penambahan lokasi baru secara bebas. Penghapusan kolom ini perlu dicatat karena PRD versi 1.0 masih mendokumentasikan kolom tersebut.

### 23.3 Tabel Pemeriksaan Air

Tabel pemeriksaan air menyimpan hasil pemeriksaan harian. Kolom yang tersedia meliputi pengenal unik, identitas pengguna sebagai kunci asing, identitas lokasi sebagai kunci asing, tanggal pemeriksaan, sesi pemeriksaan, waktu pemeriksaan, kondisi, keterangan penjelasan, foto bukti, nama disk foto, serta pengenal waktu pembuatan dan pembaruan. Keterangan bersifat wajib dan tidak memiliki batas panjang. Nama disk foto memiliki nilai bawaan disk publik untuk menjaga kompatibilitas dengan data lama.

Tabel ini memiliki tiga batasan penting. **Batasan pertama** adalah batasan unik yang menggabungkan identitas lokasi, tanggal, dan sesi, yang berfungsi sebagai jaminan terakhir terhadap duplikasi pemeriksaan. **Batasan kedua** adalah kunci asing ke tabel pengguna dengan aksi hapus berantai, sehingga menghapus pengguna akan ikut menghapus data pemeriksaannya. Namun pengaman pada pengendali sebenarnya menolak penghapusan pengguna yang memiliki data. **Batasan ketiga** adalah kunci asing ke tabel lokasi monitoring dengan aksi hapus dibatasi, sehingga lokasi yang masih memiliki data pemeriksaan tidak dapat dihapus. Batasan ketiga ini diubah dari aksi hapus berantai menjadi aksi hapus dibatasi melalui migrasi tersendiri, agar integritas histori pemeriksaan tetap terjaga meskipun validasi pada pengendali dilewati.

### 23.4 Tabel Lokasi Bulanan

Tabel lokasi bulanan menyimpan master lokasi input bulanan. Struktur kolomnya identik dengan tabel lokasi monitoring, meliputi pengenal unik, nama lokasi, keterangan yang dapat dikosongkan, dan status dengan nilai bawaan aktif. Tabel ini tidak diberi batasan unik pada nama lokasi di tingkat basis data, keunikannya hanya dijaga oleh validasi pada pengendali.

### 23.5 Tabel Laporan Bulanan

Tabel laporan bulanan menyimpan data input keuangan. Kolomnya meliputi pengenal unik, identitas pengguna sebagai kunci asing dengan aksi hapus berantai, identitas lokasi sebagai kunci asing yang dapat dikosongkan dengan aksi hapus menetapkan kosong, tanggal input, kondisi, keterangan penjelasan, foto bukti, nama disk foto dengan nilai bawaan disk publik, serta pengenal waktu pembuatan dan pembaruan.

Kolom identitas lokasi pada tabel ini sengaja dapat dikosongkan, berbeda dengan kolom identitas lokasi pada tabel pemeriksaan. Hal ini karena kolom tersebut ditambahkan melalui migrasi setelah tabel terlebih dahulu dibuat, sehingga data lama yang sudah tercatat tidak memiliki lokasi. Untuk menampung data lama tersebut, migrasi membuat satu lokasi cadangan dan kemudian mengisi seluruh baris yang lokasinya kosong dengan lokasi cadangan tersebut, sehingga tidak ada data yang tersisa tanpa lokasi.

### 23.6 Tabel Pendukung Pustaka Bawaan

Sistem mengikuti struktur bawaan Laravel yang menyediakan tabel untuk pengingat kata kunci, pekerjaan yang gagal dijalankan, dan token akses pribadi. Tabel ini tidak digunakan secara langsung oleh logika bisnis sistem, namun tetap disertakan agar instalasi framework berjalan normal.

---

## 24. RELASI DATA

Hubungan data pada sistem ini didominasi oleh hubungan satu ke banyak.

Satu pengguna dapat memiliki banyak data pemeriksaan air. Satu pengguna juga dapat memiliki banyak data laporan bulanan. Satu lokasi monitoring dapat memiliki banyak data pemeriksaan air. Satu lokasi bulanan dapat memiliki banyak data laporan bulanan. Dengan demikian, satu baris pengguna dan satu baris lokasi dapat dirujuk oleh banyak baris data pada saat bersamaan, dan penghapusan pada sisi master tidak boleh langsung menghapus data pada sisi transaksi.

Arah batasan penting untuk dipahami. Batasan pada sisi pengguna menggunakan aksi hapus berantai, sehingga secara teknis penghapusan pengguna akan ikut menghapus data pemeriksaannya. Akan tetapi, pengaman pada lapisan aplikasi menolak penghapusan pengguna yang masih memiliki data, sehingga secara praktis aksi berantai tersebut tidak akan terjadi melalui jalur antarmuka. Sebaliknya, batasan pada sisi lokasi menggunakan aksi hapus dibatasi, sehingga penghapusan lokasi yang masih dirujuk akan ditolak oleh basis data dengan sendirinya. Kedua perbedaan ini dirancang sengaja, yaitu untuk menghormati kenyataan bahwa data pemeriksaan adalah jejak kerja petugas yang sebaiknya tidak hilang, sedangkan data lokasi adalah master data yang keliru bila terhapus.

---

## 25. ATURAN VALIDASI INPUT DAN PESAN GALAT

Seluruh validasi dilakukan di sisi server menggunakan kelas permintaan formulir terpisah untuk tiap aksi, sehingga aturan dapat diuji secara terpisah dari pengendali. Pendekatan ini membuat aturan bisnis terdokumentasi pada satu tempat dan mudah diaudit.

### 25.1 Validasi Formulir Pemeriksaan Harian

**Lokasi** wajib dipilih, harus benar-benar ada di dalam basis data, dan harus berstatus aktif. Lokasi juga harus belum tercatat pada kombinasi tanggal dan sesi yang sama.

**Tanggal** wajib diisi, harus berupa tanggal yang valid, dan tidak boleh melebihi hari ini.

**Sesi** wajib dipilih dan harus salah satu dari tiga nilai sesi yang sah.

**Kondisi** wajib dipilih dan harus salah satu dari tiga nilai kondisi yang sah.

**Keterangan** wajib diisi dan harus berupa teks.

**Foto** wajib diunggah, harus berupa gambar, formatnya harus salah satu dari tiga format yang diizinkan, dan ukurannya tidak boleh melebihi lima megabyte.

### 25.2 Validasi Formulir Ubah Pemeriksaan

Aturan ubah mengikuti aturan simpan, dengan tiga perbedaan penting. Pertama, foto tidak lagi wajib, dan apabila tidak diunggah maka foto lama dipertahankan. Kedua, lokasi yang sedang disunting tetap dapat dipilih meskipun saat ini berstatus nonaktif, agar data lama tidak kehilangan konteks lokasinya. Ketiga, pemeriksaan keunikan mengabaikan baris yang sedang disunting itu sendiri, sehingga admin dapat menyimpan data tanpa perubahan dan tetap lolos validasi.

### 25.3 Validasi Formulir Laporan Bulanan

**Tanggal** wajib diisi, harus berupa tanggal yang valid, dan tidak boleh melebihi hari ini.

**Lokasi** wajib dipilih dan harus benar-benar ada di dalam basis data. Perlu dicatat bahwa validasi lokasi pada modul laporan bulanan tidak membatasi lokasi harus berstatus aktif, berbeda dengan modul pemeriksaan harian yang membatasi.

**Kondisi** wajib dipilih dan harus salah satu dari tiga nilai kondisi laporan bulanan yang sah.

**Keterangan** wajib diisi dan harus berupa teks.

**Foto** wajib diunggah, harus berupa gambar, formatnya harus sesuai, dan ukurannya tidak boleh melebihi lima megabyte. Pada formulir ubah, foto bersifat opsional.

### 25.4 Validasi Master Lokasi

Nama lokasi wajib diisi, harus berupa teks, panjangnya maksimal dua ratus lima puluh lima karakter, dan harus unik di dalam tabelnya. Keterangan bersifat opsional dan harus berupa teks apabila diisi. Status wajib dipilih dan harus salah satu dari dua nilai status yang sah. Pada formulir ubah, pemeriksaan keunikan mengabaikan baris yang sedang disunting.

### 25.5 Validasi Formulir Pengguna

Nama wajib diisi, harus berupa teks, panjangnya maksimal dua ratus lima lima karakter. Surel wajib diisi, harus berupa alamat surel yang valid, panjangnya maksimal dua ratus lima puluh lima karakter, dan harus unik. Kata kunci wajib diisi, harus berupa teks, panjangnya minimal delapan karakter, dan harus cocok dengan konfirmasi kata kunci. Peran wajib dipilih dan harus salah satu dari dua nilai peran yang sah. Pada formulir ubah, kata kunci boleh dikosongkan, dan surel diabaikan keunikannya untuk baris yang sedang disunting.

### 25.6 Kumpulan Pesan Galat

Seluruh pesan galat disusun dalam Bahasa Indonesia dan spesifik terhadap field yang bermasalah, bukan pesan umum. Pesan yang disediakan meliputi pesan lokasi wajib dipilih, pesan lokasi tidak valid atau tidak aktif, pesan pemeriksaan untuk lokasi dan sesi tersebut sudah dilakukan, pesan tanggal wajib diisi, pesan tanggal tidak valid, pesan tanggal tidak boleh melebihi hari ini, pesan sesi pemeriksaan wajib dipilih, pesan sesi pemeriksaan tidak valid, pesan kondisi wajib dipilih, pesan kondisi tidak valid, pesan keterangan wajib diisi, pesan keterangan tidak valid, pesan foto wajib diunggah, pesan file harus berupa gambar, pesan format foto hanya menerima tiga format tertentu, pesan ukuran foto maksimal lima megabyte, pesan nama lokasi wajib diisi, pesan nama lokasi sudah digunakan, pesan status tidak valid, pesan surel sudah digunakan, pesan konfirmasi kata kunci tidak cocok, pesan email atau password salah, pesan terlalu banyak percobaan login dengan sisa waktu, pesan akun tidak memiliki akses ke sistem, serta seluruh pesan penolakan berbasis kepemilikan dan berbasis integritas data.

### 25.7 Penyimpanan Input yang Gagal

Apabila validasi gagal, sistem mengembalikan pengguna ke halaman formulir dengan mempertahankan seluruh isian yang sudah diketik kecuali berkas, dan menampilkan pesan galat di bawah field yang bermasalah. Pendekatan ini mengurangi beban kerja petugas, karena isian yang sudah diketik tidak hilang begitu saja. Field foto yang gagal divalidasi tidak dapat dipertahankan karena merupakan berkas.

---

## 26. MODEL KEAMANAN SISTEM

Keamanan sistem dibangun dari lima lapis yang saling melengkapi, yaitu lapis konfigurasi peladen, lapis middleware rute, lapis pemeriksaan kepemilikan di dalam pengendali, lapis pencegahan penyalahgunaan halaman login, dan lapis privasi berkas foto.

### 26.1 Lapis Konfigurasi Peladen

Seluruh halaman aplikasi berada di belakang middleware penengah dan penyimpan sesi, sehingga halaman yang tidak memiliki pengenal sesi tidak dapat dilayani secara langsung. Konfigurasi aplikasi dibaca dari berkas lingkungan pada direktori akar, mencakup nama aplikasi, lingkungan, mode debug, URL aplikasi, kunci enkripsi, dan konfigurasi basis data. Penyimpan sesi dan tembolok pada instalasi ini memakai berkas lokal, dengan masa berlaku sesi seratus dua puluh menit. Nilai yang bersifat lokal dan tidak boleh dipublikasikan, seperti kunci enkripsi dan kata sandi basis data, tidak disimpan di dalam repositori.

### 26.2 Lapis Middleware Peran

Dua middleware membatasi akses ke aplikasi. Middleware autentikasi memaksa pengguna untuk melakukan login terlebih dahulu, dan mengembalikan pengguna ke halaman login apabila belum masuk. Middleware peran memeriksa nilai peran pengguna terhadap peran yang diminta oleh rute, lalu menolak akses dengan kode tiga ratus tiga belas apabila tidak sesuai, disertai pesan dalam Bahasa Indonesia. Seluruh rute aplikasi dilindungi middleware autentikasi. Rute yang membaca data transaksi menerima admin dan petugas, rute yang mengubah data transaksi menerima admin dan petugas dengan syarat kepemilikan, sedangkan rute yang menambah, mengubah, dan menghapus data master hanya menerima admin. Dengan demikian batas peran ditegakkan oleh kerangka aplikasi, bukan hanya dengan menyembunyikan tombol pada tampilan.

### 26.3 Lapis Pemeriksaan Kepemilikan

Pembatasan peran saja belum cukup, sebab petugas yang sah tetap harus dibatasi hanya pada datanya sendiri. Oleh karena itu pengendali menerapkan tiga pemeriksaan. **Pemeriksaan pertama** adalah pembatasan kueri, yaitu menambahkan syarat identitas pengguna pada seluruh kueri daftar, detail, dan agregasi ketika peran pengguna adalah petugas. **Pemeriksaan kedua** adalah penolakan akses, yaitu mengembalikan kode tiga ratus tiga belas apabila baris data yang dituju bukan milik pengguna. **Pemeriksaan ketiga** adalah penolakan perubahan, yaitu menolak pembaruan maupun penghapusan baris data apabila baris tersebut bukan milik pengguna. Pendekatan ini membuat batas data tidak hanya berlaku pada tampilan daftar, tetapi juga pada setiap akses langsung melalui alamat.

### 26.4 Lapis Pencegahan Penyalahgunaan Halaman Login

Sistem membatasi jumlah percobaan login menjadi lima kali dan memberikan penundaan enam puluh detik setelah jatah percobaan habis dipakai. Kunci pembatasan dibentuk dari gabungan alamat surel yang dikecilkan hurufnya dan alamat protokol jaringan, sehingga percobaan dari satu perangkat dengan beberapa surel berbeda maupun dari beberapa perangkat dengan surel yang sama tidak dapat melewati pembatas. Catatan percobaan dihapus segera setelah login berhasil, sehingga pengguna yang sah tidak pernah terbawa terkunci oleh percobaan sebelumnya. Ketika pengguna terkunci, sistem tidak menampilkan halaman galat bawaan kerangka kerja, melainkan mengembalikan pengguna ke formulir login dengan pesan yang menyebutkan sisa waktu dalam satuan detik atau menit, dan hanya mempertahankan isian surel. Mekanisme ini menutup celah penyalahgunaan yang lazim terjadi pada halaman login terbuka.

### 26.5 Lapis Privasi Berkas Foto

Foto bukti tidak pernah disajikan langsung sebagai berkas statis. Setiap permintaan foto terlebih dahulu melewati pemeriksaan autentikasi dan pemeriksaan peran, kemudian diperiksa apakah pemohon adalah admin atau pemilik baris data yang memuat foto tersebut. Penerusan berkas dilakukan melalui pengendali, bukan melalui tautan langsung ke penyimpanan, sehingga jalur foto tidak dapat diakses tanpa melewati pemeriksaan tersebut. Rincian teknis penyimpanan dan privasi foto dibahas pada bagian bukti foto.

### 26.6 Catatan tentang Batas Keamanan

Perlu dicatat bahwa sistem belum memiliki beberapa lapisan keamanan produksi yang umum digunakan, yaitu belum ada autentikasi dua faktor, belum ada rotasi kata sandi, belum ada catatan audit untuk perubahan data, dan belum ada kebijakan retensi berkas foto. Batasan tersebut berada di luar ruang lingkup versi 2.0 dan dicatat sebagai backlog perbaikan pada bagian rekomendasi perbaikan. Selain itu, instalasi yang terhubung dengan mode debug aktif dapat menampilkan pesan galat internal, sehingga mode debug wajib dinonaktifkan pada lingkungan produksi.

## 27. STRATEGI PENYIMPANAN FOTO BUKTI

Foto bukti adalah data paling sensitif pada sistem. Karena itu penyimpanan foto dirancang sebagai komponen tersendiri.

### 27.1 Dua Disk dengan Peran Berbeda

Sistem memakai dua disk penyimpanan berkas. Disk publik adalah disk bawaan Laravel. Direktori simpanannya ditautkan secara simbolis ke direktori publik, sehingga isinya dapat dibuka lewat tautan statis. Disk bukti adalah disk tambahan. Disk ini tidak punya alamat publik dan ditandai dengan sifat privat. Semua foto baru ditulis ke disk bukti. Kolom penanda disk pada baris data diisi dengan nama disk bukti.

### 27.2 Pemisahan Direktori per Modul

Foto dipisahkan ke dua direktori. Direktori pertama dipakai untuk foto pemeriksaan harian. Direktori kedua dipakai untuk foto laporan bulanan. Pemisahan ini mencegah kedua jenis foto tercampur. Penelusuran arsip jadi lebih mudah per modul. Penamaan berkas diserahkan kepada mekanisme bawaan kerangka kerja. Nama berkas yang tersimpan karena itu berupa nama acak. Nama asli berkas milik pengguna tidak dipakai.

### 27.3 Ketahanan terhadap Galat Penyimpanan

Penyimpanan berkas dibungkus penanganan galat. Kegagalan menulis berkas tidak menyebabkan halaman berhenti dengan pesan teknis. Bila penyimpanan gagal, sistem mencatat kejadian ke dalam catatan aplikasi. Pengendali lalu membalas dengan pesan galat dalam Bahasa Indonesia. Pesan itu menyebutkan bahwa foto bukti gagal disimpan. Dengan begitu pengalaman pengguna tetap terkendali. Kesalahan tetap tercatat untuk diperiksa kemudian.

### 27.4 Penghapusan Berkas yang Tidak Mengganggu Data

Penghapusan berkas diperlakukan sebagai operasi terbaik yang mungkin. Kegagalan menghapus berkas tidak menggagalkan penghapusan baris data. Bila berkas tidak ditemukan, baris data tetap dapat dibersihkan. Berkas sisa dapat ditangani kemudian. Pembungkus serupa dipakai saat memeriksa keberadaan berkas. Pembungkus serupa dipakai saat membaca isi berkas. Ketidaknormalan penyimpanan tidak diteruskan sebagai galat ke pengguna.

### 27.5 Pembersihan Jalur dan Verifikasi

Jalur berkas dinormalisasi sebelum dibaca atau dihapus. Normalisasi menolak jalur yang mengandung potongan naik ke direktori induk. Dengan begitu tidak ada cara mengakses berkas di luar direktori yang ditentukan. Pembacaan isi berkas memakai pembacaan berpelindung yang sama. Berkas yang rusak tidak menyebabkan halaman berhenti mendadak.

### 27.6 Perpindahan Foto Lama

Versi sebelumnya menyimpan foto pada disk publik. Karena itu tersedia perintah baris perintah untuk memindahkan foto lama ke disk bukti. Perintah berjalan bertahap. Perintah memverifikasi bahwa berkas sumber memang ada. Perintah memeriksa bahwa berkas tujuan belum ada atau isinya sama. Perintah memindahkan berkas. Perintah lalu menandai kolom penanda disk pada baris terkait. Perintah aman dijalankan berulang kali. Perintah melaporkan jumlah berkas yang dipindahkan. Perintah melaporkan jumlah berkas yang dilewati karena sudah ada. Perintah melaporkan jumlah berkas yang gagal.

## 28. KLASIFIKASI DAN PRIVASI DATA

### 28.1 Kategori Data

Berdasarkan sifat dan tingkat kerahasiaannya, data pada sistem dapat dibagi menjadi empat kategori. Kategori pertama adalah data identitas akun. Kategori ini meliputi nama, surel, dan kata kunci terenkripsi. Surel bersifat pribadi dan tidak boleh ditampilkan kepada pengguna lain. Kategori kedua adalah data operasional. Kategori ini meliputi hasil pemeriksaan harian dan laporan bulanan beserta kondisi, keterangan, dan waktu pencatatan. Data ini bersifat internal. Data ini hanya boleh dibaca oleh admin dan petugas pemilik datanya. Kategori ketiga adalah data dokumentasi. Kategori ini berupa foto bukti yang memuat kondisi fisik lokasi. Kategori ini paling sensitif. Kategori ini dapat dipakai untuk menilai mutu kerja dan kondisi daerah. Kategori keempat adalah data master. Kategori ini meliputi nama dan status lokasi beserta akun admin. Kategori ini bersifat rujukan bersama.

### 28.2 Hak Akses terhadap Foto

Akses ke foto bukti diberikan hanya kepada dua pihak. Pihak pertama adalah admin sebagai pengelola sistem. Pihak kedua adalah petugas sebagai pemilik baris data tempat foto tersebut tersimpan. Petugas tidak dapat melihat foto milik petugas lain. Aturan ini berlaku meskipun keduanya memiliki peran yang sama. Setiap permintaan foto melewati pemeriksaan tersebut. Foto tidak dapat diambil dengan cara menyalin alamat berkas dan membukanya langsung melalui peramban.

### 28.3 Penyimpanan yang Tidak Dapat Diindeks

Disk bukti berada di luar direktori yang dilayani langsung oleh peladen web. Berkas foto karena itu tidak dapat ditemukan oleh mesin pencari. Berkas foto juga tidak dapat diambil tanpa melewati pengendali. Sifat ini menghapus kemungkinan pengindeksan otomatis. Sifat ini juga menutup kemungkinan pengambilan berkas tanpa pemeriksaan.

### 28.4 Prinsip Minimasi Data

Sistem tidak meminta data yang tidak diperlukan. Formulir tidak meminta nomor telepon. Formulir juga tidak meminta alamat domisili. Formulir tidak meminta data pribadi lain di luar nama dan surel untuk keperluan akun. Keterangan pada hasil pemeriksaan wajib diisi. Keterangan itu tidak dibatasi panjangnya. Keterangan itu tetap bersifat data operasional. Data itu hanya tersedia bagi pihak yang berhak. Prinsip minimasi ini dimaksudkan agar jumlah data yang bocor saat terjadi insiden tetap seminimal mungkin.

### 28.5 Retensi dan Penghapusan

Sistem belum menerapkan kebijakan retensi otomatis. Foto lama dan data pemeriksaan tetap tersimpan sampai pengendali menghapusnya. Penghapusan berkas foto dilakukan bersamaan dengan penghapusan baris data. Karena tidak ada kebijakan retensi, data yang sudah tidak diperlukan dapat menumpuk pada penyimpanan. Kondisi ini dicatat sebagai backlog perbaikan. Rekomendasi untuk bagian ini adalah menetapkan jangka waktu retensi dan menyediakan proses pengarsipan data bulanan yang sudah selesai.

---

## 29. DESAIN ANTARMUKA DAN RESPONSIFITAS

### 29.1 Kerangka Tampilan

Semua halaman setelah login memakai satu kerangka tampilan bersama. Kerangka itu memuat sidebar navigasi. Kerangka itu juga memuat area konten utama. Kerangka itu memuat lapisan pesan. Halaman login memakai kerangka terpisah yang lebih sederhana. Halaman login belum memerlukan navigasi aplikasi. Struktur ini memastikan setiap modul punya bentuk yang konsisten. Struktur ini memastikan jarak yang konsisten. Struktur ini memastikan posisi menu yang konsisten. Pengguna tidak perlu mempelajari tata letak baru pada setiap modul.

### 29.2 Pustaka Antarmuka

Antarmuka dibangun di atas pustaka Bootstrap. Versi yang dipakai adalah lima titik tiga titik tiga. Ikon memakai pustaka ikon Bootstrap. Grafik pada dashboard memakai pustaka Chart.js. Ketiga pustaka dimuat dari layanan konten daring. Versi yang dimuat dikunci pada berkas konfigurasi. Dengan begitu tampilan dapat diulang pada perangkat lain.

### 29.3 Pola Halaman

Walaupun modul berbeda, pola halaman yang dipakai relatif terbatas. Pola pertama adalah pola daftar. Pola ini dipakai untuk histori dan master data. Pola ini memuat judul halaman. Pola ini memuat panel penyaring. Pola ini memuat tabel data. Pola ini memuat paginasi. Pola kedua adalah pola formulir. Pola ini dipakai untuk tambah dan ubah. Pola ini memuat panel isian. Pola ini memuat tombol simpan dan tombol batal. Pola ketiga adalah pola halaman detail. Pola ini menampilkan satu baris data secara lengkap. Pola ini menampilkan foto bukti. Pola ini menampilkan tombol aksi sesuai hak akses. Pola keempat adalah pola halaman login. Pola ini memuat satu formulir di tengah layar.

### 29.4 Indikator Visual

Kondisi dan status ditampilkan memakai lencana berwarna. Warna hijau dipakai untuk kondisi normal. Warna kuning atau jingga dipakai untuk kondisi yang perlu perhatian. Warna merah dipakai untuk kondisi yang tidak dapat diterima. Warna netral tidak dipakai karena tidak berkaitan dengan status kondisi.

### 29.5 Konfirmasi Aksi Berisiko

Aksi yang tidak dapat dibatalkan meminta konfirmasi terlebih dahulu. Konfirmasi dipakai pada aksi hapus. Pesan konfirmasi menyebutkan nama aksi. Pesan konfirmasi menyebutkan bahwa tindakan tidak dapat dibatalkan. Pemisahan warna antara kondisi dan status membuat pengguna dapat membedakan temuan petugas dengan penilaian sistem. Pemisahan ini membuat keduanya jelas dan tidak mudah tertukar.

### 29.6 Pesan Sistem

Pesan berhasil dan pesan galat ditampilkan pada lapisan pesan di bagian atas konten. Pesan berhasil memakai warna hijau. Pesan galat memakai warna merah. Pesan validasi ditampilkan tepat di bawah field yang bermasalah. Pesan validasi dirinci per field. Pendekatan ini membuat pengguna tahu bagian mana yang harus diperbaiki.

### 29.7 Tampilan Sempit

Seluruh halaman memakai kelas responsif dari pustaka antarmuka. Tabel dapat digeser secara mendatar pada layar sempit. Judul dan tombol disusun agar tetap terbaca. Formulir memakai susunan satu kolom pada layar sempit. Tampilan tetap dapat digunakan pada telepon genggam, yang menjadi perangkat yang paling sering dipakai petugas di lapangan.

---

## 30. KEBUTUHAN NON-FUNGSIONAL

### 30.1 Kebutuhan Performa

Volume data harian kecil. Jumlah lokasi pemeriksaan di bawah dua puluh lokasi. Setiap lokasi diperiksa tiga kali sehari. Data pemeriksaan harian di bawah enam puluh baris per hari. Data laporan bulanan juga kecil. Jumlahnya sebanding dengan jumlah lokasi aktif. Query dashboard menggabungkan beberapa tabel. Kecepatan query ditopang indeks pada kolom yang sering dicari. Query juga dibatasi berdasarkan peran pengguna.

### 30.2 Kebutuhan Keandalan

Penyimpanan pemeriksaan harian dan laporan bulanan berjalan dalam satu transaksi. Bila satu langkah gagal, seluruh perubahan dibatalkan. Tidak ada kondisi setengah tersimpan. Penghapusan berkas foto berjalan di luar transaksi. Kegagalan hapus berkas tidak membatalkan hapus baris data.

### 30.3 Kebutuhan Kompatibilitas

Aplikasi berjalan pada PHP versi delapan atau lebih baru. Basis data memakai MySQL versi delapan atau lebih baru. Kebutuhan MySQL delapan muncul karena dashboard memakai fungsi jendela ROW_NUMBER. Fungsi itu tidak tersedia pada MySQL versi lima. Pustaka antarmuka dimuat dari jaringan. Perangkat pengguna harus dapat mengakses jaringan internet.

### 30.4 Kebutuhan Keamanan

Semua halaman berada di belakang autentikasi. Pembatasan peran ditegakkan pada setiap rute. Data transaksi dibatasi berdasarkan kepemilikan. Foto hanya dapat diakses admin atau pemiliknya. Foto disimpan pada direktori privat. Percobaan login dibatasi lima kali. Penundaan enam puluh detik berlaku setelah batas itu habis.

### 30.5 Kebutuhan Kemudahan Penggunaan

Seluruh pesan galat ditulis dalam Bahasa Indonesia. Istilah teknis dihindari pada pesan yang dilihat pengguna. Formulir memisahkan label, isian, dan pesan galat. Tombol aksi berada pada posisi yang konsisten. Pengguna baru dapat memakai sistem tanpa panduan tambahan.

### 30.6 Kebutuhan Pemeliharaan

Aturan validasi diletakkan pada kelas permintaan formulir terpisah. Nilai kondisi diletakkan pada konstanta model. Pembatasan peran diletakkan pada middleware terpisah. Penyimpanan foto diletakkan pada satu kelas pendukung. Struktur ini membuat perubahan pada satu bagian tidak merusak bagian lain.

### 30.7 Kebutuhan Ekstensibilitas

Menambah lokasi baru tidak memerlukan perubahan kode. Lokasi baru cukup ditambah melalui modul master lokasi. Menambah akun baru tidak memerlukan perubahan kode. Menambah sesi pemeriksaan baru memerlukan penyesuaian konstanta sesi dan aturan validasi. Menambah kondisi baru memerlukan penyesuaian konstanta kondisi dan daftar label. Menambah kolom data memerlukan migrasi dan penyesuaian formulir. Menambah modul baru dapat mengikuti pola yang sudah dipakai modul lama, yaitu pengendali, model, permintaan formulir, dan tampilan.

### 30.8 Catatan Kebutuhan Non-Fungsional

Beberapa kebutuhan produksi belum dipenuhi pada versi ini. Belum ada pencetakan catatan audit. Belum ada pemantauan otomatis untuk mendeteksi kegagalan. Belum ada kebijakan pencadangan otomatis. Belum ada halaman pemantauan kesehatan sistem. Kebutuhan tersebut dicatat sebagai backlog perbaikan.

### 30.9 Kebutuhan Ketersediaan

Aplikasi berjalan pada satu peladen. Tidak ada konfigurasi penyeimbangan beban. Tidak ada proses pencadangan otomatis yang dikonfigurasi di dalam aplikasi. Ketersediaan aplikasi bergantung pada ketersediaan peladen dan basis data. Untuk penggunaan produksi, pencadangan basis data dan penyimpanan foto perlu dijalankan sebagai pekerjaan terjadwal di luar aplikasi.


## 31. STRATEGI PENGUJIAN

### 31.1 Kerangka Pengujian

Sistem memakai kerangka pengujian bawaan Laravel. Konfigurasi pengujian memuat dua rangkaian, yaitu rangkaian uji kesatuan dan rangkaian uji fitur. Pengujian yang relevan bagi sistem ini berada pada rangkaian uji fitur. Verifikasi dilakukan terhadap perilaku aplikasi secara utuh, bukan terhadap satu fungsi terisolasi.

### 31.2 Keadaan Uji yang Dipakai

Pengujian berjalan pada keadaan uji terpisah. Basis data uji disiapkan ulang untuk tiap pengujian. Penyimpanan berkas uji memakai penyimpanan sementara. Dengan demikian pengujian tidak menyentuh data maupun berkas milik lingkungan pengembangan.

### 31.3 Rangkaian Pengujian Fitur

Terdapat sepuluh berkas pengujian fitur dengan jumlah pengujian sebagai berikut. Berkas autentikasi memuat sembilan pengujian. Berkas dasbor memuat enam pengujian. Berkas contoh bawaan memuat satu pengujian. Berkas pengelolaan laporan bulanan memuat tujuh pengujian. Berkas laporan bulanan berkas PDF memuat lima belas pengujian. Berkas migrasi kolom penanda disk foto memuat dua belas pengujian. Berkas pengelolaan monitoring harian memuat sembilan pengujian. Berkas privasi foto memuat empat puluh pengujian. Berkas pemindahan foto ke disk bukti memuat tiga puluh tiga pengujian. Berkas otorisasi peran memuat enam pengujian. Total pengujian fitur mencapai seratus tiga puluh delapan.

### 31.4 Cakupan Pengujian

Cakupan pengujian dibagi ke dalam beberapa bidang. **Bidang autentikasi** diuji pada login berhasil, login gagal, pembatasan peran, penguncian setelah lima percobaan, dan penghapusan catatan percobaan setelah berhasil. **Bidang dasbor** diuji pada perhitungan statistik, filter periode, pemotongan hasil, dan ketersediaan data tanpa baris. **Bidang monitoring** diuji pada penambahan, perubahan, penghapusan, penolakan duplikasi sesi, penolakan lokasi nonaktif, dan penolakan akses antar petugas. **Bidang laporan bulanan** diuji pada penambahan, perubahan, penghapusan, pembatasan akses, dan pencarian berdasarkan bulan. **Bidang PDF** diuji pada judul halaman, isi tabel, jumlah foto, orientasi, dan penolakan akses. **Bidang foto** diuji pada privatisasi jalur, penolakan akses antar pengguna, peladen berkas, sanitasi jalur, dan perpindahan berkas lama

### 31.5 Bentuk Pengujian

Sebagian besar pengujian memakai pola Arrange, Act, Assert. Pola ini membuat setiap pengujian mudah dibaca dan mudah ditelusuri. Pengujian yang memeriksa tampilan memuat pemeriksaan respons dan isi halaman. Pengujian yang memeriksa keamanan memuat pemeriksaan kode penolakan. Pengujian yang memeriksa berkas memuat pemeriksaan isi berkas dan penolakan jalur.

### 31.6 Hasil Verifikasi

Pada pemeriksaan terakhir, seluruh rangkaian uji fitur lulus tanpa kegagalan. Hasil ini menunjukkan bahwa perilaku yang didokumentasikan pada dokumen ini sesuai dengan implementasi saat ini. Perlu dicatat bahwa hasil lulus tidak berarti tidak ada potensi masalah, sebab pengujian hanya dapat memverifikasi hal yang sudah ditulis sebagai pengujian. Bagian yang tidak memiliki pengujian tetap menjadi risiko yang tidak tertutup

---

## 32. KRITERIA PENERIMAAN

### 32.1 Kriteria Penerimaan Modul Autentikasi


### 32.1 Kriteria Penerimaan Modul Autentikasi

Pengguna yang belum login tidak dapat membuka halaman aplikasi. Pengguna dengan akun dan kata kunci yang benar berhasil masuk. Pengguna dengan peran yang tidak dikenal ditolak. Pengguna yang sudah lima kali gagal menunggu enam puluh detik. Login berhasil menghapus catatan percobaan gagal. Logout mengembalikan pengguna ke halaman login. Pengguna yang sudah login tidak dapat membuka halaman login lagi.

### 32.2 Kriteria Penerimaan Modul Dashboard

Statistik pada dasbor sesuai dengan data yang ada di dalam basis data. Filter periode mengubah hasil perhitungan. Filter lokasi membatasi hasil perhitungan. Pilihan cepat periode berfungsi. Pilihan cepat lokasi berfungsi. Jumlah pemeriksaan, jumlah lokasi terpantau, jumlah kondisi tidak normal, jumlah kepatuhan, jumlah foto, dan jumlah laporan bulanan ditampilkan. Grafik sebaran kondisi sesuai dengan data. Grafik kepatuhan sesi sesuai dengan data. Tabel status terakhir menampilkan satu baris per lokasi. Tabel kepatuhan sesi menampilkan tiga sesi. Ringkasan laporan bulanan dimuat melalui permintaan terpisah. Lokasi tanpa data menampilkan label belum ada.

### 32.3 Kriteria Penerimaan Modul Monitoring Harian

Petugas dapat menambah data pemeriksaan. Admin dapat menambah data pemeriksaan. Data yang disimpan memuat lokasi, tanggal, sesi, waktu, kondisi, keterangan, dan foto. Kombinasi lokasi, tanggal, dan sesi yang sama ditolak. Lokasi nonaktif ditolak pada saat menambah. Keterangan kosong ditolak. Foto yang bukan gambar ditolak. Foto melebihi lima megabyte ditolak. Pengguna lain tidak dapat membuka data milik petugas. Pengguna lain tidak dapat mengubah data milik petugas. Pengguna lain tidak dapat menghapus data milik petugas. Admin dapat mengubah dan menghapus seluruh data. Admin dapat mengekspor data laporan bulanan ke berkas PDF. Modul monitoring harian tidak menyediakan ekspor PDF.

### 32.4 Kriteria Penerimaan Modul Laporan Bulanan

Data laporan bulanan dapat ditambah dan diubah. Validasi bulan berjalan. Lokasi yang tidak ada ditolak. Kondisi yang tidak sesuai katalog bulanan ditolak. Data milik petugas tidak dapat diakses petugas lain. Riwayat menampilkan data sesuai filter bulan. Admin dapat menghapus data. Data hanya berisi data milik pengguna sendiri. Ringkasan menampilkan jumlah dan foto. Berkas PDF dapat dibuat oleh admin. Berkas PDF menampilkan kolom kondisi dan foto. Berkas PDF memakai orientasi mendatar.

### 32.5 Kriteria Penerimaan Modul Lokasi

Admin dapat menambah, mengubah, dan menghapus lokasi monitoring. Admin dapat menambah, mengubah, dan menghapus lokasi bulanan. Nama lokasi ganda ditolak. Lokasi yang memiliki data pemeriksaan tidak dapat dihapus. Lokasi yang memiliki data laporan bulanan tidak dapat dihapus. Lokasi nonaktif tidak muncul pada pilihan lokasi pada formulir pemeriksaan.

### 32.6 Kriteria Penerimaan Modul Pengguna

Admin dapat menambah, mengubah, dan menghapus pengguna. Surel ganda ditolak. Kata sandi terlalu pendek ditolak. Konfirmasi kata sandi yang tidak sama ditolak. Peran tidak dapat diubah pada formulir ubah. Admin tidak dapat menghapus dirinya sendiri. Admin tidak dapat menghapus admin terakhir. Admin tidak dapat menghapus petugas yang memiliki data.

### 32.7 Kriteria Penerimaan Keamanan Foto

Foto baru tersimpan pada disk bukti. Foto baru tidak dapat diakses lewat alamat statis. Admin dapat melihat seluruh foto. Petugas hanya dapat melihat fotonya sendiri. Permintaan foto tanpa login ditolak. Permintaan foto oleh pengguna lain ditolak. Jalur berkas yang menunjuk keluar dari direktori foto ditolak. Perintah pemindahan foto lama memindahkan berkas lama ke disk bukti. Perintah aman dijalankan berulang kali. Nilai penanda disk pada data lama tetap terbaca.

### 32.8 Kriteria Penerimaan Antarmuka

Seluruh halaman setelah login memakai kerangka tampilan bersama. Galat validasi ditampilkan di bawah field yang bermasalah. Galat validasi berbahasa Indonesia. Pesan berhasil ditampilkan setelah proses simpan. Aksi hapus meminta konfirmasi. Tampilan tetap dapat dibaca pada layar sempit. Foto dapat dibuka dari halaman detail dan halaman daftar.

### 32.9 Status Pemenuhan Kriteria

Seluruh kriteria penerimaan di atas telah diimplementasikan dan diverifikasi melalui rangkaian uji fitur. Karena itu versi 2.0 dapat dinyatakan memenuhi kriteria penerimaan yang ditetapkan pada dokumen ini.

---

## 33. PENYESUAIAN DENGAN PRD VERSI 1.0

### 33.1 Kedudukan PRD Versi 1.0

PRD versi 1.0 disusun sebagai dokumen perancangan. Ruang lingkupnya hanya modul monitoring harian. Isinya berupa rancangan, bukan catatan keadaan. PRD versi 1.0 tidak berlaku lagi sebagai rujukan teknis. PRD versi 1.0 tetap berguna sebagai catatan sejarah. PRD versi 1.0 menunjukkan tujuan awal sistem.

### 33.2 Perubahan Cakupan Modul

Versi 1.0 hanya merancang modul monitoring harian. Versi 2.0 menambah lima kelompok kemampuan baru. **Kemampuan pertama** adalah modul laporan bulanan. Modul ini mencatat kondisi air untuk keperluan pelaporan keuangan. Modul ini dilengkapi master lokasi bulanan. Modul ini dilengkapi ekspor berkas PDF. **Kemampuan kedua** adalah modul manajemen pengguna. Modul ini memicu pembatasan peran. Modul ini dilengkapi pengaman penghapusan akun. **Kemampuan ketiga** adalah modul keamanan foto. Modul ini memindahkan foto ke penyimpanan privat. Modul ini menambah kolom penanda disk. Modul ini menambah perintah pemindahan foto lama. **Kemampuan keempat** adalah perluasan dasbor. Dasbor kini punya filter periode dan filter lokasi. Dasbor kini punya enam kartu statistik. Dasbor kini punya dua grafik. Dasbor kini punya tabel status terakhir per lokasi. Dasbor kini punya tabel kepatuhan sesi. Dasbor kini punya ringkasan laporan bulanan yang dimuat terpisah. **Kemampuan kelima** adalah modul lokasi yang dipisah. Master lokasi monitoring dan master lokasi bulanan kini terpisah. Pemisahan ini tidak ada pada versi 1.0.

### 33.3 Perubahan terhadap Struktur Data

Perubahan pertama adalah penghapusan kolom jenis pada tabel lokasi. PRD versi 1.0 mendokumentasikan kolom jenis. Kolom itu menandai apakah lokasi berupa GWT atau kolam. Kolom itu kemudian dihapus melalui migrasi. Penghapusan ini disengaja agar lokasi baru dapat ditambah tanpa batasan jenis. Perubahan kedua adalah penambahan kolom foto disk pada kedua tabel foto. Kolom itu mencatat disk tempat berkas berada. Kolom itu bernilai bawaan disk publik. Nilai itu menjaga kompatibilitas dengan data lama. Perubahan ketiga adalah penambahan kolom lokasi pada tabel laporan bulanan. Kolom itu ditambahkan melalui migrasi terpisah. Kolom itu dapat dikosongkan untuk menampung data lama. Data lama yang kosong kemudian diisi satu lokasi cadangan. Perubahan keempat adalah perubahan batasan kunci asing. Batasan lokasi pada tabel pemeriksaan diubah dari hapus berantai menjadi hapus dibatasi. Perubahan itu menjaga histori pemeriksaan tetap utuh.




### 33.4 Perubahan terhadap Proses Bisnis

PRD versi 1.0 merancang satu proses pemeriksaan harian. PRD versi 2.0 menambah proses laporan bulanan. PRD versi 2.0 menambah proses pengelolaan pengguna. PRD versi 2.0 menambah proses pengelolaan dua master lokasi. PRD versi 2.0 menambah proses konfirmasi sebelum menghapus data. PRD versi 2.0 menambah pembatasan akses berbasis peran dan kepemilikan.

### 33.5 Perubahan terhadap Antarmuka

PRD versi 1.0 merancang satu halaman daftar dan satu halaman formulir. PRD versi 2.0 menambah halaman daftar dan formulir laporan bulanan. PRD versi 2.0 menambah halaman dasbor dengan kartu statistik dan grafik. PRD versi 2.0 menambah halaman ekspor PDF. PRD versi 2.0 memakai lencana berwarna untuk kondisi. PRD versi 2.0 memakai penanda foto privat.

### 33.6 Perubahan terhadap Kebutuhan Keamanan

PRD versi 1.0 belum membahas keamanan foto secara khusus. PRD versi 2.0 menjadikan foto bukti sebagai data privat. PRD versi 2.0 menambahkan jalur akses foto yang melewati pemeriksaan. PRD versi 2.0 menambahkan pembatasan percobaan login. PRD versi 2.0 menambahkan pemeriksaan kepemilikan data. PRD versi 2.0 menambahkan pengaman penghapusan akun. PRD versi 2.0 menambahkan batasan integritas di tingkat basis data.

### 33.7 Perubahan terhadap Pengujian

PRD versi 1.0 tidak menyertakan strategi pengujian. PRD versi 2.0 menyertakan strategi pengujian. PRD versi 2.0 mencantumkan jumlah pengujian pada setiap bidang. PRD versi 2.0 mencantumkan hasil verifikasi terakhir.

### 33.8 Ringkasan Keselarasan



---

## 34. TEMUAN DAN CATATAN

Bagian ini merekam hal-hal yang ditemukan pada implementasi dan belum diselesaikan pada versi 2.0. Temuan ini tidak menjadi bagian dari ruang lingkup versi 2.0, melainkan dicatat agar tidak hilang saat terjadi pergantian maintainer.

### 34.1 Filter Kondisi dan Petugas Belum Tersedia

PRD versi 1.0 menyebut kebutuhan pencarian data berdasarkan tanggal, lokasi, kondisi, dan petugas. Pada implementasi saat ini, halaman histori monitoring hanya menyediakan filter tanggal dan filter lokasi. Filter kondisi dan filter petugas belum tersedia. Dasbor juga belum menyediakan kedua filter tersebut. Konsekuensinya, pencarian kondisi tertentu pada periode tertentu masih memerlukan penyaringan manual.

### 34.2 Konstanta Status Peringatan Tidak Digunakan

Model pemeriksaan air mendeklarasikan konstanta pemetaan status peringatan. Konstanta itu memetakan kondisi normal menjadi status normal, dan memetakan kedua kondisi lainnya menjadi status perlu perhatian. Pemetaan tersebut sebenarnya sudah diterapkan, tetapi tidak melalui konstanta itu. Status dihitung langsung di dalam pengendali. Akibatnya, konstanta tersebut hanya dideklarasikan dan Konstanta tersebut sebaiknya dihapus atau dipakai ulang agar tidak membingungkan.

### 34.3 Perhitungan Ringkasan Bulanan pada Dasbor Mengulang Kueri

Ringkasan laporan bulanan pada dasbor menghitung jumlah per kategori kondisi dengan mengulang kueri untuk setiap kategori. Jumlah kueri bertambah seiring bertambahnya jumlah kategori. Pada jumlah kategori saat ini, pengulangannya belum terasa berat, tetapi akan terasa bila jumlah kategori bertambah.

### 34.4 Tabel PDF Tidak Memuat Kolom Petugas

Tabel pada halaman antarmuka memuat kolom petugas untuk role admin. Tabel pada berkas PDF laporan bulanan tidak memuat kolom petugas. Perbedaan ini disengaja agar lebar tabel tetap proporsional dan foto tetap terbaca. Identitas petugas dapat dilihat pada halaman antarmuka.

### 34.5 Kebutuhan MySQL Versi Delapan

Tabel status terakhir pada dasbor dihitung memakai fungsi jendela ROW_NUMBER. Fungsi itu tersedia mulai MySQL versi delapan. MySQL versi lima dan MariaDB versi lama tidak menyediakan fungsi tersebut. Instalasi pada lingkungan dengan basis data yang lebih lama akan gagal menampilkan bagian tersebut. Kebutuhan versi basis data ini perlu disampaikan kepada pengelola sistem sebelum instalasi.

### 34.6 Foto Lama Tetap Tersimpan pada Disk Publik

Sistem sudah menyimpan foto baru pada disk bukti. Namun foto lama yang dibuat sebelum perpindahan masih berada pada disk publik. Foto lama itu masih dapat dibaca, karena kolom penanda disk memiliki nilai bawaan disk publik. Foto lama juga masih dapat diakses lewat jalur statis pada disk publik. Keamanan foto lama baru ikut tercapai setelah perintah pemindahan dijalankan. Perlu dipastikan bahwa perintah pemindahan sudah dijalankan pada seluruh lingkungan yang dipakai.

### 34.7 Pustaka Antarmuka Dimuat dari Jaringan

Pustaka antarmuka, pustaka ikon, dan pustaka grafik dimuat dari layanan konten daring. Setiap pengguna pertama kali membuka halaman perlu mengunduh berkas pustaka tersebut. Pengguna tanpa jaringan tidak dapat memakai sistem. Pemuatan pustaka dari jaringan juga bergantung pada ketersediaan layanan tersebut. Untuk pemakaian lapangan yang tidak selalu tersedia jaringan, disarankan menyimpan berkas pustaka di dalam proyek.

### 34.8 Master Lokasi Bulanan Tidak Membatasi Status Aktif

Validasi lokasi pada modul laporan bulanan hanya memeriksa keberadaan lokasi. Validasi itu tidak memeriksa status lokasi. Modul pemeriksaan harian berbeda, karena memaksa lokasi yang dipilih berstatus aktif. Akibatnya, laporan bulanan dapat diisi dengan lokasi yang sudah dinonaktifkan. Perbedaan ini disengaja agar data lama tetap dapat ditranskripsikan, namun perlu diketahui pengelola.

### 34.9 Daftar Pengguna Tanpa Pencarian

### 34.10 Keterangan Tidak Dibatasi Panjang

Kolom keterangan pada kedua tabel data bersifat wajib dan tidak dibatasi panjangnya. Tidak ada batas jumlah karakter pada sisi server maupun basis data. Keterangan yang sangat panjang dapat membuat tampilan tabel dan dokumen PDF melebar. Pembatasan jumlah karakter perlu dipertimbangkan.

### 34.11 Catatan Penutup Temuan

Seluruh temuan di atas tidak menghambat fungsi utama sistem. Temuan bersifat kosmetik, dokumentatif, atau berkaitan dengan performa. Tidak ada temuan yang menyebabkan kegagalan pada alur utama.

## 35. BACKLOG PERBAIKAN YANG DIREKOMENDASIKAN

### 35.1 Prioritas Tinggi

**Butir pertama** adalah menjalankan perintah pemindahan foto lama pada seluruh lingkungan yang dipakai. Pemindahan Penjemputan foto lama membuat privasi foto benar-benar tercapai. **Butir kedua** adalah menonaktifkan mode debug pada lingkungan produksi. Mode debug dapat menampilkan pesan galat internal. **Butir ketiga** adalah memastikan basis data yang dipakai adalah MySQL versi delapan atau lebih baru. Bagian status terakhir pada dasbor membutuhkan versi tersebut. **Butir keempat** adalah menyimpan kata sandi akun awal yang kuat. Kata sandi bawaan untuk pengembangan tidak boleh dipakai pada produksi. **Butir kelima** adalah mencadangkan basis data dan penyimpanan foto secara terjadwal. Pencadangan di dalam aplikasi belum dikonfigurasi.

### 35.2 Prioritas Sedang

**Butir pertama** adalah menambah filter kondisi dan filter petugas pada histori monitoring. Butir ini menjawab kebutuhan yang sudah disebutkan pada PRD versi 1.0. **Butir kedua** adalah menambah kolom petugas pada tabel PDF laporan bulanan. Kolom itu membantu penelusuran siapa yang melakukan pencatatan. **Butir ketiga** adalah mengurangi pengulangan kueri pada ringkasan bulanan dasbor. Pengulangan itu perlu diganti dengan satu kueri pengelompokan. **Butir keempat** adalah menghapus konstanta status peringatan yang tidak digunakan. **Butir kelima** adalah menambah pencarian pada daftar pengguna. **Butir kelima** adalah menyimpan berkas pustaka antarmuka di dalam proyek agar sistem tetap dapat dipakai tanpa jaringan.

### 35.3 Prioritas Rendah


**Butir pertama** adalah membatasi panjang kolom keterangan. Batasan itu menjaga tampilan tabel dan dokumen PDF tetap rapi. **Butir kedua** adalah menyimpan berkas pustaka antarmuka di dalam proyek. Penyimpanan lokal membuat sistem tetap dapat dipakai tanpa jaringan. **Butir ketiga** adalah menulis policy retensi data. Policy itu mengatur kapan arsip lama dipindahkan atau dihapus. **Butir keempat** adalah menambah catatan audit untuk perubahan data. Catatan audit memudahkan penelusuran siapa mengubah apa. **Butir kelima** adalah menyamakan pembatasan lokasi aktif pada kedua modul. Penyamaan itu membuat aturan validasi lebih mudah diingat.

### 35.4 Catatan tentang Prioritas

Prioritas tinggi berisi hal yang harus disiapkan sebelum sistem dipakai secara nyata. Prioritas sedang berisi hal yang memperbaiki pengalaman dan ketelitian. Prioritas rendah berisi hal yang memperbaiki kerapian dan keterbacaan dokumen.

---

## 36. KESIMPULAN




### 36.1 Ringkasan Capaian

Sistem monitoring kondisi GWT, kolam air bersih, dan laporan bulanan air telah dibangun dan berjalan. Sistem ini menggantikan pencatatan manual dengan pencatatan digital yang terstruktur. Sistem ini menyimpan hasil pemeriksaan harian sebanyak tiga kali sehari. Sistem ini menyimpan laporan bulanan untuk keperluan pelaporan keuangan. Sistem ini menyimpan foto bukti secara privat dan hanya dapat diakses oleh pihak yang berhak. Sistem ini membatasi akses berdasarkan peran dan kepemilikan data. Sistem ini menyediakan dasbor yang menampilkan ringkasan, grafik, dan status terakhir per lokasi. Sistem ini menyediakan ekspor laporan bulanan ke berkas PDF. Seluruh perilaku tersebut telah diverifikasi melalui seratus tiga puluh delapan pengujian fitur.

### 36.2 Posisi PRD Versi 2.0

Dokumen ini adalah dokumentasi as-built. Dokumen ini menggambarkan sistem yang benar-benar ada, bukan rencana yang akan dibangun.


### 36.3 Dokumen Sumber

Seluruh uraian pada dokumen ini bersumber dari berkas definisi rute, berkas pengendali setiap modul, model beserta konstantanya, kelas permintaan formulir, berkas tampilan, berkas migrasi basis data, konfigurasi penyimpanan berkas, berkas pengujian fitur, dan berkas konfigurasi proyek.



### 36.4 Penutup

Dokumen ini disusun sebagai rujukan utama Sistem Monitoring Kondisi GWT, Kolam Air Bersih, dan Laporan Bulanan Air. Dokumen ini menggantikan PRD versi 1.0 sebagai acuan teknis. Dokumen ini perlu diperbarui apabila terdapat perubahan pada sistem. Pembaruan dokumen ini sebaiknya dilakukan bersama dengan perubahan kode, agar uraian pada dokumen tidak tertinggal dari implementasi.
