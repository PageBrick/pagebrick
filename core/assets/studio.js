// The split screens of the panel (the page editor and the menus): the form on the left, the site as visitors will see it on the right, live,
// and a draft that saves itself. Lists can be reordered by dragging the handle (or with the arrow keys on it). No build step.
//
// How the page beside the form stays close to real time:
//  - moving something in a list changes the order inside the page at once, with no round trip (the sections are marked in it);
//  - typing asks the server for the page, but only the sections that changed are replaced inside the page that is already there;
//  - only when the page around the sections changed, or the theme does not mark them, the new page loads in a second frame and takes the place of the first.
(() => {
    const form = document.querySelector('[data-studio]');
    if (!form) return;
    const texts = JSON.parse(form.dataset.texts);
    const fill = (text, ...values) => values.reduce((out, value, i) => out.replace(`%${i + 1}$s`, value), text).replace('%s', values[0]);
    const body = form.querySelector('.studio-body');
    const note = form.querySelector('[data-save-note]');
    const pill = form.querySelector('[data-state-pill]');
    const published = form.dataset.state !== 'draft';
    const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const live = document.createElement('div'); // what a screen reader is told when something moves
    live.className = 'sr-only';
    live.setAttribute('aria-live', 'polite');
    form.append(live);

    // ------------------------------------------------------------------ the page beside the form
    const frames = [...form.querySelectorAll('[data-frame]')];
    let active = frames[0];
    active.dataset.active = '';
    const previewStatus = form.querySelector('[data-preview-status]');
    let refreshing = false;
    let again = false;
    let controller = null;
    let lastHtml = '';
    let swaps = 0;
    let pendingShow = ''; // a section just added: once the page has it, the page scrolls to it
    const toFrame = (message) => active.contentWindow?.postMessage({pb: true, ...message}, '*');
    const waiting = new Map(); // answers the page owes us: type => resolve
    const ask = (message, expect, wait = 600) => new Promise((resolve) => {
        const timer = setTimeout(() => { waiting.delete(expect); resolve(null); }, wait);
        waiting.set(expect, (answer) => { clearTimeout(timer); waiting.delete(expect); resolve(answer); });
        toFrame(message);
    });

    // The sections the server marked in a page, and the page around them (the page without its sections, to tell whether only sections changed).
    const SECTIONS = /<!--pb-section:([a-z0-9_-]+)-->([\s\S]*?)<!--\/pb-section:\1-->/g;
    const analyse = (html) => {
        const sections = {};
        const order = [];
        const skeleton = html.replace(/<script>window\.PB_FRAME[\s\S]*?<\/script>/, '').replace(SECTIONS, (all, name, inner) => { sections[name] = inner; order.push(name); return '<!--S-->'; });
        return {skeleton, sections, order};
    };

    // A new page loads in the frame that is not showing and takes its place when ready (or soon after: it does not wait for every picture).
    const show = (html) => new Promise((resolve) => {
        const next = frames.find((frame) => frame !== active);
        const at = active.contentWindow ? [active.contentWindow.scrollX, active.contentWindow.scrollY] : [0, 0];
        let done = false;
        const swap = () => {
            if (done) return;
            done = true;
            try { next.contentWindow.scrollTo(...at); } catch (error) {}
            delete active.dataset.active;
            next.dataset.active = '';
            active = next;
            resolve();
        };
        next.addEventListener('load', swap, {once: true});
        setTimeout(swap, 700); // a slow picture or font is not a reason to keep the old page on screen
        next.srcdoc = html + `<!--${++swaps}-->`; // never the same text twice: some browsers do not reload for that
    });

    const apply = async (html) => {
        const before = analyse(lastHtml);
        const after = analyse(html);
        const sameFrame = lastHtml !== '' && before.skeleton === after.skeleton && after.order.length > 0
            && [...before.order].sort().join() === [...after.order].sort().join();
        lastHtml = html;
        if (sameFrame) {
            const patches = {};
            // lastHtml may be older than what the page shows (a reorder happens in the page itself), so every section is compared by content
            for (const name of after.order) if (before.sections[name] !== after.sections[name]) patches[name] = after.sections[name];
            const answer = await ask({type: 'patch', patches, order: after.order}, 'patched');
            if (answer?.ok) return;
        }
        await show(html);
    };

    const refresh = async () => {
        if (refreshing) { again = true; controller?.abort(); return; } // the newest state is the one worth showing
        refreshing = true;
        again = false;
        const slow = setTimeout(() => { previewStatus.textContent = texts.loading; }, 350);
        controller = new AbortController();
        try {
            const data = new FormData(form);
            data.delete('action');
            data.set('organizer', '1');
            const response = await fetch(form.dataset.previewUrl, {method: 'POST', body: window.pbSeal?.(data) ?? data, signal: controller.signal});
            if (!response.ok) throw Object.assign(new Error('refused'), {status: response.status}); // the answer is an error page, not the site: keep the page that is showing
            await apply(await response.text());
            previewStatus.textContent = '';
            if (pendingShow && analyse(lastHtml).order.includes(pendingShow)) { toFrame({type: 'show', name: pendingShow}); pendingShow = ''; }
        } catch (error) {
            if (error.name !== 'AbortError') previewStatus.textContent = error.status ? fill(texts.refused, error.status) : texts.offline;
        }
        clearTimeout(slow);
        refreshing = false;
        if (again) { again = false; refresh(); }
    };

    // ------------------------------------------------------------------ the draft saves itself
    let dirty = false;
    let saving = false;
    let saveAgain = false;
    const say = (text, problem = false) => { note.textContent = text; note.classList.toggle('problem', problem); };
    const setState = (hasDraft) => {
        const state = !published ? 'draft' : hasDraft ? 'changes' : 'live';
        form.dataset.state = state;
        pill.textContent = state === 'changes' ? texts.changes : texts.live || pill.textContent;
        pill.classList.toggle('warn', state === 'changes');
        pill.classList.toggle('ok', state === 'live');
        form.querySelector('[data-discard]')?.toggleAttribute('hidden', !hasDraft);
    };
    const autosave = async (explicit = false) => {
        if (saving) { saveAgain = true; return; }
        saving = true;
        say(texts.saving);
        try {
            const data = new FormData(form);
            data.delete('action');
            if (explicit === true) data.set('explicit', '1'); // the button, not the timer: a page never published keeps a version in its history
            const response = await fetch(form.dataset.autosaveUrl, {method: 'POST', body: window.pbSeal?.(data) ?? data, headers: {Accept: 'application/json'}});
            if (!response.ok) throw Object.assign(new Error('refused'), {status: response.status});
            const answer = await response.json();
            if (answer.ok) {
                dirty = false;
                say(fill(answer.where === 'draft' ? texts.saved : texts.savedPage, answer.at));
                if (published) setState(answer.hasDraft);
            } else {
                say(fill(answer.error ? texts.failed : texts.offline, answer.error || ''), true);
            }
        } catch (error) {
            say(error.status ? fill(texts.refused, error.status) : texts.offline, true);
        }
        saving = false;
        if (saveAgain) { saveAgain = false; autosave(); }
    };

    let previewTimer;
    let saveTimer;
    // {preview: false}: something that already shows in the page (a section moved there) and needs no new page from the server
    const changed = ({preview = true} = {}) => {
        dirty = true;
        if (preview) {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(refresh, 140);
        }
        clearTimeout(saveTimer);
        saveTimer = setTimeout(autosave, 1800);
    };
    const onChange = (event) => { if (!event.target.closest?.('[data-no-save]')) changed(); };
    // which page of the site is looked at (menus screen) is not content: the page beside the form just changes
    form.querySelector('.studio-preview-page select')?.addEventListener('change', () => { lastHtml = ''; refresh(); });
    form.addEventListener('input', onChange);
    form.addEventListener('change', onChange);
    // the title is also the heading of the screen
    form.querySelector('#title')?.addEventListener('input', (event) => {
        const title = form.querySelector('.studio-title');
        title.textContent = event.target.value || '…';
        title.title = event.target.value;
    });
    form.addEventListener('submit', (event) => {
        // "Save draft" does not need to leave the page: it saves like the timer does and says so
        if (event.submitter?.value === 'draft') {
            event.preventDefault();
            clearTimeout(saveTimer);
            autosave(true);
            return;
        }
        if (event.submitter?.formTarget !== '_blank') dirty = false; // sent to the server: nothing is left behind
    });
    addEventListener('beforeunload', (event) => { if (dirty) event.preventDefault(); });

    // ------------------------------------------------------------------ lists that can be reordered: drag the handle, or use the arrow keys on it
    const items = (list) => [...list.querySelectorAll(':scope > [data-sort]')];
    const nameOf = (item) => item.querySelector('[data-sort-name]')?.textContent.trim() || item.querySelector('.pb-section-name')?.textContent.trim()
        || item.querySelector('input')?.value.trim() || item.dataset.sort;
    // Called once an item has a new place (the DOM is already in the new order).
    const moved = (list, item) => {
        const all = items(list);
        live.textContent = fill(texts.moved, nameOf(item), all.indexOf(item) + 1, all.length);
        if (list.matches('[data-sections]')) {
            // the page shows it at once, with no round trip; if it cannot (a theme that marks its sections differently) the server draws it
            const order = all.map((one) => one.dataset.section);
            changed({preview: false});
            ask({type: 'reorder', order}, 'reordered', 500).then((answer) => { if (!answer?.ok) refresh(); });
        } else {
            changed();
        }
    };

    const dragRow = (event, list) => {
        const grip = event.target.closest('[data-grip]');
        if (!grip || event.button !== 0) return;
        const item = grip.closest('[data-sort]');
        if (!item || item.parentElement !== list) return;
        event.preventDefault();
        // The rows stay where they are in the DOM while one is dragged (moving a node that holds the pointer capture ends the drag):
        // the dragged one follows the pointer, the others slide aside, and the DOM changes once, on release.
        const rows = items(list);
        const from = rows.indexOf(item);
        const scroller = list.closest('.studio-edit') || document.scrollingElement;
        const listTop = () => list.getBoundingClientRect().top;
        const base = listTop();
        const tops = rows.map((row) => row.getBoundingClientRect().top - base);
        const heights = rows.map((row) => row.offsetHeight);
        const gap = rows.length > 1 ? Math.max(0, tops[1] - (tops[0] + heights[0])) : 0;
        const grab = event.clientY - (base + tops[from]);
        let pointerY = event.clientY;
        let to = from;
        let raf = 0;
        let done = false;
        item.classList.add('dragging');
        rows.forEach((row) => { if (row !== item) row.classList.add('shifting'); });
        grip.setPointerCapture(event.pointerId);

        const place = () => {
            const y = pointerY - listTop() - grab; // where the dragged row's top is, inside the list
            item.style.transform = `translateY(${y - tops[from]}px)`;
            const middle = y + heights[from] / 2;
            to = rows.filter((row, j) => j !== from && tops[j] + heights[j] / 2 < middle).length;
            const order = rows.filter((row) => row !== item);
            order.splice(to, 0, item);
            let top = tops[0];
            const newTop = new Map();
            order.forEach((row) => { newTop.set(row, top); top += heights[rows.indexOf(row)] + gap; });
            rows.forEach((row, j) => { if (row !== item) row.style.transform = `translateY(${newTop.get(row) - tops[j]}px)`; });
        };
        const edges = () => {
            const box = scroller.getBoundingClientRect();
            const speed = pointerY < box.top + 50 ? -14 : pointerY > box.bottom - 50 ? 14 : 0;
            if (speed) { scroller.scrollTop += speed; place(); }
            raf = requestAnimationFrame(edges);
        };
        const move = (ev) => { pointerY = ev.clientY; place(); };
        const finish = () => {
            if (done) return;
            done = true;
            grip.removeEventListener('pointermove', move);
            grip.removeEventListener('pointerup', finish);
            grip.removeEventListener('pointercancel', finish);
            grip.removeEventListener('lostpointercapture', finish);
            cancelAnimationFrame(raf);
            rows.forEach((row) => { row.classList.remove('shifting'); row.style.transform = ''; }); // no slide back: the DOM takes the new order now
            item.classList.remove('dragging');
            if (to !== from) {
                const others = rows.filter((row) => row !== item);
                to === 0 ? others[0].before(item) : others[to - 1].after(item);
                moved(list, item);
            }
        };
        grip.addEventListener('pointermove', move);
        grip.addEventListener('pointerup', finish);
        grip.addEventListener('pointercancel', finish);
        grip.addEventListener('lostpointercapture', finish);
        place();
        raf = requestAnimationFrame(edges);
    };

    form.querySelectorAll('[data-sortable]').forEach((list) => {
        list.addEventListener('pointerdown', (event) => dragRow(event, list));
        list.addEventListener('keydown', (event) => {
            const grip = event.target.closest('[data-grip]');
            if (!grip || !['ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const item = grip.closest('[data-sort]');
            const all = items(list);
            const at = all.indexOf(item);
            const to = event.key === 'Home' ? 0 : event.key === 'End' ? all.length - 1 : Math.max(0, Math.min(all.length - 1, at + (event.key === 'ArrowDown' ? 1 : -1)));
            if (to === at) return;
            to > at ? all[to].after(item) : all[to].before(item);
            grip.focus();
            moved(list, item);
        });
    });
    // items added or removed in a menu change what the page shows, and what is announced
    form.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (button?.matches('[data-add], [data-remove]') && button.closest('[data-menu-location]')) {
            live.textContent = button.matches('[data-add]') ? texts.itemAdded : texts.itemRemoved;
        }
    });

    // ------------------------------------------------------------------ sections the client adds, copies and removes (when the theme lets them)
    const gallery = form.querySelector('[data-gallery]');
    const sectionList = form.querySelector('[data-sections]');
    let openGallery = () => {};
    if (gallery && sectionList) {
        const empty = form.querySelector('[data-sections-empty]');
        const rowOf = (name) => (name ? sectionList.querySelector(`:scope > [data-section="${CSS.escape(name)}"]`) : null);
        const syncEmpty = () => { if (empty) empty.hidden = items(sectionList).length > 0; };
        let target = ''; // the section a new one goes before; '' is the end of the list
        let busy = false;

        // what was just done, and the way back
        const toast = document.createElement('div');
        toast.className = 'studio-toast';
        toast.setAttribute('role', 'status');
        toast.hidden = true;
        form.querySelector('.studio-edit').append(toast);
        let toastTimer;
        const tell = (text, undo = null, problem = false) => {
            clearTimeout(toastTimer);
            toast.replaceChildren(Object.assign(document.createElement('span'), {textContent: text}));
            toast.classList.toggle('problem', problem);
            if (undo) {
                const button = Object.assign(document.createElement('button'), {type: 'button', textContent: texts.undo});
                button.addEventListener('click', () => { toast.hidden = true; undo(); });
                toast.append(button);
            }
            toast.hidden = false;
            toastTimer = setTimeout(() => { toast.hidden = true; }, undo ? 9000 : 5000);
        };

        // the server draws the row (a copy is what the form holds now, so nothing typed is lost) and the form keeps it like any other section
        const request = async (fields) => {
            const data = new FormData(form);
            data.delete('action');
            Object.entries(fields).forEach(([key, value]) => data.set(key, value));
            const response = await fetch(gallery.dataset.sectionUrl, {method: 'POST', body: window.pbSeal?.(data) ?? data, headers: {Accept: 'application/json'}});
            const answer = await response.json().catch(() => null);
            if (!answer?.ok) throw new Error(answer?.error || (response.status >= 400 ? fill(texts.refused, response.status) : texts.offline));
            return answer;
        };
        const put = (html, before) => {
            const holder = document.createElement('template');
            holder.innerHTML = html.trim();
            const row = holder.content.firstElementChild;
            before ? before.before(row) : sectionList.append(row);
            row.dispatchEvent(new CustomEvent('pb:added', {bubbles: true})); // the text editor and the links start in the new fields
            return row;
        };
        const added = (row, message) => {
            syncEmpty();
            row.scrollIntoView({block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth'});
            row.querySelector('.field input:not([type=hidden]), .field textarea')?.focus({preventScroll: true});
            live.textContent = message;
            tell(message);
            pendingShow = row.dataset.section;
            changed();
        };
        const run = async (work) => {
            if (busy) return;
            busy = true;
            try { await work(); } catch (error) { tell(error.message, null, true); }
            busy = false;
        };

        openGallery = (before = '') => {
            target = rowOf(before) ? before : '';
            gallery.showModal();
            gallery.querySelector('[data-add-piece]')?.focus();
        };
        gallery.addEventListener('click', (event) => { if (event.target === gallery) gallery.close(); }); // the dark area around it
        form.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            const row = button.closest('[data-section]');
            if (button.matches('[data-open-gallery]')) {
                openGallery('');
            } else if (button.matches('[data-insert-before]')) {
                openGallery(row.dataset.section);
            } else if (button.matches('[data-gallery-close]')) {
                gallery.close();
            } else if (button.matches('[data-add-piece]')) {
                gallery.close();
                run(async () => {
                    const answer = await request({op: 'add', type: button.dataset.addPiece});
                    added(put(answer.html, rowOf(target)), fill(texts.pieceAdded, answer.label));
                });
            } else if (button.matches('[data-duplicate]') && row) {
                run(async () => {
                    const answer = await request({op: 'duplicate', section: row.dataset.section});
                    added(put(answer.html, row.nextElementSibling), fill(texts.pieceCopied, answer.label));
                });
            } else if (button.matches('[data-remove-piece]') && row) {
                const next = row.nextElementSibling;
                const label = nameOf(row);
                row.remove();
                syncEmpty();
                changed();
                live.textContent = fill(texts.pieceRemoved, label);
                tell(fill(texts.pieceRemoved, label), () => {
                    next?.isConnected ? next.before(row) : sectionList.append(row);
                    syncEmpty();
                    changed();
                    live.textContent = fill(texts.pieceBack, label);
                });
            }
        });
        // a piece's row shows the title being typed in it, and says when it is switched off
        form.addEventListener('input', (event) => {
            if (/^f\[_blocks\]\[[a-z0-9]+\]\[title\]$/.test(event.target.name || '')) event.target.closest('[data-piece]').querySelector('[data-piece-title]').textContent = event.target.value;
        });
        form.addEventListener('change', (event) => {
            if (event.target.matches('[data-visible-switch]')) event.target.closest('[data-piece]').querySelector('[data-hidden-badge]').hidden = event.target.checked;
        });
    }

    // a group the client opens is the one shown on the page; one the pointer is over is outlined there (sections that move, and the others)
    const groupName = (node) => node.closest?.('details[data-group]')?.dataset.group || node.closest?.('[data-section]')?.dataset.section || '';
    const edit = form.querySelector('.studio-edit');
    edit.addEventListener('toggle', (event) => {
        const name = event.target.matches?.('details') ? groupName(event.target) : '';
        if (name && event.target.open) toFrame({type: 'show', name});
    }, true);
    edit.addEventListener('pointerover', (event) => {
        if (!event.target.closest('.dragging')) toFrame({type: 'outline', name: groupName(event.target)});
    });
    edit.addEventListener('pointerleave', () => toFrame({type: 'outline', name: ''}));

    // what the page itself says: a section was picked or dragged there, or it answers what was asked of it
    addEventListener('message', (event) => {
        if (!frames.some((frame) => frame.contentWindow === event.source) || !event.data?.pb) return;
        const message = event.data;
        if (waiting.has(message.type)) { waiting.get(message.type)(message); return; }
        const list = form.querySelector('[data-sections]');
        if (message.type === 'insert') {
            openGallery(message.before || ''); // the "+" between two sections of the page itself
        } else if (message.type === 'order' && list) {
            // dragged on the page: it already shows the new order; the list follows
            const byName = Object.fromEntries(items(list).map((item) => [item.dataset.section, item]));
            message.order.filter((name) => byName[name]).forEach((name) => list.append(byName[name]));
            const one = byName[message.moved] || items(list)[0];
            live.textContent = fill(texts.moved, nameOf(one), items(list).indexOf(one) + 1, items(list).length);
            changed({preview: false});
        } else if (message.type === 'select') {
            const item = form.querySelector(`[data-sections] > [data-section="${CSS.escape(message.name)}"], details[data-group="${CSS.escape(message.name)}"]`);
            if (!item) return;
            setView('edit');
            (item.matches('details') ? item : item.querySelector(':scope > details')).open = true;
            item.scrollIntoView({block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth'});
            item.classList.add('picked');
            setTimeout(() => item.classList.remove('picked'), 1400);
            item.querySelector('summary')?.focus({preventScroll: true});
        }
    });

    // ------------------------------------------------------------------ menus: a label that is left empty shows the page's title; the page beside the form scrolls to the menu being edited
    if (form.dataset.studio === 'menus') {
        const hint = (select) => {
            const label = select.closest('[data-menu-item]')?.querySelector('[data-menu-label]');
            if (label) label.placeholder = select.value.startsWith('page:') ? select.selectedOptions[0].textContent.trim() : texts.menuLabel;
        };
        form.querySelectorAll('[data-menu-item] .pb-link select').forEach(hint);
        form.addEventListener('change', (event) => { if (event.target.matches('.pb-link select')) hint(event.target); });
        form.addEventListener('click', (event) => { // a new item starts with the hint of the page it points to
            if (event.target.closest('[data-add]')) setTimeout(() => form.querySelectorAll('[data-menu-item] .pb-link select').forEach(hint), 0);
        });
        form.addEventListener('change', (event) => {
            const quick = event.target.closest?.('[data-quick-add]');
            if (!quick || !quick.value) return;
            const card = quick.closest('[data-menu-location]');
            card.querySelector('[data-add]').click(); // a new row at the end of the menu...
            const row = [...card.querySelectorAll('[data-menu-item]')].pop();
            const select = row.querySelector('.pb-link select');
            select.value = quick.value; // ...already pointing to the page that was picked
            select.dispatchEvent(new Event('change', {bubbles: true}));
            quick.value = '';
            row.scrollIntoView({block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth'});
        });
        let edgeTimer;
        form.addEventListener('focusin', (event) => {
            const card = event.target.closest('[data-menu-location]');
            if (!card) return;
            clearTimeout(edgeTimer);
            edgeTimer = setTimeout(() => toFrame({type: 'edge', to: card.dataset.edge}), 120);
        });
    }

    // ------------------------------------------------------------------ the divider: drag it (mouse, touch), or use the arrow keys; two clicks put it back
    const divider = form.querySelector('[data-split]');
    const DEFAULT_SHARE = form.dataset.studio === 'menus' ? 34 : 40;
    const SPLIT_KEY = form.dataset.studio === 'menus' ? 'pb-studio-split-menus' : 'pb-studio-split';
    let share = DEFAULT_SHARE; // what the person chose, as a percentage of the width: the CSS keeps both sides at 20 rem or more
    const rem = () => parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
    const limits = () => {
        const width = body.getBoundingClientRect().width || 1;
        return {min: Math.ceil((20 * rem() / width) * 1000) / 10, max: Math.floor(((width - 20 * rem() - 10) / width) * 1000) / 10};
    };
    const actual = () => (edit.getBoundingClientRect().width / (body.getBoundingClientRect().width || 1)) * 100;
    const showShare = () => {
        const {min, max} = limits();
        divider.setAttribute('aria-valuenow', String(Math.round(actual())));
        divider.setAttribute('aria-valuemin', String(Math.ceil(min)));
        divider.setAttribute('aria-valuemax', String(Math.floor(max)));
    };
    const setShare = (next, save = true) => {
        const {min, max} = limits();
        share = Math.round(Math.min(Math.max(next, min), Math.max(min, max)) * 10) / 10;
        body.style.setProperty('--studio-left', share + '%');
        showShare();
        if (save) { try { localStorage.setItem(SPLIT_KEY, String(share)); } catch (error) {} }
    };
    try { share = parseFloat(localStorage.getItem(SPLIT_KEY)) || DEFAULT_SHARE; } catch (error) { share = DEFAULT_SHARE; }
    body.style.setProperty('--studio-left', share + '%');
    showShare();
    let lastPress = 0;
    divider.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) return;
        event.preventDefault();
        // two presses in a row (a double click, a double tap) put it back; a drag cancels the "double" by taking longer
        if (event.timeStamp - lastPress < 350) { lastPress = 0; setShare(DEFAULT_SHARE); return; }
        lastPress = event.timeStamp;
        divider.setPointerCapture(event.pointerId);
        body.classList.add('resizing');
        const box = body.getBoundingClientRect();
        const move = (ev) => setShare(((ev.clientX - box.left) / box.width) * 100);
        const finish = () => {
            divider.removeEventListener('pointermove', move);
            divider.removeEventListener('pointerup', finish);
            divider.removeEventListener('pointercancel', finish);
            body.classList.remove('resizing');
        };
        divider.addEventListener('pointermove', move);
        divider.addEventListener('pointerup', finish);
        divider.addEventListener('pointercancel', finish);
    });
    divider.addEventListener('keydown', (event) => {
        const step = event.shiftKey ? 8 : 2;
        const {min, max} = limits();
        const next = {ArrowLeft: actual() - step, ArrowRight: actual() + step, Home: min, End: max}[event.key];
        if (next === undefined) return;
        event.preventDefault();
        setShare(next);
    });
    addEventListener('resize', showShare); // the window changes, the person's choice does not: only what is announced

    // ------------------------------------------------------------------ tabs on a narrow screen, and the size of the page beside the form
    const setView = (view) => {
        body.dataset.view = view;
        form.querySelectorAll('[data-tab]').forEach((tab) => tab.setAttribute('aria-selected', String(tab.dataset.tab === view)));
        if (view === 'preview' && dirty) refresh();
    };
    form.querySelectorAll('[data-tab]').forEach((tab) => tab.addEventListener('click', () => setView(tab.dataset.tab)));
    form.querySelectorAll('[data-device]').forEach((button) => button.addEventListener('click', () => {
        form.querySelector('.studio-frames').dataset.device = button.dataset.device;
        form.querySelectorAll('[data-device]').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
    }));

    refresh();
})();
