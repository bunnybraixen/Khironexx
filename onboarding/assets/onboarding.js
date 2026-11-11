document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.getElementById('onboarding-sidebar');
  var menuButton = document.querySelector('[data-onboarding-menu]');

  if (sidebar && menuButton) {
    menuButton.addEventListener('click', function () {
      var isOpen = sidebar.classList.toggle('is-open');
      menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  document.querySelectorAll('[data-pending-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var notice = form.querySelector('[data-form-notice]');
      if (notice) {
        notice.hidden = false;
        notice.textContent = 'El practicante y el responsable se seleccionan desde las tablas existentes. El guardado estará disponible cuando se defina la tabla de procesos de onboarding.';
      }
    });
  });
});