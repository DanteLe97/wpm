// Halloween Effects JavaScript

class HalloweenEffects {
    constructor() {
        this.isActive = true;
        
        // Get settings from data attributes
        const container = document.querySelector('.mac-animation-container');
        
        // Get customizable settings
        this.enableCustomCursor = container ? (container.getAttribute('data-enable-custom-cursor') || container.dataset.enableCustomCursor || 'true') === 'true' : true;
        this.enableFixedDecorations = container ? (container.getAttribute('data-enable-fixed-decorations') || container.dataset.enableFixedDecorations || 'true') === 'true' : true;
        this.enableBatSwarm = container ? (container.getAttribute('data-enable-bat-swarm') || container.dataset.enableBatSwarm || 'true') === 'true' : true;
        this.enableClickEffects = container ? (container.getAttribute('data-enable-click-effects') || container.dataset.enableClickEffects || 'true') === 'true' : true;
        
        // Get image URLs from data attributes (will be injected by loader from config)
        // Get plugin URL from data attribute or calculate from script src
        let pluginUrl = container ? (container.getAttribute('data-plugin-url') || container.dataset.pluginUrl || '') : '';
        
        // If no plugin URL, try to calculate from script src
        if (!pluginUrl) {
            const scripts = document.getElementsByTagName('script');
            for (let i = 0; i < scripts.length; i++) {
                const src = scripts[i].src;
                if (src && src.includes('halloween/style-2/script.js')) {
                    // Extract plugin URL from script src
                    const match = src.match(/(.*\/mac-seasonal-effects\/)/);
                    if (match) {
                        pluginUrl = match[1];
                    }
                    break;
                }
            }
        }
        
        const fallbackBase = pluginUrl ? pluginUrl + 'animations/halloween/style-2/images/' : '';
        
        this.cursorDefault = container ? (container.getAttribute('data-cursor-default') || container.dataset.cursorDefault || fallbackBase + 'download-1.png') : fallbackBase + 'download-1.png';
        this.cursorPointer = container ? (container.getAttribute('data-cursor-pointer') || container.dataset.cursorPointer || fallbackBase + 'download-2.png') : fallbackBase + 'download-2.png';
        
        // Get pumpkin tree image - use getAttribute for better compatibility with multi-hyphenated attributes
        const pumpkinTreeAttr = container ? container.getAttribute('data-pumpkin-tree') : null;
        this.pumpkinTree = pumpkinTreeAttr || (container ? container.dataset.pumpkinTree : null) || fallbackBase + 'trick-treat3-img.png';
        
        // Debug log for pumpkin tree
        if (pumpkinTreeAttr) {
            console.log('MAC Seasonal Effects: Found pumpkin_tree from data attribute:', pumpkinTreeAttr);
        } else {
            console.log('MAC Seasonal Effects: Using fallback for pumpkin_tree:', this.pumpkinTree);
        }
        
        this.floatingGhost = container ? (container.getAttribute('data-floating-ghost') || container.dataset.floatingGhost || fallbackBase + 'category1-img.png') : fallbackBase + 'category1-img.png';
        this.batImage = container ? (container.getAttribute('data-bat-image') || container.dataset.batImage || fallbackBase + 'download-3.png') : fallbackBase + 'download-3.png';
        
        // Get animation settings
        this.pumpkinCount = container ? parseInt(container.getAttribute('data-pumpkin-count') || container.dataset.pumpkinCount || '3') : 3;
        this.pumpkinSpawnIntervalMin = container ? parseInt(container.getAttribute('data-pumpkin-spawn-interval-min') || container.dataset.pumpkinSpawnIntervalMin || '200') : 200;
        this.pumpkinSpawnIntervalMax = container ? parseInt(container.getAttribute('data-pumpkin-spawn-interval-max') || container.dataset.pumpkinSpawnIntervalMax || '800') : 800;
        this.pumpkinFallDurationMin = container ? parseFloat(container.getAttribute('data-pumpkin-fall-duration-min') || container.dataset.pumpkinFallDurationMin || '4') : 4;
        this.pumpkinFallDurationMax = container ? parseFloat(container.getAttribute('data-pumpkin-fall-duration-max') || container.dataset.pumpkinFallDurationMax || '12') : 12;
        this.batSwarmCount = container ? parseInt(container.getAttribute('data-bat-swarm-count') || container.dataset.batSwarmCount || '90') : 90;
        this.clickCreatureCount = container ? parseInt(container.getAttribute('data-click-creature-count') || container.dataset.clickCreatureCount || '12') : 12;
        
        // Set CSS variables for cursor
        if (this.enableCustomCursor && container) {
            document.documentElement.style.setProperty('--cursor-default', `url('${this.cursorDefault}'), auto`);
            document.documentElement.style.setProperty('--cursor-pointer', `url('${this.cursorPointer}'), pointer`);
            // Also set for background-image (button ghosts)
            document.documentElement.style.setProperty('--cursor-pointer-bg', `url('${this.cursorPointer}')`);
        }
        
        // Load pumpkin images from data attributes or use fallback defaults
        // Falling pumpkins images (for falling animation) - always ensure 8 images
        const defaultFallingImages = [
            'trick-treat3-img.png',
            'trick-treat5-img.png',
            'home1-img.png',
            'home2-img.png',
            'about-img.png',
            'category1-img.png',
            'category2-img.png',
            'category3-img.png'
        ];
        
        this.images = [];
        for (let i = 1; i <= 8; i++) {
            const attrName = 'data-falling-pumpkin-' + i;
            const attrValue = container ? container.getAttribute(attrName) : null;
            if (attrValue) {
                this.images.push(attrValue);
            } else if (fallbackBase && i <= defaultFallingImages.length) {
                // Fallback to default images if no custom image provided
                this.images.push(fallbackBase + defaultFallingImages[i - 1]);
            }
        }
        
        // Click creatures images (for click burst effects) - always ensure 5 images
        const defaultClickImages = [
            'category1-img.png',
            'category2-img.png',
            'category3-img.png',
            'home2-img.png',
            'home1-img.png'
        ];
        
        this.images_2 = [];
        for (let i = 1; i <= 5; i++) {
            const attrName = 'data-click-creature-' + i;
            const attrValue = container ? container.getAttribute(attrName) : null;
            if (attrValue) {
                this.images_2.push(attrValue);
            } else if (fallbackBase && i <= defaultClickImages.length) {
                // Fallback to default images if no custom image provided
                this.images_2.push(fallbackBase + defaultClickImages[i - 1]);
            }
        }
        
        // Warn if no images loaded
        if (this.images.length === 0 && this.images_2.length === 0) {
            console.warn('MAC Seasonal Effects: No images loaded. Plugin URL may not be properly injected.');
        }
        
        // Cache screen dimensions
        this.screenWidth = window.innerWidth;
        this.screenHeight = window.innerHeight;
        
        // Pre-calculate size options
        this.sizeOptions = [
            { width: 20, opacity: 0.6 },
            { width: 30, opacity: 0.7 },
            { width: 45, opacity: 0.8 },
            { width: 65, opacity: 0.9 },
            { width: 90, opacity: 0.95 }
        ];
        
        // Animation timing cache
        this.timingCache = {
            minDuration: this.pumpkinFallDurationMin,
            maxDuration: this.pumpkinFallDurationMax,
            minDelay: 0,
            maxDelay: 1,
            spawnInterval: { min: this.pumpkinSpawnIntervalMin, max: this.pumpkinSpawnIntervalMax }
        };
        
        this.init();
    }

    // Optimized random utilities
    getRandomXPosition() {
        return -100 + Math.random() * (this.screenWidth + 200);
    }

    getRandomYStart() {
        return -300 + Math.random() * 250;
    }

    getRandomSize() {
        return this.sizeOptions[Math.floor(Math.random() * this.sizeOptions.length)];
    }

    getRandomAnimationDuration() {
        const { minDuration, maxDuration } = this.timingCache;
        return minDuration + Math.random() * (maxDuration - minDuration);
    }

    getRandomAnimationDelay() {
        const { minDelay, maxDelay } = this.timingCache;
        return minDelay + Math.random() * (maxDelay - minDelay);
    }

    updateScreenDimensions() {
        this.screenWidth = window.innerWidth;
        this.screenHeight = window.innerHeight;
    }

    init() {
        const initEffects = () => {
            this.startEffects();
            if (this.enableClickEffects) {
                this.setupClickBurst();
            }
            // Setup event delegation for pumpkins (better performance)
            this.setupPumpkinClickDelegation();
            this.preloadImages();
            this.setupResizeHandler();
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initEffects);
        } else {
            initEffects();
        }
    }
    
    // Event delegation for pumpkins - better performance than individual listeners
    setupPumpkinClickDelegation() {
        // Use single event listener on document with event delegation
        document.addEventListener('click', (e) => {
            if (!this.isActive) return;
            
            // Check if clicked element is a pumpkin
            const pumpkin = e.target.closest('.halloween-pumpkin');
            if (!pumpkin) return;
            
            // Prevent multiple rapid clicks
            if (pumpkin.classList.contains('exploding') || pumpkin.classList.contains('clicked')) {
                return;
            }
            
            pumpkin.classList.add('clicked');
            
            // Get position immediately (before any DOM changes)
            const rect = pumpkin.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            
            // Create click burst if enabled
            if (this.enableClickEffects) {
                this.createClickBurst(centerX, centerY);
            }
            
            // Add exploding class - CSS transition will handle animation
            pumpkin.classList.add('exploding');
            
            // Remove after transition completes (use transitionend event)
            const handleTransitionEnd = (event) => {
                if (event.target === pumpkin && event.propertyName === 'opacity') {
                    pumpkin.removeEventListener('transitionend', handleTransitionEnd);
                    if (pumpkin.parentNode) {
                        pumpkin.parentNode.removeChild(pumpkin);
                    }
                }
            };
            
            pumpkin.addEventListener('transitionend', handleTransitionEnd, { once: true });
            
            // Fallback: remove after 400ms if transitionend doesn't fire
            setTimeout(() => {
                if (pumpkin.parentNode) {
                    pumpkin.removeEventListener('transitionend', handleTransitionEnd);
                    pumpkin.parentNode.removeChild(pumpkin);
                }
            }, 400);
        }, true); // Use capture phase for better performance
    }

    setupResizeHandler() {
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.updateScreenDimensions();
            }, 250);
        });
    }

    preloadImages() {
        this.images.forEach(src => {
            const img = new Image();
            img.src = src;
        });
    }

    startEffects() {
        if (!this.isActive) return;

        // Create initial pumpkins
        for (let i = 0; i < this.pumpkinCount; i++) {
            setTimeout(() => this.createFallingPumpkin(), i * 100);
        }
        
        // Start continuous pumpkin rain
        this.scheduleNextPumpkin();
        
        // Add fixed decorations
        if (this.enableFixedDecorations) {
            this.createFixedDecorations();
        }
        
        // Create bat swarm on page load
        if (this.enableBatSwarm) {
            this.createBatSwarm();
        }

        // Add halloween class to body
        document.body.classList.add('halloween-loaded');
    }

    createFallingPumpkin() {
        if (!this.isActive) return;

        const pumpkin = document.createElement('div');
        pumpkin.className = 'halloween-pumpkin';
        
        const img = document.createElement('img');
        img.src = this.images[Math.floor(Math.random() * this.images.length)];
        
        const size = this.getRandomSize();
        const randomX = this.getRandomXPosition();
        const startY = this.getRandomYStart();
        const duration = this.getRandomAnimationDuration();
        const delay = this.getRandomAnimationDelay();
        
        pumpkin.style.setProperty('--pumpkin-width', `${size.width}px`);
        pumpkin.style.setProperty('--pumpkin-opacity', size.opacity);
        pumpkin.style.setProperty('--pumpkin-x', `${randomX}px`);
        pumpkin.style.setProperty('--pumpkin-y', `${startY}px`);
        pumpkin.style.setProperty('--animation-duration', `${duration}s`);
        pumpkin.style.setProperty('--animation-delay', `${delay}s`);
        
        pumpkin.appendChild(img);
        // No individual click listener - using event delegation in setupPumpkinClickDelegation()
        
        document.body.appendChild(pumpkin);

        setTimeout(() => {
            if (pumpkin.parentNode) {
                pumpkin.parentNode.removeChild(pumpkin);
            }
        }, 20000);
    }
    
    scheduleNextPumpkin() {
        if (!this.isActive) return;
        
        const { min, max } = this.timingCache.spawnInterval;
        const randomInterval = min + Math.random() * (max - min);
        
        setTimeout(() => {
            if (this.isActive) {
                this.createFallingPumpkin();
                this.scheduleNextPumpkin();
            }
        }, randomInterval);
    }

    createFixedDecorations() {
        this.createFixedPumpkinTree();
        
        if (window.innerWidth > 768) {
            this.createSideFloatingGhost();
        }
    }
    
    createFixedPumpkinTree() {
        const pumpkinTree = document.createElement('div');
        pumpkinTree.className = 'fixed-pumpkin-tree';
        
        const img = document.createElement('img');
        const imageUrl = this.pumpkinTree; // Store in variable for use in callbacks
        img.src = imageUrl;
        
        // Debug log
        console.log('MAC Seasonal Effects: Creating fixed pumpkin tree with image:', imageUrl);
        
        // Add error handler to check if image loads
        img.onerror = function() {
            console.error('MAC Seasonal Effects: Failed to load pumpkin tree image:', imageUrl);
        };
        img.onload = function() {
            console.log('MAC Seasonal Effects: Successfully loaded pumpkin tree image:', imageUrl);
        };
        
        pumpkinTree.appendChild(img);
        document.body.appendChild(pumpkinTree);
    }
    
    createSideFloatingGhost() {
        const ghost = document.createElement('div');
        ghost.className = 'side-floating-ghost';
        ghost.style.setProperty('--ghost-top', '10%');
        
        const img = document.createElement('img');
        img.src = this.floatingGhost;
        
        ghost.appendChild(img);
        document.body.appendChild(ghost);
        
        let direction = 1;
        let position = 10;
        
        const animate = () => {
            if (!this.isActive || !ghost.parentNode) {
                return;
            }
            
            position += direction * 0.1;
            
            if (position >= 70) direction = -1;
            if (position <= 10) direction = 1;
            
            ghost.style.setProperty('--ghost-top', `${position}%`);
            
            requestAnimationFrame(animate);
        };
        
        requestAnimationFrame(animate);
    }
    
    createBatSwarm() {
        const batCount = this.batSwarmCount + Math.floor(Math.random() * 11);
        
        for (let i = 0; i < batCount; i++) {
            this.createSwarmBat(i, batCount);
        }
    }
    
    createSwarmBat(index, totalCount) {
        if (!this.isActive) return;
        
        const bat = document.createElement('div');
        bat.className = 'swarm-bat';
        bat.style.setProperty('--bat-z-index', '9999');
        
        const img = document.createElement('img');
        img.src = this.batImage;
        
        bat.appendChild(img);
        
        const centerX = window.innerWidth / 2;
        const centerY = window.innerHeight / 2;
        
        bat.style.setProperty('--bat-x', `${centerX}px`);
        bat.style.setProperty('--bat-y', `${centerY}px`);
        
        document.body.appendChild(bat);
        
        const angle = (Math.PI * 2 * index) / totalCount + (Math.random() - 0.5) * 0.3;
        const speed = 8 + Math.random() * 12;
        
        let posX = centerX;
        let posY = centerY;
        let currentSize = 30 + Math.random() * 25;
        let frame = 0;
        const maxFrames = 120;
        
        bat.style.setProperty('--bat-size', `${currentSize}px`);
        
        const animate = () => {
            if (!bat.parentNode || !this.isActive || frame >= maxFrames) {
                if (bat.parentNode) {
                    bat.parentNode.removeChild(bat);
                }
                return;
            }
            
            frame++;
            
            posX += Math.cos(angle) * speed;
            posY += Math.sin(angle) * speed;
            
            bat.style.setProperty('--bat-x', `${posX}px`);
            bat.style.setProperty('--bat-y', `${posY}px`);
            
            const progress = frame / maxFrames;
            const opacity = 1 - progress;
            const sizeMultiplier = 1 + progress * 0.5;
            
            bat.style.setProperty('--bat-opacity', opacity);
            bat.style.setProperty('--bat-size', `${currentSize * sizeMultiplier}px`);
            
            requestAnimationFrame(animate);
        };
        
        requestAnimationFrame(animate);
    }
    
    setupClickBurst() {
        // Throttle click burst to prevent performance issues
        let lastClickTime = 0;
        const clickThrottle = 50; // Minimum 50ms between bursts
        
        // Single event listener with throttling
        document.addEventListener('click', (e) => {
            if (!this.isActive) return;
            
            // Skip if clicked on pumpkin (handled by delegation)
            if (e.target.closest('.halloween-pumpkin')) {
                return;
            }
            
            const now = Date.now();
            if (now - lastClickTime < clickThrottle) {
                return; // Throttle rapid clicks
            }
            lastClickTime = now;
            
            // Create burst at click position
            this.createClickBurst(e.clientX, e.clientY);
            
            // Extra burst for buttons
            const isButton = e.target.closest('button, .elementor-button, [role="button"], input[type="submit"], input[type="button"], .btn, .button');
            if (isButton) {
                // Use requestAnimationFrame for better performance
                requestAnimationFrame(() => {
                    this.createClickBurst(e.clientX, e.clientY);
                });
            }
        }, true); // Use capture phase
    }

    createClickBurst(x, y) {
        if (!x || !y || x < 0 || y < 0) return;
        
        // Reduce creature count if too many active creatures (performance optimization)
        const activeCreatures = document.querySelectorAll('.click-creature').length;
        let creatureCount = this.clickCreatureCount + Math.floor(Math.random() * 9);
        
        // Limit total active creatures to prevent performance issues
        if (activeCreatures > 50) {
            creatureCount = Math.min(creatureCount, 5); // Reduce to max 5 if too many active
        } else if (activeCreatures > 30) {
            creatureCount = Math.min(creatureCount, 8); // Reduce to max 8
        }
        
        // Use requestAnimationFrame to batch DOM operations
        requestAnimationFrame(() => {
            for (let i = 0; i < creatureCount; i++) {
                this.createFlyingCreature(x, y, i, creatureCount);
            }
        });
    }
    
    createFlyingCreature(startX, startY, index, totalCount) {
        const creature = document.createElement('div');
        creature.className = 'click-creature';
        
        // Optimize: Set will-change for better performance
        creature.style.willChange = 'transform, opacity';
        
        const img = document.createElement('img');
        img.src = this.images_2[Math.floor(Math.random() * this.images_2.length)];
        
        const size = 20 + Math.random() * 25;
        creature.style.setProperty('--creature-size', `${size}px`);
        
        creature.appendChild(img);
        
        creature.style.setProperty('--creature-x', `${startX - size/2}px`);
        creature.style.setProperty('--creature-y', `${startY - size/2}px`);
        creature.style.setProperty('--creature-opacity', '0.8');
        
        document.body.appendChild(creature);
        
        const angle = (Math.PI * 2 * index) / totalCount + (Math.random() - 0.5) * 1.2;
        const speed = 4 + Math.random() * 6;
        const lifespan = 180 + Math.random() * 120;
        
        let frame = 0;
        let posX = startX - size/2;
        let posY = startY - size/2;
        let velocityX = Math.cos(angle) * speed;
        let velocityY = Math.sin(angle) * speed - 2.5;
        
        // Cache values to reduce calculations
        let lastOpacity = 0.8;
        let lastScale = 1;
        let lastTranslateX = 0;
        let lastTranslateY = 0;
        
        const animate = () => {
            if (!creature.parentNode || frame >= lifespan) {
                // Clean up will-change
                creature.style.willChange = 'auto';
                if (creature.parentNode) {
                    creature.parentNode.removeChild(creature);
                }
                return;
            }
            
            frame++;
            
            posX += velocityX;
            posY += velocityY;
            
            velocityX += (Math.random() - 0.5) * 0.3;
            velocityY += 0.05;
            
            const progress = frame / lifespan;
            const opacity = 0.8 * (1 - progress);
            const scale = 0.5 + (1 - progress) * 0.5;
            const translateX = posX - startX + size/2;
            const translateY = posY - startY + size/2;
            
            // Only update if values changed significantly (reduce DOM updates)
            if (Math.abs(opacity - lastOpacity) > 0.01 || 
                Math.abs(scale - lastScale) > 0.01 ||
                Math.abs(translateX - lastTranslateX) > 1 ||
                Math.abs(translateY - lastTranslateY) > 1) {
                
                creature.style.setProperty('--creature-opacity', opacity);
                creature.style.setProperty('--creature-scale', scale);
                creature.style.setProperty('--creature-translate-x', `${translateX}px`);
                creature.style.setProperty('--creature-translate-y', `${translateY}px`);
                
                lastOpacity = opacity;
                lastScale = scale;
                lastTranslateX = translateX;
                lastTranslateY = translateY;
            }
            
            requestAnimationFrame(animate);
        };
        
        requestAnimationFrame(animate);
    }

    cleanupElements() {
        document.querySelectorAll('.halloween-pumpkin, .click-creature, .fixed-pumpkin-tree, .side-floating-ghost, .swarm-bat').forEach(el => {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        });
    }

    toggle() {
        this.isActive = !this.isActive;
        if (!this.isActive) {
            this.cleanupElements();
        }
    }

    destroy() {
        this.isActive = false;
        this.cleanupElements();
        document.body.classList.remove('halloween-loaded');
    }
}

// Initialize Halloween Effects - wait for container to be available
let halloweenFX = null;

function initHalloweenEffects() {
    // Check if container exists
    const container = document.querySelector('.mac-animation-container');
    
    if (!container) {
        // Container not found yet, wait a bit and try again
        console.log('MAC Seasonal Effects: Container not found, retrying...');
        setTimeout(initHalloweenEffects, 100);
        return;
    }
    
    // Container found, check data attributes
    console.log('MAC Seasonal Effects: Container found, checking data attributes...');
    const pumpkinTreeAttr = container.getAttribute('data-pumpkin-tree');
    console.log('MAC Seasonal Effects: data-pumpkin-tree attribute:', pumpkinTreeAttr);
    console.log('MAC Seasonal Effects: All data attributes:', Array.from(container.attributes).map(attr => attr.name + '="' + attr.value + '"').join(', '));
    
    // Container found, initialize
    console.log('MAC Seasonal Effects: Initializing Halloween Effects');
    halloweenFX = new HalloweenEffects();
    
    // Global functions for easy control
    window.toggleHalloweenEffects = () => halloweenFX ? halloweenFX.toggle() : null;
    window.destroyHalloweenEffects = () => halloweenFX ? halloweenFX.destroy() : null;
    
    // Auto cleanup on page unload
    window.addEventListener('beforeunload', () => {
        if (halloweenFX) {
            halloweenFX.destroy();
        }
    });
    
    console.log('🎃 Halloween Effects Loaded! Use toggleHalloweenEffects() or destroyHalloweenEffects() in console to control.');
}

// Try to initialize immediately if DOM is ready, otherwise wait
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHalloweenEffects);
} else {
    // DOM already loaded, but container might be injected later via wp_footer
    // Try immediately, then retry if not found
    initHalloweenEffects();
}

