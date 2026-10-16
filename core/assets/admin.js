// PageBrick panel: repeatable lists, image picker, link fields and confirmations. No build step.
(() => {
    const root = document.documentElement;

    // Light, dark or the system's (what a new account starts with: it follows the device). The choice is kept on the server,
    // with the person's account, so it follows them to any browser; this browser also remembers it for the sign-in screen.
    const themeControl = document.querySelector('[data-theme-control]');
    themeControl?.addEventListener('click', (event) => {
        const choice = event.target.closest('[data-theme-choice]')?.dataset.themeChoice;
        if (!choice) return;
        if (choice === 'system') {
            root.removeAttribute('data-theme');
            root.removeAttribute('data-theme-saved');
        } else {
            root.dataset.theme = choice;
            root.setAttribute('data-theme-saved', '');
        }
        try { choice === 'system' ? localStorage.removeItem('pb-theme') : localStorage.setItem('pb-theme', choice); } catch (e) {}
        themeControl.querySelectorAll('[data-theme-choice]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.themeChoice === choice)));
        const token = document.querySelector('meta[name=pb-csrf]')?.content;
        if (token) {
            fetch(themeControl.dataset.url, { method: 'POST', body: new URLSearchParams({ _csrf: token, theme: choice }), keepalive: true });
        }
    });
    // What this browser remembers belongs to nobody in particular (whoever used it last), so a signed-in person never
    // inherits it: their own choice comes from their account.

    // On a narrow screen the list of screens opens from the button in the header; Esc closes it (and any open menu).
    const navToggle = document.querySelector('[data-nav-toggle]');
    const setNav = (open) => {
        root.classList.toggle('nav-open', open);
        navToggle?.setAttribute('aria-expanded', String(open));
        if (open) document.querySelector('.side a')?.focus();
    };
    navToggle?.addEventListener('click', () => setNav(!root.classList.contains('nav-open')));
    document.querySelector('[data-nav-close]')?.addEventListener('click', () => setNav(false));
    matchMedia('(min-width: 861px)').addEventListener?.('change', (event) => event.matches && setNav(false)); // older Safari has no addEventListener here
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const menu = document.querySelector('.menu[open]');
        if (menu) {
            menu.removeAttribute('open');
            menu.querySelector('summary')?.focus();
        } else if (root.classList.contains('nav-open')) {
            setNav(false);
            navToggle?.focus();
        }
    });

    // Password fields: an eye inside the field. Open eye: show the password; closed eye: hide it again.
    const eyes = {
        show: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/></svg>',
        hide: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.73 5.08a10.74 10.74 0 0 1 11.21 6.57 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-1.45 2.49"/><path d="M14.08 14.16a3 3 0 0 1-4.24-4.24"/><path d="M17.48 17.5a10.75 10.75 0 0 1-15.42-5.15 1 1 0 0 1 0-.7 10.75 10.75 0 0 1 4.45-5.14"/><path d="m2 2 20 20"/></svg>',
    };
    document.querySelectorAll('input[type=password]').forEach((input) => {
        const box = document.createElement('span');
        box.className = 'password-field';
        input.replaceWith(box);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'reveal';
        const show = (visible) => {
            input.type = visible ? 'text' : 'password';
            button.innerHTML = visible ? eyes.hide : eyes.show;
            button.title = visible ? document.body.dataset.hidePassword : document.body.dataset.showPassword;
            button.setAttribute('aria-label', button.title);
            button.setAttribute('aria-pressed', String(visible));
        };
        button.addEventListener('click', () => show(input.type === 'password'));
        box.append(input, button);
        show(false);
    });

    const dialog = document.getElementById('pb-media');
    let picking = null; // what is waiting for a choice: an image field, or {callback} from the text editor
    // The text editor asks for a picture of the library through this: callback({id, thumb, alt}).
    window.pbPickImage = (callback) => {
        if (!dialog) return;
        picking = {callback};
        dialog.showModal();
    };

    const setImage = (box, id, thumb) => {
        const field = box.querySelector('input[type=hidden]');
        field.value = id;
        field.dispatchEvent(new Event('input', {bubbles: true})); // the page editor refreshes its preview and saves its draft
        const preview = box.querySelector('[data-preview]');
        preview.replaceChildren();
        if (thumb) {
            const img = new Image();
            img.src = thumb;
            img.alt = '';
            preview.append(img);
        }
    };

    // A link field shows the address box only when "Outro endereço…" is chosen.
    const syncLink = (box) => {
        box.querySelector('input').hidden = box.querySelector('select').value !== '';
    };
    document.querySelectorAll('[data-link]').forEach(syncLink);
    document.addEventListener('change', (event) => {
        const box = event.target.closest('[data-link]');
        if (box) syncLink(box);
    });
    document.addEventListener('pb:added', (event) => event.target.querySelectorAll?.('[data-link]').forEach(syncLink)); // fields that came with a new section

    document.addEventListener('click', (event) => {
        // The menus of the header (notices, account) close when clicking anywhere else.
        document.querySelectorAll('.menu[open]').forEach((menu) => menu.contains(event.target) || menu.removeAttribute('open'));
        const button = event.target.closest('button');
        if (!button) return;
        const item = button.closest('[data-item]');

        if (button.matches('[data-add]')) {
            const list = button.closest('[data-list]');
            const index = 'n' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
            const html = list.querySelector(':scope > template').innerHTML.replaceAll('__i__', index);
            const items = list.querySelector(':scope > [data-items]');
            items.insertAdjacentHTML('beforeend', html);
            items.lastElementChild.querySelectorAll('[data-link]').forEach(syncLink);
            items.lastElementChild.dispatchEvent(new CustomEvent('pb:added', {bubbles: true})); // the text editor mounts on its fields
            items.dispatchEvent(new Event('input', {bubbles: true}));
            items.lastElementChild.querySelector('input, textarea, select')?.focus();
        } else if (button.matches('[data-remove]')) {
            const items = item.parentElement;
            item.remove();
            items.dispatchEvent(new Event('input', {bubbles: true}));
        } else if (button.matches('[data-up]') && item.previousElementSibling) {
            item.previousElementSibling.before(item);
            item.parentElement.dispatchEvent(new Event('input', {bubbles: true}));
        } else if (button.matches('[data-down]') && item.nextElementSibling) {
            item.nextElementSibling.after(item);
            item.parentElement.dispatchEvent(new Event('input', {bubbles: true}));
        } else if (button.matches('[data-pick]') && dialog) {
            picking = button.closest('[data-image]');
            dialog.showModal();
        } else if (button.matches('[data-clear]')) {
            setImage(button.closest('[data-image]'), '', '');
        } else if (button.matches('[data-media-id]') && picking) {
            if (picking.callback) picking.callback({id: button.dataset.mediaId, thumb: button.dataset.thumb, alt: button.dataset.alt || ''});
            else setImage(picking, button.dataset.mediaId, button.dataset.thumb);
            dialog.close();
        } else if (button.matches('[data-close]')) {
            dialog.close();
        }
    });

    // Uploading from inside the picker: the new image goes to the top of the grid.
    dialog?.querySelector('[data-upload]').addEventListener('change', async (event) => {
        const input = event.target;
        const status = dialog.querySelector('[data-status]');
        const grid = dialog.querySelector('[data-grid]');
        for (const file of input.files) {
            status.textContent = input.dataset.sending + ' ' + file.name;
            const body = new FormData();
            body.append('_csrf', input.dataset.csrf);
            body.append('file', file);
            const response = await fetch(input.dataset.url, { method: 'POST', body, headers: { Accept: 'application/json' } });
            const result = await response.json().catch(() => ({ error: response.statusText }));
            if (!response.ok) {
                status.textContent = result.error;
                input.value = '';
                return;
            }
            const choice = document.createElement('button');
            choice.type = 'button';
            choice.dataset.mediaId = result.id;
            choice.dataset.thumb = result.thumb;
            const img = new Image();
            img.src = result.thumb;
            img.alt = file.name;
            choice.append(img);
            grid.prepend(choice);
        }
        status.textContent = '';
        input.value = '';
    });

    document.addEventListener('submit', (event) => {
        const message = event.submitter?.dataset.confirm ?? event.target.dataset.confirm;
        if (message && !confirm(message)) event.preventDefault();
    });

    // Updates and installs show each step as it really happens, like the demo on pagebrick.org: one request per step,
    // each line ticked when the server says it's done. Without JavaScript the forms post as before.
    const stepLog = (form, texts) => {
        document.querySelectorAll('.update-log, a.button[data-next]').forEach((old) => old.remove()); // a new run starts clean
        const log = document.createElement('ol');
        log.className = 'update-log';
        log.setAttribute('aria-live', 'polite');
        const row = form.closest('.action-row'); // on the Updates screen the buttons share a row: the log goes above it
        form.hidden = true;
        row ? row.before(log) : form.after(log);
        let current = [];
        const line = (label, state = 'running') => {
            const item = document.createElement('li');
            item.className = state;
            item.textContent = label;
            log.append(item);
            return item;
        };
        return {
            line,
            // Shows the step's lines, asks the server, ticks them. A refusal comes back as the server's own message.
            async step(labels, request) {
                current = labels.map((label) => line(label));
                const response = await request();
                // A server or a firewall may answer with a page instead of JSON: then say which answer it was.
                const answer = await response.json().catch(() => {
                    throw Object.assign(new Error(`${texts.failed} (HTTP ${response.status})`), {fromServer: true});
                });
                if (answer.ok === false) throw Object.assign(new Error(answer.message), {fromServer: true});
                current.forEach((item) => { item.className = 'done'; });
                return answer;
            },
            failed(error) {
                current.forEach((item) => { item.className = 'failed'; });
                line(error.fromServer ? error.message : texts.failed, 'result failed');
            },
            // The way out of the log is a main button, and it takes the focus: the form that held it is gone.
            done() {
                const next = document.createElement('a');
                const arrow = document.createElement('span');
                next.className = 'button';
                next.dataset.next = '';
                next.href = location.pathname;
                arrow.setAttribute('aria-hidden', 'true');
                arrow.textContent = '→';
                next.append(texts.continue, arrow);
                row ? row.prepend(next) : log.after(next);
                next.focus();
            },
            last: () => current,
        };
    };
    const fill = (text, ...values) => values.reduce((out, value, i) => out.replace(`%${i + 1}$s`, value), text);
    const post = (url, form, fields, withFile = false) => {
        const body = new FormData(form);
        if (!withFile) body.delete('package'); // a .zip travels once, in its own step; the next steps are small
        Object.entries(fields).forEach(([key, value]) => body.set(key, value));
        return () => fetch(url, {method: 'POST', body, headers: {Accept: 'application/json'}});
    };

    // PageBrick itself. The last step is the new version's first request, which opens every page.
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!form.matches('[data-update-core]') || event.defaultPrevented) return;
        event.preventDefault();
        const text = form.dataset;
        const log = stepLog(form, text);
        try {
            await log.step([text.download], post(text.stepUrl, form, {step: 'download'}));
            await log.step([text.verify], post(text.stepUrl, form, {step: 'verify'}));
            await log.step([text.backup, text.swap], post(text.stepUrl, form, {step: 'apply'}));
            const {result} = await log.step([text.check], () => fetch(text.resultUrl, {headers: {Accept: 'application/json'}}));
            const ok = Boolean(result && result.ok);
            log.last()[0].className = ok ? 'done' : 'failed';
            log.line(ok ? text.ok : text.undone.replace('%s', (result && result.problem) || '?'), ok ? 'result ok' : 'result rolled-back');
            // "Voltar" now means the version this page was opened with, not the one it still says
            const back = form.closest('.action-row')?.querySelector('[value=restore-core] [data-label]');
            if (ok && back && text.back) back.textContent = text.back.replace('%s', text.from);
        } catch (error) {
            log.failed(error);
        }
        log.done();
    });

    // A theme or plugin: from a .zip (receive) or from the catalog (download, verify), then install, then — when it's
    // the one in use — every page opened with it; if one breaks, the previous version is back before anyone sees it.
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!form.matches('[data-package-steps]') || event.defaultPrevented) return;
        event.preventDefault();
        const text = JSON.parse(document.getElementById('pb-step-texts').textContent);
        const {type, slug = '', version = ''} = form.dataset;
        const ask = (step) => post(text.url, form, {type, slug, step}, step === 'receive');
        const log = stepLog(form, text);
        try {
            let info;
            if (slug) {
                await log.step([text.downloading.replace('%s', version)], ask('download'));
                info = await log.step([text.signature], ask('verify'));
            } else {
                info = await log.step([text.sending, text.package], ask('receive'));
            }
            await log.step([...(info.replaces ? [text.backup] : []), fill(text.installing, info.name, info.version)], ask('install'));
            if (info.in_use) {
                const {problem} = await log.step([text.check], ask('check'));
                if (problem) {
                    log.last()[0].className = 'failed';
                    log.line(text.undone.replace('%s', problem), 'result rolled-back');
                } else {
                    log.line(fill(text.ok, info.name, info.version), 'result ok');
                }
            } else {
                log.line(fill(info.replaces ? text.ok : text.installed, info.name, info.version), 'result ok');
            }
        } catch (error) {
            log.failed(error);
            form.hidden = false; // the file is still chosen: sending again needs no new choice
        }
        log.done();
    });

    // Settings, Address and languages: before forcing https:// over plain http, the browser itself tries the https:// address of the site.
    // A certificate that is not there would lock everybody out, so only a page that opened (any answer: it is a check of the connection) goes on.
    document.querySelectorAll('form[data-https-check]').forEach((form) => form.addEventListener('submit', async (event) => {
        if (form.dataset.checked) return;
        event.preventDefault();
        const note = form.querySelector('[data-https-note]');
        note.hidden = false;
        note.classList.remove('error');
        note.textContent = form.dataset.checking;
        try {
            await fetch(form.dataset.httpsCheck, {mode: 'no-cors', cache: 'no-store'});
            form.elements.https_ok.value = '1';
            form.dataset.checked = '1';
            form.requestSubmit(event.submitter);
        } catch (error) {
            note.classList.add('error');
            note.textContent = form.dataset.failed;
        }
    }));

    // The person's photo: after choosing a file it is shown in a circle, to be dragged and zoomed into place; what is in the circle is what is sent
    // (a square picture, drawn on a canvas). Without this (old browser, script off) the file goes as it is and the server cuts the centre.
    document.querySelectorAll('form[data-crop]').forEach((form) => {
        const input = form.querySelector('input[type=file]');
        const box = form.querySelector('[data-crop-box]');
        const view = box.querySelector('[data-crop-view]');
        const img = view.querySelector('img');
        const zoom = box.querySelector('input[type=range]');
        const SENT = 320; // pixels of the picture that is sent
        let base = 1, scale = 1, x = 0, y = 0, ready = false; // x, y: the picture's top-left corner inside the circle, in pixels of the circle
        const side = () => view.clientWidth;
        const draw = () => {
            x = Math.min(0, Math.max(side() - img.naturalWidth * scale, x)); // the picture always covers the circle
            y = Math.min(0, Math.max(side() - img.naturalHeight * scale, y));
            img.style.transform = `translate(${x}px, ${y}px) scale(${scale})`;
        };
        const zoomTo = (next, cx = side() / 2, cy = side() / 2) => { // around a point of the circle
            next = Math.max(base, Math.min(base * 4, next));
            x = cx - (cx - x) * next / scale;
            y = cy - (cy - y) * next / scale;
            scale = next;
            zoom.value = String(scale / base);
            draw();
        };
        input.addEventListener('change', () => {
            const file = input.files[0];
            ready = false;
            box.hidden = true;
            if (!file || !file.type.startsWith('image/')) return;
            const url = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(url);
                box.hidden = false; // before measuring: a closed box has no size
                base = scale = side() / Math.min(img.naturalWidth, img.naturalHeight); // the shorter side fills the circle
                x = (side() - img.naturalWidth * scale) / 2;
                y = (side() - img.naturalHeight * scale) / 2;
                zoom.value = '1';
                ready = true;
                draw();
            };
            img.src = url; // a picture the browser cannot show leaves the box closed: the server does the centred cut
        });
        zoom.addEventListener('input', () => zoomTo(base * Number(zoom.value)));
        let drag = null;
        view.addEventListener('pointerdown', (event) => { drag = {id: event.pointerId, px: event.clientX, py: event.clientY, x, y}; view.setPointerCapture(event.pointerId); });
        view.addEventListener('pointermove', (event) => {
            if (!drag || event.pointerId !== drag.id) return;
            x = drag.x + event.clientX - drag.px;
            y = drag.y + event.clientY - drag.py;
            draw();
        });
        ['pointerup', 'pointercancel'].forEach((name) => view.addEventListener(name, () => { drag = null; }));
        view.addEventListener('wheel', (event) => {
            event.preventDefault();
            const at = view.getBoundingClientRect();
            zoomTo(scale * (event.deltaY < 0 ? 1.1 : 1 / 1.1), event.clientX - at.left, event.clientY - at.top);
        }, {passive: false});
        view.addEventListener('keydown', (event) => {
            const move = {ArrowLeft: [20, 0], ArrowRight: [-20, 0], ArrowUp: [0, 20], ArrowDown: [0, -20]}[event.key];
            if (move) { event.preventDefault(); x += move[0]; y += move[1]; draw(); }
            else if (event.key === '+' || event.key === '=') { event.preventDefault(); zoomTo(scale * 1.1); }
            else if (event.key === '-') { event.preventDefault(); zoomTo(scale / 1.1); }
        });
        form.addEventListener('submit', (event) => {
            if (!ready || form.dataset.cropped) return;
            event.preventDefault();
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = SENT;
            canvas.getContext('2d').drawImage(img, -x / scale, -y / scale, side() / scale, side() / scale, 0, 0, SENT, SENT);
            canvas.toBlob((blob) => {
                try {
                    const files = new DataTransfer();
                    files.items.add(new File([blob], 'foto.' + (blob.type === 'image/png' ? 'png' : blob.type === 'image/webp' ? 'webp' : 'jpg'), {type: blob.type}));
                    input.files = files.files;
                } catch (error) { /* an old browser: the file goes as it is */ }
                form.dataset.cropped = '1';
                HTMLFormElement.prototype.submit.call(form);
            }, 'image/webp', 0.92);
        });
    });

    // A button that copies the text of a field (the link of an invitation): data-copy = the field's id, data-done = what the button says after.
    document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
        const field = document.getElementById(button.dataset.copy);
        field.select();
        try {
            await navigator.clipboard.writeText(field.value);
        } catch (error) {
            document.execCommand('copy'); // the page is not on https, or the browser asks for no clipboard: the text stays selected for Ctrl+C
        }
        button.textContent = button.dataset.done;
    }));

    // The rich text editor doesn't take attachments: images go in image fields.
    document.addEventListener('trix-file-accept', (event) => event.preventDefault());
})();
