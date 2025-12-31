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

### Get Comprehensive Shipment Chart Data

```http
GET /dashboard/shipment-chart?chart_type=daily&date_from=2024-12-01&date_to=2024-12-31
Authorization: Bearer {token}
```

**Parameters:**
- `chart_type` (required): `daily`, `monthly`, `yearly`, `category`, `vehicle_type`, `customer`, `status`, `priority`, `total`
- `date_from` (optional): Start date (YYYY-MM-DD) - Custom date range
- `date_to` (optional): End date (YYYY-MM-DD) - Custom date range  
- `period` (optional): `day`, `week`, `month`, `year` - Predefined time periods
- `category_id` (optional): Filter by specific category ID
- `vehicle_type_id` (optional): Filter by specific vehicle type ID
- `year` (optional): Specific year for monthly chart (default: current year)
- `start_year` (optional): Start year for yearly chart (default: current year - 4)
- `end_year` (optional): End year for yearly chart (default: current year)

**Chart Types & Examples:**

#### 1. **Daily Chart** - Shipments per day
```http
# Daily shipments in December 2024
GET /dashboard/shipment-chart?chart_type=daily&date_from=2024-12-01&date_to=2024-12-31

# Daily shipments this week
GET /dashboard/shipment-chart?chart_type=daily&period=week
```

**Response:**
```json
{
  "data": [
    {
      "date": "2024-12-01",
      "day_name": "Sunday",
      "count": 15,
      "formatted_date": "01 Dec 2024"
    },
    {
      "date": "2024-12-02",
      "day_name": "Monday", 
      "count": 25,
      "formatted_date": "02 Dec 2024"
    }
  ],
  "meta": {
    "chart_type": "daily",
    "period": "custom",
    "date_range": {
      "from": "2024-12-01",
      "to": "2024-12-31"
    }
  }
}
```

#### 2. **Monthly Chart** - Shipments per month
```http
# Monthly shipments in 2024
GET /dashboard/shipment-chart?chart_type=monthly&year=2024

# Monthly shipments this year
GET /dashboard/shipment-chart?chart_type=monthly
```

**Response:**
```json
{
  "data": [
    {
      "month": 1,
      "month_name": "January",
      "month_short": "Jan",
      "count": 120,
      "year": 2024
    },
    {
      "month": 2,
      "month_name": "February",
      "month_short": "Feb", 
      "count": 95,
      "year": 2024
    }
  ]
}
```

#### 3. **Yearly Chart** - Shipments per year
```http
# Yearly shipments from 2020-2024
GET /dashboard/shipment-chart?chart_type=yearly&start_year=2020&end_year=2024
```

**Response:**
```json
{
  "data": [
    {
      "year": 2020,
      "count": 850
    },
    {
      "year": 2021,
      "count": 1200
    },
    {
      "year": 2024,
      "count": 1800
    }
  ]
}
```

#### 4. **Category Chart** - Distribution by shipment category
```http
# Category distribution this month
GET /dashboard/shipment-chart?chart_type=category&period=month

# Category distribution with date range
GET /dashboard/shipment-chart?chart_type=category&date_from=2024-01-01&date_to=2024-12-31
```

**Response:**
```json
{
  "data": [
    {
      "category_id": 1,
      "category_name": "Dokumen",
      "count": 150,
      "percentage": 60.0
    },
    {
      "category_id": 2,
      "category_name": "Paket",
      "count": 100,
      "percentage": 40.0
    }
  ]
}
```

#### 5. **Vehicle Type Chart** - Distribution by vehicle type
```http
# Vehicle type distribution this year
GET /dashboard/shipment-chart?chart_type=vehicle_type&period=year

# Vehicle type for specific category
GET /dashboard/shipment-chart?chart_type=vehicle_type&category_id=1
```

**Response:**
```json
{
  "data": [
    {
      "vehicle_type_id": 1,
      "vehicle_type_name": "Motor",
      "count": 180,
      "percentage": 72.0
    },
    {
      "vehicle_type_id": 2,
      "vehicle_type_name": "Mobil",
      "count": 70,
      "percentage": 28.0
    }
  ]
}
```

#### 6. **Customer Chart** - Top customers by shipment count
```http
# Top 20 customers this month
GET /dashboard/shipment-chart?chart_type=customer&period=month

# Top customers in date range
GET /dashboard/shipment-chart?chart_type=customer&date_from=2024-01-01&date_to=2024-12-31
```

**Response:**
```json
{
  "data": [
    {
      "customer_id": 5,
      "customer_name": "PT ABC Corp",
      "division_name": "Marketing",
      "count": 45,
      "percentage": 18.0
    },
    {
      "customer_id": 8,
      "customer_name": "CV XYZ",
      "division_name": "Sales",
      "count": 30,
      "percentage": 12.0
    }
  ]
}
```

#### 7. **Status Chart** - Distribution by shipment status
```http
# Status distribution this month
GET /dashboard/shipment-chart?chart_type=status&period=month
```

**Response:**
```json
{
  "data": [
    {
      "status": "completed",
      "status_label": "Selesai",
      "count": 120,
      "percentage": 48.0
    },
    {
      "status": "in_progress",
      "status_label": "Dalam Perjalanan",
      "count": 80,
      "percentage": 32.0
    },
    {
      "status": "pending",
      "status_label": "Menunggu Persetujuan",
      "count": 50,
      "percentage": 20.0
    }
  ]
}
```

#### 8. **Priority Chart** - Distribution by priority level
```http
# Priority distribution this year
GET /dashboard/shipment-chart?chart_type=priority&period=year
```

**Response:**
```json
{
  "data": [
    {
      "priority": "regular",
      "priority_label": "Regular",
      "count": 200,
      "percentage": 80.0
    },
    {
      "priority": "urgent",
      "priority_label": "Urgent",
      "count": 50,
      "percentage": 20.0
    }
  ]
}
```

#### 9. **Total Shipments** - Comprehensive shipment summary
```http
# Total shipments summary this month
GET /dashboard/shipment-chart?chart_type=total&period=month

# Total shipments in date range
GET /dashboard/shipment-chart?chart_type=total&date_from=2024-01-01&date_to=2024-12-31
```

**Response:**
```json
{
  "data": {
    "total_shipments": 250,
    "summary": {
      "completed": 120,
      "in_progress": 80,
      "pending": 40,
      "cancelled": 10,
      "urgent": 50,
      "regular": 200
    },
    "status_breakdown": {
      "created": {
        "count": 15,
        "label": "Dibuat",
        "percentage": 6.0
      },
      "pending": {
        "count": 10,
        "label": "Menunggu Persetujuan", 
        "percentage": 4.0
      },
      "assigned": {
        "count": 15,
        "label": "Ditugaskan",
        "percentage": 6.0
      },
      "in_progress": {
        "count": 80,
        "label": "Dalam Perjalanan",
        "percentage": 32.0
      },
      "completed": {
        "count": 120,
        "label": "Selesai",
        "percentage": 48.0
      },
      "cancelled": {
        "count": 10,
        "label": "Dibatalkan",
        "percentage": 4.0
      }
    },
    "priority_breakdown": {
      "urgent": {
        "count": 50,
        "label": "Urgent",
        "percentage": 20.0
      },
      "regular": {
        "count": 200,
        "label": "Regular",
        "percentage": 80.0
      }
    },
    "top_categories": [
      {
        "category_name": "Dokumen",
        "count": 150,
        "percentage": 60.0
      },
      {
        "category_name": "Paket",
        "count": 100,
        "percentage": 40.0
      }
    ],
    "vehicle_types": [
      {
        "vehicle_name": "Motor",
        "count": 180,
        "percentage": 72.0
      },
      {
        "vehicle_name": "Mobil",
        "count": 70,
        "percentage": 28.0
      }
    ],
    "time_breakdown": {
      "today": 25,
      "this_week": 85,
      "this_month": 250,
      "this_year": 1200
    },
    "completion_rate": 48.0
  }
}
```

**Features:**
- **Multi-dimensional analysis**: Time-based, category, vehicle, customer, status, priority
- **Flexible filtering**: Date ranges, periods, category/vehicle filters
- **Role-based data**: Admin sees all, Kurir sees assigned, User sees created shipments
- **Percentage calculations**: Perfect for pie charts and analytics
- **Top performers**: Customer ranking with division info
- **Complete metadata**: Chart type, applied filters, date ranges
- **Dashboard ready**: Optimized for various chart visualizations

### Get Shipment Categories Report

```http
GET /shipment-categories-report?period=month&category_id=1
Authorization: Bearer {token}
```

**Parameters:**
- `period` (optional): `day`, `week`, `month`, `year` - Predefined time periods
- `date_from` (optional): Start date (YYYY-MM-DD) - Custom date range
- `date_to` (optional): End date (YYYY-MM-DD) - Custom date range  
- `category_id` (optional): Filter by specific category ID

**Examples:**

```http
# Get today's category report
GET /shipment-categories-report?period=day

# Get this month's category report
GET /shipment-categories-report?period=month

# Get custom date range report
GET /shipment-categories-report?date_from=2024-01-01&date_to=2024-12-31

# Get specific category report for this year
GET /shipment-categories-report?period=year&category_id=1
```

**Response:**

```json
{
  "data": {
    "category_reports": [
      {
        "category": {
          "id": 1,
          "name": "Dokumen",
          "description": "Pengiriman dokumen penting"
        },
        "statistics": {
          "total_shipments": 25,
          "completed": 20,
          "in_progress": 3,
          "pending": 2,
          "cancelled": 0,
          "urgent_priority": 5,
          "completion_rate": 80.0,
          "percentage_of_total": 50.0
        },
        "status_breakdown": {
          "created": 1,
          "pending": 1,
          "assigned": 0,
          "in_progress": 3,
          "completed": 20,
          "cancelled": 0
        }
      },
      {
        "category": {
          "id": 2,
          "name": "Paket",
          "description": "Pengiriman paket barang"
        },
        "statistics": {
          "total_shipments": 15,
          "completed": 12,
          "in_progress": 2,
          "pending": 1,
          "cancelled": 0,
          "urgent_priority": 3,
          "completion_rate": 80.0,
          "percentage_of_total": 30.0
        },
        "status_breakdown": {
          "created": 0,
          "pending": 1,
          "assigned": 0,
          "in_progress": 2,
          "completed": 12,
          "cancelled": 0
        }
      }
    ],
    "overall_stats": {
      "total_shipments": 50,
      "total_categories_used": 3,
      "most_used_category": "Dokumen",
      "period_summary": {
        "from": "2024-12-01",
        "to": "2024-12-31",
        "period_type": "month"
      }
    }
  }
}
```

**Features:**
- **Role-based filtering**: Admin sees all data, Kurir sees assigned shipments, User sees created shipments
- **Flexible time filtering**: Predefined periods or custom date ranges
- **Category analytics**: Completion rates, status breakdown, priority analysis
- **Performance metrics**: Most used categories, percentage distribution
- **Dashboard integration**: Perfect for category performance widgets

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
