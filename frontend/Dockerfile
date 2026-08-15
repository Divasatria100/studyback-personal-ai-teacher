# Use Node.js LTS Alpine as base image
FROM node:20-alpine

# Set working directory
WORKDIR /app

# Install dependencies for better npm performance
RUN apk add --no-cache \
    git

# Copy package files first for better layer caching
COPY package*.json ./

# Install dependencies
RUN npm install

# Copy application files
COPY . .

# Expose Vite development server port
EXPOSE 5173

# Default command - can be overridden in docker-compose.yml
# Vite must bind to 0.0.0.0 to be accessible from host
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
