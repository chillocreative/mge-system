import { useState, useEffect } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import staffService from '@/services/staffService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlinePencil, HiOutlineArrowLeft } from 'react-icons/hi';

const statusColors = {
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-gray-100 text-gray-600',
    resigned: 'bg-red-100 text-red-700',
};

const labelize = (v) => (v ? String(v).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : '-');

const maritalStatusLabels = { single: 'Belum Berkahwin', married: 'Berkahwin', divorced: 'Bercerai' };

function Row({ label, value }) {
    return (
        <div className="flex justify-between gap-4 border-b border-gray-100 py-2 last:border-0">
            <span className="text-sm text-gray-500">{label}</span>
            <span className="text-right text-sm font-medium text-gray-900">{value || '-'}</span>
        </div>
    );
}

function Card({ title, children }) {
    return (
        <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <h2 className="mb-3 text-sm font-semibold uppercase text-gray-500">{title}</h2>
            {children}
        </div>
    );
}

export default function StaffDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { can } = useAuth();
    const [emp, setEmp] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        (async () => {
            setLoading(true);
            try {
                const res = await staffService.get(id);
                setEmp(res.data);
            } catch {
                toast.error('Failed to load staff member');
                navigate('/staff');
            } finally {
                setLoading(false);
            }
        })();
    }, [id]);

    if (loading) return <LoadingSpinner />;
    if (!emp) return null;

    return (
        <div className="mx-auto max-w-4xl">
            <div className="mb-6 flex items-center justify-between">
                <Link to="/staff" className="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                    <HiOutlineArrowLeft className="h-4 w-4" />
                    Back to Staff
                </Link>
                {can('staff.edit') && (
                    <Link
                        to={`/staff/${emp.id}/edit`}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePencil className="h-5 w-5" />
                        Edit
                    </Link>
                )}
            </div>

            {/* Header */}
            <div className="mb-6 flex flex-col items-center gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:flex-row sm:items-center">
                {emp.photo_path ? (
                    <img
                        src={staffService.getPhotoUrl(emp.id)}
                        alt={emp.full_name}
                        className="h-20 w-20 rounded-full object-cover ring-1 ring-gray-200"
                    />
                ) : (
                    <div className="flex h-20 w-20 items-center justify-center rounded-full bg-primary-100 text-2xl font-semibold text-primary-700">
                        {(emp.first_name?.[0] || '').toUpperCase()}
                    </div>
                )}
                <div className="text-center sm:text-left">
                    <h1 className="text-2xl font-bold text-gray-900">{emp.full_name}</h1>
                    <p className="text-sm text-gray-500">
                        {emp.designation?.name || 'No designation'}
                        {emp.department?.name ? ` · ${emp.department.name}` : ''}
                    </p>
                    <div className="mt-2 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{emp.employee_no}</span>
                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusColors[emp.status] || 'bg-gray-100 text-gray-600'}`}>
                            {emp.status}
                        </span>
                    </div>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card title="Particulars">
                    <Row label="IC / Passport No" value={emp.ic_passport_no} />
                    <Row label="Email" value={emp.email} />
                    <Row label="Phone" value={emp.phone} />
                    <Row label="Gender" value={labelize(emp.gender)} />
                    <Row label="Date of Birth" value={emp.dob} />
                    <Row label="Address" value={emp.address} />
                </Card>

                <Card title="Employment">
                    <Row label="Department" value={emp.department?.name} />
                    <Row label="Designation" value={emp.designation?.name} />
                    <Row label="Employment Type" value={labelize(emp.employment_type)} />
                    <Row label="Category" value={labelize(emp.category)} />
                    <Row label="Reporting Manager" value={emp.manager?.full_name} />
                    <Row label="Hire Date" value={emp.hire_date} />
                    <Row label="Resign Date" value={emp.resign_date} />
                    <Row
                        label="Base Salary"
                        value={emp.base_salary ? `RM ${Number(emp.base_salary).toLocaleString('en-MY', { minimumFractionDigits: 2 })}` : null}
                    />
                </Card>

                <Card title="Family & Emergency Contact">
                    <Row label="Marital Status" value={emp.marital_status ? maritalStatusLabels[emp.marital_status] : null} />
                    {emp.marital_status === 'married' && (
                        <>
                            <Row label="Spouse Name" value={emp.spouse_name} />
                            <Row label="Spouse IC No" value={emp.spouse_ic_no} />
                        </>
                    )}
                    <Row label="Number of Children" value={emp.number_of_children ?? null} />
                    <Row label="Emergency Contact Name" value={emp.emergency_contact_name} />
                    <Row label="Emergency Contact Phone" value={emp.emergency_contact_phone} />
                    <Row label="Relationship" value={emp.emergency_contact_relationship} />
                </Card>

                <Card title="Bank & Statutory">
                    <Row label="Bank Name" value={emp.bank_name} />
                    <Row label="Bank Account No" value={emp.bank_account_no} />
                    <Row label="EPF No" value={emp.epf_no} />
                    <Row label="SOCSO No" value={emp.socso_no} />
                    <Row label="Tax No" value={emp.tax_no} />
                </Card>

                <Card title="Login Account">
                    {emp.user ? (
                        <>
                            <Row label="Linked User" value={`${emp.user.first_name ?? ''} ${emp.user.last_name ?? ''}`.trim() || emp.user.email} />
                            <Row label="Email" value={emp.user.email} />
                            <p className="mt-2 text-xs text-green-600">This staff can use Leave &amp; Training self-service.</p>
                        </>
                    ) : (
                        <div className="py-2">
                            <p className="text-sm text-gray-500">No login account linked.</p>
                            <p className="mt-1 text-xs text-gray-400">
                                {can('staff.edit')
                                    ? 'Use Edit → Login Account to link or create one (needed for Leave & Training self-service).'
                                    : 'Ask HR to link a login (needed for Leave & Training self-service).'}
                            </p>
                        </div>
                    )}
                </Card>
            </div>
        </div>
    );
}
