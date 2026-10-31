(function () {
  'use strict';
  // "Alle auswählen" in Tabellen mit Auswahlfeldern
  document.querySelectorAll('[data-check-all]').forEach(function (all) {
    all.addEventListener('change', function () {
      var scope = all.closest('table') || document;
      scope.querySelectorAll('[data-check]').forEach(function (c) { c.checked = all.checked; });
      all.dispatchEvent(new CustomEvent('bulk-update', { bubbles: true }));
    });
  });

  // Anzahl der angekreuzten Hydranten anzeigen (Hydrantenliste)
  var counter = document.getElementById('bulk-count');
  if (counter) {
    var update = function () {
      var n = document.querySelectorAll('[data-check]:checked').length;
      counter.textContent = n > 0 ? n + ' ausgewählt' : '';
    };
    document.addEventListener('change', update);
    document.addEventListener('bulk-update', update);
  }
})();
