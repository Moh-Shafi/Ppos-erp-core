# POS SaaS — Multi-Tenant POS System

A multi-tenant SaaS Point of Sale system built with React + Laravel.

## Stack

| Layer | Technology |
|-------|-----------|
| Frontend | React, TypeScript, Vite, Tailwind CSS, shadcn/ui |
| Backend | Laravel, REST API, Sanctum, MySQL |
| Infrastructure | Docker, Nginx, VPS |

## Project Structure

```
pos-saas/
├── frontend/          # React + TypeScript + Vite
├── backend/           # Laravel API
├── docker/            # Docker configs
├── docs/              # Documentation
└── README.md
```

## Phase 1 — Foundation

- User registration (creates User + Tenant + Store)
- Authentication via Sanctum tokens
- Tenant isolation (users only see their own tenant data)
- Dashboard with basic info
- Store settings

## Quick Start

```bash
# Start backend (MySQL + Laravel)
docker compose up -d

# Start frontend
cd frontend
npm install
npm run dev
```

## API Base URL

```
http://localhost:8000/api/v1
```
