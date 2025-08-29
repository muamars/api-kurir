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

## 🔗 API Endpoints

### Authentication

```
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
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
POST   /api/v1/files/upload-spj
GET    /api/v1/files/download-spj/{id}
GET    /api/v1/files/download-photos/{id}
```

### Master Data

```
GET    /api/v1/divisions
GET    /api/v1/users
POST   /api/v1/users
PUT    /api/v1/users/{id}
DELETE /api/v1/users/{id}
GET    /api/v1/roles
POST   /api/v1/roles
PUT    /api/v1/roles/{id}
DELETE /api/v1/roles/{id}
GET    /api/v1/permissions
POST   /api/v1/permissions
PUT    /api/v1/permissions/{id}
DELETE /api/v1/permissions/{id}
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

## 🔧 Development Commands

### Development

```bash
# Start development server
php artisan serve

# Run queue worker (for notifications)
php artisan queue:work

# Monitor logs
php artisan pail
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

### Authentication

```javascript
// Login
const response = await fetch("/api/v1/auth/login", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        email: "admin@example.com",
        password: "password",
    }),
});

const { data } = await response.json();
const token = data.token;

// Use token for subsequent requests
const shipments = await fetch("/api/v1/shipments", {
    headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
    },
});
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
