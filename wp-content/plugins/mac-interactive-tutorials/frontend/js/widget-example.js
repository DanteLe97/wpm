/**
 * MAC Interactive Tutorials - Frontend Widget
 * 
 * File này minh họa cách implement floating widget
 */

(function($) {
    'use strict';
    
    class MacTutorialWidget {
        constructor(data) {
            this.tutorial = data.tutorial;
            this.currentStep = data.current_step || 0;
            this.ajaxUrl = data.ajax_url;
            this.nonce = data.nonce;
            this.isActive = true;
            
            this.init();
        }
        
        init() {
            this.createWidget();
            this.setupEvents();
            this.loadStep(this.currentStep);
        }
        
        createWidget() {
            const widget = `
                <div id="mac-tutorial-widget" class="mac-tutorial-widget">
                    <div class="mac-tutorial-widget__header">
                        <div class="mac-tutorial-widget__title">
                            <strong>${this.tutorial.title}</strong>
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
            this.$widget.on('click', '.mac-btn-pause', () => this.pause());
            this.$widget.on('click', '.mac-btn-close', () => this.close());
            
            // Keyboard shortcuts
            $(document).on('keydown', (e) => {
                if (!this.isActive) return;
                
                if (e.key === 'ArrowRight' && e.ctrlKey) {
                    e.preventDefault();
                    this.nextStep();
                } else if (e.key === 'ArrowLeft' && e.ctrlKey) {
                    e.preventDefault();
                    this.prevStep();
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
                
                $(document).on('mousemove.tutorial-drag', (e) => {
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
                
                $(document).on('mouseup.tutorial-drag', () => {
                    isDragging = false;
                    $(document).off('mousemove.tutorial-drag mouseup.tutorial-drag');
                    
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
                const pos = JSON.parse(savedPosition);
                this.$widget.css(pos);
            }
        }
        
        loadStep(stepIndex) {
            if (!this.tutorial.steps || !this.tutorial.steps[stepIndex]) {
                return;
            }
            
            const step = this.tutorial.steps[stepIndex];
            const totalSteps = this.tutorial.steps.length;
            const progress = ((stepIndex + 1) / totalSteps) * 100;
            
            // Update content
            const stepContent = `
                <h3>${step.title}</h3>
                <div class="mac-step-description">${step.description}</div>
                ${step.target_selector ? `<div class="mac-step-hint">Look for: <code>${step.target_selector}</code></div>` : ''}
            `;
            
            this.$widget.find('.mac-tutorial-widget__step-content').html(stepContent);
            
            // Update progress
            this.$widget.find('.mac-progress-fill').css('width', progress + '%');
            this.$widget.find('.mac-progress-text').text(`Step ${stepIndex + 1} of ${totalSteps}`);
            
            // Update buttons
            this.$widget.find('.mac-btn-prev').prop('disabled', stepIndex === 0);
            this.$widget.find('.mac-btn-next').text(stepIndex === totalSteps - 1 ? 'Complete' : 'Next');
            
            // Highlight element if selector provided
            if (step.target_selector) {
                this.highlightElement(step.target_selector);
            }
            
            // Update state
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
            }
        }
        
        nextStep() {
            if (this.currentStep < this.tutorial.steps.length - 1) {
                this.currentStep++;
                this.loadStep(this.currentStep);
                
                // Navigate to target URL if provided
                const step = this.tutorial.steps[this.currentStep];
                if (step.target_url) {
                    this.navigateToUrl(step.target_url);
                }
            } else {
                this.complete();
            }
        }
        
        prevStep() {
            if (this.currentStep > 0) {
                this.currentStep--;
                this.loadStep(this.currentStep);
                
                // Navigate to target URL if provided
                const step = this.tutorial.steps[this.currentStep];
                if (step.target_url) {
                    this.navigateToUrl(step.target_url);
                }
            }
        }
        
        navigateToUrl(url) {
            // Check if URL is absolute or relative
            if (url.startsWith('http://') || url.startsWith('https://')) {
                window.location.href = url;
            } else {
                // Relative URL - construct admin URL
                const adminUrl = ajaxurl.replace('/admin-ajax.php', '/');
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
                    <p>You've completed: <strong>${this.tutorial.title}</strong></p>
                </div>
            `);
        }
        
        close() {
            if (confirm('Are you sure you want to close this tutorial? You can resume it later.')) {
                this.pause();
                this.$widget.fadeOut();
            }
        }
        
        updateState(action, stepIndex) {
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
                    if (!response.success) {
                        console.error('Failed to update state:', response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error:', error);
                }
            });
        }
    }
    
    // Initialize widget when data is available
    if (typeof MacTutorialData !== 'undefined') {
        $(document).ready(function() {
            new MacTutorialWidget(MacTutorialData);
        });
    }
    
})(jQuery);

