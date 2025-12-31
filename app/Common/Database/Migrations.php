<?php
namespace App\Common\Database;

defined( 'ABSPATH' ) || exit;

class Migrations {

    public static function run() {
        self::create_requests_table();
        self::create_expenses_table();
        self::create_notes_table();
    }

    private static function create_requests_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ou_requests';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            priority varchar(20) DEFAULT 'medium' NOT NULL,
            status varchar(20) DEFAULT 'pending' NOT NULL,
            reminder_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY reminder_date (reminder_date)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    private static function create_expenses_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ou_expenses';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'USD' NOT NULL,
            category varchar(50) NOT NULL,
            description text,
            receipt_url varchar(255),
            status varchar(20) DEFAULT 'pending' NOT NULL,
            reminder_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY reminder_date (reminder_date)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    private static function create_notes_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ou_notes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            request_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            note text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY request_id (request_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}
