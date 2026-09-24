// gallery.js - Interaksi Galeri Foto & Lightbox

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

// Lightbox dengan navigasi
document.addEventListener("DOMContentLoaded", function () {
  const lightbox = document.getElementById("lightbox");
  const lightboxImg = document.getElementById("lightboxImg");
  const caption = document.getElementById("caption");
  const counter = document.getElementById("lightboxCounter");
  const closeBtn = document.getElementById("closeBtn");
  const downloadBtn = document.getElementById("downloadBtn");
  const prevBtn = document.getElementById("prevBtn");
  const nextBtn = document.getElementById("nextBtn");
  const backdrop = document.querySelector(".lightbox__backdrop");

  if (!lightbox || !lightboxImg) return;

  const figures = Array.from(document.querySelectorAll(".gallery figure"));
  if (figures.length === 0) return;

  let currentIndex = 0;

  function showImage(index) {
    if (index < 0) {
      index = figures.length - 1;
    } else if (index >= figures.length) {
      index = 0;
    }

    currentIndex = index;
    const figure = figures[currentIndex];
    const img = figure.querySelector("img");
    const fullSrc = figure.getAttribute("data-full") || (img ? img.dataset.full : "");
    const imgCaption = figure.getAttribute("data-caption") || (img ? img.alt : "");

    // Set langsung tanpa menunggu agar foto langsung tampil
    lightboxImg.src = fullSrc;
    lightboxImg.alt = imgCaption;
    if (caption) caption.textContent = imgCaption;
    if (counter) counter.textContent = (currentIndex + 1) + " / " + figures.length;

    if (downloadBtn) {
      downloadBtn.href = fullSrc;
      const filename = decodeURIComponent(fullSrc.split("/").pop());
      downloadBtn.setAttribute("download", filename);
    }
  }

  function openLightbox(index) {
    lightbox.classList.add("open");
    document.body.style.overflow = "hidden";
    showImage(index);
  }

  function closeLightbox() {
    lightbox.classList.remove("open");
    document.body.style.overflow = "";
    lightboxImg.src = "";
    if (downloadBtn) downloadBtn.href = "";
  }

  // Klik figure atau gambar untuk membuka lightbox
  figures.forEach(function (figure, idx) {
    figure.addEventListener("click", function (e) {
      if (e.target.closest(".gallery-download-btn, a")) return;
      openLightbox(idx);
    });
  });

  // Tombol navigasi
  if (prevBtn) {
    prevBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      showImage(currentIndex - 1);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      showImage(currentIndex + 1);
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      closeLightbox();
    });
  }

  if (backdrop) {
    backdrop.addEventListener("click", function () {
      closeLightbox();
    });
  }

  // Navigasi keyboard (Esc, Panah Kiri, Panah Kanan)
  document.addEventListener("keydown", function (e) {
    if (!lightbox.classList.contains("open")) return;

    if (e.key === "Escape") {
      closeLightbox();
    } else if (e.key === "ArrowLeft") {
      showImage(currentIndex - 1);
    } else if (e.key === "ArrowRight") {
      showImage(currentIndex + 1);
    }
  });
});
