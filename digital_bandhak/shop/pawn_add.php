<?php
define('IS_SHOP', true);
require_once '../includes/config.php';
requireLogin('owner', '../index.php');

$shopId  = $_SESSION['shop_id'];
$ownerId = $_SESSION['user_id'];

// Block if subscription expired
$subCheck = checkSubscription($pdo, $shopId);
if (!$subCheck) {
    // Show expired popup then redirect to subscription page
    header("Location: subscription.php?expired=1"); exit;
}
$step    = $_GET['step'] ?? '1';
$error   = '';

// STEP 3: Final Save
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['final_save'])) {
    $ownerPw = $_POST['owner_password'] ?? '';
    $shopRow = $pdo->prepare("SELECT password FROM shops WHERE shop_id=?"); $shopRow->execute([$shopId]); $sh=$shopRow->fetch();
    if (!$sh || !password_verify($ownerPw,$sh['password'])) {
        $error='Owner password galat hai!'; $step='2';
        $previewData=$json=json_decode(base64_decode($_POST['preview_data']),true);
        $previewEncoded=$_POST['preview_data'];
    } else {
        $data=json_decode(base64_decode($_POST['preview_data']),true);
        $bandhakId     = generateUniqueBandhakId($pdo,$shopId);
        $receiptNumber = generateReceiptNumber($shopId);

        // Find/create customer
        $cust=$pdo->prepare("SELECT id FROM customers WHERE mobile=? AND shop_id=?"); $cust->execute([$data['mobile'],$shopId]); $custRow=$cust->fetch();
        if (!$custRow) {
            $pdo->prepare("INSERT INTO customers (shop_id,bandhak_id,full_name,mobile,address,aadhaar_masked,father_spouse) VALUES (?,?,?,?,?,?,?)")
                ->execute([$shopId,$bandhakId,$data['full_name'],$data['mobile'],$data['address']??'',maskAadhaar($data['aadhaar']??''),$data['father_spouse']??'']);
            $custId=$pdo->lastInsertId();
        } else { $custId=$custRow['id']; }

        // Upload MULTIPLE photos — store as JSON array
        $photos=[];
        if (!empty($_FILES['item_photos']['name'][0])) {
            if (!is_dir(UPLOAD_PATH.'products/')) mkdir(UPLOAD_PATH.'products/',0755,true);
            foreach($_FILES['item_photos']['tmp_name'] as $i=>$tmp) {
                if ($_FILES['item_photos']['error'][$i]===UPLOAD_ERR_OK) {
                    $ext=strtolower(pathinfo($_FILES['item_photos']['name'][$i],PATHINFO_EXTENSION));
                    if (in_array($ext,['jpg','jpeg','png','webp','gif'])) {
                        $fname='products/'.uniqid().'.'.$ext;
                        if (move_uploaded_file($tmp,UPLOAD_PATH.$fname)) $photos[]=$fname;
                    }
                }
            }
        }
        $photosJson = !empty($photos) ? json_encode($photos) : null;
        $firstPhoto = $photos[0] ?? null;

        // Extra fields JSON
        $extraFields = $data['extra_fields'] ?? [];
        $extraJson   = !empty($extraFields) ? json_encode($extraFields) : null;

        $loanAmt  = floatval($data['loan_amount']);
        $durUnit  = $data['duration_unit'] ?? 'months';
        $months   = $durUnit==='years' ? $data['duration']*12 : ($durUnit==='days' ? ceil($data['duration']/30) : $data['duration']);
        $dueDate  = date('Y-m-d', strtotime('+'.$data['duration'].' '.$durUnit, strtotime($data['pawn_date'])));

        $pdo->prepare("INSERT INTO pawn_entries (bandhak_id,shop_id,customer_id,item_type,item_description,item_photo,loan_amount,interest_rate,duration_months,pawn_date,due_date,remaining_amount,receipt_number,created_by,created_by_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$bandhakId,$shopId,$custId,$data['item_type'],$data['item_desc'],$firstPhoto,$loanAmt,$data['interest_rate'],$months,$data['pawn_date'],$dueDate,$loanAmt,$receiptNumber,'owner',$ownerId]);
        $pawnId=$pdo->lastInsertId();

        // Store extra photos in separate table if more than 1
        if (count($photos)>1) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS pawn_photos (id INT AUTO_INCREMENT PRIMARY KEY, pawn_id INT NOT NULL, photo_path VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
                foreach ($photos as $ph) {
                    $pdo->prepare("INSERT INTO pawn_photos (pawn_id,photo_path) VALUES (?,?)")->execute([$pawnId,$ph]);
                }
            } catch(Exception $e) {}
        }

        auditLog($pdo,$shopId,'pawn_created',"New pawn: $bandhakId — {$data['full_name']} ₹$loanAmt",'owner',$ownerId,$_SESSION['user_name'],$bandhakId);
        header("Location:pawn_view.php?id=$pawnId&new=1"); exit;
    }
}

// STEP 2: Preview
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['preview'])) {
    $step='2';
    $extraFields=[];
    foreach (($_POST['extra_key']??[]) as $i=>$key) {
        if ($key && isset($_POST['extra_val'][$i]) && $_POST['extra_val'][$i])
            $extraFields[$key]=$_POST['extra_val'][$i];
    }
    $previewData=[
        'full_name'    =>trim($_POST['full_name']),
        'mobile'       =>preg_replace('/\D/','',trim($_POST['mobile']??'')),
        'father_spouse'=>trim($_POST['father_spouse']??''),
        'address'      =>trim($_POST['address']??''),
        'aadhaar'      =>trim($_POST['aadhaar']??''),
        'item_type'    =>trim($_POST['item_type']),
        'item_desc'    =>trim($_POST['item_desc']),
        'loan_amount'  =>floatval($_POST['loan_amount']??0),
        'interest_rate'=>floatval($_POST['interest_rate']??2),
        'duration'     =>intval($_POST['duration']??1),
        'duration_unit'=>$_POST['duration_unit']??'months',
        'pawn_date'    =>$_POST['pawn_date']??date('Y-m-d'),
        'payment_mode' =>$_POST['payment_mode']??'Cash',
        'extra_fields' =>$extraFields,
    ];
    $previewEncoded=base64_encode(json_encode($previewData));
}

// Customer search
$searchResults=[];
if (!empty($_GET['search_customer'])) {
    $q='%'.trim($_GET['search_customer']).'%';
    $s=$pdo->prepare("SELECT c.*,pe.bandhak_id as last_bid,pe.item_type as last_item FROM customers c LEFT JOIN pawn_entries pe ON pe.customer_id=c.id AND pe.status='active' WHERE c.shop_id=? AND (c.full_name LIKE ? OR c.mobile LIKE ? OR c.address LIKE ? OR pe.bandhak_id LIKE ?) GROUP BY c.id ORDER BY c.full_name LIMIT 8");
    $s->execute([$shopId,$q,$q,$q,$q]); $searchResults=$s->fetchAll();
}

$unreadCount=0;
try { $u=$pdo->prepare("SELECT COUNT(*) FROM admin_chat_messages WHERE shop_id=? AND sender_type='admin' AND is_read=0"); $u->execute([$shopId]); $unreadCount=$u->fetchColumn(); } catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>New Pawn Entry — Digital Bandhak</title>
<link rel="stylesheet" href="../css/style.css"/>
<style>
.item-fields-box{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin-top:10px}
.field-row-extra{display:flex;gap:8px;margin-bottom:8px;align-items:center}
.photo-thumb{width:64px;height:64px;border-radius:8px;object-fit:cover;border:1px solid var(--border);flex-shrink:0}
.photo-remove{background:var(--danger-bg);color:var(--danger);border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;margin-top:-60px;margin-left:-10px;position:relative}
</style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="dashboard-layout">
<aside class="sidebar" id="sidebar">
  <a href="dashboard.php"><span class="sidebar-icon">📊</span> Dashboard</a>
  <a href="pawn_add.php" class="active"><span class="sidebar-icon">➕</span> New Entry</a>
  <a href="pawn_list.php"><span class="sidebar-icon">📋</span> Pawn List</a>
  <a href="payments.php"><span class="sidebar-icon">💰</span> Payments</a>
  <div class="sidebar-divider"></div>
  <a href="reports.php"><span class="sidebar-icon">📄</span> Reports</a>
  <a href="interest_calc.php"><span class="sidebar-icon">🧮</span> Calculator</a>
  <a href="subscription.php"><span class="sidebar-icon">🔁</span> Subscription</a>
  <a href="staff.php"><span class="sidebar-icon">👷</span> Staff</a>
  <a href="chat.php"><span class="sidebar-icon">💬</span> Chat<?php if($unreadCount): ?><span class="badge-count"><?=$unreadCount?></span><?php endif; ?></a>
  <a href="profile.php"><span class="sidebar-icon">👤</span> My Profile</a>
  <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
</aside>
<main class="main-content">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
    <h2>➕ New Pawn Entry</h2>
    <a href="dashboard.php" class="btn btn-outline btn-sm">← Dashboard</a>
  </div>
  <?php if ($error): ?><div class="alert alert-danger">✖ <?=htmlspecialchars($error)?></div><?php endif; ?>

  <!-- Steps -->
  <div class="step-progress mb-16">
    <div class="step"><div class="step-dot <?=$step>='1'?'done':'current'?>">1</div><div class="step-label">Details</div></div>
    <div class="step-line"></div>
    <div class="step"><div class="step-dot <?=$step=='2'?'current':($step>'2'?'done':'pending')?>">2</div><div class="step-label">Preview</div></div>
    <div class="step-line"></div>
    <div class="step"><div class="step-dot <?=$step=='3'?'current':'pending'?>">3</div><div class="step-label">Confirm</div></div>
    <div class="step-line"></div>
    <div class="step"><div class="step-dot pending">4</div><div class="step-label">Print</div></div>
  </div>

<?php if ($step=='1'): ?>
  <!-- Customer Search -->
  <div class="card mb-16">
    <div class="card-title">🔍 Existing Customer Search</div>
    <div style="display:flex;gap:10px">
      <input class="form-control" type="text" id="custSearch" placeholder="Name, Mobile, Address, Bandhak ID…" style="flex:1" oninput="debounceSearch(this.value)"/>
      <button class="btn btn-outline btn-sm" onclick="document.getElementById('custSearch').value='';document.getElementById('searchResultsBox').innerHTML=''">Clear</button>
    </div>
    <div id="searchResultsBox" style="margin-top:10px"></div>
  </div>

  <!-- FORM -->
  <form method="POST" enctype="multipart/form-data" id="pawnForm">
    <!-- Customer -->
    <div class="card mb-16">
      <div class="card-title">👤 Customer Details</div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input class="form-control" type="text" name="full_name" id="f_name" required placeholder="Ramesh Kumar"/>
        </div>
        <div class="form-group">
          <label class="form-label">Mobile * (+91)</label>
          <div style="display:flex">
            <span style="background:var(--surface);border:1px solid var(--border2);border-right:none;border-radius:var(--radius) 0 0 var(--radius);padding:9px 11px;font-size:13px;color:var(--text3)">+91</span>
            <input class="form-control" type="tel" name="mobile" id="f_mobile" required placeholder="9876543210" maxlength="10" style="border-radius:0 var(--radius) var(--radius) 0;border-left:none" oninput="this.value=this.value.replace(/\D/g,'').slice(0,10)"/>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Father / Spouse Name</label>
          <input class="form-control" type="text" name="father_spouse" id="f_father" placeholder="S/o, W/o, D/o…"/>
        </div>
        <div class="form-group">
          <label class="form-label">Aadhaar (Last 4 digits)</label>
          <input class="form-control" type="text" name="aadhaar" id="f_aadhaar" placeholder="XXXX XXXX XXXX" maxlength="14"/>
        </div>
        <div class="form-group col-span-2">
          <label class="form-label">Full Address</label>
          <input class="form-control" type="text" name="address" id="f_address" placeholder="Gali, Village/Mohalla, City, District, State, Pincode"/>
        </div>
      </div>
    </div>

    <!-- Item -->
    <div class="card mb-16">
      <div class="card-title">💍 Item Details</div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Item Category *</label>
          <select class="form-control" name="item_type" id="itemTypeSelect" required onchange="showItemFields(this.value)">
            <option value="">-- Select Category --</option>
            <optgroup label="Jewellery">
              <option value="Gold Jewellery">Gold Jewellery</option>
              <option value="Silver Jewellery">Silver Jewellery</option>
              <option value="Diamond Jewellery">Diamond Jewellery</option>
              <option value="Platinum Jewellery">Platinum Jewellery</option>
            </optgroup>
            <optgroup label="Electronics">
              <option value="Mobile Phone">Mobile Phone</option>
              <option value="Laptop">Laptop / Computer</option>
              <option value="Tablet">Tablet</option>
              <option value="TV / Display">TV / Display</option>
              <option value="Electronics">Other Electronics</option>
            </optgroup>
            <optgroup label="Vehicle">
              <option value="Two Wheeler">Two Wheeler (Bike/Scooter)</option>
              <option value="Four Wheeler">Four Wheeler (Car)</option>
              <option value="Bicycle">Bicycle</option>
              <option value="E-Vehicle">E-Vehicle</option>
            </optgroup>
            <optgroup label="Documents">
              <option value="Land Document">Land Document</option>
              <option value="Property Document">Property Document</option>
              <option value="Other Document">Other Document</option>
            </optgroup>
            <optgroup label="Other">
              <option value="Watch">Watch / Clock</option>
              <option value="Musical Instrument">Musical Instrument</option>
              <option value="Tool / Equipment">Tool / Equipment</option>
              <option value="Other">Other</option>
            </optgroup>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description *</label>
          <input class="form-control" type="text" name="item_desc" required placeholder="e.g. 22K gold necklace, 18g"/>
        </div>
      </div>

      <!-- Dynamic Item Fields -->
      <div id="dynamicFieldsBox" class="item-fields-box" style="display:none">
        <div class="form-label" style="margin-bottom:10px">📋 Item Details</div>
        <div id="dynamicFieldsInner"></div>
        <!-- Hidden inputs for extra fields -->
        <div id="extraFieldsHidden"></div>
      </div>

      <!-- Photos -->
      <div class="form-group mt-16 col-span-2">
        <label class="form-label">Item Photos (upto 5) — Click to Add</label>
        <div class="upload-zone" onclick="document.getElementById('photoInput').click()" id="uploadZone">
          <span style="font-size:28px">📷</span><br/>
          <strong>Click to upload photos</strong><br/>
          <span class="text-small">JPG/PNG max 5MB each · Upto 5 photos</span>
        </div>
        <input type="file" id="photoInput" name="item_photos[]" accept="image/*" multiple style="display:none" onchange="handlePhotos(this)"/>
        <div id="photoPreviews" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px"></div>
      </div>
    </div>

    <!-- Loan -->
    <div class="card mb-16">
      <div class="card-title">💰 Loan Details</div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Loan Amount (₹) <span class="text-muted" style="font-weight:400">(0 allowed)</span></label>
          <input class="form-control" type="number" name="loan_amount" id="loan_amount" min="0" value="0" oninput="calcLoanPreview()"/>
        </div>
        <div class="form-group">
          <label class="form-label">Interest Rate (%/month)</label>
          <input class="form-control" type="number" name="interest_rate" id="interest_rate" step="0.1" min="0" value="2" oninput="calcLoanPreview()"/>
        </div>
        <div class="form-group">
          <label class="form-label">Duration</label>
          <div style="display:flex;gap:8px">
            <input class="form-control" type="number" name="duration" id="duration_months" value="1" min="1" style="width:80px;flex-shrink:0" oninput="calcLoanPreview()"/>
            <select class="form-control" name="duration_unit" id="duration_unit" onchange="calcLoanPreview()">
              <option value="months">Month(s)</option>
              <option value="years">Year(s)</option>
              <option value="days">Day(s)</option>
            </select>
          </div>
          <p class="text-small text-muted mt-8">Village area: 2 year = 24 months. Interest monthly grows.</p>
        </div>
        <div class="form-group">
          <label class="form-label">Pawn Date</label>
          <input class="form-control" type="date" name="pawn_date" value="<?=date('Y-m-d')?>"/>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Mode</label>
          <select class="form-control" name="payment_mode">
            <option>Cash</option><option>Online</option><option>UPI</option><option>Cheque</option>
          </select>
        </div>
      </div>
      <div id="loan-preview"></div>
    </div>

    <div style="display:flex;gap:10px">
      <button type="submit" name="preview" class="btn btn-gold btn-lg" onclick="return validatePawnForm()">Preview →</button>
      <a href="dashboard.php" class="btn btn-outline btn-lg">Cancel</a>
    </div>
  </form>

<?php elseif ($step=='2'): ?>
  <!-- PREVIEW -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;flex-wrap:wrap">
    <div class="receipt" style="max-width:100%">
      <div class="receipt-logo-row">
        <div class="receipt-logo"><?=strtoupper(substr($_SESSION['shop_name'],0,2))?></div>
        <div><div class="receipt-shop-name"><?=htmlspecialchars($_SESSION['shop_name'])?></div><div style="font-size:11px;color:var(--text3)"><?=$shopId?></div></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
        <div>
          <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text3);margin-bottom:5px">👤 Customer</div>
          <div class="receipt-row"><span class="receipt-key">Name</span><span class="receipt-val"><?=htmlspecialchars($previewData['full_name'])?></span></div>
          <div class="receipt-row"><span class="receipt-key">Mobile</span><span class="receipt-val">+91 <?=htmlspecialchars($previewData['mobile'])?></span></div>
          <?php if (!empty($previewData['aadhaar'])): ?><div class="receipt-row"><span class="receipt-key">Aadhaar</span><span class="receipt-val"><?=maskAadhaar($previewData['aadhaar'])?></span></div><?php endif; ?>
          <?php if (!empty($previewData['address'])): ?><div class="receipt-row"><span class="receipt-key">Address</span><span class="receipt-val"><?=htmlspecialchars(substr($previewData['address'],0,35))?></span></div><?php endif; ?>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text3);margin-bottom:5px">💍 Item</div>
          <div class="receipt-row"><span class="receipt-key">Type</span><span class="receipt-val"><?=htmlspecialchars($previewData['item_type'])?></span></div>
          <div class="receipt-row"><span class="receipt-key">Desc</span><span class="receipt-val"><?=htmlspecialchars(substr($previewData['item_desc'],0,25))?></span></div>
          <?php foreach (($previewData['extra_fields']??[]) as $k=>$v): ?>
          <div class="receipt-row"><span class="receipt-key"><?=htmlspecialchars($k)?></span><span class="receipt-val"><?=htmlspecialchars($v)?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
      <hr style="border:none;border-top:1px dashed var(--border);margin:8px 0"/>
      <?php
        $loan=$previewData['loan_amount']; $rate=$previewData['interest_rate'];
        $dur=$previewData['duration']; $unit=$previewData['duration_unit']??'months';
        $months=$unit==='years'?$dur*12:($unit==='days'?ceil($dur/30):$dur);
        $interest=round($loan*($rate/100)*$months,2); $total=$loan+$interest;
      ?>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;text-align:center;margin-bottom:10px">
        <div style="background:var(--surface);border-radius:6px;padding:8px"><div style="font-size:10px;color:var(--text3)">Loan</div><div style="font-weight:700;color:var(--gold)">₹<?=number_format($loan,0)?></div></div>
        <div style="background:var(--surface);border-radius:6px;padding:8px"><div style="font-size:10px;color:var(--text3)">Interest</div><div style="font-weight:700;color:var(--warning)">₹<?=number_format($interest,0)?></div></div>
        <div style="background:var(--surface);border-radius:6px;padding:8px;border:1px solid var(--gold)"><div style="font-size:10px;color:var(--text3)">Total Due</div><div style="font-weight:700;color:var(--danger)">₹<?=number_format($total,0)?></div></div>
      </div>
      <div class="receipt-row"><span class="receipt-key">Date</span><span class="receipt-val"><?=$previewData['pawn_date']?></span></div>
      <div class="receipt-row"><span class="receipt-key">Duration</span><span class="receipt-val"><?=$dur?> <?=$unit?> (<?=$months?> months)</span></div>
      <div class="receipt-row"><span class="receipt-key">Rate</span><span class="receipt-val"><?=$rate?>%/month</span></div>
      <div class="receipt-watermark">PREVIEW — NOT CONFIRMED</div>
    </div>

    <div class="card">
      <div class="card-title">🔐 Owner Confirmation</div>
      <p class="text-muted mb-16">Owner password se confirm karke save karo.</p>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="final_save" value="1"/>
        <input type="hidden" name="preview_data" value="<?=htmlspecialchars($previewEncoded)?>"/>
        <div class="form-group">
          <label class="form-label">Owner Password *</label>
          <input class="form-control" type="password" name="owner_password" required placeholder="Confirm karo" autofocus/>
        </div>
        <div class="form-group">
          <label class="form-label">Item Photos (add now if missed)</label>
          <input class="form-control" type="file" name="item_photos[]" accept="image/*" multiple/>
          <p class="text-small text-muted mt-8">Multiple select kar sakte ho</p>
        </div>
        <div style="display:flex;gap:10px;margin-top:12px">
          <button type="submit" class="btn btn-gold btn-lg">✔ Confirm & Save</button>
          <a href="pawn_add.php" class="btn btn-outline btn-lg">← Edit</a>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>
</main>
</div>

<?php include '../includes/mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>
// ---- DYNAMIC ITEM FIELDS ----
const itemFieldConfig = {
  'Gold Jewellery':    [['Weight (g/mg)','weight'],['Karat (22K/18K)','karat'],['Market Value (₹)','market_value'],['Making Charges','making'],['Hallmark Number','hallmark']],
  'Silver Jewellery':  [['Weight (g)','weight'],['Purity','purity'],['Market Value (₹)','market_value']],
  'Diamond Jewellery': [['Weight (carat)','weight'],['Cut/Clarity','clarity'],['Certification No.','cert_no'],['Market Value (₹)','market_value']],
  'Platinum Jewellery':[['Weight (g)','weight'],['Purity (950/850)','purity'],['Market Value (₹)','market_value']],
  'Mobile Phone':      [['Brand & Model','model'],['IMEI Number 1','imei1'],['IMEI Number 2','imei2'],['Color','color'],['Storage (GB)','storage'],['Condition','condition']],
  'Laptop':            [['Brand & Model','model'],['Serial Number','serial_no'],['Processor','processor'],['RAM (GB)','ram'],['Storage','storage'],['Condition','condition']],
  'Tablet':            [['Brand & Model','model'],['Serial Number','serial_no'],['IMEI / WiFi Only','imei1'],['Condition','condition']],
  'TV / Display':      [['Brand & Model','model'],['Size (inch)','size'],['Serial Number','serial_no'],['Type (LED/OLED)','type'],['Condition','condition']],
  'Electronics':       [['Brand & Model','model'],['Serial Number','serial_no'],['Condition','condition']],
  'Two Wheeler':       [['Brand & Model','model'],['Registration No.','reg_no'],['Chassis No.','chassis_no'],['Engine No.','engine_no'],['Year','year'],['Color','color']],
  'Four Wheeler':      [['Brand & Model','model'],['Registration No.','reg_no'],['Chassis No.','chassis_no'],['Engine No.','engine_no'],['Year','year'],['Color','color'],['RC Status','rc_status']],
  'Bicycle':           [['Brand & Model','model'],['Color','color'],['Condition','condition'],['Frame No.','frame_no']],
  'E-Vehicle':         [['Brand & Model','model'],['Registration No.','reg_no'],['Battery Capacity','battery'],['Condition','condition']],
  'Land Document':     [['Khasra/Plot No.','plot_no'],['Area (bigha/sqft)','area'],['Location','location'],['Registry Date','registry_date'],['Owner Name in Doc','doc_owner']],
  'Property Document': [['Property Address','prop_address'],['Area','area'],['Registry No.','registry_no'],['Registry Date','registry_date']],
  'Other Document':    [['Document Type','doc_type'],['Document No.','doc_no'],['Issue Date','issue_date']],
  'Watch':             [['Brand & Model','model'],['Serial No.','serial_no'],['Material','material'],['Condition','condition']],
  'Other':             [['Brand/Make','brand'],['Serial/ID No.','serial_no'],['Condition','condition'],['Market Value (₹)','market_value']],
};

function showItemFields(itemType) {
  const box   = document.getElementById('dynamicFieldsBox');
  const inner = document.getElementById('dynamicFieldsInner');
  const hidden= document.getElementById('extraFieldsHidden');
  const fields= itemFieldConfig[itemType] || [];

  if (!fields.length) { box.style.display='none'; return; }
  box.style.display='block';

  // Build field inputs
  inner.innerHTML = '<div class="form-grid">';
  fields.forEach(([label,key]) => {
    inner.innerHTML += `
      <div class="form-group">
        <label class="form-label">${label}</label>
        <input class="form-control extra-field" type="text" data-key="${key}" placeholder="${label}" oninput="updateHiddenFields()"/>
      </div>`;
  });
  inner.innerHTML += '</div>';
}

function updateHiddenFields() {
  const hidden = document.getElementById('extraFieldsHidden');
  hidden.innerHTML = '';
  document.querySelectorAll('.extra-field').forEach(inp => {
    if (inp.value.trim()) {
      hidden.innerHTML += `<input type="hidden" name="extra_key[]" value="${inp.dataset.key}"/><input type="hidden" name="extra_val[]" value="${inp.value.trim()}"/>`;
    }
  });
}

// ---- MULTI PHOTO HANDLER ----
let selectedFiles = [];

function handlePhotos(inp) {
  const newFiles = Array.from(inp.files);
  // Limit to 5 total
  const available = 5 - selectedFiles.length;
  const toAdd     = newFiles.slice(0, available);

  toAdd.forEach(f => {
    if (f.size > 5*1024*1024) { showAlert(f.name + ' too large (max 5MB)', 'warning'); return; }
    selectedFiles.push(f);
  });

  if (newFiles.length > available) {
    showAlert('Maximum 5 photos allowed. Some were skipped.', 'warning');
  }

  renderPhotoPreviews();
  rebuildFileInput();
}

function removePhoto(idx) {
  selectedFiles.splice(idx, 1);
  renderPhotoPreviews();
  rebuildFileInput();
}

function renderPhotoPreviews() {
  const prev = document.getElementById('photoPreviews');
  prev.innerHTML = '';
  selectedFiles.forEach((f,i) => {
    const reader = new FileReader();
    reader.onload = e => {
      const wrap = document.createElement('div');
      wrap.style.cssText = 'position:relative;display:inline-block';
      wrap.innerHTML = `
        <img src="${e.target.result}" class="photo-thumb" alt="Photo ${i+1}"/>
        <button type="button" onclick="removePhoto(${i})" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:var(--danger);color:#fff;border:none;cursor:pointer;font-size:11px;line-height:1;display:flex;align-items:center;justify-content:center">✕</button>
        <div style="font-size:10px;color:var(--text3);text-align:center;margin-top:2px">${i+1}</div>`;
      prev.appendChild(wrap);
    };
    reader.readAsDataURL(f);
  });

  const zone = document.getElementById('uploadZone');
  if (selectedFiles.length >= 5) {
    zone.style.opacity='0.5'; zone.style.pointerEvents='none';
    zone.innerHTML = `<span style="font-size:20px">✔</span><br/><strong>5/5 photos selected</strong>`;
  } else {
    zone.style.opacity='1'; zone.style.pointerEvents='auto';
    zone.innerHTML = `<span style="font-size:24px">📷</span><br/><strong>Add more photos</strong> (${selectedFiles.length}/5)<br/><span class="text-small">JPG/PNG max 5MB each</span>`;
  }
}

function rebuildFileInput() {
  // Create a new DataTransfer to rebuild the file input
  try {
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    document.getElementById('photoInput').files = dt.files;
  } catch(e) {
    // Fallback: create hidden file inputs
    document.querySelectorAll('.photo-file-hidden').forEach(el => el.remove());
    selectedFiles.forEach((f,i) => {
      // Can't programmatically set file input values in all browsers
      // Just track in selectedFiles array
    });
  }
}

// ---- CUSTOMER SEARCH ----
let searchTimer = null;
function debounceSearch(q) {
  clearTimeout(searchTimer);
  const box = document.getElementById('searchResultsBox');
  if (q.length < 2) { box.innerHTML=''; return; }
  searchTimer = setTimeout(async () => {
    const r   = await fetch('pawn_add.php?search_customer='+encodeURIComponent(q));
    const html= await r.text();
    const doc = new DOMParser().parseFromString(html,'text/html');
    const el  = doc.getElementById('searchData');
    box.innerHTML = el ? el.innerHTML : '<p class="text-muted text-small">Koi customer nahi mila</p>';
  }, 350);
}

function fillCustomer(name, mobile, address, aadhaar, father) {
  document.getElementById('f_name').value    = name    || '';
  document.getElementById('f_mobile').value  = mobile  || '';
  document.getElementById('f_address').value = address || '';
  document.getElementById('f_aadhaar').value = aadhaar || '';
  document.getElementById('f_father').value  = father  || '';
  document.getElementById('searchResultsBox').innerHTML = '';
  document.getElementById('custSearch').value = '';
  showAlert('Customer details filled!','success');
}

calcLoanPreview();

function validatePawnForm() {
  const dur = parseInt(document.getElementById('duration_months')?.value) || 0;
  if (dur < 1) {
    showAlert('Duration kam se kam 1 hona chahiye!', 'warning');
    document.getElementById('duration_months').value = 1;
    calcLoanPreview();
    return false;
  }
  const name = document.getElementById('f_name')?.value.trim();
  const mob  = document.getElementById('f_mobile')?.value.trim();
  if (!name) { showAlert('Customer naam zaroori hai', 'warning'); return false; }
  if (!mob || mob.length < 10) { showAlert('Valid 10-digit mobile daalo', 'warning'); return false; }
  return true;
}
</script>

<!-- Search Results Data (hidden, for AJAX extraction) -->
<?php if (!empty($searchResults)): ?>
<div id="searchData" style="display:none">
  <?php foreach ($searchResults as $sr): ?>
  <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius);margin-bottom:6px;background:var(--surface);cursor:pointer"
    onclick="fillCustomer('<?=addslashes($sr['full_name'])?>','<?=preg_replace('/\D/','',$sr['mobile'])?>','<?=addslashes($sr['address']??'')?>','<?=addslashes($sr['aadhaar_masked']??'')?>','<?=addslashes($sr['father_spouse']??'')?>')">
    <div>
      <strong style="font-size:13px"><?=htmlspecialchars($sr['full_name'])?></strong>
      <span class="text-muted text-small" style="margin-left:8px">+91 <?=htmlspecialchars($sr['mobile'])?></span>
      <?php if ($sr['address']): ?><div class="text-small text-muted"><?=htmlspecialchars(substr($sr['address'],0,50))?></div><?php endif; ?>
      <?php if ($sr['last_bid']): ?><div class="text-small" style="color:var(--gold)"><?=htmlspecialchars($sr['last_bid'])?> · <?=htmlspecialchars($sr['last_item']??'')?></div><?php endif; ?>
    </div>
    <span class="btn btn-outline btn-sm" style="flex-shrink:0">Fill ↗</span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</body>
</html>
