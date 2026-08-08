document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-confirm-cancel-assignment]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      if (!confirm(link.dataset.confirmCancelAssignment || 'ยืนยันการยกเลิกการมอบหมายงานนี้หรือไม่?')) {
        event.preventDefault();
      }
    });
  });
});