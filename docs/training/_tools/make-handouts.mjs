// Generate PDF handouts of the training video recording scripts, so they
// can be shared with non-technical staff instead of raw GitHub markdown.
// Converts each script Markdown -> styled HTML (reusing docs/user-guides'
// md2html.mjs) -> PDF via headless Chrome (no extra npm deps).
// Output: docs/training/pdf/<name>.pdf
//
// Usage: node docs/training/_tools/make-handouts.mjs
//   Override the browser with CHROME_BIN=/path/to/chrome (or msedge).
import { execFileSync } from 'child_process';
import { mkdirSync, rmSync, existsSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath, pathToFileURL } from 'url';

const here = dirname(fileURLToPath(import.meta.url));
const trainingDir = resolve(here, '..');
const md2html = resolve(trainingDir, '../user-guides/_tools/md2html.mjs');
const outDir = resolve(trainingDir, 'pdf');
const tmpDir = resolve(here, '.tmp');
mkdirSync(outDir, { recursive: true });
mkdirSync(tmpDir, { recursive: true });

const candidates = [
  process.env.CHROME_BIN,
  'C:/Program Files/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
  '/usr/bin/google-chrome',
  '/usr/bin/chromium',
].filter(Boolean);
const browser = candidates.find((p) => existsSync(p));
if (!browser) {
  console.error('No Chrome/Edge found. Set CHROME_BIN to a Chromium-based browser.');
  process.exit(1);
}

const docs = ['README', 'getting-started', 'sales', 'support', 'accounts', 'manager', 'admin', 'intern'];

for (const name of docs) {
  const html = resolve(tmpDir, `${name}.html`);
  execFileSync('node', [md2html, resolve(trainingDir, `${name}.md`), html], { stdio: 'inherit' });

  const pdf = resolve(outDir, `${name}.pdf`);
  execFileSync(browser, [
    '--headless=new', '--disable-gpu', '--no-sandbox', '--no-pdf-header-footer',
    `--print-to-pdf=${pdf}`,
    pathToFileURL(html).href,
  ], { stdio: 'inherit' });
  console.log(`PDF -> docs/training/pdf/${name}.pdf`);
}

rmSync(tmpDir, { recursive: true, force: true });
console.log('\nDone. Training handouts are in docs/training/pdf/');
