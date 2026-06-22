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
import Milestones from '@/pages/projects/milestones/Milestones';
import ProjectInvoices from '@/pages/projects/invoices/ProjectInvoices';
import Correspondence from '@/pages/projects/correspondence/Correspondence';
import Discussions from '@/pages/projects/discussions/Discussions';
import Contracts from '@/pages/projects/contracts/Contracts';
import Clients from '@/pages/clients/Clients';
import Users from '@/pages/users/Users';
import Roles from '@/pages/roles/Roles';
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
import StaffList from '@/pages/staff/StaffList';
import StaffForm from '@/pages/staff/StaffForm';
import StaffDetail from '@/pages/staff/StaffDetail';
import Attendance from '@/pages/hr/attendance/Attendance';
import LeaveList from '@/pages/hr/leave/LeaveList';
import LeaveRequestForm from '@/pages/hr/leave/LeaveRequestForm';
import LeaveApproval from '@/pages/hr/leave/LeaveApproval';
import LeaveBalance from '@/pages/hr/leave/LeaveBalance';
import MyLeave from '@/pages/hr/leave/MyLeave';
import Training from '@/pages/hr/training/Training';
import MyTraining from '@/pages/hr/training/MyTraining';
import Notifications from '@/pages/notifications/Notifications';
import Calendar from '@/pages/hr/calendar/Calendar';
import PayrollList from '@/pages/hr/payroll/PayrollList';
import PayslipDetail from '@/pages/hr/payroll/PayslipDetail';
import EaForm from '@/pages/hr/payroll/EaForm';
import Vehicles from '@/pages/assets/Vehicles';
import VehicleDetail from '@/pages/assets/VehicleDetail';
import Inventory from '@/pages/assets/Inventory';
import ItemDetail from '@/pages/assets/ItemDetail';
import Maintenance from '@/pages/assets/Maintenance';
import Meetings from '@/pages/meetings/Meetings';
import MeetingForm from '@/pages/meetings/MeetingForm';
import MeetingDetail from '@/pages/meetings/MeetingDetail';
import CompanyDocuments from '@/pages/documents/CompanyDocuments';
import Drawings from '@/pages/documents/Drawings';
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

                    {/* Notifications — all authenticated users */}
                    <Route path="/notifications" element={<Notifications />} />

                    {/* Projects — requires projects.view */}
                    <Route element={<PermissionGate permission="projects.view" />}>
                        <Route path="/projects" element={<Projects />} />
                        <Route path="/projects/create" element={<ProjectCreate />} />
                        <Route path="/projects/milestones" element={<Milestones />} />
                        <Route path="/projects/invoices" element={<ProjectInvoices />} />
                        <Route path="/projects/correspondence" element={<Correspondence />} />
                        <Route path="/projects/discussions" element={<Discussions />} />
                        <Route path="/projects/contracts" element={<Contracts />} />
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

                    {/* Roles & Permissions — requires roles.view */}
                    <Route element={<PermissionGate permission="roles.view" />}>
                        <Route path="/roles" element={<Roles />} />
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

                    {/* Staff — requires staff.view */}
                    <Route element={<PermissionGate permission="staff.view" />}>
                        <Route path="/staff" element={<StaffList />} />
                        <Route path="/staff/create" element={<StaffForm />} />
                        <Route path="/staff/:id" element={<StaffDetail />} />
                        <Route path="/staff/:id/edit" element={<StaffForm />} />
                    </Route>

                    {/* HR — Attendance */}
                    <Route element={<PermissionGate permission="attendance.view" />}>
                        <Route path="/hr/attendance" element={<Attendance />} />
                    </Route>

                    {/* HR — Leave: self-service (any requester) */}
                    <Route element={<PermissionGate permission="leave.request" />}>
                        <Route path="/leave/my" element={<MyLeave />} />
                        <Route path="/hr/leave/apply" element={<LeaveRequestForm />} />
                    </Route>

                    {/* HR — Leave: approvals (designated approvers) */}
                    <Route element={<PermissionGate permission="leave.view" />}>
                        <Route path="/hr/leave/approvals" element={<LeaveApproval />} />
                    </Route>

                    {/* HR — Leave: org-wide management (managers/HR only) */}
                    <Route element={<PermissionGate permission="leave.manage" />}>
                        <Route path="/hr/leave" element={<LeaveList />} />
                        <Route path="/hr/leave/balances" element={<LeaveBalance />} />
                    </Route>

                    {/* HR — Training */}
                    <Route element={<PermissionGate permission="training.request" />}>
                        <Route path="/training/my" element={<MyTraining />} />
                    </Route>
                    <Route element={<PermissionGate permission="training.view" />}>
                        <Route path="/hr/training" element={<Training />} />
                    </Route>

                    {/* HR — Calendar */}
                    <Route element={<PermissionGate permission="calendar.view" />}>
                        <Route path="/hr/calendar" element={<Calendar />} />
                    </Route>

                    {/* HR — Payroll */}
                    <Route element={<PermissionGate permission="payroll.view" />}>
                        <Route path="/hr/payroll" element={<PayrollList />} />
                        <Route path="/hr/payroll/ea-form" element={<EaForm />} />
                        <Route path="/hr/payroll/:id" element={<PayslipDetail />} />
                    </Route>

                    {/* Assets — Vehicles / Inventory / Maintenance */}
                    <Route element={<PermissionGate permission="assets.view" />}>
                        <Route path="/assets/vehicles" element={<Vehicles />} />
                        <Route path="/assets/vehicles/:id" element={<VehicleDetail />} />
                    </Route>
                    <Route element={<PermissionGate permission="inventory.view" />}>
                        <Route path="/assets/inventory" element={<Inventory />} />
                        <Route path="/assets/inventory/:id" element={<ItemDetail />} />
                    </Route>
                    <Route element={<PermissionGate permission="maintenance.view" />}>
                        <Route path="/assets/maintenance" element={<Maintenance />} />
                    </Route>

                    {/* Meeting Minutes */}
                    <Route element={<PermissionGate permission="meetings.view" />}>
                        <Route path="/meetings" element={<Meetings />} />
                        <Route path="/meetings/create" element={<MeetingForm />} />
                        <Route path="/meetings/:id" element={<MeetingDetail />} />
                        <Route path="/meetings/:id/edit" element={<MeetingForm />} />
                    </Route>

                    {/* Documents library */}
                    <Route element={<PermissionGate permission="documents.view" />}>
                        <Route path="/documents/company" element={<CompanyDocuments />} />
                    </Route>
                    <Route element={<PermissionGate permission="drawings.view" />}>
                        <Route path="/projects/drawings" element={<Drawings />} />
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
