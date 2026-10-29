App.start(function () {
  var list = App.$('#list'), editId = 0;
  function load() {
    return App.api('categories').then(function (d) {
      if (!d.items.length) { list.innerHTML = '<p class="empty">No categories yet.</p>'; return; }
      list.innerHTML = App.table(['Name', 'Resources', 'Actions'], d.items.map(function (c) {
        if (c.id === editId) {
          return ['<form class="inline-edit" data-rename="' + c.id + '"><input name="name" value="' + App.esc(c.name) + '" maxlength="80" required aria-label="Category name"><button class="btn btn-sm btn-primary" type="submit">Save</button><button class="btn btn-sm btn-ghost" type="button" data-act="cancel">Cancel</button></form>', c.used, ''];
        }
        return [App.esc(c.name), c.used,
          '<button class="btn btn-sm" type="button" data-act="edit" data-id="' + c.id + '">Rename</button><button class="btn btn-sm btn-danger" type="button" data-act="delete" data-id="' + c.id + '" data-name="' + App.esc(c.name) + '">Delete</button>'];
      }));
    }).catch(App.showError);
  }
  function done(r) { App.clearFlash(); App.flash('success', r.message); editId = 0; return load(); }

  App.$('#addForm').addEventListener('submit', App.guard(function (ev) {
    ev.preventDefault();
    return App.api('categories', { body: { action: 'add', name: App.$('#name').value } }).then(function (r) { App.$('#name').value = ''; return done(r); });
  }));
  list.addEventListener('submit', App.guard(function (ev) {
    ev.preventDefault();
    var f = ev.target;
    return App.api('categories', { body: { action: 'rename', id: +f.getAttribute('data-rename'), name: f.elements.name.value } }).then(done);
  }));
  list.addEventListener('click', App.guard(function (ev) {
    var b = ev.target.closest('button[data-act]'); if (!b) { return; }
    var act = b.getAttribute('data-act'), id = +b.getAttribute('data-id');
    if (act === 'edit') { editId = id; return load(); }
    if (act === 'cancel') { editId = 0; return load(); }
    return App.confirm('Delete the category "' + b.getAttribute('data-name') + '"?').then(function (ok) {
      return ok ? App.api('categories', { body: { action: 'delete', id: id } }).then(done) : null;
    });
  }));
  load();
});
