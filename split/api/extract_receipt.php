<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

/**
 * OCR a receipt photo with OpenAI gpt-5-mini (structured outputs) and return
 * parsed fields to PRE-FILL the review screen. Nothing is committed here — the
 * user confirms/edits, then POSTs to receipts.php.
 *
 * Auth + guard: valid JWT, owns the target session, under the monthly OCR limit.
 * Input: multipart upload field `image` + `session_id`.
 * On success the OCR counter is incremented and the stored image path returned
 * so the client can attach it when saving the receipt.
 */

$user = require_user();
$uid = (string) $user['id'];

if (method() !== 'POST') {
    jfail('Method not allowed.', 405);
}

$sessionId = (string) ($_POST['session_id'] ?? '');
require_owned_session($sessionId, $uid);

guard_ocr_scan($user); // 402 if over the monthly limit

if (empty($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    jfail('No image uploaded.', 422);
}

$tmp = $_FILES['image']['tmp_name'];
$size = (int) ($_FILES['image']['size'] ?? 0);
if ($size <= 0 || $size > 10 * 1024 * 1024) {
    jfail('Image must be between 1 byte and 10 MB.', 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmp) ?: '';
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($allowed[$mime])) {
    jfail('Unsupported image type. Use JPEG, PNG, or WebP.', 422);
}

// Persist to the private uploads dir (outside any web root in production).
$uploadsDir = (string) (cfg('uploads_dir') ?: (__DIR__ . '/../private_uploads'));
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0700, true);
}
$filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
$dest = rtrim($uploadsDir, '/') . '/' . $filename;
if (!move_uploaded_file($tmp, $dest)) {
    jfail('Could not store the uploaded image.', 500);
}

$dataUrl = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($dest));

// Reserve the scan BEFORE calling OpenAI: closes the check-then-use race between
// concurrent requests and stops unlimited free retries from draining the budget.
increment_ocr($uid);

$parsed = openai_extract($dataUrl);
if ($parsed === null) {
    jout([
        'status'     => 'failed',
        'image_path' => $filename,
        'message'    => 'Could not read this receipt. Try a clearer photo or enter items manually.',
    ], 200);
}

$parsed['status'] = 'ready';
$parsed['image_path'] = $filename;
jout($parsed, 200);

/**
 * Calls OpenAI; returns the parsed associative array or null on failure.
 * Retries once on malformed JSON, then gives up gracefully.
 */
function openai_extract(string $dataUrl): ?array
{
    $apiKey = (string) cfg('openai_api_key', '');
    if ($apiKey === '') {
        return null;
    }

    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'merchant'  => ['type' => ['string', 'null']],
            'currency'  => ['type' => ['string', 'null']],
            'subtotal'  => ['type' => ['number', 'null']],
            'tax'       => ['type' => ['number', 'null']],
            'tip'       => ['type' => ['number', 'null']],
            'total'     => ['type' => ['number', 'null']],
            'line_items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'name'       => ['type' => 'string'],
                        'quantity'   => ['type' => 'number'],
                        'unit_price' => ['type' => 'number'],
                        'total'      => ['type' => 'number'],
                    ],
                    'required' => ['name', 'quantity', 'unit_price', 'total'],
                ],
            ],
            'confidence' => ['type' => 'number'],
        ],
        'required' => ['merchant', 'currency', 'subtotal', 'tax', 'tip', 'total', 'line_items', 'confidence'],
    ];

    $body = [
        'model' => 'gpt-5-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => "You transcribe a photo of a restaurant or store receipt into structured JSON. Be precise and literal.\n"
                    . "Rules:\n"
                    . "- Output EVERY purchased line item, in the order printed, with its name exactly as shown, quantity, per-unit price, and line total.\n"
                    . "- If the same item is printed on multiple lines (even with identical name, quantity and price), output a SEPARATE entry for EACH printed line — never merge, combine, or sum duplicate lines. The number of entries must match the number of printed item lines.\n"
                    . "- Do NOT output subtotal, tax, service charge, rounding adjustment, total, change, cash/card, or any non-item line as a line item.\n"
                    . "- If a quantity is printed, use it; otherwise quantity is 1. unit_price × quantity should equal the line total; if a discount makes them differ, keep the printed line total and set unit_price = total / quantity.\n"
                    . "- Numbers must be plain decimals with no currency symbols or thousands separators.\n"
                    . "- currency: the printed currency as a 3-letter ISO code (e.g. RM → MYR, \$ → USD, S\$ → SGD); null if unclear.\n"
                    . "- subtotal, tax, tip (gratuity/service charge) and total: take from the printed summary; use null when not shown.\n"
                    . "- confidence: 0..1 for how legible/clear the receipt was.\n"
                    . "- If the image is too blurry or not a receipt, return empty line_items and a low confidence.",
            ],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Transcribe this receipt into the required JSON schema.'],
                    // detail:high makes the model read at full resolution — important for small receipt text.
                    ['type' => 'image_url', 'image_url' => ['url' => $dataUrl, 'detail' => 'high']],
                ],
            ],
        ],
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => ['name' => 'receipt', 'strict' => true, 'schema' => $schema],
        ],
        'seed' => 7,                 // best-effort reproducibility across identical images
        'max_completion_tokens' => 4000,
    ];

    // Use the model's default reasoning — gpt-5-mini needs it to read the whole
    // receipt; lowering it tanked accuracy. Two attempts to tolerate malformed JSON.
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $content = openai_request($apiKey, $body);
        if ($content === null) {
            continue;
        }
        $parsed = json_decode($content, true);
        if (is_array($parsed)) {
            return normalize_extraction($parsed);
        }
    }
    return null;
}

/** Single HTTP call; returns the message content string or null. */
function openai_request(string $apiKey, array $body): ?string
{
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT => 60,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code < 200 || $code >= 300) {
        return null;
    }
    $json = json_decode($resp, true);
    return $json['choices'][0]['message']['content'] ?? null;
}

/** Coerce types + recompute obvious totals so the review screen starts sane. */
function normalize_extraction(array $p): array
{
    $num = static fn($v) => $v === null ? null : round((float) $v, 2);
    $items = [];
    foreach (($p['line_items'] ?? []) as $it) {
        $name = trim((string) ($it['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $qty = (float) ($it['quantity'] ?? 1);
        if ($qty <= 0) {
            $qty = 1;
        }
        $unit = round((float) ($it['unit_price'] ?? 0), 2);
        $total = isset($it['total']) ? round((float) $it['total'], 2) : round($qty * $unit, 2);
        $items[] = ['name' => $name, 'quantity' => $qty, 'unit_price' => $unit, 'total' => $total];
    }
    $currency = normalize_currency($p['currency'] ?? null, null);
    return [
        'merchant'   => ($p['merchant'] ?? null) ? trim((string) $p['merchant']) : null,
        'currency'   => $currency,
        'subtotal'   => $num($p['subtotal'] ?? null),
        'tax'        => $num($p['tax'] ?? null),
        'tip'        => $num($p['tip'] ?? null),
        'total'      => $num($p['total'] ?? null),
        'line_items' => $items,
        'confidence' => isset($p['confidence']) ? round((float) $p['confidence'], 2) : null,
    ];
}
