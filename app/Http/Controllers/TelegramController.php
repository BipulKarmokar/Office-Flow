<?php
namespace App\Http\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use App\Services\NotificationService;

class TelegramController {
    
    /**
     * Handle incoming Telegram Webhook
     */
    public function handle_webhook( WP_REST_Request $request ) {
        $update = $request->get_json_params();
        
        // Debug Logging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Telegram Webhook Received: ' . print_r( $update, true ) );
        }
        
        if ( ! $update ) {
            return new WP_REST_Response( [ 'status' => 'No payload' ], 200 );
        }

        // 1. Handle Command Messages (/start)
        if ( isset( $update['message']['text'] ) ) {
            $this->handle_message( $update['message'] );
        }

        // 2. Handle Callback Queries (Button Clicks)
        if ( isset( $update['callback_query'] ) ) {
            $this->handle_callback( $update['callback_query'] );
        }

        return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
    }

    /**
     * Handle Text Messages
     */
    private function handle_message( $message ) {
        $chat_id = $message['chat']['id'];
        $text = trim( $message['text'] );

        // /start COMMAND
        if ( strpos( $text, '/start' ) === 0 ) {
            // Extract token if present: /start 123456
            $parts = explode( ' ', $text );
            if ( count( $parts ) > 1 ) {
                $token = $parts[1];
                $this->link_account( $chat_id, $token );
            } else {
                NotificationService::send_telegram( $chat_id, "Welcome! To link your account, please go to your Dashboard settings and click 'Connect Telegram'." );
            }
        }
    }

    /**
     * Link Telegram Chat ID to WP User
     */
    private function link_account( $chat_id, $token ) {
        // Find user with this temp token
        $users = get_users( [
            'meta_key' => 'ou_telegram_temp_token',
            'meta_value' => $token,
            'number' => 1
        ] );

        if ( ! empty( $users ) ) {
            $user = $users[0];
            
            // Verify token expiry
            $expiry = get_user_meta( $user->ID, 'ou_telegram_token_expiry', true );
            if ( time() > $expiry ) {
                NotificationService::send_telegram( $chat_id, "❌ Link token expired. Please try again from the dashboard." );
                return;
            }

            // Save Chat ID & Clear Token
            update_user_meta( $user->ID, 'ou_telegram_chat_id', $chat_id );
            update_user_meta( $user->ID, 'ou_telegram_enabled', '1' ); // Auto-enable
            delete_user_meta( $user->ID, 'ou_telegram_temp_token' );
            delete_user_meta( $user->ID, 'ou_telegram_token_expiry' );

            NotificationService::send_telegram( $chat_id, "✅ Account Linked Successfully! You will now receive notifications here." );
        } else {
            NotificationService::send_telegram( $chat_id, "❌ Invalid token. Please check your dashboard." );
        }
    }

    /**
     * Handle Callback Queries (Inline Buttons)
     */
    private function handle_callback( $callback ) {
        $chat_id = $callback['message']['chat']['id'];
        $data = $callback['data']; // e.g. "approve_request_15"
        $callback_id = $callback['id'];

        // Format: action_type_id (e.g., approve_request_123)
        $parts = explode( '_', $data );
        if ( count( $parts ) !== 3 ) {
            return; // Invalid format
        }

        $action = $parts[0]; // approve / reject
        $type = $parts[1];   // request / expense
        $id = intval( $parts[2] );

        // Verify Admin Access (Simple check: is this chat ID linked to an admin?)
        // In a real app, you'd be more strict, but for hackathon:
        $user = $this->get_user_by_chat_id( $chat_id );
        if ( ! $user || ! user_can( $user->ID, 'manage_options' ) ) {
            $this->answer_callback( $callback_id, "❌ Unauthorized" );
            return;
        }

        global $wpdb;
        $table = ($type === 'request') ? $wpdb->prefix . 'ou_requests' : $wpdb->prefix . 'ou_expenses';
        $status = ($action === 'approve') ? ($type === 'request' ? 'in_progress' : 'approved') : 'rejected';

        // Update DB
        $updated = $wpdb->update( 
            $table, 
            [ 'status' => $status ], 
            [ 'id' => $id ] 
        );

        if ( $updated ) {
            // Fetch updated item to notify user
            $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
            if ( $item ) {
                NotificationService::notify_user_status_update( $type, $item );
            }

            $msg = ($action === 'approve') ? "✅ {$type} #{$id} Approved!" : "❌ {$type} #{$id} Rejected.";
            
            // Edit the original message to remove buttons and show result
            $this->edit_message_text( $chat_id, $callback['message']['message_id'], $msg . "\n\n(Processed by Telegram)" );
            $this->answer_callback( $callback_id, "Processed successfully" );
        } else {
            $this->answer_callback( $callback_id, "⚠️ Failed or already processed" );
        }
    }

    private function get_user_by_chat_id( $chat_id ) {
        $users = get_users( [
            'meta_key' => 'ou_telegram_chat_id',
            'meta_value' => $chat_id,
            'number' => 1
        ] );
        return ! empty( $users ) ? $users[0] : null;
    }

    private function answer_callback( $callback_id, $text ) {
        $token = get_option( 'ou_telegram_bot_token' );
        wp_remote_post( "https://api.telegram.org/bot{$token}/answerCallbackQuery", [
            'body' => [ 'callback_query_id' => $callback_id, 'text' => $text ]
        ]);
    }

    private function edit_message_text( $chat_id, $message_id, $text ) {
        $token = get_option( 'ou_telegram_bot_token' );
        wp_remote_post( "https://api.telegram.org/bot{$token}/editMessageText", [
            'body' => [ 
                'chat_id' => $chat_id, 
                'message_id' => $message_id, 
                'text' => $text 
            ]
        ]);
    }
}
