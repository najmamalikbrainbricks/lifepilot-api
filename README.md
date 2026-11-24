# LifePilot API — Backend Service

## Overview
Laravel 11/12 REST API powering the LifePilot productivity and daily intention platform.

## Configuration
- **Database**: MySQL `lifepilot_db` on `127.0.0.1:3306`
- **Host**: `http://127.0.0.1:8000`
- **API Prefix**: `/api/v1`
- **CORS**: Configured for `http://localhost:3000` and `http://127.0.0.1:3000`

## API Endpoints Summary

### Authentication
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/me`

### Profile & Settings
- `GET /api/v1/preferences`
- `PUT /api/v1/preferences`
- `GET|POST /api/v1/goals`
- `PUT|DELETE /api/v1/goals/{id}`
- `GET|POST /api/v1/work-shifts`
- `PUT|DELETE /api/v1/work-shifts/{id}`

### Routines & Builder
- `GET|POST /api/v1/routines`
- `GET|PUT|DELETE /api/v1/routines/{id}`
- `POST /api/v1/routines/{id}/archive`
- `POST /api/v1/routines/{id}/unarchive`
- `GET|POST /api/v1/routines/{id}/steps`
- `PUT|DELETE /api/v1/routines/{id}/steps/{stepId}`
- `POST /api/v1/routines/{id}/steps/reorder`
- `GET|POST /api/v1/routines/{id}/schedules`
- `PUT|DELETE /api/v1/routines/{id}/schedules/{scheduleId}`
- `GET|POST /api/v1/routines/{id}/alarm-rule`

### Task Execution Engine
- `GET /api/v1/occurrences`
- `GET /api/v1/occurrences/{id}`
- `POST /api/v1/occurrences/{id}/start`
- `POST /api/v1/occurrences/{id}/steps/{stepId}/complete`
- `POST /api/v1/occurrences/{id}/steps/{stepId}/skip`
- `POST /api/v1/occurrences/{id}/complete`
- `POST /api/v1/occurrences/{id}/skip`
- `POST /api/v1/occurrences/{id}/pause`

### Reports & Migration
- `GET /api/v1/reports/daily`
- `POST /api/v1/migration/import`

## Running Automated Tests
```powershell
php artisan test
```
All 26 feature and unit tests run against in-memory SQLite and pass without dependencies on external services.
