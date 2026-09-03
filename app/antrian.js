function pengaturan() {
  $.ajax({
    url: "app/antrian.php?p=pengaturan",
    type: "GET",
    dataType: "json",
    success: function (data) {
      $("#namars").html(data.nama_instansi || "RSU Permata Medika Kebumen");
      $("#text").html(data.text || "Kualitas Pelayanan Adalah Keutamaan Kami");
    },
    error: function (xhr) {
      console.error("Gagal ambil pengaturan:", xhr.statusText);
    }
  });
}

function tampilAntrian() {
  $.getJSON("app/antrian.php?p=nomor", function (data) {
    const nomor = $("#nomor");
    nomor.empty();
    if (data && data.no_reg !== "000") {
      nomor.html(`
        <h2>${data.nm_pasien}</h2>
        <h1>${data.no_reg}</h1>
        <b>${data.nm_poli} - ${data.nm_dokter}</b>
      `);
    } else {
      nomor.html("<h1>000</h1><b>Tidak ada antrian</b>");
    }
  }).fail(xhr => console.error("Gagal ambil nomor:", xhr.statusText));

  $.getJSON("app/antrian.php?p=poli", function (data) {
    const wrapper = $("#datapoli");
    wrapper.empty();
    if (!data || data.length === 0) {
      wrapper.html("<div class='poli-card'><h5>Tidak ada data poli aktif</h5></div>");
      return;
    }

    $.each(data, function (i, item) {
      const pasienList = (item.data_pasien && item.data_pasien.length > 0)
        ? item.data_pasien.map(pasien => `
            <h2>${pasien.no_reg}</h2>
            <h6>${pasien.nm_pasien}</h6>
          `).join("")
        : `<h2>000</h2><h6>Tidak ada antrian</h6>`;

      wrapper.append(`
        <div class="poli-card">
          <h5>${item.nm_poli}</h5>
          <div style="border-top:1px solid #80d8ff;margin:4px 0;"></div>
          <h6>${item.nm_dokter}</h6>
          ${pasienList}
        </div>
      `);
    });
  }).fail(xhr => console.error("Gagal ambil poli:", xhr.statusText));
}

function updateClock() {
  const now = new Date();
  const h = now.getHours().toString().padStart(2, "0");
  const m = now.getMinutes().toString().padStart(2, "0");
  const s = now.getSeconds().toString().padStart(2, "0");
  $("#clock").text(`${h}:${m}:${s}`);
}

function startStream() {
  const video = document.getElementById("tvStream");
  const streamURL = "http://192.168.9.136/hls/stream.m3u8";
  const fallback = "video/edukasi.mp4";

  if (Hls.isSupported()) {
    const hls = new Hls({ maxBufferLength: 10 });
    hls.loadSource(streamURL);
    hls.attachMedia(video);
  } else if (video.canPlayType("application/vnd.apple.mpegurl")) {
    video.src = streamURL;
  } else {
    video.src = fallback;
  }

  document.body.addEventListener("click", () => {
    video.muted = false;
    video.volume = 0.3;
    video.play();
  }, { once: true });
}

/* =======================
   TTS + CALL QUEUE
   ======================= */
let callPlaying = false;
let activeCallToken = null;
let heartbeatTimer = null;
let lastCallToken = sessionStorage.getItem("antrianpoli_last_call_token") || null;

function normalisasiGelar(teks) {
  if (!teks) return "";
  return teks
    .replace(/\bdrg\.\b/gi, "dokter gigi")
    .replace(/\bdr\.\b/gi, "dokter")
    .replace(/\bSp\.A\b/gi, "spesialis anak")
    .replace(/\bSp\.PD\b/gi, "spesialis penyakit dalam")
    .replace(/\bSp\.B\b/gi, "spesialis bedah")
    .replace(/\bSp\.OG\b/gi, "spesialis obstetri dan ginekologi")
    .replace(/\bSp\.THT-KL\b/gi, "spesialis T H T kepala dan leher")
    .replace(/\bSp\.KFR\b/gi, "spesialis kedokteran fisik dan rehabilitasi")
    .replace(/\bSp\.P\b/gi, "spesialis paru")
    .replace(/\bTN\b/gi, "Tuan")
    .replace(/\bNY\b/gi, "Nyonya")
    .replace(/\bNN\b/gi, "Nona")
    .replace(/\bSDR\b/gi, "Saudara")
    .replace(/\bBY\b/gi, "Bayi")
    .replace(/\bAN\b/gi, "Anak")
    .replace(/\s+/g, " ")
    .trim();
}

function angkaTerbilang(n) {
  const s = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan"];
  n = parseInt(n, 10);
  if (isNaN(n)) return "";
  if (n === 0) return "nol";
  if (n < 10) return s[n];
  if (n < 20) return n === 10 ? "sepuluh" : s[n - 10] + " belas";
  if (n < 100) return s[Math.floor(n / 10)] + " puluh" + (n % 10 ? " " + s[n % 10] : "");
  if (n < 200) return "seratus" + (n % 100 ? " " + angkaTerbilang(n % 100) : "");
  if (n < 1000) return s[Math.floor(n / 100)] + " ratus" + (n % 100 ? " " + angkaTerbilang(n % 100) : "");
  return n.toString();
}

function stopHeartbeat() {
  if (heartbeatTimer) {
    clearInterval(heartbeatTimer);
    heartbeatTimer = null;
  }
}

function startHeartbeat(token) {
  stopHeartbeat();
  heartbeatTimer = setInterval(() => {
    if (!token) return;
    $.ajax({
      url: "app/antrian.php?p=heartbeat",
      type: "POST",
      data: { call_token: token },
      dataType: "json"
    }).fail(xhr => console.warn("Heartbeat call gagal:", xhr.statusText));
  }, 5000);
}

function ackCall(token) {
  if (!token) return;
  stopHeartbeat();

  $.ajax({
    url: "app/antrian.php?p=ack",
    type: "POST",
    data: { call_token: token },
    dataType: "json"
  }).done(() => {
    if (activeCallToken === token) {
      activeCallToken = null;
      callPlaying = false;
    }
  }).fail(xhr => {
    console.error("ACK pemanggilan gagal:", xhr.statusText);
    // Tetap lepaskan lock browser agar polling dapat mencoba lagi.
    // Server tetap menjaga call sebagai playing sampai ACK berhasil.
    if (activeCallToken === token) {
      activeCallToken = null;
      callPlaying = false;
    }
  });
}

function panggilSuara() {
  // Jangan pernah meminta nomor berikutnya ketika TTS lokal masih berjalan.
  if (callPlaying) return;

  $.getJSON("app/antrian.php?p=panggil", function (data) {
    if (!data || data.length === 0) return;

    const item = data[0];
    const token = item.call_token;
    if (!token) {
      console.error("Respons panggilan tidak memiliki call_token");
      return;
    }

    // Polling 3 detik akan mendapatkan call playing yang sama.
    // Jangan memutar ulang call yang sudah diproses oleh tab ini.
    if (token === lastCallToken) return;
    if (callPlaying) return;

    callPlaying = true;
    activeCallToken = token;
    lastCallToken = token;
    sessionStorage.setItem("antrianpoli_last_call_token", token);
    startHeartbeat(token);

    const notif = $("#notif")[0];
    const angka = parseInt(item.no_reg, 10);
    const teksNomor = angkaTerbilang(angka);
    const nama = normalisasiGelar(item.nm_pasien);
    const dokter = normalisasiGelar(item.nm_dokter);
    const teks = `Nomor antrian ${teksNomor}, atas nama ${nama}, silakan menuju ${item.nm_poli}, ${dokter}.`;

    const selesai = function () {
      ackCall(token);
    };

    const mulaiTTS = function () {
      try {
        responsiveVoice.speak(teks, "Indonesian Female", {
          rate: 1,
          pitch: 1,
          volume: 1,
          onend: selesai,
          onerror: selesai
        });
      } catch (e) {
        console.error("TTS error:", e);
        selesai();
      }
    };

    if (notif && typeof notif.play === "function") {
      notif.currentTime = 0;
      const playPromise = notif.play();
      if (playPromise && typeof playPromise.then === "function") {
        playPromise.then(mulaiTTS).catch(() => mulaiTTS());
      } else {
        mulaiTTS();
      }
    } else {
      mulaiTTS();
    }
  }).fail(xhr => console.error("Gagal mengambil panggilan:", xhr.statusText));
}

$(document).ready(function () {
  pengaturan();
  tampilAntrian();
  startStream();
  updateClock();

  setInterval(panggilSuara, 3000);
  setInterval(updateClock, 1000);
  setInterval(tampilAntrian, 3000);
});
