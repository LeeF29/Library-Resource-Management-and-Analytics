/*
 * Shared front-end code for every HTML page.
 * - talks to the PHP JSON API in /api
 * - builds the sidebar / top bar after checking who is logged in
 * - small helpers used by the page scripts (tables, badges, dialogs, ...)
 */
(function () {
  'use strict';

  var scriptSrc = document.currentScript.src;
  var BASE = scriptSrc.replace(/\/assets\/js\/app\.js.*$/, '');   // e.g. http://localhost/library
  var App = window.App = { base: BASE, user: null, csrf: '' };

  // ---------- helpers ----------
  App.esc = function (s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  var esc = App.esc;
  App.$ = function (sel, root) { return (root || document).querySelector(sel); };
  App.$$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  App.url = function (path) { return BASE + '/' + path.replace(/^\//, ''); };
  App.param = function (name) { return new URLSearchParams(location.search).get(name) || ''; };

  App.fmtDate = function (d) {
    if (!d) { return '—'; }
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(d);
    if (!m) { return esc(d); }
    var mon = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][+m[2] - 1];
    return mon + ' ' + (+m[3]) + ', ' + m[1];
  };
  App.typeLabel = function (t) { return { book: 'Book', journal: 'Journal', other: 'Other material' }[t] || t; };

  var BADGE = { borrowed: 'Borrowed', overdue: 'Overdue', returned: 'Returned', available: 'Available',
                unavailable: 'All borrowed', inactive: 'Inactive', active: 'Active' };
  App.badge = function (status) {
    return '<span class="badge badge-' + esc(status) + '">' + esc(BADGE[status] || status) + '</span>';
  };
  App.availBadge = function (r) {
    if (!r.is_active) { return App.badge('inactive'); }
    var ok = r.available_copies > 0;
    return '<span class="badge badge-' + (ok ? 'available' : 'unavailable') + '">' + (ok ? 'Available' : 'All borrowed') +
           ' (' + r.available_copies + ' of ' + r.total_copies + ')</span>';
  };

  /** headers: ['Title', ...]; rows: array of arrays of ready-made (already escaped) HTML. */
  App.table = function (headers, rows, opts) {
    opts = opts || {};
    var h = '<div class="table-wrap"><table><thead><tr>' + headers.map(function (x) {
      return '<th' + (x === 'Actions' || x === 'Action' ? ' class="actions-col"' : '') + '>' + esc(x) + '</th>';
    }).join('') + '</tr></thead><tbody>';
    rows.forEach(function (r, i) {
      var cls = opts.rowClass ? opts.rowClass(i) : '';
      h += '<tr' + (cls ? ' class="' + cls + '"' : '') + '>' + r.map(function (c, j) {
        var isAct = headers[j] === 'Actions' || headers[j] === 'Action';
        return '<td' + (isAct ? ' class="actions"' : '') + ' data-label="' + esc(headers[j]) + '">' + c + '</td>';
      }).join('') + '</tr>';
    });
    return h + '</tbody></table></div>';
  };

  App.pager = function (el, pg, onChange) {
    if (!el) { return; }
    if (!pg || pg.pages <= 1) { el.innerHTML = ''; return; }
    el.innerHTML = '<nav class="pager" aria-label="Pages">' +
      (pg.page > 1 ? '<a href="#" data-p="' + (pg.page - 1) + '">&laquo; Prev</a>' : '') +
      '<span>Page ' + pg.page + ' of ' + pg.pages + ' (' + pg.total + ' records)</span>' +
      (pg.page < pg.pages ? '<a href="#" data-p="' + (pg.page + 1) + '">Next &raquo;</a>' : '') + '</nav>';
    App.$$('a[data-p]', el).forEach(function (a) {
      a.addEventListener('click', function (ev) { ev.preventDefault(); onChange(+a.getAttribute('data-p')); });
    });
  };

  // ---------- messages ----------
  App.flash = function (type, msg) {
    var box = App.$('#flashes');
    if (!box) { return; }
    var d = document.createElement('div');
    d.className = 'alert alert-' + type;
    d.setAttribute('role', 'alert');
    d.textContent = msg;
    box.appendChild(d);
    if (type === 'success') { setTimeout(function () { d.remove(); }, 6000); }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };
  App.clearFlash = function () { var b = App.$('#flashes'); if (b) { b.innerHTML = ''; } };
  App.flashNext = function (type, msg) {            // show a message on the NEXT page
    try { sessionStorage.setItem('flash', JSON.stringify([type, msg])); } catch (e) { /* ignore */ }
  };
  function showStoredFlash() {
    try {
      var f = sessionStorage.getItem('flash');
      if (f) { sessionStorage.removeItem('flash'); f = JSON.parse(f); App.flash(f[0], f[1]); }
    } catch (e) { /* ignore */ }
  }
  /** Show an error from the API. Validation errors (422) become a list. */
  App.showError = function (err) {
    App.clearFlash();
    var box = App.$('#flashes');
    if (!box) { return; }
    var d = document.createElement('div');
    d.className = 'alert alert-error';
    d.setAttribute('role', 'alert');
    var detail = err && err.errors && err.errors.length && !(err.errors.length === 1 && err.errors[0] === err.message);
    if (detail) {
      d.innerHTML = '<strong>Please fix the following:</strong><ul>' + err.errors.map(function (m) { return '<li>' + esc(m) + '</li>'; }).join('') + '</ul>';
    } else {
      d.textContent = (err && err.message) || 'Something went wrong.';
    }
    box.appendChild(d);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  App.confirm = function (msg) {
    return new Promise(function (resolve) {
      var dlg = App.$('#confirmDialog');
      if (!dlg || typeof dlg.showModal !== 'function') { resolve(window.confirm(msg)); return; }
      App.$('#confirmText').textContent = msg;
      dlg.returnValue = '';
      dlg.addEventListener('close', function once() {
        dlg.removeEventListener('close', once);
        resolve(dlg.returnValue === 'ok');
      });
      dlg.showModal();
    });
  };

  // ---------- API ----------
  /** api('books', {params:{q:'x'}}) -> GET ; api('books', {body:{action:'delete', id:1}}) -> POST */
  App.api = function (name, opts) {
    opts = opts || {};
    var url = BASE + '/api/' + name + '.php';
    if (opts.params) {
      var q = new URLSearchParams();
      Object.keys(opts.params).forEach(function (k) {
        if (opts.params[k] !== '' && opts.params[k] !== null && opts.params[k] !== undefined) { q.set(k, opts.params[k]); }
      });
      var s = q.toString();
      if (s) { url += '?' + s; }
    }
    var init = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (opts.body) {
      init.method = 'POST';
      init.headers['Content-Type'] = 'application/json';
      init.headers['X-CSRF-Token'] = App.csrf;
      init.body = JSON.stringify(opts.body);
    }
    return fetch(url, init).then(function (res) {
      return res.json().catch(function () { return { ok: false, error: 'Unexpected server response.' }; }).then(function (data) {
        if (res.status === 401 && !opts.noRedirect) {
          App.flashNext('error', 'Please log in to continue.');
          location.href = BASE + '/login.html';
          return new Promise(function () {});          // stop the chain
        }
        if (!res.ok || data.ok === false) {
          var e = new Error(data.error || 'Request failed.');
          e.status = res.status; e.errors = data.errors || [];
          throw e;
        }
        return data;
      });
    });
  };
  /** Wrap a click/submit handler so API errors are shown as messages. */
  App.guard = function (fn) {
    return function (ev) {
      var r;
      try { r = fn.call(this, ev); } catch (e) { App.showError(e); return; }
      if (r && r.catch) { r.catch(App.showError); }
    };
  };

  // ---------- page frame ----------
  // Two layouts: "staff" (sidebar workspace) and "public" (simple top bar for the catalog and student pages).
  var STAFF_NAV = [['staff/dashboard.html', 'Overview', 'dashboard'], ['staff/books.html', 'Catalog', 'books'],
                   ['staff/transactions.html', 'Circulation', 'transactions'], ['staff/borrow.html', 'New Loan', 'borrow'],
                   ['staff/members.html', 'Members', 'members'], ['staff/categories.html', 'Categories', 'categories'],
                   ['staff/reports.html', 'Reports', 'reports']];
  var ROLE_LABEL = { admin: 'Administrator', librarian: 'Librarian', student: 'Student' };
  var ICON = '<span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5"/></svg></span>';

  function confirmDialog() {
    var dlg = document.createElement('dialog');
    dlg.id = 'confirmDialog';
    dlg.innerHTML = '<form method="dialog"><p id="confirmText">Are you sure?</p><div class="dialog-actions">' +
      '<button class="btn btn-ghost" value="cancel">Cancel</button><button class="btn btn-danger" value="ok">Yes, continue</button></div></form>';
    document.body.appendChild(dlg);
  }

  function logoutHandler() {
    return App.guard(function () {
      return App.api('auth', { body: { action: 'logout' } }).then(function () {
        App.flashNext('success', 'You have been logged out.');
        location.href = BASE + '/login.html';
      });
    });
  }

  function buildStaff(user, active, title) {
    var content = App.$('#content');
    var items = STAFF_NAV.concat(user.role === 'admin' ? [['admin/users.html', 'User Accounts', 'users']] : []);
    var app = document.createElement('div');
    app.className = 'app';
    app.innerHTML =
      '<aside class="sidebar" id="sidebar"><div class="brand">' + ICON + '<span>Library System<small>Staff workspace</small></span></div><nav>' +
      items.map(function (i) {
        return '<a href="' + esc(App.url(i[0])) + '" class="' + (i[2] === active ? 'active' : '') + '">' + esc(i[1]) + '</a>';
      }).join('') + '</nav><a class="side-note" href="' + esc(App.url('index.html')) + '"><strong>Public catalog</strong>See what students see &rarr;</a></aside>' +
      '<div class="main"><header class="topbar"><button class="menu-btn" id="menuBtn" type="button" aria-label="Toggle menu" aria-controls="sidebar">&#9776;</button>' +
      '<div class="topbar-title"><span class="eyebrow">' + esc(ROLE_LABEL[user.role]) + ' workspace</span><h1>' + esc(title) + '</h1></div>' +
      '<div class="who"><span class="avatar" aria-hidden="true">' + esc(user.full_name.charAt(0).toUpperCase()) + '</span><span class="who-name"><strong>' +
      esc(user.full_name) + '</strong><small>@' + esc(user.username) + '</small></span><button class="btn btn-ghost" id="logoutBtn" type="button">Log out</button></div></header>' +
      '<div class="content" id="contentWrap"><div id="flashes"></div></div></div>';
    document.body.insertBefore(app, document.body.firstChild);
    App.$('#contentWrap').appendChild(content);
    confirmDialog();
    App.$('#menuBtn').addEventListener('click', function () { App.$('#sidebar').classList.toggle('open'); });
    App.$('#logoutBtn').addEventListener('click', logoutHandler());
  }

  function buildPublic(user, active) {
    var content = App.$('#content');
    var staff = user && user.role !== 'student';
    var links = [['index.html', 'Catalog', 'catalog']];
    if (user && user.role === 'student') { links.push(['student/my_loans.html', 'My Borrowed Books', 'loans']); }
    if (staff) { links.push([user.home, 'Staff workspace', 'staff']); }
    var wrap = document.createElement('div');
    wrap.className = 'public';
    wrap.innerHTML =
      '<header class="pub-top"><div class="pub-inner"><a class="brand" href="' + esc(App.url('index.html')) + '">' + ICON + '<span>Library System<small>Online catalog</small></span></a>' +
      '<nav class="pub-nav">' + links.map(function (l) {
        return '<a href="' + esc(App.url(l[0])) + '" class="' + (l[2] === active ? 'active' : '') + '">' + esc(l[1]) + '</a>';
      }).join('') + '</nav><div class="who">' +
      (user ? '<span class="who-name"><strong>' + esc(user.full_name) + '</strong><small>' + esc(ROLE_LABEL[user.role]) + '</small></span><button class="btn btn-ghost" id="logoutBtn" type="button">Log out</button>'
            : '<a class="btn btn-gold" href="' + esc(App.url('login.html')) + '">Log in</a>') +
      '</div></div></header><main class="pub-main" id="contentWrap"><div id="flashes"></div></main>' +
      '<footer class="pub-foot">Library Resource Management and Analytics System</footer>';
    document.body.insertBefore(wrap, document.body.firstChild);
    App.$('#contentWrap').appendChild(content);
    
    var lo = App.$('#logoutBtn');
    if (lo) { lo.addEventListener('click', logoutHandler()); }
  }

  function denied(user) {
    document.body.innerHTML = '<div class="login-wrap"><div class="login-card"><h1>Access denied</h1>' +
      '<p>Your account does not have permission to open this page.</p><a class="btn btn-primary" href="' + esc(App.url(user.home)) + '">Go back</a></div></div>';
    document.body.classList.add('ready');
  }

  /**
   * Every page script calls App.start(function (user) { ... }).
   * <body data-roles="admin,librarian"> = login required for those roles; no data-roles = public page.
   * <body data-layout="staff|public"> picks the frame; no layout = bare page (login).
   */
  App.start = function (init) {
    var b = document.body;
    var roles = (b.getAttribute('data-roles') || '').split(',').filter(Boolean);
    var layout = b.getAttribute('data-layout') || '';
    App.api('auth', { noRedirect: true }).then(function (data) {
      App.csrf = data.csrf;
      App.user = data.user;
      var user = data.user;
      if (!user && roles.length) {
        App.flashNext('error', 'Please log in to continue.');
        location.href = BASE + '/login.html';
        return null;
      }
      if (user && roles.length && roles.indexOf(user.role) === -1) { denied(user); return null; }
      var title = b.getAttribute('data-title') || document.title;
      if (layout === 'staff') { buildStaff(user, b.getAttribute('data-active'), title); }
      else if (layout === 'public') { buildPublic(user, b.getAttribute('data-active')); }
      b.classList.add('ready');
      showStoredFlash();
      return init(user);
    }).catch(function (e) {
      b.classList.add('ready');
      b.insertAdjacentHTML('afterbegin', '<div class="alert alert-error">Cannot reach the server: ' + esc(e.message) + '. Make sure Apache and MySQL are running in XAMPP.</div>');
    });
  };

  /** CSS-only book cover: colour depends on the category. */
  App.cover = function (r, cls) {
    var hue = ((r.category_id || 0) * 47 + 140) % 360;
    return '<div class="cover ' + (cls || '') + '" style="background:linear-gradient(160deg,hsl(' + hue + ' 40% 28%),hsl(' + ((hue + 25) % 360) + ' 38% 44%))" aria-hidden="true">' +
      '<span class="cover-title">' + esc(r.title) + '</span><span class="cover-cat">' + esc(r.category_name || '') + '</span></div>';
  };

  App.debounce = function (fn, ms) {
    var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); };
  };
  /** Read a form into a plain object (checkboxes -> true/false). */
  App.formData = function (form) {
    var o = {};
    App.$$('input,select,textarea', form).forEach(function (el) {
      if (!el.name) { return; }
      o[el.name] = el.type === 'checkbox' ? el.checked : el.value;
    });
    return o;
  };
  App.fillSelect = function (sel, items, valueKey, labelFn, firstLabel) {
    sel.innerHTML = (firstLabel !== undefined ? '<option value="">' + esc(firstLabel) + '</option>' : '') +
      items.map(function (i) { return '<option value="' + esc(i[valueKey]) + '">' + esc(labelFn(i)) + '</option>'; }).join('');
  };
  /** Type-to-filter for <select size=...> lists */
  App.filterSelect = function (input, select) {
    var options = Array.prototype.slice.call(select.options);
    input.addEventListener('input', function () {
      var term = input.value.toLowerCase();
      options.forEach(function (o) {
        var show = o.text.toLowerCase().indexOf(term) !== -1;
        o.hidden = !show; o.disabled = !show;
      });
    });
  };
})();
