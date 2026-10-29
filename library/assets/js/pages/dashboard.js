App.start(function () {
  App.api('dashboard').then(function (d) {
    var s = d.stats, pct = s.copies ? Math.round(s.available / s.copies * 100) : 0;
    App.$('#availPct').textContent = pct;
    App.$('#availBar').style.width = pct + '%';
    App.$('#availText').textContent = s.available + ' of ' + s.copies + ' copies ready for the next reader.';
    App.$('#stats').innerHTML =
      stat('Titles in catalog', s.titles, s.copies + ' total copies', '') +
      stat('Copies on loan', s.borrowed, 'currently out', '') +
      stat('Overdue returns', s.overdue, 'past due date', s.overdue ? 'bad' : '') +
      stat('Registered members', s.members, s.members_active + ' active', '');

    var max = Math.max.apply(null, d.activity.map(function (a) { return a.count; }).concat([1]));
    App.$('#activity').innerHTML = d.activity.every(function (a) { return !a.count; }) ? '<p class="empty">No borrowing recorded in this period.</p>' :
      '<div class="vbars" role="img" aria-label="Bar chart of items borrowed per month">' + d.activity.map(function (a) {
        return '<div class="vbar"><span class="vbar-val">' + a.count + '</span><div class="vbar-fill" style="height:' + Math.round(a.count / max * 100) + '%"></div><span class="vbar-label">' + App.esc(a.label) + '</span></div>';
      }).join('') + '</div>';

    var tmax = d.top.length ? Math.max(1, d.top[0].c) : 1;
    App.$('#top').innerHTML = d.top.length ? '<ol class="hbars">' + d.top.map(function (t) {
      return '<li><div class="hb"><div class="hbar-head"><span>' + App.esc(t.title) + '</span><span>' + t.c + '</span></div><div class="hbar-sub">' + App.esc(t.author) + '</div>' +
        '<div class="hbar-track"><div class="hbar-fill" style="width:' + Math.round(t.c / tmax * 100) + '%"></div></div></div></li>';
    }).join('') + '</ol>' : '<p class="empty">No borrowing recorded yet.</p>';

    App.$('#overdue').innerHTML = d.overdue.length ? App.table(['Member', 'Item', 'Due'], d.overdue.map(function (o) {
      return [App.esc(o.full_name) + ' <small>(' + App.esc(o.member_no) + ')</small>', App.esc(o.title), App.fmtDate(o.due_date)];
    })) + '<p><a href="transactions.html?status=overdue">See all overdue &raquo;</a></p>' : '<p class="empty">Nothing is overdue.</p>';

    App.$('#recent').innerHTML = d.recent.length ? App.table(['Member', 'Item', 'Status'], d.recent.map(function (r) {
      return [App.esc(r.full_name), App.esc(r.title), App.badge(r.status)];
    })) + '<p><a href="transactions.html">Open circulation &raquo;</a></p>' : '<p class="empty">No transactions yet.</p>';
  }).catch(App.showError);

  function stat(label, num, sub, cls) {
    return '<div class="card stat"><span class="stat-label">' + label + '</span><span class="stat-num ' + cls + '">' + num + '</span><span class="stat-sub">' + sub + '</span></div>';
  }
});
