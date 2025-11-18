<?php
// Admin UI for Distribution Lists (PostNotification custom lists)

if ( ! defined( 'ABSPATH' ) ) { exit; }

function post_notification_admin_sub() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'post_notification' ), 403 );
    }

    global $wpdb;
    $t_lists = $wpdb->prefix . 'post_notification_lists';
    $t_rel   = $wpdb->prefix . 'post_notification_list_users';

    $action = isset($_GET['subaction']) ? sanitize_text_field($_GET['subaction']) : '';
    $slug   = isset($_GET['slug']) ? sanitize_key($_GET['slug']) : '';
    $export = isset($_GET['export']) ? sanitize_text_field($_GET['export']) : '';

    // Detail: members of a list (with optional CSV export)
    if ( $action === 'detail' && $slug !== '' ) {
        $list_id = function_exists('pn_list_get_id_by_slug') ? pn_list_get_id_by_slug( $slug ) : 0;
        if ( ! $list_id ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'List not found.', 'post_notification' ) . '</p></div>';
            return;
        }

        // Fetch list info
        $name = function_exists('pn_list_get_name_by_slug') ? pn_list_get_name_by_slug( $slug ) : $slug;

        // Pagination
        $paged   = max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1);
        $perPage = 50;
        $offset  = ($paged - 1) * $perPage;

        // Total count
        $total = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$t_rel} WHERE list_id=%d", $list_id ) );

        // Export CSV
        if ( $export === 'csv' ) {
            $rows = $wpdb->get_results( $wpdb->prepare("SELECT user_id FROM {$t_rel} WHERE list_id=%d ORDER BY user_id ASC", $list_id), ARRAY_A );
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="pn-list-'.esc_attr($slug).'-members.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, array('user_id','user_email','display_name'));
            foreach ( (array)$rows as $r ) {
                $u = get_user_by('id', intval($r['user_id']));
                fputcsv($out, array(
                    intval($r['user_id']),
                    $u ? $u->user_email : '',
                    $u ? $u->display_name : ''
                ));
            }
            fclose($out);
            exit;
        }

        // Paged members
        $user_ids = $wpdb->get_col( $wpdb->prepare("SELECT user_id FROM {$t_rel} WHERE list_id=%d ORDER BY user_id ASC LIMIT %d OFFSET %d", $list_id, $perPage, $offset ) );

        echo '<div class="wrap">';
        echo '<h2>'.esc_html(sprintf(__('Distribution list: %s','post_notification'), $name)).'</h2>';
        echo '<p>'.esc_html(sprintf(__('Slug: %s','post_notification'), $slug)).'</p>';
        echo '<p>'.esc_html(sprintf(__('Members: %d','post_notification'), $total)).' | ';
        $csv_url = esc_url( add_query_arg( array('page'=>'post_notification/admin.php','action'=>'lists','subaction'=>'detail','slug'=>$slug,'export'=>'csv'), admin_url('admin.php') ) );
        echo '<a class="button button-secondary" href="'.$csv_url.'">'.esc_html__('Export CSV','post_notification').'</a></p>';

        echo '<table class="widefat fixed striped"><thead><tr><th>'.esc_html__('User','post_notification').'</th><th>'.esc_html__('Email','post_notification').'</th></tr></thead><tbody>';
        if ( ! empty( $user_ids ) ) {
            foreach ( $user_ids as $uid ) {
                $u = get_user_by('id', intval($uid));
                if ( ! $u ) continue;
                $profile = esc_url( get_edit_user_link( $u->ID ) );
                echo '<tr><td><a href="'.$profile.'">'.esc_html($u->display_name).' (ID '.$u->ID.')</a></td><td>'.esc_html($u->user_email).'</td></tr>';
            }
        } else {
            echo '<tr><td colspan="2">'.esc_html__('No members in this list.','post_notification').'</td></tr>';
        }
        echo '</tbody></table>';

        // Pagination links
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ( $totalPages > 1 ) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ( $p = 1; $p <= $totalPages; $p++ ) {
                $url = esc_url( add_query_arg( array('page'=>'post_notification/admin.php','action'=>'lists','subaction'=>'detail','slug'=>$slug,'paged'=>$p), admin_url('admin.php') ) );
                $class = $p === $paged ? ' class="page-numbers current"' : ' class="page-numbers"';
                echo '<a'.$class.' href="'.$url.'">'.$p.'</a> ';
            }
            echo '</div></div>';
        }

        echo '<p><a class="button" href="'.esc_url( admin_url('admin.php?page=post_notification/admin.php&action=lists') ).'">'.esc_html__('Back to lists','post_notification').'</a></p>';
        echo '</div>';
        return;
    }

    // Overview of lists
    $lists = function_exists('pn_list_get_lists') ? pn_list_get_lists() : array();

    echo '<div class="wrap">';
    echo '<h2>'.esc_html__('Distribution lists','post_notification').'</h2>';

    if ( empty( $lists ) ) {
        echo '<p>'.esc_html__('No lists found. They will be created automatically on first sync.','post_notification').'</p>';
        echo '</div>';
        return;
    }

    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th>'.esc_html__('Name','post_notification').'</th>';
    echo '<th>'.esc_html__('Slug','post_notification').'</th>';
    echo '<th>'.esc_html__('Members','post_notification').'</th>';
    echo '<th>'.esc_html__('Actions','post_notification').'</th>';
    echo '</tr></thead><tbody>';

    foreach ( (array)$lists as $l ) {
        $lid  = intval($l['id']);
        $slug = sanitize_key($l['slug']);
        $name = sanitize_text_field($l['name']);
        $cnt  = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$t_rel} WHERE list_id=%d", $lid ) );
        $detail = esc_url( add_query_arg( array('page'=>'post_notification/admin.php','action'=>'lists','subaction'=>'detail','slug'=>$slug), admin_url('admin.php') ) );
        $csv    = esc_url( add_query_arg( array('page'=>'post_notification/admin.php','action'=>'lists','subaction'=>'detail','slug'=>$slug,'export'=>'csv'), admin_url('admin.php') ) );
        echo '<tr>';
        echo '<td>'.esc_html($name).'</td>';
        echo '<td><code>'.esc_html($slug).'</code></td>';
        echo '<td>'.esc_html($cnt).'</td>';
        echo '<td><a class="button" href="'.$detail.'">'.esc_html__('View members','post_notification').'</a> ';
        echo '<a class="button button-secondary" href="'.$csv.'">'.esc_html__('CSV','post_notification').'</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}
