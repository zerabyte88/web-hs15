// vidgallery.js - Interaksi Galeri Video

// Sembunyikan/tampilkan header saat scroll
let lastScrollTop = 0;
window.addEventListener("scroll", function () {
  const header = document.querySelector("header");
  if (!header) return;

  const scrollTop = window.scrollY || document.documentElement.scrollTop;

  if (scrollTop > lastScrollTop && scrollTop > 100) {
    header.classList.add("hide-header");
  } else if (scrollTop < lastScrollTop) {
    header.classList.remove("hide-header");
  }

  lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
}, { passive: true });

// Pause video lain jika salah satu video diputar
document.addEventListener("DOMContentLoaded", function () {
  const videos = document.querySelectorAll(".gallery video");

  videos.forEach(function (video) {
    video.addEventListener("play", function () {
      videos.forEach(function (other) {
        if (other !== video && !other.paused) {
          other.pause();
        }
      });
    });
  });
});
