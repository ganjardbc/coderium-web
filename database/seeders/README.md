# Database Seeders

This directory contains various seeders for populating the database with sample data.

## Available Seeders

### CourseSeeder
Creates comprehensive course data including:
- 5 courses with different complexity levels
- Course-module relationships with proper ordering
- Course assessments and assignments
- Student enrollments and progress tracking
- Discussion forums and posts
- Certificate templates

**Usage:**
```bash
php artisan db:seed --class=CourseSeeder
```

### CourseOnlySeeder
Creates basic course data without the full complexity:
- 3 basic courses
- Simple module assignments
- Basic enrollments
- Minimal certificate templates

**Usage:**
```bash
php artisan db:seed --class=CourseOnlySeeder
```

### ClassroomSeeder
Creates the full classroom system with tracks, levels, modules, and lessons.

**Usage:**
```bash
php artisan db:seed --class=ClassroomSeeder
```

## Running All Seeders

To run all seeders including courses:
```bash
php artisan db:seed
```

## Course Data Structure

The CourseSeeder creates the following courses:

1. **Full Stack Web Development** (Active)
   - 4 modules assigned
   - Comprehensive final assessment
   - Final project assignment
   - Discussion forum

2. **Modern Frontend Development** (Active)
   - 3 modules assigned
   - Focus on modern frontend technologies
   - Certificate template included

3. **Backend API Development** (Active)
   - 3 modules assigned
   - API development focused content
   - Professional certificate template

4. **Web Development Fundamentals** (Active)
   - 2 modules assigned
   - Beginner-friendly content
   - Basic certificate template

5. **Advanced Web Technologies** (Draft)
   - 2 modules assigned
   - Advanced topics
   - Currently inactive for testing

## Dependencies

- CourseSeeder depends on having modules available (either from ClassroomSeeder or creates standalone modules)
- Certificate templates are created automatically if they don't exist
- Student users are created if none exist

## Notes

- All seeders are designed to be run multiple times without creating duplicates
- Existing data is checked before creating new records
- Progress and enrollment data is randomized for realistic testing scenarios
