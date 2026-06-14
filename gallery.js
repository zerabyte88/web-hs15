// Header hide/show on scroll
var lastScrollTop = 0;

document.addEventListener("scroll", function () {
  var header = document.querySelector("header");
  if (!header) return;

  var scrollTop = window.scrollY || document.documentElement.scrollTop;

  // Scroll ke bawah - sembunyikan header
  if (scrollTop > lastScrollTop && scrollTop > 100) {
    header.classList.add("hide-header");
  } 
  // Scroll ke atas - tampilkan header
  else if (scrollTop < lastScrollTop) {
    header.classList.remove("hide-header");
  }
  
  lastScrollTop = scrollTop <= 0 ? 0 : scrollTop; // For Mobile or negative scrolling
});

document.addEventListener("DOMContentLoaded", () => {
  const lightbox    = document.getElementById("lightbox");
  const lightboxImg = document.getElementById("lightboxImg");
  const caption     = document.getElementById("caption");
  const closeBtn    = document.getElementById("closeBtn");

  // Klik thumbnail → buka lightbox
  document.querySelectorAll(".gallery img").forEach(img => {
    img.addEventListener("click", () => {
      lightbox.classList.add("open");          // gunakan class "open" konsisten
      lightboxImg.src = img.dataset.full;      // tampilkan gambar asli
      caption.textContent = img.alt || "";     // isi caption
    });
  });

  // Klik tombol close → tutup
  closeBtn.addEventListener("click", () => {
    lightbox.classList.remove("open");
    lightboxImg.src = ""; // kosongkan agar tidak ada gambar tersisa
  });

  // Klik backdrop (area luar gambar) → tutup
  lightbox.addEventListener("click", (e) => {
    if (e.target === lightbox) {
      lightbox.classList.remove("open");
      lightboxImg.src = "";
    }
  });

  // Tekan ESC → tutup
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      lightbox.classList.remove("open");
      lightboxImg.src = "";
    }
  });
});
