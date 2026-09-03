<?php
require_once('../conf/conf.php');
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');

// =============================================================
// FILTER DISPLAY
// =============================================================
$poli_filter   = "'U0006','U0131','UK108','U0021','U0178','U0107','U0003','U1025','U0075'";
$dokter_filter = "'8201232007405K','D0000093','911121191125','89061223080001','690923180912','870413180101','8007262001385K','D0000013','790316180912','002010002','D0000086','920108010925','910212090925','8403292211032'";

$jamreset = '23:00:00';

if (!isset($_GET['p'])) {
    echo json_encode(["status" => "error", "message" => "Parameter tidak ditemukan"]);
    exit;
}

// =============================================================
// HELPER: escape nilai SQL sederhana
// =============================================================
function sql_escape($value) {
    return addslashes((string)$value);
}

switch ($_GET['p']) {

    // =========================================================
    // NOMOR ANTRIAN AKTIF
    // =========================================================
    case 'nomor':
        $sql = "
            SELECT
                b.no_reg,
                a.status,
                d.nm_poli,
                c.nm_pasien,
                a.no_rawat,
                a.kd_dokter,
                e.nm_dokter
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
                "no_reg"    => "000",
                "nm_pasien" => "-",
                "nm_poli"   => "-",
                "nm_dokter" => "-",
                "status"    => "0"
            ];
        }

        echo json_encode($data);
        break;

    // =========================================================
    // PEMANGGILAN SUARA
    // =========================================================
    case 'panggil':
        $sql = "
            SELECT
                a.no_rawat,
                b.no_reg,
                c.nm_pasien,
                d.nm_poli,
                e.nm_dokter
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
        ";

        $hasil = bukaquery($sql);
        $data = [];

        if (mysqli_num_rows($hasil) > 0) {
            $r = mysqli_fetch_assoc($hasil);
            $data[] = $r;

            bukaquery2("UPDATE antripoli SET status='3' WHERE status='2'");
            bukaquery2("UPDATE antripoli SET status='2' WHERE no_rawat='" . sql_escape($r['no_rawat']) . "'");
        }

        echo json_encode($data);
        break;

    // =========================================================
    // DAFTAR POLI + DOKTER HARI INI
    // SATU KOMBINASI kd_poli + kd_dokter = SATU CARD
    // =========================================================
    case 'poli':
        $hari = strtoupper(date('l'));

        $map = [
            "SUNDAY"    => "AKHAD",
            "MONDAY"    => "SENIN",
            "TUESDAY"   => "SELASA",
            "WEDNESDAY" => "RABU",
            "THURSDAY"  => "KAMIS",
            "FRIDAY"    => "JUMAT",
            "SATURDAY"  => "SABTU"
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
            ORDER BY j.jam_mulai ASC, j.kd_poli ASC, j.kd_dokter ASC
        ";

        $hasil = bukaquery($sql);
        $data = [];

        if (mysqli_num_rows($hasil) == 0) {
            echo json_encode([]);
            break;
        }

        // Dedup API:
        // kombinasi poli + dokter hanya dikirim satu kali.
        $seen = [];

        while ($r = mysqli_fetch_assoc($hasil)) {

            $uniqueKey = $r['kd_poli'] . '|' . $r['kd_dokter'];

            if (isset($seen[$uniqueKey])) {
                continue;
            }

            $seen[$uniqueKey] = true;

            $jamMulai   = $r['jam_mulai'];
            $jamSelesai = $r['jam_selesai'];
            $jamSekarang = date('H:i:s');

            $aktif = (
                $jamSekarang >= $jamMulai &&
                ($jamSelesai == null || $jamSekarang <= $jamSelesai)
            );

            $belumMulai = ($jamSekarang < $jamMulai);
            $sudahSelesai = (
                $jamSelesai != null &&
                $jamSekarang > $jamSelesai
            );

            if ($aktif) {

                $sqlAntri = "
                    SELECT
                        b.no_reg,
                        c.nm_pasien
                    FROM antripoli a
                    INNER JOIN reg_periksa b ON a.no_rawat = b.no_rawat
                    INNER JOIN pasien c ON b.no_rkm_medis = c.no_rkm_medis
                    WHERE a.status IN ('1','2','3')
                    AND b.kd_poli = '" . sql_escape($r['kd_poli']) . "'
                    AND b.kd_dokter = '" . sql_escape($r['kd_dokter']) . "'
                    ORDER BY a.no_rawat DESC
                    LIMIT 1
                ";

                $antri = bukaquery($sqlAntri);

                if (mysqli_num_rows($antri) > 0) {
                    $p = mysqli_fetch_assoc($antri);

                    $pasienData = [[
                        "no_reg"    => $p["no_reg"],
                        "nm_pasien" => $p["nm_pasien"]
                    ]];
                } else {
                    $pasienData = [[
                        "no_reg"    => "000",
                        "nm_pasien" => "Belum ada antrian"
                    ]];
                }

            } elseif ($belumMulai) {

                $jamText = date("H:i", strtotime($jamMulai)) .
                    ($jamSelesai ? " - " . date("H:i", strtotime($jamSelesai)) : "");

                $pasienData = [[
                    "no_reg"    => $jamText,
                    "nm_pasien" => "Belum mulai"
                ]];

            } elseif ($sudahSelesai) {

                $jamText = date("H:i", strtotime($jamMulai)) .
                    ($jamSelesai ? " - " . date("H:i", strtotime($jamSelesai)) : "");

                $pasienData = [[
                    "no_reg"    => $jamText,
                    "nm_pasien" => "Selesai"
                ]];

            } else {
                $pasienData = [[
                    "no_reg"    => "000",
                    "nm_pasien" => "Belum ada antrian"
                ]];
            }

            $data[] = [
                "kd_poli"    => $r["kd_poli"],
                "nm_poli"    => $r["nm_poli"],
                "kd_dokter"  => $r["kd_dokter"],
                "nm_dokter"  => $r["nm_dokter"],
                "jam_mulai"  => $r["jam_mulai"],
                "jam_selesai" => $r["jam_selesai"],
                "data_pasien" => $pasienData
            ];
        }

        echo json_encode($data);
        break;

    default:
        echo json_encode([
            "status" => "error",
            "message" => "Parameter tidak valid"
        ]);
}
?>
