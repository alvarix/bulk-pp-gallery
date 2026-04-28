# Bulk PP Post

A WordPress plugin for bulk-generating gallery posts from media library images, with automatic metadata parsing from filenames. Includes a filterable gallery block with lightbox and infinite scroll.

## What it does

**Part 1 -- Bulk image to post generator**

Select images in the Media Library, choose the bulk action, and the plugin creates a `ppgal2` post for each image. Post title, type, breed, and tags are parsed automatically from the filename convention:

```
title.ext                                    -> Title only
type__title.ext                              -> Type + Title
type__title__breed.tag1.tag2.ext             -> Type + Title + Breed + Tags
type__title__breed1_breed2.tag1.tag2.ext     -> Type + Title + Multiple Breeds + Tags
```

Uses `__` (double underscore) as the segment delimiter because WordPress `sanitize_file_name()` collapses `--` into `-` on upload. Use `_` (single underscore) inside the breed segment to assign multiple breeds; use `-` for spaces within a breed name. WordPress also strips `+` on upload — it cannot be used as a delimiter.

WordPress-appended suffixes like `-scaled` and `-rotated` are stripped before parsing.

Examples:

| Filename | Title | Type | Breed(s) | Tags |
|---|---|---|---|---|
| `fluffy-boy.jpg` | Fluffy Boy | -- | -- | -- |
| `street__big-sunset.jpg` | Big Sunset | Street | -- | -- |
| `studio__portrait__yorkie.wip.adoption.jpg` | Portrait | Studio | Yorkie | wip, adoption |
| `studio__portrait__yorkie_labrador.wip.jpg` | Portrait | Studio | Yorkie, Labrador | wip |

**Part 2 -- PP Gallery Plus block**

A Gutenberg block that renders a responsive image grid with:

- **Filter dropdowns** for Type and Breed taxonomies (with counts) and a reset button
- **Column selector** to adjust grid layout on the fly
- **Infinite scroll** loading via AJAX
- **Lightbox** with keyboard navigation, touch swipe, and adjacent post prefetching for instant prev/next
- **Alternate thumbnail** support (per-post override with disable toggle)

## Configuration

Constants are defined at the top of `bulk-pp-post.php`:

```php
define( 'PPGAL2_CPT',       'ppgal2' );        // Post type slug
define( 'PPGAL2_TAX_TYPE',  'ppgal2_type' );   // Type taxonomy
define( 'PPGAL2_TAX_BREED', 'ppgal2_breed' );  // Breed taxonomy
define( 'PPGAL2_TAX_TAG',   'ppgal2_tag' );    // Tag taxonomy
```

## Installation

1. Copy the `bulk-pp-gallery/` folder into `wp-content/plugins/`
2. Activate **Bulk PP Post** from the WordPress admin
3. Go to **Settings > Permalinks** and click Save to flush rewrite rules

## Settings

The **Settings & Help** page under PP Gallery Items has two tabs:

- **Settings** -- posts per page (overridable per-block), RSS feed inclusion toggle, default type filter, and default sort order.
- **Help** -- filename convention reference and usage instructions.

## Taxonomies

- **Types** -- hierarchical (e.g. Street, Studio)
- **Breeds** -- flat tags (e.g. Yorkie, Labrador)
- **Gallery Tags** -- flat tags (e.g. WIP, Alternate, Adoption)

All are auto-created during bulk import when parsed from filenames. You can also manage them manually under **PP Gallery Items** in the admin sidebar.

## Bulk action workflow

1. Name your image files using the convention above
2. Upload to **Media > Library**
3. Select images (works in both list view and grid view)
4. Choose **Create PP Gallery Posts** from the Bulk Actions dropdown (list view) or click the toolbar button (grid view)
5. Confirm in the modal -- posts are created with parsed metadata

## PP Gallery Plus block

Add the **PP Gallery Plus** block to any page.

**Block settings** (in the editor sidebar Inspector panel):

- **Posts per page** -- items per infinite scroll batch (defaults to the global setting)
- **Show alternate thumbnails** -- use alt thumbs when available (default: on)

**Frontend features:**

- Filter dropdowns narrow results by Type or Breed (with post counts)
- Sort by custom order, date, title, or breed
- Titles/Thumbs toggle switches between image grid and flat title list with small thumbnails
- "Reset filters" button appears when any filter is active
- Column selector adjusts grid from 1 to N columns
- Scroll down to load more items automatically
- Click any image to open a full-screen lightbox (arrow keys, swipe, Escape to close)
- **Custom order** via `menu_order` field, editable from the admin list view
- URL parameters to pre-filter the gallery: `?type=street&breed=cat&sort=order-desc`
- `ppgal2_initial_query_args` filter hook allows themes to modify the initial query (e.g. server-side filtering from URL params to avoid AJAX flash)

## Alternate thumbnail

Each gallery post has an optional alternate thumbnail, managed via a meta box in the post editor sidebar. Three-tier logic:

1. **Block level** -- `showAltThumbs` attribute enables/disables globally
2. **Per post** -- upload an alternate image via the meta box
3. **Per post override** -- "Don't use alternate thumbnail" checkbox disables it for that post

## RSS

Gallery items are included in the site's main RSS feed by default. This can be toggled off in **Settings & Help > Settings**.

## File structure

```
bulk-pp-gallery/
  bulk-pp-post.php                  Main plugin file
  assets/
    admin.js                        Media Library bulk action modal
    admin.css                       Modal styles
  blocks/
    pp-gallery-plus/
      editor.js                     Block editor registration + sidebar controls
      gallery.php                   Server-side render template
      gallery.js                    Columns, filters, infinite scroll, lightbox
      gallery.css                   Grid, filter bar, lightbox styles
```

## No build step required

All scripts use plain JS against WordPress globals. No webpack, no npm, no compilation. Drop the folder in and activate.

## Requirements

- WordPress 6.4+
- PHP 8.0+
- Block editor enabled
