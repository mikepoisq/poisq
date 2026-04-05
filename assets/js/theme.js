/* ─── Theme initialisation (anti-flicker) ──────────────────
   This script is loaded synchronously in <head> (no defer/async).
   It sets data-theme on <html> BEFORE the first paint.
   ────────────────────────────────────────────────────────── */
(function () {
  var saved = localStorage.getItem('theme');
  var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  var isDark = saved === 'dark' || (!saved && prefersDark);
  document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
})();

/* Toggle between light / dark and persist the choice */
function toggleTheme() {
  var current = document.documentElement.getAttribute('data-theme');
  var next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  _updateThemeBtn();
}

/* Update the toggle button icon/title to match the active theme */
function _updateThemeBtn() {
  var btn = document.getElementById('themeToggle');
  if (!btn) return;
  var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  btn.title = isDark ? 'Светлая тема' : 'Тёмная тема';
  btn.setAttribute('aria-label', isDark ? 'Светлая тема' : 'Тёмная тема');
  var icon = document.getElementById('themeIcon');
  if (!icon) return;
  if (isDark) {
    /* Sun SVG paths */
    icon.innerHTML =
      '<circle cx="12" cy="12" r="5"/>' +
      '<line x1="12" y1="1" x2="12" y2="3"/>' +
      '<line x1="12" y1="21" x2="12" y2="23"/>' +
      '<line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>' +
      '<line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>' +
      '<line x1="1" y1="12" x2="3" y2="12"/>' +
      '<line x1="21" y1="12" x2="23" y2="12"/>' +
      '<line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>' +
      '<line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';
  } else {
    /* Moon SVG path */
    icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
  }
}

/* Run once the DOM is ready */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', _updateThemeBtn);
} else {
  _updateThemeBtn();
}
