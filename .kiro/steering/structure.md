# Project Structure & Organization

## API Architecture

This is a Laravel API-first application with clear separation of concerns following Laravel conventions.

## Controller Organization

```
app/Http/Controllers/Api/
├── AuthController.php              # Authentication & token management
├── ShipmentController.php          # Shipment CRUD & workflow
├── ShipmentProgressController.php  # Progress tracking & updates
├── DashboardController.php         # Analytics & statistics
├── NotificationController.php      # Notification management
├── FileController.php              # File uploads & downloads
├── DivisionController.php          # Division management
├── UserController.php              # User management
├── RoleController.php              # Role management (Admin only)
└── PermissionController.php        # Permission management (Admin only)
```

## Model Relationships

```
app/Models/
├── User.php                 # Users with roles, divisions, and shipment relations
├── Division.php             # Company divisions/departments
├── Shipment.php             # Main shipment entity
├── ShipmentDestination.php  # Multiple delivery destinations per shipment
├── ShipmentItem.php         # Items within shipments
├── ShipmentProgress.php     # Progress tracking with photos
└── Notification.php         # User notifications
```

## Request Validation

-   All API endpoints use Form Request classes for validation
-   Located in `app/Http/Requests/` with descriptive names
-   Validation rules are centralized and reusable

## API Versioning & Routes

-   API routes are versioned under `/api/v1/` prefix
-   Authentication routes are public, all others require Sanctum token
-   Role-based middleware protects admin-only endpoints
-   Permission-based middleware for granular access control

## File Storage Structure

-   SPJ documents: `storage/app/spj/`
-   Progress photos: `storage/app/progress_photos/`
-   Thumbnails: Auto-generated with Intervention Image

## Database Conventions

-   Uses standard Laravel migration naming
-   Foreign key relationships follow Laravel conventions
-   Soft deletes not implemented (hard deletes used)
-   Timestamps on all relevant tables

## Service Layer

-   `app/Services/NotificationService.php` handles notification logic
-   Services encapsulate complex business logic outside controllers

## Console Commands

-   `app/Console/Commands/GenerateApiToken.php` for development token generation
-   Custom Artisan commands for common development tasks

## Testing Structure

-   Feature tests for API endpoints
-   Unit tests for business logic
-   Test database seeding for consistent test data
