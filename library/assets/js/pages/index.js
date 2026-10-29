/* Public catalog: read-only. Anyone can search; there are NO add/edit/loan actions on this page. */
App.start(function (user) {
  var form = App.$('#filters'), list = App.$('#list'), pager = App.$('#pager'), page = 1, items = [];

  // Hero buttons depend on who is looking
  var actions = '<a class="btn btn-gold" href="#catalogTop">Browse catalog</a>';
  if (!user) { actions += '<a class="btn btn-light" href="' + App.url('login.html') + '">Log in to see my loans</a>'; }
  else if (user.role === 'student') { actions += '<a class="btn btn-light" href="' + App.url('student/my_loans.html') + '">My borrowed books</a>'; }
  else { actions += '<a class="btn btn-light" href="' + App.url(user.home) + '">Open staff workspace</a>'; }
  App.$('#heroActions').innerHTML = actions;

  App.api('categories').then(function (d) { App.fillSelect(App.$('#fCategory'), d.items, 'id', function (c) { return c.name; }, 'All categories'); });

  function load() {
    var p = App.formData(form); p.page = page;
    return App.api('resources', { params: p }).then(function (d) {
      items = d.items;
      var s = d.summary, pct = s.copies ? Math.round(s.available / s.copies * 100) : 0;
      App.$('#availPct').textContent = pct;
      App.$('#availBar').style.width = pct + '%';
      App.$('#availText').textContent = s.available + ' of ' + s.copies + ' copies are on the shelf right now (' + s.titles + ' titles).';
      if (!items.length) { list.innerHTML = '<div class="card"><p class="empty">No resources match your search.</p></div>'; pager.innerHTML = ''; return; }
      list.innerHTML = '<div class="catalog">' + items.map(function (r, i) {
        return '<article class="book"><div class="book-top">' + App.cover(r) + '<div><span class="tag">' + App.esc(r.category_name) + '</span>' +
          '<h3>' + App.esc(r.title) + '</h3><div class="author">' + App.esc(r.author) + '</div>' +
          '<small>' + App.esc(App.typeLabel(r.resource_type)) + (r.year_published ? ' · ' + r.year_published : '') + '</small></div></div>' +
          '<div class="book-foot">' + App.availBadge(r) + '<button class="btn btn-sm" type="button" data-i="' + i + '">Details</button></div></article>';
      }).join('') + '</div>';
      App.pager(pager, d.page, function (p2) { page = p2; load().then(function () { window.scrollTo({ top: 0 }); }); });
    }).catch(App.showError);
  }

  form.addEventListener('submit', function (ev) { ev.preventDefault(); page = 1; load(); });
  App.$('#clear').addEventListener('click', function () { form.reset(); page = 1; load(); });
  App.$('select[name=category]', form).addEventListener('change', function () { page = 1; load(); });

  // Details dialog (read-only)
  var dlg = App.$('#detailDialog');
  list.addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-i]');
    if (!b) { return; }
    var r = items[+b.getAttribute('data-i')];
    App.$('#detailBody').innerHTML = '<div class="detail-head">' + App.cover(r, 'lg') + '<div><span class="tag">' + App.esc(r.category_name) + '</span><h2>' + App.esc(r.title) + '</h2><p class="muted">' + App.esc(r.author) + '</p>' + App.availBadge(r) + '</div></div>' +
      '<dl class="details"><dt>Type</dt><dd>' + App.esc(App.typeLabel(r.resource_type)) + '</dd><dt>ISBN</dt><dd>' + App.esc(r.isbn || '—') + '</dd>' +
      '<dt>Publisher</dt><dd>' + App.esc(r.publisher || '—') + '</dd><dt>Year</dt><dd>' + App.esc(r.year_published || '—') + '</dd>' +
      '<dt>Copies</dt><dd>' + r.available_copies + ' available of ' + r.total_copies + '</dd></dl>' +
      '<p class="muted">To borrow this item, please visit the library desk.</p>';
    dlg.showModal();
  });
  App.$('#detailClose').addEventListener('click', function () { dlg.close(); });
  load();
});
