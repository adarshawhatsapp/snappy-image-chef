
# Image Optimizer WordPress Plugin

A WordPress plugin to optimize images using a dedicated Sharp-based image optimization server.

## Features

- Automatically optimize new image uploads
- Bulk optimization for existing media library images
- Individual image optimization from the media library
- Convert images to WebP or AVIF formats
- Resize and compress images
- Display optimized images with fallbacks for older browsers
- Track optimization statistics
- Detailed dashboard with optimization metrics

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Access to a Sharp-based image optimization server

## Installation

1. Upload the `image-optimizer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Image Optimizer > Settings to configure the plugin

## Configuration

### Server Setup

Before using the plugin, you need to set up the image optimization server:

1. Install and configure the Sharp-based image optimization server from the `server` directory
2. Make note of the server URL and API key from the `ecosystem.config.js` file
3. Enter these details in the plugin settings

### Plugin Configuration

1. Go to Image Optimizer > Settings in the WordPress admin
2. Enter your server URL (e.g., http://your-server.com:3000)
3. Enter the API key (matching the one in your server's ecosystem.config.js)
4. Configure optimization settings:
   - Auto-optimize new uploads
   - Replace original images (not recommended unless storage is limited)
   - Output format (WebP, AVIF, JPEG, PNG)
   - Image quality (1-100)
   - Maximum width (pixels)

## Usage

### Dashboard

The plugin dashboard provides an overview of:
- Total images in your media library
- Number of optimized images
- Total space saved
- Average savings percentage
- Server connection status

### Bulk Optimization

To optimize all existing images at once:

1. Navigate to Image Optimizer > Bulk Optimization
2. Click "Start Optimization"
3. Monitor the progress and results

### Individual Optimization

To optimize specific images:

1. Navigate to Image Optimizer > Individual Images
2. Browse through your media library
3. Click the "Optimize" button next to images you want to optimize

You can also optimize individual images directly from:
- The media library grid view
- The attachment details screen
- The media uploader

### Statistics

The Statistics page shows:
- Overall optimization metrics
- Space savings
- Recently optimized images with before/after sizes

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

## Server API Documentation

The image optimization server exposes the following endpoints:

### Health Check
```
GET /health
```

Response:
```json
{
  "status": "ok"
}
```

### Optimize Image
```
POST /optimize
```

**Headers:**
- `X-API-Key`: Your API key (required)

**Parameters (multipart/form-data):**
- `image` (required): The image file to optimize

**Query Parameters:**
- `quality` (optional): Output quality (1-100, default: 75)
- `format` (optional): Output format (webp, avif, jpeg, png, default: webp)
- `maxWidth` (optional): Maximum width in pixels (default: 2000)
- `return` (optional): Return type (binary, url, default: binary)

## Credits

Developed by [Your Name]
