import { useState } from 'react';
import { Link } from 'react-router-dom';
import apiClient from '@/services/apiClient';
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

export default function ForgotPassword() {
    const [email, setEmail] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [sent, setSent] = useState(false);
    const [error, setError] = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError('');
        try {
            await apiClient.post('/forgot-password', { email });
            setSent(true);
        } catch (err) {
            if (err.response?.status === 422) {
                setError(err.response.data.errors?.email?.[0] || 'Invalid email address.');
            } else if (err.response?.status === 404) {
                setSent(true);
            } else {
                toast.error(err.response?.data?.message || 'Security system error. Try again.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    const inputBase = 'w-full rounded-xl border bg-white/5 px-4 py-3.5 text-sm text-white placeholder-white/40 backdrop-blur transition-all duration-200 focus:outline-none focus:ring-2';

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

                {sent ? (
                    <div className="rounded-3xl border border-white/15 bg-white/[0.07] p-9 text-center shadow-2xl shadow-black/40 backdrop-blur-2xl">
                        <div className="mx-auto mb-8 flex h-20 w-20 items-center justify-center rounded-3xl bg-lime-400/15 ring-1 ring-lime-400/30">
                            <svg className="h-10 w-10 text-lime-300" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                        </div>
                        <h2 className="text-2xl font-black tracking-tight text-white">Transmission Sent</h2>
                        <p className="mt-4 text-base leading-relaxed text-emerald-200/70">
                            If <span className="font-bold text-lime-300">{email}</span> matches our records, a recovery link has been dispatched.
                        </p>
                        <div className="mt-10 flex flex-col gap-3">
                            <Link to="/login" className="flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-lime-400 to-emerald-500 px-6 py-4 text-sm font-black text-emerald-950 shadow-lg shadow-lime-500/25 transition-all hover:from-lime-300 hover:to-emerald-400">
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M15 19l-7-7 7-7" /></svg>
                                Back to Portal Login
                            </Link>
                            <button type="button" onClick={() => { setSent(false); setEmail(''); }}
                                className="py-2 text-xs font-bold uppercase tracking-widest text-emerald-300/50 transition-colors hover:text-lime-300">
                                Try Alternate Email
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="rounded-3xl border border-white/15 bg-white/[0.07] p-9 shadow-2xl shadow-black/40 backdrop-blur-2xl">
                        <div className="mb-8">
                            <div className="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-lime-400/15 text-lime-300 ring-1 ring-lime-400/30">
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            </div>
                            <h2 className="text-3xl font-black tracking-tight text-white">Account Recovery</h2>
                            <p className="mt-2 text-sm font-medium text-emerald-200/60">Enter your email address to initiate the reset protocol.</p>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-1.5">
                                <label htmlFor="email" className="text-[10px] font-bold uppercase tracking-widest text-lime-200/80">Email Address</label>
                                <input id="email" name="email" type="email" autoComplete="email" required value={email}
                                    onChange={(e) => { setEmail(e.target.value); setError(''); }}
                                    className={`${inputBase} ${error ? 'border-red-400/50 focus:border-red-400 focus:ring-red-400/20' : 'border-white/15 hover:border-white/30 focus:border-lime-400/70 focus:ring-lime-400/20'}`}
                                    placeholder="name@mge-eng.com" />
                                {error && <p className="mt-1.5 text-[11px] font-bold text-red-300">{error}</p>}
                            </div>

                            <button type="submit" disabled={submitting}
                                className="group relative flex w-full items-center justify-center overflow-hidden rounded-xl bg-gradient-to-r from-lime-400 to-emerald-500 px-4 py-4 text-sm font-black text-emerald-950 shadow-lg shadow-lime-500/25 transition-all hover:from-lime-300 hover:to-emerald-400 active:scale-[0.98] disabled:opacity-70">
                                <span className="relative z-10 flex items-center gap-2">
                                    {submitting ? (
                                        <>
                                            <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" /></svg>
                                            Verifying...
                                        </>
                                    ) : (
                                        <>
                                            Request Reset Link
                                            <svg className="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                        </>
                                    )}
                                </span>
                            </button>
                        </form>

                        <div className="mt-8 flex items-center justify-center gap-2 border-t border-white/10 pt-6">
                            <Link to="/login" className="text-xs font-bold uppercase tracking-widest text-emerald-300/50 transition-colors hover:text-lime-300">
                                Return to Login
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
