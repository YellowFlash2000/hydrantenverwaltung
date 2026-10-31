(function () {
  'use strict';
  // Mängel und Fotos nur zeigen, wenn das Ergebnis "Mangel" gewählt ist
  var form = document.getElementById('revision-form');
  if (!form) { return; }
  var section = form.querySelector('[data-defect-section]');
  function sync() {
    var chosen = form.querySelector('input[name=ergebnis]:checked');
    var defect = chosen && chosen.value === 'mangel';
    if (section) { section.hidden = !defect; }
    if (!defect && section) {
      section.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = false; });
      var file = section.querySelector('input[type=file]');
      if (file) { file.value = ''; file.dispatchEvent(new Event('upload-reset')); }
    }
  }
  form.querySelectorAll('input[name=ergebnis]').forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
