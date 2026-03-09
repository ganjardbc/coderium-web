# Design Document

## Overview

This design outlines the architectural enhancements to transform the existing Laravel classroom system from a rigid Track → Level → Module → Lesson hierarchy into a flexible system supporting both traditional track-based learning and new course-based learning paths. The design maintains full backward compatibility while introducing reusable modules, dynamic certificates, enhanced progress tracking, and flexible assignment systems.

The core architectural shift moves from direct foreign key relationships to a more flexible assignment-based system using pivot tables and polymorphic relationships, following Laravel best practices for maintainable and scalable code.

## Architecture

### Current Architecture
```
Track (1) → Level (N) → Module (N) → Lesson (N)
         ↓
    Certificate (1:1 per user)
         ↓
    Progress (binary completion)
```

### Enhanced Architecture
```
Track (1) → Level (N) ←→ Module (N) → Lesson (N)
                    ↗         ↘
Course (1) ←→ Module (N)    Assessment (polymorphic)
    ↓              ↓
Certificate ←→ Learning Path (polymorphic)
    ↓              ↓
Progress Tracking (granular metrics)
```

### Key Architectural Principles

1. **Polymorphic Design**: Use Laravel's polymorphic relationships to support multiple learning path types
2. **Pivot Table Strategy**: Replace direct foreign keys with flexible many-to-many relationships
3. **Service Layer Pattern**: Implement business logic in dedicated service classes
4. **Backward Compatibility**: Maintain existing interfaces while adding new functionality
5. **Migration Strategy**: Gradual transformation with rollback capabilities

## Components and Interfaces

### Enhanced Models

#### Module Model (Enhanced)
```php
class Module extends Model
{
    // Existing relationships (maintained for backward compatibility)
    public function level()
    {
        return $this->belongsTo(Level::class);
    }
    
    // New flexible relationships
    public function levels()
    {
        return $this->belongsToMany(Level::class, 'level_modules')
                    ->withPivot(['order', 'is_required'])
                    ->withTimestamps();
    }
    
    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_modules')
                    ->withPivot(['order', 'is_required'])
                    ->withTimestamps();
    }
    
    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }
    
    public function assessments()
    {
        return $this->morphMany(Assessment::class, 'assessable');
    }
}
```

#### Course Model (New)
```php
class Course extends Model
{
    protected $fillable = [
        'title', 'description', 'slug', 'is_active', 
        'certificate_template_id', 'estimated_duration'
    ];
    
    public function modules()
    {
        return $this->belongsToMany(Module::class, 'course_modules')
                    ->withPivot(['order', 'is_required'])
                    ->withTimestamps()
                    ->orderBy('pivot_order');
    }
    
    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }
    
    public function certificates()
    {
        return $this->morphMany(Certificate::class, 'certifiable');
    }
    
    public function progress()
    {
        return $this->morphMany(LearningProgress::class, 'progressable');
    }
}
```

#### Certificate Model (Enhanced)
```php
class Certificate extends Model
{
    protected $fillable = [
        'user_id', 'certifiable_type', 'certifiable_id',
        'template_id', 'issued_at', 'certificate_data'
    ];
    
    // Polymorphic relationship to support both tracks and courses
    public function certifiable()
    {
        return $this->morphTo();
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function template()
    {
        return $this->belongsTo(CertificateTemplate::class);
    }
}
```

#### LearningProgress Model (Enhanced)
```php
class LearningProgress extends Model
{
    protected $fillable = [
        'user_id', 'progressable_type', 'progressable_id',
        'completion_percentage', 'time_spent_minutes', 
        'last_accessed_at', 'completed_at', 'engagement_score'
    ];
    
    protected $casts = [
        'completion_percentage' => 'decimal:2',
        'last_accessed_at' => 'datetime',
        'completed_at' => 'datetime'
    ];
    
    // Polymorphic relationship to support tracks, courses, modules, lessons
    public function progressable()
    {
        return $this->morphTo();
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### Service Layer Architecture

#### ModuleAssignmentService
```php
class ModuleAssignmentService
{
    public function assignModuleToLevel(Module $module, Level $level, array $options = [])
    {
        // Validate no duplicate assignment within same level
        // Create level_modules pivot record
        // Update module relationships
    }
    
    public function assignModuleToCourse(Module $module, Course $course, array $options = [])
    {
        // Validate assignment constraints
        // Create course_modules pivot record
        // Update course relationships
    }
    
    public function validateAssignment(Module $module, $assignable)
    {
        // Check for duplicate assignments
        // Validate business rules
        // Return validation results
    }
}
```

#### ProgressTrackingService
```php
class ProgressTrackingService
{
    public function updateProgress($user, $progressable, array $metrics)
    {
        // Update granular progress metrics
        // Calculate completion percentage
        // Trigger completion events if applicable
    }
    
    public function calculateAggregateProgress($user, $learningPath)
    {
        // Aggregate progress from child components
        // Calculate overall completion metrics
        // Return comprehensive progress data
    }
    
    public function getProgressSummary($user, $learningPath)
    {
        // Generate progress summary with backward compatibility
        // Include both binary and granular metrics
    }
}
```

#### CertificateService
```php
class CertificateService
{
    public function generateCertificate($user, $certifiable)
    {
        // Determine appropriate certificate template
        // Generate certificate with dynamic data
        // Store certificate record
    }
    
    public function selectTemplate($certifiable)
    {
        // Dynamic template selection based on certifiable type
        // Support both track and course templates
    }
}
```

## Data Models

### Database Schema Changes

#### New Tables

**course_modules** (Pivot Table)
```sql
CREATE TABLE course_modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NOT NULL,
    order INT NOT NULL DEFAULT 0,
    is_required BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_course_module (course_id, module_id)
);
```

**level_modules** (Pivot Table)
```sql
CREATE TABLE level_modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NOT NULL,
    order INT NOT NULL DEFAULT 0,
    is_required BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_level_module (level_id, module_id)
);
```

**courses**
```sql
CREATE TABLE courses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    slug VARCHAR(255) UNIQUE NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT true,
    certificate_template_id BIGINT UNSIGNED NULL,
    estimated_duration INT NULL COMMENT 'Duration in minutes',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (certificate_template_id) REFERENCES certificate_templates(id) ON DELETE SET NULL
);
```

**course_enrollments**
```sql
CREATE TABLE course_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    progress_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_course (user_id, course_id)
);
```

#### Modified Tables

**certificates** (Enhanced for Polymorphic Support)
```sql
ALTER TABLE certificates 
ADD COLUMN certifiable_type VARCHAR(255) NULL AFTER user_id,
ADD COLUMN certifiable_id BIGINT UNSIGNED NULL AFTER certifiable_type,
ADD INDEX idx_certifiable (certifiable_type, certifiable_id);

-- Maintain backward compatibility by keeping track_id temporarily
-- Migration will populate polymorphic fields from existing track_id
```

**learning_progress** (Enhanced Metrics)
```sql
CREATE TABLE learning_progress (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    progressable_type VARCHAR(255) NOT NULL,
    progressable_id BIGINT UNSIGNED NOT NULL,
    completion_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    time_spent_minutes INT NOT NULL DEFAULT 0,
    engagement_score DECIMAL(3,2) NULL COMMENT 'Score from 0.00 to 1.00',
    last_accessed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_progressable (progressable_type, progressable_id),
    UNIQUE KEY unique_user_progressable (user_id, progressable_type, progressable_id)
);
```

### Migration Strategy

#### Phase 1: Add New Tables and Columns
1. Create new tables (courses, course_modules, level_modules, course_enrollments)
2. Add polymorphic columns to certificates table
3. Create learning_progress table
4. Maintain existing foreign keys during transition

#### Phase 2: Data Migration
1. Populate level_modules from existing module.level_id relationships
2. Convert existing certificates to polymorphic format
3. Migrate lesson_progress data to new learning_progress format
4. Validate data integrity after migration

#### Phase 3: Cleanup (Optional)
1. Remove deprecated columns after validation period
2. Update constraints and indexes
3. Archive old progress tables

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

Let me analyze the acceptance criteria to determine which ones can be tested as properties:

<function_calls>
<invoke name="prework">
<parameter name="featureName">enhanced-classroom-system

Based on the prework analysis, here are the testable correctness properties:

**Property 1: Module Assignment Without Duplication**
*For any* module and level, when the module is assigned to the level, the system should create exactly one assignment relationship while maintaining only one module record, regardless of how many levels the module is assigned to.
**Validates: Requirements 1.1**

**Property 2: Duplicate Assignment Prevention**
*For any* module and level where an assignment already exists, attempting to create another assignment should fail with a clear error message and leave the existing assignment unchanged.
**Validates: Requirements 1.2, 5.2**

**Property 3: Module Update Consistency**
*For any* module assigned to multiple levels or courses, when the module is updated, all assignment contexts should reflect the same updated module data.
**Validates: Requirements 1.3**

**Property 4: Assignment Removal Isolation**
*For any* module assigned to multiple levels or courses, removing one assignment should preserve the module and all other assignments unchanged.
**Validates: Requirements 1.4, 5.4**

**Property 5: Course Module Ordering**
*For any* course with multiple modules, when modules are assigned with specific order values, retrieving the course should return modules in the exact same order.
**Validates: Requirements 2.1, 2.3**

**Property 6: Module Reusability Across Contexts**
*For any* module assigned to a course, the module should remain available for assignment to other courses and levels without restriction.
**Validates: Requirements 2.2**

**Property 7: Course Deletion Module Preservation**
*For any* course with assigned modules, when the course is deleted, all assigned modules should remain in the system and available for other assignments.
**Validates: Requirements 2.4**

**Property 8: Dynamic Certificate Template Selection**
*For any* completed learning path (track or course), the certificate generation should select the template type that matches the learning path type (track template for tracks, course template for courses).
**Validates: Requirements 3.1, 3.2, 3.3**

**Property 9: Certificate Uniqueness Constraint**
*For any* user and learning path combination, attempting to create multiple certificates should fail, maintaining exactly one certificate per user per learning path.
**Validates: Requirements 3.4**

**Property 10: Granular Progress Recording**
*For any* progress update event, the system should record all required metrics (completion percentage, time spent, engagement level) and maintain both binary completion status and detailed metrics.
**Validates: Requirements 4.1, 4.4**

**Property 11: Track Progress Aggregation**
*For any* track with multiple levels and modules, the overall track progress should equal the weighted average of all level and module completion percentages.
**Validates: Requirements 4.2**

**Property 12: Course Progress Aggregation**
*For any* course with multiple modules, the overall course progress should equal the weighted average of all module completion percentages, independent of level associations.
**Validates: Requirements 4.3**

**Property 13: Assignment Validation and Bulk Operations**
*For any* bulk assignment operation, all individual assignment validations (including duplicate checking) should be applied to each assignment in the batch.
**Validates: Requirements 5.1, 5.5**

**Property 14: Level Module Listing Completeness**
*For any* level, the system should return all modules assigned to that level, regardless of whether those modules are also assigned to other levels or courses.
**Validates: Requirements 5.3**

**Property 15: Assessment Context Support**
*For any* assessment created for a module, the assessment should function correctly whether accessed through a track context or course context, recording appropriate context information.
**Validates: Requirements 8.1, 8.2**

**Property 16: Assessment Report Aggregation**
*For any* assessment report generation, results should include data from both track-based and course-based assessment completions.
**Validates: Requirements 8.3**

**Property 17: Assessment Progress Contribution**
*For any* completed assessment, the assessment results should contribute to the overall progress metrics of the containing learning path.
**Validates: Requirements 8.4**

**Property 18: Enrollment Record Consistency**
*For any* course enrollment, the system should create enrollment records with the same structure and behavior patterns as track enrollments.
**Validates: Requirements 9.1**

**Property 19: Unified Progress Views**
*For any* user with progress in both tracks and courses, the system should provide consistent progress information format regardless of learning path type.
**Validates: Requirements 9.2, 10.3**

**Property 20: Bulk Enrollment Operations**
*For any* bulk enrollment operation, the system should handle both track and course enrollments with identical validation and processing patterns.
**Validates: Requirements 9.3**

**Property 21: Enrollment Constraint Consistency**
*For any* enrollment limit configuration, the constraints should be enforced identically across both track and course enrollment types.
**Validates: Requirements 9.4**

**Property 22: API Response and Pattern Consistency**
*For any* API operation (CRUD, data retrieval), the system should provide consistent response structures and follow identical patterns for both tracks and courses.
**Validates: Requirements 10.1, 10.2**

**Property 23: Security Policy Consistency**
*For any* authentication or authorization check, the system should apply identical security policies regardless of whether the resource is track-based or course-based.
**Validates: Requirements 10.4**

## Error Handling

### Validation Errors
- **Duplicate Assignment**: Clear error messages when attempting to assign a module to a level where it already exists
- **Invalid References**: Proper error handling for non-existent modules, levels, or courses
- **Constraint Violations**: Database constraint violations should be caught and converted to user-friendly messages

### Migration Errors
- **Data Integrity**: Rollback capabilities for failed migration steps
- **Constraint Conflicts**: Handle existing data that may conflict with new constraints
- **Performance Issues**: Monitor migration performance and provide progress feedback

### Service Layer Error Handling
- **Transaction Management**: Use database transactions for multi-step operations
- **Graceful Degradation**: Maintain system functionality when non-critical features fail
- **Logging**: Comprehensive error logging for debugging and monitoring

## Testing Strategy

### Dual Testing Approach

The testing strategy employs both unit testing and property-based testing to ensure comprehensive coverage:

**Unit Tests**: Focus on specific examples, edge cases, and integration points
- Test specific module assignment scenarios
- Validate error conditions and edge cases
- Test migration scripts with sample data
- Integration tests for service layer interactions

**Property-Based Tests**: Verify universal properties across all inputs using **PHPUnit with Eris** (PHP property-based testing library)
- Generate random modules, levels, courses, and users
- Test all 23 correctness properties with minimum 100 iterations each
- Each property test tagged with: **Feature: enhanced-classroom-system, Property {number}: {property_text}**

### Property Test Configuration

```php
// Example property test structure
class ModuleAssignmentPropertyTest extends TestCase
{
    use \Eris\TestTrait;
    
    /**
     * Feature: enhanced-classroom-system, Property 1: Module Assignment Without Duplication
     */
    public function testModuleAssignmentWithoutDuplication()
    {
        $this->forAll(
            Generator\elements($this->generateModules()),
            Generator\elements($this->generateLevels())
        )->then(function ($module, $level) {
            $initialModuleCount = Module::count();
            
            $this->moduleAssignmentService->assignModuleToLevel($module, $level);
            
            $this->assertEquals($initialModuleCount, Module::count());
            $this->assertTrue($level->modules()->where('id', $module->id)->exists());
        });
    }
}
```

### Testing Coverage Requirements

- **Unit Tests**: Cover specific examples and error conditions
- **Property Tests**: Minimum 100 iterations per property
- **Integration Tests**: Test service layer interactions and API endpoints
- **Migration Tests**: Validate data migration accuracy and rollback capabilities
- **Performance Tests**: Ensure acceptable performance with large datasets

The combination of unit and property tests ensures both concrete behavior validation and comprehensive input coverage, providing confidence in the system's correctness across all scenarios.
