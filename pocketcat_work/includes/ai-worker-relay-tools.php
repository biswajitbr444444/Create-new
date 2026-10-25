<section class="ai-worker-tools" aria-labelledby="ai-worker-tools-title">
    <h3 id="ai-worker-tools-title">Cloudflare Worker Code Generator</h3>
    <p class="muted">Generate a Worker that forwards PocketCat's selected model dynamically. The provider key is only used in this browser to create the code; it is not saved by this page.</p>
    <div class="grid2 ai-worker-generator-fields">
        <label>Upstream API Base URL<input type="url" data-worker-base placeholder="https://provider.example/v1" autocomplete="url" spellcheck="false"></label>
        <label>Upstream API Key<input type="password" data-worker-api-key placeholder="Enter your provider API key" autocomplete="new-password"></label>
    </div>
    <div class="actions ai-worker-generator-actions">
        <button class="btn secondary" type="button" data-worker-random-token>Generate Random Shared Token</button>
        <button class="btn" type="button" data-worker-generate>Generate Cloudflare Worker Code</button>
    </div>
    <div class="ai-worker-code-wrap" data-worker-code-wrap hidden>
        <label>Generated worker.js code<textarea data-worker-code rows="16" readonly spellcheck="false"></textarea></label>
        <button class="btn secondary" type="button" data-worker-copy>Copy Code</button>
        <p class="muted">The generated code contains your provider API key and relay token. Store and deploy it as a secret; do not publish it.</p>
    </div>
    <div class="ai-worker-guide">
        <h4>Step-by-step setup</h4>
        <ol>
            <li>Enter your provider Base URL and API Key above, choose or generate a Relay Shared Token, then generate and copy the Worker code.</li>
            <li>Open Cloudflare and sign in or create a free account. You do not need a custom domain; the free <code>workers.dev</code> subdomain works.</li>
            <li>In <strong>Workers &amp; Pages</strong>, choose <strong>Create</strong> → <strong>Create Worker</strong>, name it, and deploy.</li>
            <li>Choose <strong>Edit Code</strong>, replace the starter code in <code>worker.js</code> with the copied code, then deploy again.</li>
            <li>Copy the Worker URL into <strong>Relay URL</strong>, ensure the Relay Shared Token matches, save settings, and test the connection.</li>
        </ol>
        <div class="actions">
            <a class="btn secondary" href="https://dash.cloudflare.com/?to=/:account/workers-and-pages" target="_blank" rel="noopener noreferrer">Open Cloudflare Workers Dashboard</a>
            <a class="btn secondary" href="https://dash.cloudflare.com/sign-up/workers-and-pages" target="_blank" rel="noopener noreferrer">Create Free Cloudflare Account</a>
        </div>
    </div>
</section>
