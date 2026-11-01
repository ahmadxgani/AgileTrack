import { Link, usePage } from '@inertiajs/react';
import {
    CalendarRange,
    ClipboardCheck,
    FolderKanban,
    LayoutGrid,
    ListChecks,
    Settings,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as adminUsers } from '@/routes/admin/users';
import { edit as settings } from '@/routes/profile';
import { index as projects } from '@/routes/projects';
import { shortcut as backlogShortcut } from '@/routes/projects/backlog';
import { shortcut as reviewsShortcut } from '@/routes/projects/reviews';
import { shortcut as sprintsShortcut } from '@/routes/sprints';
import type { NavItem } from '@/types';

const workspaceItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Projects',
        href: projects(),
        icon: FolderKanban,
    },
    {
        title: 'Backlog',
        href: backlogShortcut(),
        icon: ListChecks,
        match: '/backlog',
    },
    {
        title: 'Sprints',
        href: sprintsShortcut(),
        icon: CalendarRange,
        match: '/sprints',
    },
    {
        title: 'Reviews',
        href: reviewsShortcut(),
        icon: ClipboardCheck,
        match: '/reviews',
    },
];

const accountItems: NavItem[] = [
    {
        title: 'Settings',
        href: settings(),
        icon: Settings,
        match: '/settings',
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;

    const adminItems: NavItem[] = auth.user?.is_administrator
        ? [
              {
                  title: 'Users',
                  href: adminUsers(),
                  icon: Users,
                  match: '/admin/users',
              },
          ]
        : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="group-data-[collapsible=icon]:p-0!"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo className="group-data-[collapsible=icon]:[&>span:last-child]:hidden" />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-4">
                <NavMain label="Workspace" items={workspaceItems} />
                {adminItems.length > 0 && (
                    <NavMain label="Administration" items={adminItems} />
                )}
                <NavMain
                    label="Account"
                    items={accountItems}
                    className="mt-auto"
                />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
