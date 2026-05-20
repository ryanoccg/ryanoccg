/**
 * Updates an existing WordPress post by slug.
 * Re-uses the markdown parser + html converter from wordpress-publisher.js.
 *
 * Usage:
 *   node scripts/wordpress-updater.js blogs/blog/articles/article.md
 *
 * Behaviour:
 *   - Finds existing post by slug
 *   - Updates title, content, excerpt (description), Yoast focus_keyphrase + meta description
 *   - Does NOT regenerate thumbnail or fetch new images (keeps existing media)
 *   - Fails loudly if no post with that slug exists
 */

import fs from 'fs';
import path from 'path';
import https from 'https';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const httpsAgent = new https.Agent({ rejectUnauthorized: false });

function loadEnv() {
  const envPath = path.join(__dirname, '..', '.env');
  if (!fs.existsSync(envPath)) {
    console.error('.env file not found');
    process.exit(1);
  }
  for (const line of fs.readFileSync(envPath, 'utf-8').split('\n')) {
    const t = line.trim();
    if (!t || t.startsWith('#')) continue;
    const eq = t.indexOf('=');
    if (eq === -1) continue;
    const k = t.slice(0, eq).trim();
    const v = t.slice(eq + 1).trim();
    if (!process.env[k]) process.env[k] = v;
  }
}

function parseFrontmatter(content) {
  const m = content.match(/^---\n([\s\S]*?)\n---\n([\s\S]*)$/);
  if (!m) return { meta: {}, body: content };
  const meta = {};
  for (const line of m[1].split('\n')) {
    const i = line.indexOf(':');
    if (i === -1) continue;
    const k = line.slice(0, i).trim();
    let v = line.slice(i + 1).trim();
    if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
      v = v.slice(1, -1);
    }
    meta[k] = v;
  }
  return { meta, body: m[2] };
}

function escapeHtml(s) {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function markdownToHtml(md) {
  let html = md;
  html = html.replace(/```(\w+)?\n([\s\S]*?)```/g, (_, lang, code) =>
    `<div class="coding-div"><pre><code class="language-${lang || 'text'}">${escapeHtml(code.trim())}</code></pre></div>`
  );
  html = html.replace(/`([^`]+)`/g, (_, c) => `<code>${escapeHtml(c)}</code>`);
  html = html.replace(/^> (.+)$/gm, '<blockquote><p>$1</p></blockquote>');
  html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
  html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
  html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
  html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
  html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" loading="lazy" />');
  html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');

  const lines = html.split('\n');
  const out = [];
  let inList = false;
  for (const line of lines) {
    if (line.match(/^- (.+)$/)) {
      const content = line.replace(/^- (.+)$/, '$1');
      if (!inList) { out.push('<ul>'); inList = true; }
      out.push(`<li>${content}</li>`);
    } else {
      if (inList) { out.push('</ul>'); inList = false; }
      out.push(line);
    }
  }
  if (inList) out.push('</ul>');
  html = out.join('\n');

  const tableRegex = /\|(.+)\|\n\|[-| ]+\|\n((?:\|.+\|\n?)+)/g;
  html = html.replace(tableRegex, (_, header, body) => {
    const heads = header.split('|').filter(c => c.trim()).map(c => `<th>${c.trim()}</th>`);
    const rows = body.trim().split('\n').map(row => {
      const cells = row.split('|').filter(c => c.trim()).map(c => `<td>${c.trim()}</td>`);
      return `<tr>${cells.join('')}</tr>`;
    });
    return `<table><thead><tr>${heads.join('')}</tr></thead><tbody>${rows.join('')}</tbody></table>`;
  });

  html = html.replace(/\n\n/g, '</p><p>');
  html = `<p>${html}</p>`;
  html = html.replace(/<p>\s*<\/p>/g, '');
  html = html.replace(/<p>\s*(<h[1-6]>)/g, '$1');
  html = html.replace(/(<\/h[1-6]>)\s*<\/p>/g, '$1');
  html = html.replace(/<p>\s*(<table>)/g, '$1');
  html = html.replace(/(<\/table>)\s*<\/p>/g, '$1');
  html = html.replace(/<p>\s*(<ul>)/g, '$1');
  html = html.replace(/(<\/ul>)\s*<\/p>/g, '$1');
  html = html.replace(/<p>\s*(<pre>)/g, '$1');
  html = html.replace(/(<\/pre>)\s*<\/p>/g, '$1');
  html = html.replace(/<p>\s*(<blockquote>)/g, '$1');
  html = html.replace(/(<\/blockquote>)\s*<\/p>/g, '$1');
  return html;
}

function wpAuth() {
  return 'Basic ' + Buffer.from(`${process.env.WP_USERNAME}:${process.env.WP_APP_PASSWORD}`).toString('base64');
}

async function findPostBySlug(slug) {
  const url = `${process.env.WP_API_URL}/posts?slug=${encodeURIComponent(slug)}&status=publish,draft,future,private`;
  const res = await fetch(url, { headers: { Authorization: wpAuth() }, agent: httpsAgent });
  if (!res.ok) throw new Error(`Lookup failed ${res.status}: ${await res.text()}`);
  const posts = await res.json();
  return posts[0] || null;
}

async function updatePost(id, payload) {
  const res = await fetch(`${process.env.WP_API_URL}/posts/${id}`, {
    method: 'POST',
    headers: { Authorization: wpAuth(), 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
    agent: httpsAgent,
  });
  if (!res.ok) throw new Error(`Update failed ${res.status}: ${await res.text()}`);
  return res.json();
}

async function main() {
  loadEnv();
  const mdPath = process.argv[2];
  if (!mdPath) { console.log('Usage: node scripts/wordpress-updater.js <markdown-file>'); process.exit(1); }

  const raw = fs.readFileSync(path.resolve(mdPath), 'utf-8');
  const { meta, body } = parseFrontmatter(raw);
  const slug = meta.slug || path.basename(mdPath, '.md');
  const title = meta.title;

  console.log(`\nLooking up post: slug="${slug}"`);
  const existing = await findPostBySlug(slug);
  if (!existing) {
    console.error(`No post found with slug "${slug}". Use wordpress-publisher.js to create.`);
    process.exit(1);
  }
  console.log(`Found post ID ${existing.id} — current title: "${existing.title.rendered}"`);

  const html = markdownToHtml(body);

  const payload = {
    title,
    content: html,
    excerpt: meta.description || '',
    meta: {},
  };
  if (meta.description) payload.meta._yoast_wpseo_metadesc = meta.description;
  if (meta.focus_keyphrase) payload.meta._yoast_wpseo_focuskw = meta.focus_keyphrase;

  console.log(`Updating with new title: "${title}"`);
  const updated = await updatePost(existing.id, payload);

  console.log(`\nUpdated successfully.`);
  console.log(`   URL:    ${updated.link}`);
  console.log(`   Title:  ${updated.title.rendered}`);
  console.log(`   Status: ${updated.status}`);

  if (updated.status === 'publish' && updated.link) {
    const inspectUrl = `https://search.google.com/search-console/inspect?resource_id=${encodeURIComponent('sc-domain:ryanoccg.com')}&id=${encodeURIComponent(updated.link)}`;
    console.log(`   Re-crawl request (click then press "Request Indexing"):`);
    console.log(`   ${inspectUrl}`);
  }
}

main().catch(err => { console.error(`\nError: ${err.message}`); process.exit(1); });
