function showSetupInfo() {
  const setupSelect = document.getElementById('setup_id');
  const selected = setupSelect.options[setupSelect.selectedIndex];

  if (!selected) {
    return;
  }

  document.getElementById('customer_show').value = selected.dataset.customer || '-';
  document.getElementById('phone_show').value = selected.dataset.phone || '-';
  document.getElementById('product_show').value = selected.dataset.product || '-';

  const oldStatus = selected.dataset.paymentStatus;

  if (oldStatus !== '') {
    document.getElementById('paymentpro_status').value = oldStatus;
  }
}

