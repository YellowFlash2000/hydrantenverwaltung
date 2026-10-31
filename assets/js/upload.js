(function () {
  'use strict';
  // Verkleinert Fotos im Browser vor dem Hochladen. Das spart mobile Daten und umgeht Upload-Grenzen des Webspace.
  // Ohne Unterstützung (ältere Browser) werden die Originale unverändert hochgeladen, der Server verkleinert dann selbst.
  var MAX_SIDE = 2000;
  var SKIP_BELOW = 1200 * 1024;

  if (!window.createImageBitmap || !window.DataTransfer || !HTMLCanvasElement.prototype.toBlob) { return; }

  function shrink(file) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { return Promise.resolve(file); }
    return createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
      var scale = Math.min(1, MAX_SIDE / Math.max(bmp.width, bmp.height));
      if (scale === 1 && file.size < SKIP_BELOW) { if (bmp.close) { bmp.close(); } return file; }
      var canvas = document.createElement('canvas');
      canvas.width = Math.max(1, Math.round(bmp.width * scale));
      canvas.height = Math.max(1, Math.round(bmp.height * scale));
      var ctx = canvas.getContext('2d');
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);
      if (bmp.close) { bmp.close(); }
      return new Promise(function (resolve) {
        canvas.toBlob(function (blob) {
          if (!blob || (scale === 1 && blob.size >= file.size)) { resolve(file); return; }
          var base = file.name.replace(/\.[^.]+$/, '') || 'foto';
          resolve(new File([blob], base + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
        }, 'image/jpeg', 0.85);
      });
    }).catch(function () { return file; });
  }

  function sizeText(n) { return n >= 1048576 ? (n / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }

  document.querySelectorAll('input[type=file][data-resize]').forEach(function (input) {
    // Handys ersetzen bei jeder neuen Aufnahme die bisherige Auswahl. Darum sammeln wir die Fotos selbst
    // und zeigen sie als Liste, aus der einzelne wieder entfernt werden können.
    var collected = [];
    var list = null;
    var multiple = input.multiple;

    function ensureList() {
      if (list) { return list; }
      list = document.createElement('ul');
      list.className = 'photo-list';
      list.setAttribute('aria-live', 'polite');
      var anchor = input.nextElementSibling && input.nextElementSibling.classList.contains('hint') ? input.nextElementSibling : input;
      anchor.parentNode.insertBefore(list, anchor.nextSibling);
      return list;
    }

    function sync() {
      try {
        var dt = new DataTransfer();
        collected.forEach(function (f) { dt.items.add(f); });
        input.files = dt.files;
      } catch (e) { /* Originale bleiben ausgewählt */ }
      var ul = ensureList();
      ul.textContent = '';
      collected.forEach(function (f, i) {
        var li = document.createElement('li');
        var name = document.createElement('span');
        name.textContent = 'Foto ' + (i + 1) + ' (' + sizeText(f.size) + ')';
        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'btn btn-secondary btn-sm';
        del.textContent = 'Entfernen';
        del.addEventListener('click', function () { collected.splice(i, 1); sync(); });
        li.appendChild(name);
        li.appendChild(del);
        ul.appendChild(li);
      });
      ul.hidden = collected.length === 0;
    }

    input.addEventListener('upload-reset', function () { collected = []; sync(); });

    input.addEventListener('change', function () {
      var files = Array.prototype.slice.call(input.files || []);
      if (!files.length) { return; }
      var form = input.form;
      var buttons = form ? form.querySelectorAll('button[type=submit]') : [];
      buttons.forEach(function (b) { b.disabled = true; });
      var hint = document.createElement('p');
      hint.className = 'hint';
      hint.textContent = 'Bilder werden vorbereitet …';
      input.parentNode.appendChild(hint);

      Promise.all(files.map(shrink)).then(function (out) {
        if (!multiple) { collected = []; }
        out.forEach(function (f) { collected.push(f); });
        sync();
      }).then(function () {
        hint.remove();
        buttons.forEach(function (b) { b.disabled = false; });
      });
    });
  });
})();
