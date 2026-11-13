(function () {
  // Konfirmasi sebelum submit form (data-confirm) / klik tombol (data-confirm-form)
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-confirm-form]');
    if (b && !window.confirm(b.getAttribute('data-confirm-form'))) { e.preventDefault(); return; }
    if (e.target.closest('[data-print]')) { e.preventDefault(); window.print(); return; }

    // Lightbox foto bukti: tampil dalam jendela, tidak membuka foto ukuran penuh di halaman
    var a = e.target.closest('a[data-lightbox]');
    if (a && window.bootstrap) {
      e.preventDefault();
      document.getElementById('lightboxImg').src = a.getAttribute('href');
      window.bootstrap.Modal.getOrCreateInstance(document.getElementById('lightbox')).show();
    }
  });

  // Sidebar mobile
  var sb = document.getElementById('sidebar'), bd = document.getElementById('backdrop'), bg = document.getElementById('burger');
  function toggle(open) { if (!sb) return; sb.classList.toggle('open', open); if (bd) bd.classList.toggle('show', open); }
  if (bg) bg.addEventListener('click', function () { toggle(!sb.classList.contains('open')); });
  if (bd) bd.addEventListener('click', function () { toggle(false); });

  // Form user: sembunyikan pilihan desa bila role bukan kepala desa
  var roleSel = document.getElementById('roleSel'), desaWrap = document.getElementById('desaWrap');
  if (roleSel && desaWrap) {
    var sync = function () { desaWrap.style.display = roleSel.value === 'kepala_desa' ? '' : 'none'; };
    roleSel.addEventListener('change', sync); sync();
  }

  // Foto: perkecil di browser (maks 1600px) + pratinjau kecil. Pengunggah boleh memilih foto sebesar apa pun.
  document.querySelectorAll('input.js-photo').forEach(function (inp) {
    var prev = document.createElement('img');
    prev.className = 'photo-preview'; prev.alt = 'Pratinjau foto'; prev.style.display = 'none';
    inp.parentNode.appendChild(prev);
    function show(file) { if (prev.dataset.u) URL.revokeObjectURL(prev.dataset.u); var u = URL.createObjectURL(file); prev.dataset.u = u; prev.src = u; prev.style.display = 'block'; }

    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0];
      if (!f) { prev.style.display = 'none'; return; }
      show(f);
      if (!/^image\/(jpeg|png|webp)$/.test(f.type) || !window.DataTransfer) return;
      var img = new Image(), url = URL.createObjectURL(f);
      img.onload = function () {
        var max = 1600, w = img.naturalWidth, h = img.naturalHeight, s = Math.min(1, max / Math.max(w, h));
        var c = document.createElement('canvas'); c.width = Math.round(w * s); c.height = Math.round(h * s);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (blob) {
          URL.revokeObjectURL(url);
          if (!blob || blob.size >= f.size) return;
          var dt = new DataTransfer();
          dt.items.add(new File([blob], f.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
          inp.files = dt.files;
        }, 'image/jpeg', 0.85);
      };
      img.src = url;
    });
  });
})();
