<?php
#------------------------------------------------------
# INFO
#------------------------------------------------------
# This is part of the Post Notification Plugin for
# Wordpress. Please see the readme.txt for details.
#------------------------------------------------------

class Walker_pn_CategoryDropdown extends Walker {

    public $tree_type = 'category';
    public $db_fields = array('parent' => 'category_parent', 'id' => 'cat_ID'); //TODO: decouple this

    public function start_el(&$output, $object, $depth = 0, $args = array(), $current_object_id = 0) {
        $pad = str_repeat('&nbsp;', $depth * 3);

        $cat_name = apply_filters('list_cats', $object->cat_name, $object);
        $output .= "\t<option value=\"" . $object->cat_ID . "\"";
        if (in_array($object->cat_ID, $args['sel_cat'])) {
            $output .= ' selected="selected"';
        }
        $output .= '>';
        $output .= $pad . $cat_name;
        $output .= "</option>\n";

        return $output;
    }

}

function post_notification_admin_sub() {
    global $wpdb;
    $t_emails = $wpdb->prefix . 'post_notification_emails';
    $t_cats = $wpdb->prefix . 'post_notification_cats';

    $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
    if ($action === 'remove_email') {
        $remove = true;
    } else {
        $remove = false;
    }

    echo '<h3>' . __('List of addresses:', 'post_notification') . '</h3>';

    $removeEmailChecked = filter_input(INPUT_POST, 'removeEmailChecked', FILTER_SANITIZE_SPECIAL_CHARS);
    if ($removeEmailChecked !== null) {
        if ($removeEmailChecked === null) {
            echo '<div class = "error">' . __('No address checked!', 'post_notification') . '</div>';
        } else {
            $removeEmail = filter_input(INPUT_POST, 'removeEmail', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            echo __('The following addresses were deleted:', 'post_notification') . '<br /><br />';


            foreach ($removeEmail as $removeAddress) {
                //Multiple table delete only works with mysql 4.0 or 4.1
                $wpdb->query("DELETE $t_cats, $t_emails 
					FROM $t_emails LEFT JOIN $t_cats USING (id) 
					WHERE email_addr = '$removeAddress'");
                echo "$removeAddress<br />";
            }
        }
    } else {
        $Email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($Email !== null AND strlen($Email) > 7) {
            $email = $Email;
        } else {
            $email = '*';
        }
        $categories = filter_input(INPUT_POST, 'cats', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        if ($categories !== null) {
            $sel_cats = $categories;
        } else {
            $sel_cats = '';
        }

        if (!is_array($sel_cats)) {
            $sel_cats = array();
        }

        $limit = filter_input(INPUT_POST, 'limit', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($limit === null) {
            $limit = 50;
        }

        if (!is_numeric($limit)) {
            $limit = 50;
        }
        if ($limit < 1) {
            $limit = 1;
        }

        $start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($start !== false || $start !== null) {
            //$start = $_POST['start'];
        } else {
            $start = '';
        }

        if (!is_numeric($start)) {
            $start = 0;
        }
        $next = filter_input(INPUT_POST, 'next', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($next !== null) {
            $start += $limit;
        }
        $perv = filter_input(INPUT_POST, 'perv', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($perv !== null) {
            $start -= $limit;
        }
        if ($start < 0) {
            $start = 0;
        }
        $sortby = filter_input(INPUT_POST, 'sortby', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($sortby === null or $sortby === false) {
            $sortby = 'id';
        }
        $sortorder = filter_input(INPUT_POST, 'sortorder', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($sortorder === null or $sortorder === false) {
            $sortorder = 'ASC';
        }

        $sortsrt = " $sortby $sortorder ";

        $show_idp = filter_input(INPUT_POST, 'show_id', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($show_idp !== null) {
            $show_id = true;
        }
        $show_listp = filter_input(INPUT_POST, 'show_list', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($show_listp !== null) {
            $show_list = true;
        }
        $show_unconfp = filter_input(INPUT_POST, 'show_unconf', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($show_unconfp !== null) {
            $show_unconf = true;
        }

        echo '<form method="post" action="admin.php?page=post_notification/admin.php&action=' . $action . '"> ';
        echo __('Email:', 'post_notification') . ' <input name="email" type="text" size="30" value="' . $email . '"> ';
        echo __('Cats:', 'post_notification') . ' <select name="cats[]" multiple="multiple"  style="height:auto"> ';
        $cats = get_categories();
        $walker = new Walker_pn_CategoryDropdown;
        echo call_user_func_array(array(&$walker, 'walk'), array($cats, 0, array('sel_cat' => $sel_cats)));
        echo '</select> ';
        echo __('Limit:', 'post_notification') . ' <input name="limit" type="text" size="4" value="' . $limit . '" /> ';
        echo __('Start at:', 'post_notification') . ' <input name="start" type="text" size="4" value="' . $start . '" />  ';

        echo __('Sort by:', 'post_notification') . ' <select name="sortby"  size = "1" > ' .
        '<option value="id" ' . (($sortby === 'id') ? 'selected="selected"' : '') . '>' . __('ID', 'post_notification') . '</option>' .
        '<option value="email_addr" ' . (($sortby === 'email_addr') ? 'selected="selected"' : '') . '>' . __('Address', 'post_notification') . '</option>' .
        '<option value="date_subscribed" ' . (($sortby === 'date_subscribed') ? 'selected="selected"' : '') . '>' . __('Date accepted', 'post_notification') . '</option>' .
        '<option value="subscribe_ip" ' . (($sortby === 'subscribe_ip') ? 'selected="selected"' : '') . '>' . __('IP', 'post_notification') . '</option>' .
        '</select>';
        echo ' <select name="sortorder"  size = "1" > ' .
        '<option value="ASC" ' . (($sortorder === 'ASC') ? 'selected="selected"' : '') . '>' . __('Ascending', 'post_notification') . '</option>' .
        '<option value="DESC" ' . (($sortorder === 'DESC') ? 'selected="selected"' : '') . '>' . __('Descending', 'post_notification') . '</option>' .
        '</select>';

        echo '<BR  /> ';
        echo __('Show unconfirmed mails:', 'post_notification') . ' <input name="show_unconf" type="checkbox" ';
        if ($show_unconf) {
            echo ' checked = "checked" ';
        }
        echo '/><br /> ';
        echo __('Only show cat ids:', 'post_notification') . ' <input name="show_id" type="checkbox" ';
        if ($show_id) {
            echo ' checked = "checked" ';
        }
        echo '/><br/> ';
        echo __('Show as list:', 'post_notification') . ' <input name="show_list" type="checkbox" ';
        if ($show_list) {
            echo ' checked = "checked" ';
        }
        echo '/> ';
        ?></select><br />
        <input type="submit" name="submit" value="<?php _e('Update', 'post_notification'); ?>" /><input type="submit" name="perv" value="<<--" /><input type="submit" name="next" value="-->>" />
        <form>
        <?php
        ///Ok, now let's do some work.

        if ($remove) {
            echo '<form method="post" action="admin.php?page=post_notification/admin.php&action=remove_email">';
        }


        $email = str_replace('*', '%', $email);

        $sel_cats = implode(',', $sel_cats);


        ($show_unconf) ? $wadd = ' AND gets_mail IS NULL ' : $wadd = ' AND gets_mail = 1 ';
        if ($sel_cats == '') {
            $emails = $wpdb->get_results("SELECT email_addr, gets_mail, last_modified, date_subscribed, id, act_code, subscribe_ip FROM $t_emails  WHERE email_addr LIKE '$email' $wadd ORDER BY $sortsrt LIMIT $start, $limit ");
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $t_emails  WHERE email_addr LIKE '$email' $wadd");
        } else {
            $emails = $wpdb->get_results("SELECT email_addr, gets_mail, last_modified, date_subscribed, e.id AS id, act_code, subscribe_ip FROM $t_emails e, $t_cats c WHERE email_addr LIKE '$email' $wadd AND e.id = c.id AND c.cat_id IN ($sel_cats) GROUP BY e.id ORDER BY $sortsrt LIMIT $start, $limit ");
            $total = $wpdb->get_var("SELECT COUNT(e.id)  FROM $t_emails e, $t_cats c WHERE email_addr LIKE '$email' $wadd AND e.id = c.id AND c.cat_id IN ($sel_cats)");
        }

        if (!$emails) {
            echo '<p class="error">' . __('No entries found!', 'post_notification') . '</p>';
            echo '</div>';
            return;
        }
        echo '<p>';
        echo str_replace(
                array('@@start', '@@end', '@@total'),
                array($start, $start + count($emails) - 1, $total),
                __('Showing entry @@start to @@end of @@total entries.', 'post_notification')
        );
        echo '</p>';
        if (!$show_list) {
            echo '<table class="post_notification_admin_table"><tr>';
            if ($remove) {
                echo '<th class="pn_remove"><b>&nbsp;</b></th>';
            }

            echo '<th class="pn_mail_address">' . __('Address', 'post_notification') . '</th>
                <th class="pn_user_link">' . __('User', 'user') . '</th>
                <th class="pn_subscribed_cats">' . __('Subscribed categories', 'post_notification') . '</th>
				<th class="pn_accepted"><b>' . __('Accepted', 'post_notification') . '</th>
				<th class="pn_date_accepted"><b>' . __('Date accepted', 'post_notification') . '</th>
				<th class="pn_ip">' . __('IP', 'post_notification') . '</th>
				
				</tr>';
        } else {
            echo '<br /><br />';
        }

        foreach ($emails as $email) {
            $email_addr = $email->email_addr;
            $gets_mail = $email->gets_mail;
            $wp_user_account = pn_get_wp_user_link($email_addr);
//          $last_modified = $email->last_modified;
            $datestr = get_option('date_format') . ' ' . get_option('time_format');
            $date_subscribed = post_notification_date_i18n_tz($datestr, post_notification_mysql2gmdate($email->date_subscribed));
            $id = $email->id;
            $ip = long2ip($email->subscribe_ip);

            if ($gets_mail == "1") {
                $gets_mail = __('Yes', 'post_notification');
            } else {
                $gets_mail = __('No', 'post_notification');
            }

            $modlink = post_notification_get_mailurl($email->email_addr, $email->act_code);


            $subcats_db = $wpdb->get_results("SELECT cat_id FROM $t_cats  WHERE id = " . $id . " ORDER BY cat_id ASC");
            $catnames = '';
            if (isset($subcats_db)) {
                foreach ($subcats_db as $subcat) {
                    $cat = $subcat->cat_id;
                    if ($cat == 0) {
                        if ($show_id) {
                            $catnames .= '<abbr title="' . __('All', 'post_notification') . '">0</abbr>, ';
                        } else {
                            $catnames .= __('All', 'post_notification') . ', ';
                        }
                    } else {
                        $cat = get_category($cat); //ID -> Object
                        if ($show_id) {
                            $catnames .= '<abbr title="' . $cat->cat_name . '">' . $subcat->cat_id . '</abbr>, ';
                        } else {
                            $catnames .= $cat->cat_name . ', ';
                        }
                    }
                }
                $catnames = substr($catnames, 0, -2);
            }


            if (!$show_list) {
                echo "<tr>";
                if ($remove) {
                    echo "<td class=\"pn_remove\"><input type=\"checkbox\" name=\"removeEmail[]\" value=\"$email_addr\" /></td>";
                }
                echo "<td class=\"pn_mail_address\"><a href=\"$modlink\" target=\"_blank\">$email_addr<a></td>";
                echo "<td class=\"pn_user_link\">$wp_user_account</td>";
                echo "<td class=\"pn_subscribed_cats\">$catnames</td>";
                echo "<td class=\"pn_accepted\">$gets_mail</td>";
                echo "<td class=\"pn_date_accepted\">$date_subscribed</td>";
                echo "<td class=\"pn_ip\">$ip</td>";

                echo "</tr>";
            } else {
                echo $email_addr . '<br/>';
            }
        }
        echo "</table>";
        if ($remove) {
            ?>
                <script type="text/javascript">
                    function post_notification_checkall(value) {
                        boxes = document.getElementsByName("removeEmail[]");
                        for (i = 0; i < boxes.length; i++) {
                            boxes[i].checked = value;
                        }
                    }
                </script>

            <?php
            echo '<br />' .
            '<input type="button" onclick="post_notification_checkall(true)"  value="' . __('Check all', 'post_notification') . '" />' .
            '<input type="button" onclick="post_notification_checkall(false)" value="' . __('Uncheck all', 'post_notification') . '" />' .
            '<br /> <input type="submit" name="removeEmailChecked" value="' . __('Delete', 'post_notification') . '"></form>';
        }
    }
}
?>
