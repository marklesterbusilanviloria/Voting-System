<?php
// =============================================================
//  admin/dashboard.php
// =============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

// Auto-sync election statuses
$db->exec("UPDATE elections SET status='ongoing' WHERE start_date <= NOW() AND end_date >= NOW() AND status='upcoming'");
$db->exec("UPDATE elections SET status='ended'   WHERE end_date   <  NOW() AND status='ongoing'");

// ── Active / most-recent election ───────────────────────────
// We must get the election FIRST so we have an ID to filter the stats with.
$election = $db->query("SELECT * FROM elections WHERE status='ongoing' LIMIT 1")->fetch();
if (!$election) {
    $election = $db->query("SELECT * FROM elections ORDER BY start_date DESC LIMIT 1")->fetch();
}

// ── Stats ────────────────────────────────────────────────────
// Total voters (Global: all eligible students)
$totalVoters = $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();

// Default values in case there are no elections created yet
$totalCandidates = 0;
$votesCast       = 0;

if ($election) {
    // Candidates for THIS election
    $stmtC = $db->prepare("SELECT COUNT(*) FROM candidates WHERE election_id = ?");
    $stmtC->execute([$election['id']]);
    $totalCandidates = $stmtC->fetchColumn();

    // Votes cast in THIS election
    $stmtV = $db->prepare("SELECT COUNT(DISTINCT voter_id) FROM votes WHERE election_id = ?");
    $stmtV->execute([$election['id']]);
    $votesCast = $stmtV->fetchColumn();
}

// Calculate unvoted
$notVoted = $totalVoters - $votesCast;

// ── Post-election: winner per position = highest vote count ──
// Purely automatic — no manual override needed.
$winners = []; // keyed by position title

if ($election && $election['status'] === 'ended') {
    $rows = $db->prepare(
        "SELECT p.title        AS position,
                p.sort_order,
                c.id           AS candidate_id,
                c.name,
                c.course,
                c.student_id   AS cand_sid,
                c.photo,
                COUNT(v.id)    AS vote_count
         FROM positions p
         JOIN candidates c  ON c.position_id  = p.id AND c.election_id = p.election_id
         LEFT JOIN votes v  ON v.candidate_id = c.id AND v.election_id = p.election_id
         WHERE p.election_id = ?
         GROUP BY p.id, c.id
         ORDER BY p.sort_order ASC, vote_count DESC"
    );
    $rows->execute([$election['id']]);

    // Collect all candidates per position, then detect ties at the top vote count.
    // A tie means two or more candidates share the highest vote count (including zero).
    $allByPosition = []; // position title => [ candidate rows ]
    foreach ($rows->fetchAll() as $r) {
        $allByPosition[$r['position']][] = $r;
    }
    foreach ($allByPosition as $position => $cands) {
        $topVotes = (int) $cands[0]['vote_count'];
        $topCands = array_filter($cands, fn($c) => (int)$c['vote_count'] === $topVotes);
        $isTie    = count($topCands) > 1;
        // Store as array of winners; each entry has an extra 'is_tie' flag
        foreach ($topCands as $tc) {
            $tc['is_tie']             = $isTie;
            $winners[$position][]     = $tc;
        }
    }
}

$navActive     = 'dashboard';
$sidebarActive = 'dashboard';
$flash         = getFlash();

// ── Organisation details for the printable document ──────────
$orgName    = 'College of Science Association (COSA)';
$orgLogoUrl = '/assets/img/icons/logo.png'; // adjust if path differs
$printDate  = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | iVOTE CS</title>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <base href="<?= BASE_URL ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shared.css">
    <style>
        body { background:#f8fafc; padding-top:0px; }
        .page-wrap { display:flex; }
        .main { flex:1; margin-left:280px; padding:40px; min-height:100vh; }
        h1 { font-family:'Montserrat',sans-serif; color:#12341d; font-size:1.8rem; font-weight:900; margin-bottom:6px; }
        .sub { color:#33553e; margin-bottom:30px; font-size:0.95rem; }

        /* Stats */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:30px; }
        .stat-card {
            background:#fff; border-radius:18px; padding:24px;
            border:1px solid #e2e8f0; box-shadow:0 4px 15px rgba(0,0,0,0.05); transition:transform 0.25s;
        }
        .stat-card:hover { transform:translateY(-4px); }
        .stat-label { font-size:0.8rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:10px; }
        .stat-value { font-family:'Montserrat',sans-serif; font-size:2rem; font-weight:900; color:#12341d; }
        .stat-sub   { font-size:0.82rem; color:#33553e; margin-top:6px; }

        /* Election card */
        .election-card {
            background:#fff; border-radius:18px; padding:28px;
            border:1px solid #e2e8f0; margin-bottom:30px;
        }
        .election-card h2 { font-family:'Montserrat',sans-serif; font-size:1.1rem; font-weight:800; color:#12341d; margin-bottom:16px; }
        .election-title-row { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:6px; }
        .election-name  { font-family:'Montserrat',sans-serif; font-size:1.2rem; font-weight:800; color:#12341d; }
        .election-dates { color:#33553e; font-size:0.82rem; margin-top:4px; font-weight:600; }
        .status-pill {
            display:inline-block; padding:4px 14px; border-radius:100px;
            font-family:'Montserrat',sans-serif; font-weight:700; font-size:0.72rem;
            letter-spacing:0.5px; text-transform:uppercase;
        }
        .pill-ongoing  { background:#d1fae5; color:#065f46; }
        .pill-upcoming { background:#dbeafe; color:#1e40af; }
        .pill-ended    { background:#f1f5f9; color:#64748b; }

        /* Countdown */
        .countdown-row { display:flex; gap:14px; flex-wrap:wrap; margin-top:20px; }
        .cd-block {
            background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px;
            padding:14px 20px; text-align:center; min-width:72px; flex:1;
        }
        .cd-num { font-family:'Montserrat',sans-serif; font-size:1.8rem; font-weight:900; color:#12341d; display:block; line-height:1; }
        .cd-lbl { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#64748b; margin-top:4px; display:block; }

        /* Results - screen */
        .section-title {
            font-family:'Montserrat',sans-serif; color:#12341d; font-size:1.1rem; font-weight:800;
            margin-bottom:16px; display:flex; align-items:center; gap:10px;
        }
        .results-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; margin-bottom:24px; }
        .winner-card {
            background:#fff; border-radius:16px; border:1px solid #e2e8f0;
            box-shadow:0 3px 12px rgba(0,0,0,0.05); overflow:hidden;
        }
        .winner-card-header {
            background:linear-gradient(135deg,#12341d,#33553e); color:#fff;
            padding:10px 16px; font-family:'Montserrat',sans-serif; font-weight:800; font-size:0.82rem;
        }
        .winner-card-body { padding:16px; display:flex; align-items:center; gap:14px; }
        .winner-avatar {
            width:52px; height:52px; border-radius:50%; flex-shrink:0;
            background:#d5e8db; display:flex; align-items:center; justify-content:center;
            font-weight:900; color:#12341d; font-size:1.1rem; overflow:hidden; border:2px solid #a4c1ad;
        }
        .winner-avatar img { width:100%; height:100%; object-fit:cover; }
        .winner-name { font-family:'Montserrat',sans-serif; font-weight:800; font-size:0.95rem; color:#0f172a; }
        .winner-meta { font-size:0.78rem; color:#64748b; margin-top:3px; }
        .elected-tag {
            display:inline-block; margin-top:6px; padding:3px 10px;
            background:#d1fae5; color:#065f46; border-radius:100px;
            font-size:0.68rem; font-weight:800; text-transform:uppercase;
            letter-spacing:0.5px; font-family:'Montserrat',sans-serif;
        }

        /* Download button */
        .btn-download {
            padding:12px 28px; background:#12341d; color:#fff; border:none;
            border-radius:12px; font-family:'Montserrat',sans-serif; font-weight:800;
            font-size:14px; cursor:pointer; transition:all 0.2s; margin-bottom:24px;
            display:inline-flex; align-items:center; gap:8px;
        }
        .btn-download:hover { background:#33553e; transform:translateY(-2px); }

        /* Quick actions */
        .actions-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; }
        .action-card {
            background:#12341d; color:#fff; border-radius:16px; padding:22px;
            border:none; text-align:left; font-family:'Geist',sans-serif;
            transition:all 0.25s; text-decoration:none; display:block;
        }
        .action-card:hover { background:#33553e; transform:translateY(-3px); box-shadow:0 12px 24px rgba(18,52,29,0.2); }
        .action-icon { font-size:1.6rem; margin-bottom:12px; }
        .action-name { font-family:'Montserrat',sans-serif; font-weight:800; font-size:0.95rem; }
        .action-desc { font-size:0.78rem; opacity:0.75; margin-top:4px; }

        /* Sidebar adjustments */
        .sidebar .nav-logo { color: #12341d; text-decoration: none; margin-bottom: 20px; }
        .sidebar .logo-icon { background: #d5e8db; width: 50px; height: 50px; border-radius: 50%; border: 2px solid #12341d; }
        .sidebar .logo-text { font-size: 18px; }
        .sidebar-logout { margin-top: auto; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        #printDoc { display:none; }

        /* ── Mobile responsive ── */
        @media (max-width: 768px) {
            .main { margin-left:0; padding:20px; }
            h1 { font-size:1.3rem; margin-left: 45px; margin-top: 8px; margin-bottom: 15px;}
            .stat-value { font-size:1.6rem; }
            /* Countdown blocks: don't let them get too tiny */
            .cd-block { min-width:56px; padding:10px 12px; }
            .cd-num { font-size:1.4rem; }
            /* Winner cards: full-width on phones */
            .results-grid { grid-template-columns:1fr; }
            /* Action cards: 1-col on phones */
            .actions-grid { grid-template-columns:1fr; }
            /* Download button: full width */
            .btn-download { width:100%; justify-content:center; }
            /* Election title row: stack pill below title */
            .election-title-row { flex-direction:column; align-items:flex-start; gap:8px; }
        }

        @media print {
            /* Hide the normal page and browser-injected header */
            body > *:not(#printDoc) { display:none !important; }
            h1, .sub { display:none !important; }

            #printDoc {
                display:block !important;
                font-family:'Geist', Arial, sans-serif;
                color:#0f172a;
            }

            @page {
                size:A4 portrait;
                margin:12mm 18mm 18mm;
                /* suppress browser-printed URL / page title lines */
                @top-center   { content: none; }
                @bottom-center { content: none; }
            }

            /* Header */
            .doc-header {
                text-align:center;
                padding:24px 20px 20px;
                margin-bottom:24px;
                border-bottom:3px solid #12341d;
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                border-radius: 8px;
            }
            .doc-logo {
                width:80px; height:80px; object-fit:contain;
                margin-bottom:12px; display:block; margin-left:auto; margin-right:auto;
                border-radius:50%; border:3px solid #12341d; background:#fff; padding:4px;
            }
            .doc-orgname {
                font-family:'Montserrat', Arial, sans-serif;
                font-size:14pt; font-weight:900; color:#12341d;
                margin:0 0 6px; text-transform:uppercase; letter-spacing:0.8px;
                text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }
            .doc-elec-title {
                font-family:'Montserrat', Arial, sans-serif;
                font-size:12pt; font-weight:800; color:#33553e; margin:0 0 8px;
                letter-spacing:0.3px;
            }
            .doc-meta { font-size:9pt; color:#64748b; margin:0; font-weight:600; }

            /* Certified banner */
            .doc-certified {
                background:#f0fdf4; border:1px solid #6ee7b7;
                border-radius:6px; padding:7px 14px; margin:14px 0 18px;
                text-align:center; font-size:8.5pt; color:#065f46;
                font-family:'Montserrat', Arial, sans-serif; font-weight:700;
            }

            /* Table */
            .doc-table { width:100%; border-collapse:collapse; }
            .doc-table thead tr { background:#12341d; }
            .doc-table th {
                color:#fff; font-family:'Montserrat', Arial, sans-serif;
                font-size:8pt; font-weight:800; text-transform:uppercase;
                letter-spacing:0.4px; padding:8px 10px; text-align:left;
            }
            .doc-table td { padding:8px 10px; font-size:9pt; border-bottom:1px solid #e2e8f0; }
            .doc-table tbody tr:nth-child(even) td { background:#f8fafc; }
            .doc-table tbody tr:last-child td { border-bottom:none; }
            .td-num    { color:#94a3b8; font-size:8pt; text-align:center; }
            .td-pos    { font-family:'Montserrat', Arial, sans-serif; font-weight:700; color:#12341d; font-size:8.5pt; }
            .td-name   { font-weight:600; }
            .td-badge  {
                display:inline-block; background:#d1fae5; color:#065f46;
                border-radius:20px; padding:2px 8px;
                font-size:7pt; font-weight:800; text-transform:uppercase;
            }

            /* Signature area */
            .sig-section { margin-top:36px; }
            .sig-title {
                font-family:'Montserrat', Arial, sans-serif; font-weight:800;
                font-size:8.5pt; color:#12341d; text-transform:uppercase;
                letter-spacing:0.5px; margin-bottom:24px;
            }
            .sig-row { display:flex; justify-content:space-around; gap:20px; }
            .sig-block { flex:1; text-align:center; }
            .sig-line  { border-top:1px solid #0f172a; padding-top:5px; margin-top:36px; }
            .sig-label {
                font-family:'Montserrat', Arial, sans-serif;
                font-size:7.5pt; font-weight:700; color:#475569;
                text-transform:uppercase; letter-spacing:0.3px;
            }

            /* Footer — fixed so it repeats on every printed page */
            .doc-footer {
                position:fixed; bottom:0; left:0; right:0;
                display:flex; justify-content:space-between;
                font-size:7pt; color:#94a3b8;
                border-top:1px solid #e2e8f0; padding-top:6px;
                padding-bottom:4px;
                background:#fff;
            }

            /* Push content up so the last row never slides under the footer */
            #printDoc { padding-bottom:16mm; }
        }
    </style>
</head>
<body>
<?php // require_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="page-wrap">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="main">

        <?php if ($flash): ?>
            <div class="flash <?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <h1>Admin Dashboard</h1>
        <p class="sub">Welcome back, <?= htmlspecialchars(currentUser()['name']) ?>. Here's the system overview.</p>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Approved Voters</div>
                <div class="stat-value"><?= number_format($totalVoters) ?></div>
                <div class="stat-sub">Eligible to vote</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Voted</div>
                <div class="stat-value"><?= number_format($votesCast) ?></div>
                <div class="stat-sub"><?= $totalVoters > 0 ? round($votesCast / $totalVoters * 100, 1) : 0 ?>% turnout</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Not Yet Voted</div>
                <div class="stat-value" style="color:<?= $notVoted > 0 ? '#d97706' : '#12341d' ?>">
                    <?= number_format($notVoted) ?>
                </div>
                <div class="stat-sub"><?= $notVoted > 0 ? 'Still pending' : 'All voted!' ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Candidates</div>
                <div class="stat-value"><?= $totalCandidates ?></div>
                <div class="stat-sub">Registered candidates</div>
            </div>
        </div>

        <!-- Election + countdown -->
        <div class="election-card">
            <h2>Current Election</h2>
            <?php if ($election): ?>
                <div class="election-title-row">
                    <span class="election-name"><?= htmlspecialchars($election['title']) ?></span>
                    <span class="status-pill pill-<?= $election['status'] ?>"><?= ucfirst($election['status']) ?></span>
                </div>
                <div class="election-dates">
                    <?= date('M d, Y g:ia', strtotime($election['start_date'])) ?>
                    &nbsp;→&nbsp;
                    <?= date('M d, Y g:ia', strtotime($election['end_date'])) ?>
                </div>
                <?php if ($election['status'] === 'ongoing'): ?>
                    <p style="font-size:0.85rem;color:#64748b;margin-top:12px">Time remaining until election closes:</p>
                    <div class="countdown-row" id="countdown"></div>
                <?php elseif ($election['status'] === 'upcoming'): ?>
                    <p style="font-size:0.85rem;color:#64748b;margin-top:12px">Time until election opens:</p>
                    <div class="countdown-row" id="countdown"></div>
                <?php else: ?>
                    <p style="font-size:0.85rem;color:#94a3b8;margin-top:12px">This election has ended. Elected officers are shown below.</p>
                <?php endif; ?>
            <?php else: ?>
                <p style="color:#94a3b8;">No elections have been created yet. <a href="/admin/elections.php" style="color:#33553e;font-weight:700">Create one →</a></p>
            <?php endif; ?>
        </div>

        <!-- Elected officers (screen) -->
        <?php if (!empty($winners)): ?>
        <div class="section-title">🏆 Elected Officers</div>

        <button class="btn-download" onclick="window.print()">
            ⬇️ Download / Print Results Document
        </button>

        <div class="results-grid">
            <?php foreach ($winners as $position => $winnerList): ?>
            <div class="winner-card">
                <div class="winner-card-header"><?= htmlspecialchars($position) ?></div>
                <?php foreach ($winnerList as $w): ?>
                <div class="winner-card-body">
                    <div class="winner-avatar">
                        <?php if ($w['photo']): ?>
                            <img src="/<?= htmlspecialchars(ltrim($w['photo'], '/')) ?>" alt="">
                        <?php else: ?>
                            <?= htmlspecialchars(mb_substr($w['name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="winner-name"><?= htmlspecialchars($w['name']) ?></div>
                        <div class="winner-meta">
                            <?= htmlspecialchars($w['course']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($w['cand_sid']) ?>
                        </div>
                        <span class="elected-tag"><?= $w['is_tie'] ? '🤝 Tie' : '🏅 Elected' ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Quick actions -->
        <div class="section-title">Quick Actions</div>
        <div class="actions-grid">
            <a href="/admin/accounts.php" class="action-card">
                <div class="action-icon"><span><img src = /assets/img/icons/manageAccountW.png></span></div>
                <div class="action-name">Manage Accounts</div>
                <div class="action-desc">Add or manage voter accounts</div>
            </a>
            <a href="/admin/elections.php" class="action-card">
                <div class="action-icon"><span><img src = /assets/img/icons/electionW.png></span></div>
                <div class="action-name">Manage Elections</div>
                <div class="action-desc">Create or close elections</div>
            </a>
            <a href="/admin/candidates.php" class="action-card">
                <div class="action-icon"><span><img src = /assets/img/icons/addCandidateW.png></span></div>
                <div class="action-name">Manage Candidates</div>
                <div class="action-desc">Register candidates per position</div>
            </a>
        </div>

    </main>
</div>

<!-- ═══════════════════════════════════════════════════════════
     PRINTABLE DOCUMENT
     Hidden on screen — only rendered when window.print() fires.
═══════════════════════════════════════════════════════════ -->
<?php if (!empty($winners) && $election): ?>
<div id="printDoc">

    <div class="doc-header">
        <img class="doc-logo"
             src="<?= htmlspecialchars($orgLogoUrl) ?>"
             alt="Logo"
             onerror="this.style.display='none'">
        <p class="doc-orgname"><?= htmlspecialchars($orgName) ?></p>
        <p class="doc-elec-title"><?= htmlspecialchars($election['title']) ?> — Official Results</p>
        <p class="doc-meta">
            Election Period:
            <?= date('F d, Y', strtotime($election['start_date'])) ?>
            &nbsp;–&nbsp;
            <?= date('F d, Y', strtotime($election['end_date'])) ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            Document Generated: <?= $printDate ?>
        </p>
    </div>

    <div class="doc-certified">
        ✅ &nbsp;The following candidates have been duly ELECTED as Officers based on the final vote tally.
    </div>

    <table class="doc-table">
        <thead>
            <tr>
                <th style="width:6%; text-align:center">#</th>
                <th style="width:32%">Position</th>
                <th style="width:36%">Elected Officer</th>
                <th style="width:26%">Course / Student ID</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($winners as $position => $winnerList): ?>
            <?php foreach ($winnerList as $w): ?>
            <tr>
                <td class="td-num"><?= $i++ ?></td>
                <td class="td-pos"><?= htmlspecialchars($position) ?></td>
                <td class="td-name">
                    <?= htmlspecialchars($w['name']) ?>
                    &nbsp;<span class="td-badge"><?= $w['is_tie'] ? 'Tie' : 'Elected' ?></span>
                </td>
                <td><?= htmlspecialchars($w['course']) ?><br><span style="color:#94a3b8;font-size:7.5pt"><?= htmlspecialchars($w['cand_sid']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="sig-section">
        <div class="sig-title">Certified and Attested by:</div>
        <div class="sig-row">
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">COMELEC Chairperson</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">College Dean / Adviser</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-label">Date Certified</div>
            </div>
        </div>
    </div>

    <div class="doc-footer">
        <span><?= htmlspecialchars($orgName) ?></span>
        <span>System-generated by iVOTE CS &nbsp;|&nbsp; Confidential</span>
        <span>Page 1 of 1</span>
    </div>

</div>
<?php endif; ?>

<script src="<?= BASE_URL ?>assets/js/shared.js"></script>
<script>
<?php if ($election && in_array($election['status'], ['ongoing','upcoming'])): ?>
(function () {
    const target = new Date("<?= addslashes($election['status'] === 'ongoing' ? $election['end_date'] : $election['start_date']) ?>").getTime();
    const el = document.getElementById('countdown');
    if (!el) return;
    function tick() {
        const diff = target - Date.now();
        if (diff <= 0) {
            el.innerHTML = '<span style="color:#33553e;font-weight:700">⏰ Refresh to update status.</span>';
            return;
        }
        const d = Math.floor(diff / 86400000),
              h = Math.floor(diff % 86400000 / 3600000),
              m = Math.floor(diff % 3600000  / 60000),
              s = Math.floor(diff % 60000    / 1000);
        el.innerHTML = [['Days',d],['Hours',h],['Mins',m],['Secs',s]]
            .map(([l,v]) => `<div class="cd-block"><span class="cd-num">${String(v).padStart(2,'0')}</span><span class="cd-lbl">${l}</span></div>`)
            .join('');
    }
    tick();
    setInterval(tick, 1000);
})();
<?php endif; ?>
</script>
</body>
</html>
