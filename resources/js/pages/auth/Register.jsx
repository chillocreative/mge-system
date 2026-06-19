import { useState } from 'react';
import { Link } from 'react-router-dom';
import authService from '@/services/authService';
import toast from 'react-hot-toast';

function Aurora() {
    return (
        <>
            <style>{`
                @keyframes auroraFloat { 0%,100%{transform:translate(0,0) scale(1)} 33%{transform:translate(40px,-50px) scale(1.15)} 66%{transform:translate(-30px,30px) scale(0.92)} }
                @keyframes auroraFloat2 { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(-50px,40px) scale(1.2)} }
            `}</style>
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
                <div className="absolute -left-32 -top-32 h-[34rem] w-[34rem] rounded-full bg-emerald-500/30 blur-[120px]" style={{ animation: 'auroraFloat 16s ease-in-out infinite' }} />
                <div className="absolute right-[-10rem] top-1/4 h-[30rem] w-[30rem] rounded-full bg-lime-400/25 blur-[130px]" style={{ animation: 'auroraFloat2 19s ease-in-out infinite' }} />
                <div className="absolute bottom-[-12rem] left-1/3 h-[32rem] w-[32rem] rounded-full bg-teal-400/20 blur-[140px]" style={{ animation: 'auroraFloat 22s ease-in-out infinite' }} />
                <div className="absolute inset-0 opacity-[0.07]" style={{
                    backgroundImage: 'linear-gradient(rgba(190,242,100,0.4) 1px, transparent 1px), linear-gradient(90deg, rgba(190,242,100,0.4) 1px, transparent 1px)',
                    backgroundSize: '46px 46px',
                    maskImage: 'radial-gradient(ellipse at center, black 30%, transparent 75%)',
                }} />
            </div>
        </>
    );
}

export default function Register() {
    const [formData, setFormData] = useState({
        full_name: '', ic_number: '', email: '', password: '', password_confirmation: '', phone: '',
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
        'w-full rounded-xl border bg-white/5 px-4 py-3 text-sm text-white placeholder-white/40 backdrop-blur transition-all duration-200 focus:outline-none focus:ring-2';
    const inputOk = `${inputBase} border-white/15 hover:border-white/30 focus:border-lime-400/70 focus:ring-lime-400/20`;
    const inputErr = `${inputBase} border-red-400/50 focus:border-red-400 focus:ring-red-400/20`;
    const field = (name) => (errors[name] ? inputErr : inputOk);

    if (registered) {
        return (
            <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#03140c] px-6 py-12 text-white">
                <div className="absolute inset-0 bg-gradient-to-br from-[#03130b] via-[#052016] to-[#03241a]" />
                <Aurora />
                <div className="relative z-10 w-full max-w-md rounded-3xl border border-white/15 bg-white/[0.07] p-9 text-center shadow-2xl shadow-black/40 backdrop-blur-2xl">
                    <div className="mx-auto mb-8 flex h-20 w-20 items-center justify-center rounded-3xl bg-lime-400/15 ring-1 ring-lime-400/30">
                        <svg className="h-10 w-10 text-lime-300" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 className="text-3xl font-black tracking-tight text-white">Request Received</h2>
                    <p className="mt-4 text-base leading-relaxed text-emerald-200/70">
                        Your account <span className="font-bold text-lime-300">({formData.email})</span> has been submitted for verification. You will be notified once a System Administrator approves your access.
                    </p>
                    <div className="mt-10">
                        <Link to="/login" className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-lime-400 to-emerald-500 px-8 py-4 text-sm font-black text-emerald-950 shadow-lg shadow-lime-500/25 transition-all hover:from-lime-300 hover:to-emerald-400 active:scale-[0.98]">
                            Return to Secure Login
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        </Link>
                    </div>
                </div>
            </div>
        );
    }

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
                        <span className="block text-[10px] font-bold uppercase tracking-[0.25em] text-lime-300/80">Join the Force</span>
                    </div>
                </div>

                <div className="my-10 hidden max-w-lg lg:block">
                    <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-lime-300/20 bg-lime-300/5 px-4 py-1.5 backdrop-blur">
                        <span className="text-xs font-bold uppercase tracking-widest text-lime-200/90">Workforce Enrollment</span>
                    </div>
                    <h1 className="text-5xl font-black leading-[1.05] tracking-tight text-white">
                        Empowering{' '}
                        <span className="bg-gradient-to-r from-lime-300 via-lime-400 to-emerald-400 bg-clip-text text-transparent">Engineering Talent.</span>
                    </h1>
                    <p className="mt-6 text-lg leading-relaxed text-emerald-100/70">
                        Become part of Malaysia's leading civil engineering team. Access specialized tools for project oversight and safety management.
                    </p>
                </div>

                <div className="hidden items-center justify-between border-t border-white/10 pt-8 lg:flex">
                    <p className="text-xs font-medium text-emerald-300/60">© {new Date().getFullYear()} Multi Green Engineering Sdn. Bhd.</p>
                </div>
            </div>

            {/* Form panel — glass card */}
            <div className="relative z-10 flex flex-1 items-center justify-center px-4 py-10 lg:px-12">
                <div className="w-full max-w-md rounded-3xl border border-white/15 bg-white/[0.07] p-7 shadow-2xl shadow-black/40 backdrop-blur-2xl sm:p-9">
                    <div className="mb-7">
                        <h2 className="text-3xl font-black tracking-tight text-white">Get Started</h2>
                        <p className="mt-2 text-sm font-medium text-emerald-200/60">Submit your professional details for system access</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-1.5">
                            <label htmlFor="full_name" className="text-[10px] font-bold uppercase tracking-widest text-lime-200/80">Full Name</label>
                            <input id="full_name" name="full_name" type="text" required value={formData.full_name} onChange={handleChange} className={field('full_name')} placeholder="Ahmad Razif" />
                            {errors.full_name && <p className="mt-1 text-[10px] font-bold text-red-300">{errors.full_name[0]}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <label htmlFor="ic_number" className="text-[10px] font-bold uppercase tracking-widest text-lime-200/80">IC Number</label>
                            <input id="ic_number" name="ic_number" type="text" value={formData.ic_number} onChange={handleChange} className={field('ic_number')} placeholder="e.g. 901231-14-5678" />
                            {errors.ic_number && <p className="mt-1 text-[10px] font-bold text-red-300">{errors.ic_number[0]}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <label htmlFor="email" className="text-[10px] font-bold uppercase tracking-widest text-lime-200/80">Email Address</label>
                            <input id="email" name="email" type="email" autoComplete="email" required value={formData.email} onChange={handleChange} className={field('email')} placeholder="name@mge-eng.com" />
                            {errors.email && <p className="mt-1 text-[10px] font-bold text-red-300">{errors.email[0]}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <label htmlFor="phone" className="text-[10px] font-bold uppercase tracking-widest text-lime-200/80">Contact Number</label>
                            <input id="phone" name="phone" type="tel" value={formData.phone} onChange={handleChange} className={field('phone')} placeholder="+60 1x-xxx xxxx" />
                        </div>

                        <div className="space-y-1.5">
                            <label htmlFor="password" className="text-[10px] font-bold uppercase tracking-widest text-lime-200/80">Security Password</label>
                            <div className="relative">
                                <input id="password" name="password" type={showPassword ? 'text' : 'password'} required value={formData.password} onChange={handleChange} className={`${field('password')} pr-12`} placeholder="Min. 8 characters" />
                                <button type="button" onClick={() => setShowPassword(!showPassword)} tabIndex={-1} className="absolute right-4 top-1/2 -translate-y-1/2 text-white/40 hover:text-lime-300">
                                    {showPassword ? (
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                    ) : (
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    )}
                                </button>
                            </div>
                            {errors.password && <p className="mt-1 text-[10px] font-bold text-red-300">{errors.password[0]}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <label htmlFor="password_confirmation" className="text-[10px] font-bold uppercase tracking-widest text-lime-200/80">Verify Password</label>
                            <input id="password_confirmation" name="password_confirmation" type={showPassword ? 'text' : 'password'} required value={formData.password_confirmation} onChange={handleChange} className={field('password_confirmation')} placeholder="Repeat password" />
                            {errors.password_confirmation && <p className="mt-1 text-[10px] font-bold text-red-300">{errors.password_confirmation[0]}</p>}
                        </div>

                        <div className="rounded-xl border border-lime-300/15 bg-lime-300/5 px-4 py-3 text-[11px] font-medium leading-relaxed text-emerald-100/70">
                            Note: Registration requests are subject to <span className="font-bold text-lime-300">manual verification</span> by the HR department.
                        </div>

                        <button type="submit" disabled={submitting} className="group relative flex w-full items-center justify-center overflow-hidden rounded-xl bg-gradient-to-r from-lime-400 to-emerald-500 px-4 py-4 text-sm font-black text-emerald-950 shadow-lg shadow-lime-500/25 transition-all hover:from-lime-300 hover:to-emerald-400 active:scale-[0.98] disabled:opacity-70">
                            <span className="relative z-10 flex items-center gap-2">
                                {submitting ? (
                                    <>
                                        <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" /></svg>
                                        Processing...
                                    </>
                                ) : (
                                    <>
                                        Submit Registration
                                        <svg className="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                    </>
                                )}
                            </span>
                        </button>
                    </form>

                    <p className="mt-7 text-center text-sm font-medium text-emerald-200/60">
                        Already have access?{' '}
                        <Link to="/login" className="font-bold text-lime-300 underline decoration-lime-400/50 decoration-2 underline-offset-4 hover:text-lime-200">Sign In</Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
