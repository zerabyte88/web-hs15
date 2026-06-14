// Header hide/show on scroll
var lastScrollTop = 0;
var headerHideTimeout;

document.addEventListener("scroll", function () {
  var header = document.querySelector("header");
  if (!header) return;

  var scrollTop = window.scrollY || document.documentElement.scrollTop;

  // Clear previous timeout
  clearTimeout(headerHideTimeout);

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

var vplay = {
  activeVideo: null,
  fullscreenContainer: null,

  init: function () {
    // Event delegation di level gallery (bukan per video)
    const gallery = document.getElementById("gallery");
    if (!gallery) return;

    // Setup IntersectionObserver untuk lazy load video
    vplay.setupLazyLoading();

    // Gunakan event delegation - cukup 1 listener di gallery
    gallery.addEventListener("click", function (e) {
      const video = e.target.closest(".vWrap video");
      if (video && !vplay.activeVideo) {
        vplay.toggle(video);
      }
    });

    // Tekan ESC untuk keluar fullscreen
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && vplay.activeVideo) {
        vplay.toggle(vplay.activeVideo);
      }
    });

    // Klik overlay untuk tutup fullscreen
    document.addEventListener("click", function (e) {
      if (e.target.id === "vplay-overlay" && vplay.activeVideo) {
        vplay.toggle(vplay.activeVideo);
      }
    });
  },

  setupLazyLoading: function () {
    const gallery = document.getElementById("gallery");
    const videos = gallery.querySelectorAll(".vWrap video");

    const observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            const video = entry.target;
            const src = video.closest(".vWrap").getAttribute("data-video");
            
            // Load video hanya jika belum di-load
            if (!video.src && src) {
              video.src = src;
              observer.unobserve(video);
            }
          }
        });
      },
      {
        rootMargin: "50px"
      }
    );

    videos.forEach(function (video) {
      observer.observe(video);
    });
  },

  toggle: function (video) {
    if (!video) return;

    const isGoingFullscreen = !video.classList.contains("full");

    if (isGoingFullscreen) {
      // Masuk fullscreen
      vplay.activeVideo = video;
      video.classList.add("full");
      video.controls = true;

      // Buat overlay
      const overlay = document.createElement("div");
      overlay.id = "vplay-overlay";
      document.body.appendChild(overlay);
      vplay.fullscreenContainer = overlay;

      // Buat close button
      const closeBtn = document.createElement("button");
      closeBtn.id = "vplay-close";
      closeBtn.textContent = "X";
      closeBtn.style.position = "fixed";
      closeBtn.style.top = "20px";
      closeBtn.style.right = "20px";
      closeBtn.style.zIndex = "10002";
      closeBtn.style.background = "rgba(0, 0, 0, 0.8)";
      closeBtn.style.color = "white";
      closeBtn.style.border = "none";
      closeBtn.style.fontSize = "32px";
      closeBtn.style.cursor = "pointer";
      closeBtn.style.width = "40px";
      closeBtn.style.height = "40px";
      closeBtn.style.borderRadius = "50%";
      closeBtn.style.transition = "background 0.3s ease";
      closeBtn.onclick = function() { vplay.toggle(video); };
      closeBtn.onmouseover = function() { this.style.background = "rgba(255,0,0,1)"; };
      closeBtn.onmouseout = function() { this.style.background = "rgba(255,0,0,0.8)"; };
      document.body.appendChild(closeBtn);

      // Disable scroll
      document.body.style.overflow = "hidden";
    } else {
      // Keluar fullscreen
      video.classList.remove("full");
      video.controls = true;
      vplay.activeVideo = null;

      // Hapus overlay
      if (vplay.fullscreenContainer) {
        vplay.fullscreenContainer.remove();
        vplay.fullscreenContainer = null;
      }

      // Hapus close button
      const closeBtn = document.getElementById("vplay-close");
      if (closeBtn) closeBtn.remove();

      // Enable scroll kembali
      document.body.style.overflow = "";

      // Pause video
      video.pause();
    }
  }
};

document.addEventListener("DOMContentLoaded", function () {
  vplay.init();
});
