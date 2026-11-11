document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.getElementById('onboarding-sidebar');
  var menuButton = document.querySelector('[data-onboarding-menu]');

  if (sidebar && menuButton) {
    menuButton.addEventListener('click', function () {
      var isOpen = sidebar.classList.toggle('is-open');
      menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });
});