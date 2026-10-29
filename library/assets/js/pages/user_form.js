App.start(function () {
  var id = parseInt(App.param('id'), 10) || 0, form = App.$('#form'), role = App.$('#role');
  document.querySelector('.topbar h1').textContent = id ? 'Edit Account' : 'Add Account';
  function toggle() { App.$('#memberField').hidden = role.value !== 'student'; }
  role.addEventListener('change', toggle);

  var memP = App.api('members', { params: { available_for_account: 1, user_id: id } }).then(function (d) {
    App.fillSelect(App.$('#member_id'), d.items, 'id', function (m) { return m.member_no + ' — ' + m.full_name; }, '— none —');
  });
  if (id) {
    App.$('#saveBtn').textContent = 'Save changes';
    App.$('#pwLabel').textContent = 'New password';
    App.$('#pwNote').textContent = 'Leave blank to keep the current password. At least 8 characters.';
    Promise.all([memP, App.api('users', { params: { id: id } })]).then(function (r) {
      var u = r[1].item;
      form.elements.username.value = u.username; form.elements.full_name.value = u.full_name; role.value = u.role;
      form.elements.member_id.value = u.member_id || ''; toggle();
    }).catch(function (e) { App.showError(e); form.hidden = true; });
  } else { toggle(); }

  form.addEventListener('submit', App.guard(function (ev) {
    ev.preventDefault();
    var body = App.formData(form); body.action = id ? 'update' : 'create'; if (id) { body.id = id; }
    return App.api('users', { body: body }).then(function (r) { App.flashNext('success', r.message); location.href = 'users.html'; });
  }));
});
