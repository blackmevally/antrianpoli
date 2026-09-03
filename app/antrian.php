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

/* File-based call state: tidak mengubah struktur database SIMRS Khanza. */
function call_state_dir(): string
{
    return dirname(__DIR__) . '/runtime';
}

function call_state_file(): string
{
    return call_state_dir() . '/call_state.json';
}

function call_lock_open()
{
    $dir = call_state_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Direktori runtime tidak dapat dibuat');
    }

    $handle = fopen($dir . '/call.lock', 'c');
    if (!$handle || !flock($handle, LOCK_EX)) {
        if ($handle) fclose($handle);
        throw new RuntimeException('Lock pemanggilan tidak dapat diperoleh');
    }
    return $handle;
}

function call_lock_close($handle): void
{
    if (is_resource($handle)) {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function call_state_read(): ?array
{
    $file = call_state_file();
    if (!is_file($file)) return null;

    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') return null;

    $state = json_decode($raw, true);
    return is_array($state) && !empty($state['call_token']) ? $state : null;
}

function call_state_write(array $state): void
{
    $dir = call_state_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Direktori runtime tidak dapat dibuat');
    }

    $tmp = $dir . '/call_state.' . bin2hex(random_bytes(6)) . '.tmp';
    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
        throw new RuntimeException('State pemanggilan tidak dapat disimpan');
    }

    if (!rename($tmp, call_state_file())) {
        @unlink($tmp);
        throw new RuntimeException('State pemanggilan tidak dapat dipindahkan');
    }
}

function call_state_clear(): void
{
    $file = call_state_file();
    if (is_file($file) && !unlink($file)) {
        throw new RuntimeException('State pemanggilan tidak dapat dihapus');
    }
}

function call_payload(mysqli $db, array $state): ?array
{
    $stmt = mysqli_prepare($db, "SELECT a.no_rawat,b.no_reg,c.nm_pasien,d.nm_poli,e.nm_dokter FROM antripoli a INNER JOIN reg_periksa b ON a.no_rawat=b.no_rawat INNER JOIN pasien c ON b.no_rkm_medis=c.no_rkm_medis INNER JOIN poliklinik d ON b.kd_poli=d.kd_poli INNER JOIN dokter e ON b.kd_dokter=e.kd_dokter WHERE a.no_rawat=? LIMIT 1");
    if (!$stmt) throw new RuntimeException(mysqli_error($db));
    mysqli_stmt_bind_param($stmt, 's', $state['no_rawat']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    if (!$row) return null;

    $row['call_id'] = $state['call_id'];
    $row['call_token'] = $state['call_token'];
    $row['call_state'] = 'playing';
    $row['started_at'] = $state['started_at'];
    $row['last_seen_at'] = $state['last_seen_at'];
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
        $lock = null;
        $db = null;
        try {
            $lock = call_lock_open();
            $now = date('Y-m-d H:i:s');
            $state = call_state_read();

            if ($state && ($state['status'] ?? '') === 'playing') {
                $lastSeen = strtotime($state['last_seen_at'] ?? $state['started_at'] ?? $now);
                $stale = $lastSeen !== false && (time() - $lastSeen > 30);

                if (!$stale) {
                    $state['last_seen_at'] = $now;
                    call_state_write($state);
                    $db = db();
                    $payload = call_payload($db, $state);
                    if ($payload) {
                        api_json([$payload]);
                    }
                    // State tidak boleh hilang hanya karena data pasien sementara tidak ditemukan.
                    api_json([]);
                }

                // Browser/display sudah tidak mengirim heartbeat. Pulihkan call lama agar queue tidak macet.
                $db = db();
                $db->begin_transaction();
                $stmt = mysqli_prepare($db, "UPDATE antripoli SET status='3' WHERE no_rawat=? AND status='2'");
                if (!$stmt) throw new RuntimeException(mysqli_error($db));
                mysqli_stmt_bind_param($stmt, 's', $state['no_rawat']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $db->commit();
                call_state_clear();
                $state = null;
            }

            $db = $db ?: db();
            $db->begin_transaction();

            $sql = "SELECT a.no_rawat,b.no_reg,c.nm_pasien,d.nm_poli,e.nm_dokter FROM antripoli a INNER JOIN reg_periksa b ON a.no_rawat=b.no_rawat INNER JOIN pasien c ON b.no_rkm_medis=c.no_rkm_medis INNER JOIN poliklinik d ON b.kd_poli=d.kd_poli INNER JOIN dokter e ON b.kd_dokter=e.kd_dokter WHERE a.status='1' " . display_filter_sql() . " ORDER BY a.no_rawat ASC LIMIT 1 FOR UPDATE";
            $result = mysqli_query($db, $sql);
            if ($result === false) throw new RuntimeException(mysqli_error($db));
            $row = mysqli_fetch_assoc($result);

            if (!$row) {
                $db->commit();
                api_json([]);
            }

            $token = bin2hex(random_bytes(16));
            $callId = date('YmdHis') . '-' . bin2hex(random_bytes(4));
            $state = [
                'call_id' => $callId,
                'call_token' => $token,
                'no_rawat' => $row['no_rawat'],
                'status' => 'playing',
                'started_at' => $now,
                'last_seen_at' => $now
            ];

            $stmt = mysqli_prepare($db, "UPDATE antripoli SET status='2' WHERE no_rawat=? AND status='1'");
            if (!$stmt) throw new RuntimeException(mysqli_error($db));
            mysqli_stmt_bind_param($stmt, 's', $row['no_rawat']);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            if ($affected !== 1) throw new RuntimeException('Nomor antrian gagal di-claim');

            call_state_write($state);
            $db->commit();

            $row['call_id'] = $callId;
            $row['call_token'] = $token;
            $row['call_state'] = 'playing';
            $row['started_at'] = $now;
            $row['last_seen_at'] = $now;
            api_json([$row]);
        } catch (Throwable $e) {
            if ($db instanceof mysqli) {
                try { $db->rollback(); } catch (Throwable $ignored) {}
            }
            error_log('AntrianPoli panggil error: ' . $e->getMessage());
            api_error(500, 'Gagal memproses pemanggilan antrian');
        } finally {
            call_lock_close($lock);
        }
        break;

    case 'heartbeat':
        $token = trim((string)($_GET['call_token'] ?? $_POST['call_token'] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) api_error(400, 'call_token tidak valid');

        $lock = call_lock_open();
        try {
            $state = call_state_read();
            if (!$state || ($state['call_token'] ?? '') !== $token || ($state['status'] ?? '') !== 'playing') {
                api_json(['ok'=>false]);
            }
            $state['last_seen_at'] = date('Y-m-d H:i:s');
            call_state_write($state);
            api_json(['ok'=>true]);
        } finally {
            call_lock_close($lock);
        }
        break;

    case 'ack':
        $token = trim((string)($_GET['call_token'] ?? $_POST['call_token'] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) api_error(400, 'call_token tidak valid');

        $lock = call_lock_open();
        $db = null;
        try {
            $state = call_state_read();
            if (!$state || ($state['call_token'] ?? '') !== $token) {
                api_json(['ok'=>true,'status'=>'done']);
            }

            $db = db();
            $db->begin_transaction();
            $stmt = mysqli_prepare($db, "UPDATE antripoli SET status='3' WHERE no_rawat=? AND status='2'");
            if (!$stmt) throw new RuntimeException(mysqli_error($db));
            mysqli_stmt_bind_param($stmt, 's', $state['no_rawat']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $db->commit();
            call_state_clear();
            api_json(['ok'=>true,'call_id'=>$state['call_id'],'status'=>'done']);
        } catch (Throwable $e) {
            if ($db instanceof mysqli) {
                try { $db->rollback(); } catch (Throwable $ignored) {}
            }
            error_log('AntrianPoli ack error: ' . $e->getMessage());
            api_error(500, 'Gagal menyelesaikan pemanggilan antrian');
        } finally {
            call_lock_close($lock);
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
