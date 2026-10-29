App.start(function () {
  var form = App.$('#filters'), list = App.$('#list'), pager = App.$('#pager'), page = 1, status = App.param('status');
  var CH = [['', 'All'], ['borrowed', 'Borrowed'], ['overdue', 'Overdue'], ['returned', 'Returned']];
  function chips() {
    App.$('#chips').innerHTML = CH.map(function (c) { return '<button type="button" class="chip ' + (c[0] === status ? 'active' : '') + '" data-s="' + c[0] + '">' + c[1] + '</button>'; }).join('');
  }
  function load() {
    var p = App.formData(form); p.page = page; p.status = status;
    chips();
    return App.api('transactions', { params: p }).then(function (d) {
      if (!d.items.length) { list.innerHTML = '<p class="empty">No transactions match your filters.</p>'; pager.innerHTML = ''; return; }
      list.innerHTML = App.table(['#', 'Member', 'Resource', 'Borrowed', 'Due', 'Returned', 'Status', 'Action'], d.items.map(function (t) {
        return [t.id, App.esc(t.full_name) + '<br><small>' + App.esc(t.member_no) + '</small>', '<strong>' + App.esc(t.title) + '</strong>', App.fmtDate(t.borrow_date), App.fmtDate(t.due_date), App.fmtDate(t.return_date), App.badge(t.status),
          t.status === 'returned' ? '—' : '<button class="btn btn-sm btn-primary" type="button" data-id="' + t.id + '" data-title="' + App.esc(t.title) + '" data-who="' + App.esc(t.full_name) + '">Return</button>'];
      }), { rowClass: function (i) { return d.items[i].status === 'overdue' ? 'row-overdue' : ''; } });
      App.pager(pager, d.page, function (p2) { page = p2; load(); });
    }).catch(App.showError);
  }
  App.$('#chips').addEventListener('click', function (ev) { var b = ev.target.closest('[data-s]'); if (b) { status = b.getAttribute('data-s'); page = 1; load(); } });
  list.addEventListener('click', App.guard(function (ev) {
    var b = ev.target.closest('button[data-id]'); if (!b) { return; }
    return App.confirm('Mark "' + b.getAttribute('data-title') + '" as returned by ' + b.getAttribute('data-who') + '?').then(function (ok) {
      return ok ? App.api('transactions', { body: { action: 'return', id: +b.getAttribute('data-id') } }).then(function (r) { App.clearFlash(); App.flash('success', r.message); return load(); }) : null;
    });
  }));
  form.addEventListener('submit', function (ev) { ev.preventDefault(); page = 1; load(); });
  App.$('#clear').addEventListener('click', function () { form.reset(); status = ''; page = 1; load(); });
  load();
});
