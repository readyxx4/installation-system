document.querySelectorAll('.cs-status-tab').forEach(function (button) {
    button.addEventListener('click', function () {
        const filter = this.dataset.filter || 'all';

        document.querySelectorAll('.cs-status-tab').forEach(function (tab) {
            tab.classList.remove('active');
        });

        this.classList.add('active');

        document.querySelectorAll('#setupStatusRows tr[data-status-group]').forEach(function (row) {
            const group = row.dataset.statusGroup || 'all';
            row.style.display = (filter === 'all' || group === filter) ? '' : 'none';
        });
    });
});

