<?php
namespace App\Http\Controllers;

use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class SettingsController {

    public static function update_notification_pref( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $user_id = get_current_user_id();
        
        // General Notification Toggle
        if ( isset( $params['enabled'] ) ) {
            update_user_meta( $user_id, 'ou_notifications_enabled', $params['enabled'] ? '1' : '0' );
        }

        // Generate Linking Token
        if ( isset( $params['generate_telegram_token'] ) ) {
            $token = wp_generate_password( 6, false, false );
            update_user_meta( $user_id, 'ou_telegram_temp_token', $token );
            update_user_meta( $user_id, 'ou_telegram_token_expiry', time() + 300 ); // 5 mins
            
            return new WP_REST_Response( [ 'token' => $token ], 200 );
        }
        
        // Telegram Notification Toggle
        if ( isset( $params['telegram_enabled'] ) ) {
            update_user_meta( $user_id, 'ou_telegram_enabled', $params['telegram_enabled'] ? '1' : '0' );
        }

        // Global Reminder Toggle (Admin Only)
        if ( current_user_can( 'manage_options' ) && isset( $params['reminders_enabled'] ) ) {
            update_option( 'ou_reminders_enabled', $params['reminders_enabled'] ? '1' : '0' );
        }
        
        // Telegram Settings
        if ( isset( $params['telegram_chat_id'] ) ) {
            update_user_meta( $user_id, 'ou_telegram_chat_id', sanitize_text_field( $params['telegram_chat_id'] ) );
        }
        
        // Admin Only: Telegram Bot Token
        if ( current_user_can( 'manage_options' ) && isset( $params['telegram_bot_token'] ) ) {
            update_option( 'ou_telegram_bot_token', sanitize_text_field( $params['telegram_bot_token'] ) );
        }

        // Admin Only: Test Webhook Status
        if ( current_user_can( 'manage_options' ) && isset( $params['test_webhook'] ) ) {
            $token = get_option( 'ou_telegram_bot_token' );
            if ( ! $token ) {
                return new WP_REST_Response( [ 'error' => 'No token saved' ], 400 );
            }
            
            $response = wp_remote_get( "https://api.telegram.org/bot{$token}/getWebhookInfo" );
            if ( is_wp_error( $response ) ) {
                 return new WP_REST_Response( [ 'error' => $response->get_error_message() ], 500 );
            }
            
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            return new WP_REST_Response( $body, 200 );
        }

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public static function get_notification_pref( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $enabled = get_user_meta( $user_id, 'ou_notifications_enabled', true );
        $telegram_enabled = get_user_meta( $user_id, 'ou_telegram_enabled', true );
        
        $data = [
            'enabled' => $enabled !== '0',
            'telegram_enabled' => $telegram_enabled === '1', // Default to false if not set? Or check logic. Let's default false.
            'telegram_chat_id' => get_user_meta( $user_id, 'ou_telegram_chat_id', true ),
            'telegram_bot_name' => 'OfficeUtilitiesBot', // You could fetch this from API if you want
            'webhook_url' => get_rest_url( null, 'office-utilities/v1/telegram/webhook' )
        ];
        
        if ( current_user_can( 'manage_options' ) ) {
            $data['telegram_bot_token'] = get_option( 'ou_telegram_bot_token', '' );
            $data['reminders_enabled'] = get_option( 'ou_reminders_enabled', '0' ) === '1';
        }

        return new WP_REST_Response( $data, 200 );
    }
}
