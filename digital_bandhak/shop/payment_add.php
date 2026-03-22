<?php
// shop/payment_add.php — redirect to payments.php
// This file exists as compatibility alias
$pawnId = intval($_GET['pawn_id'] ?? 0);
header('Location: payments.php' . ($pawnId ? '?pawn_id='.$pawnId : ''));
exit;
