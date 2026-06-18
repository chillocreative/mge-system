import { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import attendanceService from '@/services/attendanceService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineUpload, HiOutlineSearch, HiOutlineClock, HiOutlineTrash } from 'react-icons/hi';

const statusColors = {
    present: 'bg-green-100 text-green-700',
    late: 'bg-yellow-100 text-yellow-700',
    absent: 'bg-red-100 text-red-700',
    half_day: 'bg-blue-100 text-blue-700',
};

export default function Attendance() {
    const { can } = useAuth();
    const [records, setRecords] = useState([]);
    const [loading, setLoading] = useState(true);
    const [pagination, setPagination] = useState({});
    const [statusFilter, setStatusFilter] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [showUpload, setShowUpload] = useState(false);
    const [file, setFile] = useState(null);
    const [uploading, setUploading] = useState(false);
    const [uploadResult, setUploadResult] = useState(null);

    const fetchRecords = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (statusFilter) params.status = statusFilter;
            if (dateFrom) params.date_from = dateFrom;
            if (dateTo) params.date_to = dateTo;
            const res = await attendanceService.list(params);
            setRecords(res.data?.data || []);
            setPagination(res.data || {});
        } catch {
            setRecords([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const t = setTimeout(() => fetchRecords(), 300);
        return () => clearTimeout(t);
    }, [statusFilter, dateFrom, dateTo]);

    const handleUpload = async (e) => {
        e.preventDefault();
        if (!file) return;
        setUploading(true);
        setUploadResult(null);
        try {
            const fd = new FormData();
            fd.append('file', file);
            const res = await attendanceService.upload(fd);
            setUploadResult(res.data || res);
            toast.success(res.message || 'Attendance uploaded');
            fetchRecords();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Upload failed');
            if (err.response?.data?.data) setUploadResult(err.response.data.data);
        } finally {
            setUploading(false);
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Attendance</h1>
                    <p className="text-sm text-gray-500">Daily attendance records (upload via Excel)</p>
                </div>
                {can('attendance.upload') && (
                    <button
                        onClick={() => { setShowUpload(true); setUploadResult(null); }}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlineUpload className="h-5 w-5" />
                        Upload Attendance
                    </button>
                )}
            </div>

            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">All Statuses</option>
                    <option value="present">Present</option>
                    <option value="late">Late</option>
                    <option value="absent">Absent</option>
                    <option value="half_day">Half Day</option>
                </select>
                <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : records.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineClock className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No attendance records found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Employee</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Clock In</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Clock Out</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Hours</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {records.map((r) => (
                                    <tr key={r.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">
                                            {r.user ? `${r.user.first_name} ${r.user.last_name || ''}` : (r.employee?.full_name || `#${r.user_id}`)}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.date}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.clock_in ? new Date(r.clock_in).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '-'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.clock_out ? new Date(r.clock_out).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '-'}</td>
                                        <td className="px-4 py-3 text-right text-sm text-gray-600">{r.working_hours}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[r.status] || 'bg-gray-100 text-gray-600'}`}>
                                                {String(r.status).replace('_', ' ')}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {pagination.last_page > 1 && (
                        <div className="flex items-center justify-between border-t px-4 py-3">
                            <p className="text-sm text-gray-500">Showing {pagination.from}-{pagination.to} of {pagination.total}</p>
                            <div className="flex gap-1">
                                {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((page) => (
                                    <button key={page} onClick={() => fetchRecords(page)}
                                        className={`rounded px-3 py-1 text-sm ${page === pagination.current_page ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}>
                                        {page}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {showUpload && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowUpload(false)}>
                    <div className="mx-4 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-1 text-lg font-semibold text-gray-900">Upload Attendance</h3>
                        <p className="mb-4 text-xs text-gray-500">Excel file with columns: employee_id (or email), date, clock_in, clock_out.</p>
                        <form onSubmit={handleUpload} className="space-y-4">
                            <input type="file" accept=".xlsx,.xls,.csv" onChange={(e) => setFile(e.target.files[0] || null)} required
                                className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100" />
                            {uploadResult && (
                                <div className="max-h-40 overflow-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                                    <p>Imported: {uploadResult.imported ?? uploadResult.created ?? 0}</p>
                                    {(uploadResult.errors?.length > 0) && (
                                        <ul className="mt-1 list-disc pl-4 text-red-600">
                                            {uploadResult.errors.slice(0, 10).map((er, i) => <li key={i}>{typeof er === 'string' ? er : er.message}</li>)}
                                        </ul>
                                    )}
                                </div>
                            )}
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowUpload(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
                                <button type="submit" disabled={uploading} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                    {uploading ? 'Uploading...' : 'Upload'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
