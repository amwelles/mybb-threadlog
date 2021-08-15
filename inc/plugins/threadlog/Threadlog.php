<?php

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

class Threadlog {
    private $uid;

    public function __construct($uid) {
        $this->uid = $uid;
    }
    /**
     * Helper function that prints a json response
     * @param int $status Status code to output
     * @param mixed $data Data to output
     */
    public static function json_response($status, $data = '') {
        global $charset;
        http_response_code($status);
        header("Content-type: application/json; charset={$charset}");
        echo json_encode($data);
        exit;
    }

    /**
     * POST: xmlhttp.php?action=threadlog&update=<single|multi>&field=reorder
     */
    public function handle_reorder() {
        global $mybb, $db, $templates;
        if ($mybb->get_input('update') === 'multi') {
            $this->handle_reorder_multi();
        } else if ($mybb->get_input('update') === 'single') {
            $this->handle_reorder_single();
        }
        self::json_response(404);
    }

    /**
     * POST: xmlhttp.php?action=threadlog&update=<single|multi>&field=description
     */
    public function handle_description() {
        global $mybb, $db;
        if ($mybb->get_input('update') === 'multi') {
            $this->handle_description_multi();
        } else if ($mybb->get_input('update') === 'single') {
            $this->handle_description_single();
        }
        self::json_response(404);
    }

    /**
     * POST: xmlhttp.php?action=threadlog&update=<single|multi>&field=date
     */
    public function handle_date() {
        // todo
        self::json_response(404);
    }

    /**
    * Generate an array of template values from a given threadlog entry
    * @param array $thread Result row from database of a threadlog entry with thread details
    * @param int $entry_count This entry "number", for calculating even or odd row classes
    * @param array $participants_by_tid Pre-calculated participant array keyed by thread id
    * @param int $count_total Total count of entries, for use with calculating whether this is the last entry
    * @return array Keyed array of values
    */
    public static function row_template_values($thread, $entry_count, $participants_by_tid = null, $count_total) {
        global $mybb, $db, $templates;
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
            $participants_query = $db->write_query("SELECT distinct p.uid,p.username,u.usergroup,u.displaygroup
            from `{$db->table_prefix}posts` as p
            left join `{$db->table_prefix}users` as u on u.uid=p.uid
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
        // thread information
        $return_values['thread_title'] = "<a href=\"{$mybb->settings['bburl']}/showthread.php?tid=". $thread['tid'] ."\">". $thread['subject'] ."</a>";
        // date
        $date = $mybb->settings['threadlog_dateoverride'] && !empty($thread['dateoverride']) ? $thread['dateoverride'] : $thread['dateline'];
        $return_values['thread_date'] = date($mybb->settings['dateformat'], $date);
        $return_values['thread_latest_poster'] = '<a href="'.
            get_profile_link($thread['lastposteruid']).'" target="_blank">'.
            format_name($thread['lastposter'], $thread['lastposterusergroup'], $thread['lastposterdisplaygroup']).
            "</a>";
        $return_values['thread_latest_date'] = date($mybb->settings['dateformat'], $thread['lastpost']);
        $return_values['thread_prefix'] = $thread['displaystyle'];
        // set up participant links
        if (!array_key_exists($tid, $participants_by_tid) || empty($participants_by_tid[$tid])) {
            $return_values['thread_participants'] = 'N/A';
        } else {
            $other_users = $participants_by_tid[$tid];
            $participant_links = [];
            foreach ($other_users as $other_user) {
                $participant_links[] = '<a href="'.
                    get_profile_link($other_user['uid']).'" target="_blank">'.
                    format_name($other_user['username'], $other_user['usergroup'], $other_user['displaygroup'])
                ."</a>";
            }
            $return_values['thread_participants'] = implode(", ", $participant_links);
        }
        // show description
        if ($mybb->settings['threadlog_describe']) {
            $description = $thread['description'];
            eval('$return_values[\'thread_description\'] = "'.$templates->get('threadlog_description').'";');
        }
        // show description
        if ($mybb->settings['threadlog_dateoverride']) {
            // todo: date editor
        }
        // set up thread actions
        if ($mybb->user['uid'] == $uid) {
            $thread_actions = '';
            if (intval($mybb->settings['threadlog_reorder']) == 1) {
                $threadrow = $return_values['thread_row'];
                $moveup = (intval($entry_count) !== 1 ? '<option value="up">Move Up</option>' : '');
                $movedown = (intval($entry_count) !== intval($count_total) ? '<option value="down">Move Down</option>' : '');
                eval('$thread_actions .= "'.$templates->get('threadlog_reorder_select').'";');
            }
            if (intval($mybb->settings['threadlog_describe']) == 1 || intval($mybb->settings['threadlog_dateoverride']) == 1) {
                $eid = $thread['eid'];
                eval('$thread_actions .= "'.$templates->get('threadlog_edit_thread').'";');
            }
            $return_values['thread_actions'] = $thread_actions;
            eval('$return_values[\'thread_actions_cell\'] = "'.$templates->get('threadlog_row_actions').'";');
        }
        return $return_values;
    }

    /**
     * Check that the page number is valid on a threadlog page
     * @return boolean
     */
    private function is_page_valid($page) {
        global $mybb, $db;
        $query = $db->simple_select('threadlogentry', 'COUNT(eid) as count', 'uid='. $this->uid);
        $count = intval($db->fetch_field($query, 'count'));
        $perpage = intval($mybb->settings['threadlog_perpage']);
        // if page is greater than the max or less than 1, it's not valid
        if ($page > ceil($count / $perpage) || $page < 1) {
            return false;
        }
        return true;
    }

    /**
     * Reorder multiple entries at once - this is what happens after drag and drop
     * Input: 'threadlogEntries' - a list of entry ids
     * Output: The entries on this page are updated to match the order of the sent ids
     */
    private function handle_reorder_multi() {
        global $mybb, $db;
        $page = intval($mybb->input['page']);
        $entries = $mybb->input['threadlogEntries'];

        if (!$this->is_page_valid($page) || empty($entries)) {
            self::json_response(403, "Information sent was not valid");
        }

        $update_values = [];
        // stitch them into a query with the new given roworder
        foreach ($entries as $i => $entry) {
            $update_values[] = sprintf("(%d, %d)", $entry, $i);
        }

        // Using an insert duplicate key to do a mass update so we don't run N queries
        $db->write_query("INSERT into `". $db->table_prefix ."threadlogentry` (eid, roworder)
            VALUES ".implode(", ", $update_values)." ON DUPLICATE KEY UPDATE roworder=VALUES(roworder)");
        self::json_response(200, $update_values);
    }

    /**
     * Reorder a single threadlog entry by moving it up or down
     *
     * Input: entry - the entry ID to move, direction - either 'up' or 'down', template - whether to return a template or not
     * Output: the entry sent is swapped with the next or previous one, and the entry that was swapped is sent back as html
     */
    private function handle_reorder_single() {
        global $mybb, $db;
        $eid = $mybb->input['entry'];
        $direction = $mybb->input['direction'];
        if ($direction !== 'up' && $direction !== 'down') {
            Threadlog::json_response(403, "Direction must be up or down, got '{$direction}'");
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
            ORDER BY roworder ".($direction === 'up' ? 'DESC' : 'ASC')."
            LIMIT 1");

        // don't let the user try to move the first or last one up/down
        if (!$db->num_rows($query)) {
            self::json_response(403, "Failed to move {$eid}");
        }

        $entry_to_swap = $db->fetch_array($query);
        $current_roworder = $direction === 'up' ? $entry_to_swap['roworder'] + 1 : $entry_to_swap['roworder'] - 1;

        // perform the swap
        $db->write_query("INSERT into `{$db->table_prefix}threadlogentry` (eid, roworder)
            VALUES ({$eid}, {$entry_to_swap['roworder']}), ({$entry_to_swap['eid']}, $current_roworder) ON DUPLICATE KEY UPDATE roworder=VALUES(roworder)");
        // did the API request ask for a new template?
        if ($mybb->input['template']) {
            // get the total count
            $query = $db->write_query("SELECT count(e.eid) as total from `{$db->table_prefix}threadlogentry` as e
                left join `{$db->table_prefix}threads` as t on t.tid=e.tid
                left join `{$db->table_prefix}forums` as f on f.fid=t.fid
                where t.visible and f.threadlog_include and e.uid={$entry_to_swap['uid']}");
            $count_total = $db->fetch_field($query, 'count');
            $template_row = threadlog_row_template_values($entry_to_swap,
                ($mybb->input['template'] == 'up' ? 0 : $mybb->settings['threadlog_perpage']),
                null,
                $count_total
            );
            extract($template_row);
            eval("\$threadlog_row = \"".$templates->get("threadlog_row")."\";");
            self::json_response(200, $threadlog_row);
        }
        self::json_response(200, $entry_to_swap);
    }

    /**
     * Update a single description
     */
    private function handle_description_single() {
        global $mybb, $db;
        $eid = $db->escape_string($mybb->input['entry']);
        $description = $db->escape_string(htmlspecialchars_uni($mybb->input['description']));
        if ($db->update_query('threadlogentry', ['description' => $description], "eid={$eid}") > 0) {
            self::json_response(200);
        }
        self::json_response(400, "Update failed.");
    }

    private function handle_description_multi() {
        global $mybb, $db;
        if (!$mybb->input['entries']) self::json_response(400, "No entries");
        // sanitize
        $entries = [];
        foreach ($mybb->input['entries'] as $eid=>$description) {
            $entries[intval($eid)] = $db->escape_string(htmlspecialchars_uni($description));
        }
        // validate
        $query = $db->simple_select('threadlogentry', 'eid', 'eid in ('.implode(array_keys($entries)).')');
        if ($db->num_rows($query) !== count($entries)) self::json_response(400, "At least one entry identifier is invalid.");

        // mass update
        $update_values = [];
        foreach ($entries as $eid=>$description) {
            $update_values[] = sprintf("(%d, %d)", $eid, $description);
        }
        $db->write_query("INSERT into `". $db->table_prefix ."threadlogentry` (eid, description)
            VALUES ".implode(", ", $update_values)." ON DUPLICATE KEY UPDATE description=VALUES(description)");
        self::json_response(200, $update_values);
    }

    private function handle_date_single() {

    }

    private function handle_date_multi() {

    }
}
