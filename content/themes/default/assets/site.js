// Mobile menu. Without JavaScript the menu simply stays open.
(() => {
    const button = document.querySelector('.menu-toggle');
    const nav = document.getElementById('menu-principal');
    if (!button || !nav) return;
    button.addEventListener('click', () => {
        const open = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(open));
        nav.classList.toggle('is-open', open);
    });
})();
