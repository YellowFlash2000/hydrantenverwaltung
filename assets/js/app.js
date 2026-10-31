(function () {
  'use strict';

  // Menue auf kleinen Bildschirmen ein-/ausklappen
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('main-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Bestaetigungsdialog fuer Formulare mit data-confirm
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (form && form.dataset && form.dataset.confirm) {
      if (!window.confirm(form.dataset.confirm)) {
        ev.preventDefault();
      }
    }
  });

  // Text in die Zwischenablage kopieren (Button mit data-copy="#selector")
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-copy]') : null;
    if (!btn) { return; }
    var src = document.querySelector(btn.dataset.copy);
    if (src && navigator.clipboard) {
      navigator.clipboard.writeText(src.value || src.textContent || '');
      var old = btn.textContent;
      btn.textContent = 'Kopiert';
      setTimeout(function () { btn.textContent = old; }, 1500);
    }
  });

  // Live-Vorschau der Farben (Einstellungen > Darstellung)
  var preview = document.getElementById('theme-preview');
  if (preview) {
    var luminance = function (hex) {
      var v = [1, 3, 5].map(function (i) {
        var c = parseInt(hex.substr(i, 2), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
      });
      return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2];
    };
    var textOn = function (hex) { return luminance(hex) > 0.4 ? '#111111' : '#ffffff'; };
    var apply = function () {
      var p = document.getElementById('color_primary').value;
      var h = document.getElementById('color_header').value;
      var b = document.getElementById('color_bg').value;
      preview.style.setProperty('--c-primary', p);
      preview.style.setProperty('--c-primary-dark', p);
      preview.style.setProperty('--c-primary-text', textOn(p));
      preview.style.setProperty('--c-header', h);
      preview.style.setProperty('--c-header-text', textOn(h));
      preview.style.setProperty('--c-bg', b);
      document.getElementById('pv-name').textContent = document.getElementById('app_name').value;
    };
    ['color_primary', 'color_header', 'color_bg', 'app_name'].forEach(function (id) {
      document.getElementById(id).addEventListener('input', apply);
    });
    apply();
  }
})();
