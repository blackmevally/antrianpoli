<?php
/**
 * Database foundation untuk endpoint/API.
 *
 * Urutan konfigurasi:
 * 1. conf/database.local.php (khusus localhost, tidak di-commit)
 * 2. Environment variable DB_HOST/DB_USER/DB_PASS/DB_NAME
 * 3. Default XAMPP: 127.0.0.1 / root / kosong / sik
 */

function db()
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $localConfig = __DIR__ . '/database.local.php';
    $config = is_file($localConfig) ? require $localConfig : [];
    if (!is_array($config)) {
        $config = [];
    }

    $host = $config['host'] ?? (getenv('DB_HOST') ?: '127.0.0.1');
    $user = $config['user'] ?? (getenv('DB_USER') ?: 'root');
    $pass = $config['pass'] ?? (getenv('DB_PASS') ?: '');
    $name = $config['name'] ?? (getenv('DB_NAME') ?: 'sik');
    $port = (int)($config['port'] ?? (getenv('DB_PORT') ?: 3306));

    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = mysqli_connect($host, $user, $pass, $name, $port);

    if (!$connection) {
        error_log('AntrianPoli DB connection failed: ' . mysqli_connect_error());
        api_error(500, 'Koneksi database gagal');
    }

    mysqli_set_charset($connection, 'utf8mb4');
    return $connection;
}

function db_escape($value)
{
    return mysqli_real_escape_string(db(), (string)$value);
}

function db_in(array $values)
{
    $values = array_values(array_filter($values, static function ($value) {
        return is_scalar($value) && (string)$value !== '';
    }));

    if (!$values) {
        return '';
    }

    $escaped = array_map(static function ($value) {
        return "'" . db_escape($value) . "'";
    }, $values);

    return implode(',', $escaped);
}

function db_query($sql)
{
    $result = mysqli_query(db(), $sql);

    if ($result === false) {
        error_log('AntrianPoli SQL error: ' . mysqli_error(db()));
        api_error(500, 'Query database gagal');
    }

    return $result;
}

function api_error($status, $message)
{
    http_response_code((int)$status);
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_json($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function load_queue_config()
{
    $file = dirname(__DIR__) . '/config_suara.json';

    if (!is_file($file)) {
        return ['poli_aktif' => [], 'dokter_aktif' => []];
    }

    $raw = file_get_contents($file);
    $config = json_decode($raw ?: '', true);

    if (!is_array($config)) {
        error_log('AntrianPoli: config_suara.json tidak valid');
        return ['poli_aktif' => [], 'dokter_aktif' => []];
    }

    $poli = isset($config['poli_aktif']) ? $config['poli_aktif'] : (isset($config['poli_suara']) ? $config['poli_suara'] : []);
    $dokter = isset($config['dokter_aktif']) ? $config['dokter_aktif'] : [];

    return [
        'poli_aktif' => is_array($poli) ? $poli : [],
        'dokter_aktif' => is_array($dokter) ? $dokter : []
    ];
}
