/**
 * Publishes blog posts to WordPress via REST API
 *
 * Usage:
 *   node scripts/wordpress-publisher.js blog/articles/article.md [--draft]
 *
 * Requires .env:
 *   WP_API_URL=https://ryanoccg.com/blog/wp-json/wp/v2
 *   WP_USERNAME=admin
 *   WP_APP_PASSWORD=xxxx xxxx xxxx xxxx
 *   UNSPLASH_ACCESS_KEY=xxxxxxxx
 */

import fs from 'fs';
import path from 'path';
import https from 'https';
import { fileURLToPath } from 'url';
import { generateThumbnail } from './image-generator.js';
import { fetchImages } from './unsplash-fetcher.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Allow self-signed certificates for local DDEV (development only)
const httpsAgent = new https.Agent({
  rejectUnauthorized: false
});

// Load .env manually (avoid extra dependency)
function loadEnv() {
  const envPath = path.join(__dirname, '..', '.env');
  if (!fs.existsSync(envPath)) {
    console.error('❌ .env file not found. Copy .env.example and fill in your credentials.');
    process.exit(1);
  }
  const lines = fs.readFileSync(envPath, 'utf-8').split('\n');
  for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eqIndex = trimmed.indexOf('=');
    if (eqIndex === -1) continue;
    const key = trimmed.slice(0, eqIndex).trim();
    const value = trimmed.slice(eqIndex + 1).trim();
    if (!process.env[key]) process.env[key] = value;
  }
}

function parseFrontmatter(content) {
  const match = content.match(/^---\n([\s\S]*?)\n---\n([\s\S]*)$/);
  if (!match) return { meta: {}, body: content };

  const meta = {};
  for (const line of match[1].split('\n')) {
    const colonIndex = line.indexOf(':');
    if (colonIndex === -1) continue;
    const key = line.slice(0, colonIndex).trim();
    let value = line.slice(colonIndex + 1).trim();
    // Remove surrounding quotes
    if ((value.startsWith('"') && value.endsWith('"')) ||
        (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }
    meta[key] = value;
  }

  return { meta, body: match[2] };
}

function escapeHtml(str) {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function markdownToHtml(md) {
  let html = md;

  // Code blocks (must be before other replacements)
  // Wrap in .coding-div for styled code blocks
  html = html.replace(/```(\w+)?\n([\s\S]*?)```/g, (_, lang, code) => {
    return `<div class="coding-div"><pre><code class="language-${lang || 'text'}">${escapeHtml(code.trim())}</code></pre></div>`;
  });

  // Inline code
  html = html.replace(/`([^`]+)`/g, (_, code) => `<code>${escapeHtml(code)}</code>`);

  // Blockquotes (must be before headers/paragraphs)
  html = html.replace(/^> (.+)$/gm, '<blockquote><p>$1</p></blockquote>');

  // Headers
  html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
  html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
  html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');

  // Bold and italic
  html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');

  // Links
  html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');

  // Images
  html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" loading="lazy" />');

  // Unordered lists - merge consecutive <li> into single <ul>
  const lines = html.split('\n');
  const processed = [];
  let inList = false;

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (line.match(/^- (.+)$/)) {
      const content = line.replace(/^- (.+)$/, '$1');
      if (!inList) {
        processed.push('<ul>');
        inList = true;
      }
      processed.push(`<li>${content}</li>`);
    } else {
      if (inList) {
        processed.push('</ul>');
        inList = false;
      }
      processed.push(line);
    }
  }
  if (inList) processed.push('</ul>');
  html = processed.join('\n');

  // Tables (basic)
  const tableRegex = /\|(.+)\|\n\|[-| ]+\|\n((?:\|.+\|\n?)+)/g;
  html = html.replace(tableRegex, (_, header, body) => {
    const headers = header.split('|').filter(c => c.trim()).map(c => `<th>${c.trim()}</th>`);
    const rows = body.trim().split('\n').map(row => {
      const cells = row.split('|').filter(c => c.trim()).map(c => `<td>${c.trim()}</td>`);
      return `<tr>${cells.join('')}</tr>`;
    });
    return `<table><thead><tr>${headers.join('')}</tr></thead><tbody>${rows.join('')}</tbody></table>`;
  });

  // Paragraphs
  html = html.replace(/\n\n/g, '</p><p>');
  html = `<p>${html}</p>`;

  // Clean up empty paragraphs and malformed tags
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
  const { WP_USERNAME, WP_APP_PASSWORD } = process.env;
  if (!WP_USERNAME || !WP_APP_PASSWORD) {
    console.error('❌ WP_USERNAME and WP_APP_PASSWORD required in .env');
    process.exit(1);
  }
  return 'Basic ' + Buffer.from(`${WP_USERNAME}:${WP_APP_PASSWORD}`).toString('base64');
}

async function uploadMedia(filePath, altText) {
  const apiUrl = process.env.WP_API_URL;
  const filename = path.basename(filePath);
  const fileBuffer = fs.readFileSync(filePath);

  const ext = path.extname(filename).toLowerCase();
  const mimeTypes = { '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.webp': 'image/webp' };
  const contentType = mimeTypes[ext] || 'image/jpeg';

  const res = await fetch(`${apiUrl}/media`, {
    method: 'POST',
    headers: {
      Authorization: wpAuth(),
      'Content-Disposition': `attachment; filename="${filename}"`,
      'Content-Type': contentType,
    },
    body: fileBuffer,
    agent: httpsAgent,
  });

  if (!res.ok) {
    const err = await res.text();
    throw new Error(`Upload failed (${res.status}): ${err}`);
  }

  const media = await res.json();

  // Update alt text
  if (altText) {
    await fetch(`${apiUrl}/media/${media.id}`, {
      method: 'POST',
      headers: {
        Authorization: wpAuth(),
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ alt_text: altText }),
      agent: httpsAgent,
    });
  }

  console.log(`✅ Uploaded: ${filename} (ID: ${media.id})`);
  return media;
}

async function getBlogCategoryId() {
  const apiUrl = process.env.WP_API_URL;

  // Try to find existing "Blog" category
  const res = await fetch(`${apiUrl}/categories?search=Blog`, {
    headers: { Authorization: wpAuth() },
    agent: httpsAgent,
  });

  if (res.ok) {
    const categories = await res.json();
    const blogCat = categories.find(c => c.name === 'Blog');
    if (blogCat) return blogCat.id;
  }

  // Create "Blog" category if not exists
  const createRes = await fetch(`${apiUrl}/categories`, {
    method: 'POST',
    headers: {
      Authorization: wpAuth(),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ name: 'Blog', slug: 'blog' }),
    agent: httpsAgent,
  });

  if (createRes.ok) {
    const newCat = await createRes.json();
    console.log(`✅ Created "Blog" category (ID: ${newCat.id})`);
    return newCat.id;
  }

  return null; // Fallback to Uncategorized
}

async function createPost(title, content, slug, featuredImageId, status, meta) {
  const apiUrl = process.env.WP_API_URL;

  // Get Blog category ID
  const blogCategoryId = await getBlogCategoryId();

  const postData = {
    title,
    content,
    slug,
    status, // 'publish' or 'draft'
    featured_media: featuredImageId || 0,
    categories: blogCategoryId ? [blogCategoryId] : [],
    meta: {},
  };

  // Add Yoast SEO meta if available
  if (meta.focus_keyphrase || meta.description) {
    postData.yoast_head_json = {};
    if (meta.description) {
      postData.meta._yoast_wpseo_metadesc = meta.description;
    }
    if (meta.focus_keyphrase) {
      postData.meta._yoast_wpseo_focuskw = meta.focus_keyphrase;
    }
  }

  const res = await fetch(`${apiUrl}/posts`, {
    method: 'POST',
    headers: {
      Authorization: wpAuth(),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(postData),
    agent: httpsAgent,
  });

  if (!res.ok) {
    const err = await res.text();
    throw new Error(`Create post failed (${res.status}): ${err}`);
  }

  return await res.json();
}

// ─── Main ─────────────────────────────────────────────────────────────────

async function main() {
  loadEnv();

  const args = process.argv.slice(2);
  const mdPath = args.find(a => !a.startsWith('--'));
  const isDraft = args.includes('--draft');

  if (!mdPath) {
    console.log('Usage: node scripts/wordpress-publisher.js <markdown-file> [--draft]');
    process.exit(1);
  }

  const fullPath = path.resolve(mdPath);
  if (!fs.existsSync(fullPath)) {
    console.error(`❌ File not found: ${fullPath}`);
    process.exit(1);
  }

  const raw = fs.readFileSync(fullPath, 'utf-8');
  const { meta, body } = parseFrontmatter(raw);

  const title = meta.title || path.basename(fullPath, '.md').replace(/-/g, ' ');
  const slug = meta.slug || path.basename(fullPath, '.md');
  const status = isDraft ? 'draft' : 'publish';

  console.log(`\n📝 Publishing: "${title}"`);
  console.log(`   Status: ${status}`);
  console.log(`   Slug: ${slug}\n`);

  // Step 1: Generate thumbnail
  console.log('🎨 Step 1: Generating thumbnail...');
  const thumbnailPath = generateThumbnail(title, slug);

  // Step 2: Fetch Unsplash images
  let unsplashImages = [];
  if (meta.unsplash_query && process.env.UNSPLASH_ACCESS_KEY) {
    console.log('\n📸 Step 2: Fetching Unsplash images...');
    unsplashImages = await fetchImages(
      meta.unsplash_query,
      slug,
      process.env.UNSPLASH_ACCESS_KEY,
      3
    );
  } else {
    console.log('\n⏭️  Step 2: Skipping Unsplash (no query or API key)');
  }

  // Step 3: Upload images to WordPress
  console.log('\n📤 Step 3: Uploading images to WordPress...');

  let featuredImageId = null;

  // Upload thumbnail as featured image
  const thumbMedia = await uploadMedia(thumbnailPath, `${title} - Thumbnail`);
  featuredImageId = thumbMedia.id;

  // Upload Unsplash images
  const uploadedImages = [];
  for (const img of unsplashImages) {
    const media = await uploadMedia(img.path, img.alt);
    uploadedImages.push({
      ...img,
      wpId: media.id,
      wpUrl: media.source_url,
    });
  }

  // Step 4: Process content
  console.log('\n📄 Step 4: Processing content...');

  let processedBody = body;

  // Replace image placeholders with uploaded WordPress URLs
  // Supports: ![alt](UNSPLASH:0), ![alt](UNSPLASH:1), etc.
  processedBody = processedBody.replace(
    /!\[([^\]]*)\]\(UNSPLASH:(\d+)\)/g,
    (_, alt, index) => {
      const idx = parseInt(index, 10);
      if (uploadedImages[idx]) {
        return `![${alt || uploadedImages[idx].alt}](${uploadedImages[idx].wpUrl})`;
      }
      return '';
    }
  );

  const htmlContent = markdownToHtml(processedBody);

  // Step 5: Create post
  console.log('\n🚀 Step 5: Creating WordPress post...');
  const post = await createPost(title, htmlContent, slug, featuredImageId, status, meta);

  // Save response
  const publishedDir = path.join(__dirname, '..', 'blog', 'published');
  if (!fs.existsSync(publishedDir)) fs.mkdirSync(publishedDir, { recursive: true });

  fs.writeFileSync(
    path.join(publishedDir, `${slug}.json`),
    JSON.stringify({
      id: post.id,
      link: post.link,
      status: post.status,
      title: post.title.rendered,
      date: post.date,
      featuredImageId,
      images: uploadedImages.map(i => ({ filename: i.filename, wpUrl: i.wpUrl, credit: i.credit })),
    }, null, 2)
  );

  console.log(`\n✅ Done!`);
  console.log(`   📎 URL: ${post.link}`);
  console.log(`   📊 Status: ${post.status}`);
  console.log(`   🖼️  Images: ${uploadedImages.length + 1} uploaded\n`);
}

main().catch((err) => {
  console.error(`\n❌ Error: ${err.message}`);
  process.exit(1);
});
