const AUDIO_PATH = "assets/notif.mp3";
const VIDEO_VOLUME_NORMAL = 0.3;   // volume normal video (30%)
const VIDEO_VOLUME_DUCK = 0.3;     // volume saat idle (sama dengan normal)
const VIDEO_VOLUME_MUTE = 0.0;     // mute penuh saat TTS aktif

let lastCalled = {};
let activeHighlight = null;

/* ==========================================================
   🔹 STREAM DARI SERVER LINUX (NGINX + OBS)
   ========================================================== */
function startStream() {
  const video = document.getElementById("tvStream");
  if (!video) return;

  const streamURL = "http://192.168.9.136/hls/stream.m3u8"; // ⚙️ ubah IP server sesuai setup
  const fallbackVideo = "video/edukasi.mp4";

  video.muted = true; // mulai dalam kondisi mute agar autoplay tidak diblok
  video.volume = 0;
  video.loop = true;

  function playStream(url) {
    if (Hls.isSupported()) {
      const hls = new Hls({ maxBufferLength: 10 });
      hls.loadSource(url);
      hls.attachMedia(video);
      hls.on(Hls.Events.ERROR, (event, data) => {
        if (data.fatal) {
          console.warn("⚠️ Stream error, mencoba ulang...");
          setTimeout(() => playStream(url), 4000);
        }
      });
    } else if (video.canPlayType("application/vnd.apple.mpegurl")) {
      video.src = url;
    } else {
      console.error("🚫 HLS tidak didukung di browser ini");
      useFallback();
    }
  }

  function useFallback() {
    console.warn("⚠️ Stream tidak aktif, memutar video edukasi lokal...");
    video.src = fallbackVideo;
    video.muted = false;
    video.volume = VIDEO_VOLUME_DUCK;
    video.play().catch(() => {});
  }

  // cek apakah stream aktif
  fetch(streamURL, { method: "HEAD" })
    .then(res => {
      if (res.ok) playStream(streamURL);
      else useFallback();
    })
    .catch(() => useFallback());

  // aktifkan audio stream setelah user klik pertama
  document.body.addEventListener("click", () => {
    video.muted = false;
    video.volume = VIDEO_VOLUME_NORMAL;
    video.play().catch(() => {});
    console.log("🎵 Audio stream diaktifkan oleh pengguna.");
  }, { once: true });
}

/* ==========================================================
   🔤 KONVERSI TEKS UNTUK PEMBACAAN NATURAL TTS
   ========================================================== */
function normalizeTTS(teks) {
  if (!teks) return "";

  // buat salinan untuk pencocokan tanpa mengubah teks asli
  let t = teks.toUpperCase();

  // ubah gelar di salinan teks
  t = t.replace(/\bDR\.\b/g, "dokter ");
  t = t.replace(/\bDR\b/g, "dokter ");
  t = t.replace(/SP\.PD/gi, "Spesialis Penyakit Dalam");
  t = t.replace(/SP\.A/gi, "Spesialis Anak");
  t = t.replace(/SP\.OG/gi, "Spesialis Kebidanan dan Kandungan");
  t = t.replace(/SP\.B/gi, "Spesialis Bedah");
  t = t.replace(/SP\.THT/gi, "Spesialis T H T");
  t = t.replace(/\bSDR\b/g, "Saudara ");
  t = t.replace(/SP\.P/gi, "Spesialis Paru");

  // sapaan umum
  t = t.replace(/\bTN\b/g, "Tuan");
  t = t.replace(/\bNY\b/g, "Nyonya");
  t = t.replace(/\bNN\b/g, "Nona");
  t = t.replace(/\bBY\b/g, "Bayi");

  // hapus nol di depan angka
  t = t.replace(/\b0+(\d+)\b/g, "$1");

  // ubah angka jadi kata
  t = t.replace(/\b(\d+)\b/g, (m) => angkaKeTeks(parseInt(m)));

  // hasil akhir, kembalikan ke huruf biasa (tidak semua kapital)
  return t.charAt(0).toUpperCase() + t.slice(1).toLowerCase();
}


/* ==========================================================
   🔢 KONVERSI ANGKA KE TEKS INDONESIA
   ========================================================== */
function angkaKeTeks(angka) {
  const a = ["nol","satu","dua","tiga","empat","lima","enam","tujuh","delapan","sembilan"];
  const b = ["sepuluh","sebelas","dua belas","tiga belas","empat belas","lima belas",
             "enam belas","tujuh belas","delapan belas","sembilan belas"];
  if (angka < 10) return a[angka];
  if (angka < 20) return b[angka - 10];
  if (angka < 100) {
    const p = Math.floor(angka / 10);
    const s = angka % 10;
    return a[p] + " puluh" + (s ? " " + a[s] : "");
  }
  if (angka < 200) return "seratus " + angkaKeTeks(angka - 100);
  if (angka < 1000) {
    const r = Math.floor(angka / 100);
    const s = angka % 100;
    return a[r] + " ratus" + (s ? " " + angkaKeTeks(s) : "");
  }
  return angka.toString();
}

/* ==========================================================
   🔊 PEMANGGILAN SUARA (sinkron notif + TTS + ducking)
   ========================================================== */
function panggilSuara() {
  $.getJSON("app/antrian.php?p=panggil", data => {
    if (!data || data.length === 0) return;
    const v = document.getElementById("tvStream");
    const a = document.getElementById("notif");

    data.forEach(item => {
      updateNomorDisplay(item);

      const key = `${item.nm_poli}-${item.nm_dokter}`;
      lastCalled[key] = {
        nm_poli: item.nm_poli,
        nm_dokter: item.nm_dokter,
        no_reg: item.no_reg,
        nm_pasien: item.nm_pasien
      };

      let namaTTS = item.nm_pasien.toUpperCase()
        .replace(/\bTN\b/g, "Tuan")
        .replace(/\bNY\b/g, "Nyonya")
        .replace(/\bNN\b/g, "Nona")
        .replace(/\bBY\b/g, "Bayi");

      const teksRaw = `Nomor antrian ${item.no_reg}, atas nama ${namaTTS}, silakan menuju ${item.nm_poli}, ${item.nm_dokter}`;
      const teks = normalizeTTS(teksRaw);

      a.src = AUDIO_PATH;
      a.currentTime = 0;

      a.play().then(() => {
        highlightPoli(item.nm_poli, item.nm_dokter);

        // aktifkan efek glow pada kotak utama
        triggerGlow(true);

        a.onended = function () {
          // mute video sebelum TTS
          if (v) { v.muted = true; v.volume = 0.0; }

          responsiveVoice.speak(teks, "Indonesian Female", {
            rate: 1,
            pitch: 1,
            volume: 1,
            onend: () => {
              // kembalikan volume normal
              if (v) { v.muted = false; v.volume = VIDEO_VOLUME_NORMAL; }
              $(".poli-card").removeClass("heartbeat");
              triggerGlow(false);
            }
          });
        };
      }).catch(() => {});
    });
  });
}

/* ==========================================================
   💡 EFEK GLOW NOMOR UTAMA
   ========================================================== */
function triggerGlow(active = true) {
  const box = document.getElementById("nomor-box");
  if (!box) return;
  if (active) box.classList.add("active");
  else box.classList.remove("active");
}

/* ==========================================================
   💡 UPDATE NOMOR UTAMA
   ========================================================== */
function updateNomorDisplay(item) {
  if (!item || item.no_reg === "000") return;
  $("#nomor-box").html(`
    <h2>${item.nm_pasien}</h2>
    <h1>${item.no_reg}</h1>
    <b>${item.nm_poli} - ${item.nm_dokter}</b>
  `);
}

/* ==========================================================
   💡 HIGHLIGHT POLI AKTIF
   ========================================================== */
function highlightPoli(poli, dokter) {
  if (activeHighlight) clearTimeout(activeHighlight);
  const cards = $(".poli-card").filter(function () {
    return (
      $(this).find("h5").text().trim() === poli &&
      $(this).find("small").text().trim() === dokter
    );
  });
  cards.addClass("heartbeat");
  activeHighlight = setTimeout(() => {
    cards.removeClass("heartbeat");
  }, 8000);
}

/* ==========================================================
   🏥 DAFTAR POLI PER DOKTER
   ========================================================== */
function tampilDaftarPoli() {
  $.getJSON("app/antrian.php?p=poli", d => {
    const w = $("#datapoli");
    w.empty();
    if (!d || d.length === 0) {
      w.html("<div class='poli-card'><b>Tidak ada data</b></div>");
      return;
    }

    const poliDokterMap = {};
    d.forEach(p => {
      const key = `${p.nm_poli}-${p.nm_dokter}`;
      if (!poliDokterMap[key]) poliDokterMap[key] = p;
    });

    let aktif = [];
    Object.values(poliDokterMap).forEach(p => {
      if (p.data_pasien.length > 0) {
        const valid = p.data_pasien.filter(px => px.no_reg !== "000");
        if (valid.length > 0) {
          aktif.push(p);
          lastCalled[`${p.nm_poli}-${p.nm_dokter}`] = {
            nm_poli: p.nm_poli,
            nm_dokter: p.nm_dokter,
            no_reg: valid[0].no_reg,
            nm_pasien: valid[0].nm_pasien
          };
        }
      }
    });

    if (aktif.length < 4) {
      const cadangan = Object.keys(lastCalled)
        .filter(k => !aktif.some(a => `${a.nm_poli}-${a.nm_dokter}` === k))
        .slice(0, 4 - aktif.length)
        .map(k => {
          const lc = lastCalled[k];
          return {
            nm_poli: lc.nm_poli || "Poli Tidak Dikenal",
            nm_dokter: lc.nm_dokter || "-",
            data_pasien: [lc]
          };
        });
      aktif = aktif.concat(cadangan);
    }

    aktif.slice(-4).forEach(p => {
      const key = `${p.nm_poli}-${p.nm_dokter}`;
      let pasien = null;
      if (p.data_pasien.length > 0) pasien = p.data_pasien[0];
      else if (lastCalled[key]) pasien = lastCalled[key];
      if (!pasien) return;

      const c = $(`
        <div class='poli-card'>
          <h5>${p.nm_poli}</h5>
          <small>${p.nm_dokter}</small>
          <hr>
          <div>
            <h2>${pasien.no_reg}</h2>
            <h6>${pasien.nm_pasien}</h6>
          </div>
        </div>
      `);
      w.append(c);
    });
  });
}

/* ==========================================================
   ⏰ CLOCK
   ========================================================== */
function updateClock() {
  $("#clock").text(new Date().toLocaleTimeString("id-ID", { hour12: false }));
}

/* ==========================================================
   🚀 INIT
   ========================================================== */
$(function () {
  startStream();
  tampilDaftarPoli();
  updateClock();
  setInterval(updateClock, 1000);
  setInterval(() => {
    tampilDaftarPoli();
    panggilSuara();
  }, 5000);

  $("#btnTestAV").on("click", () => {
    const a = document.getElementById("notif");
    const v = document.getElementById("tvStream");
    a.play().catch(() => {});
    responsiveVoice.speak("Tes suara antrian aktif", "Indonesian Female");
    if (v) v.play().catch(() => {});
    $("#btnTestAV").text("✅ Audio Aktif").css("background", "#4CAF50");
  });

  $(document).one("click", () => {
    const a = document.getElementById("notif");
    a.play().catch(() => {});
    responsiveVoice.speak("Audio aktif", "Indonesian Female");
  });
});
