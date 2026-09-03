<?php
require_once __DIR__ . '/../conf/database.php';

date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$action = $_GET['p'] ?? $_POST['p'] ?? '';

if ($action === '') {
    api_error(400, 'Parameter p tidak ditemukan');
}

function display_filter_sql(string $poliAlias = 'd', string $dokterAlias = 'e'): string
{
    $config = load_queue_config();
    $parts = [];
    if (!empty($config['poli_aktif'])) $parts[] = $poliAlias . '.kd_poli IN (' . db_in($config['poli_aktif']) . ')';
    if (!empty($config['dokter_aktif'])) $parts[] = $dokterAlias . '.kd_dokter IN (' . db_in($config['dokter_aktif']) . ')';
    return $parts ? ' AND ' . implode(' AND ', $parts) : '';
}

function hari_kerja_indo(): string
{
    $map = ['SUNDAY'=>'AKHAD','MONDAY'=>'SENIN','TUESDAY'=>'SELASA','WEDNESDAY'=>'RABU','THURSDAY'=>'KAMIS','FRIDAY'=>'JUMAT','SATURDAY'=>'SABTU'];
    return $map[strtoupper(date('l'))] ?? 'SENIN';
}

function call_payload(mysqli $db, array $queue): ?array
{
    $stmt = mysqli_prepare($db, "SELECT a.no_rawat,b.no_reg,c.nm_pasien,d.nm_poli,e.nm_dokter FROM antripoli a INNER JOIN reg_periksa b ON a.no_rawat=b.no_rawat INNER JOIN pasien c ON b.no_rkm_medis=c.no_rkm_medis INNER JOIN poliklinik d ON b.kd_poli=d.kd_poli INNER JOIN dokter e ON b.kd_dokter=e.kd_dokter WHERE a.no_rawat=? LIMIT 1");
    if (!$stmt) throw new RuntimeException(mysqli_error($db));
    mysqli_stmt_bind_param($stmt, 's', $queue['no_rawat']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    if (!$row) return null;
    $row['call_id'] = (int)$queue['id'];
    $row['call_token'] = $queue['call_token'];
    $row['call_state'] = $queue['status'];
    $row['started_at'] = $queue['started_at'];
    return $row;
}

switch ($action) {
    case 'nomor':
        $sql = "SELECT b.no_reg,a.status,d.nm_poli,c.nm_pasien,a.no_rawat,a.kd_dokter,e.nm_dokter FROM antripoli a INNER JOIN reg_periksa b ON a.no_rawat=b.no_rawat INNER JOIN pasien c ON b.no_rkm_medis=c.no_rkm_medis INNER JOIN poliklinik d ON b.kd_poli=d.kd_poli INNER JOIN dokter e ON b.kd_dokter=e.kd_dokter WHERE a.status IN ('1','2') " . display_filter_sql() . " ORDER BY a.no_rawat ASC LIMIT 1";
        $result = db_query($sql);
        $data = mysqli_fetch_assoc($result);
        api_json($data ?: ['no_reg'=>'000','nm_pasien'=>'-','nm_poli'=>'-','nm_dokter'=>'-','status'=>'0']);
        break;

    case 'panggil':
        $db = db();
        $db->begin_transaction();
        try {
            // Selama masih ada call playing, polling mengembalikan call yang sama.
            $playingResult = mysqli_query($db, "SELECT id,no_rawat,call_token,status,started_at,last_seen_at FROM antrianpoli_call_queue WHERE status='playing' ORDER BY id ASC LIMIT 1 FOR UPDATE");
            if ($playingResult === false) throw new RuntimeException(mysqli_error($db));
            $playing = mysqli_fetch_assoc($playingResult);
            if ($playing) {
                $payload = call_payload($db, $playing);
                $db->commit();
                api_json($payload ? [$payload] : []);
            }

            $sql = "SELECT a.no_rawat,b.no_reg,c.nm_pasien,d.nm_poli,e.nm_dokter FROM antripoli a INNER JOIN reg_periksa b ON a.no_rawat=b.no_rawat INNER JOIN pasien c ON b.no_rkm_medis=c.no_rkm_medis INNER JOIN poliklinik d ON b.kd_poli=d.kd_poli INNER JOIN dokter e ON b.kd_dokter=e.kd_dokter WHERE a.status='1' " . display_filter_sql() . " ORDER BY a.no_rawat ASC LIMIT 1 FOR UPDATE";
            $result = mysqli_query($db, $sql);
            if ($result === false) throw new RuntimeException(mysqli_error($db));
            $row = mysqli_fetch_assoc($result);
            if (!$row) {
                $db->commit();
                api_json([]);
            }

            $token = bin2hex(random_bytes(16));
            $stmt = mysqli_prepare($db, "INSERT INTO antrianpoli_call_queue (no_rawat,call_token,status,created_at,started_at,last_seen_at) VALUES (?,?,'playing',NOW(),NOW(),NOW())");
            if (!$stmt) throw new RuntimeException(mysqli_error($db));
            mysqli_stmt_bind_param($stmt, 'ss', $row['no_rawat'], $token);
            mysqli_stmt_execute($stmt);
            $queueId = mysqli_insert_id($db);
            mysqli_stmt_close($stmt);
            if ($queueId <= 0) throw new RuntimeException('Call queue gagal dibuat');

            $stmt = mysqli_prepare($db, "UPDATE antripoli SET status='2' WHERE no_rawat=? AND status='1'");
            if (!$stmt) throw new RuntimeException(mysqli_error($db));
            mysqli_stmt_bind_param($stmt, 's', $row['no_rawat']);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            if ($affected !== 1) throw new RuntimeException('Nomor antrian gagal di-claim');

            $db->commit();
            $row['call_id'] = $queueId;
            $row['call_token'] = $token;
            $row['call_state'] = 'playing';
            $row['started_at'] = date('Y-m-d H:i:s');
            api_json([$row]);
        } catch (Throwable $e) {
            $db->rollback();
            error_log('AntrianPoli panggil error: ' . $e->getMessage());
            api_error(500, 'Gagal memproses pemanggilan antrian');
        }
        break;

    case 'heartbeat':
        $token = trim((string)($_GET['call_token'] ?? $_POST['call_token'] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) api_error(400, 'call_token tidak valid');
        $db = db();
        $stmt = mysqli_prepare($db, "UPDATE antrianpoli_call_queue SET last_seen_at=NOW() WHERE call_token=? AND status='playing'");
        if (!$stmt) api_error(500, 'Gagal memperbarui heartbeat');
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $ok = mysqli_stmt_affected_rows($stmt) >= 1;
        mysqli_stmt_close($stmt);
        api_json(['ok'=>$ok]);
        break;

    case 'ack':
        $token = trim((string)($_GET['call_token'] ?? $_POST['call_token'] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) api_error(400, 'call_token tidak valid');
        $db = db();
        $db->begin_transaction();
        try {
            $stmt = mysqli_prepare($db, "SELECT id,no_rawat,status FROM antrianpoli_call_queue WHERE call_token=? LIMIT 1 FOR UPDATE");
            if (!$stmt) throw new RuntimeException(mysqli_error($db));
            mysqli_stmt_bind_param($stmt, 's', $token);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $queue = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            if (!$queue) throw new RuntimeException('Call tidak ditemukan');

            if ($queue['status'] === 'playing') {
                $stmt = mysqli_prepare($db, "UPDATE antrianpoli_call_queue SET status='done',finished_at=NOW(),last_seen_at=NOW() WHERE id=? AND status='playing'");
                if (!$stmt) throw new RuntimeException(mysqli_error($db));
                mysqli_stmt_bind_param($stmt, 'i', $queue['id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare($db, "UPDATE antripoli SET status='3' WHERE no_rawat=? AND status='2'");
                if (!$stmt) throw new RuntimeException(mysqli_error($db));
                mysqli_stmt_bind_param($stmt, 's', $queue['no_rawat']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            $db->commit();
            api_json(['ok'=>true,'call_id'=>(int)$queue['id'],'status'=>'done']);
        } catch (Throwable $e) {
            $db->rollback();
            error_log('AntrianPoli ack error: ' . $e->getMessage());
            api_error(500, 'Gagal menyelesaikan pemanggilan antrian');
        }
        break;

    case 'poli':
        $hariindo = hari_kerja_indo();
        $jamSekarang = date('H:i:s');
        $hariSql = db_escape($hariindo);
        $sql = "SELECT j.kd_poli,p.nm_poli,j.kd_dokter,d.nm_dokter,j.jam_mulai,j.jam_selesai FROM jadwal j INNER JOIN poliklinik p ON j.kd_poli=p.kd_poli INNER JOIN dokter d ON j.kd_dokter=d.kd_dokter WHERE j.hari_kerja='{$hariSql}' " . display_filter_sql('p','d') . " ORDER BY j.jam_mulai ASC,j.kd_poli ASC,j.kd_dokter ASC";
        $result = db_query($sql);
        $data=[]; $seen=[];
        while ($row=mysqli_fetch_assoc($result)) {
            $key=$row['kd_poli'].'|'.$row['kd_dokter'];
            if(isset($seen[$key])) continue;
            $seen[$key]=true;
            $jamMulai=$row['jam_mulai']; $jamSelesai=$row['jam_selesai'];
            $aktif=$jamSekarang >= $jamMulai && ($jamSelesai===null || $jamSekarang <= $jamSelesai);
            $belumMulai=$jamSekarang < $jamMulai;
            if($aktif){
                $kdPoli=db_escape((string)$row['kd_poli']); $kdDokter=db_escape((string)$row['kd_dokter']);
                $queueSql="SELECT b.no_reg,c.nm_pasien FROM antripoli a INNER JOIN reg_periksa b ON a.no_rawat=b.no_rawat INNER JOIN pasien c ON b.no_rkm_medis=c.no_rkm_medis WHERE a.status IN ('1','2','3') AND b.kd_poli='{$kdPoli}' AND b.kd_dokter='{$kdDokter}' ORDER BY a.no_rawat DESC LIMIT 1";
                $queue=db_query($queueSql); $patient=mysqli_fetch_assoc($queue);
                $pasienData=[['no_reg'=>$patient['no_reg']??'000','nm_pasien'=>$patient['nm_pasien']??'Belum ada antrian']];
            } else {
                $jamText=date('H:i',strtotime($jamMulai)); if($jamSelesai) $jamText.=' - '.date('H:i',strtotime($jamSelesai));
                $pasienData=[['no_reg'=>$jamText,'nm_pasien'=>$belumMulai?'Belum mulai':'Selesai']];
            }
            $data[]=['kd_poli'=>$row['kd_poli'],'nm_poli'=>$row['nm_poli'],'kd_dokter'=>$row['kd_dokter'],'nm_dokter'=>$row['nm_dokter'],'jam_mulai'=>$row['jam_mulai'],'jam_selesai'=>$row['jam_selesai'],'data_pasien'=>$pasienData];
        }
        api_json($data);
        break;

    default:
        api_error(400, 'Parameter tidak valid');
}
