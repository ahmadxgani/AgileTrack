# Rancangan AgileTrack

**Status:** Menunggu persetujuan pengguna  
**Sumber kebutuhan:** `SRS_AgileTrack_Kelompok_6_MPSI (1).docx` dan preferensi UI shadcn/ui (`https://ui.shadcn.com/llms.txt`)  
**Tanggal:** 2026-10-31
**UI:** shadcn/ui melalui starter kit React resmi Laravel

## Tujuan dan alasan

AgileTrack membantu tim kecil mengelola pekerjaan Agile dalam satu tempat: dari proyek dan backlog, ke sprint dan papan tugas, sampai meninjau hasil serta memantau manfaat. Sistem ditujukan untuk benar-benar dipakai tim, bukan hanya untuk demo. Karena ini proyek akademik nonkomersial, implementasi perlu mudah dijalankan dan dirawat oleh kelompok, tanpa infrastruktur atau abstraksi yang tidak dibutuhkan.

SRS menjadi sumber kebutuhan. Rancangan ini mengurutkan implementasi secara bertahap; tidak menganggap semua 28 kebutuhan fungsional harus selesai sekaligus sebelum sistem dapat digunakan.

## Pengguna dan konteks

Peran awal mengikuti SRS: administrator, Owner/Sponsor, Product Owner, Project Manager, Business Analyst, anggota tim, QA, dan pelanggan/klien. Satu akun dapat memiliki lebih dari satu peran melalui relasi sederhana, karena anggota tim kecil dapat merangkap tugas. Hak akses mengikuti peran dan keterkaitan proyek; pelanggan hanya melihat hasil yang dibagikan kepadanya. Default awal: review dicatat oleh pengguna internal yang berwenang; akun pelanggan eksternal baru diaktifkan setelah alur dan batas data divalidasi. Matriks izin rinci harus disepakati sebelum penggunaan nyata, termasuk akses internal.

Asumsi desain: kelompok kecil memelihara satu aplikasi web untuk satu organisasi/tim, dengan beberapa proyek. Belum ada kebutuhan untuk multi-tenant, aplikasi mobile native, integrasi sistem eksternal, atau skala komersial.

## Sasaran versi awal

Versi awal harus dapat dijalankan secara lokal, menyimpan data secara persisten, memiliki autentikasi, dan mendukung alur tim yang paling sering dipakai:

1. Administrator berwenang membuat, memperbarui, menonaktifkan akun serta menetapkan peran; pengguna masuk dan melihat proyek yang boleh diakses.
2. Pengguna berwenang membuat proyek dan mengelola backlog.
3. Tim membuat sprint, memilih item pekerjaan, menetapkan satu atau lebih penanggung jawab, dan memperbarui status pada Kanban.
4. Project Manager dan Owner melihat ringkasan status pekerjaan tanpa formula progres yang belum disepakati.
5. Tim mencatat jadwal dan catatan sprint review, umpan balik, keputusan setuju/perlu perbaikan, serta tindak lanjut melalui backlog.

Sediakan data contoh yang jelas ditandai sebagai demo untuk memudahkan evaluasi. Jangan membuat akun demo dengan kredensial tetap aktif di lingkungan produksi.

## Tahapan produk

### Fondasi dan alur kerja pertama

- Kerangka aplikasi, konfigurasi lingkungan, database, migrasi, factory/seed data, dan petunjuk menjalankan aplikasi.
- Login/logout; administrasi akun dasar untuk membuat, memperbarui, menonaktifkan akun, dan menetapkan satu atau lebih peran; pembatasan akses berbasis peran/proyek.
- Proyek, backlog, sprint, penugasan, dan perubahan status Kanban.
- Review sprint minimal: jadwal, catatan, umpan balik, keputusan (disetujui/perlu perbaikan), pemberi keputusan/waktu, serta tindak lanjut melalui backlog.
- Dashboard ringkas berbasis hitungan/status aktual, tanpa menyatakan formula persentase progres yang belum divalidasi.
- Uji otomatis untuk aturan validasi, akses, dan alur kerja utama.

### Iterasi berikutnya sebelum sistem dianggap lengkap terhadap SRS

- Benefits tracker untuk pemilik manfaat, KPI, target, periode, dan realisasi.
- Riwayat pengiriman/inkremen, laporan/ekspor, notifikasi, audit log, konfigurasi dasar, pencadangan harian dengan pemeriksaan status dan uji pemulihan, serta pencatatan insiden pemeliharaan.
- Impor CSV sederhana hanya bila prioritas dan batasannya disetujui.
- Grafik burndown/velocity hanya setelah definisi satuan estimasi dan perhitungan disepakati.
- Antarmuka Indonesia dan Inggris, deployment staging/produksi berbasis cloud, dan pemeriksaan operasional.

Urutan pengiriman adalah usulan untuk validasi Product Owner, bukan penghapusan atau penurunan prioritas dari SRS. FR-20 memiliki perbedaan prioritas antara §3.3 dan tabel FR, dan format ekspor FR-21/§7.2 perlu diklarifikasi. Semua kebutuhan Must Have dan kriteria penerimaan SRS tetap berlaku pada penyelesaian proyek; UAT tertulis, tanpa defect kritis/tinggi terbuka, dan penerimaan deliverable.

## Bukan sasaran

- Aplikasi mobile native.
- Integrasi ERP, payroll, keuangan, AI/prediksi, atau migrasi data besar.
- Arsitektur microservices, Kubernetes, event bus, multi-tenant, atau pemisahan layanan.
- Real-time collaboration/presence, workflow builder, atau permission engine generik.
- Menetapkan target kinerja, retensi backup, atau formula progres/benefit yang tidak ditentukan SRS tanpa persetujuan pemilik kebutuhan.
- Menganggap ekspor PDF/Excel, email, cloud hosting, dan backup produksi sudah terpenuhi hanya karena scaffold lokal tersedia.

## Pilihan teknis awal (keputusan implementasi)

Pengguna menetapkan penggunaan shadcn/ui untuk komponen UI. Default implementasi yang saya rekomendasikan untuk mendukungnya:

- **Backend dan aplikasi:** Laravel versi stabil yang kompatibel dengan PHP yang tersedia, sebagai monolit modular. Gunakan pola MVC dan fitur framework secara langsung; tidak membuat lapisan abstraksi tambahan tanpa kebutuhan. Versi Laravel/PHP harus dipastikan kompatibel sebelum scaffold.
- **Antarmuka:** React + TypeScript melalui Laravel React starter kit, Inertia.js, dan Vite, dengan Tailwind CSS. Gunakan komponen shadcn/ui yang dipasang ke source project melalui CLI dan disesuaikan seperlunya. shadcn/ui bukan library komponen runtime; komponennya berupa kode milik aplikasi. Hindari SPA/API terpisah: Inertia menghubungkan halaman React ke Laravel dalam satu aplikasi.
- **Database:** PostgreSQL sebagai database utama agar model relasional konsisten untuk pemakaian tim; Docker Compose dapat menyediakan database lokal bila sesuai lingkungan. Jika Docker menjadi hambatan, dokumentasikan alternatif pengembangan lokal yang sederhana.
- **Autentikasi:** gunakan starter kit Laravel React/Inertia yang kompatibel untuk autentikasi sesi dan hashing password bawaan. Jangan menulis mekanisme kriptografi sendiri. Pendaftaran publik tidak aktif secara default; nonaktifkan route pendaftaran di backend dan hapus referensi route terkait di UI agar route typing/build tidak gagal. Akun dibuat melalui seed lokal atau administrasi terbatas.
- **Otorisasi dan form:** gunakan peran eksplisit dan policy/gate Laravel; setiap query/mutasi data proyek harus memeriksa akses. Untuk formulir, gunakan `useForm`/`Form` milik Inertia yang dipadukan dengan komponen input/field shadcn/ui, sehingga state submit dan error validasi Laravel tidak membutuhkan library form tambahan.
- **Kualitas:** PHPUnit/Pest mengikuti pilihan bawaan scaffold; gunakan pemeriksaan TypeScript, formatter/linter minimal, dan CI sederhana bila konfigurasi repositori mendukungnya.
- **Deployment:** satu deployment aplikasi Laravel yang menyajikan aset Vite hasil build; mulai tanpa Inertia SSR atau server frontend terpisah. Sediakan konfigurasi environment dan panduan staging/produksi tanpa mengunci ke vendor cloud tertentu.

## Bentuk domain dan pengalaman pengguna

Entitas inti awal: User, Role/UserRole, Project, ProjectMembership, BacklogItem, Sprint, Review/Decision, Comment, serta riwayat status untuk penelusuran progres. Manfaat/KPI menjadi modul berikutnya. Gunakan foreign key, validasi server, dan transaksi database untuk perubahan yang saling terkait. Hindari duplikasi agregat dashboard; hitung dari data sumber sampai ada bukti kebutuhan performa.

Status Kanban usulan awal: `To do`, `In progress`, `Done`. Nilai status dipusatkan agar dapat dikonfigurasi kemudian, tetapi tidak membangun editor kolom generik pada versi awal. Prioritas backlog usulan awal: `Low`, `Medium`, `High`, `Urgent`. Product Owner perlu memvalidasi nama/status ini, proyek yang wajib untuk item, aturan sprint aktif/ditutup, dan apakah item boleh punya lebih dari satu penanggung jawab sebelum implementasi aturan terkait. Sampai itu diputuskan, implementer jangan mengunci aturan bisnis yang mahal diubah.

Navigasi awal: Dashboard, Projects, Backlog, Sprint/Kanban, Reviews, Benefits, dan Account. Modul yang belum tersedia ditandai belum aktif, bukan tombol palsu. Gunakan komponen shadcn/ui yang relevan (misalnya Sidebar, Button, Card, Table, Dialog, Form/Input, Badge, Tabs, Toast) dan pertahankan aksesibilitas serta konsistensi desain. Antarmuka responsif untuk browser desktop dan layar kecil; dukungan Bahasa Indonesia dan Inggris dapat dimulai dengan struktur terjemahan dan bahasa awal Indonesia.

## Keamanan dan operasional

- Semua halaman privat memerlukan autentikasi; otorisasi dicek pada setiap operasi baca/tulis sesuai kebutuhan akses.
- Validasi input di server, CSRF/session protection framework, dan escaping output bawaan.
- Rahasia konfigurasi hanya melalui environment; `.env` tidak dilacak git dan `.env.example` tidak berisi rahasia.
- Gunakan HTTPS pada deployment; jangan klaim keamanan atau ketersediaan produksi sebelum konfigurasi hosting ditinjau.
- Jangan simpan password plaintext. Hindari membocorkan detail teknis atau data sensitif ke pesan error.
- Backup harian dan pemulihan adalah kebutuhan SRS untuk operasional; scaffold menyertakan prosedur yang dapat dijalankan/didokumentasikan dan menyebut dengan jelas bila penjadwalan bergantung pada hosting.
- Log aplikasi dibatasi pada informasi operasional yang perlu; jangan merekam password, token, atau isi sensitif.

## Kriteria keberhasilan scaffold

- Instalasi dan menjalankan aplikasi lokal dijelaskan dari checkout bersih.
- Migrasi database dan seed data dapat dijalankan berulang secara aman pada lingkungan development.
- Pengguna dapat login; administrasi akun membuat/memperbarui/menonaktifkan akun dan menetapkan peran; uji memastikan pengguna tanpa izin tidak bisa membaca atau mengubah proyek di luar aksesnya.
- Alur proyek -> backlog -> sprint -> Kanban -> review/keputusan berjalan dan divalidasi otomatis.
- Dashboard merangkum status aktual tanpa klaim formula yang belum ditetapkan, dan tampilan dapat digunakan pada ukuran layar desktop dan kecil.
- Tidak ada rahasia atau akun demo produksi dalam repository.
- README menyatakan secara jujur fitur yang sudah ada, yang belum ada, asumsi yang perlu divalidasi, serta daftar fase yang tersisa terhadap SRS.

## Verifikasi yang direncanakan

Setelah rancangan disetujui dan implementasi dimulai: jalankan test suite pada database test terisolasi; uji login, route pendaftaran yang dinonaktifkan, otorisasi lintas proyek, validasi tanggal sprint, pembuatan backlog, perpindahan status, dan konsistensi ringkasan dashboard. Sertakan pemeriksaan migration/seed dari database kosong, pemeriksaan TypeScript, production Vite build, dan langkah manual smoke test. Test regresi untuk bug harus gagal sebelum perbaikan dan lulus sesudahnya. Pengujian keamanan/akses hanya pada aplikasi lokal yang dibangun, bukan target eksternal.

## Keputusan pemilik kebutuhan sebelum modul terkait dipakai nyata

Pengguna meminta agar saya menentukan default implementasi, jadi pilihan teknis dan nilai awal usulan ditetapkan di atas. Hal berikut tetap kebijakan pemilik produk/data dan dicatat sebagai validasi sebelum fitur terkait dipakai sungguhan:

- Matriks izin final, proyek yang dapat diakses per peran, dan aturan akses pelanggan.
- Kolom Kanban, aturan penutupan sprint, skala prioritas, field wajib, formula progres, estimasi, dan jumlah penanggung jawab.
- Definisi KPI, satuan/sumber/frekuensi, target, realisasi dan status manfaat.
- Format laporan dan pilihan PDF/Excel; kebutuhan laporan juga perlu diklarifikasi karena prioritas FR-20 berbeda antara bagian SRS.
- Peristiwa notifikasi, browser yang didukung, target waktu respons/pengguna bersamaan, retensi backup, serta pemilik operasional pemulihan dan pencatatan insiden.
- Anggaran/operasional layanan cloud dan email; teknologi boleh dipilih implementer dalam batas yang disepakati.

Catat keputusan dalam backlog atau dokumen kebutuhan bertanggal dan hubungkan ke test case. Tunda hanya fitur yang aturannya belum jelas; jangan aktifkan akses/data nyata dengan izin default yang belum ditinjau.

## Hal yang diserahkan kepada implementer

Implementer dapat memilih struktur file dan versi patch dependency yang kompatibel, dengan tetap mengikuti batasan arsitektur di atas. Bila scaffold saat ini bertentangan dengan pilihan tersebut atau toolchain tidak cocok, berhenti dan laporkan perbedaannya sebelum mengganti pilihan dasar.