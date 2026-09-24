document.addEventListener('DOMContentLoaded', function () {
  const profileMenus = document.querySelectorAll('.profile-menu');
  const profileAvatars = document.querySelectorAll('.profile-avatar');

  const profileEndpoint = window.location.pathname.includes('/php/') ? 'profile_image.php' : 'php/profile_image.php';
  fetch(profileEndpoint, { credentials: 'same-origin' })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      if (!data.image) return;
      profileAvatars.forEach(function (avatar) {
        avatar.src = data.image;
      });
    })
    .catch(function () {
      // Keep the default logo if the profile request is unavailable.
    });

  profileMenus.forEach(function (profileMenu) {
    const button = profileMenu.querySelector('.profile-button');
    const dropdown = profileMenu.querySelector('.profile-dropdown');

    if (!button || !dropdown) return;

    function closeMenu() {
      dropdown.classList.remove('is-open');
      button.setAttribute('aria-expanded', 'false');
    }

    button.addEventListener('click', function (event) {
      event.stopPropagation();
      const isOpen = button.getAttribute('aria-expanded') === 'true';
      dropdown.classList.toggle('is-open', !isOpen);
      button.setAttribute('aria-expanded', String(!isOpen));
    });

    document.addEventListener('click', function (event) {
      if (!profileMenu.contains(event.target)) closeMenu();
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeMenu();
    });
  });

  // Mobile 3-dots menu handling
  const mobileMenuBtns = document.querySelectorAll('.mobile-menu-btn');
  mobileMenuBtns.forEach(function (btn) {
    const wrap = btn.closest('.mobile-menu-wrap');
    if (!wrap) return;
    const dropdown = wrap.querySelector('.mobile-dropdown');
    if (!dropdown) return;

    function closeMobileMenu() {
      dropdown.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
    }

    btn.addEventListener('click', function (event) {
      event.stopPropagation();
      const isOpen = dropdown.classList.contains('is-open');
      document.querySelectorAll('.mobile-dropdown.is-open').forEach(function (d) {
        if (d !== dropdown) d.classList.remove('is-open');
      });
      dropdown.classList.toggle('is-open', !isOpen);
      btn.setAttribute('aria-expanded', String(!isOpen));
    });

    document.addEventListener('click', function (event) {
      if (!wrap.contains(event.target)) closeMobileMenu();
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeMobileMenu();
    });
  });
});

