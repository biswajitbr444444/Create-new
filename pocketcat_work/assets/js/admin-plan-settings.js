(() => {
  'use strict';
  const form = document.getElementById('plan-settings-form');
  if (!form || !window.fetch || !window.FormData) return;

  const saveButtons = [...form.querySelectorAll('button[type="submit"]')];
  let toastTimer;
  const toast = (message, isError = false) => {
    let node = document.getElementById('plan-save-toast');
    if (!node) {
      node = document.createElement('div');
      node.id = 'plan-save-toast';
      node.className = 'plan-save-toast';
      node.setAttribute('role', 'status');
      node.setAttribute('aria-live', 'polite');
      document.body.appendChild(node);
    }
    node.textContent = message;
    node.dataset.error = isError ? 'true' : 'false';
    node.classList.add('visible');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => node.classList.remove('visible'), 5000);
  };

  try {
    if (window.sessionStorage.getItem('pc-plan-settings-saved') === '1') {
      window.sessionStorage.removeItem('pc-plan-settings-saved');
      toast('Plan settings saved. Public pricing, billing and the homepage now use the updated catalogue.');
    }
  } catch (_) { /* Storage can be unavailable in private browsing; saving still works. */ }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(form);
    const payload = { plan_meta: {}, plans: {} };
    const keys = ['free', 'basic', 'premium'];
    for (const key of keys) {
      payload.plan_meta[key] = {
        name: String(formData.get(`meta[${key}][name]`) || '').trim(),
        price: String(formData.get(`meta[${key}][price]`) || '').trim(),
        period: String(formData.get(`meta[${key}][period]`) || 'month'),
        description: String(formData.get(`meta[${key}][description]`) || '').trim(),
        enabled: form.querySelector(`input[name="meta[${key}][enabled]"]`)?.checked === true
      };
      payload.plans[key] = {};
    }

    form.querySelectorAll('input[name^="plans["]').forEach((input) => {
      const match = input.name.match(/^plans\[(free|basic|premium)\]\[([^\]]+)\]$/);
      if (!match) return;
      const [, plan, feature] = match;
      payload.plans[plan][feature] = input.type === 'checkbox'
        ? input.checked
        : String(input.value).trim();
    });

    saveButtons.forEach((button) => {
      button.disabled = true;
      button.dataset.originalLabel = button.textContent;
      button.textContent = 'Saving…';
    });
    try {
      const csrf = form.querySelector('input[name="_csrf"]')?.value || window.POCKETCAT_CSRF || '';
      const response = await fetch('/api/admin/plans', {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(payload)
      });
      const raw = await response.text();
      let result;
      try { result = raw ? JSON.parse(raw) : {}; }
      catch (_) { throw new Error(raw.replace(/<[^>]*>/g, ' ').trim() || `Save failed (${response.status}).`); }
      if (!response.ok || result.success !== true) throw new Error(result.error || result.message || `Save failed (${response.status}).`);
      try { window.sessionStorage.setItem('pc-plan-settings-saved', '1'); } catch (_) { /* optional */ }
      toast(result.message || 'Plan settings saved successfully.');
      window.setTimeout(() => window.location.reload(), 450);
    } catch (error) {
      toast(error?.message || 'Could not save plan settings. Check your connection and try again.', true);
      saveButtons.forEach((button) => {
        button.disabled = false;
        button.textContent = button.dataset.originalLabel || 'Save Plan Settings';
      });
    }
  });
})();
