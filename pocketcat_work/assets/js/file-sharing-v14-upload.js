(function () {
    'use strict';

    function init() {
        const cfg = window.FILE_PAGE || {};
        const csrf = cfg.csrf || window.POCKETCAT_CSRF || '';
        const queue = document.getElementById('queue');
        const drop = document.getElementById('drop');
        const picker = document.getElementById('picker');
        const folderPicker = document.getElementById('folderPicker');
        const grid = document.getElementById('fileGrid');
        if (!queue || !drop || !picker) return;

        const uploads = new Map();
        let MAX_CONCURRENT_UPLOADS = 2;
        const concurrencyPicker = document.getElementById('uploadConcurrency');
        if (concurrencyPicker) { concurrencyPicker.addEventListener('change', () => { MAX_CONCURRENT_UPLOADS = Math.max(2, Math.min(4, Number(concurrencyPicker.value) || 2)); pumpQueue(); }); }
        let activeUploads = 0;
        let toastTimer = 0;
        const retryDelays = [2000, 5000, 10000];
        const sleep = (ms) => new Promise(resolve => window.setTimeout(resolve, ms));
        const toast = (message) => { let el=document.getElementById('pcUploadToast'); if(!el){el=document.createElement('div');el.id='pcUploadToast';el.className='pc-upload-toast';el.setAttribute('role','status');el.setAttribute('aria-live','polite');document.body.appendChild(el);} el.textContent=message;el.classList.add('show');clearTimeout(toastTimer);toastTimer=window.setTimeout(()=>el.classList.remove('show'),6500); };
        const dialog = (title, message, confirmLabel='OK', cancelLabel='Cancel') => new Promise(resolve => { let host=document.getElementById('pcFileDialog'); if(!host){host=document.createElement('div');host.id='pcFileDialog';host.className='pc-file-dialog';host.innerHTML='<div class=pc-file-dialog-panel role=dialog aria-modal=true aria-labelledby=pcFileDialogTitle><h2 id=pcFileDialogTitle></h2><p class=pc-file-dialog-message></p><div class=pc-file-dialog-actions><button type=button data-cancel></button><button type=button data-confirm></button></div></div>';document.body.appendChild(host);} host.querySelector('h2').textContent=title;host.querySelector('.pc-file-dialog-message').textContent=message;const cancel=host.querySelector('[data-cancel]'),ok=host.querySelector('[data-confirm]');cancel.textContent=cancelLabel;ok.textContent=confirmLabel;cancel.hidden=cancelLabel==='';host.hidden=false;const done=v=>{host.hidden=true;cancel.onclick=ok.onclick=null;host.removeEventListener('click',outside);resolve(v)};const outside=e=>{if(e.target===host)done(false)};host.addEventListener('click',outside);cancel.onclick=()=>done(false);ok.onclick=()=>done(true);ok.focus(); });
        const notify = (title, message) => dialog(title, message, 'Close', '');
        document.addEventListener('click', e => { const opened=[...document.querySelectorAll('details.file-menu[open]')]; opened.forEach(d=>{if(!d.contains(e.target))d.open=false;}); });
        document.addEventListener('toggle', e => { if(e.target.matches?.('details.file-menu') && e.target.open){document.querySelectorAll('details.file-menu[open]').forEach(d=>{if(d!==e.target)d.open=false;});requestAnimationFrame(()=>{const d=e.target,p=d.querySelector('.file-menu-pop');if(!p)return;d.classList.remove('menu-up');const r=d.getBoundingClientRect(),h=Math.min(p.scrollHeight,window.innerHeight*.65);if(window.innerHeight-r.bottom<h+12)d.classList.add('menu-up');});} }, true);
        const selectedUploads = () => [...uploads.values()].filter(s => s.selected && !s.removed);
        let raf = 0;

        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (m) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[m]));

        const formatBytes = (bytes) => {
            const n = Math.max(0, Number(bytes) || 0);
            if (n < 1024) return n.toFixed(0) + ' B';
            const units = ['KB', 'MB', 'GB', 'TB'];
            let value = n / 1024;
            let i = 0;
            while (value >= 1024 && i < units.length - 1) {
                value /= 1024;
                i++;
            }
            const decimals = value >= 100 ? 0 : value >= 10 ? 1 : 2;
            return value.toFixed(decimals) + ' ' + units[i];
        };

        const formatSpeed = (bytesPerSecond) => {
            const n = Number(bytesPerSecond) || 0;
            return n > 0 ? formatBytes(n) + '/s' : '—';
        };

        const formatEta = (seconds) => {
            if (!Number.isFinite(seconds) || seconds < 0 || seconds > 864000) return 'Calculating…';
            const s = Math.ceil(seconds);
            if (s < 60) return s + 's';
            const m = Math.floor(s / 60);
            const r = s % 60;
            if (m < 60) return m + 'm ' + r + 's';
            const h = Math.floor(m / 60);
            return h + 'h ' + (m % 60) + 'm';
        };

        const makeCard = (f) => {
            const d = document.createElement('div');
            d.className = 'upload-card card';
            d.innerHTML = `
                <div class="upload-head">
                    <label class="upload-select-wrap"><input type="checkbox" class="upload-select" aria-label="Select upload"></label><div class="upload-title-wrap"><b class="upload-name"></b><span class="upload-path"></span></div>
                    <span class="status">Preparing…</span>
                </div>
                <div class="upload-progress-track"><i></i></div>
                <div class="upload-missing-warning" hidden>File Not Found: This file was deleted or moved from its original local directory.</div>
                <div class="upload-metrics">
                    <span class="upload-size">Upload size: ${esc(formatBytes(f.size))}</span>
                    <span class="upload-percent">Progress: 0% / 100%</span>
                    <span class="upload-speed">Network speed: —</span>
                    <span class="upload-eta">ETA: Calculating…</span>
                </div>
                <div class="upload-actions"></div>
            `;
            d.querySelector('.upload-name').textContent = f.name;
            d.querySelector('.upload-path').textContent = f.webkitRelativePath || '';
            queue.prepend(d);
            return d;
        };

        const ensureMetrics = () => {
            let box = document.getElementById('uploadMetrics');
            if (box) return box;
            box = document.createElement('div');
            box.id = 'uploadMetrics';
            box.className = 'upload-live-summary';
            box.innerHTML = `
                <div><span>Upload progress</span><strong data-metric="progress">0% / 100%</strong></div>
                <div><span>Upload size</span><strong data-metric="bytes">0 B / 0 B</strong></div>
                <div><span>Network speed</span><strong data-metric="speed">—</strong></div>
                <div><span>ETA</span><strong data-metric="eta">—</strong></div>
                <div><span>Queue</span><strong data-metric="queue">0 active · 0 queued</strong></div>
            `;
            queue.before(box);
            return box;
        };

        const setStatus = (state, text) => {
            state.card.querySelector('.status').textContent = text;
            state.card.dataset.state = state.phase;
        };

        const updateCard = (state, force = false) => {
            const now = performance.now();
            const elapsed = Math.max(0.001, (now - state.startedAt) / 1000);
            const uploaded = Math.min(state.total, Math.max(0, state.uploaded));
            state.speed = uploaded > state.speedBaseBytes ? (uploaded - state.speedBaseBytes) / Math.max(0.001, (now - state.speedBaseTime) / 1000) : 0;
            if (now - (state.lastProgressAt || now) > 2000) state.speed = 0;
            if (uploaded >= state.total) state.speed = state.lastSpeed || state.speed;
            state.lastSpeed = state.speed || state.lastSpeed;
            const percent = state.total ? Math.min(100, (uploaded / state.total) * 100) : 0;
            const eta = state.speed > 0 ? (state.total - uploaded) / state.speed : Infinity;

            state.card.querySelector('.upload-progress-track i').style.width = percent.toFixed(2) + '%';
            state.card.querySelector('.upload-size').textContent = 'Upload size: ' + formatBytes(uploaded) + ' / ' + formatBytes(state.total);
            state.card.querySelector('.upload-percent').textContent = 'Progress: ' + percent.toFixed(percent < 10 ? 1 : 0) + '% / 100%';
            state.card.querySelector('.upload-speed').textContent = 'Network speed: ' + formatSpeed(state.speed);
            state.card.querySelector('.upload-eta').textContent = 'ETA: ' + (uploaded >= state.total ? 'Complete' : formatEta(eta));
            state.lastUpdate = now;

            if (force || now - state.speedBaseTime >= 500) {
                state.speedBaseBytes = uploaded;
                state.speedBaseTime = now;
            }
            state.elapsed = elapsed;
        };

        const ensureBatchActions = () => {
            let box = document.getElementById('uploadBatchActions');
            if (box) return box;
            box = document.createElement('div');
            box.id = 'uploadBatchActions';
            box.className = 'upload-batch-actions';
            box.innerHTML = '<label class="upload-select-all-wrap"><input type="checkbox" id="uploadSelectAll"> Select all uploads</label><button type="button" class="btn small secondary upload-pause-selected">Pause Selected</button><button type="button" class="btn small secondary upload-resume-selected">Resume Selected</button><button type="button" class="btn small secondary upload-cancel-selected">Cancel Selected</button><button type="button" class="btn small upload-retry-selected">Retry Selected</button><button type="button" class="btn small secondary upload-remove-selected">Remove Selected</button><button type="button" class="btn small upload-retry-all">Retry failed</button><button type="button" class="btn small secondary upload-cancel-all">Cancel active uploads</button><button type="button" class="btn small secondary upload-clear-failed">Clear all cancelled / failed</button>';
            box.querySelector('#uploadSelectAll').onchange = e => { [...uploads.values()].forEach(s=>{s.selected=e.target.checked;const cb=s.card?.querySelector('.upload-select');if(cb)cb.checked=s.selected;}); };
            box.querySelector('.upload-pause-selected').onclick=()=>selectedUploads().forEach(pauseUpload);
            box.querySelector('.upload-resume-selected').onclick=()=>selectedUploads().forEach(resumeUpload);
            box.querySelector('.upload-cancel-selected').onclick=()=>selectedUploads().forEach(cancelUpload);
            box.querySelector('.upload-retry-selected').onclick=()=>selectedUploads().forEach(s=>{if(['error','cancelled','paused','missing'].includes(s.phase))enqueueUpload(s,true)});
            box.querySelector('.upload-remove-selected').onclick=()=>selectedUploads().forEach(removeUpload);
            queue.before(box);
            box.querySelector('.upload-retry-all').onclick = () => {
                [...uploads.values()].filter((s) => s.phase === 'error' || s.phase === 'cancelled').forEach((s) => enqueueUpload(s, true));
            };
            box.querySelector('.upload-cancel-all').onclick = () => {
                [...uploads.values()].filter((s) => ['queued','preparing','uploading','retrying'].includes(s.phase)).forEach((s) => cancelUpload(s));
            };
            box.querySelector('.upload-clear-failed').onclick = async () => {
                const failed = [...uploads.values()].filter((s) => s.phase === 'error' || s.phase === 'cancelled');
                if (!failed.length) return;
                if (!(await dialog('Remove upload cards?', 'Remove '+failed.length+' cancelled or failed upload cards? Active and queued uploads will remain untouched.', 'Remove'))) return;
                failed.forEach((s) => removeUpload(s));
            };
            return box;
        };

        const renderSummary = () => {
            raf = 0;
            const now = performance.now();
            const active = [...uploads.values()].filter((s) => s.phase === 'uploading' || s.phase === 'retrying' || s.phase === 'preparing');
            const all = [...uploads.values()];
            const visible = all.filter((s) => s.phase !== 'done');
            const total = all.reduce((n, s) => n + s.total, 0);
            const uploaded = all.reduce((n, s) => n + Math.min(s.total, s.uploaded), 0);
            const speed = active.reduce((n, s) => n + (now - (s.lastProgressAt || now) <= 2000 ? (s.lastSpeed || s.speed || 0) : 0), 0);
            const eta = speed > 0 ? Math.max(0, total - uploaded) / speed : Infinity;
            const box = ensureMetrics();
            const batchActions = ensureBatchActions();
            const failedCount = all.filter((s) => s.phase === 'error' || s.phase === 'cancelled' || s.phase === 'missing').length;
            const cancellableCount = all.filter((s) => ['queued','preparing','uploading','retrying'].includes(s.phase)).length;
            batchActions.hidden = !all.length;
            batchActions.querySelector('.upload-retry-all').disabled = failedCount === 0;
            batchActions.querySelector('.upload-cancel-all').disabled = cancellableCount === 0;
            batchActions.querySelector('.upload-clear-failed').disabled = failedCount === 0;
            const pct = total ? Math.min(100, uploaded / total * 100) : 0;
            box.querySelector('[data-metric="progress"]').textContent = pct.toFixed(pct < 10 ? 1 : 0) + '% / 100%';
            box.querySelector('[data-metric="bytes"]').textContent = formatBytes(uploaded) + ' / ' + formatBytes(total);
            box.querySelector('[data-metric="speed"]').textContent = formatSpeed(speed);
            box.querySelector('[data-metric="eta"]').textContent = active.length ? formatEta(eta) : (all.length && uploaded >= total ? 'Complete' : '—');
            const queued = [...uploads.values()].filter((s) => s.phase === 'queued').length;
            const paused = all.filter(s=>s.phase==='paused').length;
            box.querySelector('[data-metric="queue"]').textContent = active.length + ' active · ' + queued + ' queued · ' + paused + ' paused';
            const selectAll= batchActions.querySelector('#uploadSelectAll'); if(selectAll){selectAll.checked=all.length>0&&all.every(s=>s.selected);selectAll.indeterminate=all.some(s=>s.selected)&&!selectAll.checked;}
            batchActions.querySelector('.upload-pause-selected').disabled=!all.some(s=>s.selected&&['uploading','preparing','retrying','queued'].includes(s.phase));
            batchActions.querySelector('.upload-resume-selected').disabled=!all.some(s=>s.selected&&s.phase==='paused');
            batchActions.querySelector('.upload-cancel-selected').disabled=!all.some(s=>s.selected&&['queued','preparing','uploading','retrying','paused'].includes(s.phase));
            batchActions.querySelector('.upload-retry-selected').disabled=!all.some(s=>s.selected&&['error','cancelled','paused','missing'].includes(s.phase));
            batchActions.querySelector('.upload-remove-selected').disabled=!all.some(s=>s.selected);
            box.hidden = !all.length;
        };

        const scheduleRender = () => {
            if (!raf) raf = requestAnimationFrame(renderSummary);
        };

        const xhrRequest = (url, options, state, baseLoaded = 0) => new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            state.xhr = xhr;
            state.currentXhr = xhr;
            xhr.open(options.method || 'POST', url, true);
            if (options.headers) Object.entries(options.headers).forEach(([k, v]) => xhr.setRequestHeader(k, v));
            xhr.responseType = 'text';
            let lastEventAt = performance.now();
            let uploadComplete = false;
            let stallTimer = 0;
            let settled = false;

            const clear = () => {
                if (stallTimer) clearInterval(stallTimer);
                stallTimer = 0;
                if (state.xhr === xhr) state.xhr = null;
            };

            const fail = (message) => {
                if (settled) return;
                settled = true;
                clear();
                reject(new Error(message));
            };

            if (xhr.upload) {
                xhr.upload.onprogress = (event) => {
                    if (!event.lengthComputable) return;
                    lastEventAt = performance.now();
                    uploadComplete = event.loaded >= event.total;
                    state.uploaded = Math.min(state.total, baseLoaded + event.loaded);
                    state.lastProgressAt = lastEventAt;
                    updateCard(state);
                    scheduleRender();
                };
            }

            xhr.onprogress = () => {
                lastEventAt = performance.now();
            };

            xhr.upload?.addEventListener?.('load', () => { uploadComplete = true; lastEventAt = performance.now(); });

            xhr.onload = () => {
                if (settled) return;
                settled = true;
                clear();
                let body = {};
                try { body = xhr.responseText ? JSON.parse(xhr.responseText) : {}; } catch (_) {}
                if (xhr.status >= 200 && xhr.status < 300) return resolve({ xhr, body });
                reject(new Error(body.error || ('Upload request failed (HTTP ' + xhr.status + ').')));
            };
            xhr.onerror = () => fail('Network error while uploading.');
            xhr.onabort = () => fail(state.pauseRequested ? 'Upload paused.' : (state.stalled ? 'Upload stalled. Check the network connection and retry.' : 'Upload cancelled.'));
            xhr.ontimeout = () => fail('Upload request timed out. Check the network connection and retry.');
            xhr.timeout = 0;

            stallTimer = window.setInterval(() => {
                if (settled) return;
                if (!uploadComplete && state.uploaded < state.total && performance.now() - lastEventAt > 30000) {
                    state.stalled = true;
                    try { xhr.abort(); } catch (_) {}
                }
            }, 2000);

            xhr.send(options.body || null);
        });

        const readJsonFetch = async (url, options) => {
            const response = await fetch(url, options);
            const text = await response.text();
            let body = {};
            try { body = JSON.parse(text); } catch (_) {}
            if (!response.ok) throw new Error(body.error || ('Server error ' + response.status) + (body.request_id ? ' — ' + body.request_id : ''));
            return body;
        };

        const api = (action, data) => readJsonFetch('/api/files/?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf},
            body: JSON.stringify(data)
        });

        const createFileCard = (f) => {
            if (!grid || !f || !f.id) return;
            const id = Number(f.id);
            const existing = document.getElementById('file-' + id);
            if (existing) return existing;
            const kind = String(f.kind || 'file');
            const mime = String(f.mime_type || 'application/octet-stream');
            const token = String(f.public_token || '');
            const previewUrl = token ? ('/file/' + encodeURIComponent(token)) : '#';
            const assetUrl = token ? ('/file/download/?id=' + id + '&token=' + encodeURIComponent(token)) : '#';
            const downloadUrl = token ? ('/file/download/?id=' + id + '&token=' + encodeURIComponent(token) + '&download=1') : '#';
            const article = document.createElement('article');
            article.id = 'file-' + id;
            article.className = 'file-card file-card-new';
            article.dataset.fileId = String(id);
            article.dataset.token = token;
            article.dataset.name = String((f.original_name || '') + ' ' + (f.relative_path || '')).toLowerCase();
            article.dataset.kind = kind;
            article.dataset.size = String(f.size_bytes || f.size || 0);
            article.dataset.mime = mime;
            const icon = kind.charAt(0).toUpperCase();
            let preview = '<span>' + esc(kind.toUpperCase()) + '</span>';
            if (mime.startsWith('image/') && token) preview = '<img src="' + esc(assetUrl) + '" alt="" loading="lazy">';
            else if (mime.startsWith('video/')) preview = '<span data-preview-src="' + esc(previewUrl) + '">▶ Video</span>'; 
            else if (mime.startsWith('audio/')) preview = '<span>♫ Audio</span>';
            else if (mime === 'application/pdf') preview = '<span>PDF</span>';
            article.innerHTML = `
                <div class="file-card-top"><input class="file-select" type="checkbox" value="${id}"><div class="file-icon">${esc(icon)}</div><div class="file-main"><strong title="${esc(f.original_name || '')}">${esc(f.original_name || '')}</strong><span class="muted">${esc(f.relative_path || '')}</span></div><details class="file-menu"><summary>•••</summary><div class="file-menu-pop"><a href="${esc(previewUrl)}" target="_blank">Preview</a><a href="/links/?edit=" aria-disabled="true">Edit Link</a><a href="/qr/file-${esc(token)}" target="_blank">Customize QR</a><button type="button" class="native-share" data-share-url="${esc(previewUrl)}">Share</button><button type="button" class="copy-file-link" data-copy-url="${esc(previewUrl)}">Copy Link</button><a aria-disabled="true">Analytics</a><a class="file-download-link" href="${esc(downloadUrl)}" download="${esc(String(f.original_name || 'file').split(/[\\/]/).pop())}">Download</a><button type="button" class="file-info" data-id="${id}">Info</button><button type="button" class="create-link-analytics" data-id="${id}">Create Link &amp; Analytics</button><button type="button" class="delete-file" data-id="${id}">Delete</button></div></details></div>
                <div class="file-preview-mini">${preview}</div>
                <div class="file-meta"><span>${esc(formatBytes(f.size_bytes || f.size || 0))}</span><span>0 downloads</span><span class="pill">${esc(f.scan_status || f.status || 'clean')}</span></div>
                <div class="file-link-strip">${(mime.startsWith('image/')||mime.startsWith('video/')||mime.startsWith('audio/'))?`<button type="button" class="media-preview-trigger" data-file-media-preview data-url="${esc(assetUrl)}" data-download="${esc(downloadUrl)}" data-name="${esc(f.original_name||'file')}" data-mime="${esc(mime)}">Preview</button>`:''}<span>Not shared</span><button type="button" class="quick-share" data-id="${id}">Create link</button></div>
            `;
            grid.prepend(article);
            bindFileActions(article);
            window.dispatchEvent(new CustomEvent('pocketcat:media-added',{detail:{file:f}}));
            const stat = document.getElementById('fileCountStat');
            if (stat) stat.textContent = String(grid.querySelectorAll('.file-card').length);
            return article;
        };

        const bindFileActions = (root = document) => {
            root.querySelectorAll('.file-info').forEach((b) => {
                if (b.dataset.bound) return; b.dataset.bound = '1';
                b.onclick = async () => { try { const j = await api('info', {id:Number(b.dataset.id)}); const f=j.file||j; await notify('File information', 'Name: '+(f.original_name||'—')+'\nType: '+(f.mime_type||'—')+'\nSize: '+formatBytes(f.size_bytes||0)+'\nStatus: '+(f.status||'—')+' / '+(f.scan_status||'—')+'\nUploaded: '+(f.created_at||'—')+'\nModified: '+(f.updated_at||'—')+'\nFolder: '+(f.relative_path||'Root')+'\nDownloads: '+(f.downloads||0)); } catch(e) { await notify('File information unavailable', e.message||'Unable to load file information.'); } };
            });
            root.querySelectorAll('.create-link-analytics').forEach((b) => {
                if (b.dataset.bound) return; b.dataset.bound = '1';
                b.onclick = async () => { const old=b.textContent; b.disabled=true; b.textContent='Creating…'; try { const j=await api('share_create',{ids:[Number(b.dataset.id)],title:'Shared file',expires_at:'',max_downloads:0,view_only:false}); location.href='/links/?created='+encodeURIComponent(j.link_id)+'#myQrCode'; } catch(e) { await notify('Could not create share link', e.message||'The file may still be scanning.'); } finally { b.disabled=false; b.textContent=old; } };
            });
            root.querySelectorAll('.delete-file').forEach((b) => {
                if (b.dataset.bound) return;
                b.dataset.bound = '1';
                b.onclick = async () => {
                    if (!(await dialog('Move file to Trash?', 'This file will be moved to Trash and can be restored later.', 'Move to Trash'))) return;
                    try {
                        await api('delete', {id: Number(b.dataset.id)});
                        const removedName=b.closest('.file-card')?.querySelector('.file-main strong')?.textContent||'File'; b.closest('.file-card')?.remove(); toast('🗑️ '+removedName+' moved to Trash');
                        const stat = document.getElementById('fileCountStat');
                        const remaining = grid?.querySelectorAll('.file-card:not(.folder-card)').length || 0;
                        if (stat) stat.textContent = String(remaining);
                        if (!remaining && grid && !document.getElementById('emptyFiles')) {
                            const empty = document.createElement('div'); empty.id = 'emptyFiles'; empty.className = 'empty-state'; empty.textContent = 'No files uploaded yet.'; grid.after(empty);
                        }
                        count();
                    } catch (e) { await notify('Delete failed', e.message); }
                };
            });
            root.querySelectorAll('.copy-file-link').forEach((b) => {
                if (b.dataset.bound) return; b.dataset.bound = '1';
                b.addEventListener('click', async () => {
                    const url = b.dataset.copyUrl || b.closest('.file-card')?.querySelector('.file-download-link')?.href || '';
                    if (!url) return notify('Copy link unavailable', 'This item does not have a share URL yet.');
                    try { await navigator.clipboard.writeText(new URL(url, location.href).href); toast('🔗 Link copied to clipboard'); }
                    catch (_) { const field=document.createElement('textarea');field.value=new URL(url,location.href).href;field.style.position='fixed';field.style.opacity='0';document.body.append(field);field.select();const ok=document.execCommand('copy');field.remove();if(ok)toast('🔗 Link copied to clipboard');else await notify('Copy link',new URL(url,location.href).href); }
                });
            });
            root.querySelectorAll('.native-share').forEach((b) => {
                if (b.dataset.bound) return;
                b.dataset.bound = '1';
                b.onclick = () => {
                    const u = b.dataset.shareUrl;
                    if (navigator.share) navigator.share({title: 'PocketCat file', url: u}).catch(() => {});
                    else if (navigator.clipboard) navigator.clipboard.writeText(u).then(() => notify('Link copied', 'The share link has been copied to your clipboard.'));
                };
            });
        };

        const fetchCompletedFile = async (id) => {
            const url = '/api/files/?action=status&id=' + encodeURIComponent(id);
            const f = await readJsonFetch(url, {method: 'GET', headers: {'Accept': 'application/json'}});
            createFileCard(f);
            return f;
        };

        const setQueuedActions = (state) => {
            const actions = state.card.querySelector('.upload-actions');
            actions.innerHTML = '<button type="button" class="btn small secondary upload-pause">Pause</button><button type="button" class="btn small secondary upload-cancel">Cancel</button><button type="button" class="btn small secondary upload-remove" aria-label="Remove upload">× Remove</button>';
            actions.querySelector('.upload-pause').onclick = () => pauseUpload(state);
            actions.querySelector('.upload-cancel').onclick = () => cancelUpload(state);
            actions.querySelector('.upload-remove').onclick = () => removeUpload(state);
        };

        const pumpQueue = () => {
            while (activeUploads < MAX_CONCURRENT_UPLOADS) {
                const next = [...uploads.values()].find((s) => s.phase === 'queued' && !s.cancelled && !s.running);
                if (!next) break;
                activeUploads++;
                const retry = !!next.retryRequested;
                next.phase = retry ? 'retrying' : 'preparing';
                runUpload(next, retry).finally(() => {
                    activeUploads = Math.max(0, activeUploads - 1);
                    pumpQueue();
                });
            }
            scheduleRender();
        };

        const enqueueUpload = (state, retry = false) => {
            if (state.phase === 'done' || state.running) return;
            state.cancelled = false;
            state.retryRequested = !!retry;
            state.phase = 'queued';
            state.card.classList.remove('upload-error');
            setStatus(state, retry ? 'Queued for retry…' : 'Queued…');
            setQueuedActions(state);
            scheduleRender();
            pumpQueue();
        };

        const setError = (state, message) => {
            state.phase = 'error';
            state.card.classList.add('upload-error');
            setStatus(state, message || 'Upload failed.');
            const actions = state.card.querySelector('.upload-actions');
            actions.innerHTML = '<button type="button" class="btn small upload-retry">Retry upload</button><button type="button" class="btn small secondary upload-reselect">Re-select File</button><button type="button" class="btn small secondary upload-dismiss">Remove</button>';
            actions.querySelector('.upload-retry').onclick = () => enqueueUpload(state, true);
            actions.querySelector('.upload-reselect').onclick = () => reselectFile(state);
            if(/file|blob|read|not found|missing/i.test(message||'')){state.phase='missing';state.card.querySelector('.upload-missing-warning').hidden=false;setStatus(state,'File Missing');}
            actions.querySelector('.upload-dismiss').onclick = () => removeUpload(state);
            scheduleRender();
        };

        const removeUpload = async (state) => {
            if (!state || state.removed) return;
            state.removed = true;
            state.cancelled = true;
            state.pauseRequested = false;
            if (state.xhr) { try { state.xhr.abort(); } catch (_) {} }
            if (state.session && state.session.id && state.phase !== 'done') {
                try { await api('cancel_upload', {id: Number(state.session.id)}); } catch (_) {}
            }
            uploads.delete(state.id);
            if (state.card && state.card.isConnected) state.card.remove();
            scheduleRender();
        };

        const cancelUpload = async (state) => {
            if (state.phase === 'done' || state.phase === 'cancelled') return;
            state.pauseRequested = false;
            state.cancelled = true;
            state.phase = 'cancelled';
            setStatus(state, 'Cancelling…');
            const xhr = state.xhr;
            if (xhr) { try { xhr.abort(); } catch (_) {} }
            if (state.session && state.session.id) {
                try { await api('cancel_upload', {id: Number(state.session.id)}); } catch (_) {}
            }
            state.session = null;
            state.offset = 0;
            state.uploaded = 0;
            state.phase = 'cancelled';
            state.card.classList.remove('upload-error');
            setStatus(state, 'Cancelled');
            state.card.querySelector('.upload-actions').innerHTML = '<button type="button" class="btn small upload-retry">Retry</button><button type="button" class="btn small secondary upload-dismiss">Dismiss / Delete</button>';
            state.card.querySelector('.upload-retry').onclick = () => enqueueUpload(state, true);
            state.card.querySelector('.upload-dismiss').onclick = () => removeUpload(state);
            updateCard(state, true);
            scheduleRender();
        };

        const setUploadingActions = (state) => {
            const actions = state.card.querySelector('.upload-actions');
            actions.innerHTML = '<button type="button" class="btn small secondary upload-pause">Pause</button><button type="button" class="btn small secondary upload-cancel">Cancel</button>';
            actions.querySelector('.upload-pause').onclick = () => pauseUpload(state);
            actions.querySelector('.upload-cancel').onclick = () => cancelUpload(state);
        };

        const pauseUpload = (state) => {
            if(!state || !['queued','preparing','uploading','retrying','retry-wait'].includes(state.phase))return;
            state.pauseRequested=true; state.wasPaused=true;
            if(state.phase==='queued'||state.phase==='retry-wait'){state.phase='paused';setStatus(state,'Paused');renderPausedActions(state);scheduleRender();return;}
            setStatus(state,'Pausing…');
            if(state.xhr){try{state.xhr.abort()}catch(_){}}
        };
        const renderPausedActions = (state) => {const a=state.card.querySelector('.upload-actions');a.innerHTML='<button type="button" class="btn small upload-resume">Resume</button><button type="button" class="btn small secondary upload-cancel">Cancel</button><button type="button" class="btn small secondary upload-remove">× Remove</button>';a.querySelector('.upload-resume').onclick=()=>resumeUpload(state);a.querySelector('.upload-cancel').onclick=()=>cancelUpload(state);a.querySelector('.upload-remove').onclick=()=>removeUpload(state);};
        const resumeUpload = (state) => {if(!state||!['paused','missing'].includes(state.phase))return;if(!state.file){state.phase='missing';setStatus(state,'File Missing');state.card.querySelector('.upload-missing-warning').hidden=false;return;}state.pauseRequested=false;state.cancelled=false;state.card.querySelector('.upload-missing-warning').hidden=true;enqueueUpload(state,true);};
        const reselectFile = (state) => {const input=document.createElement('input');input.type='file';input.onchange=()=>{const f=input.files&&input.files[0];if(!f)return;state.file=f;state.total=f.size;state.uploaded=0;state.offset=0;state.session=null;state.currentPart=1;state.parts=[];state.phase='queued';state.card.querySelector('.upload-name').textContent=f.name;state.card.querySelector('.upload-path').textContent=f.webkitRelativePath||'';state.card.querySelector('.upload-missing-warning').hidden=true;enqueueUpload(state,true);};input.click();};
        const waitForNetwork = async (state) => {while(!navigator.onLine&&!state.cancelled&&!state.pauseRequested){state.phase='retrying';setStatus(state,'Waiting for Network');await sleep(1000);} };
        const isTransient = (e) => !navigator.onLine || /network|timeout|timed out|stalled|HTTP 429|HTTP 5\d\d|server error 429|server error 5\d\d/i.test(String(e&&e.message||e));

        const uploadLocalChunked = async (state) => {
            const x = state.session;
            const chunk = Math.max(131072, Math.min(Number(x.chunk_size || 524288), Number(x.max_bytes || 524288)));
            let start = state.offset || 0;
            while (start < state.total) {
                if(state.pauseRequested||state.cancelled) throw new Error(state.pauseRequested?'Upload paused.':'Upload cancelled.');
                state.phase = 'uploading';
                state.stalled = false;
                const end = Math.min(state.total, start + chunk);
                let result = null;
                let lastError = null;
                for (let attempt = 0; attempt < 3; attempt++) {
                    try {
                        const fd = new FormData();
                        fd.append('id', x.id);
                        fd.append('start', String(start));
                        fd.append('total', String(state.total));
                        fd.append('chunk', state.file.slice(start, end, state.file.type || 'application/octet-stream'), state.file.name + '.part');
                        result = await xhrRequest('/api/files/?action=local_chunk', {
                            method: 'POST',
                            headers: {'Accept': 'application/json', 'X-CSRF-Token': csrf},
                            body: fd
                        }, state, start);
                        lastError = null;
                        break;
                    } catch (e) {
                        if(state.pauseRequested||state.cancelled) throw e;
                        lastError = e;
                        // A lost response can mean the server already appended the chunk.
                        // Ask the server for the authoritative offset before retrying.
                        try {
                            const probe = await readJsonFetch('/api/files/?action=local_status&id=' + encodeURIComponent(x.id), {
                                method: 'GET', headers: {'Accept': 'application/json'}
                            });
                            const received = Number(probe.received || 0);
                            if (received > start) {
                                start = Math.min(state.total, received);
                                state.offset = start;
                                state.uploaded = start;
                                updateCard(state, true);
                                scheduleRender();
                                lastError = null;
                                break;
                            }
                        } catch (_) {}
                        if (attempt < 2) await new Promise((r) => setTimeout(r, 500 * (attempt + 1)));
                    }
                }
                if (lastError) throw lastError;
                if (!result) continue;
                const received = Number(result.body.received || end);
                start = Math.max(start, received);
                state.offset = start;
                state.uploaded = start;
                state.lastProgressAt = performance.now();
                updateCard(state, true);
                scheduleRender();
                if (result.body.complete) return result.body;
            }
            return {id: x.id, status: 'pending_scan', public_url: x.public_url};
        };

        const uploadServer = async (state) => {
            const x = state.session;
            const fd = new FormData();
            fd.append('id', String(x.id));
            fd.append('file', state.file, state.file.name);
            const result = await xhrRequest('/api/files/?action=server_upload', {
                method: 'POST', headers: {'Accept': 'application/json', 'X-CSRF-Token': csrf}, body: fd
            }, state, 0);
            state.uploaded = state.total;
            return result.body;
        };

        const uploadSingle = async (state) => {
            const result = await xhrRequest(state.session.upload_url, {
                method: 'PUT', headers: {'Content-Type': state.file.type || 'application/octet-stream'}, body: state.file
            }, state, 0);
            state.uploaded = state.total;
            const body = await api('complete', {id: Number(state.session.id)});
            return body;
        };

        const uploadMultipart = async (state) => {
            const x = state.session;
            if (!state.parts) state.parts = [];
            const partSize = Number(x.part_size || 104857600);
            for (let i = state.currentPart || 1; i <= x.parts.length; i++) {
                state.currentPart = i;
                const start = (i - 1) * partSize;
                const end = Math.min(state.total, i * partSize);
                const part = state.file.slice(start, end, state.file.type || 'application/octet-stream');
                const result = await xhrRequest(x.parts[i - 1], {
                    method: 'PUT', headers: {'Content-Type': state.file.type || 'application/octet-stream'}, body: part
                }, state, start);
                const etag = (result.xhr.getResponseHeader('ETag') || '').replaceAll('"', '');
                state.parts = state.parts.filter((p) => p.part !== i);
                state.parts.push({part: i, etag});
                state.parts.sort((a, b) => a.part - b.part);
                state.uploaded = end;
                updateCard(state, true);
                setStatus(state, 'Uploaded ' + i + '/' + x.parts.length + ' parts');
                scheduleRender();
                state.currentPart = i + 1;
            }
            return await api('complete', {id: Number(x.id), parts: state.parts});
        };

        const createPostUploadShare = async (state) => {
            if (!state.session || !state.session.id || state.autoShareStarted) return;
            state.autoShareStarted = true;
            try {
                let f = null;
                for (let i = 0; i < 15; i++) {
                    f = await readJsonFetch('/api/files/?action=status&id=' + encodeURIComponent(state.session.id), {method:'GET',headers:{'Accept':'application/json'}}).catch(() => null);
                    if (f && f.status === 'clean' && ['clean','skipped'].includes(f.scan_status)) break;
                    if (f && ['quarantined','deleted','expired'].includes(f.status)) return;
                    await new Promise(resolve => setTimeout(resolve, 2000));
                }
                if (!f || f.status !== 'clean' || !['clean','skipped'].includes(f.scan_status) || state.removed) return;
                const share = await api('share_create', {ids:[Number(state.session.id)],title:f.original_name || 'Shared file',expires_at:'',max_downloads:0,view_only:false});
                if (state.removed || !state.card?.isConnected) return;
                const actions = state.card.querySelector('.upload-actions');
                const a = document.createElement('a'); a.className='btn small'; a.href=share.short_url; a.target='_blank'; a.rel='noopener'; a.textContent='Open share link';
                const copy = document.createElement('button'); copy.type='button'; copy.className='btn small secondary'; copy.textContent='Copy link';
                copy.onclick=async()=>{try{await navigator.clipboard.writeText(share.short_url);copy.textContent='Copied!';setTimeout(()=>copy.textContent='Copy link',1400)}catch(_){window.prompt('Copy secure share link',share.short_url)}};
                actions.append(a,copy);
            } catch (e) {
                if (state.card?.isConnected) { const note=document.createElement('span'); note.className='muted'; note.textContent='Share link not auto-created: file may still be scanning.'; state.card.querySelector('.upload-actions')?.append(note); }
            }
        };

        async function runUpload(state, retry) {
            if (state.running) return;
            state.running = true;
            state.phase = retry ? 'retrying' : 'preparing';
            state.retryRequested = false;
            state.stalled = false;
            state.cancelled = false;
            state.card.classList.remove('upload-error');
            state.card.querySelector('.upload-actions').innerHTML = '';
            setStatus(state, retry ? 'Retrying…' : 'Preparing…');
            if (!retry) {
                state.startedAt = performance.now();
                state.speedBaseTime = state.startedAt;
                state.speedBaseBytes = state.uploaded;
            } else {
                state.lastProgressAt = performance.now();
            }
            scheduleRender();
            try {
                if (!state.session) {
                    state.session = await api('create', {
                        name: state.file.name,
                        size: state.file.size,
                        mime: state.file.type || 'application/octet-stream',
                        relative_path: state.file.webkitRelativePath || state.file.name
                    });
                }
                if (state.cancelled) throw new Error('Upload cancelled.');
                if (state.session.name) {
                    state.card.querySelector('.upload-name').textContent = state.session.name;
                }
                setUploadingActions(state);
                let result;
                if (state.session.mode === 'local') result = await uploadLocalChunked(state);
                else if (state.session.mode === 'server') result = await uploadServer(state);
                else if (state.session.mode === 'single') result = await uploadSingle(state);
                else if (state.session.mode === 'multipart') result = await uploadMultipart(state);
                else throw new Error('Unsupported upload mode.');

                state.uploaded = state.total;
                state.phase = 'done'; state.retryCount=0;
                updateCard(state, true);
                toast('Upload complete: '+state.file.name);
                setStatus(state, result.status === 'clean' ? 'Uploaded successfully' : 'Uploaded · scanning');
                state.card.classList.add('upload-success');
                state.card.querySelector('.upload-actions').innerHTML = '<span class="upload-complete-badge">✓ Ready in Files</span><button type="button" class="btn small secondary upload-remove">Dismiss</button>';
                state.card.querySelector('.upload-remove').onclick = () => removeUpload(state);
                try {
                    await fetchCompletedFile(Number(state.session.id));
                    createPostUploadShare(state);
                    window.setTimeout(()=>removeUpload(state), 2200);
                } catch (_) {
                    /* The upload itself succeeded; leave the success state visible. */
                }
                scheduleRender();
            } catch (e) {
                if (state.pauseRequested) {
                    state.pauseRequested=false; state.phase='paused'; setStatus(state,'Paused'); renderPausedActions(state); scheduleRender();
                } else if (state.cancelled) {
                    state.phase = 'cancelled';
                    setStatus(state, 'Cancelled');
                    state.card.querySelector('.upload-actions').innerHTML = '<button type="button" class="btn small upload-retry">Retry</button><button type="button" class="btn small secondary upload-dismiss">Dismiss / Delete</button>';
                    state.card.querySelector('.upload-retry').onclick = () => enqueueUpload(state, true);
                    state.card.querySelector('.upload-dismiss').onclick = () => removeUpload(state);
                } else if (isTransient(e) && (state.retryCount||0) < 8 && !state.removed) {
                    // Release the active slot immediately and requeue this upload at the tail.
                    // A flaky item must never block the next pending file in a large batch.
                    state.retryCount=(state.retryCount||0)+1;
                    state.phase='retry-wait';
                    setStatus(state, navigator.onLine?'Retry queued ('+state.retryCount+')':'Waiting for Network · queued for retry');
                    state.card.querySelector('.upload-actions').innerHTML='<button type="button" class="btn small secondary upload-pause">Pause</button><button type="button" class="btn small secondary upload-cancel">Cancel</button>';
                    state.card.querySelector('.upload-pause').onclick=()=>pauseUpload(state);
                    state.card.querySelector('.upload-cancel').onclick=()=>cancelUpload(state);
                    uploads.delete(state.id); uploads.set(state.id,state);
                    const delay=retryDelays[Math.min(state.retryCount-1,retryDelays.length-1)];
                    window.setTimeout(()=>{if(state.removed||state.cancelled||state.phase!=='retry-wait')return;state.phase='queued';state.retryRequested=true;setStatus(state,'Retry queued · waiting for an upload slot');pumpQueue();},delay);
                } else {
                    state.retryCount=0; setError(state, e.message || 'Upload failed.');
                }
            } finally {
                state.running = false;
                if(state.phase==='queued'&&state.retryRequested&&!state.cancelled&&!state.removed){state.retryRequested=false;queueMicrotask(()=>pumpQueue());}
                scheduleRender();
            }
        }

        const add = (list) => {
            const files = [...list].filter((file) => file && Number(file.size || 0) > 0);
            files.forEach((file) => {
                const state = {
                    id: 'up-' + Date.now() + '-' + Math.random().toString(36).slice(2),
                    file,
                    total: Number(file.size || 0),
                    uploaded: 0,
                    offset: 0,
                    currentPart: 1,
                    parts: [],
                    phase: 'queued',
                    card: null,
                    session: null,
                    running: false,
                    cancelled: false,
                    retryRequested: false,
                    speed: 0,
                    lastSpeed: 0,
                    speedBaseBytes: 0,
                    speedBaseTime: performance.now(),
                    lastProgressAt: performance.now(), selected:false, pauseRequested:false, retryCount:0, wasPaused:false
                };
                state.card = makeCard(file);
                state.card.querySelector('.upload-select').addEventListener('change',e=>{state.selected=e.target.checked;scheduleRender()});
                uploads.set(state.id, state);
                setStatus(state, 'Queued…');
                setQueuedActions(state);
            });
            picker.value = '';
            scheduleRender();
            pumpQueue();
        };

        window.addEventListener('offline',()=>{[...uploads.values()].filter(s=>['uploading','retrying','preparing'].includes(s.phase)).forEach(s=>{if(s.phase!=='preparing')setStatus(s,'Waiting for Network');});scheduleRender();});
        window.addEventListener('online',()=>{[...uploads.values()].filter(s=>['retrying','retry-wait'].includes(s.phase)&&!s.running).forEach(s=>enqueueUpload(s,true));scheduleRender();});
        picker.addEventListener('change', () => add(picker.files));
        folderPicker?.addEventListener('change', () => { add(folderPicker.files); folderPicker.value = ''; });
        document.getElementById('bulkUpload')?.addEventListener('click', () => picker.click());
        ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('drag'); }));
        ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove('drag'); }));
        const droppedFiles = async (dt) => {
            const direct = [...(dt?.files || [])];
            const items = [...(dt?.items || [])];
            if (!items.length) return direct;
            const out = [];
            const walk = async (entry, path = '') => {
                if (!entry) return;
                if (entry.isFile) {
                    await new Promise((resolve) => entry.file((file) => {
                        try { Object.defineProperty(file, 'webkitRelativePath', {value: path + file.name, configurable: true}); } catch (_) {}
                        out.push(file); resolve();
                    }, resolve));
                    return;
                }
                if (entry.isDirectory) {
                    const reader = entry.createReader();
                    await new Promise((resolve) => {
                        const read = () => reader.readEntries(async (entries) => {
                            if (!entries.length) return resolve();
                            for (const child of entries) await walk(child, path + entry.name + '/');
                            read();
                        }, resolve);
                        read();
                    });
                }
            };
            for (const item of items) {
                const entry = item.webkitGetAsEntry?.();
                if (entry) await walk(entry);
                else { const file = item.getAsFile?.(); if (file) out.push(file); }
            }
            return out.length ? out : direct;
        };
        drop.addEventListener('drop', async (e) => add(await droppedFiles(e.dataTransfer)));
        document.getElementById('choose')?.addEventListener('click', () => picker.click());
        document.getElementById('chooseFolder')?.addEventListener('click', () => folderPicker?.click());

        const search = document.getElementById('fileSearch');
        const kind = document.getElementById('kindFilter');
        const filter = () => {
            const text = (search?.value || '').toLowerCase();
            const k = kind?.value || '';
            grid?.querySelectorAll('.file-card').forEach((c) => {
                c.hidden = !!((text && !c.dataset.name.includes(text)) || (k && c.dataset.kind !== k));
            });
        };
        search?.addEventListener('input', filter);
        kind?.addEventListener('change', filter);
        document.getElementById('tableToggle')?.addEventListener('click', (e) => { grid.classList.toggle('table-view'); e.currentTarget.textContent = grid.classList.contains('table-view') ? 'Grid view' : 'Table view'; });

        const visibleCards = () => [...(grid?.querySelectorAll('.file-card') || [])].filter(c => !c.hidden);
        const count = () => {
            const selectedBoxes = [...document.querySelectorAll('.file-select:checked')];
            const n = selectedBoxes.length;
            const el = document.getElementById('selectionCount');
            if (el) el.textContent = n + ' item' + (n === 1 ? '' : 's') + ' selected';
            const all = document.getElementById('selectAllFiles');
            if (all) {
                const visible = visibleCards();
                all.checked = visible.length > 0 && visible.every(c => c.querySelector('.file-select')?.checked);
                all.indeterminate = visible.some(c => c.querySelector('.file-select')?.checked) && !all.checked;
            }
            return n;
        };
        document.addEventListener('change', (e) => { if (e.target.classList.contains('file-select')) count(); });
        let longPressTimer=0;let longPressTarget=null;
        grid?.addEventListener('touchstart', e=>{const card=e.target.closest('.file-card');if(!card||e.target.closest('button,a,input,summary,select,details'))return;longPressTarget=card;clearTimeout(longPressTimer);longPressTimer=window.setTimeout(()=>{const cb=card.querySelector('.file-select');if(cb){cb.checked=true;cb.dispatchEvent(new Event('change',{bubbles:true}));card.classList.add('file-card-selected');count();toast('Selected '+(card.querySelector('.file-main strong')?.textContent||'file'));}longPressTarget=null;},500);},{passive:true});
        ['touchend','touchmove','touchcancel'].forEach(ev=>grid?.addEventListener(ev,()=>{clearTimeout(longPressTimer);longPressTimer=0;longPressTarget=null;},{passive:true}));
        document.getElementById('selectAllFiles')?.addEventListener('change', (e) => {
            visibleCards().forEach(card => { const x = card.querySelector('.file-select'); if (x) x.checked = e.target.checked; }); count();
        });
        document.getElementById('clearFileSelection')?.addEventListener('click', () => {
            document.querySelectorAll('.file-select').forEach(x => x.checked = false); count();
        });
        // Recalculate select-all state when filters change.
        search?.addEventListener('input', count);
        kind?.addEventListener('change', count);

        bindFileActions();

        const selected = () => [...document.querySelectorAll('.file-select:checked')].map((x) => Number(x.value));
        document.getElementById('bulkDelete')?.addEventListener('click', async () => {
            const ids = selected(); if (!ids.length) return notify('No files selected', 'Select at least one file first.');
            const warning = 'Move ' + ids.length + ' selected file(s) to Trash? They can be restored later.';
            if (!(await dialog('Move selected files to Trash?', warning+' Selected files will remain recoverable.', 'Move to Trash'))) return;
            const b = document.getElementById('bulkDelete'), status = document.getElementById('bulkDownloadStatus');
            b.disabled = true; const old = b.textContent; b.textContent = 'Deleting…';
            try {
                const j = await api('bulk_delete', {ids});
                const moved = j.trashed || j.deleted || [];
                moved.forEach(id => document.getElementById('file-' + id)?.remove());
                const failures = j.failed || [];
                if (status) status.textContent = failures.length ? `${moved.length} moved to Trash; ${failures.length} failed.` : `${moved.length} item(s) moved to Trash.`;
                if (moved.length) toast('🗑️ '+moved.length+' item(s) moved to Trash');
                count();
                const stat = document.getElementById('fileCountStat');
                if (stat) stat.textContent = String(grid?.querySelectorAll('.file-card:not(.folder-card)').length || 0);
                if (!grid?.querySelector('.file-card:not(.folder-card)')) {
                    let empty = document.getElementById('emptyFiles');
                    if (!empty) { empty = document.createElement('div'); empty.id = 'emptyFiles'; empty.className = 'empty-state'; empty.textContent = 'No files uploaded yet.'; grid?.after(empty); }
                }
                if (failures.length) await notify('Some items were not moved', failures.map(x => `#${x.id}: ${x.error}`).join('\n'));
            } catch (e) { await notify('Bulk move failed', e.message || 'Bulk delete failed.'); }
            finally { b.disabled = false; b.textContent = old; }
        });

        document.getElementById('downloadZip')?.addEventListener('click', async () => {
            const ids = selected(); if (!ids.length) return notify('No files selected', 'Select at least one file first.');
            const mode = document.getElementById('dashboardBulkMode')?.value || 'single';
            const format = document.getElementById('dashboardArchiveFormat')?.value || 'zip';
            const status = document.getElementById('bulkDownloadStatus');
            if (mode === 'original') {
                if (!(await dialog('Download original files?', `Download ${ids.length} original file(s) individually? Your browser may ask permission for multiple downloads. Original names and extensions will be preserved.`, 'Download'))) return;
                const chosen = new Set(ids.map(String));
                const cards = [...document.querySelectorAll('#fileGrid .file-card')]
                    .filter(card => chosen.has(card.dataset.fileId));
                if (!cards.length) return notify('Selection unavailable', 'Could not find the selected files. Refresh the page and try again.');
                const originalButton = document.getElementById('downloadZip');
                if (originalButton) { originalButton.disabled = true; originalButton.textContent = 'Starting downloads…'; }
                let started = 0; const failures = [];
                for (const card of cards) {
                    try {
                        const id = Number(card.dataset.fileId), token = card.dataset.token || '';
                        if (!id || !token) throw new Error('Missing file download metadata.');
                        // Ask the authenticated backend for a one-time download URL. This avoids
                        // accidentally downloading an HTML fallback page and keeps download limits
                        // and analytics consistent with single-file downloads.
                        const result = await api('download_start', {id, token});
                        if (!result.url || typeof result.url !== 'string') throw new Error('The server did not return a download URL.');
                        const a = document.createElement('a'); a.href = result.url; a.rel = 'noopener';
                        // The authenticated stream endpoint sends the authoritative filename in Content-Disposition.
                        // Do not force a client-side filename that can mask a server-side HTML error response.
                        document.body.appendChild(a); a.click(); a.remove(); started++;
                    } catch (err) {
                        failures.push((card.querySelector('.file-main strong')?.textContent || ('File #' + card.dataset.fileId)) + ': ' + (err.message || 'download failed'));
                    }
                    await new Promise(resolve => setTimeout(resolve, 450));
                }
                if (status) status.textContent = failures.length
                    ? `${started} download(s) started; ${failures.length} failed. ${failures.join(' | ')}`
                    : `${started} original download(s) started. Original filenames and extensions are preserved.`;
                if (failures.length) await notify('Some downloads could not start', failures.join('\n'));
                if (originalButton) { originalButton.disabled = false; originalButton.textContent = 'Download selected'; }
                return;
            }
            const b = document.getElementById('downloadZip'); b.disabled = true;
            if (status) status.textContent = 'Preparing archive on the server… your browser will stream the download directly.';
            try {
                // Native form navigation lets the browser stream large attachments without buffering the whole archive in JS memory.
                const form = document.createElement('form'); form.method = 'POST'; form.action = '/api/files/?action=zip'; form.target = '_blank'; form.hidden = true;
                const add = (name, value) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = String(value); form.appendChild(input); };
                add('_csrf', csrf); add('mode', mode); add('format', format); ids.forEach(id => add('ids[]', id));
                document.body.appendChild(form); form.submit(); form.remove();
                if (status) status.textContent = 'Archive request submitted. The server is preparing a streamed download.';
            } catch (e) { if (status) status.textContent = e.message || 'Archive download failed.'; await notify('Download failed', e.message || 'Archive download failed.'); }
            finally { b.disabled = false; }
        });

        const folderModal = document.getElementById('folderModal');
        document.getElementById('createFolder')?.addEventListener('click',()=>{if(folderModal)folderModal.hidden=false;document.getElementById('newFolderName')?.focus();});
        document.getElementById('closeFolder')?.addEventListener('click',()=>{if(folderModal)folderModal.hidden=true;});
        document.getElementById('saveFolder')?.addEventListener('click',async()=>{
            const input=document.getElementById('newFolderName'),result=document.getElementById('folderResult'),btn=document.getElementById('saveFolder');
            const name=(input?.value||'').trim(); if(!name){if(result)result.textContent='Enter a folder name.';return;}
            btn.disabled=true; if(result)result.textContent='Creating folder…';
            try{await api('folder_create',{name});let library=document.querySelector('.folder-library .file-grid');if(!library){const section=document.createElement('section');section.className='card folder-library';section.innerHTML='<div class="file-toolbar"><div><h2 style="margin:0">Folders</h2><p class="muted" style="margin:.25rem 0 0">Organize uploads using named folders.</p></div></div><div class="file-grid"></div>';document.querySelector('.upload-panel')?.after(section);library=section.querySelector('.file-grid');}if(library){const exists=[...library.querySelectorAll('.folder-card')].some(c=>(c.dataset.folderName||'').toLowerCase()===name.toLowerCase());if(!exists){const card=document.createElement('article');card.className='file-card folder-card';card.dataset.folderName=name;card.dataset.name=name.toLowerCase();card.dataset.size='0';card.innerHTML='<div class="file-card-top"><span class="file-icon">📁</span><div class="file-main"><strong></strong><span class="muted">Folder</span></div></div><div class="file-meta"><span>Created just now</span></div><div class="file-link-strip"><button type="button" class="folder-info">Info</button></div>';card.querySelector('strong').textContent=name;const info=card.querySelector('.folder-info');info.dataset.name=name;info.dataset.created=new Date().toISOString();info.addEventListener('click',()=>notify('Folder information','Name: '+name+'\nCreated: '+info.dataset.created+'\nFolder type: Shared Files'));library.prepend(card);window.dispatchEvent(new CustomEvent('pocketcat:media-added'));}}if(result)result.textContent='Folder created.';if(folderModal)folderModal.hidden=true;input.value='';}
            catch(e){if(result)result.textContent=e.message||'Could not create folder.';}
            finally{btn.disabled=false;}
        });
        document.querySelectorAll('.folder-info').forEach(b=>b.addEventListener('click',()=>notify('Folder information','Name: '+b.dataset.name+'\nCreated: '+b.dataset.created+'\nFolder type: Shared Files')));
        const expiryPreset=document.getElementById('shareExpiryPreset'), customExpiry=document.getElementById('shareExpiry');
        expiryPreset?.addEventListener('change',()=>{if(customExpiry)customExpiry.disabled=expiryPreset.value!=='custom';});
        const resolvedExpiry=()=>{
            if(!expiryPreset||expiryPreset.value==='never')return '';
            if(expiryPreset.value==='custom')return customExpiry?.value||'';
            const seconds={'1h':3600,'6h':21600,'24h':86400,'2d':172800,'7d':604800,'1m':2592000}[expiryPreset.value];
            if(!seconds)return '';const d=new Date(Date.now()+seconds*1000);const pad=n=>String(n).padStart(2,'0');return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes());
        };

        const modal = document.getElementById('shareModal');
        const openShare = () => { if (!selected().length) return notify('No files selected', 'Select at least one file first.'); if (modal) modal.hidden = false; };
        document.getElementById('createShare')?.addEventListener('click', openShare);
        // Delegate the create-share action so it works for both initial and newly uploaded cards.
        // Media preview controls deliberately use a different class and never open the share modal.
        document.addEventListener('click', (event) => {
            const b = event.target.closest('.quick-share');
            if (!b || !document.getElementById('fileGrid')?.contains(b)) return;
            const id = Number(b.dataset.id);
            if (!id) return;
            document.querySelectorAll('.file-select').forEach((x) => { x.checked = Number(x.value) === id; });
            count(); openShare();
        });
        document.getElementById('closeShare')?.addEventListener('click', () => { if (modal) modal.hidden = true; });
        document.getElementById('saveShare')?.addEventListener('click', async () => {
            const btn = document.getElementById('saveShare'); btn.disabled = true;
            try {
                const j = await api('share_create', {ids: selected(), title: document.getElementById('shareTitle').value, password: document.getElementById('sharePassword').value, expires_at: resolvedExpiry(), max_downloads: Number(document.getElementById('shareMax').value || 0), view_only: document.getElementById('shareViewOnly').checked, theme_accent: document.getElementById('themeAccent').value, theme_background: document.getElementById('themeBackground').value, theme_card: document.getElementById('themeCard').value, og_description: document.getElementById('ogDescription').value, og_image_url: document.getElementById('ogImage').value, os: {iOS: document.getElementById('routeIos').value, Android: document.getElementById('routeAndroid').value, Windows: document.getElementById('routeWindows').value, macOS: document.getElementById('routeMac').value}, country_code: document.getElementById('routeCountry').value, country_url: document.getElementById('routeCountryUrl').value, deep_link_url: document.getElementById('deepLink').value, web_fallback_url: document.getElementById('webFallback').value, utm_source: document.getElementById('utmSource').value, utm_medium: document.getElementById('utmMedium').value, utm_campaign: document.getElementById('utmCampaign').value, utm_term: document.getElementById('utmTerm').value, utm_content: document.getElementById('utmContent').value, meta_pixel: document.getElementById('metaPixel').value, gtm_id: document.getElementById('gtmId').value, tiktok_pixel: document.getElementById('tiktokPixel').value, linkedin_tag: document.getElementById('linkedinTag').value});
                document.getElementById('shareResult').innerHTML = '<strong>Share ready.</strong><br><a target="_blank" href="' + esc(j.short_url) + '">' + esc(j.short_url) + '</a><br><small>Analytics: <a href="/link-analytics/?id=' + j.link_id + '">Open link analytics</a> · <a target="_blank" href="' + esc(j.qr_url) + '">Customize QR</a></small>';
            } catch (e) { await notify('Delete failed', e.message); } finally { btn.disabled = false; }
        });

        window.FILE_UPLOAD_FORMAT = {formatBytes, formatSpeed, formatEta};
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once: true});
    else init();
}());
