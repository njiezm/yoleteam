// Exporte chaque écran en PNG (desktop 1440 px + mobile 390 px @2x) puis assemble un PDF.
// Usage : NODE_PATH=<dossier contenant puppeteer-core> node tools/export.cjs [slug…]
const path = require('path');
const fs = require('fs');
const { pathToFileURL } = require('url');
const puppeteer = require('puppeteer-core');
const screens = require('./screens.cjs');

const ROOT = path.resolve(__dirname, '..');
const OUT = path.join(ROOT, 'export');
const CHROME = process.env.CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const only = process.argv.slice(2);
const base = pathToFileURL(path.join(ROOT, 'app.html')).href;

const DEVICES = [
  { key: 'desktop', width: 1440, height: 900, dsf: 1 },
  { key: 'mobile', width: 390, height: 844, dsf: 2, mobile: true },
];

(async () => {
  for (const d of DEVICES) fs.mkdirSync(path.join(OUT, 'png', d.key), { recursive: true });
  const browser = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--allow-file-access-from-files'] });
  const page = await browser.newPage();
  const list = screens.filter(s => !only.length || only.includes(s.slug));

  for (const s of list) {
    for (const d of DEVICES) {
      if (s.mobileOnly && d.key === 'desktop') continue;
      await page.setViewport({ width: d.width, height: d.height, deviceScaleFactor: d.dsf, isMobile: !!d.mobile, hasTouch: !!d.mobile });
      await page.goto('about:blank');
      await page.goto(base + (s.url.startsWith('?') ? s.url : s.url), { waitUntil: 'networkidle0' });
      await page.evaluate(() => document.fonts.ready);
      await new Promise(r => setTimeout(r, 250));
      const file = path.join(OUT, 'png', d.key, `${s.n}-${s.slug}.png`);
      // Les écrans avec modale / panneau restent à la taille de l'écran, les autres en pleine page.
      if (!/modal=|sheet=1/.test(s.url)) {
        const h = await page.evaluate(() => Math.max(document.documentElement.scrollHeight, document.body.scrollHeight));
        await page.setViewport({ width: d.width, height: Math.max(h, d.height), deviceScaleFactor: d.dsf, isMobile: !!d.mobile, hasTouch: !!d.mobile });
        await new Promise(r => setTimeout(r, 150));
      }
      await page.screenshot({ path: file });
      console.log('✓', d.key, s.n, s.slug);
    }
  }

  // PDF : une page par écran (desktop + mobile côte à côte)
  if (!only.length) {
    const img = p => 'data:image/png;base64,' + fs.readFileSync(p).toString('base64');
    const pages = list.map(s => {
      const dk = path.join(OUT, 'png', 'desktop', `${s.n}-${s.slug}.png`);
      const mb = path.join(OUT, 'png', 'mobile', `${s.n}-${s.slug}.png`);
      return `<section><header><span>${s.n}</span><div><small>${s.group}</small><h2>${s.title}</h2></div></header>
        <div class="row">${fs.existsSync(dk) ? `<figure class="dk"><img src="${img(dk)}"><figcaption>Desktop · 1440 px</figcaption></figure>` : ''}
        <figure class="mb"><img src="${img(mb)}"><figcaption>Mobile · 390 px</figcaption></figure></div></section>`;
    }).join('');
    const html = `<!doctype html><html><head><meta charset="utf-8"><style>
      @page { size: 420mm 297mm; margin: 0 }
      body { margin: 0; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #0B2545 }
      section { height: 297mm; box-sizing: border-box; padding: 14mm 16mm; page-break-after: always; background: #F4F7FB; display: flex; flex-direction: column; overflow: hidden }
      header { display: flex; align-items: center; gap: 14px; margin-bottom: 10mm }
      header span { background: #F5B700; font-weight: 800; border-radius: 10px; padding: 6px 10px; font-size: 18px }
      header small { text-transform: uppercase; letter-spacing: .12em; font-weight: 700; color: #64748B; font-size: 11px }
      header h2 { margin: 2px 0 0; font-size: 24px }
      .row { display: flex; gap: 12mm; align-items: flex-start; flex: 1; min-height: 0 }
      figure { margin: 0; display: flex; flex-direction: column; min-height: 0; max-height: 100% }
      figure img { border-radius: 10px; box-shadow: 0 8px 30px rgba(11,37,69,.18); object-fit: cover; object-position: top; background: #fff }
      .dk { flex: 1 } .dk img { width: 100%; max-height: 240mm }
      .mb img { width: 82mm; max-height: 240mm }
      figcaption { font-size: 11px; color: #64748B; margin-top: 6px; font-weight: 600 }
      .cover { background: #0B2545; color: #fff; justify-content: center; padding: 30mm }
      .cover h1 { font-size: 64px; margin: 0 } .cover p { color: #B1C6E0; font-size: 20px }
      .cover b { color: #F5B700 }
    </style></head><body>
      <section class="cover"><p><b>YoleTeam</b> · Association Yole Nou</p><h1>Maquettes de l’application</h1><p>Plan d’équipage de yole ronde, présences du jour, régates — mode hors ligne.<br>${list.length} écrans · desktop & mobile · septembre 2026</p></section>
      ${pages}</body></html>`;
    const tmp = path.join(OUT, '_print.html');
    fs.writeFileSync(tmp, html);
    await page.setViewport({ width: 1587, height: 1123, deviceScaleFactor: 1 });
    await page.goto(pathToFileURL(tmp).href, { waitUntil: 'load' });
    await page.pdf({ path: path.join(OUT, 'YoleTeam-maquettes.pdf'), preferCSSPageSize: true, printBackground: true });
    fs.unlinkSync(tmp);
    console.log('✓ PDF', path.join(OUT, 'YoleTeam-maquettes.pdf'));
  }
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
