// The panel's rich text editor (richtext fields): TipTap on ProseMirror, with the panel's own toolbar, floating menu and "/" menu.
// A field is a <textarea data-richtext>; this file puts an editor after it and keeps the textarea's value up to date, so the form
// posts as always. Without JavaScript the textarea stays and still works. Texts come from the server in window.PbEditorTexts.
// Build: `npm run build` in tools/editor (writes core/assets/editor.js). Sites never run a build.
import {Editor, Mark, mergeAttributes} from '@tiptap/core';
import {NodeSelection} from '@tiptap/pm/state';
import Document from '@tiptap/extension-document';
import Paragraph from '@tiptap/extension-paragraph';
import Text from '@tiptap/extension-text';
import Bold from '@tiptap/extension-bold';
import Italic from '@tiptap/extension-italic';
import Underline from '@tiptap/extension-underline';
import Strike from '@tiptap/extension-strike';
import Heading from '@tiptap/extension-heading';
import Blockquote from '@tiptap/extension-blockquote';
import HorizontalRule from '@tiptap/extension-horizontal-rule';
import HardBreak from '@tiptap/extension-hard-break';
import Image from '@tiptap/extension-image';
import {BulletList, OrderedList, ListItem, ListKeymap} from '@tiptap/extension-list';
import {Table, TableRow, TableHeader, TableCell} from '@tiptap/extension-table';
import {UndoRedo, Dropcursor, Gapcursor, TrailingNode, Placeholder} from '@tiptap/extensions';

const T = window.PbEditorTexts || {};
const icon = (name) => `<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">${__ICONS__[name]}</svg>`;
const plain = (text) => text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

/** What the server also accepts in a link (it cleans again on save): web, e-mail, phone, this site, and "www.site.com" typed bare. */
const cleanHref = (value) => {
    value = value.trim();
    if (/^(https?:\/\/|mailto:|tel:|\/|#)/i.test(value)) return value;
    if (/^[a-z0-9-]+(\.[a-z0-9-]+)+(\/\S*)?$/i.test(value)) return 'https://' + value;
    if (/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(value)) return 'mailto:' + value;
    return '';
};

const Link = Mark.create({
    name: 'link',
    inclusive: false,
    addAttributes: () => ({href: {default: null}}),
    parseHTML: () => [{tag: 'a[href]'}],
    renderHTML: ({HTMLAttributes}) => ['a', mergeAttributes({href: HTMLAttributes.href}), 0],
    addCommands() {
        return {
            setLink: (href) => ({commands}) => commands.setMark(this.name, {href}),
            unsetLink: () => ({commands}) => commands.unsetMark(this.name, {extendEmptyMarkRange: true}),
        };
    },
});

/** Only pictures of the media library: the server rebuilds the whole tag from data-media, so nothing else is accepted. */
const LibraryImage = Image.extend({
    addAttributes: () => ({
        src: {default: null},
        alt: {default: ''},
        mediaId: {
            default: null,
            parseHTML: (element) => element.getAttribute('data-media'),
            renderHTML: (attributes) => (attributes.mediaId ? {'data-media': attributes.mediaId} : {}),
        },
    }),
    parseHTML: () => [{tag: 'img[data-media]'}],
});

const el = (tag, className, attributes = {}) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, value));
    return node;
};

/** A button that never takes the focus from the text (so the selection stays), but works with the keyboard. */
const button = (className, html, label, onClick) => {
    const node = el('button', className, {type: 'button', title: label, 'aria-label': label});
    node.innerHTML = html;
    node.addEventListener('mousedown', (event) => event.preventDefault());
    node.addEventListener('click', onClick);
    return node;
};

/** A toolbar is one tab stop: the arrows (and Home/End) move between its buttons. Returns what to call after a button is enabled or disabled. */
function roving(bar) {
    const buttons = () => [...bar.querySelectorAll('button')];
    const focusable = () => buttons().filter((node) => !node.disabled && !node.closest('[hidden]'));
    bar.addEventListener('focusin', (event) => {
        if (event.target.matches('button')) buttons().forEach((node) => { node.tabIndex = node === event.target ? 0 : -1; });
    });
    bar.addEventListener('keydown', (event) => {
        const list = focusable();
        const at = list.indexOf(document.activeElement);
        if (at < 0 || !['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
        const to = event.key === 'Home' ? 0 : event.key === 'End' ? list.length - 1 : (at + (event.key === 'ArrowRight' ? 1 : list.length - 1)) % list.length;
        list[to].focus();
        event.preventDefault();
    });
    return () => {
        const list = focusable();
        const current = list.find((node) => node.tabIndex === 0) || list[0];
        buttons().forEach((node) => { node.tabIndex = node === current ? 0 : -1; });
    };
}

/** Focus goes to the next thing in the page after the editor (a keyboard user's way out of a table or a list). */
const FOCUSABLE = 'a[href], summary, button:not(:disabled), input:not([type=hidden]):not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])';
function leaveAfter(wrap) {
    [...document.querySelectorAll(FOCUSABLE)]
        .find((node) => !wrap.contains(node) && wrap.compareDocumentPosition(node) & Node.DOCUMENT_POSITION_FOLLOWING && node.offsetParent !== null)?.focus();
}

function mount(textarea) {
    if (textarea.dataset.mounted) return;
    textarea.dataset.mounted = '1';
    const label = document.getElementById(textarea.id + '-label');
    const wrap = el('div', 'pb-rt');
    const surface = el('div', 'pb-rt-surface');
    textarea.hidden = true;
    textarea.after(wrap);

    const hint = el('span', 'sr-only', {id: textarea.id + '-hint'});
    hint.textContent = T.leave;
    const leave = () => leaveAfter(wrap);
    const ui = {}; // the toolbar, floating menu and "/" menu are built after the editor, which calls into them
    const editor = new Editor({
        element: surface,
        content: textarea.value,
        extensions: [
            Document, Paragraph, Text, HardBreak, Bold, Italic, Underline, Strike, Blockquote, HorizontalRule,
            Heading.configure({levels: [2, 3]}),
            BulletList, OrderedList, ListItem, ListKeymap,
            UndoRedo, Dropcursor.configure({color: 'var(--accent)', width: 2}), Gapcursor, TrailingNode,
            Link,
            LibraryImage,
            Table.configure({resizable: false}),
            TableRow, TableHeader, TableCell,
            Placeholder.configure({placeholder: T.placeholder || ''}),
        ],
        editorProps: {
            attributes: {class: 'pb-rt-content', role: 'textbox', 'aria-multiline': 'true', 'aria-describedby': hint.id, ...(label ? {'aria-labelledby': label.id} : {})},
            handleKeyDown: (view, event) => {
                if (ui.slash?.key(event)) return true;
                // Tab moves between cells and list items inside the text, so Esc is the way out: it goes on to the next field.
                if (event.key === 'Escape') { leave(); return true; }
                return false;
            },
        },
        onUpdate: ({editor}) => { textarea.value = editor.isEmpty ? '' : editor.getHTML(); },
        onTransaction: () => ui.refresh?.(),
        onSelectionUpdate: () => { ui.slash?.update(); ui.bubble?.update(); },
        onFocus: () => ui.bubble?.update(),
        onBlur: ({event}) => {
            // Moving into the floating menu (its text boxes) is not leaving the editor.
            if (event?.relatedTarget && ui.bubble?.node.contains(event.relatedTarget)) return;
            setTimeout(() => { if (!ui.bubble?.node.contains(document.activeElement)) ui.bubble?.hide(); ui.slash?.hide(); }, 0);
        },
    });
    if (label) label.addEventListener('click', (event) => { event.preventDefault(); editor.commands.focus(); });

    // ------------------------------------------------------------ the toolbar
    const run = (fn) => () => { fn(editor.chain().focus()).run(); };
    const groups = [
        [
            {id: 'undo', icon: 'undo-2', label: T.undo, act: run((c) => c.undo()), can: () => editor.can().undo()},
            {id: 'redo', icon: 'redo-2', label: T.redo, act: run((c) => c.redo()), can: () => editor.can().redo()},
        ],
        [
            {id: 'h2', icon: 'heading-2', label: T.h2, act: run((c) => c.toggleHeading({level: 2})), on: () => editor.isActive('heading', {level: 2})},
            {id: 'h3', icon: 'heading-3', label: T.h3, act: run((c) => c.toggleHeading({level: 3})), on: () => editor.isActive('heading', {level: 3})},
        ],
        [
            {id: 'bold', icon: 'bold', label: T.bold, act: run((c) => c.toggleBold()), on: () => editor.isActive('bold')},
            {id: 'italic', icon: 'italic', label: T.italic, act: run((c) => c.toggleItalic()), on: () => editor.isActive('italic')},
            {id: 'underline', icon: 'underline', label: T.underline, act: run((c) => c.toggleUnderline()), on: () => editor.isActive('underline')},
            {id: 'strike', icon: 'strikethrough', label: T.strike, act: run((c) => c.toggleStrike()), on: () => editor.isActive('strike')},
        ],
        [
            {id: 'bullets', icon: 'list', label: T.bullets, act: run((c) => c.toggleBulletList()), on: () => editor.isActive('bulletList')},
            {id: 'numbers', icon: 'list-ordered', label: T.numbers, act: run((c) => c.toggleOrderedList()), on: () => editor.isActive('orderedList')},
            {id: 'quote', icon: 'quote', label: T.quote, act: run((c) => c.toggleBlockquote()), on: () => editor.isActive('blockquote')},
        ],
        [
            {id: 'link', icon: 'link', label: T.link, act: () => bubble.openLink(), on: () => editor.isActive('link'), can: () => !editor.state.selection.empty || editor.isActive('link')},
            {id: 'image', icon: 'image', label: T.image, act: () => insertImage()},
            {id: 'table', icon: 'table', label: T.table, act: run((c) => c.insertTable({rows: 3, cols: 3, withHeaderRow: true})), can: () => !editor.isActive('table')},
            {id: 'rule', icon: 'minus', label: T.rule, act: run((c) => c.setHorizontalRule())},
        ],
    ];
    const toolbar = el('div', 'pb-rt-toolbar', {role: 'toolbar', 'aria-label': T.toolbar});
    const controls = [];
    for (const group of groups) {
        const box = el('div', 'pb-rt-group');
        for (const item of group) {
            const node = button('pb-rt-btn', icon(item.icon), item.label, item.act);
            if (item.on) node.setAttribute('aria-pressed', 'false');
            box.append(node);
            controls.push({item, node});
        }
        toolbar.append(box);
    }

    // Tools for the table the cursor is in.
    const tableActions = [
        [T.rowAbove, (c) => c.addRowBefore()], [T.rowBelow, (c) => c.addRowAfter()], [T.rowDelete, (c) => c.deleteRow()],
        [T.colLeft, (c) => c.addColumnBefore()], [T.colRight, (c) => c.addColumnAfter()], [T.colDelete, (c) => c.deleteColumn()],
        [T.headerRow, (c) => c.toggleHeaderRow()], [T.tableDelete, (c) => c.deleteTable()],
    ];
    const syncToolbar = roving(toolbar);
    const tableBar = el('div', 'pb-rt-tablebar', {role: 'toolbar', 'aria-label': T.tableTools});
    tableBar.hidden = true;
    for (const [text, act] of tableActions) {
        const node = el('button', 'pb-rt-text-btn', {type: 'button'});
        node.textContent = text;
        node.addEventListener('mousedown', (event) => event.preventDefault());
        node.addEventListener('click', () => act(editor.chain().focus()).run());
        tableBar.append(node);
    }

    const syncTableBar = roving(tableBar);

    function refresh() {
        for (const {item, node} of controls) {
            if (item.on) node.setAttribute('aria-pressed', String(item.on()));
            if (item.can) node.disabled = !item.can();
        }
        tableBar.hidden = !editor.isActive('table');
        syncToolbar();
        syncTableBar();
    }

    // ------------------------------------------------------------ pictures from the media library
    function insertImage() {
        if (!window.pbPickImage) return;
        window.pbPickImage((media) => {
            editor.chain().focus().insertContent({type: 'image', attrs: {src: media.thumb, alt: media.alt || '', mediaId: String(media.id)}}).run();
            // select it, so the floating menu asks what the picture shows
            let found = -1;
            editor.state.doc.descendants((node, pos) => { if (node.type.name === 'image' && node.attrs.mediaId === String(media.id)) found = pos; });
            if (found >= 0) editor.chain().focus().setNodeSelection(found).run();
        });
    }

    // ------------------------------------------------------------ the floating menu: text, link and picture
    const bubble = (() => {
        const node = el('div', 'pb-rt-bubble', {role: 'toolbar', 'aria-label': T.toolbar});
        node.hidden = true;
        const marks = el('div', 'pb-rt-bubble-row');
        for (const id of ['bold', 'italic', 'underline', 'strike', 'link']) {
            const {item} = controls.find((control) => control.item.id === id);
            const control = button('pb-rt-btn', icon(item.icon), item.label, item.act);
            control.dataset.for = id;
            marks.append(control);
        }
        const linkForm = el('form', 'pb-rt-bubble-row');
        const linkInput = el('input', '', {type: 'text', 'aria-label': T.linkUrl, placeholder: 'https://', autocomplete: 'off'});
        const linkApply = button('pb-rt-btn', icon('check'), T.apply, () => {});
        linkApply.type = 'submit';
        const linkRemove = button('pb-rt-btn', icon('unlink'), T.unlink, () => { editor.chain().focus().extendMarkRange('link').unsetLink().run(); close(); });
        const linkCancel = button('pb-rt-btn', icon('x'), T.cancel, () => close());
        linkForm.append(linkInput, linkApply, linkRemove, linkCancel);
        const imageForm = el('div', 'pb-rt-bubble-row');
        const altInput = el('input', '', {type: 'text', 'aria-label': T.imageAlt, placeholder: T.imageAlt, autocomplete: 'off', maxlength: '200'});
        const imageRemove = button('pb-rt-btn', icon('trash-2'), T.imageRemove, () => { editor.chain().focus().deleteSelection().run(); });
        imageForm.append(altInput, imageRemove);
        node.append(marks, linkForm, imageForm);
        wrap.append(node);

        let mode = 'marks'; // marks | link | image
        const place = (from, to) => {
            const a = editor.view.coordsAtPos(from);
            const b = editor.view.coordsAtPos(to);
            const box = wrap.getBoundingClientRect();
            const left = Math.max(4, Math.min((Math.min(a.left, b.left) + Math.max(a.right, b.right)) / 2 - box.left - node.offsetWidth / 2, box.width - node.offsetWidth - 4));
            let top = Math.min(a.top, b.top) - box.top - node.offsetHeight - 8;
            if (top < 0) top = Math.max(a.bottom, b.bottom) - box.top + 8;
            node.style.left = left + 'px';
            node.style.top = top + 'px';
        };
        const show = (next) => {
            mode = next;
            marks.hidden = next !== 'marks';
            linkForm.hidden = next !== 'link';
            imageForm.hidden = next !== 'image';
            node.hidden = false;
        };
        function close() {
            if (mode === 'link') editor.commands.focus();
            hide();
        }
        function hide() { node.hidden = true; mode = 'marks'; }
        const update = () => {
            if (mode === 'link') return; // the person is typing an address: leave the box where it is
            const {selection} = editor.state;
            if (!editor.isFocused && !node.contains(document.activeElement)) return hide();
            if (selection instanceof NodeSelection && selection.node.type.name === 'image') {
                show('image');
                altInput.value = selection.node.attrs.alt || '';
                return place(selection.from, selection.to);
            }
            if (selection.empty) return hide();
            show('marks');
            node.querySelectorAll('[data-for]').forEach((control) => {
                const {item} = controls.find((c) => c.item.id === control.dataset.for);
                control.setAttribute('aria-pressed', String(item.on ? item.on() : false));
            });
            place(selection.from, selection.to);
        };
        const openLink = () => {
            const {selection} = editor.state;
            if (selection.empty && !editor.isActive('link')) return;
            if (selection.empty) editor.chain().extendMarkRange('link').run();
            const {from, to} = editor.state.selection;
            linkInput.value = editor.getAttributes('link').href || '';
            linkRemove.hidden = !editor.isActive('link');
            show('link');
            place(from, to);
            linkInput.focus();
            linkInput.select();
        };
        linkForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const href = cleanHref(linkInput.value);
            if (linkInput.value.trim() === '') editor.chain().focus().extendMarkRange('link').unsetLink().run();
            else if (href !== '') editor.chain().focus().extendMarkRange('link').setLink(href).run();
            else {
                linkInput.setCustomValidity(T.linkBad);
                linkInput.reportValidity();
                linkInput.setCustomValidity('');
                return;
            }
            close();
        });
        node.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(); }
        });
        altInput.addEventListener('input', () => editor.chain().updateAttributes('image', {alt: altInput.value}).run());
        altInput.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); editor.commands.focus(); } });
        return {node, update, hide, openLink};
    })();
    editor.on('update', () => bubble.update());

    // ------------------------------------------------------------ the "/" menu: type / at the start of an empty line
    const slash = (() => {
        const node = el('div', 'pb-rt-slash', {role: 'listbox', 'aria-label': T.insert});
        node.hidden = true;
        wrap.append(node);
        const items = [
            {icon: 'heading-2', label: T.h2, words: 'titulo title heading h2', act: (c) => c.setNode('heading', {level: 2})},
            {icon: 'heading-3', label: T.h3, words: 'subtitulo subtitle subheading h3', act: (c) => c.setNode('heading', {level: 3})},
            {icon: 'list', label: T.bullets, words: 'lista list bullets topicos', act: (c) => c.toggleBulletList()},
            {icon: 'list-ordered', label: T.numbers, words: 'numerada numbered ordered numeros', act: (c) => c.toggleOrderedList()},
            {icon: 'quote', label: T.quote, words: 'citacao quote cita', act: (c) => c.toggleBlockquote()},
            {icon: 'minus', label: T.rule, words: 'divisor linha divider rule separador', act: (c) => c.setHorizontalRule()},
            {icon: 'image', label: T.image, words: 'imagem foto image picture imagen', act: null},
            {icon: 'table', label: T.table, words: 'tabela table tabla', act: (c) => c.insertTable({rows: 3, cols: 3, withHeaderRow: true})},
        ];
        let open = [];
        let active = 0;
        let range = null;
        const hide = () => { node.hidden = true; open = []; editor.view.dom.removeAttribute('aria-activedescendant'); };
        const choose = (item) => {
            const chain = editor.chain().focus().deleteRange(range);
            hide();
            if (item.act) item.act(chain).run();
            else { chain.run(); insertImage(); }
        };
        const draw = () => {
            node.replaceChildren(...open.map((item, i) => {
                const row = el('div', 'pb-rt-slash-item', {role: 'option', id: `${textarea.id}-slash-${i}`, 'aria-selected': String(i === active)});
                row.innerHTML = icon(item.icon);
                row.append(document.createTextNode(item.label));
                row.addEventListener('mousedown', (event) => { event.preventDefault(); choose(item); });
                return row;
            }));
            editor.view.dom.setAttribute('aria-activedescendant', `${textarea.id}-slash-${active}`);
        };
        const update = () => {
            const {selection} = editor.state;
            const {$from} = selection;
            const text = $from.parent.textContent;
            // only on a top-level paragraph that is nothing but "/" and what was typed after it
            if (!selection.empty || $from.depth !== 1 || $from.parent.type.name !== 'paragraph' || $from.parentOffset !== text.length || !/^\/\S*$/.test(text)) return hide();
            const query = plain(text.slice(1));
            const next = items.filter((item) => plain(item.label + ' ' + item.words).includes(query));
            if (!next.length) return hide();
            if (next.length !== open.length || next.some((item, i) => item !== open[i])) active = 0;
            open = next;
            range = {from: $from.start(), to: $from.pos};
            node.hidden = false;
            draw();
            const at = editor.view.coordsAtPos($from.pos);
            const box = wrap.getBoundingClientRect();
            node.style.left = Math.max(4, Math.min(at.left - box.left, box.width - node.offsetWidth - 4)) + 'px';
            node.style.top = at.bottom - box.top + 6 + 'px';
        };
        const key = (event) => {
            if (node.hidden) return false;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                active = (active + (event.key === 'ArrowDown' ? 1 : open.length - 1)) % open.length;
                draw();
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                choose(open[active]);
            } else if (event.key === 'Escape') {
                hide();
            } else {
                return false;
            }
            event.preventDefault();
            return true;
        };
        editor.on('update', update);
        return {update, key, hide};
    })();

    // Ctrl/Cmd+K: a link for the selected text
    surface.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && !event.shiftKey && !event.altKey && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            bubble.openLink();
        }
    });

    Object.assign(ui, {refresh, bubble, slash});
    wrap.prepend(toolbar, tableBar, surface);
    wrap.append(hint);
    refresh();
}

const mountAll = (root) => root.querySelectorAll('textarea[data-richtext]').forEach(mount);
mountAll(document);
// Items added to a list in the panel bring their own fields
document.addEventListener('pb:added', (event) => mountAll(event.target));
