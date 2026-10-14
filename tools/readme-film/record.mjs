// Records the short film at the top of the README (docs/media/editing.gif): someone changes the headline of the home page in the panel, saves, and the site shows it.
// Redo it whenever the panel changes (the GIF shows the panel as it looks that day).
//
// What you need: Node 20+, `npm i puppeteer-core`, ffmpeg, and a clean ENGLISH site, installed and running (the installer's example content is what the film edits),
// with an administrator. Then, from this folder:
//
//   PB_URL=http://localhost:8080 PB_EMAIL=admin@example.com PB_PASSWORD=... CHROME=/path/to/chrome node record.mjs
//
// It writes frames and a list to ./frames. Then (from a SHORT path: the Windows build of ffmpeg fails on very long ones):
//
//   cd frames && ffmpeg -y -f concat -safe 0 -i list.txt -vf "fps=12,scale=900:-1:flags=lanczos,split[a][b];[a]palettegen=max_colors=96:stats_mode=diff[p];[b][p]paletteuse=dither=bayer:bayer_scale=4:diff_mode=rectangle" -t 9.5 ../../../docs/media/editing.gif
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

// sign in
await page.goto(`${URL_}/admin/login`, {waitUntil: 'networkidle0'});
await page.type('#email', process.env.PB_EMAIL);
await page.type('#password', process.env.PB_PASSWORD);
await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('main button[type=submit]')]);

// 1. the list of pages, 2. the home page
await page.goto(`${URL_}/admin/pages`, {waitUntil: 'networkidle0'});
await wait(300);
await frame(0.4);
await page.click('a[href="/admin/pages/edit?id=1"]');
await page.waitForSelector('input[name="f[hero][title]"]');
await wait(300);
await frame(0.6);

// 3. the headline is replaced, a few letters at a time
const title = 'input[name="f[hero][title]"]';
await page.click(title);
await selectAll();
await frame(0.3);
const text = 'Handmade furniture, built to last.';
for (let i = 0; i < text.length; i += 2) { await page.keyboard.type(text.slice(i, i + 2)); await frame(0.06); }
await frame(0.4);

// 4. the supporting text too
const body = 'textarea[name="f[hero][text]"]';
await page.click(body);
await selectAll();
await page.keyboard.press('Backspace');
const more = 'Tables, chairs and shelves made to order in our own workshop.';
for (let i = 0; i < more.length; i += 4) { await page.keyboard.type(more.slice(i, i + 4)); await frame(0.05); }
await frame(0.3);

// 5. down to Save, and Save
await page.evaluate(() => [...document.querySelectorAll('button')].find((x) => x.textContent.trim() === 'Save').setAttribute('data-film-save', ''));
await page.evaluate(() => scrollTo({top: document.querySelector('[data-film-save]').getBoundingClientRect().top + scrollY - 520, behavior: 'instant'}));
await wait(60);
await frame(0.12);
await frame(0.3);
await Promise.all([page.waitForNavigation({waitUntil: 'networkidle0'}), page.click('[data-film-save]')]);
await wait(250);
await page.evaluate(() => scrollTo({top: 0, behavior: 'instant'}));
await wait(150);
await frame(1.1);

// 6. the site shows it
await page.goto(`${URL_}/`, {waitUntil: 'networkidle0'});
await wait(300);
await frame(1.6);

fs.writeFileSync(`${OUT}list.txt`, lines.join('\n') + `\nfile 'f-${String(n).padStart(4, '0')}.png'\n`);
console.log(`${n} frames in ${OUT}`);
await browser.close();
