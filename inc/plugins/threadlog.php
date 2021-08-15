<?php

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

define('PLUGIN_THREADLOG_ROOT', MYBB_ROOT . 'inc/plugins/threadlog');

function threadlog_info()
{
    return array(
        "name"          => "Threadlog",
        "description"   => "Creates a reorderable threadlog for users<br />
            <a style=\"color: green\" href=\"index.php?module=config-plugins&action=update_threadlog\">Update</a>",
        "website"       => "http://autumnwelles.com/",
        "author"        => "Autumn Welles",
        "authorsite"    => "http://autumnwelles.com/",
        "version"       => "5.0",
        "guid"          => "",
        "codename"      => "threadlog+",
        "compatibility" => "18*"
    );
}

function threadlog_generate_entries()
{
    global $db;
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
}

function threadlog_install()
{
    global $db, $mybb;

    require_once(PLUGIN_THREADLOG_ROOT . '/templates.php');

    // alter the forum table
    $db->write_query("ALTER TABLE `". $db->table_prefix ."forums` ADD `threadlog_include` TINYINT( 1 ) NOT NULL DEFAULT '1'");

    // add table for threadlog entries per user
    $db->write_query("CREATE TABLE `". $db->table_prefix ."threadlogentry` (
        eid INT(10) UNSIGNED NOT NULL auto_increment,
        tid INT(10) UNSIGNED NOT NULL DEFAULT 0,
        uid INT(10) UNSIGNED NOT NULL DEFAULT 0,
        roworder INT(7) UNSIGNED NOT NULL DEFAULT 0,
        description TEXT,
        dateoverride INT(10) DEFAULT -1,
        KEY(tid),
        KEY(uid),
        PRIMARY KEY(eid)
    )");

    threadlog_generate_entries();

    // settings
    $setting_group = array(
        'name' => 'threadlog_settings',
        'title' => 'Threadlog Settings',
        'description' => 'Modify settings for the threadlog plugin.',
        'disporder' => 5,
        'isdefault' => 0,
    );
    $gid = $db->insert_query("settinggroups", $setting_group);
    $settings_array = [
        [
            'name' => 'threadlog_perpage',
            'title' => 'Threads per page',
            'description' => 'Enter the number of threads that should display per page.',
            'optionscode' => 'text',
            'value' => 50,
            'disporder' => 2,
            'gid' => $gid
        ],
        [   'name' => 'threadlog_reorder',
            'title' => 'Threadlog reordering',
            'description' => 'Allow users to reorder their threads in the threadlog',
            'optionscode' => 'onoff',
            'value' => true,
            'disporder' => 3,
            'gid' => $gid
        ],
        [   'name' => 'threadlog_describe',
            'title' => 'Threadlog descriptions',
            'description' => 'Allow users to add descriptions to threads in the threadlog',
            'optionscode' => 'onoff',
            'value' => true,
            'disporder' => 4,
            'gid' => $gid
        ],
        [   'name' => 'threadlog_dateoverride',
            'title' => 'Threadlog Date Override',
            'description' => 'Allow users to specify a date for their threadlog items - this does not affect the thread for other users',
            'optionscode' => 'onoff',
            'value' => true,
            'disporder' => 5,
            'gid' => $gid
        ]
    ];
    $db->insert_query_multiple('settings', $settings_array);

    rebuild_settings();

    // todo: add a template group - not having one is stupid
    // templates
    $template_queries = [];
    foreach ($templates as $name => $template) {
        $template_queries[] = [
            'title' => 'threadlog_'.$name,
            'template' => $db->escape_string($template),
            'sid' => '-1',
            'version' => '',
            'dateline' => time(),
        ];
    }
    $db->insert_query_multiple('templates', $template_queries);
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
    $db->drop_column("forums", "threadlog_include");
    $db->delete_query('settings', "name LIKE 'threadlog%'");
    $db->delete_query('settinggroups', "name = 'threadlog_settings'");
    $db->delete_query("templates", "title LIKE 'threadlog%' AND AND sid='-2'");
    rebuild_settings();
}

function threadlog_activate()
{

}

function threadlog_deactivate()
{

}

require_once(PLUGIN_THREADLOG_ROOT . '/admin.php');
require_once(PLUGIN_THREADLOG_ROOT . '/Threadlog.php');
require_once(PLUGIN_THREADLOG_ROOT . '/xmlhttp.php');

/**
 * Display friendly text in the who's online list
 */
$plugins->add_hook('fetch_wol_activity_end', 'threadlog_fetch_wol');
function threadlog_fetch_wol(&$user_activity)
{
	global $user, $mybb, $uid_list;

    preg_match('/\/([a-z]+)\.php\??(.*)/', $user_activity['location'], $matches);
    if (empty($matches)) return $user_activity; //something went wrong with parsing

	$filename = $matches[1];
	// get parameters of the URI
	if (!empty($matches[2])) {
		parse_str(html_entity_decode($matches[2]), $parameters);
	}

    if ($filename !== 'misc' || $parameters['action'] !== 'threadlog') return $user_activity;

    $threadlog_uids = [];
    // if the user isn't viewing their own threadlog
    if (isset($parameters['uid']) && $parameters['uid'] != $user['uid']) {
        $user_activity['uid'] = $parameters['uid'];
        $uid_list[$parameters['uid']] = $parameters['uid'];
    }
    $user_activity['activity'] = 'threadlog';

	return $user_activity;
}
$plugins->add_hook('build_friendly_wol_location_end', 'threadlog_build_friendly');
function threadlog_build_friendly(&$plugin_array)
{
	global $usernames;
	if ($plugin_array['user_activity']['activity'] != "threadlog") return $plugin_array;
    $uid = $plugin_array['user_activity']['uid'];
    if (!empty($uid ) && !empty($usernames[$uid])) {
        $plugin_array['location_name'] = sprintf('Viewing <a href="%s" target="_blank">%s</a>\'s <a href="misc.php?action=threadlog&uid=%s">Threadlog</a>',
            get_profile_link($plugin_array['user_activity']['uid']),
            $usernames[$plugin_array['user_activity']['uid']],
            $uid
        );
    } else {
        $plugin_array['location_name'] = sprintf('Viewing Threadlog');
    }
    return $plugin_array;
}

/**
 * Create a threadlog entry if one doesn't exist yet
 */
function create_threadlog_entry($uid, $tid) {
    global $db;
    $tid = $db->escape_string($tid);
    $uid = $db->escape_string($uid);
    //check if it already exists
    $query = $db->simple_select('threadlogentry', 'eid', 'tid='.$tid.' and uid='.$uid);
    if ($db->num_rows($query)) return;

    // update the other ones
    $db->write_query("UPDATE `{$db->table_prefix}threadlogentry`
        set roworder=roworder+1 where uid={$uid}");
    // insert the new one in first place
    $db->insert_query('threadlogentry', ['uid' => $uid, 'tid' => $tid, 'roworder' => '0']);
}

$plugins->add_hook('admin_config_plugins_begin', 'threadlog_regen_entries');
function threadlog_regen_entries()
{
    global $db, $mybb;
    if ($mybb->input['module'] != 'config-plugins' || $mybb->input['action'] != "regen_threadlogs") {
        return;
    }
    $db->delete_query('threadlogentry');
    threadlog_generate_entries();

    flash_message("Threadlogs regenerated", "success");
    admin_redirect("index.php?module=config-plugins");
}

// hook into new post/new thread
// add a threadlog entry if one doesn't exist
$plugins->add_hook('datahandler_post_insert_thread_end', 'threadlog_new_post_or_thread');
$plugins->add_hook('datahandler_post_insert_post_end', 'threadlog_new_post_or_thread');
function threadlog_new_post_or_thread(&$datahandler)
{
    global $mybb;
    $uid = $datahandler->post_insert_data['uid'] ? $datahandler->post_insert_data['uid'] : $mybb->user['uid'];
    $tid = $datahandler->post_insert_data['tid'] ? $datahandler->post_insert_data['tid'] : $mybb->input['tid'];
    create_threadlog_entry($uid, $tid);
}

$plugins->add_hook('editpost_start', 'threadlog_handle_authorswitch');
function threadlog_handle_authorswitch()
{
    global $mybb, $db;
    $newuid = $mybb->get_input('authorswitch', MyBB::INPUT_INT);
    if (!$newuid) return;

    $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
    $post = get_post($pid);

    // check if the old user still has posts in the thread
    $query = $db->write_query("SELECT distinct uid from `{$db->table_prefix}posts`
        WHERE tid={$post['tid']} AND pid !={$pid}");
    $delete_entry = true;
    while ($row = $db->fetch_array($query)) {
        // if the participant still has posts in the thread, do nothing
        if ($row['uid'] === $post['uid']) $delete_entry = false;
    }
    if ($delete_entry) {
        $db->delete_query('threadlogentry', "tid={$post['tid']} AND uid={$post['uid']}");
    }
    // create an entry if one doesn't exist yet
    create_threadlog_entry($newuid, $post['tid']);
}



// hook into post delete
// delete the threadlog entry if there are no more posts from the user
$plugins->add_hook('class_moderation_delete_post_start', 'threadlog_delete_post');
function threadlog_delete_post(&$pid) {
    global $db;
    $pid = $db->escape_string($pid);
    // get the post
    $query = $db->simple_select('posts', 'uid, tid', 'pid='.$pid);
    $post = $db->fetch_array($query);
    $db->free_result($query);
    // get the thread participants
    $query = $db->write_query("SELECT distinct uid from `{$db->table_prefix}posts`
        WHERE tid={$post['tid']} AND pid !={$pid}");
    while ($row = $db->fetch_array($query)) {
        // if the participant still has posts in the thread, do nothing
        if ($row['uid'] === $post['uid']) return;
    }
    //echo 'deleting threadlogentry';
    $db->delete_query('threadlogentry', "tid={$post['tid']} AND uid={$post['uid']}");
    //echo 'done';
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
    $db->delete_query('threadlogentry', "uid IN ({$datahandler->delete_uids})");
}

/**
 * Gets all of the thread participants indexed by thread id
 * Does not include the user themself
 * @param integer user id to exclude
 * @param array list of thread ids to index
 * @return array
 */
function get_thread_participants($uid, $tids) {
    global $db;
    $tid_string = implode(',', $tids);
    // get all of the participants per thread, except for the current user
    $participants_query = $db->write_query("SELECT distinct p.tid,p.uid,p.username,u.usergroup,u.displaygroup
        from `{$db->table_prefix}posts` as p
        left join `{$db->table_prefix}users` as u on u.uid=p.uid
        where p.uid != ".$uid." and p.visible=1 and p.tid in
            (select tid from `".$db->table_prefix."threadlogentry` where tid in ({$tid_string}))
        order by p.tid");
    // unpack it into a 2d array keyed by thread id
    $participants_by_tid = [];
    while ($thread_participant = $db->fetch_array($participants_query)) {
        $current_tid = $thread_participant['tid'];
        if (array_key_exists($current_tid, $participants_by_tid)) {
            $participants_by_tid[$current_tid][] = $thread_participant;
        } else {
            $participants_by_tid[$current_tid] = [$thread_participant];
        }
    }
    return $participants_by_tid;
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
    $templatelist .= "threadlog_row_actions,threadlog_nothreads,threadlog_row,threadlog_page";
    // check for a UID
    if (isset($mybb->input['uid'])) {
        $uid = $mybb->get_input('uid', MyBB::INPUT_INT);
    } elseif (isset($mybb->user['uid'])) {
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
    // get the total counts
    $query = $db->write_query("SELECT
        count(e.eid) as total,
        count(case t.closed when 1 then 1 else null end) as closed_num,
        count(case when (t.closed!=1 and t.lastposteruid!={$uid}) then 1 else null end) as replies
        from `{$db->table_prefix}threadlogentry` as e
        left join `{$db->table_prefix}threads` as t on t.tid=e.tid
        left join `{$db->table_prefix}forums` as f on f.fid=t.fid
        where e.uid={$uid} and f.threadlog_include=1 and t.visible");

    $counts = $db->fetch_array($query);
    $count_total = $counts['total'];
    $count_closed = $counts['closed_num'];
    $count_replies = $counts['replies'];
    $count_active = $count_total - $count_closed;

    $query = $db->simple_select("users", 'username,displayname', 'uid='.$uid);
    if ($db->num_rows($query)) {
        $user = $db->fetch_array($query);
        $username = $user['displayname'] ? $user['displayname'] : $user['username'];
    }

    // add the breadcrumb
    add_breadcrumb($username .'\'s Threadlog', "misc.php?action=threadlog");

    $multipage = multipage($count_total, $per_page, $page, $threadlog_url);

    // get the entries
    $entry_descrip = intval($mybb->settings['threadlog_describe']) === 1 ? 'e.description,' : '';
    $query = $db->write_query("SELECT e.eid,e.uid,e.tid,{$entry_descrip}e.roworder,
            t.username,t.subject,p.displaystyle,t.dateline,t.replies,t.views,
            t.lastpost,t.lastposter,t.lastposteruid,u.usergroup as lastposterusergroup,u.displaygroup as lastposterdisplaygroup,
            t.prefix,t.closed
        from `{$db->table_prefix}threadlogentry` as e
        left join `{$db->table_prefix}threads` as t on t.tid=e.tid
        left join `{$db->table_prefix}users` as u on u.uid=t.lastposteruid
        left join `{$db->table_prefix}threadprefixes` as p on p.pid = t.prefix
        left join `{$db->table_prefix}forums` as f on f.fid=t.fid
        where e.uid={$uid} and f.threadlog_include=1 and t.visible
        ORDER BY e.roworder LIMIT ". $start .", ". $per_page);

    // can edit threadlog
    $threadlog_settings = '';
    if ($mybb->user['uid'] === $uid) {
        // reorderability
        if (intval($mybb->settings['threadlog_reorder']) === 1) {
            $threadlog_settings .= 'reorderable ';
        }
        if (intval($mybb->settings['threadlog_describe']) === 1) {
            $threadlog_settings .= 'describeable ';
        }
        if (intval($mybb->settings['threadlog_dateoverride']) === 1) {
            $threadlog_settings .= 'dateable ';
        }

        if ($threadlog_settings === '') {
            $threadlog_columns = '4';
        } else {
            eval('$threadlog_buttons = "'.$templates->get('threadlog_action_buttons').'";');
            $threadlog_columns = '5';
            eval('$actions_header = "'.$templates->get("threadlog_actions_header").'";');
        }
    }

    $threadlog_list = '';

    // no entries
    if ($db->num_rows($query) < 1) {
        eval("\$threadlog_list .= \"". $templates->get("threadlog_nothreads") ."\";");
    } else {
        // unpack into array first
        $threads = [];
        while ($thread = $db->fetch_array($query)) {
            $threads[] = $thread;
        }
        $tids = array_map(function($thread) { return $thread['tid']; }, $threads);
        $participants_by_tid = get_thread_participants($uid, $tids);

        // process each threadlog entry
        $entry_count = $per_page * ($page - 1);
        foreach ($threads as $thread) {
            $entry_count++;
            $values = Threadlog::row_template_values($thread, $entry_count, $participants_by_tid, $count_total);
            // extract the keys of the array into the template variables
            extract($values);
            // add the row to the list
            eval("\$threadlog_list .= \"".$templates->get("threadlog_row")."\";");
        }
    }

    eval("\$threadlog_page = \"".$templates->get("threadlog_page")."\";");
    output_page($threadlog_page);

    exit;
}