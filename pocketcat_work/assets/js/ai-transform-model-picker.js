document.addEventListener('DOMContentLoaded', () => {
  const picker = document.querySelector('[data-transform-model-picker]');
  if (!picker) return;
  const search = picker.querySelector('[data-model-search]');
  const options = picker.querySelector('[data-model-options]');
  const hidden = document.querySelector('[name="model_id"]');
  const label = picker.querySelector('[data-model-current]');
  const custom = picker.querySelector('[data-model-custom]');
  const useCustom = picker.querySelector('[data-model-use-custom]');
  let selected = hidden?.value || '';

  function setModel(value) {
    const model = value.trim();
    if (!/^[A-Za-z0-9][A-Za-z0-9._:/-]{0,189}$/.test(model)) {
      custom.setCustomValidity('Enter a valid model ID.');
      custom.reportValidity();
      return;
    }
    custom.setCustomValidity('');
    selected = model;
    hidden.value = model;
    label.textContent = model;
    picker.open = false;
    render();
  }

  function render() {
    const term = search.value.trim().toLowerCase();
    options.replaceChildren();
    let count = 0;
    for (const [family, models] of window.POCKETCAT_AI_MODEL_CATALOG || []) {
      const filtered = models.filter((model) => `${family} ${model}`.toLowerCase().includes(term));
      if (!filtered.length) continue;
      const group = document.createElement('section');
      const heading = document.createElement('h4');
      heading.textContent = family;
      group.append(heading);
      for (const model of filtered) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ai-model-option';
        button.textContent = model;
        button.setAttribute('aria-selected', String(model === selected));
        button.addEventListener('click', () => setModel(model));
        group.append(button);
        count += 1;
      }
      options.append(group);
    }
    if (!count) {
      const empty = document.createElement('p');
      empty.className = 'muted';
      empty.textContent = 'No matching models.';
      options.append(empty);
    }
  }

  search.addEventListener('input', render);
  picker.addEventListener('toggle', () => {
    if (picker.open) {
      search.value = '';
      render();
      search.focus();
    }
  });
  useCustom.addEventListener('click', () => setModel(custom.value));
  custom.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      setModel(custom.value);
    }
  });
  label.textContent = selected;
  render();
});
