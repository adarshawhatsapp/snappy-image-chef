
<?php
/**
 * Uninstall Image Optimizer
 *
 * @package ImageOptimizer
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('image_optimizer_settings');

// Delete optimized images folder (optional, uncomment to enable)
/*
$upload_dir = wp_upload_dir();
$optimized_dir = $upload_dir['basedir'] . '/optimized';

if (is_dir($optimized_dir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($optimized_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    
    rmdir($optimized_dir);
}
*/
