document.addEventListener('DOMContentLoaded', function () {
  let pendingCancelUrl = '';

  const modal = document.createElement('div');
  modal.className = 'assignment-cancel-modal';
  modal.hidden = true;
  modal.innerHTML = `
    <div class="assignment-cancel-backdrop" data-cancel-close></div>
    <div class="assignment-cancel-dialog" role="dialog" aria-modal="true" aria-labelledby="assignmentCancelTitle">
      <div class="assignment-cancel-icon" aria-hidden="true">×</div>
      <div class="assignment-cancel-copy">
        <h2 id="assignmentCancelTitle">ยืนยันการยกเลิกใบงานติดตั้ง</h2>
        <p id="assignmentCancelMessage">ยืนยันการยกเลิกใบงานติดตั้งนี้หรือไม่?</p>
        <small>ข้อมูลใบงานจะยังถูกเก็บไว้เป็นประวัติ</small>
      </div>
      <div class="assignment-cancel-actions">
        <button type="button" class="assignment-cancel-back" data-cancel-close>กลับ</button>
        <button type="button" class="assignment-cancel-confirm">ยืนยันยกเลิก</button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);

  const message = modal.querySelector('#assignmentCancelMessage');
  const confirmButton = modal.querySelector('.assignment-cancel-confirm');

  function closeModal() {
    pendingCancelUrl = '';
    modal.hidden = true;
    document.body.classList.remove('assignment-cancel-open');
  }

  function openModal(link) {
    const setupId = link.dataset.cancelSetupId || '';
    pendingCancelUrl = link.href;
    message.textContent = `ยืนยันการยกเลิกใบงานติดตั้ง ${setupId || 'นี้'} หรือไม่?`;
    modal.hidden = false;
    document.body.classList.add('assignment-cancel-open');
    modal.querySelector('.assignment-cancel-back').focus();
  }

  document.querySelectorAll('[data-confirm-cancel-assignment]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      event.preventDefault();
      openModal(link);
    });
  });

  modal.querySelectorAll('[data-cancel-close]').forEach(function (button) {
    button.addEventListener('click', closeModal);
  });

  confirmButton.addEventListener('click', function () {
    if (pendingCancelUrl) {
      window.location.href = pendingCancelUrl;
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.hidden) {
      closeModal();
    }
  });
});
