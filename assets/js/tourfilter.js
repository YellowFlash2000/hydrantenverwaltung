(function () {
  'use strict';
  // Tour-Link: Auswahl "Alle" / "Nur offene" je Tour im Gerät merken
  var box = document.querySelector('[data-tour-filter]');
  if (!box) { return; }
  var key = 'hyd_tourfilter_' + box.getAttribute('data-tour-filter');
  var store = {
    get: function () { try { return window.localStorage.getItem(key); } catch (e) { return null; } },
    set: function (v) { try { window.localStorage.setItem(key, v); } catch (e) { /* Speicher nicht verfügbar */ } }
  };
  var params = new URLSearchParams(window.location.search);
  if (!params.has('nur') && !params.has('teilnehmer') && store.get() === 'offen') {
    params.set('nur', 'offen');
    window.location.replace(window.location.pathname + '?' + params.toString() + window.location.hash);
    return;
  }
  if (params.get('nur') === 'offen' || params.get('nur') === 'alle') { store.set(params.get('nur')); }
  box.addEventListener('click', function (ev) {
    var a = ev.target.closest ? ev.target.closest('a[data-filter]') : null;
    if (a) { store.set(a.getAttribute('data-filter')); }
  });
})();
