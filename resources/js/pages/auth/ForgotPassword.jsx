import { useState } from 'react';
import { Link } from 'react-router-dom';
import apiClient from '@/services/apiClient';
import toast from 'react-hot-toast';

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
                setError(err.response.data.errors?.email?.[0] || 'Invalid corporate email.');
            } else if (err.response?.status === 404) {
                setSent(true);
            } else {
                toast.error(err.response?.data?.message || 'Security system error. Try again.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-6 py-12">
            <div className="w-full max-w-[440px]">
                {/* Brand Header */}
                <div className="mb-10 flex flex-col items-center">
                    <Link to="/login" className="group flex flex-col items-center gap-4 transition-transform active:scale-95">
                        <div className="flex h-16 w-16 items-center justify-center rounded-3xl bg-primary-800 shadow-xl shadow-primary-900/20 group-hover:bg-primary-900 transition-colors">
                            <svg width="36" height="36" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <ellipse cx="20" cy="22" rx="18" ry="14" stroke="#bef264" strokeWidth="2.5" />
                                <path d="M12 24C12 20 9 17 9 17C9 17 6 20 6 24C6 28 9 29 9 29C9 29 12 28 12 24Z" fill="#bef264" />
                                <path d="M23 21C23 16 20 13 20 13C20 13 17 16 17 21C17 26 20 29 20 29C20 29 23 26 23 21Z" fill="#bef264" />
                                <path d="M34 24C34 20 31 17 31 17C31 17 28 20 28 24C28 28 31 29 31 29C31 29 34 28 34 24Z" fill="#bef264" />
                            </svg>
                        </div>
                        <div className="text-center">
                            <span className="block text-2xl font-black tracking-tighter text-primary-900">MGE-PMS</span>
                            <span className="block text-[10px] font-black uppercase tracking-[0.3em] text-primary-400">Security Gate</span>
                        </div>
                    </Link>
                </div>

                {sent ? (
                    /* Success State */
                    <div className="overflow-hidden rounded-[2.5rem] bg-white p-10 shadow-2xl shadow-primary-900/5 ring-1 ring-primary-100 text-center">
                        <div className="mx-auto mb-8 flex h-20 w-20 items-center justify-center rounded-full bg-green-50 ring-8 ring-green-50/50">
                            <svg className="h-10 w-10 text-green-600" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                        </div>
                        <h2 className="text-2xl font-black tracking-tight text-primary-900">Transmission Sent</h2>
                        <p className="mt-4 text-base leading-relaxed text-primary-500">
                            If <span className="font-bold text-primary-800">{email}</span> matches our corporate records, a recovery link has been dispatched.
                        </p>
                        
                        <div className="mt-10 flex flex-col gap-3">
                            <Link
                                to="/login"
                                className="flex items-center justify-center gap-2 rounded-2xl bg-primary-800 px-6 py-4 text-sm font-bold text-white shadow-xl shadow-primary-900/10 transition-all hover:bg-primary-900"
                            >
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M15 19l-7-7 7-7" />
                                </svg>
                                Back to Portal Login
                            </Link>
                            <button
                                type="button"
                                onClick={() => { setSent(false); setEmail(''); }}
                                className="text-xs font-bold uppercase tracking-widest text-primary-400 hover:text-primary-900 transition-colors py-2"
                            >
                                Try Alternate Email
                            </button>
                        </div>
                    </div>
                ) : (
                    /* Form State */
                    <div className="overflow-hidden rounded-[2.5rem] bg-white p-10 shadow-2xl shadow-primary-900/5 ring-1 ring-primary-100">
                        <div className="mb-8">
                            <div className="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-accent-50 text-accent-600 ring-1 ring-accent-200">
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <h2 className="text-3xl font-black tracking-tight text-primary-900">Account Recovery</h2>
                            <p className="mt-2 text-sm font-medium text-primary-500">
                                Enter your verified corporate email to initiate the reset protocol.
                            </p>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-1.5">
                                <label htmlFor="email" className="text-[10px] font-bold uppercase tracking-widest text-primary-700">
                                    System Email
                                </label>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    autoComplete="email"
                                    required
                                    value={email}
                                    onChange={(e) => { setEmail(e.target.value); setError(''); }}
                                    className={`w-full rounded-2xl border px-5 py-4 text-sm transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-primary-500/10 ${
                                        error
                                            ? 'border-red-200 bg-red-50 text-red-900 focus:border-red-500'
                                            : 'border-primary-100 bg-white text-primary-900 placeholder-primary-300 hover:border-primary-300 focus:border-primary-600 shadow-sm'
                                    }`}
                                    placeholder="name@mge-eng.com"
                                />
                                {error && <p className="mt-1.5 text-[11px] font-bold text-red-600">{error}</p>}
                            </div>

                            <button
                                type="submit"
                                disabled={submitting}
                                className="group relative flex w-full items-center justify-center overflow-hidden rounded-2xl bg-primary-800 px-4 py-4 text-sm font-bold text-white shadow-xl shadow-primary-900/10 transition-all hover:bg-primary-900 hover:shadow-primary-900/20 active:scale-[0.98] disabled:opacity-70"
                            >
                                <span className="relative z-10 flex items-center gap-2">
                                    {submitting ? (
                                        <>
                                            <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                                            </svg>
                                            Verifying...
                                        </>
                                    ) : (
                                        <>
                                            Request Reset Link
                                            <svg className="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                            </svg>
                                        </>
                                    )}
                                </span>
                            </button>
                        </form>

                        <div className="mt-8 flex items-center justify-center gap-2 border-t border-primary-50 pt-6">
                            <Link to="/login" className="text-xs font-bold uppercase tracking-widest text-primary-400 hover:text-primary-900 transition-colors">
                                Return to Credentials Entry
                            </Link>
                        </div>
                    </div>
                )}

                <p className="mt-10 text-center text-[11px] font-medium leading-relaxed text-primary-400 px-8">
                    Contact System Support if you have lost access to your corporate inbox or need further assistance.
                </p>
            </div>
        </div>
    );
}
