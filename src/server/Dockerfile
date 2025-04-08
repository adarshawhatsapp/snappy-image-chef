
FROM node:18-alpine

# Install dependencies for Sharp
RUN apk add --no-cache \
    python3 \
    make \
    g++ \
    libc6-compat

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --only=production

# Copy app files
COPY . .

# Create directories
RUN mkdir -p uploads temp

# Expose the port
EXPOSE 3000

# Start the app
CMD ["node", "index.js"]
