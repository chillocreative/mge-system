import { useState } from 'react';
import { Link } from 'react-router-dom';
import authService from '@/services/authService';
import toast from 'react-hot-toast';

function BrandPanel() {
    return (
        <div className="relative hidden lg:flex lg:w-1/2 flex-col bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 overflow-hidden">
            <div className="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 rounded-full bg-accent-400/10 blur-3xl" />
            <div className="absolute bottom-0 left-0 -ml-16 -mb-16 w-96 h-96 rounded-full bg-primary-500/10 blur-3xl" />
            
            <div className="absolute inset-0 opacity-[0.03]" 
                style={{
                    backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2v-4h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2v-4h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`
                }} 
            />

            <div className="relative z-10 flex flex-1 flex-col p-16">
                <div className="flex items-center gap-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 backdrop-blur-md ring-1 ring-white/20">
                        <svg width="32" height="32" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <ellipse cx="20" cy="22" rx="18" ry="14" stroke="#bef264" strokeWidth="2.5" />
                            <path d="M12 24C12 20 9 17 9 17C9 17 6 20 6 24C6 28 9 29 9 29C9 29 12 28 12 24Z" fill="#bef264" />
                            <path d="M23 21C23 16 20 13 20 13C20 13 17 16 17 21C17 26 20 29 20 29C20 29 23 26 23 21Z" fill="#bef264" />
                            <path d="M34 24C34 20 31 17 31 17C31 17 28 20 28 24C28 28 31 29 31 29C31 29 34 28 34 24Z" fill="#bef264" />
                        </svg>
                    </div>
                    <div>
                        <span className="block text-2xl font-black tracking-tighter text-white">MGE-PMS</span>
                        <span className="block text-[10px] font-bold uppercase tracking-[0.2em] text-accent-300/80">Join the Force</span>
                    </div>
                </div>

                <div className="mb-auto mt-auto max-w-lg">
                    <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 backdrop-blur-sm">
                        <span className="text-xs font-bold uppercase tracking-widest text-white/90">Workforce Enrollment</span>
                    </div>
                    
                    <h1 className="text-5xl font-black leading-[1.1] text-white">
                        Empowering <br />
                        <span className="bg-gradient-to-r from-accent-300 to-accent-500 bg-clip-text text-transparent">Engineering Talent.</span>
                    </h1>
                    
                    <p className="mt-6 text-lg leading-relaxed text-primary-100/80">
                        Become part of Malaysia's leading civil engineering team. Access specialized tools for project oversight and safety management.
                    </p>

                    <div className="mt-10 space-y-4">
                        {[
                            { icon: '🏗️', text: 'Centralized Project Control' },
                            { icon: '🦺', text: 'Automated Safety Reporting' },
                            { icon: '📊', text: 'Real-time Financial Analytics' },
                        ].map((item) => (
                            <div key={item.text} className="flex items-center gap-4 group">
                                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white/5 ring-1 ring-white/10 transition-all group-hover:bg-accent-400/20 group-hover:ring-accent-400/40">
                                    {item.icon}
                                </span>
                                <span className="text-sm font-bold text-primary-100/90">{item.text}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex items-center justify-between border-t border-white/10 pt-8">
                    <p className="text-xs font-medium text-primary-300">
                        © {new Date().getFullYear()} Multi Green Engineering Sdn. Bhd.
                    </p>
                </div>
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
        'w-full rounded-xl border px-4 py-3 text-sm transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-primary-500/10 focus:ring-offset-0';
    const inputOk = `${inputBase} border-primary-100 bg-white text-primary-900 placeholder-primary-300 hover:border-primary-300 focus:border-primary-600 shadow-sm`;
    const inputErr = `${inputBase} border-red-200 bg-red-50 text-red-900 placeholder-red-300 focus:border-red-500 shadow-sm`;
    const field = (name) => (errors[name] ? inputErr : inputOk);

    if (registered) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-slate-50 px-6 py-12">
                <div className="w-full max-w-md text-center">
                    <div className="mx-auto mb-8 flex h-24 w-24 items-center justify-center rounded-3xl bg-green-100 shadow-xl shadow-green-200/50">
                        <svg className="h-12 w-12 text-green-600" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 className="text-3xl font-black tracking-tight text-primary-900">Request Received</h2>
                    <p className="mt-4 text-base leading-relaxed text-primary-500">
                        Your account <span className="font-bold text-primary-700">({formData.email})</span> has been submitted for verification. You will be notified once a System Administrator approves your access.
                    </p>
                    <div className="mt-10">
                        <Link
                            to="/login"
                            className="inline-flex items-center gap-2 rounded-xl bg-primary-800 px-8 py-4 text-sm font-bold text-white shadow-xl shadow-primary-900/10 transition-all hover:bg-primary-900 hover:shadow-primary-900/20 active:scale-[0.98]"
                        >
                            Return to Secure Login
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="flex min-h-screen bg-white">
            <BrandPanel />

            {/* Form panel */}
            <div className="flex flex-1 flex-col items-center justify-center px-8 py-12 lg:px-20 overflow-y-auto">
                {/* Mobile branding */}
                <div className="mb-12 flex flex-col items-center lg:hidden">
                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-700 shadow-lg shadow-primary-700/20">
                        <svg width="28" height="28" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <ellipse cx="20" cy="22" rx="18" ry="14" stroke="#bef264" strokeWidth="2.5" />
                            <path d="M12 24C12 20 9 17 9 17C9 17 6 20 6 24C6 28 9 29 9 29C9 29 12 28 12 24Z" fill="#bef264" />
                            <path d="M23 21C23 16 20 13 20 13C20 13 17 16 17 21C17 26 20 29 20 29C20 29 23 26 23 21Z" fill="#bef264" />
                            <path d="M34 24C34 20 31 17 31 17C31 17 28 20 28 24C28 28 31 29 31 29C31 29 34 28 34 24Z" fill="#bef264" />
                        </svg>
                    </div>
                </div>

                <div className="w-full max-w-[440px]">
                    <div className="mb-8">
                        <h2 className="text-3xl font-black tracking-tight text-primary-900">Get Started</h2>
                        <p className="mt-2 text-sm font-medium text-primary-500">Submit your professional details for system access</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <label htmlFor="first_name" className="text-[10px] font-bold uppercase tracking-widest text-primary-700">
                                    First Name
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
                                {errors.first_name && <p className="mt-1 text-[10px] font-bold text-red-600">{errors.first_name[0]}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <label htmlFor="last_name" className="text-[10px] font-bold uppercase tracking-widest text-primary-700">
                                    Last Name
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
                                {errors.last_name && <p className="mt-1 text-[10px] font-bold text-red-600">{errors.last_name[0]}</p>}
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <label htmlFor="email" className="text-[10px] font-bold uppercase tracking-widest text-primary-700">
                                Work Email
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
                                placeholder="name@mge-eng.com"
                            />
                            {errors.email && <p className="mt-1 text-[10px] font-bold text-red-600">{errors.email[0]}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <label htmlFor="phone" className="text-[10px] font-bold uppercase tracking-widest text-primary-700">
                                Contact Number
                            </label>
                            <input
                                id="phone"
                                name="phone"
                                type="tel"
                                value={formData.phone}
                                onChange={handleChange}
                                className={field('phone')}
                                placeholder="+60 1x-xxx xxxx"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <label htmlFor="password" className="text-[10px] font-bold uppercase tracking-widest text-primary-700">
                                Security Password
                            </label>
                            <div className="relative">
                                <input
                                    id="password"
                                    name="password"
                                    type={showPassword ? 'text' : 'password'}
                                    required
                                    value={formData.password}
                                    onChange={handleChange}
                                    className={`${field('password')} pr-12`}
                                    placeholder="Min. 8 characters"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-4 top-1/2 -translate-y-1/2 text-primary-400 hover:text-primary-600"
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
                            {errors.password && <p className="mt-1 text-[10px] font-bold text-red-600">{errors.password[0]}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <label htmlFor="password_confirmation" className="text-[10px] font-bold uppercase tracking-widest text-primary-700">
                                Verify Password
                            </label>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type={showPassword ? 'text' : 'password'}
                                required
                                value={formData.password_confirmation}
                                onChange={handleChange}
                                className={field('password_confirmation')}
                                placeholder="Repeat password"
                            />
                            {errors.password_confirmation && (
                                <p className="mt-1 text-[10px] font-bold text-red-600">{errors.password_confirmation[0]}</p>
                            )}
                        </div>

                        <div className="rounded-xl border border-primary-100 bg-primary-50 px-4 py-3 text-[11px] font-medium leading-relaxed text-primary-600">
                            Note: Registration requests are subject to <span className="font-bold text-primary-900 underline decoration-accent-400">manual verification</span> by the HR department.
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
                                        Processing...
                                    </>
                                ) : (
                                    <>
                                        Submit Registration
                                        <svg className="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                        </svg>
                                    </>
                                )}
                            </span>
                        </button>
                    </form>

                    <p className="mt-8 text-center text-sm font-medium text-primary-500">
                        Already have access?{' '}
                        <Link to="/login" className="font-bold text-primary-900 underline decoration-accent-400 decoration-2 underline-offset-4 hover:text-accent-700">
                            Sign In
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
