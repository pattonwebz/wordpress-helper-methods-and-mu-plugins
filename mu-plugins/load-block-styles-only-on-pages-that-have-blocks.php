<?php
/**
 * Dequeues block styles if no blocks appear on a page. This should generally be considered safe but always
 * a chance that some other elements on pages use the same styles even if not a block.
 */


/**
 * Conditionally dequeue Gutenberg block styles if no blocks are present in the post.
 */
function conditionally_dequeue_block_styles() {
	// Only run this check on singular pages to avoid issues on archives.
    if ( ! is_singular() ) {
        return;
    }

	// Ensure we have a valid post object.
    global $post;
	if ( ! $post instanceof WP_Post ) {
	        return;
    }

    // Check if the post has blocks; if it does, do not dequeue styles.
    if ( function_exists( 'has_blocks' ) && has_blocks( $post ) ) {
        return;
    }

    // Dequeue Gutenberg block styles if no blocks are found.
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
}
add_action( 'wp_enqueue_scripts', 'conditionally_dequeue_block_styles', 100 );