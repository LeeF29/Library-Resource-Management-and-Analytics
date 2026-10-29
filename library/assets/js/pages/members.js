App.start(function () {
  var form = App.$('#filters'), list = App.$('#list'), pager = App.$('#pager'), page = 1;
  function load() {
    var p = App.formData(form); p.page = page;
    return App.api('members', { params: p }).then(function (d) {
      if (!d.items.length) { list.innerHTML = '<p class="empty">No members found.</p>'; pager.innerHTML = ''; return; }
      list.innerHTML = App.table(['Member ID', 'Name', 'Email', 'Contact', 'Borrowed now', 'Status', 'Actions'], d.items.map(function (m) {
        var a = '<a class="btn btn-sm" href="member_view.html?id=' + m.id + '">History</a><a class="btn btn-sm" href="member_form.html?id=' + m.id + '">Edit</a>';
        a += m.status === 'active'
          ? '<button class="btn btn-sm" type="button" data-act="deactivate" data-id="' + m.id + '" data-name="' + App.esc(m.full_name) + '">Deactivate</button>'
          : '<button class="btn btn-sm" type="button" data-act="activate" data-id="' + m.id + '" data-name="' + App.esc(m.full_name) + '">Activate</button>';
        if (d.can_delete) { a += '<button class="btn btn-sm btn-danger" type="button" data-act="delete" data-id="' + m.id + '" data-name="' + App.esc(m.full_name) + '">Delete</button>'; }
        return [App.esc(m.member_no), '<strong>' + App.esc(m.full_name) + '</strong>', App.esc(m.email || '—'), App.esc(m.contact_number || '—'), m.on_loan, App.badge(m.status), a];
      }));
      App.pager(pager, d.page, function (p2) { page = p2; load(); });
    }).catch(App.showError);
  }
  var MSG = { deactivate: 'Deactivate NAME? They will no longer be able to borrow items.', delete: 'Permanently delete NAME? This cannot be undone.' };
  list.addEventListener('click', App.guard(function (ev) {
    var b = ev.target.closest('button[data-act]'); if (!b) { return; }
    var act = b.getAttribute('data-act');
    var ask = MSG[act] ? App.confirm(MSG[act].replace('NAME', b.getAttribute('data-name'))) : Promise.resolve(true);
    return ask.then(function (ok) {
      if (!ok) { return; }
      return App.api('members', { body: { action: act, id: +b.getAttribute('data-id') } }).then(function (r) { App.clearFlash(); App.flash('success', r.message); return load(); });
    });
  }));
  form.addEventListener('submit', function (ev) { ev.preventDefault(); page = 1; load(); });
  App.$('#clear').addEventListener('click', function () { form.reset(); page = 1; load(); });
  load();
});
