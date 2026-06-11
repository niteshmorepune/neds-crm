// Tiny, purpose-built Markdown -> styled HTML for the NEDS CRM user guides.
// Handles: headings, tables, ordered/unordered lists, blockquotes, code spans,
// bold, links, horizontal rules, and the italic footer line.
// Usage: node md2html.mjs <input.md> <output.html>
import { readFileSync, writeFileSync } from 'fs';

const md = readFileSync(process.argv[2], 'utf8');

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const inline = (s) =>
  esc(s)
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    // Cross-links between guides point to sibling .pdf handouts, not .md.
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, (_, text, url) => `<a href="${url.replace(/\.md(#|$)/, '.pdf$1')}">${text}</a>`);

const lines = md.split(/\r?\n/);
let html = '';
let i = 0;

const flushTable = (rows) => {
  const head = rows[0];
  const body = rows.slice(2);
  let t = '<table><thead><tr>' + head.map((c) => `<th>${inline(c)}</th>`).join('') + '</tr></thead><tbody>';
  for (const r of body) t += '<tr>' + r.map((c) => `<td>${inline(c)}</td>`).join('') + '</tr>';
  return t + '</tbody></table>';
};

while (i < lines.length) {
  const line = lines[i];

  if (/^\s*$/.test(line)) { i++; continue; }

  if (/^---+\s*$/.test(line)) { html += '<hr>'; i++; continue; }

  const m = line.match(/^(#{1,6})\s+(.*)$/);
  if (m) { const lvl = m[1].length; html += `<h${lvl}>${inline(m[2])}</h${lvl}>`; i++; continue; }

  // blockquote (one or more consecutive "> " lines)
  if (/^>\s?/.test(line)) {
    const buf = [];
    while (i < lines.length && /^>\s?/.test(lines[i])) {
      buf.push(lines[i].replace(/^>\s?/, ''));
      i++;
    }
    html += `<blockquote>${inline(buf.join(' '))}</blockquote>`;
    continue;
  }

  // table
  if (/^\s*\|/.test(line)) {
    const rows = [];
    while (i < lines.length && /^\s*\|/.test(lines[i])) {
      rows.push(lines[i].trim().replace(/^\|/, '').replace(/\|\s*$/, '').split('|').map((c) => c.trim()));
      i++;
    }
    html += flushTable(rows);
    continue;
  }

  // ordered list
  if (/^\s*\d+\.\s+/.test(line)) {
    html += '<ol>';
    while (i < lines.length && /^\s*\d+\.\s+/.test(lines[i])) {
      html += `<li>${inline(lines[i].replace(/^\s*\d+\.\s+/, ''))}</li>`;
      i++;
    }
    html += '</ol>';
    continue;
  }

  // unordered list
  if (/^\s*[-*]\s+/.test(line)) {
    html += '<ul>';
    while (i < lines.length && /^\s*[-*]\s+/.test(lines[i])) {
      html += `<li>${inline(lines[i].replace(/^\s*[-*]\s+/, ''))}</li>`;
      i++;
    }
    html += '</ul>';
    continue;
  }

  if (/^\*[^*].*\*$/.test(line.trim())) {
    html += `<p class="muted">${inline(line.trim().replace(/^\*/, '').replace(/\*$/, ''))}</p>`;
    i++; continue;
  }

  html += `<p>${inline(line)}</p>`;
  i++;
}

const doc = `<!doctype html><html><head><meta charset="utf-8">
<style>
  @page { size: A4; margin: 18mm 16mm; }
  * { box-sizing: border-box; }
  body { font-family: "Segoe UI", Arial, sans-serif; color: #1f2937; font-size: 11.5px; line-height: 1.55; }
  .brand { font-size: 10px; letter-spacing: .08em; text-transform: uppercase; color: #6366f1; font-weight: 700; margin-bottom: 6px; }
  h1 { font-size: 24px; color: #111827; border-bottom: 3px solid #4f46e5; padding-bottom: 8px; margin: 0 0 4px; }
  h2 { font-size: 16px; color: #4f46e5; margin: 22px 0 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
  h3 { font-size: 13px; color: #111827; margin: 16px 0 4px; }
  p { margin: 6px 0; }
  ul, ol { margin: 6px 0 6px 18px; padding: 0; }
  li { margin: 2px 0; }
  a { color: #4f46e5; text-decoration: none; }
  code { background: #eef2ff; color: #4338ca; padding: 1px 5px; border-radius: 4px; font-family: "Cascadia Code", Consolas, monospace; font-size: 10.5px; }
  strong { color: #111827; }
  hr { border: none; border-top: 1px solid #e5e7eb; margin: 16px 0; }
  blockquote { margin: 10px 0; padding: 8px 12px; background: #fffbeb; border-left: 3px solid #f59e0b; color: #92400e; border-radius: 0 4px 4px 0; }
  table { border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 10.5px; }
  th { background: #111827; color: #fff; text-align: left; padding: 7px 9px; font-weight: 600; }
  td { border: 1px solid #e5e7eb; padding: 6px 9px; vertical-align: top; }
  tbody tr:nth-child(even) { background: #f9fafb; }
  .muted { color: #6b7280; font-style: italic; font-size: 10px; }
  h1, h2, h3 { page-break-after: avoid; }
  table, ul, ol, blockquote { page-break-inside: avoid; }
</style></head><body>
<div class="brand">Niranjan Enterprises Digital Solutions — CRM</div>
${html}
</body></html>`;

writeFileSync(process.argv[3], doc, 'utf8');
console.log('HTML -> ' + process.argv[3]);
