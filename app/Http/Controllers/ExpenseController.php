<?php
namespace App\Http\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use App\Services\NotificationService;

defined( 'ABSPATH' ) || exit;

class ExpenseController {

    public static function index( WP_REST_Request $request ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ou_expenses';
        $user_id = get_current_user_id();
        $is_admin = current_user_can( 'manage_options' );
        
        if ( $is_admin ) {
            // HR sees all expenses
            $results = $wpdb->get_results( "SELECT e.*, u.display_name as user_name FROM $table_name e LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID ORDER BY e.created_at DESC" );
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
        $table_name = $wpdb->prefix . 'ou_expenses';
        $id = $request->get_param( 'id' );
        $params = $request->get_json_params();

        $data = [];
        if ( isset( $params['status'] ) ) {
            $data['status'] = sanitize_text_field( $params['status'] );
        }

        if ( empty( $data ) ) {
             return new WP_REST_Response( [ 'message' => 'No fields to update' ], 400 );
        }

        $data['updated_at'] = current_time( 'mysql' );

        $updated = $wpdb->update( 
            $table_name, 
            $data, 
            [ 'id' => $id ], 
            [ '%s', '%s' ], 
            [ '%d' ] 
        );

        if ( $updated !== false ) {
            // Fetch updated record
             $item = $wpdb->get_row( $wpdb->prepare( "SELECT e.*, u.display_name as user_name FROM $table_name e LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID WHERE e.id = %d", $id ) );
            
             // Notify user if status changed
            if ( isset( $data['status'] ) ) {
                NotificationService::notify_user_status_update( 'expense', $item );
            }
             
            return new WP_REST_Response( $item, 200 );
        }

        return new WP_REST_Response( [ 'message' => 'Error updating expense' ], 500 );
    }

    public static function store( WP_REST_Request $request ) {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'ou_expenses';

            $user_id = get_current_user_id();
            
            // Check if user is a team member or admin
            $is_member = get_user_meta( $user_id, 'ou_is_member', true );
            if ( ! $is_member && ! current_user_can( 'manage_options' ) ) {
                 return new WP_REST_Response( [ 'message' => 'You must be added to the team to submit expenses.' ], 403 );
            }

            $params = $request->get_params(); // get_params() retrieves body (including multipart) and query params

            // Fallback for multipart/form-data where params might be in $_POST
            if ( empty( $params['amount'] ) && ! empty( $_POST['amount'] ) ) {
                $params = array_merge( $params, $_POST );
            }

            // Validation
            if ( empty( $params['amount'] ) || empty( $params['category'] ) ) {
                return new WP_REST_Response( [ 'message' => 'Missing fields: Amount or Category' ], 400 );
            }

            // Handle File Upload
            $receipt_url = '';
            $files = $request->get_file_params();

            // Fix for when $_FILES is populated but get_file_params() might be empty or structured differently
            if ( empty( $files ) && ! empty( $_FILES ) ) {
                $files = $_FILES;
            }

            if ( ! empty( $files['receipt'] ) ) {
                if ( ! function_exists( 'media_handle_upload' ) ) {
                    require_once( ABSPATH . 'wp-admin/includes/image.php' );
                    require_once( ABSPATH . 'wp-admin/includes/file.php' );
                    require_once( ABSPATH . 'wp-admin/includes/media.php' );
                }

                $attachment_id = media_handle_upload( 'receipt', 0 );
                
                if ( is_wp_error( $attachment_id ) ) {
                     error_log( 'OU Upload Error: ' . $attachment_id->get_error_message() );
                     // Don't fail the whole request, just log it? Or fail?
                     // Let's fail for now but with clear message
                     return new WP_REST_Response( [ 'message' => 'Upload failed: ' . $attachment_id->get_error_message() ], 400 );
                } else {
                    $receipt_url = wp_get_attachment_url( $attachment_id );
                }
            }

            // Reminder Logic
            $reminder_date = null;
            if ( ! empty( $params['reminder_days'] ) && is_numeric( $params['reminder_days'] ) ) {
                // Verify permission (double check backend side)
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
            'amount'      => sanitize_text_field( $params['amount'] ),
            'currency'    => 'BDT', // Force BDT
            'category'    => sanitize_text_field( $params['category'] ),
                'description' => sanitize_textarea_field( $params['description'] ?? '' ),
                'receipt_url' => $receipt_url,
                'status'      => 'pending',
                'reminder_date' => $reminder_date,
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' )
            ];

            $inserted = $wpdb->insert( $table_name, $data );

            if ( $inserted ) {
                $data['id'] = $wpdb->insert_id;
                
                // Schedule Single Action via Action Scheduler
                if ( $reminder_date && function_exists( 'as_schedule_single_action' ) ) {
                    $timestamp = strtotime( $reminder_date );
                    if ( $timestamp > time() ) {
                        as_schedule_single_action( $timestamp, 'ou_send_single_reminder_expense', [ 'expense_id' => $data['id'] ] );
                    }
                }

                // Send Notification
                try {
                    NotificationService::notify_admin( 'expense', $data, $user_id );
                } catch ( \Exception $e ) {
                    error_log( 'OU Notification Error: ' . $e->getMessage() );
                }

                return new WP_REST_Response( $data, 201 );
            }

            return new WP_REST_Response( [ 'message' => 'Error saving expense: ' . $wpdb->last_error ], 500 );

        } catch ( \Exception $e ) {
            error_log( 'OU Store Exception: ' . $e->getMessage() );
            return new WP_REST_Response( [ 'message' => 'Server Error: ' . $e->getMessage() ], 500 );
        }
    }
}
