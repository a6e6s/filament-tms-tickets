(function () {
    'use strict';

    const MAX_ELEMENTS = 5;
    const MAX_BYTES = 2 * 1024 * 1024;
    const QUALITY_STEPS = [0.8, 0.6, 0.4];
    const OVERLAY_ID = 'tms-tickets-pick-overlay';
    const MODAL_SELECTOR = '.fi-modal';

    const state = { auto: [], elements: [] };
    const fields = {};
    let picking = null;

    function isOwnNode(node) {
        return node.id === OVERLAY_ID || node.classList?.contains('tms-tickets-highlight') || node.matches?.(MODAL_SELECTOR);
    }

    async function capture(target, options = {}) {
        const canvas = await html2canvas(target, {
            useCORS: true,
            logging: false,
            scale: 1,
            backgroundColor: '#ffffff',
            ignoreElements: isOwnNode,
            ...options,
        });

        if (! canvas.width || ! canvas.height) {
            return null;
        }

        for (const quality of QUALITY_STEPS) {
            const uri = canvas.toDataURL('image/jpeg', quality);
            if (uri.length * 0.75 <= MAX_BYTES) {
                return uri;
            }
        }

        return null;
    }

    function captureViewport() {
        return capture(document.body, {
            x: window.scrollX,
            y: window.scrollY,
            width: window.innerWidth,
            height: window.innerHeight,
        });
    }

    function write(el, value) {
        el.value = JSON.stringify(value);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function syncAll() {
        const values = { auto: state.auto, elements: state.elements };

        for (const key of Object.keys(fields)) {
            if (fields[key].isConnected) {
                write(fields[key], values[key]);
            }
        }

        window.dispatchEvent(new CustomEvent('tms-tickets:captures', {
            detail: { count: state.elements.length },
        }));
    }

    function start() {
        state.elements = [];
        state.auto = [];
        syncAll();

        captureViewport()
            .then((uri) => {
                state.auto = uri ? [uri] : [];
                syncAll();
            })
            .catch(() => {
                state.auto = [];
                syncAll();
            });
    }

    function clear() {
        state.elements = [];
        syncAll();
    }

    function elementAt(x, y) {
        const overlay = document.getElementById(OVERLAY_ID);
        if (overlay) {
            overlay.style.pointerEvents = 'none';
        }

        const target = document.elementFromPoint(x, y);

        if (overlay) {
            overlay.style.pointerEvents = '';
        }

        return target && ! isOwnNode(target) ? target : null;
    }

    function teardown() {
        document.getElementById(OVERLAY_ID)?.remove();
        document.querySelectorAll('.tms-tickets-highlight').forEach((node) => node.remove());
        document.querySelectorAll(MODAL_SELECTOR).forEach((modal) => {
            modal.style.display = '';
        });
        document.removeEventListener('keydown', onPickKey, true);
        picking = null;
    }

    function onPickKey(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            teardown();
        }
    }

    function pick() {
        if (picking || state.elements.length >= MAX_ELEMENTS) {
            return;
        }

        document.querySelectorAll(MODAL_SELECTOR).forEach((modal) => {
            modal.style.display = 'none';
        });

        const overlay = document.createElement('div');
        overlay.id = OVERLAY_ID;
        overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483647;cursor:crosshair;';

        const highlight = document.createElement('div');
        highlight.className = 'tms-tickets-highlight';
        highlight.style.cssText = 'position:fixed;pointer-events:none;z-index:2147483646;border:2px solid #f59e0b;background:rgba(245,158,11,0.15);';

        document.body.append(overlay, highlight);
        picking = { overlay, highlight };

        overlay.addEventListener('mousemove', (event) => {
            const target = elementAt(event.clientX, event.clientY);
            if (! target) {
                highlight.style.display = 'none';
                return;
            }

            const rect = target.getBoundingClientRect();
            Object.assign(highlight.style, {
                display: 'block',
                left: `${rect.left}px`,
                top: `${rect.top}px`,
                width: `${rect.width}px`,
                height: `${rect.height}px`,
            });
        });

        overlay.addEventListener('click', async (event) => {
            event.preventDefault();
            event.stopPropagation();

            const target = elementAt(event.clientX, event.clientY);
            teardown();

            if (! target) {
                return;
            }

            try {
                const uri = await capture(target);
                if (uri) {
                    state.elements.push(uri);
                    syncAll();
                } else {
                    window.dispatchEvent(new CustomEvent('tms-tickets:captures', { detail: { error: true } }));
                }
            } catch (error) {
                window.dispatchEvent(new CustomEvent('tms-tickets:captures', { detail: { error: true } }));
            }
        }, true);

        document.addEventListener('keydown', onPickKey, true);
    }

    window.TmsTickets = {
        start,
        clear,
        pick,
        bindAuto(el) {
            fields.auto = el;
            write(el, state.auto);
        },
        bindElements(el) {
            fields.elements = el;
            write(el, state.elements);
        },
    };
})();
