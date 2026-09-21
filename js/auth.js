// auth.js - Helper untuk interaksi form autentikasi (toggle show/hide password)
document.addEventListener('DOMContentLoaded', function () {
  const toggleButtons = document.querySelectorAll('.toggle-password');

  toggleButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      const input = this.parentElement.querySelector('input');
      const icon = this.querySelector('ion-icon');

      if (!input) return;

      if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
          icon.setAttribute('name', 'eye-off-outline');
        }
      } else {
        input.type = 'password';
        if (icon) {
          icon.setAttribute('name', 'eye-outline');
        }
      }
    });
  });
});

