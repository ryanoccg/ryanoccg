// vite.config.js
import { resolve } from 'path'
import { defineConfig } from 'vite'
import { copyFileSync } from 'fs'

export default defineConfig({
  root: resolve(__dirname, 'src'),
  base: '/',
  build: {
    outDir: '../dist',
    emptyOutDir: true,
    // Performance optimizations
    minify: 'esbuild',
    target: 'es2015',
    cssCodeSplit: true,
    // Chunk size optimization
    chunkSizeWarningLimit: 1000,
    rollupOptions: {
      input: resolve(__dirname, 'src/index.html'),
      output: {
        // Manual chunks for better caching
        manualChunks: {
          'vendor': ['bootstrap', '@popperjs/core'],
        },
        // Optimize chunk file names
        chunkFileNames: 'assets/js/[name]-[hash].js',
        entryFileNames: 'assets/js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          // Keep sitemap.xml and robots.txt in root without hash
          if (assetInfo.name === 'sitemap.xml' || assetInfo.name === 'robots.txt') {
            return '[name].[ext]'
          }
          return 'assets/[ext]/[name]-[hash].[ext]'
        }
      },
      plugins: [{
        name: 'copy-static-files',
        writeBundle() {
          // Copy sitemap.xml, robots.txt, and llm.txt to dist root
          try {
            copyFileSync(
              resolve(__dirname, 'src/sitemap.xml'),
              resolve(__dirname, 'dist/sitemap.xml')
            )
            copyFileSync(
              resolve(__dirname, 'src/robots.txt'),
              resolve(__dirname, 'dist/robots.txt')
            )
            copyFileSync(
              resolve(__dirname, 'src/llm.txt'),
              resolve(__dirname, 'dist/llm.txt')
            )
          } catch (err) {
            console.warn('Could not copy static files:', err.message)
          }
        }
      }]
    },
    // Enable compression
    reportCompressedSize: true,
    // Source map for production debugging (disable if not needed)
    sourcemap: false
  },
  server: {
    host: '127.0.0.1',
    port: 8080
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src')
    }
  },
  // CSS optimization
  css: {
    devSourcemap: false
  }
})