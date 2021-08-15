<?php

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

$plugins->add_hook("admin_config_plugins_begin", "threadlog_admin_update");
function threadlog_admin_update()
{
    global $mybb, $db;

    require_once(PLUGIN_THREADLOG_ROOT . '/templates.php');

    $updated = false;
    if ($mybb->input['module'] != 'config-plugins' || $mybb->input['action'] != "update_threadlog") {
        return;
    }

    if (!$db->field_exists('dateoverride', 'threadlogentry')) {
        $db->add_column('threadlogentry', 'dateoverride', 'INT(10) default -1');

        $query = $db->simple_select('settinggroups', 'gid', 'name="threadlog_settings"');
        $gid = $db->fetch_field($query, 'gid');
        $new_settings = [
            [
                'name' => 'threadlog_describe',
                'title' => 'Threadlog Descriptions',
                'description' => 'Allow users to add descriptions to threadlog items',
                'optionscode' => 'onoff',
                'value' => false,
                'disporder' => 4,
                'gid' => $gid
            ],
            [
                'name' => 'threadlog_dateoverride',
                'title' => 'Threadlog Date Override',
                'description' => 'Allow users to specify a date for their threadlog items - this does not affect the thread for other users',
                'optionscode' => 'onoff',
                'value' => false,
                'disporder' => 5,
                'gid' => $gid
            ]
        ];
        $db->insert_query_multiple('settings', $new_settings);
        rebuild_settings();
        $updated = true;
    }

    // insert any templates we don't have yet
    $query = $db->simple_select('templates', 'tid,title,sid', 'title LIKE "threadlog%"');
    $existing_templates = [];
    $move_tids = [];
    while ($template = $db->fetch_array($query)) {
        $existing_templates[$template['title']] = true;
        if ($template['sid'] != '-2') {
            $move_tids[] = $template['tid'];
        }
    }
    // move templates to default area
    if (count($move_tids)) {
        $db->update_query('templates', ['sid' => '-2'], 'tid in ('.implode(',', $move_tids).')');
    }
    $query = $db->simple_select('templategroups', 'gid', 'prefix="threadlog"');
    if (!$db->num_rows($query)) {
        $db->insert_query('templategroups', [
            'prefix' => 'threadlog',
            'title'  => 'Threadlog',
            'isdefault' => 0
        ]);
    }

    $template_queries = [];
    foreach ($templates as $name => $template) {
        if (!$existing_templates['threadlog_'.$name]) {
            $template_queries[] = [
                'title' => 'threadlog_'.$name,
                'template' => $db->escape_string($template),
                'sid' => '-2',
                'version' => '',
                'dateline' => TIME_NOW
            ];
        }
    }

    if (count($template_queries)) {
        $db->insert_query_multiple('templates', $template_queries);
        $updated = true;
    }

    // todo: TEMPLATE CHANGES
    // todo: add the "threadlog-toggles" id to the toggles
    // todo: replace $reorderscript with:
    /**
     * <script type="text/javascript" src="{$mybb->settings[\'bburl\']}/inc/plugins/threadlog/jquery-ui.min.js"></script>
     * <script type="text/javascript" src="{$mybb->settings[\'bburl\']}/inc/plugins/threadlog/threadlog-edit.js"></script>
     */

    if (!$updated) {
        flash_message("No update needed", "success");
    } else {
        flash_message("Threadlog plugin updated", "success");
    }
    admin_redirect("index.php?module=config-plugins");
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