<?php
define('IS_SHOP', true);
require_once '../includes/config.php';
requireLogin('owner', '../index.php');

$shopId = $_SESSION['shop_id'];
$pawnId = intval($_GET['id'] ?? 0);
$isNew  = isset($_GET['new']);

$stmt = $pdo->prepare("SELECT pe.*, c.full_name, c.mobile, c.aadhaar_masked, c.address, s.shop_name, s.city FROM pawn_entries pe JOIN customers c ON pe.customer_id=c.id JOIN shops s ON pe.shop_id=s.shop_id WHERE pe.id=? AND pe.shop_id=?");
$stmt->execute([$pawnId, $shopId]);
$pawn = $stmt->fetch();
if (!$pawn) { header('Location: dashboard.php'); exit; }

// Payment history for this pawn
$pays = $pdo->prepare("SELECT * FROM payments WHERE pawn_id=? ORDER BY payment_date DESC");
$pays->execute([$pawnId]);
$payments = $pays->fetchAll();
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title><?= htmlspecialchars($pawn['bandhak_id']) ?> — Digital Bandhak</title>
  <link rel="stylesheet" href="../css/style.css"/>
</head>
<body>
<nav class="navbar">
  <a class="navbar-brand" href="dashboard.php">🏅 Digital <span>Bandhak</span></a>
  <div class="navbar-right">
    <a href="dashboard.php" class="btn btn-outline btn-sm">← Back</a>
    <button class="btn btn-gold btn-sm" onclick="printReceipt(<?= $pawnId ?>)">🖨 Print Receipt</button>
    <?php if ($pawn['status']==='active'): ?>
    <a href="payments.php?pawn_id=<?= $pawnId ?>" class="btn btn-success btn-sm">💰 Add Payment</a>
    <?php endif; ?>
  </div>
</nav>

<div class="main-content container">
  <?php if ($isNew): ?><div class="alert alert-success">✔ Pawn entry successfully save ho gayi! Receipt print karo.</div><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px" class="pawn-view-grid">
    <!-- Receipt Preview -->
    <div>
      <div class="receipt" style="max-width:100%">
        <div class="receipt-logo-row">
          <div class="receipt-logo"><?= strtoupper(substr($pawn['shop_name'],0,2)) ?></div>
          <div>
            <div class="receipt-shop-name"><?= htmlspecialchars($pawn['shop_name']) ?></div>
            <div style="font-size:11px;color:#999"><?= htmlspecialchars($pawn['city'] ?? '') ?></div>
          </div>
        </div>
        <div class="receipt-row"><span class="receipt-key">Receipt No.</span><span class="receipt-val"><?= htmlspecialchars($pawn['receipt_number']) ?></span></div>
        <div class="receipt-row"><span class="receipt-key">Bandhak ID</span><span class="receipt-val"><?= htmlspecialchars($pawn['bandhak_id']) ?></span></div>
        <hr style="border:none;border-top:1px dashed #ddd;margin:8px 0"/>
        <?php if ($pawn['item_photo']): ?>
        <div style="text-align:center;margin:8px 0"><img src="../uploads/<?= htmlspecialchars($pawn['item_photo']) ?>" style="max-height:100px;border-radius:8px;border:1px solid #eee" alt="Item"/></div>
        <?php endif; ?>
        <div class="receipt-row"><span class="receipt-key">Item</span><span class="receipt-val"><?= htmlspecialchars($pawn['item_type']) ?></span></div>
        <div class="receipt-row"><span class="receipt-key">Description</span><span class="receipt-val" style="max-width:180px;word-break:break-word;text-align:right"><?= htmlspecialchars($pawn['item_description']) ?></span></div>
        <hr style="border:none;border-top:1px dashed #ddd;margin:8px 0"/>
        <div class="receipt-row"><span class="receipt-key">Customer</span><span class="receipt-val"><?= htmlspecialchars($pawn['full_name']) ?></span></div>
        <div class="receipt-row"><span class="receipt-key">Mobile</span><span class="receipt-val"><?= htmlspecialchars($pawn['mobile']) ?></span></div>
        <div class="receipt-row"><span class="receipt-key">Aadhaar</span><span class="receipt-val"><?= htmlspecialchars($pawn['aadhaar_masked']) ?></span></div>
        <hr style="border:none;border-top:1px dashed #ddd;margin:8px 0"/>
        <div class="receipt-row"><span class="receipt-key">Loan Amount</span><span class="receipt-val">₹<?= number_format($pawn['loan_amount'],2) ?></span></div>
        <div class="receipt-row"><span class="receipt-key">Interest</span><span class="receipt-val"><?= $pawn['interest_rate'] ?>% / month</span></div>
        <div class="receipt-row"><span class="receipt-key">Duration</span><span class="receipt-val"><?= $pawn['duration_months'] ?> months</span></div>
        <div class="receipt-row"><span class="receipt-key">Pawn Date</span><span class="receipt-val"><?= date('d M Y', strtotime($pawn['pawn_date'])) ?></span></div>
        <div class="receipt-row"><span class="receipt-key">Due Date</span><span class="receipt-val"><?= date('d M Y', strtotime($pawn['due_date'])) ?></span></div>
        <hr style="border:none;border-top:1px dashed #ddd;margin:8px 0"/>
        <div class="receipt-row"><span class="receipt-key">Total Paid</span><span class="receipt-val" style="color:green">₹<?= number_format($pawn['total_paid'],2) ?></span></div>
        <div style="background:#faf6ee;border-radius:8px;padding:8px 10px;display:flex;justify-content:space-between;margin:8px 0">
          <span style="font-weight:700">Remaining</span>
          <span style="font-weight:700;color:<?= $pawn['remaining_amount']>0?'#8B1E1E':'green' ?>">₹<?= number_format($pawn['remaining_amount'],2) ?></span>
        </div>
        <div style="text-align:right"><span class="badge badge-<?= $pawn['status'] ?>"><?= ucfirst($pawn['status']) ?></span></div>
        <div class="receipt-watermark">Digital Bandhak</div>
      </div>
      <div style="display:flex;gap:8px;margin-top:12px">
        <button class="btn btn-gold w-full" onclick="printReceipt(<?= $pawnId ?>)">🖨 Print Receipt</button>
        <button class="btn btn-outline" onclick="printReceipt(<?= $pawnId ?>,'dup')">Duplicate Copy</button>
      </div>
    </div>

    <!-- Payment History -->
    <div>
      <div class="card">
        <div class="card-title">💰 Payment History</div>
        <?php if (empty($payments)): ?>
        <p class="text-muted" style="text-align:center;padding:20px">Koi payment nahi hui abhi tak</p>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Amount</th><th>Mode</th><th>Remaining</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $pay): ?>
            <tr>
              <td><?= date('d M y', strtotime($pay['payment_date'])) ?></td>
              <td style="color:var(--success);font-weight:600">₹<?= number_format($pay['amount'],2) ?></td>
              <td><?= strtoupper($pay['payment_mode']) ?></td>
              <td style="color:<?= $pay['remaining_after']==0?'var(--success)':'var(--danger)' ?>">₹<?= number_format($pay['remaining_after'],2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
        <?php if ($pawn['status']==='active'): ?>
        <a href="payments.php?pawn_id=<?= $pawnId ?>" class="btn btn-gold w-full mt-12">+ Add Payment</a>
        <?php endif; ?>
      </div>

      <!-- Customer Password -->
      <div class="card mt-16">
        <div class="card-title">🔑 Customer Login Password</div>
        <p class="text-muted text-small mb-12">Customer ko yeh password de do. Woh <strong>Bandhak ID + Password</strong> se login kar sakta hai.</p>
        <form method="POST" action="../php/set_customer_password.php">
          <input type="hidden" name="customer_id" value="<?= $pawn['customer_id'] ?>"/>
          <input type="hidden" name="bandhak_id"  value="<?= htmlspecialchars($pawn['bandhak_id']) ?>"/>
          <div style="display:flex;gap:8px">
            <input class="form-control" type="text" name="new_password" placeholder="e.g. verma123" maxlength="20" required style="flex:1"/>
            <button type="submit" class="btn btn-gold btn-sm">Set Password</button>
          </div>
        </form>
      </div>

      <!-- Actions -->
      <?php if ($pawn['status']==='active'): ?>
      <div class="card mt-16">
        <div class="card-title" style="color:var(--danger)">⚠ Danger Zone</div>
        <button class="btn btn-danger w-full" onclick="confirmDelete(<?= $pawnId ?>,'<?= $pawn['bandhak_id'] ?>')">🗑 Delete Entry (Soft Delete)</button>
        <p class="text-small text-muted mt-8">Entry soft-delete hogi, audit log record hoga.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal-backdrop" id="modal-delete" style="display:none">
  <div class="modal-box">
    <div class="modal-title">⚠ Entry Delete?</div>
    <div class="modal-body">Bandhak <strong id="delete_bandhak_label"></strong> delete ho jayegi. Audit log record hoga.</div>
    <div class="form-group"><label class="form-label">Owner Password</label><input class="form-control" type="password" id="delete_owner_pw"/></div>
    <input type="hidden" id="delete_pawn_id"/>
    <div class="modal-footer">
      <button class="btn btn-danger" onclick="submitDelete()">Delete</button>
      <button class="btn btn-outline" onclick="closeModal('modal-delete')">Cancel</button>
    </div>
  </div>
</div>

<?php include '../includes/mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>
function printReceipt(id, type) {
  const url = '../php/receipt_print.php?id=' + id + (type==='dup'?'&dup=1':'');
  window.open(url, '_blank', 'width=440,height=680');
}
</script>
</body>
</html>
