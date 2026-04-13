# AGENTS.md

## Key Commands

```bash
# Full setup (install, env, key, migrate, build)
composer run setup

# Start all dev servers (Laravel:8000, queue, logs, Vite:5173)
composer run dev

# Start with SSR
composer run dev:ssr

# Run tests
./vendor/bin/pest
./vendor/bin/pest --parallel

# Code quality
npm run lint          # ESLint with auto-fix
npm run format        # Prettier format
vendor/bin/pint       # Laravel Pint (PHP)
```

## Verification Order

After code changes: `npm run lint` → `npm run format` → `./vendor/bin/pest`

## Testing

- Uses **Pest** (not raw PHPUnit)
- Tests run with **SQLite in-memory** (configured in `phpunit.xml`)
- Do not commit changes unless tests pass

## Tech Stack

- **Laravel 12** + **PHP 8.4+**
- **Vue 3** + **TypeScript** + **Inertia.js** (SPA)
- **Tailwind CSS 4** (not v3)
- Radix Vue for headless UI components

## Architecture Notes

- Admin routes under `/admin` prefix (auth required)
- API v1 endpoints under `/api/v1`
- Polymorphic media via `Mediable` trait (`Media` → `Post`/`Playlist`)
- Reusable components: `DataTable`, `Searchbar`, `RichTextEditor`, `MediaUploader`

## Important Conventions

- **No Tailwind CSS v3** patterns; use Tailwind CSS 4 syntax
- Prefer `clsx` + `tailwind-merge` for conditional classes
- Use shadcn-vue inspired patterns for UI components in `resources/js/components/ui/`
- Dark/light mode via `useAppearance` composable, theme in localStorage

## Docker

- Uses **MySQL** for app database
- Node container handles asset building
- See `docker-compose.yml` for service config
