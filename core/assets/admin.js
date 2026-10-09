// PageBrick panel: repeatable lists, image picker, link fields and confirmations. No build step.
(() => {
    // Light or dark: follows the system until the switch is used. The choice is kept on the server, with the person's
    // account, so it follows them to any browser; this browser also remembers it for the sign-in screen.
    const themeSwitch = document.querySelector('[data-theme-switch]');
    const keepTheme = (theme) => {
        const token = document.querySelector('meta[name=pb-csrf]')?.content;
        if (themeSwitch && token) {
            fetch(themeSwitch.dataset.url, { method: 'POST', body: new URLSearchParams({ _csrf: token, theme }), keepalive: true });
        }
    };
    themeSwitch?.addEventListener('click', () => {
        const root = document.documentElement;
        const dark = root.dataset.theme ? root.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
        root.dataset.theme = dark ? 'light' : 'dark';
        root.setAttribute('data-theme-saved', '');
        try { localStorage.setItem('pb-theme', root.dataset.theme); } catch (e) {}
        keepTheme(root.dataset.theme);
    });
    // What this browser remembers belongs to nobody in particular (whoever used it last), so a signed-in person never
    // inherits it: their own choice comes from their account.

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
    let picking = null; // the image field waiting for a choice

    const setImage = (box, id, thumb) => {
        box.querySelector('input[type=hidden]').value = id;
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

    document.addEventListener('click', (event) => {
        // The "Sistema" menu closes when clicking anywhere else.
        document.querySelectorAll('.nav-more[open]').forEach((menu) => menu.contains(event.target) || menu.removeAttribute('open'));
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
            items.lastElementChild.querySelector('input, textarea, select')?.focus();
        } else if (button.matches('[data-remove]')) {
            item.remove();
        } else if (button.matches('[data-up]') && item.previousElementSibling) {
            item.previousElementSibling.before(item);
        } else if (button.matches('[data-down]') && item.nextElementSibling) {
            item.nextElementSibling.after(item);
        } else if (button.matches('[data-pick]') && dialog) {
            picking = button.closest('[data-image]');
            dialog.showModal();
        } else if (button.matches('[data-clear]')) {
            setImage(button.closest('[data-image]'), '', '');
        } else if (button.matches('[data-media-id]') && picking) {
            setImage(picking, button.dataset.mediaId, button.dataset.thumb);
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
        const message = event.target.dataset.confirm;
        if (message && !confirm(message)) event.preventDefault();
    });

    // Updates and installs show each step as it really happens, like the demo on pagebrick.org: one request per step,
    // each line ticked when the server says it's done. Without JavaScript the forms post as before.
    const stepLog = (form, texts) => {
        const log = document.createElement('ol');
        log.className = 'update-log';
        log.setAttribute('aria-live', 'polite');
        form.hidden = true;
        form.after(log);
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
                const answer = await (await request()).json();
                if (answer.ok === false) throw Object.assign(new Error(answer.message), {fromServer: true});
                current.forEach((item) => { item.className = 'done'; });
                return answer;
            },
            failed(error) {
                current.forEach((item) => { item.className = 'failed'; });
                line(error.fromServer ? error.message : texts.failed, 'result failed');
            },
            done() {
                const next = document.createElement('a');
                next.href = location.pathname;
                next.textContent = texts.continue;
                log.after(next);
            },
            last: () => current,
        };
    };
    const fill = (text, ...values) => values.reduce((out, value, i) => out.replace(`%${i + 1}$s`, value), text);
    const post = (url, form, fields) => {
        const body = new FormData(form);
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
        const ask = (step) => post(text.url, form, {type, slug, step});
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
        }
        log.done();
    });

    // The rich text editor doesn't take attachments: images go in image fields.
    document.addEventListener('trix-file-accept', (event) => event.preventDefault());
})();
