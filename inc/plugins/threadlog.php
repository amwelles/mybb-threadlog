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
        "codename"      => "threadlog",
        "compatibility" => "18*"
    );
}

function threadlog_install()
{
    global $db, $mybb;

    // alter the forum table
    $db->write_query("ALTER TABLE `". $db->table_prefix ."forums` ADD `threadlog_include` TINYINT( 1 ) NOT NULL DEFAULT '0'");

    // SETTINGS

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
    $threadlog_page = '<html>
  <head>
    <title>{$mybb->settings[\'bbname\']} - {$username}\'s Threadlog</title>
    {$headerinclude}
  </head>
  <body>
    {$header}

    {$multipage}
    
        <table id="threadlog" class="tborder" border="0" cellpadding="{$theme[\'tablespace\']}" cellspacing="{$theme[\'borderwidth\']}">
            <thead>
                <tr>
                    <td class="thead" colspan="4">{$username}\'s Threadlog &middot; <a href="{$mybb->settings[\'bburl\']}/member.php?action=profile&uid={$uid}">View Profile</a></td>
                </tr>
                <tr>
                    <td class="tcat">Thread</td>
                    <td class="tcat" align="center">Participants</td>
                    <td class="tcat" align="center">Posts</td>
                    <td class="tcat" align="right">Last Post</td>
                </tr>
            </thead>
            <tbody>
                {$threadlog_list}
            </tbody>
            <tfoot>
                <tr><td class="tfoot" colspan="4" align="center">
                {$count_active} active &middot;
                {$count_closed} closed &middot;
                {$count_replies} need replies &middot;
                {$count_total} total
                </td></tr>
            </tfoot>
        </table>

    {$multipage}
      
    {$footer}
    <script type="text/javascript" src="{$mybb->settings[\'bburl\']}/inc/plugins/threadlog/threadlog.js"></script>
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
    $threadlog_row = '<tr class="{$thread_status}"><td class="{$thread_row}">{$thread_title}<div class="smalltext">on {$thread_date}</small></td>
    <td class="{$thread_row}" align="center">{$thread_participants}</td>
    <td class="{$thread_row}" align="center"><a href="javascript:MyBB.whoPosted({$tid});">{$thread_posts}</a></td>
    <td class="{$thread_row}" align="right">Last post by {$thread_latest_poster}<div class="smalltext">on {$thread_latest_date}</div></td></tr>';

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
    $threadlog_nothreads = "<tr><td colspan='4'>No threads to speak of.</td></tr>";

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

    // delete forum option
    $db->write_query("ALTER TABLE `". $db->table_prefix ."forums` DROP `threadlog_include`;");

    // delete settings
    $db->delete_query('settings', "name IN ('threadlog_perpage')");

    // delete settings group
    $db->delete_query('settinggroups', "name = 'threadlog_settings'");

    // delete templates
    $db->delete_query("templates", "title IN ('threadlog_page','threadlog_row','threadlog_nothreads')");

    // rebuild
    rebuild_settings();
}

function threadlog_activate()
{

}

function threadlog_deactivate()
{

}

// this is the main beef, right here
$plugins->add_hook('misc_start', 'threadlog');

function threadlog()
{
    global $mybb, $templates, $theme, $lang, $header, $headerinclude, $footer, $uid, $tid;

    // show the threadlog when we call it
    if($mybb->get_input('action') == 'threadlog')
    {
        global $mybb, $db, $templates;

        $templatelist = "multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";

        // check for a UID
        if(isset($mybb->input['uid']))
        {
            $uid = intval($mybb->input['uid']);
        }

        // if no UID, show logged in user
        elseif(isset($mybb->user['uid']))
        {
            $uid = $mybb->user['uid'];
        }

        else
        {
            exit;
        }

        // get the username and UID of current user
        $userquery = $db->simple_select('users', 'username', 'uid = '. $uid .'');

        // make sure single quotes are replaced so we don't muck up queries
        $username = str_replace("'", "&#39;", $db->fetch_field($userquery, 'username'));

        // add the breadcrumb
        add_breadcrumb($username .'\'s Threadlog', "misc.php?action=threadlog");

        // set up this variable, idk why?
        $threads = "";

        // get threads that this user participated in
        $query = $db->simple_select("posts", "DISTINCT tid", "uid = ".$uid."");
        $topics = "";
        
        // build our topic list
        while($row = $db->fetch_array($query))
        {
            $topics .= $row['tid'] .",";
        }

        // remove last comma
        $topics = substr_replace($topics, "", -1);

        // set up topics query
        if(isset($topics))
        {
            $tids = " AND tid IN ('". str_replace(',', '\',\'', $topics) ."')";
        }
        else
        {
            $tids = "";
        }

        // get the list of forums to include
        $query = $db->simple_select("forums", "fid", "threadlog_include = 1");
        if($db->num_rows($query) < 1)
        {
            $forum_select = " ";
        }
        else
        {
            $i = 0;
            while($forum = $db->fetch_array($query)) {
                $i++;
                if ($i > 1) {
                    $fids .= ",'". $forum['fid'] ."'";
                } else {
                    $fids .= "'". $forum['fid'] ."'";
                }
            }
            $forum_select = " AND fid IN(". $fids .")";
        }

        $count_total = 0;
        $count_closed = 0;
        $count_replies = 0;
        $count_active = 0;

        // set up the pager
        $threadlog_url = htmlspecialchars_uni("misc.php?action=threadlog&uid=". $uid);

        $per_page = intval($mybb->settings['threadlog_perpage']);

        $page = $mybb->get_input('page', MyBB::INPUT_INT);
        if($page && $page > 0)
        {
            $start = ($page - 1) * $per_page;
        }
        else
        {
            $start = 0;
            $page = 1;
        }

        $query = $db->simple_select("threads", "COUNT(*) AS threads", "visible = 1". $tids . $forum_select);
        $threadlog_total = $db->fetch_field($query, "threads");

        $multipage = multipage($threadlog_total, $per_page, $page, $threadlog_url);

        // final query
        $query = $db->simple_select("threads", "tid,fid,subject,dateline,replies,lastpost,lastposter,lastposteruid,prefix,closed", "visible = 1". $tids . $forum_select ." ORDER BY `tid` DESC LIMIT ". $start .", ". $per_page);
        if($db->num_rows($query) < 1)
        {
            eval("\$threadlog_list .= \"". $templates->get("threadlog_nothreads") ."\";");
        }
        while($thread = $db->fetch_array($query))
        {

            $count_total++;

            $tid = $thread['tid'];

            $posts_query = $db->simple_select("posts", "tid", "visible = 1 AND tid = '". $tid ."'");
            $thread_posts = $db->num_rows($posts_query);

            // set up row styles
            if($count_total % 2)
            {
                $thread_row = "trow2";
            }
            else
            {
                $thread_row = "trow1";
            }

            // set up classes for active, needs reply, and closed threads
            if($thread['closed'] == 1)
            {
                $thread_status = "closed";
                $count_closed++;
            }
            else
            {
                $count_active++;
                $thread_status = "active";

                // print($thread['lastposteruid']); print $uid; die();

                if($thread['lastposteruid'] != $uid)
                {
                    $thread_status .= " needs-reply";
                    $count_replies++;
                }
            }

            // set up thread link
            $thread_title = "<a href=\"{$mybb->settings['bburl']}/showthread.php?tid=". $thread['tid'] ."\">". $thread['subject'] ."</a>";

            // set up thread date
            $thread_date = date($mybb->settings['dateformat'], $thread['dateline']);

            // set up last poster
            $thread_latest_poster = "<a href=\"{$mybb->settings['bburl']}/member.php?action=profile&uid=". $thread['lastposteruid'] ."\">". $thread['lastposter'] ."</a>";

            // set up date of last post
            $thread_latest_date = date($mybb->settings['dateformat'], $thread['lastpost']);

            // set up thread prefix
            $thread_prefix = '';
            $query2 = $db->simple_select("threadprefixes", "prefix", "pid = ".$thread['prefix']);
            $prefix = $db->fetch_array($query2);
            if($thread['prefix'] != 0)
            {
                $thread_prefix = $prefix['prefix'];
            }
            else
            {
                $thread_prefix = "Unknown";
            }

            // set up skills/attributes, but only if it exists!
            if($db->table_exists('usernotes')) {
                $usernotes = '';
                $query5 = $db->simple_select("usernotes", "*", "tid = ". $thread['tid'] ." AND uid = ".$uid);
                $usernotes = $db->fetch_array($query5);
            }

            // set up participants
            $thread_participants = 'N/A';
            $i = 0;
            $query4 = $db->simple_select("posts", "DISTINCT uid, username", "tid = ". $thread['tid'] ." AND uid != '". $uid ."'");
            while($participant = $db->fetch_array($query4)) {
                $i++;
                if($i == 1) {
                    $thread_participants = "<a href=\"{$mybb->settings['bburl']}/member.php?action=profile&uid=". $participant['uid'] ."\">". $participant['username'] ."</a>";
                } else {
                    $thread_participants .= ", <a href=\"{$mybb->settings['bburl']}/member.php?action=profile&uid=". $participant['uid'] ."\">". $participant['username'] ."</a>";
                }
            }

            // add the row to the list
            eval("\$threadlog_list .= \"".$templates->get("threadlog_row")."\";");

        } // end while

        eval("\$threadlog_page = \"".$templates->get("threadlog_page")."\";");

        output_page($threadlog_page);

        exit;

    } // end threadlog action
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

    // print_r($mybb->input['threadlog_include']); die();

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