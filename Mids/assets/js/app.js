/* =====================================================================
   A2Z Central — front-end behaviour (plain JavaScript, no framework)
   1 Helpers · 2 Layout · 3 Modals · 4 Filters · 5 Forms · 6 Kanban
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
})();
