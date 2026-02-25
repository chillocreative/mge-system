import { useState } from 'react';
import { Link } from 'react-router-dom';
import authService from '@/services/authService';
import toast from 'react-hot-toast';

function BrandPanel() {
    return (
        <div className="relative hidden lg:flex lg:w-1/2 flex-col bg-primary-700 overflow-hidden">
            <div
                className="absolute inset-0 opacity-10"
                style={{
                    backgroundImage:
                        'radial-gradient(circle at 2px 2px, rgba(163,230,53,0.4) 1px, transparent 0)',
                    backgroundSize: '32px 32px',
                }}
            />
            <div className="absolute bottom-0 left-0 right-0 h-1 bg-accent-400" />

            <div className="relative z-10 flex flex-1 flex-col p-12">
                {/* Logo */}
                <div className="flex items-center gap-3">
                    <svg width="44" height="44" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="20" cy="22" rx="18" ry="14" stroke="#bef264" strokeWidth="2.5" />
                        <path d="M12 24C12 20 9 17 9 17C9 17 6 20 6 24C6 28 9 29 9 29C9 29 12 28 12 24Z" fill="#bef264" />
                        <path d="M23 21C23 16 20 13 20 13C20 13 17 16 17 21C17 26 20 29 20 29C20 29 23 26 23 21Z" fill="#bef264" />
                        <path d="M34 24C34 20 31 17 31 17C31 17 28 20 28 24C28 28 31 29 31 29C31 29 34 28 34 24Z" fill="#bef264" />
                    </svg>
                    <span className="text-xl font-bold tracking-tight text-white">MGE-PMS</span>
                </div>

                <div className="mb-auto mt-auto flex flex-col">
                    <div className="mb-3 inline-flex">
                        <span className="rounded-full border border-accent-400/40 bg-accent-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-accent-300">
                            New Account
                        </span>
                    </div>
                    <h1 className="text-4xl font-extrabold leading-tight text-white">
                        Join the<br />
                        <span className="text-accent-400">MGE Team.</span>
                    </h1>
                    <p className="mt-4 max-w-xs text-base leading-relaxed text-primary-100">
                        Create your account and get access to project dashboards, safety logs, and collaboration tools.
                    </p>

                    <div className="mt-8 space-y-3">
                        {[
                            { icon: '🏗', text: 'Real-time project tracking' },
                            { icon: '🦺', text: 'Safety & compliance management' },
                            { icon: '📊', text: 'Finance and payroll oversight' },
                        ].map((item) => (
                            <div key={item.text} className="flex items-center gap-3">
                                <span className="text-base">{item.icon}</span>
                                <span className="text-sm text-primary-200">{item.text}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="mt-auto opacity-20">
                    <svg viewBox="0 0 400 80" fill="none" xmlns="http://www.w3.org/2000/svg" className="w-full">
                        <path d="M0 80C100 80 150 20 200 50C250 80 300 10 400 40V80H0Z" fill="#bef264" />
                    </svg>
                </div>
                <p className="mt-4 text-xs text-primary-300">
                    © {new Date().getFullYear()} Multi Green Engineering Sdn. Bhd.
                </p>
            </div>
        </div>
    );
}

export default function Register() {
    const [formData, setFormData] = useState({
        first_name: '',
        last_name: '',
        email: '',
        password: '',
        password_confirmation: '',
        phone: '',
    });
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const [registered, setRegistered] = useState(false);
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
            await authService.register(formData);
            setRegistered(true);
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors || {});
            } else {
                toast.error(error.response?.data?.message || 'Registration failed');
            }
        } finally {
            setSubmitting(false);
        }
    };

    const inputBase =
        'w-full rounded-lg border px-4 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-accent-400 focus:ring-offset-1';
    const inputOk = `${inputBase} border-primary-200 bg-white text-primary-700 placeholder-primary-300 focus:border-accent-400`;
    const inputErr = `${inputBase} border-red-400 bg-red-50 text-primary-700 placeholder-primary-300 focus:border-red-500 focus:ring-red-400`;
    const field = (name) => (errors[name] ? inputErr : inputOk);

    if (registered) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-primary-50 px-6 py-12">
                <div className="w-full max-w-sm text-center">
                    <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 ring-8 ring-green-50">
                        <svg className="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </div>
                    <h2 className="text-2xl font-bold text-primary-700">Account Submitted</h2>
                    <p className="mt-3 text-sm leading-relaxed text-primary-400">
                        Your registration is <strong className="text-primary-600">pending admin approval</strong>. You'll receive access once an administrator activates your account.
                    </p>
                    <Link
                        to="/login"
                        className="mt-8 inline-flex items-center gap-2 rounded-lg bg-primary-700 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-accent-400 focus:ring-offset-2"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Login
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="flex min-h-screen">
            <BrandPanel />

            {/* Form panel */}
            <div className="flex flex-1 flex-col items-center justify-center bg-primary-50 px-6 py-12 lg:px-12 overflow-y-auto">
                {/* Mobile logo */}
                <div className="mb-8 flex flex-col items-center lg:hidden">
                    <svg width="48" height="48" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="20" cy="22" rx="18" ry="14" stroke="#84cc16" strokeWidth="2.5" />
                        <path d="M12 24C12 20 9 17 9 17C9 17 6 20 6 24C6 28 9 29 9 29C9 29 12 28 12 24Z" fill="#15803d" />
                        <path d="M23 21C23 16 20 13 20 13C20 13 17 16 17 21C17 26 20 29 20 29C20 29 23 26 23 21Z" fill="#15803d" />
                        <path d="M34 24C34 20 31 17 31 17C31 17 28 20 28 24C28 28 31 29 31 29C31 29 34 28 34 24Z" fill="#15803d" />
                    </svg>
                    <span className="mt-3 text-xl font-bold text-primary-700">MGE-PMS</span>
                </div>

                <div className="w-full max-w-sm">
                    <div className="mb-8">
                        <h2 className="text-2xl font-bold text-primary-700">Create your account</h2>
                        <p className="mt-1 text-sm text-primary-400">Fill in your details to request access</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label htmlFor="first_name" className="mb-1.5 block text-sm font-medium text-primary-600">
                                    First name
                                </label>
                                <input
                                    id="first_name"
                                    name="first_name"
                                    type="text"
                                    required
                                    value={formData.first_name}
                                    onChange={handleChange}
                                    className={field('first_name')}
                                    placeholder="Ahmad"
                                />
                                {errors.first_name && <p className="mt-1 text-xs text-red-600">{errors.first_name[0]}</p>}
                            </div>
                            <div>
                                <label htmlFor="last_name" className="mb-1.5 block text-sm font-medium text-primary-600">
                                    Last name
                                </label>
                                <input
                                    id="last_name"
                                    name="last_name"
                                    type="text"
                                    required
                                    value={formData.last_name}
                                    onChange={handleChange}
                                    className={field('last_name')}
                                    placeholder="Razif"
                                />
                                {errors.last_name && <p className="mt-1 text-xs text-red-600">{errors.last_name[0]}</p>}
                            </div>
                        </div>

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
                                className={field('email')}
                                placeholder="you@mge-eng.com"
                            />
                            {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email[0]}</p>}
                        </div>

                        <div>
                            <label htmlFor="phone" className="mb-1.5 block text-sm font-medium text-primary-600">
                                Phone{' '}
                                <span className="font-normal text-primary-400">(optional)</span>
                            </label>
                            <input
                                id="phone"
                                name="phone"
                                type="tel"
                                value={formData.phone}
                                onChange={handleChange}
                                className={field('phone')}
                                placeholder="+60 12-345 6789"
                            />
                        </div>

                        <div>
                            <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-primary-600">
                                Password
                            </label>
                            <div className="relative">
                                <input
                                    id="password"
                                    name="password"
                                    type={showPassword ? 'text' : 'password'}
                                    required
                                    value={formData.password}
                                    onChange={handleChange}
                                    className={`${field('password')} pr-11`}
                                    placeholder="Min. 8 characters"
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
                            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password[0]}</p>}
                        </div>

                        <div>
                            <label htmlFor="password_confirmation" className="mb-1.5 block text-sm font-medium text-primary-600">
                                Confirm password
                            </label>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type={showPassword ? 'text' : 'password'}
                                required
                                value={formData.password_confirmation}
                                onChange={handleChange}
                                className={field('password_confirmation')}
                                placeholder="Re-enter password"
                            />
                            {errors.password_confirmation && (
                                <p className="mt-1 text-xs text-red-600">{errors.password_confirmation[0]}</p>
                            )}
                        </div>

                        <div className="rounded-lg bg-accent-50 px-4 py-3 text-xs text-accent-700 ring-1 ring-accent-200">
                            Your account will require <strong>admin approval</strong> before you can log in.
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
                                    Creating account...
                                </span>
                            ) : (
                                'Create account'
                            )}
                        </button>
                    </form>

                    <p className="mt-6 text-center text-sm text-primary-400">
                        Already have an account?{' '}
                        <Link to="/login" className="font-semibold text-accent-600 hover:text-accent-500">
                            Sign in
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
