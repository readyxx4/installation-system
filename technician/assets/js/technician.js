(() => {
  'use strict';

  document.querySelectorAll('[data-technician-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
      const message = element.dataset.technicianConfirm || '';

      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  });

  const notification = document.querySelector('[data-technician-notification]');
  if (notification) {
    window.requestAnimationFrame(() => {
      notification.classList.add('show');
    });

    window.setTimeout(() => {
      notification.classList.add('hide');
      notification.classList.remove('show');
    }, 4500);
  }

  const notificationToggle = document.querySelector('[data-technician-notification-toggle]');
  const notificationDropdown = document.querySelector('[data-technician-notification-dropdown]');
  if (notificationToggle && notificationDropdown) {
    const positionNotificationDropdown = () => {
      const rect = notificationToggle.getBoundingClientRect();
      const gap = 12;
      const edgeGap = window.matchMedia('(max-width: 760px)').matches ? 12 : 28;
      notificationDropdown.style.setProperty('--technician-notification-top', `${rect.bottom + gap}px`);
      notificationDropdown.style.setProperty('--technician-notification-right', `${Math.max(edgeGap, window.innerWidth - rect.right)}px`);
    };

    notificationToggle.addEventListener('click', (event) => {
      event.stopPropagation();
      positionNotificationDropdown();
      const isOpen = notificationDropdown.classList.toggle('show');
      notificationToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    notificationDropdown.addEventListener('click', (event) => {
      event.stopPropagation();
    });

    window.addEventListener('resize', () => {
      if (notificationDropdown.classList.contains('show')) {
        positionNotificationDropdown();
      }
    });

    document.addEventListener('click', () => {
      notificationDropdown.classList.remove('show');
      notificationToggle.setAttribute('aria-expanded', 'false');
    });
  }
  const historyCards = Array.from(document.querySelectorAll('[data-history-item], [data-technician-history-card]'));
  const historySearch = document.querySelector('[data-history-search]');
  if (historyCards.length && historySearch) {
    const form = historySearch.closest('form');
    const emptyState = document.querySelector('[data-history-search-empty]');
    let activeStatus = 'all';

    const updateHistoryCards = () => {
      const query = historySearch.value.trim().toLowerCase();
      let visibleCount = 0;

      historyCards.forEach((card) => {
        const status = card.dataset.historyStatus || '';
        const text = card.dataset.historySearchText || '';
        const matchesStatus = activeStatus === 'all' || status === activeStatus;
        const matchesSearch = query === '' || text.includes(query);
        const shouldShow = matchesStatus && matchesSearch;

        card.style.display = shouldShow ? '' : 'none';

        if (shouldShow) {
          visibleCount += 1;
        }
      });

      if (emptyState) {
        emptyState.style.display = visibleCount === 0 ? '' : 'none';
      }
    };

    document.querySelectorAll('[data-history-filter]').forEach((button) => {
      button.addEventListener('click', () => {
        activeStatus = button.dataset.historyFilter || 'all';

        document.querySelectorAll('[data-history-filter]').forEach((tab) => {
          tab.classList.remove('active');
        });

        button.classList.add('active');
        updateHistoryCards();
      });
    });

    historySearch.addEventListener('input', updateHistoryCards);

    form?.addEventListener('submit', (event) => {
      event.preventDefault();
      updateHistoryCards();
    });

    document.querySelectorAll('[data-history-search-reset]').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        activeStatus = 'all';
        historySearch.value = '';

        document.querySelectorAll('[data-history-filter]').forEach((tab) => {
          tab.classList.toggle('active', (tab.dataset.historyFilter || 'all') === 'all');
        });

        updateHistoryCards();
        historySearch.focus();
      });
    });

    updateHistoryCards();
  }
  const focusedAssignmentRow = document.querySelector('[data-technician-focus-row]');
  if (focusedAssignmentRow) {
    window.requestAnimationFrame(() => {
      focusedAssignmentRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  }
})();
