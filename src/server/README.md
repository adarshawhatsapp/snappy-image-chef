
# Image Optimization Server

A high-performance image optimization server built with Node.js and Sharp.

## Features

- Convert images to WebP and AVIF formats
- Resize images while maintaining aspect ratio
- Compress images with configurable quality
- API key authentication
- Rate limiting
- CORS support for cross-origin requests

## Installation

```bash
# Clone the repository
git clone <repository-url>
cd image-optimization-server

# Install dependencies
npm install

# Start the server
npm start
```

## Configuration

Edit the configuration section in `index.js` or use environment variables:

- `PORT`: Server port (default: 3000)
- `API_KEY`: API key for authentication

## API Endpoints

### Optimize Image

```
POST /optimize
```

Parameters:
- `image` (required): The image file to optimize (multipart/form-data)
- `quality` (optional): Output quality (1-100, default: 75)
- `format` (optional): Output format (webp, avif, jpeg, png, default: webp)
- `maxWidth` (optional): Maximum width in pixels (default: 2000)
- `return` (optional): Return type (binary, url, default: binary)

Headers:
- `X-API-Key`: Your API key

## Usage Examples

### cURL

```bash
curl -X POST \
  http://localhost:3000/optimize \
  -H 'X-API-Key: your-api-key' \
  -F 'image=@/path/to/your/image.jpg' \
  -o optimized.webp
```

### JavaScript

```javascript
const form = new FormData();
form.append('image', imageFile);

fetch('http://localhost:3000/optimize?quality=80&format=webp', {
  method: 'POST',
  headers: {
    'X-API-Key': 'your-api-key'
  },
  body: form
})
.then(response => response.blob())
.then(blob => {
  // Use the optimized image
  const url = URL.createObjectURL(blob);
  document.querySelector('img').src = url;
});
```

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

```bash
# Edit the service file path
nano image-optimizer.service

# Copy the service file to systemd directory
sudo cp image-optimizer.service /etc/systemd/system/

# Start the service
sudo systemctl enable image-optimizer
sudo systemctl start image-optimizer
```
