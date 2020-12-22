<?php

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}


function threadlog_info()
{
    return array(
        "name"          => "Threadlog",
        "description"   => "Creates a threadlog for users",
        "website"       => "http://autumnwelles.com/",
        "author"        => "Autumn Welles",
        "authorsite"    => "http://autumnwelles.com/",
        "version"       => "3.0",
        "guid"          => "",
        "codename"      => "threadlog+",
        "compatibility" => "18*"
    );
}

//todo: check if we really need this
function array_from_field($query_result, $field) {
    global $db;
    $items = [];
    while($row = $db->fetch_array($query_result))
    {
        $items[] = $row[$field];
    }
    return $items;
}

function threadlog_install()
{
    global $db, $mybb;

    // alter the forum table
    $db->write_query("ALTER TABLE `". $db->table_prefix ."forums` ADD `threadlog_include` TINYINT( 1 ) NOT NULL DEFAULT '1'");

    // add table for threadlog entries per user
    $db->write_query("CREATE TABLE `". $db->table_prefix ."threadlogentry` (
        eid INT(10) UNSIGNED NOT NULL auto_increment,
        tid INT(10) UNSIGNED NOT NULL DEFAULT 0,
        uid INT(10) UNSIGNED NOT NULL DEFAULT 0,
        roworder INT(7) UNSIGNED NOT NULL DEFAULT 0,
        description TEXT,
        KEY(tid),
        KEY(uid),
        PRIMARY KEY(eid)
    )");

    // get all the user/thread participation instances sorted by date
    $query = $db->write_query("SELECT DISTINCT posts.tid, posts.uid, threads.dateline
        FROM `". $db->table_prefix ."posts` AS posts
        LEFT JOIN `". $db->table_prefix ."threads` AS threads ON posts.tid=threads.tid
        ORDER BY posts.uid, threads.dateline DESC");

    // fetch all the user threads and add entries for each of them
    $queries = [];
    $current_uid = '';
    while($row = $db->fetch_array($query))
    {
        if ($current_uid !== $row['uid']) {
            $order = 0;
            $current_uid = $row['uid'];
        }
        $queries[] = [
            'uid' => $row['uid'],
            'tid' => $row['tid'],
            'roworder' => $order++
        ];
    }
    $db->insert_query_multiple('threadlogentry', $queries);

    // make a settings group
    $setting_group = array(
        'name' => 'threadlog_settings',
        'title' => 'Threadlog Settings',
        'description' => 'Modify settings for the threadlog plugin.',
        'disporder' => 5,
        'isdefault' => 0,
    );

    // get the settings group ID
    $gid = $db->insert_query("settinggroups", $setting_group);

    // define the settings
    $settings_array = array(
        'threadlog_perpage' => array(
            'title' => 'Threads per page',
            'description' => 'Enter the number of threads that should display per page.',
            'optionscode' => 'text',
            'value' => 50,
            'disporder' => 2,
        ),
        'threadlog_reorder' => array(
            'title' => 'Threadlog reordering',
            'description' => 'Allow users to reorder their threads in the threadlog',
            'optionscode' => 'onoff',
            'value' => true,
            'disporder' => 3
        ),
    );

    // add the settings
    foreach($settings_array as $name => $setting)
    {
        $setting['name'] = $name;
        $setting['gid'] = $gid;
        $db->insert_query('settings', $setting);
    }

    // rebuild
    rebuild_settings();

    // TEMPLATES

    // define the page template
    //todo: only show actions if it's this user's threadlog
    $threadlog_page = '<html>
<head>
    <title>{$mybb->settings[\'bbname\']} - {$username}\'s Threadlog</title>
    {$headerinclude}
        <link rel="stylesheet" href="{$mybb->settings[\'bburl\']}/inc/plugins/threadlog/threadlog-reorder.min.css">
    </head>
<body>
    {$header}

    {$multipage}

        <table id="threadlog" class="tborder" border="0" cellpadding="{$theme[\'tablespace\']}" cellspacing="{$theme[\'borderwidth\']}">
            <thead>
                <tr>
                    <td class="thead" colspan="{$threadlog_columns}">{$username}\'s Threadlog &middot; <a href="{$mybb->settings[\'bburl\']}/member.php?action=profile&uid={$uid}">View Profile</a>{$threadlog_buttons}</td>
                </tr>
                <tr>
                    <td class="tcat">Thread</td>
                    <td class="tcat" align="center">Participants</td>
                    <td class="tcat" align="center">Posts</td>
                    <td class="tcat" align="right">Last Post</td>
                    {$actions_header}
                </tr>
            </thead>
            <tbody>
                {$threadlog_list}
            </tbody>
            <tfoot>
                <tr><td class="tfoot" colspan="{$threadlog_columns}" align="center">
                <a href="#" id="active">{$count_active} active</a> &middot;
                <a href="#" id="closed">{$count_closed} closed</a> &middot;
                <a href="#" id="need-replies">{$count_replies} need replies</a> &middot;
                <a href="#" id="show-all">{$count_total} total</a>
                </td></tr>
            </tfoot>
        </table>

    {$multipage}

    {$footer}
    <script type="text/javascript" src="{$mybb->settings[\'bburl\']}/inc/plugins/threadlog/threadlog.js"></script>
    {$reorderscript}
</body>
</html>';

    // create the page template
    $insert_array = array(
        'title' => 'threadlog_page',
        'template' => $db->escape_string($threadlog_page),
        'sid' => '-1',
        'version' => '',
        'dateline' => time(),
    );

    // insert the page template into DB
    $db->insert_query('templates', $insert_array);

    // define the row template
    $threadlog_row = '<tr class="{$thread_status}" data-entry="{$eid}"><td class="{$thread_row}">{$thread_prefix} {$thread_title}<div class="smalltext">on {$thread_date}</small></td>
    <td class="{$thread_row}" align="center">{$thread_participants}</td>
    <td class="{$thread_row}" align="center"><a href="javascript:MyBB.whoPosted({$tid});">{$thread_posts}</a></td>
    <td class="{$thread_row}" align="right">Last post by {$thread_latest_poster}<div class="smalltext">on {$thread_latest_date}</div></td>{$thread_reorder_actions}</tr>';

    // create the row template
    $insert_array = array(
        'title' => 'threadlog_row',
        'template' => $db->escape_string($threadlog_row),
        'sid' => '-1',
        'version' => '',
        'dateline' => time(),
    );

    // insert the list row into DB
    $db->insert_query('templates', $insert_array);

    // define the row template
    $threadlog_nothreads = '<tr><td colspan="{$threadlog_columns}">No threads to speak of.</td></tr>';

    // create the row template
    $insert_array = array(
        'title' => 'threadlog_nothreads',
        'template' => $db->escape_string($threadlog_nothreads),
        'sid' => '-1',
        'version' => '',
        'dateline' => time(),
    );

    // insert the list row into DB
    $db->insert_query('templates', $insert_array);

}

function threadlog_is_installed()
{
    global $db, $mybb;
    if($db->field_exists("threadlog_include", "forums") &&
        isset($mybb->settings['threadlog_perpage'])) {
        return true;
    }
    return false;
}

function threadlog_uninstall()
{
    global $db;

    $db->drop_table("threadlogentry");
    $db->write_query("ALTER TABLE `". $db->table_prefix ."forums` DROP `threadlog_include`;");
    $db->delete_query('settings', "name IN ('threadlog_perpage', 'threadlog_reorder')");
    $db->delete_query('settinggroups', "name = 'threadlog_settings'");
    $db->delete_query("templates", "title IN ('threadlog_page','threadlog_row','threadlog_nothreads')");

    rebuild_settings();
}

function threadlog_activate()
{

}

function threadlog_deactivate()
{

}

/**
 * Create a threadlog entry if one doesn't exist yet
 */
function create_threadlog_entry($uid, $tid, $skip_checks = false) {
    global $db;
    //check if it already exists
    $query = $db->simple_select('threadlogentry', 'eid', 'tid='.$tid);
    if ($db->num_rows($query)) return;

    // update the other ones
    $db->write_query("UPDATE `{$db->table_prefix}threadlogentry`
        set roworder=roworder+1");
    // insert the new one in first place
    $db->insert_query('threadlogentry', ['uid' => $uid, 'tid' => $tid, 'roworder' => '0']);
}

// hook into new post/new thread
// add a threadlog entry if one doesn't exist
$plugins->add_hook('datahandler_post_insert_thread_end', 'threadlog_new_post_or_thread');
$plugins->add_hook('datahandler_post_insert_post_end', 'threadlog_new_post_or_thread');
function threadlog_new_post_or_thread(&$datahandler)
{
    create_threadlog_entry($datahandler->post_insert_data['uid'], $datahandler->post_insert_data['tid']);
}


// hook into post delete
// delete the threadlog entry if there are no more posts from the user
$plugins->add_hook('class_moderation_delete_post_start', 'threadlog_delete_post');
function threadlog_delete_post(&$pid) {
    global $db;
    // get the post
    $query = $db->simple_select('posts', 'uid, tid', 'pid='.$pid);
    $post = $db->fetch_array($query);
    // get the thread participants
    $query = $db->write_query("SELECT distinct uid from `{$db->table_prefix}posts`
        WHERE tid={$post['tid']}) AND pid != {$pid}");
    $rows = $db->fetch_array($query);
    foreach ($rows as $row) {
        // if the participant still has posts in the thread, do nothing
        if ($row['uid'] === $post['uid']) return;
    }
    $db->delete_query('threadlogentry', "tid={$post['tid']} AND uid={$post['uid']}");
}

// delete the threadlog entry for all users
$plugins->add_hook('class_moderation_delete_thread_start', 'threadlog_delete_thread');
function threadlog_delete_thread(&$tid) {
    global $db;
    $db->delete_query('threadlogentry', "tid={$tid}");
}

// delete all threadlog entries when a user is deleted
$plugins->add_hook('datahandler_user_delete_posts', 'threadlog_delete_user');
function threadlog_delete_user(&$datahandler) {
    global $db;
    if (empty($datahandler->delete_uids)) return;
    $db->delete_query('threadlogentry', "uid IN {$datahandler->delete_uids}");
}

/**
 * Gets all of the thread participants indexed by thread id for this user
 * Does not include the user themself
 * @return array
 */
function get_thread_participants($uid) {
    global $db;
    // todo: there is a way to optimize this to only get the participants for the current page
    // currently gets from all pages
    // get all of the participants per thread, except for the current user
    $participants_query = $db->write_query("SELECT distinct p.uid,p.username, p.tid
        from `".$db->table_prefix."posts` as p
        where p.uid != ".$uid." and p.visible=1 and p.tid in
            (select tid from `".$db->table_prefix."threadlogentry` where uid=".$uid.")
        order by p.tid");
    // unpack it into a 2d array keyed by thread id
    $participants_by_tid = [];
    while ($thread_participant = $db->fetch_array($participants_query)) {
        $current_tid = $thread_participant['tid'];
        if (array_key_exists($current_tid, $participants_by_tid)) {
            $participants_by_tid[$current_tid][] = [
                'uid' => $thread_participant['uid'],
                'username' => $thread_participant['username']
            ];
        } else {
            $participants_by_tid[$current_tid] = array([
                'uid' => $thread_participant['uid'],
                'username' => $thread_participant['username']
            ]);
        }
    }
    return $participants_by_tid;
}

/**
 * Generate an array of template values from a given threadlog entry
 * @return array Keyed array of values
 */
function threadlog_row_template_values($thread, $entry_count, $participants_by_tid = null, $count_total)
{
    global $mybb, $db;
    $return_values = [];

    $return_values['uid'] = $uid = $thread['uid'];
    $return_values['tid'] = $tid = $thread['tid'];
    $return_values['eid'] = $thread['eid'];
    $return_values['thread_posts'] = $thread['replies'] + 1;

    // get the participants for just this thread if we don't have a participant list
    if (!$participants_by_tid) {
        $participants_by_tid = [
            $tid => []
        ];
        $participants_query = $db->write_query("SELECT distinct p.uid,p.username
        from `".$db->table_prefix."posts` as p
        where p.uid !={$uid} and p.visible=1 and p.tid={$tid}
        order by p.pid");
        while ($participant = $db->fetch_array($participants_query)) {
            $participants_by_tid[$tid][] = [
                'uid' => $participant['uid'],
                'username' => $participant['username']
            ];
        }
    }

    // set up row styles
    $return_values['thread_row'] = ($entry_count % 2 ? "trow2" : "trow1");
    $return_values['thread_status'] = $thread['closed'] == 1 ? "closed" : "active" .
        ($thread['lastposteruid'] != $uid ? " needs-reply" : "");

    // todo: add a description editor
    // thread information
    $return_values['thread_title'] = "<a href=\"{$mybb->settings['bburl']}/showthread.php?tid=". $thread['tid'] ."\">". $thread['subject'] ."</a>";
    $return_values['thread_date'] = date($mybb->settings['dateformat'], $thread['dateline']);
    $return_values['thread_latest_poster'] = "<a href=\"{$mybb->settings['bburl']}/member.php?action=profile&uid=". $thread['lastposteruid'] ."\">". $thread['lastposter'] ."</a>";
    $return_values['thread_latest_date'] = date($mybb->settings['dateformat'], $thread['lastpost']);
    $return_values['thread_prefix'] = $thread['displaystyle'];

    // set up participant links
    if (!array_key_exists($tid, $participants_by_tid) || empty($participants_by_tid[$tid])) {
        $return_values['thread_participants'] = 'N/A';
    } else {
        $other_users = $participants_by_tid[$tid];
        $participant_links = [];
        foreach ($other_users as $other_user) {
            $participant_links[] = "<a href=\"{$mybb->settings['bburl']}/member.php?action=profile&uid=". $other_user['uid'] ."\">". $other_user['username'] ."</a>";
        }
        $return_values['thread_participants'] = implode(", ", $participant_links);
    }

    // set up thread reorder actions
    $return_values['thread_reorder_actions'] = '';
    if ($mybb->user['uid'] === $uid && intval($mybb->settings['threadlog_reorder']) === 1) {
        $return_values['thread_reorder_actions'] = '<td class="'.$return_values['thread_row'].'" align="right"><select class="threadrow-reorder">'.
            '<option value="">Reorder</option>'.
            (intval($thread['roworder']) !== 0 ? '<option value="up">Move Up</option>' : '').
            (intval($thread['roworder']) !== $count_total - 1 ?'<option value="down">Move Down</option></select></td>' : '');
    }
    return $return_values;
}

/**
 * Gets all of the threadlog entries for the user ordered by roworder
 * GET misc.php?action=threadlog<&uid=#>
 */
$plugins->add_hook('misc_start', 'threadlog');
function threadlog() {
    global $mybb, $templates, $db, $theme, $lang, $header, $headerinclude, $footer, $uid, $tid;

    // show the threadlog when we call it
    if ($mybb->request_method !== 'get' || $mybb->get_input('action') !== 'threadlog') {
        return;
    }

    $templatelist = "multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";

    // check for a UID
    if(isset($mybb->input['uid'])){
        $uid = intval($mybb->input['uid']);
    } elseif(isset($mybb->user['uid'])) {
        $uid = $mybb->user['uid'];
    } else {
        exit;
    }

    // set up the pager
    $threadlog_url = htmlspecialchars_uni("misc.php?action=threadlog&uid=". $uid);
    $per_page = intval($mybb->settings['threadlog_perpage']);
    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    if($page && $page > 0) {
        $start = ($page - 1) * $per_page;
    } else {
        $start = 0;
        $page = 1;
    }
    // get the total, counts, and the username
    $query = $db->write_query("SELECT
        count(e.eid) as total,
        count(case t.closed when 1 then 1 else null end) as closed_num,
        count(case when (t.closed!=1 and t.lastposteruid!=1) then 1 else null end) as replies,
        u.username
        from `{$db->table_prefix}threadlogentry` as e
        left join `{$db->table_prefix}threads` as t on t.tid=e.tid
        left join `{$db->table_prefix}forums` as f on f.fid=t.fid
        left join `{$db->table_prefix}users` as u on u.uid=e.uid
        where e.uid={$uid} and f.threadlog_include=1 and t.visible");

    $counts = $db->fetch_array($query);
    $count_total = $counts['total'];
    $count_closed = $counts['closed_num'];
    $count_replies = $counts['replies'];
    $count_active = $count_total - $count_closed;
    // make sure single quotes are replaced so we don't muck up queries
    $username = str_replace("'", "&#39;", $counts['username']);

    // add the breadcrumb
    add_breadcrumb($username .'\'s Threadlog', "misc.php?action=threadlog");

    $multipage = multipage($count_total, $per_page, $page, $threadlog_url);

    // get the entries
    $query = $db->write_query("SELECT e.eid,e.uid,e.tid,e.roworder,t.username,t.subject,p.displaystyle,t.dateline,t.replies,t.views,t.lastpost,t.lastposter,t.lastposteruid,t.prefix,t.closed
        from `{$db->table_prefix}threadlogentry` as e
        left join `{$db->table_prefix}threads` as t on t.tid=e.tid
        left join `{$db->table_prefix}threadprefixes` as p on p.pid = t.prefix
        left join `{$db->table_prefix}forums` as f on f.fid=t.fid
        where e.uid={$uid} and f.threadlog_include=1 and t.visible
        ORDER BY e.roworder LIMIT ". $start .", ". $per_page);

    // thread reorder script
    $reorderscript = '';
    if ($mybb->user['uid'] === $uid && intval($mybb->settings['threadlog_reorder']) === 1) {
        $threadlog_columns = '5';
        $actions_header = '<td class="tcat" align="right">Actions</td>';
        $reorderscript = "<script type=\"text/javascript\" src=\"{$mybb->settings['bburl']}/inc/plugins/threadlog/jquery-ui.min.js\"></script>".
        "<script type=\"text/javascript\" src=\"{$mybb->settings['bburl']}/inc/plugins/threadlog/threadlog-reorder.js\"></script>";
        $threadlog_buttons = "<div class=\"postbit_buttons\"><a href=\"#\" id=\"edit-threadlog-btn\">Edit</a><a href=\"#\" id=\"save-threadlog-btn\" style=\"display: none\">Save</a><a href=\"#\" id=\"cancel-threadlog-btn\" style=\"display: none\">Cancel</a></div>";
    } else {
        $threadlog_columns = '4';
    }

    // no entries
    if ($db->num_rows($query) < 1) {
        eval("\$threadlog_list .= \"". $templates->get("threadlog_nothreads") ."\";");
    }

    $participants_by_tid = get_thread_participants($uid);

    // process each threadlog entry
    $entry_count = 0;
    while ($thread = $db->fetch_array($query)) {
        $entry_count++;
        $values = threadlog_row_template_values($thread, $entry_count, $participants_by_tid, $count_total);
        // extract the keys of the array into the template variables
        extract($values);
        // add the row to the list
        eval("\$threadlog_list .= \"".$templates->get("threadlog_row")."\";");
    } // end while

    eval("\$threadlog_page = \"".$templates->get("threadlog_page")."\";");
    output_page($threadlog_page);

    exit;
}

/**
 * Check that the page number is valid on a threadlog page
 * @return boolean
 */
function threadlog_page_valid($page, $uid) {
    global $mybb, $db;
    $query = $db->simple_select('threadlogentry', 'COUNT(eid) as count', 'uid='. $uid);
    $count = intval($db->fetch_field($query, 'count'));
    $perpage = intval($mybb->settings['threadlog_perpage']);
    // if page is greater than the max or less than 1, it's not valid
    if ($page > ceil($count / $perpage) || $page < 1) {
        return false;
    }
    return true;
}

/**
 * Helper function that prints a json response
 */
function json_response($status, $data = '') {
    global $charset;
    http_response_code($status);
    header("Content-type: application/json; charset={$charset}");
    echo json_encode($data);
    exit;
}

/**
 * Perform an ajax request to reorder threads
 *
 * Route: POST xmlhttp.php?action=threadlog&reorder=<multi|single>
 */
$plugins->add_hook('xmlhttp', 'json_threadlog_reorder');
function json_threadlog_reorder() {
    global $mybb, $db, $templates;
    if ($mybb->request_method !== 'post' || $mybb->get_input('action') !== 'threadlog') {
        return;
    }
    // validate
    if (!isset($mybb->user['uid'])) {
        json_response(405, "Not logged in");
    }
    $uid = $mybb->user['uid'];
    if ($mybb->get_input('uid') && $mybb->get_input('uid') !== $uid) json_response(405, "Cannot edit another user's threadlog.");
    /**
     * Reorder multiple entries at once - this is what happens after drag and drop
     * Input: 'threadlogEntries' - a list of entry ids
     * Output: The entries on this page are updated to match the order of the sent ids
     */
    if ($mybb->get_input('reorder') === 'multi') {
        $page = intval($mybb->input['page']);
        $entries = $mybb->input['threadlogEntries'];

        if (!threadlog_page_valid($page, $uid) || empty($entries)) {
            json_response(403, "Information sent was not valid");
        }

        $update_values = [];
        // stitch them into a query with the new given roworder
        foreach ($entries as $i => $entry) {
            $update_values[] = sprintf("(%d, %d)", $entry, $i);
        }

        // Using an insert duplicate key to do a mass update so we don't run N queries
        $db->write_query("INSERT into `". $db->table_prefix ."threadlogentry` (eid, roworder)
            VALUES ".implode(", ", $update_values)." ON DUPLICATE KEY UPDATE roworder=VALUES(roworder)");
        json_response(200, $update_values);
    /**
     * Reorder a single threadlog entry by moving it up or down
     *
     * Input: entry - the entry ID to move, direction - either 'up' or 'down'
     * Output: the entry sent is swapped with the next or previous one, and the entry that was swapped is sent back as html
     */
    } else if ($mybb->get_input('reorder') === 'single') {
        $eid = $mybb->input['entry'];
        $direction = $mybb->input['direction'];
        if ($direction !== 'up' && $direction !== 'down') {
            json_response(403, "Direction must be up or down, got '{$direction}'");
        }
        // get the entry we will swap with
        // we need the whole thing because it could be on a different page, plus we need to check what's visible
        $query = $db->write_query("SELECT e.*,t.username,t.subject,p.displaystyle,t.dateline,t.replies,t.views,t.lastpost,t.lastposter,t.lastposteruid,t.prefix,t.closed
            from `{$db->table_prefix}threadlogentry` as e
            LEFT JOIN `{$db->table_prefix}threads` as t on t.tid=e.tid
            left join `".$db->table_prefix."threadprefixes` as p on p.pid = t.prefix
            LEFT JOIN `{$db->table_prefix}forums` as f on f.fid=t.fid
            WHERE t.visible=1 and f.threadlog_include=1 and roworder ".($direction === 'up' ? '<' : '>')."
                (SELECT roworder from `{$db->table_prefix}threadlogentry` WHERE eid={$eid})
            ORDER BY roworder
            LIMIT 1");
        // todo: this is triggering, check the query above
        // don't let the user try to move the first or last one up/down
        if (!$db->num_rows($query)) json_response(403, "Failed to move {$eid}");

        $entry_to_swap = $db->fetch_array($query);
        $current_roworder = $direction === 'up' ? $entry_to_swap['roworder'] + 1 : $entry_to_swap['roworder'] - 1;

        // perform the swap
        $db->write_query("INSERT into `{$db->table_prefix}threadlogentry` (eid, roworder)
            VALUES ({$eid}, {$entry_to_swap['roworder']}), ({$entry_to_swap['eid']}, $current_roworder) ON DUPLICATE KEY UPDATE roworder=VALUES(roworder)");
        // did the API request ask for a new template?
        if ($mybb->input['first'] || $mybb->input['last']) {
            // get the total count
            $query = $db->write_query("SELECT count(e.eid) as total from `{$db->table_prefix}threadlogentry` as e
                left join `{$db->table_prefix}threads` as t on t.tid=e.tid
                left join `{$db->table_prefix}forums` as f on f.fid=t.fid
                where t.visible and f.threadlog_include and e.uid={$entry_to_swap['uid']}");
            $count_total = $db->fetch_field($query, 'count');
            $template_row = threadlog_row_template_values($entry_to_swap,
                ($mybb->input['first'] ? 0 : $mybb->settings['threadlog_perpage']),
                null,
                $count_total
            );
            extract($template_row);
            eval("\$threadlog_row = \"".$templates->get("threadlog_row")."\";");
            json_response(200, $threadlog_row);
        }
        json_response(200, $entry_to_swap);
    } else {
        json_response(404);
    }
}

// add field to ACP
$plugins->add_hook("admin_forum_management_edit", "threadlog_forum_edit");
$plugins->add_hook("admin_forum_management_edit_commit", "threadlog_forum_commit");

function threadlog_forum_edit()
{
    global $plugins;
    $plugins->add_hook("admin_formcontainer_end", "threadlog_formcontainer_editform");
}

function threadlog_formcontainer_editform()
{
    global $mybb, $db, $lang, $form, $form_container, $fid;

    $query = $db->simple_select('forums', 'threadlog_include', "fid='{$fid}'", array('limit' => 1));
    $include = $db->fetch_field($query, 'threadlog_include');

    if($form_container->_title == "Edit Forum")
    {
        // create input fields
        $threadlog_forum_include = array(
            $form->generate_check_box("threadlog_include", 1, "Include in threadlog?", array("checked" => $include))
        );
        $form_container->output_row("Threadlog", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $threadlog_forum_include)."</div>");
    }
}

function threadlog_forum_commit()
{
    global $db, $mybb, $cache, $fid;

    $update_array = array(
        "threadlog_include" => $mybb->get_input('threadlog_include', MyBB::INPUT_INT),
    );
    $db->update_query("forums", $update_array, "fid='{$fid}'");

    $cache->update_forums();
}
