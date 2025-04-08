
# Image Optimizer WordPress Plugin

A WordPress plugin to optimize images using a dedicated Sharp-based image optimization server.

## Features

- Automatically optimize new image uploads
- Bulk optimization for existing media library images
- Convert images to WebP or AVIF formats
- Resize and compress images
- Display optimized images with fallbacks for older browsers
- Track optimization statistics

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Access to a Sharp-based image optimization server

## Installation

1. Upload the `image-optimizer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Settings > Image Optimizer to configure the plugin

## Configuration

1. Enter the URL of your image optimization server
2. Configure optimization settings:
   - Auto-optimize new uploads
   - Replace original images (not recommended unless storage is limited)
   - Output format (WebP, AVIF, JPEG, PNG)
   - Image quality (1-100)
   - Maximum width (pixels)

## Usage

### Automatic Optimization

When enabled, the plugin automatically optimizes new images when they are uploaded to the media library.

### Bulk Optimization

To optimize existing images:

1. Navigate to Media > Optimize Images
2. Click "Start Optimization"
3. Wait for the optimization process to complete

## Troubleshooting

- If images fail to optimize, check the connection to your optimization server
- Verify your server URL and API key are correct
- Check your server logs for errors
- Make sure your WordPress installation has write permissions for the uploads directory

## Frequently Asked Questions

### Can I revert to original images?

Yes, if you haven't enabled "Replace originals" option. Original images are always preserved by default.

### What image formats are supported?

The plugin supports JPEG, PNG, and GIF images for optimization.

### How much space can I save?

Typically, you can save 30-70% of storage space depending on your image content and quality settings.

## Credits

Developed by [Your Name]
