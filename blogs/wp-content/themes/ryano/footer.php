<footer class="site-footer">
    <div class="container">
        <div class="footer-text">
            <p>&copy; <?php echo date('Y'); ?> <a href="https://ryanoccg.com" target="_blank" rel="noopener noreferrer">Ryano Chu</a>. All rights reserved.</p>
            <p style="margin-top: 0.5rem; font-size: 0.85rem;">
                Full-Stack Developer | <a href="https://ryanoccg.com/#contact" target="_blank" rel="noopener noreferrer">Get in Touch</a> | <a href="<?php echo esc_url(home_url('/')); ?>">Blog Home</a>
            </p>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<button id="back-to-top" class="back-to-top" aria-label="Back to top">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
    </svg>
</button>

<script>
// Back to Top functionality
(function() {
    const backToTopBtn = document.getElementById('back-to-top');

    // Show/hide button based on scroll position
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopBtn.classList.add('show');
        } else {
            backToTopBtn.classList.remove('show');
        }
    });

    // Smooth scroll to top
    backToTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
