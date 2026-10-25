(function () {
    'use strict';

    function init() {
        const box = document.getElementById('support-messages');
        const form = document.getElementById('support-form');
        const input = document.getElementById('support-input');
        const fileInput = document.getElementById('support-file');
        const attachBox = document.getElementById('support-attachments');
        const tid = box?.dataset.thread;
        const csrf = window.POCKETCAT_CSRF || '';
        if (!box || !form || !input || !fileInput || !attachBox || !tid) return;

        const uploads = new Map();
        const selected = [];
        const MAX_CONCURRENT = 3;
        let active = 0;

        const esc = (s) => String(s ?? '').replace(/[&<>'"]/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
        }[c] || c));
        const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
        const bytes = (n) => {
            n = Math.max(0, Number(n) || 0);
            if (n < 1024) return n.toFixed(0) + ' B';
            const u = ['KB', 'MB', 'GB', 'TB']; let v = n / 1024; let i = 0;
            while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
            return v.toFixed(v >= 100 ? 0 : v >= 10 ? 1 : 2) + ' ' + u[i];
        };
        const speed = (n) => n > 0 ? bytes(n) + '/s' : '—';
        const json = async (r) => {
            const text = await r.text(); let d = {};
            try { d = JSON.parse(text); } catch (_) {}
            if (!r.ok || d.ok === false) throw new Error(d.error || ('Request failed (HTTP ' + r.status + ').'));
            return d;
        };
        const post = (url, body) => fetch(url, {method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}, body}).then(json);
        const api = (action, data) => {
            const fd = new FormData(); fd.append('_csrf', csrf); fd.append('action', action);
            Object.entries(data || {}).forEach(([k, v]) => fd.append(k, String(v)));
            return post('/api/upload-chunk/', fd);
        };

        const drawSummary = () => {
            let s = document.getElementById('support-upload-summary');
            if (!s) {
                s = document.createElement('div'); s.id = 'support-upload-summary'; s.className = 'support-upload-summary';
                attachBox.before(s);
            }
            const all = [...uploads.values()];
            const total = all.reduce((n, x) => n + x.total, 0);
            const done = all.reduce((n, x) => n + Math.min(x.total, x.loaded), 0);
            const now = performance.now();
            const currentSpeed = all.filter((x) => ['uploading', 'retrying', 'preparing'].includes(x.phase)).reduce((n, x) => n + (now - (x.lastProgressAt || now) <= 2000 ? (x.lastSpeed || 0) : 0), 0);
            const pct = total ? Math.min(100, done / total * 100) : 0;
            s.innerHTML = '<div><span>Upload progress</span><strong>' + pct.toFixed(pct < 10 ? 1 : 0) + '% / 100%</strong></div>' +
                '<div><span>Upload size</span><strong>' + bytes(done) + ' / ' + bytes(total) + '</strong></div>' +
                '<div><span>Network speed</span><strong>' + speed(currentSpeed) + '</strong></div>' +
                '<div><span>Queue</span><strong>' + active + ' active · ' + all.filter((x) => x.phase === 'queued').length + ' queued</strong></div>';
            s.hidden = !all.length;
        };

        const renderCard = (state) => {
            state.card.querySelector('.support-upload-progress i').style.width = (state.total ? Math.min(100, state.loaded / state.total * 100) : 0).toFixed(2) + '%';
            state.card.querySelector('.support-upload-size').textContent = bytes(state.loaded) + ' / ' + bytes(state.total);
            state.card.querySelector('.support-upload-percent').textContent = Math.round(state.total ? state.loaded / state.total * 100 : 0) + '% / 100%';
            state.card.querySelector('.support-upload-speed').textContent = speed(state.lastSpeed || 0);
        };
        const setStatus = (state, text) => { state.card.querySelector('.support-upload-status').textContent = text; state.card.dataset.state = state.phase; };
        const card = (file) => {
            const d = document.createElement('div'); d.className = 'support-upload-card';
            d.innerHTML = '<div class="support-upload-head"><strong class="support-upload-name"></strong><span class="support-upload-status">Queued…</span></div>' +
                '<div class="support-upload-progress"><i></i></div>' +
                '<div class="support-upload-metrics"><span>Size: <b class="support-upload-size">0 B / ' + esc(bytes(file.size)) + '</b></span><span>Progress: <b class="support-upload-percent">0% / 100%</b></span><span>Network: <b class="support-upload-speed">—</b></span></div>' +
                '<div class="support-upload-actions"><button type="button" class="btn small secondary support-upload-cancel">Cancel</button></div>';
            d.querySelector('.support-upload-name').textContent = file.name;
            attachBox.appendChild(d); return d;
        };

        const request = (state, offset, chunkFile) => new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest(); xhr.open('POST', '/api/upload-chunk/', true); xhr.responseType = 'text'; state.xhr = xhr;
            const fd = new FormData(); fd.append('_csrf', csrf); fd.append('action', 'chunk'); fd.append('token', state.token); fd.append('offset', String(offset));
            fd.append('chunk', chunkFile, state.file.name + '.part');
            let loaded = 0; let started = performance.now(); let settled = false;
            xhr.upload.onprogress = (e) => {
                if (!e.lengthComputable) return;
                loaded = e.loaded; const now = performance.now(); const elapsed = Math.max(.001, (now - started) / 1000);
                state.loaded = Math.min(state.total, offset + e.loaded); state.lastSpeed = e.loaded / elapsed; state.lastProgressAt = now; renderCard(state); drawSummary();
            };
            xhr.onload = () => {
                if (settled) return; settled = true; state.xhr = null; let d = {};
                try { d = xhr.responseText ? JSON.parse(xhr.responseText) : {}; } catch (_) {}
                if (xhr.status >= 200 && xhr.status < 300 && d.ok !== false) return resolve(d);
                reject(new Error(d.error || ('Upload failed (HTTP ' + xhr.status + ').')));
            };
            xhr.onerror = () => { if (!settled) { settled = true; state.xhr = null; reject(new Error('Network error while uploading.')); } };
            xhr.onabort = () => { if (!settled) { settled = true; state.xhr = null; reject(new Error(state.cancelled ? 'Upload cancelled.' : 'Upload aborted.')); } };
            xhr.send(fd);
        });

        async function upload(state) {
            state.phase = 'preparing'; setStatus(state, 'Preparing…');
            if (!state.token) {
                const x = await api('init', {scope: 'support', context_id: tid, name: state.file.name, size: state.file.size, mime: state.file.type || 'application/octet-stream', relative_path: state.file.webkitRelativePath || state.file.name});
                state.token = x.token; state.chunkSize = Number(x.chunk_size || 262144); state.name = x.name || state.file.name;
                state.card.querySelector('.support-upload-name').textContent = state.name;
            }
            let offset = state.offset || 0;
            while (offset < state.total) {
                if (state.cancelled) throw new Error('Upload cancelled.');
                state.phase = 'uploading'; state.stalled = false; setStatus(state, 'Uploading…');
                const end = Math.min(state.total, offset + state.chunkSize);
                let result = null; let last = null;
                for (let attempt = 0; attempt < 3; attempt++) {
                    try {
                        result = await request(state, offset, state.file.slice(offset, end, state.file.type || 'application/octet-stream')); last = null; break;
                    } catch (e) {
                        last = e;
                        try {
                            const st = await api('status', {token: state.token});
                            const received = Number(st.received || 0);
                            if (received > offset) { offset = Math.min(state.total, received); state.offset = offset; state.loaded = offset; renderCard(state); drawSummary(); last = null; break; }
                        } catch (_) {}
                        if (attempt < 2) await sleep(500 * (attempt + 1));
                    }
                }
                if (last) throw last;
                if (!result) continue;
                offset = Math.max(offset, Number(result.received || end)); state.offset = offset; state.loaded = offset; renderCard(state); drawSummary();
            }
            const done = await api('complete', {token: state.token});
            state.loaded = state.total; state.phase = 'done'; renderCard(state); setStatus(state, 'Uploaded');
            state.card.querySelector('.support-upload-actions').innerHTML = '<span class="upload-complete-badge">✓ Ready</span>';
            return done;
        }

        const enqueue = (state, retry) => {
            if (state.phase === 'done') return;
            state.cancelled = false; state.phase = 'queued'; setStatus(state, retry ? 'Queued for retry…' : 'Queued…');
            state.card.querySelector('.support-upload-actions').innerHTML = '<button type="button" class="btn small secondary support-upload-cancel">Cancel</button>';
            state.card.querySelector('.support-upload-cancel').onclick = () => cancel(state);
            pump();
        };
        const fail = (state, message) => {
            state.phase = 'error'; state.card.classList.add('upload-error'); setStatus(state, message || 'Upload failed.');
            state.card.querySelector('.support-upload-actions').innerHTML = '<button type="button" class="btn small support-upload-retry">Retry upload</button><button type="button" class="btn small secondary support-upload-cancel">Cancel</button>';
            state.card.querySelector('.support-upload-retry').onclick = () => enqueue(state, true);
            state.card.querySelector('.support-upload-cancel').onclick = () => cancel(state); drawSummary();
        };
        const cancel = async (state) => {
            if (state.phase === 'done' || state.phase === 'cancelled') return;
            state.cancelled = true; state.phase = 'cancelled'; setStatus(state, 'Cancelling…');
            if (state.xhr) { try { state.xhr.abort(); } catch (_) {} }
            if (state.token) { try { await api('abort', {token: state.token}); } catch (_) {} }
            state.token = ''; state.offset = 0; state.loaded = 0;
            state.card.querySelector('.support-upload-actions').innerHTML = '<button type="button" class="btn small support-upload-retry">Retry upload</button>';
            state.card.querySelector('.support-upload-retry').onclick = () => enqueue(state, true);
            setStatus(state, 'Cancelled'); renderCard(state); drawSummary(); pump();
        };
        function pump() {
            while (active < MAX_CONCURRENT) {
                const next = [...uploads.values()].find((x) => x.phase === 'queued' && !x.cancelled && !x.running);
                if (!next) break; active++; next.running = true;
                upload(next).catch((e) => { if (next.cancelled) { setStatus(next, 'Cancelled'); } else fail(next, e.message); }).finally(() => { next.running = false; active = Math.max(0, active - 1); drawSummary(); pump(); });
            }
            drawSummary();
        }
        function add(list) {
            [...list].forEach((file) => {
                if (!file || file.size < 1) return;
                selected.push(file);
                const state = {file, total: file.size, loaded: 0, offset: 0, token: '', name: file.name, phase: 'queued', running: false, cancelled: false, lastSpeed: 0, lastProgressAt: performance.now(), xhr: null, card: null};
                state.card = card(file); uploads.set('u' + Date.now() + Math.random(), state);
                state.card.querySelector('.support-upload-cancel').onclick = () => cancel(state); renderCard(state);
            });
            fileInput.value = ''; pump();
        }

        fileInput.addEventListener('change', () => add(fileInput.files));
        const choose = document.getElementById('support-choose-files'); choose?.addEventListener('click', () => fileInput.click());
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = input.value.trim();
            if (!body && !selected.length) return;
            const btn = form.querySelector('button[type="submit"]'); input.disabled = true; if (btn) btn.disabled = true;
            try {
                // Finish any queued/failed uploads before sending the message.
                [...uploads.values()].filter((x) => ['queued', 'error', 'cancelled'].includes(x.phase)).forEach((x) => { if (x.phase !== 'done') enqueue(x, x.phase !== 'queued'); });
                while ([...uploads.values()].some((x) => ['queued', 'preparing', 'uploading', 'retrying'].includes(x.phase))) await sleep(150);
                const failed = [...uploads.values()].find((x) => x.phase !== 'done');
                if (failed) throw new Error('Please retry or remove the failed attachment before sending.');
                const tokens = [...uploads.values()].map((x) => x.token).filter(Boolean);
                const fd = new FormData(); fd.append('action', 'send'); fd.append('thread_id', tid); fd.append('body', body); fd.append('_csrf', csrf); tokens.forEach((t) => fd.append('upload_tokens[]', t));
                const d = await post('/api/support/', fd);
                input.value = ''; selected.length = 0; uploads.clear(); attachBox.innerHTML = ''; document.getElementById('support-upload-summary')?.remove();
                render([d]); box.scrollTop = box.scrollHeight;
            } catch (err) {
                const n = document.createElement('div'); n.className = 'flash error'; n.textContent = err.message || 'Could not send message.'; form.parentNode.insertBefore(n, form); setTimeout(() => n.remove(), 7000);
            } finally { input.disabled = false; if (btn) btn.disabled = false; input.focus(); }
        });

        function render(ms) {
            for (const m of ms) {
                if (document.querySelector('[data-mid="' + m.id + '"]')) continue;
                const d = document.createElement('div'); d.className = 'support-message ' + (m.sender_role === 'admin' ? 'from-admin' : 'from-user'); d.dataset.mid = m.id;
                const atts = m.attachments || (m.attachment_path ? [m.attachment_path] : []);
                d.innerHTML = '<small>' + esc(m.sender_role === 'admin' ? 'Admin' : 'You') + ' · ' + esc(m.created_at) + '</small><div>' + esc(m.body || '').replace(/\n/g, '<br>') + '</div>' + atts.map((x) => '<p><a class="btn small secondary" target="_blank" rel="noopener" href="' + esc(x) + '">Open attachment</a></p>').join(''); box.appendChild(d);
            }
        }
        async function poll() { try { const fd = new URLSearchParams({action: 'messages', thread_id: tid, _csrf: csrf}); const d = await post('/api/support/', fd); if (d.ok) render(d.messages || []); } catch (_) {} }
        box.scrollTop = box.scrollHeight; setInterval(poll, 4000); drawSummary();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once: true}); else init();
}());
