<?php
require_once __DIR__ . '/../conf/database.php';

date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$action = $_GET['p'] ?? '';

if ($action === '') {
    api_error(400, 'Parameter p tidak ditemukan');
}

/**
 * Filter yang berasal dari config_suara.json.
 * Jika filter kosong, endpoint tidak membatasi poli/dokter.
 */
function display_filter_sql(string $poliAlias = 'd', string $dokterAlias = 'e'): string
{
    $config = load_queue_config();
    $parts = [];

    if (!empty($config['poli_aktif'])) {
        $parts[] = $poliAlias . '.kd_poli IN (' . db_in($config['poli_aktif']) . ')';
    }

    if (!empty($config['dokter_aktif'])) {
        $parts[] = $dokterAlias . '.kd_dokter IN (' . db_in($config['dokter_aktif']) . ')';
    }

    return $parts ? ' AND ' . implode(' AND ', $parts) : '';
}

function hari_kerja_indo(): string
{
    $map = [
        'SUNDAY' => 'AKHAD',
        'MONDAY' => 'SENIN',
        'TUESDAY' => 'SELASA',
        'WEDNESDAY' => 'RABU',
        'THURSDAY' => 'KAMIS',
        'FRIDAY' => 'JUMAT',
        'SATURDAY' => 'SABTU'
    ];

    return $map[strtoupper(date('l'))] ?? 'SENIN';
}

switch ($action) {

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
            WHERE a.status IN ('1','2')
            " . display_filter_sql() . "
            ORDER BY a.no_rawat ASC
            LIMIT 1
        ";

        $result = db_query($sql);
        $data = mysqli_fetch_assoc($result);

        api_json($data ?: [
            'no_reg' => '000',
            'nm_pasien' => '-',
            'nm_poli' => '-',
            'nm_dokter' => '-',
            'status' => '0'
        ]);
        break;

    case 'panggil':
        /**
         * Claim atomik:
         * - satu koneksi
         * - transaction + row lock
         * - ambil satu status=1
         * - selesaikan status=2 sebelumnya
         * - ubah item terpilih menjadi status=2
         *
         * Ini mencegah dua request display mengambil nomor yang sama
         * secara bersamaan.
         */
        $db = db();
        $db->begin_transaction();

        try {
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
                WHERE a.status = '1'
                " . display_filter_sql() . "
                ORDER BY a.no_rawat ASC
                LIMIT 1
                FOR UPDATE
            ";

            $result = mysqli_query($db, $sql);

            if ($result === false) {
                throw new RuntimeException(mysqli_error($db));
            }

            $row = mysqli_fetch_assoc($result);

            if (!$row) {
                $db->commit();
                api_json([]);
            }

            // Pertahankan perilaku lama: nomor yang sedang status=2
            // dianggap selesai ketika nomor baru dipanggil.
            $finish = mysqli_query(
                $db,
                "UPDATE antripoli SET status='3' WHERE status='2'"
            );

            if ($finish === false) {
                throw new RuntimeException(mysqli_error($db));
            }

            $stmt = mysqli_prepare(
                $db,
                "UPDATE antripoli SET status='2' WHERE no_rawat=? AND status='1'"
            );

            if (!$stmt) {
                throw new RuntimeException(mysqli_error($db));
            }

            mysqli_stmt_bind_param($stmt, 's', $row['no_rawat']);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affected !== 1) {
                throw new RuntimeException('Nomor antrian gagal di-claim');
            }

            $db->commit();
            api_json([$row]);

        } catch (Throwable $e) {
            $db->rollback();
            error_log('AntrianPoli panggil error: ' . $e->getMessage());
            api_error(500, 'Gagal memproses pemanggilan antrian');
        }
        break;

    case 'poli':
        $hariindo = hari_kerja_indo();
        $jamSekarang = date('H:i:s');

        $hariSql = db_escape($hariindo);

        // Alias e/d tetap digunakan agar filter config dapat dipakai.
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
            WHERE j.hari_kerja = '{$hariSql}'
            " . display_filter_sql('p', 'd') . "
            ORDER BY j.jam_mulai ASC, j.kd_poli ASC, j.kd_dokter ASC
        ";

        $result = db_query($sql);
        $data = [];
        $seen = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $key = $row['kd_poli'] . '|' . $row['kd_dokter'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $jamMulai = $row['jam_mulai'];
            $jamSelesai = $row['jam_selesai'];

            $aktif = (
                $jamSekarang >= $jamMulai &&
                ($jamSelesai === null || $jamSekarang <= $jamSelesai)
            );

            $belumMulai = $jamSekarang < $jamMulai;
            $sudahSelesai = (
                $jamSelesai !== null &&
                $jamSekarang > $jamSelesai
            );

            if ($aktif) {
                $kdPoli = db_escape((string)$row['kd_poli']);
                $kdDokter = db_escape((string)$row['kd_dokter']);

                $queueSql = "
                    SELECT b.no_reg, c.nm_pasien
                    FROM antripoli a
                    INNER JOIN reg_periksa b ON a.no_rawat = b.no_rawat
                    INNER JOIN pasien c ON b.no_rkm_medis = c.no_rkm_medis
                    WHERE a.status IN ('1','2','3')
                    AND b.kd_poli = '{$kdPoli}'
                    AND b.kd_dokter = '{$kdDokter}'
                    ORDER BY a.no_rawat DESC
                    LIMIT 1
                ";

                $queue = db_query($queueSql);
                $patient = mysqli_fetch_assoc($queue);

                $pasienData = [[
                    'no_reg' => $patient['no_reg'] ?? '000',
                    'nm_pasien' => $patient['nm_pasien'] ?? 'Belum ada antrian'
                ]];
            } elseif ($belumMulai || $sudahSelesai) {
                $jamText = date('H:i', strtotime($jamMulai));
                if ($jamSelesai) {
                    $jamText .= ' - ' . date('H:i', strtotime($jamSelesai));
                }

                $pasienData = [[
                    'no_reg' => $jamText,
                    'nm_pasien' => $belumMulai ? 'Belum mulai' : 'Selesai'
                ]];
            } else {
                $pasienData = [[
                    'no_reg' => '000',
                    'nm_pasien' => 'Belum ada antrian'
                ]];
            }

            $data[] = [
                'kd_poli' => $row['kd_poli'],
                'nm_poli' => $row['nm_poli'],
                'kd_dokter' => $row['kd_dokter'],
                'nm_dokter' => $row['nm_dokter'],
                'jam_mulai' => $row['jam_mulai'],
                'jam_selesai' => $row['jam_selesai'],
                'data_pasien' => $pasienData
            ];
        }

        api_json($data);
        break;

    default:
        api_error(400, 'Parameter tidak valid');
}
