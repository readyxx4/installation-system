document.addEventListener('DOMContentLoaded', function () {
  const addForm = document.getElementById('techAddForm');
  const editForm = document.getElementById('techEditForm');
  const form = addForm || editForm;
  if (!form) return;

  const nameInput = document.getElementById('tech_name');
  const phoneInput = document.getElementById('tech_phone');
  const emailInput = document.getElementById('tech_email');
  const phoneError = document.getElementById('phoneDuplicateError');
  const emailError = document.getElementById('emailDuplicateError');
  const duplicateUrl = form.dataset.duplicateUrl || form.action;
  const currentId = form.dataset.currentTechId || '';

  const passInput = document.getElementById('tech_password');
  const passButton = document.getElementById('passwordTrigger');
  const changeInput = document.getElementById('change_password');
  const originalPassword = form.dataset.originalPassword || '';

  function setError(input, box, msg) {
    const wrap = input.closest('.staff-input-wrap');
    box.textContent = msg;
    box.classList.toggle('is-visible', !!msg);
    wrap.classList.toggle('has-live-error', !!msg);
    input.dataset.duplicate = msg ? '1' : '0';
  }

  async function check(field, input, box) {
    const value = input.value.trim();
    if (!value) return false;

    const params = new URLSearchParams({
      ajax: 'check_duplicate',
      field: field,
      value: value
    });

    if (currentId) {
      params.set('id', currentId);
      params.set('exclude_tech_id', currentId);
    }

    const response = await fetch(`${duplicateUrl}?${params.toString()}`);
    const result = await response.json();

    if (result.duplicate) {
      const label = field === 'tech_phone' ? 'เบอร์โทรศัพท์' : 'อีเมล';
      setError(input, box, `${label}นี้มีผู้ใช้งานแล้ว (รหัสช่าง ${result.tech_id})`);
      return true;
    }

    setError(input, box, '');
    return false;
  }

  if (passButton && passInput && changeInput) {
    passButton.addEventListener('click', function () {
      const editing = changeInput.value === '1';

      if (editing) {
        changeInput.value = '0';
        passInput.readOnly = true;
        passInput.value = originalPassword;
        this.classList.remove('is-active');
        this.textContent = 'แก้รหัสผ่าน';
      } else {
        changeInput.value = '1';
        passInput.readOnly = false;
        passInput.focus();
        passInput.select();
        this.classList.add('is-active');
        this.textContent = addForm ? 'ยกเลิก' : 'ยกเลิก';
      }
    });
  }

  nameInput.addEventListener('blur', function () {
    this.value = this.value.trim().replace(/\s+/g, ' ');
    this.setCustomValidity(/^\S+(?:\s+\S+)+$/u.test(this.value) ? '' : 'กรุณากรอกชื่อและนามสกุล โดยเว้นวรรคระหว่างชื่อกับนามสกุล');
  });

  nameInput.addEventListener('input', function () {
    this.setCustomValidity('');
  });

  phoneInput.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 10);
    setError(this, phoneError, '');
    if (addForm && this.value.length === 10) check('tech_phone', this, phoneError);
  });

  phoneInput.addEventListener('blur', function () {
    check('tech_phone', phoneInput, phoneError);
  });

  emailInput.addEventListener('input', function () {
    setError(this, emailError, '');
  });

  emailInput.addEventListener('blur', function () {
    check('tech_email', emailInput, emailError);
  });

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    nameInput.value = nameInput.value.trim().replace(/\s+/g, ' ');

    if (!/^\S+(?:\s+\S+)+$/u.test(nameInput.value)) {
      nameInput.setCustomValidity('กรุณากรอกชื่อและนามสกุล โดยเว้นวรรคระหว่างชื่อกับนามสกุล');
      nameInput.reportValidity();
      return;
    }

    if (!form.reportValidity()) return;

    const phoneDuplicate = await check('tech_phone', phoneInput, phoneError);
    const emailDuplicate = await check('tech_email', emailInput, emailError);
    if (phoneDuplicate || emailDuplicate) return;

    form.submit();
  });
});
