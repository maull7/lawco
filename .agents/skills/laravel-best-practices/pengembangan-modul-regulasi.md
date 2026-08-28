Implementasikan pengembangan Modul Manajemen Regulasi dengan tujuan meningkatkan pengelolaan regulasi, klasifikasi, hubungan antar regulasi, dan kesiapan integrasi AI di masa depan.

Pada fitur Upload Regulasi, tambahkan field metadata sebagai berikut: Nomor Regulasi, Judul Regulasi, Jenis Regulasi, Tahun Regulasi, Category, dan Sub Category. Nomor Regulasi, Judul Regulasi, Jenis Regulasi, Tahun Regulasi, dan File Regulasi wajib diisi. Field Sub Category harus mendukung multi-select (checkbox) karena satu regulasi dapat masuk ke lebih dari satu sub category. File regulasi yang diunggah minimal mendukung format PDF.

Tambahkan fitur Master Category dan Sub Category. Admin dapat membuat, mengubah, menghapus, mengaktifkan, dan menonaktifkan Sub Category. Setiap Category dapat memiliki banyak Sub Category. Saat admin memilih Category pada form regulasi, sistem harus menampilkan daftar Sub Category yang sesuai dengan Category tersebut. Karena satu regulasi dapat memiliki lebih dari satu Sub Category, gunakan relasi many-to-many antara regulasi dan sub category.

Tambahkan fitur Master Jenis Regulasi. Admin dapat mengelola data jenis regulasi seperti Undang-Undang, Peraturan Pemerintah, Peraturan OJK, Surat Edaran OJK, Peraturan Menteri, Keputusan Menteri, dan jenis lainnya. Pada setiap Jenis Regulasi wajib terdapat pengaturan Level Regulasi yang dapat dipilih dari Level 1 sampai Level 5.

Implementasikan sistem Hierarki Regulasi dengan ketentuan bahwa Level 1 merupakan level tertinggi dan Level 5 merupakan level terendah. Struktur level adalah: Level 1, Level 2, Level 3, Level 4, dan Level 5. Informasi level ini akan digunakan sebagai dasar analisis AI dan validasi regulasi di masa depan. Regulasi pada level yang lebih rendah tidak boleh bertentangan dengan regulasi yang berada pada level lebih tinggi.

Tambahkan tombol "Peraturan Terkait" pada halaman tambah dan edit regulasi. Ketika tombol tersebut diklik, tampilkan modal yang memungkinkan admin mencari regulasi berdasarkan nomor regulasi, judul regulasi, tahun regulasi, maupun jenis regulasi. Admin dapat memilih satu atau lebih regulasi menggunakan checkbox. Relasi yang dipilih akan disimpan sebagai hubungan antar regulasi sehingga pada halaman detail regulasi dapat ditampilkan daftar regulasi yang saling terkait.

Tambahkan tombol "Dokumen Tambahan" pada halaman tambah dan edit regulasi. Ketika tombol diklik, tampilkan form upload dokumen pendukung. Dokumen tambahan dapat berupa ringkasan regulasi, penjelasan regulasi, interpretasi hukum, FAQ, dokumen sosialisasi, lampiran, maupun dokumen pendukung lainnya. Setiap dokumen tambahan memiliki field Nama Dokumen, Jenis Dokumen, dan File. Sistem harus mendukung upload file PDF, DOCX, XLSX, dan PPTX. Satu regulasi dapat memiliki banyak dokumen tambahan.

Pada halaman detail regulasi, tampilkan seluruh metadata regulasi, daftar Sub Category yang dipilih, Jenis Regulasi beserta Level Regulasinya, daftar Peraturan Terkait, dan daftar Dokumen Tambahan yang dapat di-preview atau diunduh.



- Pada halaman dengan url http://localhost:8001/sectors tambahkan saat nama sektor diklik maka akan menampilkan informasi deskripsi dan daftar kategori yang ada di dalam sektor tersebut. Dengan modal atau halaman baru saya serahkan ke anda lebih bagusnya gimana
- http://localhost:8001/regulations/create di halaman tambahkan pilihan “Sektor”. Setelah sektor dipilih maka akan menampilkan Category dan Sub Category berdasarkan Sektor tersebut.
- Supaya ditambahkan Menu di halaman landing page depan dashboard “Sektor” dan Label InvestalawCo menjadi LawCo.