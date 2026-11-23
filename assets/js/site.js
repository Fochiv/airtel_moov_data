(() => {
    const filterButtons = [...document.querySelectorAll('[data-filter]')];
    const planCards = [...document.querySelectorAll('.plan-card[data-operator]')];

    function filterPlans(operator) {
        planCards.forEach((card) => {
            card.hidden = operator !== 'tous' && card.dataset.operator !== operator;
        });
        filterButtons.forEach((button) => button.classList.toggle('active', button.dataset.filter === operator));
    }

    filterButtons.forEach((button) => button.addEventListener('click', () => filterPlans(button.dataset.filter)));
    document.querySelectorAll('[data-operator-link]').forEach((link) => {
        link.addEventListener('click', () => {
            const operator = link.dataset.operatorLink;
            filterPlans(operator);
            try {
                sessionStorage.setItem('gooddeal-operator', operator);
            } catch {
                // Filtering remains functional if browser storage is disabled.
            }
        });
    });
    let savedOperator = null;
    try {
        savedOperator = sessionStorage.getItem('gooddeal-operator');
    } catch {
        // The page still works without browser storage.
    }
    if (savedOperator && ['airtel', 'moov'].includes(savedOperator)) filterPlans(savedOperator);

    const modalElement = document.getElementById('purchaseModal');
    const purchaseForm = document.getElementById('purchaseForm');
    if (modalElement && purchaseForm && window.bootstrap) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const packageId = document.getElementById('packageId');
        const summary = document.getElementById('selectedPlanSummary');
        const feedback = document.getElementById('purchaseFeedback');
        const submit = document.getElementById('purchaseSubmit');
        document.querySelectorAll('[data-buy]').forEach((button) => {
            button.addEventListener('click', () => {
                packageId.value = button.dataset.packageId;
                summary.innerHTML = '';
                const label = document.createElement('strong');
                label.textContent = button.dataset.packageName;
                const price = document.createElement('span');
                price.textContent = button.dataset.packagePrice;
                summary.append(label, price);
                feedback.textContent = '';
                modal.show();
            });
        });
        purchaseForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            feedback.textContent = '';
            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
            const original = submit.innerHTML;
            submit.textContent = window.GooddealData?.lang === 'en' ? 'Please wait…' : 'Veuillez patienter…';
            try {
                const response = await fetch(purchaseForm.action, {
                    method: 'POST',
                    body: new FormData(purchaseForm),
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                let result;
                try {
                    result = await response.json();
                } catch {
                    throw new Error(window.GooddealData?.copy?.serviceError);
                }
                if (!response.ok || !result.success) {
                    throw new Error(result.message || window.GooddealData?.copy?.serviceError);
                }
                if (result.redirect_url) {
                    window.location.assign(result.redirect_url);
                } else {
                    window.location.assign(result.status_url);
                }
            } catch (error) {
                feedback.textContent = error.message || window.GooddealData?.copy?.serviceError;
                submit.disabled = false;
                submit.removeAttribute('aria-busy');
                submit.innerHTML = original;
            }
        });
    }

    const adminTrigger = document.querySelector('[data-admin-trigger]');
    if (adminTrigger) {
        let clickCount = 0;
        let resetTimer;
        const trigger = () => {
            clickCount += 1;
            window.clearTimeout(resetTimer);
            if (clickCount >= 5) {
                window.location.assign('/admin/login.php');
                return;
            }
            resetTimer = window.setTimeout(() => { clickCount = 0; }, 1200);
        };
        adminTrigger.addEventListener('click', trigger);
        adminTrigger.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                trigger();
            }
        });
    }

    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (!window.confirm(element.dataset.confirm)) event.preventDefault();
        });
    });
    document.querySelectorAll('[data-edit-package]').forEach((button) => button.addEventListener('click', () => {
        const form = document.getElementById('packageForm');
        const modal = document.getElementById('packageModal');
        if (!form || !modal) return;
        form.elements.namedItem('id').value = button.dataset.id;
        form.elements.namedItem('operator').value = button.dataset.operator;
        form.elements.namedItem('price').value = button.dataset.price;
        form.elements.namedItem('data_volume').value = button.dataset.volume;
        form.elements.namedItem('validity').value = button.dataset.validity;
        form.elements.namedItem('description').value = button.dataset.description;
        form.elements.namedItem('active').checked = button.dataset.active === '1';
        document.getElementById('packageModalTitle').textContent = window.GooddealData?.lang === 'en' ? 'Edit plan' : 'Modifier le forfait';
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }));
    const packageModal = document.getElementById('packageModal');
    if (packageModal) {
        packageModal.addEventListener('hidden.bs.modal', () => {
            const form = document.getElementById('packageForm');
            if (!form) return;
            form.reset();
            form.elements.namedItem('id').value = '';
            document.getElementById('packageModalTitle').textContent = window.GooddealData?.lang === 'en' ? 'New plan' : 'Nouveau forfait';
            form.elements.namedItem('active').checked = true;
        });
    }
    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        button.addEventListener('click', () => document.body.classList.toggle('admin-menu-open'));
    });
    const adminOrders = document.querySelector('[data-admin-orders-refresh]');
    if (adminOrders) {
        const refreshDelay = Math.max(10000, Number(adminOrders.dataset.adminOrdersRefresh) || 30000);
        const orderSearchForm = adminOrders.querySelector('.order-search');
        let filtersEdited = false;
        orderSearchForm?.addEventListener('input', () => { filtersEdited = true; });
        orderSearchForm?.addEventListener('change', () => { filtersEdited = true; });
        const refreshOrders = () => {
            const activeElement = document.activeElement;
            if (document.visibilityState === 'visible' && !filtersEdited && !activeElement?.closest('.order-search')) {
                window.location.reload();
                return;
            }
            window.setTimeout(refreshOrders, 5000);
        };
        window.setTimeout(refreshOrders, refreshDelay);
    }
})();
