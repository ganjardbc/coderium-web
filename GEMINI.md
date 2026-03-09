# Coderium Project Documentation

## Project Overview
- **Name:** Coderium
- **Description:** A modern content management and showcase platform.
- **Stack:**
  - **Backend:** Laravel 12 (PHP 8.4+)
  - **Frontend:** Vue 3 (Composition API, Script Setup), Inertia.js
  - **Styling:** Tailwind CSS 4
  - **State Management:** Pinia
  - **Language:** TypeScript
  - **Testing:** Pest (PHP), Vitest (JS/TS)

## Key Directories
- `app/`: Laravel application core (Models, Http, Services, Actions).
- `resources/js/`: Vue application source.
  - `pages/`: Inertia pages.
  - `components/`: Reusable Vue components (`ui` for shadcn-vue).
  - `stores/`: Pinia stores.
  - `layouts/`: Page layouts.
- `routes/`: Application routes (`web.php`, `api.php`, etc.).

## Coding Conventions
- **Formatting:** Prettier (run `npm run format`).
- **Linting:** ESLint (run `npm run lint`).
- **Type Safety:** TypeScript strict mode enabled.
- **CSS:** Tailwind CSS utility classes.
- **Component Style:** Vue 3 Composition API with `<script setup lang="ts">`.

## Development Commands
- `npm run dev`: Start Vite development server.
- `php artisan serve`: Start Laravel development server.
- `php artisan test`: Run server-side tests.
- `vitest`: Run client-side tests.
