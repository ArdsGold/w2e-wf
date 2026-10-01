<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stores a small rolling log for generator activity and errors.
 *
 * The existing option name is intentionally preserved so upgrades do not
 * discard logs created by earlier plugin versions.
 */
class WFEBPG_Logger
{
    private const OPTION = 'wfebpg_logs';
    private const MAX_ENTRIES = 300;

    public static function log($message, $level = 'info')
    {
        $logs = get_option(self::OPTION, []);

        array_unshift($logs, [
            'time'    => current_time('mysql'),
            'level'   => $level,
            'message' => wp_strip_all_tags($message),
        ]);

        update_option(
            self::OPTION,
            array_slice($logs, 0, self::MAX_ENTRIES),
            false
        );
    }

    public static function get()
    {
        return get_option(self::OPTION, []);
    }
}
