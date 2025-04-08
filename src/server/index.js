
const express = require('express');
const multer = require('multer');
const cors = require('cors');
const morgan = require('morgan');
const helmet = require('helmet');
const sharp = require('sharp');
const fs = require('fs');
const path = require('path');
const rateLimit = require('express-rate-limit');
const { v4: uuidv4 } = require('uuid');

// Configuration
const config = {
  port: process.env.PORT || 3000,
  maxFileSize: 5 * 1024 * 1024, // 5MB
  outputDir: path.join(__dirname, 'uploads'),
  tempDir: path.join(__dirname, 'temp'),
  defaultQuality: 75,
  defaultFormat: 'webp',
  maxWidth: 2000,
  rateLimit: {
    windowMs: 15 * 60 * 1000, // 15 minutes
    max: 100 // limit each IP to 100 requests per windowMs
  },
  apiKey: process.env.API_KEY || 'your-default-api-key-change-me'
};

// Create directories if they don't exist
[config.outputDir, config.tempDir].forEach(dir => {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
});

// Initialize express app
const app = express();

// Middleware
app.use(helmet()); // Security headers
app.use(cors()); // CORS for cross-origin requests
app.use(morgan('combined')); // Logging
app.use(express.json());

// Rate limiting
const limiter = rateLimit(config.rateLimit);
app.use(limiter);

// API key middleware
const apiKeyAuth = (req, res, next) => {
  const apiKey = req.header('X-API-Key');
  
  if (!apiKey || apiKey !== config.apiKey) {
    return res.status(401).json({ error: 'Unauthorized - Invalid API key' });
  }
  
  next();
};

// Configure multer for file uploads
const upload = multer({
  storage: multer.memoryStorage(),
  limits: {
    fileSize: config.maxFileSize // 5MB limit
  },
  fileFilter: (req, file, cb) => {
    // Accept only image files
    if (!file.mimetype.match(/^image\/(jpeg|png|gif|svg\+xml)$/)) {
      return cb(new Error('Only image files are allowed'), false);
    }
    cb(null, true);
  }
});

// Health check endpoint
app.get('/health', (req, res) => {
  res.status(200).json({ status: 'ok' });
});

// Image optimization endpoint
app.post('/optimize', apiKeyAuth, upload.single('image'), async (req, res) => {
  try {
    if (!req.file) {
      return res.status(400).json({ error: 'No image file provided' });
    }

    // Get optimization parameters from query or use defaults
    const quality = parseInt(req.query.quality) || config.defaultQuality;
    const format = req.query.format || config.defaultFormat;
    const maxWidth = parseInt(req.query.maxWidth) || config.maxWidth;
    const returnType = req.query.return || 'binary'; // 'binary' or 'url'

    // Initialize Sharp with the uploaded image
    let sharpImage = sharp(req.file.buffer);
    
    // Get image metadata
    const metadata = await sharpImage.metadata();
    
    // Resize if needed, maintaining aspect ratio
    if (metadata.width > maxWidth) {
      sharpImage = sharpImage.resize(maxWidth, null, {
        fit: 'inside',
        withoutEnlargement: true
      });
    }

    // Convert to the requested format with quality setting
    if (format === 'webp') {
      sharpImage = sharpImage.webp({ quality });
    } else if (format === 'avif') {
      sharpImage = sharpImage.avif({ quality });
    } else if (format === 'jpeg' || format === 'jpg') {
      sharpImage = sharpImage.jpeg({ quality });
    } else if (format === 'png') {
      sharpImage = sharpImage.png({ quality });
    }

    // Process the image
    const outputBuffer = await sharpImage.toBuffer();
    
    // Calculate optimization statistics
    const originalSize = req.file.size;
    const optimizedSize = outputBuffer.length;
    const savingsPercent = ((originalSize - optimizedSize) / originalSize * 100).toFixed(2);
    
    // Log optimization results
    console.log(`Optimized image: ${originalSize} -> ${optimizedSize} bytes (${savingsPercent}% saved)`);

    if (returnType === 'binary') {
      // Set appropriate content type based on format
      res.setHeader('Content-Type', `image/${format}`);
      res.setHeader('Content-Length', outputBuffer.length);
      res.setHeader('X-Original-Size', originalSize);
      res.setHeader('X-Optimized-Size', optimizedSize);
      res.setHeader('X-Savings-Percent', savingsPercent);
      
      // Send the optimized image directly
      return res.send(outputBuffer);
    } else if (returnType === 'url') {
      // Generate a unique filename
      const filename = `${uuidv4()}.${format}`;
      const filePath = path.join(config.tempDir, filename);
      
      // Save the file
      fs.writeFileSync(filePath, outputBuffer);
      
      // Create a URL to access the file
      const fileUrl = `/temp/${filename}`;
      
      // Return metadata and URL
      return res.json({
        success: true,
        originalSize,
        optimizedSize,
        savingsPercent,
        format,
        width: metadata.width > maxWidth ? maxWidth : metadata.width,
        height: metadata.height,
        url: fileUrl
      });
    }
  } catch (error) {
    console.error('Error optimizing image:', error);
    res.status(500).json({ error: 'Image optimization failed', message: error.message });
  }
});

// Serve temporary files
app.use('/temp', express.static(config.tempDir));

// Error handling middleware
app.use((err, req, res, next) => {
  console.error(err.stack);
  
  if (err.message === 'File too large') {
    return res.status(413).json({ 
      error: 'File too large', 
      message: `Maximum file size is ${config.maxFileSize / (1024 * 1024)}MB` 
    });
  }
  
  res.status(500).json({ error: 'Something went wrong', message: err.message });
});

// Start the server
app.listen(config.port, () => {
  console.log(`Image optimization server running on port ${config.port}`);
  console.log(`Health check: http://localhost:${config.port}/health`);
  console.log(`Optimization endpoint: http://localhost:${config.port}/optimize`);
});

module.exports = app; // For testing
