import { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import profileService from '@/services/profileService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineUserCircle } from 'react-icons/hi';

export default function Profile() {
    const { user, loading, refreshUser } = useAuth();

    const [form, setForm] = useState({ full_name: '', email: '', phone: '', ic_number: '' });
    const [avatarFile, setAvatarFile] = useState(null);
    const [previewUrl, setPreviewUrl] = useState('');
    const [avatarError, setAvatarError] = useState(false);
    const [avatarVersion, setAvatarVersion] = useState(0);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    const [currentPassword, setCurrentPassword] = useState('');

    const [pwdForm, setPwdForm] = useState({ current_password: '', password: '', password_confirmation: '' });
    const [pwdErrors, setPwdErrors] = useState({});
    const [savingPwd, setSavingPwd] = useState(false);

    // Reinitialize local form state once the async user fetch completes
    useEffect(() => {
        if (user) {
            setForm({
                full_name: user.full_name || '',
                email: user.email || '',
                phone: user.phone || '',
                ic_number: user.ic_number || '',
            });
        }
    }, [user]);

    // Clean up object URLs to prevent memory leaks
    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const handleAvatarChange = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        // Revoke previous URL to avoid memory leaks
        if (previewUrl) URL.revokeObjectURL(previewUrl);

        setAvatarFile(file);
        setPreviewUrl(URL.createObjectURL(file));
        setAvatarError(false);
    };

    const handleImageError = () => {
        setAvatarError(true);
    };

    const handleProfileSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const formData = new FormData();
            formData.append('full_name', form.full_name);
            formData.append('email', form.email);
            formData.append('phone', form.phone || '');
            formData.append('ic_number', form.ic_number || '');

            if (emailChanged && currentPassword) {
                formData.append('current_password', currentPassword);
            }

            // CRITICAL: Only append avatar if a new file was explicitly chosen
            if (avatarFile) {
                formData.append('avatar', avatarFile);
            }

            await profileService.updateProfile(formData);
            toast.success('Profile updated successfully');
            if (avatarFile) setAvatarVersion((v) => v + 1);
            refreshUser();
            setAvatarFile(null);
            setPreviewUrl('');
            setCurrentPassword('');
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors || {});
            } else {
                toast.error(err.response?.data?.message || 'Failed to update profile');
            }
        } finally {
            setSaving(false);
        }
    };

    const handlePasswordSubmit = async (e) => {
        e.preventDefault();
        setSavingPwd(true);
        setPwdErrors({});
        try {
            await profileService.changePassword(pwdForm);
            toast.success('Password changed successfully');
            setPwdForm({ current_password: '', password: '', password_confirmation: '' });
        } catch (err) {
            if (err.response?.status === 422) {
                setPwdErrors(err.response.data.errors || {});
            } else {
                toast.error(err.response?.data?.message || 'Failed to change password');
            }
        } finally {
            setSavingPwd(false);
        }
    };

    if (loading) return <LoadingSpinner />;
    if (!user) return null;

    const initials = `${(user.first_name || '')[0]}${(user.last_name || '')[0]}`;
    const showInitials = avatarError || (!user.avatar && !previewUrl);
    const showImg = !avatarError && (!!previewUrl || !!user.avatar);
    const emailChanged = form.email !== (user.email || '');

    return (
        <div>
            <div className="mb-6">
                <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                    <HiOutlineUserCircle className="h-6 w-6 text-primary-600" /> My Profile
                </h1>
                <p className="text-sm text-gray-500">Manage your personal information and account security.</p>
            </div>

            <div className="space-y-6">
                {/* Profile Information Card */}
                <form onSubmit={handleProfileSubmit} className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Profile Information</h2>

                    <div className="mb-6 flex items-center gap-4">
                        <div className="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full">
                            {showInitials ? (
                                <div className="flex h-full w-full items-center justify-center rounded-full bg-accent-400 text-lg font-bold text-primary-900">
                                    {initials}
                                </div>
                            ) : showImg ? (
                                <img
                                    src={previewUrl || `${profileService.getAvatarUrl()}?v=${avatarVersion}`}
                                    alt="Profile"
                                    className="h-20 w-20 rounded-full object-cover"
                                    onError={handleImageError}
                                />
                            ) : null}
                        </div>
                        <div>
                            <label htmlFor="avatar-upload" className="inline-flex cursor-pointer items-center rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-primary-600">
                                Change photo
                            </label>
                            <input
                                id="avatar-upload"
                                type="file"
                                accept="image/*"
                                onChange={handleAvatarChange}
                                className="sr-only"
                            />
                            <p className="mt-1 text-xs text-gray-400">JPG, PNG or GIF (max 5MB)</p>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Full Name *</label>
                            <input
                                type="text"
                                required
                                value={form.full_name}
                                onChange={(e) => setForm((p) => ({ ...p, full_name: e.target.value }))}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {errors.full_name && <p className="mt-1 text-xs text-red-500">{errors.full_name[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Email *</label>
                            <input
                                type="email"
                                required
                                value={form.email}
                                onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Phone</label>
                            <input
                                type="text"
                                value={form.phone}
                                onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {errors.phone && <p className="mt-1 text-xs text-red-500">{errors.phone[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">IC Number</label>
                            <input
                                type="text"
                                value={form.ic_number}
                                onChange={(e) => setForm((p) => ({ ...p, ic_number: e.target.value }))}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {errors.ic_number && <p className="mt-1 text-xs text-red-500">{errors.ic_number[0]}</p>}
                        </div>

                        {emailChanged && (
                            <div className="sm:col-span-2">
                                <label className="mb-1 block text-sm font-medium text-gray-700">Current Password *</label>
                                <input
                                    type="password"
                                    required
                                    value={currentPassword}
                                    onChange={(e) => setCurrentPassword(e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                                <p className="mt-1 text-xs text-gray-400">Required to confirm you're changing the account's email.</p>
                                {errors.current_password && <p className="mt-1 text-xs text-red-500">{errors.current_password[0]}</p>}
                            </div>
                        )}
                    </div>

                    <div className="mt-6 flex justify-end">
                        <button
                            type="submit"
                            disabled={saving}
                            className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                        >
                            {saving ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </form>

                {/* Change Password Card */}
                <form onSubmit={handlePasswordSubmit} className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Change Password</h2>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Current Password *</label>
                            <input
                                type="password"
                                required
                                value={pwdForm.current_password}
                                onChange={(e) => setPwdForm((p) => ({ ...p, current_password: e.target.value }))}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {pwdErrors.current_password && <p className="mt-1 text-xs text-red-500">{pwdErrors.current_password[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">New Password *</label>
                            <input
                                type="password"
                                required
                                value={pwdForm.password}
                                onChange={(e) => setPwdForm((p) => ({ ...p, password: e.target.value }))}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {pwdErrors.password && <p className="mt-1 text-xs text-red-500">{pwdErrors.password[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Confirm New Password *</label>
                            <input
                                type="password"
                                required
                                value={pwdForm.password_confirmation}
                                onChange={(e) => setPwdForm((p) => ({ ...p, password_confirmation: e.target.value }))}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {pwdErrors.password_confirmation && <p className="mt-1 text-xs text-red-500">{pwdErrors.password_confirmation[0]}</p>}
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end">
                        <button
                            type="submit"
                            disabled={savingPwd}
                            className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                        >
                            {savingPwd ? 'Updating...' : 'Update Password'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
