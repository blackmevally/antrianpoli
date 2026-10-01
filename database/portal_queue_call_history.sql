-- Portal-owned queue call history.
-- Target DB: pasienrspm on 192.168.9.41.
-- Clinical queue tables remain in Khanza on 192.168.9.21.

CREATE TABLE IF NOT EXISTS portal_queue_call_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tgl_registrasi DATE NOT NULL,
    kd_dokter VARCHAR(30) NOT NULL,
    kd_poli VARCHAR(20) NOT NULL,
    no_rawat VARCHAR(30) NOT NULL,
    no_reg VARCHAR(30) NOT NULL,
    called_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_queue_history_slot (tgl_registrasi, kd_poli, kd_dokter, called_at, id),
    KEY idx_queue_history_rawat (no_rawat, called_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
