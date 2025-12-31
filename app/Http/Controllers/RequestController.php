<?php
namespace App\Http\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use App\Services\NotificationService;

defined( 'ABSPATH' ) || exit;

class RequestController {

    public static function index( WP_REST_Request $request ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ou_requests';
        
        // Check if user is admin/HR
        $user_id = get_current_user_id();
        $is_admin = current_user_can( 'manage_options' );

        if ( $is_admin ) {
            // HR sees all requests
            $results = $wpdb->get_results( "SELECT r.*, u.display_name as user_name FROM $table_name r LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID ORDER BY r.created_at DESC" );
        } else {
            // Regular users see only their own
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC", $user_id ) );
        }

        return new WP_REST_Response( $results, 200 );
    }

    public static function update( WP_REST_Request $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response( [ 'message' => 'Unauthorized' ], 403 );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ou_requests';
        $id = $request->get_param( 'id' );
        $params = $request->get_json_params();

        $data = [];
        if ( isset( $params['status'] ) ) {
            $data['status'] = sanitize_text_field( $params['status'] );
        }
        if ( isset( $params['priority'] ) ) {
            $data['priority'] = sanitize_text_field( $params['priority'] );
        }

        if ( empty( $data ) ) {
             return new WP_REST_Response( [ 'message' => 'No fields to update' ], 400 );
        }

        $data['updated_at'] = current_time( 'mysql' );

        $updated = $wpdb->update( 
            $table_name, 
            $data, 
            [ 'id' => $id ], 
            [ '%s', '%s', '%s' ], 
            [ '%d' ] 
        );

        if ( $updated !== false ) {
            // Fetch updated record
            $item = $wpdb->get_row( $wpdb->prepare( "SELECT r.*, u.display_name as user_name FROM $table_name r LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID WHERE r.id = %d", $id ) );
            
            // Notify user if status changed
            if ( isset( $data['status'] ) ) {
                NotificationService::notify_user_status_update( 'request', $item );
            }

            return new WP_REST_Response( $item, 200 );
        }

        return new WP_REST_Response( [ 'message' => 'Error updating request' ], 500 );
    }

    public static function get_notes( WP_REST_Request $request ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ou_notes';
        $request_id = $request->get_param( 'id' );

        $notes = $wpdb->get_results( $wpdb->prepare( 
            "SELECT n.*, u.display_name as user_name FROM $table_name n LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID WHERE n.request_id = %d ORDER BY n.created_at ASC", 
            $request_id 
        ) );

        return new WP_REST_Response( $notes, 200 );
    }

    public static function add_note( WP_REST_Request $request ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ou_notes';
        $request_id = $request->get_param( 'id' );
        $params = $request->get_json_params();

        if ( empty( $params['note'] ) ) {
            return new WP_REST_Response( [ 'message' => 'Note cannot be empty' ], 400 );
        }

        $user_id = get_current_user_id();

        $data = [
            'request_id' => $request_id,
            'user_id'    => $user_id,
            'note'       => sanitize_textarea_field( $params['note'] ),
            'created_at' => current_time( 'mysql' )
        ];

        $inserted = $wpdb->insert( $table_name, $data );

        if ( $inserted ) {
            $data['id'] = $wpdb->insert_id;
            $data['user_name'] = wp_get_current_user()->display_name;
            
            // Notify involved parties about the note? (Optional future enhancement)
            
            return new WP_REST_Response( $data, 201 );
        }

        return new WP_REST_Response( [ 'message' => 'Error adding note' ], 500 );
    }

    public static function store( WP_REST_Request $request ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ou_requests';

        $user_id = get_current_user_id();

        // Check if user is a team member or admin
        $is_member = get_user_meta( $user_id, 'ou_is_member', true );
        if ( ! $is_member && ! current_user_can( 'manage_options' ) ) {
             return new WP_REST_Response( [ 'message' => 'You must be added to the team to make requests.' ], 403 );
        }

        $params = $request->get_json_params();

        // Validation
        if ( empty( $params['title'] ) || empty( $params['description'] ) ) {
            return new WP_REST_Response( [ 'message' => 'Missing fields' ], 400 );
        }

        $reminder_date = null;
        if ( ! empty( $params['reminder_days'] ) && is_numeric( $params['reminder_days'] ) ) {
            // Check global permission
            $reminders_enabled = get_option( 'ou_reminders_enabled', '0' ) === '1';
            if ( $reminders_enabled ) {
                $days = intval( $params['reminder_days'] );
                if ( $days > 0 ) {
                    $reminder_date = date( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) );
                }
            }
        }

        $data = [
            'user_id'     => $user_id,
            'title'       => sanitize_text_field( $params['title'] ),
            'description' => sanitize_textarea_field( $params['description'] ),
            'priority'    => sanitize_text_field( $params['priority'] ?? 'medium' ),
            'status'      => 'pending',
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
            'reminder_date' => $reminder_date
        ];

        $inserted = $wpdb->insert( $table_name, $data );

        if ( $inserted ) {
            $data['id'] = $wpdb->insert_id;
            
            // Schedule Single Action via Action Scheduler
            if ( $reminder_date && function_exists( 'as_schedule_single_action' ) ) {
                $timestamp = strtotime( $reminder_date );
                if ( $timestamp > time() ) {
                    as_schedule_single_action( $timestamp, 'ou_send_single_reminder_request', [ 'request_id' => $data['id'] ] );
                }
            }
            
            // Send Notification
            NotificationService::notify_admin( 'request', $data, $user_id );

            return new WP_REST_Response( $data, 201 );
        }

        return new WP_REST_Response( [ 'message' => 'Error saving request', 'db_error' => $wpdb->last_error ], 500 );
    }
}
