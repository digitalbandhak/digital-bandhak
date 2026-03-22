<?php
require_once 'includes/config.php';
$siteName = getSiteName($pdo);
$logoUrl  = getSiteLogo($pdo);

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['accept_terms'])) {
    $_SESSION['terms_accepted'] = true;
    if (isLoggedIn('owner')) { header('Location:shop/dashboard.php'); exit; }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Terms & Conditions — <?=htmlspecialchars($siteName)?></title>
  <link rel="stylesheet" href="css/style.css"/>
  <style>
    .terms-body { max-height:450px; overflow-y:auto; padding:16px 18px; border:1px solid var(--border); border-radius:var(--radius-lg); background:var(--surface); line-height:1.8; font-size:13px; }
    .terms-body h4 { font-size:14px; font-weight:700; color:var(--gold-dark); margin:18px 0 5px; padding-bottom:4px; border-bottom:1px solid var(--border); }
    [data-theme="dark"] .terms-body h4 { color:var(--gold); }
    .terms-body h4:first-child { margin-top:0; }
    .terms-body p, .terms-body li { color:var(--text2); margin-bottom:5px; }
    .terms-body ol, .terms-body ul { padding-left:20px; }
    .terms-body .warning-box { background:var(--danger-bg); border-left:3px solid var(--danger); padding:10px 14px; border-radius:0 var(--radius) var(--radius) 0; margin:10px 0; }
    .terms-body .warning-box p { color:var(--danger); font-weight:600; margin:0; }
    @media print { .no-print { display:none; } }
  </style>
</head>
<body style="background:var(--bg)">

<!-- Navbar -->
<nav class="navbar no-print">
  <a class="navbar-brand" href="index.php">
    <?php if ($logoUrl): ?><img src="<?=htmlspecialchars($logoUrl)?>" alt="Logo" style="height:34px;width:34px;object-fit:contain;border-radius:8px"/><?php else: ?><span style="font-size:20px">🏛️</span><?php endif; ?>
    <?=htmlspecialchars($siteName)?>
  </a>
  <div class="navbar-right">
    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()">🌙</button>
    <a href="index.php" class="btn btn-outline btn-sm">← Login</a>
    <button class="btn btn-outline btn-sm" onclick="window.print()">🖨 Print</button>
  </div>
</nav>

<div class="main-content container" style="max-width:800px;padding-top:24px">
  <div class="card">
    <div class="card-title" style="font-size:20px">📜 Terms & Conditions</div>
    <p class="text-muted text-small mb-16">Last updated: <?=date('d F Y')?> · Version 2.0 · <?=htmlspecialchars($siteName)?></p>

    <div class="terms-body">
      <h4>1. Sweekarti (Acceptance of Terms)</h4>
      <p>Digital Bandhak platform par register karke ya use karke aap in Terms & Conditions se poori tarah sahmat hote hain. Agar aap sahmat nahi hain, to platform ka upyog na karein.</p>

      <h4>2. Shop Owner Ki Zimmedariyan</h4>
      <p>Shop owner poori tarah zimmedar hain:</p>
      <ol>
        <li>Customer data (naam, mobile, Aadhaar) ki accuracy — galat data dalna prohibited hai</li>
        <li>Loan details, interest rate aur payment records ki sahi entry</li>
        <li>Staff ke sabhi actions ka zimma owner par hoga</li>
        <li>Local pawnbroking laws, RBI guidelines aur state regulations ka palana</li>
      </ol>

      <h4>3. ⚠ Data Backup — Aapki Zimmedari (IMPORTANT)</h4>
      <div class="warning-box">
        <p>🔴 CRITICAL NOTICE: Digital Bandhak ek software platform hai. Website crash, server failure, ya kisi bhi technical problem ki sthiti mein data loss ki poori zimmedari aapki hogi.</p>
      </div>
      <ol>
        <li><strong>Apni backup notebook ya register zaroor rakhen</strong> — sabhi customers ka naam, mobile, Aadhaar (partial), item details, loan amount, dates physically note karein</li>
        <li>Monthly PDF export karein aur safely store karein</li>
        <li>Website down hone ya data loss hone par <strong>Digital Bandhak koi compensation nahi dega</strong></li>
        <li>Receipts ki physical copy bhi rakhein</li>
        <li>Dual record system follow karein — digital + manual</li>
      </ol>

      <h4>4. Data Privacy aur Aadhaar Security</h4>
      <ol>
        <li>Aadhaar sirf masked format (XXXX XXXX NNNN) mein store hoga</li>
        <li>Customer identity data share ya misuse NAHI karein</li>
        <li>Koi bhi data breach hone par turant admin ko inform karein</li>
        <li>Digital Bandhak UIDAI ka authorized agent nahi hai</li>
      </ol>

      <h4>5. ⚠ Legal Pawnbroking Notice (KANUNI SUCHNA)</h4>
      <div class="warning-box">
        <p>🔴 IMPORTANT: Pawnbroking ek regulated profession hai. Bina license ke pawnbroking illegal ho sakta hai.</p>
      </div>
      <ol>
        <li><strong>Apne state ka Pawnbroker License lena mandatory hai</strong> — bina license ke operate karna criminal offense ho sakta hai</li>
        <li><strong>Illegal items ka bandhak BILKUL NA RAKHEN</strong> — chori ka samaan, duplicate documents, weapon etc. rakhne par aapko serious legal action ho sakta hai including FIR aur jail</li>
        <li>Har transaction ki legal receipt zaroor dein</li>
        <li>Interest rate state government ke guidelines se zyada nahi honi chahiye</li>
        <li>GST registration (agar applicable) zaroori hai</li>
        <li>Anti-money laundering laws ka palana karein — suspicious transactions report karein</li>
        <li>Customer identity verification properly karein — fake Aadhaar/ID se protect rahein</li>
      </ol>

      <h4>6. Subscription aur Bhugtan</h4>
      <ol>
        <li>Subscription fees non-refundable hain (trial period ke baad)</li>
        <li>Subscription expire hone par platform access suspend ho jayega</li>
        <li>Renewal timely karein — data safe rehta hai suspended period mein bhi</li>
      </ol>

      <h4>7. Staff Access</h4>
      <ol>
        <li>Staff credentials ka zimma owner ka hai</li>
        <li>Employee leave karne par turant password change ya staff deactivate karein</li>
        <li>Staff actions audit log mein record honge</li>
      </ol>

      <h4>8. Prohibited Activities</h4>
      <ul>
        <li>Fraudulent / fake customer entries banana</li>
        <li>System hacking ya unauthorized access ki koshish</li>
        <li>Platform ka use illegal activities ke liye</li>
        <li>Stolen/illegal items ka bandhak rakhna</li>
        <li>Owner password share karna</li>
      </ul>

      <h4>9. Dayitva Seemit (Limitation of Liability)</h4>
      <p>Digital Bandhak:</p>
      <ol>
        <li>Shop owner aur customer ke beech disputes ke liye zimmedar NAHI hai</li>
        <li>Data loss, server downtime ke liye compensation nahi dega</li>
        <li>Owner dwara ki gayi galat entries ke liye zimmedar nahi hai</li>
        <li>Legal disputes mein koi party ban nahi banega</li>
      </ol>

      <h4>10. Governing Law</h4>
      <p>Yeh terms Indian laws ke antargat governed hain. Koi bhi vivaad Bihar/India ki courts mein resolve hogi.</p>

      <h4>11. Updates to Terms</h4>
      <p>Terms kabhi bhi update ho sakti hain. 30 din pehle notification diya jayega. Continued use = acceptance.</p>

      <h4>12. Contact</h4>
      <p>Support: Platform ke Private Chat feature se admin se baat karein ya email karein.</p>
    </div>

    <!-- Accept -->
    <form method="POST" style="margin-top:18px">
      <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:16px">
        <input type="checkbox" id="acceptCb" style="width:18px;height:18px;cursor:pointer;margin-top:2px;flex-shrink:0" required/>
        <label for="acceptCb" style="font-size:14px;cursor:pointer;color:var(--text)">
          Maine <strong><?=htmlspecialchars($siteName)?></strong> ke Terms & Conditions, data backup responsibility, aur legal pawnbroking requirements ko <strong>padh liya hai</strong> aur main inse <strong>poori tarah sahmat hoon</strong>.
        </label>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="submit" name="accept_terms" value="1" class="btn btn-gold btn-lg">✔ Accept & Continue</button>
        <button type="button" class="btn btn-outline" onclick="window.print()">🖨 Print / Download</button>
        <a href="index.php" class="btn btn-outline">← Back</a>
      </div>
    </form>
  </div>
</div>

<script src="js/app.js"></script>
</body>
</html>
