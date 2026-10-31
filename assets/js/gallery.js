(function () {
  'use strict';
  var dlg = document.getElementById('lightbox');
  if (!dlg || typeof dlg.showModal !== 'function') { return; } // ohne <dialog>: Link öffnet das Bild normal
  var img = dlg.querySelector('img');
  var cap = dlg.querySelector('.lightbox-caption');

  document.addEventListener('click', function (ev) {
    var a = ev.target.closest ? ev.target.closest('a[data-lightbox]') : null;
    if (!a) { return; }
    ev.preventDefault();
    var text = a.getAttribute('data-caption') || '';
    img.src = a.href;
    img.alt = text;
    cap.textContent = text;
    cap.hidden = text === '';
    dlg.showModal();
  });
  dlg.addEventListener('click', function (ev) {
    if (ev.target === dlg || (ev.target.closest && ev.target.closest('.lightbox-close'))) { dlg.close(); }
  });
  dlg.addEventListener('close', function () { img.removeAttribute('src'); });
})();
