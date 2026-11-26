const routes = [];

export function defineRoute(route) {
    routes.push(route);
    return route;
}

export function currentLocation() {
    const raw = (window.location.hash || '').replace(/^#/, '') || '/dashboard';
    const [pathPart, queryPart = ''] = raw.split('?');
    const path = pathPart || '/dashboard';
    const query = {};
    new URLSearchParams(queryPart).forEach((value, key) => {
        query[key] = value;
    });
    return { path, query, raw };
}

function matchPath(pattern, path) {
    const patternParts = pattern.split('/').filter(Boolean);
    const pathParts = path.split('/').filter(Boolean);
    if (patternParts.length !== pathParts.length) return null;
    const params = {};
    for (let index = 0; index < patternParts.length; index += 1) {
        const expected = patternParts[index];
        const actual = pathParts[index];
        if (expected.startsWith(':')) {
            params[expected.slice(1)] = decodeURIComponent(actual);
        } else if (expected !== actual) {
            return null;
        }
    }
    return params;
}

export function resolve(path) {
    for (const route of routes) {
        const params = matchPath(route.path, path);
        if (params) return { route, params };
    }
    return null;
}

export function navigate(path, options = {}) {
    const target = `#${path.replace(/^#/, '')}`;
    if (options.replace) {
        window.location.replace(target);
    } else if (window.location.hash === target) {
        window.dispatchEvent(new HashChangeEvent('hashchange'));
    } else {
        window.location.hash = target.slice(1);
    }
}

export function startRouter(handler) {
    const run = () => handler(currentLocation());
    window.addEventListener('hashchange', run);
    run();
}
