<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpenIcon,
    CompassIcon,
    HomeIcon,
    LayoutDashboardIcon,
    LogIn,
    PlaySquareIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';

const ENABLE_DARK_MODE = true;
const ENABLE_EXPLORE = true;
const ENABLE_PLAYLISTS = true;
const ENABLE_COURSE = true;

const $page = usePage();
const isAuth = computed(() => {
    return $page.props.auth.user || false;
});

const listOfMenus = computed(() => {
    const menus = [
        {
            label: 'Home',
            icon: HomeIcon,
            route: '/',
            isVisible: true,
        },
        {
            label: 'Explore',
            icon: CompassIcon,
            route: '/explore',
            isVisible: ENABLE_EXPLORE,
        },
        {
            label: 'Playlist',
            icon: PlaySquareIcon,
            route: '/playlists',
            isVisible: ENABLE_PLAYLISTS,
        },
        {
            label: 'Course',
            icon: BookOpenIcon,
            route: '/courses',
            isVisible: ENABLE_COURSE,
        },
    ];

    return menus.filter((item) => item.isVisible);
});
</script>

<template>
    <div class="layout">
        <!-- Sidebar -->
        <div class="layout__grid">
            <div class="layout__sidebar">
                <div class="layout__sidebar-inner">
                    <div class="layout__sidebar-header">
                        <Link href="/" class="flex items-center gap-2">
                            <div class="layout__logo-wrapper">
                                <AppLogoIcon class="layout__logo" />
                            </div>
                        </Link>
                    </div>
                    <div class="layout__sidebar-content">
                        <div class="layout__menu">
                            <Link
                                v-for="(menu, i) in listOfMenus"
                                :key="i"
                                :href="menu.route"
                                :class="[
                                    'layout__menu-item',
                                    $page.url === menu.route ||
                                    $page.url.startsWith(menu.route + '/')
                                        ? 'layout__menu-item--active'
                                        : 'layout__menu-item--inactive',
                                ]"
                            >
                                <div class="layout__menu-icon">
                                    <component
                                        :is="menu.icon"
                                        style="width: 20px"
                                    />
                                </div>
                                <div class="layout__menu-label">
                                    {{ menu.label }}
                                </div>
                            </Link>
                        </div>
                    </div>
                    <div class="layout__sidebar-footer">
                        <ThemeToggle v-if="ENABLE_DARK_MODE" />
                        <Button
                            v-if="isAuth"
                            :as="Link"
                            href="/admin/dashboard"
                            variant="default"
                            class="h-[44px] w-[44px] rounded-full !px-3"
                        >
                            <LayoutDashboardIcon class="inline-block h-4 w-4" />
                        </Button>
                        <Button
                            v-else
                            :as="Link"
                            href="/login"
                            variant="default"
                            class="h-[44px] w-[44px] rounded-full !px-3"
                        >
                            <LogIn class="inline-block h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="layout__main">
                <!-- Front Prepend -->
                <slot name="front-prepend" />

                <!-- Main Content -->
                <main class="layout__content">
                    <slot />
                </main>

                <!-- Front Append -->
                <slot name="front-append" />

                <!-- <footer class="layout__footer">
                    <div class="layout__footer-content">
                        <div class="layout__footer-text">
                            © {{ new Date().getFullYear() }} Coderium. All
                            rights reserved.
                        </div>
                    </div>
                </footer> -->
            </div>
        </div>
    </div>
</template>
