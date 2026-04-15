<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { FileText, Home, LayoutGrid, List, BookOpen } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Posts',
        href: '/admin/posts',
        icon: FileText,
    },
    {
        title: 'Playlists',
        href: '/admin/playlists',
        icon: List,
    },
    {
        title: 'Modules',
        href: '/admin/modules',
        icon: LayoutGrid,
    },
    {
        title: 'Courses',
        href: '/admin/courses',
        icon: BookOpen,
    },
    // {
    //     title: 'Lessons',
    //     href: '/admin/lessons',
    //     icon: BookOpen,
    // },
    // {
    //     title: 'Assessments',
    //     href: '/admin/assessments',
    //     icon: FileText,
    // },
    // {
    //     title: 'Tracks',
    //     href: '/admin/tracks',
    //     icon: GraduationCap,
    // },
    // {
    //     title: 'Classroom',
    //     href: '/admin/classroom',
    //     icon: GraduationCap,
    //     items: [
    //         {
    //             title: 'Overview',
    //             href: '/admin/classroom',
    //             icon: LayoutGrid,
    //         },
    //         {
    //             title: 'Tracks',
    //             href: '/admin/classroom/tracks',
    //             icon: BookOpen,
    //         },
    //         // {
    //         //     title: 'Courses',
    //         //     href: '/admin/classroom/courses',
    //         //     icon: GraduationCap,
    //         // },
    //         // {
    //         //     title: 'Modules',
    //         //     href: '/admin/classroom/modules',
    //         //     icon: FileText,
    //         // },
    //         {
    //             title: 'Assignments',
    //             href: '/admin/classroom/assignments',
    //             icon: Users,
    //         },
    //         {
    //             title: 'Levels',
    //             href: '/admin/classroom/levels',
    //             icon: Users,
    //         },
    //         // {
    //         //     title: 'Lessons',
    //         //     href: '/admin/classroom/lessons',
    //         //     icon: BookOpen,
    //         // },
    //         // {
    //         //     title: 'Assessments',
    //         //     href: '/admin/classroom/assessments',
    //         //     icon: Award,
    //         // },
    //         {
    //             title: 'Progress',
    //             href: '/admin/classroom/progress',
    //             icon: LayoutGrid,
    //         },
    //         {
    //             title: 'Certificates',
    //             href: '/admin/classroom/certificates',
    //             icon: Award,
    //         },
    //     ]
    // }
];

const footerNavItems: NavItem[] = [
    {
        title: 'Landing Page',
        href: '/',
        icon: Home,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/admin/dashboard">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
