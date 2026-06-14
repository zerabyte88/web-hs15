let currentIndex = 0;
let isBkgd1Active = true;
const slideInterval = 5000;
const imageUrls = [
  'http://localhost/webgallery/img/background-1.webp',
  'http://localhost/webgallery/img/background-2.webp',
  'http://localhost/webgallery/img/background-3.webp',
  'http://localhost/webgallery/img/background-4.webp',
  'http://localhost/webgallery/img/background-5.webp',
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

window.addEventListener('DOMContentLoaded', preloadImages);
