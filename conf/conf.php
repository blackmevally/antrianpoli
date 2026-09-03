<?php

/**
 * Konfigurasi koneksi database dan helper kompatibilitas aplikasi.
 *
 * PENTING:
 * - Jangan menyimpan password/credential produksi di repository.
 * - Gunakan environment variable pada server.
 * - Untuk localhost, nilai default di bawah aman sebagai placeholder
 *   dan harus disesuaikan dengan environment masing-masing.
 */

date_default_timezone_set('Asia/Jakarta');

$db_hostname = getenv('DB_HOST') ?: '127.0.0.1';
$db_username = getenv('DB_USER') ?: 'root';
$db_password = getenv('DB_PASS') ?: '';
$db_name     = getenv('DB_NAME') ?: 'sik';

// Credential aplikasi lama: ambil dari environment, jangan hard-code secret.
define('USERHYBRIDWEB', getenv('HYBRID_USER') ?: '');
define('PASHYBRIDWEB', getenv('HYBRID_PASS') ?: '');

function host()
{
    global $db_hostname;
    return $db_hostname;
}

function bukakoneksi()
{
    global $db_hostname, $db_username, $db_password, $db_name;

    $konektor = mysqli_connect(
        $db_hostname,
        $db_username,
        $db_password,
        $db_name
    );

    if (!$konektor) {
        error_log('Database connection failed: ' . mysqli_connect_error());
        die('<font color="red"><h3>Koneksi database gagal.</h3></font>');
    }

    mysqli_set_charset($konektor, 'utf8mb4');

    return $konektor;
}

$sqlinjectionchars = array("=", "-", "'", '"', '+');

function cleankar($dirty)
{
    $konektor = bukakoneksi();
    $clean = mysqli_real_escape_string($konektor, (string) $dirty);
    mysqli_close($konektor);
    return preg_replace('/[^a-zA-Z0-9\s_,@. ]/', '', $clean);
}

function mysql_safe_query($format)
{
    $args = array_slice(func_get_args(), 1);
    $args = array_map('mysql_safe_string', $args);
    $query = vsprintf($format, $args);
    return mysqli_query(bukakoneksi(), $query);
}

function mysql_safe_string($value)
{
    $konektor = bukakoneksi();
    $clean = mysqli_real_escape_string($konektor, (string) $value);
    mysqli_close($konektor);
    return $clean;
}

function validUrl($url)
{
    $format = "/^(http|https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(([0-9]{1,5})?\/.*)?$/";
    $url = strtolower($url);
    return (bool) preg_match($format, $url);
}

function validTeks($data)
{
    $save = str_replace("'", '', $data);
    $save = str_replace('\\', '', $save);
    $save = str_replace(';', '', $save);
    $save = str_replace('`', '', $save);
    $save = str_replace('--', '', $save);
    $save = str_replace('/*', '', $save);
    $save = str_replace('*/', '', $save);
    $save = str_replace('#', '', $save);
    return $save;
}

function validangka($angka)
{
    return isset($angka) && is_numeric($angka) ? $angka : 0;
}

function antisqlinjection($hal = '')
{
    // Dipertahankan untuk kompatibilitas aplikasi lama.
    // Proteksi SQL wajib dilakukan pada query masing-masing dengan
    // prepared statement / escaping sesuai koneksi database.
}

function reportsqlinjection()
{
    // Dipertahankan untuk kompatibilitas aplikasi lama.
}

function tutupkoneksi()
{
    global $konektor;
    if ($konektor instanceof mysqli) {
        mysqli_close($konektor);
    }
}

function bukaquery($sql)
{
    $konektor = bukakoneksi();
    $result = mysqli_query($konektor, $sql);

    if ($result === false) {
        error_log('SQL query failed: ' . mysqli_error($konektor));
        mysqli_close($konektor);
        die('<font color="red"><b>Query database gagal.</b></font>');
    }

    mysqli_close($konektor);
    return $result;
}

function fetch_assoc($sql)
{
    $result = bukaquery($sql);
    return mysqli_fetch_assoc($result);
}

function bukaquery2($sql)
{
    $konektor = bukakoneksi();
    $result = mysqli_query($konektor, $sql);
    mysqli_close($konektor);
    return $result;
}

function bukainput($sql)
{
    $konektor = bukakoneksi();
    $result = mysqli_query($konektor, $sql);
    mysqli_close($konektor);

    if ($result === false) {
        die('<br/><font color="red"><b>Gagal..!!</b></font>');
    }

    return $result;
}

function hapusinput($sql)
{
    $konektor = bukakoneksi();
    $result = mysqli_query($konektor, $sql);
    mysqli_close($konektor);

    if ($result === false) {
        die('<font color="red"><b>Gagal</b>, Data masih dipakai di tabel lain atau query tidak valid.</font>');
    }

    return $result;
}

function Tambah($tabelname, $attrib, $pesan)
{
    $command = bukainput('INSERT INTO ' . $tabelname . ' VALUES (' . $attrib . ')');
    echo "<img src='images/simpan.gif' />&nbsp;&nbsp; Data $pesan berhasil disimpan";
    return $command;
}

function Tambah2($tabelname, $attrib, $pesan)
{
    $command = bukainput('INSERT INTO ' . $tabelname . ' VALUES (' . $attrib . ')');
    echo "<img src='images/simpan.gif' />&nbsp;&nbsp; <font size='9'>Data $pesan berhasil disimpan</font>";
    return $command;
}

function Tambah3($tabelname, $attrib)
{
    return bukainput('INSERT INTO ' . $tabelname . ' VALUES (' . $attrib . ')');
}

function InsertData($tabelname, $attrib)
{
    return bukaquery('INSERT INTO ' . $tabelname . ' VALUES (' . $attrib . ')');
}

function InsertData2($tabelname, $attrib)
{
    return bukaquery2('INSERT INTO ' . $tabelname . ' VALUES (' . $attrib . ')');
}

function EditData($tabelname, $attrib)
{
    return bukaquery('UPDATE ' . $tabelname . ' SET ' . $attrib);
}

function Ubah($tabelname, $attrib, $pesan)
{
    $command = bukaquery('UPDATE ' . $tabelname . ' SET ' . $attrib);
    echo "<img src='images/simpan.gif' />&nbsp;&nbsp; Data $pesan berhasil diubah";
    return $command;
}

function Ubah2($tabelname, $attrib)
{
    return bukaquery('UPDATE ' . $tabelname . ' SET ' . $attrib);
}

function Hapus($tabelname, $param, $hal)
{
    $command = hapusinput('DELETE FROM ' . $tabelname . ' WHERE ' . $param);
    Zet($hal);
    return $command;
}

function Hapus2($tabelname, $param)
{
    return hapusinput('DELETE FROM ' . $tabelname . ' WHERE ' . $param);
}

function HapusAll($tabelname)
{
    return bukaquery('DELETE FROM ' . $tabelname);
}

function deletegb($sql)
{
    $hasil = bukaquery($sql);
    $baris = mysqli_fetch_row($hasil);
    $gb = $baris[0] ?? '';

    if ($gb !== '' && is_file($gb)) {
        return unlink($gb);
    }

    return false;
}

function JSRedirect($url)
{
    echo "<html><head><title></title><meta http-equiv='refresh' content='1;URL=" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'></head><body></body></html>";
}

function Zet($url)
{
    echo "<html><head><title></title><meta http-equiv='refresh' content='0;URL=" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'></head><body></body></html>";
}

function JurusKibasNaga()
{
    $id = $_SERVER['REMOTE_ADDR'] ?? '';
    $id = addslashes($id);
    return bukaquery("DELETE FROM tmp WHERE ID='$id'");
}

function konversiTgl($tanggal)
{
    list($thn, $bln, $tgl) = explode('-', $tanggal);
    return $tgl . '-' . $bln . '-' . $thn;
}

function konversiBulan($bln)
{
    switch ($bln) {
        case '01': return 'Januari';
        case '02': return 'Februari';
        case '03': return 'Maret';
        case '04': return 'April';
        case '05': return 'Mei';
        case '06': return 'Juni';
        case '07': return 'Juli';
        case '08': return 'Agustus';
        case '09': return 'September';
        case '10': return 'Oktober';
        case '11': return 'November';
        case '12': return 'Desember';
        default: return '';
    }
}
