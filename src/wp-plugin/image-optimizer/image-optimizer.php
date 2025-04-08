
<?php
/**
 * Plugin Name: Image Optimizer
 * Plugin URI: https://example.com/image-optimizer
 * Description: Optimize your WordPress images using a Sharp-based image optimization server
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: image-optimizer
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IMAGE_OPTIMIZER_VERSION', '1.0.0');
define('IMAGE_OPTIMIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IMAGE_OPTIMIZER_PLUGIN_URL', plugin_dir_url(__FILE__));

class ImageOptimizer {
    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Plugin settings
     */
    private $settings = [];

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Load settings
        $this->settings = get_option('image_optimizer_settings', [
            'server_url' => '',
            'api_key' => '',
            'auto_optimize' => 'yes',
            'replace_originals' => 'no',
            'output_format' => 'webp',
            'image_quality' => 75,
            'max_width' => 2000,
        ]);

        // Initialize plugin
        add_action('init', [$this, 'init']);
        
        // Register activation hook
        register_activation_hook(__FILE__, [$this, 'activation']);
        
        // Register deactivation hook
        register_deactivation_hook(__FILE__, [$this, 'deactivation']);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('wp_ajax_optimize_image', [$this, 'ajax_optimize_image']);
            add_action('wp_ajax_optimize_all_images', [$this, 'ajax_optimize_all_images']);
        }
        
        // Frontend hooks
        add_filter('wp_get_attachment_image_src', [$this, 'filter_attachment_image_src'], 10, 4);
        add_filter('wp_calculate_image_srcset', [$this, 'filter_image_srcset'], 10, 5);
        
        // Upload hooks
        if ($this->settings['auto_optimize'] === 'yes') {
            add_filter('wp_handle_upload', [$this, 'handle_upload'], 10, 2);
        }
    }

    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('image-optimizer', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Plugin activation
     */
    public function activation() {
        // Create necessary directories
        $upload_dir = wp_upload_dir();
        $optimized_dir = $upload_dir['basedir'] . '/optimized';
        
        if (!file_exists($optimized_dir)) {
            wp_mkdir_p($optimized_dir);
        }
        
        // Create index.php file to prevent directory listing
        if (!file_exists($optimized_dir . '/index.php')) {
            file_put_contents($optimized_dir . '/index.php', '<?php // Silence is golden');
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivation() {
        // Nothing to do yet
    }

    /**
     * Register admin menu
     */
    public function admin_menu() {
        add_options_page(
            __('Image Optimizer Settings', 'image-optimizer'),
            __('Image Optimizer', 'image-optimizer'),
            'manage_options',
            'image-optimizer',
            [$this, 'render_settings_page']
        );
        
        add_media_page(
            __('Optimize Images', 'image-optimizer'),
            __('Optimize Images', 'image-optimizer'),
            'manage_options',
            'image-optimizer-bulk',
            [$this, 'render_bulk_optimization_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('image_optimizer_settings', 'image_optimizer_settings');
        
        add_settings_section(
            'image_optimizer_server_settings',
            __('Server Settings', 'image-optimizer'),
            [$this, 'render_server_settings_section'],
            'image-optimizer'
        );
        
        add_settings_section(
            'image_optimizer_optimization_settings',
            __('Optimization Settings', 'image-optimizer'),
            [$this, 'render_optimization_settings_section'],
            'image-optimizer'
        );
        
        // Server URL
        add_settings_field(
            'server_url',
            __('Optimization Server URL', 'image-optimizer'),
            [$this, 'render_server_url_field'],
            'image-optimizer',
            'image_optimizer_server_settings'
        );
        
        // API Key
        add_settings_field(
            'api_key',
            __('API Key', 'image-optimizer'),
            [$this, 'render_api_key_field'],
            'image-optimizer',
            'image_optimizer_server_settings'
        );
        
        // Auto-optimize
        add_settings_field(
            'auto_optimize',
            __('Auto-Optimize New Uploads', 'image-optimizer'),
            [$this, 'render_auto_optimize_field'],
            'image-optimizer',
            'image_optimizer_optimization_settings'
        );
        
        // Replace originals
        add_settings_field(
            'replace_originals',
            __('Replace Original Images', 'image-optimizer'),
            [$this, 'render_replace_originals_field'],
            'image-optimizer',
            'image_optimizer_optimization_settings'
        );
        
        // Output format
        add_settings_field(
            'output_format',
            __('Output Format', 'image-optimizer'),
            [$this, 'render_output_format_field'],
            'image-optimizer',
            'image_optimizer_optimization_settings'
        );
        
        // Image quality
        add_settings_field(
            'image_quality',
            __('Image Quality', 'image-optimizer'),
            [$this, 'render_image_quality_field'],
            'image-optimizer',
            'image_optimizer_optimization_settings'
        );
        
        // Max width
        add_settings_field(
            'max_width',
            __('Maximum Width (pixels)', 'image-optimizer'),
            [$this, 'render_max_width_field'],
            'image-optimizer',
            'image_optimizer_optimization_settings'
        );
    }

    /**
     * Render server settings section
     */
    public function render_server_settings_section() {
        echo '<p>' . __('Configure the connection to your image optimization server.', 'image-optimizer') . '</p>';
    }

    /**
     * Render optimization settings section
     */
    public function render_optimization_settings_section() {
        echo '<p>' . __('Configure how images are optimized.', 'image-optimizer') . '</p>';
    }

    /**
     * Render server URL field
     */
    public function render_server_url_field() {
        $server_url = isset($this->settings['server_url']) ? esc_url($this->settings['server_url']) : '';
        ?>
        <input type="text" name="image_optimizer_settings[server_url]" value="<?php echo $server_url; ?>" class="regular-text">
        <p class="description"><?php _e('The URL of your image optimization server (e.g., http://your-server.com:3000)', 'image-optimizer'); ?></p>
        <?php
    }

    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $api_key = isset($this->settings['api_key']) ? esc_attr($this->settings['api_key']) : '';
        ?>
        <input type="text" name="image_optimizer_settings[api_key]" value="<?php echo $api_key; ?>" class="regular-text">
        <p class="description"><?php _e('API key for authorization', 'image-optimizer'); ?></p>
        <?php
    }

    /**
     * Render auto-optimize field
     */
    public function render_auto_optimize_field() {
        $auto_optimize = isset($this->settings['auto_optimize']) ? $this->settings['auto_optimize'] : 'yes';
        ?>
        <select name="image_optimizer_settings[auto_optimize]">
            <option value="yes" <?php selected($auto_optimize, 'yes'); ?>><?php _e('Yes', 'image-optimizer'); ?></option>
            <option value="no" <?php selected($auto_optimize, 'no'); ?>><?php _e('No', 'image-optimizer'); ?></option>
        </select>
        <p class="description"><?php _e('Automatically optimize images when they are uploaded', 'image-optimizer'); ?></p>
        <?php
    }

    /**
     * Render replace originals field
     */
    public function render_replace_originals_field() {
        $replace_originals = isset($this->settings['replace_originals']) ? $this->settings['replace_originals'] : 'no';
        ?>
        <select name="image_optimizer_settings[replace_originals]">
            <option value="yes" <?php selected($replace_originals, 'yes'); ?>><?php _e('Yes', 'image-optimizer'); ?></option>
            <option value="no" <?php selected($replace_originals, 'no'); ?>><?php _e('No', 'image-optimizer'); ?></option>
        </select>
        <p class="description"><?php _e('Replace original images with optimized versions (not recommended unless storage is limited)', 'image-optimizer'); ?></p>
        <?php
    }

    /**
     * Render output format field
     */
    public function render_output_format_field() {
        $output_format = isset($this->settings['output_format']) ? $this->settings['output_format'] : 'webp';
        ?>
        <select name="image_optimizer_settings[output_format]">
            <option value="webp" <?php selected($output_format, 'webp'); ?>><?php _e('WebP', 'image-optimizer'); ?></option>
            <option value="avif" <?php selected($output_format, 'avif'); ?>><?php _e('AVIF', 'image-optimizer'); ?></option>
            <option value="jpeg" <?php selected($output_format, 'jpeg'); ?>><?php _e('JPEG', 'image-optimizer'); ?></option>
            <option value="png" <?php selected($output_format, 'png'); ?>><?php _e('PNG', 'image-optimizer'); ?></option>
        </select>
        <p class="description"><?php _e('Output format for optimized images', 'image-optimizer'); ?></p>
        <?php
    }

    /**
     * Render image quality field
     */
    public function render_image_quality_field() {
        $image_quality = isset($this->settings['image_quality']) ? intval($this->settings['image_quality']) : 75;
        ?>
        <input type="range" name="image_optimizer_settings[image_quality]" min="1" max="100" value="<?php echo $image_quality; ?>" class="regular-text" oninput="this.nextElementSibling.value = this.value">
        <output><?php echo $image_quality; ?></output>
        <p class="description"><?php _e('Quality of optimized images (1-100)', 'image-optimizer'); ?></p>
        <?php
    }

    /**
     * Render max width field
     */
    public function render_max_width_field() {
        $max_width = isset($this->settings['max_width']) ? intval($this->settings['max_width']) : 2000;
        ?>
        <input type="number" name="image_optimizer_settings[max_width]" value="<?php echo $max_width; ?>" class="regular-text">
        <p class="description"><?php _e('Maximum width of optimized images in pixels (0 for no limit)', 'image-optimizer'); ?></p>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('image_optimizer_settings');
                do_settings_sections('image-optimizer');
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2><?php _e('Test Connection', 'image-optimizer'); ?></h2>
                <p><?php _e('Test the connection to your image optimization server.', 'image-optimizer'); ?></p>
                <button id="test-connection" class="button button-secondary"><?php _e('Test Connection', 'image-optimizer'); ?></button>
                <div id="test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-connection').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var $result = $('#test-result');
                
                $button.prop('disabled', true).text('<?php _e('Testing...', 'image-optimizer'); ?>');
                $result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_connection',
                        nonce: '<?php echo wp_create_nonce('test_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error inline"><p><?php _e('Connection test failed.', 'image-optimizer'); ?></p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php _e('Test Connection', 'image-optimizer'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render bulk optimization page
     */
    public function render_bulk_optimization_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get total number of images
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/gif'],
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];
        $images = get_posts($args);
        $total_images = count($images);
        ?>
        <div class="wrap">
            <h1><?php _e('Bulk Image Optimization', 'image-optimizer'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Optimize All Images', 'image-optimizer'); ?></h2>
                <p><?php printf(__('You have %d images in your media library that can be optimized.', 'image-optimizer'), $total_images); ?></p>
                
                <div id="optimization-progress" style="display: none; margin-bottom: 15px;">
                    <div class="progress-bar-container" style="height: 20px; background-color: #f0f0f0; border-radius: 3px; overflow: hidden;">
                        <div class="progress-bar" style="width: 0%; height: 100%; background-color: #0073aa;"></div>
                    </div>
                    <p class="progress-status"><?php _e('Optimizing...', 'image-optimizer'); ?> <span class="progress-count">0</span>/<span class="progress-total"><?php echo $total_images; ?></span></p>
                </div>
                
                <button id="start-optimization" class="button button-primary"><?php _e('Start Optimization', 'image-optimizer'); ?></button>
                <button id="stop-optimization" class="button button-secondary" style="display: none;"><?php _e('Stop Optimization', 'image-optimizer'); ?></button>
                
                <div id="optimization-results" style="margin-top: 20px;"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var optimization = {
                images: <?php echo json_encode($images); ?>,
                totalImages: <?php echo $total_images; ?>,
                processedImages: 0,
                successfulOptimizations: 0,
                failedOptimizations: 0,
                inProgress: false,
                results: []
            };
            
            $('#start-optimization').on('click', function() {
                startOptimization();
            });
            
            $('#stop-optimization').on('click', function() {
                stopOptimization();
            });
            
            function startOptimization() {
                if (optimization.inProgress) {
                    return;
                }
                
                optimization.inProgress = true;
                optimization.processedImages = 0;
                optimization.successfulOptimizations = 0;
                optimization.failedOptimizations = 0;
                optimization.results = [];
                
                $('#start-optimization').hide();
                $('#stop-optimization').show();
                $('#optimization-progress').show();
                $('#optimization-results').html('');
                
                $('.progress-bar').css('width', '0%');
                $('.progress-count').text('0');
                $('.progress-total').text(optimization.totalImages);
                
                processNextImage();
            }
            
            function stopOptimization() {
                optimization.inProgress = false;
                $('#start-optimization').show();
                $('#stop-optimization').hide();
                
                showResults();
            }
            
            function processNextImage() {
                if (!optimization.inProgress || optimization.processedImages >= optimization.totalImages) {
                    stopOptimization();
                    return;
                }
                
                var imageId = optimization.images[optimization.processedImages];
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'optimize_image',
                        id: imageId,
                        nonce: '<?php echo wp_create_nonce('optimize_image'); ?>'
                    },
                    success: function(response) {
                        optimization.processedImages++;
                        
                        if (response.success) {
                            optimization.successfulOptimizations++;
                            optimization.results.push({
                                id: imageId,
                                success: true,
                                savings: response.data.savings,
                                title: response.data.title
                            });
                        } else {
                            optimization.failedOptimizations++;
                            optimization.results.push({
                                id: imageId,
                                success: false,
                                error: response.data,
                                title: ''
                            });
                        }
                        
                        updateProgress();
                        processNextImage();
                    },
                    error: function() {
                        optimization.processedImages++;
                        optimization.failedOptimizations++;
                        
                        optimization.results.push({
                            id: imageId,
                            success: false,
                            error: '<?php _e('Request failed', 'image-optimizer'); ?>',
                            title: ''
                        });
                        
                        updateProgress();
                        processNextImage();
                    }
                });
            }
            
            function updateProgress() {
                var progressPercent = (optimization.processedImages / optimization.totalImages) * 100;
                $('.progress-bar').css('width', progressPercent + '%');
                $('.progress-count').text(optimization.processedImages);
            }
            
            function showResults() {
                var totalSaved = 0;
                
                for (var i = 0; i < optimization.results.length; i++) {
                    var result = optimization.results[i];
                    if (result.success) {
                        totalSaved += parseFloat(result.savings);
                    }
                }
                
                var html = '<h3><?php _e('Optimization Results', 'image-optimizer'); ?></h3>';
                html += '<p><?php _e('Processed Images', 'image-optimizer'); ?>: ' + optimization.processedImages + '/' + optimization.totalImages + '</p>';
                html += '<p><?php _e('Successful Optimizations', 'image-optimizer'); ?>: ' + optimization.successfulOptimizations + '</p>';
                html += '<p><?php _e('Failed Optimizations', 'image-optimizer'); ?>: ' + optimization.failedOptimizations + '</p>';
                html += '<p><?php _e('Total Space Saved', 'image-optimizer'); ?>: ' + formatBytes(totalSaved) + '</p>';
                
                if (optimization.failedOptimizations > 0) {
                    html += '<h4><?php _e('Errors', 'image-optimizer'); ?></h4>';
                    html += '<ul>';
                    
                    for (var i = 0; i < optimization.results.length; i++) {
                        var result = optimization.results[i];
                        if (!result.success) {
                            html += '<li>ID ' + result.id + ': ' + result.error + '</li>';
                        }
                    }
                    
                    html += '</ul>';
                }
                
                $('#optimization-results').html(html);
            }
            
            function formatBytes(bytes) {
                if (bytes < 1024) {
                    return bytes + ' B';
                } else if (bytes < 1048576) {
                    return (bytes / 1024).toFixed(2) + ' KB';
                } else {
                    return (bytes / 1048576).toFixed(2) + ' MB';
                }
            }
        });
        </script>
        <?php
    }

    /**
     * Ajax optimize image
     */
    public function ajax_optimize_image() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'optimize_image')) {
            wp_send_json_error(__('Invalid nonce', 'image-optimizer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'image-optimizer'));
        }
        
        // Check parameters
        if (!isset($_POST['id'])) {
            wp_send_json_error(__('Missing attachment ID', 'image-optimizer'));
        }
        
        $attachment_id = intval($_POST['id']);
        
        // Get attachment
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            wp_send_json_error(__('Attachment not found', 'image-optimizer'));
        }
        
        // Check if attachment is an image
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(__('Attachment is not an image', 'image-optimizer'));
        }
        
        // Get attachment file
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(__('Attachment file not found', 'image-optimizer'));
        }
        
        // Optimize image
        $result = $this->optimize_attachment($attachment_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success([
            'title' => $attachment->post_title,
            'savings' => $result['savings'],
        ]);
    }

    /**
     * Ajax optimize all images
     */
    public function ajax_optimize_all_images() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'optimize_all_images')) {
            wp_send_json_error(__('Invalid nonce', 'image-optimizer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'image-optimizer'));
        }
        
        // Get all images
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/gif'],
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];
        $images = get_posts($args);
        
        // Start optimization
        wp_send_json_success([
            'total' => count($images),
            'ids' => $images,
        ]);
    }

    /**
     * Handle upload
     */
    public function handle_upload($file, $context) {
        // Skip optimization if not an image
        if (!preg_match('/^image\/(jpeg|png|gif)$/i', $file['type'])) {
            return $file;
        }
        
        // Skip optimization if server URL is not set
        if (empty($this->settings['server_url'])) {
            return $file;
        }
        
        // Get file path
        $file_path = $file['file'];
        
        // Get file info
        $pathinfo = pathinfo($file_path);
        $upload_dir = wp_upload_dir();
        
        // Define output format and filename
        $format = $this->settings['output_format'];
        $optimized_filename = $pathinfo['filename'] . '.' . $format;
        $optimized_path = $pathinfo['dirname'] . '/' . $optimized_filename;
        
        // Optimize image
        $result = $this->optimize_image($file_path, $optimized_path);
        
        if (is_wp_error($result)) {
            // Log error
            error_log('Image Optimizer: ' . $result->get_error_message());
            return $file;
        }
        
        // Update metadata
        $file['optimized_url'] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $optimized_path);
        $file['optimized_path'] = $optimized_path;
        $file['optimization_savings'] = $result['savings'];
        
        // If replace originals is enabled, replace the original file
        if ($this->settings['replace_originals'] === 'yes') {
            // Backup original file
            copy($file_path, $file_path . '.bak');
            
            // Replace original file
            rename($optimized_path, $file_path);
        }
        
        return $file;
    }

    /**
     * Optimize attachment
     */
    public function optimize_attachment($attachment_id) {
        // Get attachment file
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return new WP_Error('file_not_found', __('Attachment file not found', 'image-optimizer'));
        }
        
        // Get attachment metadata
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        // Get file info
        $pathinfo = pathinfo($file_path);
        $upload_dir = wp_upload_dir();
        
        // Define output format and filename
        $format = $this->settings['output_format'];
        $optimized_filename = $pathinfo['filename'] . '.' . $format;
        $optimized_dir = $upload_dir['basedir'] . '/optimized/' . $pathinfo['dirname'];
        $optimized_path = $optimized_dir . '/' . $optimized_filename;
        
        // Create optimized directory if it doesn't exist
        if (!file_exists($optimized_dir)) {
            wp_mkdir_p($optimized_dir);
        }
        
        // Optimize image
        $result = $this->optimize_image($file_path, $optimized_path);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Update attachment metadata
        $metadata['optimized_image'] = [
            'file' => 'optimized/' . $pathinfo['dirname'] . '/' . $optimized_filename,
            'width' => $result['width'],
            'height' => $result['height'],
            'mime_type' => 'image/' . $format,
            'filesize' => filesize($optimized_path),
            'original_filesize' => filesize($file_path),
            'savings' => $result['savings'],
            'savings_percent' => $result['savings_percent'],
        ];
        
        wp_update_attachment_metadata($attachment_id, $metadata);
        
        // If replace originals is enabled, replace the original file
        if ($this->settings['replace_originals'] === 'yes') {
            // Backup original file
            copy($file_path, $file_path . '.bak');
            
            // Replace original file
            rename($optimized_path, $file_path);
            
            // Update attachment metadata
            $metadata['file'] = str_replace($upload_dir['basedir'] . '/', '', $file_path);
            $metadata['mime_type'] = 'image/' . $format;
            $metadata['width'] = $result['width'];
            $metadata['height'] = $result['height'];
            $metadata['filesize'] = filesize($file_path);
            
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
        
        return $result;
    }

    /**
     * Optimize image
     */
    public function optimize_image($source_path, $destination_path) {
        // Check if server URL is set
        if (empty($this->settings['server_url'])) {
            return new WP_Error('server_url_not_set', __('Server URL is not set', 'image-optimizer'));
        }
        
        // Check if source file exists
        if (!file_exists($source_path)) {
            return new WP_Error('source_file_not_found', __('Source file not found', 'image-optimizer'));
        }
        
        // Prepare request
        $url = rtrim($this->settings['server_url'], '/') . '/optimize';
        $url = add_query_arg([
            'format' => $this->settings['output_format'],
            'quality' => $this->settings['image_quality'],
            'maxWidth' => $this->settings['max_width'],
            'return' => 'binary',
        ], $url);
        
        // Prepare file for upload
        $file_content = file_get_contents($source_path);
        $boundary = wp_generate_password(24);
        $headers = [
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            'X-API-Key' => $this->settings['api_key'],
        ];
        
        // Build multipart request body
        $body = '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="image"; filename="' . basename($source_path) . '"' . "\r\n";
        $body .= 'Content-Type: ' . mime_content_type($source_path) . "\r\n\r\n";
        $body .= $file_content . "\r\n";
        $body .= '--' . $boundary . '--';
        
        // Send request
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 60,
            'redirection' => 5,
            'httpversion' => '1.1',
            'sslverify' => false,
        ]);
        
        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_message = wp_remote_retrieve_response_message($response);
            if (empty($error_message)) {
                $error_message = __('Unknown error', 'image-optimizer');
            }
            return new WP_Error('server_error', sprintf(__('Server returned error: %s', 'image-optimizer'), $error_message));
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return new WP_Error('empty_response', __('Empty response from server', 'image-optimizer'));
        }
        
        // Save optimized image
        $result = file_put_contents($destination_path, $body);
        if ($result === false) {
            return new WP_Error('save_failed', __('Failed to save optimized image', 'image-optimizer'));
        }
        
        // Get optimization statistics
        $original_size = filesize($source_path);
        $optimized_size = filesize($destination_path);
        $savings = $original_size - $optimized_size;
        $savings_percent = round(($savings / $original_size) * 100, 2);
        
        // Get image dimensions
        $dimensions = getimagesize($destination_path);
        
        return [
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'original_size' => $original_size,
            'optimized_size' => $optimized_size,
            'savings' => $savings,
            'savings_percent' => $savings_percent,
        ];
    }

    /**
     * Filter attachment image source
     */
    public function filter_attachment_image_src($image, $attachment_id, $size, $icon) {
        // Skip if no attachment ID
        if (empty($attachment_id)) {
            return $image;
        }
        
        // Get attachment metadata
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        // Skip if no optimized image
        if (empty($metadata['optimized_image'])) {
            return $image;
        }
        
        // Get optimized image URL
        $upload_dir = wp_upload_dir();
        $optimized_url = $upload_dir['baseurl'] . '/' . $metadata['optimized_image']['file'];
        
        // Return optimized image URL
        return [
            $optimized_url,
            $metadata['optimized_image']['width'],
            $metadata['optimized_image']['height'],
            true,
        ];
    }

    /**
     * Filter image srcset
     */
    public function filter_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        // Skip if no attachment ID
        if (empty($attachment_id)) {
            return $sources;
        }
        
        // Get attachment metadata
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        // Skip if no optimized image
        if (empty($metadata['optimized_image'])) {
            return $sources;
        }
        
        // Get optimized image URL
        $upload_dir = wp_upload_dir();
        $optimized_url = $upload_dir['baseurl'] . '/' . $metadata['optimized_image']['file'];
        
        // Add optimized image to srcset
        $sources[$metadata['optimized_image']['width']] = [
            'url' => $optimized_url,
            'descriptor' => 'w',
            'value' => $metadata['optimized_image']['width'],
        ];
        
        return $sources;
    }
}

// Initialize plugin
ImageOptimizer::get_instance();
