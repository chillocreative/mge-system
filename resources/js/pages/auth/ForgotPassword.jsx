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
                setError(err.response.data.errors?.email?.[0] || 'Invalid email address.');
            } else if (err.response?.status === 404) {
                // Show success anyway to not leak whether email exists
                setSent(true);
            } else {
                toast.error(err.response?.data?.message || 'Something went wrong. Please try again.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-primary-50 px-6 py-12">
            <div className="w-full max-w-sm">
                {/* Logo */}
                <div className="mb-10 flex flex-col items-center">
                    <Link to="/login" className="flex items-center gap-3 group">
                        <svg width="48" height="48" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="40" height="40" rx="8" fill="#0f172a" className="transition-colors group-hover:fill-primary-600" />
                            <path d="M10 30V12L20 22L30 12V30" stroke="#f1f5f9" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
                            <line x1="8" y1="35" x2="32" y2="35" stroke="#facc15" strokeWidth="2.5" strokeLinecap="round" />
                        </svg>
                        <span className="text-xl font-bold text-primary-700 group-hover:text-primary-600 transition-colors">MGE-PMS</span>
                    </Link>
                </div>

                {sent ? (
                    /* Success state */
                    <div className="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-primary-200 text-center">
                        <div className="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-green-100 ring-8 ring-green-50">
                            <svg className="h-7 w-7 text-green-600" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 001.183 1.981l6.478 3.488m8.839 2.51l-4.66-2.51m0 0l-1.023-.55a2.25 2.25 0 00-2.134 0l-1.022.55m0 0l-4.661 2.51m16.5 1.615a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V8.844a2.25 2.25 0 011.183-1.98l7.5-4.04a2.25 2.25 0 012.134 0l7.5 4.04a2.25 2.25 0 011.183 1.98V19.5z" />
                            </svg>
                        </div>
                        <h2 className="text-xl font-bold text-primary-700">Check your inbox</h2>
                        <p className="mt-3 text-sm leading-relaxed text-primary-400">
                            If an account exists for <strong className="text-primary-600">{email}</strong>, we've sent a password reset link. Check your spam folder if you don't see it.
                        </p>
                        <Link
                            to="/login"
                            className="mt-7 inline-flex items-center gap-2 rounded-lg bg-primary-700 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-accent-400 focus:ring-offset-2"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            Back to Login
                        </Link>
                        <button
                            type="button"
                            onClick={() => { setSent(false); setEmail(''); }}
                            className="mt-3 block w-full text-center text-sm text-primary-400 hover:text-primary-600"
                        >
                            Try a different email
                        </button>
                    </div>
                ) : (
                    /* Form state */
                    <div className="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-primary-200">
                        <div className="mb-7">
                            <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-accent-50 ring-1 ring-accent-200">
                                <svg className="h-6 w-6 text-accent-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                            </div>
                            <h2 className="text-2xl font-bold text-primary-700">Forgot password?</h2>
                            <p className="mt-1.5 text-sm text-primary-400">
                                Enter your email and we'll send you a reset link.
                            </p>
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
                                    value={email}
                                    onChange={(e) => { setEmail(e.target.value); setError(''); }}
                                    className={`w-full rounded-lg border px-4 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 ${
                                        error
                                            ? 'border-red-400 bg-red-50 text-primary-700 focus:border-red-500 focus:ring-red-400'
                                            : 'border-primary-200 bg-white text-primary-700 placeholder-primary-300 focus:border-accent-400 focus:ring-accent-400'
                                    }`}
                                    placeholder="you@mge-eng.com"
                                />
                                {error && <p className="mt-1.5 text-xs text-red-600">{error}</p>}
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
                                        Sending reset link...
                                    </span>
                                ) : (
                                    'Send reset link'
                                )}
                            </button>
                        </form>

                        <div className="mt-6 flex items-center justify-center gap-1.5">
                            <svg className="h-4 w-4 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            <Link to="/login" className="text-sm font-medium text-primary-400 hover:text-primary-600">
                                Back to login
                            </Link>
                        </div>
                    </div>
                )}

                <p className="mt-6 text-center text-xs text-primary-400">
                    Don't have an account?{' '}
                    <Link to="/register" className="font-semibold text-accent-600 hover:text-accent-500">
                        Register here
                    </Link>
                </p>
            </div>
        </div>
    );
}
