<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#161616">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header" id="site-header">
    <div class="container">
        <div class="site-logo">
            <a href="<?php echo esc_url(home_url('/')); ?>">
                <img src="<?php echo get_template_directory_uri(); ?>/assets/logo.svg"
                     alt="<?php bloginfo('name'); ?>"
                     class="logo-img">
            </a>
        </div>

        <?php
        wp_nav_menu(array(
            'theme_location' => 'primary',
            'container' => 'nav',
            'container_class' => 'site-nav',
            'fallback_cb' => 'ryano_fallback_menu',
        ));
        ?>
    </div>
</header>

<script>
// Add scroll effect to header
(function() {
    const header = document.getElementById('site-header');
    let lastScroll = 0;

    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset;

        if (currentScroll > 100) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }

        lastScroll = currentScroll;
    });
})();
</script>
