# Musikku - Pemutar Musik Interaktif

Pemutar Musik Interaktif berbasis web untuk Mahasiswa (Laravel 12 + MySQL 8).

## Fitur Enterprise

### Phase 1: RESTful API (versioned `/api/v1`)
- Full CRUD untuk Songs, Artists, Albums, Genres
- Role-based endpoints: public / user / admin
- JSON responses dengan pagination

### Phase 2: Database & Storage
- MySQL 8 dengan InnoDB, prepared statements
- Read-replica ready configuration
- S3-compatible storage untuk CDN

### Phase 3: Auth & Permission
- Bcrypt hashing + salting (Laravel built-in)
- Sanctum API token authentication
- Role: `user` dan `admin`
- Account lockout (5 failed attempts → 15 min lock)
- Password policy (min 8, mixed case, numbers)

### Phase 4: Security & LRS Local
- OWASP security headers (X-Content-Type-Options, HSTS, etc.)
- CSRF protection (web) + Sanctum (API)
- Encrypted sessions + HTTPS enforcement
- Audit logging: semua aksi CRUD tercatat
- CORS configuration

### Phase 5: Rate Limiting
| Tier | Limit | Scope |
|------|-------|-------|
| `api-auth` | 10/min | Per IP |
| `api-user` | 60/min | Per user |
| `api-admin` | 120/min | Per user |
| Login brute-force | 5 attempts | Per IP (5-min window) |

### Phase 6: Caching & CDN
- ETag-based HTTP caching middleware
- Server-side cache (file/Redis)
- CDN-ready static asset headers
- Sanctum SPA stateful domains

### Phase 7: Load Balancing & SEO
- Read-replica database config
- SEO metadata management (admin CRUD)
- Dynamic meta tags per route
- Sitemap-ready structure

### Phase 8: Availability & Recovery
- MySQL backup via admin API
- Health check endpoint (`/api/v1/admin/health`)
- Artisan health:check command
- Backup history logging

### Phase 9: Error Tracking & Logs (Admin)
- Unhandled exceptions → `error_logs` table
- Multi-channel logging (single, daily, API, security)
- Admin CRUD for error logs (view, resolve, delete)
- Audit log viewer (filterable)
- Artisan cleanup commands

## API Endpoints

### Public
```
GET  /api/v1/songs
GET  /api/v1/songs/{id}
GET  /api/v1/artists
GET  /api/v1/artists/{id}
GET  /api/v1/albums
GET  /api/v1/albums/{id}
GET  /api/v1/genres
GET  /api/v1/genres/{id}
GET  /api/v1/charts
GET  /api/v1/search?q=
```

### User (Bearer Token)
```
POST   /api/v1/register
POST   /api/v1/login
POST   /api/v1/logout
GET    /api/v1/me
PUT    /api/v1/me
PUT    /api/v1/me/password
GET    /api/v1/favorites
POST   /api/v1/favorites/{song}
GET    /api/v1/history
DELETE /api/v1/history
GET    /api/v1/playlists
POST   /api/v1/playlists
GET    /api/v1/playlists/{id}
PUT    /api/v1/playlists/{id}
DELETE /api/v1/playlists/{id}
POST   /api/v1/playlists/{id}/songs/{song}
DELETE /api/v1/playlists/{id}/songs/{song}
GET    /api/v1/songs/{id}/stream
POST   /api/v1/songs/{id}/played
```

### Admin (Bearer Token + admin role)
```
CRUD   /api/v1/admin/songs
CRUD   /api/v1/admin/artists
CRUD   /api/v1/admin/albums
CRUD   /api/v1/admin/genres
GET    /api/v1/admin/users
GET    /api/v1/admin/users/{id}
PATCH  /api/v1/admin/users/{id}/role
PATCH  /api/v1/admin/users/{id}/status
DELETE /api/v1/admin/users/{id}
GET    /api/v1/admin/dashboard/stats
GET    /api/v1/admin/error-logs
GET    /api/v1/admin/error-logs/{id}
PATCH  /api/v1/admin/error-logs/{id}/resolve
DELETE /api/v1/admin/error-logs/{id}
GET    /api/v1/admin/audit-logs
GET    /api/v1/admin/backups
POST   /api/v1/admin/backups
CRUD   /api/v1/admin/seo
GET    /api/v1/admin/health
```

## Default Accounts
| Email | Password | Role |
|-------|----------|------|
| admin@musikku.test | Admin123! | admin |
| user@musikku.test | User1234! | user |

## Setup
```bash
composer install
cp .env.example .env  # (atau edit .env)
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```
