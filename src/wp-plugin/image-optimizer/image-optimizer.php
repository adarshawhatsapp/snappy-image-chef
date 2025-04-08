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
            // Add main menu item
            add_action('admin_menu', [$this, 'admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('wp_ajax_optimize_image', [$this, 'ajax_optimize_image']);
            add_action('wp_ajax_optimize_all_images', [$this, 'ajax_optimize_all_images']);
            add_action('wp_ajax_test_connection', [$this, 'ajax_test_connection']);
            
            // Add settings link on plugin page
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
            
            // Add optimize button to media library
            add_filter('attachment_fields_to_edit', [$this, 'add_optimize_button_to_media'], 10, 2);
            add_filter('media_row_actions', [$this, 'add_optimize_row_action'], 10, 2);
            
            // Enqueue admin scripts and styles
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        }
        
        // Frontend hooks
        add_filter('wp_get_attachment_image_src', [$this, 'filter_attachment_image_src'], 10, 4);
        add_filter('wp_calculate_image_srcset', [$this, 'filter_image_srcset'], 10, 5);
        add_filter('the_content', [$this, 'filter_content_images']);
        
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
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'image-optimizer') !== false || $hook === 'upload.php' || $hook === 'post.php') {
            wp_enqueue_style('image-optimizer-admin', plugin_dir_url(__FILE__) . 'admin/css/admin.css', [], IMAGE_OPTIMIZER_VERSION);
            wp_enqueue_script('image-optimizer-admin', plugin_dir_url(__FILE__) . 'admin/js/admin.js', ['jquery'], IMAGE_OPTIMIZER_VERSION, true);
            
            wp_localize_script('image-optimizer-admin', 'imageOptimizerVars', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('image_optimizer_nonce'),
                'optimizing' => __('Optimizing...', 'image-optimizer'),
                'optimized' => __('Optimized!', 'image-optimizer'),
                'failed' => __('Failed!', 'image-optimizer'),
            ]);
        }
    }

    /**
     * Add plugin action links
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=image-optimizer-settings') . '">' . __('Settings', 'image-optimizer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Register admin menu
     */
    public function admin_menu() {
        // Add main menu item
        add_menu_page(
            __('Image Optimizer', 'image-optimizer'),
            __('Image Optimizer', 'image-optimizer'),
            'manage_options',
            'image-optimizer',
            [$this, 'render_dashboard_page'],
            'dashicons-images-alt2',
            81
        );
        
        // Add submenu items
        add_submenu_page(
            'image-optimizer',
            __('Dashboard', 'image-optimizer'),
            __('Dashboard', 'image-optimizer'),
            'manage_options',
            'image-optimizer',
            [$this, 'render_dashboard_page']
        );
        
        add_submenu_page(
            'image-optimizer',
            __('Bulk Optimization', 'image-optimizer'),
            __('Bulk Optimization', 'image-optimizer'),
            'manage_options',
            'image-optimizer-bulk',
            [$this, 'render_bulk_optimization_page']
        );
        
        add_submenu_page(
            'image-optimizer',
            __('Individual Images', 'image-optimizer'),
            __('Individual Images', 'image-optimizer'),
            'manage_options',
            'image-optimizer-individual',
            [$this, 'render_individual_optimization_page']
        );
        
        add_submenu_page(
            'image-optimizer',
            __('Statistics', 'image-optimizer'),
            __('Statistics', 'image-optimizer'),
            'manage_options',
            'image-optimizer-stats',
            [$this, 'render_statistics_page']
        );
        
        add_submenu_page(
            'image-optimizer',
            __('Settings', 'image-optimizer'),
            __('Settings', 'image-optimizer'),
            'manage_options',
            'image-optimizer-settings',
            [$this, 'render_settings_page']
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
            'image-optimizer-settings'
        );
        
        add_settings_section(
            'image_optimizer_optimization_settings',
            __('Optimization Settings', 'image-optimizer'),
            [$this, 'render_optimization_settings_section'],
            'image-optimizer-settings'
        );
        
        // Server URL
        add_settings_field(
            'server_url',
            __('Optimization Server URL', 'image-optimizer'),
            [$this, 'render_server_url_field'],
            'image-optimizer-settings',
            'image_optimizer_server_settings'
        );
        
        // API Key
        add_settings_field(
            'api_key',
            __('API Key', 'image-optimizer'),
            [$this, 'render_api_key_field'],
            'image-optimizer-settings',
            'image_optimizer_server_settings'
        );
        
        // Auto-optimize
        add_settings_field(
            'auto_optimize',
            __('Auto-Optimize New Uploads', 'image-optimizer'),
            [$this, 'render_auto_optimize_field'],
            'image-optimizer-settings',
            'image_optimizer_optimization_settings'
        );
        
        // Replace originals
        add_settings_field(
            'replace_originals',
            __('Replace Original Images', 'image-optimizer'),
            [$this, 'render_replace_originals_field'],
            'image-optimizer-settings',
            'image_optimizer_optimization_settings'
        );
        
        // Output format
        add_settings_field(
            'output_format',
            __('Output Format', 'image-optimizer'),
            [$this, 'render_output_format_field'],
            'image-optimizer-settings',
            'image_optimizer_optimization_settings'
        );
        
        // Image quality
        add_settings_field(
            'image_quality',
            __('Image Quality', 'image-optimizer'),
            [$this, 'render_image_quality_field'],
            'image-optimizer-settings',
            'image_optimizer_optimization_settings'
        );
        
        // Max width
        add_settings_field(
            'max_width',
            __('Maximum Width (pixels)', 'image-optimizer'),
            [$this, 'render_max_width_field'],
            'image-optimizer-settings',
            'image_optimizer_optimization_settings'
        );
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get statistics
        $stats = $this->get_optimization_stats();
        ?>
        <div class="wrap">
            <h1><?php _e('Image Optimizer Dashboard', 'image-optimizer'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Optimization Summary', 'image-optimizer'); ?></h2>
                
                <div class="image-optimizer-stats-grid">
                    <div class="stat-box">
                        <h3><?php _e('Total Images', 'image-optimizer'); ?></h3>
                        <div class="stat-value"><?php echo $stats['total_images']; ?></div>
                    </div>
                    
                    <div class="stat-box">
                        <h3><?php _e('Optimized Images', 'image-optimizer'); ?></h3>
                        <div class="stat-value"><?php echo $stats['optimized_images']; ?></div>
                    </div>
                    
                    <div class="stat-box">
                        <h3><?php _e('Space Saved', 'image-optimizer'); ?></h3>
                        <div class="stat-value"><?php echo $this->format_bytes($stats['total_savings']); ?></div>
                    </div>
                    
                    <div class="stat-box">
                        <h3><?php _e('Average Savings', 'image-optimizer'); ?></h3>
                        <div class="stat-value"><?php echo $stats['average_savings_percent']; ?>%</div>
                    </div>
                </div>
            </div>
            
            <div class="card-grid">
                <div class="card">
                    <h2><?php _e('Quick Actions', 'image-optimizer'); ?></h2>
                    <a href="<?php echo admin_url('admin.php?page=image-optimizer-bulk'); ?>" class="button button-primary"><?php _e('Bulk Optimize', 'image-optimizer'); ?></a>
                    <a href="<?php echo admin_url('admin.php?page=image-optimizer-individual'); ?>" class="button button-secondary"><?php _e('Select Individual Images', 'image-optimizer'); ?></a>
                    <a href="<?php echo admin_url('admin.php?page=image-optimizer-settings'); ?>" class="button button-secondary"><?php _e('Configure Settings', 'image-optimizer'); ?></a>
                </div>
                
                <div class="card">
                    <h2><?php _e('Server Status', 'image-optimizer'); ?></h2>
                    <div id="server-status">
                        <p><?php _e('Checking server status...', 'image-optimizer'); ?></p>
                    </div>
                    <button id="check-server" class="button button-secondary"><?php _e('Check Server Status', 'image-optimizer'); ?></button>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#check-server').on('click', function() {
                    var $button = $(this);
                    var $status = $('#server-status');
                    
                    $button.prop('disabled', true);
                    $status.html('<p><?php _e('Checking server status...', 'image-optimizer'); ?></p>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'test_connection',
                            nonce: imageOptimizerVars.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                $status.html('<p class="server-status-ok"><?php _e('Server is online and responding.', 'image-optimizer'); ?></p>');
                            } else {
                                $status.html('<p class="server-status-error"><?php _e('Error connecting to server:', 'image-optimizer'); ?> ' + response.data + '</p>');
                            }
                        },
                        error: function() {
                            $status.html('<p class="server-status-error"><?php _e('Connection test failed. Please check your server settings.', 'image-optimizer'); ?></p>');
                        },
                        complete: function() {
                            $button.prop('disabled', false);
                        }
                    });
                });
                
                // Initial check
                $('#check-server').trigger('click');
            });
            </script>
        </div>
        <?php
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
        <p class="description"><?php _e('API key for authorization. This should match the API_KEY in your server\'s ecosystem.config.js file.', 'image-optimizer'); ?></p>
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
                do_settings_sections('image-optimizer-settings');
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
                        nonce: imageOptimizerVars.nonce
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
     * Render individual optimization page
     */
    public function render_individual_optimization_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get images
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/gif'],
            'post_status' => 'inherit',
            'posts_per_page' => 20,
            'paged' => isset($_GET['paged']) ? intval($_GET['paged']) : 1,
        ];
        
        $query = new WP_Query($args);
        ?>
        <div class="wrap">
            <h1><?php _e('Optimize Individual Images', 'image-optimizer'); ?></h1>
            
            <div class="card">
                <p><?php _e('Select individual images to optimize. Click the "Optimize" button next to each image.', 'image-optimizer'); ?></p>
                
                <div class="image-grid">
                    <?php if ($query->have_posts()) : ?>
                        <?php while ($query->have_posts()) : $query->the_post(); ?>
                            <?php
                            $attachment_id = get_the_ID();
                            $attachment_url = wp_get_attachment_url($attachment_id);
                            $metadata = wp_get_attachment_metadata($attachment_id);
                            $is_optimized = !empty($metadata['optimized_image']);
                            $optimized_class = $is_optimized ? 'optimized' : '';
                            $optimized_text = $is_optimized ? __('Re-optimize', 'image-optimizer') : __('Optimize', 'image-optimizer');
                            $savings = $is_optimized ? $metadata['optimized_image']['savings_percent'] : 0;
                            ?>
                            <div class="image-item <?php echo $optimized_class; ?>" data-id="<?php echo $attachment_id; ?>">
                                <div class="image-preview">
                                    <?php echo wp_get_attachment_image($attachment_id, 'thumbnail'); ?>
                                    <?php if ($is_optimized) : ?>
                                        <span class="optimization-badge"><?php echo $savings; ?>% <?php _e('Saved', 'image-optimizer'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="image-details">
                                    <h4><?php echo get_the_title(); ?></h4>
                                    <p class="image-meta">
                                        <?php echo isset($metadata['width']) ? $metadata['width'] . 'x' . $metadata['height'] : ''; ?>
                                        <?php echo $this->format_bytes(filesize(get_attached_file($attachment_id))); ?>
                                    </p>
                                    <button class="optimize-image button button-secondary" data-id="<?php echo $attachment_id; ?>">
                                        <?php echo $optimized_text; ?>
                                    </button>
                                    <div class="optimization-status"></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <p><?php _e('No images found.', 'image-optimizer'); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php
                $total_pages = $query->max_num_pages;
                if ($total_pages > 1) {
                    echo '<div class="pagination">';
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1),
                    ]);
                    echo '</div>';
                }
                wp_reset_postdata();
                ?>
                
                <script>
                jQuery(document).ready(function($) {
                    $('.optimize-image').on('click', function() {
                        var $button = $(this);
                        var attachmentId = $button.data('id');
                        var $status = $button.siblings('.optimization-status');
                        
                        $button.prop('disabled', true);
                        $status.html(imageOptimizerVars.optimizing);
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'optimize_image',
                                id: attachmentId,
                                nonce: imageOptimizerVars.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    $status.html(imageOptimizerVars.optimized + ' ' + response.data.savings + '% saved');
                                    $button.closest('.image-item').addClass('optimized');
                                    $button.text('<?php _e('Re-optimize', 'image-optimizer'); ?>');
                                    
                                    // Add or update optimization badge
                                    var $badge = $button.closest('.image-item').find('.optimization-badge');
                                    if ($badge.length) {
                                        $badge.text(response.data.savings + '% <?php _e('Saved', 'image-optimizer'); ?>');
                                    } else {
                                        $button.closest('.image-item').find('.image-preview').append(
                                            '<span class="optimization-badge">' + response.data.savings + '% <?php _e('Saved', 'image-optimizer'); ?></span>'
                                        );
                                    }
                                } else {
                                    $status.html(imageOptimizerVars.failed + ': ' + response.data);
                                }
                            },
                            error: function() {
                                $status.html(imageOptimizerVars.failed);
                            },
                            complete: function() {
                                $button.prop('disabled', false);
                            }
                        });
                    });
                });
                </script>
            </div>
        </div>
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
                        nonce: imageOptimizerVars.nonce
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
                        
                        update
