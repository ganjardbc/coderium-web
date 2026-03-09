# Standalone Content Management System

## Overview

The Standalone Content Management System provides a unified approach to managing educational content through three main components: **Modules**, **Lessons**, and **Assessments**. This system is designed to be flexible, allowing content to exist independently or be connected as needed.

## System Architecture

### Content Hierarchy
```
Modules (Standalone containers)
├── Lessons (Can be assigned to modules or standalone)
└── Assessments (Can be attached to modules or standalone)
```

### Key Principles
- **Flexibility**: Content can exist independently or be connected
- **Modularity**: Each component serves a specific purpose
- **Scalability**: Easy to extend and modify
- **User-Friendly**: Intuitive interfaces for content creators

## Components

### 1. Modules (`/admin/modules`)

**Purpose**: Standalone learning containers that group related content.

**Features**:
- Independent content units
- Rich text descriptions
- Media support (images, videos)
- Duration estimation
- Publication control
- Lesson management integration

**File Structure**:
```
resources/js/pages/admin/modules/
├── Index.vue    # Module listing and management
├── Create.vue   # Module creation
├── Edit.vue     # Module editing
└── Show.vue     # Module details with lesson management
```

**Key Capabilities**:
- Create standalone modules without track/level dependencies
- Attach lessons and assessments
- Media management
- Publication workflow
- Search and filtering

### 2. Lessons (`/admin/lessons`)

**Purpose**: Individual learning content units that can be standalone or part of modules.

**Features**:
- Rich text content with WYSIWYG editor
- Multiple lesson types (text, video, interactive)
- Media attachments
- Duration estimation
- Module assignment (optional)
- Publication control

**File Structure**:
```
resources/js/pages/admin/lessons/
├── Index.vue    # Lesson listing and management
├── Create.vue   # Lesson creation
├── Edit.vue     # Lesson editing
└── Show.vue     # Lesson details and content
```

**Lesson Types**:
- **Text**: Traditional text-based content
- **Video**: Video-focused lessons
- **Interactive**: Interactive content and exercises

**Key Capabilities**:
- Create lessons with or without module assignment
- Rich content editing
- Media management
- Flexible organization
- Search and filtering by type, status, and module

### 3. Assessments (`/admin/assessments`)

**Purpose**: Quizzes and tests that can be standalone or attached to modules.

**Features**:
- Multiple question types
- Flexible scoring system
- Time limits and attempt controls
- Module attachment (optional)
- Detailed question management

**File Structure**:
```
resources/js/pages/admin/assessments/
├── Index.vue    # Assessment listing and management
├── Create.vue   # Assessment creation with question builder
├── Edit.vue     # Assessment editing
└── Show.vue     # Assessment details and analytics
```

**Question Types**:
- **Multiple Choice**: Traditional multiple choice questions
- **True/False**: Binary choice questions
- **Code Output**: Programming-related questions
- **Conceptual**: Open-ended conceptual questions

**Key Capabilities**:
- Interactive question builder
- Flexible scoring and timing
- Module integration
- Attempt tracking
- Performance analytics

## Navigation Structure

The system is organized in the admin sidebar under a unified "Modules" section:

```
Modules
├── Overview (/admin/modules)
├── Lessons (/admin/lessons)
└── Assessments (/admin/assessments)
```

## Routes

### Module Routes
| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/admin/modules` | `admin.modules.index` | List all modules |
| GET | `/admin/modules/create` | `admin.modules.create` | Create module form |
| POST | `/admin/modules` | `admin.modules.store` | Store new module |
| GET | `/admin/modules/{id}` | `admin.modules.show` | Show module details |
| GET | `/admin/modules/{id}/edit` | `admin.modules.edit` | Edit module form |
| PUT | `/admin/modules/{id}` | `admin.modules.update` | Update module |
| DELETE | `/admin/modules/{id}` | `admin.modules.destroy` | Delete module |

### Lesson Routes
| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/admin/lessons` | `admin.lessons.index` | List all lessons |
| GET | `/admin/lessons/create` | `admin.lessons.create` | Create lesson form |
| POST | `/admin/lessons` | `admin.lessons.store` | Store new lesson |
| GET | `/admin/lessons/{id}` | `admin.lessons.show` | Show lesson details |
| GET | `/admin/lessons/{id}/edit` | `admin.lessons.edit` | Edit lesson form |
| PUT | `/admin/lessons/{id}` | `admin.lessons.update` | Update lesson |
| DELETE | `/admin/lessons/{id}` | `admin.lessons.destroy` | Delete lesson |

### Assessment Routes
| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/admin/assessments` | `admin.assessments.index` | List all assessments |
| GET | `/admin/assessments/create` | `admin.assessments.create` | Create assessment form |
| POST | `/admin/assessments` | `admin.assessments.store` | Store new assessment |
| GET | `/admin/assessments/{id}` | `admin.assessments.show` | Show assessment details |
| GET | `/admin/assessments/{id}/edit` | `admin.assessments.edit` | Edit assessment form |
| PUT | `/admin/assessments/{id}` | `admin.assessments.update` | Update assessment |
| DELETE | `/admin/assessments/{id}` | `admin.assessments.destroy` | Delete assessment |

## Database Relationships

### Module Model
```php
// Relationships
public function lessons() // hasMany
public function assessments() // morphMany (polymorphic)
public function media() // morphToMany (polymorphic)
```

### Lesson Model
```php
// Relationships
public function module() // belongsTo (nullable)
public function media() // morphToMany (polymorphic)
```

### Assessment Model
```php
// Relationships
public function assessable() // morphTo (polymorphic - can be Module or null)
public function questions() // hasMany
public function attempts() // hasMany
```

## Key Features

### Content Flexibility
- **Standalone Content**: All content types can exist independently
- **Optional Relationships**: Lessons and assessments can optionally be attached to modules
- **Easy Migration**: Content can be moved between modules or made standalone

### Rich Content Management
- **WYSIWYG Editing**: Rich text editor for lesson content
- **Media Support**: Upload and manage images, videos, and documents
- **Content Types**: Different lesson types for varied learning experiences

### Assessment Builder
- **Interactive Creation**: Drag-and-drop question builder
- **Multiple Question Types**: Support for various assessment formats
- **Flexible Scoring**: Customizable points and passing scores
- **Time Management**: Optional time limits and attempt controls

### Search and Filtering
- **Advanced Search**: Search across titles, descriptions, and content
- **Smart Filtering**: Filter by type, status, module assignment
- **Quick Actions**: Easy access to common operations

### Publication Workflow
- **Draft/Published States**: Control content visibility
- **Bulk Operations**: Manage multiple items simultaneously
- **Status Tracking**: Clear indication of publication status

## Usage Examples

### Creating a Complete Learning Module
1. **Create Module**: Start with `/admin/modules/create`
2. **Add Lessons**: Create lessons and assign to the module
3. **Add Assessment**: Create assessment and attach to module
4. **Publish**: Set all content to published status

### Creating Standalone Content
1. **Standalone Lesson**: Create lesson without module assignment
2. **Standalone Assessment**: Create assessment without module attachment
3. **Later Organization**: Optionally assign to modules later

### Content Migration
1. **Module Assignment**: Edit lessons to assign/unassign modules
2. **Assessment Attachment**: Edit assessments to attach/detach from modules
3. **Bulk Updates**: Use search and filters for bulk operations

## Technical Implementation

### Controllers
- `ModuleController`: Handles module CRUD operations
- `LessonController`: Manages lesson lifecycle and module relationships
- `AssessmentController`: Handles assessments and polymorphic relationships

### Resources
- `ModuleResource`: API resource for module data
- `LessonResource`: API resource for lesson data
- `AssessmentResource`: API resource for assessment data

### Validation
- Comprehensive validation for all content types
- Relationship validation for module assignments
- Question validation for assessments

### Performance Optimizations
- Eager loading for relationships
- Pagination for large datasets
- Optimized queries with proper indexing

## Migration from Old System

### Cleaned Up Files
The following old classroom-specific files have been removed:
- `LessonCreate.vue`, `LessonEditor.vue`, `LessonIndex.vue`
- `AssessmentCreate.vue`, `AssessmentEditor.vue`, `AssessmentIndex.vue`
- `ModuleCreate.vue`, `ModuleEditor.vue`, `ModuleIndex.vue`

### Route Updates
- Old classroom routes remain for backward compatibility
- New standalone routes provide enhanced functionality
- Gradual migration path available

## Future Enhancements

### Planned Features
- **Content Templates**: Reusable content templates
- **Version Control**: Content versioning and history
- **Collaboration**: Multi-user content editing
- **Analytics**: Detailed content performance metrics
- **Export/Import**: Content portability features

### Integration Points
- **Student Interface**: Public-facing content delivery
- **Progress Tracking**: Student progress monitoring
- **Certificate Generation**: Completion certificates
- **API Access**: RESTful API for external integrations

## Best Practices

### Content Organization
- Use descriptive titles and descriptions
- Organize related content into modules
- Maintain consistent naming conventions
- Regular content reviews and updates

### Performance
- Optimize media file sizes
- Use appropriate content types
- Regular database maintenance
- Monitor system performance

### User Experience
- Clear navigation paths
- Consistent interface patterns
- Helpful error messages
- Intuitive workflows

This standalone content management system provides a flexible, scalable foundation for educational content creation and management, supporting both structured learning paths and flexible content organization.
