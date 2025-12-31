<?php
namespace App\Http\Routes;

use App\Http\Controllers\RequestController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\DashboardController;

defined( 'ABSPATH' ) || exit;

class Api {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        $namespace = 'office-utilities/v1';

        // Requests Routes
        register_rest_route( $namespace, '/requests', [
            [
                'methods'             => 'GET',
                'callback'            => [ RequestController::class, 'index' ],
                'permission_callback' => [ $this, 'get_items_permissions_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ RequestController::class, 'store' ],
                'permission_callback' => [ $this, 'create_item_permissions_check' ],
            ],
        ] );

        register_rest_route( $namespace, '/requests/(?P<id>\d+)', [
            [
                'methods'             => 'PUT', // or PATCH
                'callback'            => [ RequestController::class, 'update' ],
                'permission_callback' => [ $this, 'update_item_permissions_check' ],
            ],
        ] );

        register_rest_route( $namespace, '/requests/(?P<id>\d+)/notes', [
            [
                'methods'             => 'GET',
                'callback'            => [ RequestController::class, 'get_notes' ],
                'permission_callback' => [ $this, 'get_items_permissions_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ RequestController::class, 'add_note' ],
                'permission_callback' => [ $this, 'get_items_permissions_check' ], // Anyone involved can add notes
            ],
        ] );

        // Expenses Routes
        register_rest_route( $namespace, '/expenses', [
            [
                'methods'             => 'GET',
                'callback'            => [ ExpenseController::class, 'index' ],
                'permission_callback' => [ $this, 'get_items_permissions_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ ExpenseController::class, 'store' ],
                'permission_callback' => [ $this, 'create_item_permissions_check' ],
            ],
        ] );

        register_rest_route( $namespace, '/expenses/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [ ExpenseController::class, 'update' ],
                'permission_callback' => [ $this, 'update_item_permissions_check' ],
            ],
        ] );

        // Users Routes (HR Only)
        register_rest_route( $namespace, '/users', [
            [
                'methods'             => 'GET',
                'callback'            => [ UserController::class, 'index' ],
                'permission_callback' => [ $this, 'update_item_permissions_check' ], 
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ UserController::class, 'add_member' ],
                'permission_callback' => [ $this, 'update_item_permissions_check' ],
            ],
        ] );

        register_rest_route( $namespace, '/users/search', [
            [
                'methods'             => 'GET',
                'callback'            => [ UserController::class, 'search' ],
                'permission_callback' => [ $this, 'update_item_permissions_check' ],
            ],
        ] );

        register_rest_route( $namespace, '/users/(?P<id>\d+)', [
            [
                'methods'             => 'DELETE',
                'callback'            => [ UserController::class, 'remove_member' ],
                'permission_callback' => [ $this, 'update_item_permissions_check' ],
            ],
        ] );

        // Settings Routes
        register_rest_route( $namespace, '/settings/notifications', [
            [
                'methods'             => 'GET',
                'callback'            => [ SettingsController::class, 'get_notification_pref' ],
                'permission_callback' => [ $this, 'get_items_permissions_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ SettingsController::class, 'update_notification_pref' ],
                'permission_callback' => [ $this, 'get_items_permissions_check' ],
            ],
        ] );

        // Dashboard Stats
        register_rest_route( $namespace, '/dashboard/stats', [
            'methods' => 'GET',
            'callback' => [ new DashboardController(), 'get_stats' ],
            'permission_callback' => function() { return current_user_can( 'manage_options' ); }
        ] );

        // Telegram Webhook
        register_rest_route( $namespace, '/telegram/webhook', [
            'methods' => 'POST',
            'callback' => [ new TelegramController(), 'handle_webhook' ],
            'permission_callback' => '__return_true' // Webhooks are public
        ] );
    }

    public function get_items_permissions_check( $request ) {
        return is_user_logged_in();
    }

    public function create_item_permissions_check( $request ) {
        return is_user_logged_in();
    }

    public function update_item_permissions_check( $request ) {
        return current_user_can( 'manage_options' );
    }

    public function create_user_permissions_check( $request ) {
        return current_user_can( 'create_users' );
    }
}

new Api();
