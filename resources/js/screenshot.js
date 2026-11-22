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

    function isCrossOriginImage(node) {
        return node.tagName === 'IMG' && new URL(node.src, window.location.href).origin !== window.location.origin;
    }

    // The SVG serializer rejects attribute names that are not valid XML (wire:*, x-on:*, :class, @click).
    // Stripping them from the live DOM would make Livewire reinitialise components, so this only runs on a copy.
    const VALID_XML_ATTR = /^((xlink|xml|xmlns):)?[A-Za-z_][\w.-]*$/;

    // Alpine removes x-cloak only from live nodes; the copy would stay hidden by [x-cloak]{display:none}.
    function stripInvalidAttributes(root) {
        for (const el of [root, ...root.querySelectorAll('*')]) {
            el.removeAttribute('x-cloak');

            for (const attr of [...el.attributes]) {
                if (! VALID_XML_ATTR.test(attr.name)) {
                    el.removeAttribute(attr.name);
                }
            }
        }
    }

    async function render(target) {
        const rect = target.getBoundingClientRect();
        const box = document.createElement('div');
        // Chrome paints nothing for off-screen content, so the box stays on-screen behind the page.
        box.style.cssText = `position:fixed;left:0;top:0;z-index:-9999;pointer-events:none;width:${rect.width}px;height:${rect.height}px;overflow:hidden;background:#ffffff;`;

        const copy = target.cloneNode(true);
        copy.style.margin = '0';
        stripInvalidAttributes(copy);

        box.append(copy);
        document.body.append(box);

        try {
            return await window.htmlToImage.toCanvas(box, {
                backgroundColor: '#ffffff',
                pixelRatio: 1,
                skipFonts: true,
                filter: (node) => ! isOwnNode(node) && ! isCrossOriginImage(node),
            });
        } finally {
            box.remove();
        }
    }

    function toJpeg(canvas) {
        if (! canvas || ! canvas.width || ! canvas.height) {
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

    async function captureElement(target) {
        return toJpeg(await render(target));
    }

    async function captureViewport() {
        const body = document.body;
        const rect = body.getBoundingClientRect();

        if (! rect.width || ! rect.height) {
            return null;
        }

        const full = await render(body);
        const scale = full.width / rect.width;
        const sx = Math.max(0, -rect.left * scale);
        const sy = Math.max(0, -rect.top * scale);
        const sw = Math.min(full.width - sx, window.innerWidth * scale);
        const sh = Math.min(full.height - sy, window.innerHeight * scale);

        if (sw <= 0 || sh <= 0) {
            return null;
        }

        const viewport = document.createElement('canvas');
        viewport.width = Math.round(sw);
        viewport.height = Math.round(sh);
        viewport.getContext('2d').drawImage(full, sx, sy, sw, sh, 0, 0, viewport.width, viewport.height);

        return toJpeg(viewport);
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
                const uri = await captureElement(target);
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
