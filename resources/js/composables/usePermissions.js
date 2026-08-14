import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Permission helpers backed by shared Inertia props.
 *
 *   const { can, isAdmin } = usePermissions();
 *   if (can('assets', 'edit')) { ... }
 *   <button v-if="can('assets', 'delete')">Delete</button>
 *
 * Admins bypass all checks. Non-admins are checked against the flat
 * permission list shared as $page.props.auth.permissions.
 */
export function usePermissions() {
    const page = usePage();

    const isAdmin = computed(() => !!page.props.auth?.user?.is_admin);
    const permSet = computed(() => new Set(page.props.auth?.permissions || []));

    const can = (resource, action = 'view') => {
        if (isAdmin.value) return true;
        return permSet.value.has(`${resource}.${action}`);
    };

    const canAny = (resource, actions) => actions.some(a => can(resource, a));

    return { can, canAny, isAdmin };
}
