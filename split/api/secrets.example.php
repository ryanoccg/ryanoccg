<?php
/**
 * Template for secrets.php.
 *
 * PRODUCTION: copy to ~/app_private/secrets.php (OUTSIDE public_html / the
 * subdomain docroot) and fill in real values. Never commit the real file.
 *
 * LOCAL DEV: copy to split/api/secrets.php (gitignored) for `php -S`.
 */
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'cpaneluser_split',
        'user'    => 'cpaneluser_split',
        'pass'    => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],

    // 32+ random bytes, base64 or hex. Rotating it logs everyone out.
    'jwt_secret' => 'CHANGE_ME_LONG_RANDOM_STRING',

    // OpenAI — used server-side only by extract_receipt.php (gpt-5-mini).
    'openai_api_key' => 'sk-...',

    // Stripe (test keys for dev, live keys in production).
    'stripe_secret_key'     => 'sk_test_...',
    'stripe_webhook_secret' => 'whsec_...',
    'stripe_price_pro'      => 'price_...', // the Pro subscription price id

    // Public app URL (used for Stripe success/cancel redirects).
    'app_url' => 'https://split.ryanoccg.com',

    // Absolute path to a private uploads dir OUTSIDE any web root.
    'uploads_dir' => '/home/cpaneluser/app_private/uploads',

    // CORS: the browser origin allowed to call the API. Same-origin in prod;
    // set to the Vite dev origin (e.g. http://localhost:5173) during dev.
    'cors_origin' => 'https://split.ryanoccg.com',

    // Public AdSense client id (also exposed to the frontend build var).
    'adsense_client_id' => 'ca-pub-XXXXXXXXXXXXXXXX',

    // Shared secret guarding admin_set_plan.php (grant hidden 'super').
    'admin_secret' => 'CHANGE_ME_ADMIN_SECRET',
];
