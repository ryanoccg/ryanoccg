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
        'title'       => 'Bad Website Design Malaysia: 7 Mistakes Killing Sales (2026)',
        'description' => 'These 7 web design mistakes quietly kill sales on 80% of Malaysian SME sites. Real audits, real fixes. Spot yours in 30 seconds: WhatsApp +60174272807',
    ),
    14 => array(
        'title'       => 'Mobile Website Malaysia: Stop Losing 72% of Visitors (2026)',
        'description' => '72% of Malaysians browse phone-first (MCMC). A site that fights them on mobile loses sales daily. The 2026 mobile-first fixes that work. WhatsApp +60174272807',
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
    187 => array(
        'title'       => 'Website Maintenance Company: How to Pick One (2026)',
        'description' => 'Pick a website maintenance company that protects your site, not one that just bills you. The questions to ask, the red flags, fair pricing. WhatsApp +60174272807',
    ),
    191 => array(
        'title'       => 'E-Commerce Website Cost in Malaysia: 2026 Price Guide',
        'description' => 'What an online store really costs to build and run in 2026: build tiers (RM5k-25k), gateway fees, and the hidden monthly costs. Free quote: WhatsApp +60174272807',
    ),
    198 => array(
        'title'       => 'AI Website Builder or Professional Development?',
        'description' => 'AI website builder or professional development? An honest 2026 breakdown of cost, SEO, and ownership. Free advice: WhatsApp +60174272807',
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
// Mirrors the visible FAQ section so Google can show FAQ rich results.
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
    22 => array(
        array('Can I do website maintenance myself?', 'Yes, if you are technical and have time (3-5 hours per month). For non-technical owners or busy businesses, outsourcing is more cost-effective.'),
        array('What if I have not maintained my site in years?', 'Get a security audit first (RM 1,500-3,000). Clean up any issues, then start regular maintenance.'),
        array('My developer said maintenance is not necessary. Is that true?', 'No. All websites need maintenance. A developer who says otherwise is either inexperienced or wants you dependent on expensive emergency fixes.'),
        array('Can my hosting company handle maintenance?', 'Some do (managed WordPress hosting), but most do not. Shared hosting only covers server-level maintenance, not your website specifically.'),
        array('What happens if I cancel maintenance?', 'Your site gradually becomes vulnerable, slow, and outdated, and the risk of hacking increases significantly.'),
        array('Is yearly maintenance better than monthly?', 'No. Maintenance should be ongoing. Yearly maintenance means 11 months of vulnerability.'),
        array('Does WordPress maintenance differ from Shopify maintenance?', 'Yes. WordPress needs hands-on plugin, theme, and core updates (2-4 hours per month) because you own the stack. Shopify handles the platform itself, so maintenance focuses on app audits, theme tweaks, and storefront optimization (1-2 hours per month).'),
        array('Can maintenance fees be claimed as a business expense in Malaysia?', 'Yes. Website maintenance is a recurring business expense and qualifies as a deduction under your company tax filing. Keep your monthly invoices, as LHDN accepts both digital and printed receipts.'),
        array('How soon after launching a new website do I need maintenance?', 'Day one. WordPress core, plugins, and themes start receiving updates within weeks of any launch, so a 3-month-old unmaintained site is just as exposed as a 3-year-old one.'),
        array('Is switching maintenance providers risky?', 'Only if your current provider holds your assets hostage. Before signing up with anyone, confirm in writing that you own the domain, you have admin-level access to hosting and the site, and there is no exit fee.'),
    ),
    187 => array(
        array('Is a website maintenance company worth it for a small business?', 'Yes, if the site earns or represents revenue. The cost of one serious hack or a week of downtime almost always exceeds a year of maintenance fees, and recovery is far more expensive than prevention.'),
        array('Can I just use my hosting provider for maintenance?', 'Usually not fully. Most hosts maintain the server, not your specific website. Managed WordPress hosting is the exception, and even then it rarely covers plugin conflicts or content fixes.'),
        array('How do I switch maintenance companies safely?', 'Confirm in writing that you own your domain, hosting, and admin access before signing with anyone new. As long as you hold those, switching is low risk and no provider can hold your site hostage.'),
        array('What is the difference between a freelancer and a maintenance company?', 'A freelancer is cheaper and more personal but is a single point of failure if they get busy or stop replying. A company costs more but usually offers documented processes and backup coverage when one person is unavailable.'),
        array('How often should website maintenance actually happen?', 'Continuously, not yearly. Software updates arrive every few weeks, so a site checked once a year spends most of that year exposed.'),
    ),
    120 => array(
        array('How do I know if my website design is actually bad?', 'Three quick checks: ask three people outside your business to complete one task on their phone, run PageSpeed Insights and read the mobile score, and check your Google Analytics bounce rate. Over 70 percent on landing pages means visitors are leaving fast.'),
        array('Can I fix bad website design myself?', 'Some of it yes: updating copy, swapping stock photos, simplifying forms, and fixing the copyright year. Page speed, mobile layout, SSL, and code quality usually need a developer.'),
        array('How much does a website redesign cost in Malaysia?', 'A full redesign for an SME site typically runs RM3,500 to RM15,000 depending on scope. A targeted fix of the worst issues can often be done for RM1,500 to RM3,000.'),
        array('Will fixing the design actually increase sales?', 'If you are losing leads to friction like slow loading, poor mobile, unclear value, or hidden contact details, fixing those usually lifts enquiries within 30 to 60 days.'),
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
