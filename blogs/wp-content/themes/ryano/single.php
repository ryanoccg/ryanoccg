<?php
/**
 * Single post template
 */
get_header(); ?>

<main class="site-main site-main-single">
    <?php while (have_posts()) : the_post(); ?>
        <article class="single-post">
                <header class="post-header">
                    <h1 class="post-title"><?php the_title(); ?></h1>

                    <div class="post-meta">
                        <?php
                        $categories = get_the_category();
                        if (!empty($categories)) :
                        ?>
                            <span class="post-category"><?php echo esc_html($categories[0]->name); ?></span>
                        <?php endif; ?>
                        <span class="post-date"><?php echo get_the_date(); ?></span>
                        <span class="reading-time"><?php echo ryano_reading_time(); ?></span>
                    </div>
                </header>

                <?php if (has_post_thumbnail()) : ?>
                    <img src="<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(), 'full')); ?>"
                         alt="<?php echo esc_attr(get_the_title()); ?>"
                         class="post-thumbnail">
                <?php endif; ?>

                <div class="post-content">
                    <?php the_content(); ?>
                </div>

                <footer class="post-footer">
                    <?php
                    $prev_post = get_previous_post();
                    $next_post = get_next_post();

                    if ($prev_post || $next_post) : ?>
                        <nav class="post-navigation">
                            <?php if ($prev_post) : ?>
                                <a href="<?php echo get_permalink($prev_post); ?>" class="nav-previous">
                                    ← <?php echo get_the_title($prev_post); ?>
                                </a>
                            <?php endif; ?>

                            <?php if ($next_post) : ?>
                                <a href="<?php echo get_permalink($next_post); ?>" class="nav-next">
                                    <?php echo get_the_title($next_post); ?> →
                                </a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </footer>
        </article>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
