(function () {
  'use strict';

  // Feld "Neue Kennung" nur zeigen, wenn "Als neuen Hydranten anlegen" gewählt ist
  function sync(sel) {
    var cell = sel.closest('td');
    var box = cell ? cell.querySelector('.import-newkennung') : null;
    if (box) { box.hidden = sel.value !== 'new'; }
  }
  document.querySelectorAll('select.import-mode').forEach(function (sel) {
    sel.addEventListener('change', function () { sync(sel); });
    sync(sel);
  });

  // Alle Entscheidungen auf einmal setzen
  document.querySelectorAll('[data-set-all]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('select.import-mode').forEach(function (sel) {
        sel.value = btn.getAttribute('data-set-all');
        sync(sel);
      });
    });
  });

  // Bilder-Import: nächste Etappe automatisch starten
  var auto = document.querySelector('form[data-autosubmit]');
  if (auto) { setTimeout(function () { auto.submit(); }, 400); }
})();
