(function () {
  'use strict';
  if (!window.L) { return; }

  var TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  var ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>-Mitwirkende';
  var DEFAULT_CENTER = [51.16, 10.45];
  var primary = (getComputedStyle(document.documentElement).getPropertyValue('--c-primary') || '#c8102e').trim();

  function createMap(el, wheel) {
    var map = L.map(el, { scrollWheelZoom: wheel });
    L.tileLayer(TILE_URL, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(map);
    if (!wheel) {
      // Mausrad zoomt die Karte erst nach einem Klick hinein, damit das Scrollen der Seite nicht hängen bleibt.
      map.on('focus', function () { map.scrollWheelZoom.enable(); });
      map.on('blur', function () { map.scrollWheelZoom.disable(); });
    }
    return map;
  }

  var SVG_NS = 'http://www.w3.org/2000/svg';
  var GREY = '#8a8f94';

  // Formen im 20x20-Raster, muss mit src/lib/HydrantType.php übereinstimmen
  var POLYS = {
    dreieck_runter: '2,3 18,3 10,18',
    dreieck_hoch: '10,2 18,17 2,17',
    raute: '10,1.5 18.5,10 10,18.5 1.5,10',
    kreuz: '7,2 13,2 13,7 18,7 18,13 13,13 13,18 7,18 7,13 2,13 2,7 7,7',
    stern: '10,1.5 12.35,7.4 18.7,7.8 13.8,11.9 15.4,18 10,14.6 4.6,18 6.2,11.9 1.3,7.8 7.65,7.4'
  };

  function symbol(sym, color, opacity, size) {
    var svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', '0 0 20 20');
    svg.setAttribute('width', String(size));
    svg.setAttribute('height', String(size));
    svg.setAttribute('aria-hidden', 'true');
    var shape;
    if (POLYS[sym]) {
      shape = document.createElementNS(SVG_NS, 'polygon');
      shape.setAttribute('points', POLYS[sym]);
    } else if (sym === 'quadrat') {
      shape = document.createElementNS(SVG_NS, 'rect');
      shape.setAttribute('x', '3'); shape.setAttribute('y', '3');
      shape.setAttribute('width', '14'); shape.setAttribute('height', '14');
    } else {
      shape = document.createElementNS(SVG_NS, 'circle');
      shape.setAttribute('cx', '10'); shape.setAttribute('cy', '10'); shape.setAttribute('r', '7');
    }
    shape.setAttribute('fill', color);
    shape.setAttribute('fill-opacity', String(opacity));
    shape.setAttribute('stroke', '#ffffff');
    shape.setAttribute('stroke-width', '1.8');
    shape.setAttribute('stroke-linejoin', 'round');
    svg.appendChild(shape);
    return svg;
  }

  // opts: symbol, active (false = grau), pale (blass im Hintergrund), size, title
  function dot(latlng, map, opts) {
    opts = opts || {};
    var size = opts.size || 22;
    var icon = L.divIcon({
      className: 'hy-marker',
      html: symbol(opts.symbol, opts.color || (opts.active === false ? GREY : primary), opts.pale ? 0.5 : 0.95, size),
      iconSize: [size, size],
      iconAnchor: [size / 2, size / 2],
      popupAnchor: [0, -size / 2]
    });
    return L.marker(latlng, { icon: icon, title: opts.title || '', keyboard: true, zIndexOffset: opts.top ? 1000 : 0 }).addTo(map);
  }

  // Popup mit Kennung, Typ und (optional) Link zur Detailseite
  function popupBox(p, withLink) {
    var box = document.createElement('div');
    var name = document.createElement('strong');
    name.textContent = p.name;
    box.appendChild(name);
    if (p.info) {
      var info = document.createElement('div');
      info.textContent = p.info;
      box.appendChild(info);
    }
    if (p.status) {
      var st = document.createElement('div');
      st.textContent = p.status;
      box.appendChild(st);
    }
    if (withLink) {
      var link = document.createElement('a');
      link.href = p.url;
      link.textContent = p.linkText || 'Details ansehen';
      box.appendChild(link);
    }
    return box;
  }

  // Alle anderen aktiven Hydranten blass im Hintergrund
  function addOthers(el, map, withLink) {
    var others = [];
    try { others = JSON.parse(el.getAttribute('data-others') || '[]'); } catch (e) { others = []; }
    others.forEach(function (p) {
      dot([p.lat, p.lon], map, { symbol: p.symbol, color: p.color, pale: true, size: 20, title: p.name }).bindPopup(popupBox(p, withLink));
    });
  }

  // ---- Übersicht -------------------------------------------------------------
  function overview(el) {
    var points = [];
    try { points = JSON.parse(el.getAttribute('data-points') || '[]'); } catch (e) { points = []; }
    var map = createMap(el, true);
    var markers = {};
    var bounds = [];
    var defCenter = null;
    try { defCenter = JSON.parse(el.getAttribute('data-center') || 'null'); } catch (e) { defCenter = null; }
    var defZoom = parseInt(el.getAttribute('data-zoom') || '', 10);
    if (!isFinite(defZoom)) { defZoom = 16; }

    points.forEach(function (p) {
      var m = dot([p.lat, p.lon], map, { symbol: p.symbol, active: p.active, color: p.color, size: 22, title: p.name });
      m.bindPopup(popupBox(p, true));
      markers[p.id] = m;
      bounds.push([p.lat, p.lon]);
    });

    function refit() {
      map.invalidateSize();
      if (bounds.length > 1) { map.fitBounds(bounds, { padding: [30, 30], maxZoom: 18 }); }
      else if (bounds.length === 1) { map.setView(bounds[0], 17); }
      else if (defCenter) { map.setView(defCenter, defZoom); }
      else { map.setView(DEFAULT_CENTER, 6); }
    }
    refit();

    var split = document.getElementById('split');
    if (split) {
      var setView = function (view) {
        split.setAttribute('data-view', view);
        split.querySelectorAll('.split-tabs button').forEach(function (b) {
          b.setAttribute('aria-pressed', b.getAttribute('data-view') === view ? 'true' : 'false');
        });
        if (view === 'map') { setTimeout(refit, 30); }
      };
      split.querySelectorAll('.split-tabs button').forEach(function (b) {
        b.addEventListener('click', function () { setView(b.getAttribute('data-view')); });
      });

      document.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('[data-focus]') : null;
        if (!btn) { return; }
        var m = markers[btn.getAttribute('data-focus')];
        if (!m) { return; }
        if (window.matchMedia('(max-width: 900px)').matches) { setView('map'); }
        setTimeout(function () {
          map.invalidateSize();
          map.setView(m.getLatLng(), 18);
          m.openPopup();
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 60);
      });
    }
  }

  // ---- Detailseite -----------------------------------------------------------
  function detail(el) {
    var lat = parseFloat(el.getAttribute('data-lat'));
    var lon = parseFloat(el.getAttribute('data-lon'));
    if (!isFinite(lat) || !isFinite(lon)) { return; }
    var map = createMap(el, false);
    map.setView([lat, lon], 18);
    addOthers(el, map, true);
    dot([lat, lon], map, { symbol: el.getAttribute('data-symbol') || '', color: el.getAttribute('data-color') || '', size: 32, top: true, title: 'Dieser Hydrant' });
  }

  // ---- Standortwahl im Formular -----------------------------------------------
  function picker(el) {
    var latIn = document.querySelector(el.getAttribute('data-lat-input'));
    var lonIn = document.querySelector(el.getAttribute('data-lon-input'));
    var status = document.getElementById('locate-status');
    var statusDefault = status ? status.textContent : '';
    if (!latIn || !lonIn) { return; }
    var map = createMap(el, false);
    var marker = null;
    addOthers(el, map, false);

    function num(v) {
      var n = parseFloat(String(v).replace(',', '.'));
      return isFinite(n) ? n : null;
    }
    function fmt(n) { return n.toFixed(7).replace(/0+$/, '').replace(/\.$/, ''); }
    function write(lat, lon) { latIn.value = fmt(lat); lonIn.value = fmt(lon); }
    var zoomIn = el.getAttribute('data-zoom-input') ? document.querySelector(el.getAttribute('data-zoom-input')) : null;
    var startZoom = parseInt(el.getAttribute('data-zoom') || '', 10);
    if (!isFinite(startZoom)) { startZoom = 16; }
    function place(lat, lon, pan) {
      if (!marker) {
        marker = L.marker([lat, lon], { draggable: true }).addTo(map);
        marker.on('dragend', function () { var p = marker.getLatLng(); write(p.lat, p.lng); });
      } else {
        marker.setLatLng([lat, lon]);
      }
      if (pan) { map.setView([lat, lon], zoomIn ? map.getZoom() : Math.max(map.getZoom(), 18)); }
    }
    function fromInputs() {
      var la = num(latIn.value), lo = num(lonIn.value);
      if (la !== null && lo !== null && Math.abs(la) <= 90 && Math.abs(lo) <= 180) { place(la, lo, true); }
    }

    map.on('click', function (ev) { place(ev.latlng.lat, ev.latlng.lng, false); write(ev.latlng.lat, ev.latlng.lng); });
    latIn.addEventListener('change', fromInputs);
    lonIn.addEventListener('change', fromInputs);

    var la0 = num(latIn.value), lo0 = num(lonIn.value);
    var center = null;
    try { center = JSON.parse(el.getAttribute('data-center') || 'null'); } catch (e) { center = null; }
    if (zoomIn) {
      // Einstellungsseite: Die Zoomstufe der Karte und das Zahlenfeld halten sich gegenseitig aktuell.
      var z0 = parseInt(zoomIn.value, 10);
      if (isFinite(z0)) { startZoom = z0; }
      map.on('zoomend', function () { zoomIn.value = String(map.getZoom()); });
      zoomIn.addEventListener('change', function () {
        var z = parseInt(zoomIn.value, 10);
        if (isFinite(z) && z >= 5 && z <= 19) { map.setZoom(z); }
      });
    }
    if (la0 !== null && lo0 !== null) { place(la0, lo0, false); map.setView([la0, lo0], zoomIn ? startZoom : 18); }
    else if (center) { map.setView(center, startZoom); }
    else { map.setView(DEFAULT_CENTER, 6); }

    var locate = document.getElementById('btn-locate');
    if (locate) {
      locate.addEventListener('click', function () {
        if (!navigator.geolocation) { if (status) { status.textContent = 'Dieses Gerät unterstützt keine Standortbestimmung.'; } return; }
        if (status) { status.textContent = 'Standort wird ermittelt …'; }
        navigator.geolocation.getCurrentPosition(function (pos) {
          place(pos.coords.latitude, pos.coords.longitude, true);
          write(pos.coords.latitude, pos.coords.longitude);
          if (status) { status.textContent = 'Standort übernommen (Genauigkeit etwa ' + Math.round(pos.coords.accuracy) + ' m). Bei Bedarf den Marker verschieben.'; }
        }, function () {
          if (status) { status.textContent = 'Der Standort konnte nicht ermittelt werden. Bitte die Standortfreigabe im Browser prüfen oder auf die Karte tippen.'; }
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
      });
    }
    var clear = document.getElementById('btn-clear');
    if (clear) {
      clear.addEventListener('click', function () {
        latIn.value = ''; lonIn.value = '';
        if (marker) { map.removeLayer(marker); marker = null; }
        if (status) { status.textContent = statusDefault; }
      });
    }
  }

  // Einstellungen: Wer nur gezoomt, aber nicht in die Karte geklickt hat, speichert sonst eine leere Kartenmitte.
  var settingsForm = document.querySelector('form[data-confirm-empty-map]');
  if (settingsForm) {
    settingsForm.addEventListener('submit', function (ev) {
      var lat = document.querySelector(settingsForm.getAttribute('data-lat-input'));
      var lon = document.querySelector(settingsForm.getAttribute('data-lon-input'));
      if (lat && lon && lat.value.trim() === '' && lon.value.trim() === '') {
        if (!window.confirm('Es ist noch keine Kartenmitte gesetzt. Zum Setzen einmal in die Karte klicken (Zoomen genügt nicht).\n\nTrotzdem ohne Kartenmitte speichern?')) {
          ev.preventDefault();
        }
      }
    });
  }

  document.querySelectorAll('.js-map').forEach(function (el) {
    var mode = el.getAttribute('data-mode');
    if (mode === 'overview') { overview(el); }
    else if (mode === 'detail') { detail(el); }
    else if (mode === 'picker') { picker(el); }
  });
})();
