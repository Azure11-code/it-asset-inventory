import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Attach CSRF token from the meta tag rendered by app.blade.php.
// Needed for POST/PATCH/DELETE requests that don't go through Inertia's router.
const csrfToken = document.head.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}
