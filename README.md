# Track Kurir - Courier Tracking API

API sistem tracking kurir dengan role-based access control untuk mengelola pengiriman, delivery, dan progress tracking.

**Author:** Muammar  
**Repository:** https://github.com/muamars/api-kurir.git

## 🚀 Fitur Utama

### 🔐 Authentication & Authorization

-   ✅ Login/Logout dengan Laravel Sanctum
-   ✅ Role-based Access Control (Admin, Kurir, User)
-   ✅ Permission-based Authorization
-   ✅ Multi-division organization

### 📦 Shipment Management

-   ✅ Create, approve, assign shipments
-   ✅ Multiple destinations per shipment
-   ✅ Priority levels (regular, urgent)
-   ✅ Advanced filtering dan search
-   ✅ Real-time status tracking

### 🚚 Delivery Tracking

-   ✅ Real-time progress updates
-   ✅ Photo upload dengan thumbnails
-   ✅ Multi-destination deliveries
-   ✅ Driver assignment dan tracking
-   ✅ GPS location tracking

### 📊 Dashboard & Analytics

-   ✅ Role-specific statistics
-   ✅ Chart data (weekly, monthly, yearly)
-   ✅ Performance metrics
-   ✅ Real-time notifications

### 📁 File Management

-   ✅ SPJ document upload/download
-   ✅ Progress photos dengan compression
-   ✅ Bulk photo download (ZIP)

### 🔔 Notification System

-   ✅ Real-time notifications untuk semua events
-   ✅ Mark as read functionality
-   ✅ Unread count tracking

### 👥 User & Role Management

-   ✅ CRUD operations untuk users, roles, permissions
-   ✅ Division-based organization
-   ✅ Active/inactive user status

## 🏗️ Tech Stack

### Backend Framework

-   **Laravel 12.x** (PHP 8.2+)
-   **Laravel Sanctum** for API authentication
-   **Spatie Laravel Permission** for role-based access control
-   **SQLite** database (configurable to MySQL/PostgreSQL)

### Documentation

-   **Scramble** for automatic API documentation generation
-   **Interactive API Testing** built-in

### Image Processing

-   **Intervention Image v3** for photo processing and thumbnails

### Development Tools

-   **Laravel Pint** for code formatting
-   **PHPUnit** for testing
-   **Laravel Sail** for Docker development

## 📋 Roles & Permissions

### Default Roles:

1. **Admin** - Full access ke semua fitur

    - Approve shipments
    - Assign drivers
    - Manage users & roles
    - View all analytics

2. **Kurir** - Driver-specific access

    - View assigned shipments
    - Update delivery progress
    - Upload delivery photos
    - Track delivery history

3. **User** - Regular user access
    - Create shipment requests
    - Track own shipments
    - View delivery progress
    - Receive notifications

### Default Permissions:

-   `view-dashboard` - Akses dashboard
-   `manage-shipments` - CRUD shipments
-   `approve-shipments` - Approve pending shipments
-   `assign-drivers` - Assign drivers to shipments
-   `manage-users` - CRUD users
-   `manage-roles` - CRUD roles & permissions
-   `view-analytics` - View analytics data
-   `manage-files` - Upload/download files

## 🛠️ Setup & Installation

### 1. Clone Repository

```bash
git clone https://github.com/muamars/api-kurir.git
cd api-kurir
composer install
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Configuration

Edit `.env` file:

```env
# SQLite (default)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Atau MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=track_kurir
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Database Migration & Seeding

```bash
# Buat database SQLite
touch database/database.sqlite

# Run migrations dan seeders
php artisan migrate:fresh --seed
```

### 5. Storage Link

```bash
php artisan storage:link
```

### 6. Start Server

```bash
php artisan serve
```

## 👤 Default Users

Setelah seeding, Anda dapat login dengan:

| Email             | Password | Role  | Division |
| ----------------- | -------- | ----- | -------- |
| admin@example.com | password | Admin | Sales    |
| kurir@example.com | password | Kurir | Store    |
| user@example.com  | password | User  | Gudang   |

## 📚 API Documentation

### Automatic Documentation

-   **Scramble Documentation**: `http://localhost:8000/docs/api`
-   **Interactive Testing**: Test API langsung dari dokumentasi
-   **Auto-generated**: Update otomatis saat code berubah

### Manual Testing

-   **REST Client**: Gunakan file `test_api.http` dengan VS Code REST Client extension
-   **38+ Test Cases**: Complete scenarios untuk Admin, Driver, dan User workflows

## 🔑 Token Generation untuk Testing

### Via Artisan Command (Recommended)

```bash
# Generate permanent token untuk admin
php artisan api:generate-token admin@example.com

# Generate token dengan nama custom
php artisan api:generate-token admin@example.com --name="my-test-token"

# Generate token dengan expiration (30 hari)
php artisan api:generate-token kurir@example.com --expires=30

# Generate token dengan abilities tertentu
php artisan api:generate-token user@example.com --abilities="view-shipments,create-shipments"
```

### Via API Endpoint (Development Only)

```bash
# Generate permanent token
curl -X POST http://localhost:8000/api/v1/auth/generate-test-token \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "token_name": "permanent-test-token"}'

# Generate temporary token (7 days)
curl -X POST http://localhost:8000/api/v1/auth/generate-test-token \
  -H "Content-Type: application/json" \
  -d '{"email": "kurir@example.com", "token_name": "temp-token", "expires_in_days": 7}'
```

### Contoh Response Token Generation

```json
{
    "message": "Test token generated successfully",
    "data": {
        "user": {
            "id": 1,
            "name": "Admin User",
            "email": "admin@example.com",
            "roles": ["Admin"],
            "division": {
                "id": 1,
                "name": "Sales"
            }
        },
        "token": "1|abc123def456...",
        "token_name": "permanent-test-token",
        "expires_at": null,
        "is_permanent": true
    }
}
```

## 🔗 API Endpoints

### Authentication

```
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
POST   /api/v1/auth/generate-test-token    # Development only
```

### Shipments

```
GET    /api/v1/shipments
POST   /api/v1/shipments
GET    /api/v1/shipments/{id}
POST   /api/v1/shipments/{id}/approve
POST   /api/v1/shipments/{id}/assign-driver
POST   /api/v1/shipments/{id}/start-delivery
```

### Progress Tracking

```
GET    /api/v1/shipments/{id}/progress
POST   /api/v1/shipments/{shipment}/destinations/{destination}/progress
GET    /api/v1/driver/history
```

### Dashboard & Analytics

```
GET    /api/v1/dashboard
GET    /api/v1/dashboard/chart-data
```

### Notifications

```
GET    /api/v1/notifications
POST   /api/v1/notifications/{id}/read
POST   /api/v1/notifications/mark-all-read
GET    /api/v1/notifications/unread-count
```

### File Management

```
POST   /api/v1/shipments/{id}/upload-spj
GET    /api/v1/shipments/{id}/download-spj
GET    /api/v1/shipments/{id}/download-photos
GET    /api/v1/shipments/{id}/files
```

### Master Data

```
GET    /api/v1/divisions
GET    /api/v1/drivers
GET    /api/v1/users
POST   /api/v1/users
PUT    /api/v1/users/{id}
DELETE /api/v1/users/{id}

# Admin only routes
GET    /api/v1/roles
POST   /api/v1/roles
PUT    /api/v1/roles/{id}
DELETE /api/v1/roles/{id}
POST   /api/v1/roles/{id}/assign-permissions
POST   /api/v1/roles/{id}/remove-permissions

GET    /api/v1/permissions
POST   /api/v1/permissions
PUT    /api/v1/permissions/{id}
DELETE /api/v1/permissions/{id}
GET    /api/v1/permissions-grouped
```

## 🏗️ Project Structure

### API Controllers (`app/Http/Controllers/Api/`)

```
├── AuthController.php              # Authentication
├── ShipmentController.php          # Shipment management
├── ShipmentProgressController.php  # Progress tracking
├── DashboardController.php         # Analytics & statistics
├── NotificationController.php      # Notifications
├── FileController.php              # File management
├── DivisionController.php          # Division management
├── UserController.php              # User management
├── RoleController.php              # Role management
└── PermissionController.php        # Permission management
```

### Models (`app/Models/`)

```
├── User.php                 # User dengan roles & divisions
├── Division.php             # Company divisions
├── Shipment.php             # Main shipment model
├── ShipmentDestination.php  # Multiple destinations
├── ShipmentItem.php         # Shipment items
├── ShipmentProgress.php     # Progress tracking
└── Notification.php         # Notification system
```

### Form Requests (`app/Http/Requests/`)

```
├── LoginRequest.php
├── StoreShipmentRequest.php
├── UpdateShipmentRequest.php
├── StoreUserRequest.php
├── UpdateUserRequest.php
├── StoreRoleRequest.php
└── UpdatePermissionRequest.php
```

### Resources (`app/Http/Resources/`)

```
└── ShipmentResource.php     # API resource formatting
```

### Services (`app/Services/`)

```
└── NotificationService.php  # Notification handling
```

### Commands (`app/Console/Commands/`)

```
└── GenerateApiToken.php     # Generate API tokens for testing
```

## 🔧 Development Commands

### Development

```bash
# Start development server
php artisan serve

# Run queue worker (for notifications)
php artisan queue:work

# Monitor logs
php artisan pail

# Generate API token for testing
php artisan api:generate-token admin@example.com
```

### Database

```bash
# Fresh migration with seeding
php artisan migrate:fresh --seed

# Run specific seeder
php artisan db:seed --class=CourierTrackingSeeder
```

### Testing

```bash
# Run tests
php artisan test

# Code formatting
./vendor/bin/pint
```

## 📱 Frontend Integration

API ini siap diintegrasikan dengan frontend apapun:

### Response Format

```json
{
    "message": "Success message",
    "data": {
        // Response data
    },
    "meta": {
        // Pagination info (for paginated responses)
    }
}
```

### Authentication Flow

```javascript
// 1. Login
const loginResponse = await fetch("/api/v1/auth/login", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        email: "admin@example.com",
        password: "password",
    }),
});

const { data } = await loginResponse.json();
const token = data.token;

// 2. Use token for subsequent requests
const shipmentsResponse = await fetch("/api/v1/shipments", {
    headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
    },
});

const shipments = await shipmentsResponse.json();

// 3. Create new shipment
const createResponse = await fetch("/api/v1/shipments", {
    method: "POST",
    headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        notes: "Urgent delivery",
        priority: "urgent",
        destinations: [
            {
                receiver_name: "John Doe",
                delivery_address: "Jl. Sudirman No. 123, Jakarta",
                shipment_note: "Call before delivery",
            },
        ],
        items: [
            {
                item_name: "Documents",
                quantity: 1,
                description: "Important contracts",
            },
        ],
    }),
});

// 4. Upload progress with photo (multipart/form-data)
const formData = new FormData();
formData.append("status", "delivered");
formData.append("note", "Package delivered successfully");
formData.append("receiver_name", "John Doe");
formData.append("photo", photoFile);

const progressResponse = await fetch(
    "/api/v1/shipments/1/destinations/1/progress",
    {
        method: "POST",
        headers: {
            Authorization: `Bearer ${token}`,
        },
        body: formData,
    }
);
```

### Error Handling

```javascript
const response = await fetch("/api/v1/shipments", {
    headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
    },
});

if (!response.ok) {
    const error = await response.json();
    console.error("API Error:", error.message);

    if (response.status === 401) {
        // Token expired or invalid - redirect to login
        window.location.href = "/login";
    }
}

const data = await response.json();
```

## 🚀 Deployment

### Production Setup

1. Set environment variables
2. Configure database
3. Run migrations
4. Set up queue worker
5. Configure file storage
6. Set up SSL certificate

### Environment Variables

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=track_kurir
DB_USERNAME=your-username
DB_PASSWORD=your-password

# Queue (for notifications)
QUEUE_CONNECTION=database

# File Storage
FILESYSTEM_DISK=public
```

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Muammar**

-   GitHub: [@muamars](https://github.com/muamars)
-   Repository: [api-kurir](https://github.com/muamars/api-kurir.git)

## 🙏 Acknowledgments

-   Laravel Framework
-   Spatie Laravel Permission
-   Scramble API Documentation
-   Intervention Image
-   All contributors and supporters

---

**Track Kurir** - Making courier tracking simple and efficient! 📦🚚
