<?php
// =============================================================
//  admin/accounts.php
// =============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

// ---- CREATE ACCOUNT (POST) ---------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // ── Create new student account ──────────────────────────
    if ($act === 'create') {
        header('Content-Type: application/json');
        $sid    = trim($_POST['student_id']  ?? '');
        $fname  = trim($_POST['first_name']  ?? '');
        $lname  = trim($_POST['last_name']   ?? '');
        $mi     = trim($_POST['middle_initial'] ?? '');
        $course = trim($_POST['course']      ?? '');
        $year   = trim($_POST['year_level']  ?? '1st Year');
        $pass   = trim($_POST['password']    ?? '');

        if (!$sid || !$fname || !$lname || !$course || !$pass) {
            echo json_encode(['status'=>'error','message'=>'All required fields must be filled.']); exit;
        }
        if (strlen($pass) < 6) {
            echo json_encode(['status'=>'error','message'=>'Password must be at least 6 characters.']); exit;
        }

        // Check duplicate student ID
        $chk = $db->prepare("SELECT id FROM users WHERE student_id=? LIMIT 1");
        $chk->execute([$sid]);
        if ($chk->fetch()) {
            echo json_encode(['status'=>'error','message'=>'That Student ID is already registered.']); exit;
        }

        $email = strtolower(str_replace(['-',' '], '', $sid)) . '@student.edu.ph';
        $hash  = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

        $ins = $db->prepare(
            "INSERT INTO users (student_id, first_name, last_name, middle_initial, email,
                                password_hash, course, year_level, role, status, verified_at, verified_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'student', 'approved', NOW(), ?)"
        );
        $ins->execute([$sid, $fname, $lname, $mi, $email, $hash, $course, $year, $_SESSION['user_id']]);
        $newId = $db->lastInsertId();

        echo json_encode([
            'status'  => 'success',
            'message' => "Account for $fname $lname created.",
            'user'    => [
                'id' => $newId, 'student_id' => $sid,
                'name' => "$fname $lname", 'course' => $course,
                'year_level' => $year,
            ]
        ]); exit;
    }

    // ── Delete account (AJAX) ────────────────────────────────
    if ($act === 'delete') {
        header('Content-Type: application/json');
        $raw    = file_get_contents('php://input');
        $data   = json_decode($raw, true) ?? $_POST;
        $userId = intval($data['userId'] ?? $_POST['userId'] ?? 0);
        if ($userId && $userId !== (int)$_SESSION['user_id']) {
            $db->prepare("DELETE FROM users WHERE id=? AND role='student'")->execute([$userId]);
            echo json_encode(['status'=>'success']); exit;
        }
        echo json_encode(['status'=>'error','message'=>'Invalid request.']); exit;
    }
}

// ---- VIEW DETAIL (AJAX GET) --------------------------------
if (isset($_GET['detail'])) {
    header('Content-Type: application/json');
    $uid = intval($_GET['detail']);
    $s   = $db->prepare("SELECT * FROM users WHERE id=? AND role='student'");
    $s->execute([$uid]);
    echo json_encode($s->fetch() ?: ['error'=>'Not found']);
    exit;
}

// ---- LOAD ACCOUNTS -----------------------------------------
$search     = trim($_GET['q']    ?? '');
$filterYear = trim($_GET['year'] ?? '');
$page       = max(1, intval($_GET['page'] ?? 1));
$perPage    = 10;
$offset     = ($page - 1) * $perPage;

$where  = ["role='student'"];
$params = [];
if ($search) {
    $where[]  = "(student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filterYear) {
    $where[]  = "year_level = ?";
    $params[] = $filterYear;
}
$whereSQL = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $whereSQL");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pages = ceil($total / $perPage);

$stmt = $db->prepare("SELECT * FROM users WHERE $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$accounts = $stmt->fetchAll();

$navActive     = 'dashboard';
$sidebarActive = 'accounts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts | iVOTE CS</title>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <base href="<?= BASE_URL ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shared.css">
    <style>
        body { background:#f8fafc; padding-top:0px; }
        .page-wrap { display:flex; }
        .main { flex:1; margin-left:280px; padding:40px; }
        h2 { font-family:'Montserrat',sans-serif; color:#12341d; font-size:1.6rem; font-weight:800; margin-bottom:20px; }

        /* Top bar */
        .top-bar { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px; align-items:center; justify-content:space-between; }
        .filters { display:flex; gap:12px; flex-wrap:wrap; }
        .filter-input {
            padding:10px 14px; border:1px solid #e2e8f0; border-radius:10px;
            font-size:14px; outline:none; transition:border 0.2s; font-family:'Geist',sans-serif;
        }
        .filter-input:focus { border-color:#33553e; }
        .filter-btn {
            padding:10px 20px; background:#12341d; color:#fff; border:none;
            border-radius:10px; font-weight:700; cursor:pointer; font-family:'Montserrat',sans-serif; font-size:13px;
        }
        .btn-new {
            padding:10px 22px; background:#33553e; color:#fff; border:none;
            border-radius:10px; font-weight:800; cursor:pointer; font-family:'Montserrat',sans-serif;
            font-size:13px; display:flex; align-items:center; gap:8px; transition:all 0.2s;
        }
        .btn-new:hover { background:#12341d; transform:translateY(-1px); }

        /* Account cards */
        .accounts-list { display:flex; flex-direction:column; gap:14px; }
        .account-box {
            background:#fff; border-radius:14px; padding:20px 24px;
            border:1px solid #e2e8f0; display:flex; align-items:center;
            justify-content:space-between; gap:16px; transition:all 0.2s;
            box-shadow:0 2px 8px rgba(0,0,0,0.04);
        }
        .account-box:hover { transform:translateX(4px); box-shadow:0 6px 20px rgba(18,52,29,0.1); }
        .acc-info .sid   { font-weight:700; color:#12341d; font-size:0.92rem; }
        .acc-info .name  { font-size:1rem; font-weight:600; color:#0f172a; margin-top:2px; }
        .acc-info .meta  { font-size:0.8rem; color:#64748b; margin-top:2px; }
        .acc-status {
            display:inline-block; padding:4px 12px; border-radius:100px;
            font-size:0.72rem; font-weight:700; text-transform:uppercase;
        }
        .status-approved { background:#d1fae5; color:#065f46; }
        .status-pending  { background:#fef3c7; color:#92400e; }
        .status-rejected { background:#fee2e2; color:#991b1b; }
        .acc-actions { display:flex; gap:8px; flex-shrink:0; }
        .btn-view, .btn-del {
            width:36px; height:36px; border:none; border-radius:8px;
            cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:14px;
        }
        .btn-view { background:#d5e8db; color:#12341d; }
        .btn-del  { background:#fee2e2; color:#dc2626; }
        .btn-view:hover { background:#a4c1ad; }
        .btn-del:hover  { background:#dc2626; color:#fff; }

        /* Pagination */
        .pagination { display:flex; gap:8px; justify-content:center; margin-top:28px; flex-wrap:wrap; }
        .pg-btn {
            padding:8px 16px; border-radius:10px; border:1px solid #e2e8f0;
            background:#fff; cursor:pointer; font-weight:600; font-size:14px; transition:all 0.2s;
        }
        .pg-btn:hover, .pg-btn.active { background:#12341d; color:#fff; border-color:#12341d; }

        /* Modal base */
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.5); display:none; align-items:center; justify-content:center; z-index:9999; }
        .modal-overlay.open { display:flex; }
        .modal-box {
            background:#fff; border-radius:20px; padding:32px; width:420px; max-width:92vw;
            position:relative; animation:popIn 0.25s ease;
        }
        @keyframes popIn { from{transform:scale(0.9);opacity:0} to{transform:scale(1);opacity:1} }
        .modal-close { position:absolute; right:18px; top:14px; font-size:22px; cursor:pointer; color:#64748b; background:none; border:none; }
        .modal-box h3 { font-family:'Montserrat',sans-serif; color:#12341d; font-size:1.2rem; margin-bottom:18px; }

        /* Detail modal */
        .detail-row { padding:10px 0; border-bottom:1px solid #f1f5f9; font-size:0.9rem; }
        .detail-row:last-child { border-bottom:none; }
        .detail-label { font-weight:700; color:#64748b; font-size:0.78rem; text-transform:uppercase; }
        .detail-val   { color:#0f172a; margin-top:2px; }

        /* Create modal form */
        .form-row { margin-bottom:14px; }
        .form-label { font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.4px; display:block; margin-bottom:5px; }
        .form-input, .form-select {
            width:100%; padding:10px 13px; border:1px solid #e2e8f0; border-radius:10px;
            font-size:13px; font-family:'Geist',sans-serif; outline:none; transition:border 0.2s; box-sizing:border-box;
        }
        .form-input:focus, .form-select:focus { border-color:#33553e; }
        .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .btn-submit {
            width:100%; padding:13px; background:#12341d; color:#fff; border:none;
            border-radius:12px; font-family:'Montserrat',sans-serif; font-weight:800;
            font-size:14px; cursor:pointer; transition:all 0.2s; margin-top:6px;
        }
        .btn-submit:hover { background:#33553e; }
        .form-error { background:#fee2e2; color:#991b1b; border-radius:8px; padding:8px 12px; font-size:13px; font-weight:600; margin-bottom:12px; display:none; }
        .form-success { background:#d1fae5; color:#065f46; border-radius:8px; padding:8px 12px; font-size:13px; font-weight:600; margin-bottom:12px; display:none; }
        .required-star { color:#ef4444; }

        /* ── Mobile responsive ── */
        @media (max-width: 768px) {
            .main { margin-left:0; margin-top:7px; padding:20px; }
            h2 { margin-left: 50px; font-size:1.3rem; margin-bottom:16px; }

            /* Top bar: let everything wrap naturally at smaller sizes */
            .top-bar { gap:10px; }
            .filters { gap:8px; }
            /* Inputs shrink gracefully but don't force full-width */
            .filter-input { min-width:120px; }

            /* Account box: stack info above actions on very narrow screens */
            .account-box { flex-wrap:wrap; gap:12px; padding:16px; }
            .acc-info { flex:1; min-width:0; }
            .acc-actions { flex-shrink:0; }

            /* 2-col form grid in modal: 1 col on mobile */
            .form-grid-2 { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<?php // require_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="page-wrap">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="main">
        <h2>Voter Accounts</h2>

        <div class="top-bar">
            <form method="GET" action="" class="filters">
                <input class="filter-input" name="q" placeholder="Search name or ID..." value="<?= htmlspecialchars($search) ?>">
                <select class="filter-input" name="year">
                    <option value="">All Year Levels</option>
                    <?php foreach (['1st Year','2nd Year','3rd Year','4th Year'] as $yr): ?>
                        <option <?= $filterYear===$yr?'selected':'' ?>><?= $yr ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="filter-btn">Search</button>
                <a href="/admin/accounts.php" style="padding:10px 18px;color:#33553e;font-weight:600;text-decoration:none;font-size:14px;display:flex;align-items:center">Clear</a>
            </form>
            <button class="btn-new" onclick="openCreateModal()">
                <i class="fas fa-plus"></i> Add Student Account
            </button>
        </div>

        <div class="accounts-list" id="accountsList">
            <?php if (empty($accounts)): ?>
                <p style="color:#64748b;padding:30px 0;text-align:center">No accounts found.</p>
            <?php endif; ?>
            <?php foreach ($accounts as $a): ?>
            <div class="account-box" id="acc-<?= $a['id'] ?>">
                <div class="acc-info">
                    <div class="sid"><?= htmlspecialchars($a['student_id']) ?></div>
                    <div class="name"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></div>
                    <div class="meta"><?= htmlspecialchars($a['course']) ?> · <?= htmlspecialchars($a['year_level']) ?></div>
                </div>
                <div class="acc-actions">
                    <button class="btn-view" title="View details" onclick="viewAccount(<?= $a['id'] ?>)">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-del" title="Delete account" onclick="deleteAccount(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['first_name'])) ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <a href="?q=<?= urlencode($search) ?>&year=<?= urlencode($filterYear) ?>&page=<?= $p ?>">
                    <button class="pg-btn <?= $p===$page?'active':'' ?>"><?= $p ?></button>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <p style="color:#64748b;font-size:0.83rem;text-align:center;margin-top:14px">
            Showing <?= count($accounts) ?> of <?= $total ?> accounts
        </p>
    </main>
</div>

<!-- ── View Detail Modal ── -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('detailModal')">&times;</button>
        <h3>Voter Details</h3>
        <div id="modalContent"></div>
    </div>
</div>

<!-- ── Create Account Modal ── -->
<div class="modal-overlay" id="createModal">
    <div class="modal-box" style="width:480px">
        <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
        <h3>➕ Add Student Account</h3>
        <div class="form-error"  id="createError"></div>
        <div class="form-success" id="createSuccess"></div>

        <div class="form-grid-2">
            <div class="form-row">
                <label class="form-label">First Name <span class="required-star">*</span></label>
                <input class="form-input" id="cf_fname" type="text" placeholder="Juan">
            </div>
            <div class="form-row">
                <label class="form-label">Last Name <span class="required-star">*</span></label>
                <input class="form-input" id="cf_lname" type="text" placeholder="Dela Cruz">
            </div>
        </div>
        <div class="form-row">
            <label class="form-label">Middle Initial</label>
            <input class="form-input" id="cf_mi" type="text" placeholder="A." maxlength="5">
        </div>
        <div class="form-row">
            <label class="form-label">Student ID <span class="required-star">*</span></label>
            <input class="form-input" id="cf_sid" type="text" placeholder="M2024-00000">
        </div>
        <div class="form-grid-2">
            <div class="form-row">
                <label class="form-label">Course <span class="required-star">*</span></label>
                <select class="form-select" id="cf_course">
                    <option value="">Select course</option>
                    <option>BS Biology</option>
                    <option>BS Computer Science</option>
                    <option>BS Human Services</option>
                    <option>BS Psychology</option>
                    <option>BS Mathematics</option>
                </select>
            </div>
            <div class="form-row">
                <label class="form-label">Year Level</label>
                <select class="form-select" id="cf_year">
                    <option>1st Year</option>
                    <option>2nd Year</option>
                    <option>3rd Year</option>
                    <option>4th Year</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label">Password <span class="required-star">*</span></label>
            <input class="form-input" id="cf_pass" type="text" placeholder="Min. 6 characters">
            <span style="font-size:0.75rem;color:#94a3b8;margin-top:4px;display:block">
                This will be the student's login password. Share it with them directly.
            </span>
        </div>
        <button class="btn-submit" id="createSubmitBtn" onclick="submitCreate()">Create Account</button>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/shared.js"></script>
<script>
// ── View detail ──────────────────────────────────────────────
async function viewAccount(id) {
    const res  = await fetch(`/admin/accounts.php?detail=${id}`);
    const data = await res.json();
    if (data.error) return alert('Could not load details.');
    document.getElementById('modalContent').innerHTML = `
        <div class="detail-row"><div class="detail-label">Full Name</div><div class="detail-val">${data.first_name} ${data.last_name}</div></div>
        <div class="detail-row"><div class="detail-label">Student ID</div><div class="detail-val">${data.student_id}</div></div>
        <div class="detail-row"><div class="detail-label">Email</div><div class="detail-val">${data.email}</div></div>
        <div class="detail-row"><div class="detail-label">Course</div><div class="detail-val">${data.course}</div></div>
        <div class="detail-row"><div class="detail-label">Year Level</div><div class="detail-val">${data.year_level}</div></div>
        <div class="detail-row"><div class="detail-label">Has Voted</div><div class="detail-val">${data.has_voted ? 'Yes ✅' : 'Not yet'}</div></div>
        <div class="detail-row"><div class="detail-label">Registered</div><div class="detail-val">${data.created_at}</div></div>
    `;
    document.getElementById('detailModal').classList.add('open');
}

// ── Delete ───────────────────────────────────────────────────
async function deleteAccount(id, name) {
    if (!confirm(`Delete account for "${name}"? This cannot be undone.`)) return;
    const res    = await fetch('/admin/accounts.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({userId: id})
    });
    const result = await res.json();
    if (result.status === 'success') {
        const el = document.getElementById('acc-' + id);
        el.style.transition = 'opacity 0.3s'; el.style.opacity = '0';
        setTimeout(() => el.remove(), 300);
    } else { alert('Error: ' + result.message); }
}

// ── Create modal ─────────────────────────────────────────────
function openCreateModal() {
    document.getElementById('createError').style.display   = 'none';
    document.getElementById('createSuccess').style.display = 'none';
    ['cf_fname','cf_lname','cf_mi','cf_sid','cf_pass'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('cf_course').value = '';
    document.getElementById('cf_year').value   = '1st Year';
    document.getElementById('createModal').classList.add('open');
}

async function submitCreate() {
    const errEl = document.getElementById('createError');
    const sucEl = document.getElementById('createSuccess');
    errEl.style.display = sucEl.style.display = 'none';

    const body = new URLSearchParams({
        action:         'create',
        first_name:     document.getElementById('cf_fname').value.trim(),
        last_name:      document.getElementById('cf_lname').value.trim(),
        middle_initial: document.getElementById('cf_mi').value.trim(),
        student_id:     document.getElementById('cf_sid').value.trim(),
        course:         document.getElementById('cf_course').value,
        year_level:     document.getElementById('cf_year').value,
        password:       document.getElementById('cf_pass').value.trim(),
    });

    const btn = document.getElementById('createSubmitBtn');
    btn.disabled = true; btn.textContent = 'Creating…';

    try {
        const res    = await fetch('/admin/accounts.php', { method:'POST', body });
        const result = await res.json();

        if (result.status === 'success') {
            sucEl.textContent  = result.message;
            sucEl.style.display = 'block';
            // Prepend new card to list
            const u = result.user;
            const list = document.getElementById('accountsList');
            const box  = document.createElement('div');
            box.className = 'account-box'; box.id = 'acc-' + u.id;
            box.innerHTML = `
                <div class="acc-info">
                    <div class="sid">${u.student_id}</div>
                    <div class="name">${u.name}</div>
                    <div class="meta">${u.course} · ${u.year_level}</div>
                </div>
                
                <div class="acc-actions">
                    <button class="btn-view" onclick="viewAccount(${u.id})"><i class="fas fa-eye"></i></button>
                    <button class="btn-del"  onclick="deleteAccount(${u.id}, '${u.name.split(' ')[0]}')"><i class="fas fa-trash"></i></button>
                </div>`;
            list.insertBefore(box, list.firstChild);
            // Reset form after 1.5s then close
            setTimeout(() => { closeModal('createModal'); }, 1800);
        } else {
            errEl.textContent  = result.message;
            errEl.style.display = 'block';
        }
    } catch(e) {
        errEl.textContent = 'Network error. Please try again.';
        errEl.style.display = 'block';
    }
    btn.disabled = false; btn.textContent = 'Create Account';
}

function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
</script>
</body>
</html>