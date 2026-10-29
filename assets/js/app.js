/* =====================================================================
   A2Z Central — front-end behaviour (plain JavaScript, no framework)
   1 Helpers · 2 Layout · 3 Modals · 4 Filters · 5 Forms · 6 Kanban · 7 Charts
   ===================================================================== */
(function () {
  'use strict';

  // ---------- 1. Helpers ----------
  var A2Z = window.A2Z = window.A2Z || {};
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  // Bangladeshi digit grouping: 482000 -> 4,82,000
  function formatBD(n) {
    var s = Math.round(n).toString();
    if (s.length <= 3) { return s; }
    var last3 = s.slice(-3);
    var rest = s.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',');
    return rest + ',' + last3;
  }
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---------- 2. Layout: sidebar, dropdowns, toasts ----------
  $$('[data-sidebar-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () { document.body.classList.toggle('sidebar-open'); });
  });

  $$('[data-dropdown]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var box = document.getElementById(btn.dataset.dropdown);
      if (box) { box.classList.toggle('open'); }
    });
  });
  document.addEventListener('click', function (e) {
    $$('.dropdown.open').forEach(function (d) { if (!d.contains(e.target)) { d.classList.remove('open'); } });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      $$('.dropdown.open').forEach(function (d) { d.classList.remove('open'); });
      document.body.classList.remove('sidebar-open');
    }
  });

  function dismissToast(t) {
    t.classList.add('leaving');
    setTimeout(function () { t.remove(); }, 320);
  }
  $$('.toast').forEach(function (t, i) {
    setTimeout(function () { dismissToast(t); }, 5200 + i * 600);
    t.addEventListener('click', function () { dismissToast(t); });
  });

  // ---------- 3. Modals (the <dialog> element) ----------
  // Buttons use:  data-modal-open="id"   (create)   and   data-modal-edit="id" data-row='{json}'   (edit)
  function prepareForm(dlg, mode, row) {
    var form = $('form', dlg);
    if (!form) { return; }
    form.reset();
    var doInput = form.elements['do'];
    var idInput = form.elements['id'];
    var title = $('[data-title-create]', dlg);

    if (mode === 'create') {
      if (doInput && form.dataset.createDo) { doInput.value = form.dataset.createDo; }
      if (idInput) { idInput.value = ''; }
      if (title) { title.textContent = title.dataset.titleCreate; }
      return;
    }
    if (doInput && form.dataset.updateDo) { doInput.value = form.dataset.updateDo; }
    if (idInput && row.id !== undefined) { idInput.value = row.id; }
    if (title) { title.textContent = title.dataset.titleEdit; }

    Object.keys(row).forEach(function (key) {
      var el = form.elements[key];
      if (!el || el.type === 'hidden') { return; }
      el.value = (row[key] === null || row[key] === undefined) ? '' : row[key];
    });
    $$('[data-bind]', dlg).forEach(function (el) {
      var v = row[el.dataset.bind];
      el.textContent = (v === null || v === undefined) ? '' : v;
    });
  }

  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-modal-open]');
    if (opener) {
      var dlg = document.getElementById(opener.dataset.modalOpen);
      if (dlg) { prepareForm(dlg, 'create'); dlg.showModal(); }
      return;
    }
    var editor = e.target.closest('[data-modal-edit]');
    if (editor) {
      var dlg2 = document.getElementById(editor.dataset.modalEdit);
      var row = {};
      try { row = JSON.parse(editor.dataset.row || '{}'); } catch (err) { row = {}; }
      if (dlg2) { prepareForm(dlg2, 'edit', row); dlg2.showModal(); }
      return;
    }
    var closer = e.target.closest('[data-close]');
    if (closer) {
      var parent = closer.closest('dialog');
      if (parent) { parent.close(); }
    }
  });
  // Clicking the dark backdrop closes a dialog
  $$('dialog.modal').forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
  });

  // Styled confirmation box instead of the ugly browser confirm()
  function confirmBox(message) {
    return new Promise(function (resolve) {
      var dlg = document.getElementById('confirmDialog');
      if (!dlg) {
        dlg = document.createElement('dialog');
        dlg.id = 'confirmDialog';
        dlg.className = 'modal modal-sm';
        dlg.innerHTML = '<div class="modal-card"><div class="modal-head"><h3>Please confirm</h3></div>' +
          '<div class="modal-body"><p id="confirmText" class="muted"></p></div>' +
          '<div class="modal-foot"><button type="button" class="btn btn-ghost" data-no>Cancel</button>' +
          '<button type="button" class="btn btn-danger" data-yes>Yes, continue</button></div></div>';
        document.body.appendChild(dlg);
      }
      $('#confirmText', dlg).textContent = message;
      var yes = $('[data-yes]', dlg), no = $('[data-no]', dlg);
      var finished = false;
      function done(value) { if (finished) { return; } finished = true; dlg.close(); resolve(value); }
      yes.onclick = function () { done(true); };
      no.onclick = function () { done(false); };
      dlg.onclose = function () { done(false); };
      dlg.showModal();
    });
  }
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var message = form.dataset ? form.dataset.confirm : '';
    if (!message || form._confirmed) { return; }
    e.preventDefault();
    confirmBox(message).then(function (ok) {
      if (ok) { form._confirmed = true; form.submit(); }
    });
  });

  // ---------- 4. Search & filters ----------
  function applyTableFilter(selector) {
    var table = $(selector);
    if (!table) { return; }
    var box = $('[data-filter="' + selector + '"]');
    var select = $('[data-filter-status="' + selector + '"]');
    var q = box ? box.value.trim().toLowerCase() : '';
    var status = select ? select.value : '';
    var shown = 0;
    $$('tbody tr', table).forEach(function (tr) {
      if (tr.classList.contains('no-results')) { return; }
      var okText = q === '' || tr.textContent.toLowerCase().indexOf(q) !== -1;
      var okStatus = status === '' || tr.dataset.status === status;
      tr.hidden = !(okText && okStatus);
      if (!tr.hidden) { shown++; }
    });
    var none = $('.no-results', table);
    if (none) { none.hidden = shown !== 0; }
  }
  $$('[data-filter]').forEach(function (input) {
    input.addEventListener('input', function () { applyTableFilter(input.dataset.filter); });
  });
  $$('[data-filter-status]').forEach(function (select) {
    select.addEventListener('change', function () { applyTableFilter(select.dataset.filterStatus); });
  });
  $$('[data-filter-cards]').forEach(function (input) {
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      $$(input.dataset.filterCards).forEach(function (card) {
        card.style.display = (q === '' || card.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
      });
    });
  });
  $$('[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () { select.form.submit(); });
  });

  // ---------- 5. Small form helpers ----------
  $$('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.togglePassword);
      if (!input) { return; }
      var hidden = input.type === 'password';
      input.type = hidden ? 'text' : 'password';
      btn.textContent = hidden ? 'Hide' : 'Show';
    });
  });
  $$('[data-fill]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var parts = btn.dataset.fill.split('|');
      var email = document.getElementById('email'), pass = document.getElementById('password');
      if (email) { email.value = parts[0]; }
      if (pass) { pass.value = parts[1]; pass.focus(); }
    });
  });

  // File upload drop zone
  var fileInput = $('#fileInput'), dropzone = $('#dropzone'), dzName = $('#dzName');
  if (fileInput && dropzone && dzName) {
    var showFile = function () {
      var f = fileInput.files[0];
      if (!f) { return; }
      var size = f.size > 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB';
      dzName.textContent = f.name + ' · ' + size;
    };
    fileInput.addEventListener('change', showFile);
    ['dragenter', 'dragover'].forEach(function (ev) {
      dropzone.addEventListener(ev, function (e) { e.preventDefault(); dropzone.classList.add('drag'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      dropzone.addEventListener(ev, function (e) { e.preventDefault(); dropzone.classList.remove('drag'); });
    });
    dropzone.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; showFile(); }
    });
  }

  // KPI numbers count up when the dashboard opens
  if (!reduceMotion) {
    $$('[data-count]').forEach(function (el) {
      var format = el.dataset.format;
      var target = parseFloat(el.dataset.count);
      if ((format !== 'money' && format !== 'int') || !isFinite(target) || target === 0) { return; }
      var original = el.textContent;
      var start = performance.now();
      function tick(now) {
        var p = Math.min(1, (now - start) / 900);
        var value = target * (1 - Math.pow(1 - p, 3));
        el.textContent = format === 'money' ? '৳' + formatBD(value) : String(Math.round(value));
        if (p < 1) { requestAnimationFrame(tick); } else { el.textContent = original; }
      }
      requestAnimationFrame(tick);
    });
  }

  // ---------- 6. Kanban drag and drop ----------
  var board = $('.board');
  if (board) {
    var updateCounts = function () {
      $$('.column', board).forEach(function (col) {
        var badge = $('.count', col);
        if (badge) { badge.textContent = $$('.k-card', col).length; }
      });
    };
    $$('.k-card[draggable]', board).forEach(function (card) {
      card.addEventListener('dragstart', function (e) {
        card.classList.add('dragging');
        e.dataTransfer.setData('text/plain', card.dataset.id);
        e.dataTransfer.effectAllowed = 'move';
      });
      card.addEventListener('dragend', function () { card.classList.remove('dragging'); });
    });
    $$('.column', board).forEach(function (col) {
      col.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('drag-over'); });
      col.addEventListener('dragleave', function (e) { if (!col.contains(e.relatedTarget)) { col.classList.remove('drag-over'); } });
      col.addEventListener('drop', function (e) {
        e.preventDefault();
        col.classList.remove('drag-over');
        var id = e.dataTransfer.getData('text/plain');
        var card = $('.k-card[data-id="' + id + '"]', board);
        if (!card) { return; }
        $('.column-body', col).appendChild(card);
        updateCounts();
        // Tell the server (a small background request that expects JSON back)
        var body = new URLSearchParams({ 'do': 'move_task', id: id, status: col.dataset.status, csrf: A2Z.csrf || '' });
        fetch('projects.php', { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) { if (!d.ok) { location.reload(); } })
          .catch(function () { location.reload(); });
      });
    });
  }

  // ---------- 7. Dashboard charts (Chart.js, with a plain-CSS fallback when offline) ----------
  function fallbackBars(box, labels, values, colors, money) {
    var max = Math.max.apply(null, values.concat([1]));
    box.style.height = 'auto';
    box.innerHTML = labels.map(function (label, i) {
      var shown = money ? '৳' + formatBD(values[i]) : values[i];
      return '<div class="fb"><span class="fb-label">' + esc(label) + '</span>' +
        '<div class="fb-bar"><span style="width:' + (values[i] / max * 100) + '%;background:' + (colors ? colors[i] : '#F5A623') + '"></span></div>' +
        '<span class="fb-val">' + shown + '</span></div>';
    }).join('');
  }

  A2Z.initCharts = function () {
    var dataEl = $('#chartData');
    var barBox = $('#chartBar'), donutBox = $('#chartDonut');
    if (!dataEl || !barBox || !donutBox) { return; }
    var data = JSON.parse(dataEl.textContent);

    if (typeof window.Chart === 'undefined') {            // Chart.js could not be loaded
      fallbackBars(barBox, data.bar.labels, data.bar.values, null, data.bar.money);
      fallbackBars(donutBox, data.donut.labels, data.donut.values, data.donut.colors, false);
      return;
    }
    Chart.defaults.font.family = "'Figtree', system-ui, sans-serif";
    Chart.defaults.color = '#5E6B85';

    new Chart($('canvas', barBox), {
      type: 'bar',
      data: { labels: data.bar.labels, datasets: [{ data: data.bar.values, backgroundColor: '#F5A623', hoverBackgroundColor: '#DB8F0C', borderRadius: 8, maxBarThickness: 46 }] },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return ' ' + (data.bar.money ? '৳' + formatBD(c.parsed.y) : c.parsed.y); } } } },
        scales: {
          y: { beginAtZero: true, grid: { color: '#EDF0F5' }, border: { display: false },
               ticks: { precision: 0, callback: function (v) { return data.bar.money && v >= 100000 ? (v / 100000) + 'L' : v; } } },
          x: { grid: { display: false }, border: { display: false } }
        }
      }
    });
    new Chart($('canvas', donutBox), {
      type: 'doughnut',
      data: { labels: data.donut.labels, datasets: [{ data: data.donut.values, backgroundColor: data.donut.colors, borderWidth: 3, borderColor: '#fff' }] },
      options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } } } }
    });
  };

  function loadScript(src, callback) {
    var s = document.createElement('script');
    s.src = src;
    s.onload = function () { callback(true); };
    s.onerror = function () { s.remove(); callback(false); };
    document.head.appendChild(s);
  }
  if ($('#chartData')) {
    // 1) a local copy (works offline: put chart.umd.min.js in assets/js/)  2) the CDN  3) the CSS fallback
    loadScript('assets/js/chart.umd.min.js', function (ok) {
      if (ok) { A2Z.initCharts(); return; }
      loadScript('https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js', function () { A2Z.initCharts(); });
    });
  }
})();
