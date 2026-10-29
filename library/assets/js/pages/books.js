App.start(function () {
  var form = App.$('#filters'), list = App.$('#list'), pager = App.$('#pager'), page = 1;
  App.api('categories').then(function (d) { App.fillSelect(App.$('#fCategory'), d.items, 'id', function (c) { return c.name; }, 'All categories'); });

  function load() {
    var p = App.formData(form); p.page = page;
    return App.api('resources', { params: p }).then(function (d) {
      if (!d.items.length) { list.innerHTML = '<p class="empty">No resources match your search. <a href="book_form.html">Add a resource</a>.</p>'; pager.innerHTML = ''; return; }
      list.innerHTML = App.table(['Title', 'Author', 'ISBN', 'Category', 'Type', 'Availability', 'Actions'], d.items.map(function (r) {
        var sub = [r.publisher, r.year_published ? '(' + r.year_published + ')' : ''].filter(Boolean).join(' ');
        return ['<div style="display:flex;gap:.7rem;align-items:center">' + App.cover(r, 'sm') + '<div><strong>' + App.esc(r.title) + '</strong>' + (sub ? '<br><small>' + App.esc(sub) + '</small>' : '') + '</div></div>',
          App.esc(r.author), App.esc(r.isbn || '—'), App.esc(r.category_name), App.esc(App.typeLabel(r.resource_type)), App.availBadge(r),
          '<a class="btn btn-sm" href="book_form.html?id=' + r.id + '">Edit</a>' +
          (r.is_active ? '<button class="btn btn-sm btn-danger" type="button" data-act="delete" data-id="' + r.id + '" data-title="' + App.esc(r.title) + '">Delete</button>'
                       : '<button class="btn btn-sm" type="button" data-act="activate" data-id="' + r.id + '">Reactivate</button>')];
      }));
      App.pager(pager, d.page, function (p2) { page = p2; load(); });
    }).catch(App.showError);
  }

  list.addEventListener('click', App.guard(function (ev) {
    var b = ev.target.closest('button[data-act]'); if (!b) { return; }
    var act = b.getAttribute('data-act'), id = +b.getAttribute('data-id');
    var ask = act === 'delete' ? App.confirm('Delete "' + b.getAttribute('data-title') + '"? If it has borrowing history it will be marked Inactive instead.') : Promise.resolve(true);
    return ask.then(function (ok) {
      if (!ok) { return; }
      return App.api('resources', { body: { action: act, id: id } }).then(function (r) { App.clearFlash(); App.flash('success', r.message); return load(); });
    });
  }));
  form.addEventListener('submit', function (ev) { ev.preventDefault(); page = 1; load(); });
  App.$('#clear').addEventListener('click', function () { form.reset(); page = 1; load(); });
  load();
});
