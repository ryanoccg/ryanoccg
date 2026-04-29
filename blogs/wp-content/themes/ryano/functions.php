<?php
/**
 * Ryano Theme Functions
 */

// Enqueue styles and scripts
function ryano_scripts() {
    // Main stylesheet
    wp_enqueue_style('ryano-style', get_stylesheet_uri(), array(), '1.0.0');
}
add_action('wp_enqueue_scripts', 'ryano_scripts');

// Theme setup
function ryano_setup() {
    // Add title tag support
    add_theme_support('title-tag');

    // Add post thumbnails support
    add_theme_support('post-thumbnails');
    set_post_thumbnail_size(800, 400, true);

    // Add automatic feed links
    add_theme_support('automatic-feed-links');

    // Add HTML5 support
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));

    // Register navigation menu
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'ryano'),
    ));
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
        'prev_text' => '←',
        'next_text' => '→',
        'type' => 'array',
    ));

    if ($paginate) {
        echo '<nav class="pagination">';
        foreach ($paginate as $page) {
            echo $page;
        }
        echo '</nav>';
    }
}

// Add default placeholder image if post has no thumbnail
function ryano_get_post_thumbnail($post_id, $size = 'large') {
    if (has_post_thumbnail($post_id)) {
        return get_the_post_thumbnail_url($post_id, $size);
    }

    // Return a placeholder or default image
    return get_template_directory_uri() . '/assets/default-thumb.jpg';
}

// Format posted date
function ryano_posted_on() {
    echo '<time class="post-date" datetime="' . esc_attr(get_the_date('c')) . '">' .
         esc_html(get_the_date()) . '</time>';
}

// Display category
function ryano_post_category() {
    $categories = get_the_category();
    if (!empty($categories)) {
        echo '<span class="post-category">' . esc_html($categories[0]->name) . '</span>';
    }
}

// Calculate reading time
function ryano_reading_time() {
    $content = get_post_field('post_content', get_the_ID());
    $word_count = str_word_count(strip_tags($content));
    $reading_time = ceil($word_count / 200); // 200 words per minute

    return $reading_time . ' min read';
}

// Fallback menu if no menu is assigned
function ryano_fallback_menu() {
    echo '<nav class="site-nav">';
    echo '<ul>';
    echo '<li><a href="' . esc_url(home_url('/')) . '">Home</a></li>';
    echo '<li><a href="https://ryanoccg.com" target="_blank">Portfolio</a></li>';
    echo '</ul>';
    echo '</nav>';
}
