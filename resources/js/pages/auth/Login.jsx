import { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import toast from 'react-hot-toast';

function Aurora() {
    return (
        <>
            <style>{`
                @keyframes auroraFloat {
                    0%, 100% { transform: translate(0,0) scale(1); }
                    33% { transform: translate(40px,-50px) scale(1.15); }
                    66% { transform: translate(-30px,30px) scale(0.92); }
                }
                @keyframes auroraFloat2 {
                    0%, 100% { transform: translate(0,0) scale(1); }
                    50% { transform: translate(-50px,40px) scale(1.2); }
                }
            `}</style>
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
                <div className="absolute -left-32 -top-32 h-[34rem] w-[34rem] rounded-full bg-emerald-500/30 blur-[120px]"
                    style={{ animation: 'auroraFloat 16s ease-in-out infinite' }} />
                <div className="absolute right-[-10rem] top-1/4 h-[30rem] w-[30rem] rounded-full bg-lime-400/25 blur-[130px]"
                    style={{ animation: 'auroraFloat2 19s ease-in-out infinite' }} />
                <div className="absolute bottom-[-12rem] left-1/3 h-[32rem] w-[32rem] rounded-full bg-teal-400/20 blur-[140px]"
                    style={{ animation: 'auroraFloat 22s ease-in-out infinite' }} />
                {/* grid overlay */}
                <div className="absolute inset-0 opacity-[0.07]"
                    style={{
                        backgroundImage:
                            'linear-gradient(rgba(190,242,100,0.4) 1px, transparent 1px), linear-gradient(90deg, rgba(190,242,100,0.4) 1px, transparent 1px)',
                        backgroundSize: '46px 46px',
                        maskImage: 'radial-gradient(ellipse at center, black 30%, transparent 75%)',
                    }} />
            </div>
        </>
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
        'w-full rounded-xl border bg-white/5 px-4 py-3.5 text-sm text-white placeholder-white/40 backdrop-blur transition-all duration-200 focus:outline-none focus:ring-2';
    const inputOk = `${inputBase} border-white/15 hover:border-white/30 focus:border-lime-400/70 focus:ring-lime-400/20`;
    const inputErr = `${inputBase} border-red-400/50 focus:border-red-400 focus:ring-red-400/20`;

    return (
        <div className="relative flex min-h-screen flex-col overflow-hidden bg-[#03140c] text-white lg:flex-row">
            <div className="absolute inset-0 bg-gradient-to-br from-[#03130b] via-[#052016] to-[#03241a]" />
            <Aurora />

            {/* Brand panel */}
            <div className="relative z-10 flex flex-col justify-between p-8 lg:w-1/2 lg:p-16">
                <div className="flex items-center gap-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white p-1.5 shadow-lg shadow-lime-500/20 ring-1 ring-lime-300/30">
                        <img src="/logo.png" alt="Multi Green Engineering" className="h-full w-full object-contain" />
                    </div>
                    <div>
                        <span className="block text-2xl font-black tracking-tighter text-white">MGE-PMS</span>
                        <span className="block text-[10px] font-bold uppercase tracking-[0.25em] text-lime-300/80">Engineering Excellence</span>
                    </div>
                </div>

                <div className="my-10 hidden max-w-lg lg:block">
                    <h1 className="text-5xl font-black leading-[1.05] tracking-tight text-white">
                        Constructing{' '}
                        <span className="bg-gradient-to-r from-lime-300 via-lime-400 to-emerald-400 bg-clip-text text-transparent">
                            The Future.
                        </span>
                    </h1>
                </div>

                <div className="hidden items-center justify-between border-t border-white/10 pt-8 lg:flex">
                    <p className="text-xs font-medium text-emerald-300/60">
                        © {new Date().getFullYear()} Multi Green Engineering Sdn. Bhd.
                    </p>
                    <a href="https://mge-eng.com" target="_blank" rel="noopener noreferrer"
                        className="text-[10px] font-bold uppercase tracking-widest text-lime-300 transition-colors hover:text-lime-200">
                        mge-eng.com
                    </a>
                </div>
            </div>

            {/* Form panel — glass card */}
            <div className="relative z-10 flex flex-1 items-center justify-center px-4 pb-12 lg:px-12">
                <div className="w-full max-w-md rounded-3xl border border-white/15 bg-white/[0.07] p-7 shadow-2xl shadow-black/40 backdrop-blur-2xl sm:p-9">
                    <div className="mb-8">
                        <h2 className="text-3xl font-black tracking-tight text-white">Portal Login</h2>
                        <p className="mt-2 text-sm font-medium text-emerald-200/60">Enter your credentials to manage infrastructure projects</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div className="space-y-1.5">
                            <label htmlFor="email" className="text-xs font-bold uppercase tracking-widest text-lime-200/80">Email Address</label>
                            <input id="email" name="email" type="email" autoComplete="email" required
                                value={formData.email} onChange={handleChange}
                                className={errors.email ? inputErr : inputOk} placeholder="name@mge-eng.com" />
                            {errors.email && <p className="mt-1 text-[11px] font-bold text-red-300">{errors.email[0]}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between">
                                <label htmlFor="password" className="text-xs font-bold uppercase tracking-widest text-lime-200/80">Access Code</label>
                                <Link to="/forgot-password" className="text-[11px] font-bold text-emerald-200/60 transition-colors hover:text-lime-300">Recovery Access</Link>
                            </div>
                            <div className="relative">
                                <input id="password" name="password" type={showPassword ? 'text' : 'password'} autoComplete="current-password" required
                                    value={formData.password} onChange={handleChange}
                                    className={`${errors.password ? inputErr : inputOk} pr-12`} placeholder="••••••••" />
                                <button type="button" onClick={() => setShowPassword(!showPassword)} tabIndex={-1}
                                    className="absolute right-4 top-1/2 -translate-y-1/2 text-white/40 hover:text-lime-300">
                                    {showPassword ? (
                                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                    ) : (
                                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    )}
                                </button>
                            </div>
                            {errors.password && <p className="mt-1 text-[11px] font-bold text-red-300">{errors.password[0]}</p>}
                        </div>

                        <button type="submit" disabled={submitting}
                            className="group relative flex w-full items-center justify-center overflow-hidden rounded-xl bg-gradient-to-r from-lime-400 to-emerald-500 px-4 py-4 text-sm font-black text-emerald-950 shadow-lg shadow-lime-500/25 transition-all hover:from-lime-300 hover:to-emerald-400 hover:shadow-lime-400/40 active:scale-[0.98] disabled:opacity-70">
                            <span className="relative z-10 flex items-center gap-2">
                                {submitting ? (
                                    <>
                                        <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" /></svg>
                                        Authenticating...
                                    </>
                                ) : (
                                    <>
                                        Sign In to Portal
                                        <svg className="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                    </>
                                )}
                            </span>
                        </button>
                    </form>

                    <div className="mt-8">
                        <div className="relative flex items-center py-2">
                            <div className="flex-grow border-t border-white/10" />
                            <span className="mx-4 flex-shrink text-[10px] font-black uppercase tracking-[0.2em] text-emerald-200/40">Quick Access</span>
                            <div className="flex-grow border-t border-white/10" />
                        </div>
                        <div className="mt-4 grid grid-cols-1 gap-2.5">
                            {[
                                { role: 'Project Director', email: 'admin@mge-pms.test', icon: '🏗️' },
                            ].map((acc) => (
                                <button key={acc.email} type="button" onClick={() => fillDemo(acc.email)}
                                    className="group flex w-full items-center gap-4 rounded-xl border border-white/10 bg-white/5 p-3 text-left transition-all hover:border-lime-400/40 hover:bg-lime-400/5 active:scale-[0.99]">
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-base">{acc.icon}</span>
                                    <div className="flex-1 overflow-hidden">
                                        <div className="text-sm font-bold text-white">{acc.role}</div>
                                        <div className="truncate text-xs text-emerald-200/50">{acc.email}</div>
                                    </div>
                                    <svg className="h-4 w-4 text-lime-400 opacity-0 transition-all group-hover:translate-x-1 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            ))}
                        </div>
                    </div>

                    <p className="mt-7 text-center text-sm font-medium text-emerald-200/60">
                        Unauthorized access is prohibited.{' '}
                        <Link to="/register" className="font-bold text-lime-300 underline decoration-lime-400/50 decoration-2 underline-offset-4 hover:text-lime-200">Request Credentials</Link>
                    </p>
                    <a href="https://mge-eng.com" target="_blank" rel="noopener noreferrer"
                        className="group mt-5 flex items-center justify-center gap-2 text-xs font-bold uppercase tracking-widest text-emerald-300/50 transition-colors hover:text-lime-300">
                        <svg className="h-4 w-4 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        Back to Main Website
                    </a>
                </div>
            </div>
        </div>
    );
}
