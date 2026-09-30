<?php
$logs = file_exists("../log_panggilan.json") ? json_decode(file_get_contents("../log_panggilan.json"), true) : [];
echo "<h3>Log Panggilan Terakhir</h3><ul>";
foreach ($logs as $l) {
    echo "<li><b>{$l['nm_pasien']}</b> - {$l['nm_poli']} ({$l['nm_dokter']}) <i>{$l['waktu']}</i></li>";
}
echo "</ul>";
?>
