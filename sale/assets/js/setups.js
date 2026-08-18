document.querySelectorAll('[data-confirm-cancel-setup]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        const setupId = form.querySelector('input[name="setup_id"]')?.value || '';
        const message = setupId
            ? `ยืนยันยกเลิกใบงาน ${setupId} หรือไม่?\n\nระบบจะเปลี่ยนสถานะเป็นยกเลิกและเก็บประวัติใบงานไว้ทั้งหมด`
            : 'ยืนยันยกเลิกใบงานนี้หรือไม่?';

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-setup-search]').forEach(function (input) {
    const form = input.closest('form');
    const rows = Array.from(document.querySelectorAll('#setupStatusRows tr[data-setup-search-text]'));
    const emptyRow = document.querySelector('[data-setup-search-empty]');
    const countNodes = document.querySelectorAll('[data-setup-visible-count]');

    const updateRows = function () {
        const query = input.value.trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach(function (row) {
            const text = row.dataset.setupSearchText || '';
            const shouldShow = query === '' || text.includes(query);
            row.style.display = shouldShow ? '' : 'none';

            if (shouldShow) {
                visibleCount += 1;
            }
        });

        if (emptyRow) {
            emptyRow.style.display = visibleCount === 0 ? '' : 'none';
        }

        countNodes.forEach(function (node) {
            node.textContent = `${visibleCount} รายการ`;
        });
    };

    input.addEventListener('input', updateRows);

    form?.addEventListener('submit', function (event) {
        event.preventDefault();
        updateRows();
    });

    document.querySelectorAll('[data-setup-search-reset]').forEach(function (button) {
        button.addEventListener('click', function () {
            input.value = '';
            updateRows();
            input.focus();
        });
    });

    updateRows();
});

(function () {
    const historyRows = Array.from(document.querySelectorAll('tr[data-history-status][data-history-search-text]'));
    const historySearch = document.querySelector('[data-history-search]');

    if (!historyRows.length || !historySearch) {
        return;
    }

    const form = historySearch.closest('form');
    const emptyRow = document.querySelector('[data-history-search-empty]');
    const countNodes = document.querySelectorAll('[data-history-visible-count]');
    let activeStatus = 'all';

    const updateHistoryRows = function () {
        const query = historySearch.value.trim().toLowerCase();
        let visibleCount = 0;

        historyRows.forEach(function (row) {
            const status = row.dataset.historyStatus || '';
            const text = row.dataset.historySearchText || '';
            const matchesStatus = activeStatus === 'all' || status === activeStatus;
            const matchesSearch = query === '' || text.includes(query);
            const shouldShow = matchesStatus && matchesSearch;

            row.style.display = shouldShow ? '' : 'none';

            if (shouldShow) {
                visibleCount += 1;
            }
        });

        if (emptyRow) {
            emptyRow.style.display = visibleCount === 0 ? '' : 'none';
        }

        countNodes.forEach(function (node) {
            node.textContent = `${visibleCount} รายการ`;
        });
    };

    document.querySelectorAll('[data-history-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            activeStatus = this.dataset.historyFilter || 'all';

            document.querySelectorAll('[data-history-filter]').forEach(function (tab) {
                tab.classList.remove('active');
            });

            this.classList.add('active');
            updateHistoryRows();
        });
    });

    historySearch.addEventListener('input', updateHistoryRows);

    form?.addEventListener('submit', function (event) {
        event.preventDefault();
        updateHistoryRows();
    });

    document.querySelectorAll('[data-history-search-reset]').forEach(function (button) {
        button.addEventListener('click', function () {
            historySearch.value = '';
            updateHistoryRows();
            historySearch.focus();
        });
    });

    updateHistoryRows();
})();
