// Runs inside the preview of the panel's split screens (the site as visitors will see it, with its sections marked by pb_section()).
// Hover a section to see its name and a handle; drag the handle to move the section, click to edit it. It also does what the editor asks of the
// page without loading it again: put the sections in a new order, replace the ones that changed, scroll to the top or the bottom.
(() => {
    if (window.parent === window) return;
    const config = window.PB_FRAME || {labels: {}, movable: [], texts: {}};
    const send = (message) => parent.postMessage({pb: true, ...message}, '*');
    const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
    // A theme can switch off what waits for a scroll to show (things that fade in), which would never run again on what is replaced here.
    document.documentElement.classList.add('pb-editing');

    // The page always has a scrollbar here, the design system's: a 2px line, brick over a faint track, no arrows. A theme that hides its own
    // (the pagebrick.org one draws a reading line instead) gets this one in the preview, and its reading line steps aside.
    const bar = document.createElement('style');
    bar.textContent = `
        @supports selector(::-webkit-scrollbar) {
            html, html.js { scrollbar-width: auto !important; }
            ::-webkit-scrollbar { display: block !important; width: 2px !important; height: 2px !important; }
            ::-webkit-scrollbar-button { display: none !important; width: 0 !important; height: 0 !important; }
            ::-webkit-scrollbar-track { background: rgb(128 128 128 / .18) !important; }
            ::-webkit-scrollbar-thumb { border-radius: 2px; background: #d24e2b !important; }
            ::-webkit-scrollbar-corner { background: transparent !important; }
        }
        @supports not selector(::-webkit-scrollbar) { html, html.js { scrollbar-width: thin !important; scrollbar-color: #d24e2b rgb(128 128 128 / .18) !important; } }
        .scroll-line { display: none !important; }
    `;
    document.head.append(bar);

    // The sections are the elements between the two comments pb_section() writes around each one (pb-section:name and /pb-section:name).
    const scan = () => {
        const found = [];
        const opened = {};
        const walker = document.createTreeWalker(document, NodeFilter.SHOW_COMMENT);
        while (walker.nextNode()) {
            const comment = walker.currentNode;
            const start = /^pb-section:([a-z0-9_-]+)$/i.exec(comment.data);
            const end = /^\/pb-section:([a-z0-9_-]+)$/i.exec(comment.data);
            if (start) {
                opened[start[1]] = comment;
            } else if (end && opened[end[1]]) {
                const nodes = [];
                for (let node = opened[end[1]].nextSibling; node && node !== comment; node = node.nextSibling) {
                    if (node.nodeType === 1) nodes.push(node);
                }
                found.push({name: end[1], start: opened[end[1]], end: comment, nodes, top: 0, bottom: 0, left: 0, right: 0, visible: false});
                delete opened[end[1]];
            }
        }
        return found;
    };
    let sections = scan();
    send({type: 'ready', names: sections.map((section) => section.name)});

    // ------------------------------------------------------------------ what the editor asks of this page
    const wholeRange = (section) => {
        const nodes = [];
        for (let node = section.start; node; node = node.nextSibling) {
            nodes.push(node);
            if (node === section.end) break;
        }
        return nodes;
    };
    // The sections that can move trade places among themselves: the i-th place takes the i-th name of the new order; nothing else is touched.
    const reorder = (order) => {
        const movable = new Set(config.movable);
        const slots = sections.filter((section) => movable.has(section.name));
        const wanted = order.filter((name) => movable.has(name));
        if (!slots.length || wanted.length !== slots.length || !wanted.every((name) => slots.some((section) => section.name === name))) return false;
        const parent = slots[0].start.parentNode;
        if (slots.some((section) => section.start.parentNode !== parent || section.end.parentNode !== parent)) return false;
        const pieces = new Map(slots.map((section) => [section.name, wholeRange(section)]));
        const marks = slots.map((section) => { const mark = document.createComment('pb-slot'); section.start.before(mark); return mark; });
        pieces.forEach((nodes) => nodes.forEach((node) => node.remove()));
        marks.forEach((mark, i) => { mark.after(...pieces.get(wanted[i])); mark.remove(); });
        return true;
    };
    // The sections whose content changed are replaced where they are: the rest of the page (scroll, pictures already loaded) stays as it was.
    const patch = (patches, order) => {
        for (const [name, html] of Object.entries(patches || {})) {
            const section = sections.find((one) => one.name === name);
            if (!section || section.start.parentNode !== section.end.parentNode) return false;
            const template = document.createElement('template');
            template.innerHTML = html;
            for (let node = section.start.nextSibling; node && node !== section.end;) {
                const next = node.nextSibling;
                node.remove();
                node = next;
            }
            section.start.after(...template.content.childNodes);
        }
        if (order && !reorder(order)) return false;
        sections = scan();
        // what a theme left invisible until it scrolls into view would stay invisible: better to load the page again
        return !Object.keys(patches || {}).some((name) => sections.find((one) => one.name === name)?.nodes.some((node) => parseFloat(getComputedStyle(node).opacity) < 0.05));
    };

    let hovered = null;
    let chosen = null;
    let dragging = null;
    let fade = 0;
    let draw = () => {};
    let measure = () => {};
    addEventListener('message', (event) => {
        if (event.source !== parent || !event.data?.pb) return;
        const message = event.data;
        if (message.type === 'reorder') {
            const ok = reorder(message.order);
            if (ok) { sections = scan(); }
            hovered = chosen = null;
            draw();
            send({type: 'reordered', ok});
        } else if (message.type === 'patch') {
            const ok = patch(message.patches, message.order);
            hovered = chosen = null;
            draw();
            send({type: 'patched', ok});
        } else if (message.type === 'edge') {
            scrollTo({top: message.to === 'bottom' ? document.documentElement.scrollHeight : 0, behavior: reduceMotion ? 'auto' : 'smooth'});
        } else if (message.type === 'outline' || message.type === 'show') {
            const section = sections.find((one) => one.name === message.name);
            if (message.type === 'outline') {
                chosen = section && section.visible ? section : null;
                draw();
            } else if (section) {
                measure();
                if (section.visible) {
                    const inView = section.top >= scrollY - 40 && section.top <= scrollY + innerHeight * 0.6;
                    if (!inView) scrollTo({top: Math.max(0, section.top - 24), behavior: reduceMotion ? 'auto' : 'smooth'});
                }
                chosen = section;
                draw();
                clearTimeout(fade);
                fade = setTimeout(() => { chosen = null; draw(); }, 1800);
            }
        }
    });

    // ------------------------------------------------------------------ the handles and outlines drawn over the sections
    const style = document.createElement('style');
    style.textContent = `
        #pb-frame { position: absolute; top: 0; left: 0; width: 100%; height: 0; z-index: 2147483000; pointer-events: none; font: 600 12px/1 system-ui, sans-serif; }
        #pb-frame * { box-sizing: border-box; }
        #pb-frame .box { position: absolute; display: none; border-radius: 4px; }
        #pb-frame .hover { outline: 2px dashed #d24e2b; outline-offset: -2px; }
        #pb-frame .chosen { outline: 3px solid #d24e2b; outline-offset: -3px; background: rgb(210 78 43 / .08); transition: opacity .4s; }
        #pb-frame .lifted { background: rgb(210 78 43 / .22); outline: 2px solid #d24e2b; outline-offset: -2px; }
        #pb-frame .chip { position: absolute; display: none; align-items: center; gap: 6px; height: 28px; padding: 0 10px 0 6px; border-radius: 8px; background: #d24e2b; color: #fff;
            box-shadow: 0 4px 14px rgb(0 0 0 / .25); cursor: grab; pointer-events: auto; touch-action: none; user-select: none; white-space: nowrap; }
        #pb-frame .chip svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; }
        #pb-frame .chip.fixed { padding-left: 10px; cursor: pointer; }
        #pb-frame .chip.fixed svg { display: none; }
        #pb-frame .line { position: absolute; display: none; left: 0; right: 0; height: 4px; margin-top: -2px; background: #d24e2b; border-radius: 2px; box-shadow: 0 0 0 3px rgb(210 78 43 / .25); }
        html.pb-dragging, html.pb-dragging * { cursor: grabbing !important; user-select: none !important; }
    `;
    document.head.append(style);
    const layer = document.createElement('div');
    layer.id = 'pb-frame';
    layer.innerHTML = '<div class="box hover"></div><div class="box chosen"></div><div class="box lifted"></div><div class="line"></div>'
        + '<div class="chip"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="12" r="1"/><circle cx="9" cy="5" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="19" r="1"/></svg><span></span></div>';
    document.documentElement.append(layer);
    const [hoverBox, chosenBox, liftedBox, line, chip] = layer.children;

    measure = () => {
        const x = scrollX;
        const y = scrollY;
        for (const section of sections) {
            const rects = section.nodes.map((node) => node.getBoundingClientRect()).filter((rect) => rect.width > 0 && rect.height > 0);
            section.visible = rects.length > 0;
            if (!section.visible) continue;
            section.top = Math.min(...rects.map((rect) => rect.top)) + y;
            section.bottom = Math.max(...rects.map((rect) => rect.bottom)) + y;
            section.left = Math.min(...rects.map((rect) => rect.left)) + x;
            section.right = Math.max(...rects.map((rect) => rect.right)) + x;
        }
    };
    const frame = (box, section) => {
        box.style.cssText = `display:block;top:${section.top}px;left:${section.left}px;width:${section.right - section.left}px;height:${section.bottom - section.top}px`;
    };
    draw = () => {
        measure();
        if (hovered?.visible) {
            frame(hoverBox, hovered);
            chip.querySelector('span').textContent = config.labels[hovered.name] || hovered.name;
            const movable = config.movable.includes(hovered.name);
            chip.classList.toggle('fixed', !movable);
            chip.title = (movable ? config.texts.drag : config.texts.edit) || '';
            chip.style.display = 'flex';
            // the handle stays on screen while a tall section scrolls
            chip.style.left = `${hovered.left + 10}px`;
            chip.style.top = `${Math.min(Math.max(hovered.top + 10, scrollY + 10), hovered.bottom - 38)}px`;
        } else {
            hoverBox.style.display = 'none';
            if (!dragging) chip.style.display = 'none';
        }
        if (chosen?.visible) frame(chosenBox, chosen); else chosenBox.style.display = 'none';
    };
    const sectionAt = (x, y) => sections.find((section) => section.visible && y >= section.top && y <= section.bottom && x >= section.left && x <= section.right);

    addEventListener('pointermove', (event) => {
        if (dragging || !sections.length) return;
        const found = sectionAt(event.pageX, event.pageY) || (event.target.closest?.('#pb-frame .chip') ? hovered : null);
        if (found !== hovered) { hovered = found; draw(); }
    });
    document.documentElement.addEventListener('pointerleave', () => { if (!dragging) { hovered = null; draw(); } });
    addEventListener('scroll', () => { if (hovered) draw(); }, {passive: true});

    // click: the section is picked for editing (and the page's own links stay still: this is only a preview)
    document.addEventListener('click', (event) => {
        if (event.target.closest('#pb-frame')) return;
        if (event.target.closest('a[href], button, [role=button]')) event.preventDefault();
        const section = sectionAt(event.pageX, event.pageY);
        if (section) send({type: 'select', name: section.name});
    }, true);
    document.addEventListener('submit', (event) => event.preventDefault(), true);

    // drag the handle: a line shows where the section will land; dropping puts it there at once and tells the editor the new order
    chip.addEventListener('pointerdown', (event) => {
        if (!hovered || event.button !== 0) return;
        event.preventDefault();
        if (!config.movable.includes(hovered.name)) { send({type: 'select', name: hovered.name}); return; } // it stays where it is: a click opens it
        chip.setPointerCapture(event.pointerId);
        const section = hovered;
        const origin = event.clientY;
        let travelled = false;
        let order = sections.filter((s) => s.visible).map((s) => s.name);
        let timer = 0;
        let pointerY = event.clientY;
        let done = false;
        const others = () => sections.filter((s) => s.visible && s !== section).sort((a, b) => a.top - b.top);
        const place = () => {
            const y = pointerY + scrollY;
            const rest = others();
            const index = rest.filter((s) => y > (s.top + s.bottom) / 2).length;
            order = [...rest.slice(0, index).map((s) => s.name), section.name, ...rest.slice(index).map((s) => s.name)];
            const at = index === 0 ? (rest[0]?.top ?? section.top) : rest[index - 1].bottom;
            line.style.cssText = `display:block;top:${at}px`;
        };
        const edges = () => {
            const speed = pointerY < 60 ? -16 : pointerY > innerHeight - 60 ? 16 : 0;
            if (speed) { scrollBy(0, speed); place(); }
            timer = requestAnimationFrame(edges);
        };
        const move = (ev) => {
            pointerY = ev.clientY;
            if (!travelled && Math.abs(pointerY - origin) < 5) return;
            if (!travelled) {
                travelled = true;
                dragging = section;
                document.documentElement.classList.add('pb-dragging');
                frame(liftedBox, section);
                hoverBox.style.display = 'none';
                timer = requestAnimationFrame(edges);
            }
            place();
        };
        const finish = (ev) => {
            if (done) return;
            done = true;
            chip.removeEventListener('pointermove', move);
            chip.removeEventListener('pointerup', finish);
            chip.removeEventListener('pointercancel', finish);
            chip.removeEventListener('lostpointercapture', finish);
            cancelAnimationFrame(timer);
            document.documentElement.classList.remove('pb-dragging');
            line.style.display = 'none';
            liftedBox.style.display = 'none';
            const dropped = travelled && ev.type !== 'pointercancel';
            dragging = null;
            if (dropped) {
                const now = sections.filter((s) => s.visible).sort((a, b) => a.top - b.top).map((s) => s.name);
                if (order.join() !== now.join()) {
                    // the sections the page does not show (hidden or empty) keep their place after the ones that moved
                    const hiddenOnes = sections.filter((s) => !s.visible).map((s) => s.name);
                    const full = [...order, ...hiddenOnes];
                    if (reorder(full)) sections = scan();
                    hovered = chosen = null;
                    send({type: 'order', order: full, moved: section.name});
                }
            } else if (!travelled && ev.type === 'pointerup') {
                send({type: 'select', name: section.name});
            }
            draw();
        };
        chip.addEventListener('pointermove', move);
        chip.addEventListener('pointerup', finish);
        chip.addEventListener('pointercancel', finish);
        chip.addEventListener('lostpointercapture', finish);
    });

    addEventListener('resize', draw);
    addEventListener('load', () => { draw(); setTimeout(draw, 400); });
    if (window.ResizeObserver) new ResizeObserver(() => hovered && draw()).observe(document.documentElement);
    document.fonts?.ready.then(draw);
    draw();
})();
