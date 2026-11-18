<?php

declare(strict_types=1);

$engine = engine();

// ---- Planning groups: instructor-defined sets of vulns, each with a pre-decided
// "target" level to hand over after demoing L0. Ordered {id, name, members:[{key,
// level}]}. Loader (with legacy normalisation) is shared via vuln_groups_load();
// the same groups are also surfaced read-only on the CTF Vulnerabilities page. ----
$loadGroups = static fn (): array => vuln_groups_load();
$saveGroups = static function (array $groups): bool {
    $json = json_encode(['groups' => array_values($groups)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return @file_put_contents(vuln_groups_path(), $json, LOCK_EX) !== false;
};

// Handle POST — set levels
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if (!csrf_verify($input['csrf_token'] ?? null)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF token invalid.']);
        exit;
    }

    $action = $input['action'] ?? '';

    if ($action === 'set-level') {
        $module = (string) ($input['module'] ?? '');
        $vuln   = (string) ($input['vuln'] ?? '');
        $level  = max(0, min(3, (int) ($input['level'] ?? 0)));
        $engine->setVulnLevel($module, $vuln, $level);
        echo json_encode(['ok' => true, 'module' => $module, 'vuln' => $vuln, 'level' => $level]);
        exit;
    }

    if ($action === 'preset') {
        $preset = (string) ($input['preset'] ?? '');
        $allVulns = $engine->getAllVulns();
        $level = match ($preset) {
            'beginner' => 0,
            'secure'   => 3,
            default    => null,
        };
        if ($level !== null) {
            foreach ($allVulns['modules'] as $mod => $vulns) {
                foreach ($vulns as $vid => $v) {
                    $engine->setVulnLevel($mod, strtolower($vid), $level);
                }
            }
        }
        echo json_encode(['ok' => true, 'preset' => $preset]);
        exit;
    }

    // Create or update a planning group: {id?, name, members:[{key,level}]}.
    if ($action === 'group-save') {
        $gid  = trim((string) ($input['id'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            echo json_encode(['ok' => false, 'error' => 'Group name is required.']);
            exit;
        }
        // Sanitise members: preserve order; each {key:string, level:int 0-3}, keys unique.
        $members = [];
        $seen    = [];
        foreach ((array) ($input['members'] ?? []) as $m) {
            $k = is_array($m) ? (string) ($m['key'] ?? '') : '';
            if ($k !== '' && !isset($seen[$k])) {
                $seen[$k]  = true;
                $members[] = ['key' => $k, 'level' => max(0, min(3, (int) ($m['level'] ?? 0)))];
            }
        }
        $groups = $loadGroups();
        if ($gid !== '') {
            $found = false;
            foreach ($groups as &$gr) {
                if (($gr['id'] ?? '') === $gid) { $gr['name'] = $name; $gr['members'] = $members; $found = true; break; }
            }
            unset($gr);
            if (!$found) { echo json_encode(['ok' => false, 'error' => 'Group not found.']); exit; }
        } else {
            $gid      = 'g' . bin2hex(random_bytes(4));
            $groups[] = ['id' => $gid, 'name' => $name, 'members' => $members];
        }
        echo json_encode(['ok' => $saveGroups($groups), 'groups' => array_values($groups), 'id' => $gid]);
        exit;
    }

    if ($action === 'group-delete') {
        $gid    = trim((string) ($input['id'] ?? ''));
        $groups = array_values(array_filter($loadGroups(), static fn($g) => ($g['id'] ?? '') !== $gid));
        echo json_encode(['ok' => $saveGroups($groups), 'groups' => $groups]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

// GET — render page
$allVulns = $engine->getAllVulns();
$csrf = csrf_token();

$rows = '';
$allList = []; // for the group-editor checklist (JS)
foreach ($allVulns['modules'] as $mod => $vulns) {
    foreach ($vulns as $vid => $v) {
        $level = (int) ($v['level'] ?? 0);
        $key   = $mod . '.' . strtolower($vid);
        $title = htmlspecialchars((string) ($v['title'] ?? $vid));
        $id    = htmlspecialchars((string) ($v['id'] ?? strtoupper($vid)));
        $owasp = htmlspecialchars((string) ($v['owasp_category'] ?? ''));
        $cwe   = htmlspecialchars((string) ($v['cwe_id'] ?? ''));
        $modE  = htmlspecialchars($mod);
        $vidE  = htmlspecialchars(strtolower($vid));
        $allList[] = [
            'key'    => $key,
            'module' => $mod,
            'id'     => (string) ($v['id'] ?? strtoupper((string) $vid)),
            'title'  => (string) ($v['title'] ?? $vid),
        ];
        $opts = '';
        foreach ([0, 1, 2, 3] as $lv) {
            $sel = $lv === $level ? ' selected' : '';
            $label = ['Vulnerable', 'Weak', 'Partial', 'Secure'][$lv];
            $opts .= "<option value=\"{$lv}\"{$sel}>{$lv} · {$label}</option>";
        }
        $badge = $level >= 3
            ? 'bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-0'
            : 'bg-red-50 dark:bg-red-500/20 text-red-700 dark:text-red-300 ring-1 ring-red-200 dark:ring-0';
        $rows .= <<<HTML
            <tr class="border-t border-gray-100 dark:border-slate-800" data-key="{$key}" data-mod="{$modE}" data-vuln="{$vidE}">
                <td class="py-2"><span class="font-mono text-xs px-1.5 py-0.5 rounded {$badge}">{$id}</span></td>
                <td class="py-2 text-gray-500 dark:text-slate-400 text-xs">{$modE}</td>
                <td class="py-2 text-gray-800 dark:text-slate-200 text-sm">{$title}<div class="text-xs text-gray-400 dark:text-slate-500">{$owasp} · {$cwe}</div></td>
                <td class="py-2 text-right whitespace-nowrap" data-target></td>
                <td class="py-2 text-right">
                    <select onchange="setLevel('{$modE}','{$vidE}',this.value)"
                            class="rounded-lg bg-gray-50 dark:bg-slate-950 ring-1 ring-gray-300 dark:ring-slate-700 px-2 py-1 text-gray-900 dark:text-slate-100 text-sm focus:ring-amber-500 focus:outline-none">{$opts}</select>
                </td>
            </tr>
HTML;
    }
}

$groupsJson  = json_encode($loadGroups(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
$allListJson = json_encode($allList, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

$content = <<<HTML
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">Set difficulty levels for each vulnerability. Changes take effect immediately on the CTF app.</p>
        </div>
        <div class="flex gap-2">
            <button onclick="applyPreset('beginner')" class="rounded-lg bg-red-50 dark:bg-red-600/30 hover:bg-red-100 dark:hover:bg-red-600/50 ring-1 ring-red-200 dark:ring-0 px-3 py-2 text-xs text-red-700 dark:text-red-200 font-medium">All Vulnerable</button>
            <button onclick="applyPreset('secure')" class="rounded-lg bg-emerald-50 dark:bg-emerald-600/30 hover:bg-emerald-100 dark:hover:bg-emerald-600/50 ring-1 ring-emerald-200 dark:ring-0 px-3 py-2 text-xs text-emerald-700 dark:text-emerald-200 font-medium">All Secure</button>
        </div>
    </div>

    <div class="rounded-xl bg-white dark:bg-slate-900/60 ring-1 ring-gray-200 dark:ring-slate-800 px-4 py-3 mb-5">
        <div class="flex items-center justify-between mb-1">
            <div class="text-sm font-medium text-gray-800 dark:text-slate-200">Planning groups</div>
            <button onclick="newGroup()" class="rounded-lg bg-amber-600 hover:bg-amber-500 text-white px-3 py-1.5 text-xs font-semibold">+ New group</button>
        </div>
        <div class="text-xs text-gray-500 dark:text-slate-500 mb-3">Save a set of vulnerabilities with a <b>pre-decided target level</b> each, in the order you want. Select a group to show only those vulns, in that order, with a <b>★ Set</b> button per row — demo L0, then click the button to jump its level straight to the target. Groups change nothing until you click.</div>
        <div id="groupsBar" class="flex flex-wrap gap-2"></div>
    </div>

    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4">
        <table class="w-full">
            <thead><tr class="text-gray-500 dark:text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">ID</th><th class="pb-2">Module</th><th class="pb-2">Vulnerability</th><th class="pb-2 text-right">Target</th><th class="pb-2 text-right">Level</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>

    <div id="groupModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-slate-900 rounded-xl ring-1 ring-gray-200 dark:ring-slate-700 w-full max-w-2xl flex flex-col" style="max-height:85vh">
            <div class="p-4 border-b border-gray-100 dark:border-slate-800 space-y-2">
                <div class="text-sm font-medium text-gray-800 dark:text-slate-200">Planning group</div>
                <input id="gName" placeholder="Group name (e.g. Injection Day)" class="w-full rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-transparent px-3 py-2 text-sm text-gray-800 dark:text-slate-200">
                <input id="gSearch" oninput="renderEditor()" placeholder="filter vulnerabilities…" class="w-full rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-transparent px-3 py-2 text-sm text-gray-800 dark:text-slate-200">
                <div class="text-xs text-gray-400 dark:text-slate-500">Check a vulnerability to include it, then set the level you'll have them exploit.</div>
            </div>
            <div id="gList" class="overflow-y-auto p-3 flex-1"></div>
            <div class="p-4 border-t border-gray-100 dark:border-slate-800 flex justify-end gap-2">
                <button onclick="closeGroupModal()" class="rounded-lg bg-gray-100 dark:bg-slate-800 px-3 py-2 text-xs font-medium">Cancel</button>
                <button onclick="saveGroup()" class="rounded-lg bg-amber-600 hover:bg-amber-500 text-white px-3 py-2 text-xs font-semibold">Save group</button>
            </div>
        </div>
    </div>

    <div id="status" class="mt-3 text-sm text-gray-600 dark:text-slate-400"></div>

    <script>
    const CSRF = "{$csrf}";
    const ALL = {$allListJson};
    let GROUPS = {$groupsJson};
    const LVL_LABEL = ['Vulnerable', 'Weak', 'Partial', 'Secure'];
    const STORE_KEY = 'bs_vulncfg_group';
    let activeGroup = null;
    try { activeGroup = localStorage.getItem(STORE_KEY) || null; } catch (e) {}
    if (activeGroup && !GROUPS.some(function(g){ return g.id === activeGroup; })) activeGroup = null;

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; });
    }
    function setStatus(msg, ok) {
        document.getElementById('status').textContent = (ok === false ? '✗ ' : (ok === true ? '✓ ' : '')) + msg;
    }

    // ---- Planning groups: select to highlight each vuln's pre-decided target ----
    function renderGroupsBar() {
        const bar = document.getElementById('groupsBar');
        let html = '<button onclick="selectGroup(null)" class="rounded-lg px-3 py-1 text-xs font-medium '
            + (activeGroup === null ? 'bg-amber-600 text-white' : 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-200 hover:bg-gray-200 dark:hover:bg-slate-700')
            + '">All vulnerabilities</button>';
        if (!GROUPS.length) {
            html += '<span class="text-xs text-gray-400 dark:text-slate-500 self-center">No groups yet — create one to pin a set with target levels.</span>';
        }
        html += GROUPS.map(function(g) {
            const n = g.members ? g.members.length : 0;
            const on = g.id === activeGroup;
            return '<div class="inline-flex items-center gap-1 rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 '
                + (on ? 'bg-amber-50 dark:bg-amber-500/15' : 'bg-gray-50 dark:bg-slate-800/60') + ' pl-1 pr-1 py-0.5">'
                + '<button onclick="selectGroup(\'' + g.id + '\')" class="rounded px-2 py-0.5 text-xs font-medium '
                + (on ? 'text-amber-800 dark:text-amber-200' : 'text-gray-800 dark:text-slate-200') + '">'
                + esc(g.name) + ' <span class="text-[10px] text-gray-400 dark:text-slate-500">(' + n + ')</span></button>'
                + '<button onclick="editGroup(\'' + g.id + '\')" class="rounded bg-gray-200 dark:bg-slate-700 hover:bg-gray-300 px-1.5 py-0.5 text-[11px] text-gray-700 dark:text-slate-200">Edit</button>'
                + '<button onclick="deleteGroup(\'' + g.id + '\')" title="Delete group" class="rounded hover:bg-red-100 dark:hover:bg-red-500/20 text-red-500 px-1 py-0.5 text-[11px]">✕</button>'
                + '</div>';
        }).join('');
        bar.innerHTML = html;
    }
    function selectGroup(id) {
        activeGroup = id;
        try { if (id) localStorage.setItem(STORE_KEY, id); else localStorage.removeItem(STORE_KEY); } catch (e) {}
        applyHighlight(); renderGroupsBar();
    }
    // Table body + original row order, captured once so "All vulnerabilities" can
    // restore the config order after a group has reordered the rows.
    const TBODY = document.querySelector('tbody');
    const ORIG_ROWS = Array.prototype.slice.call(TBODY.querySelectorAll('tr[data-key]'));
    const BYKEY = {};
    ALL.forEach(function(v){ BYKEY[v.key] = v; });

    function applyHighlight() {
        const g = GROUPS.find(function(x){ return x.id === activeGroup; });
        if (!g) {
            // Restore original order, show everything, clear the Target column.
            ORIG_ROWS.forEach(function(tr){
                tr.style.display = '';
                tr.querySelector('[data-target]').innerHTML = '';
                TBODY.appendChild(tr);
            });
            return;
        }
        const members = g.members || [];
        // Hide all rows first, then re-append members in the group's order.
        ORIG_ROWS.forEach(function(tr){
            tr.style.display = 'none';
            tr.querySelector('[data-target]').innerHTML = '';
        });
        members.forEach(function(m){
            const tr = ORIG_ROWS.find(function(r){ return r.getAttribute('data-key') === m.key; });
            if (!tr) return;
            const lvl = m.level;
            tr.style.display = '';
            tr.querySelector('[data-target]').innerHTML =
                '<button type="button" onclick="applyTarget(this,' + lvl + ')" title="Set this vulnerability\\'s level to L' + lvl + '"'
                + ' class="inline-flex items-center gap-1 rounded-md bg-amber-100 dark:bg-amber-500/25 hover:bg-amber-200 dark:hover:bg-amber-500/40 text-amber-800 dark:text-amber-200 ring-1 ring-amber-300 dark:ring-amber-500/40 px-2 py-0.5 text-xs font-semibold cursor-pointer">'
                + '★ Set L' + lvl + ' · ' + LVL_LABEL[lvl] + '</button>';
            TBODY.appendChild(tr);
        });
    }
    // One-click: set the row's dropdown to the target level and apply it.
    function applyTarget(btn, lvl) {
        const tr = btn.closest('tr[data-key]');
        if (!tr) return;
        const sel = tr.querySelector('select');
        if (sel) sel.value = String(lvl);
        setLevel(tr.getAttribute('data-mod'), tr.getAttribute('data-vuln'), lvl);
    }

    // ---- Group editor: editMembers is an ORDERED list of {key, level} ----
    let editId = null;
    let editMembers = [];
    function openModal()       { const m = document.getElementById('groupModal'); m.classList.remove('hidden'); m.classList.add('flex'); }
    function closeGroupModal() { const m = document.getElementById('groupModal'); m.classList.add('hidden'); m.classList.remove('flex'); }
    function memberIndex(key) {
        for (let i = 0; i < editMembers.length; i++) { if (editMembers[i].key === key) return i; }
        return -1;
    }
    function newGroup() {
        editId = null; editMembers = [];
        document.getElementById('gName').value = '';
        document.getElementById('gSearch').value = '';
        renderEditor(); openModal();
    }
    function editGroup(id) {
        const g = GROUPS.find(function(x){ return x.id === id; });
        if (!g) return;
        editId = id;
        editMembers = (g.members || []).map(function(m){ return {key: m.key, level: m.level}; });
        document.getElementById('gName').value = g.name;
        document.getElementById('gSearch').value = '';
        renderEditor(); openModal();
    }
    function toggleMember(key, on) {
        const idx = memberIndex(key);
        if (on) { if (idx < 0) editMembers.push({key: key, level: 1}); }   // new members append to the end
        else    { if (idx >= 0) editMembers.splice(idx, 1); }
        renderEditor();
    }
    function removeMember(key) { toggleMember(key, false); }
    function setTarget(key, lvl) { const i = memberIndex(key); if (i >= 0) editMembers[i].level = Number(lvl); }
    function moveMember(key, dir) {
        const i = memberIndex(key), j = i + dir;
        if (i < 0 || j < 0 || j >= editMembers.length) return;
        const t = editMembers[i]; editMembers[i] = editMembers[j]; editMembers[j] = t;
        renderEditor();
    }
    function renderEditor() {
        // Selected vulns, in order, with reorder/level/remove controls.
        let selHtml;
        if (editMembers.length) {
            selHtml = editMembers.map(function(m, i){
                const v = BYKEY[m.key] || {title: m.key};
                let opts = '';
                for (let k = 0; k <= 3; k++) {
                    opts += '<option value="' + k + '"' + (m.level === k ? ' selected' : '') + '>L' + k + ' · ' + LVL_LABEL[k] + '</option>';
                }
                const up   = '<button type="button" onclick="moveMember(\'' + m.key + '\',-1)"' + (i === 0 ? ' disabled' : '') + ' title="Move up" class="leading-none text-xs text-gray-500 hover:text-amber-600 disabled:opacity-25">▲</button>';
                const down = '<button type="button" onclick="moveMember(\'' + m.key + '\',1)"' + (i === editMembers.length - 1 ? ' disabled' : '') + ' title="Move down" class="leading-none text-xs text-gray-500 hover:text-amber-600 disabled:opacity-25">▼</button>';
                return '<div class="flex items-center gap-2 py-1 px-2 rounded bg-amber-50/70 dark:bg-amber-500/10 mb-1">'
                    + '<span class="text-[11px] text-gray-400 w-5 text-right tabular-nums">' + (i + 1) + '.</span>'
                    + '<div class="flex flex-col -space-y-0.5">' + up + down + '</div>'
                    + '<span class="font-mono text-[11px] text-gray-500 dark:text-slate-400">' + esc(m.key) + '</span>'
                    + '<span class="text-sm text-gray-800 dark:text-slate-200 flex-1 truncate">' + esc(v.title) + '</span>'
                    + '<select onchange="setTarget(\'' + m.key + '\',this.value)" class="rounded ring-1 ring-amber-300 dark:ring-amber-500/40 bg-amber-50 dark:bg-amber-500/15 text-amber-800 dark:text-amber-200 px-1.5 py-0.5 text-xs">' + opts + '</select>'
                    + '<button type="button" onclick="removeMember(\'' + m.key + '\')" title="Remove" class="text-red-500 hover:bg-red-100 dark:hover:bg-red-500/20 rounded px-1 text-xs">✕</button>'
                    + '</div>';
            }).join('');
            selHtml = '<div class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-slate-500 mb-1">In this group · ▲▼ sets the order they appear</div>' + selHtml;
        } else {
            selHtml = '<div class="text-xs text-gray-400 dark:text-slate-500 mb-1">No vulnerabilities yet — check some below to add them.</div>';
        }

        // Add/remove list — the search box filters this part only.
        const q = (document.getElementById('gSearch').value || '').toLowerCase();
        const items = ALL.filter(function(v){ return !q || (v.module + ' ' + v.id + ' ' + v.title).toLowerCase().indexOf(q) >= 0; });
        const addHtml = items.map(function(v){
            const on = memberIndex(v.key) >= 0;
            return '<label class="flex items-center gap-2 py-1 px-2 rounded hover:bg-gray-50 dark:hover:bg-slate-800/60 cursor-pointer">'
                + '<input type="checkbox"' + (on ? ' checked' : '') + ' onchange="toggleMember(\'' + v.key + '\',this.checked)" class="h-4 w-4 rounded">'
                + '<span class="font-mono text-[11px] text-gray-500 dark:text-slate-400">' + esc(v.key) + '</span>'
                + '<span class="text-sm text-gray-800 dark:text-slate-200 flex-1">' + esc(v.title) + '</span>'
                + '</label>';
        }).join('');

        document.getElementById('gList').innerHTML = selHtml
            + '<div class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-slate-500 mb-1 mt-3 pt-2 border-t border-gray-100 dark:border-slate-800">Add vulnerabilities</div>'
            + addHtml;
    }
    async function saveGroup() {
        const name = document.getElementById('gName').value.trim();
        if (!name) { alert('Group name is required.'); return; }
        if (!editMembers.length) { alert('Add at least one vulnerability.'); return; }
        const r = await fetch('/vuln-config', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'group-save', id: editId || '', name: name, members: editMembers, csrf_token: CSRF})
        });
        const d = await r.json();
        if (d.ok) {
            GROUPS = d.groups;
            activeGroup = d.id;
            try { localStorage.setItem(STORE_KEY, d.id); } catch (e) {}
            renderGroupsBar(); applyHighlight(); closeGroupModal();
            setStatus('Group "' + name + '" saved (' + editMembers.length + ' vulns).', true);
        } else setStatus(d.error || 'Failed', false);
    }
    async function deleteGroup(id) {
        const g = GROUPS.find(function(x){ return x.id === id; });
        if (!g) return;
        if (!confirm('Delete group "' + g.name + '"? This does not change any vulnerability levels.')) return;
        const r = await fetch('/vuln-config', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'group-delete', id: id, csrf_token: CSRF})
        });
        const d = await r.json();
        if (d.ok) {
            GROUPS = d.groups;
            if (activeGroup === id) selectGroup(null); else renderGroupsBar();
            setStatus('Group deleted.', true);
        } else setStatus(d.error || 'Failed', false);
    }

    renderGroupsBar();
    applyHighlight();

    async function setLevel(mod, vuln, level) {
        const r = await fetch('/vuln-config', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'set-level', module: mod, vuln, level: Number(level), csrf_token: CSRF})
        });
        const d = await r.json();
        document.getElementById('status').textContent = d.ok
            ? '✓ ' + mod + '.' + vuln + ' → Level ' + level
            : '✗ ' + (d.error || 'Failed');
    }
    async function applyPreset(preset) {
        if (!confirm('Apply preset "' + preset + '" to ALL vulnerabilities?')) return;
        const r = await fetch('/vuln-config', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'preset', preset, csrf_token: CSRF})
        });
        const d = await r.json();
        if (d.ok) location.reload();
    }
    </script>
HTML;

sa_render('Vulnerability Config', $content, 'vuln-config');
