<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 写日志
 */
function wctf_log($level, $message, array $context = [])
{
    if (!wctf_is_debug()) {
        return;
    }

    $line = sprintf(
        "[%s] [%s] %s %s\n",
        current_time('mysql'),
        strtoupper($level),
        $message,
        !empty($context) ? wp_json_encode($context) : ''
    );

    error_log($line);
}