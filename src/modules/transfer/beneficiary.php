<?php

declare(strict_types=1);

/**
 * Beneficiary management — list / add / edit / delete, plus the JSON API used
 * to read a single beneficiary (the IDOR surface).
 *
 * ============================================================================
 *  VULNERABILITIES
 * ----------------------------------------------------------------------------
 *  beneficiary.l8 Cooling-period bypass (CWE-840):
 *      A new payee normally can't receive funds for 24h. At vuln levels the
 *      `cooling_period_until` timestamp is taken from the CLIENT's POST data
 *      and trusted, so an attacker sets it to a PAST date and transfers
 *      immediately. At the secure level the server computes it as NOW()+24h
 *      and ignores any client value.
 *
 *  beneficiary.l3 IDOR (CWE-639):
 *      GET /api/v1/beneficiaries/{id} returns any beneficiary by id with no
 *      ownership check at vuln levels — enumerate to read other users' payee
 *      PII. The secure path scopes the lookup to the current user.
 *
 *  beneficiary.g3 Stored XSS (CWE-79):
 *      The beneficiary `name` is stored raw and rendered unescaped (via
 *      vuln_out) in the list at vuln levels; htmlspecialchars() at level 3.
 *
 * Routes (wired in index.php):
 *   GET/POST /beneficiaries                 list + add/edit/delete (HTML)
 *   GET      /api/v1/beneficiaries          list (JSON)
 *   GET      /api/v1/beneficiaries/{id}     detail (JSON, IDOR surface)
 *   POST     /api/v1/beneficiaries          add (JSON)
 *   DELETE   /api/v1/beneficiaries/{id}     delete (JSON)
 */

require_once __DIR__ . '/_shared.php';

auth()->requireAuth();

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();
$uid    = (int) $me['id'];

/**
 * Resolve cooling_period_until.
 * beneficiary.l8: vuln levels trust the client value; secure computes server-side.
 */
function ben_cooling_until(TrainingEngine $engine, array $in): string
{
    if ($engine->isVulnerable('beneficiary', 'l8') && !empty($in['cooling_period_until'])) {
        // @vuln-start: cooling-period bypass — client sets the timestamp (beneficiary.l8)
        return (string) $in['cooling_period_until'];
        // @vuln-end
    }
    // @fix-start: cooling-period computed server-side as now()+24h (beneficiary.l8)
    return date('Y-m-d H:i:s', time() + 86400);
    // @fix-end
}

/**
 * Insert a beneficiary for a user.
 * beneficiary.g3: name stored raw (sanitisation happens at render via vuln_out).
 */
function ben_add(PDO $pdo, TrainingEngine $engine, int $uid, array $in): void
{
    $pdo->prepare(
        'INSERT INTO beneficiaries (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until)
         VALUES (:u, :n, :a, :i, :b, 1, :c)'
    )->execute([
        ':u' => $uid,
        ':n' => (string) ($in['name'] ?? ''),
        ':a' => (string) ($in['account_number'] ?? ''),
        ':i' => (string) ($in['ifsc_code'] ?? ''),
        ':b' => (string) ($in['bank_name'] ?? ''),
        ':c' => ben_cooling_until($engine, $in),
    ]);
}

/**
 * Read one beneficiary.
 * beneficiary.l3 IDOR: vuln levels skip the ownership check.
 *
 * @return array<string,mixed>|null
 */
function ben_get(PDO $pdo, TrainingEngine $engine, int $uid, int $id): ?array
{
    if ($engine->isVulnerable('beneficiary', 'l3')) {
        // @vuln-start: beneficiary IDOR — any id, no ownership check (beneficiary.l3)
        $s = $pdo->prepare('SELECT * FROM beneficiaries WHERE id = :id');
        $s->execute([':id' => $id]);
        // @vuln-end
    } else {
        // @fix-start: beneficiary read scoped to the owner (beneficiary.l3)
        $s = $pdo->prepare('SELECT * FROM beneficiaries WHERE id = :id AND user_id = :u');
        $s->execute([':id' => $id, ':u' => $uid]);
        // @fix-end
    }
    $row = $s->fetch();
    return $row ?: null;
}

// ============================ JSON API ====================================
$path   = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isApi  = str_starts_with($path, '/api/');

if ($isApi) {
    $in = request_input();

    // GET /api/v1/beneficiaries/{id}
    if ($method === 'GET' && route_param('id') !== null) {
        $b = ben_get($pdo, $engine, $uid, (int) route_param('id'));
        if ($b === null) {
            json_response(['ok' => false, 'error' => 'Beneficiary not found or access denied.'], 404);
        }
        json_response(['ok' => true, 'beneficiary' => $b]);
    }

    // GET /api/v1/beneficiaries
    if ($method === 'GET') {
        json_response(['ok' => true, 'beneficiaries' => xfer_user_beneficiaries($pdo, $uid)]);
    }

    // POST /api/v1/beneficiaries  (add)
    if ($method === 'POST') {
        auth()->requireSession(); // JWT is read-only
        if (!CSRF::validateToken($in['csrf_token'] ?? null)) {
            json_response(['ok' => false, 'error' => 'CSRF token required.'], 403);
        }
        ben_add($pdo, $engine, $uid, $in);
        json_response(['ok' => true, 'id' => (int) $pdo->lastInsertId()], 201);
    }

    // DELETE /api/v1/beneficiaries/{id}
    if ($method === 'DELETE' && route_param('id') !== null) {
        auth()->requireSession(); // JWT is read-only
        // Ownership-scoped delete regardless of level (deleting others' rows is
        // out of scope for the IDOR demo, which is about *reading*).
        $pdo->prepare('DELETE FROM beneficiaries WHERE id = :id AND user_id = :u')
            ->execute([':id' => (int) route_param('id'), ':u' => $uid]);
        json_response(['ok' => true]);
    }

    json_response(['ok' => false, 'error' => 'Unsupported.'], 405);
}

// ============================ HTML page ===================================
$notice = null;
$error  = null;

if ($method === 'POST') {
    auth()->requireSession(); // JWT is read-only
    if (!CSRF::validateToken(input('csrf_token'))) {
        $error = 'Invalid or missing CSRF token.';
    } else {
        $act = (string) input('action', 'add');
        if ($act === 'delete') {
            $pdo->prepare('DELETE FROM beneficiaries WHERE id = :id AND user_id = :u')
                ->execute([':id' => (int) input('id', 0), ':u' => $uid]);
            $notice = 'Beneficiary removed.';
        } else {
            ben_add($pdo, $engine, $uid, request_input());
            $notice = 'Beneficiary added.';
        }
    }
}

$beneficiaries = xfer_user_beneficiaries($pdo, $uid);

$url  = htmlspecialchars(APP_URL);
$csrf = CSRF::getHiddenField();

$alert = '';
if ($error !== null) {
    $alert = '<div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 px-3 py-2 text-sm">' . htmlspecialchars($error) . '</div>';
} elseif ($notice !== null) {
    $alert = '<div class="mb-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 text-emerald-300 px-3 py-2 text-sm">' . htmlspecialchars($notice) . '</div>';
}

// Beneficiary list. The name is the stored-XSS sink (beneficiary.g3): at vuln
// levels it is written to the page raw; at level 3 it is HTML-encoded on output.
$rows = '';
foreach ($beneficiaries as $b) {
    if ($engine->isVulnerable('beneficiary', 'g3')) {
        // @vuln-start: stored XSS — name written to the page unescaped, so stored HTML/JS runs (beneficiary.g3)
        $name = (string) $b['name'];
        // @vuln-end
    } else {
        // @fix-start: stored XSS fixed — name HTML-encoded on output, rendered as inert text (beneficiary.g3)
        $name = htmlspecialchars((string) $b['name'], ENT_QUOTES);
        // @fix-end
    }
    $acct = htmlspecialchars((string) $b['account_number']);
    $ifsc = htmlspecialchars((string) $b['ifsc_code']);
    $bank = htmlspecialchars((string) $b['bank_name']);
    $cool = $b['cooling_period_until'];
    $coolBadge = ($cool !== null && strtotime((string) $cool) > time())
        ? '<span class="text-xs px-2 py-0.5 rounded bg-amber-400/20 text-amber-300">cooling until ' . htmlspecialchars((string) $cool) . '</span>'
        : '<span class="text-xs px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300">active</span>';
    $id = (int) $b['id'];
    $rows .= <<<HTML
        <tr class="border-t border-slate-800">
            <td class="py-2 text-slate-200 text-sm">{$name}</td>
            <td class="py-2 text-slate-400 text-sm font-mono">{$acct}</td>
            <td class="py-2 text-slate-400 text-xs">{$ifsc} · {$bank}</td>
            <td class="py-2">{$coolBadge}</td>
            <td class="py-2 text-right">
                <form method="post" action="{$url}/beneficiaries" onsubmit="return confirm('Remove beneficiary?')">
                    {$csrf}<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="{$id}">
                    <button class="text-xs text-red-300 hover:text-red-200">Delete</button>
                </form>
            </td>
        </tr>
HTML;
}
if ($rows === '') {
    $rows = '<tr><td colspan="5" class="py-3 text-slate-500 text-sm">No beneficiaries yet.</td></tr>';
}

// At vuln levels expose the cooling_period_until field so trainees can tamper.
$coolField = $engine->isVulnerable('beneficiary', 'l8')
    ? '<label class="block text-xs text-yellow-500 mb-1 mt-3">cooling_period_until (should not be client-set)</label>'
      . '<input name="cooling_period_until" placeholder="2020-01-01 00:00:00" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 font-mono">'
    : '';

$content = <<<HTML
    <h2 class="text-2xl font-bold text-white mb-6">Beneficiaries</h2>
    {$alert}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4">
            <table class="w-full">
                <thead><tr class="text-slate-500 text-xs uppercase text-left">
                    <th class="pb-2">Name</th><th class="pb-2">Account</th><th class="pb-2">Bank</th><th class="pb-2">Status</th><th></th>
                </tr></thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4">
            <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-3">Add beneficiary</h3>
            <form method="post" action="{$url}/beneficiaries">
                {$csrf}
                <input type="hidden" name="action" value="add">
                <input name="name" placeholder="Name" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 mb-2">
                <input name="account_number" placeholder="Account number" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 mb-2">
                <input name="ifsc_code" placeholder="IFSC" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 mb-2">
                <input name="bank_name" placeholder="Bank" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100">
                {$coolField}
                <button class="mt-4 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Add</button>
            </form>
        </div>
    </div>
HTML;

$content .= lab_hint_banner(['beneficiary.l8', 'beneficiary.l3', 'beneficiary.g3']);

render_layout('Beneficiaries', $content, ['active' => 'beneficiaries']);
