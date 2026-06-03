<?php
/**
 * Ryano Theme Functions
 * Custom blog theme matching Ryano's portfolio design
 */

// Enqueue styles and scripts
function ryano_scripts() {
    // Main stylesheet - versioned for cache busting
    wp_enqueue_style('ryano-style', get_stylesheet_uri(), array(), '2.1.0');

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
