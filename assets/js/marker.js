(function () {
  'use strict';
  // Gelbe Oval-Markierung auf Hydrantenbildern.
  // Wert: "x,y,rx,ry" als Anteile der Bildgröße (Mittelpunkt und Radien, 0 bis 1). Das Bild selbst bleibt unverändert.
  var SVGNS = 'http://www.w3.org/2000/svg';
  var MIN_R = 0.02;
  var MAX_R = 0.5;

  function parse(value) {
    if (typeof value !== 'string' || value === '') { return null; }
    var p = value.split(',').map(Number);
    if (p.length !== 4 || p.some(function (n) { return !isFinite(n); })) { return null; }
    return { x: p[0], y: p[1], rx: p[2], ry: p[3] };
  }

  function format(m) {
    function f(n) { return String(Math.round(n * 100000) / 100000); }
    return f(m.x) + ',' + f(m.y) + ',' + f(m.rx) + ',' + f(m.ry);
  }

  function clamp(n, lo, hi) { return Math.min(hi, Math.max(lo, n)); }

  // Zwei Ellipsen (dunkle Kante, gelber Ring) in ein SVG mit viewBox 0 0 100 100 zeichnen.
  function ensureEllipses(svg) {
    var els = svg.querySelectorAll('ellipse');
    if (els.length === 2) { return els; }
    svg.textContent = '';
    ['marker-outline', 'marker-ring'].forEach(function (cls) {
      var e = document.createElementNS(SVGNS, 'ellipse');
      e.setAttribute('class', cls);
      svg.appendChild(e);
    });
    return svg.querySelectorAll('ellipse');
  }

  function draw(svg, value) {
    if (!svg) { return; }
    var m = typeof value === 'string' ? parse(value) : value;
    if (!m) { svg.classList.add('is-off'); return; }
    ensureEllipses(svg).forEach(function (e) {
      e.setAttribute('cx', String(m.x * 100));
      e.setAttribute('cy', String(m.y * 100));
      e.setAttribute('rx', String(m.rx * 100));
      e.setAttribute('ry', String(m.ry * 100));
    });
    svg.classList.remove('is-off');
  }

  var dlg = null;
  var state = null;

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text) { n.textContent = text; }
    return n;
  }

  function build() {
    dlg = el('dialog', 'marker-dialog');
    dlg.setAttribute('aria-label', 'Hydrant auf dem Bild markieren');
    dlg.appendChild(el('p', 'hint', 'Tippen oder ziehen Sie auf dem Bild, um das gelbe Oval auf den Hydranten zu setzen. Die Größe stellen Sie mit den Reglern ein.'));

    var stage = el('div', 'marker-stage');
    var img = el('img');
    img.alt = '';
    var svg = document.createElementNS(SVGNS, 'svg');
    svg.setAttribute('class', 'marker-overlay is-off');
    svg.setAttribute('viewBox', '0 0 100 100');
    svg.setAttribute('preserveAspectRatio', 'none');
    svg.setAttribute('aria-hidden', 'true');
    stage.appendChild(img);
    stage.appendChild(svg);
    dlg.appendChild(stage);

    var sizes = el('div', 'marker-sizes');
    var wl = el('label', null, 'Breite ');
    var w = el('input'); w.type = 'range'; w.min = '4'; w.max = '100'; w.step = '1';
    wl.appendChild(w);
    var hl = el('label', null, 'Höhe ');
    var h = el('input'); h.type = 'range'; h.min = '4'; h.max = '100'; h.step = '1';
    hl.appendChild(h);
    sizes.appendChild(wl);
    sizes.appendChild(hl);
    dlg.appendChild(sizes);

    var actions = el('div', 'actions');
    var ok = el('button', 'btn btn-primary', 'Übernehmen'); ok.type = 'button';
    var none = el('button', 'btn btn-secondary', 'Ohne Markierung'); none.type = 'button';
    var cancel = el('button', 'btn btn-secondary', 'Abbrechen'); cancel.type = 'button';
    actions.appendChild(ok);
    actions.appendChild(none);
    actions.appendChild(cancel);
    dlg.appendChild(actions);
    document.body.appendChild(dlg);

    function place(ev) {
      if (!state) { return; }
      var r = stage.getBoundingClientRect();
      if (!r.width || !r.height) { return; }
      state.m.x = clamp((ev.clientX - r.left) / r.width, 0, 1);
      state.m.y = clamp((ev.clientY - r.top) / r.height, 0, 1);
      draw(svg, state.m);
    }
    var dragging = false;
    stage.addEventListener('pointerdown', function (ev) {
      dragging = true;
      try { stage.setPointerCapture(ev.pointerId); } catch (e) { /* egal */ }
      place(ev);
      ev.preventDefault();
    });
    stage.addEventListener('pointermove', function (ev) { if (dragging) { place(ev); } });
    stage.addEventListener('pointerup', function () { dragging = false; });
    stage.addEventListener('pointercancel', function () { dragging = false; });

    w.addEventListener('input', function () { if (state) { state.m.rx = clamp(Number(w.value) / 200, MIN_R, MAX_R); draw(svg, state.m); } });
    h.addEventListener('input', function () { if (state) { state.m.ry = clamp(Number(h.value) / 200, MIN_R, MAX_R); draw(svg, state.m); } });

    function finish(value) {
      var s = state;
      state = null;
      dlg.close();
      if (s && s.done) { s.done(value); }
    }
    ok.addEventListener('click', function () { finish(state ? format(state.m) : null); });
    none.addEventListener('click', function () { finish(''); });
    cancel.addEventListener('click', function () { finish(null); });
    dlg.addEventListener('cancel', function (ev) { ev.preventDefault(); finish(null); });

    dlg._parts = { img: img, svg: svg, w: w, h: h };
  }

  // Editor öffnen. done(wert): "x,y,rx,ry", '' (keine Markierung) oder null (abgebrochen).
  function open(src, value, done) {
    if (typeof HTMLDialogElement === 'undefined') { done(null); return; }
    if (!dlg) { build(); }
    var p = dlg._parts;
    p.img.onload = function () {
      var m = parse(value);
      if (!m) {
        var aspect = p.img.naturalWidth && p.img.naturalHeight ? p.img.naturalWidth / p.img.naturalHeight : 1;
        var rx = 0.12;
        m = { x: 0.5, y: 0.5, rx: rx, ry: clamp(rx * aspect, MIN_R, MAX_R) };
      }
      state = { m: m, done: done };
      p.w.value = String(Math.round(m.rx * 200));
      p.h.value = String(Math.round(m.ry * 200));
      draw(p.svg, m);
    };
    p.svg.classList.add('is-off');
    p.img.removeAttribute('src');
    p.img.src = src;
    dlg.showModal();
  }

  window.HydMarker = { open: open, draw: draw, parse: parse };

  // Bearbeiten-Seite: Knopf „Markierung“ je Bild
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-marker-open]') : null;
    if (!btn) { return; }
    var form = btn.closest('form[data-marker-form]');
    if (!form) { return; }
    ev.preventDefault();
    var field = form.querySelector('input[name=marker]');
    open(form.getAttribute('data-marker-src'), field.value, function (value) {
      if (value === null) { return; }
      field.value = value;
      form.submit();
    });
  });
})();
