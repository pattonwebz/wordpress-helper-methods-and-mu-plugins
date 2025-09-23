<?php
/**
 * Plugin Name: Filter Out JIT Load Textdomain Doing It Wrong Error Log
 * Description: Prevent the "Doing It Wrong" notice for _load_textdomain_just_in_time function. A lot of plugins never updated and these flood my error logs making it hard to see actual errors.
 * Author: William Patton
 * Version: 1.0
 */

add_filter(
    'doing_it_wrong_trigger_error',
    function ( $trigger, $function_name ) {
        if ( $function_name === '_load_textdomain_just_in_time' ) {
            return false;
        }
        return $trigger;
    },
    10,
    2
);
