(() => {
  'use strict';

  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!token || typeof window.fetch !== 'function') return;

  const originalFetch = window.fetch.bind(window);
  window.fetch = (input, init = {}) => {
    const method = String(init.method || 'GET').toUpperCase();
    const target = typeof input === 'string' ? input : input?.url;

    if (!['POST', 'PUT', 'PATCH', 'DELETE'].includes(method) || !target) {
      return originalFetch(input, init);
    }

    const url = new URL(target, window.location.href);
    if (url.origin !== window.location.origin) {
      return originalFetch(input, init);
    }

    const headers = new Headers(init.headers || {});
    headers.set('X-CSRF-Token', token);
    headers.set('X-Requested-With', 'XMLHttpRequest');
    return originalFetch(input, { ...init, headers });
  };
})();
