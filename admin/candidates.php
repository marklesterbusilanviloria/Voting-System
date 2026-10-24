<?php
// =============================================================
//  admin/candidates.php
// =============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

// Auto-sync statuses before any action
$db->exec("UPDATE elections SET status='ongoing' WHERE start_date <= NOW() AND end_date >= NOW() AND status='upcoming'");
$db->exec("UPDATE elections SET status='ended'   WHERE end_date   <  NOW() AND status='ongoing'");

// ---- HANDLE POST -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $elecId = intval($_POST['election_id'] ?? 0);

    // Verify election exists and is not ongoing/ended
    $elec = null;
    if ($elecId) {
        $es = $db->prepare("SELECT * FROM elections WHERE id=? LIMIT 1");
        $es->execute([$elecId]);
        $elec = $es->fetch();
    }

    if ($elec && $elec['status'] === 'ongoing') {
        setFlash('error', 'Candidates cannot be added or removed while an election is ongoing.');
        header("Location: /admin/candidates.php?election_id=$elecId"); exit;
    }
    if ($elec && $elec['status'] === 'ended') {
        setFlash('error', 'This election has already ended. Candidate list is locked.');
        header("Location: /admin/candidates.php?election_id=$elecId"); exit;
    }

    if ($action === 'add') {
        $posId   = intval($_POST['position_id']);
        $name    = trim($_POST['name']         ?? '');
        $sid     = trim($_POST['student_id']   ?? '');
        $course  = trim($_POST['course']       ?? '');
        $party   = trim($_POST['partylist']    ?? '');
        $motto   = trim($_POST['motto']        ?? '');
        $plats   = trim($_POST['platforms']    ?? '');
        $achieve = trim($_POST['achievements'] ?? '');

        $photoPath = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename  = 'cand_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/profiles/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                $photoPath = '/uploads/profiles/' . $filename;
            }
        }

        if ($elecId && $posId && $name && $sid && $course) {
            $stmt = $db->prepare(
                "INSERT INTO candidates (election_id, position_id, student_id, name, course, partylist, motto, platforms, achievements, photo)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([$elecId, $posId, $sid, $name, $course, $party, $motto, $plats, $achieve, $photoPath]);
            setFlash('success', "Candidate \"$name\" added successfully.");
        } else {
            setFlash('error', 'Name, Student ID, Position, and Course are required.');
        }
        header("Location: /admin/candidates.php?election_id=$elecId"); exit;
    }

    if ($action === 'delete') {
        $candId = intval($_POST['candidate_id']);
        $db->prepare("DELETE FROM candidates WHERE id=?")->execute([$candId]);
        setFlash('success', 'Candidate removed.');
        header("Location: /admin/candidates.php?election_id=$elecId"); exit;
    }
}

// ---- LOAD ELECTIONS FOR SELECTOR ---------------------------
$elections = $db->query(
    "SELECT id, title, status FROM elections ORDER BY created_at DESC"
)->fetchAll();

$selectedElecId = intval($_GET['election_id'] ?? ($elections[0]['id'] ?? 0));
$selectedElec   = null;
foreach ($elections as $e) {
    if ($e['id'] === $selectedElecId) { $selectedElec = $e; break; }
}

$isLocked = $selectedElec && in_array($selectedElec['status'], ['ongoing', 'ended']);

// Load positions + candidates
$positions  = [];
$candidates = [];
if ($selectedElecId) {
    $ps = $db->prepare("SELECT * FROM positions WHERE election_id=? ORDER BY sort_order");
    $ps->execute([$selectedElecId]);
    $positions = $ps->fetchAll();

    $cs = $db->prepare(
        "SELECT c.*, p.title AS position_title
         FROM candidates c
         JOIN positions p ON p.id=c.position_id
         WHERE c.election_id=?
         ORDER BY p.sort_order, c.name"
    );
    $cs->execute([$selectedElecId]);
    $candidates = $cs->fetchAll();
}

$byPosition = [];
foreach ($candidates as $c) {
    $byPosition[$c['position_id']][] = $c;
}

$navActive     = 'dashboard';
$sidebarActive = 'candidates';
$flash         = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Candidates | iVOTE CS</title>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <base href="<?= BASE_URL ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shared.css">
    <style>
        body { background:#f8fafc; padding-top:0px; }
        .page-wrap { display:flex; }
        .main { flex:1; margin-left:280px; padding:40px; }
        h2 { font-family:'Montserrat',sans-serif; color:#12341d; font-size:1.6rem; font-weight:900; margin-bottom:20px; }

        .election-selector { display:flex; gap:12px; align-items:center; margin-bottom:28px; flex-wrap:wrap; }
        .selector-label { font-weight:700; color:#33553e; font-size:0.9rem; }
        .election-select {
            padding:10px 16px; border:1px solid #e2e8f0; border-radius:12px;
            font-size:14px; font-family:'Geist',sans-serif; outline:none; background:#fff;
            min-width:280px; transition:border 0.2s;
        }
        .election-select:focus { border-color:#33553e; }

        /* Lock banner */
        .lock-banner {
            background:#fef3c7; border:1px solid #fcd34d; border-radius:14px;
            padding:16px 20px; margin-bottom:24px; display:flex; align-items:center; gap:12px;
        }
        .lock-banner.ended { background:#f1f5f9; border-color:#e2e8f0; }
        .lock-banner .lock-icon { font-size:1.5rem; flex-shrink:0; }
        .lock-banner p { font-size:0.9rem; font-weight:600; color:#92400e; margin:0; }
        .lock-banner.ended p { color:#64748b; }

        .content-grid { display:grid; grid-template-columns:380px 1fr; gap:28px; align-items:start; }
        @media(max-width:1100px){ .content-grid{ grid-template-columns:1fr; } }

        .form-card {
            background:#fff; border-radius:20px; padding:28px;
            border:1px solid #e2e8f0; box-shadow:0 4px 16px rgba(0,0,0,0.05);
            position:relative; top:1px;
        }
        .form-card h3 { font-family:'Montserrat',sans-serif; color:#12341d; font-size:1rem; font-weight:800; margin-bottom:18px; }
        .form-row { margin-bottom:14px; }
        .form-label { font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:5px; }
        .form-input, .form-select, .form-textarea {
            width:100%; padding:10px 13px; border:1px solid #e2e8f0; border-radius:10px;
            font-size:13px; font-family:'Geist',sans-serif; outline:none; transition:border 0.2s; box-sizing:border-box;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color:#33553e; }
        .form-textarea { resize:vertical; min-height:64px; }
        .btn-add {
            width:100%; padding:13px; background:#12341d; color:#fff; border:none;
            border-radius:12px; font-family:'Montserrat',sans-serif; font-weight:800;
            font-size:14px; cursor:pointer; transition:all 0.2s; margin-top:6px;
        }
        .btn-add:hover { background:#33553e; transform:translateY(-2px); }
        .btn-add:disabled { background:#94a3b8; cursor:not-allowed; transform:none; }

        .positions-wrapper { display:flex; flex-direction:column; gap:20px; }
        .position-section { background:#fff; border-radius:18px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 3px 12px rgba(0,0,0,0.04); }
        .position-header {
            background:linear-gradient(135deg,#12341d,#33553e); color:#fff;
            padding:14px 20px; display:flex; justify-content:space-between; align-items:center;
        }
        .position-name { font-family:'Montserrat',sans-serif; font-weight:800; font-size:0.95rem; }
        .cand-count-badge { background:rgba(255,255,255,0.2); padding:3px 10px; border-radius:100px; font-size:0.75rem; font-weight:700; }
        .cand-list { padding:12px; display:flex; flex-direction:column; gap:8px; }
        .cand-row {
            display:flex; align-items:center; justify-content:space-between;
            gap:12px; padding:12px 16px; background:#f8fafc;
            border-radius:12px; border:1px solid #e2e8f0; transition:all 0.2s;
        }
        .cand-row:hover { background:#d5e8db; border-color:#a4c1ad; }
        .cand-info .cand-name { font-weight:700; color:#0f172a; font-size:0.92rem; }
        .cand-info .cand-meta { font-size:0.78rem; color:#64748b; margin-top:2px; }
        .cand-party { font-size:0.72rem; background:#d5e8db; color:#12341d; padding:3px 10px; border-radius:100px; font-weight:700; flex-shrink:0; }
        .btn-del-cand {
            width:32px; height:32px; background:#fee2e2; color:#dc2626; border:none;
            border-radius:8px; cursor:pointer; font-size:14px; flex-shrink:0; transition:all 0.2s;
        }
        .btn-del-cand:hover { background:#dc2626; color:#fff; }
        .btn-del-cand:disabled { background:#f1f5f9; color:#94a3b8; cursor:not-allowed; }
        .empty-pos { padding:16px 20px; color:#94a3b8; font-size:0.85rem; text-align:center; }
        .no-election { text-align:center; padding:60px 20px; color:#94a3b8; }
        .no-election .icon { font-size:3rem; margin-bottom:14px; }

        /* ── Mobile responsive ── */
        @media (max-width: 768px) {
            .main { margin-left:0; padding:20px; }
            h2 { font-size:1.3rem; margin-bottom:16px; margin-top: 8px; margin-left: 45px;}

            /* Election selector: dropdown full-width */
            .election-selector { flex-direction:column; align-items:stretch; gap:8px; }
            .election-select { min-width:0; width:100%; }

            /* content-grid already collapses at 1100px; on mobile just ensure padding is sane */
            .content-grid { gap:20px; }

            /* Lock banner: tighten padding */
            .lock-banner { padding:12px 14px; }

            /* Candidate rows: allow party badge and delete to wrap if very tight */
            .cand-row { flex-wrap:wrap; gap:8px; }
            .cand-info { flex:1; min-width:0; }

            /* Add candidate button: full width */
            .btn-add { padding:12px; }
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

        <h2>Manage Candidates</h2>

        <div class="election-selector">
            <span class="selector-label">Election:</span>
            <form method="GET">
                <select class="election-select" name="election_id" onchange="this.form.submit()">
                    <?php if (empty($elections)): ?>
                        <option>No elections yet</option>
                    <?php endif; ?>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $e['id']===$selectedElecId?'selected':'' ?>>
                            <?= htmlspecialchars($e['title']) ?> [<?= ucfirst($e['status']) ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (!$selectedElecId || empty($elections)): ?>
        <div class="no-election">
            <div class="icon">🗳️</div>
            <p>No elections found. <a href="/admin/elections.php" style="color:#33553e">Create one first</a>.</p>
        </div>

        <?php else: ?>

        <?php if ($selectedElec && $selectedElec['status'] === 'ongoing'): ?>
        <div class="lock-banner">
            <span class="lock-icon">🔒</span>
            <p>This election is currently <strong>ongoing</strong>. The candidate list is locked — no additions or removals are allowed until the election ends.</p>
        </div>
        <?php elseif ($selectedElec && $selectedElec['status'] === 'ended'): ?>
        <div class="lock-banner ended">
            <span class="lock-icon">🏁</span>
            <p>This election has <strong>ended</strong>. The candidate list is permanently locked.</p>
        </div>
        <?php endif; ?>

        <div class="content-grid">

            <!-- Add Candidate Form -->
            <div class="form-card">
                <h3>➕ Add Candidate</h3>
                <?php if ($isLocked): ?>
                    <p style="color:#94a3b8;font-size:0.88rem">Adding candidates is disabled while the election is <?= $selectedElec['status'] ?>.</p>
                <?php else: ?>
                <form method="POST" action="/admin/candidates.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="election_id" value="<?= $selectedElecId ?>">

                    <div class="form-row">
                        <label class="form-label">Position *</label>
                        <select class="form-select" name="position_id" required>
                            <option value="">Select Position</option>
                            <?php foreach ($positions as $pos): ?>
                                <option value="<?= $pos['id'] ?>"><?= htmlspecialchars($pos['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Full Name *</label>
                        <input class="form-input" type="text" name="name" placeholder="Last, First M." required>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Student ID *</label>
                        <input class="form-input" type="text" name="student_id" placeholder="M2023-00000" required>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Course *</label>
                        <select class="form-select" name="course" required>
                            <option value="">Select Course</option>
                            <option>Bachelor of Science in Biology</option>
                            <option>Bachelor of Science in Computer Science</option>
                            <option>Bachelor of Science in Human Services</option>
                            <option>Bachelor of Science in Psychology</option>
                            <option>Bachelor of Science in Mathematics</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Candidate Photo</label>
                        <input class="form-input" type="file" name="photo" accept="image/*">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Partylist</label>
                        <input class="form-input" type="text" name="partylist" placeholder="Party name (optional)">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Motto</label>
                        <textarea class="form-textarea" name="motto" placeholder="Campaign motto..."></textarea>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Platforms</label>
                        <textarea class="form-textarea" name="platforms" placeholder="Key platforms..."></textarea>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Achievements</label>
                        <textarea class="form-textarea" name="achievements" placeholder="Notable achievements..."></textarea>
                    </div>
                    <button type="submit" class="btn-add">Add Candidate</button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Candidates by position -->
            <div class="positions-wrapper">
                <?php if (empty($positions)): ?>
                    <div class="no-election"><p>No positions found for this election.</p></div>
                <?php endif; ?>
                <?php foreach ($positions as $pos): ?>
                    <?php $cands = $byPosition[$pos['id']] ?? []; ?>
                    <div class="position-section">
                        <div class="position-header">
                            <span class="position-name"><?= htmlspecialchars($pos['title']) ?></span>
                            <span class="cand-count-badge"><?= count($cands) ?> candidate<?= count($cands)!==1?'s':'' ?></span>
                        </div>
                        <div class="cand-list">
                            <?php if (empty($cands)): ?>
                                <div class="empty-pos">No candidates yet for this position.</div>
                            <?php else: ?>
                                <?php foreach ($cands as $c): ?>
                                <div class="cand-row">
                                    <div class="cand-info">
                                        <div class="cand-name"><?= htmlspecialchars($c['name']) ?></div>
                                        <div class="cand-meta"><?= htmlspecialchars($c['course']) ?> · <?= htmlspecialchars($c['student_id']) ?></div>
                                    </div>
                                    <?php if ($c['partylist']): ?>
                                        <span class="cand-party"><?= htmlspecialchars($c['partylist']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!$isLocked): ?>
                                    <form method="POST" onsubmit="return confirm('Remove this candidate?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="candidate_id" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="election_id" value="<?= $selectedElecId ?>">
                                        <button type="submit" class="btn-del-cand" title="Remove">✕</button>
                                    </form>
                                    <?php else: ?>
                                        <button class="btn-del-cand" disabled title="Locked">✕</button>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>
<script src="<?= BASE_URL ?>assets/js/shared.js"></script>
</body>
</html>