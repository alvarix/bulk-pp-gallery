#!/usr/bin/env wp eval-file
<?php
/**
 * One-time backfill: sync _ppgal2_breed_sort meta for all ppgal2 posts.
 *
 * Run: wp eval-file wp-content/plugins/bulk-pp-gallery/._-/backfill-breed-meta.php
 */

$posts = get_posts( array(
    'post_type'      => 'ppgal2',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
) );

$updated = 0;
$skipped = 0;

foreach ( $posts as $post_id ) {
    $breeds = get_the_terms( $post_id, 'ppgal2_breed' );
    if ( ! empty( $breeds ) && ! is_wp_error( $breeds ) ) {
        update_post_meta( $post_id, '_ppgal2_breed_sort', $breeds[0]->name );
        $updated++;
        WP_CLI::log( "  #{$post_id}: {$breeds[0]->name}" );
    } else {
        delete_post_meta( $post_id, '_ppgal2_breed_sort' );
        $skipped++;
    }
}

WP_CLI::success( "Done. {$updated} posts updated, {$skipped} without breed." );
