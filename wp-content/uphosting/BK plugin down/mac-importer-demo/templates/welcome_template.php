<?php
if (!function_exists('bk_welcome_template')) {
    function bk_welcome_template() {
       
    ?>
    <div class="page-wrap" style="margin: 20px 30px 0 2px;">

        <div class="nav-tab-wrapper">
            <a href="admin.php?page=bk-theme-welcome" class="nav-tab nav-tab-active">Import Demo</a>
        </div>   
        <div class="postbox bkpostbox">
        	<div class="hndle" style="padding: 15px 30px;">
                <h1><?php esc_html_e('Import Demo Content', 'ltp'); ?></h1>
                <p class="bk-admin-notice">
        			Import demo content and Elementor templates to your website quickly and easily.
        		</p>
            </div>
        	<div class="inside" style="margin: 30px -15px 30px -15px;">
        		<div class="main bk-welcome-main">
                    <div class="import-form-wrapper">
                        <form id="demo-import-form" method="post" action="" enctype="multipart/form-data">
                            <?php wp_nonce_field( 'bk_custom_demo_import', '_wpnonce' ); ?>
                            <div class="form-section">
                                <h3>Page Templates (JSON Files)</h3>
                                <p>Upload your JSON page templates. You can add or remove fields as needed.</p>
                                
                                <div id="page-fields-container">
                                    <!-- Page fields will be added here dynamically -->
                                </div>
                                
                                <div class="form-actions-inline">
                                    <button type="button" id="add-page-field" class="button button-secondary">
                                        <span class="dashicons dashicons-plus-alt"></span> Add Page Field
                                    </button>
                                    <!-- <button type="button" id="remove-page-field" class="button button-secondary" style="display: none;">
                                        <span class="dashicons dashicons-minus"></span> Remove Last Field
                                    </button> -->
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h3>Template Kit Import</h3>
                                <p>Import Minimal Kit with your custom site-settings.json (optional).</p>
                                
                                <div class="form-row">
                                    <label for="site_settings_file">Upload Site Settings (Optional):</label>
                                    <input type="file" id="site_settings_file" name="site_settings_file" accept=".json" class="file-input">
                                    <p class="description">Upload your custom site-settings.json to replace the default settings in Minimal Kit.</p>
                                    <div class="file-validation" id="site-settings-validation" style="display: none;"></div>
                                </div>
                                
                                <div class="kit-info">
                                    <h4>Kit Information:</h4>
                                    <div id="kit-details">
                                        <div class="kit-detail">
                                            <strong>⚡ Minimal Kit</strong><br>
                                            <small>Size: ~10KB | Type: Complete settings</small><br>
                                            <p>Complete site settings with all typography and color options. Ideal for full customization.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" id="import-demo-btn" class="button button-primary button-large">
                                    <span class="button-text">Import Demo Content</span>
                                    <span class="button-loading" style="display: none;">
                                        <img src="<?php echo BK_AD_PLUGIN_URL; ?>assets/images/ajax-loader.gif" alt="Loading" style="width: 16px; height: 16px; margin-right: 8px;">
                                        Importing...
                                    </span>
                                </button>
                                
                                <button type="button" id="clear-form-btn" class="button button-secondary">
                                    Clear Form
                                </button>
                            </div>
                        </form>
                        
                        <div id="import-progress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <div class="progress-text">Preparing import...</div>
                        </div>
                        
                        <div id="import-results" style="display: none;">
                            <h3>Import Results</h3>
                            <div id="results-content"></div>
                        </div>
                    </div>
                    
                    <!-- Download Images Section -->
                    <div class="download-images-section" style="margin-top: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">
                        <h3>📥 Download External Images</h3>
                        <p>After importing pages, you can download all external images to your media library.</p>
                        
                        <div class="download-form">
                            <div class="form-row">
                                <label for="download-page-id">Select Page:</label>
                                <select id="download-page-id" name="download_page_id" style="width: 300px;">
                                    <option value="all" selected>📄 All Pages</option>
                                    <option value="">-- Select a page --</option>
                                    <?php
                                    $pages = get_posts(array(
                                        'post_type' => 'page',
                                        'post_status' => 'publish',
                                        'numberposts' => -1,
                                        'meta_query' => array(
                                            array(
                                                'key' => '_elementor_data',
                                                'compare' => 'EXISTS'
                                            )
                                        )
                                    ));
                                    
                                    foreach ($pages as $page) {
                                        echo '<option value="' . $page->ID . '">' . esc_html($page->post_title) . ' (ID: ' . $page->ID . ')</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" id="download-images-btn" class="button button-primary" disabled>
                                    <span class="button-text">Download All Images</span>
                                    <span class="button-loading" style="display: none;">
                                        <img src="<?php echo BK_AD_PLUGIN_URL; ?>assets/images/ajax-loader.gif" alt="Loading" style="width: 16px; height: 16px; margin-right: 8px;">
                                        Downloading...
                                    </span>
                                </button>
                                
                                <button type="button" id="check-images-btn" class="button button-secondary">
                                    Check External Images
                                </button>
                            </div>
                            
                            <div id="download-progress" style="display: none; margin-top: 15px;">
                                <div class="progress-bar">
                                    <div class="progress-fill"></div>
                                </div>
                                <div class="progress-text">Checking images...</div>
                            </div>
                            
                            <div id="download-results" style="display: none; margin-top: 15px;">
                                <h4>Download Results</h4>
                                <div id="download-results-content"></div>
                            </div>
                        </div>
                    </div>
        		</div>
        	</div>
        </div>
    	<br class="clear"/>
    
    </div>
    
    <script type="text/javascript">
    // Add nonce for AJAX security
    var mac_ajax_nonce = '<?php echo wp_create_nonce('mac_ajax_nonce'); ?>';
    </script>
    
    <?php
    }
}
?>
