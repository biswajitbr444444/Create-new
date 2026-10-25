<?php
require __DIR__.'/../bootstrap.php';
require_login();
policy_require_flag('url_engine');
$title='Code Editor';
require __DIR__.'/../includes/header.php';
$ct=policy_data()['code_theme'];
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="/assets/js/code-byod.js" defer></script>
<link id="prismDay" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css">
<link id="prismNight" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" disabled>
<link rel="stylesheet" href="/assets/css/code-editor.css?v=16.3">
<link rel="stylesheet" href="/assets/css/workspace-v11.css">
<style id="pc-code-governance">
:root{--ce-accent:<?=htmlspecialchars($ct['accent'],ENT_QUOTES)?>;--ce-bg:<?=htmlspecialchars($ct['light_bg'],ENT_QUOTES)?>;font-family:<?=htmlspecialchars($ct['font_family'],ENT_QUOTES)?>}
html[data-code-theme="night"]{--ce-bg:<?=htmlspecialchars($ct['dark_bg'],ENT_QUOTES)?>;--ce-accent:<?=htmlspecialchars($ct['accent'],ENT_QUOTES)?>}
</style>

<main class="code-app" id="codeApp">
  <section class="code-studio" id="mainContainer">
    <div class="code-authbar">
      <div class="code-user"><span class="dot"></span><span>Code Studio</span><span class="user-location">Secure workspace</span></div>
      <a class="code-dashboard" href="/dashboard/">Dashboard</a>
    </div>

    <header class="code-header">
      <div class="code-header-title">Live Code Editor Pro</div>
      <div class="code-actions primary-actions">
        <button class="ce-btn theme-btn" id="themeBtn" type="button">🌙 Night Mode</button>
        <button class="ce-btn resize-btn" id="resizeBtn" type="button">Resize</button>
        <button class="ce-btn word-count-btn" id="wordCountBtn" type="button">Word Count</button>
        <button class="ce-btn history-btn" id="historyBtn" type="button">History</button>
        <button class="ce-btn history-btn" id="myCodeBtn" type="button">My Code</button>
        <button class="ce-btn search-panel-btn" id="searchBtn" type="button">Search</button>
        <button class="ce-btn settings-btn" id="settingsBtn" type="button">⚙️ Settings</button>
        <button class="ce-btn new-btn" id="newBtn" type="button">New</button>
        <button class="ce-btn edit-btn" id="editBtn" type="button">Edit Code</button>
        <button class="ce-btn save-btn" id="saveBtn" type="button">Publish</button>
        <button class="ce-btn run-btn" id="runBtn" type="button">🚀 Run Preview</button>
        <button class="ce-btn share-btn" id="shareBtn" type="button">🔗 Share</button>
        <button class="ce-btn copy-link-btn" id="copyLinkBtn" type="button">📋 Copy Link</button>
      </div>
      <div class="export-action-group">
        <select id="fileFormatSelect" class="format-selector" aria-label="Export format"></select>
        <button class="ce-btn copy-code-btn" id="copyCodeBtn" type="button">Copy Code</button>
        <button class="ce-btn download-btn" id="downloadBtn" type="button">Download</button>
      </div>
    </header>

    <div class="panel-stack">
      <section class="ce-panel" id="sizePanel" hidden>
        <div class="panel-title">Editor Size Options</div>
        <div class="button-grid" id="sizeButtons">
          <button type="button" data-size="default" class="active">Default Size</button>
          <button type="button" data-size="medium">Medium Mode</button>
          <button type="button" data-size="half">Half Screen</button>
          <button type="button" data-size="desktop">Desktop / Large</button>
          <button type="button" data-size="full">Full Screen</button>
        </div>
      </section>

      <section class="ce-panel" id="wordCountPanel" hidden>
        <div class="panel-title">Text Statistics</div>
        <div class="word-count-grid">
          <div>Total Lines <strong id="wcLines">0</strong></div>
          <div>Total Words <strong id="wcWords">0</strong></div>
          <div>Characters (All) <strong id="wcCharsAll">0</strong></div>
          <div>Characters (No Spaces) <strong id="wcCharsNoSpace">0</strong></div>
        </div>
      </section>

      <section class="ce-panel" id="historyPanel" hidden>
        <div class="panel-title panel-title-row">
          <span>Code History</span>
          <div class="panel-title-controls">
            <button class="ce-mini danger" id="historyBulkBtn" type="button">🗑️ Select &amp; Delete</button>
            <select id="historySort"><option value="views">Most Viewed</option><option value="newest">Newest First</option><option value="oldest">Oldest First</option></select>
            <select id="historyLimit"><option value="10">Top 10</option><option value="20" selected>Top 20</option><option value="30">Top 30</option><option value="50">Top 50</option></select>
          </div>
        </div>
        <div class="bulk-controls" id="historyBulkControls" hidden>
          <span>Bulk Mode</span><button class="ce-mini" id="selectAllBtn" type="button">Select All</button><button class="ce-mini danger" id="deleteSelectedBtn" type="button" disabled>Delete Selected</button><button class="ce-mini" id="cancelBulkBtn" type="button">Cancel</button>
        </div>
        <div class="pagination-controls"><button id="prevPage" type="button">« Prev Page</button><button id="prevItem" type="button">‹ Prev 1</button><span id="historyPageInfo">0 - 0 of 0</span><button id="nextItem" type="button">Next 1 ›</button><button id="nextPage" type="button">Next Page »</button></div>
        <div class="history-list" id="historyList"></div><div class="version-head">Version History</div><div class="version-list" id="versionList"></div>
      </section>

      <section class="ce-panel" id="searchPanel" hidden>
        <div class="panel-title">Advanced Search &amp; Filters</div>
        <div class="search-controls"><input id="searchInput" type="search" placeholder="Search by Title..." autocomplete="off"><select id="searchSort"><option value="views">Most Viewed</option><option value="newest">Newest First</option><option value="oldest">Oldest First</option></select><select id="searchLimit"><option value="10">Limit: 10</option><option value="20" selected>Limit: 20</option><option value="50">Limit: 50</option></select></div>
        <div class="history-list" id="searchResultsList"></div>
      </section>

      <section class="ce-panel" id="settingsPanel" hidden>
        <div class="panel-title">Advanced Paste Settings</div>
        <div class="settings-grid advanced-grid">
          <div class="setting-group"><label for="advTags">Custom Tags (Comma separated)</label><input id="advTags" placeholder="e.g. hack, python, script"></div>
          <div class="setting-group"><label for="advCategory">Category</label><input id="advCategory" list="categoryOptions" placeholder="Search or create category"><datalist id="categoryOptions"></datalist></div>
          <div class="setting-group"><label for="advSyntax">Syntax Highlighting</label><select id="advSyntax"></select></div>
          <div class="setting-group"><label for="advExpiration">Paste Expiration</label><select id="advExpiration"></select></div>
          <div class="setting-group"><label for="advPassword">Password Protection (Optional)</label><div class="password-wrapper"><input id="advPassword" type="password" placeholder="Set a custom password"><button class="toggle-pwd" id="togglePwd" type="button">Show</button></div></div>
          <div class="setting-group"><label for="advExposure">Paste Exposure</label><select id="advExposure"><option value="public">Public (Default)</option><option value="unlisted">Unlisted</option><option value="private">Private</option></select><small>Login is required for this editor. Private content is owner-only.</small></div>
        </div>
        <div class="settings-footer"><span>Settings are synced with the editor fields below.</span><button class="ce-mini primary" id="applySettingsBtn" type="button">Apply Settings</button></div>
      </section>

      <section class="ce-panel" id="myCodePanel" hidden>
        <div class="panel-title panel-title-row"><span id="myCodePanelTitle">My Code</span><select id="myCodeFilter" aria-label="My Code filter"><option value="all">All My Code</option><option value="new">My New Code</option><option value="forked">My Edited Code (Forks)</option></select></div>
        <div class="history-list" id="myCodeList"></div>
      </section>
    </div>

    <div class="title-bar">
      <label for="codeTitle">Title:</label>
      <input type="text" id="codeTitle" maxlength="150" placeholder="Enter your unique title here (Max 150 chars)...">
      <button id="saveTitleBtn" class="save-title-btn" type="button">Save</button>
    </div>

    <div id="metaInfoBar" class="meta-info-bar">
      <span id="metaCategory"></span><span id="metaSyntax"></span><span id="metaTags"></span><span id="metaExpiration"></span><span id="metaDateTime"></span><span id="metaLocation"></span><span id="metaCountdown" hidden></span>
    </div>

    <div class="editor-wrapper" id="editorWrapper">
      <div class="line-numbers" id="lineNumbers" aria-hidden="true"><span>1</span></div>
      <div class="code-container" id="codeContainer">
        <pre id="highlighting" aria-hidden="true"><code id="highlighting-content"></code></pre>
        <textarea id="liveCodeBox" spellcheck="false" autocomplete="off" autocorrect="off" autocapitalize="off" translate="no" wrap="off" placeholder="Write or paste your code here…" readonly></textarea>
      </div>
      <div class="editor-status">Tab = 4 spaces · Ctrl/Cmd+Enter = run</div>
    </div>

    <div class="editor-bottom">
      <div class="editor-language-row">
        <label for="fileFormatSelect2">Language</label>
        <select id="fileFormatSelect2" aria-label="Syntax language"></select>
        <span class="readonly-badge" id="editStateBadge">Read only</span>
      </div>
      <div class="editor-bottom-actions"><button class="ce-btn primary" id="bottomCopyBtn" type="button">Copy Code</button><button class="ce-btn download-btn" id="bottomDownloadBtn" type="button">Download</button></div>
    </div>

    <details class="workspace-drawer"><summary>Workspace Pro — multi-file editor</summary><div class="workspace-v11" id="workspaceV11"><aside class="workspace-tree"><div class="workspace-tree-head"><strong>Workspace</strong><div class="workspace-tree-actions"><button class="ce-mini" id="uploadFiles" type="button">Upload files</button><button class="ce-mini" id="newFile" type="button">+ File</button><input id="workspaceFilePicker" type="file" multiple hidden></div></div><div id="workspaceTree"></div></aside><div class="workspace-main"><div class="workspace-tabs" id="workspaceTabs"></div><div class="workspace-toolbar"><button class="ce-mini" id="backupWorkspace" type="button">Encrypted Snapshot</button><button class="ce-mini" id="downloadCurrentFile" type="button">Download current file</button><button class="ce-mini" id="downloadProject" type="button">Download Project ZIP</button><span class="workspace-badge" id="workspaceStorageBadge">Workspace data stays local until saved</span></div></div></div></details>

    <section class="settings-grid lower-settings">
      <div class="setting-card"><h3>Expiration &amp; Access</h3><div class="row"><select id="expiry"><option value="never">Never</option><option value="burn">Burn after read / View-once</option><option value="10m">10 Minutes</option><option value="1h">1 Hour</option><option value="1d">1 Day</option><option value="1w">1 Week</option><option value="1mo">1 Month</option><option value="1y">1 Year</option></select><span id="timeLeft" class="pill">Time Left: —</span></div><div class="row"><select id="privacy"><option value="public">Public</option><option value="unlisted" selected>Unlisted</option><option value="private">Private</option></select><div class="password-wrapper"><input id="password" type="password" placeholder="Optional password"><button class="toggle-pwd" id="togglePassword" type="button">Show</button></div></div></div>
      <div class="setting-card"><h3>Metadata</h3><div class="metadata" id="metadataBanner">Category: — · Syntax: Plain Text · Tags: — · Expiration: Never · Published: — · Author Location: —</div></div>
    </section>

    <section class="setting-card byod-card" id="byodCard"><h3>Storage Engine</h3><div class="row"><select id="storageEngine"><option value="platform">Platform Managed</option><option value="byod">Personal Firebase (E2EE)</option></select><span id="byodStatus" class="pill">Not connected</span></div><div id="byodFields" hidden><p class="muted">Your Firebase config and encryption key stay in this browser. PocketCat receives no Firebase credentials, passphrase, plaintext snippet, or decryption key.</p><div class="row"><input id="fbApiKey" placeholder="apiKey"><input id="fbAuthDomain" placeholder="authDomain"></div><div class="row"><input id="fbDatabaseURL" placeholder="databaseURL (Realtime DB)"><input id="fbProjectId" placeholder="projectId"></div><div class="row"><input id="fbStorageBucket" placeholder="storageBucket"><select id="fbProvider"><option value="rtdb">Realtime Database</option><option value="firestore">Firestore</option></select></div><div class="row"><input id="fbPassphrase" type="password" autocomplete="new-password" placeholder="Master passphrase (never stored)"><button class="ce-mini" id="testByod" type="button">Test Connection &amp; Init DB</button></div><div class="row"><button class="ce-mini" id="saveByod" type="button">Save Local Configuration</button><button class="ce-mini" id="disconnectByod" type="button">Disconnect</button></div></div></section>

    <section class="preview-panel" id="previewPanel" hidden><div class="preview-head"><b>Live Output Preview</b><button class="ce-mini danger" id="closePreview" type="button">Close</button></div><iframe id="livePreviewFrame" sandbox="allow-scripts allow-same-origin" title="Live code preview"></iframe></section>
  </section>
</main>

<script src="/assets/js/cloud-storage.js" defer></script>
<script src="/assets/js/code-editor.js?v=16.3" defer></script>
<script src="/assets/js/workspace-v11.js?v=16.3" defer></script>
<script>
(async()=>{try{const r=await fetch('/api/code/?action=policy',{headers:{Accept:'application/json'}}),j=await r.json();if(j.byod&&!j.byod.allowed){const e=document.getElementById('storageEngine');if(e){const o=e.querySelector('option[value="byod"]');if(o)o.disabled=true;if(e.value==='byod')e.value='platform';}}}catch(_){}})();
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
