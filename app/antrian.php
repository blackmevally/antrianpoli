<?php
require_once('../conf/conf.php');
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');

// 🔹 Filter data untuk tampil di display
$poli_filter   = "'U0075','U0027','INT','OBG'"; // Kode poli
$dokter_filter = "'8007262001385K','D0000002','D0000003','D0000004','D0000005','920108010925','910212090925','8403292211032'"; // Kode dokter (bisa dikosongkan jika ingin semua)

// 🔹 Jam reset sistem
$jamreset = '23:00:00';

if (!isset($_GET['p'])) {
    echo json_encode(["status" => "error", "message" => "Parameter tidak ditemukan"]);
    exit;
}

switch ($_GET['p']) {

    /* =============================================================
       🔹 NOMOR ANTRIAN AKTIF
       ============================================================= */
    case 'nomor':
        $sql = "
            SELECT 
                b.no_reg, a.status, d.nm_poli, c.nm_pasien, 
                a.no_rawat, a.kd_dokter, e.nm_dokter
            FROM antripoli a
            INNER JOIN reg_periksa b ON a.no_rawat = b.no_rawat
            INNER JOIN pasien c ON b.no_rkm_medis = c.no_rkm_medis
            INNER JOIN poliklinik d ON b.kd_poli = d.kd_poli
            INNER JOIN dokter e ON b.kd_dokter = e.kd_dokter
            WHERE d.kd_poli IN ($poli_filter)
            " . (!empty($dokter_filter) ? "AND e.kd_dokter IN ($dokter_filter)" : "") . "
            AND a.status IN ('1','2')
            ORDER BY a.no_rawat ASC 
            LIMIT 1
        ";

        $hasil = bukaquery($sql);

        if (mysqli_num_rows($hasil) > 0) {
            $data = mysqli_fetch_assoc($hasil);
        } else {
            $data = [
                "no_reg" => "000",
                "nm_pasien" => "-",
                "nm_poli" => "-",
                "nm_dokter" => "-",
                "status" => "0"
            ];
        }

        echo json_encode($data);
        break;

    /* =============================================================
       🔹 PEMANGGILAN SUARA (status=1 → 2)
       ============================================================= */
    case 'panggil':
        // This endpoint changes queue state. Keep it POST-only so it cannot be
        // triggered accidentally by a normal GET request. All source IPs are allowed.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo json_encode(["status" => "error", "message" => "Method tidak diizinkan"]);
            break;
        }

        // Endpoint pemanggilan dapat digunakan dari semua IP jaringan.
        // POST tetap diwajibkan agar endpoint tidak terpicu oleh request GET.
        $db = bukakoneksi();
        mysqli_set_charset($db, 'utf8mb4');
        $data = [];

        try {
            if (!mysqli_begin_transaction($db)) {
                throw new RuntimeException('transaction start failed');
            }

            // Lock only the next waiting call so two display clients cannot
            // consume the same patient concurrently.
            $sql = "
                SELECT
                    a.no_rawat, b.no_reg, c.nm_pasien,
                    d.nm_poli, e.nm_dokter,
                    d.kd_poli, e.kd_dokter
                FROM antripoli a
                INNER JOIN reg_periksa b ON a.no_rawat = b.no_rawat
                INNER JOIN pasien c ON b.no_rkm_medis = c.no_rkm_medis
                INNER JOIN poliklinik d ON b.kd_poli = d.kd_poli
                INNER JOIN dokter e ON b.kd_dokter = e.kd_dokter
                WHERE a.status='1'
                AND d.kd_poli IN ($poli_filter)
                " . (!empty($dokter_filter) ? "AND e.kd_dokter IN ($dokter_filter)" : "") . "
                ORDER BY a.no_rawat ASC
                LIMIT 1
                FOR UPDATE
            ";

            $hasil = mysqli_query($db, $sql);
            if ($hasil === false) {
                throw new RuntimeException('queue select failed');
            }

            if (mysqli_num_rows($hasil) > 0) {
                $r = mysqli_fetch_assoc($hasil);

                // Finish the previously displayed call only for this exact
                // poli + doctor pair. Never close status=2 globally.
                $stmtFinish = mysqli_prepare(
                    $db,
                    "UPDATE antripoli a
                     INNER JOIN reg_periksa b ON a.no_rawat = b.no_rawat
                     SET a.status='3'
                     WHERE a.status='2'
                       AND b.kd_poli=?
                       AND b.kd_dokter=?"
                );
                if ($stmtFinish === false) {
                    throw new RuntimeException('finish statement prepare failed');
                }
                mysqli_stmt_bind_param($stmtFinish, 'ss', $r['kd_poli'], $r['kd_dokter']);
                if (!mysqli_stmt_execute($stmtFinish)) {
                    throw new RuntimeException('finish update failed');
                }
                mysqli_stmt_close($stmtFinish);

                // Promote only the selected patient to the currently-called state.
                $stmtCall = mysqli_prepare(
                    $db,
                    "UPDATE antripoli SET status='2' WHERE no_rawat=? AND status='1'"
                );
                if ($stmtCall === false) {
                    throw new RuntimeException('call statement prepare failed');
                }
                mysqli_stmt_bind_param($stmtCall, 's', $r['no_rawat']);
                if (!mysqli_stmt_execute($stmtCall) || mysqli_stmt_affected_rows($stmtCall) !== 1) {
                    mysqli_stmt_close($stmtCall);
                    throw new RuntimeException('call update failed');
                }
                mysqli_stmt_close($stmtCall);

                // Keep the clinical transaction isolated on Khanza.
                // portal_queue_call_history is supplemental portal state and
                // is written only after the Khanza transaction commits.
                $historyEvent = array(
                    'tgl_registrasi' => null,
                    'kd_dokter' => $r['kd_dokter'],
                    'kd_poli' => $r['kd_poli'],
                    'no_rawat' => $r['no_rawat'],
                    'no_reg' => $r['no_reg'],
                );
                $data[] = $r;
            }

            if (!mysqli_commit($db)) {
                throw new RuntimeException('transaction commit failed');
            }
        } catch (Throwable $e) {
            mysqli_rollback($db);
            error_log('antrian panggil error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Gagal memproses pemanggilan"]);
            mysqli_close($db);
            break;
        }

        mysqli_close($db);

        // Best-effort portal history write after the Khanza transaction commits.
        // A history outage must never roll back or block the queue call.
        if (!empty($historyEvent)) {
            $portalDb = bukaPortalKoneksi();
            if ($portalDb) {
                $stmtHistory = mysqli_prepare(
                    $portalDb,
                    "INSERT INTO portal_queue_call_history
                        (tgl_registrasi, kd_dokter, kd_poli, no_rawat, no_reg, called_at)
                     SELECT tgl_registrasi, ?, ?, ?, ?, NOW()
                     FROM reg_periksa
                     WHERE no_rawat=?
                     LIMIT 1"
                );
                if ($stmtHistory) {
                    mysqli_stmt_bind_param(
                        $stmtHistory,
                        'sssss',
                        $historyEvent['kd_dokter'],
                        $historyEvent['kd_poli'],
                        $historyEvent['no_rawat'],
                        $historyEvent['no_reg'],
                        $historyEvent['no_rawat']
                    );
                    if (!mysqli_stmt_execute($stmtHistory)) {
                        error_log('queue portal history insert failed: '.mysqli_stmt_error($stmtHistory));
                    }
                    mysqli_stmt_close($stmtHistory);
                } else {
                    error_log('queue portal history prepare failed');
                }
                mysqli_close($portalDb);
            }
        }

        echo json_encode($data);
        break;

    /* =============================================================
       🔹 DAFTAR POLI AKTIF BERDASARKAN JADWAL DOKTER
       ============================================================= */
    case 'poli':
        $hari = strtoupper(date('l'));
        $map = [
            "SUNDAY" => "AKHAD",
            "MONDAY" => "SENIN",
            "TUESDAY" => "SELASA",
            "WEDNESDAY" => "RABU",
            "THURSDAY" => "KAMIS",
            "FRIDAY" => "JUMAT",
            "SATURDAY" => "SABTU"
        ];
        $hariindo = $map[$hari] ?? "SENIN";
        $jamSekarang = date('H:i:s');

        $sql = "
            SELECT 
                j.kd_poli,
                p.nm_poli,
                j.kd_dokter,
                d.nm_dokter,
                j.jam_mulai,
                j.jam_selesai
            FROM jadwal j
            INNER JOIN poliklinik p ON j.kd_poli = p.kd_poli
            INNER JOIN dokter d ON j.kd_dokter = d.kd_dokter
            WHERE j.hari_kerja = '$hariindo'
            AND j.kd_poli IN ($poli_filter)
            " . (!empty($dokter_filter) ? "AND j.kd_dokter IN ($dokter_filter)" : "") . "
            ORDER BY j.jam_mulai ASC
        ";

        $hasil = bukaquery($sql);
        $data = [];

        if (mysqli_num_rows($hasil) == 0) {
            $data[] = [
                "nm_poli" => "Tidak ada jadwal hari ini",
                "nm_dokter" => "-",
                "data_pasien" => [[
                    "no_reg" => "000",
                    "nm_pasien" => "-"
                ]]
            ];
            echo json_encode($data);
            break;
        }

        while ($r = mysqli_fetch_assoc($hasil)) {
            $jamMulai = $r['jam_mulai'];
            $jamSelesai = $r['jam_selesai'];
            $jamSekarang = date('H:i:s');

            $aktif = ($jamSekarang >= $jamMulai && ($jamSelesai == null || $jamSekarang <= $jamSelesai));
            $belumMulai = ($jamSekarang < $jamMulai);
            $sudahSelesai = ($jamSelesai != null && $jamSekarang > $jamSelesai);

            if ($aktif) {
                // Card bawah harus merepresentasikan PASIEN TERAKHIR DIPANGGIL,
                // bukan pasien dengan no_rawat terbesar.
                // Prioritas:
                // 1) history panggilan hari ini (paling akurat, berdasarkan called_at)
                // 2) status=2 (sedang dipanggil) sebagai fallback jika history belum tersedia
                // 3) status=3 sebagai fallback legacy.
                $pasienData = [];
                // Values originate from the trusted jadwal query above.
                $kdPoli = $r['kd_poli'];
                $kdDokter = $r['kd_dokter'];

                $sqlAntri = "
                    SELECT
                        h.no_reg,
                        c.nm_pasien
                    FROM portal_queue_call_history h
                    INNER JOIN pasien c ON h.no_rawat = c.no_rkm_medis
                    WHERE 1=0
                    LIMIT 1
                ";

                // Gunakan history jika tabel tersedia.
                $dbCheck = bukakoneksi();
                $historyAvailable = false;
                $tableCheck = mysqli_query(
                    $dbCheck,
                    "SELECT 1 FROM information_schema.tables
                     WHERE table_schema = DATABASE()
                       AND table_name = 'portal_queue_call_history'
                     LIMIT 1"
                );
                if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {
                    $historyAvailable = true;
                }
                mysqli_close($dbCheck);

                if ($historyAvailable) {
                    $sqlAntri = "
                        SELECT
                            h.no_reg,
                            c.nm_pasien
                        FROM portal_queue_call_history h
                        INNER JOIN reg_periksa b
                            ON h.no_rawat = b.no_rawat
                        INNER JOIN pasien c
                            ON b.no_rkm_medis = c.no_rkm_medis
                        WHERE h.tgl_registrasi = CURDATE()
                          AND h.kd_poli = '{$kdPoli}'
                          AND h.kd_dokter = '{$kdDokter}'
                        ORDER BY h.called_at DESC, h.id DESC
                        LIMIT 1
                    ";
                    $antri = bukaquery($sqlAntri);
                } else {
                    $antri = bukaquery("
                        SELECT b.no_reg, c.nm_pasien
                        FROM antripoli a
                        INNER JOIN reg_periksa b ON a.no_rawat = b.no_rawat
                        INNER JOIN pasien c ON b.no_rkm_medis = c.no_rkm_medis
                        WHERE a.status='2'
                          AND b.kd_poli = '{$kdPoli}'
                          AND b.kd_dokter = '{$kdDokter}'
                        ORDER BY a.no_rawat DESC
                        LIMIT 1
                    ");
                }

                if (mysqli_num_rows($antri) === 0 && $historyAvailable) {
                    // History tersedia tetapi belum mempunyai event hari ini:
                    // fallback ke pasien yang sedang dipanggil.
                    $antri = bukaquery("
                        SELECT b.no_reg, c.nm_pasien
                        FROM antripoli a
                        INNER JOIN reg_periksa b ON a.no_rawat = b.no_rawat
                        INNER JOIN pasien c ON b.no_rkm_medis = c.no_rkm_medis
                        WHERE a.status='2'
                          AND b.kd_poli = '{$kdPoli}'
                          AND b.kd_dokter = '{$kdDokter}'
                        ORDER BY a.no_rawat DESC
                        LIMIT 1
                    ");
                }

                if (mysqli_num_rows($antri) > 0) {
                    $p = mysqli_fetch_assoc($antri);
                    $pasienData[] = [
                        "no_reg" => $p["no_reg"],
                        "nm_pasien" => $p["nm_pasien"]
                    ];
                } else {
                    $pasienData[] = [
                        "no_reg" => "000",
                        "nm_pasien" => "Belum ada antrian"
                    ];
                }

                $data[] = [
                    "nm_poli" => $r["nm_poli"],
                    "nm_dokter" => $r["nm_dokter"],
                    "data_pasien" => $pasienData
                ];
            } elseif ($belumMulai) {
                $jamText = date("H:i", strtotime($jamMulai)) .
                    ($jamSelesai ? " - " . date("H:i", strtotime($jamSelesai)) : "");
                $data[] = [
                    "nm_poli" => $r["nm_poli"],
                    "nm_dokter" => $r["nm_dokter"],
                    "data_pasien" => [[
                        "no_reg" => $jamText,
                        "nm_pasien" => "Belum mulai"
                    ]]
                ];
            } elseif ($sudahSelesai) {
                $jamText = date("H:i", strtotime($jamMulai)) .
                    ($jamSelesai ? " - " . date("H:i", strtotime($jamSelesai)) : "");
                $data[] = [
                    "nm_poli" => $r["nm_poli"],
                    "nm_dokter" => $r["nm_dokter"],
                    "data_pasien" => [[
                        "no_reg" => $jamText,
                        "nm_pasien" => "Selesai"
                    ]]
                ];
            }
        }

        echo json_encode($data);
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Parameter tidak valid"]);
}
?>
