// src/main.js

// Your other imports
import * as bootstrap from 'bootstrap'
import './scss/styles.scss'

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