import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import Logo from '@/components/Logo';
import toast from 'react-hot-toast';

export default function Register() {
    const { register } = useAuth();
    const navigate = useNavigate();

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

    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
        setErrors({ ...errors, [e.target.name]: '' });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});

        try {
            await register(formData);
            toast.success('Account created successfully!');
            navigate('/dashboard', { replace: true });
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

    const inputClass =
        'w-full rounded-lg border border-primary-200 px-3.5 py-2.5 text-sm shadow-sm transition-colors focus:border-accent-400 focus:outline-none focus:ring-1 focus:ring-accent-400';

    return (
        <div className="flex min-h-screen items-center justify-center bg-primary-100 px-4 py-12">
            <div className="w-full max-w-md">
                <div className="mb-8 flex flex-col items-center">
                    <Logo size={48} variant="dark" />
                    <h1 className="mt-4 text-2xl font-bold text-primary-700">MGE-PMS</h1>
                    <p className="mt-1 text-sm text-primary-400">
                        Project Management System
                    </p>
                </div>

                <div className="rounded-xl bg-white p-8 shadow-sm ring-1 ring-primary-200">
                    <h2 className="mb-6 text-xl font-semibold text-primary-700">
                        Create your account
                    </h2>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label htmlFor="first_name" className="mb-1.5 block text-sm font-medium text-primary-500">
                                    First name
                                </label>
                                <input
                                    id="first_name"
                                    name="first_name"
                                    type="text"
                                    required
                                    value={formData.first_name}
                                    onChange={handleChange}
                                    className={inputClass}
                                />
                                {errors.first_name && (
                                    <p className="mt-1 text-sm text-red-600">{errors.first_name[0]}</p>
                                )}
                            </div>
                            <div>
                                <label htmlFor="last_name" className="mb-1.5 block text-sm font-medium text-primary-500">
                                    Last name
                                </label>
                                <input
                                    id="last_name"
                                    name="last_name"
                                    type="text"
                                    required
                                    value={formData.last_name}
                                    onChange={handleChange}
                                    className={inputClass}
                                />
                                {errors.last_name && (
                                    <p className="mt-1 text-sm text-red-600">{errors.last_name[0]}</p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-primary-500">
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
                                className={inputClass}
                                placeholder="you@example.com"
                            />
                            {errors.email && (
                                <p className="mt-1 text-sm text-red-600">{errors.email[0]}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="phone" className="mb-1.5 block text-sm font-medium text-primary-500">
                                Phone (optional)
                            </label>
                            <input
                                id="phone"
                                name="phone"
                                type="text"
                                value={formData.phone}
                                onChange={handleChange}
                                className={inputClass}
                            />
                        </div>

                        <div>
                            <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-primary-500">
                                Password
                            </label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                value={formData.password}
                                onChange={handleChange}
                                className={inputClass}
                                placeholder="Min. 8 characters"
                            />
                            {errors.password && (
                                <p className="mt-1 text-sm text-red-600">{errors.password[0]}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="password_confirmation" className="mb-1.5 block text-sm font-medium text-primary-500">
                                Confirm password
                            </label>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                required
                                value={formData.password_confirmation}
                                onChange={handleChange}
                                className={inputClass}
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={submitting}
                            className="w-full rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-accent-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {submitting ? 'Creating account...' : 'Create account'}
                        </button>
                    </form>

                    <p className="mt-6 text-center text-sm text-primary-400">
                        Already have an account?{' '}
                        <Link
                            to="/login"
                            className="font-medium text-accent-600 hover:text-accent-500"
                        >
                            Sign in
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
