<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Home, LogIn, SearchIcon, PlaySquareIcon } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import ThemeToggle from '@/components/ThemeToggle.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const ENABLE_DARK_MODE = true;
const ENABLE_SEARCH = true;
const ENABLE_PLAYLISTS = false;
</script>

<template>
    <div class="min-h-screen bg-background">
        <!-- Header -->
        <header class="border-b bg-card/50 backdrop-blur-sm sticky top-0 z-50">
            <div class="container mx-auto px-4 py-4">
                <div class="flex items-center justify-between">
                    <Link href="/" class="flex items-center gap-2">
                        <div class="w-[136px] flex items-center gap-2">
                            <AppLogoIcon
                                class="fill-current text-[var(--foreground)] dark:text-white"
                            />
                        </div>
                    </Link>

                    <nav class="flex items-center gap-2">
                        <!-- Dark Mode Toggle -->
                        <ThemeToggle v-if="ENABLE_DARK_MODE" />

                        <!-- Search -->
                        <Button
                            v-if="ENABLE_SEARCH"
                            :as="Link"
                            href="/search"
                            variant="outline"
                            class="rounded-full"
                        >
                            <SearchIcon class="inline-block h-4 w-4" />
                            <span class="ml-1">
                                Explore
                            </span>
                        </Button>

                        <Button
                            v-if="ENABLE_PLAYLISTS"
                            :as="Link"
                            href="/playlists"
                            variant="outline"
                            class="rounded-full hidden md:inline-flex"
                        >
                            <PlaySquareIcon class="inline-block h-4 w-4" />
                            <span class="ml-1">
                                Playlists
                            </span>
                        </Button>

                        <div class="pl-4 ml-4 border-l hidden md:block">
                            <Button
                                v-if="$page.props.auth.user"
                                :as="Link"
                                href="/admin/dashboard"
                                variant="default"
                                class="hidden md:block ml-2"
                            >
                                <Home class="inline-block h-4 w-4 mr-1" />
                                Dashboard
                            </Button>
                            <Button
                                v-else
                                :as="Link"
                                href="/login"
                                variant="default"
                                class="hidden md:block ml-2"
                            >
                                <LogIn class="inline-block h-4 w-4 mr-1" />
                                Login
                            </Button>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Front Prepend -->
        <slot name="front-prepend" />

        <!-- Main Content -->
        <main class="container mx-auto">
            <slot />
        </main>

        <!-- Front Append -->
        <slot name="front-append" />

        <!-- Footer -->
        <footer class="border-t bg-card/50 py-8">
            <div class="container mx-auto px-4">
                <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <div class="w-[96px] flex items-center gap-2">
                        <AppLogoIcon
                            class="fill-current text-[var(--foreground)] dark:text-white"
                        />
                    </div>
                    <p class="text-sm text-muted-foreground">
                        © {{ new Date().getFullYear() }} Coderium. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
