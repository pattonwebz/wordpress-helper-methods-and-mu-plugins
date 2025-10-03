<?php
/**
 * Plugin Name: Suppress deprecation notices.
 * Description: Suppresses specific deprecated notices so they don't end up in the debug log file.
 * Author: William Patton
 * Version: 1.0
 */

// Suppress all deprecated notices.
error_reporting( error_reporting() & ~E_DEPRECATED );
