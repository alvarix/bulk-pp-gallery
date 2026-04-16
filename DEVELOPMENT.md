# Development Notes

Problems encountered building this plugin and how they were resolved.

## 1. Filename delimiter destroyed by WordPress

**Problem**: The original filename convention used `--` (double hyphen) to separate segments: `type--title--breed.tag1.tag2.jpg`. WordPress `sanitize_file_name()` runs a regex `'/[\r\n\t -]+/'` that collapses consecutive hyphens into a single `-`. By the time the plugin reads the filename from disk, `studio--big-sunset` has become `studio-big-sunset` and the delimiter is indistinguishable from word separators.

**Solution**: Switched to `__` (double underscore) as the delimiter. Underscores are not in the sanitize regex character class, so they survive upload intact. The parser also strips WordPress-appended suffixes (`-scaled`, `-rotated`, `-1024x768`) before parsing, since WordPress adds these during image processing.

**Test**: `._-/test-1-parser.php` -- 11 cases covering all segment counts, WP suffixes, and edge cases like trailing underscores and empty segments.

## 2. Block not appearing in the editor inserter

**Problem**: The block was registered via `register_block_type($directory)` which reads `block.json`. Despite having correct metadata (title, icon, keywords), the block never appeared in the Gutenberg inserter.

**Root cause**: Multiple interacting issues:
- `block.json` had a `"render": "file:./gallery.php"` field AND the PHP args passed `render_callback` -- these conflicted.
- The `"editorScript": "file:./editor.js"` resolution in `block.json` depends on an `.asset.php` file with the correct naming convention, and the auto-generated script handle must match what WordPress expects.
- When `register_block_type($dir)` reads block.json, it may auto-register the block client-side. If the editor JS then calls `registerBlockType()` with the same name, it throws "Block already registered" silently, preventing the block from loading.

**Solution**: Abandoned `block.json` entirely. All block metadata, script handles, and the render callback are now declared in a single `register_block_type('ppgal2/gallery', $args)` call in PHP. The `editor.js` script:
- Includes a guard: `if (blocks.getBlockType('ppgal2/gallery')) return;` to avoid double-registration.
- Declares full metadata (title, icon, category, keywords) redundantly so the inserter works even if client/server sync fails.
- Falls back to a static placeholder if `wp.serverSideRender` is unavailable.

Console logging (`[ppgal2] editor.js loaded`, `[ppgal2] block registered successfully`) was added to make future debugging possible from the browser dev tools.

## 3. Lightbox AJAX action name collision

**Problem**: Clicking "next" in the lightbox showed `alert("No data found for this post.")` before the correct image loaded.

**Root cause**: The AJAX action was registered as `get_post_data` -- a generic name. The site's theme (which originally provided the gallery) also registered a `get_post_data` action with its own handler AND its own jQuery click handler on `.post_thumbnail` elements. When a user clicked next:
1. Our vanilla JS handler fired `ppgal2_get_post_data` (correct)
2. The theme's jQuery handler ALSO fired on the same `.post_thumbnail` click, calling `get_post_data` -- our PHP handler received it, checked `post_type !== 'ppgal2'`, and returned an error. The theme JS showed the alert.

**Solution**: Three changes:
- Renamed the AJAX action from `get_post_data` to `ppgal2_get_post_data` (namespaced).
- Added `e.stopPropagation()` in the click handler to prevent old theme JS from also firing.
- Changed lightbox element selectors from `id="lightbox"` / `id="lightbox-image"` to classes (`ppgal2-lightbox`, `ppgal2-lb-image`, etc.) to avoid DOM ID conflicts with the theme's lightbox markup.
- Replaced `alert()` with `console.warn()` for error cases -- alerts are disruptive and block the UI.

## 4. CPT label ambiguity

**Problem**: The admin sidebar showed "Gallery Items" which could be confused with other gallery plugins or the theme's own post types.

**Solution**: Prefixed all labels with "PP" -- "PP Gallery Items", "PP Gallery Item", etc. The bulk action reads "Create PP Gallery Posts" to distinguish from any other bulk actions.

## 5. Grid view bulk action

**Problem**: WordPress Media Library bulk actions only work in list view. Grid view uses a completely different Backbone.js-based UI with no native bulk action support.

**Solution**: `admin.js` polls for the grid view toolbar (`.media-toolbar-secondary`) and injects a "Create PP Gallery Posts" button. When clicked, it reads selected attachment IDs from `wp.media.frame.state().get('selection')` (the Backbone selection model) with a fallback to checking `.attachment.selected` DOM elements.
