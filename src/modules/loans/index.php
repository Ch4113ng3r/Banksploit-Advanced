<?php

declare(strict_types=1);

/**
 * Loans page (HTML): application form, interactive EMI calculator, and a
 * status tracker for existing loans.
 *
 * The application form posts the interest_rate as a HIDDEN field at vuln levels
 * (loans.l22) — that's the tampering surface: edit it in devtools and the
 * server stores your value. At the secure level the field is removed and the
 * server uses the product rate.
 */

require_once __DIR__ . '/loans_vuln.php';
require_once __DIR__ . '/loans_secure.php';

auth()->requireAuth();

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();
$uid    = (int) $me['id'];
$l22Level = $engine->getVulnLevel('loans', 'l22');
$l22Vuln  = $l22Level < 3;   // dispatch: vuln handler at 0-2, secure at 3

$notice = null;
$error  = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    auth()->requireSession(); // JWT is read-only
    if (!CSRF::validateToken(input('csrf_token'))) {
        $error = 'Invalid or missing CSRF token.';
    } else {
        $in = request_input();
        $res = $l22Vuln
            ? loans_apply_vuln($pdo, $engine, $uid, $in)
            : loans_apply_secure($pdo, $engine, $uid, $in);
        if ($res['ok']) {
            $notice = 'Loan application submitted. EMI: ₹' . number_format((float) $res['emi'], 2);
        } else {
            $error = $res['error'];
        }
    }
}

$loans = loans_list($pdo, $uid);
$url    = htmlspecialchars(APP_URL);
$csrf   = CSRF::getHiddenField();

$alert = '';
if ($error !== null) {
    $alert = '<div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 px-3 py-2 text-sm">' . htmlspecialchars($error) . '</div>';
} elseif ($notice !== null) {
    $alert = '<div class="mb-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 text-emerald-300 px-3 py-2 text-sm">' . htmlspecialchars($notice) . '</div>';
}

// Status tracker rows.
$stages = ['applied', 'approved', 'disbursed'];
$loanRows = '';
foreach ($loans as $l) {
    $type = htmlspecialchars(ucfirst((string) $l['loan_type']));
    $amt  = htmlspecialchars(number_format((float) $l['amount'], 2));
    $rate = htmlspecialchars((string) $l['interest_rate']);
    $ten  = (int) $l['tenure_months'];
    $status = (string) $l['status'];
    $emi = htmlspecialchars(number_format(loans_calc_emi((float) $l['amount'], (float) $l['interest_rate'], $ten), 2));

    // progress
    $track = '';
    $rejected = $status === 'rejected';
    foreach ($stages as $i => $st) {
        $reached = !$rejected && array_search($status, $stages, true) >= $i;
        $cls = $reached ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-500';
        $track .= '<span class="px-2 py-0.5 rounded text-xs ' . $cls . '">' . ucfirst($st) . '</span>';
        if ($i < count($stages) - 1) {
            $track .= '<span class="text-slate-600">→</span>';
        }
    }
    if ($rejected) {
        $track = '<span class="px-2 py-0.5 rounded text-xs bg-red-500/20 text-red-300">Rejected</span>';
    }

    $loanRows .= <<<HTML
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4 mb-3">
            <div class="flex justify-between items-start">
                <div>
                    <div class="text-white font-semibold">{$type} loan · ₹{$amt}</div>
                    <div class="text-xs text-slate-500 mt-0.5">{$rate}% · {$ten} months · EMI ₹{$emi}</div>
                </div>
                <div class="flex items-center gap-1">{$track}</div>
            </div>
        </div>
HTML;
}
if ($loanRows === '') {
    $loanRows = '<div class="text-slate-500 text-sm">No loans yet.</div>';
}

// loans.l22 — GRADUATED progressive-obscurity ladder. The server trusts the
// client interest_rate at every vuln level (see loans_apply_vuln); only how the
// parameter is EXPOSED on the page hardens as the level rises:
//   L0  visible but readonly  — naive "readonly stops tampering" (bypass in devtools/request)
//   L1  hidden field          — not shown, still submitted (find via view-source/intercept)
//   L2  not sent at all       — only an HTML comment hints the param; add it manually
//   L3  removed               — server uses the product rate, client ignored
$rateField = match (true) {
    $l22Level === 0 =>
        '<label class="block text-xs text-yellow-500 mb-1 mt-3">interest_rate (locked — readonly)</label>'
        . '<input name="interest_rate" value="11.5" readonly'
        . ' class="w-full rounded-lg bg-slate-900 ring-1 ring-slate-700 px-3 py-2 text-slate-400 font-mono cursor-not-allowed"'
        . ' title="Readonly in the UI — but the server still trusts whatever is submitted.">',
    $l22Level === 1 =>
        '<input type="hidden" name="interest_rate" value="11.5">',
    $l22Level === 2 =>
        // No field is submitted by the form; the parameter only survives as a
        // source-code hint. Add interest_rate to the request and the server honours it.
        "<!-- loans.l22: this form no longer sends interest_rate, but the server still"
        . " accepts it. Try adding the parameter to your POST: interest_rate=1.5 -->",
    default => '',   // L3 secure — no client rate field at all
};

// Loan-document panel (g7 upload / g10 XXE / g6 SSRF) targets the documents API,
// which needs an existing loan id — build a selector from the user's loans.
$docLoanOpts = '';
foreach ($loans as $l) {
    $lid = (int) $l['id'];
    $lt  = htmlspecialchars(ucfirst((string) $l['loan_type']));
    $la  = htmlspecialchars(number_format((float) $l['amount'], 0));
    $docLoanOpts .= '<option value="' . $lid . '">#' . $lid . ' · ' . $lt . ' · ₹' . $la . '</option>';
}
$hasLoans = $loans !== [];

if ($hasLoans) {
    $docPanel = <<<HTML
        <div class="mt-6 rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5">
            <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-1">Loan documents (KYC)</h3>
            <p class="text-xs text-slate-500 mb-4">Attach supporting documents to a loan application — upload a file, import an XML statement, or fetch one from a URL.</p>
            <label class="block text-xs text-slate-400 mb-1">Attach to loan</label>
            <select id="docloan" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 mb-4">{$docLoanOpts}</select>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-lg bg-slate-950/60 ring-1 ring-slate-800 p-3">
                    <label class="block text-xs text-slate-400 mb-2">Upload document</label>
                    <input type="file" id="docfile" class="w-full text-xs text-slate-300 file:mr-2 file:rounded file:border-0 file:bg-indigo-500 file:px-2 file:py-1 file:text-slate-950 file:font-semibold">
                    <button type="button" onclick="docUpload()" class="mt-3 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2 text-sm transition">Upload</button>
                </div>
                <div class="rounded-lg bg-slate-950/60 ring-1 ring-slate-800 p-3">
                    <label class="block text-xs text-slate-400 mb-2">Import XML statement</label>
                    <textarea id="docxml" rows="3" placeholder="&lt;loan&gt;...&lt;/loan&gt;" class="w-full rounded bg-slate-950 ring-1 ring-slate-700 px-2 py-1.5 text-slate-100 font-mono text-xs"></textarea>
                    <button type="button" onclick="docXml()" class="mt-3 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2 text-sm transition">Import XML</button>
                </div>
                <div class="rounded-lg bg-slate-950/60 ring-1 ring-slate-800 p-3">
                    <label class="block text-xs text-slate-400 mb-2">Fetch document by URL</label>
                    <input id="docurl" placeholder="https://… or file://…" class="w-full rounded bg-slate-950 ring-1 ring-slate-700 px-2 py-1.5 text-slate-100 font-mono text-xs">
                    <button type="button" onclick="docFetch()" class="mt-3 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2 text-sm transition">Fetch</button>
                </div>
            </div>
            <pre id="docresult" class="hidden mt-4 rounded-lg bg-slate-950 ring-1 ring-slate-800 p-3 text-xs text-slate-300 overflow-auto max-h-72 whitespace-pre-wrap"></pre>
        </div>
HTML;
} else {
    $docPanel = <<<HTML
        <div class="mt-6 rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5">
            <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-1">Loan documents (KYC)</h3>
            <p class="text-xs text-slate-500">Apply for a loan above first — documents are attached to an existing application.</p>
        </div>
HTML;
}

$content = <<<HTML
    <h2 class="text-2xl font-bold text-white mb-6">Loans</h2>
    {$alert}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5">
            <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-4">Apply for a loan</h3>
            <form method="post" action="{$url}/loans">
                {$csrf}
                <label class="block text-xs text-slate-400 mb-1">Loan type</label>
                <select name="loan_type" id="ltype" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 mb-3">
                    <option value="personal">Personal</option><option value="home">Home</option>
                </select>
                <label class="block text-xs text-slate-400 mb-1">Amount (₹) <span id="amtv" class="text-slate-500"></span></label>
                <input name="amount" id="amt" type="range" min="50000" max="2000000" step="10000" value="500000" class="w-full mb-3" oninput="recalc()">
                <label class="block text-xs text-slate-400 mb-1">Tenure (months) <span id="tenv" class="text-slate-500"></span></label>
                <input name="tenure_months" id="ten" type="range" min="6" max="240" step="6" value="48" class="w-full" oninput="recalc()">
                {$rateField}
                <div class="rounded-lg bg-slate-950 ring-1 ring-slate-800 p-3 mt-4 text-center">
                    <div class="text-xs text-slate-500">Estimated EMI</div>
                    <div id="emi" class="text-2xl font-bold text-white">₹0</div>
                </div>
                <button class="mt-4 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Apply</button>
            </form>
        </div>
        <div>
            <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-4">Your loans</h3>
            {$loanRows}
        </div>
    </div>
    {$docPanel}

    <script>
    const EMI_API = "{$url}/api/v1/loans/emi-calculator";
    const LOANS_API = "{$url}/api/v1/loans";
    function docShow(data) {
        const el = document.getElementById('docresult');
        el.classList.remove('hidden');
        let txt = JSON.stringify(data, null, 2);
        // Surface a web-served upload path as a clickable link (g7 webshell).
        const p = data && data.upload && data.upload.path;
        el.textContent = txt;
        if (p) {
            const a = document.createElement('a');
            a.href = "{$url}/" + p; a.target = "_blank";
            a.className = "block mt-2 text-indigo-400 underline break-all";
            a.textContent = "Open uploaded file → {$url}/" + p;
            el.appendChild(a);
        }
    }
    function docLoanId() { return document.getElementById('docloan').value; }
    async function docUpload() {
        const f = document.getElementById('docfile').files[0];
        if (!f) { return docShow({error: 'Choose a file first.'}); }
        const fd = new FormData(); fd.append('document', f);
        const r = await fetch(LOANS_API + '/' + docLoanId() + '/documents', {method: 'POST', body: fd});
        docShow(await r.json());
    }
    async function docXml() {
        const fd = new FormData(); fd.append('xml', document.getElementById('docxml').value);
        const r = await fetch(LOANS_API + '/' + docLoanId() + '/documents', {method: 'POST', body: fd});
        docShow(await r.json());
    }
    async function docFetch() {
        const fd = new FormData(); fd.append('document_url', document.getElementById('docurl').value);
        const r = await fetch(LOANS_API + '/' + docLoanId() + '/documents', {method: 'POST', body: fd});
        docShow(await r.json());
    }
    function rate() { const r = document.querySelector('[name=interest_rate]'); return r ? Number(r.value) : (document.getElementById('ltype').value === 'home' ? 8.75 : 11.5); }
    async function recalc() {
        const amount = Number(document.getElementById('amt').value);
        const tenure = Number(document.getElementById('ten').value);
        document.getElementById('amtv').textContent = '₹' + amount.toLocaleString('en-IN');
        document.getElementById('tenv').textContent = tenure + ' mo';
        const u = new URL(EMI_API);
        u.search = new URLSearchParams({amount, interest_rate: rate(), tenure_months: tenure});
        const d = await (await fetch(u)).json();
        document.getElementById('emi').textContent = '₹' + Number(d.emi).toLocaleString('en-IN');
    }
    recalc();
    </script>
HTML;

$content .= lab_hint_banner(['loans.l22', 'loans.g7', 'loans.g10', 'loans.g6']);

render_layout('Loans', $content, ['active' => 'loans']);
