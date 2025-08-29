# Tech Stack & Development

## Framework & Core Technologies

-   **Laravel 12.x** (PHP 8.2+)
-   **Laravel Sanctum** for API authentication
-   **Spatie Laravel Permission** for role-based access control
-   **PostgreSQL** database (configurable to MySQL/SQLite)

## Key Dependencies

-   **Scramble** - Automatic API documentation generation
-   **Intervention Image v3** - Photo processing and thumbnails
-   **Laravel Pint** - Code formatting
-   **PHPUnit** - Testing framework
-   **Laravel Sail** - Docker development environment

## Frontend Build Tools

-   **Vite** - Build tool and dev server
-   **TailwindCSS v4** - CSS framework
-   **Concurrently** - Run multiple dev processes

## Common Development Commands

### Server & Development

```bash
# Start development server
php artisan serve

# Run all dev processes (server, queue, logs, vite)
composer run dev

# Run queue worker for notifications
php artisan queue:work

# Monitor application logs
php artisan pail
```

### Database Operations

```bash
# Fresh migration with seeding
php artisan migrate:fresh --seed

# Run specific seeder
php artisan db:seed --class=RolePermissionSeeder
```

### Testing & Code Quality

```bash
# Run tests
php artisan test

# Format code with Pint
./vendor/bin/pint
```

### API Token Management

```bash
# Generate API token for testing
php artisan api:generate-token admin@example.com

# Generate token with expiration
php artisan api:generate-token kurir@example.com --expires=30
```

## Environment Configuration

-   Uses PostgreSQL by default (DB_CONNECTION=pgsql)
-   Queue connection uses database driver
-   File storage uses local disk
-   Supports both local and production environments
