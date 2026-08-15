# Studyback - Docker Development Environment

Studyback adalah aplikasi React SPA + Laravel Modular Monolith + PostgreSQL.

## Tech Stack

- **Frontend**: React + Vite + Tailwind CSS
- **Backend**: Laravel Modular Monolith (PHP 8.2)
- **Database**: PostgreSQL 16
- **Web Server**: Nginx (Reverse Proxy)
- **PDF Processing**: Poppler-utils (pdftotext)

## Docker Services

Project ini menggunakan Docker Compose dengan services berikut:

1. **postgres** - PostgreSQL 16 database
2. **backend** - Laravel application (PHP 8.2 + pdftotext)
3. **frontend** - React application (Vite dev server)
4. **nginx** - Reverse proxy untuk routing

## Prerequisites

- Docker Desktop
- Docker Compose

## Project Structure

```
studyback/
├── docker/
│   └── nginx/               # Nginx configuration
│       ├── nginx.conf       # Main nginx config
│       └── default.conf     # Site config with routing
├── backend/
│   ├── Dockerfile           # Laravel container config
│   ├── .dockerignore        # Files to exclude from Docker build
│   └── ...                  # Laravel application files
├── frontend/
│   ├── Dockerfile           # React container config
│   ├── .dockerignore        # Files to exclude from Docker build
│   └── ...                  # React application files
├── docker-compose.yml       # Docker Compose configuration
└── .env.example             # Environment variables template
```

## Setup Instructions

### 1. Environment Configuration

Copy `.env.example` to `.env` and adjust values if needed:

```bash
cp .env.example .env
```

Default ports:
- Nginx: `80`
- Frontend (Vite): `5173`
- Backend (Laravel): `8000`
- PostgreSQL: `5432`

### 2. Build and Start Services

```bash
docker compose build
docker compose up -d
```

### 3. Initialize Laravel

Generate application key and run migrations:

```bash
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate
```

## Common Commands

### View running services
```bash
docker compose ps
```

### View logs
```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f backend
docker compose logs -f frontend
docker compose logs -f postgres
docker compose logs -f nginx
```

### Stop services
```bash
docker compose stop
```

### Stop and remove containers
```bash
docker compose down
```

### Stop and remove containers + volumes
```bash
docker compose down -v
```

### Restart a service
```bash
docker compose restart backend
```

### Execute commands in containers
```bash
# Backend (Laravel)
docker compose exec backend php artisan migrate
docker compose exec backend php artisan tinker
docker compose exec backend composer install

# Frontend (React)
docker compose exec frontend npm install
docker compose exec frontend npm run build

# Database
docker compose exec postgres psql -U studyback -d studyback
```

### Rebuild containers
```bash
# Rebuild all
docker compose build --no-cache

# Rebuild specific service
docker compose build --no-cache backend
```

## Access Points

When services are running:

- **Application (via Nginx)**: http://localhost
- **Frontend (Direct)**: http://localhost:5173
- **Backend API (Direct)**: http://localhost:8000
- **PostgreSQL**: localhost:5432

## Routing

Nginx acts as reverse proxy with the following routing:

- `/` → Frontend (React/Vite)
- `/api/*` → Backend (Laravel)
- `/storage/*` → Backend (Laravel static files)
- `/@vite/*` → Frontend (Vite HMR WebSocket)

## Docker Volumes

Persistent volumes:
- `postgres_data` - Database data
- `backend_vendor` - PHP dependencies (prevents overwriting by bind mount)
- `backend_storage` - Laravel storage directory
- `frontend_node_modules` - Node.js dependencies (prevents overwriting by bind mount)

## Development Workflow

### Hot Reload

Both frontend and backend support hot reload:

1. **Frontend (Vite)**: File changes in `./frontend/src/` are automatically detected and trigger HMR
2. **Backend (Laravel)**: Laravel dev server automatically reloads on file changes in `./backend/`

### Installing Dependencies

**Backend (Composer):**
```bash
docker compose exec backend composer require package/name
```

**Frontend (npm):**
```bash
docker compose exec frontend npm install package-name
```

### Database Access

**Using psql:**
```bash
docker compose exec postgres psql -U studyback -d studyback
```

**Using Laravel Tinker:**
```bash
docker compose exec backend php artisan tinker
```

## Verifying pdftotext

The backend container includes poppler-utils for PDF text extraction:

```bash
docker compose exec backend pdftotext -v
```

## Troubleshooting

### Port conflicts
If ports are already in use, modify them in `.env`:
```
NGINX_PORT=8080
FRONTEND_PORT=5174
BACKEND_PORT=8001
DB_PORT=5433
```

### Permission issues
If you encounter permission errors with Laravel:
```bash
docker compose exec backend chmod -R 775 storage bootstrap/cache
docker compose exec backend chown -R www-data:www-data storage bootstrap/cache
```

### Database connection issues
Ensure PostgreSQL is healthy:
```bash
docker compose ps postgres
```

Check logs:
```bash
docker compose logs postgres
```

### Frontend not updating
Restart the frontend service:
```bash
docker compose restart frontend
```

### Clear all Docker resources
```bash
docker compose down -v
docker system prune -a
```

## Security Notes

- Default credentials are for **local development only**
- Never commit `.env` file with real credentials
- Change all passwords before deploying to production
- PostgreSQL is not exposed to public interface by default

## Next Steps

After Docker setup is complete:

1. Implement application features
2. Create database migrations
3. Build API endpoints
4. Develop React components
5. Configure AI integration

## License

[Your License Here]
