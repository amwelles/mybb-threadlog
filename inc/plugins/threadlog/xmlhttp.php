<?php

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

require_once(PLUGIN_THREADLOG_ROOT . '/Threadlog.php');

/**
 * Perform an ajax request to reorder threads
 * Route: POST xmlhttp.php?action=threadlog&update=<multi|single>&field=<reorder|description|date>
 */
$plugins->add_hook('xmlhttp', 'threadlog_update');
function threadlog_update() {
    global $mybb;
    if ($mybb->request_method !== 'post' || $mybb->get_input('action') !== 'threadlog') {
        return;
    }
    // validate
    if (!isset($mybb->user['uid'])) {
        Threadlog::json_response(405, "Not logged in");
    }
    $uid = $mybb->user['uid'];
    if ($mybb->get_input('uid') && $mybb->get_input('uid') !== $uid) {
        Threadlog::json_response(405, "Cannot edit another user's threadlog.");
    }

    $threadlog = new Threadlog($uid);
    switch ($mybb->input['field']) {
        case 'reorder':
            $threadlog->handle_reorder();
            break;
        case 'description':
            $threadlog->handle_description();
            break;
        case 'date':
            $threadlog->handle_date();
            break;
        default:
            Threadlog::json_response(404);
    }
}