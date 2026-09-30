<?php
$configFile = "../config/config_suara.json";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        "voice_gender" => $_POST['voice_gender'] ?? 'female',
        "voice_speed" => floatval($_POST['voice_speed'] ?? 0.9),
        "voice_pitch" => floatval($_POST['voice_pitch'] ?? 1),
        "tts_pause" => intval($_POST['tts_pause'] ?? 500),
        "poli_aktif" => $_POST['poli_aktif'] ?? [],
        "dokter_aktif" => $_POST['dokter_aktif'] ?? [],
    ];
    file_put_contents($configFile, json_encode($data, JSON_PRETTY_PRINT));
    echo "<script>alert('✅ Pengaturan disimpan');window.location='suara.php';</script>";
    exit;
}

$data = file_exists($configFile)
    ? json_decode(file_get_contents($configFile), true)
    : [
        "voice_gender" => "female",
        "voice_speed" => 0.9,
        "voice_pitch" => 1,
        "tts_pause" => 500,
        "poli_aktif" => [],
        "dokter_aktif" => [],
    ];

include("../conf/conf.php");
$poli = bukaquery("SELECT kd_poli, nm_poli FROM poliklinik ORDER BY nm_poli");
$dokter = bukaquery("SELECT kd_dokter, nm_dokter FROM dokter ORDER BY nm_dokter");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Pengaturan Suara TTS Antrian</title>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
body {
  font-family: 'Segoe UI', sans-serif;
  background: #f5f5f5;
  padding: 20px;
}
.container {
  max-width: 800px;
  margin: 0 auto;
  background: white;
  border-radius: 10px;
  box-shadow: 0 0 15px rgba(0,0,0,0.1);
  padding: 20px 30px;
}
h2 { text-align: center; color: #0d47a1; }
label { font-weight: bold; display: block; margin-top: 15px; }
button {
  background: #0d47a1; color: white; border: none; padding: 10px 20px;
  border-radius: 6px; cursor: pointer; margin-top: 20px;
}
button:hover { opacity: 0.9; }
fieldset {
  border: 1px solid #ddd;
  border-radius: 8px;
  margin-top: 20px;
  padding: 10px 15px;
}
legend {
  font-weight: bold;
  color: #0d47a1;
}
.select2-container--default .select2-selection--multiple {
  border-radius: 8px;
  border: 1px solid #ccc;
  padding: 6px;
}
</style>
</head>
<body>

<div class="container">
<h2>🔊 Pengaturan Suara & Filter TTS</h2>
<form method="post">

<!-- Jenis Suara -->
<fieldset>
<legend>🎙️ Pengaturan Suara</legend>
<label>Jenis Suara</label>
<select name="voice_gender">
  <option value="female" <?= $data['voice_gender']=='female'?'selected':'' ?>>Perempuan</option>
  <option value="male" <?= $data['voice_gender']=='male'?'selected':'' ?>>Laki-laki</option>
</select>

<label>Kecepatan Bicara (0.5–1.5)</label>
<input type="number" step="0.1" name="voice_speed" value="<?= $data['voice_speed'] ?>">

<label>Pitch (Nada Bicara)</label>
<input type="number" step="0.1" name="voice_pitch" value="<?= $data['voice_pitch'] ?>">

<label>Jeda antar kalimat (ms)</label>
<input type="number" name="tts_pause" value="<?= $data['tts_pause'] ?>">
</fieldset>

<!-- Filter Poli -->
<fieldset>
<legend>🏥 Filter Poli Aktif</legend>
<select name="poli_aktif[]" id="poli_aktif" multiple="multiple" style="width:100%;">
  <?php while ($r = mysqli_fetch_array($poli)): ?>
  <option value="<?= $r['kd_poli'] ?>" <?= in_array($r['kd_poli'], $data['poli_aktif'])?'selected':'' ?>>
    <?= $r['kd_poli'] ?> - <?= $r['nm_poli'] ?>
  </option>
  <?php endwhile; ?>
</select>
<small>Pilih poli yang akan ditampilkan dan dipanggil oleh TTS.</small>
</fieldset>

<!-- Filter Dokter -->
<fieldset>
<legend>👨‍⚕️ Filter Dokter Aktif</legend>
<select name="dokter_aktif[]" id="dokter_aktif" multiple="multiple" style="width:100%;">
  <?php while ($r = mysqli_fetch_array($dokter)): ?>
  <option value="<?= $r['kd_dokter'] ?>" <?= in_array($r['kd_dokter'], $data['dokter_aktif'])?'selected':'' ?>>
    <?= $r['kd_dokter'] ?> - <?= $r['nm_dokter'] ?>
  </option>
  <?php endwhile; ?>
</select>
<small>Pilih dokter yang akan aktif dipanggil oleh TTS.</small>
</fieldset>

<button type="submit">💾 Simpan Pengaturan</button>
</form>
</div>

<!-- Select2 Library -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
  $('#poli_aktif').select2({
    placeholder: "Cari dan pilih poli aktif...",
    allowClear: true,
    closeOnSelect: false
  });
  $('#dokter_aktif').select2({
    placeholder: "Cari dan pilih dokter aktif...",
    allowClear: true,
    closeOnSelect: false
  });
});
</script>

</body>
</html>
