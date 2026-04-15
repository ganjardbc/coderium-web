# Admin Module Management System

## Overview

The Admin Module Management system provides a dedicated interface for managing standalone learning modules. These modules are independent content units that can be used across different learning contexts without being tied to specific tracks or levels.

## Features

### Module Index (`/admin/modules`)
- **Comprehensive Module List**: View all standalone modules
- **Advanced Search**: Search by module title or description
- **Status Filtering**: Filter by published/draft status
- **Quick Actions**: Edit, delete, and view module details

### Module Creation (`/admin/modules/create`)
- **Direct Creation**: Single-step module creation process
- **Duration Management**: Set estimated completion time in hours and minutes
- **Media Support**: Upload images and videos for modules
- **Publication Control**: Draft or publish immediately

### Module Editing (`/admin/modules/{id}/edit`)
- **Full Module Details**: Edit all module properties
- **Media Management**: Add, remove, or update module media
- **Status Control**: Publish or unpublish modules

### Module Details (`/admin/modules/{id}`)
- **Module Overview**: Complete module information and statistics
- **Lesson Management**: View and manage lessons within the module
- **Quick Actions**: Direct access to common operations
- **Statistics**: View lesson count, duration, and publication status

## File Structure

```
resources/js/pages/admin/modules/
├── Index.vue          # Module listing and management
├── Create.vue         # Module creation form
├── Edit.vue          # Module editing form
└── Show.vue          # Module details and lesson management

app/Http/Controllers/Admin/
└── ModuleController.php  # Backend controller for module operations
```

## Routes

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/admin/modules` | `admin.modules.index` | List all modules |
| GET | `/admin/modules/create` | `admin.modules.create` | Module creation form |
| POST | `/admin/modules` | `admin.modules.store` | Store new module |
| GET | `/admin/modules/{id}` | `admin.modules.show` | Show module details |
| GET | `/admin/modules/{id}/edit` | `admin.modules.edit` | Edit module form |
| PUT | `/admin/modules/{id}` | `admin.modules.update` | Update module |
| DELETE | `/admin/modules/{id}` | `admin.modules.destroy` | Delete module |

## Navigation

The module management is accessible through:
- **Main Admin Sidebar**: Direct "Modules" link
- **Admin Dashboard**: Quick access card (if implemented)

## Key Features

### Search and Filtering
- **Text Search**: Search across module titles and descriptions
- **Status Filter**: Filter by published/draft status

### Content Management
- **Rich Text Descriptions**: Full formatting support for module descriptions
- **Media Integration**: Support for images and videos
- **Duration Tracking**: Estimated completion times for better planning

### Standalone Architecture
- **Independent Modules**: Modules exist independently without track/level dependencies
- **Flexible Usage**: Can be integrated into various learning contexts
- **Simple Management**: Streamlined creation and editing process

## Usage Examples

### Creating a New Module
1. Navigate to `/admin/modules`
2. Click "Create Module"
3. Fill in module details (title, description, duration)
4. Upload any media files
5. Set publication status
6. Save the module

### Managing Module Content
1. Go to module details page
2. Add lessons using the "Add Lesson" button
3. Manage existing lessons through the lesson table
4. Update module information as needed

## Integration Points

- **Lesson System**: Modules can contain multiple lessons
- **Media Management**: Integrated with the application's media system
- **Student Progress**: Module completion can be tracked independently
- **Analytics**: Module engagement tracked in admin analytics

## Permissions

- **Admin Only**: Full access to all module management features
- **Instructor**: Limited access based on permissions
- **Student**: No access to admin module management

## Technical Notes

- **Standalone Design**: Modules are not tied to tracks or levels
- **Soft Deletes**: Modules are soft-deleted to preserve data integrity
- **Media Handling**: Integrated with the application's media management system
- **Validation**: Comprehensive validation for all module properties
- **Performance**: Optimized queries with proper eager loading and pagination
