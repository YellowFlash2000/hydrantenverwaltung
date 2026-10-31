(function () {
  'use strict';
  // Der Link zu Overpass Turbo enthält die Abfrage für den eingetragenen Gemeindenamen.
  var input = document.getElementById('gemeinde');
  var link = document.getElementById('turbo-link');
  if (!input || !link) { return; }
  function update() {
    var place = input.value.replace(/[{}\r\n]/g, ' ').trim();
    var q = '[out:json][timeout:60];\n{{geocodeArea:' + place + '}}->.a;\n(\n  node["emergency"="fire_hydrant"](area.a);\n);\nout body;';
    link.href = place ? 'https://overpass-turbo.eu/?Q=' + encodeURIComponent(q) + '&R' : 'https://overpass-turbo.eu/';
  }
  input.addEventListener('input', update);
})();
