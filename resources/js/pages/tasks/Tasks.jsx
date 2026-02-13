import { useState, useEffect } from 'react';
import taskService from '@/services/taskService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { HiOutlinePlus, HiOutlineClipboardList } from 'react-icons/hi';

const statusColors = {
    pending: 'bg-gray-100 text-gray-700',
    in_progress: 'bg-yellow-100 text-yellow-700',
    in_review: 'bg-purple-100 text-purple-700',
    completed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
};

const priorityColors = {
    low: 'bg-gray-100 text-gray-600',
    medium: 'bg-blue-100 text-blue-600',
    high: 'bg-orange-100 text-orange-600',
    critical: 'bg-red-100 text-red-600',
};

export default function Tasks() {
    const [tasks, setTasks] = useState([]);
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState('');

    const fetchTasks = async () => {
        setLoading(true);
        try {
            const params = {};
            if (statusFilter) params.status = statusFilter;
            const response = await taskService.list(params);
            setTasks(response.data?.data || []);
        } catch {
            setTasks([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchTasks();
    }, [statusFilter]);

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">My Tasks</h1>
                    <p className="text-sm text-gray-500">Tasks assigned to you</p>
                </div>
                <button className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                    <HiOutlinePlus className="h-5 w-5" />
                    New Task
                </button>
            </div>

            <div className="mb-6">
                <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="in_review">In Review</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : tasks.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineClipboardList className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No tasks found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Task</th>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Project</th>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Priority</th>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Due Date</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {tasks.map((task) => (
                                <tr key={task.id} className="hover:bg-gray-50">
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-medium text-gray-900">{task.title}</p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="text-sm text-gray-600">
                                            {task.project?.name || '-'}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${priorityColors[task.priority]}`}>
                                            {task.priority}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[task.status]}`}>
                                            {task.status.replace('_', ' ')}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-gray-500">
                                        {task.due_date || '-'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
