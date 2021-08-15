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

        <table id="threadlog" class="tborder" border="0" cellpadding="{$theme[\'tablespace\']}" cellspacing="{$theme[\'borderwidth\']}" {$threadlog_settings}>
            <thead>
                <tr>
                    <td class="thead" colspan="{$threadlog_columns}">{$username}\'s Threadlog &middot; <a href="{$mybb->settings[\'bburl\']}/member.php?action=profile&uid={$uid}">View Profile</a>
                    <div class="postbit_buttons">{$threadlog_buttons}</div>
                    </td>
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
                <tr><td id="threadlog-toggles" class="tfoot" colspan="{$threadlog_columns}" align="center">
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
    <script type="text/javascript" src="{$mybb->settings[\'bburl\']}/inc/plugins/threadlog/jquery-ui.min.js"></script>
    <script type="text/javascript" src="{$mybb->settings[\'bburl\']}/inc/plugins/threadlog/threadlog-edit.js"></script>
</body>
</html>',

    'nothreads' => '
    <tr><td colspan="{$threadlog_columns}">No threads to speak of.</td></tr>',

    'row' => '
    <tr class="threadlogrow {$thread_status}" data-entry="{$eid}">
        <td class="{$thread_row}">{$thread_prefix} {$thread_title}<div class="smalltext">on {$thread_date}</small>{$thread_description}</td>
        <td class="{$thread_row}" align="center">{$thread_participants}</td>
        <td class="{$thread_row}" align="center"><a href="javascript:MyBB.whoPosted({$tid});">{$thread_posts}</a></td>
        <td class="{$thread_row}" align="right">Last post by {$thread_latest_poster}<div class="smalltext">on {$thread_latest_date}</div></td>
        {$thread_actions_cell}
    </tr>',

    'actions_header' => '
        <td class="tcat" align="right">Actions</td>',

    'row_actions' => '
        <td class="{$thread_row}" align="right">{$thread_actions}</td>',

    'action_buttons' => '
        <a href="#" id="edit-threadlog-btn">Edit</a>
        <a href="#" id="save-threadlog-btn" style="display: none">Save</a>
        <a href="#" id="cancel-threadlog-btn" style="display: none">Cancel</a>
    ',

    'reorder_select' => '
        <select class="threadrow-reorder">
            <option value="">Reorder</option>
            {$moveup}
            {$movedown}
        </select>
    ',

    'description' => '
        <div class="smalltext description">{$description}</div>
        <input type="text" class="textbox" name="description" value="{$description}" placeholder="Description" style="display: none" />
    ',

    'edit_thread' => '
    <a href="#" class="edit-single">Edit</a>
    <a href="#" class="edit-single-save" style="display: none">Save</a>
    <a href="#" class="edit-single-cancel" style="display: none">Cancel</a>
    ',

    'set_filter_input' => '
        <div style="text-align: right">
            <input type="checkbox" id="set-threadlog-filter" /> Always use <span></span> filter on page load
        </div>
    '
];