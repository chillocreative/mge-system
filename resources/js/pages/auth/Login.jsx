import { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import Logo from '@/components/Logo';
import toast from 'react-hot-toast';

export default function Login() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const from = location.state?.from?.pathname || '/dashboard';

    const [formData, setFormData] = useState({
        email: '',
        password: '',
    });
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
        setErrors({ ...errors, [e.target.name]: '' });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});

        try {
            await login(formData);
            toast.success('Welcome back!');
            navigate(from, { replace: true });
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors || {});
            } else {
                toast.error(error.response?.data?.message || 'Login failed');
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-primary-100 px-4">
            <div className="w-full max-w-md">
                <div className="mb-8 flex flex-col items-center">
                    <Logo size={48} variant="dark" />
                    <h1 className="mt-4 text-2xl font-bold text-primary-700">MGE-PMS</h1>
                    <p className="mt-1 text-sm text-primary-400">
                        Project Management System
                    </p>
                </div>

                <div className="rounded-xl bg-white p-8 shadow-sm ring-1 ring-primary-200">
                    <h2 className="mb-6 text-xl font-semibold text-primary-700">
                        Sign in to your account
                    </h2>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div>
                            <label
                                htmlFor="email"
                                className="mb-1.5 block text-sm font-medium text-primary-500"
                            >
                                Email address
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="email"
                                required
                                value={formData.email}
                                onChange={handleChange}
                                className="w-full rounded-lg border border-primary-200 px-3.5 py-2.5 text-sm shadow-sm transition-colors focus:border-accent-400 focus:outline-none focus:ring-1 focus:ring-accent-400"
                                placeholder="you@example.com"
                            />
                            {errors.email && (
                                <p className="mt-1 text-sm text-red-600">{errors.email[0]}</p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="password"
                                className="mb-1.5 block text-sm font-medium text-primary-500"
                            >
                                Password
                            </label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autoComplete="current-password"
                                required
                                value={formData.password}
                                onChange={handleChange}
                                className="w-full rounded-lg border border-primary-200 px-3.5 py-2.5 text-sm shadow-sm transition-colors focus:border-accent-400 focus:outline-none focus:ring-1 focus:ring-accent-400"
                                placeholder="Enter your password"
                            />
                            {errors.password && (
                                <p className="mt-1 text-sm text-red-600">{errors.password[0]}</p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={submitting}
                            className="w-full rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-accent-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {submitting ? 'Signing in...' : 'Sign in'}
                        </button>
                    </form>

                    <p className="mt-6 text-center text-sm text-primary-400">
                        Don't have an account?{' '}
                        <Link
                            to="/register"
                            className="font-medium text-accent-600 hover:text-accent-500"
                        >
                            Register here
                        </Link>
                    </p>
                </div>

                {/* Demo credentials */}
                <div className="mt-4 rounded-lg border border-accent-400/30 bg-accent-50 p-4">
                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-accent-700">Demo Accounts</p>
                    <div className="space-y-2 text-sm">
                        {[
                            { role: 'Admin & HR', email: 'admin@mge-pms.test' },
                            { role: 'Finances & HR', email: 'finance@mge-pms.test' },
                            { role: 'Projects', email: 'pm@mge-pms.test' },
                        ].map((acc) => (
                            <button
                                key={acc.email}
                                type="button"
                                onClick={() => {
                                    setFormData({ email: acc.email, password: 'password' });
                                    setErrors({});
                                }}
                                className="flex w-full items-center justify-between rounded-md bg-white px-3 py-2 text-left ring-1 ring-primary-200 transition-colors hover:ring-accent-400"
                            >
                                <span className="font-medium text-primary-700">{acc.role}</span>
                                <span className="text-xs text-primary-400">{acc.email}</span>
                            </button>
                        ))}
                        <p className="pt-1 text-center text-xs text-primary-400">
                            Password: <code className="rounded bg-primary-200 px-1.5 py-0.5 font-mono text-primary-600">password</code>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
