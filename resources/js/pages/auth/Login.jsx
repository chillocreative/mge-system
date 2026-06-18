import { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import toast from 'react-hot-toast';

function BrandPanel() {
    return (
        <div className="relative hidden lg:flex lg:w-1/2 flex-col bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 overflow-hidden">
            {/* Decorative abstract shapes */}
            <div className="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 rounded-full bg-accent-400/10 blur-3xl" />
            <div className="absolute bottom-0 left-0 -ml-16 -mb-16 w-96 h-96 rounded-full bg-primary-500/10 blur-3xl" />
            
            <div className="absolute inset-0 opacity-[0.03]" 
                style={{
                    backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2v-4h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2v-4h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`
                }} 
            />

            <div className="relative z-10 flex flex-1 flex-col p-16">
                {/* Logo Section */}
                <div className="flex items-center gap-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white p-1.5 ring-1 ring-white/20 shadow-lg">
                        <img src="/logo.png" alt="Multi Green Engineering" className="h-full w-full object-contain" />
                    </div>
                    <div>
                        <span className="block text-2xl font-black tracking-tighter text-white">MGE-PMS</span>
                        <span className="block text-[10px] font-bold uppercase tracking-[0.2em] text-accent-300/80">Engineering Excellence</span>
                    </div>
                </div>

                {/* Content Section */}
                <div className="mb-auto mt-auto max-w-lg">
                    <h1 className="text-5xl font-black leading-[1.1] text-white">
                        Constructing the <br />
                        <span className="bg-gradient-to-r from-accent-300 to-accent-500 bg-clip-text text-transparent">Digital Future.</span>
                    </h1>
                    
                    <p className="mt-6 text-lg leading-relaxed text-primary-100/80">
                        Seamlessly manage civil engineering projects, track resources, and ensure safety compliance with our integrated PMS.
                    </p>

                    <div className="mt-10 grid grid-cols-2 gap-6">
                        {[
                            { label: 'Efficiency', desc: 'Optimized workflows' },
                            { label: 'Compliance', desc: 'ISO 9001 standards' },
                        ].map((stat) => (
                            <div key={stat.label} className="border-l-2 border-accent-400/30 pl-4">
                                <div className="text-sm font-bold text-white">{stat.label}</div>
                                <div className="text-xs text-primary-200/60">{stat.desc}</div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Footer Section */}
                <div className="flex items-center justify-between border-t border-white/10 pt-8">
                    <p className="text-xs font-medium text-primary-300">
                        © {new Date().getFullYear()} Multi Green Engineering Sdn. Bhd.
                    </p>
                    <a
                        href="https://mge-eng.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-[10px] font-bold uppercase tracking-widest text-accent-300 transition-colors hover:text-accent-200"
                    >
                        mge-eng.com
                    </a>
                </div>
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
            toast.success('Access Granted. Welcome back.');
            navigate(from, { replace: true });
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors || {});
            } else {
                toast.error(error.response?.data?.message || 'Authentication failed');
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
        'w-full rounded-xl border px-4 py-3.5 text-sm transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-primary-500/10 focus:ring-offset-0';
    const inputOk = `${inputBase} border-primary-100 bg-white text-primary-900 placeholder-primary-300 hover:border-primary-300 focus:border-primary-600 shadow-sm`;
    const inputErr = `${inputBase} border-red-200 bg-red-50 text-red-900 placeholder-red-300 focus:border-red-500 shadow-sm`;

    return (
        <div className="flex min-h-screen bg-white">
            <BrandPanel />

            {/* Form panel */}
            <div className="flex flex-1 flex-col items-center justify-center px-8 py-12 lg:px-20">
                {/* Mobile branding */}
                <div className="mb-12 flex flex-col items-center lg:hidden">
                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-white p-1.5 ring-1 ring-primary-100 shadow-lg shadow-primary-700/10">
                        <img src="/logo.png" alt="Multi Green Engineering" className="h-full w-full object-contain" />
                    </div>
                    <span className="mt-4 text-2xl font-black tracking-tighter text-primary-900">MGE-PMS</span>
                </div>

                <div className="w-full max-w-[400px]">
                    <div className="mb-10 text-center lg:text-left">
                        <h2 className="text-3xl font-black tracking-tight text-primary-900">Portal Login</h2>
                        <p className="mt-2 text-sm font-medium text-primary-500">Enter your credentials to manage infrastructure projects</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-1.5">
                            <label htmlFor="email" className="text-xs font-bold uppercase tracking-widest text-primary-700">
                                Corporate Email
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
                                placeholder="name@mge-eng.com"
                            />
                            {errors.email && <p className="mt-1.5 text-[11px] font-bold text-red-600">{errors.email[0]}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between">
                                <label htmlFor="password" className="text-xs font-bold uppercase tracking-widest text-primary-700">
                                    Access Code
                                </label>
                                <Link
                                    to="/forgot-password"
                                    className="text-[11px] font-bold text-primary-500 hover:text-primary-900 transition-colors"
                                >
                                    Recovery Access
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
                                    className={`${errors.password ? inputErr : inputOk} pr-12`}
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-4 top-1/2 -translate-y-1/2 text-primary-400 hover:text-primary-600"
                                    tabIndex={-1}
                                >
                                    {showPassword ? (
                                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                        </svg>
                                    ) : (
                                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    )}
                                </button>
                            </div>
                            {errors.password && <p className="mt-1.5 text-[11px] font-bold text-red-600">{errors.password[0]}</p>}
                        </div>

                        <button
                            type="submit"
                            disabled={submitting}
                            className="group relative flex w-full items-center justify-center overflow-hidden rounded-xl bg-primary-800 px-4 py-4 text-sm font-bold text-white shadow-xl shadow-primary-900/10 transition-all hover:bg-primary-900 hover:shadow-primary-900/20 active:scale-[0.98] disabled:opacity-70"
                        >
                            <span className="relative z-10 flex items-center gap-2">
                                {submitting ? (
                                    <>
                                        <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                                        </svg>
                                        Authenticating...
                                    </>
                                ) : (
                                    <>
                                        Sign In to Portal
                                        <svg className="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                        </svg>
                                    </>
                                )}
                            </span>
                        </button>
                    </form>

                    <div className="mt-10">
                        <div className="relative flex items-center py-4">
                            <div className="flex-grow border-t border-primary-100"></div>
                            <span className="mx-4 flex-shrink text-[10px] font-black uppercase tracking-[0.2em] text-primary-300">Quick Access Tools</span>
                            <div className="flex-grow border-t border-primary-100"></div>
                        </div>

                        <div className="mt-6 grid grid-cols-1 gap-3">
                            {[
                                { role: 'Project Director', email: 'admin@mge-pms.test', icon: '🏗️' },
                                { role: 'Safety Inspector', email: 'pm@mge-pms.test', icon: '🦺' },
                                { role: 'Finance Control', email: 'finance@mge-pms.test', icon: '📊' },
                            ].map((acc) => (
                                <button
                                    key={acc.email}
                                    type="button"
                                    onClick={() => fillDemo(acc.email)}
                                    className="group flex w-full items-center gap-4 rounded-xl border border-primary-50 bg-white p-3.5 text-left transition-all hover:border-accent-400 hover:bg-accent-50/30 hover:shadow-md active:scale-[0.99]"
                                >
                                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-lg transition-colors group-hover:bg-accent-100">
                                        {acc.icon}
                                    </span>
                                    <div className="flex-1 overflow-hidden">
                                        <div className="text-sm font-bold text-primary-900">{acc.role}</div>
                                        <div className="truncate text-xs text-primary-400">{acc.email}</div>
                                    </div>
                                    <svg className="h-4 w-4 opacity-0 transition-all group-hover:translate-x-1 group-hover:opacity-100 text-accent-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            ))}
                        </div>
                    </div>

                    <p className="mt-10 text-center text-sm font-medium text-primary-500">
                        Unauthorized access is prohibited.{' '}
                        <Link to="/register" className="font-bold text-primary-900 underline decoration-accent-400 decoration-2 underline-offset-4 hover:text-accent-700">
                            Request Credentials
                        </Link>
                    </p>

                    <a
                        href="https://mge-eng.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="group mt-6 flex items-center justify-center gap-2 text-xs font-bold uppercase tracking-widest text-primary-400 transition-colors hover:text-primary-700"
                    >
                        <svg className="h-4 w-4 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Main Website
                    </a>
                </div>
            </div>
        </div>
    );
}
