import '../css/app.css';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { AuthProvider } from '@/context/AuthContext';
import { Toaster } from 'react-hot-toast';
import ProtectedRoute from '@/components/ProtectedRoute';
import GuestRoute from '@/components/GuestRoute';
import PermissionGate from '@/components/PermissionGate';
import DashboardLayout from '@/layouts/DashboardLayout';
import Login from '@/pages/auth/Login';
import Register from '@/pages/auth/Register';
import ForgotPassword from '@/pages/auth/ForgotPassword';
import Dashboard from '@/pages/Dashboard';
import Projects from '@/pages/projects/Projects';
import ProjectCreate from '@/pages/projects/ProjectCreate';
import ProjectDetail from '@/pages/projects/ProjectDetail';
import Tasks from '@/pages/tasks/Tasks';
import Clients from '@/pages/clients/Clients';
import Users from '@/pages/users/Users';
import FinanceOverview from '@/pages/finance/FinanceOverview';
import Invoices from '@/pages/finance/Invoices';
import InvoiceCreate from '@/pages/finance/InvoiceCreate';
import InvoiceDetail from '@/pages/finance/InvoiceDetail';
import Expenses from '@/pages/finance/Expenses';
import BudgetVsActual from '@/pages/finance/BudgetVsActual';
import MonthlySummary from '@/pages/finance/MonthlySummary';
import Safety from '@/pages/safety/Safety';
import Environmental from '@/pages/environmental/Environmental';
import Chat from '@/pages/chat/Chat';
import Email from '@/pages/email/Email';
import Unauthorized from '@/pages/Unauthorized';
import NotFound from '@/pages/NotFound';

function AppRoutes() {
    return (
        <Routes>
            {/* Guest Routes */}
            <Route element={<GuestRoute />}>
                <Route path="/login" element={<Login />} />
                <Route path="/register" element={<Register />} />
                <Route path="/forgot-password" element={<ForgotPassword />} />
            </Route>

            {/* Protected Routes */}
            <Route element={<ProtectedRoute />}>
                <Route element={<DashboardLayout />}>
                    {/* Dashboard — all authenticated users */}
                    <Route path="/" element={<Dashboard />} />
                    <Route path="/dashboard" element={<Dashboard />} />

                    {/* Projects — requires projects.view */}
                    <Route element={<PermissionGate permission="projects.view" />}>
                        <Route path="/projects" element={<Projects />} />
                        <Route path="/projects/create" element={<ProjectCreate />} />
                        <Route path="/projects/:id" element={<ProjectDetail />} />
                        <Route path="/projects/:id/edit" element={<ProjectCreate />} />
                    </Route>

                    {/* Tasks — requires tasks.view */}
                    <Route element={<PermissionGate permission="tasks.view" />}>
                        <Route path="/tasks" element={<Tasks />} />
                    </Route>

                    {/* Clients — requires clients.view */}
                    <Route element={<PermissionGate permission="clients.view" />}>
                        <Route path="/clients" element={<Clients />} />
                    </Route>

                    {/* Users — requires users.view */}
                    <Route element={<PermissionGate permission="users.view" />}>
                        <Route path="/users" element={<Users />} />
                    </Route>

                    {/* Finance — requires finance.view */}
                    <Route element={<PermissionGate permission="finance.view" />}>
                        <Route path="/finance" element={<FinanceOverview />} />
                        <Route path="/finance/invoices" element={<Invoices />} />
                        <Route path="/finance/invoices/create" element={<InvoiceCreate />} />
                        <Route path="/finance/invoices/:id" element={<InvoiceDetail />} />
                        <Route path="/finance/invoices/:id/edit" element={<InvoiceCreate />} />
                        <Route path="/finance/expenses" element={<Expenses />} />
                        <Route path="/finance/budget-vs-actual" element={<BudgetVsActual />} />
                        <Route path="/finance/monthly-summary" element={<MonthlySummary />} />
                    </Route>

                    {/* Safety — requires safety.view */}
                    <Route element={<PermissionGate permission="safety.view" />}>
                        <Route path="/safety" element={<Safety />} />
                    </Route>

                    {/* Environmental — requires environmental.view */}
                    <Route element={<PermissionGate permission="environmental.view" />}>
                        <Route path="/environmental" element={<Environmental />} />
                    </Route>

                    {/* Chat & Email — all authenticated users */}
                    <Route path="/chat" element={<Chat />} />
                    <Route path="/email" element={<Email />} />

                    {/* Unauthorized — accessible to all authenticated users */}
                    <Route path="/unauthorized" element={<Unauthorized />} />
                </Route>
            </Route>

            <Route path="*" element={<NotFound />} />
        </Routes>
    );
}

const container = document.getElementById('app');
const root = createRoot(container);

root.render(
    <BrowserRouter>
        <AuthProvider>
            <AppRoutes />
            <Toaster
                position="top-right"
                toastOptions={{
                    duration: 4000,
                    style: {
                        background: '#1f2937',
                        color: '#f9fafb',
                    },
                }}
            />
        </AuthProvider>
    </BrowserRouter>
);
