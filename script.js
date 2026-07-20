const statusElement = document.querySelector('#status')

fetch('api/index.php')
  .then((response) => response.json())
  .then((payload) => {
    statusElement.textContent = payload.message ?? 'API dapat diakses.'
  })
  .catch(() => {
    statusElement.textContent = 'API belum berjalan atau belum dapat diakses.'
  })
