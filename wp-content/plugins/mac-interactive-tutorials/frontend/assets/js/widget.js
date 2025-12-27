/**
 * Frontend Widget JavaScript
 */
(function($) {
    'use strict';
    
    class MacTutorialWidget {
        constructor(data) {
            this.tutorial = data.tutorial;
            // Ensure current_step is a valid number (0-based index)
            let currentStep = data.current_step;
            if (typeof currentStep !== 'number' || isNaN(currentStep)) {
                currentStep = parseInt(currentStep) || 0;
            }
            this.currentStep = Math.max(0, currentStep); // Ensure non-negative
            this.status = data.status || 'in-progress';
            this.ajaxUrl = data.ajax_url;
            this.nonce = data.nonce;
            this.isActive = this.status === 'in-progress';
            
            console.log('MAC Tutorial: Constructor - current_step from data:', data.current_step, 'parsed to:', this.currentStep);
            
            this.init();
        }
        
        init() {
            this.createWidget();
            this.setupEvents();
            
            // Ensure currentStep is valid before loading
            console.log('MAC Tutorial: Init - currentStep:', this.currentStep, 'type:', typeof this.currentStep);
            
            // Validate and fix currentStep if needed
            if (typeof this.currentStep !== 'number' || isNaN(this.currentStep)) {
                console.warn('MAC Tutorial: Invalid currentStep in init, fixing...');
                this.currentStep = parseInt(this.currentStep) || 0;
            }
            this.currentStep = Math.max(0, Math.floor(this.currentStep));
            
            this.loadStep(this.currentStep);
            
            if (this.status === 'pause') {
                this.pause();
            }
        }
        
        createWidget() {
            const widget = `
                <div id="mac-tutorial-widget" class="mac-tutorial-widget">
                    <div class="mac-tutorial-widget__header">
                        <div class="mac-tutorial-widget__title">
                            <strong>${this.escapeHtml(this.tutorial.title)}</strong>
                        </div>
                        <div class="mac-tutorial-widget__actions">
                            <button class="mac-btn-pause" title="Pause">
                                <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20">
                                    <path d="M538.001-212.001v-535.998h209.998v535.998H538.001Zm-326 0v-535.998h209.998v535.998H212.001Z"/>
                                </svg>
                            </button>
                            <button class="mac-btn-close" title="Close">
                                <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20">
                                    <path d="M291-253.847 253.847-291l189-189-189-189L291-706.153l189 189 189-189L706.153-669l-189 189 189 189L669-253.847l-189-189-189 189Z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="mac-tutorial-widget__content">
                        <div class="mac-tutorial-widget__step-content"></div>
                        <div class="mac-tutorial-widget__progress">
                            <div class="mac-progress-bar">
                                <div class="mac-progress-fill" style="width: 0%"></div>
                            </div>
                            <div class="mac-progress-text">Step 0 of 0</div>
                        </div>
                    </div>
                    <div class="mac-tutorial-widget__footer">
                        <button class="mac-btn-prev">Previous</button>
                        <button class="mac-btn-next">Next</button>
                    </div>
                </div>
            `;
            
            $('#mac-tutorial-widget-container').html(widget);
            this.$widget = $('#mac-tutorial-widget');
            this.setupDrag();
        }
        
        setupEvents() {
            // Navigation buttons
            this.$widget.on('click', '.mac-btn-next', () => this.nextStep());
            this.$widget.on('click', '.mac-btn-prev', () => this.prevStep());
            this.$widget.on('click', '.mac-btn-pause', () => {
                if (this.isActive) {
                    this.pause();
                } else {
                    this.resume();
                }
            });
            this.$widget.on('click', '.mac-btn-close', () => this.close());
            
            // Keyboard shortcuts
            $(document).on('keydown.mac-tutorial', (e) => {
                if (!this.isActive) return;
                
                if (e.key === 'ArrowRight' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    this.nextStep();
                } else if (e.key === 'ArrowLeft' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    this.prevStep();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.pause();
                }
            });
        }
        
        setupDrag() {
            let isDragging = false;
            let startX, startY, startLeft, startTop;
            
            this.$widget.find('.mac-tutorial-widget__header').on('mousedown', (e) => {
                if ($(e.target).closest('button').length) return;
                
                isDragging = true;
                startX = e.clientX;
                startY = e.clientY;
                
                const offset = this.$widget.offset();
                startLeft = offset.left;
                startTop = offset.top;
                
                $(document).on('mousemove.mac-tutorial-drag', (e) => {
                    if (!isDragging) return;
                    
                    const deltaX = e.clientX - startX;
                    const deltaY = e.clientY - startY;
                    
                    this.$widget.css({
                        left: (startLeft + deltaX) + 'px',
                        top: (startTop + deltaY) + 'px',
                        right: 'auto',
                        bottom: 'auto'
                    });
                });
                
                $(document).on('mouseup.mac-tutorial-drag', () => {
                    isDragging = false;
                    $(document).off('mousemove.mac-tutorial-drag mouseup.mac-tutorial-drag');
                    
                    // Save position
                    const position = {
                        left: this.$widget.css('left'),
                        top: this.$widget.css('top')
                    };
                    localStorage.setItem('mac_tutorial_widget_position', JSON.stringify(position));
                });
                
                e.preventDefault();
            });
            
            // Load saved position
            const savedPosition = localStorage.getItem('mac_tutorial_widget_position');
            if (savedPosition) {
                try {
                    const pos = JSON.parse(savedPosition);
                    this.$widget.css(pos);
                } catch (e) {
                    console.error('Failed to parse saved position');
                }
            }
        }
        
        loadStep(stepIndex) {
            // Validate stepIndex
            if (typeof stepIndex !== 'number' || isNaN(stepIndex)) {
                console.warn('MAC Tutorial: Invalid stepIndex:', stepIndex, 'type:', typeof stepIndex, 'using currentStep:', this.currentStep);
                stepIndex = this.currentStep;
            }
            
            // Ensure stepIndex is an integer
            stepIndex = Math.floor(stepIndex);
            
            // Ensure stepIndex is within valid range
            if (!this.tutorial.steps || !Array.isArray(this.tutorial.steps) || this.tutorial.steps.length === 0) {
                console.error('MAC Tutorial: No steps available');
                return;
            }
            
            const totalSteps = this.tutorial.steps.length;
            
            // Clamp stepIndex to valid range
            stepIndex = Math.max(0, Math.min(stepIndex, totalSteps - 1));
            
            // Update currentStep to ensure consistency
            this.currentStep = stepIndex;
            
            const step = this.tutorial.steps[stepIndex];
            
            if (!step) {
                console.error('MAC Tutorial: Step not found at index', stepIndex);
                return;
            }
            
            // Calculate progress (0-100%)
            const progress = totalSteps > 0 ? ((stepIndex + 1) / totalSteps) * 100 : 0;
            
            // Update content
            const stepContent = `
                <h3>${this.escapeHtml(step.title || 'Step ' + (stepIndex + 1))}</h3>
                <div class="mac-step-description">${step.description || ''}</div>
                ${step.target_selector ? `<div class="mac-step-hint">Look for: <code>${this.escapeHtml(step.target_selector)}</code></div>` : ''}
            `;
            
            this.$widget.find('.mac-tutorial-widget__step-content').html(stepContent);
            
            // Update progress with validation
            const progressPercent = Math.max(0, Math.min(100, progress));
            const progressText = `Step ${stepIndex + 1} of ${totalSteps}`;
            
            // Debug log
            console.log('MAC Tutorial: Loading step', stepIndex + 1, 'of', totalSteps, 'Progress:', progressPercent + '%');
            
            this.$widget.find('.mac-progress-fill').css('width', progressPercent + '%');
            this.$widget.find('.mac-progress-text').text(progressText);
            
            // Update buttons
            this.$widget.find('.mac-btn-prev').prop('disabled', stepIndex === 0);
            const isLastStep = stepIndex === totalSteps - 1;
            this.$widget.find('.mac-btn-next').text(isLastStep ? 'Complete' : 'Next');
            
            // Highlight element if selector provided
            if (step.target_selector) {
                this.highlightElement(step.target_selector);
            }
            
            // Update state (async, don't wait for it)
            this.updateState('update_step', stepIndex);
        }
        
        highlightElement(selector) {
            // Remove previous highlight
            $('.mac-tutorial-highlight').removeClass('mac-tutorial-highlight');
            
            // Find element
            const $element = $(selector);
            if ($element.length) {
                $element.addClass('mac-tutorial-highlight');
                
                // Scroll to element
                $('html, body').animate({
                    scrollTop: $element.offset().top - 100
                }, 500);
            } else {
                // Retry after a short delay (for dynamic content)
                setTimeout(() => {
                    const $retryElement = $(selector);
                    if ($retryElement.length) {
                        $retryElement.addClass('mac-tutorial-highlight');
                        $('html, body').animate({
                            scrollTop: $retryElement.offset().top - 100
                        }, 500);
                    }
                }, 500);
            }
        }
        
        nextStep() {
            if (!this.tutorial.steps || !Array.isArray(this.tutorial.steps)) {
                console.error('MAC Tutorial: No steps available');
                return;
            }
            
            const totalSteps = this.tutorial.steps.length;
            if (this.currentStep < totalSteps - 1) {
                const nextStepIndex = this.currentStep + 1;
                
                // Update step first
                this.currentStep = nextStepIndex;
                this.loadStep(this.currentStep);
                
                // Navigate to target URL if provided (wait for state to be saved)
                const step = this.tutorial.steps[this.currentStep];
                if (step && step.target_url) {
                    // Wait for state to be saved before navigating
                    this.updateStateAndNavigate('update_step', this.currentStep, step.target_url);
                }
            } else {
                this.complete();
            }
        }
        
        prevStep() {
            if (this.currentStep > 0) {
                const prevStepIndex = this.currentStep - 1;
                
                // Update step first
                this.currentStep = prevStepIndex;
                this.loadStep(this.currentStep);
                
                // Navigate to target URL if provided (wait for state to be saved)
                const step = this.tutorial.steps[this.currentStep];
                if (step && step.target_url) {
                    // Wait for state to be saved before navigating
                    this.updateStateAndNavigate('update_step', this.currentStep, step.target_url);
                }
            }
        }
        
        navigateToUrl(url) {
            if (!url) return;
            
            // Check if URL is absolute or relative
            if (url.startsWith('http://') || url.startsWith('https://')) {
                window.location.href = url;
            } else {
                // Relative URL - construct admin URL
                const adminUrl = this.ajaxUrl.replace('/admin-ajax.php', '/');
                window.location.href = adminUrl + url;
            }
        }
        
        pause() {
            this.updateState('pause', this.currentStep);
            this.isActive = false;
            this.$widget.addClass('mac-tutorial-paused');
            this.$widget.find('.mac-btn-pause').html(`
                <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20">
                    <path d="M356.001-252.156v-455.688L707.074-480 356.001-252.156Z"/>
                </svg>
            `).attr('title', 'Resume');
        }
        
        resume() {
            this.updateState('resume', this.currentStep);
            this.isActive = true;
            this.$widget.removeClass('mac-tutorial-paused');
            this.$widget.find('.mac-btn-pause').html(`
                <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20">
                    <path d="M538.001-212.001v-535.998h209.998v535.998H538.001Zm-326 0v-535.998h209.998v535.998H212.001Z"/>
                </svg>
            `).attr('title', 'Pause');
        }
        
        complete() {
            this.updateState('complete', this.currentStep);
            this.$widget.find('.mac-tutorial-widget__content').html(`
                <div class="mac-tutorial-complete">
                    <h3>🎉 Tutorial Complete!</h3>
                    <p>You've completed: <strong>${this.escapeHtml(this.tutorial.title)}</strong></p>
                    <button class="button button-primary mac-btn-close-widget">Close</button>
                </div>
            `);
            
            this.$widget.find('.mac-btn-close-widget').on('click', () => {
                this.close();
            });
        }
        
        close() {
            if (confirm('Are you sure you want to close this tutorial? You can resume it later from the tutorials list.')) {
                this.updateState('close', this.currentStep);
                this.$widget.fadeOut(300, () => {
                    $(document).off('keydown.mac-tutorial');
                    this.$widget.remove();
                });
            }
        }
        
        updateState(action, stepIndex, callback) {
            // Validate stepIndex
            if (typeof stepIndex !== 'number' || isNaN(stepIndex)) {
                stepIndex = this.currentStep;
            }
            
            console.log('MAC Tutorial: Updating state - action:', action, 'stepIndex:', stepIndex, 'currentStep:', this.currentStep);
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mac_tutorial_state',
                    action_type: action,
                    tutorial_id: this.tutorial.id,
                    step_index: stepIndex,
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        console.log('MAC Tutorial: State updated successfully', response.data);
                        if (typeof callback === 'function') {
                            callback(true);
                        }
                    } else {
                        console.error('MAC Tutorial: Failed to update state:', response.data);
                        if (typeof callback === 'function') {
                            callback(false);
                        }
                    }
                },
                error: (xhr, status, error) => {
                    console.error('MAC Tutorial: AJAX error:', error, xhr);
                    if (typeof callback === 'function') {
                        callback(false);
                    }
                }
            });
        }
        
        /**
         * Update state and navigate after state is saved
         */
        updateStateAndNavigate(action, stepIndex, targetUrl) {
            this.updateState(action, stepIndex, (success) => {
                if (success) {
                    // State saved successfully, now navigate
                    console.log('MAC Tutorial: State saved, navigating to:', targetUrl);
                    this.navigateToUrl(targetUrl);
                } else {
                    // State save failed, still navigate but log warning
                    console.warn('MAC Tutorial: State save failed, but navigating anyway');
                    this.navigateToUrl(targetUrl);
                }
            });
        }
        
        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
        }
    }
    
    // Initialize widget when data is available
    // Prevent multiple instances
    if (typeof MacTutorialData !== 'undefined') {
        $(document).ready(function() {
            // Remove any existing widget first
            if (window.macTutorialWidgetInstance) {
                console.log('MAC Tutorial: Removing existing widget instance');
                window.macTutorialWidgetInstance = null;
            }
            $('#mac-tutorial-widget-container').empty();
            
            // Create new instance
            console.log('MAC Tutorial: Initializing widget with step:', MacTutorialData.current_step);
            window.macTutorialWidgetInstance = new MacTutorialWidget(MacTutorialData);
        });
    }
    
})(jQuery);

