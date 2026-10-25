document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-ai-workspace]');
  if (!root) return;

  const $ = (selector) => root.querySelector(selector);
  const list = $('#chat-list');
  const projectList = $('#project-list');
  const messages = $('#main-chat-messages');
  const input = $('#chat-input');
  const form = $('#chat-form');
  const newChatButton = $('#new-chat');
  const shareButton = $('#share-chat');
  const sendButton = $('#send-btn');
  const fileInput = $('#chat-file');
  const attachmentBox = $('#chat-attachments');
  const modelPicker = $('#ai-model-picker');
  const modelSearch = $('#ai-model-search');
  const modelOptions = $('#ai-model-options');
  const customModelInput = $('#ai-custom-model-id');
  const csrf = window.POCKETCAT_CSRF || '';
  const modelCatalog = window.POCKETCAT_AI_MODEL_CATALOG || [];
  let conversationId = null;
  let lastPrompt = '';
  let attached = [];
  let uploadCount = 0;
  let conversations = [];
  let projects = [];
  let activeProjectId = null;
  let selectedModel = root.dataset.model || '';

  async function api(data) {
    const response = await fetch('/api/ai-chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
      credentials: 'same-origin',
      body: new URLSearchParams({ ...data, _csrf: csrf })
    });
    let payload;
    try {
      payload = await response.json();
    } catch {
      throw new Error(response.status === 403
        ? 'The AI service denied this request. Please check the route and your account access.'
        : `The AI service returned an invalid response (HTTP ${response.status}).`);
    }
    if (!response.ok || payload.ok === false) throw new Error(payload.error || `Request failed (HTTP ${response.status}).`);
    return payload;
  }

  function setModel(model) {
    selectedModel = model;
    $('#selected-model-label').textContent = model;
    root.dataset.model = model;
  }

  function renderModelOptions() {
    const term = modelSearch.value.trim().toLowerCase();
    modelOptions.replaceChildren();
    let count = 0;
    for (const [family, models] of modelCatalog) {
      const matches = models.filter((model) => `${family} ${model}`.toLowerCase().includes(term));
      if (!matches.length) continue;
      const group = document.createElement('section');
      group.className = 'ai-model-group';
      const heading = document.createElement('h4');
      heading.textContent = family;
      group.append(heading);
      for (const model of matches) {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'ai-model-option';
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', model === selectedModel ? 'true' : 'false');
        option.textContent = model;
        option.addEventListener('click', () => chooseModel(model));
        group.append(option);
        count += 1;
      }
      modelOptions.append(group);
    }
    if (!count) {
      const empty = document.createElement('p');
      empty.className = 'muted';
      empty.textContent = 'No matching models.';
      modelOptions.append(empty);
    }
  }

  async function chooseModel(model) {
    const normalized = model.trim();
    if (!/^[A-Za-z0-9][A-Za-z0-9._:/-]{0,189}$/.test(normalized)) {
      customModelInput.setCustomValidity('Use a model ID containing letters, numbers, dot, underscore, colon, slash, or hyphen.');
      customModelInput.reportValidity();
      return;
    }
    customModelInput.setCustomValidity('');
    try {
      await api({ action: 'model-preference', model_id: normalized, conversation_id: conversationId || '' });
      setModel(normalized);
      modelPicker.open = false;
      renderModelOptions();
    } catch (error) {
      window.alert(error.message);
    }
  }

  function renderMessage(role, text, id = '', attachments = []) {
    const row = document.createElement('article');
    row.className = `chat-row ${role}`;
    row.dataset.messageId = String(id || '');
    row.dataset.raw = String(text || '');
    const bubble = document.createElement('div');
    bubble.className = `chat-bubble ${role === 'user' ? 'user-bubble' : 'assistant-bubble'}`;
    bubble.textContent = String(text || '');
    row.append(bubble);
    if (attachments.length) {
      const chips = document.createElement('div');
      chips.className = 'chat-message-attachments';
      for (const attachment of attachments) {
        const link = document.createElement('a');
        link.className = 'chat-attachment-chip';
        link.href = attachment.url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = `📎 ${attachment.name}`;
        chips.append(link);
      }
      bubble.append(chips);
    }
    const actions = document.createElement('div');
    actions.className = 'chat-actions';
    const actionNames = role === 'assistant'
      ? [['copy', 'Copy'], ['share', 'Share'], ['task', 'Task'], ['note', 'Note'], ['project', 'Project']]
      : [['edit', 'Edit'], ['regenerate', 'Regenerate']];
    for (const [name, label] of actionNames) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'chat-action';
      button.dataset.act = name;
      button.textContent = label;
      actions.append(button);
    }
    row.append(actions);
    return row;
  }

  function emptyState(title = 'Start a conversation') {
    const wrap = document.createElement('div');
    wrap.className = 'chat-empty';
    const icon = document.createElement('div');
    icon.className = 'icon-box';
    icon.textContent = 'AI';
    const heading = document.createElement('h2');
    heading.textContent = title;
    const description = document.createElement('p');
    description.className = 'muted';
    description.textContent = 'Choose a model and ask about your work.';
    wrap.append(icon, heading, description);
    return wrap;
  }

  function filterChats() {
    const term = $('#chat-search').value.trim().toLowerCase();
    list.replaceChildren();
    for (const conversation of conversations) {
      if (!String(conversation.title || '').toLowerCase().includes(term)) continue;
      const item = document.createElement('div');
      item.className = 'ai-chat-item';
      const open = document.createElement('button');
      open.type = 'button';
      open.className = `chat-session${Number(conversation.id) === Number(conversationId) ? ' active' : ''}`;
      open.textContent = `${Number(conversation.is_pinned) ? '★ ' : ''}${conversation.title || 'New chat'}`;
      if (conversation.project_name) open.title = `Project: ${conversation.project_name}`;
      open.addEventListener('click', () => loadConversation(conversation.id));
      const menu = document.createElement('details');
      menu.className = 'ai-chat-menu';
      const summary = document.createElement('summary');
      summary.setAttribute('aria-label', `Actions for ${conversation.title || 'chat'}`);
      summary.textContent = '⋯';
      menu.append(summary);
      const actions = document.createElement('div');
      actions.className = 'ai-chat-menu-actions';
      const addAction = (label, callback) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.addEventListener('click', async () => {
          menu.open = false;
          await callback();
        });
        actions.append(button);
      };
      addAction('Rename', async () => {
        const title = window.prompt('Rename chat', conversation.title || '');
        if (!title) return;
        await api({ action: 'rename', conversation_id: conversation.id, title });
        await loadChats();
      });
      addAction(Number(conversation.is_pinned) ? 'Unpin' : 'Pin', async () => {
        await api({ action: 'pin', conversation_id: conversation.id });
        await loadChats();
      });
      addAction('Move to project…', async () => {
        const options = projects.map((project) => `${project.id}: ${project.name}`);
        const choice = window.prompt(`Enter a project ID, or leave blank to remove from a project.\n${options.join('\n')}`, conversation.project_id || '');
        if (choice === null) return;
        const projectId = choice.trim() === '' ? '' : Number(choice.split(':')[0]);
        if (projectId && !projects.some((project) => Number(project.id) === projectId)) {
          window.alert('That project is not in your project list.');
          return;
        }
        await api({ action: 'move', conversation_id: conversation.id, project_id: projectId });
        await loadChats();
        await loadProjects();
      });
      addAction('Delete', async () => {
        if (!window.confirm('Delete this chat and its message history?')) return;
        await api({ action: 'delete', conversation_id: conversation.id });
        if (Number(conversation.id) === Number(conversationId)) resetChat();
        await loadChats();
        await loadProjects();
      });
      menu.append(actions);
      item.append(open, menu);
      list.append(item);
    }
  }

  async function loadChats() {
    try {
      const result = await api({ action: 'history' });
      conversations = result.conversations || [];
      filterChats();
    } catch (error) {
      list.textContent = error.message;
    }
  }

  async function loadProjects() {
    try {
      const result = await api({ action: 'projects' });
      projects = result.projects || [];
      const term = $('#project-search').value.trim().toLowerCase();
      projectList.replaceChildren();
      for (const project of projects) {
        if (!String(project.name).toLowerCase().includes(term)) continue;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `ai-project-link${Number(project.id) === Number(activeProjectId) ? ' active' : ''}`;
        button.textContent = `${project.name} · ${project.chat_count} chats`;
        button.addEventListener('click', () => openProject(project.id));
        projectList.append(button);
      }
    } catch (error) {
      projectList.textContent = error.message;
    }
  }

  async function openProject(id) {
    try {
      const result = await api({ action: 'project-view', project_id: id });
      activeProjectId = Number(id);
      $('#active-project-name').textContent = result.project.name;
      $('#active-project-description').textContent = result.project.description || '';
      $('#project-instructions').value = result.instructions || '';
      $('#project-panel').hidden = false;
      const chatBox = $('#project-chats');
      chatBox.replaceChildren();
      for (const chat of result.chats || []) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'chat-session';
        button.textContent = chat.title;
        button.addEventListener('click', () => loadConversation(chat.id));
        chatBox.append(button);
      }
      const taskBox = $('#project-tasks');
      taskBox.replaceChildren();
      for (const task of result.tasks || []) {
        const link = document.createElement('a');
        link.href = `/tasks/?project_id=${encodeURIComponent(id)}`;
        link.textContent = `${task.title} · ${task.status}`;
        taskBox.append(link);
      }
      await loadProjects();
    } catch (error) {
      window.alert(error.message);
    }
  }

  async function loadConversation(id) {
    try {
      const result = await api({ action: 'messages', conversation_id: id });
      conversationId = Number(id);
      activeProjectId = Number(result.conversation.project_id || 0) || null;
      if (result.conversation.model_id) setModel(result.conversation.model_id);
      messages.replaceChildren();
      for (const message of result.messages || []) {
        messages.append(renderMessage(message.role, message.content, message.id, message.attachments || []));
      }
      if (!result.messages?.length) messages.append(emptyState('New chat'));
      messages.scrollTop = messages.scrollHeight;
      await loadChats();
    } catch (error) {
      window.alert(error.message);
    }
  }

  function resetChat() {
    conversationId = null;
    attached = [];
    attachmentBox.replaceChildren();
    messages.replaceChildren(emptyState());
    $('#project-panel').hidden = true;
    activeProjectId = null;
  }

  async function startChat(projectId = activeProjectId) {
    newChatButton.disabled = true;
    try {
      const result = await api({ action: 'new', project_id: projectId || '', model_id: selectedModel });
      conversationId = Number(result.conversation_id);
      activeProjectId = Number(projectId) || null;
      setModel(result.model_id || selectedModel);
      lastPrompt = '';
      attached = [];
      attachmentBox.replaceChildren();
      messages.replaceChildren(emptyState(projectId ? 'New project chat' : 'New chat'));
      input.value = '';
      await loadChats();
      input.focus();
    } catch (error) {
      window.alert(error.message);
    } finally {
      newChatButton.disabled = false;
    }
  }

  async function uploadChatFile(file) {
    if (!conversationId) await startChat();
    const init = new FormData();
    init.append('_csrf', csrf);
    init.append('action', 'init');
    init.append('scope', 'chat');
    init.append('context_id', String(conversationId));
    init.append('name', file.name);
    init.append('size', String(file.size));
    init.append('mime', file.type || 'application/octet-stream');
    init.append('relative_path', file.webkitRelativePath || file.name);
    const initResponse = await fetch('/api/upload-chunk.php', { method: 'POST', body: init, credentials: 'same-origin' });
    let session;
    try { session = await initResponse.json(); } catch { throw new Error(`Upload initialization failed (HTTP ${initResponse.status}).`); }
    if (!initResponse.ok || !session.ok) throw new Error(session.error || 'Upload initialization failed.');
    const token = session.token;
    let offset = 0;
    const chunkSize = Math.max(131072, Number(session.chunk_size || 262144));
    try {
      while (offset < file.size) {
        const formData = new FormData();
        formData.append('_csrf', csrf);
        formData.append('action', 'chunk');
        formData.append('token', token);
        formData.append('offset', String(offset));
        formData.append('chunk', file.slice(offset, Math.min(file.size, offset + chunkSize), file.type || 'application/octet-stream'), `${file.name}.part`);
        const response = await fetch('/api/upload-chunk.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        let result;
        try { result = await response.json(); } catch { throw new Error(`Upload chunk failed (HTTP ${response.status}).`); }
        if (!response.ok || !result.ok) throw new Error(result.error || 'Upload chunk failed.');
        const nextOffset = Number(result.received);
        if (nextOffset <= offset) throw new Error('Upload stalled. Please retry this file.');
        offset = nextOffset;
      }
      const complete = new FormData();
      complete.append('_csrf', csrf);
      complete.append('action', 'complete');
      complete.append('token', token);
      const response = await fetch('/api/upload-chunk.php', { method: 'POST', body: complete, credentials: 'same-origin' });
      let result;
      try { result = await response.json(); } catch { throw new Error(`Upload finalization failed (HTTP ${response.status}).`); }
      if (!response.ok || !result.ok) throw new Error(result.error || 'Upload finalization failed.');
      if (!result.attachment_id) throw new Error('The file was uploaded but not registered for chat.');
      return { id: Number(result.attachment_id), name: result.name, url: result.url, kind: result.kind || 'file', mime: result.mime || file.type, size: Number(result.size || file.size) };
    } catch (error) {
      const abort = new FormData();
      abort.append('_csrf', csrf);
      abort.append('action', 'abort');
      abort.append('token', token);
      await fetch('/api/upload-chunk.php', { method: 'POST', body: abort, credentials: 'same-origin', keepalive: true }).catch(() => {});
      throw error;
    }
  }

  $('#chat-search').addEventListener('input', filterChats);
  $('#project-search').addEventListener('input', loadProjects);
  $('#new-project').addEventListener('click', async () => {
    const name = window.prompt('Project name');
    if (!name?.trim()) return;
    const customInstructions = window.prompt('Optional project instructions', '') || '';
    try {
      const result = await api({ action: 'project-create', name: name.trim(), custom_instructions: customInstructions });
      await loadProjects();
      await openProject(result.project_id);
    } catch (error) {
      window.alert(error.message);
    }
  });
  $('#close-project').addEventListener('click', () => {
    $('#project-panel').hidden = true;
    activeProjectId = null;
    loadProjects();
  });
  $('#save-project-instructions').addEventListener('click', async () => {
    if (!activeProjectId) return;
    try {
      await api({ action: 'project-save', project_id: activeProjectId, custom_instructions: $('#project-instructions').value });
      window.alert('Project instructions saved.');
    } catch (error) {
      window.alert(error.message);
    }
  });
  $('#new-project-chat').addEventListener('click', () => startChat(activeProjectId));
  newChatButton.addEventListener('click', () => startChat(null));
  $('#ai-use-custom-model').addEventListener('click', () => chooseModel(customModelInput.value));
  customModelInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      chooseModel(customModelInput.value);
    }
  });
  modelSearch.addEventListener('input', renderModelOptions);
  modelPicker.addEventListener('toggle', () => {
    if (modelPicker.open) {
      modelSearch.value = '';
      renderModelOptions();
      modelSearch.focus();
    }
  });
  $('#ai-mode').addEventListener('change', (event) => {
    if (event.target.value === 'tasks') {
      location.href = `/ai/transform-tasks/?model_id=${encodeURIComponent(selectedModel)}`;
      return;
    }
    $('#agent-hint').textContent = event.target.value === 'agent'
      ? 'Agent mode can search your account and create workspace tasks, notes, and projects when requested.'
      : 'Chat keeps context and behaves like a normal assistant.';
  });
  shareButton.addEventListener('click', async () => {
    if (!conversationId) return window.alert('Start a chat first.');
    try {
      const result = await api({ action: 'share', conversation_id: conversationId });
      await navigator.clipboard.writeText(result.url);
      window.alert('Conversation link copied.');
    } catch (error) {
      window.alert(error.message);
    }
  });
  fileInput.addEventListener('change', async () => {
    if (!fileInput.files.length) return;
    fileInput.disabled = true;
    uploadCount = fileInput.files.length;
    for (const file of Array.from(fileInput.files)) {
      const chip = document.createElement('span');
      chip.className = 'chat-attachment-chip is-uploading';
      chip.textContent = `Uploading ${file.name}…`;
      attachmentBox.append(chip);
      try {
        const attachment = await uploadChatFile(file);
        attached.push(attachment);
        chip.className = 'chat-attachment-chip';
        chip.textContent = `📎 ${attachment.name} ✓`;
      } catch (error) {
        chip.className = 'chat-attachment-chip upload-failed';
        chip.textContent = `Upload failed: ${file.name}`;
        chip.title = error.message;
      }
    }
    fileInput.value = '';
    fileInput.disabled = false;
    uploadCount = 0;
    input.focus();
  });
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const prompt = input.value.trim();
    if ((!prompt && !attached.length) || sendButton.disabled || uploadCount) return;
    lastPrompt = prompt;
    messages.querySelector('.chat-empty')?.remove();
    messages.append(renderMessage('user', prompt || 'Attached files', '', attached.map((file) => ({ name: file.name, url: file.url }))));
    input.value = '';
    sendButton.disabled = true;
    newChatButton.disabled = true;
    const waiting = renderMessage('assistant', 'Thinking…');
    messages.append(waiting);
    messages.scrollTop = messages.scrollHeight;
    try {
      const result = await api({
        action: 'send',
        prompt,
        conversation_id: conversationId || '',
        project_id: activeProjectId || '',
        model_id: selectedModel,
        attachment_ids: attached.map((file) => file.id),
        agent: $('#ai-mode').value === 'agent' ? '1' : '0'
      });
      attached = [];
      attachmentBox.replaceChildren();
      waiting.remove();
      conversationId = Number(result.conversation_id);
      messages.append(renderMessage('assistant', result.answer, result.message_id));
      await loadChats();
      await loadProjects();
    } catch (error) {
      waiting.textContent = `AI request failed: ${error.message}`;
    } finally {
      sendButton.disabled = false;
      newChatButton.disabled = false;
      input.focus();
      messages.scrollTop = messages.scrollHeight;
    }
  });
  messages.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-act]');
    if (!button) return;
    const row = button.closest('.chat-row');
    const text = row?.dataset.raw || '';
    const action = button.dataset.act;
    if (action === 'edit') {
      input.value = text;
      input.focus();
      return;
    }
    if (action === 'regenerate') {
      input.value = lastPrompt || text;
      form.requestSubmit();
      return;
    }
    if (action === 'copy') {
      await navigator.clipboard.writeText(text);
      button.textContent = 'Copied';
      window.setTimeout(() => { button.textContent = 'Copy'; }, 1000);
      return;
    }
    if (action === 'share') {
      try {
        const result = await api({ action: 'share', conversation_id: conversationId, message_id: row.dataset.messageId });
        await navigator.clipboard.writeText(result.url);
        window.alert('Answer link copied.');
      } catch (error) {
        window.alert(error.message);
      }
      return;
    }
    if (['task', 'note', 'project'].includes(action)) {
      try {
        const response = await fetch('/api/ai-action.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
          credentials: 'same-origin',
          body: new URLSearchParams({ action, content: text, _csrf: csrf })
        });
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || `Action failed (HTTP ${response.status}).`);
        location.href = result.url;
      } catch (error) {
        window.alert(error.message);
      }
    }
  });
  window.addEventListener('error', (event) => {
    if (event.error) console.error('[PocketCat AI workspace]', event.error);
  });

  renderModelOptions();
  const promptFromQuery = new URLSearchParams(location.search).get('prompt');
  if (promptFromQuery) input.value = promptFromQuery;
  loadProjects();
  loadChats();
});
