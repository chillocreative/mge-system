import { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import toast from 'react-hot-toast';

function BrandPanel() {
    return (
        <div className="relative hidden lg:flex lg:w-1/2 flex-col bg-primary-700 overflow-hidden">
            {/* Grid background */}
            <div
                className="absolute inset-0 opacity-10"
                style={{
                    backgroundImage:
                        'linear-gradient(rgba(250,204,21,0.4) 1px, transparent 1px), linear-gradient(90deg, rgba(250,204,21,0.4) 1px, transparent 1px)',
                    backgroundSize: '48px 48px',
                }}
            />
            {/* Bottom yellow accent bar */}
            <div className="absolute bottom-0 left-0 right-0 h-1 bg-accent-400" />

            <div className="relative z-10 flex flex-col flex-1 p-12">
                {/* Logo */}
                <div className="flex items-center gap-3">
                    <svg width="44" height="44" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="40" height="40" rx="8" fill="rgba(255,255,255,0.08)" />
                        <path d="M10 30V12L20 22L30 12V30" stroke="#f1f5f9" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
                        <line x1="8" y1="35" x2="32" y2="35" stroke="#facc15" strokeWidth="2.5" strokeLinecap="round" />
                    </svg>
                    <span className="text-xl font-bold tracking-tight text-white">MGE-PMS</span>
                </div>

                {/* Hero text */}
                <div className="mt-auto mb-auto flex flex-col">
                    <div className="mb-3 inline-flex">
                        <span className="rounded-full border border-accent-400/40 bg-accent-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-accent-400">
                            Construction Management
                        </span>
                    </div>
                    <h1 className="text-4xl font-extrabold leading-tight text-white">
                        Build Smarter.<br />
                        <span className="text-accent-400">Manage Better.</span>
                    </h1>
                    <p className="mt-4 max-w-xs text-base leading-relaxed text-primary-300">
                        A unified platform for project tracking, safety compliance, HR, and finance — built for engineering teams.
                    </p>

                    {/* Feature pills */}
                    <div className="mt-8 flex flex-wrap gap-2">
                        {['Project Tracking', 'Safety & Compliance', 'Finance & Invoicing', 'Team Management'].map((f) => (
                            <span
                                key={f}
                                className="rounded-full bg-white/5 px-3 py-1.5 text-xs font-medium text-primary-300 ring-1 ring-white/10"
                            >
                                {f}
                            </span>
                        ))}
                    </div>
                </div>

                {/* Decorative construction SVG */}
                <div className="mt-auto opacity-20">
                    <svg viewBox="0 0 400 80" fill="none" xmlns="http://www.w3.org/2000/svg" className="w-full">
                        <line x1="0" y1="70" x2="400" y2="70" stroke="#facc15" strokeWidth="2" />
                        <rect x="20" y="20" width="12" height="50" fill="#facc15" />
                        <rect x="60" y="35" width="12" height="35" fill="#facc15" />
                        <rect x="100" y="10" width="12" height="60" fill="#facc15" />
                        <rect x="140" y="30" width="12" height="40" fill="#facc15" />
                        <rect x="180" y="15" width="12" height="55" fill="#facc15" />
                        <rect x="220" y="40" width="12" height="30" fill="#facc15" />
                        <rect x="260" y="25" width="12" height="45" fill="#facc15" />
                        <rect x="300" y="5" width="12" height="65" fill="#facc15" />
                        <rect x="340" y="35" width="12" height="35" fill="#facc15" />
                        <rect x="380" y="50" width="12" height="20" fill="#facc15" />
                    </svg>
                </div>

                <p className="mt-4 text-xs text-primary-500">
                    © {new Date().getFullYear()} Multi Green Engineering Sdn. Bhd.
                </p>
            </div>
        </div>
    );
}

export default function Login() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const from = location.state?.from?.pathname || '/dashboard';

    const [formData, setFormData] = useState({ email: '', password: '' });
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

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

    const fillDemo = (email) => {
        setFormData({ email, password: 'password' });
        setErrors({});
    };

    const inputBase =
        'w-full rounded-lg border px-4 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-accent-400 focus:ring-offset-1';
    const inputOk = `${inputBase} border-primary-200 bg-white text-primary-700 placeholder-primary-300 focus:border-accent-400`;
    const inputErr = `${inputBase} border-red-400 bg-red-50 text-primary-700 placeholder-primary-300 focus:border-red-500 focus:ring-red-400`;

    return (
        <div className="flex min-h-screen">
            <BrandPanel />

            {/* Form panel */}
            <div className="flex flex-1 flex-col items-center justify-center bg-primary-50 px-6 py-12 lg:px-12">
                {/* Mobile logo */}
                <div className="mb-8 flex flex-col items-center lg:hidden">
                    <svg width="48" height="48" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="40" height="40" rx="8" fill="#0f172a" />
                        <path d="M10 30V12L20 22L30 12V30" stroke="#f1f5f9" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
                        <line x1="8" y1="35" x2="32" y2="35" stroke="#facc15" strokeWidth="2.5" strokeLinecap="round" />
                    </svg>
                    <span className="mt-3 text-xl font-bold text-primary-700">MGE-PMS</span>
                </div>

                <div className="w-full max-w-sm">
                    <div className="mb-8">
                        <h2 className="text-2xl font-bold text-primary-700">Welcome back</h2>
                        <p className="mt-1 text-sm text-primary-400">Sign in to your account to continue</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div>
                            <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-primary-600">
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
                                className={errors.email ? inputErr : inputOk}
                                placeholder="you@mge-eng.com"
                            />
                            {errors.email && <p className="mt-1.5 text-xs text-red-600">{errors.email[0]}</p>}
                        </div>

                        <div>
                            <div className="mb-1.5 flex items-center justify-between">
                                <label htmlFor="password" className="text-sm font-medium text-primary-600">
                                    Password
                                </label>
                                <Link
                                    to="/forgot-password"
                                    className="text-xs font-medium text-accent-600 hover:text-accent-500"
                                >
                                    Forgot password?
                                </Link>
                            </div>
                            <div className="relative">
                                <input
                                    id="password"
                                    name="password"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="current-password"
                                    required
                                    value={formData.password}
                                    onChange={handleChange}
                                    className={`${errors.password ? inputErr : inputOk} pr-11`}
                                    placeholder="Enter your password"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-primary-400 hover:text-primary-600"
                                    tabIndex={-1}
                                >
                                    {showPassword ? (
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                        </svg>
                                    ) : (
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    )}
                                </button>
                            </div>
                            {errors.password && <p className="mt-1.5 text-xs text-red-600">{errors.password[0]}</p>}
                        </div>

                        <button
                            type="submit"
                            disabled={submitting}
                            className="w-full rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-accent-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 active:scale-[0.98]"
                        >
                            {submitting ? (
                                <span className="flex items-center justify-center gap-2">
                                    <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                                    </svg>
                                    Signing in...
                                </span>
                            ) : (
                                'Sign in'
                            )}
                        </button>
                    </form>

                    <p className="mt-6 text-center text-sm text-primary-400">
                        Don't have an account?{' '}
                        <Link to="/register" className="font-semibold text-accent-600 hover:text-accent-500">
                            Request access
                        </Link>
                    </p>

                    {/* Demo credentials */}
                    <div className="mt-8 rounded-xl border border-accent-400/25 bg-accent-50 p-4">
                        <p className="mb-3 text-xs font-bold uppercase tracking-widest text-accent-700">
                            Demo Accounts
                        </p>
                        <div className="space-y-2">
                            {[
                                { role: 'Admin & HR', email: 'admin@mge-pms.test' },
                                { role: 'Finances & HR', email: 'finance@mge-pms.test' },
                                { role: 'Projects', email: 'pm@mge-pms.test' },
                            ].map((acc) => (
                                <button
                                    key={acc.email}
                                    type="button"
                                    onClick={() => fillDemo(acc.email)}
                                    className="flex w-full items-center justify-between rounded-lg bg-white px-3 py-2 text-left ring-1 ring-primary-200 transition-all hover:ring-accent-400 hover:shadow-sm"
                                >
                                    <span className="text-sm font-semibold text-primary-700">{acc.role}</span>
                                    <span className="text-xs text-primary-400">{acc.email}</span>
                                </button>
                            ))}
                        </div>
                        <p className="mt-2.5 text-center text-xs text-primary-400">
                            Password:{' '}
                            <code className="rounded bg-white px-1.5 py-0.5 font-mono text-xs text-primary-600 ring-1 ring-primary-200">
                                password
                            </code>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
