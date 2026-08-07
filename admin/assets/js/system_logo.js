function previewSystemLogo(input) {
  const file = input.files && input.files[0];
  const preview = document.getElementById('systemLogoPreview');
  const fileName = document.getElementById('systemLogoFileName');
  const title = document.getElementById('logoPreviewTitle');
  const removeInput = document.getElementById('remove_logo');

  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    alert('กรุณาเลือกไฟล์รูปภาพเท่านั้น');
    input.value = '';
    return;
  }

  removeInput.value = '0';

  const reader = new FileReader();
  reader.onload = function (event) {
    preview.src = event.target.result;
    preview.style.display = 'block';
    fileName.textContent = file.name;
    title.textContent = 'ตัวอย่างรูปภาพใหม่';

    const imageBox = preview.closest('.system-logo-image-box');
    const oldRemoveButton = imageBox.querySelector('.logo-remove-x');
    if (!oldRemoveButton) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'logo-remove-x';
      btn.title = 'ลบรูปภาพ';
      btn.textContent = '×';
      btn.addEventListener('click', removeSystemLogo);
      imageBox.appendChild(btn);
    }
  };

  reader.readAsDataURL(file);
}

function removeSystemLogo() {
  const preview = document.getElementById('systemLogoPreview');
  const fileName = document.getElementById('systemLogoFileName');
  const title = document.getElementById('logoPreviewTitle');
  const removeInput = document.getElementById('remove_logo');
  const fileInput = document.getElementById('system_logo');
  const removeButton = document.querySelector('.logo-remove-x');

  preview.src = '';
  preview.style.display = 'none';
  fileName.textContent = 'ลบรูปภาพแล้ว กรุณากดบันทึกข้อมูล';
  title.textContent = 'ไม่มีรูปภาพ';
  removeInput.value = '1';
  fileInput.value = '';
  if (removeButton) removeButton.remove();
}

document.addEventListener('DOMContentLoaded', function () {
  const fileInput = document.getElementById('system_logo');
  const removeButton = document.querySelector('.logo-remove-x');

  if (fileInput) {
    fileInput.addEventListener('change', function () {
      previewSystemLogo(this);
    });
  }

  if (removeButton) {
    removeButton.addEventListener('click', removeSystemLogo);
  }
});
