/**
 * Makes a browser refresh reset a list page to its default, unfiltered view.
 *
 * Restoring the previous search, filters and page is handled on the server:
 * each list controller remembers them in the session, and the /assets/back and
 * /employees/back routes redirect to them. That covers the Back links and the
 * sidebar. This only needs to handle the one case the server cannot see —
 * whether this page load was a refresh or a normal navigation.
 *
 * Call once while the list page sets up.
 *
 * @returns {boolean} true when the page is reloading itself, so the caller can
 *                    skip work that is about to be thrown away.
 */
export function useListState() {
    let isReload = false;
    try {
        isReload = performance.getEntriesByType('navigation')[0]?.type === 'reload';
    } catch (e) {
        isReload = false; // older browser; treat it as a normal visit
    }

    if (isReload && window.location.search) {
        window.location.replace(window.location.pathname);

        return true;
    }

    return false;
}
