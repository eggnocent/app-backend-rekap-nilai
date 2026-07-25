// Pengecek status API sederhana untuk halaman pengantar.
// Mencoba endpoint kesehatan; jika backend belum berjalan, tampilkan status offline
// tanpa mengganggu tampilan halaman.
const statusElement = document.querySelector('#status')

// Coba beberapa kemungkinan lokasi API (relatif saat halaman disajikan lewat
// server PHP, atau absolut saat backend berjalan di port 8000).
const endpoints = ['api/index.php', 'http://localhost:8000/api/health']

async function checkApi() {
  for (const url of endpoints) {
    try {
      const response = await fetch(url)
      if (!response.ok) continue
      const payload = await response.json().catch(() => ({}))
      statusElement.textContent = payload.message ?? 'API terhubung.'
      statusElement.dataset.state = 'online'
      return
    } catch {
      // coba endpoint berikutnya
    }
  }
  statusElement.textContent = 'API belum berjalan (jalankan backend untuk mengecek koneksi).'
  statusElement.dataset.state = 'offline'
}

void checkApi()
