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
} from 'react-icons/hi';
import toast from 'react-hot-toast';

const navigation = [
    { name: 'Dashboard', href: '/dashboard', icon: HiOutlineHome, permission: null },
    { name: 'Projects', href: '/projects', icon: HiOutlineBriefcase, permission: 'projects.view' },
    { name: 'Tasks', href: '/tasks', icon: HiOutlineClipboardList, permission: 'tasks.view' },
    { name: 'Clients', href: '/clients', icon: HiOutlineOfficeBuilding, permission: 'clients.view' },
    { name: 'Finance', href: '/finance', icon: HiOutlineCurrencyDollar, permission: 'finance.view' },
    { name: 'Safety', href: '/safety', icon: HiOutlineShieldCheck, permission: 'safety.view' },
    { name: 'Environmental', href: '/environmental', icon: HiOutlineGlobe, permission: 'environmental.view' },
    { name: 'Chat', href: '/chat', icon: HiOutlineChat, permission: null },
    { name: 'Email', href: '/email', icon: HiOutlineMail, permission: null },
    { name: 'Users', href: '/users', icon: HiOutlineUserGroup, permission: 'users.view' },
];

export default function DashboardLayout() {
    const { user, logout, can } = useAuth();
    const navigate = useNavigate();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);

    const handleLogout = async () => {
        try {
            await logout();
            toast.success('Logged out successfully');
            navigate('/login');
        } catch {
            toast.error('Logout failed');
        }
    };

    const navLinkClass = ({ isActive }) =>
        `group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
            isActive
                ? 'bg-accent-400/10 text-accent-400'
                : 'text-primary-300 hover:bg-white/5 hover:text-white'
        }`;

    // Filter navigation items by user permissions
    const visibleNavigation = navigation.filter(
        (item) => item.permission === null || can(item.permission)
    );

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
                className={`fixed inset-y-0 left-0 z-50 w-64 transform bg-primary-700 transition-transform duration-200 lg:translate-x-0 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex h-16 items-center justify-between border-b border-white/10 px-4">
                    <Logo variant="light" size={32} showText />
                    <button
                        onClick={() => setSidebarOpen(false)}
                        className="rounded-lg p-1 text-primary-400 hover:text-white lg:hidden"
                    >
                        <HiOutlineX className="h-6 w-6" />
                    </button>
                </div>

                <nav className="flex-1 space-y-1 p-4">
                    {visibleNavigation.map((item) => (
                        <NavLink
                            key={item.name}
                            to={item.href}
                            className={navLinkClass}
                            onClick={() => setSidebarOpen(false)}
                        >
                            <item.icon className="h-5 w-5 shrink-0" />
                            {item.name}
                        </NavLink>
                    ))}
                </nav>

                <div className="border-t border-white/10 p-4">
                    <div className="text-xs text-primary-400">
                        MGE-PMS
                    </div>
                    <div className="text-xs text-primary-400">
                        Project Management System
                    </div>
                </div>
            </aside>

            {/* Main content */}
            <div className="lg:pl-64">
                {/* Header */}
                <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-primary-200 bg-white px-4 shadow-sm lg:px-6">
                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="rounded-lg p-2 text-primary-400 hover:text-primary-600 lg:hidden"
                    >
                        <HiOutlineMenu className="h-6 w-6" />
                    </button>

                    <div className="flex-1" />

                    {/* Profile dropdown */}
                    <div className="relative">
                        <button
                            onClick={() => setProfileOpen(!profileOpen)}
                            className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-primary-500 hover:bg-primary-50"
                        >
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary-700 text-sm font-medium text-white">
                                {user?.first_name?.[0]}
                                {user?.last_name?.[0]}
                            </div>
                            <span className="hidden md:block">
                                {user?.full_name}
                            </span>
                            <HiOutlineChevronDown className="h-4 w-4" />
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
