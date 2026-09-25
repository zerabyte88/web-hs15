/**
 * HS15 - Account Settings Interactivity
 * Handles profile photo preview, resizable white square crop box,
 * clean static image with crop overlay, and high-resolution export.
 */
document.addEventListener('DOMContentLoaded', () => {
  const profilePhotoInput = document.getElementById('profile_photo');
  const profileUploadForm = document.getElementById('profileUploadForm');
  const croppedImageData = document.getElementById('croppedImageData');
  const profilePreviewImg = document.querySelector('.profile-preview img');
  const cropPreviewControls = document.getElementById('cropPreviewControls');
  const btnAdjustAgain = document.getElementById('btnAdjustAgain');

  // Modal elements
  const cropModalOverlay = document.getElementById('cropModalOverlay');
  const cropModalCloseBtn = document.getElementById('cropModalCloseBtn');
  const cropModalCancelBtn = document.getElementById('cropModalCancelBtn');
  const cropModalApplyBtn = document.getElementById('cropModalApplyBtn');
  const cropStage = document.getElementById('cropStage');
  const cropDisplayCanvas = document.getElementById('cropDisplayCanvas');
  const cropBox = document.getElementById('cropBox');
  const cropHandles = document.querySelectorAll('.crop-handle');

  if (!profilePhotoInput || !cropModalOverlay || !cropDisplayCanvas || !cropBox) {
    return;
  }

  const ctx = cropDisplayCanvas.getContext('2d');
  let stageSize = 500;

  let currentImg = null;
  let imgScale = 1.0;
  let dw = 0; // Displayed image width
  let dh = 0; // Displayed image height
  let dx = 0; // Displayed image X offset on stage
  let dy = 0; // Displayed image Y offset on stage

  // Crop box dimensions and position
  let boxX = 0;
  let boxY = 0;
  let boxSize = 100;
  const MIN_SIZE = 40;

  // Drag & Resize state
  let isDraggingBox = false;
  let activeHandle = null;
  let startPointerX = 0;
  let startPointerY = 0;
  let startBoxX = 0;
  let startBoxY = 0;
  let startBoxSize = 0;

  // Touch pinch-to-zoom state
  let initialPinchDistance = 0;

  function updateBoxDOM() {
    cropBox.style.left = boxX + 'px';
    cropBox.style.top = boxY + 'px';
    cropBox.style.width = boxSize + 'px';
    cropBox.style.height = boxSize + 'px';
  }

  function openCropModal(imageSrc) {
    const img = new Image();
    img.onload = () => {
      currentImg = img;

      // Show modal first so cropStage has rendered layout dimensions
      cropModalOverlay.style.display = 'flex';
      cropModalOverlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';

      // Measure actual rendered size of stage
      const measuredWidth = cropStage.clientWidth || (window.innerWidth < 600 ? 300 : 500);
      stageSize = measuredWidth;

      // Set canvas dimensions
      cropDisplayCanvas.width = stageSize;
      cropDisplayCanvas.height = stageSize;

      // Fit image entirely inside stage
      imgScale = Math.min(stageSize / img.naturalWidth, stageSize / img.naturalHeight);
      dw = img.naturalWidth * imgScale;
      dh = img.naturalHeight * imgScale;
      dx = (stageSize - dw) / 2;
      dy = (stageSize - dh) / 2;

      // Draw image onto display canvas
      ctx.clearRect(0, 0, stageSize, stageSize);
      ctx.imageSmoothingEnabled = true;
      ctx.imageSmoothingQuality = 'high';
      ctx.drawImage(img, dx, dy, dw, dh);

      // Initialize crop box to max square covering image center
      boxSize = Math.min(dw, dh);
      boxX = dx + (dw - boxSize) / 2;
      boxY = dy + (dh - boxSize) / 2;
      updateBoxDOM();
    };
    img.src = imageSrc;
  }

  function closeCropModal() {
    cropModalOverlay.style.display = 'none';
    cropModalOverlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    isDraggingBox = false;
    activeHandle = null;
  }

  function resizeBoxCentered(delta) {
    let newSize = Math.max(MIN_SIZE, boxSize + delta);
    const maxPossible = Math.min(dw, dh);
    newSize = Math.min(maxPossible, newSize);

    const sizeDiff = newSize - boxSize;
    let newX = boxX - sizeDiff / 2;
    let newY = boxY - sizeDiff / 2;

    if (newX < dx) newX = dx;
    if (newY < dy) newY = dy;
    if (newX + newSize > dx + dw) newX = dx + dw - newSize;
    if (newY + newSize > dy + dh) newY = dy + dh - newSize;

    boxX = Math.max(dx, Math.min(dx + dw - newSize, newX));
    boxY = Math.max(dy, Math.min(dy + dh - newSize, newY));
    boxSize = newSize;

    updateBoxDOM();
  }

  // Handle file selection
  profilePhotoInput.addEventListener('change', (e) => {
    const file = e.target.files && e.target.files[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      alert('Pilih file gambar dengan format JPG, PNG, atau WEBP.');
      profilePhotoInput.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = (evt) => {
      openCropModal(evt.target.result);
    };
    reader.readAsDataURL(file);
  });

  // Re-adjust button if user already picked a photo
  if (btnAdjustAgain) {
    btnAdjustAgain.addEventListener('click', () => {
      if (currentImg) {
        cropModalOverlay.style.display = 'flex';
        cropModalOverlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
      } else {
        profilePhotoInput.click();
      }
    });
  }

  // Helper to normalize pointer coords taking CSS scale into account
  function getStagePointer(e) {
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    const rect = cropStage.getBoundingClientRect();
    const scaleFactor = stageSize / (rect.width || stageSize);
    return {
      x: (clientX - rect.left) * scaleFactor,
      y: (clientY - rect.top) * scaleFactor
    };
  }

  // Handle dragging corners to resize the crop box
  cropHandles.forEach((handle) => {
    const handleName = handle.dataset.handle;

    const onPointerDown = (e) => {
      e.stopPropagation();
      e.preventDefault();
      activeHandle = handleName;
      isDraggingBox = false;

      const p = getStagePointer(e);
      startPointerX = p.x;
      startPointerY = p.y;
      startBoxX = boxX;
      startBoxY = boxY;
      startBoxSize = boxSize;
    };

    handle.addEventListener('mousedown', onPointerDown);
    handle.addEventListener('touchstart', onPointerDown, { passive: false });
  });

  // Handle dragging the box to move it
  const onBoxPointerDown = (e) => {
    if (activeHandle) return;
    if (e.target.classList.contains('crop-handle')) return;

    e.preventDefault();
    isDraggingBox = true;

    const p = getStagePointer(e);
    startPointerX = p.x - boxX;
    startPointerY = p.y - boxY;
  };

  cropBox.addEventListener('mousedown', onBoxPointerDown);
  cropBox.addEventListener('touchstart', onBoxPointerDown, { passive: false });

  // Move & Resize event handlers on window
  function handlePointerMove(p) {
    if (isDraggingBox) {
      let newX = p.x - startPointerX;
      let newY = p.y - startPointerY;

      // Constrain inside displayed image
      newX = Math.max(dx, Math.min(dx + dw - boxSize, newX));
      newY = Math.max(dy, Math.min(dy + dh - boxSize, newY));

      boxX = newX;
      boxY = newY;
      updateBoxDOM();
    } else if (activeHandle) {
      const deltaX = p.x - startPointerX;
      const deltaY = p.y - startPointerY;

      if (activeHandle === 'se') {
        const delta = (deltaX + deltaY) / 2;
        const maxAllowed = Math.min(dx + dw - startBoxX, dy + dh - startBoxY);
        boxSize = Math.max(MIN_SIZE, Math.min(maxAllowed, startBoxSize + delta));
      } else if (activeHandle === 'ne') {
        const delta = (deltaX - deltaY) / 2;
        const maxAllowed = Math.min(dx + dw - startBoxX, startBoxY + startBoxSize - dy);
        const newSize = Math.max(MIN_SIZE, Math.min(maxAllowed, startBoxSize + delta));
        boxY = startBoxY + (startBoxSize - newSize);
        boxSize = newSize;
      } else if (activeHandle === 'sw') {
        const delta = (-deltaX + deltaY) / 2;
        const maxAllowed = Math.min(startBoxX + startBoxSize - dx, dy + dh - startBoxY);
        const newSize = Math.max(MIN_SIZE, Math.min(maxAllowed, startBoxSize + delta));
        boxX = startBoxX + (startBoxSize - newSize);
        boxSize = newSize;
      } else if (activeHandle === 'nw') {
        const delta = (-deltaX - deltaY) / 2;
        const maxAllowed = Math.min(startBoxX + startBoxSize - dx, startBoxY + startBoxSize - dy);
        const newSize = Math.max(MIN_SIZE, Math.min(maxAllowed, startBoxSize + delta));
        boxX = startBoxX + (startBoxSize - newSize);
        boxY = startBoxY + (startBoxSize - newSize);
        boxSize = newSize;
      }

      updateBoxDOM();
    }
  }

  window.addEventListener('mousemove', (e) => {
    if (!isDraggingBox && !activeHandle) return;
    const p = getStagePointer(e);
    handlePointerMove(p);
  });

  window.addEventListener('mouseup', () => {
    isDraggingBox = false;
    activeHandle = null;
  });

  window.addEventListener('touchmove', (e) => {
    if (e.touches.length === 1 && (isDraggingBox || activeHandle)) {
      e.preventDefault();
      const p = getStagePointer(e);
      handlePointerMove(p);
    } else if (e.touches.length === 2) {
      e.preventDefault();
      const currentDist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
      if (initialPinchDistance > 0) {
        const delta = (currentDist - initialPinchDistance) * 0.5;
        resizeBoxCentered(delta);
      }
      initialPinchDistance = currentDist;
    }
  }, { passive: false });

  window.addEventListener('touchend', () => {
    isDraggingBox = false;
    activeHandle = null;
    initialPinchDistance = 0;
  });

  // Mouse wheel on stage to adjust box size smoothly
  cropStage.addEventListener('wheel', (e) => {
    e.preventDefault();
    const step = e.deltaY < 0 ? 10 : -10;
    resizeBoxCentered(step);
  }, { passive: false });

  // Close & Cancel buttons
  cropModalCloseBtn.addEventListener('click', closeCropModal);
  cropModalCancelBtn.addEventListener('click', closeCropModal);

  cropModalOverlay.addEventListener('click', (e) => {
    if (e.target === cropModalOverlay) {
      closeCropModal();
    }
  });

  // Apply & Save
  cropModalApplyBtn.addEventListener('click', () => {
    if (!currentImg) return;

    // Calculate source image crop coordinates
    const scaleToNatural = currentImg.naturalWidth / dw;
    const srcX = Math.max(0, (boxX - dx) * scaleToNatural);
    const srcY = Math.max(0, (boxY - dy) * scaleToNatural);
    const srcSize = Math.min(
      boxSize * scaleToNatural,
      currentImg.naturalWidth - srcX,
      currentImg.naturalHeight - srcY
    );

    // Render high resolution export canvas (512x512)
    const EXPORT_SIZE = 512;
    const exportCanvas = document.createElement('canvas');
    exportCanvas.width = EXPORT_SIZE;
    exportCanvas.height = EXPORT_SIZE;
    const exportCtx = exportCanvas.getContext('2d');

    exportCtx.imageSmoothingEnabled = true;
    exportCtx.imageSmoothingQuality = 'high';

    exportCtx.drawImage(
      currentImg,
      srcX,
      srcY,
      srcSize,
      srcSize,
      0,
      0,
      EXPORT_SIZE,
      EXPORT_SIZE
    );

    const dataUrl = exportCanvas.toDataURL('image/jpeg', 0.92);
    croppedImageData.value = dataUrl;

    // Update avatar preview on page
    if (profilePreviewImg) {
      profilePreviewImg.src = dataUrl;
    }

    if (cropPreviewControls) {
      cropPreviewControls.style.display = 'flex';
    }

    // Indicate loading state & submit form
    cropModalApplyBtn.disabled = true;
    cropModalApplyBtn.innerHTML = '<ion-icon name="sync-outline" class="spinning-icon"></ion-icon> Menyimpan Foto...';

    closeCropModal();
    profileUploadForm.submit();
  });
});
