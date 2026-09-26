// vidgallery.js - Interaksi Galeri Video + Client-side Thumbnail Generation

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

  /**
   * Generate video thumbnail client-side menggunakan canvas
   * Menangkap frame pada detik ke-2 dari video, lalu mengirim ke server untuk di-cache
   */
  function captureVideoFrame(videoEl, filename) {
    return new Promise(function (resolve, reject) {
      // Buat elemen video tersembunyi untuk capture
      const tempVideo = document.createElement("video");
      tempVideo.crossOrigin = "anonymous";
      tempVideo.preload = "metadata";
      tempVideo.muted = true;
      tempVideo.playsInline = true;

      // Ambil src dari source element atau src attribute
      const sourceEl = videoEl.querySelector("source");
      const videoSrc = sourceEl ? sourceEl.src : videoEl.src;
      if (!videoSrc) {
        reject("No video source");
        return;
      }

      tempVideo.src = videoSrc;

      tempVideo.addEventListener("loadeddata", function () {
        // Seek ke detik ke-1 (atau awal jika video terlalu pendek)
        var seekTime = Math.min(1, tempVideo.duration * 0.1);
        tempVideo.currentTime = seekTime;
      });

      tempVideo.addEventListener("seeked", function () {
        try {
          var canvas = document.createElement("canvas");
          canvas.width = tempVideo.videoWidth;
          canvas.height = tempVideo.videoHeight;
          var ctx = canvas.getContext("2d");
          ctx.drawImage(tempVideo, 0, 0, canvas.width, canvas.height);
          var dataUrl = canvas.toDataURL("image/jpeg", 0.75);

          // Kirim ke server untuk di-cache
          var formData = new FormData();
          formData.append("filename", filename);
          formData.append("image_data", dataUrl);

          fetch("video_thumb.php", {
            method: "POST",
            body: formData
          })
          .then(function (resp) { return resp.json(); })
          .then(function (data) {
            if (data.success && data.poster_url) {
              resolve(data.poster_url);
            } else {
              resolve(dataUrl); // Gunakan data URL langsung sebagai fallback
            }
          })
          .catch(function () {
            resolve(dataUrl); // Gagal upload, pakai data URL langsung
          });

          // Bersihkan temp video
          tempVideo.src = "";
          tempVideo.load();
        } catch (e) {
          reject(e);
        }
      });

      tempVideo.addEventListener("error", function () {
        reject("Video load error");
      });

      // Timeout 15 detik
      setTimeout(function () {
        reject("Timeout");
      }, 15000);
    });
  }

  // IntersectionObserver untuk lazy loading poster video
  var videoItems = document.querySelectorAll("video[data-poster]");

  if ("IntersectionObserver" in window) {
    var posterObserver = new IntersectionObserver(function (entries, observer) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var vid = entry.target;
          var posterUrl = vid.dataset.poster;

          // Cek apakah poster URL adalah fallback logo (berarti belum ada thumbnail)
          if (posterUrl && !posterUrl.includes("logo.")) {
            // Thumbnail sudah ada di server, langsung pakai
            vid.poster = posterUrl;
          } else if (vid.dataset.filename) {
            // Belum ada thumbnail — generate client-side
            captureVideoFrame(vid, vid.dataset.filename)
              .then(function (url) {
                vid.poster = url;
              })
              .catch(function () {
                // Fallback: load metadata untuk menampilkan frame pertama
                vid.preload = "metadata";
              });
          }
          vid.removeAttribute("data-poster");
          observer.unobserve(vid);
        }
      });
    }, {
      rootMargin: "300px 0px"
    });

    videoItems.forEach(function (vid) {
      posterObserver.observe(vid);
    });
  } else {
    // Fallback tanpa IntersectionObserver
    videoItems.forEach(function (vid) {
      var posterUrl = vid.dataset.poster;
      if (posterUrl && !posterUrl.includes("logo.")) {
        vid.poster = posterUrl;
      } else if (vid.dataset.filename) {
        captureVideoFrame(vid, vid.dataset.filename)
          .then(function (url) {
            vid.poster = url;
          })
          .catch(function () {});
      }
      vid.removeAttribute("data-poster");
    });
  }
});
