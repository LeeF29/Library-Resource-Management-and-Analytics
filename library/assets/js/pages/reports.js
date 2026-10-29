App.start(function () {
  var form = App.$('#filters'), type = App.param('type') || 'inventory';
  var TABS = [['inventory', 'Book inventory'], ['borrowing', 'Borrowing activity'], ['popular', 'Most borrowed']];
  App.api('categories').then(function (d) { App.fillSelect(App.$('#fCategory'), d.items, 'id', function (c) { return c.name; }, 'All categories'); });

  function params() { var p = App.formData(form); p.type = type; return p; }
  function load() {
    App.$('#tabs').innerHTML = TABS.map(function (t) { return '<a href="#" class="' + (t[0] === type ? 'active' : '') + '" data-t="' + t[0] + '">' + t[1] + '</a>'; }).join('');
    App.$('#dateFilters').hidden = type === 'inventory';
    var p = params();
    var q = new URLSearchParams(); Object.keys(p).forEach(function (k) { if (p[k]) { q.set(k, p[k]); } }); q.set('export', 'csv');
    App.$('#csv').href = App.base + '/api/reports.php?' + q.toString();
    return App.api('reports', { params: p }).then(function (d) {
      App.clearFlash();
      App.$('#reportTitle').textContent = d.title;
      App.$('#summary').innerHTML = Object.keys(d.summary).map(function (k) { return '<div class="card stat"><span class="stat-label">' + App.esc(k) + '</span><span class="stat-num">' + d.summary[k] + '</span></div>'; }).join('');
      App.$('#list').innerHTML = d.rows.length ? App.table(d.header, d.rows.map(function (r) { return r.map(function (c) { return App.esc(c === '' ? '—' : c); }); })) : '<p class="empty">No records match these filters.</p>';
    }).catch(App.showError);
  }
  App.$('#tabs').addEventListener('click', function (ev) { var a = ev.target.closest('[data-t]'); if (a) { ev.preventDefault(); type = a.getAttribute('data-t'); load(); } });
  form.addEventListener('submit', function (ev) { ev.preventDefault(); load(); });
  App.$('#clear').addEventListener('click', function () { form.reset(); load(); });
  load();
});
