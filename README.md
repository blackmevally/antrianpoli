# Antrian-Poli

Aplikasi display Antrian Poli dengan text-to-speech dan filter poli/dokter.

## Database

Pastikan tabel `antripoli` menggunakan status berikut:

- `0` = belum diproses
- `1` = menunggu dipanggil
- `2` = sedang dipanggil
- `3` = selesai

Jika diperlukan, jalankan perubahan enum tersebut pada database Khanza yang memang sudah digunakan. Tidak ada tabel tambahan yang diperlukan untuk call queue aplikasi.

## Call Queue

Pemanggilan suara menggunakan **file-based atomic queue**, sehingga tidak perlu menambah atau mengubah tabel database SIMRS Khanza.

State sementara aplikasi disimpan di:

```text
runtime/call_state.json
runtime/call.lock
```

Direktori `runtime` dikecualikan dari commit sehingga state runtime tidak masuk Git.

## Alur pemanggilan

`panggil` bersifat **idempotent**:

1. Request pertama mengambil nomor `status=1` dan membuat state `playing` di file aplikasi.
2. Nomor tersebut diubah menjadi `status=2`.
3. Request berikutnya selama TTS masih berjalan mengembalikan **call yang sama**, bukan nomor berikutnya.
4. Browser mengirim heartbeat selama call aktif.
5. Setelah TTS selesai, browser mengirim `ack`.
6. Baru setelah ACK, `antripoli.status` berubah dari `2` menjadi `3`.
7. Setelah itu nomor berikutnya dapat dipanggil.

Jika display mati/crash dan heartbeat berhenti lebih dari 30 detik, state lama dipulihkan agar antrian tidak terkunci selamanya.

Dengan demikian skenario A dipanggil lalu request B masuk bersamaan tidak lagi membuat A langsung dianggap selesai dan digantikan oleh B.
