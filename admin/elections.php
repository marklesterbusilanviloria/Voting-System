<?php
// =============================================================
//  admin/elections.php 
// =============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

// ---- HANDLE POST ACTIONS -----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create new election
    if ($action === 'create') {
        $title  = trim($_POST['title'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $start  = $_POST['start_date'] ?? '';
        $end    = $_POST['end_date']   ?? '';

        if ($title && $start && $end && $end > $start) {
            $now    = date('Y-m-d H:i:s');
            $status = $start <= $now ? ($end >= $now ? 'ongoing' : 'ended') : 'upcoming';
            $stmt   = $db->prepare(
                "INSERT INTO elections (title, description, start_date, end_date, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$title, $desc, $start, $end, $status, $_SESSION['user_id']]);
            $elecId = $db->lastInsertId();

            // Add default positions for this election
            $positions = [
                'President','Vice-President Internal','Vice-President External',
                'General Secretary','Deputy Secretary','Treasurer','Auditor',
                'Business Manager','Public Information Officer',
                'Bachelor of Science in Biology Representative','Bachelor of Science in Computer Science Representative',
                'Bachelor of Science in Human Services Representative','Bachelor of Science in Psychology Representative',
                'Bachelor of Science in Mathematics Representative'
            ];
            $posStmt = $db->prepare("INSERT INTO positions (election_id, title, sort_order) VALUES (?, ?, ?)");
            foreach ($positions as $i => $pos) {
                $posStmt->execute([$elecId, $pos, $i]);
            }

            setFlash('success', 'Election created with ' . count($positions) . ' positions.');
        } else {
            setFlash('error', 'Please fill all fields and ensure end date is after start date.');
        }
        header('Location: /admin/elections.php'); exit;
    }

   // Update status manually
    if ($action === 'setstatus') {
        $id     = intval($_POST['election_id']);
        $status = $_POST['status'] ?? '';
        
        if ($id && in_array($status, ['ongoing', 'ended'])) {
            // Check if another election is already ongoing
            if ($status === 'ongoing') {
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM elections WHERE status='ongoing' AND id != ?");
                $stmtCheck->execute([$id]);
                if ($stmtCheck->fetchColumn() > 0) {
                    setFlash('error', 'Cannot start this election. Another election is currently ongoing.');
                    header('Location: /admin/elections.php'); exit;
                }
            }
            
            $db->prepare("UPDATE elections SET status=? WHERE id=?")->execute([$status, $id]);
            setFlash('success', 'Election status updated.');
        }
        header('Location: /admin/elections.php'); exit;
    }

    // Reschedule election
    if ($action === 'reschedule') {
        $id    = intval($_POST['election_id']);
        $start = $_POST['start_date'] ?? '';
        $end   = $_POST['end_date']   ?? '';
        
        if ($id && $start && $end && $end > $start) {
            $now    = date('Y-m-d H:i:s');
            $newStatus = $start <= $now ? ($end >= $now ? 'ongoing' : 'ended') : 'upcoming';
            
            // Check for ongoing conflict if the reschedule would set it to ongoing
            if ($newStatus === 'ongoing') {
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM elections WHERE status='ongoing' AND id != ?");
                $stmtCheck->execute([$id]);
                if ($stmtCheck->fetchColumn() > 0) {
                    setFlash('error', 'Reschedule failed: The new dates would overlap with another ongoing election.');
                    header('Location: /admin/elections.php'); exit;
                }
            }

            $db->prepare(
                "UPDATE elections SET start_date=?, end_date=?, status=? WHERE id=?"
            )->execute([$start, $end, $newStatus, $id]);
            setFlash('success', 'Election rescheduled successfully.');
        } else {
            setFlash('error', 'Invalid dates. End date must be after start date.');
        }
        header('Location: /admin/elections.php'); exit;
    }

    // Delete election
    if ($action === 'delete') {
        $id = intval($_POST['election_id']);
        if ($id) {
            $db->prepare("DELETE FROM elections WHERE id=?")->execute([$id]);
            setFlash('success', 'Election deleted.');
        }
        header('Location: /admin/elections.php'); exit;
    }
}

// Auto-sync election statuses based on current time
$db->exec("UPDATE elections SET status='ongoing' WHERE start_date <= NOW() AND end_date >= NOW() AND status='upcoming'");
$db->exec("UPDATE elections SET status='ended'   WHERE end_date   <  NOW() AND status='ongoing'");

// Load all elections
$elections = $db->query(
    "SELECT e.*, u.first_name, u.last_name,
            (SELECT COUNT(*) FROM positions p WHERE p.election_id=e.id) AS pos_count,
            (SELECT COUNT(*) FROM candidates c WHERE c.election_id=e.id) AS cand_count,
            (SELECT COUNT(DISTINCT voter_id) FROM votes v WHERE v.election_id=e.id) AS vote_count
     FROM elections e
     LEFT JOIN users u ON u.id = e.created_by
     ORDER BY e.created_at DESC"
)->fetchAll();

$anyOngoing = in_array('ongoing', array_column($elections, 'status'));

// For ended elections, pre-load positions + candidates so we can drive the print modal
$endedData = []; // keyed by election id
foreach ($elections as $e) {
    if ($e['status'] !== 'ended') continue;

    $pStmt = $db->prepare(
        "SELECT p.id, p.title, p.sort_order,
                COUNT(v.id) AS vote_count
         FROM positions p
         LEFT JOIN votes v ON v.position_id = p.id AND v.election_id = ?
         GROUP BY p.id ORDER BY p.sort_order"
    );
    $pStmt->execute([$e['id']]);
    $positions = $pStmt->fetchAll();

    $cStmt = $db->prepare(
        "SELECT c.*, COUNT(v.id) AS votes
         FROM candidates c
         LEFT JOIN votes v ON v.candidate_id = c.id
         WHERE c.election_id = ?
         GROUP BY c.id
         ORDER BY c.position_id, votes DESC"
    );
    $cStmt->execute([$e['id']]);
    $candsByPos = [];
    foreach ($cStmt->fetchAll() as $c) {
        $candsByPos[$c['position_id']][] = $c;
    }

    $totalVoters = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $sv = $db->prepare("SELECT COUNT(DISTINCT voter_id) FROM votes WHERE election_id=?");
    $sv->execute([$e['id']]);
    $totalVotes = (int)$sv->fetchColumn();
    $turnout = $totalVoters > 0 ? round($totalVotes / $totalVoters * 100, 1) : 0;

    $endedData[$e['id']] = [
        'positions'   => $positions,
        'candsByPos'  => $candsByPos,
        'totalVoters' => $totalVoters,
        'totalVotes'  => $totalVotes,
        'turnout'     => $turnout,
    ];
}

$navActive     = 'dashboard';
$sidebarActive = 'elections';
$flash         = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Elections | iVOTE CS</title>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <base href="<?= BASE_URL ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shared.css">
    <style>
        body { background:#f8fafc; padding-top:0px; }
        .page-wrap { display:flex; }
        .main { flex:1; margin-left:280px; padding:40px; }
        .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; flex-wrap:wrap; gap:16px; }
        .page-header h2 { font-family:'Montserrat',sans-serif; color:#12341d; font-size:1.6rem; font-weight:900; margin:0; }

        /* Create form card */
        .create-card {
            background:#fff; border-radius:20px; padding:32px;
            border:1px solid #e2e8f0; margin-bottom:32px;
            box-shadow:0 4px 16px rgba(0,0,0,0.06);
        }
        .create-card h3 { font-family:'Montserrat',sans-serif; color:#12341d; font-size:1.1rem; font-weight:800; margin-bottom:20px;}
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        .form-group.full { grid-column:span 2; }
        .form-label { font-size:0.78rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; }
        .form-input {
            padding:11px 14px; border:1px solid #e2e8f0; border-radius:10px;
            font-size:14px; font-family:'Geist',sans-serif; outline:none; transition:border 0.2s;
        }
        .form-input:focus { border-color:#33553e; box-shadow:0 0 0 3px rgba(51,85,62,0.08); }
        .btn-create {
            padding:12px 28px; background:#12341d; color:#fff; border:none;
            border-radius:12px; font-family:'Montserrat',sans-serif; font-weight:800;
            font-size:14px; cursor:pointer; transition:all 0.2s; margin-top:8px;
        }
        .btn-create:hover { background:#33553e; transform:translateY(-2px); }

        /* Election cards */
        .elections-list { display:flex; flex-direction:column; gap:18px; }
        .election-card {
            background:#fff; border-radius:18px; padding:24px 28px;
            border:1px solid #e2e8f0; box-shadow:0 3px 12px rgba(0,0,0,0.05);
            transition:all 0.25s;
        }
        .election-card:hover { box-shadow:0 8px 24px rgba(18,52,29,0.1); }
        .election-top { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
        .election-title { font-family:'Montserrat',sans-serif; font-size:1.15rem; font-weight:800; color:#12341d; }
        .election-desc  { color:#64748b; font-size:0.88rem; margin-top:4px; line-height:1.5; }
        .election-dates { color:#33553e; font-size:0.82rem; margin-top:6px; font-weight:600; }

        .pill {
            display:inline-block; padding:5px 14px; border-radius:100px;
            font-family:'Montserrat',sans-serif; font-weight:700; font-size:0.72rem;
            letter-spacing:0.5px; text-transform:uppercase; flex-shrink:0;
        }
        .pill-ongoing  { background:#d1fae5; color:#065f46; }
        .pill-upcoming { background:#dbeafe; color:#1e40af; }
        .pill-ended    { background:#f1f5f9; color:#64748b;  }

        .election-meta { display:flex; gap:24px; margin-top:16px; flex-wrap:wrap; }
        .meta-item { display:flex; flex-direction:column; }
        .meta-label { font-size:0.72rem; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; }
        .meta-value { font-family:'Montserrat',sans-serif; font-size:1.1rem; font-weight:800; color:#12341d; margin-top:3px; }

        .election-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:18px; padding-top:18px; border-top:1px solid #f1f5f9; align-items:center; }
        .act-btn {
            padding:8px 18px; border-radius:10px; font-size:0.82rem;
            font-weight:700; font-family:'Montserrat',sans-serif; cursor:pointer; transition:all 0.2s; border:none;
        }
        .btn-ongoing  { background:#d1fae5; color:#065f46; }
        .btn-ended    { background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; }
        .btn-reschedule { background:#fef9c3; color:#854d0e; border:1px solid #fde68a; }
        .btn-delete   { background:#fee2e2; color:#dc2626; }
        .act-btn:hover { filter:brightness(92%); transform:translateY(-1px); }

        /* Reschedule inline form */
        .reschedule-form {
            display:none; margin-top:16px; padding:20px; background:#fefce8;
            border:1px solid #fde68a; border-radius:14px;
        }
        .reschedule-form.open { display:block; }
        .reschedule-form .rform-title {
            font-family:'Montserrat',sans-serif; font-size:0.85rem; font-weight:800;
            color:#854d0e; margin-bottom:14px;
        }
        .reschedule-form .rform-grid { display:grid; grid-template-columns:1fr 1fr auto; gap:12px; align-items:end; }
        .reschedule-form .form-label { color:#92400e; }
        .reschedule-form .form-input { background:#fff; border-color:#fde68a; }
        .reschedule-form .form-input:focus { border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,0.12); }
        .btn-rsave {
            padding:11px 20px; background:#854d0e; color:#fff; border:none;
            border-radius:10px; font-family:'Montserrat',sans-serif; font-weight:800;
            font-size:0.82rem; cursor:pointer; transition:all 0.2s; white-space:nowrap;
        }
        .btn-rsave:hover { background:#6b3a08; transform:translateY(-1px); }

        .empty-state { text-align:center; padding:60px 20px; color:#94a3b8; }
        .empty-state .icon { font-size:3rem; margin-bottom:16px; }

        /* ── Print results modal ── */
        .modal-overlay {
            display:none; position:fixed; inset:0; z-index:1000;
            background:rgba(0,0,0,0.45); backdrop-filter:blur(4px);
            align-items:center; justify-content:center; padding:20px;
        }
        .modal-overlay.open { display:flex; }
        .modal-box {
            background:#fff; border-radius:22px; width:min(560px,100%);
            box-shadow:0 24px 60px rgba(0,0,0,0.22);
            overflow:hidden; animation:modalIn 0.22s ease;
        }
        @keyframes modalIn { from { opacity:0; transform:translateY(16px) scale(0.97); } to { opacity:1; transform:none; } }
        .modal-head {
            background:linear-gradient(145deg,#12341d,#33553e);
            padding:22px 28px; display:flex; align-items:center; justify-content:space-between;
        }
        .modal-head h3 { font-family:'Montserrat',sans-serif; color:#fff; font-size:1rem; font-weight:800; margin:0; }
        .modal-close {
            background:rgba(255,255,255,0.15); border:none; color:#fff;
            width:32px; height:32px; border-radius:50%; cursor:pointer;
            font-size:1rem; display:flex; align-items:center; justify-content:center; transition:background 0.2s;
        }
        .modal-close:hover { background:rgba(255,255,255,0.28); }
        .modal-body { padding:28px; display:grid; gap:16px; }
        .modal-print-card {
            border-radius:16px; padding:20px; border:1px solid #e2e8f0;
            display:flex; align-items:flex-start; gap:16px;
        }
        .modal-print-card.dark {
            background:linear-gradient(145deg,#12341d,#33553e);
            border-color:transparent; color:#fff;
        }
        .modal-print-card.light { background:#f8fafc; }
        .mpc-icon { font-size:1.6rem; flex-shrink:0; margin-top:2px; }
        .mpc-text h4 { font-family:'Montserrat',sans-serif; font-size:0.9rem; font-weight:800; margin:0 0 5px; }
        .modal-print-card.dark .mpc-text h4 { color:#fff; }
        .modal-print-card.light .mpc-text h4 { color:#12341d; }
        .mpc-text p { font-size:0.82rem; line-height:1.55; margin:0 0 14px; }
        .modal-print-card.dark .mpc-text p { color:rgba(255,255,255,0.78); }
        .modal-print-card.light .mpc-text p { color:#64748b; }
        .mpc-btn {
            display:inline-flex; align-items:center; gap:7px;
            padding:9px 18px; border-radius:10px; border:none; cursor:pointer;
            font-family:'Montserrat',sans-serif; font-weight:800; font-size:0.82rem; transition:all 0.18s;
        }
        .modal-print-card.dark  .mpc-btn { background:#fff; color:#12341d; }
        .modal-print-card.light .mpc-btn { background:#12341d; color:#fff; }
        .mpc-btn:hover { opacity:0.88; transform:translateY(-1px); }
        .btn-print-results {
            background:#e0f2e9; color:#12341d; border:1px solid #a7d7b8;
        }
        .btn-print-results:hover { background:#c6e8d4; }

        /* ── Mobile responsive ── */
        @media (max-width: 768px) {
            .main { margin-left:0; padding:20px; }
            .page-header h2 { font-size:1.25rem; margin-left: 45px; margin-top: 7px;}

            /* Create form: 2-col grid collapses to 1-col */
            .form-grid { grid-template-columns:1fr; }
            /* The .full span:2 rule is irrelevant at 1-col but reset it cleanly */
            .form-group.full { grid-column:span 1; }
            /* Create button full-width */
            .btn-create { width:100%; text-align:center; }

            /* Election card padding */
            .election-card { padding:18px 16px; }

            /* Reschedule form: 3-col (start, end, save) → stacked */
            .reschedule-form .rform-grid {
                grid-template-columns:1fr;
            }

            /* Election top: title and pill can wrap, already does — just tighten gap */
            .election-top { gap:10px; }
        }
    </style>
</head>
<body>
<?php // $navActive='dashboard'; require_once __DIR__ . '/../includes/navbar.php'; ?>
<div class="page-wrap">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="main">
        <?php if ($flash): ?>
            <div class="flash <?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <div class="page-header">
            <h2>Manage Elections</h2>
        </div>

        <!-- Create form -->
        <div class="create-card">
            <h3><img src = /assets/img/icons/add.png> Create New Election</h3>
            <form method="POST" action="/admin/elections.php">
                <input type="hidden" name="action" value="create">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Election Title</label>
                        <input class="form-input" type="text" name="title" placeholder="e.g. COS General Elections 2026" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <input class="form-input" type="text" name="description" placeholder="Short description (optional)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Date &amp; Time</label>
                        <input class="form-input" type="datetime-local" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date &amp; Time</label>
                        <input class="form-input" type="datetime-local" name="end_date" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn-create">Create Election</button>
                        <span style="font-size:0.78rem;color:#94a3b8;margin-top:6px">14 default positions will be added automatically.</span>
                    </div>
                </div>
            </form>
        </div>

        <!-- Elections list -->
        <?php if (empty($elections)): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <p>No elections yet. Create one above to get started.</p>
        </div>
        <?php else: ?>
        <div class="elections-list">
        <?php foreach ($elections as $e): ?>
            <div class="election-card">
                <div class="election-top">
                    <div>
                        <div class="election-title"><?= htmlspecialchars($e['title']) ?></div>
                        <?php if ($e['description']): ?>
                            <div class="election-desc"><?= htmlspecialchars($e['description']) ?></div>
                        <?php endif; ?>
                        <div class="election-dates">
                            <?= date('M d, Y g:ia', strtotime($e['start_date'])) ?>
                            &nbsp;→&nbsp;
                            <?= date('M d, Y g:ia', strtotime($e['end_date'])) ?>
                        </div>
                    </div>
                    <span class="pill pill-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span>
                </div>

                <div class="election-meta">
                    <div class="meta-item">
                        <span class="meta-label">Positions</span>
                        <span class="meta-value"><?= $e['pos_count'] ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Candidates</span>
                        <span class="meta-value"><?= $e['cand_count'] ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Votes Cast</span>
                        <span class="meta-value"><?= $e['vote_count'] ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Created By</span>
                        <span class="meta-value" style="font-size:0.9rem"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></span>
                    </div>
                </div>

                <div class="election-actions">
                    <!-- Status overrides -->
                   <form method="POST" style="display:contents">
    <input type="hidden" name="action" value="setstatus">
    <input type="hidden" name="election_id" value="<?= $e['id'] ?>">
    
    <?php if ($e['status'] !== 'ongoing'): ?>
        <?php if ($anyOngoing): ?>
            <button type="button" class="act-btn" style="background:#f1f5f9; color:#94a3b8; cursor:not-allowed;" title="Another election is already ongoing" disabled>▶ Set Ongoing</button>
        <?php else: ?>
            <button type="submit" name="status" value="ongoing" class="act-btn btn-ongoing">▶ Set Ongoing</button>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if ($e['status'] !== 'ended'): ?>
        <button type="submit" name="status" value="ended" class="act-btn btn-ended">⏹ End Election</button>
    <?php endif; ?>
</form>

                    <!-- Reschedule toggle -->
                    <button type="button" class="act-btn btn-reschedule"
                            onclick="toggleReschedule(<?= $e['id'] ?>)">
                        Reschedule
                    </button>

                    <a href="/admin/candidates.php?election_id=<?= $e['id'] ?>" class="act-btn" style="background:#d5e8db;color:#12341d;text-decoration:none;padding:8px 18px;">Manage Candidates</a>

                    <?php if ($e['status'] === 'ended' && !empty($endedData[$e['id']]['positions'])): ?>
                    <button type="button" class="act-btn btn-print-results"
                            onclick="openPrintModal(<?= $e['id'] ?>)">
                        🖨️ Print Results
                    </button>
                    <?php endif; ?>

                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this election and ALL its data? This cannot be undone.')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="election_id" value="<?= $e['id'] ?>">
                        <button type="submit" class="act-btn btn-delete">🗑 Delete</button>
                    </form>
                </div>

                <!-- Reschedule inline form -->
                <div class="reschedule-form" id="reschedule-<?= $e['id'] ?>">
                    <div class="rform-title">Reschedule Election</div>
                    <form method="POST" action="/admin/elections.php">
                        <input type="hidden" name="action" value="reschedule">
                        <input type="hidden" name="election_id" value="<?= $e['id'] ?>">
                        <div class="rform-grid">
                            <div class="form-group">
                                <label class="form-label">New Start Date &amp; Time</label>
                                <input class="form-input" type="datetime-local" name="start_date"
                                       value="<?= date('Y-m-d\TH:i', strtotime($e['start_date'])) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">New End Date &amp; Time</label>
                                <input class="form-input" type="datetime-local" name="end_date"
                                       value="<?= date('Y-m-d\TH:i', strtotime($e['end_date'])) ?>" required>
                            </div>
                            <button type="submit" class="btn-rsave">Save</button>
                        </div>
                    </form>
                </div>

            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
<!-- Per-election JSON data for JS print functions -->
<?php foreach ($elections as $e):
    if ($e['status'] !== 'ended' || empty($endedData[$e['id']])) continue;
    $ed = $endedData[$e['id']];
    $posForJs = array_map(fn($p) => [
        'id'         => $p['id'],
        'title'      => $p['title'],
        'vote_count' => $p['vote_count'],
    ], $ed['positions']);
?>
<script type="application/json" id="elecData-<?= $e['id'] ?>">
<?= json_encode([
    'title'       => $e['title'],
    'start'       => date('F d, Y', strtotime($e['start_date'])),
    'end'         => date('F d, Y', strtotime($e['end_date'])),
    'genDate'     => date('F d, Y'),
    'totalVoters' => $ed['totalVoters'],
    'totalVotes'  => $ed['totalVotes'],
    'turnout'     => $ed['turnout'],
    'positions'   => $posForJs,
    'candidates'  => $ed['candsByPos'],
]) ?>
</script>
<?php endforeach; ?>

</main>
</div>

<!-- ── Print Results Modal ── -->
<div class="modal-overlay" id="printModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>🖨️ Print Election Results</h3>
            <button class="modal-close" onclick="closePrintModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="modal-print-card dark">
                <div class="mpc-icon">🏆</div>
                <div class="mpc-text">
                    <h4>Elected Officers Summary</h4>
                    <p>Print a clean results sheet showing the top-ranked (elected) candidate per position for this election.</p>
                    <button class="mpc-btn" onclick="printWinners()">Print Officers Summary</button>
                </div>
            </div>
            <div class="modal-print-card light">
                <div class="mpc-icon">📄</div>
                <div class="mpc-text">
                    <h4>Full Results</h4>
                    <p>Print the complete results with all candidates ranked by votes across every position, plus participation data.</p>
                    <button class="mpc-btn" onclick="printFull()">Print Full Results</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Hidden winners print templates (one per ended election) ── -->
<?php foreach ($elections as $e):
    if ($e['status'] !== 'ended' || empty($endedData[$e['id']])) continue;
    $ed = $endedData[$e['id']];
?>
<div id="winnersPrint-<?= $e['id'] ?>" style="display:none;">
    <div class="doc-header">
        <img class="doc-logo" src="/assets/img/icons/logo.png" alt="Logo" onerror="this.style.display='none'">
        <p class="doc-orgname">College of Science Association (COSA)</p>
        <p class="doc-elec-title"><?= htmlspecialchars($e['title']) ?> — Official Results</p>
        <p class="doc-meta">
            Election Period:
            <?= date('F d, Y', strtotime($e['start_date'])) ?> – <?= date('F d, Y', strtotime($e['end_date'])) ?>
            &nbsp;|&nbsp; Document Generated: <?= date('F d, Y') ?>
        </p>
    </div>
    <div class="doc-certified">
        ✅ &nbsp;The following candidates have been duly ELECTED as Officers based on the final vote tally.
    </div>
    <table class="doc-table">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:30%">Position</th>
                <th style="width:36%">Elected Officer</th>
                <th style="width:29%">Course / Student ID</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($ed['positions'] as $pos):
                $posCands = $ed['candsByPos'][$pos['id']] ?? [];
                if (empty($posCands)) continue;
                $topVotes  = (int)$posCands[0]['votes'];
                $topCands  = array_filter($posCands, fn($c) => (int)$c['votes'] === $topVotes);
                $isTie     = count($topCands) > 1;
            ?>
            <?php foreach ($topCands as $tc): ?>
            <tr>
                <td class="td-num"><?= $i++ ?></td>
                <td class="td-pos"><?= htmlspecialchars($pos['title']) ?></td>
                <td class="td-name"><?= htmlspecialchars($tc['name']) ?><span class="td-badge"><?= $isTie ? 'Tie' : 'Elected' ?></span></td>
                <td class="td-course">
                    <?= htmlspecialchars($tc['course']) ?>
                    <span class="td-sid"><?= htmlspecialchars($tc['student_id'] ?? '') ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="sig-section">
        <div class="sig-title">Certified and Attested by:</div>
        <div class="sig-row">
            <div class="sig-block"><div class="sig-line"></div><div class="sig-label">COMELEC Chairperson</div></div>
            <div class="sig-block"><div class="sig-line"></div><div class="sig-label">College Dean / Adviser</div></div>
            <div class="sig-block"><div class="sig-line"></div><div class="sig-label">Date Certified</div></div>
        </div>
    </div>
    <div class="doc-footer">
        <span>College of Science Association (COSA)</span>
        <span>System-generated by iVOTE CS &nbsp;|&nbsp; Confidential</span>
        <span>Page 1 of 1</span>
    </div>
</div>
<?php endforeach; ?>
<script src="<?= BASE_URL ?>assets/js/shared.js"></script>
<script>
    // ── Reschedule toggle ──
    function toggleReschedule(id) {
        const panel = document.getElementById('reschedule-' + id);
        panel.classList.toggle('open');
    }

    // ── Print modal ──
    let _activeElecId = null;

    function openPrintModal(elecId) {
        _activeElecId = elecId;
        document.getElementById('printModal').classList.add('open');
    }
    function closePrintModal() {
        document.getElementById('printModal').classList.remove('open');
        _activeElecId = null;
    }
    // Close on overlay click
    document.getElementById('printModal').addEventListener('click', function(e) {
        if (e.target === this) closePrintModal();
    });

    // ── Shared print window helper ──
    function openPrintWindow(html, title) {
        const win = window.open('', '_blank', 'width=900,height=700');
        win.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>${title}</title>
        <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;600;700&family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
        <style>
            * { box-sizing:border-box; margin:0; padding:0; }
            body { font-family:'Geist',Arial,sans-serif; background:#fff; color:#0f172a; padding:18mm; }
            @page { size:A4 portrait; margin:14mm 18mm 20mm; }
            @media print { body { padding:0; } }

            .doc-header { text-align:center; padding-bottom:14px; margin-bottom:18px; border-bottom:2.5px solid #12341d; }
            .doc-logo { width:76px; height:76px; object-fit:contain; display:block; margin:0 auto 10px; }
            .doc-orgname { font-family:'Montserrat',Arial,sans-serif; font-size:14pt; font-weight:900; color:#12341d; margin:0 0 2px; text-transform:uppercase; letter-spacing:0.6px; }
            .doc-elec-title { font-family:'Montserrat',Arial,sans-serif; font-size:10.5pt; font-weight:800; color:#33553e; margin:0 0 5px; }
            .doc-meta { font-size:7.5pt; color:#64748b; margin:0; }

            .doc-certified { background:#f0fdf4; border:1.5px solid #6ee7b7; border-radius:6px; padding:7px 14px; margin:14px 0 18px; text-align:center; font-size:8.5pt; color:#065f46; font-family:'Montserrat',Arial,sans-serif; font-weight:700; }

            .summary-row { display:flex; gap:12px; margin-bottom:18px; }
            .summary-box { flex:1; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; }
            .summary-box .s-label { font-size:7pt; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; font-family:'Montserrat',sans-serif; font-weight:800; margin-bottom:4px; }
            .summary-box .s-value { font-family:'Montserrat',sans-serif; font-size:16pt; font-weight:900; color:#12341d; }
            .summary-box .s-sub { font-size:7pt; color:#94a3b8; margin-top:2px; }
            .turnout-bar { height:8px; background:#e2e8f0; border-radius:4px; margin:8px 0 4px; }
            .turnout-fill { height:100%; border-radius:4px; background:linear-gradient(90deg,#12341d,#33553e,#6d9078); }

            .doc-table { width:100%; border-collapse:collapse; margin-top:6px; }
            .doc-table thead tr { border-bottom:1.5px solid #12341d; }
            .doc-table th { color:#64748b; font-family:'Montserrat',Arial,sans-serif; font-size:7pt; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; padding:7px 8px; text-align:left; }
            .doc-table td { padding:9px 10px; font-size:9pt; border-bottom:1px solid #e2e8f0; vertical-align:middle; }
            .doc-table tbody tr:last-child td { border-bottom:none; }

            .pos-header-row td { background:#f8fafc; font-family:'Montserrat',sans-serif; font-weight:800; font-size:8.5pt; color:#12341d; padding:8px 8px 6px; border-bottom:1px solid #e2e8f0; border-top:6px solid #fff; }
            .pos-votes-note { font-weight:400; font-size:7.5pt; color:#94a3b8; margin-left:8px; }

            .td-num { color:#94a3b8; font-size:8pt; white-space:nowrap; }
            .td-num sup { font-size:6pt; }
            .td-pos { font-family:'Montserrat',Arial,sans-serif; font-weight:800; color:#0f172a; font-size:9pt; }
            .td-name { font-weight:600; font-size:8.5pt; }
            .td-course { font-size:8pt; color:#475569; }
            .td-sid { font-size:7pt; color:#94a3b8; display:block; margin-top:1px; }
            .td-pct { font-size:8pt; color:#475569; white-space:nowrap; }
            .td-votes { font-size:9pt; text-align:right; white-space:nowrap; }
            .td-votes strong { font-family:'Montserrat',sans-serif; font-weight:900; color:#12341d; }

            .td-badge { display:inline-block; background:#d1fae5; color:#065f46; border-radius:20px; padding:2px 9px; font-size:6.5pt; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; vertical-align:middle; margin-left:6px; font-family:'Montserrat',Arial,sans-serif; }
            .td-badge.lead { background:#fef3c7; color:#92400e; }
            .lead-row td { background:#fffbeb; }

            .sig-section { margin-top:40px; }
            .sig-title { font-family:'Montserrat',Arial,sans-serif; font-weight:800; font-size:8pt; color:#12341d; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:32px; }
            .sig-row { display:flex; justify-content:space-around; gap:20px; }
            .sig-block { flex:1; text-align:center; }
            .sig-line { border-top:1px solid #0f172a; margin-top:44px; padding-top:5px; }
            .sig-label { font-family:'Montserrat',Arial,sans-serif; font-size:7pt; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.3px; }

            .doc-footer { position:fixed; bottom:0; left:0; right:0; display:flex; justify-content:space-between; font-size:7pt; color:#94a3b8; border-top:1px solid #e2e8f0; padding:5px 18mm 3px; background:#fff; }
        </style>
        </head><body>${html}</body></html>`);
        win.document.close();
        win.onload = () => win.print();
    }

    // ── Print winners (Officers Summary) ──
    function printWinners() {
        if (!_activeElecId) return;
        const section = document.getElementById('winnersPrint-' + _activeElecId);
        if (!section) return;
        openPrintWindow(section.innerHTML, 'Elected Officers — iVOTE CS');
    }

    // ── Print full results ──
    function printFull() {
        if (!_activeElecId) return;

        // Pull the PHP-encoded data embedded per election
        const dataEl = document.getElementById('elecData-' + _activeElecId);
        if (!dataEl) return;
        const data = JSON.parse(dataEl.textContent);

        const { title, start, end, genDate, totalVoters, totalVotes, turnout, positions, candidates } = data;
        const suffixes = ['st','nd','rd'];

        let positionRows = '';
        positions.forEach(pos => {
            const cands = candidates[pos.id] || [];
            const posTotal = cands.reduce((s, c) => s + parseInt(c.votes || 0), 0);

            positionRows += `<tr class="pos-header-row"><td colspan="5">${pos.title}
                <span class="pos-votes-note">${pos.vote_count} vote${pos.vote_count != 1 ? 's' : ''} cast</span></td></tr>`;

            if (!cands.length) {
                positionRows += `<tr><td colspan="5" style="color:#94a3b8;padding:8px;font-size:8.5pt;">No candidates registered.</td></tr>`;
            } else {
                const topVotes = parseInt(cands[0].votes || 0);
                const isTie = cands.filter(c => parseInt(c.votes || 0) === topVotes).length > 1;
                cands.forEach((c, rank) => {
                    const pct = posTotal > 0 ? Math.round(c.votes / posTotal * 1000) / 10 : 0;
                    const rankN = rank + 1;
                    const suffix = suffixes[Math.min(rankN - 1, 2)] || 'th';
                    const isCandTied = isTie && parseInt(c.votes || 0) === topVotes;
                    const isLeader  = rank === 0 && !isTie;
                    const rowClass  = (isCandTied || isLeader) ? ' lead-row' : '';
                    positionRows += `<tr class="${rowClass}">
                        <td class="td-num">${rankN}<sup>${suffix}</sup></td>
                        <td class="td-name">${c.name}${isCandTied ? '<span class="td-badge lead">🤝 Tie</span>' : (isLeader ? '<span class="td-badge lead">🏆 Leading</span>' : '')}</td>
                        <td class="td-course">${c.course}${c.partylist ? `<span class="td-sid">${c.partylist}</span>` : ''}</td>
                        <td class="td-pct">${pct}%</td>
                        <td class="td-votes"><strong>${c.votes}</strong></td>
                    </tr>`;
                });
            }
        });

        const html = `
            <div class="doc-header">
                <img class="doc-logo" src="/assets/img/icons/logo.png" alt="Logo" onerror="this.style.display='none'">
                <p class="doc-orgname">College of Science Association (COSA)</p>
                <p class="doc-elec-title">${title} — Full Election Results</p>
                <p class="doc-meta">Election Period: ${start} – ${end} &nbsp;|&nbsp; Document Generated: ${genDate}</p>
            </div>
            <div class="doc-certified">📊 &nbsp;Complete candidate rankings for all positions based on the final vote tally.</div>
            <div class="summary-row">
                <div class="summary-box">
                    <div class="s-label">Eligible Voters</div>
                    <div class="s-value">${totalVoters}</div>
                    <div class="s-sub">Registered students</div>
                </div>
                <div class="summary-box">
                    <div class="s-label">Votes Submitted</div>
                    <div class="s-value">${totalVotes}</div>
                    <div class="s-sub">Recorded ballots</div>
                </div>
                <div class="summary-box" style="flex:2">
                    <div class="s-label">Voter Turnout — ${turnout}%</div>
                    <div class="turnout-bar"><div class="turnout-fill" style="width:${turnout}%"></div></div>
                    <div class="s-sub">${turnout}% of eligible voters participated</div>
                </div>
            </div>
            <table class="doc-table">
                <thead>
                    <tr>
                        <th style="width:5%">Rank</th>
                        <th style="width:32%">Candidate</th>
                        <th style="width:28%">Course / Party</th>
                        <th style="width:10%">Share</th>
                        <th style="width:10%;text-align:right">Votes</th>
                    </tr>
                </thead>
                <tbody>${positionRows}</tbody>
            </table>
            <div class="doc-footer">
                <span>College of Science Association (COSA)</span>
                <span>System-generated by iVOTE CS &nbsp;|&nbsp; Confidential</span>
                <span>${title}</span>
            </div>`;

        openPrintWindow(html, `Full Results — iVOTE CS`);
    }
</script>
</body>
</html>