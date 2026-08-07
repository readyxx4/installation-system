document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('userAddForm') || document.getElementById('userEditForm');
  if (!form) return;

  const nameInput = document.getElementById('user_name');
  const phoneInput = document.getElementById('user_phone');
  const emailInput = document.getElementById('user_email');
  const phoneDuplicateError = document.getElementById('phoneDuplicateError');
  const emailDuplicateError = document.getElementById('emailDuplicateError');
  const duplicateCheckUrl = form.dataset.duplicateUrl || form.action;
  const currentUserId = form.dataset.currentUserId || '';

  const passwordInput = document.getElementById('user_password');
  const passwordTrigger = document.getElementById('passwordChangeTrigger');
  const changePasswordInput = document.getElementById('change_password');
  const originalPassword = form.dataset.originalPassword || '';

  const provinceSelect = document.getElementById('province_id');
  const districtSelect = document.getElementById('district_id');
  const subDistrictSelect = document.getElementById('sub_district_id');
  const zipCodeInput = document.getElementById('zip_code');
  const addressDetail = document.getElementById('address_detail');
  const fullAddressInput = document.getElementById('user_address');

  const districtUrl = form.dataset.districtUrl || '';
  const subDistrictUrl = form.dataset.subDistrictUrl || '';
  const selectedProvinceName = form.dataset.selectedProvinceName || '';
  const selectedDistrictName = form.dataset.selectedDistrictName || '';
  const selectedSubdistrictName = form.dataset.selectedSubdistrictName || '';
  const selectedZipCode = form.dataset.selectedZipCode || '';

  function closeAddressDropdowns(except) {
    document.querySelectorAll('.address-dropdown.is-open').forEach(function (dropdown) {
      if (dropdown !== except) {
        dropdown.classList.remove('is-open');
      }
    });
  }

  function refreshAddressDropdown(select) {
    if (select && typeof select._refreshAddressDropdown === 'function') {
      select._refreshAddressDropdown();
    }
  }

  function initAddressDropdown(select) {
    if (!select || select.dataset.addressReady === '1') return;

    const wrap = select.closest('.address-select-wrap');
    if (!wrap) return;

    select.dataset.addressReady = '1';
    select.classList.add('address-native-select');

    const dropdown = document.createElement('div');
    dropdown.className = 'address-dropdown';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'address-select-button';

    const text = document.createElement('span');
    text.className = 'address-select-text';

    const arrow = document.createElement('span');
    arrow.className = 'address-select-arrow';
    arrow.setAttribute('aria-hidden', 'true');
    arrow.textContent = '⌄';

    const menu = document.createElement('div');
    menu.className = 'address-select-menu';

    button.appendChild(text);
    button.appendChild(arrow);
    dropdown.appendChild(button);
    dropdown.appendChild(menu);
    wrap.appendChild(dropdown);

    function render() {
      const selected = select.options[select.selectedIndex];
      const first = select.options[0];

      text.textContent = selected && selected.value ? selected.textContent : (first ? first.textContent : 'เลือกข้อมูล');
      button.disabled = select.disabled;
      dropdown.classList.toggle('is-disabled', select.disabled);
      menu.innerHTML = '';

      Array.from(select.options).forEach(function (option) {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'address-select-option';
        item.textContent = option.textContent;
        item.disabled = option.disabled || option.value === '';

        if (option.value === '') {
          item.classList.add('is-placeholder');
        }

        if (option.selected && option.value !== '') {
          item.classList.add('is-selected');
        }

        item.addEventListener('click', function () {
          if (item.disabled) return;

          select.value = option.value;
          closeAddressDropdowns();
          dropdown.classList.remove('is-open');
          select.dispatchEvent(new Event('change', { bubbles: true }));
          render();
        });

        menu.appendChild(item);
      });
    }

    button.addEventListener('click', function () {
      if (select.disabled) return;

      const willOpen = !dropdown.classList.contains('is-open');
      closeAddressDropdowns(dropdown);
      dropdown.classList.toggle('is-open', willOpen);
    });

    select.addEventListener('change', render);
    select._refreshAddressDropdown = render;
    render();
  }

  document.querySelectorAll('.js-address-select').forEach(initAddressDropdown);

  document.addEventListener('click', function (event) {
    if (!event.target.closest('.address-dropdown')) {
      closeAddressDropdowns();
    }
  });

  function resetSelect(select, text, disabled = true) {
    if (!select) return;
    select.innerHTML = `<option value="" selected>${text}</option>`;
    select.disabled = disabled;
    refreshAddressDropdown(select);
  }

  function setLoading(select, text) {
    if (!select) return;
    select.innerHTML = `<option value="" selected>${text}</option>`;
    select.disabled = true;
    refreshAddressDropdown(select);
  }

  function selectOptionByText(select, wantedText) {
    if (!select || !wantedText) return false;

    const wanted = wantedText.trim();
    const option = Array.from(select.options).find(item => item.textContent.trim() === wanted);

    if (!option) return false;

    select.value = option.value;
    return true;
  }

  function loadDistricts(provinceId) {
    if (!districtUrl) return;

    setLoading(districtSelect, 'กำลังโหลดอำเภอ...');
    resetSelect(subDistrictSelect, 'เลือกอำเภอก่อน');
    zipCodeInput.value = '';

    fetch(`${districtUrl}?province_id=${encodeURIComponent(provinceId)}`)
      .then(response => response.json())
      .then(data => {
        resetSelect(districtSelect, 'เลือกอำเภอ', false);

        data.forEach(item => {
          const option = document.createElement('option');
          option.value = item.id;
          option.textContent = item.name_th;
          districtSelect.appendChild(option);
        });

        refreshAddressDropdown(districtSelect);
      })
      .catch(() => resetSelect(districtSelect, 'โหลดอำเภอไม่สำเร็จ'));
  }

  function loadSubDistricts(districtId) {
    if (!subDistrictUrl) return;

    setLoading(subDistrictSelect, 'กำลังโหลดตำบล...');
    zipCodeInput.value = '';

    fetch(`${subDistrictUrl}?district_id=${encodeURIComponent(districtId)}`)
      .then(response => response.json())
      .then(data => {
        resetSelect(subDistrictSelect, 'เลือกตำบล', false);

        data.forEach(item => {
          const option = document.createElement('option');
          option.value = item.id;
          option.textContent = item.name_th;
          option.dataset.zipCode = item.zip_code || '';
          subDistrictSelect.appendChild(option);
        });

        refreshAddressDropdown(subDistrictSelect);
      })
      .catch(() => resetSelect(subDistrictSelect, 'โหลดตำบลไม่สำเร็จ'));
  }

  function loadSavedAddressSelection() {
    if (!selectedProvinceName || !provinceSelect || !districtSelect || !subDistrictSelect) return;
    if (!selectOptionByText(provinceSelect, selectedProvinceName)) return;

    districtSelect.innerHTML = '<option value="">กำลังโหลด...</option>';
    districtSelect.disabled = true;

    fetch(`${districtUrl}?province_id=${encodeURIComponent(provinceSelect.value)}`)
      .then(response => response.json())
      .then(data => {
        districtSelect.innerHTML = '<option value="">เลือกอำเภอ</option>';

        data.forEach(item => {
          const option = document.createElement('option');
          option.value = item.id;
          option.textContent = item.name_th;
          districtSelect.appendChild(option);
        });

        districtSelect.disabled = false;

        if (!selectOptionByText(districtSelect, selectedDistrictName)) return null;

        subDistrictSelect.innerHTML = '<option value="">กำลังโหลด...</option>';
        subDistrictSelect.disabled = true;

        return fetch(`${subDistrictUrl}?district_id=${encodeURIComponent(districtSelect.value)}`)
          .then(response => response.json())
          .then(subData => {
            subDistrictSelect.innerHTML = '<option value="">เลือกตำบล</option>';

            subData.forEach(item => {
              const option = document.createElement('option');
              option.value = item.id;
              option.textContent = item.name_th;
              option.dataset.zipCode = item.zip_code || '';
              subDistrictSelect.appendChild(option);
            });

            subDistrictSelect.disabled = false;
            selectOptionByText(subDistrictSelect, selectedSubdistrictName);

            const selected = subDistrictSelect.options[subDistrictSelect.selectedIndex];
            zipCodeInput.value = selected && selected.dataset.zipCode ? selected.dataset.zipCode : selectedZipCode;
          });
      })
      .catch(() => {
        districtSelect.innerHTML = '<option value="">เลือกจังหวัดก่อน</option>';
        districtSelect.disabled = true;
        subDistrictSelect.innerHTML = '<option value="">เลือกอำเภอก่อน</option>';
        subDistrictSelect.disabled = true;
        zipCodeInput.value = selectedZipCode;
      });
  }

  loadSavedAddressSelection();

  let phoneCheckController = null;
  let emailCheckController = null;

  function setDuplicateMessage(input, messageBox, message) {
    const wrap = input.closest('.staff-input-wrap');

    if (message) {
      messageBox.textContent = message;
      messageBox.classList.add('is-visible');
      wrap.classList.add('has-live-error');
      input.dataset.duplicate = '1';
    } else {
      messageBox.textContent = '';
      messageBox.classList.remove('is-visible');
      wrap.classList.remove('has-live-error');
      input.dataset.duplicate = '0';
    }
  }

  async function checkDuplicate(field, value, input, messageBox) {
    const trimmedValue = value.trim();

    if (!trimmedValue) {
      setDuplicateMessage(input, messageBox, '');
      return false;
    }

    if (field === 'user_phone' && !/^[0-9]{10}$/.test(trimmedValue)) {
      setDuplicateMessage(input, messageBox, '');
      return false;
    }

    if (field === 'user_email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedValue)) {
      setDuplicateMessage(input, messageBox, '');
      return false;
    }

    if (field === 'user_phone') {
      if (phoneCheckController) phoneCheckController.abort();
      phoneCheckController = new AbortController();
    } else {
      if (emailCheckController) emailCheckController.abort();
      emailCheckController = new AbortController();
    }

    const controller = field === 'user_phone' ? phoneCheckController : emailCheckController;
    const params = new URLSearchParams({
      ajax: 'check_duplicate',
      field: field,
      value: trimmedValue
    });

    if (currentUserId) {
      params.set('exclude_user_id', currentUserId);
    }

    try {
      const response = await fetch(`${duplicateCheckUrl}?${params.toString()}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        signal: controller.signal
      });

      const result = await response.json();

      if (result.duplicate) {
        const label = field === 'user_phone' ? 'เบอร์โทรศัพท์' : 'อีเมล';
        setDuplicateMessage(input, messageBox, `${label}นี้มีผู้ใช้งานแล้ว (รหัสผู้ใช้ ${result.user_id})`);
        return true;
      }

      setDuplicateMessage(input, messageBox, '');
      return false;
    } catch (error) {
      if (error.name !== 'AbortError') {
        setDuplicateMessage(input, messageBox, '');
      }

      return false;
    }
  }

  if (passwordTrigger && passwordInput && changePasswordInput) {
    passwordTrigger.addEventListener('click', function () {
      const isEditing = changePasswordInput.value === '1';

      if (isEditing) {
        changePasswordInput.value = '0';
        passwordInput.readOnly = true;
        passwordInput.value = originalPassword;
        passwordTrigger.classList.remove('is-active');
        passwordTrigger.textContent = 'แก้รหัสผ่าน';
      } else {
        changePasswordInput.value = '1';
        passwordInput.readOnly = false;
        passwordInput.focus();
        passwordInput.select();
        passwordTrigger.classList.add('is-active');
        passwordTrigger.textContent = 'ยกเลิกการเปลี่ยนรหัสผ่าน';
      }
    });
  }

  phoneInput.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 10);
    setDuplicateMessage(this, phoneDuplicateError, '');

    if (this.value.length === 10) {
      checkDuplicate('user_phone', this.value, this, phoneDuplicateError);
    }
  });

  phoneInput.addEventListener('blur', function () {
    checkDuplicate('user_phone', this.value, this, phoneDuplicateError);
  });

  nameInput.addEventListener('blur', function () {
    this.value = this.value.trim().replace(/\s+/g, ' ');
    const valid = /^\S+(?:\s+\S+)+$/u.test(this.value);
    this.setCustomValidity(valid ? '' : 'กรุณากรอกชื่อและนามสกุล โดยเว้นวรรคระหว่างชื่อกับนามสกุล');
  });

  nameInput.addEventListener('input', function () {
    this.setCustomValidity('');
  });

  emailInput.addEventListener('blur', function () {
    const value = this.value.trim();
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    this.setCustomValidity(valid ? '' : 'กรุณากรอกอีเมลให้ถูกต้อง และต้องมีเครื่องหมาย @');

    if (valid) {
      checkDuplicate('user_email', value, this, emailDuplicateError);
    }
  });

  emailInput.addEventListener('input', function () {
    this.setCustomValidity('');
    setDuplicateMessage(this, emailDuplicateError, '');
  });

  if (provinceSelect && districtSelect && subDistrictSelect) {
    provinceSelect.addEventListener('change', function () {
      if (this.value) {
        loadDistricts(this.value);
      } else {
        resetSelect(districtSelect, 'เลือกจังหวัดก่อน');
        resetSelect(subDistrictSelect, 'เลือกอำเภอก่อน');
      }
    });

    districtSelect.addEventListener('change', function () {
      if (this.value) {
        loadSubDistricts(this.value);
      } else {
        resetSelect(subDistrictSelect, 'เลือกอำเภอก่อน');
      }
    });

    subDistrictSelect.addEventListener('change', function () {
      const selected = this.options[this.selectedIndex];
      zipCodeInput.value = selected ? (selected.dataset.zipCode || '') : '';
    });
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    nameInput.value = nameInput.value.trim().replace(/\s+/g, ' ');

    if (!/^\S+(?:\s+\S+)+$/u.test(nameInput.value)) {
      nameInput.setCustomValidity('กรุณากรอกชื่อและนามสกุล โดยเว้นวรรคระหว่างชื่อกับนามสกุล');
      nameInput.reportValidity();
      return;
    }

    if (!form.reportValidity()) {
      return;
    }

    const phoneDuplicate = await checkDuplicate('user_phone', phoneInput.value, phoneInput, phoneDuplicateError);
    const emailDuplicate = await checkDuplicate('user_email', emailInput.value, emailInput, emailDuplicateError);

    if (phoneDuplicate || emailDuplicate) {
      const firstDuplicateInput = phoneDuplicate ? phoneInput : emailInput;
      firstDuplicateInput.focus();
      return;
    }

    if (fullAddressInput && addressDetail && provinceSelect && districtSelect && subDistrictSelect) {
      const detail = addressDetail.value.trim();
      const provinceText = provinceSelect.value ? provinceSelect.options[provinceSelect.selectedIndex].textContent.trim() : '';
      const districtText = districtSelect.value ? districtSelect.options[districtSelect.selectedIndex].textContent.trim() : '';
      const subText = subDistrictSelect.value ? subDistrictSelect.options[subDistrictSelect.selectedIndex].textContent.trim() : '';
      const zip = zipCodeInput.value.trim();

      if (detail && provinceText && districtText && subText && zip) {
        fullAddressInput.value = `${detail} ต.${subText} อ.${districtText} จ.${provinceText} ${zip}`;
      } else {
        fullAddressInput.value = detail;
      }
    }

    form.submit();
  });
});
