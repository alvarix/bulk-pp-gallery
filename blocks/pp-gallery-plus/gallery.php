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

$class_name = 'pp-gallery';
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

// Initial query.
$query = new WP_Query( array(
    'post_type'      => 'ppgal2',
    'posts_per_page' => $per_page,
    'paged'          => 1,
    'post_status'    => 'publish',
) );
?>

<div class="ppgal2-block"
     data-per-page="<?php echo esc_attr( $per_page ); ?>"
     data-show-alt="<?php echo $show_alt ? '1' : '0'; ?>"
     data-max-pages="<?php echo esc_attr( $query->max_num_pages ); ?>">

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
                        <?php echo esc_html( $term->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <?php if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) : ?>
            <select class="ppgal2-filter" data-taxonomy="tag" aria-label="Filter by tag">
                <option value="">All Tags</option>
                <?php foreach ( $tags as $term ) : ?>
                    <option value="<?php echo esc_attr( $term->slug ); ?>">
                        <?php echo esc_html( $term->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <button type="button" class="ppgal2-filter-reset" style="display:none;" aria-label="Reset filters">
            Reset filters
        </button>
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
