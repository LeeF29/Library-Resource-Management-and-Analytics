App.start(function () {
  var id = parseInt(App.param('id'), 10) || 0, form = App.$('#form');
  var catP = App.api('categories').then(function (d) { App.fillSelect(App.$('#category_id'), d.items, 'id', function (c) { return c.name; }, 'Choose…'); });

  if (id) {
    App.$('#saveBtn').textContent = 'Save changes';
    App.$('#activeField').hidden = false;
    document.querySelector('.topbar h1').textContent = 'Edit Resource';
    Promise.all([catP, App.api('resources', { params: { id: id } })]).then(function (res) {
      var r = res[1].item;
      ['title', 'author', 'isbn', 'category_id', 'resource_type', 'publisher', 'year_published', 'total_copies'].forEach(function (k) { form.elements[k].value = r[k] === null ? '' : r[k]; });
      form.elements.is_active.checked = !!r.is_active;
      App.$('#copiesNote').textContent = (r.total_copies - r.available_copies) + ' currently borrowed.';
    }).catch(function (e) { App.showError(e); form.hidden = true; });
  } else {
    document.querySelector('.topbar h1').textContent = 'Add Resource';
  }

  form.addEventListener('submit', App.guard(function (ev) {
    ev.preventDefault();
    var body = App.formData(form); body.action = id ? 'update' : 'create'; if (id) { body.id = id; }
    return App.api('resources', { body: body }).then(function (r) {
      App.flashNext('success', r.message);
      location.href = 'books.html';
    });
  }));
});
