'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const tabButtons = document.querySelectorAll('[data-tab]');
  const sections = document.querySelectorAll('.tab-section');
  const saveButtons = document.querySelectorAll('[data-save]');
  const galleriesContainer = document.getElementById('galleries-container');
  const toast = document.getElementById('toast');

  function showToast(message, isError = false) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.toggle('error', isError);
    toast.classList.add('visible');
    setTimeout(() => toast.classList.remove('visible'), 4000);
  }

  function switchTab(tabId) {
    sections.forEach(section => {
      section.classList.toggle('active', section.id === tabId);
    });
    tabButtons.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tab === tabId);
    });
    localStorage.setItem('cms-active-tab', tabId);
  }

  tabButtons.forEach(btn => btn.addEventListener('click', () => switchTab(btn.dataset.tab)));
  switchTab(localStorage.getItem('cms-active-tab') || 'tab-general');

  function formToJSON(form) {
    const data = new FormData(form);
    const json = {};
    data.forEach((value, key) => {
      if (key.includes('[')) {
        const keys = key.replace(/\]/g, '').split('[');
        keys.reduce((acc, cur, idx) => {
          if (idx === keys.length - 1) {
            acc[cur] = value === 'on' ? true : value;
          } else {
            acc[cur] = acc[cur] || {};
          }
          return acc[cur];
        }, json);
      } else {
        json[key] = value;
      }
    });
    json.galleries = collectGalleries();
    return json;
  }

  function collectGalleries() {
    const data = {};
    document.querySelectorAll('.gallery-block').forEach(block => {
      const key = block.dataset.galleryKey;
      if (!key) return;
      data[key] = [];
      block.querySelectorAll('.gallery-card').forEach(card => {
        data[key].push({
          src: card.querySelector('[data-field="src"]').value,
          description: card.querySelector('[data-field="description"]').value,
          visible: card.querySelector('[data-field="visible"]').checked
        });
      });
    });
    return data;
  }

  function refreshContent() {
    fetch('api.php?action=get_content', {credentials: 'include'})
      .then(res => res.json())
      .then(data => {
        if (data.error) {
          showToast(data.error, true);
          return;
        }
        fillForms(data);
      })
      .catch(() => showToast('Inhalte konnten nicht geladen werden', true));
  }

  function fillForms(data) {
    document.querySelectorAll('[data-json-field]').forEach(input => {
      const path = input.dataset.jsonField.split('.');
      let value = data;
      for (const p of path) {
        value = value?.[p];
      }
      if (value === undefined) return;
      if (input.type === 'color' || input.type === 'text' || input.type === 'url') {
        input.value = value;
      } else if (input.tagName === 'TEXTAREA') {
        input.value = value;
      }
    });
    renderNavigation(data.navigation || []);
    renderButtons(data.buttons || []);
    renderGalleries(data.galleries || {});
  }

  function renderNavigation(items) {
    const container = document.getElementById('navigation-list');
    if (!container) return;
    container.innerHTML = '';
    items.forEach((item, index) => {
      const row = document.createElement('div');
      row.className = 'nav-row';
      row.innerHTML = `
        <input type="text" name="navigation[${index}][label]" value="${item.label ?? ''}" placeholder="Label" required />
        <input type="text" name="navigation[${index}][url]" value="${item.url ?? ''}" placeholder="URL" required />
        <button type="button" class="remove" data-remove="navigation">×</button>
      `;
      container.appendChild(row);
    });
  }

  function renderButtons(items) {
    const container = document.getElementById('button-list');
    if (!container) return;
    container.innerHTML = '';
    items.forEach((item, index) => {
      const row = document.createElement('div');
      row.className = 'nav-row';
      row.innerHTML = `
        <input type="text" name="buttons[${index}][label]" value="${item.label ?? ''}" placeholder="Text" required />
        <input type="text" name="buttons[${index}][url]" value="${item.url ?? ''}" placeholder="URL" required />
        <button type="button" class="remove" data-remove="buttons">×</button>
      `;
      container.appendChild(row);
    });
  }

  function renderGalleries(galleries) {
    if (!galleriesContainer) return;
    galleriesContainer.innerHTML = '';
    Object.keys(galleries).forEach(key => {
      const section = document.createElement('section');
      section.className = 'gallery-block';
      section.dataset.galleryKey = key;
      section.innerHTML = `<h3>${key}</h3>`;
      galleries[key].forEach(item => section.appendChild(createGalleryCard(key, item)));
      const addBtn = document.createElement('button');
      addBtn.type = 'button';
      addBtn.textContent = 'Bild hinzufügen';
      addBtn.className = 'secondary';
      addBtn.addEventListener('click', () => {
        section.insertBefore(createGalleryCard(key, {src: '', description: '', visible: true}), addBtn);
      });
      section.appendChild(addBtn);
      galleriesContainer.appendChild(section);
    });
  }

  function createGalleryCard(key, item) {
    const card = document.createElement('div');
    card.className = 'gallery-card';
    card.innerHTML = `
      <input type="text" data-field="src" value="${item.src ?? ''}" placeholder="Bild URL" />
      <textarea data-field="description" placeholder="Beschreibung">${item.description ?? ''}</textarea>
      <label class="checkbox"><input type="checkbox" data-field="visible" ${item.visible ? 'checked' : ''}/> Sichtbar</label>
      <button type="button" class="remove" data-remove-gallery="${key}">Entfernen</button>
    `;
    return card;
  }

  document.body.addEventListener('click', event => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    if (target.matches('[data-remove="navigation"]')) {
      target.parentElement?.remove();
    }
    if (target.matches('[data-remove="buttons"]')) {
      target.parentElement?.remove();
    }
    if (target.matches('[data-remove-gallery]')) {
      target.closest('.gallery-card')?.remove();
    }
  });

  document.getElementById('add-navigation')?.addEventListener('click', () => {
    const container = document.getElementById('navigation-list');
    const index = container.children.length;
    const row = document.createElement('div');
    row.className = 'nav-row';
    row.innerHTML = `
      <input type="text" name="navigation[${index}][label]" placeholder="Label" required />
      <input type="text" name="navigation[${index}][url]" placeholder="URL" required />
      <button type="button" class="remove" data-remove="navigation">×</button>
    `;
    container.appendChild(row);
  });

  document.getElementById('add-button')?.addEventListener('click', () => {
    const container = document.getElementById('button-list');
    const index = container.children.length;
    const row = document.createElement('div');
    row.className = 'nav-row';
    row.innerHTML = `
      <input type="text" name="buttons[${index}][label]" placeholder="Text" required />
      <input type="text" name="buttons[${index}][url]" placeholder="URL" required />
      <button type="button" class="remove" data-remove="buttons">×</button>
    `;
    container.appendChild(row);
  });

  saveButtons.forEach(button => {
    button.addEventListener('click', event => {
      event.preventDefault();
      const form = button.closest('form');
      if (!form) return;
      const payload = formToJSON(form);
      fetch('api.php?action=save_content', {
        method: 'POST',
        credentials: 'include',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
      })
        .then(res => res.json())
        .then(data => {
          if (data.error) {
            showToast(data.error, true);
            return;
          }
          showToast('Erfolgreich gespeichert');
          refreshContent();
        })
        .catch(() => showToast('Fehler beim Speichern', true));
    });
  });

  const uploadDropzone = document.getElementById('upload-dropzone');
  if (uploadDropzone) {
    uploadDropzone.addEventListener('dragover', e => {
      e.preventDefault();
      uploadDropzone.classList.add('dragover');
    });
    uploadDropzone.addEventListener('dragleave', () => uploadDropzone.classList.remove('dragover'));
    uploadDropzone.addEventListener('drop', e => {
      e.preventDefault();
      uploadDropzone.classList.remove('dragover');
      const files = e.dataTransfer?.files;
      if (!files?.length) return;
      const formData = new FormData();
      Array.from(files).forEach(file => formData.append('files[]', file));
      fetch('api.php?action=upload', {
        method: 'POST',
        credentials: 'include',
        body: formData
      })
        .then(res => res.json())
        .then(data => {
          if (data.error) {
            showToast(data.error, true);
            return;
          }
          showToast('Upload erfolgreich');
          const list = document.getElementById('upload-list');
          if (list) {
            data.files?.forEach(file => {
              const li = document.createElement('li');
              li.innerHTML = `<a href="${file.url}" target="_blank">${file.name}</a>`;
              list.appendChild(li);
            });
          }
        })
        .catch(() => showToast('Upload fehlgeschlagen', true));
    });
  }

  refreshContent();
});
