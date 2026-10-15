// Records the short film at the top of the README (docs/media/editing.gif): someone changes the headline of the home page in the panel, with the page
// changing beside the form while they type, drags a section to a new place, publishes, and the site shows it.
// Redo it whenever the panel changes (the GIF shows the panel as it looks that day).
//
// What you need: Node 20+, `npm i puppeteer-core`, ffmpeg, and a clean ENGLISH site, installed and running (the installer's example content is what the film edits),
// with an administrator and the default theme. Then, from this folder:
//
//   PB_URL=http://localhost:8080 PB_EMAIL=admin@example.com PB_PASSWORD=... CHROME=/path/to/chrome node record.mjs
//
// It writes frames and a list to ./frames. Then (from a SHORT path: the Windows build of ffmpeg fails on very long ones):
//
//   cd frames && ffmpeg -y -f concat -safe 0 -i list.txt -vf "fps=12,scale=900:-1:flags=lanczos,split[a][b];[a]palettegen=max_colors=96:stats_mode=diff[p];[b][p]paletteuse=dither=bayer:bayer_scale=4:diff_mode=rectangle" ../editing.gif
//
// Keep it under 10 seconds and a few hundred KB. Use a plain site name and fictional text; look at the result before committing it.
import puppeteer from 'puppeteer-core';
import fs from 'node:fs';

const URL_ = (process.env.PB_URL || 'http://localhost:8080').replace(/\/$/, '');
const OUT = new URL('./frames/', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
fs.rmSync(OUT, {recursive: true, force: true});
fs.mkdirSync(OUT, {recursive: true});

const browser = await puppeteer.launch({executablePath: process.env.CHROME, args: ['--no-sandbox'], headless: true});
const page = await browser.newPage();
await page.setViewport({width: 1100, height: 640, deviceScaleFactor: 1});
await page.setExtraHTTPHeaders({'Accept-Language': 'en'});
const wait = (ms) => new Promise((r) => setTimeout(r, ms));
let n = 0;
const lines = [];
const frame = async (seconds) => {
    const name = `f-${String(++n).padStart(4, '0')}.png`;
    await page.screenshot({path: `${OUT}${name}`});
    lines.push(`file '${name}'`, `duration ${seconds}`);
};
const selectAll = async () => { await page.keyboard.down('Control'); await page.keyboard.press('a'); await page.keyboard.up('Control'); };
// the page beside the form: wait until it says something
const preview = (text, limit = 4000) => page.waitForFunction((t) => document.querySelector('iframe[data-active]')?.contentDocument?.body?.textContent.includes(t), {timeout: limit}, text).catch(() => {});
const order = () => page.$$eval('iframe[data-active]', (f) => [...f[0].contentDocument.documentElement.outerHTML.matchAll(/<!--pb-section:([a-z0-9_-]+)-->/g)].map((m) => m[1]));
// a headless browser has no pointer to hover with: show the panel the way it rests on a desktop
const resting = () => page.addStyleTag({content: '.pb-plus { opacity: 0 !important; } .pb-row-btn { opacity: .6 !important; }'});

// sign in
await page.goto(`${URL_}/admin/login`, {waitUntil: 'networkidle0'});
await page.type('#email', process.env.PB_EMAIL);
await page.type('#password', process.env.PB_PASSWORD);
await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('main button[type=submit]')]);

// 1. the list of pages, 2. the home page: the form on the left, the site on the right
await page.goto(`${URL_}/admin/pages`, {waitUntil: 'networkidle0'});
await wait(300);
await frame(0.4);
await page.click('a[href="/admin/pages/edit?id=1"]');
await page.waitForSelector('[data-sections] > [data-section="hero"]');
await page.waitForFunction(() => document.querySelector('iframe[data-active]')?.contentDocument?.getElementById('pb-frame'), {timeout: 20000});
await resting();
await wait(1200);
await frame(0.7);

// 3. the hero opens: its fields show, and the page shows which part it is
await page.click('[data-sections] > [data-section="hero"] summary');
await wait(500);
await frame(0.5);

// 4. the headline is replaced, a few letters at a time, and the page follows
const title = 'input[name="f[hero][title]"]';
await page.click(title);
await selectAll();
await frame(0.3);
const text = 'Handmade furniture, built to last.';
for (let i = 0; i < text.length; i += 3) { await page.keyboard.type(text.slice(i, i + 3)); await wait(60); await frame(0.06); }
await preview(text);
await wait(150);
await frame(0.9);

// 5. the section "Services" is dragged above "About": the list and the page change places
await page.click('[data-sections] > [data-section="hero"] summary'); // closes it
await wait(200);
const grip = async (name) => { const b = await (await page.$(`[data-sections] > [data-section="${name}"] [data-grip]`)).boundingBox(); return {x: b.x + b.width / 2, y: b.y + b.height / 2}; };
const from = await grip('services');
const to = await (await page.$('[data-sections] > [data-section="about"]')).boundingBox();
await frame(0.3);
await page.mouse.move(from.x, from.y);
await page.mouse.down();
const target = to.y + 6;
for (let i = 1; i <= 6; i++) { await page.mouse.move(from.x, from.y + ((target - from.y) * i) / 6); await frame(0.07); }
await page.mouse.up();
for (let i = 0; i < 60 && (await order()).indexOf('services') > (await order()).indexOf('about'); i++) await wait(50);
await wait(200);
await frame(1.1);

// 6. Publish: nothing was public until now
await page.click('[data-publish]');
await page.waitForNavigation({waitUntil: 'networkidle0'});
await resting();
await wait(400);
await frame(1.1);

// 7. the site shows it
await page.goto(`${URL_}/`, {waitUntil: 'networkidle0'});
await wait(300);
await frame(1.6);

fs.writeFileSync(`${OUT}list.txt`, lines.join('\n') + `\nfile 'f-${String(n).padStart(4, '0')}.png'\n`);
console.log(`${n} frames in ${OUT}`);
await browser.close();
