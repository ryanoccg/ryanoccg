/**
 * Generates branded thumbnails for ryanoccg.com blog posts
 * Output: 1200x630 PNG (OG image standard)
 * Style: #161616 → #069AFF gradient, monospace font
 */

import { createCanvas, registerFont } from 'canvas';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const IMAGES_DIR = path.join(__dirname, '..', 'blog', 'images');

const WIDTH = 1200;
const HEIGHT = 800; // Changed from 630 to match blog card aspect ratio (1.5:1)

function wrapText(ctx, text, maxWidth) {
  const words = text.split(' ');
  const lines = [];
  let line = '';

  for (const word of words) {
    const testLine = line ? `${line} ${word}` : word;
    const { width } = ctx.measureText(testLine);
    if (width > maxWidth && line) {
      lines.push(line);
      line = word;
    } else {
      line = testLine;
    }
  }
  if (line) lines.push(line);
  return lines;
}

export function generateThumbnail(title, slug) {
  const canvas = createCanvas(WIDTH, HEIGHT);
  const ctx = canvas.getContext('2d');

  // Background gradient
  const gradient = ctx.createLinearGradient(0, 0, WIDTH, HEIGHT);
  gradient.addColorStop(0, '#0d0d0d');
  gradient.addColorStop(0.6, '#161616');
  gradient.addColorStop(1, '#0a2540');
  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, WIDTH, HEIGHT);

  // Accent line (top)
  const accentGradient = ctx.createLinearGradient(0, 0, WIDTH * 0.6, 0);
  accentGradient.addColorStop(0, '#069AFF');
  accentGradient.addColorStop(1, 'transparent');
  ctx.fillStyle = accentGradient;
  ctx.fillRect(0, 0, WIDTH * 0.6, 4);

  // Decorative dots pattern (subtle)
  ctx.fillStyle = 'rgba(6, 154, 255, 0.04)';
  for (let x = 900; x < WIDTH; x += 30) {
    for (let y = 50; y < HEIGHT - 50; y += 30) {
      ctx.beginPath();
      ctx.arc(x, y, 2, 0, Math.PI * 2);
      ctx.fill();
    }
  }

  // Category label
  ctx.font = 'bold 18px monospace';
  ctx.fillStyle = '#069AFF';
  ctx.fillText('// BLOG', 80, 100);

  // Title text
  ctx.font = 'bold 52px monospace';
  ctx.fillStyle = '#ffffff';
  const lines = wrapText(ctx, title, WIDTH - 200);
  const lineHeight = 68;
  const startY = 180;

  lines.forEach((line, i) => {
    ctx.fillText(line, 80, startY + i * lineHeight);
  });

  // Divider line
  const dividerY = startY + lines.length * lineHeight + 30;
  ctx.fillStyle = '#069AFF';
  ctx.fillRect(80, dividerY, 60, 3);

  // Author + site
  ctx.font = '22px monospace';
  ctx.fillStyle = '#888888';
  ctx.fillText('Ryano Chu', 80, dividerY + 45);

  ctx.font = '20px monospace';
  ctx.fillStyle = '#069AFF';
  ctx.fillText('ryanoccg.com', 80, dividerY + 80);

  // Bottom accent line
  const bottomGradient = ctx.createLinearGradient(WIDTH * 0.4, 0, WIDTH, 0);
  bottomGradient.addColorStop(0, 'transparent');
  bottomGradient.addColorStop(1, '#069AFF');
  ctx.fillStyle = bottomGradient;
  ctx.fillRect(WIDTH * 0.4, HEIGHT - 4, WIDTH * 0.6, 4);

  // Save
  if (!fs.existsSync(IMAGES_DIR)) {
    fs.mkdirSync(IMAGES_DIR, { recursive: true });
  }

  const outputPath = path.join(IMAGES_DIR, `${slug}-thumbnail.png`);
  const buffer = canvas.toBuffer('image/png');
  fs.writeFileSync(outputPath, buffer);

  console.log(`✅ Thumbnail generated: ${outputPath}`);
  return outputPath;
}

// CLI usage: node scripts/image-generator.js "Article Title" "article-slug"
const args = process.argv.slice(2);
if (args.length >= 2) {
  generateThumbnail(args[0], args[1]);
} else if (args.length === 1) {
  const slug = args[0].toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  generateThumbnail(args[0], slug);
}
