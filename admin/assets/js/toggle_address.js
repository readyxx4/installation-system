function toggleAddress(button) {
  const cell = button.closest('.address-cell');
  const shortText = cell.querySelector('.address-short');
  const fullText = cell.querySelector('.address-full');

  if (fullText.style.display === 'none' || fullText.style.display === '') {
    shortText.style.display = 'none';
    fullText.style.display = 'block';
    button.textContent = 'ย่อข้อความ';
  } else {
    shortText.style.display = 'inline';
    fullText.style.display = 'none';
    button.textContent = 'ดูเพิ่มเติม';
  }
}

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-toggle-address]').forEach(function (button) {
    button.addEventListener('click', function () {
      toggleAddress(button);
    });
  });
});
