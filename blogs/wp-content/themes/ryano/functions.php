<?php
/**
 * Ryano Theme Functions
 * Custom blog theme matching Ryano's portfolio design
 */

// Enqueue styles and scripts
function ryano_scripts() {
    // Main stylesheet - versioned for cache busting
    wp_enqueue_style('ryano-style', get_stylesheet_uri(), array(), '2.2.0');

    // Google Fonts for better typography (optional)
    wp_enqueue_style('ryano-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap', array(), null);
}
add_action('wp_enqueue_scripts', 'ryano_scripts');

// Theme setup
function ryano_setup() {
    // Add title tag support
    add_theme_support('title-tag');

    // Add post thumbnails support with custom sizes
    add_theme_support('post-thumbnails');
    set_post_thumbnail_size(1200, 630, true); // Default thumbnail
    add_image_size('ryano-blog-card', 800, 450, true); // Blog card size
    add_image_size('ryano-hero', 1920, 1080, true); // Hero size

    // Add automatic feed links
    add_theme_support('automatic-feed-links');

    // Add HTML5 support
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'script',
        'style',
    ));

    // Add responsive embeds support
    add_theme_support('responsive-embeds');

    // Add custom logo support
    add_theme_support('custom-logo', array(
        'height'      => 50,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ));

    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'ryano'),
        'footer'  => __('Footer Menu', 'ryano'),
    ));

    // Add editor styles
    add_theme_support('editor-styles');
    add_editor_style('style.css');
}
add_action('after_setup_theme', 'ryano_setup');

// Excerpt length
function ryano_excerpt_length($length) {
    return 30;
}
add_filter('excerpt_length', 'ryano_excerpt_length');

// Excerpt more text
function ryano_excerpt_more($more) {
    return '...';
}
add_filter('excerpt_more', 'ryano_excerpt_more');

// Custom pagination
function ryano_pagination() {
    global $wp_query;

    $big = 999999999;

    $paginate = paginate_links(array(
        'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
        'format' => '?paged=%#%',
        'current' => max(1, get_query_var('paged')),
        'total' => $wp_query->max_num_pages,
        'prev_text' => '← Prev',
        'next_text' => 'Next →',
        'type' => 'array',
        'mid_size' => 2,
        'end_size' => 1,
    ));

    if ($paginate) {
        echo '<nav class="pagination" aria-label="Posts pagination">';
        foreach ($paginate as $page) {
            echo $page;
        }
        echo '</nav>';
    }
}

// Calculate reading time
function ryano_reading_time() {
    $content = get_post_field('post_content', get_the_ID());
    $word_count = str_word_count(strip_tags($content));
    $reading_time = ceil($word_count / 200); // 200 words per minute

    return $reading_time . ' min';
}

// Fallback menu if no menu is assigned
function ryano_fallback_menu() {
    echo '<nav class="site-nav">';
    echo '<ul>';
    echo '<li><a href="' . esc_url(home_url('/')) . '">Home</a></li>';
    echo '<li><a href="https://ryanoccg.com" target="_blank" rel="noopener noreferrer">Portfolio</a></li>';
    echo '<li><a href="https://ryanoccg.com/#contact" target="_blank" rel="noopener noreferrer">Contact</a></li>';
    echo '</ul>';
    echo '</nav>';
}

// Add viewport meta tag
function ryano_viewport_meta() {
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">';
}
add_action('wp_head', 'ryano_viewport_meta', 1);

// Google Analytics 4 (gtag.js) — match homepage tracking (G-XJ5EEW4SPG)
function ryano_google_analytics() {
    // Skip GA on local/staging
    if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE !== 'production') {
        return;
    }
    if (strpos($_SERVER['HTTP_HOST'] ?? '', '.ddev.site') !== false) {
        return;
    }
    ?>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-XJ5EEW4SPG"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-XJ5EEW4SPG');
    </script>
    <?php
}
add_action('wp_head', 'ryano_google_analytics', 1);

// Add preconnect for performance
function ryano_resource_hints($urls, $relation_type) {
    if ('dns-prefetch' === $relation_type) {
        $urls[] = array(
            'href' => 'https://fonts.googleapis.com',
        );
        $urls[] = array(
            'href' => 'https://fonts.gstatic.com',
        );
    }

    if ('preconnect' === $relation_type) {
        $urls[] = array(
            'href' => 'https://fonts.googleapis.com',
            'crossorigin' => 'anonymous',
        );
        $urls[] = array(
            'href' => 'https://fonts.gstatic.com',
            'crossorigin' => 'anonymous',
        );
    }

    return $urls;
}
add_filter('wp_resource_hints', 'ryano_resource_hints', 10, 2);

// Remove unnecessary WordPress head elements
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');

// Per-post SEO title/description overrides (id => [title, description])
// title: max 48 chars. ryano_custom_document_title() appends ' | Ryano Web'
//        (12 chars), so 48 is the budget that keeps the rendered <title> at 60.
//        Measuring the override alone is how three 62-68 char titles slipped through.
// description: 120-155 chars, include CTA
$ryano_seo_overrides = array(
    120 => array(
        'title'       => 'Bad Website Design in Malaysia: 20 Sites Tested',
        'description' => 'We tested 20 Malaysian SME homepages on mobile: none of the 19 measured had an LCP under 4 seconds. See what failed. WhatsApp +60174272807',
    ),
    14 => array(
        'title'       => 'Mobile Website Malaysia: Stop Losing 72%',
        'description' => '72% of Malaysians browse phone-first (MCMC). A site that fights them on mobile loses sales daily. The 2026 mobile-first fixes. WhatsApp +60174272807',
    ),
    126 => array(
        'title'       => 'Core Web Vitals 2026: 3 Metrics to Pass',
        'description' => 'Failed Core Web Vitals = Page 2 rankings. The 3 metrics Google now demands in 2026, with exact fixes for Malaysian sites. Free audit: WhatsApp +60174272807',
    ),
    22 => array(
        'title'       => 'Website Maintenance Malaysia: 2026 Costs',
        'description' => 'What website maintenance really costs in Malaysia (2026): monthly plans, hidden fees, and which tier your site needs. Free quote: WhatsApp +60174272807',
    ),
    26 => array(
        'title'       => 'Web Design Malaysia: What Converts',
        'description' => 'What separates web design that converts from design that just looks nice, plus the practical 2026 fixes that lift enquiries. WhatsApp +60174272807',
    ),
    187 => array(
        'title'       => 'Website Maintenance Company: How to Pick',
        'description' => 'Pick a website maintenance company that protects your site, not one that just bills you. The questions to ask and the red flags. WhatsApp +60174272807',
    ),
    191 => array(
        'title'       => 'Ecommerce Website Price in Malaysia 2026',
        'description' => 'What an online store really costs to build and run in 2026: build tiers (RM5k-25k), gateway fees, and hidden monthly costs. WhatsApp +60174272807',
    ),
    198 => array(
        'title'       => 'AI Website Builder or Professional Development?',
        'description' => 'AI website builder or professional development? An honest 2026 breakdown of cost, SEO, and ownership. Free advice: WhatsApp +60174272807',
    ),
    // 2026-07-27: post 176 had no override despite being the site's highest-impression
    // blog page (518 impressions/30d). It owns the generic "plan/package" query cluster
    // (website maintenance plan, pos 31.9) while pillar 22 holds the geo terms, so lead
    // the title with the exact generic keyword. No cannibalisation with 22.
    176 => array(
        'title'       => 'Website Maintenance Plan: What It Covers',
        'description' => 'What a real website maintenance plan covers, what each tier costs, and the line items cheap plans quietly skip. Free review: WhatsApp +60174272807',
    ),
    // 2026-08-05: posts 20 and 218 are the two best-ranking pages on the site (pos 8.0
    // and 6.5) but were the only top-10 pages still falling back to get_the_excerpt(),
    // so their SERP snippet was a truncated first paragraph ending in an ellipsis.
    // Every other page sits at pos 30-70 where expected CTR is ~0, making these two the
    // only places a snippet rewrite can actually move clicks.
    20 => array(
        'title'       => 'How to Rank on Google Page 1 in Malaysia',
        'description' => 'Google Business Profile, location keywords, speed, mobile, then local links. Most local searches reach page 1 in 3 to 6 months. WhatsApp +60174272807',
    ),
    218 => array(
        'title'       => 'Signs It Is Time to Redesign Your Website',
        'description' => 'Traffic holding steady while enquiries fall is the clearest sign. Score your own site before you spend on a redesign. Free review: WhatsApp +60174272807',
    ),
    // 2026-08-21: posts 257, 262, 265 scheduled for 26 Aug, 2 Sep and 9 Sep.
    257 => array(
        'title'       => 'Shopify Pricing Malaysia: What You Actually Pay',
        'description' => 'Plan rates, the monthly versus yearly gap, and the extra transaction fee that applies wherever Shopify Payments is unavailable. With the maths.',
    ),
    262 => array(
        'title'       => 'Hosting and Domain Cost After the First Year',
        'description' => 'Hosting and domains are sold at one price and renewed at another. Real renewal figures, which line items should be free, and what a year settles at.',
    ),
    265 => array(
        'title'       => 'What AI Website Builders Cannot Build',
        'description' => 'AI can produce a working website in an afternoon. Integrations, undocumented business rules, payment compliance and accountability are what remain.',
    ),
    // 2026-09-08: the last five posts still falling back to get_the_excerpt(), so their
    // SERP snippet was a truncated first paragraph ending mid-sentence. Posts 221, 230
    // and 235 predate the 2026-08-05 publisher fix; 12 and 18 predate overrides entirely.
    // No WhatsApp CTA here, matching the newer 257/262/265 convention.
    12 => array(
        'title'       => 'WordPress or Custom Build? A Malaysian Guide',
        'description' => 'Most Malaysian SMEs should use WordPress. The specific cases where custom development earns its cost, with local price comparisons for both.',
    ),
    18 => array(
        'title'       => 'E-Commerce Website Malaysia: 2026 Setup Guide',
        'description' => 'WooCommerce, Shopify or marketplace? Platform costs, the features that matter, a 30-day launch checklist, and Malaysian legal requirements.',
    ),
    221 => array(
        'title'       => 'Website Backups: What Actually Protects You',
        'description' => 'Having backups and having a recovery you can trust are different things. What a real backup covers, and the restore test almost nobody runs.',
    ),
    230 => array(
        'title'       => 'Traffic Down, Rankings Flat? Blame AI Answers',
        'description' => 'Impressions steady, clicks down a third. AI answers sit above your listing and absorb the visit. What changed, and how to get cited instead.',
    ),
    235 => array(
        'title'       => 'Why Visitors Leave Your Website on Their Phones',
        'description' => 'Speed, tap targets, and the friction you cannot see from a desk. How to test your site like a real mobile visitor, and what to fix first.',
    ),
    // 2026-09-15: the ten posts that never had an override. Their <title> fell back to
    // the post title plus ' | Ryano Web' and rendered at 62-88 chars, so Google was
    // truncating the part that carries the keyword. Each title below leads with the
    // exact query the page ranks for, then drops the trailing qualifiers.
    //
    // Post 115's description also carried an em dash. It came from get_the_excerpt(),
    // which mirrors the body text, so the override is what fixes it in the SERP; the
    // body copy itself is still to be cleaned separately.
    136 => array(
        'title'       => 'WordPress Website Maintenance Malaysia 2026',
        'description' => 'What WordPress maintenance really costs in Malaysia (2026): plugin updates, core upgrades, security patches, and the costs that break SME budgets.',
    ),
    137 => array(
        'title'       => 'Ecommerce Website Maintenance Malaysia 2026',
        'description' => 'The maintenance checklist for WooCommerce and Shopify stores in Malaysia, real monthly costs, and how to prevent a checkout outage.',
    ),
    115 => array(
        'title'       => 'AI Chatbots for Small Business: Worth It?',
        'description' => 'AI chatbots in 2026 do more than auto-replies. They book calls, qualify leads and close sales. What works, what does not, and what it costs.',
    ),
    8 => array(
        'title'       => 'Website Cost in Malaysia: 2026 Price Guide',
        'description' => 'Complete breakdown of website costs in Malaysia for 2026. From RM2,000 landing pages to RM50,000+ custom systems. Get transparent pricing.',
    ),
    76 => array(
        'title'       => 'Why Your Malaysian Website Is Slow (2026)',
        'description' => 'The five most common causes of slow websites in Malaysia, how to measure yours in ten minutes, and exactly how to fix each one in 2026.',
    ),
    243 => array(
        'title'       => 'WhatsApp Business API: What It Costs',
        'description' => 'Per-message pricing, which template category is free, and why the timing of a notification decides whether you pay for it. Honestly costed.',
    ),
    104 => array(
        'title'       => 'Website Security Checklist for 2026',
        'description' => '53% of small businesses have no cybersecurity plan. Use this checklist to protect your website from hackers, malware, and data breaches in 2026.',
    ),
    24 => array(
        'title'       => 'Landing Page or Full Website? (Malaysia)',
        'description' => 'Landing page or full website? The differences, costs (RM 2,000 vs RM 10,000), when to use each, and real Malaysian business cases.',
    ),
    10 => array(
        'title'       => '5 Signs Your Website Needs a Redesign',
        'description' => 'The five warning signs your Malaysian business needs a website redesign, plus real before and after cases with cost estimates.',
    ),
    16 => array(
        'title'       => 'How to Choose a Web Developer in Malaysia',
        'description' => 'Hiring a web developer in Malaysia: the vetting checklist, red flags, interview questions, and how agency and freelancer pricing compares.',
    ),
    // 2026-09-15: post 277, scheduled for 16 Sep. The post title runs 56 chars, which
    // renders at 68 once ' | Ryano Web' is appended, so it needs an override from day one.
    277 => array(
        'title'       => 'Taking Over a Website From Your Developer',
        'description' => 'The five areas of control that decide whether a website is really yours, how to verify each one, and the transfer order that can save you a two-month wait.',
    ),
);

// Add Open Graph meta tags + <meta name="description"> for better SEO
function ryano_og_meta_tags() {
    if (is_single()) {
        global $post, $ryano_seo_overrides;

        $post_id = get_the_ID();
        $override = isset($ryano_seo_overrides[$post_id]) ? $ryano_seo_overrides[$post_id] : array();

        $og_title       = get_the_title();
        $og_description = !empty($override['description']) ? $override['description'] : get_the_excerpt();
        $og_url         = get_permalink();
        $og_image       = has_post_thumbnail() ? get_the_post_thumbnail_url(null, 'full') : get_template_directory_uri() . '/assets/hero-bg.png';

        echo '<meta name="description" content="' . esc_attr($og_description) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($og_description) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($og_url) . '">' . "\n";
        echo '<meta property="og:image" content="' . esc_url($og_image) . '">' . "\n";
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";

        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($og_title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($og_description) . '">' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url($og_image) . '">' . "\n";
    }
}
add_action('wp_head', 'ryano_og_meta_tags');

// Per-post FAQPage structured data (id => [ [q, a], ... ])
// Google stopped showing FAQ rich results on 2026-05-07, so the payoff here is
// machine-readable Q&A for answer and AI engines, not a richer SERP listing.
// Google's policy requires the marked-up content to be visible on the page and
// not misleading. This file goes further by convention: answers are a word-for-word
// copy of the visible text, which makes "does it still match?" a diff, not a judgement.
$ryano_faq_schema = array(
    198 => array(
        array('Is an AI website builder good for SEO?', 'It can handle the basics, but it caps out. You get less control over structured data, page speed, and URL structure than a custom build, so an AI builder is fine for a simple brochure site and limiting once ranking becomes a real goal.'),
        array('Can I move my site off an AI website builder later?', 'Sometimes, but rarely cleanly. Most builders let you export raw content, not the design or functionality, so a move usually means rebuilding. That lock-in is the main reason to think about ownership before you commit, not after.'),
        array('Is professional web development worth the higher cost?', 'If your website is a core revenue channel, yes. The higher upfront fee buys custom features, full SEO control, and a portable stack you own outright, which is what keeps costs predictable as you scale instead of climbing with every plan tier.'),
    ),
    14 => array(
        array('Do I need a separate mobile website?', 'No. One responsive website that adapts is better than two separate sites.'),
        array('Will mobile-first hurt my desktop experience?', 'No. Mobile-first means starting with mobile, then enhancing for desktop. Desktop still looks great.'),
        array('How do I test mobile design without a developer?', 'Use Chrome DevTools mobile simulator or sites like BrowserStack.'),
        array('Should I build a mobile app instead?', 'Only if you need app-specific features (push notifications, offline mode). Most businesses just need a mobile-first website.'),
        array('What about tablets?', 'Mobile-first responsive design automatically handles tablets too.'),
        array('How often should I update for new phone sizes?', 'Responsive design automatically adapts. No updates needed for new phones.'),
    ),
    18 => array(
        array('Can I start with Shopee/Lazada instead?', 'Yes, great for testing products. But you don’t own the customer relationship. Use marketplaces + your own site.'),
        array('Do I need to hold inventory?', 'Not necessarily. Dropshipping is an option, but margins are lower and you have less control.'),
        array('How long until I make money?', 'Most stores take 3-6 months to become profitable. First months are learning and building audience.'),
        array('Can I sell internationally?', 'Yes, but consider: shipping costs, customs, returns logistics, payment gateways (Stripe or PayPal).'),
        array('Should I offer COD (Cash on Delivery)?', 'Pros: Customers trust it, especially in rural areas Cons: 15-25% return rate, courier charges extra Recommendation: Offer it but encourage online payment (discount for prepayment)'),
    ),
    // 22 (website-maintenance-malaysia) intentionally NOT in this array:
    // its FAQPage is emitted by ryano_pillar_maintenance_schema() below.
    // Keeping it here too would output two FAQPage blocks on one URL,
    // which makes Google ignore both.
    187 => array(
        array('Is a website maintenance company worth it for a small business?', 'Yes, if the site earns or represents revenue. The cost of one serious hack or a week of downtime almost always exceeds a year of maintenance fees, and recovery is far more expensive than prevention.'),
        array('Can I just use my hosting provider for maintenance?', 'Usually not fully. Most hosts maintain the server, not your specific website. Managed WordPress hosting is the exception, and even then it rarely covers plugin conflicts or content fixes.'),
        array('How do I switch maintenance companies safely?', 'Confirm in writing that you own your domain, hosting, and admin access before signing with anyone new. As long as you hold those, switching is low risk and no provider can hold your site hostage.'),
        array('What is the difference between a freelancer and a maintenance company?', 'A freelancer is cheaper and more personal but is a single point of failure if they get busy or stop replying. A company costs more but usually offers documented processes and backup coverage when one person is unavailable.'),
        array('How often should website maintenance actually happen?', 'Continuously, not yearly. Software updates arrive every few weeks, so a site checked once a year spends most of that year exposed.'),
    ),
    120 => array(
        array('What makes a website design bad?', 'A website design is bad when it stops a ready buyer from acting. In our October 2026 test of 20 Malaysian SME homepages, the most common problems were slow mobile loading (all 19 measurable sites had a Largest Contentful Paint over 4 seconds), hidden prices and out-of-date copyright years.'),
        array('How slow is too slow for a homepage?', 'Google\'s web.dev guidance treats a Largest Contentful Paint of 2.5 seconds or less as good and anything above 4.0 seconds as poor, measured at the 75th percentile of visits on mobile and desktop.'),
        array('Should I show prices on my website?', 'Usually yes, at least as a starting price or a typical range. In our test, every aircon service and bakery showed a ringgit figure, while none of the renovation, dental or accounting sites did, which leaves price-comparing visitors with nothing to anchor on.'),
        array('Does an old copyright year really matter?', 'It costs nothing to fix and removes a doubt. A visitor who sees © 2020 in 2026 may wonder whether the business is still operating, right when they are deciding whether to get in touch.'),
        array('How can I test my website\'s speed for free?', 'Paste your homepage into Google\'s PageSpeed Insights and read the mobile results. Lighthouse, which powers it, is also built into Chrome\'s developer tools if you want to run it yourself.'),
    ),
    191 => array(
        array('How much does an online store cost in Malaysia?', 'Most small to mid stores cost RM5,000 to RM25,000 to build, plus RM300 to RM800 a month to run.'),
        array('Is Shopify or WooCommerce cheaper?', 'Shopify has a fixed monthly fee but less setup. WooCommerce has no platform fee but needs hosting and maintenance. WooCommerce is usually cheaper at scale.'),
        array('Why is an e-commerce website more expensive than a normal website?', 'Because it has to process payments, manage stock, and secure customer data, which adds engineering a brochure site never needs.'),
        array('What ongoing costs does an online store have?', 'Payment gateway fees (around 2.5 to 3 percent per card sale), hosting, platform or plugin subscriptions, and a maintenance plan.'),
        array('Do these prices apply outside Malaysia?', 'The figures are in RM, but the cost drivers are the same anywhere: checkout, payments, hosting, and upkeep. Swap the currency and the logic still holds.'),
    ),
    126 => array(
        array('Does fixing Core Web Vitals guarantee higher rankings?', 'No, but it removes a known ranking penalty. Google has confirmed Core Web Vitals are a ranking signal, but content quality and backlinks still carry more weight. Speed fixes give you a fairer fight, not a free win.'),
        array('How long does optimization take?', 'A standard small business site can be optimized in 5-10 working days. The 75th percentile data in Search Console takes 28 days to fully update, so the visible improvement lands 4-6 weeks after the work is done.'),
        array('Should I switch hosts just for speed?', 'If your TTFB is consistently over 800ms, yes. Hosting upgrades have the largest single impact on every other metric, because everything else stacks on top of the server response time.'),
        array('Will a faster site really increase sales?', 'Yes, if the funnel is otherwise healthy. Speed cannot fix a confusing checkout or a weak product, but if you already have traffic and decent conversions, every 100ms of improvement should produce a measurable lift.'),
        array('Is INP harder than the old FID?', 'Yes. FID only measured the first interaction; INP measures every interaction throughout the visit. Sites that easily passed FID are now failing INP, especially e-commerce stores and sites with heavy interactive features.'),
    ),
    20 => array(
        array('How do I rank on Google page 1 in Malaysia?', 'Start with your Google Business Profile, add clear location keywords to your key pages, make the site fast and mobile-friendly, then earn a few links from trusted local sites. Most local searches reach page 1 in 3 to 6 months of consistent work, sooner for low-competition keywords.'),
        array('How long does SEO take?', 'Typically 3-6 months to see significant results. Local SEO can show results faster (4-8 weeks).'),
        array('Can I do SEO myself?', 'Yes, basic SEO is learnable. But it’s time-intensive and technical. Most businesses find outsourcing more cost-effective.'),
        array('Does social media help SEO?', 'Indirectly. Social signals don’t directly affect ranking, but social traffic and engagement help.'),
        array('Should I focus on Google or other search engines?', 'Google dominates 96% of search in Malaysia. Focus there first.'),
        array('What’s the difference between SEO and SEM?', 'SEO = organic (free) rankings. SEM = paid ads (Google Ads). Both important, different strategies.'),
        array('Can I guarantee #1 ranking?', 'No. Anyone who guarantees #1 is lying. Google’s algorithm is complex and constantly changing. Ethical SEO promises improvement, not guarantees.'),
    ),
    8 => array(
        array('Can I build a website myself for free?', 'Yes, using Wix or WordPress.com, but you’ll have limitations (ads, restricted features, no custom domain). For a professional business site, expect to spend at least RM 2,000.'),
        array('Do I own the website after paying?', 'Yes, if the developer uses WordPress or open-source platforms. Always clarify ownership before starting.'),
        array('What if I need changes after launch?', 'Most developers offer 1-3 months of minor updates included. After that, expect RM 100-300/hour for changes.'),
        array('Is it cheaper to hire overseas developers?', 'Sometimes, but communication delays, time zone differences, and quality issues often make it not worth the savings.'),
        array('How long does it take to build a website?', 'Simple site: 1-2 weeks Business website: 2-4 weeks E-commerce: 4-8 weeks Custom system: 2-6 months'),
    ),
    // 2026-07-27: post 76 had no FAQPage schema (only gap of 14 audited pages).
    // Mirrors the visible "Questions worth answering directly" block verbatim;
    // the page body was em-dash cleaned first so schema and visible text match.
    76 => array(
        array('How much does this cost?', 'Better hosting (RM 50-100/month for a VPS) plus optionally a premium caching plugin like WP Rocket (around RM 150/year). Cloudflare CDN’s free tier covers most SME needs. Total: under RM 100/month above whatever you are already spending.'),
        array('Will speed actually improve my Google ranking?', 'Yes. Page speed is a confirmed ranking factor for both desktop and mobile. More importantly, a faster site keeps visitors engaged longer, which signals quality to Google.'),
        array('How long does the fix take?', 'Basic fixes (compression, caching, Cloudflare) can be done in a few hours. Full audit and optimization with hosting migration usually takes 1-3 days.'),
        array('My site was fast at launch but has slowed down. Why?', 'Extremely common with WordPress. Adding plugins, content, and images over time cumulatively bloats the site. A 1.2MB launch can easily reach 4-5MB after two years without active maintenance. Regular speed audits every six months prevent this drift. Most professional website maintenance Malaysia plans include monthly performance optimization.'),
        array('Should I rebuild the site?', 'Rarely the first step. The five fixes above usually deliver dramatic improvements without a rebuild. A rebuild is warranted only if the underlying theme is fundamentally inefficient or the codebase is unsalvageable.'),
    ),
    // 2026-08-05: post 243, mirrors the visible FAQ block verbatim.
    243 => array(
        array('How much does the WhatsApp Business API cost per message?', 'Meta prices per delivered template message by category and country, in small per-message amounts rather than a flat monthly fee. There are three categories: marketing, utility, and authentication. Marketing always costs money. Utility is free inside an open customer service window, so your bill depends more on when messages fire than how many you send.'),
        array('Is the WhatsApp Business API free?', 'No, but a large share of messages can be. Since 1 July 2025 Meta charges per delivered template. Non-template messages and utility templates delivered inside an open customer service window are free, and that window opens whenever the customer messages you first. Marketing templates are charged regardless.'),
        array('What is the difference between WhatsApp Business and the WhatsApp Business API?', 'WhatsApp Business is a free app a person uses on a phone. The API has no app or inbox. It connects to your own system so messages send automatically when something changes, and it can be wired to a shared inbox or CRM so several people work from one number.'),
        array('How long does it take to set up?', 'Plan for a couple of weeks before going live. Meta business verification, number verification, and template approval all take time, and templates are sometimes rejected on the first attempt. The integration work itself is usually shorter than the approval waiting.'),
        array('Do customers have to opt in?', 'Yes. Meta requires opt-in before you send template messages, and the opt-in has to make clear the customer will receive WhatsApp messages from your business. Collecting it at the point of sale, on the job sheet or booking form, is the least awkward moment.'),
        array('Can it work with my existing system?', 'Usually. The API is a standard integration, so anything that can send a web request can trigger a message. The real question is whether your system knows when a job changes stage. If that information only lives in someone\'s head, that gets built first.'),
    ),
    // 2026-08-05: post 248, scheduled for 2026-08-12. Mirrors the visible FAQ block verbatim.
    248 => array(
        array('What is a customer status page?', 'A public web page showing the current stage of a customer\'s job or order, reachable by a permanent link with no login. It answers the question they would otherwise phone to ask, in plain language rather than internal terminology.'),
        array('Does a customer status page need a login?', 'Often not, and requiring one is a common reason these projects stall. A long unguessable link per job is enough only when the page shows a stage and a rough date, with no personal details, addresses, amounts, or identity data. Anything beyond that needs proper authentication.'),
        array('How much does it cost to build?', 'It depends almost entirely on whether your system already records job stages. If it does, this is a small build. If stages currently live in people\'s heads or a spreadsheet, most of the cost is capturing them properly, and the page itself is the cheap part.'),
        array('Will it actually reduce support calls?', 'It reduces one specific kind: the repeated "where is my order" call. It does nothing for change requests, complaints, or quote chasing. Listen to a week of your actual calls before assuming which kind you have.'),
        array('Should it send notifications too?', 'They work better together. The notification gets the page looked at, and the page means the notification does not have to carry all the detail. Sending updates as utility messages rather than marketing ones also keeps the messaging cost down.'),
        array('What if my job stages change often?', 'Design the page around a small number of stable stages rather than every internal step. Customers want to know roughly where their job sits and what happens next, not your full process. Fewer, stabler stages age better and need less maintenance.'),
    ),
    // 2026-08-21: post 257, scheduled for 2026-08-26. Mirrors the visible FAQ block verbatim.
    257 => array(
        array('Is Shopify Payments available in Malaysia?', 'No. Malaysian merchants use a third-party gateway such as Stripe, iPay88 or Billplz, and Shopify applies an additional transaction fee of 2% on Basic, 1% on Grow and 0.6% on Advanced on top of the gateway\'s own rate.'),
        array('Why is my Shopify bill higher than the advertised price?', 'The advertised price is the annual rate. Monthly billing costs about a third more on every plan, and app subscriptions plus movement in the USD exchange rate add to the total.'),
        array('Which Shopify plan is cheapest overall?', 'It depends on volume. On monthly billing, Basic is cheaper below roughly US$4,000 in monthly sales, near RM17,000. On yearly billing the crossover is about US$3,000. Above it, Grow\'s lower transaction fee outweighs the higher subscription.'),
        array('Can I avoid Shopify\'s third-party transaction fee?', 'Not when accepting card payments through an external gateway from a country where Shopify Payments is unavailable. Manual methods such as bank transfer or cash on delivery fall outside it, which is part of why they stay common locally.'),
    ),
    // 2026-08-21: post 262, scheduled for 2026-09-02. Mirrors the visible FAQ block verbatim.
    262 => array(
        array('Why did my hosting bill suddenly go up?', 'Your promotional term ended. Introductory rates usually apply to a prepaid multi-year term, after which the account renews at the standard rate. On the plans checked here that renewal was around three to four times the promotional rate.'),
        array('Should I pay for an SSL certificate?', 'Usually not much. Let\'s Encrypt provides free, automatically renewing certificates that browsers trust, and most reputable hosts install them at little or no charge. Paid certificates mainly matter for organisation-validated or extended-validation requirements.'),
        array('Is it cheaper to prepay hosting for several years?', 'Cheaper per month, yes. It also commits you to one provider for four years, which is a long time to bet on a setup you have not launched yet, so weigh the saving against the chance of outgrowing the plan.'),
        array('What is the cheapest way to keep a domain long term?', 'An at-cost registrar. Providers such as Cloudflare Registrar pass through only the registry and ICANN fees with no markup, so there is no first-year discount to be recovered later.'),
    ),
    // 2026-08-21: post 265, scheduled for 2026-09-09. Mirrors the visible FAQ block verbatim.
    265 => array(
        array('Can AI replace a web developer?', 'For static, self-contained websites, largely yes. Where a project is dominated by integrations, undocumented business logic, payment compliance or ongoing accountability, the remaining work is defining requirements and connecting systems, and that is where the effort concentrates.'),
        array('Is it cheaper to build a website with AI than hire a freelancer?', 'For a brochure site, yes, and often by a wide margin on the initial build. The comparison narrows once the site has to connect to other systems, because that work is priced by complexity rather than page count and does not shrink when the pages get generated.'),
        array('What should I use an AI website builder for?', 'Validating an idea, launching a campaign page quickly, or running a small service business site that does not integrate with anything. Those are genuine wins, not compromises.'),
    ),
    // 2026-09-08: posts 10, 12 and 16 carry real visible FAQ sections but had no schema.
    // Listed questions are the ones answered by visible prose, copied verbatim. The
    // question-shaped headings left out are answered only by a table or bullet list
    // (post 10 "What Should a Redesign Cost in Malaysia?", post 12 "What is WordPress?",
    // post 16 "How long should it take to build a website?"): flattening those into one
    // answer string would put text in the markup that no reader sees in that form.
    // Posts 24 and 104 stay absent on purpose: neither has a visible FAQ to mirror.
    10 => array(
        array('How long does a redesign take?', 'Typically 4-8 weeks for a business website. E-commerce or custom features can take 8-12 weeks.'),
        array('Will I lose my Google rankings?', 'Not if done properly. A good developer will set up 301 redirects and maintain your SEO structure.'),
        array('Can I keep my current domain?', 'Yes, absolutely. You keep your domain and just point it to the new website.'),
        array('What happens to my old website?', 'Most developers keep a backup. You can also request a copy before launch.'),
        array('Do I need new content/photos?', 'Not necessarily, but fresh content usually performs better. Many redesigns reuse 60-70% of old content.'),
    ),
    12 => array(
        array('Is WordPress secure?', 'Yes, if maintained properly. WordPress itself is secure. Issues come from outdated plugins or weak passwords. Regular website maintenance keeps those risks in check.'),
        array('Can WordPress handle high traffic?', 'Yes. CNN, TechCrunch, and major sites use WordPress. Proper hosting and caching are key.'),
        array('Will I own the website?', 'With WordPress: Yes, completely. With custom: Depends on contract, so always negotiate source code ownership.'),
        array('Can I switch developers later?', 'WordPress: Easy. Custom: Harder, depends on technology used.'),
        array('What about Wix or Squarespace?', 'Good for very small businesses (< RM 50k/year revenue). For serious businesses, WordPress or custom is better.'),
        array('Do I need to know coding?', 'WordPress: No. Custom: No, but technical knowledge helps with communication.'),
    ),
    16 => array(
        array('Should I hire local or overseas?', 'Local advantages: easier communication, same timezone, can meet in person, understands Malaysian market. Usually worth the slight premium.'),
        array('What if the developer disappears?', 'This is why contracts and source code access matter. Choose developers with established track records.'),
        array('Can I negotiate the price?', 'Yes, but focus on scope instead. “What can we remove to hit RM X budget?” works better than “Give me 20% discount.”'),
        array('How do I know if the price is fair?', 'Get 2-3 quotes. If one is way lower/higher, ask why. Average of 3 quotes is usually fair market rate.'),
        array('Should I pay hourly or fixed price?', 'Fixed price for defined projects. Hourly for ongoing maintenance or unclear scope.'),
    ),
    // 2026-09-15: post 277, scheduled for 16 Sep. Answers extracted from the published
    // post body rather than retyped, so the mirror holds by construction.
    277 => array(
        array('Can a developer legally keep my domain name?', 'It depends on the contract, who is recorded as registrant, and the law that applies. If they are listed as the registrant, the registration is in their name and you may need to rely on contractual terms or a formal dispute process rather than a simple request. Being merely the Administrative Contact gives practical control without ownership, which is a different and usually easier problem.'),
        array('My developer says the site is on their own hosting. Is that a problem?', 'It works while the relationship does and becomes a problem when it ends. Shared reseller hosting often has no way to give you independent, permanent access, so plan on migrating to an account billed in your own name.'),
        array('I only have WordPress admin. What can I still do?', 'More than people assume. On most hosts you can install a backup plugin and take a real copy of the database and files. What you still lack are the accounts underneath, which may be spread across a registrar, a DNS provider, an email provider and a host, so treat this as protecting your content while you chase the rest.'),
        array('Should I change all the passwords immediately?', 'Change them, but check the recovery email and two-factor settings first. A password change alone does not remove someone who can still trigger a reset.'),
    ),
);

// Emit FAQPage JSON-LD on posts that have FAQ entries defined above
function ryano_faq_jsonld() {
    if (!is_single()) return;
    global $ryano_faq_schema;
    $post_id = get_queried_object_id();
    if (empty($ryano_faq_schema[$post_id])) return;

    $entities = array();
    foreach ($ryano_faq_schema[$post_id] as $pair) {
        $entities[] = array(
            '@type'          => 'Question',
            'name'           => $pair[0],
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text'  => $pair[1],
            ),
        );
    }
    $schema = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $entities,
    );
    echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
}
add_action('wp_head', 'ryano_faq_jsonld');

// Shorten <title> tag — default "Post Title – Site Name" appends 31+ chars
// New format: "SEO Title | Ryano Web" (keeps total under ~65 chars)
function ryano_custom_document_title($title) {
    global $ryano_seo_overrides;
    if (is_single()) {
        $post_id  = get_queried_object_id();
        $override = isset($ryano_seo_overrides[$post_id]) ? $ryano_seo_overrides[$post_id] : array();
        $seo_title = !empty($override['title']) ? $override['title'] : get_the_title();
        return $seo_title . ' | Ryano Web';
    }
    return $title;
}
add_filter('pre_get_document_title', 'ryano_custom_document_title');

// Add custom body classes
function ryano_body_classes($classes) {
    // Add class for single posts
    if (is_single()) {
        $classes[] = 'single-post-page';
    }

    // Add class for blog index
    if (is_home() || is_archive()) {
        $classes[] = 'blog-index-page';
    }

    // Add class if has post thumbnail
    if (has_post_thumbnail()) {
        $classes[] = 'has-thumbnail';
    }

    return $classes;
}
add_filter('body_class', 'ryano_body_classes');

// Improve WordPress image loading with lazy loading
function ryano_add_lazy_loading($attr, $attachment, $size) {
    $attr['loading'] = 'lazy';
    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'ryano_add_lazy_loading', 10, 3);

// Add structured data for blog posts
function ryano_structured_data() {
    if (is_single()) {
        global $post;

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => get_the_title(),
            'description' => get_the_excerpt(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            // sameAs links blog author/publisher to the brand entity defined on the
            // homepage (Person + ProfessionalService), so answer/generative engines
            // resolve them as one entity instead of an anonymous byline. (GEO)
            'author' => array(
                '@type' => 'Person',
                'name' => 'Ryano Chu',
                'url' => 'https://ryanoccg.com',
                'sameAs' => array(
                    'https://www.linkedin.com/in/ryano-chu-chee-guan-76307079/',
                    'https://www.instagram.com/ryanoccg/',
                ),
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => 'Ryano Web',
                'url' => 'https://ryanoccg.com',
                'sameAs' => array(
                    'https://www.linkedin.com/in/ryano-chu-chee-guan-76307079/',
                    'https://www.instagram.com/ryanoccg/',
                ),
            ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => get_permalink(),
            ),
        );

        if (has_post_thumbnail()) {
            $schema['image'] = array(
                '@type' => 'ImageObject',
                'url' => get_the_post_thumbnail_url(null, 'full'),
            );
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }
}
add_action('wp_head', 'ryano_structured_data');

// Pillar-specific structured data for post 22 (website-maintenance-malaysia)
// FAQPage + HowTo + Service schemas — boost rich result eligibility for the highest-impression page
function ryano_pillar_maintenance_schema() {
    if (!is_single(22)) {
        return;
    }

    // FAQPage schema (12 Q&As mirroring the on-page FAQ section)
    $faqs = array(
        array('Can I do website maintenance myself?', 'Yes, if you are technical and have 3-5 hours per month available. Non-technical owners or busy Malaysian SMEs almost always save money by outsourcing: the cost of one missed update can exceed a year of maintenance fees.'),
        array('What if I haven\'t maintained my site in years?', 'Start with a security audit (RM 1,500-3,000) to find vulnerabilities and backdoors. Once the site is clean, move to regular monthly maintenance to prevent the issue from recurring.'),
        array('My developer said maintenance isn\'t necessary. Is that true?', 'No. Every website needs ongoing maintenance. A developer claiming otherwise is either inexperienced or hoping you\'ll pay for expensive emergency fixes later. WordPress, plugins, themes, and PHP all receive security patches every few weeks.'),
        array('Can\'t my hosting company handle maintenance?', 'Most cannot. Shared hosting providers handle server-level updates only. Managed WordPress hosts (WP Engine, Kinsta) cover core updates but not your plugins, themes, or content. Maintenance is a separate responsibility.'),
        array('What happens if I cancel maintenance?', 'Your site becomes gradually more vulnerable. Within 3-6 months you will likely have outdated plugins with public CVEs, no recent backup, and broken SSL renewal. The risk of being hacked rises sharply after month 4.'),
        array('Is yearly maintenance better than monthly?', 'No. "Yearly maintenance" means 11 months of vulnerability. Security patches and plugin updates need to be applied weekly, not annually. Monthly retainer plans exist for this reason.'),
        array('Does WordPress maintenance differ from Shopify maintenance?', 'Yes, significantly. WordPress requires hands-on plugin, theme, and core updates (2-4 hours/month) because you own the stack. Shopify handles the platform itself, so your maintenance focuses on app audits, theme tweaks, and storefront optimization (1-2 hours/month).'),
        array('Can maintenance fees be claimed as a business expense in Malaysia?', 'Yes. Website maintenance is a recurring business expense and qualifies as a deduction under your company tax filing with LHDN. Keep monthly invoices, as both digital and printed receipts are accepted.'),
        array('How soon after launching a new website do I need maintenance?', 'Day one. WordPress core, plugins, and themes start receiving updates within weeks of any launch. A 3-month-old unmaintained site is just as exposed as a 3-year-old one.'),
        array('Is switching maintenance providers risky?', 'Only if your current provider holds your assets hostage. Before signing up, confirm in writing: you own the domain, you have admin-level access to hosting and the site, and there is no exit fee. A clean handover should take less than 48 hours.'),
        array('Can I pause maintenance for one or two months?', 'Technically yes, practically no. WordPress plugin vulnerabilities are exploited within hours of public disclosure. A 60-day pause is enough exposure to get hit by routine mass-scan attacks. Downgrade to a lower tier instead of pausing.'),
        array('Can I mix DIY and professional maintenance?', 'Yes, this hybrid model works well for technical Malaysian SME owners. Handle content updates and image swaps yourself. Outsource the dangerous parts: core updates, plugin updates, backups, security monitoring, and emergency response. Hybrid plans typically run RM 150-250/month.'),
    );
    $faq_schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(function($q) {
            return array(
                '@type' => 'Question',
                'name' => $q[0],
                'acceptedAnswer' => array('@type' => 'Answer', 'text' => $q[1]),
            );
        }, $faqs),
    );
    echo '<script type="application/ld+json">' . wp_json_encode($faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

    // HowTo schema — maintenance schedule
    $howto = array(
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => 'How to Maintain a Malaysian Business Website',
        'description' => 'A complete maintenance schedule for WordPress and custom websites in Malaysia, broken down by frequency.',
        'totalTime' => 'PT5H',
        'estimatedCost' => array('@type' => 'MonetaryAmount', 'currency' => 'MYR', 'value' => '300'),
        'step' => array(
            array('@type' => 'HowToStep', 'name' => 'Daily automated tasks', 'text' => 'Run automated backups, uptime monitoring, malware scanning, and SSL certificate checks. Most are handled by your hosting or maintenance service automatically.'),
            array('@type' => 'HowToStep', 'name' => 'Weekly checks (15 minutes)', 'text' => 'Apply WordPress core and plugin updates, review uptime reports, check for broken links, and verify the previous week\'s backup actually restored cleanly.'),
            array('@type' => 'HowToStep', 'name' => 'Monthly maintenance (1-2 hours)', 'text' => 'Review Google Analytics traffic, check page speed (PageSpeed Insights, Core Web Vitals), audit security logs, test contact forms, and review user-generated content.'),
            array('@type' => 'HowToStep', 'name' => 'Quarterly deep audit (3-4 hours)', 'text' => 'Full security audit, database optimization, content audit (update outdated posts), SEO audit, and review of all installed plugins for relevance.'),
            array('@type' => 'HowToStep', 'name' => 'Yearly review (1 day)', 'text' => 'Renew domain and SSL, review hosting plan, full backup verification with restore test, design refresh planning, and budget review for the next 12 months.'),
        ),
    );
    echo '<script type="application/ld+json">' . wp_json_encode($howto, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

    // Service / Offer schema — 3 maintenance packages
    $service = array(
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'serviceType' => 'Website Maintenance',
        'provider' => array(
            '@type' => 'Person',
            'name' => 'Ryano Chu',
            'url' => 'https://ryanoccg.com',
            'telephone' => '+60174272807',
            'areaServed' => 'Malaysia',
        ),
        'areaServed' => array('@type' => 'Country', 'name' => 'Malaysia'),
        'hasOfferCatalog' => array(
            '@type' => 'OfferCatalog',
            'name' => 'Website Maintenance Plans',
            'itemListElement' => array(
                array(
                    '@type' => 'Offer',
                    'name' => 'Essential Plan',
                    'description' => 'Weekly WordPress, plugin and theme updates. Daily automated backups (30-day retention). Weekly malware scans. Uptime monitoring with 99.9% guarantee. SSL monitoring. Monthly report.',
                    'price' => '300',
                    'priceCurrency' => 'MYR',
                    'priceSpecification' => array('@type' => 'UnitPriceSpecification', 'price' => '300', 'priceCurrency' => 'MYR', 'unitCode' => 'MON'),
                ),
                array(
                    '@type' => 'Offer',
                    'name' => 'Business Plan',
                    'description' => 'Everything in Essential. Daily security scans. Monthly performance optimization. Broken link fixes. 30 min content updates. Priority support with 4-hour response.',
                    'price' => '450',
                    'priceCurrency' => 'MYR',
                    'priceSpecification' => array('@type' => 'UnitPriceSpecification', 'price' => '450', 'priceCurrency' => 'MYR', 'unitCode' => 'MON'),
                ),
                array(
                    '@type' => 'Offer',
                    'name' => 'E-Commerce Plan',
                    'description' => 'Everything in Business. Hourly backups. Staging environment. Advanced security. Monthly speed audits. 1 hour content updates. Priority support with 2-hour response.',
                    'price' => '650',
                    'priceCurrency' => 'MYR',
                    'priceSpecification' => array('@type' => 'UnitPriceSpecification', 'price' => '650', 'priceCurrency' => 'MYR', 'unitCode' => 'MON'),
                ),
            ),
        ),
    );
    echo '<script type="application/ld+json">' . wp_json_encode($service, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}
add_action('wp_head', 'ryano_pillar_maintenance_schema');

// HowTo schema for post 20 — "how to rank on google page 1 in malaysia" already sits at
// pos 19.3 and is pure step-by-step intent, but the page only carried FAQPage.
// Steps mirror the five visible "Quick Wins" H3s verbatim, including their stated times.
function ryano_seo_guide_howto_schema() {
    if (!is_single(20)) {
        return;
    }

    $howto = array(
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => 'How to Rank on Google Page 1',
        'description' => 'The five highest-leverage SEO tasks for a local business website, in the order to do them.',
        'totalTime' => 'PT4H',
        'step' => array(
            array('@type' => 'HowToStep', 'name' => 'Fix your Google Business Profile', 'text' => 'Claim your listing, add accurate address, hours and phone, choose the correct categories, upload 10 or more photos, and collect 5 to 10 reviews. Takes about 30 minutes and can rank you in the local map pack within 2 weeks.'),
            array('@type' => 'HowToStep', 'name' => 'Add location keywords to your pages', 'text' => 'Not just "web developer" but "web developer Penang" or "web developer Malaysia". Include your city or state in title tags. Takes about an hour.'),
            array('@type' => 'HowToStep', 'name' => 'Make your site mobile-friendly', 'text' => 'Google ranks the mobile version of your site. Test it with Google\'s mobile-friendly tool. Fixing a mobile-broken site is worth a 30 to 50 percent ranking improvement.'),
            array('@type' => 'HowToStep', 'name' => 'Speed up your website', 'text' => 'Compress images, enable caching, and move to fast hosting. Takes about 2 hours. Every 1 second of delay costs roughly 7 percent of conversions.'),
            array('@type' => 'HowToStep', 'name' => 'Earn 3 to 5 links from local sites', 'text' => 'Target local newspaper articles, business directories, partner websites, and your chamber of commerce. A small number of trusted local links gives a significant trust boost.'),
        ),
    );
    echo '<script type="application/ld+json">' . wp_json_encode($howto, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}
add_action('wp_head', 'ryano_seo_guide_howto_schema');

// 301 redirects for consolidated/retired posts (keyword cannibalization cleanup)
// Old post stays published so its permalink resolves, but always 301s to the canonical replacement.
$ryano_consolidated_posts = array(
    110 => 198, // ai-website-builder-vs-custom-development -> ai-website-builder-vs-professional-web-development
    10  => 218, // website-redesign-malaysia -> signs-website-redesign
    137 => 22,  // ecommerce-website-maintenance-malaysia -> website-maintenance-malaysia
    187 => 22,  // how-to-choose-website-maintenance-company -> website-maintenance-malaysia
    8   => 191, // website-cost-malaysia-2026 -> ecommerce-website-cost-malaysia
    12  => 16,  // wordpress-developer-malaysia -> hire-web-developer-malaysia
);

// Priority 1 runs ahead of redirect_canonical, so ?p= and slash variants land on the target in one hop.
function ryano_content_consolidation_redirects() {
    global $ryano_consolidated_posts;
    if (is_admin() || !is_singular('post')) return;
    $id = get_queried_object_id();
    if (isset($ryano_consolidated_posts[$id])) {
        $target = get_permalink($ryano_consolidated_posts[$id]);
        if ($target) { wp_redirect($target, 301); exit; }
    }
}
add_action('template_redirect', 'ryano_content_consolidation_redirects', 1);

// Keep retired and placeholder URLs out of wp-sitemap.xml.
//
// Consolidated posts have to stay published: the redirect above resolves their permalinks
// before 301ing, so unpublishing one would break its redirect instead of retiring the URL.
// Dropping them from the sitemap is the part that can be done safely, and it matters
// because Google is currently crawl-starved here (posts have waited weeks for a
// first crawl), so every advertised URL that only ever 301s is budget spent for
// nothing. Page 2 is WordPress's stock "Sample Page", which still returns 200.
$ryano_sitemap_excluded = array(
    'post' => array_keys($ryano_consolidated_posts),
    'page' => array(2),
);

function ryano_filter_sitemap_entries($args, $post_type) {
    global $ryano_sitemap_excluded;
    if (empty($ryano_sitemap_excluded[$post_type])) {
        return $args;
    }
    $existing = isset($args['post__not_in']) ? (array) $args['post__not_in'] : array();
    $args['post__not_in'] = array_merge($existing, $ryano_sitemap_excluded[$post_type]);
    return $args;
}
add_filter('wp_sitemaps_posts_query_args', 'ryano_filter_sitemap_entries', 10, 2);

// Merges into the robots tag WordPress already prints rather than emitting a second one.
// is_page(2) would also match a page titled or slugged "2", so compare the ID directly.
function ryano_noindex_placeholder_page($robots) {
    if (is_page() && 2 === get_queried_object_id()) {
        $robots['noindex'] = true;
        $robots['follow'] = true;
    }
    return $robots;
}
add_filter('wp_robots', 'ryano_noindex_placeholder_page');

// Security: Disable XML-RPC
add_filter('xmlrpc_enabled', '__return_false');

// Performance: Limit post revisions
if (!defined('WP_POST_REVISIONS')) {
    define('WP_POST_REVISIONS', 3);
}

// Custom comment callback (if you want to add comments later)
function ryano_comment_callback($comment, $args, $depth) {
    // Custom comment HTML structure
    // Can be implemented later if needed
}
