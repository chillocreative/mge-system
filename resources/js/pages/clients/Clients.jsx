import { useState, useEffect } from 'react';
import clientService from '@/services/clientService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { HiOutlinePlus, HiOutlineSearch, HiOutlineOfficeBuilding } from 'react-icons/hi';

export default function Clients() {
    const [clients, setClients] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');

    const fetchClients = async () => {
        setLoading(true);
        try {
            const params = {};
            if (search) params.search = search;
            const response = await clientService.list(params);
            setClients(response.data?.data || []);
        } catch {
            setClients([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            fetchClients();
        }, 400);
        return () => clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        fetchClients();
    }, []);

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Clients</h1>
                    <p className="text-sm text-gray-500">Manage construction clients</p>
                </div>
                <button className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                    <HiOutlinePlus className="h-5 w-5" />
                    New Client
                </button>
            </div>

            <div className="mb-6">
                <div className="relative max-w-md">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search clients..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                </div>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : clients.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineOfficeBuilding className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No clients found</p>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {clients.map((client) => (
                        <div
                            key={client.id}
                            className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200"
                        >
                            <div className="mb-3 flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-sm font-bold text-primary-700">
                                    {client.company_name?.[0]}
                                </div>
                                <div>
                                    <h3 className="text-sm font-semibold text-gray-900">
                                        {client.company_name}
                                    </h3>
                                    <p className="text-xs text-gray-500">{client.contact_person}</p>
                                </div>
                            </div>
                            <div className="space-y-1.5 text-xs text-gray-500">
                                <p>{client.email}</p>
                                {client.phone && <p>{client.phone}</p>}
                                {client.city && <p>{client.city}, {client.country}</p>}
                            </div>
                            <div className="mt-3 flex items-center justify-between border-t pt-3">
                                <span
                                    className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                        client.status === 'active'
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-gray-100 text-gray-600'
                                    }`}
                                >
                                    {client.status}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
