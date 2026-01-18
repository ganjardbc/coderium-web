# Database Seeders

This directory contains database seeders for populating the application with sample data.

## Available Seeders

### Main Seeders

- **DatabaseSeeder**: Main seeder that runs all other seeders
- **ClassroomSeeder**: Comprehensive seeder for all classroom features
- **ClassroomOnlySeeder**: Seeder for classroom data only (useful for existing databases)

### Feature-Specific Seeders

- **PlaylistSeeder**: Creates sample playlists
- **PostSeeder**: Creates sample posts
- **MediaSeeder**: Creates sample media files
- **PlaylistPostSeeder**: Links posts to playlists
- **AchievementSeeder**: Creates default achievements
- **CertificateTemplateSeeder**: Creates certificate templates

## Classroom Features Included

The ClassroomSeeder creates comprehensive sample data for:

### 📚 **Tracks & Content Structure**
- 4 complete learning tracks (Web Development, JavaScript/React, Python Data Science, Flutter Mobile)
- Multiple difficulty levels (beginner, intermediate, advanced)
- Hierarchical content: Tracks → Levels → Modules → Lessons
- Different lesson types: text, video, interactive

### 👥 **Users & Roles**
- 3 instructor accounts with realistic profiles
- 17 learner accounts (2 named + 15 random)
- Admin user for management

### 📝 **Assessments & Quizzes**
- Lesson-level assessments with multiple question types
- Module-level comprehensive tests
- Multiple choice, true/false, and conceptual questions
- Configurable passing scores and attempt limits

### 📋 **Assignments & Submissions**
- Practical coding assignments for each track
- Detailed instructions and evaluation checklists
- Sample student submissions with grades and feedback
- Realistic due dates and submission timestamps

### 💬 **Discussions & Community**
- Module-specific discussion forums
- Sample discussion posts from students
- Pinned important topics
- Realistic conversation threads

### 📊 **Progress Tracking**
- Learner enrollments across multiple tracks
- Lesson completion progress
- Assessment attempts with realistic scores
- Time tracking for learning activities

### 🏆 **Achievements & Certificates**
- Default achievement system
- Certificate templates for course completion
- Progress-based achievement unlocking

## Usage

### Seed Everything (Fresh Database)
```bash
php artisan db:seed
```

### Seed Only Classroom Data
```bash
php artisan db:seed --class=ClassroomOnlySeeder
```

### Seed Specific Features
```bash
php artisan db:seed --class=ClassroomSeeder
php artisan db:seed --class=AchievementSeeder
php artisan db:seed --class=CertificateTemplateSeeder
```

## Sample Data Overview

### Tracks Created

1. **Web Development Fundamentals** (Free, Beginner)
   - HTML Fundamentals
   - CSS Styling  
   - JavaScript Basics
   - Duration: 8 hours

2. **Advanced JavaScript & React** (Premium $99.99, Intermediate)
   - Advanced JavaScript (ES6+)
   - React Fundamentals
   - Duration: 12 hours

3. **Python for Data Science** (Premium $149.99, Intermediate)
   - Python Programming Basics
   - Data Analysis with Pandas
   - Data Visualization
   - Duration: 16 hours

4. **Mobile App Development with Flutter** (Premium $199.99, Advanced, Draft)
   - Flutter Basics
   - Advanced Flutter Development
   - Duration: 20 hours

### User Accounts Created

**Instructors:**
- sarah.johnson@coderium.id (Dr. Sarah Johnson)
- michael.chen@coderium.id (Prof. Michael Chen)  
- emily.rodriguez@coderium.id (Dr. Emily Rodriguez)

**Learners:**
- alex.thompson@student.coderium.id (Alex Thompson)
- maria.garcia@student.coderium.id (Maria Garcia)
- Plus 15 additional random learners

**Admin:**
- admin@coderium.id (Admin User)

All accounts use password: `password`

## Development Notes

- The seeder creates realistic learning progressions with some learners more advanced than others
- Assessment attempts include both passing and failing scores to simulate real usage
- Assignment submissions have varied quality and realistic feedback
- Discussion posts create authentic community engagement scenarios
- Progress tracking reflects realistic learning patterns with some incomplete courses

## Customization

To modify the sample data:

1. Edit the content arrays in `ClassroomSeeder.php`
2. Adjust user creation in `createInstructors()` and `createStudents()`
3. Modify progress simulation in `createEnrollmentsAndProgress()`
4. Update assessment questions and assignments as needed

The seeder is designed to be flexible and can be easily extended with additional tracks, content types, or user scenarios.
