<?php

if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

$templates = [
    'page' => '
<html>
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
            <tbody class="threadrow-container">
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
</html>',

    'nothreads' => '
    <tr><td colspan="{$threadlog_columns}">No threads to speak of.</td></tr>',

    'row' => '
    <tr class="threadlogrow {$thread_status}" data-entry="{$eid}">
        <td class="{$thread_row}">{$thread_prefix} {$thread_title}<div class="smalltext">on {$thread_date}</small></td>
        <td class="{$thread_row}" align="center">{$thread_participants}</td>
        <td class="{$thread_row}" align="center"><a href="javascript:MyBB.whoPosted({$tid});">{$thread_posts}</a></td>
        <td class="{$thread_row}" align="right">Last post by {$thread_latest_poster}<div class="smalltext">on {$thread_latest_date}</div></td>
        {$thread_reorder_actions}
    </tr>',

    'actions_header' => '
        <td class="tcat" align="right">Actions</td>',

    'row_actions' => '
        <td class="{$thread_row}" align="right">{$thread_actions}</td>'
];