let currentIndex = 0;
let isBkgd1Active = true;
const slideInterval = 5000;
const imageUrls = [
  '../img/background-1.webp',
  '../img/background-2.webp',
  '../img/background-3.webp',
  '../img/background-4.webp',
  '../img/background-5.webp',
];

const preloadedImages = [];

function preloadImages() {
  let loaded = 0;
  imageUrls.forEach((url, index) => {
    const img = new Image();
    img.src = url;
    img.onload = () => {
      preloadedImages[index] = img;
      loaded++;
      if (loaded === imageUrls.length) {
        startSlideshow();
      }
    };
    img.onerror = () => {
      console.error(`Gagal memuat gambar: ${url}`);
      loaded++;
      if (loaded === imageUrls.length) {
        startSlideshow();
      }
    };
  });
}

function changeBackground() {
  const bkgd1 = document.getElementById('bkgd1');
  const bkgd2 = document.getElementById('bkgd2');
  const nextImage = preloadedImages[currentIndex];

  if (!bkgd1 || !bkgd2 || !nextImage) return;

  if (isBkgd1Active) {
    bkgd2.style.backgroundImage = `url('${nextImage.src}')`;
    bkgd2.style.opacity = 1;
    bkgd1.style.opacity = 0;
  } else {
    bkgd1.style.backgroundImage = `url('${nextImage.src}')`;
    bkgd1.style.opacity = 1;
    bkgd2.style.opacity = 0;
  }

  isBkgd1Active = !isBkgd1Active;
  currentIndex = (currentIndex + 1) % preloadedImages.length;

  setTimeout(changeBackground, slideInterval);
}

function startSlideshow() {
  const bkgd1 = document.getElementById('bkgd1');
  if (!bkgd1 || !preloadedImages[0]) return;

  bkgd1.style.backgroundImage = `url('${preloadedImages[0].src}')`;
  bkgd1.style.opacity = 1;
  currentIndex = 1;
  setTimeout(changeBackground, slideInterval);
}

// Animasi counter angka statistik
function animateValue(elem, start, end, duration) {
  if (!elem) return;
  if (start === end) {
    elem.textContent = end;
    return;
  }
  const range = end - start;
  let current = start;
  const increment = end > start ? 1 : -1;
  const stepTime = Math.abs(Math.floor(duration / (range || 1)));
  const timer = setInterval(function () {
    current += increment;
    elem.textContent = current;
    if (current === end) {
      clearInterval(timer);
    }
  }, Math.max(stepTime, 25));
}

// Mengambil data statistik dari backend (jumlah foto & video)
function loadGalleryStats() {
  const photoElem = document.getElementById('statPhotos');
  const videoElem = document.getElementById('statVideos');

  if (!photoElem && !videoElem) return;

  fetch('../php/stats_helper.php')
    .then((res) => {
      if (!res.ok) throw new Error('Gagal mengambil statistik');
      return res.json();
    })
    .then((data) => {
      if (photoElem && typeof data.photos === 'number') {
        animateValue(photoElem, 0, data.photos, 700);
      }
      if (videoElem && typeof data.videos === 'number') {
        animateValue(videoElem, 0, data.videos, 700);
      }
    })
    .catch((err) => {
      console.warn('Statistik galeri tidak dapat dimuat:', err);
      if (photoElem) photoElem.textContent = '0';
      if (videoElem) videoElem.textContent = '0';
    });
}

window.addEventListener('DOMContentLoaded', () => {
  preloadImages();
  loadGalleryStats();
});
