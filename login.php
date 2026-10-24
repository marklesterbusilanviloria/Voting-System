<?php
// =============================================================
//  login.php — Login
// =============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/rate_limiter.php';

redirectIfLoggedIn();

$rateLimiter = new RateLimiter(getDB());

$navActive = 'login';
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        // ✅ Define $sid FIRST before passing to check()
        $sid  = trim($_POST['student_id'] ?? '');
        $pass = $_POST['password'] ?? '';

        // ✅ check() now has $sid available, and is inside the right block
        $rateLimiter->check('login', $sid);

        if ($sid === '' || $pass === '') {
            $error = 'Please fill in all fields.';
            // ✅ Empty submissions count as a failure too
            $rateLimiter->recordFailure('login', $sid);
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE student_id = ? LIMIT 1");
            $stmt->execute([$sid]);
            $user = $stmt->fetch();

            if ($user && password_verify($pass, $user['password_hash'])) {
                // ✅ Successful login — reset the counter
                $rateLimiter->recordSuccess('login', $sid);
                loginUser($user);
                $dest = ($user['role'] === 'admin') ? '/admin/dashboard.php' : '/student/dashboard.php';
                header("Location: $dest");
                exit;
            } else {
                // ✅ Failed login — increment the counter
                $rateLimiter->recordFailure('login', $sid);
                $error = 'Invalid Student ID or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COS | Student Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&family=Geist:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shared.css">
    <style>
        :root {
            --deep-green: #12341d; --mid-green: #33553e;
            --soft-green: #6d9078; --pale-green: #a4c1ad; --tint-green: #d5e8db;
        }
        body {
            margin:0; padding:0; font-family:'Geist',sans-serif;
            background: linear-gradient(135deg, #33553e 0%, #b8d1c0 100%);
            display:flex; justify-content:center; align-items:center;
            min-height:100vh; overflow-x:hidden;
            /* Added padding so the card doesn't hit the navbar on small screens */
            padding-top: 100px; 
            box-sizing: border-box;
        }
        .background-blur {
            position:fixed; inset:0; background:inherit;
            filter:blur(100px); z-index:-1;
        }
        #container-card {
            width:100%; max-width:450px;
            background:rgba(255,255,255,0.3);
            backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
            border:1px solid rgba(255,255,255,0.2);
            border-radius:28px;
            box-shadow:0 15px 35px -5px rgba(18,52,29,0.2),0 0 15px rgba(255,255,255,0.1);
            overflow:hidden; transition:all 0.5s cubic-bezier(0.4,0,0.2,1);
            display:flex; flex-direction:column;
            margin-bottom: 40px; /* Space at bottom */
        }
        /* ... (rest of your existing card CSS) ... */
        .top-bar { height:6px; background:linear-gradient(to right,var(--pale-green),var(--tint-green),var(--pale-green)); opacity:0.8;}
        .card-logo-container { padding-top:30px; display:flex; justify-content:center; }
        .logo-image {
            width:80px; height:80px; border-radius:50%; object-fit:cover;
            border:3px solid var(--tint-green); background:rgba(255,255,255,0.7);
            filter:drop-shadow(0 4px 8px rgba(18,52,29,0.1));
        }
        .header-section { padding:15px 40px; text-align:center; }
        .badge {
            display:inline-block; padding:4px 12px;
            background:rgba(213,232,219,0.6); border-radius:100px;
            margin-bottom:8px; border:1px solid rgba(255,255,255,0.3);
        }
        .badge span {
            font-family:'Montserrat',sans-serif; color:#0c0c0c;
            font-weight:800; font-size:9px; letter-spacing:2px; text-transform:uppercase;
        }
        h1 {
            font-family:'Montserrat',sans-serif; color:#fff; margin:0;
            font-size:24px; font-weight:900; text-shadow:0 2px 4px rgba(18,52,29,0.4);
        }
        .form-container { padding:0 40px 30px; }
        .field-group { margin-bottom:15px; }
        label {
            font-family:'Montserrat',sans-serif; font-size:10px; font-weight:800;
            color:#12341d; margin-bottom:6px; display:block;
            text-transform:uppercase;
        }
        input {
            font-family:'Geist',sans-serif; width:100%; padding:12px 15px;
            background:rgba(255,255,255,0.2); border:2px solid rgba(255,255,255,0.15);
            border-radius:12px; font-size:14px; outline:none; box-sizing:border-box;
            transition:all 0.3s ease; color:#12341d;
        }
        input:focus { border-color:var(--mid-green); background:rgba(255,255,255,0.4); }
        .btn-primary {
            font-family:'Montserrat',sans-serif; width:100%; padding:15px;
            background:var(--deep-green); color:#fff; border:none;
            border-radius:12px; font-weight:800; font-size:13px; cursor:pointer;
            text-transform:uppercase; letter-spacing:1.5px; margin-top:10px;
            transition:0.3s;
        }
        .btn-primary:hover { background:var(--mid-green); transform:translateY(-2px); }
        .alert { padding:10px 14px; border-radius:10px; margin-bottom:14px; font-size:13px; font-weight:600; background:rgba(239,68,68,0.2); color:#721c24; border:1px solid #f5c6cb; }
         .secure-footer {
            background:rgba(213,232,219,0.1); padding:12px; text-align:center;
            border-top:1px solid rgba(255,255,255,0.1);
        }
        .secure-text { font-size:9px; color:#515154; font-weight:700; letter-spacing:1px; text-transform:uppercase; }
        .alert { padding:10px 14px; border-radius:10px; margin-bottom:14px; font-size:13px; font-weight:600; }
        .alert-error   { background:rgba(239,68,68,0.25); color:#fff; border:1px solid rgba(239,68,68,0.4); }

        footer {
            background: var(--deep-forest);
            color: var(--pale-leaf);
            text-align: center;
            padding: 60px 20px;
        }
        
        footer {
            background: #12341d;
            color: #699878;
            text-align: center;
            padding: 60px 20px;
        }
        
        @media (max-width: 768px) {
            .main { margin-left:0; padding:20px; }
            #container-card { margin:20px; }
}
    
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="background-blur"></div>

<div id="container-card">
    <div class="top-bar"></div>
    <div class="card-logo-container">
        <img src="/assets/img/icons/logo.png" alt="COS Logo" class="logo-image">
    </div>
    
    <div class="header-section">
        <div class="badge"><span>COLLEGE OF SCIENCE</span></div>
        <h1 id="form-title">Login</h1>
    </div>

    <div class="form-container">
        <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="field-group">
                <label>Student ID</label>
                <input type="text" name="student_id" placeholder="M2023-00000" required>
            </div>
            <div class="field-group" style="margin-bottom:25px">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-primary">Verify &amp; Enter</button>
        </form>
    </div>
    <div class="secure-footer">
        <p class="secure-text">🔒 Secure 256-bit Encrypted Portal</p>
    </div>
</div>


<script src="<?= BASE_URL ?>assets/js/shared.js"></script>

</body>
</html>

<!-- <?php require_once __DIR__ . '/includes/footer.php'; ?> -->