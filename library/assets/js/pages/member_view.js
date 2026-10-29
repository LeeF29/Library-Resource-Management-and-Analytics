App.start(function () {
  var id = parseInt(App.param('id'), 10) || 0;
  App.api('members', { params: { id: id, history: 1 } }).then(function (d) {
    var m = d.item;
    document.querySelector('.topbar h1').textContent = 'Member: ' + m.full_name;
    App.$('#info').innerHTML = '<dt>Member ID</dt><dd>' + App.esc(m.member_no) + '</dd><dt>Name</dt><dd>' + App.esc(m.full_name) + '</dd><dt>Email</dt><dd>' + App.esc(m.email || '—') +
      '</dd><dt>Contact</dt><dd>' + App.esc(m.contact_number || '—') + '</dd><dt>Status</dt><dd>' + App.badge(m.status) + '</dd>';
    App.$('#editLink').href = 'member_form.html?id=' + m.id;
    App.$('#loanLink').href = 'borrow.html?member=' + m.id;
    App.$('#history').innerHTML = d.history.length ? App.table(['Resource', 'Borrowed', 'Due', 'Returned', 'Status'], d.history.map(function (t) {
      return [App.esc(t.title), App.fmtDate(t.borrow_date), App.fmtDate(t.due_date), App.fmtDate(t.return_date), App.badge(t.status)];
    }), { rowClass: function (i) { return d.history[i].status === 'overdue' ? 'row-overdue' : ''; } }) : '<p class="empty">This member has not borrowed anything yet.</p>';
  }).catch(function (e) { App.showError(e); });
});
