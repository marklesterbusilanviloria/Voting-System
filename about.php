<?php
// =============================================================
//  about.php
// =============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
$navActive = 'about';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About the Creators | iVOTE CS</title>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;700&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shared.css">
    <style>
        :root { --deep-green:#12341d; --tint-green:#d5e8db; --white-glass:rgba(255,255,255,0.3); }
        body {
            background: linear-gradient(135deg,#33553e 0%,#b8d1c0 100%);
            background-attachment:fixed; color:#fff; min-height:100vh; padding-top:80px;
        }
        .main-container { max-width:1400px; margin:0 auto; padding:60px 20px; text-align:center; }
        .badge {
            display:inline-block; padding:6px 16px;
            background:rgba(213,232,219,0.4); border-radius:100px; margin-bottom:15px;
            border:1px solid rgba(255,255,255,0.3); backdrop-filter:blur(5px);
        }
        .badge span {
            font-family:'Montserrat',sans-serif; color:var(--tint-green);
            font-weight:800; font-size:10px; letter-spacing:3px; text-transform:uppercase;
        }
        h2 {
            font-family:'Montserrat',sans-serif; font-weight:900; font-size:3rem;
            color:#fff; margin:0 0 10px; text-shadow:0 4px 10px rgba(18,52,29,0.3);
        }
        .subtitle { font-weight:500; color:var(--tint-green); max-width:700px; margin:0 auto 50px; font-size:1.1rem; line-height:1.6; }
        .dev-scroll-wrapper {
            display:flex; overflow-x:auto; gap:25px; padding:40px 15px;
            scroll-snap-type:x mandatory; scrollbar-width:thin;
            scrollbar-color:#6d9078 transparent; -webkit-overflow-scrolling:touch;
        }
        .dev-scroll-wrapper::-webkit-scrollbar { height:8px; }
        .dev-scroll-wrapper::-webkit-scrollbar-track { background:rgba(255,255,255,0.1); border-radius:10px; }
        .dev-scroll-wrapper::-webkit-scrollbar-thumb { background:#6d9078; border-radius:10px; }
        .dev-card {
            flex:0 0 320px; background:var(--white-glass); backdrop-filter:blur(20px);
            -webkit-backdrop-filter:blur(20px); border-radius:28px; padding:50px 30px;
            border:1px solid rgba(255,255,255,0.2); box-shadow:0 15px 35px rgba(18,52,29,0.2);
            display:flex; flex-direction:column; align-items:center;
            scroll-snap-align:center; transition:all 0.4s cubic-bezier(0.4,0,0.2,1);
        }
        .dev-card:hover { transform:translateY(-12px); background:rgba(255,255,255,0.4); border-color:var(--tint-green); }
        .img-placeholder {
            width:160px; height:160px; background:rgba(255,255,255,0.2);
            border-radius:50%; margin-bottom:35px; border:4px solid var(--tint-green);
            display:flex; align-items:center; justify-content:center;
            color:var(--tint-green); font-weight:700; font-size:0.9rem; letter-spacing:1px;
            box-shadow:0 8px 20px rgba(0,0,0,0.1); overflow:hidden;
        }
        .img-placeholder img { width:100%; height:100%; object-fit:cover; }
        .name { font-family:'Montserrat',sans-serif; font-weight:800; font-size: 100%; color:#fff; margin:0 0 12px; }
        .contribution {
            font-family:'Geist',sans-serif; font-weight:500; font-size:13px;
            color:var(--deep-green); background:var(--tint-green);
            padding:10px 20px; border-radius:14px; text-transform:uppercase;
            letter-spacing:1px; box-shadow:0 4px 10px rgba(0,0,0,0.1);
        }
        .scroll-hint { margin-top:30px; color:var(--tint-green); font-weight:700; font-size:0.85rem; text-transform:uppercase; letter-spacing:3px; opacity:0.8; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="main-container">
    <div class="badge"><span>Meet the Team</span></div>
    <h2>About the Creators</h2>
    <p class="subtitle">The iVOTE CS system was built by a dedicated group of BS Computer Science students committed to making the COS election process fair, accessible, and modern.</p>

    <div class="dev-scroll-wrapper">
        <?php
        $team = [
            ['name'=>'Alwin Publico',  'role'=>'Frontend Developer'],
            ['name'=>'Barbie Ann San Buenaventura',  'role'=>'Frontend Developer'],
            ['name'=>'Carmelo Rivera',  'role'=>'Frontend Developer'],
            ['name'=>'Christian Adam Rico',  'role'=>'Frontend Developer'],
            ['name'=>'Danilo Quirido Jr.',  'role'=>'Frontend Developer'],
            ['name'=>'Francia Mae Mercado',  'role'=>'Frontend Developer'],
            ['name'=>'Miguel Adrian Sajulga',  'role'=>'Frontend Developer'],
            ['name'=>'Albert Gerome San Juan',  'role'=>'Frontend Developer'],
            ['name'=>'Mark Lester Viloria',  'role'=>'Frontend Developer'],
            ['name'=>'Ian Kurt Valencia',  'role'=>'UI/UX Designer & Frontend Developer'],
            ['name'=>'Cirelle Sadia',  'role'=>'Data Accumulator'],
            ['name'=>'Stefanie Sorell Sesuca',  'role'=>'Data Accumulator'],
            ['name'=>'Joana Zapanta',  'role'=>'Data Accumulator'],
            ['name'=>'Rian Niño Suñiga',  'role'=>'Backend Developer'],
            ['name'=>'John Edward Villadiego',  'role'=>'Backend Developer'],
        ];
        foreach ($team as $member):
            $initials = implode('', array_map(fn($p) => strtoupper($p[0]), explode(' ', $member['name'])));
        ?>
        <div class="dev-card">
            <div class="img-placeholder"><?= htmlspecialchars($initials) ?></div>
            <h3 class="name"><?= htmlspecialchars($member['name']) ?></h3>
            <p class="contribution"><?= htmlspecialchars($member['role']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="scroll-hint">&larr; Swipe to explore the team &rarr;</p>
</div>

<?php if (!isLoggedIn()): ?>
<div class="modal-overlay" id="authModal">
    <div class="modal-card">
        <h2>Access iVOTE CS</h2>
        <p>Choose how you want to proceed</p>
        <div class="modal-btns">
            <a href="/login.php" class="m-btn m-login">Log In</a>
            <a href="/login.php" class="m-btn m-register">Register New Account</a>
        </div>
        <span class="close-link" onclick="closeAuthModal()">Close</span>
    </div>
</div>
<?php endif; ?>

<script src="<?= BASE_URL ?>assets/js/shared.js"></script>
</body>
</html>
<?php require_once __DIR__ . '/includes/footer.php'; ?>