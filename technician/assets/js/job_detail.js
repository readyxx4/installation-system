(() => {
  'use strict';

  const modal = document.querySelector('[data-job-detail-receive-modal]');
  const trigger = document.querySelector('[data-job-detail-receive-open]');
  const message = modal?.querySelector('[data-job-detail-receive-message]');
  const submitButton = modal?.querySelector('[data-job-detail-receive-submit]');
  const cancelButtons = modal ? Array.from(modal.querySelectorAll('[data-job-detail-receive-cancel]')) : [];

  if (!modal || !trigger || !message || !submitButton || typeof modal.showModal !== 'function') {
    return;
  }

  let isSubmitting = false;

  const setMessage = (text = '') => {
    message.textContent = text;
    message.hidden = text === '';
  };

  const setSubmitting = (submitting) => {
    isSubmitting = submitting;
    submitButton.disabled = submitting;
    submitButton.setAttribute('aria-busy', submitting ? 'true' : 'false');
    submitButton.querySelector('span').textContent = submitting ? 'กำลังยืนยัน...' : 'ยืนยัน';
  };

  const closeModal = () => {
    if (!isSubmitting && modal.open) {
      modal.close();
    }
  };

  trigger.addEventListener('click', () => {
    setMessage();
    setSubmitting(false);
    modal.showModal();
  });

  cancelButtons.forEach((button) => button.addEventListener('click', closeModal));

  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });

  modal.addEventListener('cancel', (event) => {
    if (isSubmitting) {
      event.preventDefault();
    }
  });

  modal.addEventListener('close', () => {
    setMessage();
    setSubmitting(false);
  });

  submitButton.addEventListener('click', async () => {
    if (isSubmitting) {
      return;
    }

    const assignId = trigger.dataset.receiveAssignId || '';
    const actionUrl = trigger.dataset.receiveAction || '';
    const returnUrl = trigger.dataset.receiveReturnUrl || window.location.href;

    if (!assignId || !actionUrl) {
      setMessage('ไม่สามารถยืนยันรับสินค้าได้ กรุณาลองใหม่อีกครั้ง');
      return;
    }

    setSubmitting(true);
    setMessage();

    try {
      const formData = new FormData();
      formData.set('action', 'confirm_product_receive');
      formData.set('assign_id', assignId);

      const response = await fetch(actionUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        redirect: 'follow',
      });
      const resultUrl = new URL(response.url, window.location.href);

      if (!response.ok || resultUrl.searchParams.get('receive') !== 'created') {
        throw new Error('receive-confirmation-failed');
      }

      window.location.replace(returnUrl);
    } catch (_) {
      setSubmitting(false);
      setMessage('ไม่สามารถยืนยันรับสินค้าได้ กรุณาลองใหม่อีกครั้ง');
    }
  });
})();

(() => {
  'use strict';

  const modal = document.querySelector('[data-install-complete-modal]');
  const openButton = document.querySelector('[data-install-complete-open]');
  const form = modal?.querySelector('[data-install-complete-form]');
  const fileInput = modal?.querySelector('[data-install-complete-files]');
  const countInput = modal?.querySelector('[data-install-complete-count]');
  const existingInputContainer = modal?.querySelector('[data-install-complete-existing]');
  const addButton = modal?.querySelector('[data-install-complete-add]');
  const preview = modal?.querySelector('[data-install-complete-preview]');
  const message = modal?.querySelector('[data-install-complete-message]');
  const saveButton = modal?.querySelector('[data-install-complete-save]');
  const cancelButtons = modal ? Array.from(modal.querySelectorAll('[data-install-complete-cancel]')) : [];
  const previewDialog = document.querySelector('[data-install-complete-preview-dialog]');
  const previewImage = previewDialog?.querySelector('[data-install-complete-preview-image]');
  const previewCloseButton = previewDialog?.querySelector('[data-install-complete-preview-close]');

  if (!modal || !openButton || !form || !fileInput || !countInput || !existingInputContainer || !addButton || !preview || !message || !saveButton || typeof modal.showModal !== 'function') {
    return;
  }

  let isSubmitting = false;
  let selectedFiles = [];
  const maxFiles = Number.parseInt(form.dataset.maxFiles || '0', 10);
  let confirmedPhotos = [];

  try {
    const parsedPhotos = JSON.parse(form.dataset.confirmedPhotos || '[]');
    confirmedPhotos = Array.isArray(parsedPhotos)
      ? parsedPhotos.filter((photo) => photo && typeof photo.path === 'string' && typeof photo.url === 'string')
      : [];
  } catch (_) {
    confirmedPhotos = [];
  }

  const setMessage = (text = '') => {
    message.textContent = text;
    message.hidden = text === '';
  };

  const setSubmitting = (submitting) => {
    isSubmitting = submitting;
    addButton.disabled = submitting || !maxFiles || selectedFiles.length >= maxFiles;
    saveButton.disabled = submitting || selectedFiles.length === 0;
    cancelButtons.forEach((button) => {
      button.disabled = submitting;
    });
  };

  const clearSelection = () => {
    selectedFiles.forEach((entry) => {
      if (entry.kind === 'new') {
        URL.revokeObjectURL(entry.url);
      }
    });
    selectedFiles = [];
    fileInput.value = '';
    countInput.value = '0';
    existingInputContainer.replaceChildren();
    preview.replaceChildren();
    preview.hidden = true;
  };

  const resetSelection = () => {
    clearSelection();
    selectedFiles = confirmedPhotos.map((photo) => ({
      kind: 'existing',
      path: photo.path,
      url: photo.url,
    }));
    syncSelection();
  };

  const openPreview = (entry, index) => {
    if (!previewDialog || !previewImage || typeof previewDialog.showModal !== 'function') {
      return;
    }

    previewImage.src = entry.url;
    previewImage.alt = `รูปงานติดตั้งที่ ${index + 1}`;
    previewDialog.showModal();
    previewCloseButton?.focus();
  };

  const syncSelection = () => {
    const transfer = new DataTransfer();
    selectedFiles
      .filter((entry) => entry.kind === 'new')
      .forEach((entry) => transfer.items.add(entry.file));
    fileInput.files = transfer.files;
    countInput.value = String(transfer.files.length);

    existingInputContainer.replaceChildren();
    selectedFiles
      .filter((entry) => entry.kind === 'existing')
      .forEach((entry) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'installation_existing_proofs[]';
        input.value = entry.path;
        existingInputContainer.append(input);
      });

    preview.replaceChildren();
    selectedFiles.forEach((entry, index) => {
      const figure = document.createElement('figure');
      const open = document.createElement('button');
      const image = document.createElement('img');
      const remove = document.createElement('button');

      open.type = 'button';
      open.className = 'install-complete-modal__preview-open';
      open.setAttribute('aria-label', `ดูรูปงานติดตั้งที่ ${index + 1}`);
      image.src = entry.url;
      image.alt = `รูปงานติดตั้งที่ ${index + 1}`;
      open.append(image);
      open.addEventListener('click', () => openPreview(entry, index));

      remove.type = 'button';
      remove.className = 'install-complete-modal__preview-remove';
      remove.setAttribute('aria-label', `ลบรูปงานติดตั้งที่ ${index + 1}`);
      remove.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
      remove.addEventListener('click', () => {
        if (entry.kind === 'new') {
          URL.revokeObjectURL(entry.url);
        }
        selectedFiles.splice(index, 1);
        syncSelection();
      });

      figure.append(open, remove);
      preview.append(figure);
    });

    const hasFiles = selectedFiles.length > 0;
    preview.hidden = !hasFiles;
    setSubmitting(false);
  };

  const addSelectedFiles = (files) => {
    const hasInvalidType = files.some((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type));
    const hasInvalidSize = files.some((file) => file.size > 5 * 1024 * 1024);

    if (hasInvalidType) {
      setMessage('กรุณาเลือกไฟล์ JPG, PNG หรือ WebP เท่านั้น');
      return;
    }
    if (hasInvalidSize) {
      setMessage('แต่ละรูปต้องมีขนาดไม่เกิน 5 MB');
      return;
    }
    if (!maxFiles || selectedFiles.length + files.length > maxFiles) {
      setMessage(`อัปโหลดรูปได้สูงสุด ${maxFiles} รูป`);
      return;
    }

    files.forEach((file) => selectedFiles.push({ kind: 'new', file, url: URL.createObjectURL(file) }));
    setMessage();
    syncSelection();
  };

  const closeModal = () => {
    if (!isSubmitting && modal.open) {
      modal.close();
    }
  };

  openButton.addEventListener('click', () => {
    resetSelection();
    setMessage();
    modal.showModal();
  });

  addButton.addEventListener('click', () => {
    if (!maxFiles || selectedFiles.length >= maxFiles) {
      setMessage(`อัปโหลดรูปได้สูงสุด ${maxFiles} รูป`);
      return;
    }

    fileInput.click();
  });
  cancelButtons.forEach((button) => button.addEventListener('click', closeModal));

  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });

  modal.addEventListener('cancel', (event) => {
    if (isSubmitting) {
      event.preventDefault();
    }
  });

  modal.addEventListener('close', () => {
    if (!isSubmitting) {
      resetSelection();
      setMessage();
    }
  });

  fileInput.addEventListener('change', () => {
    const files = Array.from(fileInput.files || []);
    fileInput.value = '';
    if (files.length === 0) {
      return;
    }
    addSelectedFiles(files);
  });

  form.addEventListener('submit', (event) => {
    if (isSubmitting) {
      event.preventDefault();
      return;
    }
    if (selectedFiles.length === 0) {
      event.preventDefault();
      setMessage('กรุณาเพิ่มรูปงานติดตั้งอย่างน้อย 1 รูป');
      return;
    }

    setMessage('กำลังยืนยันรูปงานติดตั้ง...');
    setSubmitting(true);
  });

  resetSelection();

  previewCloseButton?.addEventListener('click', () => previewDialog.close());
  previewDialog?.addEventListener('click', (event) => {
    if (event.target === previewDialog) {
      previewDialog.close();
    }
  });
  previewDialog?.addEventListener('close', () => {
    if (previewImage) {
      previewImage.removeAttribute('src');
      previewImage.alt = '';
    }
  });
})();

(() => {
  'use strict';

  const openButton = document.querySelector('[data-installation-result-open]');
  const modal = document.querySelector('[data-installation-result-modal]');
  const closeButton = document.querySelector('[data-installation-result-close]');
  const modalStage = document.querySelector('[data-installation-result-stage]');
  const modalImage = document.querySelector('[data-installation-result-modal-image]');
  const previousButton = document.querySelector('[data-installation-result-prev]');
  const nextButton = document.querySelector('[data-installation-result-next]');
  const imageLinks = Array.from(document.querySelectorAll('[data-installation-result-image]'));
  const thumbnailButtons = Array.from(document.querySelectorAll('[data-installation-result-thumb]'));

  if (!modal || !closeButton || !modalStage || !modalImage || !previousButton || !nextButton || thumbnailButtons.length === 0) {
    return;
  }

  let currentIndex = 0;

  const renderImage = (index) => {
    const itemCount = thumbnailButtons.length;
    currentIndex = (index + itemCount) % itemCount;
    const item = thumbnailButtons[currentIndex];

    modalImage.src = item.dataset.imageSrc || '';
    modalImage.alt = item.dataset.imageAlt || 'รูปงานติดตั้ง';

    thumbnailButtons.forEach((thumbnail, thumbnailIndex) => {
      const isActive = thumbnailIndex === currentIndex;
      thumbnail.classList.toggle('is-active', isActive);
      thumbnail.toggleAttribute('aria-current', isActive);
      if (isActive) {
        thumbnail.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      }
    });

    const hasMultipleImages = itemCount > 1;
    previousButton.hidden = !hasMultipleImages;
    nextButton.hidden = !hasMultipleImages;
  };

  const openGallery = (index = 0) => {
    renderImage(index);
    if (!modal.open) {
      modal.showModal();
    }
    closeButton.focus();
  };

  imageLinks.forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      openGallery(Number(link.dataset.galleryIndex || 0));
    });
  });

  thumbnailButtons.forEach((thumbnail) => {
    thumbnail.addEventListener('click', () => {
      renderImage(Number(thumbnail.dataset.galleryIndex || 0));
    });
  });

  openButton?.addEventListener('click', () => openGallery());
  previousButton.addEventListener('click', () => renderImage(currentIndex - 1));
  nextButton.addEventListener('click', () => renderImage(currentIndex + 1));
  closeButton.addEventListener('click', () => modal.close());

  modalStage.addEventListener('click', (event) => {
    if (event.target === modalStage) {
      modal.close();
    }
  });

  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      modal.close();
    }
  });

  modal.addEventListener('close', () => {
    modalImage.removeAttribute('src');
    modalImage.alt = '';
    thumbnailButtons.forEach((thumbnail) => {
      thumbnail.classList.remove('is-active');
      thumbnail.removeAttribute('aria-current');
    });
    currentIndex = 0;
  });
})();
