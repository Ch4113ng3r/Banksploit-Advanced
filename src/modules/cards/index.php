<?php

declare(strict_types=1);

/**
 * Cards page (HTML).
 *
 * Lists the user's cards with a MASKED number in the UI (the frontend always
 * masks). The point of cards.l24 is that the *API* (GET /api/v1/cards/{id})
 * leaks the full PAN+CVV at vuln levels even though this page hides it — open
 * the network tab / call the API to see the plaintext.
 *
 * Block/unblock, PIN change and limit forms post to the JSON API endpoints.
 */

require_once __DIR__ . '/cards_vuln.php';
require_once __DIR__ . '/cards_secure.php';

auth()->requireAuth();

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();
$uid    = (int) $me['id'];
$secure = !$engine->isVulnerable('cards', 'l24');

// Always list with the secure (masked) shape for display.
$cards = cards_list_secure($pdo, $uid);

$url  = htmlspecialchars(APP_URL);

$cardCards = '';
foreach ($cards as $c) {
    $id    = (int) $c['id'];
    $type  = htmlspecialchars(ucfirst((string) $c['card_type']));
    $mask  = htmlspecialchars((string) $c['card_number']);
    $exp   = htmlspecialchars((string) $c['expiry_date']);
    $limit = htmlspecialchars(number_format((float) $c['spending_limit'], 2));
    $blocked = (int) $c['is_blocked'] === 1;
    $statusBadge = $blocked
        ? '<span class="text-xs px-2 py-0.5 rounded bg-red-500/20 text-red-300">blocked</span>'
        : '<span class="text-xs px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300">active</span>';
    $blockLabel = $blocked ? 'Unblock' : 'Block';
    $blockVal   = $blocked ? '0' : '1';
    $gradient = $c['card_type'] === 'credit'
        ? 'from-purple-600 to-indigo-500' : 'from-sky-600 to-cyan-500';

    $cardCards .= <<<HTML
        <div class="rounded-2xl bg-gradient-to-br {$gradient} p-5 text-white shadow-lg">
            <div class="flex justify-between items-start">
                <span class="text-sm uppercase tracking-wide opacity-80">{$type} card</span>
                {$statusBadge}
            </div>
            <div class="font-mono text-lg tracking-widest mt-6">{$mask}</div>
            <div class="flex justify-between text-xs mt-4 opacity-90">
                <span>Exp {$exp}</span>
                <span>Limit ₹{$limit}</span>
            </div>
        </div>
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4 mb-6 text-sm">
            <div class="flex flex-wrap gap-2" data-card="{$id}">
                <button onclick="cardAction({$id},'block',{blocked:{$blockVal}})" class="rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-1.5 text-slate-200">{$blockLabel}</button>
                <button onclick="cardPin({$id})" class="rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-1.5 text-slate-200">Change PIN</button>
                <button onclick="cardLimit({$id})" class="rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-1.5 text-slate-200">Set limit</button>
                <button onclick="cardReveal({$id})" class="rounded-lg bg-amber-500/20 hover:bg-amber-500/30 px-3 py-1.5 text-amber-200">Reveal (API)</button>
            </div>
            <pre id="card-out-{$id}" class="mt-3 text-xs text-slate-400 whitespace-pre-wrap break-all"></pre>
        </div>
HTML;
}
if ($cardCards === '') {
    $cardCards = '<div class="text-slate-500 text-sm">No cards issued.</div>';
}

// JS talks to the JSON API. CSRF token included for secure level.
$csrfToken = CSRF::generateToken();

$content = <<<HTML
    <h2 class="text-2xl font-bold text-white mb-6">My Cards</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6">{$cardCards}</div>

    <script>
    const CSRF = "{$csrfToken}";
    const API = "{$url}/api/v1/cards";
    async function api(path, method, body) {
        const r = await fetch(API + path, {
            method, headers: {'Content-Type':'application/json'},
            body: body ? JSON.stringify({...body, csrf_token: CSRF}) : undefined
        });
        return r.json();
    }
    function show(id, data) { document.getElementById('card-out-'+id).textContent = JSON.stringify(data, null, 2); }
    async function cardAction(id, action, body) { show(id, await api('/'+id+'/'+action, 'PUT', body)); setTimeout(()=>location.reload(), 600); }
    async function cardPin(id) { const p = prompt('New 4-digit PIN'); if(!p) return; const o = prompt('Old PIN (ignored at level 0)')||''; show(id, await api('/'+id+'/pin','PUT',{new_pin:p, old_pin:o})); }
    async function cardLimit(id) { const l = prompt('New spending limit'); if(!l) return; show(id, await api('/'+id+'/limit','PUT',{spending_limit:Number(l)})); }
    async function cardReveal(id) { const r = await fetch(API+'/'+id); show(id, await r.json()); }
    </script>
HTML;

$content .= lab_hint_banner(['cards.l24']);

render_layout('Cards', $content, ['active' => 'cards']);
