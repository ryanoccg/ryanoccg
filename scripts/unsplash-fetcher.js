/**
 * Fetches images from Unsplash API for blog posts
 * Free: 50 requests/hour (demo), 5000 requests/hour (production)
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const IMAGES_DIR = path.join(__dirname, '..', 'blog', 'images');

const UNSPLASH_API = 'https://api.unsplash.com';

async function searchPhotos(query, accessKey, count = 3) {
  const url = new URL(`${UNSPLASH_API}/search/photos`);
  url.searchParams.set('query', query);
  url.searchParams.set('per_page', String(count));
  url.searchParams.set('orientation', 'landscape');
  url.searchParams.set('content_filter', 'high');

  const res = await fetch(url.toString(), {
    headers: { Authorization: `Client-ID ${accessKey}` },
  });

  if (!res.ok) {
    const err = await res.text();
    throw new Error(`Unsplash API error ${res.status}: ${err}`);
  }

  const data = await res.json();
  return data.results;
}

async function downloadImage(url, outputPath) {
  const res = await fetch(url);
  if (!res.ok) throw new Error(`Download failed: ${res.status}`);
  const buffer = await res.arrayBuffer();
  fs.writeFileSync(outputPath, Buffer.from(buffer));
}

export async function fetchImages(query, slug, accessKey, count = 3) {
  if (!accessKey) {
    throw new Error('UNSPLASH_ACCESS_KEY is required. Get one at https://unsplash.com/developers');
  }

  if (!fs.existsSync(IMAGES_DIR)) {
    fs.mkdirSync(IMAGES_DIR, { recursive: true });
  }

  console.log(`🔍 Searching Unsplash for: "${query}"`);
  const photos = await searchPhotos(query, accessKey, count);

  if (photos.length === 0) {
    console.log('⚠️  No photos found, try a different query');
    return [];
  }

  const downloaded = [];

  for (let i = 0; i < photos.length; i++) {
    const photo = photos[i];
    const suffix = i === 0 ? 'hero' : `0${i}`;
    const filename = `${slug}-${suffix}.jpg`;
    const outputPath = path.join(IMAGES_DIR, filename);

    // Use regular quality for hero, small for content images
    const url = i === 0 ? photo.urls.regular : photo.urls.small;

    await downloadImage(url, outputPath);

    downloaded.push({
      path: outputPath,
      filename,
      alt: photo.alt_description || photo.description || query,
      credit: `Photo by ${photo.user.name} on Unsplash`,
      unsplashUrl: photo.links.html,
      width: photo.width,
      height: photo.height,
    });

    // Trigger download event (Unsplash API guideline)
    if (photo.links.download_location) {
      fetch(`${photo.links.download_location}?client_id=${accessKey}`).catch(() => {});
    }

    console.log(`✅ Downloaded: ${filename} (${photo.user.name})`);
  }

  return downloaded;
}

// CLI usage: node scripts/unsplash-fetcher.js "web development" "article-slug" YOUR_KEY
const args = process.argv.slice(2);
if (args.length >= 3) {
  fetchImages(args[0], args[1], args[2]).catch(console.error);
}
