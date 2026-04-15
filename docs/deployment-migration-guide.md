# Enhanced Classroom System - Deployment and Migration Guide

## Overview

This guide provides step-by-step instructions for deploying the Enhanced Classroom System, which transforms the existing Laravel classroom system to support both track-based and course-based learning paths while maintaining full backward compatibility.

## Prerequisites

### System Requirements

- **PHP**: 8.1 or higher
- **Laravel**: 10.x or higher
- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Memory**: Minimum 512MB PHP memory limit (1GB recommended for large datasets)
- **Storage**: Additional 20% disk space for migration backups

### Environment Preparation

1. **Backup Current System**
   ```bash
   # Create database backup
   mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
   
   # Create application backup
   tar -czf app_backup_$(date +%Y%m%d_%H%M%S).tar.gz /path/to/application
   ```

2. **Update Dependencies**
   ```bash
   composer update
   npm install && npm run build
   ```

3. **Environment Configuration**
   ```bash
   # Copy environment file if needed
   cp .env.example .env
   
   # Update database configuration
   php artisan config:cache
   ```

## Migration Strategy

The migration follows a **phased approach** to ensure zero downtime and data integrity:

### Phase 1: Schema Foundation (Safe)
- Creates new tables without affecting existing data
- Adds new columns with default values
- No breaking changes to existing functionality

### Phase 2: Data Migration (Critical)
- Migrates existing data to new structures
- Maintains backward compatibility
- Includes comprehensive validation

### Phase 3: Schema Updates (Careful)
- Updates existing table constraints
- Optimizes indexes for performance
- Maintains all existing relationships

## Step-by-Step Migration Process

### Step 1: Pre-Migration Validation

```bash
# Verify system health
php artisan migrate:status
php artisan queue:work --stop-when-empty

# Check database integrity
php artisan classroom:verify-data-integrity

# Ensure no pending migrations
php artisan migrate:status | grep -c "Ran?"
```

### Step 2: Create Database Backup

```bash
# Automated backup with timestamp
php artisan backup:run --only-db

# Manual backup (alternative)
mysqldump -u $DB_USERNAME -p$DB_PASSWORD $DB_DATABASE > pre_migration_backup_$(date +%Y%m%d_%H%M%S).sql
```

### Step 3: Run Phase 1 Migrations (Schema Foundation)

```bash
# Run the foundation migration
php artisan migrate --path=database/migrations/2026_01_19_141520_create_enhanced_classroom_tables.php

# Verify new tables were created
php artisan migrate:status | grep "2026_01_19_141520"
```

**Expected Output:**
```
Migration table created successfully.
Migrating: 2026_01_19_141520_create_enhanced_classroom_tables
Migrated:  2026_01_19_141520_create_enhanced_classroom_tables (XX.XXs)
```

**Verification:**
```sql
-- Verify new tables exist
SHOW TABLES LIKE 'courses';
SHOW TABLES LIKE 'course_modules';
SHOW TABLES LIKE 'level_modules';
SHOW TABLES LIKE 'course_enrollments';
SHOW TABLES LIKE 'learning_progress';
```

### Step 4: Add Polymorphic Support

```bash
# Add polymorphic columns to certificates table
php artisan migrate --path=database/migrations/2026_01_19_141557_add_polymorphic_columns_to_certificates_table.php

# Verify columns were added
php artisan tinker
>>> Schema::hasColumn('certificates', 'certifiable_type')
>>> Schema::hasColumn('certificates', 'certifiable_id')
```

### Step 5: Run Phase 2 Migrations (Data Migration)

**⚠️ CRITICAL PHASE - Monitor Closely**

```bash
# Run data migration with verbose output
php artisan migrate --path=database/migrations/2026_01_19_143028_migrate_existing_data_to_enhanced_classroom_system.php -v

# Monitor migration progress
tail -f storage/logs/laravel.log
```

**Expected Output:**
```
Migrating: 2026_01_19_143028_migrate_existing_data_to_enhanced_classroom_system
Migrated X module-level relationships to level_modules table.
Migrated Y lesson progress records to learning_progress table.
Updated Z certificate records with polymorphic relationships.
Migrated:  2026_01_19_143028_migrate_existing_data_to_enhanced_classroom_system (XX.XXs)
```

**Data Validation:**
```bash
# Verify data migration
php artisan classroom:verify-data-integrity --detailed

# Check specific counts
php artisan tinker
>>> DB::table('level_modules')->count()
>>> DB::table('learning_progress')->count()
>>> DB::table('certificates')->whereNotNull('certifiable_type')->count()
```

### Step 6: Run Phase 3 Migrations (Schema Updates)

```bash
# Update table constraints and indexes
php artisan migrate --path=database/migrations/2026_01_19_151146_update_tables_for_enhanced_classroom_system.php

# Add performance indexes
php artisan migrate --path=database/migrations/2026_01_19_152830_add_performance_indexes_to_enhanced_classroom_system.php
```

### Step 7: Post-Migration Validation

```bash
# Run comprehensive system check
php artisan classroom:verify-data-integrity --full

# Test backward compatibility
php artisan test --filter=BackwardCompatibilityTest

# Performance check
php artisan classroom:monitor-performance --duration=5
```

## Rollback Procedures

### Emergency Rollback (If Issues Occur)

**⚠️ Only use if critical issues are detected**

```bash
# Stop all processes
php artisan down

# Rollback migrations in reverse order
php artisan migrate:rollback --path=database/migrations/2026_01_19_152830_add_performance_indexes_to_enhanced_classroom_system.php
php artisan migrate:rollback --path=database/migrations/2026_01_19_151146_update_tables_for_enhanced_classroom_system.php
php artisan migrate:rollback --path=database/migrations/2026_01_19_143028_migrate_existing_data_to_enhanced_classroom_system.php
php artisan migrate:rollback --path=database/migrations/2026_01_19_141557_add_polymorphic_columns_to_certificates_table.php
php artisan migrate:rollback --path=database/migrations/2026_01_19_141520_create_enhanced_classroom_tables.php

# Restore from backup if needed
mysql -u username -p database_name < backup_file.sql

# Bring system back online
php artisan up
```

### Partial Rollback (Specific Migration)

```bash
# Rollback specific migration
php artisan migrate:rollback --step=1

# Verify rollback
php artisan migrate:status
```

## Configuration Changes

### Environment Variables

Add the following to your `.env` file:

```env
# Enhanced Classroom System Configuration
CLASSROOM_CACHE_ENABLED=true
CLASSROOM_CACHE_TTL=3600
CLASSROOM_PERFORMANCE_MONITORING=true
CLASSROOM_BULK_OPERATIONS_LIMIT=1000

# Certificate Generation
CERTIFICATE_STORAGE_DISK=public
CERTIFICATE_TEMPLATE_CACHE=true

# Progress Tracking
PROGRESS_GRANULAR_TRACKING=true
PROGRESS_ENGAGEMENT_SCORING=true
```

### Cache Configuration

```bash
# Clear and rebuild cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Setup Redis for enhanced performance (optional)
php artisan queue:table
php artisan migrate
```

### Queue Configuration

```bash
# Setup queue workers for bulk operations
php artisan queue:table
php artisan migrate

# Start queue workers
php artisan queue:work --queue=default,bulk-operations --timeout=300
```

## Performance Optimization

### Database Indexes

The migration automatically adds performance indexes. Verify they were created:

```sql
-- Check course-related indexes
SHOW INDEX FROM courses;
SHOW INDEX FROM course_modules;
SHOW INDEX FROM course_enrollments;

-- Check learning progress indexes
SHOW INDEX FROM learning_progress;
SHOW INDEX FROM level_modules;
```

### Caching Setup

```bash
# Enable classroom caching
php artisan config:set classroom.cache.enabled true

# Warm up caches
php artisan classroom:cache-warm

# Monitor cache performance
php artisan classroom:cache-stats
```

### Query Optimization

```bash
# Analyze slow queries
php artisan classroom:analyze-queries --slow-only

# Optimize database
php artisan db:optimize
```

## Monitoring and Maintenance

### Health Checks

```bash
# Daily health check
php artisan classroom:health-check

# Performance monitoring
php artisan classroom:monitor-performance --continuous

# Data integrity verification
php artisan classroom:verify-data-integrity --schedule
```

### Automated Monitoring

Add to your cron schedule (`crontab -e`):

```bash
# Health checks every hour
0 * * * * cd /path/to/app && php artisan classroom:health-check

# Performance monitoring every 6 hours
0 */6 * * * cd /path/to/app && php artisan classroom:monitor-performance

# Daily data integrity check
0 2 * * * cd /path/to/app && php artisan classroom:verify-data-integrity
```

## Troubleshooting

### Common Issues and Solutions

#### 1. Migration Timeout

**Problem:** Migration takes too long and times out

**Solution:**
```bash
# Increase PHP memory and time limits
php -d memory_limit=2G -d max_execution_time=0 artisan migrate

# Run migrations in chunks
php artisan classroom:migrate-in-chunks --chunk-size=1000
```

#### 2. Foreign Key Constraint Errors

**Problem:** Foreign key constraints prevent migration

**Solution:**
```bash
# Temporarily disable foreign key checks
php artisan tinker
>>> DB::statement('SET FOREIGN_KEY_CHECKS=0;')
>>> # Run problematic migration
>>> DB::statement('SET FOREIGN_KEY_CHECKS=1;')
```

#### 3. Duplicate Entry Errors

**Problem:** Unique constraint violations during data migration

**Solution:**
```bash
# Clean duplicate data before migration
php artisan classroom:clean-duplicates --dry-run
php artisan classroom:clean-duplicates --execute
```

#### 4. Performance Degradation

**Problem:** System becomes slow after migration

**Solution:**
```bash
# Rebuild indexes
php artisan db:optimize

# Clear and rebuild caches
php artisan cache:clear
php artisan classroom:cache-warm

# Analyze and optimize queries
php artisan classroom:optimize-queries
```

### Log Analysis

Monitor these log files during and after migration:

```bash
# Application logs
tail -f storage/logs/laravel.log

# Migration-specific logs
tail -f storage/logs/migration.log

# Performance logs
tail -f storage/logs/performance.log
```

### Database Monitoring

```sql
-- Monitor active connections
SHOW PROCESSLIST;

-- Check table sizes
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.tables 
WHERE table_schema = 'your_database_name'
ORDER BY (data_length + index_length) DESC;

-- Monitor slow queries
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;
```

## Testing Procedures

### Pre-Deployment Testing

```bash
# Run full test suite
php artisan test

# Run migration-specific tests
php artisan test --filter=MigrationTest

# Run backward compatibility tests
php artisan test --filter=BackwardCompatibilityTest

# Run integration tests
php artisan test --filter=EnhancedClassroomIntegrationTest
```

### Post-Deployment Validation

```bash
# Verify all features work
php artisan classroom:feature-test --comprehensive

# Test API endpoints
php artisan test --filter=ApiTest

# Performance benchmarks
php artisan classroom:benchmark --compare-baseline
```

## Security Considerations

### Access Control

```bash
# Verify permissions are correctly applied
php artisan classroom:verify-permissions

# Test policy enforcement
php artisan test --filter=PolicyTest
```

### Data Protection

```bash
# Encrypt sensitive data
php artisan classroom:encrypt-sensitive-data

# Verify data integrity
php artisan classroom:verify-checksums
```

## Deployment Checklist

### Pre-Deployment

- [ ] System backup completed
- [ ] Dependencies updated
- [ ] Environment configured
- [ ] Test suite passes
- [ ] Performance baseline established

### During Deployment

- [ ] Maintenance mode enabled
- [ ] Migrations run successfully
- [ ] Data validation completed
- [ ] Performance indexes created
- [ ] Cache cleared and rebuilt

### Post-Deployment

- [ ] System health check passes
- [ ] Backward compatibility verified
- [ ] Performance monitoring active
- [ ] User acceptance testing completed
- [ ] Documentation updated

## Support and Maintenance

### Regular Maintenance Tasks

```bash
# Weekly tasks
php artisan classroom:cleanup-old-data
php artisan classroom:optimize-performance
php artisan classroom:update-statistics

# Monthly tasks
php artisan classroom:deep-analysis
php artisan classroom:archive-old-records
php artisan classroom:performance-report
```

### Emergency Contacts

- **System Administrator**: [Contact Information]
- **Database Administrator**: [Contact Information]
- **Development Team Lead**: [Contact Information]

### Documentation Updates

Keep the following documentation current:
- API documentation
- User guides
- Administrative procedures
- Troubleshooting guides

## Conclusion

The Enhanced Classroom System migration is designed to be safe and reversible. Follow this guide carefully, monitor the system closely during migration, and don't hesitate to rollback if issues arise. The system maintains full backward compatibility, so existing functionality will continue to work throughout the process.

For additional support or questions, refer to the troubleshooting section or contact the development team.
