(function () {
  'use strict';

  // Präfix-Feld nur bei "Bestehende Datenbank mitbenutzen" anzeigen
  var modes = document.querySelectorAll('input[name=db_mode]');
  var group = document.getElementById('prefix-group');
  var prefix = document.getElementById('db_prefix');
  var syncMode = function () {
    var existing = document.querySelector('input[name=db_mode]:checked');
    var on = !!existing && existing.value === 'existing';
    if (group) { group.hidden = !on; }
    if (prefix) { prefix.required = on; }
  };
  for (var i = 0; i < modes.length; i++) { modes[i].addEventListener('change', syncMode); }
  syncMode();
  var box = document.querySelector('input[name=pretty_urls]');
  var note = document.getElementById('rewrite-note');
  if (!box || !note || !window.fetch) { return; }

  // Prüft, ob der Webserver unbekannte Adressen an index.php weitergibt (mod_rewrite + .htaccess).
  fetch('rewrite-test', { cache: 'no-store', credentials: 'same-origin' })
    .then(function (r) { return r.text(); })
    .then(function (t) {
      if (t.trim() === 'REWRITE_OK') {
        box.checked = true;
        note.textContent = '✔ Kurze Adressen funktionieren auf diesem Webspace.';
      } else {
        box.checked = false;
        note.textContent = 'Kurze Adressen sind hier nicht verfügbar. Es werden Adressen mit index.php verwendet (funktioniert genauso).';
      }
    })
    .catch(function () {
      box.checked = false;
      note.textContent = 'Kurze Adressen konnten nicht geprüft werden. Es werden Adressen mit index.php verwendet (funktioniert genauso).';
    });
})();
