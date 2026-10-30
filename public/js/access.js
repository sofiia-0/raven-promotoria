(() => {
    const inScope = () => document.body.matches('.raven-access, .raven-admin');
    if (!inScope()) return;
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-access-password-toggle]');
        if (!button) return;
        const field = document.getElementById(button.getAttribute('aria-controls'));
        if (!field) return;
        const reveal = field.type === 'password';
        const start = field.selectionStart, end = field.selectionEnd;
        field.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(reveal));
        button.setAttribute('aria-label', reveal ? button.dataset.hideLabel : button.dataset.showLabel);
        button.classList.toggle('is-revealed', reveal);
        try { field.setSelectionRange(start, end); } catch (_) { /* El navegador conserva el valor. */ }
    });
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!form.matches('[data-access-submit]')) return;
        if (form.dataset.pending === 'true') { event.preventDefault(); return; }
        form.dataset.pending = 'true';
        form.setAttribute('aria-busy', 'true');
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            const label = button.querySelector('[data-submit-label]');
            if (label) { label.dataset.originalLabel = label.textContent; label.textContent = button.dataset.pendingLabel || 'Procesando…'; }
        }
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-access-submit]').forEach(form => {
            delete form.dataset.pending;
            form.removeAttribute('aria-busy');
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = false;
            const label = button?.querySelector('[data-submit-label]');
            if (label?.dataset.originalLabel) label.textContent = label.dataset.originalLabel;
        });
        document.querySelectorAll('[data-access-password-toggle]').forEach(button => {
            const field = document.getElementById(button.getAttribute('aria-controls'));
            if (field) field.type = 'password';
            button.setAttribute('aria-pressed', 'false');
            button.setAttribute('aria-label', button.dataset.showLabel);
            button.classList.remove('is-revealed');
        });
    });
    window.addEventListener('admin-user-editor-open', () => requestAnimationFrame(() => requestAnimationFrame(() => {
        const heading = document.getElementById('admin-editor-title');
        heading?.focus({preventScroll:true});
        heading?.closest('[data-user-editor]')?.scrollIntoView({block:'start', behavior:matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
    })));
    window.addEventListener('admin-user-editor-closed', event => requestAnimationFrame(() => requestAnimationFrame(() => {
        const target = event.detail?.userId ? document.getElementById('admin-edit-'+event.detail.userId) : null;
        (target || document.getElementById('admin-create-user'))?.focus({preventScroll:true});
    })));
    const error = document.querySelector('[data-access-errors]');
    if (error) error.focus();
})();
