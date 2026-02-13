import { useAuth } from '@/context/AuthContext';

/**
 * Hook for checking permissions in components.
 *
 * Usage:
 *   const { can, canAny, hasRole } = usePermission();
 *   if (can('projects.create')) { ... }
 *   if (canAny(['projects.edit', 'projects.delete'])) { ... }
 */
export default function usePermission() {
    const { can, canAny, canAll, hasRole, hasAnyRole } = useAuth();

    return { can, canAny, canAll, hasRole, hasAnyRole };
}
