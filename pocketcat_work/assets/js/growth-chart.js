(() => {
  const charts = [...document.querySelectorAll('[data-growth-chart]')];
  if (!charts.length) return;
  const safeText = (node, value) => { if (node) node.textContent = String(value ?? ''); };
  function render(chart, data) {
    safeText(chart.querySelector('[data-growth-title]'), data.title);
    safeText(chart.querySelector('[data-growth-description]'), data.description);
    const badge = chart.querySelector('[data-growth-badge]');
    if (badge) badge.hidden = !data.show_badge;
    const holder = chart.querySelector('[data-growth-bars]');
    if (!holder || !Array.isArray(data.series) || !data.series.length) return;
    const max = Math.max(1, ...data.series.map(item => Number(item.value) || 0));
    holder.replaceChildren(...data.series.map(item => {
      const col = document.createElement('div'); col.className = 'chart-col';
      const value = document.createElement('span'); value.className = 'chart-value';
      value.textContent = (Number(item.value) || 0).toLocaleString();
      const bar = document.createElement('div'); bar.className = 'bar';
      bar.style.height = `${Math.max(8, Math.round((Number(item.value) || 0) / max * 100))}%`;
      const label = document.createElement('span'); label.className = 'chart-label'; label.textContent = String(item.label ?? '');
      col.append(value, bar, label); return col;
    }));
  }
  let inFlight = false;
  async function refresh() {
    if (inFlight || document.visibilityState === 'hidden') return;
    inFlight = true;
    try {
      const response = await fetch('/api/growth.php', { cache: 'no-store', headers: { Accept: 'application/json' } });
      if (!response.ok) return;
      const data = await response.json();
      if (data && data.ok) charts.forEach(chart => render(chart, data));
    } catch (_) { /* Keep the server-rendered chart if polling is temporarily unavailable. */ }
    finally { inFlight = false; }
  }
  refresh();
  window.setInterval(refresh, 5000);
  document.addEventListener('visibilitychange', refresh);
})();
