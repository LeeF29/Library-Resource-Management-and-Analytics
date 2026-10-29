App.start(function () {
  App.$('#rules').textContent = 'Loans are recorded for today. Standard loan period and limits are set by the library (see README); the system enforces them.';
  var mem = App.$('#member_id'), res = App.$('#resource_id'), pre = parseInt(App.param('member'), 10) || 0;
  App.filterSelect(App.$('#memberFilter'), mem);
  App.filterSelect(App.$('#resourceFilter'), res);

  function load() {
    return Promise.all([App.api('members', { params: { options: 1 } }), App.api('resources', { params: { options: 1 } })]).then(function (r) {
      App.fillSelect(mem, r[0].items, 'id', function (m) { return m.member_no + ' — ' + m.full_name; });
      App.fillSelect(res, r[1].items, 'id', function (x) { return x.title + ' — ' + x.author + ' (' + x.available_copies + ' available)'; });
      if (pre) { mem.value = String(pre); pre = 0; }
      App.$('#emptyNote').innerHTML = (r[0].items.length ? '' : '<p class="empty">There are no active members yet.</p>') + (r[1].items.length ? '' : '<p class="empty">No resources are available to borrow right now.</p>');
    });
  }
  App.$('#form').addEventListener('submit', App.guard(function (ev) {
    ev.preventDefault();
    return App.api('transactions', { body: { action: 'borrow', member_id: mem.value, resource_id: res.value } }).then(function (r) {
      App.clearFlash(); App.flash('success', r.message); return load();
    });
  }));
  load().catch(App.showError);
});
