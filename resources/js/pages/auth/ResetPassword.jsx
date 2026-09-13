import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
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

export default function ResetPassword() {
    const [searchParams] = useSearchParams();
    const token = searchParams.get('token');
    const email = searchParams.get('email');
    const isInvalidLink = !token || !email;

    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [success, setSuccess] = useState(false);
    const [error, setError] = useState('');

    const inputBase = 'w-full rounded-xl border bg-white/5 px-4 py-3.5 text-sm text-white placeholder-white/40 backdrop-blur transition-all duration-200 focus:outline-none focus:ring-2';

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError('');
        try {
            const res = await authService.resetPassword({
                token,
                email,
                password,
                password_confirmation: confirmPassword
            });
            toast.success(res.message || 'Password successfully reset.');
            setSuccess(true);
        } catch (err) {
            if (err.response?.status === 422) {
                if (err.response.data?.errors) {
                    const firstField = Object.keys(err.response.data.errors)[0];
                    setError(err.response.data.errors[firstField]?.[0]);
                } else {
                    setError(err.response.data?.message || 'An error occurred while resetting your password.');
                }
            } else {
                toast.error(err.response?.data?.message || 'Security system error. Try again.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-[#03140c] px-4 py-12 text-white">
            <div className="absolute inset-0 bg-gradient-to-br from-[#03130b] via-[#052016] to-[#03241a]" />
            <Aurora />

            <div className="relative z-10 w-full max-w-md">
                {/* Brand */}
                <div className="mb-8 flex flex-col items-center">
                    <Link to="/login" className="group flex flex-col items-center gap-4 transition-transform active:scale-95">
                        <div className="flex h-16 w-16 items-center justify-center rounded-3xl bg-white p-2 shadow-lg shadow-lime-500/20 ring-1 ring-lime-300/30 transition-transform group-hover:scale-105">
                            <img src="/logo.png" alt="Multi Green Engineering" className="h-full w-full object-contain" />
                        </div>
                        <div className="text-center">
                            <span className="block text-2xl font-black tracking-tighter text-white">MGE-PMS</span>
                            <span className="block text-[10px] font-black uppercase tracking-[0.3em] text-lime-300/80">Security Gate</span>
                        </div>
                    </Link>
                </div>

                {isInvalidLink ? (
                    <div className="rounded-3xl border border-white/15 bg-white/[0.07] p-9 text-center shadow-2xl shadow-black/40 backdrop-blur-2xl">
                        <div className="mx-auto mb-8 flex h-20 w-20 items-center justify-center rounded-3xl bg-red-400/15 ring-1 ring-red-400/30">
                            <svg className="h-10 w-10 text-red-300" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h2 className="text-2xl font-black tracking-tight text-white">Invalid Reset Link</h2>
                        <p className="mt-4 text-base leading-relaxed text-emerald-200/70">
                            This password reset link is invalid, incomplete, or has already been used.
                        </p>
                        <div className="mt-10 flex flex-col gap-3">
                            <Link to="/forgot-password" className="flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-lime-400 to-emerald-500 px-6 py-4 text-sm font-black text-emerald-950 shadow-lg shadow-lime-500/25 transition-all hover:from-lime-300 hover:to-emerald-400">
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" /></svg>
                                Request New Reset Link
                            </Link>
                        </div>
                    </div>
                ) : success ? (
                    <div className="rounded-3xl border border-white/15 bg-white/[0.07] p-9 text-center shadow-2xl shadow-black/40 backdrop-blur-2xl">
                        <div className="mx-auto mb-8 flex h-20 w-20 items-center justify-center rounded-3xl bg-lime-400/15 ring-1 ring-lime-400/30">
                            <svg className="h-10 w-10 text-lime-300" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />
                            </svg>
                        </div>
                        <h2 className="text-2xl font-black tracking-tight text-white">Access Restored</h2>
                        <p className="mt-4 text-base leading-relaxed text-emerald-200/70">
                            Your password has been successfully updated. You may now sign in with your new credentials.
                        </p>
                        <div className="mt-10 flex flex-col gap-3">
                            <Link to="/login" className="flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-lime-400 to-emerald-500 px-6 py-4 text-sm font-black text-emerald-950 shadow-lg shadow-lime-500/25 transition-all hover:from-lime-300 hover:to-emerald-400">
                                Continue to Login
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                            </Link>
                        </div>
                    </div>
                ) : (
                    <div className="rounded-3xl border border-white/15 bg-white/[0.07] p-9 shadow-2xl shadow-black/40 backdrop-blur-2xl">
                        <div className="mb-8">
                            <div className="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-lime-400/15 text-lime-300 ring-1 ring-lime-400/30">
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                            </div>
                            <h2 className="text-3xl font-black tracking-tight text-white">Set New Credentials</h2>
                            <p className="mt-2 text-sm font-medium text-emerald-200/60">Choose a strong password to secure your account.</p>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-1.5">
                                <label htmlFor="password" className="text-[10px] font-bold uppercase tracking-widest text-lime-200/80">New Password</label>
                                <input id="password" name="password" type="password" autoComplete="new-password" required value={password}
                                    onChange={(e) => { setPassword(e.target.value); setError(''); }}
                                    className={`${inputBase} ${error ? 'border-red-400/50 focus:border-red-400 focus:ring-red-400/20' : 'border-white/15 hover:border-white/30 focus:border-lime-400/70 focus:ring-lime-400/20'}`}
                                    placeholder="••••••••" />
                                {error && <p className="mt-1.5 text-[11px] font-bold text-red-300">{error}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <label htmlFor="confirmPassword" className="text-[10px] font-bold uppercase tracking-widest text-lime-200/80">Confirm New Password</label>
                                <input id="confirmPassword" name="confirmPassword" type="password" autoComplete="new-password" required value={confirmPassword}
                                    onChange={(e) => { setConfirmPassword(e.target.value); setError(''); }}
                                    className={`${inputBase} border-white/15 hover:border-white/30 focus:border-lime-400/70 focus:ring-lime-400/20`}
                                    placeholder="••••••••" />
                            </div>

                            <button type="submit" disabled={submitting}
                                className="group relative flex w-full items-center justify-center overflow-hidden rounded-xl bg-gradient-to-r from-lime-400 to-emerald-500 px-4 py-4 text-sm font-black text-emerald-950 shadow-lg shadow-lime-500/25 transition-all hover:from-lime-300 hover:to-emerald-400 active:scale-[0.98] disabled:opacity-70">
                                <span className="relative z-10 flex items-center gap-2">
                                    {submitting ? (
                                        <>
                                            <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" /></svg>
                                            Updating...
                                        </>
                                    ) : (
                                        <>
                                            Update Password
                                            <svg className="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                        </>
                                    )}
                                </span>
                            </button>
                        </form>

                        <div className="mt-8 flex items-center justify-center gap-2 border-t border-white/10 pt-6">
                            <Link to="/forgot-password" className="text-xs font-bold uppercase tracking-widest text-emerald-300/50 transition-colors hover:text-lime-300">
                                Forgot this link?
                            </Link>
                        </div>
                    </div>
                )}

                <p className="mt-8 px-8 text-center text-[11px] font-medium leading-relaxed text-emerald-300/40">
                    Contact System Support if you have lost access to your inbox or need further assistance.
                </p>
            </div>
        </div>
    );
}
