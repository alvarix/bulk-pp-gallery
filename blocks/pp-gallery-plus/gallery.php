<?php
/**
 * PP Gallery Plus block render template.
 *
 * @param array  $attributes Block attributes.
 * @param string $content    Block content (empty for dynamic blocks).
 */

// Block attribute overrides the global setting; global setting overrides the default.
$global_per_page = function_exists( 'ppgal2_get_posts_per_page' ) ? ppgal2_get_posts_per_page() : 20;
$per_page        = isset( $attributes['postsPerPage'] ) && $attributes['postsPerPage'] !== 20
    ? (int) $attributes['postsPerPage']
    : $global_per_page;
$show_alt        = isset( $attributes['showAltThumbs'] ) ? (bool) $attributes['showAltThumbs'] : true;
$class_name = 'pp-gallery full-width-wp-gallery';
if ( ! empty( $attributes['className'] ) ) {
    $class_name .= ' ' . $attributes['className'];
}

// Gather taxonomy terms for filter dropdowns.
$types  = get_terms( array( 'taxonomy' => 'ppgal2_type',  'hide_empty' => true ) );
$breeds = get_terms( array( 'taxonomy' => 'ppgal2_breed', 'hide_empty' => true ) );
$tags   = get_terms( array( 'taxonomy' => 'ppgal2_tag',   'hide_empty' => true ) );
$has_filters = ( ! is_wp_error( $types ) && ! empty( $types ) )
            || ( ! is_wp_error( $breeds ) && ! empty( $breeds ) )
            || ( ! is_wp_error( $tags ) && ! empty( $tags ) );

// Build initial query args with default type/sort from settings.
$default_type = get_option( 'ppgal2_default_type', '' );
$default_sort = get_option( 'ppgal2_default_sort', 'date-desc' );

$initial_args = array(
    'post_type'      => 'ppgal2',
    'posts_per_page' => $per_page,
    'paged'          => 1,
    'post_status'    => 'publish',
);

if ( $default_type ) {
    $initial_args['tax_query'] = array( array(
        'taxonomy' => 'ppgal2_type',
        'field'    => 'slug',
        'terms'    => $default_type,
    ) );
}

if ( $default_sort && $default_sort !== 'date-desc' ) {
    $sort_parts = explode( '-', $default_sort, 2 );
    $sort_field = $sort_parts[0];
    $sort_dir   = strtoupper( $sort_parts[1] ?? 'DESC' );

    if ( $sort_field === 'breed' ) {
        $initial_args['orderby']  = array( 'meta_value' => $sort_dir, 'ID' => $sort_dir );
        $initial_args['meta_key'] = '_ppgal2_breed_sort';
    } else {
        $field = $sort_field === 'title' ? 'title' : 'date';
        $initial_args['orderby'] = array( $field => $sort_dir, 'ID' => $sort_dir );
    }
}

// Filterable via 'ppgal2_initial_query_args' hook (e.g. theme reads URL params).
$query = new WP_Query( apply_filters( 'ppgal2_initial_query_args', $initial_args ) );
?>

<div class="ppgal2-block"
     data-per-page="<?php echo esc_attr( $per_page ); ?>"
     data-show-alt="<?php echo $show_alt ? '1' : '0'; ?>"
     data-max-pages="<?php echo esc_attr( $query->max_num_pages ); ?>"
     data-default-type="<?php echo esc_attr( get_option( 'ppgal2_default_type', '' ) ); ?>"
     data-default-sort="<?php echo esc_attr( get_option( 'ppgal2_default_sort', 'date-desc' ) ); ?>"
     data-prefiltered="<?php echo ! empty( $query->query_vars['tax_query'] ) ? '1' : '0'; ?>">

    <!-- Filter bar -->
    <?php if ( $has_filters ) : ?>
    <div class="ppgal2-filters">
        <?php if ( ! is_wp_error( $types ) && ! empty( $types ) ) : ?>
            <select class="ppgal2-filter" data-taxonomy="type" aria-label="Filter by type">
                <option value="">All Types</option>
                <?php foreach ( $types as $term ) : ?>
                    <option value="<?php echo esc_attr( $term->slug ); ?>">
                        <?php echo esc_html( $term->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <?php if ( ! is_wp_error( $breeds ) && ! empty( $breeds ) ) : ?>
            <select class="ppgal2-filter" data-taxonomy="breed" aria-label="Filter by breed">
                <option value="">All Breeds</option>
                <?php foreach ( $breeds as $term ) : ?>
                    <option value="<?php echo esc_attr( $term->slug ); ?>">
                        <?php echo esc_html( $term->name ); ?> (<?php echo $term->count; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <button type="button" class="ppgal2-filter-reset" style="display:none;" aria-label="Reset filters">
            Reset filters
        </button>

        <button type="button" class="ppgal2-title-toggle" aria-label="Toggle view">
            List View
        </button>

        <select class="ppgal2-sort" aria-label="Sort by">
            <option value="date-desc">Newest first</option>
            <option value="date-asc">Oldest first</option>
            <option value="title-asc">Title A-Z</option>
            <option value="title-desc">Title Z-A</option>
            <option value="breed-asc">Breed A-Z</option>
            <option value="breed-desc">Breed Z-A</option>
        </select>
    </div>
    <?php endif; ?>

    <!-- Gallery grid -->
    <ul class="<?php echo esc_attr( $class_name ); ?>">
        <?php
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                echo ppgal2_render_gallery_item( get_the_ID(), $show_alt );
            }
            wp_reset_postdata();
        }
        ?>
    </ul>

    <!-- Infinite scroll sentinel -->
    <?php if ( $query->max_num_pages > 1 ) : ?>
        <div class="ppgal2-sentinel" aria-hidden="true">
            <div class="ppgal2-loading">Loading&hellip;</div>
        </div>
    <?php endif; ?>

    <!-- Lightbox -->
    <div class="ppgal2-lightbox" style="display:none;" role="dialog" aria-modal="true">
        <div class="lightbox-content">
            <button class="ppgal2-close" aria-label="Close">&times;</button>
            <button class="ppgal2-prev" aria-label="Previous">&#10094;</button>
            <button class="ppgal2-next" aria-label="Next">&#10095;</button>
            <div class="ppgal2-lightbox-spinner" style="display:none;">
                <svg width="34" height="34" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <style>.sp{animation:bounce 1.05s infinite}.sp2{animation-delay:.1s}.sp3{animation-delay:.2s}@keyframes bounce{0%,57%{transform:translateY(0)}28%{transform:translateY(-6px)}}</style>
                    <circle class="sp" cx="4" cy="12" r="3"/>
                    <circle class="sp sp2" cx="12" cy="12" r="3"/>
                    <circle class="sp sp3" cx="20" cy="12" r="3"/>
                </svg>
            </div>
            <div class="lightbox-inner-content">
                <h2 class="ppgal2-lb-title"></h2>
                <img class="ppgal2-lb-image" src="" alt="" />
                <div class="ppgal2-lb-description"></div>
            </div>
        </div>
    </div>
</div>
