/* ============================================================
   AkkuApps UI Animations v2.0
   Small, attractive micro-interactions
   ============================================================ */

(function() {
  'use strict';

  const AkkuAnimations = {
    // Initialize all animations
    init() {
      this.initScrollReveal();
      this.initCountUp();
      this.initRippleEffect();
      this.initTypingEffect();
      this.initParallaxCards();
      this.initHoverTilt();
      this.initNotificationBadge();
      this.initSmoothScroll();
      this.initScrollTop();
      this.initDropdowns();
      this.initTabs();
      this.initModals();
      this.initSearchClear();
      this.initFileUpload();
      this.initToastSystem();
      this.initMobileNav();
      this.initThemeToggle();
    },

    /* ---------- Scroll Reveal ---------- */
    initScrollReveal() {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const el = entry.target;
            const delay = el.dataset.revealDelay || 0;
            const direction = el.dataset.reveal || 'up';

            setTimeout(() => {
              el.style.opacity = '1';
              el.style.transform = 'translateY(0) translateX(0) scale(1)';
            }, delay * 1000);

            observer.unobserve(el);
          }
        });
      }, { threshold: 0.1, rootMargin: '0px 0px -20px 0px' });

      document.querySelectorAll('[data-reveal]').forEach(el => {
        const direction = el.dataset.reveal;
        el.style.opacity = '0';
        el.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';

        switch(direction) {
          case 'up': el.style.transform = 'translateY(20px)'; break;
          case 'down': el.style.transform = 'translateY(-20px)'; break;
          case 'left': el.style.transform = 'translateX(20px)'; break;
          case 'right': el.style.transform = 'translateX(-20px)'; break;
          case 'scale': el.style.transform = 'scale(0.9)'; break;
          case 'fade': el.style.transform = 'none'; break;
        }

        observer.observe(el);
      });
    },

    /* ---------- Count Up Animation ---------- */
    initCountUp() {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const el = entry.target;
            const target = parseInt(el.dataset.count) || 0;
            const duration = parseInt(el.dataset.duration) || 1500;
            const prefix = el.dataset.prefix || '';
            const suffix = el.dataset.suffix || '';

            this.animateCount(el, 0, target, duration, prefix, suffix);
            observer.unobserve(el);
          }
        });
      }, { threshold: 0.5 });

      document.querySelectorAll('[data-count]').forEach(el => observer.observe(el));
    },

    animateCount(el, start, end, duration, prefix, suffix) {
      const startTime = performance.now();

      const update = (currentTime) => {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Easing function (easeOutQuart)
        const ease = 1 - Math.pow(1 - progress, 4);
        const current = Math.floor(start + (end - start) * ease);

        el.textContent = prefix + current.toLocaleString() + suffix;

        if (progress < 1) {
          requestAnimationFrame(update);
        } else {
          el.classList.add('count-done');
          // Small bounce effect
          el.style.transform = 'scale(1.1)';
          setTimeout(() => el.style.transform = 'scale(1)', 150);
        }
      };

      requestAnimationFrame(update);
    },

    /* ---------- Ripple Effect ---------- */
    initRippleEffect() {
      document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn, .sidebar-link, .nav-link, .dropdown-item');
        if (!btn) return;

        const ripple = document.createElement('span');
        const rect = btn.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;

        ripple.style.cssText = `
          position: absolute;
          width: ${size}px;
          height: ${size}px;
          left: ${x}px;
          top: ${y}px;
          background: rgba(255,255,255,0.2);
          border-radius: 50%;
          transform: scale(0);
          animation: ripple 0.5s ease-out;
          pointer-events: none;
        `;

        btn.style.position = 'relative';
        btn.style.overflow = 'hidden';
        btn.appendChild(ripple);

        setTimeout(() => ripple.remove(), 500);
      });
    },

    /* ---------- Typing Effect ---------- */
    initTypingEffect() {
      document.querySelectorAll('[data-typing]').forEach(el => {
        const text = el.dataset.typing;
        const speed = parseInt(el.dataset.typingSpeed) || 50;
        let i = 0;
        el.textContent = '';

        const type = () => {
          if (i < text.length) {
            el.textContent += text.charAt(i);
            i++;
            setTimeout(type, speed);
          }
        };

        // Start after a small delay
        setTimeout(type, 300);
      });
    },

    /* ---------- Parallax Cards ---------- */
    initParallaxCards() {
      document.querySelectorAll('.card[data-parallax]').forEach(card => {
        card.addEventListener('mousemove', (e) => {
          const rect = card.getBoundingClientRect();
          const x = (e.clientX - rect.left) / rect.width - 0.5;
          const y = (e.clientY - rect.top) / rect.height - 0.5;

          card.style.transform = `
            perspective(1000px)
            rotateY(${x * 5}deg)
            rotateX(${-y * 5}deg)
            translateY(-2px)
          `;
        });

        card.addEventListener('mouseleave', () => {
          card.style.transform = 'perspective(1000px) rotateY(0) rotateX(0) translateY(0)';
        });
      });
    },

    /* ---------- Hover Tilt ---------- */
    initHoverTilt() {
      document.querySelectorAll('[data-tilt]').forEach(el => {
        el.addEventListener('mousemove', (e) => {
          const rect = el.getBoundingClientRect();
          const x = (e.clientX - rect.left) / rect.width;
          const y = (e.clientY - rect.top) / rect.height;

          const tiltX = (y - 0.5) * 10;
          const tiltY = (x - 0.5) * -10;

          el.style.transform = `perspective(1000px) rotateX(${tiltX}deg) rotateY(${tiltY}deg) scale(1.02)`;
        });

        el.addEventListener('mouseleave', () => {
          el.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale(1)';
        });
      });
    },

    /* ---------- Notification Badge Pulse ---------- */
    initNotificationBadge() {
      document.querySelectorAll('.nav-badge').forEach(badge => {
        if (parseInt(badge.textContent) > 0) {
          badge.style.animation = 'pulse 2s ease-in-out infinite';
        }
      });
    },

    /* ---------- Smooth Scroll ---------- */
    initSmoothScroll() {
      document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', (e) => {
          const target = document.querySelector(anchor.getAttribute('href'));
          if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        });
      });
    },

    /* ---------- Scroll to Top ---------- */
    initScrollTop() {
      const scrollTop = document.querySelector('.scroll-top');
      if (!scrollTop) return;

      window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
          scrollTop.classList.add('visible');
        } else {
          scrollTop.classList.remove('visible');
        }
      });

      scrollTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    },

    /* ---------- Dropdowns ---------- */
    initDropdowns() {
      document.querySelectorAll('.dropdown').forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle, [data-toggle="dropdown"]');
        if (!toggle) return;

        toggle.addEventListener('click', (e) => {
          e.stopPropagation();
          document.querySelectorAll('.dropdown.open').forEach(d => {
            if (d !== dropdown) d.classList.remove('open');
          });
          dropdown.classList.toggle('open');
        });
      });

      document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
      });
    },

    /* ---------- Tabs ---------- */
    initTabs() {
      document.querySelectorAll('.tabs').forEach(tabGroup => {
        const tabs = tabGroup.querySelectorAll('.tab');
        const contents = tabGroup.parentElement.querySelectorAll('.tab-content');

        tabs.forEach((tab, index) => {
          tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));

            tab.classList.add('active');
            if (contents[index]) contents[index].classList.add('active');
          });
        });
      });
    },

    /* ---------- Modals ---------- */
    initModals() {
      // Open modal
      document.querySelectorAll('[data-modal]').forEach(trigger => {
        trigger.addEventListener('click', () => {
          const modalId = trigger.dataset.modal;
          const modal = document.getElementById(modalId);
          if (modal) modal.classList.add('active');
        });
      });

      // Close modal
      document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
          if (e.target === overlay || e.target.closest('.modal-close')) {
            overlay.classList.remove('active');
          }
        });
      });

      // Escape key
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
        }
      });
    },

    /* ---------- Search Clear ---------- */
    initSearchClear() {
      document.querySelectorAll('.search-box input').forEach(input => {
        const clearBtn = input.parentElement.querySelector('.clear-btn');
        if (!clearBtn) return;

        clearBtn.addEventListener('click', () => {
          input.value = '';
          input.focus();
          input.dispatchEvent(new Event('input'));
        });
      });
    },

    /* ---------- File Upload ---------- */
    initFileUpload() {
      document.querySelectorAll('.file-upload').forEach(upload => {
        const input = upload.querySelector('input[type="file"]');
        if (!input) return;

        upload.addEventListener('click', () => input.click());
        upload.addEventListener('dragover', (e) => {
          e.preventDefault();
          upload.classList.add('dragover');
        });
        upload.addEventListener('dragleave', () => upload.classList.remove('dragover'));
        upload.addEventListener('drop', (e) => {
          e.preventDefault();
          upload.classList.remove('dragover');
          if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
          }
        });
      });
    },

    /* ---------- Toast System ---------- */
    toastContainer: null,

    initToastSystem() {
      this.toastContainer = document.createElement('div');
      this.toastContainer.className = 'toast-container';
      document.body.appendChild(this.toastContainer);
    },

    showToast(message, type = 'info', duration = 3000) {
      if (!this.toastContainer) this.initToastSystem();

      const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
      };

      const toast = document.createElement('div');
      toast.className = `toast ${type}`;
      toast.innerHTML = `
        <span style="font-size:14px">${icons[type]}</span>
        <span style="font-size:12px">${message}</span>
      `;

      this.toastContainer.appendChild(toast);

      // Auto remove
      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        setTimeout(() => toast.remove(), 300);
      }, duration);

      // Click to dismiss
      toast.addEventListener('click', () => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        setTimeout(() => toast.remove(), 300);
      });
    },

    /* ---------- Mobile Navigation ---------- */
    initMobileNav() {
      const toggle = document.querySelector('.nav-toggle');
      const sidebar = document.querySelector('.sidebar');

      if (!toggle || !sidebar) return;

      toggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');

        // Create overlay
        let overlay = document.querySelector('.sidebar-overlay');
        if (sidebar.classList.contains('open')) {
          if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            overlay.style.cssText = `
              position: fixed; inset: 0; background: rgba(0,0,0,0.5);
              z-index: 98; backdrop-filter: blur(2px);
            `;
            overlay.addEventListener('click', () => {
              sidebar.classList.remove('open');
              overlay.remove();
            });
            document.body.appendChild(overlay);
          }
        } else if (overlay) {
          overlay.remove();
        }
      });
    },

    /* ---------- Theme Toggle ---------- */
    initThemeToggle() {
      const toggle = document.querySelector('[data-theme-toggle], #themeToggle');
      if (!toggle) return;

      const savedTheme = localStorage.getItem('theme') || 'dark';
      document.documentElement.setAttribute('data-theme', savedTheme);

      const syncIcon = (theme) => {
        const icon = toggle.querySelector('i');
        if (icon) {
          icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
      };

      syncIcon(savedTheme);

      if (window.AkkuTheme && typeof window.AkkuTheme.setTheme === 'function') {
        return;
      }

      toggle.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        syncIcon(next);
      });
    },

    /* ---------- Skeleton Loading ---------- */
    showSkeleton(container, count = 3) {
      container.innerHTML = '';
      for (let i = 0; i < count; i++) {
        const skeleton = document.createElement('div');
        skeleton.className = 'skeleton';
        skeleton.style.cssText = `
          height: 40px; margin-bottom: 8px; border-radius: 6px;
        `;
        container.appendChild(skeleton);
      }
    },

    /* ---------- Lazy Image Loading ---------- */
    initLazyImages() {
      const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const img = entry.target;
            img.src = img.dataset.src;
            img.classList.add('loaded');
            imageObserver.unobserve(img);
          }
        });
      });

      document.querySelectorAll('img[data-src]').forEach(img => imageObserver.observe(img));
    },

    /* ---------- Confetti Effect ---------- */
    confetti(colors = ['#6366f1', '#10b981', '#f59e0b', '#ec4899']) {
      const container = document.createElement('div');
      container.style.cssText = `
        position: fixed; inset: 0; pointer-events: none; z-index: 9999;
        overflow: hidden;
      `;
      document.body.appendChild(container);

      for (let i = 0; i < 50; i++) {
        const conf = document.createElement('div');
        const color = colors[Math.floor(Math.random() * colors.length)];
        const left = Math.random() * 100;
        const delay = Math.random() * 2;
        const duration = 1 + Math.random() * 2;

        conf.style.cssText = `
          position: absolute;
          width: ${4 + Math.random() * 6}px;
          height: ${4 + Math.random() * 6}px;
          background: ${color};
          left: ${left}%;
          top: -10px;
          border-radius: ${Math.random() > 0.5 ? '50%' : '2px'};
          animation: confettiFall ${duration}s ${delay}s ease-in forwards;
        `;
        container.appendChild(conf);
      }

      // Add keyframes if not exists
      if (!document.getElementById('confetti-style')) {
        const style = document.createElement('style');
        style.id = 'confetti-style';
        style.textContent = `
          @keyframes confettiFall {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
          }
        `;
        document.head.appendChild(style);
      }

      setTimeout(() => container.remove(), 4000);
    },

    /* ---------- Loading Overlay ---------- */
    showLoading(message = 'Loading...') {
      const overlay = document.createElement('div');
      overlay.id = 'akku-loading-overlay';
      overlay.style.cssText = `
        position: fixed; inset: 0; background: rgba(15,15,18,0.8);
        backdrop-filter: blur(4px); z-index: 5000;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: 12px;
      `;
      overlay.innerHTML = `
        <div class="loading loading-lg"></div>
        <span style="color: var(--text-secondary); font-size: 13px;">${message}</span>
      `;
      document.body.appendChild(overlay);
    },

    hideLoading() {
      const overlay = document.getElementById('akku-loading-overlay');
      if (overlay) overlay.remove();
    },

    /* ---------- AJAX with Loading ---------- */
    async fetch(url, options = {}) {
      this.showLoading(options.loadingMessage || 'Loading...');
      try {
        const response = await fetch(url, options);
        const data = await response.json();
        return data;
      } catch (error) {
        this.showToast('Network error occurred', 'error');
        throw error;
      } finally {
        this.hideLoading();
      }
    }
  };

  // Auto-init on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => AkkuAnimations.init());
  } else {
    AkkuAnimations.init();
  }

  // Expose globally
  window.AkkuAnimations = AkkuAnimations;
})();
