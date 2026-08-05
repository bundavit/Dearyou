# DearYou Architecture

DearYou is a monorepo with a Laravel application in `backend/`. Repository-level infrastructure, deployment templates, API documentation, portfolio screenshots, and project documentation remain at the root.

## Current Layout

```text
DearYou/
|-- backend/       Laravel web application and API
|-- deploy/        DigitalOcean, Nginx, systemd, and backup templates
|-- docker/        Local PHP and Nginx images/configuration
|-- docs/          Product, architecture, and deployment documentation
|-- postman/       API collection
|-- screenshots/   Portfolio images
|-- scripts/       Screenshot and local demo tooling
`-- compose.yaml   Local multi-service environment
```

The Blade frontend remains inside `backend/resources/views/` while the backend API is expanded. A standalone `frontend/` application can be introduced later without moving the Laravel application again.

## Runtime Boundaries

```text
Browser / API client
        |
        v
Nginx -> Laravel routes and middleware
        |
        +-- Web controllers -> Blade views
        +-- API controllers -> JSON responses
        +-- Queue worker -> Resend and background work
        +-- Scheduler -> cleanup and maintenance commands
        |
        v
Eloquent models -> MySQL
        |
        `-- Laravel filesystem -> uploaded media
```

## Local Docker Services

- `web`: Nginx on host port `8088` by default
- `app`: PHP 8.4 FPM and Composer
- `mysql`: MySQL 8.4, private to the Docker network
- `node`: Vite development server on host port `5174` by default
- `worker`: Laravel queue worker
- `scheduler`: Laravel scheduler process

Docker is optional for local development. Production continues to use the documented DigitalOcean Nginx, PHP-FPM, MySQL, worker, and scheduler setup.

## Migration Direction

1. Keep all current web behavior working from Laravel.
2. Version and complete the JSON API.
3. Introduce a separate frontend only after each required workflow has an API contract.
4. Remove Blade pages incrementally after their replacement is verified.
