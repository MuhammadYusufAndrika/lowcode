import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Building2, GitBranch, LayoutGrid, Settings, Users } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const permissions = auth.permissions ?? [];
    const items: NavItem[] = [{ title: 'Dasbor', url: '/dashboard', icon: LayoutGrid }];
    if (permissions.some((permission) => ['workflows.view.all', 'workflows.view.own'].includes(permission)))
        items.push({ title: 'Workflow', url: '/workflows', icon: GitBranch });
    if (permissions.includes('users.manage')) items.push({ title: 'Pengguna & peran', url: '/users', icon: Users });
    if (permissions.includes('departments.manage')) items.push({ title: 'Departemen', url: '/departments', icon: Building2 });
    items.push({ title: 'Pengaturan', url: '/settings/profile', icon: Settings });
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
