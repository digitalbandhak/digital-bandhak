<?php
require_once '../includes/config.php';
requireLogin('super_admin', '../index.php');

$shopId = trim($_GET['shop_id'] ?? '');
$type   = $_GET['type'] ?? 'pays';

if (!$shopId) { jsonResponse(['error' => 'No shop ID']); }

if ($type === 'subs') {
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE shop_id=? ORDER BY created_at DESC");
    $stmt->execute([$shopId]);
    jsonResponse(['subs' => $stmt->fetchAll()]);
}

if ($type === 'pays') {
    $shop = $pdo->prepare("SELECT * FROM shops WHERE shop_id=?"); $shop->execute([$shopId]); $shopData=$shop->fetch();
    $total = $pdo->prepare("SELECT COUNT(*) FROM pawn_entries WHERE shop_id=?"); $total->execute([$shopId]);
    $activ = $pdo->prepare("SELECT COUNT(*) FROM pawn_entries WHERE shop_id=? AND status='active'"); $activ->execute([$shopId]);
    $coll  = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE shop_id=?"); $coll->execute([$shopId]);
    $pend  = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount),0) FROM pawn_entries WHERE shop_id=? AND status='active'"); $pend->execute([$shopId]);
    jsonResponse([
        'total_pawns'     => $total->fetchColumn(),
        'active_pawns'    => $activ->fetchColumn(),
        'total_collected' => $coll->fetchColumn(),
        'pending'         => $pend->fetchColumn(),
        'shop'            => $shopData,
    ]);
}
