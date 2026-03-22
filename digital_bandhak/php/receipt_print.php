<?php
require_once '../includes/config.php';
if (!isLoggedIn()) { header('Location:../index.php'); exit; }

$id  = intval($_GET['id'] ?? 0);
$dup = !empty($_GET['dup']);

$stmt = $pdo->prepare("
    SELECT pe.*, c.full_name, c.mobile, c.address, c.aadhaar_masked, c.father_spouse,
           s.shop_name, s.owner_name, s.owner_mobile, s.city, s.state, s.gst_number,
           s.license_number, s.address as shop_address
    FROM pawn_entries pe
    JOIN customers c ON pe.customer_id = c.id
    JOIN shops s ON pe.shop_id = s.shop_id
    WHERE pe.id = ?
");
$stmt->execute([$id]); $p = $stmt->fetch();
if (!$p) die('<p style="padding:20px;font-family:sans-serif;color:red">Receipt not found (ID: '.$id.')</p>');

// Payment history
$pays = $pdo->prepare("SELECT * FROM payments WHERE pawn_id=? ORDER BY payment_date ASC");
$pays->execute([$id]); $payments = $pays->fetchAll();

$totalPaid = array_sum(array_column($payments, 'amount'));
$months    = max(1, $p['duration_months'] ?? 1);
$interest  = round($p['loan_amount'] * ($p['interest_rate']/100) * $months, 2);
$totalDue  = $p['loan_amount'] + $interest;

$logoUrl  = getSiteLogo($pdo, '../');
$siteName = getSiteName($pdo);

// Item-specific extra fields
$itemType = strtolower($p['item_type'] ?? '');
$isGold   = strpos($itemType,'gold')!==false || strpos($itemType,'silver')!==false || strpos($itemType,'jewel')!==false || strpos($itemType,'diamond')!==false;
$isMobile = strpos($itemType,'mobile')!==false || strpos($itemType,'phone')!==false;
$isVehicle= strpos($itemType,'wheeler')!==false || strpos($itemType,'bike')!==false || strpos($itemType,'car')!==false || strpos($itemType,'vehicle')!==false;
$isLand   = strpos($itemType,'land')!==false || strpos($itemType,'property')!==false || strpos($itemType,'document')!==false;
$isElec   = strpos($itemType,'electronic')!==false || strpos($itemType,'laptop')!==false || strpos($itemType,'tv')!==false;
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1"/>
  <title>Receipt — <?=htmlspecialchars($p['bandhak_id'])?></title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Segoe UI',Arial,sans-serif;font-size:12px;background:#f5f5f5;color:#222;}
    .page{max-width:400px;margin:0 auto;background:#fff;box-shadow:0 2px 16px rgba(0,0,0,.12);}

    /* Header */
    .rh{background:linear-gradient(135deg,#7B4800,#B8760A);color:#fff;padding:14px 16px;text-align:center;}
    .rh-logo{width:52px;height:52px;object-fit:contain;border-radius:10px;background:#fff;padding:4px;margin-bottom:6px;}
    .rh-logo-fallback{width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,.2);display:inline-flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:6px;}
    .rh-name{font-size:16px;font-weight:700;letter-spacing:.02em;}
    .rh-sub{font-size:10px;opacity:.85;margin-top:2px;}
    .rh-ids{display:flex;justify-content:space-between;margin-top:8px;font-size:10px;opacity:.9;background:rgba(0,0,0,.15);border-radius:6px;padding:5px 10px;}

    /* Badge */
    .receipt-status{display:inline-block;padding:3px 10px;border-radius:12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;}
    .status-active{background:#E8F5E9;color:#2E7D32;}
    .status-closed{background:#E3F2FD;color:#1565C0;}

    /* Section */
    .sec{padding:10px 14px;border-bottom:1px dashed #e0d5c5;}
    .sec:last-child{border-bottom:none;}
    .sec-title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#7B4800;margin-bottom:7px;padding-bottom:4px;border-bottom:1px solid #f0e8d8;}
    .row{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:4px;gap:6px;}
    .row-key{color:#666;font-size:11px;flex-shrink:0;}
    .row-val{font-weight:600;font-size:12px;text-align:right;word-break:break-word;}

    /* Money boxes */
    .money-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin:6px 0;}
    .money-box{background:#f8f4ee;border-radius:8px;padding:7px 6px;text-align:center;border:1px solid #e0d5c5;}
    .money-box.highlight{background:#FFF3E0;border-color:#B8760A;}
    .money-box.danger{background:#FFF0F0;border-color:#D32F2F;}
    .money-label{font-size:9px;color:#888;text-transform:uppercase;}
    .money-val{font-size:14px;font-weight:700;color:#333;margin-top:2px;}
    .money-box.highlight .money-val{color:#B8760A;}
    .money-box.danger .money-val{color:#D32F2F;}
    .money-box.paid .money-val{color:#2E7D32;}

    /* Item photo */
    .item-photo-wrap{text-align:center;padding:8px 0;}
    .item-photo-wrap img{max-width:120px;max-height:100px;border-radius:8px;border:1px solid #e0d5c5;}

    /* Payment history */
    .pay-table{width:100%;border-collapse:collapse;font-size:11px;}
    .pay-table th{background:#f8f4ee;color:#7B4800;padding:5px 8px;text-align:left;font-size:10px;font-weight:700;}
    .pay-table td{padding:5px 8px;border-bottom:1px solid #f0e8d8;}
    .pay-table tr:last-child td{border-bottom:none;}

    /* Footer */
    .rfooter{background:#f8f4ee;padding:12px 14px;font-size:10px;color:#666;text-align:center;}
    .rfooter strong{color:#7B4800;}
    .sig-line{border-top:1px solid #ccc;margin-top:10px;padding-top:6px;display:flex;justify-content:space-between;}

    /* Print button */
    .print-bar{display:flex;gap:8px;padding:12px 14px;background:#f8f4ee;border-top:2px solid #e0d5c5;}
    .print-bar button{flex:1;padding:10px;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;}
    .btn-print{background:#B8760A;color:#fff;}
    .btn-close{background:#eee;color:#333;}
    @media print{
      .print-bar{display:none!important;}
      body{background:#fff;}
      .page{box-shadow:none;max-width:100%;}
    }
    @media(max-width:420px){
      .money-val{font-size:12px;}
      .money-label{font-size:8px;}
    }
  </style>
</head>
<body>
<div class="page">
  <!-- HEADER -->
  <div class="rh">
    <?php if ($logoUrl): ?>
      <img src="<?=htmlspecialchars($logoUrl)?>" class="rh-logo" alt="Logo"/>
    <?php else: ?>
      <div class="rh-logo-fallback">🏛️</div>
    <?php endif; ?>
    <div class="rh-name"><?=htmlspecialchars($p['shop_name'])?></div>
    <div class="rh-sub">
      <?php if (!empty($p['city'])): ?><?=htmlspecialchars($p['city'])?><?php endif; ?>
      <?php if (!empty($p['owner_mobile'])): ?> · <?=htmlspecialchars($p['owner_mobile'])?><?php endif; ?>
      <?php if (!empty($p['gst_number'])): ?><br/>GST: <?=htmlspecialchars($p['gst_number'])?><?php endif; ?>
      <?php if (!empty($p['license_number'])): ?> · Lic: <?=htmlspecialchars($p['license_number'])?><?php endif; ?>
    </div>
    <div class="rh-ids">
      <span>🪪 <?=htmlspecialchars($p['bandhak_id'])?></span>
      <span class="receipt-status status-<?=$p['status']?>"><?=ucfirst($p['status'])?></span>
      <span>📄 <?=htmlspecialchars($p['receipt_number']??'—')?></span>
    </div>
  </div>

  <!-- CUSTOMER DETAILS -->
  <div class="sec">
    <div class="sec-title">👤 Customer Details</div>
    <div class="row"><span class="row-key">Full Name</span><span class="row-val"><?=htmlspecialchars($p['full_name'])?></span></div>
    <?php if (!empty($p['father_spouse'])): ?>
    <div class="row"><span class="row-key">Father/Spouse</span><span class="row-val"><?=htmlspecialchars($p['father_spouse'])?></span></div>
    <?php endif; ?>
    <div class="row"><span class="row-key">Mobile</span><span class="row-val">+91 <?=htmlspecialchars($p['mobile'])?></span></div>
    <?php if (!empty($p['aadhaar_masked'])): ?>
    <div class="row"><span class="row-key">Aadhaar</span><span class="row-val"><?=htmlspecialchars($p['aadhaar_masked'])?></span></div>
    <?php endif; ?>
    <?php if (!empty($p['address'])): ?>
    <div class="row"><span class="row-key">Address</span><span class="row-val"><?=htmlspecialchars($p['address'])?></span></div>
    <?php endif; ?>
  </div>

  <!-- ITEM DETAILS -->
  <div class="sec">
    <div class="sec-title">
      <?php
      if ($isGold) echo '💍 Jewellery Details';
      elseif ($isMobile) echo '📱 Mobile Phone Details';
      elseif ($isVehicle) echo '🚗 Vehicle Details';
      elseif ($isLand) echo '📄 Document Details';
      elseif ($isElec) echo '💻 Electronics Details';
      else echo '📦 Item Details';
      ?>
    </div>

    <?php if (!empty($p['item_photo']) && file_exists('../uploads/'.$p['item_photo'])): ?>
    <div class="item-photo-wrap">
      <img src="../uploads/<?=htmlspecialchars($p['item_photo'])?>" alt="Item Photo"/>
    </div>
    <?php endif; ?>

    <div class="row"><span class="row-key">Category</span><span class="row-val"><?=htmlspecialchars($p['item_type'])?></span></div>
    <div class="row"><span class="row-key">Description</span><span class="row-val"><?=htmlspecialchars($p['item_description'])?></span></div>

    <?php
    // Parse extra fields from description if stored as JSON key:value format
    // Or show item-specific helper rows based on type
    if ($isGold): ?>
    <div style="background:#FFF9E8;border-radius:6px;padding:7px 10px;margin-top:4px;border:1px solid #F0D090">
      <div style="font-size:10px;font-weight:700;color:#7B4800;margin-bottom:4px">JEWELLERY INFO</div>
      <div class="row"><span class="row-key">Metal Type</span><span class="row-val"><?=htmlspecialchars($p['item_type'])?></span></div>
      <?php // Weight/karat would be in description — parse if possible
      $desc = $p['item_description'];
      if (preg_match('/(\d+\.?\d*)\s*g/i', $desc, $m)):?><div class="row"><span class="row-key">Weight</span><span class="row-val"><?=$m[1]?>g</span></div><?php endif; ?>
      <?php if (preg_match('/(\d+)\s*[Kk]/i', $desc, $m)):?><div class="row"><span class="row-key">Karat</span><span class="row-val"><?=$m[1]?>K</span></div><?php endif; ?>
    </div>
    <?php elseif ($isMobile): ?>
    <div style="background:#E8F4FF;border-radius:6px;padding:7px 10px;margin-top:4px;border:1px solid #B0D4F0">
      <div style="font-size:10px;font-weight:700;color:#1565C0;margin-bottom:4px">MOBILE INFO</div>
      <div class="row"><span class="row-key">Description</span><span class="row-val"><?=htmlspecialchars(substr($desc,0,40))?></span></div>
    </div>
    <?php elseif ($isVehicle): ?>
    <div style="background:#E8FFE8;border-radius:6px;padding:7px 10px;margin-top:4px;border:1px solid #A0D4A0">
      <div style="font-size:10px;font-weight:700;color:#1B5E20;margin-bottom:4px">VEHICLE INFO</div>
      <div class="row"><span class="row-key">Details</span><span class="row-val"><?=htmlspecialchars(substr($desc,0,40))?></span></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- LOAN DETAILS -->
  <div class="sec">
    <div class="sec-title">💰 Loan Details</div>
    <div class="money-grid">
      <div class="money-box highlight">
        <div class="money-label">Loan</div>
        <div class="money-val">₹<?=number_format($p['loan_amount'],0)?></div>
      </div>
      <div class="money-box">
        <div class="money-label">Interest</div>
        <div class="money-val">₹<?=number_format($interest,0)?></div>
      </div>
      <div class="money-box danger">
        <div class="money-label">Total Due</div>
        <div class="money-val">₹<?=number_format($totalDue,0)?></div>
      </div>
    </div>
    <div class="money-grid">
      <div class="money-box paid">
        <div class="money-label">Paid</div>
        <div class="money-val">₹<?=number_format($totalPaid,0)?></div>
      </div>
      <div class="money-box" style="<?=$p['remaining_amount']>0?'background:#FFF0F0;border-color:#D32F2F':'background:#E8F5E9;border-color:#2E7D32'?>">
        <div class="money-label">Remaining</div>
        <div class="money-val" style="color:<?=$p['remaining_amount']>0?'#D32F2F':'#2E7D32'?>">₹<?=number_format($p['remaining_amount'],0)?></div>
      </div>
      <div class="money-box">
        <div class="money-label">Rate/Month</div>
        <div class="money-val"><?=$p['interest_rate']?>%</div>
      </div>
    </div>
    <div class="row" style="margin-top:6px"><span class="row-key">Pawn Date</span><span class="row-val"><?=date('d M Y',strtotime($p['pawn_date']))?></span></div>
    <div class="row"><span class="row-key">Due Date</span><span class="row-val"><?=date('d M Y',strtotime($p['due_date']))?></span></div>
    <div class="row"><span class="row-key">Duration</span><span class="row-val"><?=$p['duration_months']?> month(s)</span></div>
  </div>

  <!-- PAYMENT HISTORY -->
  <?php if (!empty($payments)): ?>
  <div class="sec">
    <div class="sec-title">📊 Payment History (<?=count($payments)?> payments)</div>
    <table class="pay-table">
      <thead><tr><th>#</th><th>Date</th><th>Amount</th><th>Mode</th><th>Remaining</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $i => $pay): ?>
      <tr>
        <td><?=$i+1?></td>
        <td><?=date('d M y',strtotime($pay['payment_date']))?></td>
        <td style="color:#2E7D32;font-weight:700">₹<?=number_format($pay['amount'],0)?></td>
        <td><?=strtoupper($pay['payment_mode']??'Cash')?></td>
        <td style="color:<?=$pay['remaining_after']==0?'#2E7D32':'#D32F2F'?>">₹<?=number_format($pay['remaining_after'],0)?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- FOOTER -->
  <div class="rfooter">
    <div>Yeh receipt valid legal document hai · <?=htmlspecialchars($siteName)?></div>
    <?php if (!empty($p['license_number'])): ?>
    <div style="margin-top:2px">License No: <?=htmlspecialchars($p['license_number'])?></div>
    <?php endif; ?>
    <div class="sig-line">
      <div>Customer Signature<br/><span style="font-size:8px">_______________</span></div>
      <div style="text-align:center">Date: <?=date('d/m/Y')?></div>
      <div style="text-align:right">Shopkeeper Signature<br/><span style="font-size:8px">_______________</span></div>
    </div>
  </div>

  <!-- Print buttons -->
  <div class="print-bar no-print">
    <button class="btn-print" onclick="window.print()">🖨 Print / Save PDF</button>
    <button class="btn-close" onclick="window.close()">✕ Close</button>
  </div>
</div>
</body>
</html>
