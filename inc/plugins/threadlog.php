<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("member_profile_start", "threadlog_profile");

function threadlog_info()
{
	/**
	 * Array of information about the plugin.
	 * name: The name of the plugin
	 * description: Description of what the plugin does
	 * website: The website the plugin is maintained at (Optional)
	 * author: The name of the author of the plugin
	 * authorsite: The URL to the website of the author (Optional)
	 * version: The version number of the plugin
	 * guid: Unique ID issued by the MyBB Mods site for version checking
	 * compatibility: A CSV list of MyBB versions supported. Ex, "121,123", "12*". Wildcards supported.
	 */
	return array(
		"name"			=> "Threadlog",
		"description"	=> "Creates dynamic threadlogs linked from each user's profile.",
		"website"		=> "https://github.com/amwelles/mybb-threadlog",
		"author"		=> "Autumn Welles",
		"authorsite"	=> "http://novembird.com/mybb/",
		"version"		=> "0.7",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}

/**
 * ADDITIONAL PLUGIN INSTALL/UNINSTALL ROUTINES
 *
 * _install():
 *   Called whenever a plugin is installed by clicking the "Install" button in the plugin manager.
 *   If no install routine exists, the install button is not shown and it assumed any work will be
 *   performed in the _activate() routine.
 *
 * function hello_install()
 * {
 * }
 *
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
 *
 * function hello_is_installed()
 * {
 *		global $db;
 *		if($db->table_exists("hello_world"))
 *  	{
 *  		return true;
 *		}
 *		return false;
 * }
 *
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
 *
 * function hello_uninstall()
 * {
 * }
 *
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    "visible" by adding templates/template changes, language changes etc.
 *
 * function hello_activate()
 * {
 * }
 *
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially "hide" the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
 *
 * function hello_deactivate()
 * {
 * }
 */

function threadlog_activate() {
	global $db, $lang;

	// create settings group
	$settingarray = array(
		'name' => 'threadlog',
		'title' => 'Threadlog',
		'description' => 'Settings for profile threadlogs.',
		'disporder' => 100,
		'isdefault' => 0
	);

	$gid = $db->insert_query("settinggroups", $settingarray);

	// add settings
	$setting0 = array(
		"sid" => NULL,
		"name" => "threadlog_hidden",
		"title" => "Excluded Forums",
		"description" => "Enter the forum IDs, separated by a comma, of forums that should be hidden on the threadlog. Leave blank to disable.",
		"optionscode" => "text",
		"value" => NULL,
		"disporder" => 1,
		"gid" => $gid
	);

	$db->insert_query("settings", $setting0);

	$setting1 = array(
		"sid" => NULL,
		"name" => "threadlog_archive",
		"title" => "Archive Forums",
		"description" => "Enter the forum IDs, separated by a comma, of forums that are considered archive forums. Leave blank to disable.",
		"optionscode" => "text",
		"value" => NULL,
		"disporder" => 2,
		"gid" => $gid
	);

	$db->insert_query("settings", $setting1);

	$setting2 = array(
		"sid" => NULL,
		"name" => "threadlog_dead",
		"title" => "Dead Forums",
		"description" => "Enter the forum IDs, separated by a comma, of forums that are considered dead thread forums. Leave blank to disable.",
		"optionscode" => "text",
		"value" => NULL,
		"disporder" => 3,
		"gid" => $gid
	);

	$db->insert_query("settings", $setting2);

	$setting3 = array(
		"sid" => NULL,
		"name" => "threadlog_prefix",
		"title" => "Show thread prefix?",
		"description" => "Choose \'yes\' to show the thread prefix (useful if you want to show location).",
		"optionscode" => "yesno",
		"value" => "0",
		"disporder" => 4,
		"gid" => $gid
	);

	$db->insert_query("settings", $setting3);

	rebuild_settings();

	// set up templates
	$template0 = array(
		"tid" => NULL,
		"title" => "threadlog",
		"template" => $db->escape_string('<html>
<head>
<title>{$title}</title>
{$headerinclude}
<style>
.active a:first-child {
font-weight: bold;
}
.dead {
text-decoration: line-through;
}
.dead .lastposter, .archived .lastposter {
display: none;
}
</style>
</head>
<body>
{$header}

<ol>
{$threads}
</ol>

{$footer}
</body>
</html>'),
		"sid" => "-1"
	);
	$db->insert_query("templates", $template0);
	
	$template1 = array(
		"tid" => NULL,
		"title" => "threadlog_row",
		"template" => $db->escape_string('<li class="{$class}">{$prefix} {$threadlink} <small>({$participants})</small> <span class="lastposter">&mdash; Last post on {$lastpostdate} by {$lastposter}</span></li>'),
		"sid" => "-1"
	);
	$db->insert_query("templates", $template1);

	$template2 = array(
		"tid" => NULL,
		"title" => "threadlog_nothreads",
		"template" => $db->escape_string('<li class="none">No threads to speak of.</li>'),
		"sid" => "-1"
	);
	$db->insert_query("templates", $template2);

	// edit templates
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

	// creates a link under online status
	find_replace_templatesets('member_profile', '#'.preg_quote('{$online_status}').'#', '{$online_status}<br />'."\n".'<strong>Threadlog:</strong> <a href="member.php?action=profile&show=threadlog&uid={$uid}">Click me!</a>');
}

function threadlog_deactivate() {
	global $db, $mybb;

	// delete settings group
	$db->delete_query("settinggroups", "name = 'threadlog'");

	// remove settings
	$db->delete_query("settings", 'name IN ( \'threadlog_hidden\',\'threadlog_archive\',\'threadlog_dead\',\'threadlog_prefix\' )');

	rebuild_settings();

	// delete templates
	$db->delete_query('templates', 'title IN ( \'threadlog\',\'threadlog_row\',\'threadlog_nothreads\' )');

	// edit templates
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('member_profile', '#'.preg_quote('<br />'."\n".'<strong>Threadlog:</strong> <a href="member.php?action=profile&show=threadlog&uid={$uid}">Click me!</a>').'#', " ", 0);
}


function threadlog_profile() {
	global $mybb, $lang, $db, $threadlog, $templates, $header, $footer, $headerinclude, $title, $theme;

	if($mybb->input['uid'] != '') {
		$uid = intval($mybb->input['uid']);
	} else {
		$uid = $mybb->user['uid'];
	}

	$userquery = $db->simple_select('users', 'username', 'uid = '.$uid.'');
	$username = str_replace("'", "&#39;", $db->fetch_field($userquery, 'username'));

	if($mybb->input['show'] == "threadlog") {

		global $threads, $bgcolor;

		$threads = '';

		// add a title
		$title = $lang->sprintf($mybb->settings['bbname'].' - Threadlog of '.$username.'');
		
		// add some breadcrumbs
		add_breadcrumb("<a href='/member.php?action=profile&uid=". $uid ."'>Profile of ". $username ."</a>");
		add_breadcrumb("Threadlog");

		// query the posts table for the threads a user is involved in
		$query = $db->simple_select("posts", "DISTINCT tid", "uid = ".$uid."");
		$topics = "";
		while($row = $db->fetch_array($query)) {
			$topics .= $row['tid'].",";
		}
		// remove last comma
		$topics = substr_replace($topics, "", -1);

		// set up archived forums array
		if($mybb->settings['threadlog_archive'] != '') {
			$archived = explode(",", $mybb->settings['threadlog_archive']);
		}
		if($mybb->settings['threadlog_dead'] != '') {
			$dead = explode(",", $mybb->settings['threadlog_dead']);
		}

		if(isset($topics)) {
			$foo = " AND tid IN ('". str_replace(',', '\',\'', $topics) ."')";
		} else {
			$foo = '';
		}

		if(($mybb->settings['threadlog_hidden']) != '' && isset($mybb->settings['threadlog_hidden'])) {
			$bar = " AND fid NOT IN ('". str_replace(',', '\',\'', $mybb->settings['threadlog_hidden']) ."')";
		} else {
			$bar = '';
		}
		
		// query the threads table for the active/archived/dead threads, excluding the hidden forums
		$query = $db->simple_select("threads", "tid,fid,subject,lastpost,lastposter,prefix", "visible = '1'".$foo.$bar);
		if($db->num_rows($query) < 1) {
			eval("\$threads .= \"".$templates->get("threadlog_nothreads")."\";");
		}
		while($thread = $db->fetch_array($query)) {

			$count_total++;

			// set up classes for archived, dead, and active rows
			if(isset($archived) && in_array($thread['fid'], $archived)) {
				$class = 'archived'; $count_archived++; }
			elseif(isset($dead) && in_array($thread['fid'], $dead)) {
				$class = 'dead'; $count_dead++; }
			else {
				$class = 'active'; $count_active++; }

			// set up thread link
			$threadlink = '<a href="showthread.php?tid='.$thread['tid'].'">'.$thread['subject'].'</a>';

			// set up last poster
			$lastposter = $thread['lastposter'];

			// set up last post date
			$lastpostdate = date($mybb->settings['dateformat'], $thread['lastpost']);

			// set up thread prefix, but only if we want it to
			$prefix = '';
			if($mybb->settings['threadlog_prefix'] == '1') {
				$query2 = $db->simple_select("threadprefixes", "prefix", "pid = ".$thread['prefix']);
				$prefix = $db->fetch_array($query2);
				$prefix = $prefix['prefix'];
			}

			// set up xthreads, but only if it exists!
			if($db->table_exists('threadfields_data')) {
				$xthreads = '';
				$query3 = $db->simple_select("threadfields_data", "*", "tid = ".$thread['tid']);
				$xthreads = $db->fetch_array($query3);
			}

			// set up skills/attributes, but only if it exists!
			if($db->table_exists('usernotes')) {
				$usernotes = '';
				$query5 = $db->simple_select("usernotes", "*", "tid = ". $thread['tid'] ." AND uid = ".$uid);
				$usernotes = $db->fetch_array($query5);
			}

			// set up participants
			$participants = '';
			$i = 0;
			$query4 = $db->simple_select("posts", "DISTINCT username", "tid = ". $thread['tid'] ." AND username != '". $username ."'");
			while($participant = $db->fetch_array($query4)) {
				$i++;
				if($i == 1) {
					$participants .= $participant['username'];
				} else {
					$participants .= ', '.$participant['username'];
				}
			}

			eval("\$threads .= \"".$templates->get("threadlog_row")."\";");
		
		}

		if(!isset($count_dead)) {
			$count_dead = 0;
		}

		if(!isset($count_active)) {
			$count_active = 0;
		}

		if(!isset($count_archived)) {
			$count_archived = 0;
		}

		if(!isset($count_total)) {
			$count_total = 0;
		}

		eval("\$threadlog_page = \"".$templates->get("threadlog")."\";");

		output_page($threadlog_page);

		exit;
	}
}
?>
