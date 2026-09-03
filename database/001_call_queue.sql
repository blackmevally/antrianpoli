-- AntrianPoli: queue pemanggilan yang terpisah dari tabel SIMRS `antripoli`.
-- Jalankan sekali pada database aplikasi/SIMRS yang digunakan display.

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
