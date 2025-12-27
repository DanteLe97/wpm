/**
 * Admin JavaScript for Step Builder
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        const $stepsBuilder = $('#mac-tutorial-steps-builder');
        if (!$stepsBuilder.length) {
            return;
        }
        
        const $stepsList = $stepsBuilder.find('.mac-steps-list');
        let stepIndex = $stepsList.find('.mac-step-item').length;
        
        // Add step
        $stepsBuilder.on('click', '.mac-add-step', function(e) {
            e.preventDefault();
            addStep();
        });
        
        // Remove step
        $stepsBuilder.on('click', '.mac-remove-step', function(e) {
            e.preventDefault();
            if (confirm(macTutorialAdmin.strings.removeConfirm)) {
                $(this).closest('.mac-step-item').remove();
                updateStepNumbers();
            }
        });
        
        // Make steps sortable
        if ($.fn.sortable) {
            $stepsList.sortable({
                handle: '.mac-step-header',
                axis: 'y',
                update: function() {
                    updateStepNumbers();
                }
            });
        }
        
        /**
         * Add new step
         */
        function addStep() {
            const template = $('#mac-step-template').html();
            const stepHtml = template
                .replace(/\{\{index\}\}/g, stepIndex)
                .replace(/\{\{number\}\}/g, stepIndex + 1);
            
            $stepsList.append(stepHtml);
            stepIndex++;
            updateStepNumbers();
            
            // Focus on new step title
            $stepsList.find('.mac-step-item').last().find('.mac-step-title').focus();
        }
        
        /**
         * Update step numbers
         */
        function updateStepNumbers() {
            $stepsList.find('.mac-step-item').each(function(index) {
                const $item = $(this);
                $item.attr('data-index', index);
                $item.find('.mac-step-number').text(index + 1);
                
                // Update input names
                $item.find('input, textarea, select').each(function() {
                    const $input = $(this);
                    const name = $input.attr('name');
                    if (name) {
                        const newName = name.replace(/mac_steps\[\d+\]/, 'mac_steps[' + index + ']');
                        $input.attr('name', newName);
                    }
                });
            });
        }
        
        // Initialize step numbers
        updateStepNumbers();
        
        // Handle editor switch
        $stepsBuilder.on('click', '.mac-switch-editor', function(e) {
            e.preventDefault();
            const $button = $(this);
            const stepIndex = $button.data('step-index');
            const $wrapper = $button.closest('.mac-step-content').find('.mac-description-wrapper[data-step-index="' + stepIndex + '"]');
            const $textareaWrapper = $wrapper.find('.mac-description-textarea-wrapper');
            const $editorWrapper = $wrapper.find('.mac-description-editor-wrapper');
            const $textarea = $textareaWrapper.find('textarea');
            
            // Toggle visibility
            if ($textareaWrapper.is(':visible')) {
                // Switch to editor
                const textareaContent = $textarea.val();
                
                // Check if editor already exists
                if ($editorWrapper.find('.wp-editor-container').length > 0) {
                    // Editor exists, just show it
                    const editorId = $editorWrapper.find('textarea.wp-editor-area').attr('id');
                    
                    if (editorId && typeof tinyMCE !== 'undefined') {
                        if (tinyMCE.get(editorId)) {
                            tinyMCE.get(editorId).setContent(textareaContent);
                        } else {
                            $editorWrapper.find('textarea.wp-editor-area').val(textareaContent);
                            tinyMCE.execCommand('mceAddEditor', false, editorId);
                        }
                    } else {
                        $editorWrapper.find('textarea.wp-editor-area').val(textareaContent);
                    }
                } else {
                    // Editor doesn't exist, create it via AJAX
                    createEditor($wrapper, stepIndex, textareaContent);
                    return; // Will continue in callback
                }
                
                $textareaWrapper.hide();
                $editorWrapper.show();
                $button.find('.mac-editor-mode-text').hide();
                $button.find('.mac-editor-mode-visual').show();
            } else {
                // Switch to textarea
                const editorId = $editorWrapper.find('textarea.wp-editor-area').attr('id');
                let editorContent = '';
                
                if (editorId && typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
                    // Get content from TinyMCE
                    editorContent = tinyMCE.get(editorId).getContent();
                } else {
                    // Get from textarea in editor
                    editorContent = $editorWrapper.find('textarea.wp-editor-area').val();
                }
                
                // Update textarea
                $textarea.val(editorContent);
                
                $editorWrapper.hide();
                $textareaWrapper.show();
                $button.find('.mac-editor-mode-text').show();
                $button.find('.mac-editor-mode-visual').hide();
            }
        });
        
        /**
         * Create editor via AJAX
         */
        function createEditor($wrapper, stepIndex, content) {
            const $editorWrapper = $wrapper.find('.mac-description-editor-wrapper');
            const namePrefix = 'mac_steps[' + stepIndex + '][description]';
            const editorId = 'mac_step_editor_' + stepIndex + '_' + Date.now();
            
            // Show loading
            $editorWrapper.html('<p>Loading editor...</p>');
            
            $.ajax({
                url: macTutorialAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mac_tutorial_get_editor',
                    step_index: stepIndex,
                    content: content,
                    editor_id: editorId,
                    textarea_name: namePrefix,
                    nonce: macTutorialAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data && response.data.editor_html) {
                        $editorWrapper.html(response.data.editor_html);
                        
                        // Initialize TinyMCE
                        if (typeof tinyMCE !== 'undefined') {
                            tinyMCE.execCommand('mceAddEditor', false, editorId);
                        }
                        
                        // Initialize quicktags
                        if (typeof QTags !== 'undefined') {
                            QTags._buttonsInit();
                        }
                        
                        // Update button state
                        const $button = $wrapper.closest('.mac-step-content').find('.mac-switch-editor');
                        $wrapper.closest('.mac-step-content').find('.mac-description-textarea-wrapper').hide();
                        $editorWrapper.show();
                        $button.find('.mac-editor-mode-text').hide();
                        $button.find('.mac-editor-mode-visual').show();
                    } else {
                        alert('Failed to load editor');
                        $editorWrapper.html('');
                    }
                },
                error: function() {
                    alert('Error loading editor');
                    $editorWrapper.html('');
                }
            });
        }
    });
    
})(jQuery);

