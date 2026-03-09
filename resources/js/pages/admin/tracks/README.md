# Admin Tracks Management

This directory contains the Vue.js components for managing tracks in the admin panel.

## Files Overview

### `index.vue`
- **Purpose**: Main tracks listing page with search and pagination
- **Features**: 
  - Display all tracks in a data table
  - Search functionality
  - Pagination support
  - Quick actions (View, Edit, Levels, Delete)
  - Track statistics (levels count, enrollments, duration, price)
  - Difficulty level badges
  - Publishing status indicators

### `create.vue`
- **Purpose**: Create new track form
- **Features**:
  - Basic information (title, slug, description)
  - Track details (difficulty level, duration, instructor)
  - Pricing configuration (premium/free, price)
  - Publishing controls
  - Auto-slug generation from title
  - Form validation with error display

### `edit.vue`
- **Purpose**: Edit existing track form
- **Features**:
  - Same form fields as create
  - Pre-populated with existing track data
  - Delete track functionality
  - Form validation with error display

### `show.vue`
- **Purpose**: Track detail view with comprehensive information
- **Features**:
  - Track overview with statistics cards
  - Tabbed interface for different sections:
    - **Overview**: Basic track information and settings
    - **Levels**: List of track levels with management options
    - **Enrollments**: Student enrollment data and progress
    - **Analytics**: Track performance metrics
  - Quick edit access
  - Navigation breadcrumbs

### `levels.vue`
- **Purpose**: Manage levels within a specific track
- **Features**:
  - List all levels for the track
  - Level ordering controls (move up/down)
  - Level management actions (view, edit, modules, delete)
  - Add new level functionality
  - Order index display

## Data Flow

### Track Model Properties
```typescript
interface Track {
    id: number;
    title: string;
    description: string;
    slug: string;
    is_premium: boolean;
    price: number | null;
    is_published: boolean;
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    estimated_duration: number | null;
    levels_count: number;
    enrollments_count: number;
    instructor?: {
        id: number;
        name: string;
        email: string;
    };
    created_at: string;
    updated_at: string;
}
```

### Controller Routes
- `GET /admin/tracks` - List tracks (index.vue)
- `GET /admin/tracks/create` - Create form (create.vue)
- `POST /admin/tracks` - Store new track
- `GET /admin/tracks/{track}` - Show track details (show.vue)
- `GET /admin/tracks/{track}/edit` - Edit form (edit.vue)
- `PUT /admin/tracks/{track}` - Update track
- `DELETE /admin/tracks/{track}` - Delete track
- `GET /admin/tracks/{track}/levels` - Track levels (levels.vue)

## Features

### CRUD Operations
- ✅ **Create**: Full track creation with validation
- ✅ **Read**: Comprehensive track listing and detail views
- ✅ **Update**: Complete track editing capabilities
- ✅ **Delete**: Safe track deletion with confirmation

### Additional Features
- **Search & Filter**: Search tracks by title/description
- **Pagination**: Handle large track datasets
- **Validation**: Client and server-side validation
- **Responsive Design**: Mobile-friendly interface
- **Accessibility**: Proper ARIA labels and keyboard navigation
- **Type Safety**: Full TypeScript support

### UI Components Used
- DataTable for listings
- Card layouts for forms and details
- Tabs for organized information display
- Badges for status indicators
- Buttons with proper variants
- Form inputs with validation states

## Usage

1. **Access**: Navigate to `/admin/tracks` in the admin panel
2. **Create**: Click "Create Track" to add a new track
3. **Manage**: Use the data table actions to view, edit, or delete tracks
4. **Levels**: Access track levels through the "Levels" action or track detail page
5. **Search**: Use the search bar to find specific tracks

## Dependencies

- Vue 3 with Composition API
- Inertia.js for server-side rendering
- Tailwind CSS for styling
- Lucide Vue icons
- Custom UI components (Button, Card, Input, etc.)
- DataTable component for listings
