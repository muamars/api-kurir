<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DivisionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\ShipmentPhotoController;
use App\Http\Controllers\Api\ShipmentProgressController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

// Authentication Routes (Public - tidak perlu token)
Route::post('/login', [AuthController::class, 'apiLogin']);

// Courier Tracking API Authentication
Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [ApiAuthController::class, 'login']);

    // Test token generation (development only)
    Route::post('/auth/generate-test-token', [ApiAuthController::class, 'generateTestToken']);
});

// Protected Routes (Perlu token)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'apiLogout']);
    Route::get('/user', [AuthController::class, 'apiUser']);

    // Blog API Routes
    Route::middleware('permission:view blogs')->group(function () {
        Route::get('/blogs', [BlogController::class, 'apiIndex']);
        Route::get('/blogs/{blog}', [BlogController::class, 'apiShow']);
    });

    Route::middleware('permission:create blogs')->group(function () {
        Route::post('/blogs', [BlogController::class, 'apiStore']);
    });

    Route::middleware('permission:edit blogs')->group(function () {
        Route::put('/blogs/{blog}', [BlogController::class, 'apiUpdate']);
    });

    Route::middleware('permission:delete blogs')->group(function () {
        Route::delete('/blogs/{blog}', [BlogController::class, 'apiDestroy']);
    });

    // Project API Routes
    Route::middleware('permission:view projects')->group(function () {
        Route::get('/projects', [ProjectController::class, 'apiIndex']);
        Route::get('/projects/{project}', [ProjectController::class, 'apiShow']);
    });

    Route::middleware('permission:create projects')->group(function () {
        Route::post('/projects', [ProjectController::class, 'apiStore']);
    });

    Route::middleware('permission:edit projects')->group(function () {
        Route::put('/projects/{project}', [ProjectController::class, 'apiUpdate']);
    });

    Route::middleware('permission:delete projects')->group(function () {
        Route::delete('/projects/{project}', [ProjectController::class, 'apiDestroy']);
    });

    // Courier Tracking API Routes
    Route::prefix('v1')->group(function () {
        Route::post('/auth/logout', [ApiAuthController::class, 'logout']);
        Route::get('/auth/me', [ApiAuthController::class, 'me']);

        // Shipment Routes
        Route::get('/shipments', [ShipmentController::class, 'index']);
        Route::post('/shipments', [ShipmentController::class, 'store']);
        Route::get('/shipments/{shipment}', [ShipmentController::class, 'show']);
        Route::put('/shipments/{shipment}', [ShipmentController::class, 'update']);

        // Admin/Manager actions
        Route::post('/shipments/{shipment}/approve', [ShipmentController::class, 'approve']);
        Route::post('/shipments/{shipment}/assign-driver', [ShipmentController::class, 'assignDriver']);
        Route::post('/shipments/bulk-assign-driver', [ShipmentController::class, 'bulkAssignDriver']);
        Route::get('/shipments/bulk-assignments', [ShipmentController::class, 'getBulkAssignmentHistory']);
        Route::get('/shipments/bulk-assignments/{bulkAssignmentId}', [ShipmentController::class, 'getBulkAssignmentDetail']);
        Route::post('/shipments/{shipment}/pending', [ShipmentController::class, 'pending']);
        Route::post('/shipments/{shipment}/cancel', [ShipmentController::class, 'cancel']);

        // Driver actions
        Route::post('/shipments/{shipment}/start-delivery', [ShipmentController::class, 'startDelivery']);

        // Progress tracking
        Route::post('/shipments/{shipment}/destinations/{destination}/progress', [ShipmentProgressController::class, 'updateProgress']);
        Route::get('/shipments/{shipment}/progress', [ShipmentProgressController::class, 'getProgress']);
        Route::get('/shipments/{shipment}/destinations/{destination}/status-history', [ShipmentProgressController::class, 'getDestinationStatusHistory']);
        Route::get('/shipments/{shipment}/destinations/{destination}/ordered-history', [ShipmentProgressController::class, 'getOrderedStatusHistory']);
        Route::get('/shipments/{shipment}/destinations/{destination}/status-duration', [ShipmentProgressController::class, 'getStatusDuration']);
        Route::get('/shipments/{shipment}/destinations/{destination}/all-durations', [ShipmentProgressController::class, 'getAllStatusDurations']);
        Route::get('/driver/history', [ShipmentProgressController::class, 'getDriverHistory']);
        Route::get('/driver/performance-report', [ShipmentProgressController::class, 'getDriverPerformanceReport']);
        Route::get('/driver/route-report', [ShipmentProgressController::class, 'getDriverRouteReport']);

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/chart', [DashboardController::class, 'getChartData']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

        // Shipment photos
        Route::get('/shipments/{shipment}/photos', [ShipmentPhotoController::class, 'index']);
        Route::post('/shipments/{shipment}/photos/admin', [ShipmentPhotoController::class, 'uploadAdminPhotos']);
        Route::post('/shipments/{shipment}/photos/pickup', [ShipmentPhotoController::class, 'uploadPickupPhoto']);
        Route::post('/shipments/{shipment}/photos/delivery', [ShipmentPhotoController::class, 'uploadDeliveryPhoto']);
        Route::delete('/shipments/{shipment}/photos/{photo}', [ShipmentPhotoController::class, 'destroy']);

        // Master data
        Route::get('/divisions', [DivisionController::class, 'index']);
        Route::get('/drivers', [UserController::class, 'getDrivers']);
        Route::get('/users', [UserController::class, 'getUsers']);

        // Customer Management (all authenticated users can view)
        Route::get('/customers', [\App\Http\Controllers\Api\CustomerController::class, 'index']);
        Route::get('/customers/{customer}', [\App\Http\Controllers\Api\CustomerController::class, 'show']);

        // Kurir self-service status toggle
        Route::post('/my-status/toggle', [UserController::class, 'toggleMyStatus']);
        Route::get('/my-status', [UserController::class, 'getMyStatus']);

        // Shipment Categories (all users can view)
        Route::get('/shipment-categories', [\App\Http\Controllers\Api\ShipmentCategoryController::class, 'index']);
        Route::get('/shipment-categories/{shipmentCategory}', [\App\Http\Controllers\Api\ShipmentCategoryController::class, 'show']);

        // Vehicle Types (all users can view)
        Route::get('/vehicle-types', [\App\Http\Controllers\Api\VehicleTypeController::class, 'index']);
        Route::get('/vehicle-types/{vehicleType}', [\App\Http\Controllers\Api\VehicleTypeController::class, 'show']);

        // Role & Permission Management Routes (Admin only)
        Route::middleware('role:Admin')->group(function () {
            // User Management
            Route::get('/users/{user}', [UserController::class, 'show']);
            Route::apiResource('users', UserController::class)->except(['index', 'show']);
            Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive']);

            // Roles
            Route::apiResource('roles', RoleController::class);
            Route::post('/roles/{role}/assign-permissions', [RoleController::class, 'assignPermissions']);
            Route::post('/roles/{role}/remove-permissions', [RoleController::class, 'removePermissions']);

            // Permissions
            Route::apiResource('permissions', PermissionController::class);
            Route::get('/permissions-grouped', [PermissionController::class, 'getByGroup']);

            // Shipment Categories Management (Admin only)
            Route::post('/shipment-categories', [\App\Http\Controllers\Api\ShipmentCategoryController::class, 'store']);
            Route::put('/shipment-categories/{shipmentCategory}', [\App\Http\Controllers\Api\ShipmentCategoryController::class, 'update']);
            Route::delete('/shipment-categories/{shipmentCategory}', [\App\Http\Controllers\Api\ShipmentCategoryController::class, 'destroy']);

            // Vehicle Types Management (Admin only)
            Route::post('/vehicle-types', [\App\Http\Controllers\Api\VehicleTypeController::class, 'store']);
            Route::put('/vehicle-types/{vehicleType}', [\App\Http\Controllers\Api\VehicleTypeController::class, 'update']);
            Route::delete('/vehicle-types/{vehicleType}', [\App\Http\Controllers\Api\VehicleTypeController::class, 'destroy']);

            // Customer Management (Admin only for create/update/delete)
            Route::post('/customers', [\App\Http\Controllers\Api\CustomerController::class, 'store']);
            Route::put('/customers/{customer}', [\App\Http\Controllers\Api\CustomerController::class, 'update']);
            Route::delete('/customers/{customer}', [\App\Http\Controllers\Api\CustomerController::class, 'destroy']);
        });
    });
});
