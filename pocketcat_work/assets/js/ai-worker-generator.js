document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.ai-worker-tools').forEach((card) => {
    const base = card.querySelector('[data-worker-base]');
    const apiKey = card.querySelector('[data-worker-api-key]');
    const codeWrap = card.querySelector('[data-worker-code-wrap]');
    const code = card.querySelector('[data-worker-code]');
    const relayToken = card.closest('[data-connection-panel="relay"]')?.querySelector('[name="bedrock_relay_token"]');
    const randomButton = card.querySelector('[data-worker-random-token]');
    const generateButton = card.querySelector('[data-worker-generate]');
    const copyButton = card.querySelector('[data-worker-copy]');

    function tokenValue() {
      if (relayToken?.dataset.masked === '1') return '';
      return relayToken?.value.trim() || '';
    }

    randomButton?.addEventListener('click', () => {
      if (!relayToken || tokenValue()) return;
      const bytes = crypto.getRandomValues(new Uint8Array(32));
      relayToken.value = Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('');
      relayToken.dataset.masked = '0';
      relayToken.dispatchEvent(new Event('input', { bubbles: true }));
    });

    generateButton?.addEventListener('click', () => {
      const baseUrl = base?.value.trim().replace(/\/+$/, '') || '';
      const key = apiKey?.value || '';
      const token = tokenValue();
      let parsed;
      try {
        parsed = new URL(baseUrl);
      } catch {
        base?.setCustomValidity('Enter a valid HTTPS API base URL.');
        base?.reportValidity();
        return;
      }
      base?.setCustomValidity('');
      if (parsed.protocol !== 'https:' || parsed.username || parsed.password || parsed.search || parsed.hash) {
        base?.setCustomValidity('Use an HTTPS API base URL without embedded credentials, query parameters, or a fragment.');
        base?.reportValidity();
        return;
      }
      if (!key) {
        apiKey?.setCustomValidity('Enter the upstream provider API key.');
        apiKey?.reportValidity();
        return;
      }
      apiKey?.setCustomValidity('');
      if (!token) {
        relayToken?.setCustomValidity('Enter a Relay Shared Token or generate one above.');
        relayToken?.reportValidity();
        return;
      }
      relayToken?.setCustomValidity('');
      const config = JSON.stringify({ upstreamBaseUrl: baseUrl, upstreamApiKey: key, sharedToken: token }, null, 2);
      code.value = `const CONFIG = ${config};

export default {
  async fetch(request) {
    if (request.method !== "POST") {
      return new Response("Method not allowed", { status: 405 });
    }
    if (request.headers.get("x-pocketcat-relay-token") !== CONFIG.sharedToken) {
      return new Response(JSON.stringify({ error: "Unauthorized relay request" }), {
        status: 401,
        headers: { "content-type": "application/json; charset=utf-8" }
      });
    }

    const incomingUrl = new URL(request.url);
    const endpoint = incomingUrl.pathname.endsWith("/responses")
      ? "/responses"
      : "/chat/completions";
    const base = CONFIG.upstreamBaseUrl.replace(/\\/+$/, "");
    const upstreamUrl = base + endpoint;
    const headers = new Headers(request.headers);
    headers.delete("x-pocketcat-relay-token");
    headers.delete("host");
    headers.delete("content-length");
    headers.set("authorization", \`Bearer \${CONFIG.upstreamApiKey}\`);
    headers.set("content-type", "application/json");
    headers.set("accept", request.headers.get("accept") || "application/json");
    headers.set("user-agent", "PocketCat-Cloudflare-Relay/1.0");

    try {
      const response = await fetch(upstreamUrl, {
        method: "POST",
        headers,
        body: await request.arrayBuffer(),
        redirect: "error"
      });
      const responseHeaders = new Headers(response.headers);
      responseHeaders.delete("set-cookie");
      responseHeaders.set("x-content-type-options", "nosniff");
      return new Response(response.body, {
        status: response.status,
        headers: responseHeaders
      });
    } catch {
      return new Response(JSON.stringify({ error: "The upstream AI provider is unavailable." }), {
        status: 502,
        headers: { "content-type": "application/json; charset=utf-8" }
      });
    }
  }
};`;
      codeWrap.hidden = false;
      code.focus();
      code.select();
    });

    copyButton?.addEventListener('click', async () => {
      if (!code?.value) return;
      try {
        await navigator.clipboard.writeText(code.value);
        copyButton.textContent = 'Copied';
        window.setTimeout(() => { copyButton.textContent = 'Copy Code'; }, 1200);
      } catch {
        code.focus();
        code.select();
        document.execCommand('copy');
      }
    });
  });
});
