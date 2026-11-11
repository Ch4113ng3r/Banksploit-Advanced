<?php

declare(strict_types=1);

/**
 * Request inspector — a live view over the request_log table populated by the
 * RequestLogger middleware. Lists recent requests; clicking one expands full
 * headers / request body / response body. User-controlled fields are badged,
 * and known-vulnerable endpoints get inline "try changing this" annotations.
 * Filter by URL/method/status, auto-refresh (AJAX poll), and clear the log.
 *
 * All data is fetched from /api/v1/training/requests (see training/api.php).
 */

require_once __DIR__ . '/_shared.php';

auth()->requireAuth();

$url  = htmlspecialchars(APP_URL);

// Annotation map: endpoint substring -> hint shown next to user-controlled fields.
$annotations = [
    '/api/v1/transfer/execute'   => 'from_account_id / amount — try another account id or a negative amount (transfer.l1 / l6)',
    '/api/v1/profile'            => 'user_id / role — IDOR + mass assignment (profile.l5)',
    '/api/v1/cards'              => '{id} — IDOR; response leaks full PAN+CVV (cards.l24)',
    '/api/v1/accounts'           => '{id} — IDOR on account / statement (transfer.l1)',
    '/api/v1/admin'              => 'no role check at L0 — broken function-level authz (admin.l4)',
    '/login'                     => "customer_id — try ADMIN001' --  (auth.g1)",
    '/api/v1/loans'              => 'interest_rate / document_url / xml — tampering, SSRF, XXE',
    '/api/v1/payments'           => 'biller_name — stored XSS (beneficiary.g3)',
    '/statements'                => 'q / account_id — SQLi + IDOR (statements.g2 / transfer.l1)',
];
$annJson = json_encode($annotations, JSON_UNESCAPED_SLASHES);

$content = <<<HTML
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-white">Request Inspector</h2>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-xs text-slate-400">
                <input type="checkbox" id="autoRefresh" onchange="toggleAuto()" class="accent-indigo-500"> Auto-refresh (2s)
            </label>
            <button onclick="clearLog()" class="rounded-lg bg-slate-800 hover:bg-red-600/40 px-3 py-1.5 text-slate-300 text-sm">Clear log</button>
        </div>
    </div>

    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-3 mb-4 grid grid-cols-1 sm:grid-cols-4 gap-2">
        <input id="fq" oninput="reload()" placeholder="filter URL…" class="rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 text-sm">
        <select id="fmethod" onchange="reload()" class="rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 text-sm">
            <option value="">any method</option><option>GET</option><option>POST</option><option>PUT</option><option>DELETE</option>
        </select>
        <input id="fstatus" oninput="reload()" placeholder="status (e.g. 200)" class="rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 text-sm">
        <div class="text-xs text-slate-500 self-center" id="count"></div>
    </div>

    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="text-slate-500 text-xs uppercase text-left bg-slate-900/80">
                <th class="px-4 py-2">Method</th><th class="px-4 py-2">URL</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Duration</th><th class="px-4 py-2">When</th>
            </tr></thead>
            <tbody id="reqRows"></tbody>
        </table>
    </div>

    <script>
    const API = "{$url}/api/v1/training";
    const ANN = {$annJson};
    let timer = null;

    function methodColor(m){ return {GET:'text-sky-300',POST:'text-amber-300',PUT:'text-indigo-300',DELETE:'text-red-300'}[m] || 'text-slate-300'; }
    function statusColor(c){ c=+c; return c>=500?'text-red-400':c>=400?'text-amber-400':c>=300?'text-sky-400':'text-emerald-400'; }
    function esc(s){ return String(s==null?'':s).replace(/[&<>]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }

    function annotate(url){
        for (const [k,v] of Object.entries(ANN)) if (url.indexOf(k)===0 || url.indexOf(k)!==-1) return v;
        return null;
    }

    async function reload(){
        const q = document.getElementById('fq').value;
        const m = document.getElementById('fmethod').value;
        const s = document.getElementById('fstatus').value;
        const u = new URL(API + "/requests");
        u.search = new URLSearchParams({q, method:m, status:s});
        const d = await (await fetch(u)).json();
        const tb = document.getElementById('reqRows');
        tb.innerHTML = '';
        (d.requests||[]).forEach(r => {
            const tr = document.createElement('tr');
            tr.className = 'border-t border-slate-800 hover:bg-slate-800/40 cursor-pointer';
            tr.onclick = () => expand(r.id, tr);
            tr.innerHTML = `<td class="px-4 py-2 font-mono \${methodColor(r.method)}">\${esc(r.method)}</td>`+
                `<td class="px-4 py-2 text-slate-300 font-mono text-xs break-all">\${esc(r.url)}</td>`+
                `<td class="px-4 py-2 font-mono \${statusColor(r.response_code)}">\${esc(r.response_code)}</td>`+
                `<td class="px-4 py-2 text-slate-500 text-xs">\${esc(r.duration_ms)}ms</td>`+
                `<td class="px-4 py-2 text-slate-500 text-xs">\${esc((r.created_at||'').substr(11,8))}</td>`;
            tb.appendChild(tr);
        });
        document.getElementById('count').textContent = (d.requests||[]).length + ' requests';
    }

    async function expand(id, rowEl){
        // collapse if already open
        if (rowEl.nextElementSibling && rowEl.nextElementSibling.classList.contains('detail')) {
            rowEl.nextElementSibling.remove(); return;
        }
        const d = await (await fetch(API + "/requests/" + id)).json();
        if (!d.ok) return;
        const r = d.request;
        const ann = annotate(r.url);
        const uc = userControlled(r.request_body);
        const tr = document.createElement('tr');
        tr.className = 'detail';
        tr.innerHTML = `<td colspan="5" class="px-4 py-3 bg-slate-950/60">
            \${ann ? `<div class="mb-2 text-xs rounded bg-amber-500/10 ring-1 ring-amber-500/30 text-amber-200 px-2 py-1">&#128073; \${esc(ann)}</div>`:''}
            \${uc.length ? `<div class="mb-2 text-xs">User-controlled: \${uc.map(f=>`<span class="inline-block bg-red-500/20 text-red-300 rounded px-1.5 py-0.5 mr-1 font-mono">\${esc(f)}</span>`).join('')}</div>`:''}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 text-xs">
                \${block('Headers', fmtHeaders(r.headers), uc)}
                \${block('Request body', fmtBody(r.request_body), uc)}
                \${block('Response body', fmtBody(r.response_body), uc)}
            </div></td>`;
        rowEl.after(tr);
    }

    function userControlled(body){
        try { const j = JSON.parse(body); const o = j.user_controlled || (j.body? Object.keys(j.body):[]); return Array.isArray(o)?o:Object.keys(o||{}); }
        catch(e){ return []; }
    }

    // --- formatting: turn stored JSON into readable, line-structured text ---
    // Headers stored as a JSON object -> "Key: Value" one per line.
    function fmtHeaders(raw){
        // NOTE: this file is a PHP heredoc, so a literal \\n here would become a
        // real newline inside a JS string literal (= syntax error). Build the
        // newline at runtime with String.fromCharCode(10) to stay safe.
        const NL = String.fromCharCode(10);
        try { const o = JSON.parse(raw); if (o && typeof o==='object' && !Array.isArray(o))
            return Object.entries(o).map(([k,v])=>k+': '+v).join(NL); }
        catch(e){}
        return raw || '';
    }
    // Bodies: pretty-print JSON (2-space indent, newlines). The request body is
    // wrapped by RequestLogger as {user_controlled, query, body} — unwrap to the
    // meaningful parts so it reads cleanly.
    function fmtBody(raw){
        if (raw == null || raw === '') return '';
        try {
            let o = JSON.parse(raw);
            if (o && typeof o==='object' && ('body' in o || 'query' in o || 'user_controlled' in o)) {
                const out = {};
                if (o.user_controlled && o.user_controlled.length) out.user_controlled = o.user_controlled;
                if (o.query && Object.keys(o.query).length) out.query = o.query;
                if (o.body && Object.keys(o.body).length) out.body = o.body;
                o = Object.keys(out).length ? out : o;
            }
            return JSON.stringify(o, null, 2);
        } catch(e){ return raw; }
    }
    function block(title, content, uc){
        const id = 'c'+Math.random().toString(36).slice(2);
        let html = esc(content || '');
        // Highlight user-controlled JSON keys in red so they stand out. Field
        // names are simple identifiers, so a literal split/join is enough (no
        // regex needed — avoids escaping headaches inside this PHP heredoc).
        (uc || []).forEach(f => {
            const needle = '&quot;' + esc(f) + '&quot;:';
            const repl = '&quot;<span class="text-red-400 font-semibold">' + esc(f) + '</span>&quot;:';
            html = html.split(needle).join(repl);
        });
        return `<div><div class="flex justify-between text-slate-400 mb-1"><span>\${title}</span>`+
            `<button onclick="copyEl('\${id}')" class="text-indigo-400 hover:text-indigo-300">copy</button></div>`+
            `<pre id="\${id}" class="bg-slate-900 ring-1 ring-slate-800 rounded p-2 overflow-auto max-h-64 text-slate-300 whitespace-pre leading-relaxed" style="font-family:ui-monospace,monospace">\${html}</pre></div>`;
    }
    function copyEl(id){ navigator.clipboard.writeText(document.getElementById(id).textContent); }

    function toggleAuto(){
        if (document.getElementById('autoRefresh').checked) { timer = setInterval(reload, 2000); }
        else { clearInterval(timer); timer = null; }
    }
    async function clearLog(){
        if (!confirm('Clear the request log?')) return;
        await fetch(API + "/requests/clear", {method:'POST'});
        reload();
    }
    reload();
    </script>
HTML;

render_layout('Request Inspector', $content, ['active' => 'inspector']);
