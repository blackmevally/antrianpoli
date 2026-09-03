/*
 * AntrianPoli - single TTS/call queue engine.
 * UI/poli rendering tetap ditangani oleh display.php.
 * Database Khanza tidak disentuh oleh file ini.
 */
(function () {
  "use strict";

  let callPlaying = false;
  let activeCallToken = null;
  let heartbeatTimer = null;
  let ackRetryTimer = null;
  let lastCallToken = sessionStorage.getItem("antrianpoli_last_call_token") || null;

  function normalisasiGelar(teks) {
    if (!teks) return "";
    return teks
      .replace(/\bdrg\.\b/gi, "dokter gigi")
      .replace(/\bdr\b\.?/gi, "dokter")
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
      .replace(/\bTN\b/gi, "Tuan")
      .replace(/\bNY\b/gi, "Nyonya")
      .replace(/\bNN\b/gi, "Nona")
      .replace(/\bSDR\b/gi, "Saudara")
      .replace(/\bBY\b/gi, "Bayi")
      .replace(/\bAN\b/gi, "Anak")
      .replace(/\./g, "")
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

  function perbaikiNamaNatural(nama) {
    if (!nama) return "";
    return nama.toLowerCase().replace(/\b([a-z])/g, c => c.toUpperCase()).replace(/\s+/g, " ").trim();
  }

  function triggerGlow(active) {
    const box = document.getElementById("nomor-box");
    if (box) box.classList.toggle("active", !!active);
  }

  function updateNomorDisplay(item) {
    if (!item || item.no_reg === "000") return;
    const box = document.getElementById("nomor-box");
    if (!box) return;
    box.innerHTML = `
      <h2>${item.nm_pasien || "-"}</h2>
      <h1>${item.no_reg || "000"}</h1>
      <b>${item.nm_poli || "-"} - ${item.nm_dokter || "-"}</b>
    `;
  }

  function stopHeartbeat() {
    if (heartbeatTimer) {
      clearInterval(heartbeatTimer);
      heartbeatTimer = null;
    }
  }

  function startHeartbeat(token) {
    stopHeartbeat();
    heartbeatTimer = setInterval(function () {
      if (!token) return;
      $.ajax({
        url: "app/antrian.php?p=heartbeat",
        type: "POST",
        data: { call_token: token },
        dataType: "json"
      }).fail(function (xhr) {
        console.warn("Heartbeat call gagal:", xhr.statusText);
      });
    }, 5000);
  }

  function clearAckRetry() {
    if (ackRetryTimer) {
      clearTimeout(ackRetryTimer);
      ackRetryTimer = null;
    }
  }

  function ackCall(token) {
    if (!token || token !== activeCallToken) return;
    clearAckRetry();

    $.ajax({
      url: "app/antrian.php?p=ack",
      type: "POST",
      data: { call_token: token },
      dataType: "json",
      timeout: 5000
    }).done(function (response) {
      if (response && response.ok === false) {
        scheduleAckRetry(token);
        return;
      }
      stopHeartbeat();
      if (activeCallToken === token) {
        activeCallToken = null;
        callPlaying = false;
        triggerGlow(false);
      }
    }).fail(function (xhr) {
      console.error("ACK pemanggilan gagal:", xhr.statusText);
      // Jangan mengambil nomor baru sebelum ACK benar-benar diterima server.
      scheduleAckRetry(token);
    });
  }

  function scheduleAckRetry(token) {
    if (token !== activeCallToken) return;
    clearAckRetry();
    ackRetryTimer = setTimeout(function () {
      ackCall(token);
    }, 2000);
  }

  function selesaiTTS(token) {
    if (token !== activeCallToken) return;
    ackCall(token);
  }

  function mulaiTTS(item, token) {
    const notif = document.getElementById("notif");
    const angka = parseInt(item.no_reg, 10);
    const teksNomor = angkaTerbilang(angka);
    const nama = perbaikiNamaNatural(normalisasiGelar(item.nm_pasien));
    const dokter = normalisasiGelar(item.nm_dokter);
    const poli = normalisasiGelar(item.nm_poli);
    const teks = `Nomor antrian ${teksNomor}, atas nama ${nama}, silakan menuju ${poli}, ${dokter}.`;

    const speak = function () {
      triggerGlow(true);
      try {
        responsiveVoice.speak(teks, "Indonesian Female", {
          rate: 1,
          pitch: 1,
          volume: 1,
          onend: function () { selesaiTTS(token); },
          onerror: function () { selesaiTTS(token); }
        });
      } catch (e) {
        console.error("TTS error:", e);
        selesaiTTS(token);
      }
    };

    if (notif && typeof notif.play === "function") {
      notif.currentTime = 0;
      const p = notif.play();
      if (p && typeof p.then === "function") {
        p.then(speak).catch(speak);
      } else {
        speak();
      }
    } else {
      speak();
    }
  }

  function panggilSuara() {
    // Tidak boleh ada request TTS baru selama panggilan sebelumnya belum ACK.
    if (callPlaying) return;

    $.getJSON("app/antrian.php?p=panggil", function (data) {
      if (!data || data.length === 0) return;

      const item = data[0];
      const token = item.call_token;
      if (!token) {
        console.error("Respons panggilan tidak memiliki call_token");
        return;
      }

      // API dapat mengembalikan call PLAYING yang sama pada setiap polling.
      if (token === lastCallToken) return;
      if (callPlaying) return;

      callPlaying = true;
      activeCallToken = token;
      lastCallToken = token;
      sessionStorage.setItem("antrianpoli_last_call_token", token);
      startHeartbeat(token);
      updateNomorDisplay(item);
      mulaiTTS(item, token);
    }).fail(function (xhr) {
      console.error("Gagal mengambil panggilan:", xhr.statusText);
    });
  }

  // Satu-satunya scheduler TTS di seluruh halaman.
  $(function () {
    setInterval(panggilSuara, 3000);
    panggilSuara();
  });
})();
