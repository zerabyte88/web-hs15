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

document.addEventListener("DOMContentLoaded", function () {
  const videos = document.querySelectorAll(".gallery video");

  // Pause video lain jika salah satu video diputar
  videos.forEach(function (video) {
    video.addEventListener("play", function () {
      videos.forEach(function (other) {
        if (other !== video && !other.paused) {
          other.pause();
        }
      });
    });
  });

  // Lazy loading untuk poster video menggunakan IntersectionObserver
  const lazyVideos = document.querySelectorAll("video[data-poster]");
  if ("IntersectionObserver" in window) {
    const posterObserver = new IntersectionObserver(function (entries, observer) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          const vid = entry.target;
          if (vid.dataset.poster) {
            vid.poster = vid.dataset.poster;
            vid.removeAttribute("data-poster");
          }
          observer.unobserve(vid);
        }
      });
    }, {
      rootMargin: "250px 0px"
    });

    lazyVideos.forEach(function (vid) {
      posterObserver.observe(vid);
    });
  } else {
    lazyVideos.forEach(function (vid) {
      if (vid.dataset.poster) {
        vid.poster = vid.dataset.poster;
        vid.removeAttribute("data-poster");
      }
    });
  }
});
