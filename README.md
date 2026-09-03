# Antrian-Poli

Aplikasi display Antrian Poli dengan text-to-speech dan filter poli/dokter.

## Database

Pastikan tabel `antripoli` menggunakan status berikut:

- `0` = belum diproses
- `1` = menunggu dipanggil
- `2` = sedang dipanggil
- `3` = selesai

Jika diperlukan, jalankan:

```sql
ALTER TABLE `antripoli`
CHANGE `status` `status` ENUM('0','1','2','3')
CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;
```

### Call Queue

Mulai Phase 1A.1, pemanggilan suara menggunakan tabel aplikasi `antrianpoli_call_queue` agar dua request `panggil` tidak dapat menimpa panggilan yang masih berjalan.

Jalankan sekali SQL berikut pada database yang sama:

```sql
-- file: database/001_call_queue.sql
CREATE TABLE IF NOT EXISTS antrianpoli_call_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    no_rawat VARCHAR(17) NOT NULL,
    call_token CHAR(32) NOT NULL,
    status ENUM('playing', 'done', 'cancelled') NOT NULL DEFAULT 'playing',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL DEFAULT NULL,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_call_token (call_token),
    KEY idx_call_status_created (status, created_at),
    KEY idx_call_no_rawat (no_rawat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Alur pemanggilan

`panggil` sekarang bersifat **idempotent**:

1. Request pertama mengambil nomor `status=1` dan membuat satu `playing` call.
2. Request berikutnya selama TTS masih berjalan mengembalikan **call yang sama**, bukan nomor berikutnya.
3. Browser mengirim `ack` setelah TTS selesai.
4. Baru setelah ACK, `antripoli.status` berubah dari `2` menjadi `3` dan nomor berikutnya dapat dipanggil.
5. Browser mengirim heartbeat selama call aktif sehingga lock dapat dipantau.

Dengan demikian skenario A dipanggil lalu request B masuk bersamaan tidak lagi menghasilkan A langsung dianggap selesai dan B menggantikan suara A.
