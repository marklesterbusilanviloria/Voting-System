<?php
// =============================================================
//  index.php — Public landing page  (Guest view)
// =============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Logged-in users get bounced to their dashboard
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? '/admin/dashboard.php' : '/student/dashboard.php'));
    exit;
}

$navActive = 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iVOTE CS — College of Science Online Voting System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shared.css">
    <style>
        body { background-color: #f8fafc; padding-top: 80px; }

        /* Hero */
        .hero {
            min-height: calc(100vh - 80px);
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #12341d 0%, #33553e 50%, #a4c1ad 100%);
            text-align: center; padding: 60px 20px;
            position: relative; overflow: hidden;
        }
        .hero::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(circle at 30% 50%, rgba(255,255,255,0.07) 0%, transparent 60%),
                        radial-gradient(circle at 70% 80%, rgba(255,255,255,0.05) 0%, transparent 50%);
        }
        .hero-content { position: relative; z-index: 1; max-width: 700px; }
        .hero-badge {
            display: inline-block; padding: 6px 18px;
            background: rgba(255,255,255,0.15); border-radius: 100px;
            border: 1px solid rgba(255,255,255,0.25); margin-bottom: 24px;
        }
        .hero-badge span {
            font-family: 'Montserrat', sans-serif; color: #d5e8db;
            font-weight: 800; font-size: 10px; letter-spacing: 3px; text-transform: uppercase;
        }
        .hero h1 {
            font-family: 'Montserrat', sans-serif; font-weight: 900;
            font-size: clamp(2.2rem, 5vw, 3.5rem); color: #fff; margin-bottom: 20px;
            text-shadow: 0 4px 12px rgba(0,0,0,0.2); line-height: 1.15;
        }
        .hero p {
            font-size: 1.1rem; color: rgba(255,255,255,0.85); margin-bottom: 40px; line-height: 1.7;
        }
        .hero-btns { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
        .btn-hero-primary {
            display: inline-flex; align-items: center; justify-content: center;
            font-family: 'Montserrat', sans-serif; background: #fff; color: #12341d;
            padding: 16px 36px; border-radius: 50px; font-weight: 800; font-size: 15px;
            border: none; cursor: pointer; box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            transition: all 0.3s ease; text-decoration: none;
        }
        .btn-hero-primary:hover { background: #d5e8db; transform: translateY(-3px); }
        .btn-hero-secondary {
            display: inline-flex; align-items: center; justify-content: center;
            font-family: 'Montserrat', sans-serif;
            background: rgba(255,255,255,0.15); color: #fff;
            padding: 16px 36px; border-radius: 50px; font-weight: 700; font-size: 15px;
            border: 1px solid rgba(255,255,255,0.3); cursor: pointer;
            transition: all 0.3s ease; text-decoration: none;
        }
        .btn-hero-secondary:hover { background: rgba(255,255,255,0.25); transform: translateY(-3px); }

        /* Steps section */
        .steps-section { padding: 80px 20px; background: #fff; }
        .section-header { text-align: center; margin-bottom: 50px; }
        .section-header h2 {
            font-family: 'Montserrat', sans-serif; font-weight: 900;
            font-size: 2rem; color: #12341d; margin-bottom: 12px;
        }
        .section-header p { color: #33553e; font-size: 1rem; max-width: 500px; margin: 0 auto; }
        .steps-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 30px; max-width: 1000px; margin: 0 auto;
        }
        .step-card {
            background: #f8fafc; border-radius: 20px; padding: 30px;
            border: 1px solid #e2e8f0; text-align: center;
            transition: all 0.3s ease;
        }
        .step-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(18,52,29,0.1); }
        .step-number {
            width: 52px; height: 52px; border-radius: 50%;
            background: linear-gradient(135deg, #12341d, #33553e);
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-family: 'Montserrat', sans-serif; font-weight: 900; font-size: 1.2rem;
            margin: 0 auto 20px;
        }
        .step-card h3 {
            font-family: 'Montserrat', sans-serif; color: #12341d;
            font-weight: 800; margin-bottom: 10px;
        }
        .step-card p { color: #33553e; font-size: 0.9rem; line-height: 1.6; }

        .info-grid {
            display: grid;
            grid-template-columns: 2.2fr 2.8fr;
            gap: 20px;
            margin-top: 5%;
            margin-left: 15%;
        }

        .unq {
            background: #f8fafc;
            max-width: 500px;
            border-radius: 20px;
            padding: 30px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .unq h2 { 
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 40px; 
            position: relative;
            padding-bottom: 10px;
            font-family: var(--heading-font);
            color: var(--deep-green);
        }

        .unq h2::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 60px;
            height: 4px;
            background: var(--deep-green);
        }

        .unq li{
            font-family: var(--body-font);
            margin-left: 20px;
            color: var(--deep-green);
        }

        @media (max-width: 768px) {
            h1 { font-size: 2.5rem; }
            .nav-links { display: none; }
            .info-grid { grid-template-columns: 1fr; }
            .info-grid { margin-left: 0;}
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<section class="hero">
    <div class="hero-content">
        <div class="hero-badge"><span>COSA Election 2026-2027</span></div>
        <h1>Your Vote.<br>Your Voice. Your Future.</h1>
        <p>The official secure online voting platform for the COS Student Organization. Cast your ballot from anywhere — fast, transparent, and tamper-proof.</p>
        <div class="hero-btns">
            <a href="/login.php" class="btn-hero-primary">Log In to Vote</a>
            <a href="/about.php" class="btn-hero-secondary">Meet the Team</a>
        </div>
    </div>
</section>

<section class="steps-section">
    <div class="section-header">
        <h2>How to Vote</h2>
        <p>Follow these simple steps to cast your ballot securely.</p>
    </div>
    <div class="steps-grid">
        <div class="step-card">
            <div class="step-number">1</div>
            <h3>Get Your Account</h3>
            <p>Your official voting credentials will be securely provided to you by the Super Admin.</p>
        </div>
        <div class="step-card">
            <div class="step-number">2</div>
            <h3>Log In</h3>
            <p>Access the portal using your official Student ID and password during the active election period.</p>
        </div>
        <div class="step-card">
            <div class="step-number">3</div>
            <h3>Cast Your Vote</h3>
            <p>Review the candidates running for each position, make your selections, and submit your ballot.</p>
        </div>
        <div class="step-card">
            <div class="step-number">4</div>
            <h3>View Results</h3>
            <p>Watch the live, transparent results on the dashboard as votes are counted in real-time.</p>
        </div>
    </div>

    <section class="info-grid">
        <div class = "unq">
            <h2>Voter Eligibility</h2>
            <ul>
                <li>Currently enrolled this semester</li>
                <li>Have a registered account on the COSA Voting System</li>
            </ul>
            <br><br>
        </div>
        <div class = "unq">
            <h2>Voting Rules</h2>
            <ul>
                <li>One vote per position only</li>
                <li>No vote buying or coercion</li>
                <li>Submitted ballots are final</li>
            </ul>
        </div>
    </section>
</section>

    
 


<!-- GUEST AUTH MODAL
<div class="modal-overlay" id="authModal">
    <div class="modal-card">
        <h2>Access iVOTE CS</h2>
        <p>Choose how you want to proceed</p>
        <div class="modal-btns">
            <a href="/login.php" class="m-btn m-login">Log In</a>
            <a href="/login.php?view=register" class="m-btn m-register" onclick="sessionStorage.setItem('showRegister','1')">Register New Account</a>
        </div>
        <span class="close-link" onclick="closeAuthModal()">Close</span>
    </div>
</div> -->

<script src="<?= BASE_URL ?>assets/js/shared.js"></script>
</body>
</html>

<?php require_once __DIR__ . '/includes/footer.php'; ?>