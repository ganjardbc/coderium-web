<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
</script>

<template>
    <div class="min-h-screen bg-background">
        <!-- Header -->
        <header class="border-b bg-card/50 backdrop-blur-sm sticky top-0 z-50">
            <div class="container mx-auto px-4 py-4">
                <div class="flex items-center justify-between">
                    <Link href="/" class="flex items-center gap-2">
                        <div class="flex items-center gap-2">
                            <svg class="h-8 w-8 text-primary" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="text-2xl font-bold">Coderium</span>
                        </div>
                    </Link>

                    <nav class="flex items-center gap-2">
                        <Button
                            :as="Link"
                            href="/search"
                            variant="outline"
                            class="rounded-full"
                        >
                            <Search class="inline-block h-4 w-4 mr-1" />
                            Search
                        </Button>

                        <Button
                            v-if="$page.props.auth.user"
                            :as="Link"
                            href="/admin/dashboard"
                            variant="default"
                            class="hidden md:block"
                        >
                            Dashboard
                        </Button>

                        <template v-else>
                            <Button
                                :as="Link"
                                href="/login"
                                variant="ghost"
                                class="text-sm hidden md:block"
                            >
                                Login
                            </Button>
                            <Button
                                :as="Link"
                                href="/register"
                                variant="default"
                                class="hidden md:block"
                            >
                                Get Started
                            </Button>
                        </template>
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
                    <div class="flex items-center gap-2">
                        <svg class="h-6 w-6 text-primary" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="font-semibold">Coderium</span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        © {{ new Date().getFullYear() }} Coderium. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
