document.addEventListener('DOMContentLoaded', () => {
  const layout = document.querySelector('.admin-user-split-layout');
  const rows = Array.from(document.querySelectorAll('.user-detail-row'));
  const panel = document.querySelector('.admin-user-detail-panel');
  const content = document.querySelector('[data-user-detail-content]');
  const closeButton = document.querySelector('[data-detail-close]');

  if (!layout || !rows.length || !panel || !content) {
    return;
  }

  const fields = {
    id: document.querySelector('[data-detail-id]'),
    name: document.querySelector('[data-detail-name]'),
    role: document.querySelector('[data-detail-role]'),
    code: document.querySelector('[data-detail-code]'),
    phone: document.querySelector('[data-detail-phone]'),
    email: document.querySelector('[data-detail-email]'),
    created: document.querySelector('[data-detail-created]'),
    address: document.querySelector('[data-detail-address]'),
    edit: document.querySelector('[data-detail-edit]')
  };

  const setText = (element, value) => {
    if (element) {
      element.textContent = value || '-';
    }
  };

  const selectRow = (row) => {
    rows.forEach((item) => item.classList.remove('is-selected'));
    row.classList.add('is-selected');

    const data = row.dataset;
    const editUrl = data.userEditUrl || '#';

    setText(fields.id, data.userId);
    setText(fields.name, data.userName);
    setText(fields.role, data.userRole);
    setText(fields.code, data.userId);
    setText(fields.phone, data.userPhone);
    setText(fields.email, data.userEmail);
    setText(fields.created, data.userCreated);
    setText(fields.address, data.userAddress);

    if (fields.role) {
      fields.role.className = '';
      fields.role.classList.add('admin-user-detail-role', data.userRoleBadge || 'blue');
    }

    if (fields.edit) {
      fields.edit.href = editUrl;
    }

    layout.classList.add('has-detail');
    panel.hidden = false;
  };

  const closePanel = () => {
    rows.forEach((item) => item.classList.remove('is-selected'));
    layout.classList.remove('has-detail');
    panel.hidden = true;
  };

  rows.forEach((row) => {
    row.addEventListener('click', (event) => {
      if (event.target.closest('a, button[data-toggle-address]')) {
        return;
      }

      selectRow(row);
    });

    row.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }

      if (event.target.closest('a, button[data-toggle-address]')) {
        return;
      }

      event.preventDefault();
      selectRow(row);
    });
  });

  if (closeButton) {
    closeButton.addEventListener('click', closePanel);
  }
});
