// src/main.js

// Your other imports
import * as bootstrap from 'bootstrap'
import './scss/styles.scss'

// ─── Conversion Tracking ────────────────────────────────────────────────────
// Fires GA4 events for high-intent actions. Mark each in GA4 admin
// (Configure → Events → toggle "Mark as key event") to count as conversions.
// Events fired here: whatsapp_click, resume_download, view_pricing, quote_request

function trackEvent(name, params = {}) {
  if (window.gtag) window.gtag('event', name, params);
}

function locationHint(el) {
  const section = el.closest('section, header, footer, #pricing-items, #contact, #portfolio-items, .resume-hero, .resume-final-cta, .pricing-note');
  if (!section) return 'unknown';
  return section.id || section.className.split(' ').find(c => c) || 'unknown';
}

// Delegated click tracking — covers all WhatsApp + PDF links on any page
document.addEventListener('click', (e) => {
  const link = e.target.closest('a');
  if (!link || !link.href) return;

  if (link.href.includes('wa.me/60174272807')) {
    trackEvent('whatsapp_click', {
      event_category: 'engagement',
      link_location: locationHint(link),
    });
  } else if (link.href.includes('Ryano_Resume.pdf')) {
    trackEvent('resume_download', {
      event_category: 'engagement',
      link_location: locationHint(link),
    });
  }
});

// Pricing section viewed — fires once per page load when ≥40% scrolled into view
const pricingSection = document.getElementById('pricing');
if (pricingSection && 'IntersectionObserver' in window) {
  let pricingFired = false;
  const pricingObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting && !pricingFired) {
        pricingFired = true;
        trackEvent('view_pricing', { event_category: 'engagement' });
        pricingObserver.disconnect();
      }
    });
  }, { threshold: 0.4 });
  pricingObserver.observe(pricingSection);
}

// Select all links that have hash (#) in them
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function(e) {
      e.preventDefault();

      // Get the target element
      const target = document.querySelector(this.getAttribute('href'));
      
      if (target) {
          // Get the target's position
          const targetPosition = target.getBoundingClientRect().top;
          const offsetPosition = targetPosition + window.pageYOffset - 100; // 500px offset

          // Smooth scroll to target
          window.scrollTo({
              top: offsetPosition,
              behavior: 'smooth'
          });
      }
  });
});

// Mobile menu functionality
const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
const mobileMenu = document.querySelector('.mobile-menu');
const mobileMenuLinks = document.querySelectorAll('.mobile-menu a');

mobileMenuBtn.addEventListener('click', () => {
    mobileMenu.classList.toggle('active');
});

// Close mobile menu when clicking a link
mobileMenuLinks.forEach(link => {
    link.addEventListener('click', () => {
        mobileMenu.classList.remove('active');
    });
});

// Close mobile menu when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.mobile-menu') &&
        !e.target.closest('.mobile-menu-btn') &&
        mobileMenu.classList.contains('active')) {
        mobileMenu.classList.remove('active');
    }
});

// ─── Latest Blog (homepage) ──────────────────────────────────────────────────
// Fetches 3 newest posts from the WordPress REST API and replaces the static
// fallback cards. If the request fails, the static cards in the HTML stay
// visible so the section is never empty.

const blogGrid = document.getElementById('latest-blog-grid');
if (blogGrid) {
    // Don't use _fields here — WP REST drops _embedded when _fields is set,
    // even with _embed=1. Payload for 3 posts is ~50KB which is fine.
    const BLOG_API = 'https://ryanoccg.com/blogs/wp-json/wp/v2/posts?per_page=3&_embed=1';

    const stripTags = (html) => html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    const formatDate = (iso) => {
        const d = new Date(iso);
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    };
    const readingTime = (html) => {
        const words = stripTags(html).split(/\s+/).filter(Boolean).length;
        return Math.max(1, Math.ceil(words / 200));
    };

    const renderCard = (post) => {
        const title = stripTags(post.title.rendered);
        const excerpt = stripTags(post.excerpt.rendered).replace(/\[…\]$/, '…');
        const featured = post._embedded?.['wp:featuredmedia']?.[0]?.source_url || '';
        const date = formatDate(post.date);
        const mins = readingTime(post.content?.rendered || post.excerpt.rendered);

        return `
            <div class="col-12 col-lg-4">
                <a href="${post.link}" class="blog-card-link">
                    <article class="blog-card">
                        <div class="blog-card-image" style="background-image: url('${featured}');"></div>
                        <div class="blog-card-body">
                            <div class="blog-card-meta">${date} · ${mins} min read</div>
                            <h3 class="blog-card-title">${title}</h3>
                            <p class="blog-card-excerpt">${excerpt}</p>
                            <span class="blog-card-cta">Read article →</span>
                        </div>
                    </article>
                </a>
            </div>
        `;
    };

    fetch(BLOG_API)
        .then(r => r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`)))
        .then(posts => {
            if (!Array.isArray(posts) || posts.length === 0) return;
            blogGrid.innerHTML = posts.map(renderCard).join('');
            blogGrid.dataset.fallbackLoaded = '1';
        })
        .catch(() => {
            // Silent fail: static fallback cards remain visible.
        });
}

// ─── Quote Form ───────────────────────────────────────────────────────────────
// Replace this with your deployed Worker URL:
//   Development : http://127.0.0.1:8787
//   Production  : https://api.ryanoccg.com/quote
const WORKER_URL = 'https://api.ryanoccg.com/quote';

const quoteForm = document.getElementById('quote-form');
if (quoteForm) {
    const statusEl = quoteForm.querySelector('.form-status');
    const submitBtn = quoteForm.querySelector('.quote-submit');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');

    const setStatus = (msg, type) => {
        statusEl.textContent = msg;
        statusEl.classList.remove('success', 'error');
        if (type) statusEl.classList.add(type);
    };

    const setLoading = (loading) => {
        submitBtn.disabled = loading;
        btnText.classList.toggle('d-none', loading);
        btnLoading.classList.toggle('d-none', !loading);
    };

    const resetTurnstile = () => {
        if (window.turnstile) window.turnstile.reset();
    };

    quoteForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Honeypot
        if (quoteForm.querySelector('[name="_gotcha"]').value) return;

        // HTML5 validation
        if (!quoteForm.checkValidity()) {
            quoteForm.classList.add('was-validated');
            setStatus('Please fill in all required fields.', 'error');
            return;
        }

        // Turnstile token check
        const turnstileToken = quoteForm.querySelector('[name="cf-turnstile-response"]')?.value;
        if (!turnstileToken) {
            setStatus('Please complete the verification checkbox.', 'error');
            return;
        }

        setLoading(true);
        setStatus('', null);

        const formData = new FormData(quoteForm);
        const payload = {
            name:                    formData.get('name'),
            email:                   formData.get('email'),
            phone:                   formData.get('phone'),
            package:                 formData.get('package'),
            budget:                  formData.get('budget'),
            message:                 formData.get('message'),
            _gotcha:                 formData.get('_gotcha'),
            'cf-turnstile-response': turnstileToken,
        };

        try {
            const res = await fetch(WORKER_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });

            const data = await res.json().catch(() => ({}));

            if (res.ok && data.ok) {
                quoteForm.reset();
                quoteForm.classList.remove('was-validated');
                resetTurnstile();
                setStatus(data.message || "Thanks! I'll get back to you within 24 hours.", 'success');
                if (window.gtag) {
                    window.gtag('event', 'quote_request', { event_category: 'contact' });
                }
            } else {
                resetTurnstile();
                setStatus(data.error || 'Something went wrong. Please email ryanoccg@gmail.com directly.', 'error');
            }
        } catch {
            resetTurnstile();
            setStatus('Network error. Please email ryanoccg@gmail.com directly.', 'error');
        } finally {
            setLoading(false);
        }
    });
}