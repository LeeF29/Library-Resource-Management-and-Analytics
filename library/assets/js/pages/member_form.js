App.start(function () {
  var id = parseInt(App.param('id'), 10) || 0, form = App.$('#form');
  document.querySelector('.topbar h1').textContent = id ? 'Edit Member' : 'Add Member';
  if (id) {
    App.$('#saveBtn').textContent = 'Save changes';
    App.api('members', { params: { id: id } }).then(function (d) {
      ['member_no', 'full_name', 'email', 'contact_number'].forEach(function (k) { form.elements[k].value = d.item[k] || ''; });
    }).catch(function (e) { App.showError(e); form.hidden = true; });
  }
  form.addEventListener('submit', App.guard(function (ev) {
    ev.preventDefault();
    var body = App.formData(form); body.action = id ? 'update' : 'create'; if (id) { body.id = id; }
    return App.api('members', { body: body }).then(function (r) { App.flashNext('success', r.message); location.href = 'members.html'; });
  }));
});
