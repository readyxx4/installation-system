document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-confirm-delete]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      if (!confirm(link.dataset.confirmDelete || 'ยืนยันการลบข้อมูลนี้หรือไม่?')) {
        event.preventDefault();
      }
    });
  });
});
