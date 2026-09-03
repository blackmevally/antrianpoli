<?php date_default_timezone_set("Asia/Jakarta"); ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Display Antrian RSU Permata Medika Kebumen</title>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets/responsivevoice.js"></script>

<style>
*{box-sizing:border-box}
html,body{width:100%;height:100%}
body{
  font-family:'Segoe UI',sans-serif;color:#fff;margin:0;overflow:hidden;
  background:#101820;position:relative
}
body::before{
  content:"";position:fixed;inset:0;
  background:url("assets/bg_rsu.png") center/cover no-repeat;
  opacity:.34;z-index:-2
}
body::after{
  content:"";position:fixed;inset:0;
  background:rgba(8,20,32,.48);z-index:-1
}

/* HEADER */
header{
  height:72px;background:rgba(13,71,161,.90);display:flex;
  justify-content:space-between;align-items:center;padding:8px 24px;
  backdrop-filter:blur(5px);position:relative;z-index:10
}
#namars{font-size:clamp(19px,1.7vw,27px);font-weight:bold;line-height:1.05}
#text{font-size:clamp(10px,.8vw,13px);opacity:.92;margin-top:3px}
#clock{font-size:clamp(17px,1.5vw,23px);font-weight:bold;white-space:nowrap}
#btnTestAV{
  background:#35b957;color:#fff;border:none;border-radius:6px;
  padding:6px 10px;cursor:pointer;font-weight:bold;font-size:12px
}

/* MAIN */
main{
  height:calc(100vh - 104px);padding:8px 18px 5px;
  display:flex;flex-direction:column;gap:8px;overflow:hidden
}

/* TIGA KOLOM: POLI KIRI - NOMOR - POLI KANAN */
#top-section{
  flex:1 1 auto;min-height:0;width:100%;
  display:grid;
  grid-template-columns:minmax(190px,22%) minmax(400px,56%) minmax(190px,22%);
  gap:10px;align-items:stretch
}

/* PANEL KIRI / KANAN */
.side-poli{
  min-width:0;min-height:0;
  display:grid;
  grid-template-columns:1fr;
  grid-template-rows:repeat(5,minmax(0,1fr));
  gap:6px;
  overflow:hidden
}

.side-poli .poli-side-card{
  min-width:0;min-height:0;
  border-radius:10px;
  padding:6px 8px;
  display:flex;
  flex-direction:column;
  justify-content:center;
  text-align:center;
  overflow:hidden;
  background:linear-gradient(145deg,#004d40,#00796b);
  box-shadow:0 0 8px rgba(0,0,0,.42)
}
.side-poli .poli-side-card h5{
  margin:0 0 2px;font-size:clamp(10px,.82vw,14px);
  color:#d9f7ff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis
}
.side-poli .poli-side-card .dokter{
  font-size:clamp(8px,.66vw,11px);white-space:nowrap;
  overflow:hidden;text-overflow:ellipsis
}
.side-poli .poli-side-card .nomor{
  font-size:clamp(20px,2vw,31px);font-weight:800;
  line-height:1;margin:2px 0
}
.side-poli .poli-side-card .pasien{
  font-size:clamp(8px,.62vw,11px);white-space:nowrap;
  overflow:hidden;text-overflow:ellipsis
}
.side-poli .poli-side-card .status{
  font-size:clamp(8px,.62vw,11px);font-weight:bold;margin-top:2px
}

/* NOMOR UTAMA */
#right-panel{
  min-width:0;height:100%;display:flex;justify-content:center;
  align-items:center;border-radius:18px;background:rgba(16,29,43,.72);
  backdrop-filter:blur(7px);box-shadow:0 0 18px rgba(0,0,0,.45);padding:10px
}
#nomor{width:100%;height:100%;display:flex;justify-content:center;align-items:center}
#nomor-box{
  width:94%;height:88%;min-height:200px;text-align:center;
  border:4px solid #80d8ff;border-radius:22px;background:rgba(5,13,22,.84);
  box-shadow:0 0 25px rgba(0,0,0,.55);padding:12px 18px;
  display:flex;flex-direction:column;justify-content:center;
  align-items:center;overflow:hidden
}
#nomor-box.active{
  animation:glowPulse 1.2s infinite;border-color:#00b0ff
}
@keyframes glowPulse{
  0%,100%{box-shadow:0 0 20px 5px rgba(0,176,255,.42)}
  50%{box-shadow:0 0 34px 10px rgba(0,255,255,.72)}
}
#nomor h2{
  font-size:clamp(17px,1.7vw,29px);margin:2px 0 5px;color:#80d8ff;
  max-width:94%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis
}
#nomor h1{
  font-size:clamp(80px,9vw,150px);margin:0;line-height:.9;letter-spacing:2px
}
#nomor b{
  font-size:clamp(15px,1.5vw,24px);color:#ffcc80;margin-top:8px;
  max-width:94%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis
}

/* KARTU POLI BAWAH */
#datapoli{
  flex:0 0 18vh;min-height:95px;max-height:160px;
  display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
  grid-auto-rows:1fr;gap:6px;overflow:hidden;padding:0 1px
}
.poli-card{
  min-width:0;min-height:0;border-radius:9px;padding:6px 8px;
  text-align:center;box-shadow:0 0 7px rgba(0,0,0,.4);
  display:flex;flex-direction:column;justify-content:center;align-items:center;
  overflow:hidden
}
.poli-card h5{
  width:100%;font-size:clamp(10px,.85vw,15px);margin:0 0 2px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis
}
.poli-card h2{font-size:clamp(20px,2vw,32px);line-height:1;margin:2px 0}
.poli-card h6{
  width:100%;font-size:clamp(8px,.65vw,12px);margin:1px 0;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis
}

/* RUNNING TEXT */
#running-text{
  position:fixed;bottom:0;left:0;width:100%;height:32px;
  background:rgba(13,71,161,.90);color:#fff;font-size:clamp(13px,1vw,18px);
  font-weight:bold;padding:5px 0;overflow:hidden;backdrop-filter:blur(4px);z-index:20
}
#running-text span{
  display:inline-block;padding-left:100%;white-space:nowrap;
  animation:marquee 125s linear infinite
}
@keyframes marquee{
  from{transform:translate(0,0)}to{transform:translate(-100%,0)}
}
audio{display:none}

/* RESPONSIVE */
@media(max-width:1200px){
  #top-section{
    grid-template-columns:minmax(145px,20%) minmax(360px,60%) minmax(145px,20%)
  }
  .side-poli{grid-template-rows:repeat(4,minmax(0,1fr))}
  .side-poli .poli-side-card:nth-child(n+5){display:none}
}
@media(max-height:700px){
  header{height:62px;padding:6px 18px}
  main{height:calc(100vh - 90px);padding:5px 10px;gap:5px}
  #datapoli{flex-basis:17vh;min-height:80px}
  .side-poli{gap:4px}
  #nomor-box{min-height:170px}
  #running-text{height:27px;padding:3px 0}
}
@media(max-width:850px){
  #top-section{
    grid-template-columns:minmax(110px,19%) minmax(300px,62%) minmax(110px,19%);
    gap:5px
  }
  .side-poli .poli-side-card{padding:4px}
  .side-poli .poli-side-card h5{font-size:9px}
  .side-poli .poli-side-card .dokter,
  .side-poli .poli-side-card .pasien,
  .side-poli .poli-side-card .status{font-size:7px}
}
</style>
</head>

<body>
<header>
  <div>
    <div id="namars">RSU Permata Medika Kebumen</div>
    <div id="text">Selamat datang di RSU Permata Medika Kebumen | Kualitas Pelayanan Adalah Keutamaan Kami</div>
  </div>
  <div style="display:flex;align-items:center;gap:10px;">
    <div id="clock">00:00:00</div>
    <button id="btnTestAV">🔈 Tes Audio</button>
  </div>
</header>

<main>
  <div id="top-section">

    <!-- POLI SISI KIRI -->
    <div id="poli-kiri" class="side-poli"></div>

    <!-- NOMOR ANTRIAN UTAMA -->
    <div id="right-panel">
      <div id="nomor">
        <div id="nomor-box">
          <h2>-</h2>
          <h1>000</h1>
          <b>Tidak ada antrian</b>
        </div>
      </div>
    </div>

    <!-- POLI SISI KANAN -->
    <div id="poli-kanan" class="side-poli"></div>

  </div>

  <div id="datapoli">
    <div class="poli-card"><h5>Memuat...</h5></div>
  </div>
</main>

<div id="running-text">
  <span id="running-message">Gunakan obat sesuai petunjuk dokter/apoteker. Selalu jaga kebersihan tangan dan kesehatan Anda.</span>
</div>

<audio id="notif" src="assets/notif.mp3"></audio>

<script>
/* 🔊 Audio/TTS
   Stream/video dinonaktifkan. Audio notifikasi + ResponsiveVoice tetap aktif. */
function setVideoVolume(vol, mute=false){
  // Dipertahankan sebagai fungsi kompatibilitas agar fitur TTS lama tidak berubah.
  // Tidak ada video/stream yang dikontrol.
}

/* 🕐 Clock */
function updateClock(){ $("#clock").text(new Date().toLocaleTimeString("id-ID",{hour12:false})); }

/* 🔢 Angka Terbilang */
function angkaTerbilang(n){
  const s=["","satu","dua","tiga","empat","lima","enam","tujuh","delapan","sembilan"];
  n=parseInt(n,10);
  if(n<10)return s[n];
  if(n<20)return n==10?"sepuluh":s[n-10]+" belas";
  if(n<100)return s[Math.floor(n/10)]+" puluh"+(n%10?" "+s[n%10]:"");
  if(n<200)return"seratus"+(n%100?" "+angkaTerbilang(n%100):"");
  if(n<1000)return s[Math.floor(n/100)]+" ratus"+(n%100?" "+angkaTerbilang(n%100):"");
  return n;
}

/* 👨‍⚕️ Normalisasi Gelar dan Singkatan (fix anti-ejaan "Sp. A" jadi natural) */
function normalisasiGelar(teks){
  if(!teks) return "";

  return teks
    // Dokter umum dan gigi
    .replace(/\bdrg\.\b/gi, "dokter gigi")
    .replace(/\bdr\b\.?/gi, "dokter")

    // Spesialis (gunakan pola dengan opsional titik & spasi)
    .replace(/\bSp\.?\s*A\b/gi, "spesialis anak")
    .replace(/\bSp\.?\s*S\b/gi, "spesialis saraf")
    .replace(/\bSp\.?\s*U\b/gi, "spesialis urologi")
    .replace(/\bSp\.?\s*PD\b/gi, "spesialis penyakit dalam")
    .replace(/\bSp\.?\s*B\b/gi, "spesialis bedah")
    .replace(/\bSp\.?\s*OG\b/gi, "spesialis obstetri dan ginekologi")
    .replace(/\bSp\.?\s*THT[- ]?KL\b/gi, "spesialis T H T kepala dan leher")
    .replace(/\bSp\.?\s*KFR\b/gi, "spesialis kedokteran fisik dan rehabilitasi")
    .replace(/\bSp\.?\s*KK\b/gi, "spesialis kulit dan kelamin")
    .replace(/\bSp\.?\s*Rad\b/gi, "spesialis radiologi")
    .replace(/\bSp\.?\s*An\b/gi, "spesialis anestesi")
    .replace(/\bSp\.?\s*P\b/gi, "spesialis paru")
    .replace(/\bSp\.?\s*BM\b/gi, "spesialis bedah mulut")
    .replace(/\bSp\.?\s*M\b/gi, "spesialis mata")

    // Sapaan pasien
    .replace(/\bTN\b/gi, "Tuan")
    .replace(/\bNY\b/gi, "Nyonya")
    .replace(/\bNN\b/gi, "Nona")
    .replace(/\bSDR\b/gi, "Saudara")
    .replace(/\bBY\b/gi, "Bayi")
    .replace(/\bAN\b/gi, "Anak")

    // Hapus titik dan perapihan akhir
    .replace(/\./g, "")
    .replace(/\s+/g, " ")
    .trim();
}


/* 💡 Glow Efek */
function triggerGlow(a=true){const b=$("#nomor-box");a?b.addClass("active"):b.removeClass("active");}

/* === 🔊 Fungsi pemanggil suara (fix: tidak eja huruf kapital) === */
function panggilSuara() {
  $.getJSON("app/antrian.php?p=panggil", data => {
    if (!data || data.length === 0) return;
    const notif = $("#notif")[0];

    data.forEach(i => {
      // 🔹 Update tampilan nomor asli dari database
      updateNomorDisplay(i);

      const angka = parseInt(i.no_reg, 10);
      const teksNomor = angkaTerbilang(angka);

      // 🔹 Normalisasi nama untuk TTS (tanpa ejaan)
      const namaTTS = perbaikiNamaNatural(normalisasiGelar(i.nm_pasien));
      const dokterTTS = normalisasiGelar(i.nm_dokter);
      const poliTTS = normalisasiGelar(i.nm_poli);

      // 🔹 Kalimat natural
      const teks = `Nomor antrian ${teksNomor}, atas nama ${namaTTS}, silakan menuju ${poliTTS}, ${dokterTTS}.`;

      // 🔊 Jalankan suara
      notif.currentTime = 0;
      notif.play().then(() => {
        triggerGlow(true);
        responsiveVoice.speak(teks, "Indonesian Female", {
          rate: 1,
          pitch: 1.0,
          volume: 1,
          onstart: () => setVideoVolume(VIDEO_VOLUME_MUTE, true),
          onend: () => {
            triggerGlow(false);
            setVideoVolume(VIDEO_VOLUME_NORMAL, false);
          }
        });
      });
    });
  });
}

/* ✨ Fungsi bantu: ubah huruf kapital agar tidak dieja oleh TTS */
function perbaikiNamaNatural(nama) {
  if (!nama) return "";
  return nama
    .toLowerCase() // ubah ke huruf kecil semua dulu
    .replace(/\b([a-z])/g, c => c.toUpperCase()) // kapital di awal tiap kata
    .replace(/\s+/g, " ")
    .trim();
}



/* 🔢 Update Tampilan Nomor (tampilkan teks asli dari database) */
function updateNomorDisplay(i){
  if(!i || i.no_reg === "000") return;

  $("#nomor-box").html(`
    <h2>${i.nm_pasien}</h2>
    <h1>${i.no_reg}</h1>
    <b>${i.nm_poli} - ${i.nm_dokter}</b>
  `);
}


/* 🏥 Card poli kiri/kanan
   Data berasal dari endpoint poli yang sama, tanpa mengubah data/database. */
function renderSidePoli(d){
  if(!Array.isArray(d)) d=[];

  const cards = d.map(r => {
    const pasien = (r.data_pasien && r.data_pasien.length > 0)
      ? r.data_pasien[0]
      : { no_reg:"000", nm_pasien:"Belum ada antrian" };

    const no_reg = pasien.no_reg || "000";
    const nm_pasien = pasien.nm_pasien || "Belum ada antrian";

    let status = "AKTIF";
    let warna = "linear-gradient(145deg,#004d40,#00796b)";

    if(/belum mulai/i.test(nm_pasien)){
      status="BELUM MULAI";
      warna="linear-gradient(145deg,#004C99,#0066CC)";
    }else if(/selesai/i.test(nm_pasien)){
      status="SELESAI";
      warna="linear-gradient(145deg,#616161,#8e8e8e)";
    }else if(/tidak ada antrian|belum ada/i.test(nm_pasien)){
      status="TIDAK ADA ANTRIAN";
      warna="linear-gradient(145deg,#7f1d1d,#b71c1c)";
    }

    return `
      <div class="poli-side-card" style="background:${warna};">
        <h5>${r.nm_poli || "-"}</h5>
        <div class="dokter">${r.nm_dokter || "-"}</div>
        <div class="nomor">${no_reg}</div>
        <div class="pasien">${nm_pasien}</div>
        <div class="status">${status}</div>
      </div>`;
  }).join("");

  const kiri = d.slice(0,5);
  const kanan = d.slice(5,10);

  function build(arr){
    return arr.map(r => {
      const pasien=(r.data_pasien && r.data_pasien.length)
        ? r.data_pasien[0]
        : {no_reg:"000",nm_pasien:"Belum ada antrian"};
      const no_reg=pasien.no_reg||"000";
      const nm_pasien=pasien.nm_pasien||"Belum ada antrian";

      let status="AKTIF";
      let warna="linear-gradient(145deg,#004d40,#00796b)";
      if(/belum mulai/i.test(nm_pasien)){
        status="BELUM MULAI";
        warna="linear-gradient(145deg,#004C99,#0066CC)";
      }else if(/selesai/i.test(nm_pasien)){
        status="SELESAI";
        warna="linear-gradient(145deg,#616161,#8e8e8e)";
      }else if(/tidak ada antrian|belum ada/i.test(nm_pasien)){
        status="TIDAK ADA ANTRIAN";
        warna="linear-gradient(145deg,#7f1d1d,#b71c1c)";
      }

      return `<div class="poli-side-card" style="background:${warna};">
        <h5>${r.nm_poli||"-"}</h5>
        <div class="dokter">${r.nm_dokter||"-"}</div>
        <div class="nomor">${no_reg}</div>
        <div class="pasien">${nm_pasien}</div>
        <div class="status">${status}</div>
      </div>`;
    }).join("");
  }

  $("#poli-kiri").html(build(kiri));
  $("#poli-kanan").html(build(kanan));

  if(!kiri.length) $("#poli-kiri").html(`<div class="poli-side-card"><h5>Belum ada poli</h5></div>`);
  if(!kanan.length) $("#poli-kanan").html(`<div class="poli-side-card"><h5>Belum ada poli</h5></div>`);
}

/* 🏥 Daftar Poli - grid responsif, seluruh mapping ditampilkan */
function updateDataPoli(){
  $.getJSON("app/antrian.php?p=poli", d => {
    renderSidePoli(d || []);

    if (!d || d.length === 0) {
      $("#datapoli").html(`
        <div class="poli-card" style="background:rgba(60,60,60,0.9);">
          <h5>Tidak ada poli aktif</h5>
        </div>
      `);
      return;
    }

    let html = "";

    d.forEach(r => {
      const pasien = (r.data_pasien && r.data_pasien.length > 0)
        ? r.data_pasien[0]
        : { no_reg: "000", nm_pasien: "Belum ada antrian" };
      const no_reg = pasien.no_reg;
      const nm_pasien = pasien.nm_pasien;

      // Tentukan status berdasarkan teks pasien
      let status = "AKTIF";
      let warna = "linear-gradient(145deg,#004d40,#00695c)"; // 🟩 aktif

      if (/belum mulai/i.test(nm_pasien)) {
        status = "BELUM MULAI";
        warna = "linear-gradient(145deg,#004C99,#0066CC)"; // 🟦 biru
      } else if (/selesai/i.test(nm_pasien)) {
        status = "SELESAI";
        warna = "linear-gradient(145deg,#757575,#9e9e9e)"; // ⚫ abu
      } else if (/tidak ada antrian|belum ada/i.test(nm_pasien)) {
        status = "TIDAK ADA ANTRIAN";
        warna = "linear-gradient(145deg,#b71c1c,#d32f2f)"; // 🟥 merah
      }

      html += `
        <div class="poli-card" style="background:${warna};">
          <h5><b>${r.nm_poli}</b></h5>
          <div style="border-top:1px solid #fff;margin:4px 0;"></div>
          <h6>${r.nm_dokter}</h6>
          <h2>${no_reg}</h2>
          <h6>${nm_pasien}</h6>
          <div style="margin-top:4px;font-size:12px;font-weight:bold;text-transform:uppercase;opacity:.95;">
            ${status}
          </div>
        </div>
      `;
    });

    $("#datapoli").html(html);
  });
}

/* 🧾 Running Text — versi panjang informatif RSU Permata Medika Kebumen */
const runningMessage = `
SELAMAT DATANG DI RUMAH SAKIT UMUM PERMATA MEDIKA KEBUMEN — 
Jl. Indrakila No.17, Kebumen, Jawa Tengah. 
Kami hadir memberikan pelayanan kesehatan profesional, ramah, dan berorientasi pada keselamatan pasien. 
LAYANAN TERSEDIA: POLI UMUM, POLI GIGI, POLI ANAK, POLI PENYAKIT DALAM, POLI KANDUNGAN & KEBIDANAN, POLI BEDAH, REHABILITASI MEDIK, RADIOLOGI, DAN LABORATORIUM. 
IGD 24 JAM SIAP MELAYANI ANDA SETIAP SAAT. 
Nomor antrian dipanggil sesuai urutan, mohon menunggu dengan tertib di area tunggu. 
Gunakan masker, jaga kebersihan tangan, dan tetap jaga jarak selama berada di area rumah sakit. 
Silakan nikmati tayangan edukasi kesehatan selama menunggu giliran pemeriksaan. 
Untuk informasi lebih lanjut, hubungi (0287) 384418 atau WhatsApp 0811-2998-301. 
Ikuti kami di Instagram @rsu.permatamedika.kbm untuk informasi layanan dan promo kesehatan terkini. 
TERIMA KASIH ATAS KEPERCAYAAN ANDA KEPADA RSU PERMATA MEDIKA KEBUMEN — KESEHATAN ANDA ADALAH PRIORITAS KAMI.
`;

// Atur teks berjalan
$("#running-message").text(runningMessage.replace(/\s+/g, " ").trim());

/* ⚙️ Inisialisasi
   Stream dinonaktifkan. Fitur antrian, TTS, notif, jam, polling poli,
   dan tombol tes audio tetap berjalan seperti sebelumnya. */
$(function(){
  updateClock();setInterval(updateClock,1000);
  setInterval(panggilSuara,3000);
  updateDataPoli();setInterval(updateDataPoli,3000);
  $("#btnTestAV").on("click",()=>{const a=$("#notif")[0];a.play();responsiveVoice.speak("Tes suara antrian aktif","Indonesian Female");$("#btnTestAV").text("✅ Audio Aktif").css("background","#4CAF50");});
});
</script>
</body>
</html>
