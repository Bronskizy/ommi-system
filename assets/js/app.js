document.addEventListener('DOMContentLoaded', function () {
  var nav = document.querySelector('[data-main-nav]');
  var toggle = document.querySelector('[data-nav-toggle]');
  var closeButton = document.querySelector('[data-nav-close]');
  var backdrop = document.querySelector('[data-nav-backdrop]');
  var header = document.querySelector('[data-site-header]');
  var lastFocusedElement = null;

  function closeNavigation() {
    if (!nav) return;
    nav.classList.remove('open');
    backdrop && backdrop.classList.remove('open');
    document.body.classList.remove('nav-open');
    toggle && toggle.setAttribute('aria-expanded', 'false');
    if (lastFocusedElement) lastFocusedElement.focus();
    lastFocusedElement = null;
  }

  function openNavigation() {
    if (!nav) return;
    lastFocusedElement = document.activeElement;
    nav.classList.add('open');
    backdrop && backdrop.classList.add('open');
    document.body.classList.add('nav-open');
    toggle && toggle.setAttribute('aria-expanded', 'true');
    closeButton && closeButton.focus();
  }

  if (nav) {
    nav.querySelectorAll('a').forEach(function (link) {
      var linkUrl = new URL(link.href, window.location.origin);
      if (linkUrl.pathname === window.location.pathname) {
        link.classList.add('active');
        link.setAttribute('aria-current', 'page');
      }
      link.addEventListener('click', closeNavigation);
    });
  }

  toggle && toggle.addEventListener('click', function () {
    nav && nav.classList.contains('open') ? closeNavigation() : openNavigation();
  });
  closeButton && closeButton.addEventListener('click', closeNavigation);
  backdrop && backdrop.addEventListener('click', closeNavigation);

  function updateHeaderElevation() {
    header && header.classList.toggle('is-scrolled', window.scrollY > 8);
  }
  updateHeaderElevation();
  window.addEventListener('scroll', updateHeaderElevation, { passive: true });

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-confirm]');
    if (trigger && !window.confirm(trigger.dataset.confirm)) event.preventDefault();
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && nav && nav.classList.contains('open')) closeNavigation();
  });
});
