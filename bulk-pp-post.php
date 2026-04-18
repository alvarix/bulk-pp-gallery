<?php
/**
 * Plugin Name: Bulk PP Post
 * Description: Bulk-generate gallery posts from media library images with auto-parsed metadata. Includes a filterable gallery block with lightbox and infinite scroll.
 * Version: 2.0.0
 * Author: Alvar
 * Text Domain: ppgal2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =========================================================================
// CONFIGURATION
// =========================================================================

define( 'PPGAL2_CPT',       'ppgal2' );
define( 'PPGAL2_TAX_TYPE',  'ppgal2_type' );
define( 'PPGAL2_TAX_BREED', 'ppgal2_breed' );
define( 'PPGAL2_TAX_TAG',   'ppgal2_tag' );

define( 'PPGAL2_DIR', plugin_dir_path( __FILE__ ) );
define( 'PPGAL2_URL', plugin_dir_url( __FILE__ ) );

// =========================================================================
// 1. Custom Post Type
// =========================================================================
add_action( 'init', 'ppgal2_register_cpt' );

/**
 * Register the ppgal2 custom post type.
 */
function ppgal2_register_cpt() {
    register_post_type( PPGAL2_CPT, array(
        'labels' => array(
            'name'               => 'PP Gallery Items',
            'singular_name'      => 'PP Gallery Item',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New PP Gallery Item',
            'edit_item'          => 'Edit PP Gallery Item',
            'new_item'           => 'New PP Gallery Item',
            'view_item'          => 'View PP Gallery Item',
            'search_items'       => 'Search PP Gallery Items',
            'not_found'          => 'No PP gallery items found',
            'not_found_in_trash' => 'No PP gallery items found in trash',
            'menu_name'          => 'PP Gallery Items',
        ),
        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,
        'menu_icon'    => 'dashicons-format-gallery',
        'supports'     => array( 'title', 'editor', 'thumbnail' ),
        'rewrite'      => array( 'slug' => 'gallery' ),
    ) );
}

// =========================================================================
// 2. Taxonomies
// =========================================================================
add_action( 'init', 'ppgal2_register_taxonomies' );

/**
 * Register type, breed, and tag taxonomies for ppgal2.
 */
function ppgal2_register_taxonomies() {
    // Type (street, studio, etc.)
    register_taxonomy( PPGAL2_TAX_TYPE, PPGAL2_CPT, array(
        'labels' => array(
            'name'          => 'Types',
            'singular_name' => 'Type',
            'search_items'  => 'Search Types',
            'all_items'     => 'All Types',
            'edit_item'     => 'Edit Type',
            'update_item'   => 'Update Type',
            'add_new_item'  => 'Add New Type',
            'new_item_name' => 'New Type Name',
            'menu_name'     => 'Types',
        ),
        'public'       => true,
        'hierarchical' => true,
        'show_in_rest' => true,
        'rewrite'      => array( 'slug' => 'gallery-type' ),
    ) );

    // Breed
    register_taxonomy( PPGAL2_TAX_BREED, PPGAL2_CPT, array(
        'labels' => array(
            'name'          => 'Breeds',
            'singular_name' => 'Breed',
            'search_items'  => 'Search Breeds',
            'all_items'     => 'All Breeds',
            'edit_item'     => 'Edit Breed',
            'update_item'   => 'Update Breed',
            'add_new_item'  => 'Add New Breed',
            'new_item_name' => 'New Breed Name',
            'menu_name'     => 'Breeds',
        ),
        'public'       => true,
        'hierarchical' => false,
        'show_in_rest' => true,
        'rewrite'      => array( 'slug' => 'gallery-breed' ),
    ) );

    // Tags (WIP, alternate, adoption, etc.)
    register_taxonomy( PPGAL2_TAX_TAG, PPGAL2_CPT, array(
        'labels' => array(
            'name'          => 'Gallery Tags',
            'singular_name' => 'Gallery Tag',
            'search_items'  => 'Search Gallery Tags',
            'all_items'     => 'All Gallery Tags',
            'edit_item'     => 'Edit Gallery Tag',
            'update_item'   => 'Update Gallery Tag',
            'add_new_item'  => 'Add New Gallery Tag',
            'new_item_name' => 'New Gallery Tag Name',
            'menu_name'     => 'Tags',
        ),
        'public'       => true,
        'hierarchical' => false,
        'show_in_rest' => true,
        'rewrite'      => array( 'slug' => 'gallery-tag' ),
    ) );
}

// =========================================================================
// 2b. Breed sort meta sync
// =========================================================================
add_action( 'set_object_terms', 'ppgal2_sync_breed_sort_meta', 10, 4 );

/**
 * Cache breed term name as post meta for sortable queries.
 * WordPress doesn't support ORDER BY taxonomy term natively,
 * so we mirror the first breed name to _ppgal2_breed_sort.
 *
 * @param int    $object_id  Post ID.
 * @param array  $terms      Term slugs or IDs being set.
 * @param array  $tt_ids     Term taxonomy IDs.
 * @param string $taxonomy   Taxonomy slug.
 */
function ppgal2_sync_breed_sort_meta( $object_id, $terms, $tt_ids, $taxonomy ) {
    if ( $taxonomy !== PPGAL2_TAX_BREED ) {
        return;
    }
    $breed_terms = get_the_terms( $object_id, PPGAL2_TAX_BREED );
    if ( ! empty( $breed_terms ) && ! is_wp_error( $breed_terms ) ) {
        update_post_meta( $object_id, '_ppgal2_breed_sort', $breed_terms[0]->name );
    } else {
        delete_post_meta( $object_id, '_ppgal2_breed_sort' );
    }
}

// =========================================================================
// 3. Post meta: alternate thumbnail
// =========================================================================
add_action( 'init', 'ppgal2_register_meta' );
add_action( 'add_meta_boxes', 'ppgal2_add_meta_boxes' );
add_action( 'save_post_' . PPGAL2_CPT, 'ppgal2_save_meta', 10, 2 );

/**
 * Register post meta for REST API visibility.
 */
function ppgal2_register_meta() {
    register_post_meta( PPGAL2_CPT, 'ppgal2_thumb_alt', array(
        'type'         => 'integer',
        'single'       => true,
        'show_in_rest' => true,
        'description'  => 'Attachment ID of alternate thumbnail',
    ) );
    register_post_meta( PPGAL2_CPT, 'ppgal2_no_alt_thumb', array(
        'type'         => 'boolean',
        'single'       => true,
        'show_in_rest' => true,
        'description'  => 'Disable alternate thumbnail for this post',
    ) );
}

/**
 * Add the alternate thumbnail meta box to the post editor.
 */
function ppgal2_add_meta_boxes() {
    add_meta_box(
        'ppgal2_alt_thumb',
        'Alternate Thumbnail',
        'ppgal2_alt_thumb_metabox',
        PPGAL2_CPT,
        'side',
        'default'
    );
}

/**
 * Render the alternate thumbnail meta box.
 *
 * @param WP_Post $post Current post object.
 */
function ppgal2_alt_thumb_metabox( $post ) {
    $thumb_id    = (int) get_post_meta( $post->ID, 'ppgal2_thumb_alt', true );
    $no_alt      = (bool) get_post_meta( $post->ID, 'ppgal2_no_alt_thumb', true );
    $preview_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '';

    wp_nonce_field( 'ppgal2_alt_thumb', 'ppgal2_alt_thumb_nonce' );
    ?>
    <div id="ppgal2-alt-thumb-wrap">
        <div id="ppgal2-alt-thumb-preview">
            <?php if ( $preview_url ) : ?>
                <img src="<?php echo esc_url( $preview_url ); ?>" style="max-width:100%;height:auto;" />
            <?php endif; ?>
        </div>
        <input type="hidden" name="ppgal2_thumb_alt" id="ppgal2_thumb_alt"
               value="<?php echo esc_attr( $thumb_id ); ?>" />
        <p>
            <button type="button" class="button" id="ppgal2-select-thumb">
                <?php echo $thumb_id ? 'Change Image' : 'Select Image'; ?>
            </button>
            <button type="button" class="button" id="ppgal2-remove-thumb"
                    style="<?php echo $thumb_id ? '' : 'display:none;'; ?>">
                Remove
            </button>
        </p>
        <p>
            <label>
                <input type="checkbox" name="ppgal2_no_alt_thumb" value="1"
                       <?php checked( $no_alt ); ?> />
                Don't use alternate thumbnail
            </label>
        </p>
    </div>
    <script>
    jQuery(function($){
        var frame;
        $('#ppgal2-select-thumb').on('click', function(e){
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = wp.media({ title:'Select Alternate Thumbnail', multiple:false, library:{type:'image'} });
            frame.on('select', function(){
                var attachment = frame.state().get('selection').first().toJSON();
                $('#ppgal2_thumb_alt').val(attachment.id);
                var url = attachment.sizes && attachment.sizes.thumbnail
                    ? attachment.sizes.thumbnail.url : attachment.url;
                $('#ppgal2-alt-thumb-preview').html('<img src="'+url+'" style="max-width:100%;height:auto;" />');
                $('#ppgal2-select-thumb').text('Change Image');
                $('#ppgal2-remove-thumb').show();
            });
            frame.open();
        });
        $('#ppgal2-remove-thumb').on('click', function(e){
            e.preventDefault();
            $('#ppgal2_thumb_alt').val('');
            $('#ppgal2-alt-thumb-preview').empty();
            $('#ppgal2-select-thumb').text('Select Image');
            $(this).hide();
        });
    });
    </script>
    <?php
}

/**
 * Save alternate thumbnail meta on post save.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function ppgal2_save_meta( $post_id, $post ) {
    if ( ! isset( $_POST['ppgal2_alt_thumb_nonce'] )
         || ! wp_verify_nonce( $_POST['ppgal2_alt_thumb_nonce'], 'ppgal2_alt_thumb' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    $thumb_id = isset( $_POST['ppgal2_thumb_alt'] ) ? absint( $_POST['ppgal2_thumb_alt'] ) : 0;
    update_post_meta( $post_id, 'ppgal2_thumb_alt', $thumb_id );

    $no_alt = ! empty( $_POST['ppgal2_no_alt_thumb'] );
    update_post_meta( $post_id, 'ppgal2_no_alt_thumb', $no_alt );
}

// =========================================================================
// 4. Media Library bulk action
// =========================================================================
add_filter( 'bulk_actions-upload',        'ppgal2_register_bulk_action' );
add_filter( 'handle_bulk_actions-upload', 'ppgal2_handle_bulk_action', 10, 3 );
add_action( 'admin_notices',              'ppgal2_bulk_action_notice' );
add_action( 'admin_footer-upload.php',    'ppgal2_bulk_action_modal' );
add_action( 'admin_enqueue_scripts',      'ppgal2_admin_scripts' );

/**
 * Register the bulk action in Media Library.
 *
 * @param array $actions Existing bulk actions.
 * @return array
 */
function ppgal2_register_bulk_action( $actions ) {
    $actions['ppgal2_create_posts'] = 'Create PP Gallery Posts';
    return $actions;
}

/**
 * Enqueue admin scripts on the Media Library page.
 *
 * @param string $hook Current admin page hook.
 */
function ppgal2_admin_scripts( $hook ) {
    if ( 'upload.php' !== $hook ) {
        return;
    }
    wp_enqueue_style(  'ppgal2-admin', PPGAL2_URL . 'assets/admin.css', array(), '2.0.0' );
    wp_enqueue_script( 'ppgal2-admin', PPGAL2_URL . 'assets/admin.js',  array( 'jquery' ), '2.0.0', true );

    wp_localize_script( 'ppgal2-admin', 'ppgal2Data', array(
        'nonce'    => wp_create_nonce( 'ppgal2_bulk_create' ),
        'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
        'cptLabel' => 'PP Gallery Items',
    ) );
}

add_action( 'wp_ajax_ppgal2_bulk_create', 'ppgal2_ajax_bulk_create' );

/**
 * Parse a media filename into structured gallery post data.
 *
 * Uses "__" (double underscore) as segment delimiter because WordPress
 * sanitize_file_name() collapses "--" into "-" on upload.
 *
 * Supports 1-3 segments:
 *   title
 *   type__title
 *   type__title__breed.tag1.tag2
 *
 * Also strips WordPress suffixes like "-scaled" and "-rotated"
 * before parsing.
 *
 * @param string $filename Filename without extension.
 * @return array { title: string, type: string|null, breed: string|null, tags: string[] }
 */
function ppgal2_parse_filename( $filename ) {
    // Strip WP-appended suffixes (-scaled, -rotated, dimension suffixes like -1024x768)
    $filename = preg_replace( '/-(scaled|rotated|\d+x\d+)$/', '', $filename );

    $segments = explode( '__', $filename );
    $result   = array( 'title' => '', 'type' => null, 'breed' => null, 'tags' => array() );

    $count = count( $segments );

    if ( $count === 1 ) {
        $result['title'] = ucwords( str_replace( array( '-', '_' ), ' ', $segments[0] ) );
    } elseif ( $count === 2 ) {
        $result['type']  = ucwords( str_replace( array( '-', '_' ), ' ', $segments[0] ) );
        $result['title'] = ucwords( str_replace( array( '-', '_' ), ' ', $segments[1] ) );
    } else {
        $result['type']  = ucwords( str_replace( array( '-', '_' ), ' ', $segments[0] ) );
        $result['title'] = ucwords( str_replace( array( '-', '_' ), ' ', $segments[1] ) );

        $breed_tags = explode( '.', $segments[2] );
        if ( ! empty( $breed_tags[0] ) ) {
            $result['breed'] = ucwords( str_replace( array( '-', '_' ), ' ', $breed_tags[0] ) );
        }
        // Clean tag values: trim whitespace, strip hyphens/underscores, drop empties
        $raw_tags = array_slice( $breed_tags, 1 );
        $result['tags'] = array_values( array_filter( array_map( function( $t ) {
            return trim( str_replace( array( '-', '_' ), ' ', $t ) );
        }, $raw_tags ) ) );
    }

    return $result;
}

/**
 * AJAX handler: bulk-create gallery posts from selected media attachments.
 * Parses each filename for type, breed, and tags.
 */
function ppgal2_ajax_bulk_create() {
    check_ajax_referer( 'ppgal2_bulk_create', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    $attachment_ids = isset( $_POST['attachment_ids'] )
        ? array_map( 'intval', (array) $_POST['attachment_ids'] )
        : array();

    if ( empty( $attachment_ids ) ) {
        wp_send_json_error( 'No images selected.' );
    }

    $created = 0;

    foreach ( $attachment_ids as $attachment_id ) {
        $attachment = get_post( $attachment_id );
        if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
            continue;
        }

        $filename = pathinfo( get_attached_file( $attachment_id ), PATHINFO_FILENAME );
        $parsed   = ppgal2_parse_filename( $filename );

        $post_id = wp_insert_post( array(
            'post_type'   => PPGAL2_CPT,
            'post_title'  => $parsed['title'],
            'post_status' => 'publish',
        ) );

        if ( ! $post_id || is_wp_error( $post_id ) ) {
            continue;
        }

        set_post_thumbnail( $post_id, $attachment_id );
        update_post_meta( $post_id, 'ppgal2_source_filename', $filename );

        if ( $parsed['type'] ) {
            wp_set_object_terms( $post_id, $parsed['type'], PPGAL2_TAX_TYPE );
        }
        if ( $parsed['breed'] ) {
            wp_set_object_terms( $post_id, $parsed['breed'], PPGAL2_TAX_BREED );
        }
        if ( ! empty( $parsed['tags'] ) ) {
            wp_set_object_terms( $post_id, $parsed['tags'], PPGAL2_TAX_TAG );
        }

        $created++;
    }

    wp_send_json_success( array(
        'created' => $created,
        'message' => sprintf( '%d PP gallery post(s) created.', $created ),
    ) );
}

/**
 * No-op fallback for the bulk action redirect.
 *
 * @param string $redirect_url Redirect URL.
 * @param string $action       Action name.
 * @param array  $post_ids     Selected post IDs.
 * @return string
 */
function ppgal2_handle_bulk_action( $redirect_url, $action, $post_ids ) {
    return $redirect_url;
}

/**
 * Display admin notice after bulk post creation.
 */
function ppgal2_bulk_action_notice() {
    if ( ! empty( $_REQUEST['ppgal2_created'] ) ) {
        printf(
            '<div class="notice notice-success is-dismissible"><p>%d PP gallery post(s) created.</p></div>',
            intval( $_REQUEST['ppgal2_created'] )
        );
    }
}

/**
 * Render the bulk action confirmation modal in Media Library footer.
 */
function ppgal2_bulk_action_modal() {
    ?>
    <div id="ppgal2-modal" class="ppgal2-modal" style="display:none;">
        <div class="ppgal2-modal-overlay"></div>
        <div class="ppgal2-modal-content">
            <h2>Create PP Gallery Posts</h2>
            <p class="ppgal2-modal-count"></p>
            <p class="description">Filenames will be parsed automatically:<br>
                <code>type__title__breed.tag1.tag2.ext</code></p>
            <div class="ppgal2-modal-actions">
                <button type="button" class="button button-primary" id="ppgal2-modal-confirm">Create Posts</button>
                <button type="button" class="button" id="ppgal2-modal-cancel">Cancel</button>
            </div>
            <div id="ppgal2-modal-progress" style="display:none;">
                <span class="spinner is-active"></span> Creating posts&hellip;
            </div>
        </div>
    </div>
    <?php
}

// =========================================================================
// 5. AJAX: lightbox post data + load more
// =========================================================================
add_action( 'wp_ajax_ppgal2_get_post_data',        'ppgal2_ajax_get_post_data' );
add_action( 'wp_ajax_nopriv_ppgal2_get_post_data', 'ppgal2_ajax_get_post_data' );

/**
 * AJAX handler: return post data for the lightbox overlay.
 */
function ppgal2_ajax_get_post_data() {
    $post_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
    $post    = get_post( $post_id );

    if ( ! $post || PPGAL2_CPT !== $post->post_type ) {
        wp_send_json_error( 'Post not found.' );
    }

    $image_url = get_the_post_thumbnail_url( $post_id, 'large' );
    if ( ! $image_url ) {
        $image_url = '';
    }

    wp_send_json_success( array(
        'title'       => get_the_title( $post_id ),
        'description' => apply_filters( 'the_content', $post->post_content ),
        'image_url'   => $image_url,
        'date'        => get_the_date( '', $post_id ),
    ) );
}

add_action( 'wp_ajax_ppgal2_load_more',        'ppgal2_ajax_load_more' );
add_action( 'wp_ajax_nopriv_ppgal2_load_more', 'ppgal2_ajax_load_more' );

/**
 * AJAX handler: return paginated gallery items as HTML.
 * Accepts page, per_page, type, breed, tag query params.
 */
function ppgal2_ajax_load_more() {
    $page     = isset( $_GET['page'] )     ? max( 1, intval( $_GET['page'] ) ) : 1;
    $per_page = isset( $_GET['per_page'] ) ? max( 1, intval( $_GET['per_page'] ) ) : 20;

    $tax_query = array();

    if ( ! empty( $_GET['type'] ) ) {
        $tax_query[] = array(
            'taxonomy' => PPGAL2_TAX_TYPE,
            'field'    => 'slug',
            'terms'    => sanitize_text_field( $_GET['type'] ),
        );
    }
    if ( ! empty( $_GET['breed'] ) ) {
        $tax_query[] = array(
            'taxonomy' => PPGAL2_TAX_BREED,
            'field'    => 'slug',
            'terms'    => sanitize_text_field( $_GET['breed'] ),
        );
    }
    if ( ! empty( $_GET['tag'] ) ) {
        $tax_query[] = array(
            'taxonomy' => PPGAL2_TAX_TAG,
            'field'    => 'slug',
            'terms'    => sanitize_text_field( $_GET['tag'] ),
        );
    }

    $args = array(
        'post_type'      => PPGAL2_CPT,
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'post_status'    => 'publish',
    );
    if ( ! empty( $tax_query ) ) {
        $tax_query['relation'] = 'AND';
        $args['tax_query']     = $tax_query;
    }

    // Sort handling
    $sort = isset( $_GET['sort'] ) ? sanitize_text_field( $_GET['sort'] ) : 'date-desc';
    $sort_parts = explode( '-', $sort, 2 );
    $sort_field = $sort_parts[0];
    $sort_dir   = isset( $sort_parts[1] ) ? strtoupper( $sort_parts[1] ) : 'DESC';

    if ( $sort_field === 'breed' ) {
        // Sort by breed taxonomy term name
        $args['orderby']  = 'meta_value';
        $args['meta_key'] = '_ppgal2_breed_sort';
        $args['order']    = $sort_dir;
    } else {
        $args['orderby'] = $sort_field === 'title' ? 'title' : 'date';
        $args['order']   = $sort_dir;
    }

    $show_alt    = ! empty( $_GET['show_alt_thumbs'] );
    $show_titles = ! empty( $_GET['show_titles'] );

    $query = new WP_Query( $args );
    $html  = '';

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $html .= ppgal2_render_gallery_item( get_the_ID(), $show_alt, $show_titles );
        }
        wp_reset_postdata();
    }

    wp_send_json_success( array(
        'html'      => $html,
        'has_more'  => $page < $query->max_num_pages,
        'max_pages' => $query->max_num_pages,
    ) );
}

/**
 * Render a single gallery list item.
 *
 * @param int  $post_id     Post ID.
 * @param bool $show_alt    Whether block-level alt thumbs are enabled.
 * @param bool $show_titles Whether to show titles below thumbnails.
 * @return string HTML for one <li>.
 */
function ppgal2_render_gallery_item( $post_id, $show_alt = true, $show_titles = false ) {
    $no_alt = (bool) get_post_meta( $post_id, 'ppgal2_no_alt_thumb', true );
    $alt_id = (int) get_post_meta( $post_id, 'ppgal2_thumb_alt', true );
    $title  = get_the_title( $post_id );
    $link   = get_the_permalink( $post_id );

    if ( $show_alt && ! $no_alt && $alt_id ) {
        $thumb = wp_get_attachment_image_url( $alt_id, 'thumbnail' );
    } else {
        $thumb = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
    }

    if ( ! $thumb ) {
        $thumb = '';
    }

    ob_start();
    ?>
    <li class="pp-gallery__item">
        <figure>
            <a class="ppgal2-thumb" title="<?php echo esc_attr( $title ); ?>"
               data-post-id="<?php echo esc_attr( $post_id ); ?>"
               href="<?php echo esc_url( $link ); ?>">
                <img src="<?php echo esc_url( $thumb ); ?>" width="400"
                     alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
            </a>
            <?php if ( $show_titles ) : ?>
                <figcaption class="ppgal2-thumb-title"><?php echo esc_html( $title ); ?></figcaption>
            <?php endif; ?>
            <?php edit_post_link( 'Edit', '', '', $post_id ); ?>
        </figure>
    </li>
    <?php
    return ob_get_clean();
}

// =========================================================================
// 6. Block registration
// =========================================================================
add_action( 'init', 'ppgal2_register_block' );

/**
 * Register the PP Gallery Plus block.
 *
 * Pure PHP registration -- no block.json dependency.
 * All metadata, scripts, styles, and render callback defined here.
 */
function ppgal2_register_block() {
    wp_register_script(
        'ppgal2-editor',
        PPGAL2_URL . 'blocks/pp-gallery-plus/editor.js',
        array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
        '2.0.0',
        true
    );

    wp_register_script(
        'ppgal2-gallery-view',
        PPGAL2_URL . 'blocks/pp-gallery-plus/gallery.js',
        array(),
        '2.0.0',
        true
    );

    wp_register_style(
        'ppgal2-gallery-style',
        PPGAL2_URL . 'blocks/pp-gallery-plus/gallery.css',
        array(),
        '2.0.0'
    );

    register_block_type( 'ppgal2/gallery', array(
        'api_version'     => 3,
        'title'           => 'PP Gallery Plus',
        'description'     => 'Filterable image gallery with lightbox and infinite scroll.',
        'category'        => 'media',
        'icon'            => 'format-gallery',
        'keywords'        => array( 'gallery', 'portfolio', 'grid' ),
        'editor_script'   => 'ppgal2-editor',
        'view_script'     => 'ppgal2-gallery-view',
        'style'           => 'ppgal2-gallery-style',
        'render_callback' => 'ppgal2_render_block',
        'attributes'      => array(
            'postsPerPage'  => array( 'type' => 'number',  'default' => 20 ),
            'showAltThumbs' => array( 'type' => 'boolean', 'default' => true ),
            'showTitles'    => array( 'type' => 'boolean', 'default' => false ),
        ),
        'supports'        => array(
            'align'    => array( 'wide', 'full' ),
            'multiple' => false,
        ),
    ) );
}

/**
 * Server-side render callback for the gallery block.
 *
 * @param array  $attributes Block attributes.
 * @param string $content    Block content.
 * @return string Rendered HTML.
 */
function ppgal2_render_block( $attributes, $content ) {
    ob_start();
    include PPGAL2_DIR . 'blocks/pp-gallery-plus/gallery.php';
    return ob_get_clean();
}

// =========================================================================
// 7. Frontend scripts
// =========================================================================
add_action( 'wp_enqueue_scripts', 'ppgal2_frontend_scripts' );

/**
 * Localize AJAX URL for the gallery frontend script.
 */
function ppgal2_frontend_scripts() {
    wp_localize_script( 'ppgal2-gallery-view', 'ppgal2Front', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    ) );
}

// =========================================================================
// 8. RSS feed inclusion
// =========================================================================
add_action( 'pre_get_posts', 'ppgal2_add_to_rss' );

/**
 * Include ppgal2 posts in the main RSS feed.
 *
 * @param WP_Query $query The current query object.
 */
function ppgal2_add_to_rss( $query ) {
    if ( ! get_option( 'ppgal2_include_in_rss', true ) ) {
        return;
    }
    if ( $query->is_feed() && $query->is_main_query() ) {
        $existing = $query->get( 'post_type' );
        if ( empty( $existing ) ) {
            $existing = array( 'post' );
        }
        $query->set( 'post_type', array_merge( (array) $existing, array( PPGAL2_CPT ) ) );
    }
}

// =========================================================================
// 9. Plugin action links
// =========================================================================
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ppgal2_action_links' );

/**
 * Add Settings link to plugin row.
 *
 * @param string[] $links Existing links.
 * @return string[]
 */
function ppgal2_action_links( $links ) {
    $link = sprintf(
        '<a href="%s">%s</a>',
        esc_url( admin_url( 'edit.php?post_type=' . PPGAL2_CPT . '&page=ppgal2-settings' ) ),
        'Settings &amp; Help'
    );
    array_unshift( $links, $link );
    return $links;
}

// =========================================================================
// 10. Settings
// =========================================================================
add_action( 'admin_init', 'ppgal2_register_settings' );

/**
 * Register plugin options with the Settings API.
 */
function ppgal2_register_settings() {
    register_setting( 'ppgal2_options', 'ppgal2_posts_per_page', array(
        'type'              => 'integer',
        'default'           => 20,
        'sanitize_callback' => 'absint',
    ) );

    register_setting( 'ppgal2_options', 'ppgal2_include_in_rss', array(
        'type'              => 'boolean',
        'default'           => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ) );
}

/**
 * Get the configured posts-per-page value.
 *
 * @return int
 */
function ppgal2_get_posts_per_page() {
    return (int) get_option( 'ppgal2_posts_per_page', 20 );
}

// =========================================================================
// 11. Admin page
// =========================================================================
add_action( 'admin_menu', 'ppgal2_register_admin_page' );

/**
 * Register settings submenu page.
 */
function ppgal2_register_admin_page() {
    add_submenu_page(
        'edit.php?post_type=' . PPGAL2_CPT,
        'Bulk PP Post — Settings & Help',
        'Settings & Help',
        'manage_options',
        'ppgal2-settings',
        'ppgal2_render_admin_page'
    );
}

/**
 * Render the plugin admin/help page.
 */
function ppgal2_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Handle form save
    if ( isset( $_POST['ppgal2_save_settings'] ) ) {
        check_admin_referer( 'ppgal2_settings' );
        update_option( 'ppgal2_posts_per_page', absint( $_POST['ppgal2_posts_per_page'] ) );
        update_option( 'ppgal2_include_in_rss', isset( $_POST['ppgal2_include_in_rss'] ) );
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    $per_page = ppgal2_get_posts_per_page();
    ?>
    <div class="wrap">
        <h1>Bulk PP Post</h1>

        <nav class="nav-tab-wrapper" style="margin-bottom:1.5em;">
            <a href="#ppgal2-tab-settings" class="nav-tab nav-tab-active" data-tab="ppgal2-tab-settings">Settings</a>
            <a href="#ppgal2-tab-help" class="nav-tab" data-tab="ppgal2-tab-help">Help</a>
        </nav>

        <!-- Settings tab -->
        <div id="ppgal2-tab-settings">
            <form method="post">
                <?php wp_nonce_field( 'ppgal2_settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ppgal2_posts_per_page">Posts per page</label></th>
                        <td>
                            <input type="number" name="ppgal2_posts_per_page" id="ppgal2_posts_per_page"
                                   value="<?php echo esc_attr( $per_page ); ?>" min="1" max="200" step="1"
                                   class="small-text" />
                            <p class="description">Number of gallery items loaded per page (initial load and each infinite scroll batch). Block-level setting overrides this if set.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Include in RSS feed</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ppgal2_include_in_rss" value="1"
                                       <?php checked( get_option( 'ppgal2_include_in_rss', true ) ); ?> />
                                Include gallery items in the site's main RSS feed
                            </label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="ppgal2_save_settings" class="button button-primary" value="Save Settings" />
                </p>
            </form>
        </div>

        <!-- Help tab -->
        <div id="ppgal2-tab-help" style="display:none;">
            <h2>Filename Convention</h2>
            <p>Name your image files before uploading. Use <code>__</code> (double underscore) as the delimiter between segments. WordPress strips double hyphens on upload, so <code>--</code> will not work.</p>
            <table class="widefat fixed" style="max-width:600px;">
                <thead><tr><th>Filename</th><th>Result</th></tr></thead>
                <tbody>
                    <tr>
                        <td><code>fluffy-boy.jpg</code></td>
                        <td>Title: "Fluffy Boy"</td>
                    </tr>
                    <tr>
                        <td><code>street__fluffy-boy.jpg</code></td>
                        <td>Type: Street, Title: "Fluffy Boy"</td>
                    </tr>
                    <tr>
                        <td><code>studio__fluffy-boy__yorkie.wip.adoption.jpg</code></td>
                        <td>Type: Studio, Title: "Fluffy Boy", Breed: Yorkie, Tags: wip, adoption</td>
                    </tr>
                </tbody>
            </table>

            <h2>How to Use</h2>
            <ol>
                <li>Upload images to the <strong>Media Library</strong> (name them using the convention above).</li>
                <li>Select images, choose <strong>Create PP Gallery Posts</strong> from Bulk Actions (works in both list and grid view).</li>
                <li>Add the <strong>PP Gallery Plus</strong> block to any page.</li>
                <li>Visitors can filter by Type, Breed, or Tag and scroll for more.</li>
            </ol>
        </div>
    </div>

    <script>
    (function () {
        var tabs = document.querySelectorAll('.nav-tab-wrapper .nav-tab');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                tabs.forEach(function (t) { t.classList.remove('nav-tab-active'); });
                document.getElementById('ppgal2-tab-settings').style.display = 'none';
                document.getElementById('ppgal2-tab-help').style.display = 'none';
                tab.classList.add('nav-tab-active');
                document.getElementById(tab.dataset.tab).style.display = '';
            });
        });
    })();
    </script>
    <?php
}
