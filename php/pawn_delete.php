<?php
// php/pawn_delete.php
require_once '../includes/config.php';
requireLogin('owner', '../index.php');

$pawnId  = intval($_POST['pawn_id'] ?? 0);
$ownerPw = $_POST['owner_password'] ?? '';
$shopId  = $_SESSION['shop_id'];

// Verify password
$shopRow = $pdo->prepare("SELECT password FROM shops WHERE shop_id=?"); $shopRow->execute([$shopId]); $sh = $shopRow->fetch();
if (!$sh || !password_verify($ownerPw, $sh['password'])) jsonResponse(['success'=>false,'msg'=>'Owner password galat hai']);

// Get pawn
$pawnStmt = $pdo->prepare("SELECT * FROM pawn_entries WHERE id=? AND shop_id=?"); $pawnStmt->execute([$pawnId, $shopId]); $pawn = $pawnStmt->fetch();
if (!$pawn) jsonResponse(['success'=>false,'msg'=>'Entry nahi mili']);

// Soft delete
$del = $pdo->prepare("UPDATE pawn_entries SET status='deleted', deleted_at=NOW(), deleted_by=? WHERE id=?");
$del->execute([$_SESSION['user_id'], $pawnId]);

auditLog($pdo, $shopId, 'pawn_deleted', "Pawn deleted: {$pawn['bandhak_id']}", 'owner', $_SESSION['user_id'], $_SESSION['user_name'], $pawn['bandhak_id']);

jsonResponse(['success'=>true]);
