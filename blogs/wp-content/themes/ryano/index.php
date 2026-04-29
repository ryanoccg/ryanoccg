<?php
/**
 * Main template file - Blog listing page
 */
get_header(); ?>

<main class="site-main">
    <div class="container">
        <header class="page-header">
            <h1 class="page-title">Blog</h1>
            <p class="page-description">Web development insights, tutorials, and thoughts from Ryano Chu</p>
        </header>

        <?php if (have_posts()) : ?>
            <div class="blog-grid">
                <?php while (have_posts()) : the_post(); ?>
                    <article class="blog-card">
                        <a href="<?php the_permalink(); ?>" class="blog-card-link">
                            <?php if (has_post_thumbnail()) : ?>
                                <img src="<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(), 'large')); ?>"
                                     alt="<?php echo esc_attr(get_the_title()); ?>"
                                     class="blog-card-thumbnail">
                            <?php endif; ?>

                            <div class="blog-card-content">
                                <div class="blog-card-meta">
                                    <?php
                                    $categories = get_the_category();
                                    if (!empty($categories)) :
                                    ?>
                                        <span class="blog-card-category"><?php echo esc_html($categories[0]->name); ?></span>
                                    <?php endif; ?>
                                    <span class="blog-card-date"><?php echo get_the_date(); ?></span>
                                </div>

                                <h2 class="blog-card-title">
                                    <?php the_title(); ?>
                                </h2>

                                <div class="blog-card-excerpt">
                                    <?php echo wp_trim_words(get_the_excerpt(), 20, '...'); ?>
                                </div>

                                <div class="blog-card-footer">
                                    <span class="read-more">
                                        Read More →
                                    </span>
                                    <span class="reading-time"><?php echo ryano_reading_time(); ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php ryano_pagination(); ?>

        <?php else : ?>
            <div class="no-posts">
                <p>No posts found.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php get_footer(); ?>
