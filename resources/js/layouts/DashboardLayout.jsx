import { useState } from 'react';
import { Outlet, NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import Logo from '@/components/Logo';
import {
    HiOutlineHome,
    HiOutlineBriefcase,
    HiOutlineClipboardList,
    HiOutlineUserGroup,
    HiOutlineOfficeBuilding,
    HiOutlineCurrencyDollar,
    HiOutlineShieldCheck,
    HiOutlineGlobe,
    HiOutlineChat,
    HiOutlineMail,
    HiOutlineMenu,
    HiOutlineX,
    HiOutlineLogout,
    HiOutlineChevronDown,
    HiOutlineIdentification,
    HiOutlineCalendar,
    HiOutlineClock,
    HiOutlineCash,
    HiOutlineClipboardCheck,
    HiOutlineTruck,
    HiOutlineCube,
    HiOutlineCog,
    HiOutlineDocumentText,
    HiOutlineFolderOpen,
    HiOutlineUsers,
    HiOutlineChevronDoubleLeft,
    HiOutlineFlag,
    HiOutlineDocumentDuplicate,
    HiOutlineChatAlt2,
    HiOutlineKey,
} from 'react-icons/hi';
import toast from 'react-hot-toast';

const navigation = [
    { name: 'Dashboard', href: '/dashboard', icon: HiOutlineHome, permission: null },
    {
        name: 'Projects', icon: HiOutlineBriefcase,
        children: [
            { name: 'All Projects', href: '/projects', icon: HiOutlineBriefcase, permission: 'projects.view' },
            { name: 'Tasks', href: '/tasks', icon: HiOutlineClipboardList, permission: 'tasks.view' },
            { name: 'Milestones', href: '/projects/milestones', icon: HiOutlineFlag, permission: 'projects.view' },
            { name: 'Invoices', href: '/projects/invoices', icon: HiOutlineDocumentText, permission: 'projects.view' },
            { name: 'Correspondence', href: '/projects/correspondence', icon: HiOutlineDocumentDuplicate, permission: 'projects.view' },
            { name: 'Discussions', href: '/projects/discussions', icon: HiOutlineChatAlt2, permission: 'projects.view' },
            { name: 'Contracts', href: '/projects/contracts', icon: HiOutlineClipboardCheck, permission: 'projects.view' },
            { name: 'Drawings', href: '/projects/drawings', icon: HiOutlineFolderOpen, permission: 'drawings.view' },
        ],
    },
    { name: 'Clients', href: '/clients', icon: HiOutlineOfficeBuilding, permission: 'clients.view' },
    { name: 'Finance', href: '/finance', icon: HiOutlineCurrencyDollar, permission: 'finance.view' },
    {
        name: 'HR', icon: HiOutlineUsers,
        children: [
            { name: 'Staff', href: '/staff', icon: HiOutlineIdentification, permission: 'staff.view' },
            { name: 'Attendance', href: '/hr/attendance', icon: HiOutlineClock, permission: 'attendance.view' },
            { name: 'My Leave', href: '/leave/my', icon: HiOutlineCalendar, permission: 'leave.request' },
            { name: 'Leave (All)', href: '/hr/leave', icon: HiOutlineClipboardCheck, permission: 'leave.manage' },
            { name: 'Payroll', href: '/hr/payroll', icon: HiOutlineCash, permission: 'payroll.view' },
            { name: 'EA Form', href: '/hr/payroll/ea-form', icon: HiOutlineDocumentText, permission: 'payroll.ea-form' },
            { name: 'Calendar', href: '/hr/calendar', icon: HiOutlineCalendar, permission: 'calendar.view' },
        ],
    },
    {
        name: 'Assets', icon: HiOutlineCube,
        children: [
            { name: 'Vehicles', href: '/assets/vehicles', icon: HiOutlineTruck, permission: 'assets.view' },
            { name: 'Inventory', href: '/assets/inventory', icon: HiOutlineCube, permission: 'inventory.view' },
            { name: 'Maintenance', href: '/assets/maintenance', icon: HiOutlineCog, permission: 'maintenance.view' },
        ],
    },
    { name: 'Meetings', href: '/meetings', icon: HiOutlineClipboardList, permission: 'meetings.view' },
    {
        name: 'Documents', icon: HiOutlineFolderOpen,
        children: [
            { name: 'Company Docs', href: '/documents/company', icon: HiOutlineDocumentText, permission: 'documents.view' },
        ],
    },
    { name: 'Safety', href: '/safety', icon: HiOutlineShieldCheck, permission: 'safety.view' },
    { name: 'Environmental', href: '/environmental', icon: HiOutlineGlobe, permission: 'environmental.view' },
    { name: 'Chat', href: '/chat', icon: HiOutlineChat, permission: null },
    { name: 'Email', href: '/email', icon: HiOutlineMail, permission: null },
    { name: 'Users', href: '/users', icon: HiOutlineUserGroup, permission: 'users.view' },
    { name: 'Roles & Permissions', href: '/roles', icon: HiOutlineKey, permission: 'roles.view' },
];

export default function DashboardLayout() {
    const { user, logout, can } = useAuth();
    const navigate = useNavigate();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const [openGroups, setOpenGroups] = useState({});
    const [collapsed, setCollapsed] = useState(() => {
        try { return localStorage.getItem('sidebarCollapsed') === '1'; } catch { return false; }
    });

    const toggleGroup = (name) => setOpenGroups((p) => ({ ...p, [name]: !p[name] }));
    const toggleCollapsed = () => setCollapsed((c) => {
        const next = !c;
        try { localStorage.setItem('sidebarCollapsed', next ? '1' : '0'); } catch { /* ignore */ }
        return next;
    });

    const handleLogout = async () => {
        try {
            await logout();
            toast.success('Logged out successfully');
            navigate('/login');
        } catch {
            toast.error('Logout failed');
        }
    };

    // Filter navigation items by user permissions
    const visibleNavigation = navigation
        .map((item) => {
            if (item.children) {
                const children = item.children.filter(
                    (c) => c.permission == null || can(c.permission)
                );
                return children.length ? { ...item, children } : null;
            }
            return item.permission == null || can(item.permission) ? item : null;
        })
        .filter(Boolean);

    return (
        <div className="min-h-screen bg-primary-100">
            {/* Mobile sidebar overlay */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar — Dark Navy */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 flex transform flex-col bg-primary-700 transition-all duration-200 lg:translate-x-0 w-64 ${
                    collapsed ? 'lg:w-20' : 'lg:w-64'
                } ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}
            >
                <div className={`flex h-20 shrink-0 items-center justify-between border-b border-white/10 px-4 ${collapsed ? 'lg:px-2' : ''}`}>
                    <div className={`flex flex-col ${collapsed ? 'lg:hidden' : ''}`}>
                        <Logo variant="light" size={32} showText />
                        <span className="ml-10 text-[10px] font-medium tracking-widest text-primary-400 uppercase">Project Management System</span>
                    </div>
                    {collapsed && (
                        <div className="mx-auto hidden lg:block">
                            <Logo variant="light" size={32} />
                        </div>
                    )}
                    {/* Desktop collapse toggle */}
                    <button
                        onClick={toggleCollapsed}
                        title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        className={`hidden rounded-lg p-1 text-primary-400 hover:bg-white/5 hover:text-white lg:block ${collapsed ? 'lg:absolute lg:right-1 lg:top-2' : ''}`}
                    >
                        <HiOutlineChevronDoubleLeft className={`h-5 w-5 transition-transform ${collapsed ? 'rotate-180' : ''}`} />
                    </button>
                    {/* Mobile close */}
                    <button
                        onClick={() => setSidebarOpen(false)}
                        className="rounded-lg p-1 text-primary-400 hover:text-white lg:hidden"
                    >
                        <HiOutlineX className="h-6 w-6" />
                    </button>
                </div>

                <nav className="min-h-0 flex-1 space-y-1 overflow-y-auto p-3">
                    {visibleNavigation.map((item) => {
                        if (item.children) {
                            const isOpen = !!openGroups[item.name];
                            return (
                                <div key={item.name}>
                                    <button
                                        type="button"
                                        onClick={() => { if (collapsed) setCollapsed(false); toggleGroup(item.name); }}
                                        title={collapsed ? item.name : undefined}
                                        className={`group flex w-full items-center rounded-lg px-3 py-2.5 text-sm font-medium text-primary-300 transition-colors hover:bg-white/5 hover:text-white ${collapsed ? 'lg:justify-center' : 'justify-between gap-3'}`}
                                    >
                                        <span className="flex items-center gap-3">
                                            <item.icon className="h-5 w-5 shrink-0" />
                                            <span className={collapsed ? 'lg:hidden' : ''}>{item.name}</span>
                                        </span>
                                        <HiOutlineChevronDown className={`h-4 w-4 transition-transform ${isOpen ? 'rotate-180' : ''} ${collapsed ? 'lg:hidden' : ''}`} />
                                    </button>
                                    {isOpen && !collapsed && (
                                        <div className="mt-1 ml-3 space-y-1 border-l border-white/10 pl-3">
                                            {item.children.map((child) => (
                                                <NavLink
                                                    key={child.name}
                                                    to={child.href}
                                                    end
                                                    className={({ isActive }) =>
                                                        `flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-medium transition-colors ${
                                                            isActive
                                                                ? 'bg-accent-400/10 text-accent-400'
                                                                : 'text-primary-400 hover:bg-white/5 hover:text-white'
                                                        }`
                                                    }
                                                    onClick={() => setSidebarOpen(false)}
                                                >
                                                    <child.icon className="h-4 w-4 shrink-0" />
                                                    {child.name}
                                                </NavLink>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            );
                        }
                        return (
                            <NavLink
                                key={item.name}
                                to={item.href}
                                title={collapsed ? item.name : undefined}
                                className={({ isActive }) =>
                                    `group flex items-center rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${collapsed ? 'lg:justify-center gap-3' : 'gap-3'} ${
                                        isActive
                                            ? 'bg-accent-400/10 text-accent-400'
                                            : 'text-primary-300 hover:bg-white/5 hover:text-white'
                                    }`
                                }
                                onClick={() => setSidebarOpen(false)}
                            >
                                <item.icon className="h-5 w-5 shrink-0" />
                                <span className={collapsed ? 'lg:hidden' : ''}>{item.name}</span>
                            </NavLink>
                        );
                    })}
                </nav>
            </aside>

            {/* Main content */}
            <div className={`transition-all duration-200 ${collapsed ? 'lg:pl-20' : 'lg:pl-64'}`}>
                {/* Header */}
                <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-white/10 bg-gradient-to-r from-primary-700 to-primary-800 px-4 shadow-sm shadow-black/10 lg:px-6">
                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="rounded-lg p-2 text-white/70 hover:bg-white/10 hover:text-white lg:hidden"
                    >
                        <HiOutlineMenu className="h-6 w-6" />
                    </button>

                    <div className="flex-1" />

                    {/* Profile dropdown */}
                    <div className="relative">
                        <button
                            onClick={() => setProfileOpen(!profileOpen)}
                            className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-white/90 transition-colors hover:bg-white/10"
                        >
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-accent-400 text-sm font-bold text-primary-900">
                                {user?.first_name?.[0]}
                                {user?.last_name?.[0]}
                            </div>
                            <span className="hidden md:block">
                                {user?.full_name}
                            </span>
                            <HiOutlineChevronDown className="h-4 w-4 text-white/60" />
                        </button>

                        {profileOpen && (
                            <>
                                <div
                                    className="fixed inset-0 z-40"
                                    onClick={() => setProfileOpen(false)}
                                />
                                <div className="absolute right-0 z-50 mt-1 w-56 rounded-lg border border-primary-200 bg-white py-1 shadow-lg">
                                    <div className="border-b border-primary-200 px-4 py-3">
                                        <p className="text-sm font-medium text-primary-700">
                                            {user?.full_name}
                                        </p>
                                        <p className="text-xs text-primary-400">
                                            {user?.email}
                                        </p>
                                        {user?.roles?.[0] && (
                                            <span className="mt-1 inline-block rounded-full bg-accent-400/10 px-2 py-0.5 text-xs font-medium text-accent-600">
                                                {user.roles[0]}
                                            </span>
                                        )}
                                    </div>
                                    <button
                                        onClick={handleLogout}
                                        className="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                                    >
                                        <HiOutlineLogout className="h-4 w-4" />
                                        Sign out
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                </header>

                {/* Page content */}
                <main className="p-4 lg:p-6">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
