<?php
/**
 * Threadlog for MyBB 1.8
 * Copyright (c) 2015 amwelles
 * http://github.com/amwelles
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}


function threadlog_info()
{
  return array(
    "name"          => "Threadlog",
    "description"   => "Creates a list of threads which the user has participated in.",
    "website"       => "http://github.com/amwelles/mybb-threadlog",
    "author"        => "Autumn Welles",
    "authorsite"    => "http://github.com/amwelles",
    "version"       => "2.0",
    "guid"          => "",
    "compatibility" => "*"
  );
}

function threadlog_install()
{
	global $db, $mybb;

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
		'threadlog_forums' => array(
			'title' => 'Included forums',
			'description' => 'Add a comma-separated list of forum IDs to be included in the threadlog. (e.g. 4,8,10)',
			'optionscode' => 'text',
			'value' => '',
			'disporder' => 1,
		),
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
    <h1>{$username}\'s Threadlog</h1>

    {$multipage}
	
		<ul id="threadlog">
			{$threadlog_list}
		</ul>

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
	$threadlog_row = '<li class="{$thread_status}">{$thread_title} with {$thread_participants} on {$thread_date}<br>
	<span class="meta">Last post by {$thread_latest_poster} on {$thread_latest_date}</span></li>';

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
	$threadlog_nothreads = "<li>No threads to speak of.</li>";

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

// this is the main beef, right here
$plugins->add_hook('misc_start', 'threadlog');

function threadlog()
{
	global $mybb, $templates, $lang, $header, $headerinclude, $footer;

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

		// set up forums query
		if(($mybb->settings['threadlog_forums'] != '') && isset($mybb->settings['threadlog_forums']))
		{
			$fids = " AND fid IN ('". str_replace(',', '\',\'', $mybb->settings['threadlog_forums']) ."')";
		}
		else
		{
			$fids = "";
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

		$query = $db->simple_select("threads", "COUNT(*) AS threads", "visible = 1". $tids . $fids);
		$threadlog_total = $db->fetch_field($query, "threads");

		$multipage = multipage($threadlog_total, $per_page, $page, $threadlog_url);

		// final query
		$query = $db->simple_select("threads", "tid,fid,subject,dateline,replies,lastpost,lastposter,lastposteruid,prefix,closed", "visible = 1". $tids . $fids ." LIMIT ". $start .", ". $per_page);
		if($db->num_rows($query) < 1)
		{
			eval("\$threadlog_list .= \"". $templates->get("threadlog_nothreads") ."\";");
		}
		while($thread = $db->fetch_array($query))
		{

			$count_total++;

			// set up classes for active and closed threads
			if($thread['closed'] == 1)
			{
				$thread_status = "closed";
				$count_closed++;
			}
			if($thread['lastposteruid'] != $uid)
			{
				$thread_status = "needs-reply";
				$count_replies++;
			}
			else
			{
				$thread_status = "active";
				$count_active++;
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
			$thread_participants = 'no other participants';
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

function threadlog_is_installed()
{
	global $mybb;

	if(isset($mybb->settings['threadlog_forums']))
	{
		return true;
	}

	return false;
}

function threadlog_uninstall()
{
	global $db;

	// delete settings
	$db->delete_query('settings', "name IN ('threadlog_forums','threadlog_perpage')");

	// delete settings group
	$db->delete_query('settinggroups', "name = 'threadlog_settings'");

	// rebuild
	rebuild_settings();

	// delete templates
	$db->delete_query("templates", "title IN ('threadlog_page','threadlog_row','threadlog_nothreads')");
}

function threadlog_activate()
{

}

function threadlog_deactivate()
{

}