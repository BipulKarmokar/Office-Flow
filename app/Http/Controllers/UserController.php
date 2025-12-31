<?php
namespace App\Http\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_User_Query;

defined( 'ABSPATH' ) || exit;

class UserController {

    /**
     * Get list of office team members (users with ou_is_member meta)
     */
    public static function index( WP_REST_Request $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response( [ 'message' => 'Unauthorized' ], 403 );
        }

        $args = [
            'meta_key'     => 'ou_is_member',
            'meta_value'   => '1',
            'orderby'      => 'display_name',
            'order'        => 'ASC',
            'number'       => 100
        ];

        $user_query = new WP_User_Query( $args );
        $users = [];

        foreach ( $user_query->get_results() as $user ) {
            $users[] = [
                'id'           => $user->ID,
                'name'         => $user->display_name,
                'email'        => $user->user_email,
                'registered'   => $user->user_registered,
                'roles'        => $user->roles
            ];
        }

        return new WP_REST_Response( $users, 200 );
    }

    /**
     * Search for potential members (users NOT in the team)
     */
    public static function search( WP_REST_Request $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response( [ 'message' => 'Unauthorized' ], 403 );
        }

        $term = sanitize_text_field( $request->get_param( 'term' ) );
        
        $args = [
            'search'         => '*' . $term . '*',
            'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
            'number'         => 20,
            'meta_query'     => [
                [
                    'key'     => 'ou_is_member',
                    'compare' => 'NOT EXISTS' // Only users NOT in the team
                ]
            ],
            'exclude'        => [ get_current_user_id() ] // Exclude self if desired, though admin might want to be in team
        ];

        $user_query = new WP_User_Query( $args );
        $users = [];

        foreach ( $user_query->get_results() as $user ) {
            $users[] = [
                'id'    => $user->ID,
                'name'  => $user->display_name,
                'email' => $user->user_email
            ];
        }

        return new WP_REST_Response( $users, 200 );
    }

    /**
     * Add an existing user to the team
     */
    public static function add_member( WP_REST_Request $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response( [ 'message' => 'Unauthorized' ], 403 );
        }

        $params = $request->get_json_params();
        $user_id = intval( $params['user_id'] );

        if ( ! $user_id ) {
            return new WP_REST_Response( [ 'message' => 'Invalid User ID' ], 400 );
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
             return new WP_REST_Response( [ 'message' => 'User not found' ], 404 );
        }

        update_user_meta( $user_id, 'ou_is_member', '1' );

        $response_data = [
            'id'           => $user->ID,
            'name'         => $user->display_name,
            'email'        => $user->user_email,
            'registered'   => $user->user_registered,
            'roles'        => $user->roles
        ];

        return new WP_REST_Response( $response_data, 200 );
    }

    /**
     * Remove a user from the team
     */
    public static function remove_member( WP_REST_Request $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response( [ 'message' => 'Unauthorized' ], 403 );
        }

        $user_id = intval( $request->get_param( 'id' ) );

        if ( ! $user_id ) {
            return new WP_REST_Response( [ 'message' => 'Invalid User ID' ], 400 );
        }

        delete_user_meta( $user_id, 'ou_is_member' );

        return new WP_REST_Response( [ 'message' => 'User removed from team' ], 200 );
    }
}
