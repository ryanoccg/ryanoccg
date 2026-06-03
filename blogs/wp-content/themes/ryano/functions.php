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
// title: keep under 60 chars so Google doesn't truncate
// description: 120-155 chars, include CTA
$ryano_seo_overrides = array(
    120 => array(
        'title'       => 'Bad Website Design Malaysia: 7 Mistakes Killing Sales',
        'description' => 'These 7 web design mistakes silently kill 80% of Malaysian SME sites. Real audits, real fixes — spot yours in 30 sec. Free review: WhatsApp +60174272807',
    ),
    126 => array(
        'title'       => 'Core Web Vitals 2026: 3 Speed Metrics Malaysian Sites Must Pass',
        'description' => 'Failed Core Web Vitals = Page 2 rankings. The 3 metrics Google now demands in 2026, with exact fixes for Malaysian sites. Free audit: WhatsApp +60174272807',
    ),
    22 => array(
        'title'       => 'Website Maintenance Malaysia 2026: Real Costs + Plans',
        'description' => 'What website maintenance really costs in Malaysia (2026): monthly plans, hidden fees, and which tier your site needs. Free quote: WhatsApp +60174272807',
    ),
    26 => array(
        'title'       => 'Web Design Malaysia: 7 Mistakes & Quick Fixes (2026)',
        'description' => '7 web design mistakes I find on 80% of Malaysian SME audits — fix them in 30 mins. Free website review: WhatsApp +60174272807',
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
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author(),
                'url' => 'https://ryanoccg.com',
            ),
            'publisher' => array(
                '@type' => 'Person',
                'name' => 'Ryano Chu',
                'url' => 'https://ryanoccg.com',
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
        array('Can I do website maintenance myself?', 'Yes, if you are technical and have 3-5 hours per month available. Non-technical owners or busy Malaysian SMEs almost always save money by outsourcing — the cost of one missed update can exceed a year of maintenance fees.'),
        array('What if I haven\'t maintained my site in years?', 'Start with a security audit (RM 1,500-3,000) to find vulnerabilities and backdoors. Once the site is clean, move to regular monthly maintenance to prevent the issue from recurring.'),
        array('My developer said maintenance isn\'t necessary. Is that true?', 'No. Every website needs ongoing maintenance. A developer claiming otherwise is either inexperienced or hoping you\'ll pay for expensive emergency fixes later. WordPress, plugins, themes, and PHP all receive security patches every few weeks.'),
        array('Can\'t my hosting company handle maintenance?', 'Most cannot. Shared hosting providers handle server-level updates only. Managed WordPress hosts (WP Engine, Kinsta) cover core updates but not your plugins, themes, or content. Maintenance is a separate responsibility.'),
        array('What happens if I cancel maintenance?', 'Your site becomes gradually more vulnerable. Within 3-6 months you will likely have outdated plugins with public CVEs, no recent backup, and broken SSL renewal. The risk of being hacked rises sharply after month 4.'),
        array('Is yearly maintenance better than monthly?', 'No. "Yearly maintenance" means 11 months of vulnerability. Security patches and plugin updates need to be applied weekly, not annually. Monthly retainer plans exist for this reason.'),
        array('Does WordPress maintenance differ from Shopify maintenance?', 'Yes, significantly. WordPress requires hands-on plugin, theme, and core updates (2-4 hours/month) because you own the stack. Shopify handles the platform itself, so your maintenance focuses on app audits, theme tweaks, and storefront optimization (1-2 hours/month).'),
        array('Can maintenance fees be claimed as a business expense in Malaysia?', 'Yes. Website maintenance is a recurring business expense and qualifies as a deduction under your company tax filing with LHDN. Keep monthly invoices — both digital and printed receipts are accepted.'),
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
