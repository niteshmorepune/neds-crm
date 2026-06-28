// Generate branded PDF handouts of the user guides.
// Converts each guide Markdown -> styled HTML (md2html.mjs) -> PDF via headless
// Chrome (no extra npm deps). Output: docs/user-guides/pdf/<name>.pdf
//
// Usage: node docs/user-guides/_tools/make-handouts.mjs
//   Override the browser with CHROME_BIN=/path/to/chrome (or msedge).
import { execFileSync } from 'child_process';
import { mkdirSync, rmSync, existsSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath, pathToFileURL } from 'url';

const here = dirname(fileURLToPath(import.meta.url));
const guidesDir = resolve(here, '..');
const outDir = resolve(guidesDir, 'pdf');
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

const guides = ['getting-started', 'sales', 'support', 'accounts', 'manager', 'admin', 'client-portal', 'integrations'];

for (const name of guides) {
  const html = resolve(tmpDir, `${name}.html`);
  execFileSync('node', [resolve(here, 'md2html.mjs'), resolve(guidesDir, `${name}.md`), html], { stdio: 'inherit' });

  const pdf = resolve(outDir, `${name}.pdf`);
  execFileSync(browser, [
    '--headless=new', '--disable-gpu', '--no-sandbox', '--no-pdf-header-footer',
    `--print-to-pdf=${pdf}`,
    pathToFileURL(html).href,
  ], { stdio: 'inherit' });
  console.log(`PDF -> docs/user-guides/pdf/${name}.pdf`);
}

rmSync(tmpDir, { recursive: true, force: true });
console.log('\nDone. Handouts are in docs/user-guides/pdf/');
