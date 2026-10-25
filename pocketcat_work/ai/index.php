<?php
require __DIR__.'/../bootstrap.php';
require_login();
$user=current_user();
$userId=(int)$user['id'];
$remainingLabel=ai_remaining_label($userId,$user);
$selectedModel=ai_user_model_preference($userId)?:ai_credentials($userId)['model'];
$title='AI Assistant';
require __DIR__.'/../includes/header.php';
?>
<main class="ai-workspace" data-ai-workspace data-model="<?=e($selectedModel)?>">
    <div class="page-head">
        <div>
            <span class="eyebrow">AI Workspace</span>
            <h1>AI Assistant</h1>
            <p class="muted">Persistent chats and projects, with an optional bounded workspace agent.</p>
        </div>
        <a class="btn secondary" href="/ai/transform-tasks/">Turn Text Into Tasks</a>
    </div>

    <div class="ai-modebar card">
        <label>Mode
            <select id="ai-mode">
                <option value="chat">Chat</option>
                <option value="agent">Agent</option>
                <option value="tasks">Text → Tasks</option>
            </select>
        </label>
        <details class="ai-model-picker" id="ai-model-picker">
            <summary><span>Model</span><strong id="selected-model-label"><?=e($selectedModel)?></strong></summary>
            <div class="ai-model-menu">
                <label class="visually-hidden" for="ai-model-search">Search models</label>
                <input id="ai-model-search" type="search" placeholder="Search by model name, vendor, or ID…" autocomplete="off">
                <div class="ai-custom-model">
                    <label for="ai-custom-model-id">Custom Model ID</label>
                    <div class="actions">
                        <input id="ai-custom-model-id" type="text" placeholder="vendor/model-id" maxlength="190" spellcheck="false">
                        <button class="btn small" id="ai-use-custom-model" type="button">Use Custom Model ID</button>
                    </div>
                </div>
                <div id="ai-model-options" class="ai-model-options" role="listbox" aria-label="Available models"></div>
            </div>
        </details>
        <span class="muted" id="agent-hint">Agent actions are limited to your PocketCat workspace data.</span>
    </div>

    <div class="chat-layout ai-chat-layout">
        <aside class="card chat-sidebar ai-sidebar">
            <section class="ai-sidebar-section">
                <div class="chat-sidebar-head">
                    <strong>Projects</strong>
                    <button class="btn small" id="new-project" type="button">+ New Project</button>
                </div>
                <label class="visually-hidden" for="project-search">Search projects</label>
                <input id="project-search" type="search" placeholder="Search projects…" autocomplete="off">
                <div id="project-list" class="ai-project-list"></div>
                <section id="project-panel" class="ai-project-panel" hidden>
                    <div class="ai-project-panel-head">
                        <h3 id="active-project-name"></h3>
                        <button class="icon-btn" id="close-project" type="button" aria-label="Close project">×</button>
                    </div>
                    <p id="active-project-description" class="muted"></p>
                    <label for="project-instructions">Project instructions</label>
                    <textarea id="project-instructions" rows="4" maxlength="10000"></textarea>
                    <button class="btn small" id="save-project-instructions" type="button">Save Instructions</button>
                    <button class="btn secondary small" id="new-project-chat" type="button">New project chat</button>
                    <h4>Project chats</h4>
                    <div id="project-chats"></div>
                    <h4>Tasks</h4>
                    <div id="project-tasks"></div>
                </section>
            </section>
            <section class="ai-sidebar-section">
                <div class="chat-sidebar-head">
                    <strong>Chats</strong>
                    <div class="actions">
                        <button class="btn small" id="new-chat" type="button">+ New Chat</button>
                        <button class="icon-btn" id="share-chat" type="button" title="Share full conversation" aria-label="Share conversation">↗</button>
                    </div>
                </div>
                <label class="visually-hidden" for="chat-search">Search chats</label>
                <input id="chat-search" type="search" placeholder="Search chats…" autocomplete="off">
                <div id="chat-list"></div>
            </section>
        </aside>

        <section class="card chat-shell chat-main">
            <div class="chat-messages" id="main-chat-messages" aria-live="polite">
                <div class="chat-empty">
                    <div class="icon-box">AI</div>
                    <h2>Start a conversation</h2>
                    <p class="muted">Choose a model and ask about your work.</p>
                </div>
            </div>
            <form class="chat-composer" id="chat-form">
                <?=csrf_field()?>
                <textarea id="chat-input" rows="3" placeholder="Message PocketCat AI…" aria-label="Message PocketCat AI"></textarea>
                <input type="file" id="chat-file" class="chat-file-input" multiple accept="image/*,audio/*,video/*,.zip,.pdf,.txt,.csv,.json,.md,.php,.js,.css,.sql,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
                <label class="chat-file-label" for="chat-file">📎 Attach files</label>
                <div id="chat-attachments" class="chat-attachments"></div>
                <div class="composer-row">
                    <span class="muted"><?=e($remainingLabel)?></span>
                    <button class="btn" id="send-btn" type="submit">Send</button>
                </div>
            </form>
        </section>
    </div>
</main>
<script defer src="/assets/js/ai-model-catalog.js?v=19.79"></script>
<script defer src="/assets/js/ai-workspace.js?v=19.79"></script>
<?php require __DIR__.'/../includes/footer.php'; ?>
