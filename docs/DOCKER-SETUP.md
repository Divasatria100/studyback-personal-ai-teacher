# Docker Setup Documentation - Studyback

## Overview

Dokumentasi ini menjelaskan setup Docker untuk Studyback local development environment.

## Architecture

```
┌──────────────┐
│    Nginx     │  Port 80
│ Reverse Proxy│
└──────┬───────┘
       │
       ├────────────┬────────────┐
       │            │            │
┌──────▼──────┐ ┌──▼────────┐   │
│   Frontend  │ │  Backend  │   │
│ React/Vite  │ │  Laravel  │   │
│ Port 5173   │ │ Port 8000 │   │
└─────────────┘ └──────┬────┘   │
                       │        │
                ┌──────▼────────▼───┐
                │    PostgreSQL     │
                │     Port 5432     │
                └───────────────────┘
```

## Docker Services

### 1. PostgreSQL (postgres)

**Image**: `postgres:16-alpine`

**Purpose**: Database server

**Features**:
- Persistent volume untuk data
- Health check untuk memastikan database ready sebelum backend start
- Environment variables untuk database credentials

**Environment Variables**:
```env
POSTGRES_DB=studyback
POSTGRES_USER=studyback
POSTGRES_PASSWORD=studyback_password
```

**Volume**:
- `postgres_data:/var/lib/postgresql/data` - Persistent database storage

**Health Check**:
```yaml
test: pg_isready -U studyback -d studyback
interval: 10s
timeout: 5s
retries: 5
```

### 2. Backend (backend)

**Base Image**: `php:8.2-fpm-alpine`

**Purpose**: Laravel application server

**Features**:
- PHP 8.2 dengan extensions yang diperlukan Laravel
- PostgreSQL PDO driver
- Poppler-utils (pdftotext) untuk PDF text extraction
- Composer untuk dependency management
- Development dengan hot reload

**Installed PHP Extensions**:
- pdo, pdo_pgsql, pgsql
- mbstring, exif, pcntl, bcmath
- gd (dengan freetype dan jpeg support)
- xml

**System Packages**:
- poppler-utils (pdftotext command)
- PostgreSQL client
- Git, curl, zip, unzip

**Volumes**:
- `./backend:/var/www/html` - Source code bind mount
- `backend_vendor:/var/www/html/vendor` - Composer dependencies (prevents host override)
- `backend_storage:/var/www/html/storage` - Laravel storage directory

**Command**: `php artisan serve --host=0.0.0.0 --port=8000`

**Depends On**: postgres (with health check)

### 3. Frontend (frontend)

**Base Image**: `node:20-alpine`

**Purpose**: React application with Vite dev server

**Features**:
- Node.js 20 LTS
- Hot Module Replacement (HMR)
- Vite bind ke 0.0.0.0 untuk accessibility dari host
- Automatic file watching

**Volumes**:
- `./frontend:/app` - Source code bind mount
- `frontend_node_modules:/app/node_modules` - npm dependencies (prevents host override)

**Command**: `npm run dev -- --host 0.0.0.0`

**Vite Configuration**:
```js
server: {
  host: '0.0.0.0',
  port: 5173,
  watch: {
    usePolling: true,  // Required for Docker file watching
  },
  hmr: {
    host: 'localhost',
    port: 5173,
  },
}
```

### 4. Nginx (nginx)

**Image**: `nginx:alpine`

**Purpose**: Reverse proxy dan routing

**Routing Rules**:
- `/` → frontend:5173 (React SPA)
- `/api/*` → backend:8000 (Laravel API)
- `/storage/*` → backend:8000 (Laravel storage files)
- `/@vite/*`, `/__vite_ping` → frontend:5173 (Vite HMR WebSocket)

**Configuration Files**:
- `docker/nginx/nginx.conf` - Main nginx configuration
- `docker/nginx/default.conf` - Site-specific configuration

**Features**:
- Gzip compression
- WebSocket support untuk Vite HMR
- Proper headers untuk proxying

**Depends On**: backend, frontend

## Docker Volumes

### Named Volumes (Persistent)

1. **postgres_data**
   - Purpose: PostgreSQL database storage
   - Persists: Database tables, indexes, data
   - Cleanup: `docker compose down -v`

2. **backend_vendor**
   - Purpose: PHP Composer dependencies
   - Persists: vendor/ directory
   - Why: Prevents host bind mount from overwriting container dependencies
   - Cleanup: `docker compose down -v`

3. **backend_storage**
   - Purpose: Laravel storage directory
   - Persists: logs, cache, uploads
   - Cleanup: `docker compose down -v`

4. **frontend_node_modules**
   - Purpose: Node.js npm dependencies
   - Persists: node_modules/ directory
   - Why: Prevents host bind mount from overwriting container dependencies
   - Cleanup: `docker compose down -v`

### Bind Mounts

1. **./backend → /var/www/html**
   - Purpose: Laravel source code
   - Enables: Real-time code changes without rebuild

2. **./frontend → /app**
   - Purpose: React source code
   - Enables: Hot Module Replacement (HMR)

3. **./docker/nginx/*.conf → /etc/nginx/**
   - Purpose: Nginx configuration
   - Enables: Configuration changes without rebuild

## Networking

**Network Name**: `studyback_network`

**Driver**: bridge

**Service Communication**:
Services communicate using service names (DNS):
- Backend connects to database: `postgres:5432`
- Nginx proxies to: `frontend:5173` and `backend:8000`

**Advantages**:
- No hardcoded IPs
- Automatic service discovery
- Isolated from host network
- Port mapping untuk host access

## Environment Variables

### Project Root (.env)

```env
# Application
APP_NAME=Studyback
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Docker Ports
NGINX_PORT=80
FRONTEND_PORT=5173
BACKEND_PORT=8000
DB_PORT=5432

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=studyback
DB_USERNAME=studyback
DB_PASSWORD=studyback_password

# Cache & Session
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

# Vite
VITE_API_URL=http://localhost:8000
```

### Backend (.env)

Laravel memerlukan `.env` sendiri. Copy dari `.env.example`:

```bash
cd backend
cp .env.example .env
```

Pastikan database configuration di `backend/.env` sesuai dengan Docker:
```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=studyback
DB_USERNAME=studyback
DB_PASSWORD=studyback_password
```

## .dockerignore Files

### Backend (.dockerignore)

Excludes:
- `.git/`, `.env`, `vendor/`, `node_modules/`
- Build artifacts: `storage/logs/`, `public/storage/`
- Development files: `.editorconfig`, `.phpunit.result.cache`
- IDE files: `.vscode/`, `.idea/`
- Documentation: `README.md`, `docs/`

### Frontend (.dockerignore)

Excludes:
- `.git/`, `.env`, `node_modules/`
- Build artifacts: `dist/`, `build/`, `.vite/`
- Development files: `.eslintrc*`, `.prettierrc*`
- IDE files: `.vscode/`, `.idea/`
- Documentation: `README.md`, `docs/`

## Development Workflow

### Initial Setup

```bash
# 1. Copy environment file
cp .env.example .env

# 2. Build containers
docker compose build

# 3. Start services
docker compose up -d

# 4. Initialize Laravel
docker compose exec backend cp .env.example .env
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate

# 5. Install frontend dependencies (if needed)
docker compose exec frontend npm install
```

### Daily Development

```bash
# Start services
docker compose up -d

# Watch logs
docker compose logs -f

# Stop services
docker compose stop
```

### Making Changes

**Backend (Laravel)**:
1. Edit files in `./backend/`
2. Changes are immediately reflected (no rebuild needed)
3. For dependency changes: `docker compose exec backend composer install`

**Frontend (React)**:
1. Edit files in `./frontend/src/`
2. Vite HMR automatically updates browser
3. For dependency changes: `docker compose exec frontend npm install`

**Docker Configuration**:
1. Edit `docker-compose.yml`, `Dockerfile`, or nginx configs
2. Rebuild: `docker compose build`
3. Restart: `docker compose up -d`

### Adding Dependencies

**PHP Package**:
```bash
docker compose exec backend composer require vendor/package
```

**Node Package**:
```bash
docker compose exec frontend npm install package-name
```

### Database Operations

**Run Migrations**:
```bash
docker compose exec backend php artisan migrate
```

**Seed Database**:
```bash
docker compose exec backend php artisan db:seed
```

**Access Database**:
```bash
docker compose exec postgres psql -U studyback -d studyback
```

**Laravel Tinker**:
```bash
docker compose exec backend php artisan tinker
```

## Troubleshooting

### Port Already in Use

**Problem**: Port conflicts with existing services

**Solution**: Modify ports in `.env`:
```env
NGINX_PORT=8080
FRONTEND_PORT=5174
BACKEND_PORT=8001
DB_PORT=5433
```

Then restart:
```bash
docker compose down
docker compose up -d
```

### Backend Cannot Connect to Database

**Problem**: `SQLSTATE[08006] Connection refused`

**Diagnosis**:
```bash
docker compose ps postgres
docker compose logs postgres
```

**Solutions**:
1. Ensure postgres is healthy: `docker compose ps`
2. Check DB_HOST in backend/.env is set to `postgres` (not `localhost`)
3. Restart backend: `docker compose restart backend`

### Frontend Hot Reload Not Working

**Problem**: Changes in React files not reflected in browser

**Diagnosis**:
```bash
docker compose logs frontend
```

**Solutions**:
1. Ensure `usePolling: true` in vite.config.js
2. Restart frontend: `docker compose restart frontend`
3. Hard refresh browser (Ctrl+Shift+R)

### Permission Denied on Laravel Storage

**Problem**: Laravel cannot write to storage directory

**Solution**:
```bash
docker compose exec backend chmod -R 775 storage bootstrap/cache
docker compose exec backend chown -R www-data:www-data storage bootstrap/cache
```

### "pdftotext: command not found"

**Problem**: pdftotext not available in backend container

**Diagnosis**:
```bash
docker compose exec backend pdftotext -v
```

**Solution**: Rebuild backend image:
```bash
docker compose build --no-cache backend
docker compose up -d
```

### Container Keeps Restarting

**Diagnosis**:
```bash
docker compose ps
docker compose logs [service-name]
```

**Common Causes**:
1. **Backend**: Missing APP_KEY in .env
   ```bash
   docker compose exec backend php artisan key:generate
   ```

2. **Frontend**: Missing dependencies
   ```bash
   docker compose exec frontend npm install
   ```

3. **Database**: Data corruption
   ```bash
   docker compose down -v
   docker compose up -d
   ```

### Clean Slate

To completely reset Docker environment:

```bash
# Stop and remove everything
docker compose down -v

# Remove images
docker compose down --rmi all

# Clean Docker system
docker system prune -a -f

# Rebuild from scratch
docker compose build --no-cache
docker compose up -d
```

## Performance Optimization

### Build Optimization

1. **.dockerignore**: Mengurangi context size untuk faster builds
2. **Layer caching**: Dependency installation sebelum copying source code
3. **Multi-stage builds**: Tidak digunakan untuk dev, tapi bisa ditambahkan untuk production

### Development Performance

1. **Named volumes untuk dependencies**: Prevents overwriting by bind mounts
2. **File watching**: Vite polling untuk Docker compatibility
3. **Alpine images**: Smaller image size, faster pulls

### Database Performance

1. **Persistent volumes**: Data tidak hilang saat restart
2. **Health checks**: Backend menunggu database ready
3. **Connection pooling**: Laravel default connection pooling

## Security Considerations

### Local Development

- Default credentials **HANYA untuk local development**
- Jangan commit `.env` dengan credentials asli
- PostgreSQL tidak exposed ke public interface (localhost only)

### Production Deployment

**DO NOT use this Docker setup for production without**:

1. **Environment Variables**: Use secrets management
2. **Database**: Use managed database service (AWS RDS, etc.)
3. **Nginx**: Add SSL/TLS certificates
4. **Images**: Use production-optimized builds (multi-stage, no dev dependencies)
5. **Networking**: Remove exposed ports, use internal networks only
6. **Volumes**: Use proper backup strategies

## Validation

### Pre-flight Check

```bash
# 1. Validate docker-compose.yml
docker compose config

# 2. Build images
docker compose build

# 3. Start services
docker compose up -d

# 4. Check status
docker compose ps
```

Expected output:
```
NAME                  STATUS              PORTS
studyback_postgres    Up (healthy)        0.0.0.0:5432->5432/tcp
studyback_backend     Up                  0.0.0.0:8000->8000/tcp
studyback_frontend    Up                  0.0.0.0:5173->5173/tcp
studyback_nginx       Up                  0.0.0.0:80->80/tcp
```

### Health Checks

```bash
# Check postgres health
docker compose exec postgres pg_isready -U studyback

# Check backend
curl http://localhost:8000

# Check frontend
curl http://localhost:5173

# Check nginx
curl http://localhost
```

### Verify pdftotext

```bash
docker compose exec backend pdftotext -v
```

Expected output: `pdftotext version X.X.X`

## References

- [Docker Compose Specification](https://docs.docker.com/compose/compose-file/)
- [PHP Official Images](https://hub.docker.com/_/php)
- [PostgreSQL Official Images](https://hub.docker.com/_/postgres)
- [Nginx Official Images](https://hub.docker.com/_/nginx)
- [Node.js Official Images](https://hub.docker.com/_/node)
- [Laravel Documentation](https://laravel.com/docs)
- [Vite Documentation](https://vitejs.dev/)

## Changelog

### Version 1.0.0 - Initial Setup

**Created**:
- docker-compose.yml dengan 4 services (postgres, backend, frontend, nginx)
- Backend Dockerfile (PHP 8.2 + pdftotext)
- Frontend Dockerfile (Node 20 + Vite)
- Nginx configuration (reverse proxy)
- .dockerignore files
- Environment configuration
- Documentation

**Features**:
- Hot reload untuk frontend dan backend
- PostgreSQL dengan health check
- Named volumes untuk dependencies
- Service discovery via Docker network
- Development-optimized workflow
