<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

/**
 * Authenticated image serving. Receipt images live in a private dir outside the
 * web root; they are only readable via this script after verifying the caller
 * owns the receipt. The token may arrive as a Bearer header or ?token= (so <img>
 * tags can load it). NOTE: a query-string token can appear in logs — acceptable
 * for v1; a signed short-lived URL would be the hardened alternative.
 */

// Prefer an explicit ?token= (image tags can't set headers); fall back to Bearer.
if (!empty($_GET['token'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['token'];
}

$user = require_user();
$uid = (string) $user['id'];
$receiptId = (string) ($_GET['receipt_id'] ?? '');
$receipt = require_owned_receipt($receiptId, $uid);

$imagePath = (string) ($receipt['image_path'] ?? '');
if ($imagePath === '') {
    jfail('No image for this receipt.', 404);
}

$file = uploads_path($imagePath);
if (!is_file($file)) {
    jfail('Image file missing.', 404);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file) ?: 'application/octet-stream';
// Only ever serve real image types; never let a polyglot be sniffed as markup.
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    $mime = 'application/octet-stream';
}

header_remove('Content-Type');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($file);
exit;
