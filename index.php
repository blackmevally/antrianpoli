<?php date_default_timezone_set("Asia/Jakarta"); ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Display Antrian RSU Permata Medika Kebumen</title>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script src="assets/responsivevoice.js"></script>

<style>
body {
  font-family: 'Segoe UI', sans-serif;
  color: white;
  margin: 0;
  overflow: hidden;
  background-color: #0b0b0b;
  position: relative;
}

/* Background */
body::before {
  content: "";
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: url("assets/bg_rsu.png") center center / cover no-repeat;
  opacity: 0.3;
  z-index: -1;
}

/* Header */
header {
  background: rgba(13, 71, 161, 0.7);
  color: white;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 30px;
  backdrop-filter: blur(4px);
}
#namars { font-size: 26px; font-weight: bold; }
#text { font-size: 13px; opacity: .9; }
#clock { font-size: 22px; font-weight: bold; }

/* Layout utama */
main {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 120px);
  padding: 10px 20px 10px;
  box-sizing: border-box;
}

/* Baris atas */
#top-section {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  gap: 10px;
  width: 100%;
  box-sizing: border-box;
}

/* Video kiri */
#video-container {
  flex: 0 0 60%;
  position: relative;
  background: rgba(0,0,0,0.15);
  backdrop-filter: blur(6px);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 0 15px rgba(0,0,0,0.4);
}
#tvStream {
  position: absolute;
  top: 0; left: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
  background-color: transparent;
  border-radius: 16px;
  z-index: 2;
}
#volume-indicator {
  position: absolute;
  top: 10px; left: 10px;
  background: rgba(0,0,0,0.6);
  padding: 6px 10px;
  border-radius: 10px;
  font-size: 22px;
}

/* Panel kanan (Nomor antrian utama) */
#right-panel {
  flex: 0 0 40%;
  display: flex;
  justify-content: center;
  align-items: center;
  border-radius: 16px;
  background: rgba(25,25,25,0.35);
  backdrop-filter: blur(5px);
  box-shadow: 0 0 15px rgba(0,0,0,0.5);
}
#right-panel, #video-container {
  aspect-ratio: 16 / 9;
  height: auto;
}
#nomor-box {
  text-align: center;
  border: 4px solid #80d8ff;
  border-radius: 20px;
  background: rgba(0,0,0,0.35);
  box-shadow: 0 0 25px rgba(0,0,0,0.6);
  width: 95%;
  height: 90%;
  padding: 25px 10px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  overflow: hidden;
}
#nomor-box.active {
  animation: glowPulse 1.2s infinite;
  border-color: #00b0ff;
  box-shadow: 0 0 25px 8px rgba(0,176,255,0.6);
}
@keyframes glowPulse {
  0%,100%{box-shadow:0 0 20px 6px rgba(0,176,255,0.5);}
  50%{box-shadow:0 0 35px 12px rgba(0,255,255,0.8);}
}
#nomor h2 { font-size: 40px; margin: 5px 0; color: #80d8ff; }
#nomor h1 { font-size: 140px; margin: 0; line-height: 1; }
#nomor b { font-size: 22px; color: #ffcc80; }

/* Daftar Poli bawah */
#datapoli {
  display: flex;
  flex-wrap: wrap; /* ✅ bisa 2 baris atau lebih */
  justify-content: center;
  align-items: stretch;
  gap: 10px;
  margin-top: 10px;
  margin-bottom: 5px;
  overflow: hidden;
  height: auto;
  max-height: 30vh; /* ✅ batas tinggi agar pas antara video & running text */
  padding-bottom: 5px;
  box-sizing: border-box;
}

.poli-card {
  flex: 1 1 240px; /* ✅ fleksibel, menyesuaikan lebar layar */
  max-width: 260px;
  background: rgba(38, 50, 56, 0.9);
  border-radius: 10px;
  padding: 10px 12px;
  text-align: center;
  box-shadow: 0 0 8px rgba(0,0,0,0.4);
  transition: transform 0.3s ease;
}

.poli-card:hover {
  transform: scale(1.04);
}

.poli-card h5 {
  font-size: 16px;
  color: #80d8ff;
  margin: 3px 0;
}
.poli-card h2 {
  font-size: 32px;
  margin: 0;
  color: #fff;
}
.poli-card h6 {
  font-size: 13px;
  color: #ccc;
  margin: 0;
}


/* Running Text */
#running-text {
  position: fixed;
  bottom: 0; left: 0;
  width: 100%;
  background: rgba(13,71,161,0.7);
  color: white;
  font-size: 18px;
  font-weight: bold;
  padding: 5px 0;
  overflow: hidden;
  backdrop-filter: blur(4px);
}
#running-text span {
  display: inline-block;
  padding-left: 100%;
  white-space: nowrap;
  animation: marquee 125s linear infinite;
}
@keyframes marquee {
  from { transform: translate(0,0); }
  to { transform: translate(-100%,0); }
}
audio { display: none; }
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
    <button id="btnTestAV" style="background:#f44336;color:white;border:none;border-radius:6px;padding:5px 10px;cursor:pointer;font-weight:bold;">
      🔈 Tes Audio
    </button>
  </div>
</header>

<main>
  <div id="top-section">
    <div id="video-container">
      <video id="tvStream" autoplay muted loop></video>
      <div id="volume-indicator">🔊</div>
    </div>
    <div id="right-panel">
      <div id="nomor">
        <div id="nomor-box">
          <h2>-</h2><h1>000</h1><b>Tidak ada antrian</b>
        </div>
      </div>
    </div>
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
const VIDEO_VOLUME_NORMAL = 0.3;
const VIDEO_VOLUME_MUTE = 0.0;

/* 🔊 Video Volume Control */
function setVideoVolume(vol, mute=false){
  const v=$("#tvStream")[0];
  const icon=$("#volume-indicator");
  if(v){v.volume=vol;v.muted=mute;}
  icon.text(mute?"🔇":"🔊");
}

/* 🎙️ Ducking */
const originalSpeak = responsiveVoice.speak;
responsiveVoice.speak = function(text,voice,options){
  setVideoVolume(VIDEO_VOLUME_MUTE,true);
  const wrapped = Object.assign({},options,{
    onend:function(){
      setVideoVolume(VIDEO_VOLUME_NORMAL,false);
      if(options && typeof options.onend==="function") options.onend();
    }
  });
  originalSpeak.call(this,text,voice,wrapped);
};

/* 🎥 Stream */
function startStream(){
  const video=document.getElementById("tvStream");
  const streamURL="http://192.168.9.136/hls/stream.m3u8";
  const fallback="video/edukasi.mp4";
  if(Hls.isSupported()){
    const hls=new Hls({maxBufferLength:10});
    hls.loadSource(streamURL);
    hls.attachMedia(video);
  } else if(video.canPlayType("application/vnd.apple.mpegurl")) video.src=streamURL;
  else video.src=fallback;
  document.body.addEventListener("click",()=>{video.muted=false;video.volume=VIDEO_VOLUME_NORMAL;video.play();},{once:true});
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

/* 🏥 Daftar Poli (tanpa auto-scroll, responsif 2 baris) */
function updateDataPoli(){
  $.getJSON("app/antrian.php?p=poli", d => {
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

/* ⚙️ Inisialisasi */
$(function(){
  startStream();
  updateClock();setInterval(updateClock,1000);
  setInterval(panggilSuara,3000);
  updateDataPoli();setInterval(updateDataPoli,3000);
  $("#btnTestAV").on("click",()=>{const a=$("#notif")[0];a.play();responsiveVoice.speak("Tes suara antrian aktif","Indonesian Female");$("#btnTestAV").text("✅ Audio Aktif").css("background","#4CAF50");});
});
</script>
</body>
</html>
