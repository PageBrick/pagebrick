// The bar at the bottom of the site for people signed in to the panel (core/views/admin-bar.php). No build step.
(() => {
    const host = document.getElementById('pb-bar');
    const template = document.getElementById('pb-bar-template');
    if (!host || !template || !host.attachShadow) return;
    const root = host.attachShadow({mode: 'open'});
    root.append(template.content.cloneNode(true));
    const $ = (selector) => root.querySelector(selector);
    const bar = $('.bar'), tab = $('.tab'), live = $('[data-live]'), msg = $('.msg');
    const store = {
        get: (kind, key) => { try { return window[kind].getItem(key); } catch (e) { return null; } },
        set: (kind, key, value) => { try { window[kind].setItem(key, value); } catch (e) {} },
    };

    // Hide it for a while (this tab only: the next visit shows it again) and bring it back from the little brick.
    const show = (visible) => {
        bar.hidden = !visible;
        tab.hidden = visible;
        host.style.height = visible ? '40px' : '0';
        store.set('sessionStorage', 'pb-bar', visible ? 'shown' : 'hidden');
    };
    show(store.get('sessionStorage', 'pb-bar') !== 'hidden');
    $('[data-hide]').addEventListener('click', () => show(false));
    tab.addEventListener('click', () => show(true));

    root.querySelectorAll('[data-reload]').forEach((button) => button.addEventListener('click', () => location.reload()));
    // The same address in a window the size of a phone (it keeps itself up to date too, so it can sit beside the panel).
    $('[data-phone]').addEventListener('click', () => window.open(location.href, 'pb-phone', 'popup=yes,width=420,height=860'));

    // Live: ask the panel now and then whether anything that shapes the site was saved; if so, show it.
    let on = store.get('localStorage', 'pb-live') !== '0';
    let stamp = host.dataset.stamp;
    let timer = null;
    const paint = () => live.setAttribute('aria-checked', String(on));
    paint();
    live.addEventListener('click', () => {
        on = !on;
        store.set('localStorage', 'pb-live', on ? '1' : '0');
        paint();
    });
    const typing = () => {
        const active = document.activeElement;
        return !!active && (active.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName));
    };
    // Never reload more than a few times a minute, whatever happens (a stamp that kept changing would loop forever).
    const reload = () => {
        const now = Date.now();
        const recent = JSON.parse(store.get('sessionStorage', 'pb-reloads') || '[]').filter((time) => now - time < 60000);
        if (recent.length >= 5) {
            on = false;
            paint();
            msg.hidden = false;
            return;
        }
        store.set('sessionStorage', 'pb-reloads', JSON.stringify([...recent, now]));
        location.reload();
    };
    const check = async () => {
        if (document.hidden || !host.dataset.poll) return;
        try {
            const response = await fetch(host.dataset.poll, {credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}});
            const data = await response.json();
            if (data.stamp && data.stamp !== stamp) {
                stamp = data.stamp;
                if (on && !typing()) reload(); else msg.hidden = false; // not while someone is typing on the page
            }
        } catch (e) {
            clearInterval(timer); // signed out of the panel, or offline: stop quietly
        }
    };
    timer = setInterval(check, 4000);
    document.addEventListener('visibilitychange', check); // back on this tab: look right away
})();
