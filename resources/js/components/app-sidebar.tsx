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
    const superAdmin = auth.roles.includes('Super Admin');
    if (superAdmin || permissions.includes('requests.submit')) items.push({ title: 'Katalog layanan', url: '/catalog', icon: GitBranch });
    if (superAdmin || permissions.some((p) => ['requests.view.own', 'requests.view.all'].includes(p)))
        items.push({ title: 'Pengajuan', url: '/requests', icon: LayoutGrid });
    if (permissions.includes('approvals.decide') && auth.roles.includes('Approver'))
        items.push({ title: 'Persetujuan', url: '/approvals', icon: Users });
    if (superAdmin || permissions.includes('monitoring.view')) items.push({ title: 'Monitoring', url: '/monitoring', icon: LayoutGrid });
    if (superAdmin || permissions.includes('reports.view')) items.push({ title: 'Laporan & audit', url: '/reports', icon: LayoutGrid });
    if (superAdmin || permissions.includes('notifications.view')) items.push({ title: 'Notifikasi', url: '/notifications', icon: LayoutGrid });
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
