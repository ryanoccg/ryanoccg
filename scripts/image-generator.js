/**
 * Generates branded thumbnails for ryanoccg.com blog posts (V3)
 * Output: 1200x800 PNG with smart title shortening
 * Style: Dark theme with blue accents, tech-focused
 */

import { createCanvas } from 'canvas';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const IMAGES_DIR = path.join(__dirname, '..', 'blog', 'images');

const WIDTH = 1200;
const HEIGHT = 800;

// Shorten long titles intelligently (V3 feature)
function shortenTitle(title) {
  const patterns = [
    { regex: /\s*\(.*?Guide.*?\)/gi, replacement: '' },
    { regex: /\s*\(.*?Price.*?\)/gi, replacement: '' },
    { regex: /\s*\(Malaysia.*?\)/gi, replacement: '' },
    { regex: /\s*Complete\s+\d{4}\s+/gi, replacement: '' },
    { regex: /\s+in\s+Malaysia\b/gi, replacement: '' },
    { regex: /\s+for\s+Malaysian\s+/gi, replacement: ' for ' },
    { regex: /:\s+.*/g, replacement: '' },
  ];

  let short = title;
  for (const { regex, replacement } of patterns) {
    short = short.replace(regex, replacement);
  }

  short = short.trim();

  if (short.length > 60) {
    const firstSentence = short.split(/[:\-\?]/)[0].trim();
    if (firstSentence.length >= 20) {
      short = firstSentence;
    }
  }

  return short;
}

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

  // 1. Background gradient (diagonal)
  const bgGradient = ctx.createLinearGradient(0, 0, WIDTH, HEIGHT);
  bgGradient.addColorStop(0, '#0d0d0d');
  bgGradient.addColorStop(0.6, '#161616');
  bgGradient.addColorStop(1, '#0a2540');
  ctx.fillStyle = bgGradient;
  ctx.fillRect(0, 0, WIDTH, HEIGHT);

  // 2. Dot grid pattern (tech vibe)
  ctx.fillStyle = 'rgba(255, 255, 255, 0.05)';
  for (let x = 0; x < WIDTH; x += 20) {
    for (let y = 0; y < HEIGHT; y += 20) {
      ctx.fillRect(x, y, 2, 2);
    }
  }

  // 3. Radial glow (bottom-right accent)
  const glow = ctx.createRadialGradient(950, 650, 50, 950, 650, 350);
  glow.addColorStop(0, 'rgba(6, 154, 255, 0.15)');
  glow.addColorStop(1, 'rgba(6, 154, 255, 0)');
  ctx.fillStyle = glow;
  ctx.fillRect(0, 0, WIDTH, HEIGHT);

  // 4. Top label
  ctx.font = 'bold 20px "Courier New"';
  ctx.fillStyle = '#069AFF';
  ctx.fillText('// BLOG', 80, 100);

  // 5. Shorten title (V3)
  const shortTitle = shortenTitle(title);

  // 6. Calculate font size based on title length
  let fontSize = 96;
  if (shortTitle.length > 40) fontSize = 84;
  if (shortTitle.length > 50) fontSize = 72;

  ctx.font = `bold ${fontSize}px "Courier New"`;
  ctx.fillStyle = '#FFFFFF';

  // Add text shadow
  ctx.shadowColor = 'rgba(6, 154, 255, 0.3)';
  ctx.shadowBlur = 20;
  ctx.shadowOffsetX = 0;
  ctx.shadowOffsetY = 4;

  const maxWidth = 900;
  const lineHeight = fontSize * 1.2;
  let y = 200;

  // Word wrap
  const lines = wrapText(ctx, shortTitle, maxWidth);

  // Center vertically if only 1-2 lines
  if (lines.length <= 2) {
    y = 280;
  }

  // Draw each line
  lines.forEach((line, index) => {
    ctx.fillText(line, 80, y + (index * lineHeight));
  });

  // Reset shadow
  ctx.shadowColor = 'transparent';
  ctx.shadowBlur = 0;

  // 7. Accent line
  const accentY = y + (lines.length * lineHeight) + 40;
  ctx.fillStyle = '#FFB800';
  ctx.fillRect(80, accentY, 120, 4);

  // 8. Footer metadata
  ctx.font = '28px "Courier New"';
  ctx.fillStyle = '#888888';
  ctx.fillText('Ryano Chu', 80, 720);

  ctx.font = 'bold 28px "Courier New"';
  ctx.fillStyle = '#069AFF';
  ctx.fillText('ryanoccg.com', 80, 760);

  // 9. Background icon (code brackets)
  ctx.font = '200px "Courier New"';
  ctx.fillStyle = 'rgba(6, 154, 255, 0.08)';
  ctx.fillText('</>', 880, 500);

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
