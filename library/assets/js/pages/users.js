App.start(function () {
  var list = App.$('#list'), ROLE = { admin: 'Administrator', librarian: 'Librarian', student: 'Student' };
  function load() {
    return App.api('users').then(function (d) {
      list.innerHTML = App.table(['Username', 'Name', 'Role', 'Linked member', 'Status', 'Actions'], d.items.map(function (u) {
        var a = '<a class="btn btn-sm" href="user_form.html?id=' + u.id + '">Edit</a>';
        if (u.id !== d.me) {
          a += u.is_active ? '<button class="btn btn-sm btn-danger" type="button" data-act="deactivate" data-id="' + u.id + '" data-name="' + App.esc(u.username) + '">Deactivate</button>'
                           : '<button class="btn btn-sm" type="button" data-act="activate" data-id="' + u.id + '">Activate</button>';
        }
        return ['<strong>' + App.esc(u.username) + '</strong>', App.esc(u.full_name), App.esc(ROLE[u.role] || u.role), App.esc(u.member_no || '—'), App.badge(u.is_active ? 'active' : 'inactive'), a];
      }));
    }).catch(App.showError);
  }
  list.addEventListener('click', App.guard(function (ev) {
    var b = ev.target.closest('button[data-act]'); if (!b) { return; }
    var act = b.getAttribute('data-act');
    var ask = act === 'deactivate' ? App.confirm('Deactivate the account "' + b.getAttribute('data-name') + '"? They will not be able to log in.') : Promise.resolve(true);
    return ask.then(function (ok) {
      return ok ? App.api('users', { body: { action: act, id: +b.getAttribute('data-id') } }).then(function (r) { App.clearFlash(); App.flash('success', r.message); return load(); }) : null;
    });
  }));
  load();
});
