# Ryano Blog Theme v2.0

A modern, tech-focused WordPress blog theme designed to match Ryano Chu's portfolio website at [ryanoccg.com](https://ryanoccg.com).

## 🎨 Design Features

### Visual Style
- **Dark Theme**: Professional dark color scheme (#161616 background, #069AFF primary accent)
- **Tech Aesthetic**: Code-inspired design with `//` prefixes, monospace fonts, and developer-friendly styling
- **Responsive Grid**: Auto-filling grid layout that adapts from desktop to mobile
- **Glassmorphism**: Subtle backdrop filters and gradient overlays
- **Smooth Animations**: Card hover effects, scroll-based header changes, and page transitions
- **Portfolio Integration**: Uses same visual assets and branding as main portfolio site

### Typography & Readability
- System font stack for performance (Inter as fallback)
- Optimized line-height (1.85) for long-form reading
- Code blocks with syntax-ready styling
- Heading hierarchy with visual decorations
- Reading time indicator on all posts

## ⚡ Performance & SEO

- **Lazy Loading**: Native lazy loading for all images
- **Resource Hints**: DNS prefetch and preconnect for faster font loading
- **Open Graph**: Complete OG meta tags for social sharing
- **Schema.org**: Structured data for blog posts and author
- **Clean Head**: Removed unnecessary WordPress bloat
- **Security**: XML-RPC disabled, limited post revisions

## 🚀 Quick Start

1. **Copy Theme Files**
   ```bash
   cp -r ryano /path/to/wordpress/wp-content/themes/
   ```

2. **Activate Theme**
   - WordPress Admin → Appearance → Themes
   - Find "Ryano Blog" and click "Activate"

3. **Set Up Navigation** (Optional)
   - Appearance → Menus
   - Create menu with: Blog Home, Portfolio, Contact
   - Assign to "Primary Menu" location

4. **Recommended Settings**
   - Settings → Reading → Posts per page: 9
   - Settings → Permalinks → Post name (/%postname%/)

## 📝 Content Guidelines

### Featured Images
- **Recommended Size**: 1200x630px (Open Graph standard)
- **Format**: JPG/PNG (WebP supported)
- **Fallback**: If no image, displays code symbol `</>`

### Writing Posts
- Use H2, H3, H4 for hierarchy
- Add categories for organization
- Write custom excerpts (25 words ideal)
- Add alt text to all images
- Use code blocks for snippets

### Categories
Suggested categories for developer blog:
- Web Development
- Laravel / PHP
- JavaScript / Vue.js
- Tutorials
- Projects
- Tips & Tricks

## 🎨 Customization

### Color Palette
Edit `style.css` CSS variables (lines 17-27):
```css
:root {
    --color-bg: #161616;
    --color-primary: #069AFF;
    --color-accent: #FFB800;
    /* ... */
}
```

### Logo
Replace `assets/logo.svg` with your logo (SVG recommended, 200x50px)

### Background
Replace `assets/hero-bg.png` to change hero section background

### Typography
To use different fonts:
1. Edit Google Fonts link in `functions.php` (line 11)
2. Update font-family in `style.css` body selector

## 📂 File Structure

```
ryano/
├── assets/
│   ├── hero-bg.png      # Hero background (from portfolio)
│   └── logo.svg         # Site logo (from portfolio)
├── style.css            # Main stylesheet (2.0)
├── functions.php        # Theme functions & features
├── header.php           # Header + sticky scroll effect
├── footer.php           # Footer with links
├── index.php            # Blog listing (grid layout)
├── single.php           # Single post template
├── screenshot.png       # Theme preview (WP admin)
└── README.md           # This file
```

## 🎯 Key Features Breakdown

### Blog Index (Homepage)
- Hero section with gradient title and `//` prefix
- Responsive card grid (360px min-width)
- Category badges with hover animations
- Reading time indicators
- Smart pagination with arrows
- Fade-in animations on load

### Single Post Page
- Clean, centered layout (900px max-width)
- Featured image with rounded corners
- Code-style heading decorators (`//`)
- Syntax-ready code blocks
- Blockquotes with gradient backgrounds
- Previous/Next post navigation
- Back to blog button

### Styling Components
- **Cards**: Hover lift effect, border glow, gradient backgrounds
- **Code Blocks**: Dark background, monospace font, inline/block styles
- **Blockquotes**: Left border accent, gradient fade, quote decoration
- **Tables**: Bordered cells, colored headers
- **Links**: Underline on hover, color transition

## 🌐 Browser Support

- Chrome/Edge (latest 2 versions)
- Firefox (latest 2 versions)
- Safari 14+
- iOS Safari 14+
- Chrome Mobile (latest)

## 🛠️ Development

### Local Environment
Works with:
- DDEV
- Local by Flywheel
- XAMPP/MAMP
- Docker-based WordPress

### Version History
- **v2.0.0** (2026-04-29): Complete redesign with tech-focused aesthetic, improved performance
- **v1.0.0** (2026-04-29): Initial release

## 📧 Support & Contact

- **Portfolio**: [ryanoccg.com](https://ryanoccg.com)
- **Contact**: [ryanoccg.com/#contact](https://ryanoccg.com/#contact)
- **Issues**: Use WordPress theme's help section or reach out directly

## 🏆 Credits

- **Design & Development**: Ryano Chu Chee Guan
- **Inspiration**: Modern developer blogs, tech portfolios
- **Built for**: Developers, tech writers, programming enthusiasts

## 📄 License

GNU General Public License v2 or later  
http://www.gnu.org/licenses/gpl-2.0.html

---

Made with ❤️ by [Ryano Chu](https://ryanoccg.com) | Full-Stack Developer
