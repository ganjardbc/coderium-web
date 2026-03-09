# Enhanced Classroom System API Documentation

## Overview

This document provides comprehensive API documentation for the Enhanced Classroom System, which extends the existing Laravel classroom system to support both track-based and course-based learning paths while maintaining full backward compatibility.

## Base URL

```
https://your-domain.com/api/v1
```

## Authentication

All protected endpoints require authentication using Laravel's web authentication system. Include the session cookie or CSRF token in your requests.

## Response Format

All API responses follow a consistent JSON format:

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {},
  "errors": {}
}
```

## New Course Management Endpoints

### Course CRUD Operations

#### List Courses

```http
GET /api/v1/courses
```

**Parameters:**
- `is_active` (boolean, optional): Filter by active status
- `search` (string, optional): Search by title or description
- `sort` (string, optional): Sort field (created_at, title, estimated_duration)
- `order` (string, optional): Sort order (asc, desc)
- `per_page` (integer, optional): Items per page (default: 12)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Advanced Laravel Development",
      "description": "Comprehensive course on Laravel framework",
      "slug": "advanced-laravel-development",
      "is_active": true,
      "estimated_duration": 1200,
      "modules_count": 5,
      "enrollments_count": 42,
      "certificate_template": {
        "id": 1,
        "name": "Course Completion Certificate"
      },
      "created_at": "2024-01-15T10:00:00Z",
      "updated_at": "2024-01-15T10:00:00Z"
    }
  ],
  "links": {},
  "meta": {}
}
```

#### Create Course

```http
POST /api/v1/courses
```

**Authorization:** Requires `canManageClassroomContent()` permission

**Request Body:**
```json
{
  "title": "Advanced Laravel Development",
  "description": "Comprehensive course on Laravel framework",
  "slug": "advanced-laravel-development",
  "is_active": true,
  "certificate_template_id": 1,
  "estimated_duration": 1200
}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "title": "Advanced Laravel Development",
    "slug": "advanced-laravel-development",
    "modules": [],
    "certificate_template": null
  }
}
```

#### Get Course Details

```http
GET /api/v1/courses/{slug}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "title": "Advanced Laravel Development",
    "description": "Comprehensive course description",
    "slug": "advanced-laravel-development",
    "is_active": true,
    "estimated_duration": 1200,
    "modules": [
      {
        "id": 1,
        "title": "Laravel Basics",
        "order": 1,
        "is_required": true,
        "lessons": []
      }
    ],
    "enrollment": {
      "id": 1,
      "enrolled_at": "2024-01-15T10:00:00Z",
      "progress_percentage": 45.50
    },
    "progress": {
      "completion_percentage": 45.50,
      "time_spent_minutes": 180,
      "engagement_score": 0.85
    }
  }
}
```

#### Update Course

```http
PUT /api/v1/courses/{slug}
```

**Authorization:** Requires `canManageClassroomContent()` permission

**Request Body:**
```json
{
  "title": "Updated Course Title",
  "description": "Updated description",
  "is_active": false
}
```

#### Delete Course

```http
DELETE /api/v1/courses/{slug}
```

**Authorization:** Requires `canManageClassroomContent()` permission

**Response:**
```json
{
  "message": "Course deleted successfully."
}
```

### Course Module Management

#### Get Course Modules

```http
GET /api/v1/courses/{slug}/modules
```

**Response:**
```json
{
  "course": {
    "id": 1,
    "title": "Advanced Laravel Development",
    "slug": "advanced-laravel-development"
  },
  "modules": [
    {
      "id": 1,
      "title": "Laravel Basics",
      "order": 1,
      "is_required": true,
      "progress": {
        "completion_percentage": 75.0,
        "time_spent_minutes": 120
      }
    }
  ]
}
```

#### Assign Module to Course

```http
POST /api/v1/courses/{slug}/modules
```

**Authorization:** Requires `canManageClassroomContent()` permission

**Request Body:**
```json
{
  "module_id": 1,
  "order": 1,
  "is_required": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "Module assigned to course successfully.",
  "assignment_data": {
    "module_id": 1,
    "course_id": 1,
    "order": 1,
    "is_required": true
  }
}
```

#### Remove Module from Course

```http
DELETE /api/v1/courses/{slug}/modules
```

**Authorization:** Requires `canManageClassroomContent()` permission

**Request Body:**
```json
{
  "module_id": 1
}
```

#### Update Module Order

```http
PUT /api/v1/courses/{slug}/modules/order
```

**Authorization:** Requires `canManageClassroomContent()` permission

**Request Body:**
```json
{
  "module_orders": {
    "1": 0,
    "2": 1,
    "3": 2
  }
}
```

## Course Enrollment Endpoints

### User Enrollment Management

#### Enroll in Course

```http
POST /api/v1/courses/{courseSlug}/enroll
```

**Authentication:** Required

**Response:**
```json
{
  "message": "Successfully enrolled in course.",
  "enrollment": {
    "id": 1,
    "enrolled_at": "2024-01-15T10:00:00Z",
    "progress_percentage": 0.00,
    "completed_at": null
  }
}
```

#### Unenroll from Course

```http
DELETE /api/v1/courses/{courseSlug}/enroll
```

**Authentication:** Required

**Response:**
```json
{
  "message": "Successfully unenrolled from course."
}
```

#### Get Enrollment Status

```http
GET /api/v1/courses/{courseSlug}/enrollment/status
```

**Authentication:** Optional

**Response:**
```json
{
  "enrolled": true,
  "enrollment": {
    "id": 1,
    "enrolled_at": "2024-01-15T10:00:00Z",
    "completed_at": null,
    "progress_percentage": 45.50
  },
  "progress": {
    "completion_percentage": 45.50,
    "time_spent_minutes": 180,
    "engagement_score": 0.85,
    "last_accessed_at": "2024-01-15T14:30:00Z"
  }
}
```

#### Update Enrollment Progress

```http
PUT /api/v1/courses/{courseSlug}/enrollment/progress
```

**Authentication:** Required

**Request Body:**
```json
{
  "progress_percentage": 75.5,
  "time_spent_minutes": 45,
  "engagement_score": 0.9
}
```

**Response:**
```json
{
  "message": "Progress updated successfully.",
  "enrollment": {
    "id": 1,
    "progress_percentage": 75.5,
    "completed_at": null
  },
  "progress": {
    "completion_percentage": 75.5,
    "time_spent_minutes": 225,
    "engagement_score": 0.9
  }
}
```

#### Get User Enrollments

```http
GET /api/v1/user/course-enrollments
```

**Authentication:** Required

**Parameters:**
- `status` (string, optional): Filter by status (active, completed)

**Response:**
```json
{
  "enrollments": [
    {
      "id": 1,
      "course": {
        "id": 1,
        "title": "Advanced Laravel Development",
        "slug": "advanced-laravel-development",
        "description": "Course description",
        "estimated_duration": 1200
      },
      "enrolled_at": "2024-01-15T10:00:00Z",
      "completed_at": null,
      "progress_percentage": 45.50,
      "progress": {
        "completion_percentage": 45.50,
        "time_spent_minutes": 180
      }
    }
  ],
  "stats": {
    "total": 5,
    "active": 3,
    "completed": 2
  }
}
```

### Administrative Enrollment Management

#### Bulk Enroll Users

```http
POST /api/v1/admin/course-enrollments/bulk
```

**Authorization:** Requires `canManageClassroomContent()` permission

**Request Body:**
```json
{
  "enrollments": [
    {
      "user_id": 1,
      "course_id": 1
    },
    {
      "user_id": 2,
      "course_id": 1
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Bulk enrollment completed.",
  "results": {
    "enrolled": 2,
    "skipped": 0,
    "errors": []
  }
}
```

#### Get Enrollment Statistics

```http
GET /api/v1/admin/course-enrollments/statistics
```

**Authorization:** Requires `canManageClassroomContent()` permission

**Response:**
```json
{
  "overall_stats": {
    "total_enrollments": 150,
    "active_enrollments": 120,
    "completed_enrollments": 30,
    "average_progress": 67.5
  },
  "course_stats": [
    {
      "id": 1,
      "title": "Advanced Laravel Development",
      "slug": "advanced-laravel-development",
      "total_enrollments": 42,
      "active_enrollments": 35,
      "completed_enrollments": 7,
      "completion_rate": 16.67
    }
  ]
}
```

## Enhanced Existing Endpoints

### Module Management Updates

The existing module endpoints have been enhanced to support flexible assignments:

#### Assign Module to Level

```http
POST /api/v1/admin/modules/{moduleId}/assign-to-level
```

**Request Body:**
```json
{
  "level_id": 1,
  "order": 1,
  "is_required": true
}
```

#### Remove Module from Level

```http
DELETE /api/v1/admin/modules/{moduleId}/remove-from-level
```

**Request Body:**
```json
{
  "level_id": 1
}
```

### Progress Tracking Updates

Enhanced progress tracking endpoints now support granular metrics:

#### Update Progress

```http
PUT /api/v1/progress/{progressableType}/{progressableId}
```

**Request Body:**
```json
{
  "completion_percentage": 75.5,
  "time_spent_minutes": 45,
  "engagement_score": 0.9,
  "last_accessed_at": "2024-01-15T14:30:00Z"
}
```

### Certificate Management Updates

Certificate endpoints now support polymorphic relationships:

#### Generate Certificate

```http
POST /api/v1/certificates/generate
```

**Request Body:**
```json
{
  "certifiable_type": "App\\Models\\Course",
  "certifiable_id": 1,
  "template_id": 1
}
```

## Error Responses

### Common Error Codes

- `400` - Bad Request: Invalid request parameters
- `401` - Unauthorized: Authentication required
- `403` - Forbidden: Insufficient permissions
- `404` - Not Found: Resource not found
- `422` - Unprocessable Entity: Validation errors
- `500` - Internal Server Error: Server error

### Error Response Format

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "title": ["The title field is required."],
    "slug": ["The slug has already been taken."]
  }
}
```

## Migration Guide for Existing Integrations

### Breaking Changes

**None** - The enhanced system maintains full backward compatibility with existing endpoints.

### New Features Available

1. **Course Management**: New course-based learning paths alongside existing tracks
2. **Enhanced Progress Tracking**: Granular metrics including time spent and engagement scores
3. **Flexible Module Assignments**: Modules can now be reused across multiple levels and courses
4. **Polymorphic Certificates**: Certificates can be earned from both tracks and courses

### Recommended Updates

1. **Update Client Applications**: Add support for course endpoints to provide users with course-based learning options
2. **Enhanced Progress Display**: Utilize new granular progress metrics for better user experience
3. **Certificate Management**: Update certificate displays to handle both track and course certificates

### Backward Compatibility

All existing endpoints continue to work without modification:
- Track management endpoints remain unchanged
- Existing progress tracking continues to function
- Current certificate generation maintains existing behavior
- All existing API response formats are preserved

### Migration Steps for Client Applications

1. **Phase 1**: Update to handle new response fields (optional)
   - Add support for new progress metrics
   - Handle polymorphic certificate data

2. **Phase 2**: Implement course features (optional)
   - Add course listing and detail views
   - Implement course enrollment functionality
   - Add course progress tracking

3. **Phase 3**: Enhanced features (optional)
   - Utilize bulk operations for administrative functions
   - Implement advanced progress analytics
   - Add unified learning path views

## Rate Limiting

API endpoints are subject to rate limiting:
- **Public endpoints**: 60 requests per minute per IP
- **Authenticated endpoints**: 200 requests per minute per user
- **Administrative endpoints**: 100 requests per minute per user

## Pagination

List endpoints support pagination with the following parameters:
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (default: 15, max: 100)

Pagination metadata is included in the response:
```json
{
  "data": [],
  "links": {
    "first": "https://api.example.com/v1/courses?page=1",
    "last": "https://api.example.com/v1/courses?page=10",
    "prev": null,
    "next": "https://api.example.com/v1/courses?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

## Webhooks (Future Enhancement)

The system is designed to support webhooks for real-time notifications:
- Course enrollment events
- Progress milestone achievements
- Certificate generation events
- Module completion events

*Webhook implementation is planned for a future release.*
