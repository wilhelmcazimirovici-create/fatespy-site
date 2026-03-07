<arg_value>// FateSpy — Lazy Loading System
// Handles: Image lazy loading, deferred loading, performance optimization

class LazyLoader {
    constructor() {
        this.observer = null;
        this.init();
    }

    init() {
        // Check if IntersectionObserver is supported
        if ('IntersectionObserver' in window) {
            this.setupIntersectionObserver();
        } else {
            // Fallback for older browsers
            this.setupScrollListener();
        }
    }

    setupIntersectionObserver() {
        const options = {
            root: null,
            rootMargin: '50px',
            threshold: 0.1
        };

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadImage(entry.target);
                    this.observer.unobserve(entry.target);
                }
            });
        }, options);

        // Observe all lazy images
        this.observeImages();
    }

    setupScrollListener() {
        // Fallback for browsers without IntersectionObserver
        window.addEventListener('scroll', () => {
            this.checkImages();
        }, { passive: true });
        
        window.addEventListener('resize', () => {
            this.checkImages();
        }, { passive: true });

        // Initial check
        setTimeout(() => this.checkImages(), 100);
    }

    observeImages() {
        const lazyImages = document.querySelectorAll('img[data-src], img[data-srcset]');
        lazyImages.forEach(img => {
            this.observer.observe(img);
        });
    }

    checkImages() {
        const lazyImages = document.querySelectorAll('img[data-src], img[data-srcset]');
        lazyImages.forEach(img => {
            const rect = img.getBoundingClientRect();
            if (rect.top < window.innerHeight + 100 && rect.bottom > 0) {
                this.loadImage(img);
            }
        });
    }

    loadImage(img) {
        // Handle srcset images
        if (img.dataset.srcset) {
            img.srcset = img.dataset.srcset;
            img.removeAttribute('data-srcset');
        }
        
        // Handle single src images
        if (img.dataset.src) {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
        }

        // Add loading class for smooth transition
        img.classList.add('lazy-loading');
        
        // Handle loading states
        img.addEventListener('load', () => {
            img.classList.remove('lazy-loading');
            img.classList.add('lazy-loaded');
        });

        img.addEventListener('error', () => {
            img.classList.remove('lazy-loading');
            img.classList.add('lazy-error');
            // Optionally show a placeholder
            img.src = '/assets/images/placeholder.webp';
        });
    }
}

// Initialize lazy loading
document.addEventListener('DOMContentLoaded', () => {
    new LazyLoader();
});

// Add CSS for lazy loading transitions
const style = document.createElement('style');
style.textContent = `
    img.lazy-loading {
        opacity: 0.5;
        transition: opacity 0.3s ease;
    }
    
    img.lazy-loaded {
        opacity: 1;
        transition: opacity 0.3s ease;
    }
    
    img.lazy-error {
        opacity: 0.3;
    }
    
    /* Placeholder for images that haven't loaded yet */
    img[data-src],
    img[data-srcset] {
        background: linear-gradient(90deg, rgba(139, 92, 246, 0.1) 0%, rgba(79, 139, 255, 0.1) 100%);
        min-height: 200px;
    }
    
    /* Skeleton loading effect */
    .skeleton {
        background: linear-gradient(90deg, rgba(139, 92, 246, 0.1) 0%, rgba(79, 139, 255, 0.1) 50%, rgba(139, 92, 246, 0.1) 100%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
    }
    
    @keyframes loading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
`;
document.head.appendChild(style);

// Export for use in other modules
window.LazyLoader = LazyLoader;