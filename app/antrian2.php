<?php
require_once('../conf/conf.php');
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');

// 🔹 Daftar poli yang diikutkan dalam display
$poli_filter = "'U0107','U0003','U1025'";

// 🔹 Jam reset sistem (bisa disesuaikan)
$jamreset = '23:00:00';

if (!isset($_GET['p'])) {
    echo json_encode(["status" => "error", "message" => "Parameter tidak ditemukan"]);
    exit;
}

switch ($_GET['p']) {

    /* =============================================================
       🔹 TAMPILKAN NOMOR ANTRIAN AKTIF (status 1 atau 2)
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
       🔹 PEMANGGILAN SUARA (AMBIL STATUS=1, UBAH KE STATUS=2)
       ============================================================= */
    case 'panggil':
        $sql = "
            SELECT 
                a.no_rawat, b.no_reg, c.nm_pasien, 
                d.nm_poli, e.nm_dokter
            FROM antripoli a
            INNER JOIN reg_periksa b ON a.no_rawat = b.no_rawat
            INNER JOIN pasien c ON b.no_rkm_medis = c.no_rkm_medis
            INNER JOIN poliklinik d ON b.kd_poli = d.kd_poli
            INNER JOIN dokter e ON b.kd_dokter = e.kd_dokter
            WHERE a.status='1' 
            AND d.kd_poli IN ($poli_filter)
            ORDER BY a.no_rawat ASC 
            LIMIT 1
        ";

        $hasil = bukaquery($sql);
        $data = [];

        if (mysqli_num_rows($hasil) > 0) {
            $r = mysqli_fetch_assoc($hasil);
            $data[] = $r;

            // 🔄 Update status antrian
            bukaquery2("UPDATE antripoli SET status='3' WHERE status='2'");
            bukaquery2("UPDATE antripoli SET status='2' WHERE no_rawat='{$r['no_rawat']}'");
        }

        echo json_encode($data);
        break;

    /* =============================================================
       🔹 TAMPILKAN POLI AKTIF BERDASARKAN JADWAL DOKTER
       ============================================================= */
    case 'poli':
        // Hari dan jam sekarang
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

        // 🔹 Ambil jadwal poli aktif hari ini
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
            ORDER BY j.jam_mulai ASC
        ";

        $hasil = bukaquery($sql);
        $data = [];

        if (mysqli_num_rows($hasil) == 0) {
            // ❗ Tidak ada jadwal sama sekali hari ini
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

    // Cek status waktu
    $aktif = ($jamSekarang >= $jamMulai && ($jamSelesai == null || $jamSekarang <= $jamSelesai));
    $belumMulai = ($jamSekarang < $jamMulai);
    $sudahSelesai = ($jamSelesai != null && $jamSekarang > $jamSelesai);

    if ($aktif) {
        // 🔹 Ambil antrian pasien (status 1,2,3)
        $sqlAntri = "
            SELECT 
                b.no_reg,
                c.nm_pasien
            FROM antripoli a
            INNER JOIN reg_periksa b ON a.no_rawat = b.no_rawat
            INNER JOIN pasien c ON b.no_rkm_medis = c.no_rkm_medis
            WHERE a.status IN ('1','2','3')
            AND b.kd_poli = '{$r['kd_poli']}'
            AND b.kd_dokter = '{$r['kd_dokter']}'
            ORDER BY a.no_rawat DESC
            LIMIT 1
        ";
        $antri = bukaquery($sqlAntri);
        $pasienData = [];

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
        // 🔹 Belum mulai jam poli
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
        // 🔹 Sudah lewat jam praktek
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

    /* =============================================================
       🔹 PARAMETER TIDAK VALID
       ============================================================= */
    default:
        echo json_encode(["status" => "error", "message" => "Parameter tidak valid"]);
}
?>
