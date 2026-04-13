# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Environment

### Local Setup (without Docker)
1. Install PHP 8.4+, Composer, Node.js 22+, and PostgreSQL/MySQL.
2. Clone repository and run:
   ```bash
   composer install
   npm install
   cp .env.example .env
   php artisan key:generate
   ```
3. Configure database in `.env` (default uses SQLite for testing).
4. Run migrations: `php artisan migrate`
5. Start dev servers:
   ```bash
   # Laravel dev server (port 8000)
   php artisan serve
   # Vite dev server (port 5173) in another terminal
   npm run dev
   ```
6. Visit `http://localhost:8000`

### Docker Setup
- Use `docker-compose up` to start app, database, and Node containers.
- App runs on port 8000, database on MySQL default port.
- Node container handles asset building.

### Herd (Optional)
- This project is configured for Laravel Herd.

## Common Commands

### PHP (Laravel)
```bash
composer install           # Install PHP dependencies
php artisan migrate        # Run migrations
php artisan db:seed        # Seed database
php artisan serve          # Start Laravel dev server
php artisan test           # Run all tests (uses Pest)
./vendor/bin/pest          # Run Pest tests directly
vendor/bin/pint            # Format PHP code (Laravel Pint)
```

### Frontend (Vue 3 + TypeScript + Vite)
```bash
npm install                # Install Node dependencies
npm run dev                # Start Vite dev server (HMR)
npm run build              # Build for production
npm run build:ssr          # Build for SSR
npm run format             # Format frontend code (Prettier)
npm run format:check       # Check formatting
npm run lint               # Lint and fix (ESLint)
```

### Combined Development
```bash
composer run dev           # Start all dev servers (Laravel, queue, logs, Vite) concurrently
composer run dev:ssr       # Start with SSR
composer run setup         # Full setup (install, env, key, migrate, build)
```

### Testing
```bash
php artisan test           # Run all tests
php artisan test --testsuite=Feature  # Run feature tests
php artisan test --testsuite=Unit     # Run unit tests
./vendor/bin/pest          # Run Pest tests
./vendor/bin/pest --parallel # Run tests in parallel
```

### Code Quality
```bash
# PHP
vendor/bin/pint            # Format PHP code

# Frontend
npm run format             # Format resources/ with Prettier
npm run lint               # ESLint with auto-fix
```

## Architecture Overview

### Tech Stack
- **Backend**: Laravel 12 (PHP 8.4), PostgreSQL/MySQL, Laravel Fortify (auth)
- **Frontend**: Vue 3 (Composition API), TypeScript, Inertia.js (SPA), Tailwind CSS 4
- **UI Components**: Radix Vue (headless), shadcn-vue inspired components
- **Rich Text Editor**: Tiptap
- **Build Tool**: Vite
- **Testing**: Pest (PHP), PHPUnit

### Key Patterns
- **Monolithic SPA**: Inertia.js bridges Laravel backend with Vue frontend; server-side routing returns Inertia responses.
- **Admin/Public Separation**: Admin routes under `/admin` prefix with auth middleware; public routes for site visitors.
- **API Versioning**: API routes under `/api/v1` with public and authenticated sections.
- **Polymorphic Media**: `Media` model attaches to `Post` and `Playlist` via `Mediable` trait.
- **Reusable Components**: DataTable, Searchbar, RichTextEditor, MediaUploader components shared across admin.

### Directory Structure Highlights
```
app/
├── Http/Controllers/
│   ├── Admin/           # Admin panel controllers (CRUD for posts/playlists)
│   ├── Api/             # API controllers (v1 endpoints)
│   └── ...              # Public controllers (Home, Post, Playlist, Search)
├── Models/              # Eloquent models (Post, Playlist, Media, User, etc.)
├── Http/Resources/      # API resources (PostResource, PlaylistResource)
└── Providers/           # Service providers

resources/
├── js/
│   ├── components/      # Vue components
│   │   ├── ui/         # Reusable UI components (shadcn-vue style)
│   │   ├── admin/      # Admin-specific components
│   │   ├── DataTable.vue    # Generic table with sorting, pagination
│   │   ├── Searchbar.vue    # Search input component
│   │   └── RichTextEditor.vue  # Tiptap editor
│   ├── layouts/         # Layout components (FrontLayout, AdminLayout)
│   ├── pages/           # Inertia pages (mapped to routes)
│   │   ├── admin/       # Admin pages
│   │   ├── Home.vue     # Public home
│   │   ├── PostDetail.vue
│   │   └── ...
│   └── composables/     # Vue composables (useAppearance, etc.)
├── css/
│   └── app.css          # Tailwind CSS 4 with custom theme, dark mode
└── views/
    └── app.blade.php    # Root template

routes/
├── web.php              # Web routes (public, admin, dashboard)
├── api.php              # API routes (v1, public/protected)
└── settings.php         # User settings routes (profile, password, 2FA)

database/
├── migrations/          # Database schema
└── seeders/             # Seed data
```

### Key Models & Relationships
- **User**: Authentication, owns posts and playlists.
- **Post**: Articles, image carousels, or videos; belongs to many playlists via pivot.
- **Playlist**: Collections of posts; belongs to a user.
- **Media**: Polymorphic attachments for posts and playlists.
- **PostLike** & **PostView**: Track engagement.

### API Endpoints (v1)
- **Public**: `GET /api/v1/posts`, `GET /api/v1/playlists`, `POST /api/v1/posts/{slug}/like`, `GET /api/v1/search`
- **Protected** (requires auth): `POST /api/v1/posts`, `PUT /api/v1/posts/{slug}`, media upload, analytics, playlist management.

## Testing
- Uses Pest (built on PHPUnit) for PHP tests.
- Test suites: Feature (integration), Unit.
- Database uses SQLite in-memory for testing (configured in phpunit.xml).
- Run specific test file: `./vendor/bin/pest tests/Feature/ExampleTest.php`

## Code Quality & Formatting
- **PHP**: Laravel Pint (config in `vendor/laravel/pint`).
- **JavaScript/TypeScript**: ESLint (config in `eslint.config.js`) with Vue and TypeScript plugins.
- **Prettier**: Formatting for frontend files (config in `.prettierrc`).
- **Git Hooks**: None configured; CI runs linting/tests on push.

## Environment Variables
Key variables in `.env`:
- `APP_ENV`, `APP_DEBUG`, `APP_URL`
- Database: `DB_CONNECTION`, `DB_*`
- Session: `SESSION_DRIVER=database`
- Filesystem: `FILESYSTEM_DISK` (local or S3)
- AWS S3 for media storage (optional)
- `VITE_APP_NAME` passed to frontend

## Deployment
### Production Build
```bash
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
```

### Docker Production
- Use `Dockerfile` for PHP app, `Dockerfile.node` for Node build.
- See `DEPLOY_VPS.md` for manual deployment instructions.

## CI/CD
- GitHub Actions: `.github/workflows/lint.yml` (Pint + Prettier + ESLint), `.github/workflows/tests.yml` (Pest).
- Runs on push to `main` and `develop` branches.

## Common Development Workflows

### Adding a New Admin CRUD
1. **Create Migration**: Add table if needed (e.g., `php artisan make:migration create_items_table`).
2. **Create Model**: `app/Models/Item.php` with relationships, fillable, casts.
3. **Create Controller**: Place in `app/Http/Controllers/Admin/ItemController.php` extending `Controller`. Use resource methods (`index`, `create`, `store`, `edit`, `update`, `destroy`).
4. **Add Routes**: In `routes/web.php`, add `Route::resource('admin/items', ItemController::class)->except(['show'])` inside admin group.
5. **Create Vue Page**: Add `resources/js/pages/admin/Items/Index.vue` (or `Create.vue`, `Edit.vue`) using `AdminLayout`. Use `DataTable` component for listing.
6. **API Endpoints**: If needed, add API routes in `routes/api.php` under protected group with corresponding controller in `app/Http/Controllers/Api/`.

### Creating a New Vue Component
- Place reusable components in `resources/js/components/` (sub‑directory by domain).
- Use TypeScript interfaces for props.
- Use Tailwind CSS for styling; refer to theme variables in `app.css`.
- For UI components, follow shadcn‑vue patterns (see existing components in `resources/js/components/ui/`).

### Adding an API Endpoint
1. **Create Controller** (or add method): In `app/Http/Controllers/Api/`.
2. **Define Route**: In `routes/api.php` under `v1` prefix, with appropriate middleware (`auth:web` for protected).
3. **Return JSON** or use API Resources (`app/Http/Resources/`).
4. **Test**: Write Pest feature test in `tests/Feature/Api/`.

### Adding a New Page (Inertia)
1. **Add Route** in `routes/web.php` mapping to a controller method.
2. **Controller Method**: Return `Inertia::render('PageName', [ ...props ])`.
3. **Create Vue Page**: In `resources/js/pages/PageName.vue` with `<script setup lang="ts">`. Use a layout (`FrontLayout` or `AdminLayout`).
4. **Props**: Define TypeScript interfaces for props passed from controller.

### Working with Media Uploads
- Use `MediaUploader` component (supports drag‑and‑drop, multiple files).
- Backend handles upload via `MediaController` (`/api/v1/media/upload`).
- Media attached polymorphically via `Mediable` trait.

## Notes
- **Dark/Light Mode**: Implemented via `useAppearance` composable; theme stored in localStorage.
- **Infinite Scroll**: Used on home page for posts.
- **SEO**: Meta tags and structured data in post detail.
- **Admin UI**: Uses reusable DataTable with search, sort, pagination.
- **Media Upload**: Supports drag‑and‑drop, multiple files, image/video preview.
- **Two‑Factor Authentication**: Components exist but are currently disabled pending backend implementation (see recent commits).
