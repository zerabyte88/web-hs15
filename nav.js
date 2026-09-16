document.addEventListener('DOMContentLoaded', function () {
  const profileMenus = document.querySelectorAll('.profile-menu');

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
});
