/**
 * Cm_Diehard FPC dynamic-block client — native DOM/fetch implementation.
 *
 * Drop-in replacement for the legacy Prototype.js client (fpc-prototype.js).
 * Exposes the same window.Diehard API surface (constructor +
 * setParams/setBlocks/setDefaultIgnoredBlocks + static replaceBlocks) so the
 * beforebodyend template and the LoadController jsonp/esi responses work
 * unchanged on a frontend that no longer ships Prototype.
 */
(function (window, document) {
    'use strict';

    const IGNORED_COOKIE = 'diehard_ignored';

    function readCookie(name) {
        const prefix = `${name}=`;
        const row = document.cookie.split('; ').find((c) => c.startsWith(prefix));
        return row ? decodeURIComponent(row.slice(prefix.length)) : null;
    }

    class Diehard {
        constructor(url, action) {
            this.url = url;
            this.action = action;
            this.params = {};
            this.blocks = {};
            this.defaultIgnored = [];

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.loadDynamicContent());
            } else {
                this.loadDynamicContent();
            }
        }

        setParams(params) {
            this.params = params || {};
        }

        setBlocks(blocks) {
            this.blocks = blocks || {};
        }

        setDefaultIgnoredBlocks(blocks) {
            this.defaultIgnored = blocks || [];
        }

        loadDynamicContent() {
            const cookie = readCookie(IGNORED_COOKIE);
            let ignored;
            if (cookie === null) {        // no cookie => only cached pages hit so far, ignore all defaults
                ignored = this.defaultIgnored;
            } else if (cookie === '-') {  // sentinel for "no blocks ignored"
                ignored = [];
            } else {
                ignored = cookie.split(',');
            }

            const blocks = {};
            Object.keys(this.blocks).forEach((selector) => {
                if (!ignored.includes(this.blocks[selector])) {
                    blocks[selector] = this.blocks[selector];
                }
            });
            this.blocks = blocks;

            if (Object.keys(this.blocks).length === 0) {
                return;
            }

            const payload = {
                full_action_name: this.action,
                blocks: this.blocks,
                params: this.params,
            };
            const separator = this.url.includes('?') ? '&' : '?';
            const requestUrl = `${this.url}${separator}json=${encodeURIComponent(JSON.stringify(payload))}`;

            fetch(requestUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((response) => response.json())
                .then((data) => Diehard.replaceBlocks(data))
                .catch(() => { /* leave placeholders in place if the hole-punch request fails */ });
        }

        static replaceBlocks(data) {
            if (!data || !data.blocks) {
                return;
            }
            Object.keys(data.blocks).forEach((selector) => {
                const target = document.querySelector(selector);
                if (!target) {
                    return;
                }
                const fragment = document.createRange().createContextualFragment(data.blocks[selector]);
                target.replaceWith(fragment);
            });
            document.dispatchEvent(new CustomEvent('diehard:load', { detail: { data } }));
        }
    }

    window.Diehard = Diehard;
}(window, document));
