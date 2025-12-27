<?php
// Update all error_log to crm_log in CRM API Manager
$file = 'wp-content/plugins/mac-core/includes/class-crm-api-manager.php';
$content = file_get_contents($file);

// Replace all error_log with $this->crm_log
$content = str_replace('error_log(', '$this->crm_log(', $content);

file_put_contents($file, $content);
echo "Updated all error_log to crm_log in CRM API Manager\n";
?>
