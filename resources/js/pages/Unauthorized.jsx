import { Link } from 'react-router-dom';
import { HiOutlineShieldExclamation } from 'react-icons/hi';

export default function Unauthorized() {
    return (
        <div className="flex items-center justify-center py-24">
            <div className="text-center">
                <HiOutlineShieldExclamation className="mx-auto h-16 w-16 text-red-400" />
                <h1 className="mt-4 text-3xl font-bold text-gray-900">Access Denied</h1>
                <p className="mt-2 text-sm text-gray-500">
                    You do not have permission to access this page.
                </p>
                <p className="mt-1 text-sm text-gray-400">
                    Contact your administrator if you believe this is an error.
                </p>
                <Link
                    to="/dashboard"
                    className="mt-6 inline-block rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                >
                    Go to Dashboard
                </Link>
            </div>
        </div>
    );
}
