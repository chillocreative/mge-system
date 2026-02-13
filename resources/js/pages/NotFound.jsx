import { Link } from 'react-router-dom';

export default function NotFound() {
    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50">
            <div className="text-center">
                <h1 className="text-6xl font-bold text-gray-200">404</h1>
                <p className="mt-4 text-lg font-medium text-gray-600">Page not found</p>
                <p className="mt-2 text-sm text-gray-500">
                    The page you're looking for doesn't exist.
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
