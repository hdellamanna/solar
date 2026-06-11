/**
 * Service worker registration + update toast.
 *
 * - Skipped entirely in dev (Vite HMR + SW = pain; trust the cache miss).
 * - Registers `/sw.js` on window 'load'.
 * - On `updatefound` while a previous SW is active, shows a non-blocking
 *   toast: "Nova versão disponível — recarregue". Clicking "Atualizar"
 *   posts { type: 'SKIP_WAITING' } to the waiting worker, which then
 *   activates and triggers a one-shot reload.
 *
 * Loaded via a dynamic import in app.js only when `import.meta.env.PROD`.
 */
const TOAST_ID = 'solar-sw-update-toast';
const TOAST_LIFETIME_MS = 0; // 0 = sticky until user dismisses or updates

function ensureToast() {
    let el = document.getElementById(TOAST_ID);
    if (el) return el;

    el = document.createElement('div');
    el.id = TOAST_ID;
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.style.cssText = [
        'position:fixed',
        'left:50%',
        'bottom:24px',
        'transform:translateX(-50%)',
        'z-index:9999',
        'display:flex',
        'align-items:center',
        'gap:12px',
        'padding:12px 16px',
        'border-radius:14px',
        'background:#0F172A',
        'color:#F8FAFC',
        'font:500 14px/1.2 Inter, system-ui, -apple-system, sans-serif',
        'box-shadow:0 12px 32px rgba(15,23,42,0.25), 0 2px 6px rgba(15,23,42,0.10)',
        'backdrop-filter:saturate(150%) blur(8px)',
        '-webkit-backdrop-filter:saturate(150%) blur(8px)',
        'max-width:min(94vw, 520px)',
        'opacity:0',
        'transition:opacity 220ms ease-out, transform 220ms ease-out',
        'pointer-events:none',
    ].join(';');

    el.innerHTML = `
        <span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:9999px;background:linear-gradient(135deg,#FF8A3D,#FFC93C);color:#0F172A;font-weight:700;">S</span>
        <span style="flex:1;min-width:0;">Nova versão disponível — recarregue para atualizar.</span>
        <button type="button" data-sw-action="dismiss" aria-label="Dispensar" style="background:transparent;border:0;color:#94A3B8;cursor:pointer;padding:4px 6px;border-radius:8px;font-size:18px;line-height:1;">&times;</button>
        <button type="button" data-sw-action="update" style="background:linear-gradient(135deg,#FF8A3D,#FFC93C);color:#0F172A;border:0;border-radius:10px;padding:8px 14px;font:600 13px/1 Inter, system-ui, sans-serif;cursor:pointer;">Atualizar</button>
    `;

    document.body.appendChild(el);

    requestAnimationFrame(() => {
        el.style.opacity = '1';
        el.style.transform = 'translateX(-50%) translateY(0)';
        el.style.pointerEvents = 'auto';
    });

    return el;
}

function dismissToast() {
    const el = document.getElementById(TOAST_ID);
    if (!el) return;
    el.style.opacity = '0';
    el.style.transform = 'translateX(-50%) translateY(8px)';
    el.style.pointerEvents = 'none';
    setTimeout(() => el.remove(), 260);
}

function showUpdateToast(worker) {
    const toast = ensureToast();

    toast.querySelector('[data-sw-action="dismiss"]').addEventListener('click', () => {
        dismissToast();
    });

    toast.querySelector('[data-sw-action="update"]').addEventListener('click', () => {
        worker.postMessage({ type: 'SKIP_WAITING' });
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            window.location.reload();
        }, { once: true });
    });

    if (TOAST_LIFETIME_MS > 0) {
        setTimeout(dismissToast, TOAST_LIFETIME_MS);
    }
}

function register() {
    if (!('serviceWorker' in navigator)) return;

    // Vite injects import.meta.env.PROD at build time.
    // Dev: import.meta.env.PROD === false → we skip registration entirely.
    if (!import.meta.env || !import.meta.env.PROD) return;

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/', updateViaCache: 'none' })
            .then((registration) => {
                if (registration.waiting) {
                    showUpdateToast(registration.waiting);
                }

                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    if (!newWorker) return;
                    newWorker.addEventListener('statechange', () => {
                        if (
                            newWorker.state === 'installed' &&
                            navigator.serviceWorker.controller
                        ) {
                            showUpdateToast(newWorker);
                        }
                    });
                });
            })
            .catch((err) => {
                // Soft-fail: SW is a progressive enhancement. Log and move on.
                // eslint-disable-next-line no-console
                console.warn('[solar-sw] registration failed:', err);
            });
    });
}

register();
