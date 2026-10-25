(() => {
  const targets = [...document.querySelectorAll('[data-platform-stat]')];
  if (!targets.length) return;
  let inFlight = false;
  async function refresh() {
    if (inFlight || document.visibilityState === 'hidden') return;
    inFlight = true;
    try {
      const response = await fetch('/api/stats.php', { cache: 'no-store', headers: { Accept: 'application/json' } });
      if (!response.ok) return;
      const data = await response.json();
      targets.forEach((node) => {
        const key = node.getAttribute('data-platform-stat');
        if (Object.prototype.hasOwnProperty.call(data, key)) node.textContent = Number(data[key] || 0).toLocaleString();
      });
      const homepageSection = document.querySelector('[data-live-workspace-section="homepage"]');
      if (homepageSection) homepageSection.hidden = data.show_homepage_section === false;
    } catch (_) { /* Keep server-rendered values if the API is temporarily unavailable. */ }
    finally { inFlight = false; }
  }
  refresh();
  window.setInterval(refresh, 5000);
  document.addEventListener('visibilitychange', refresh);
})();
