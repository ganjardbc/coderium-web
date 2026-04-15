# Admin Courses Feature

## Overview

The Admin Courses feature provides a comprehensive course management system that allows administrators to create, manage, and organize courses with modular content structure.

## Features

### Course Management
- **Create Courses**: Create new courses with title, description, slug, and settings
- **Edit Courses**: Update course information and configuration
- **Course Status**: Activate/deactivate courses for student enrollment
- **Certificate Integration**: Assign certificate templates to courses

### Module Assignment
- **Flexible Module Assignment**: Add any published module to courses
- **Module Ordering**: Define the sequence of modules within a course
- **Required/Optional Modules**: Mark modules as required or optional
- **Module Management**: View, edit, and remove modules from courses

### Course Structure
- **Modular Design**: Courses are built from reusable modules
- **Hierarchical Organization**: Courses → Modules → Lessons → Assessments
- **Progress Tracking**: Built-in support for student progress monitoring

## File Structure

### Frontend Pages
- `resources/js/pages/admin/courses/Index.vue` - Course listing and management
- `resources/js/pages/admin/courses/Form.vue` - Unified course creation and editing form
- `resources/js/pages/admin/courses/Show.vue` - Course details and overview
- `resources/js/pages/admin/courses/Modules.vue` - Module assignment interface

### Backend Controller
- `app/Http/Controllers/Admin/CourseAdminController.php` - Main course management controller

### Routes
All routes are prefixed with `/admin/courses` and require admin authentication:
- `GET /admin/courses` - List all courses
- `GET /admin/courses/create` - Show course creation form (uses Form.vue)
- `POST /admin/courses` - Store new course
- `GET /admin/courses/{course}` - Show course details
- `GET /admin/courses/{course}/edit` - Show course editing form (uses Form.vue)
- `GET /admin/courses/{course}/edit` - Show course edit form
- `PUT /admin/courses/{course}` - Update course
- `DELETE /admin/courses/{course}` - Delete course
- `GET /admin/courses/{course}/modules` - Module management interface
- `POST /admin/courses/{course}/modules` - Assign module to course
- `DELETE /admin/courses/{course}/modules/{module}` - Remove module from course
- `PUT /admin/courses/{course}/modules/{module}/order` - Update module order
- `PUT /admin/courses/{course}/modules/{module}/required` - Update module requirement

## Usage

### Creating a Course
1. Navigate to Admin → Courses
2. Click "Create Course"
3. Fill in course details:
   - Title and description
   - URL slug
   - Estimated duration
   - Certificate template (optional)
   - Active status
4. Save the course

### Adding Modules to a Course
1. Go to the course details page
2. Click "Manage Modules" or navigate to the modules tab
3. Select modules from the available list
4. Configure module order and requirement status
5. Save the module assignments

### Managing Course Content
- **View Course**: See course overview with module assignments and statistics
- **Edit Course**: Update course information and settings
- **Module Management**: Add, remove, and reorder modules
- **Progress Monitoring**: View enrollment and completion statistics

## Integration

### Database Schema
The courses feature uses the existing `courses` table and `course_modules` pivot table for module assignments.

### Relationships
- **Course ↔ Modules**: Many-to-many relationship with ordering and requirement flags
- **Course → Enrollments**: One-to-many for student enrollments
- **Course → Certificates**: Polymorphic relationship for certificate generation
- **Course → Certificate Templates**: Belongs-to relationship for template assignment

### API Resources
Uses the existing `CourseResource` for consistent API responses across the application.

## Security

- All routes require authentication and admin role (`role:admin_only` middleware)
- Course deletion is protected against courses with existing enrollments
- Module assignments validate module existence and publication status

## Future Enhancements

- Drag-and-drop module reordering
- Bulk module assignment
- Course templates
- Advanced progress analytics
- Course prerequisites
- Automated certificate generation
