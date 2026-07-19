// Generate all branded marketing PDFs (pitch deck + explainer guide + vertical
// one-pagers). Injects the NEDS logo as base64 into each HTML doc, then renders
// to PDF via headless Chrome (same approach as docs/user-guides/_tools/make-handouts.mjs).
//
// Usage: node docs/marketing/_tools/make-marketing-pdfs.mjs
//   Override the browser with CHROME_BIN=/path/to/chrome (or msedge).
import { execFileSync } from 'child_process';
import { mkdirSync, rmSync, existsSync, readFileSync, writeFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath, pathToFileURL } from 'url';

const here = dirname(fileURLToPath(import.meta.url));
const marketingDir = resolve(here, '..');
const projectRoot = resolve(marketingDir, '../..');
const outDir = resolve(marketingDir, 'pdf');
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

const toDataUri = (path) => {
  const buf = readFileSync(path);
  return `data:image/png;base64,${buf.toString('base64')}`;
};

const logoUri = toDataUri(resolve(projectRoot, 'public/images/neds-logo.png'));
const logoMarkUri = toDataUri(resolve(projectRoot, 'public/images/neds-logo-square.png'));

const docs = [
  { html: 'pitch-deck.html', pdf: 'neds-crm-pitch-deck.pdf' },
  { html: 'explainer-guide.html', pdf: 'neds-crm-explainer-guide.pdf' },
  { html: 'travel-vertical-pitch.html', pdf: 'neds-tours-travel-ai-solution.pdf' },
];

for (const { html: htmlName, pdf: pdfName } of docs) {
  let html = readFileSync(resolve(marketingDir, htmlName), 'utf8');
  html = html.replaceAll('{{LOGO_URI}}', logoUri).replaceAll('{{LOGO_MARK_URI}}', logoMarkUri);

  const tmpHtml = resolve(tmpDir, htmlName);
  writeFileSync(tmpHtml, html, 'utf8');

  const pdf = resolve(outDir, pdfName);
  execFileSync(browser, [
    '--headless=new', '--disable-gpu', '--no-sandbox', '--no-pdf-header-footer',
    `--print-to-pdf=${pdf}`,
    pathToFileURL(tmpHtml).href,
  ], { stdio: 'inherit' });
  console.log(`PDF -> docs/marketing/pdf/${pdfName}`);
}

rmSync(tmpDir, { recursive: true, force: true });
console.log('\nDone. Marketing PDFs are in docs/marketing/pdf/');
