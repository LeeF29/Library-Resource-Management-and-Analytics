App.start(function (user) {
  var BASE = App.base;
  if (user) { location.href = App.url(user.home); return; }
  try {
    var f = sessionStorage.getItem('flash');
    if (f) { sessionStorage.removeItem('flash'); f = JSON.parse(f); App.flash(f[0], f[1]); }
  } catch (e) { /* ignore */ }
  App.$('#loginForm').addEventListener('submit', App.guard(function (ev) {
    ev.preventDefault();
    App.clearFlash();
    var d = App.formData(ev.target);
    return App.api('auth', { noRedirect: true, body: { action: 'login', username: d.username, password: d.password } }).then(function (r) {
      location.href = BASE + '/' + r.home;
    }).catch(function (e) { App.$('#password').value = ''; throw e; });
  }));
});
