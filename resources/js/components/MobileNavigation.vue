<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    GraduationCap,
    Home,
    LogIn,
    LogOut,
    Menu,
    Search,
    Settings,
    User,
} from 'lucide-vue-next';
import { ref } from 'vue';

const page = usePage();
const isOpen = ref(false);

const closeMenu = () => {
    isOpen.value = false;
};

const navigationItems = [
    {
        title: 'Home',
        href: '/',
        icon: Home,
    },
    {
        title: 'Learning Tracks',
        href: '/classroom/tracks',
        icon: GraduationCap,
    },
    {
        title: 'Explore',
        href: '/search',
        icon: Search,
    },
    {
        title: 'Playlists',
        href: '/playlists',
        icon: BookOpen,
    },
];

const userMenuItems = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
        icon: Home,
    },
    {
        title: 'Profile Settings',
        href: '/settings/profile',
        icon: Settings,
    },
];
</script>

<template>
    <div class="lg:hidden">
        <Sheet v-model:open="isOpen">
            <SheetTrigger as-child>
                <Button variant="ghost" size="icon" class="h-10 w-10">
                    <Menu class="h-5 w-5" />
                    <span class="sr-only">Open navigation menu</span>
                </Button>
            </SheetTrigger>
            <SheetContent side="left" class="w-[280px] p-0">
                <SheetHeader class="p-6 pb-4">
                    <SheetTitle class="flex items-center gap-2 text-left">
                        <AppLogoIcon class="h-6 w-6 fill-current" />
                        <span class="font-semibold">Coderium</span>
                    </SheetTitle>
                </SheetHeader>

                <div class="flex h-full flex-col">
                    <!-- Main Navigation -->
                    <nav class="flex-1 px-6">
                        <div class="space-y-1">
                            <Link
                                v-for="item in navigationItems"
                                :key="item.title"
                                :href="item.href"
                                @click="closeMenu"
                                class="flex touch-manipulation items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                                :class="
                                    page.url === item.href
                                        ? 'bg-accent text-accent-foreground'
                                        : 'text-muted-foreground'
                                "
                            >
                                <component :is="item.icon" class="h-5 w-5" />
                                {{ item.title }}
                            </Link>
                        </div>

                        <Separator class="my-6" />

                        <!-- User Section -->
                        <div v-if="page.props.auth.user" class="space-y-1">
                            <div class="px-3 py-2">
                                <p
                                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Account
                                </p>
                            </div>
                            <Link
                                v-for="item in userMenuItems"
                                :key="item.title"
                                :href="item.href"
                                @click="closeMenu"
                                class="flex touch-manipulation items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                                :class="
                                    page.url === item.href
                                        ? 'bg-accent text-accent-foreground'
                                        : 'text-muted-foreground'
                                "
                            >
                                <component :is="item.icon" class="h-5 w-5" />
                                {{ item.title }}
                            </Link>
                        </div>
                    </nav>

                    <!-- Bottom Section -->
                    <div class="p-6 pt-0">
                        <Separator class="mb-4" />

                        <!-- User Info -->
                        <div v-if="page.props.auth.user" class="mb-4">
                            <div
                                class="flex items-center gap-3 rounded-lg bg-muted/50 p-3"
                            >
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10"
                                >
                                    <User class="h-4 w-4 text-primary" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">
                                        {{ page.props.auth.user.name }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ page.props.auth.user.email }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Auth Actions -->
                        <div class="space-y-2">
                            <Button
                                v-if="page.props.auth.user"
                                :as="Link"
                                href="/logout"
                                method="post"
                                variant="outline"
                                class="h-12 w-full justify-start"
                                @click="closeMenu"
                            >
                                <LogOut class="mr-2 h-4 w-4" />
                                Sign Out
                            </Button>
                            <Button
                                v-else
                                :as="Link"
                                href="/login"
                                variant="default"
                                class="h-12 w-full justify-start"
                                @click="closeMenu"
                            >
                                <LogIn class="mr-2 h-4 w-4" />
                                Sign In
                            </Button>
                        </div>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    </div>
</template>
