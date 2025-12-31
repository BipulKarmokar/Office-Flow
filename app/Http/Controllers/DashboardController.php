<?php
namespace App\Http\Controllers;

use WP_REST_Request;
use WP_REST_Response;

class DashboardController {
    
    /**
     * Get Dashboard Stats
     */
    public function get_stats( WP_REST_Request $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response( [ 'message' => 'Forbidden' ], 403 );
        }

        global $wpdb;
        $expenses_table = $wpdb->prefix . 'ou_expenses';
        $requests_table = $wpdb->prefix . 'ou_requests';

        // Basic Counts
        // Count users who are team members (have ou_is_member meta)
        $total_employees = count( get_users( [ 'meta_key' => 'ou_is_member', 'meta_value' => '1' ] ) );
        $pending_requests = $wpdb->get_var( "SELECT COUNT(*) FROM $requests_table WHERE status = 'pending'" );
        $pending_expenses = $wpdb->get_var( "SELECT COUNT(*) FROM $expenses_table WHERE status = 'pending'" );
        $total_spent = $wpdb->get_var( "SELECT SUM(amount) FROM $expenses_table WHERE status = 'approved'" );

        // Recent Requests
        $recent_requests = $wpdb->get_results( 
            "SELECT r.*, u.display_name as user_name 
             FROM $requests_table r 
             LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID 
             ORDER BY r.created_at DESC 
             LIMIT 5" 
        );

        // Recent Expenses
        $recent_expenses = $wpdb->get_results( 
            "SELECT e.*, u.display_name as user_name 
             FROM $expenses_table e 
             LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID 
             ORDER BY e.created_at DESC 
             LIMIT 5" 
        );

        return new WP_REST_Response( [
            'overview' => [
                'employees' => $total_employees,
                'pending_requests' => $pending_requests,
                'pending_expenses' => $pending_expenses,
                'total_spent' => $total_spent ?? 0
            ],
            'recent_requests' => $recent_requests,
            'recent_expenses' => $recent_expenses
        ], 200 );
    }
}
