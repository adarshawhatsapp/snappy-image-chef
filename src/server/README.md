
# Image Optimization Server

A high-performance image optimization server built with Node.js and Sharp.

## Features

- Convert images to WebP and AVIF formats
- Resize images while maintaining aspect ratio
- Compress images with configurable quality
- API key authentication
- Rate limiting
- CORS support for cross-origin requests

## Quick Setup Guide

### Prerequisites

- Node.js 14.x or higher
- NPM or Yarn

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd image-optimization-server

# Install dependencies
npm install

# Configure your API key
# Edit ecosystem.config.js and change the API_KEY value
# You'll need this same API key in your WordPress plugin

# Start the server
npm start
```

### Docker Installation

```bash
# Clone the repository
git clone <repository-url>
cd image-optimization-server

# Edit the API_KEY in the docker-compose.yml file
# Then build and start the container
docker-compose up -d
```

## Configuration

Edit the configuration in `ecosystem.config.js` or use environment variables:

- `PORT`: Server port (default: 3000)
- `API_KEY`: API key for authentication (IMPORTANT: change this to a secure random string)

## API Documentation

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

**Example Response (when return=url):**
```json
{
  "success": true,
  "originalSize": 1024000,
  "optimizedSize": 256000,
  "savingsPercent": "75.00",
  "format": "webp",
  "width": 1200,
  "height": 800,
  "url": "/temp/f47ac10b-58cc-4372-a567-0e02b2c3d479.webp"
}
```

## Testing with Postman

1. Create a new POST request to `http://your-server:3000/optimize`
2. Add header: `X-API-Key` with your API key value
3. In the "Body" tab, select "form-data"
4. Add a key named "image", change the type to "File", and select an image file
5. Add optional query parameters: `?quality=80&format=webp&maxWidth=1200&return=url`
6. Send the request

## Deployment

### Using PM2

```bash
# Install PM2 globally
npm install -g pm2

# Start the server with PM2
pm2 start ecosystem.config.js

# Configure PM2 to start on boot
pm2 startup
pm2 save
```

### Using systemd

Edit the paths in the `image-optimizer.service` file, then:

```bash
# Copy the service file to systemd directory
sudo cp image-optimizer.service /etc/systemd/system/

# Start the service
sudo systemctl enable image-optimizer
sudo systemctl start image-optimizer
```

## Troubleshooting

- Check logs with `pm2 logs` or `journalctl -u image-optimizer`
- Verify the server is running with `curl http://localhost:3000/health`
- Test the API key with a simple request before integrating with WordPress
