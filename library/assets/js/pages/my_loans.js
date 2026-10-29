/* Student page: shows ONLY the logged-in student's own loans (server enforces this). Read-only. */
App.start(function () {
  var all = [], filter = '';
  App.api('transactions', { params: { mine: 1 } }).then(function (d) {
    if (!d.linked) { App.$('#linkWarn').innerHTML = '<div class="alert alert-error">Your account is not linked to a member record. Please ask the librarian.</div>'; return; }
    all = d.items;
    var cur = all.filter(function (t) { return t.status !== 'returned'; });
    var od = all.filter(function (t) { return t.status === 'overdue'; });
    App.$('#stats').innerHTML =
      '<div class="card stat"><span class="stat-label">Currently borrowed</span><span class="stat-num">' + cur.length + '</span></div>' +
      '<div class="card stat"><span class="stat-label">Overdue</span><span class="stat-num ' + (od.length ? 'bad' : '') + '">' + od.length + '</span></div>' +
      '<div class="card stat"><span class="stat-label">Total borrowed</span><span class="stat-num">' + all.length + '</span></div>';
    var chips = [['', 'All'], ['borrowed', 'Borrowed'], ['overdue', 'Overdue'], ['returned', 'Returned']];
    App.$('#chips').innerHTML = chips.map(function (c) { return '<button type="button" class="chip" data-f="' + c[0] + '">' + c[1] + '</button>'; }).join('');
    App.$('#chips').addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-f]'); if (!b) { return; }
      filter = b.getAttribute('data-f'); render();
    });
    render();
  }).catch(App.showError);

  function render() {
    App.$$('#chips .chip').forEach(function (c) { c.classList.toggle('active', c.getAttribute('data-f') === filter); });
    var rows = all.filter(function (t) { return !filter || t.status === filter; });
    App.$('#list').innerHTML = rows.length ? App.table(['Title', 'Borrowed', 'Due', 'Returned', 'Status'], rows.map(function (t) {
      return ['<strong>' + App.esc(t.title) + '</strong><br><small>' + App.esc(t.author) + '</small>', App.fmtDate(t.borrow_date), App.fmtDate(t.due_date), App.fmtDate(t.return_date), App.badge(t.status)];
    }), { rowClass: function (i) { return rows[i].status === 'overdue' ? 'row-overdue' : ''; } })
      : '<p class="empty">' + (all.length ? 'Nothing matches this filter.' : 'You have not borrowed anything yet.') + '</p>';
  }
});
