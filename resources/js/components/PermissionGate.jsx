import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';

/**
 * Route guard that checks permissions before rendering child routes.
 *
 * Props:
 *   permission  - single permission string (e.g. "projects.view")
 *   permissions - array of permissions, user needs ANY to pass
 *   role        - single role string
 *   roles       - array of roles, user needs ANY to pass
 *
 * If the check fails → redirects to /unauthorized
 */
export default function PermissionGate({ permission, permissions, role, roles }) {
    const { can, canAny, hasRole, hasAnyRole } = useAuth();

    let allowed = false;

    if (permission) {
        allowed = can(permission);
    } else if (permissions) {
        allowed = canAny(permissions);
    } else if (role) {
        allowed = hasRole(role);
    } else if (roles) {
        allowed = hasAnyRole(roles);
    } else {
        // No restrictions specified → allow
        allowed = true;
    }

    if (!allowed) {
        return <Navigate to="/unauthorized" replace />;
    }

    return <Outlet />;
}
