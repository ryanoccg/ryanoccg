<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

/**
 * Authenticated image serving. Receipt images live in a private dir outside the
 * web root; they are only readable via this script after verifying the caller
 * owns the receipt. Token may arrive as a Bearer header or ?token= (so <img>
 * tags can load it).
 */

if (!empty($_GET['token']) && !bearer_token()) {
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

// image_path is a bare filename; resolve under the private uploads dir and
// guard against traversal.
$uploadsDir = rtrim((string) (cfg('uploads_dir') ?: (__DIR__ . '/../private_uploads')), '/');
$file = $uploadsDir . '/' . basename($imagePath);
if (!is_file($file)) {
    jfail('Image file missing.', 404);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file) ?: 'application/octet-stream';

header_remove('Content-Type');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=3600');
readfile($file);
exit;
