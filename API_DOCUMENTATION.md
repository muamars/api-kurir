# Courier Tracking API Documentation

## Base URL

```
http://localhost:8000/api/v1
```

## Authentication

All protected endpoints require Bearer token in Authorization header:

```
Authorization: Bearer {token}
```

## Endpoints

### Authentication

#### Login

```http
POST /auth/login
Content-Type: application/json

{
    "email": "admin@example.com",
    "password": "password"
}
```

#### Logout

```http
POST /auth/logout
Authorization: Bearer {token}
```

#### Get Current User

```http
GET /auth/me
Authorization: Bearer {token}
```

### Master Data

#### Get Divisions

```http
GET /divisions
Authorization: Bearer {token}
```

#### Get Drivers

```http
GET /drivers
Authorization: Bearer {token}
```

#### Get Users

```http
GET /users?division_id=1&role=User
Authorization: Bearer {token}
```

### Shipments

#### Get Shipments

```http
GET /shipments?status=pending&priority=urgent&driver_id=2
Authorization: Bearer {token}
```

#### Create Shipment

```http
POST /shipments
Authorization: Bearer {token}
Content-Type: application/json

{
    "notes": "Urgent delivery needed",
    "priority": "urgent",
    "deadline": "2025-09-01",
    "destinations": [
        {
            "receiver_name": "John Doe",
            "delivery_address": "Jl. Sudirman No. 123, Jakarta",
            "shipment_note": "Call before delivery"
        },
        {
            "receiver_name": "Jane Smith",
            "delivery_address": "Jl. Thamrin No. 456, Jakarta",
            "shipment_note": "Office hours only"
        }
    ],
    "items": [
        {
            "item_name": "Documents",
            "quantity": 1,
            "description": "Important contracts"
        },
        {
            "item_name": "Package",
            "quantity": 2,
            "description": "Product samples"
        }
    ]
}
```

#### Get Shipment Detail

```http
GET /shipments/{id}
Authorization: Bearer {token}
```

#### Approve Shipment (Admin only)

```http
POST /shipments/{id}/approve
Authorization: Bearer {token}
```

#### Assign Driver (Admin only)

```http
POST /shipments/{id}/assign-driver
Authorization: Bearer {token}
Content-Type: application/json

{
    "driver_id": 2
}
```

#### Start Delivery (Driver only)

```http
POST /shipments/{id}/start-delivery
Authorization: Bearer {token}
```

### Progress Tracking

#### Update Progress (Driver only)

```http
POST /shipments/{shipment_id}/destinations/{destination_id}/progress
Authorization: Bearer {token}
Content-Type: multipart/form-data

status: delivered
photo: [file]
note: Successfully delivered
receiver_name: John Doe
received_photo: [file]
```

#### Get Shipment Progress

```http
GET /shipments/{id}/progress
Authorization: Bearer {token}
```

#### Get Driver History

```http
GET /driver/history
Authorization: Bearer {token}
```

## Sample Users

### Admin

-   Email: admin@example.com
-   Password: password
-   Permissions: Full access

### Kurir

-   Email: kurir@example.com
-   Password: password
-   Permissions: View shipments, update progress

### User

-   Email: user@example.com
-   Password: password
-   Permissions: Create shipments, view own shipments

## Status Flow

### Shipment Status

1. `pending` - Created by user, waiting for approval
2. `approved` - Approved by admin
3. `assigned` - Driver assigned by admin
4. `in_progress` - Driver started delivery
5. `completed` - All destinations completed
6. `cancelled` - Cancelled by admin

### Destination Status

1. `pending` - Not yet visited
2. `in_progress` - Driver en route
3. `completed` - Successfully delivered
4. `failed` - Delivery failed

### Progress Status

1. `arrived` - Driver arrived at location
2. `delivered` - Successfully delivered
3. `failed` - Delivery failed

## Dashboard & Analytics

### Get Dashboard Stats

```http
GET /dashboard
Authorization: Bearer {token}
```

### Get Chart Data

```http
GET /dashboard/chart?period=week
Authorization: Bearer {token}
```

## Notifications

### Get Notifications

```http
GET /notifications?unread_only=true&type=shipment_created
Authorization: Bearer {token}
```

### Get Unread Count

```http
GET /notifications/unread-count
Authorization: Bearer {token}
```

### Mark Notification as Read

```http
POST /notifications/{notification_id}/read
Authorization: Bearer {token}
```

### Mark All as Read

```http
POST /notifications/mark-all-read
Authorization: Bearer {token}
```

### Delete Notification

```http
DELETE /notifications/{notification_id}
Authorization: Bearer {token}
```

## File Management

### Upload SPJ Document

```http
POST /shipments/{id}/upload-spj
Authorization: Bearer {token}
Content-Type: multipart/form-data

spj_file: [file]
```

### Download SPJ Document

```http
GET /shipments/{id}/download-spj
Authorization: Bearer {token}
```

### Download All Photos (ZIP)

```http
GET /shipments/{id}/download-photos
Authorization: Bearer {token}
```

### Get File Information

```http
GET /shipments/{id}/files
Authorization: Bearer {token}
```

## Enhanced Search & Filter

### Advanced Shipment Search

```http
GET /shipments?search=SPJ-20250829&status[]=pending&status[]=approved&date_from=2025-08-01&date_to=2025-08-31&sort_by=priority&sort_order=asc&per_page=20
Authorization: Bearer {token}
```

Available filters:

-   `search`: Search in shipment ID, notes, receiver names, addresses
-   `status[]`: Multiple status filter (pending, approved, assigned, in_progress, completed, cancelled)
-   `priority`: urgent or regular
-   `date_from` & `date_to`: Date range filter
-   `division_id`: Filter by creator's division
-   `created_by`: Filter by specific creator
-   `driver_id`: Filter by assigned driver
-   `sort_by`: created_at, updated_at, priority, status
-   `sort_order`: asc or desc
-   `per_page`: Results per page (default: 15)

## Notification Types

-   `shipment_created`: New shipment request (sent to Admins)
-   `shipment_approved`: Shipment approved (sent to Creator)
-   `shipment_assigned`: Driver assigned (sent to Driver & Creator)
-   `delivery_started`: Delivery started (sent to Creator)
-   `destination_delivered`: Destination delivered (sent to Creator)
-   `delivery_completed`: All destinations completed (sent to Creator & Admins)

## Dashboard Data Structure

### Admin Dashboard

-   Pending approvals count
-   Unassigned shipments count
-   Active drivers count
-   Recent shipments list

### Driver Dashboard

-   Assigned shipments today
-   In-progress deliveries
-   Completed deliveries today
-   Pending destinations count

### User Dashboard

-   My total shipments
-   Pending approval count
-   In-delivery count
-   Completed this month

## Role & Permission Management

### Roles

#### Get All Roles

```http
GET /api/v1/roles
Authorization: Bearer {token}
```

**Response:**

```json
{
    "data": [
        {
            "id": 1,
            "name": "Admin",
            "guard_name": "web",
            "permissions": [
                {
                    "id": 1,
                    "name": "view-dashboard",
                    "guard_name": "web"
                }
            ]
        }
    ]
}
```

#### Create Role

```http
POST /api/v1/roles
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Manager",
  "permissions": ["view-dashboard", "manage-shipments"]
}
```

#### Get Role Details

```http
GET /api/v1/roles/{id}
Authorization: Bearer {token}
```

#### Update Role

```http
PUT /api/v1/roles/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Senior Manager",
  "permissions": ["view-dashboard", "manage-shipments", "approve-shipments"]
}
```

#### Delete Role

```http
DELETE /api/v1/roles/{id}
Authorization: Bearer {token}
```

#### Assign Permissions to Role

```http
POST /api/v1/roles/{id}/assign-permissions
Authorization: Bearer {token}
Content-Type: application/json

{
  "permissions": ["view-dashboard", "manage-shipments"]
}
```

#### Remove Permissions from Role

```http
POST /api/v1/roles/{id}/remove-permissions
Authorization: Bearer {token}
Content-Type: application/json

{
  "permissions": ["manage-shipments"]
}
```

### Permissions

#### Get All Permissions

```http
GET /api/v1/permissions
Authorization: Bearer {token}
```

#### Get Permissions Grouped by Category

```http
GET /api/v1/permissions-grouped
Authorization: Bearer {token}
```

**Response:**

```json
{
    "data": {
        "view": [
            {
                "id": 1,
                "name": "view-dashboard",
                "guard_name": "web"
            }
        ],
        "manage": [
            {
                "id": 2,
                "name": "manage-shipments",
                "guard_name": "web"
            }
        ]
    }
}
```

#### Create Permission

```http
POST /api/v1/permissions
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "export-reports",
  "guard_name": "web"
}
```

#### Get Permission Details

```http
GET /api/v1/permissions/{id}
Authorization: Bearer {token}
```

#### Update Permission

```http
PUT /api/v1/permissions/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "export-all-reports",
  "guard_name": "web"
}
```

#### Delete Permission

```http
DELETE /api/v1/permissions/{id}
Authorization: Bearer {token}
```

### Available Permissions

| Permission          | Description                  |
| ------------------- | ---------------------------- |
| `view-dashboard`    | Access to dashboard          |
| `manage-shipments`  | Create and manage shipments  |
| `approve-shipments` | Approve pending shipments    |
| `assign-drivers`    | Assign drivers to shipments  |
| `update-progress`   | Update delivery progress     |
| `manage-users`      | Manage user accounts         |
| `manage-roles`      | Manage roles and permissions |

### Default Roles

| Role      | Permissions                          |
| --------- | ------------------------------------ |
| **Admin** | All permissions                      |
| **Kurir** | `view-dashboard`, `update-progress`  |
| **User**  | `view-dashboard`, `manage-shipments` |

**Note:** Only users with Admin role can access role and permission management endpoints.
