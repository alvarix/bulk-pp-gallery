<?php
/**
 * Test: ppgal2_parse_filename
 *
 * Run: php ._-/test-1-parser.php
 */

function ppgal2_parse_filename( $filename ) {
    // Strip WP-appended suffixes
    $filename = preg_replace( '/-(scaled|rotated|\d+x\d+)$/', '', $filename );

    $segments = explode( '__', $filename );
    $result   = array( 'title' => '', 'type' => null, 'breed' => null, 'tags' => array() );
    $count    = count( $segments );

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
        $raw_tags = array_slice( $breed_tags, 1 );
        $result['tags'] = array_values( array_filter( array_map( function( $t ) {
            return trim( str_replace( array( '-', '_' ), ' ', $t ) );
        }, $raw_tags ) ) );
    }

    return $result;
}

// -------------------------------------------------------------------
// Test cases
// -------------------------------------------------------------------
$tests = array(
    array(
        'fluffy-boy',
        array( 'title' => 'Fluffy Boy', 'type' => null, 'breed' => null, 'tags' => array() ),
    ),
    array(
        'street__big-sunset',
        array( 'title' => 'Big Sunset', 'type' => 'Street', 'breed' => null, 'tags' => array() ),
    ),
    array(
        'studio__portrait__yorkie.wip.adoption',
        array( 'title' => 'Portrait', 'type' => 'Studio', 'breed' => 'Yorkie', 'tags' => array( 'wip', 'adoption' ) ),
    ),
    // WP -scaled suffix stripped
    array(
        'studio__portrait__yorkie.wip-scaled',
        array( 'title' => 'Portrait', 'type' => 'Studio', 'breed' => 'Yorkie', 'tags' => array( 'wip' ) ),
    ),
    // WP dimension suffix stripped
    array(
        'street__big-sunset-1024x768',
        array( 'title' => 'Big Sunset', 'type' => 'Street', 'breed' => null, 'tags' => array() ),
    ),
    array(
        'portrait',
        array( 'title' => 'Portrait', 'type' => null, 'breed' => null, 'tags' => array() ),
    ),
    // Breed with no tags
    array(
        'studio__fluffy__labrador',
        array( 'title' => 'Fluffy', 'type' => 'Studio', 'breed' => 'Labrador', 'tags' => array() ),
    ),
    // -rotated suffix
    array(
        'fluffy-boy-rotated',
        array( 'title' => 'Fluffy Boy', 'type' => null, 'breed' => null, 'tags' => array() ),
    ),
    // Tag with stray underscore should be cleaned
    array(
        'studio__portrait__yorkie.wip_.adoption',
        array( 'title' => 'Portrait', 'type' => 'Studio', 'breed' => 'Yorkie', 'tags' => array( 'wip', 'adoption' ) ),
    ),
    // Tag with hyphen should become space
    array(
        'studio__portrait__yorkie.work-in-progress',
        array( 'title' => 'Portrait', 'type' => 'Studio', 'breed' => 'Yorkie', 'tags' => array( 'work in progress' ) ),
    ),
    // Empty tag segments should be dropped
    array(
        'studio__portrait__yorkie.wip..adoption',
        array( 'title' => 'Portrait', 'type' => 'Studio', 'breed' => 'Yorkie', 'tags' => array( 'wip', 'adoption' ) ),
    ),
);

$pass = 0;
$fail = 0;

foreach ( $tests as $i => $test ) {
    list( $input, $expected ) = $test;
    $actual = ppgal2_parse_filename( $input );

    if ( $actual === $expected ) {
        echo "PASS  [{$i}] {$input}\n";
        $pass++;
    } else {
        echo "FAIL  [{$i}] {$input}\n";
        echo "  expected: " . json_encode( $expected ) . "\n";
        echo "  actual:   " . json_encode( $actual ) . "\n";
        $fail++;
    }
}

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
