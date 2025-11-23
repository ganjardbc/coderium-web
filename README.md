# Coderium

A modern content management and showcase platform built with Laravel, Vue 3, and Inertia.js. Coderium allows you to create, organize, and share posts (articles, image carousels, and videos) in an Instagram-like interface with powerful playlist organization features.

## ✨ Features

### Public Features
- **Home Page**: Browse playlists and recent posts with infinite scroll
- **Post Detail**: View individual posts with support for articles, image carousels, and videos
- **Search**: Full-text search across all posts with filtering capabilities
- **Post Interactions**: Like posts and track views
- **SEO Optimized**: Meta tags and structured data for better search engine visibility

### Admin Features
- **Dashboard**: Analytics and site statistics
- **Post Management**: Create, edit, and delete posts with rich text editor
- **Playlist Management**: Organize posts into playlists with custom ordering
- **Media Management**: Upload and manage images and videos with polymorphic relationships
- **Search & Filters**: Advanced search functionality for posts and playlists
- **Reusable Components**: DataTable and Searchbar components for consistent UI

## 🛠 Tech Stack

### Frontend
- **Vue 3** - Progressive JavaScript framework with Composition API
- **TypeScript** - Type-safe development
- **Inertia.js** - Modern monolith approach
- **Tailwind CSS 4** - Utility-first CSS framework
- **Radix Vue** - Headless UI components
- **Tiptap** - Rich text editor
- **VueUse** - Collection of Vue composition utilities

### Backend
- **Laravel 12** - PHP web application framework
- **PHP 8.4** - Modern PHP features
- **PostgreSQL/MySQL** - Relational database
- **Laravel Fortify** - Authentication scaffolding

### Development Tools
- **Vite** - Next-generation frontend tooling
- **ESLint & Prettier** - Code formatting and linting
- **Herd** - Local development environment

## 📦 Installation

### Prerequisites
- PHP 8.4 or higher
- Composer
- Node.js 18+ and npm
- PostgreSQL or MySQL
- Herd (optional, for local development)

### Setup Steps

1. **Clone the repository**
```bash
git clone https://github.com/ganjardbc/coderium-web.git
cd coderium-web
```

2. **Install PHP dependencies**
```bash
composer install
```

3. **Install Node dependencies**
```bash
npm install
```

4. **Environment configuration**
```bash
cp .env.example .env
php artisan key:generate
```

5. **Configure your database** in `.env`
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=coderium
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

6. **Run migrations**
```bash
php artisan migrate
```

7. **Seed the database** (optional)
```bash
php artisan db:seed
```

8. **Build assets**
```bash
npm run dev
```

9. **Start the development server**
```bash
php artisan serve
```

Visit `http://localhost:8000` to see the application.

## 📁 Project Structure

```
coderium-web/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # Admin panel controllers
│   │   │   ├── Api/            # API endpoints
│   │   │   └── ...             # Public controllers
│   │   └── Resources/          # API resources
│   ├── Models/                 # Eloquent models
│   └── Providers/              # Service providers
├── resources/
│   ├── js/
│   │   ├── components/         # Vue components
│   │   │   ├── ui/            # UI components (shadcn-vue)
│   │   │   ├── admin/         # Admin components
│   │   │   ├── DataTable.vue  # Reusable table component
│   │   │   ├── Searchbar.vue  # Search component
│   │   │   └── RichTextEditor.vue
│   │   ├── layouts/           # Layout components
│   │   ├── pages/             # Inertia pages
│   │   │   ├── admin/         # Admin pages
│   │   │   ├── Home.vue
│   │   │   ├── PostDetail.vue
│   │   │   ├── Search.vue
│   │   │   └── Playlists.vue
│   │   └── app.ts             # Vue app entry
│   ├── css/
│   │   └── app.css            # Global styles
│   └── views/
│       └── app.blade.php      # Root template
├── routes/
│   ├── web.php                # Web routes
│   ├── api.php                # API routes
│   └── settings.php           # Settings routes
├── database/
│   ├── migrations/            # Database migrations
│   └── seeders/               # Database seeders
└── public/
    └── build/                 # Compiled assets
```

## 🗄 Database Schema

### Core Tables

**users**
- Authentication and user management

**playlists**
- id, title, description, slug, cover, user_id, is_published, order

**posts**
- id, title, subtitle, content, slug, type (article/carousel/video), cover, tags, user_id, is_published, published_at, views_count, likes_count

**playlist_post** (pivot)
- playlist_id, post_id, order, user_id

**media** (polymorphic)
- id, name, path, mime_type, size, mediable_type, mediable_id

**post_views**
- post_id, user_id, ip_address

**post_likes**
- post_id, user_id, ip_address

## 🎨 Key Components

### Reusable Components

**DataTable Component**
- Generic TypeScript component for displaying tabular data
- Supports custom columns, actions, slots, and pagination
- Used in admin posts and playlists management

**Searchbar Component**
- Reusable search input with search and clear buttons
- v-model support for two-way binding
- Emits search and clear events

**RichTextEditor Component**
- Tiptap-based WYSIWYG editor
- Toolbar with text formatting, headings, lists, links, and images
- Outputs HTML content

**MediaUploader Component**
- File upload with drag-and-drop support
- Image and video preview
- Multiple file support for carousels

## 🔌 API Endpoints

### Public API
- `GET /api/posts/{slug}/like` - Toggle post like
- Post views are tracked automatically on detail page load

### Admin Routes
- `GET /admin/dashboard` - Admin dashboard
- `GET /admin/posts` - List all posts with search
- `POST /admin/posts` - Create new post
- `PUT /admin/posts/{slug}` - Update post
- `DELETE /admin/posts/{slug}` - Delete post
- `GET /admin/playlists` - List all playlists with search
- `POST /admin/playlists` - Create new playlist
- `PUT /admin/playlists/{slug}` - Update playlist
- `DELETE /admin/playlists/{slug}` - Delete playlist

## 🚀 Deployment

### Build for Production

```bash
npm run build
```

### Optimize Application

```bash
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

## 📝 Development Notes

### Code Quality
- ESLint for JavaScript/TypeScript linting
- Prettier for code formatting
- TypeScript strict mode enabled

### Git Workflow
```bash
# Format code
npm run format

# Check formatting
npm run format:check

# Lint and fix
npm run lint
```

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 👤 Author

**Ganjar Hadiatna**
- GitHub: [@ganjardbc](https://github.com/ganjardbc)

## 🙏 Acknowledgments

- Laravel Team for the amazing framework
- Vue.js Team for the reactive frontend framework
- Inertia.js for seamless SPA experience
- shadcn-vue for beautiful UI components
- Tiptap for the rich text editor
