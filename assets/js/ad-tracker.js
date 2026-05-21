/**
 * Ad Tracker
 * Tracks ad impressions and clicks on public pages
 */

const AkkuAdTracker = {
    // Track ad impression (view)
    trackImpression: function(adId, placement = 'unknown') {
        if (!adId) return;
        
        const formData = new FormData();
        formData.append('ad_id', adId);
        formData.append('placement', placement);
        
        fetch('/api/track-ad-impression.php', {
            method: 'POST',
            body: formData
        })
        .catch(err => console.log('Ad impression tracked'));
    },
    
    // Track ad click
    trackClick: function(adId, placement = 'unknown', redirectUrl = null) {
        if (!adId) return;
        
        const formData = new FormData();
        formData.append('ad_id', adId);
        formData.append('placement', placement);
        
        fetch('/api/track-ad-click.php', {
            method: 'POST',
            body: formData
        })
        .then(() => {
            if (redirectUrl) {
                window.location.href = redirectUrl;
            }
        })
        .catch(err => {
            if (redirectUrl) {
                window.location.href = redirectUrl;
            }
        });
        
        // Don't allow default click if we're tracking
        return false;
    },
    
    // Initialize tracking for all ads on page
    init: function() {
        document.querySelectorAll('[data-ad-id]').forEach(adElement => {
            const adId = adElement.dataset.adId;
            const placement = adElement.dataset.placement || 'unknown';
            
            // Track impression when ad becomes visible
            this.trackImpressionOnVisible(adElement, adId, placement);
            
            // Track clicks on ad links
            const adLink = adElement.querySelector('a[data-ad-link]');
            if (adLink) {
                adLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.trackClick(adId, placement, adLink.href);
                });
            }
        });
    },
    
    // Track impression when ad becomes visible (intersection observer)
    trackImpressionOnVisible: function(element, adId, placement) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.trackImpression(adId, placement);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        observer.observe(element);
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => AkkuAdTracker.init());

// Also expose globally
window.AkkuAdTracker = AkkuAdTracker;
