# Antrian-Poli
Antrian Poli Klinik
Sudah menggunakan text to speech menggunakan librari dari responsivevoice.js
data yang ditampilkan sudah sesuai jam praktek dokter
jalankan Query SQL berikut untuk update enum agar suara tidak looping

ALTER TABLE `antripoli` CHANGE `status` `status` ENUM('0','1','2','3') CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;


## Timestamp pemanggilan untuk e-Pasien

Versi ini mencatat waktu event ketika nomor baru dipindahkan ke `antripoli.status='2'` ke tabel `portal_queue_call_history`. Jalankan SQL berikut sekali pada database SIMRS yang sama dengan portal e-Pasien:

```sql
CREATE TABLE IF NOT EXISTS portal_queue_call_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tgl_registrasi DATE NOT NULL,
    kd_dokter VARCHAR(20) NOT NULL,
    kd_poli CHAR(5) NOT NULL,
    no_rawat VARCHAR(17) NOT NULL,
    no_reg VARCHAR(20) NOT NULL,
    called_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_queue_call_lookup (tgl_registrasi, kd_poli, kd_dokter, called_at),
    KEY idx_queue_call_rawat (no_rawat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Logging history bersifat non-blocking: jika tabel belum tersedia, proses pemanggilan antrean utama tetap berjalan.
