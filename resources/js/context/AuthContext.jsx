import { createContext, useContext, useState, useEffect, useCallback, useMemo } from 'react';
import authService from '@/services/authService';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    const fetchUser = useCallback(async () => {
        try {
            const response = await authService.getUser();
            setUser(response.data);
        } catch {
            setUser(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchUser();
    }, [fetchUser]);

    const login = async (credentials) => {
        const response = await authService.login(credentials);
        setUser(response.data);
        return response;
    };

    const register = async (data) => {
        const response = await authService.register(data);
        setUser(response.data);
        return response;
    };

    const logout = async () => {
        await authService.logout();
        setUser(null);
    };

    // ── Role checks ──

    const hasRole = useCallback(
        (role) => user?.roles?.includes(role) ?? false,
        [user]
    );

    const hasAnyRole = useCallback(
        (roles) => roles.some((r) => user?.roles?.includes(r)),
        [user]
    );

    // ── Permission checks ──

    const can = useCallback(
        (permission) => {
            if (!user) return false;
            // Admin & HR bypasses all checks (mirrors Gate::before on backend)
            if (user.roles?.includes('Admin & HR')) return true;
            return user.permissions?.includes(permission) ?? false;
        },
        [user]
    );

    const canAny = useCallback(
        (permissions) => permissions.some((p) => can(p)),
        [can]
    );

    const canAll = useCallback(
        (permissions) => permissions.every((p) => can(p)),
        [can]
    );

    const value = useMemo(
        () => ({
            user,
            loading,
            login,
            register,
            logout,
            isAuthenticated: !!user,
            // Role helpers
            hasRole,
            hasAnyRole,
            // Permission helpers
            can,
            canAny,
            canAll,
        }),
        [user, loading, hasRole, hasAnyRole, can, canAny, canAll]
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
}

export default AuthContext;
