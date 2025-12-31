<?php
namespace App\Services;

defined( 'ABSPATH' ) || exit;

class NotificationService {

    /**
     * Notify Admin about new submission
     */
    public static function notify_admin( $type, $item, $author_id ) {
        // Find all admins
        $admins = get_users( [ 'role__in' => [ 'administrator' ] ] );
        
        $author = get_userdata( $author_id );
        $author_name = $author ? $author->display_name : 'Unknown User';

        foreach ( $admins as $admin_user ) {
             // 1. Email Notification
             $enabled = get_user_meta( $admin_user->ID, 'ou_notifications_enabled', true );
             if ( $enabled !== '0' ) { 
                $subject = '';
                $message = '';
                $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

                if ( $type === 'request' ) {
                    $subject = sprintf( '[Office Utilities] New Request: %s', $item['title'] );
                    $message = sprintf(
                        "<h3>New Office Request</h3>
                        <p><strong>From:</strong> %s</p>
                        <p><strong>Item:</strong> %s</p>
                        <p><strong>Priority:</strong> %s</p>
                        <p><strong>Description:</strong><br>%s</p>
                        <p><a href='%s'>Login to Dashboard</a></p>",
                        $author_name,
                        $item['title'],
                        ucfirst( $item['priority'] ),
                        nl2br( $item['description'] ),
                        admin_url( 'admin.php?page=office-utilities' )
                    );
                } elseif ( $type === 'expense' ) {
                    $subject = sprintf( '[Office Utilities] New Expense Claim: %s', $item['amount'] . ' ' . $item['currency'] );
                    $message = sprintf(
                        "<h3>New Expense Claim</h3>
                        <p><strong>From:</strong> %s</p>
                        <p><strong>Amount:</strong> %s %s</p>
                        <p><strong>Category:</strong> %s</p>
                        <p><strong>Description:</strong><br>%s</p>
                        <p><a href='%s'>Login to Dashboard</a></p>",
                        $author_name,
                        $item['amount'],
                        $item['currency'],
                        $item['category'],
                        nl2br( $item['description'] ),
                        admin_url( 'admin.php?page=office-utilities' )
                    );
                }
                wp_mail( $admin_user->user_email, $subject, $message, $headers );
             }

             // 2. Telegram Notification
             $admin_chat_id = get_user_meta( $admin_user->ID, 'ou_telegram_chat_id', true );
             $admin_tg_enabled = get_user_meta( $admin_user->ID, 'ou_telegram_enabled', true );
             
             if ( $admin_chat_id && $admin_tg_enabled === '1' ) {
                 $msg = ($type === 'request') 
                    ? "🔔 *New Request*\nFrom: {$author_name}\nItem: {$item['title']}\nPriority: {$item['priority']}"
                    : "💰 *New Expense*\nFrom: {$author_name}\nAmount: {$item['amount']} {$item['currency']}\nCategory: {$item['category']}";
                 
                 // Add Approve/Reject Buttons
                 $buttons = [
                     [ 'text' => '✅ Approve', 'callback_data' => "approve_{$type}_{$item['id']}" ],
                     [ 'text' => '❌ Reject',  'callback_data' => "reject_{$type}_{$item['id']}" ]
                 ];
                 
                 self::send_telegram( $admin_chat_id, $msg, $buttons );
             }
        }
    }

    /**
     * Notify User about status update
     */
    public static function notify_user_status_update( $type, $item ) {
        if ( empty( $item->user_id ) ) return;

        // Check User Preference
        $enabled = get_user_meta( $item->user_id, 'ou_notifications_enabled', true );
        $user = get_userdata( $item->user_id );
        if ( ! $user ) return;

        if ( $enabled !== '0' ) {
            $subject = '';
            $message = '';
            $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

            if ( $type === 'request' ) {
                $subject = sprintf( '[Office Utilities] Request Update: %s', $item->title );
                $message = sprintf(
                    "<p>Hi %s,</p>
                    <p>Your request for <strong>%s</strong> has been updated.</p>
                    <p><strong>New Status:</strong> <span style='color:blue'>%s</span></p>
                    <p>Login to check details.</p>",
                    $user->display_name,
                    $item->title,
                    ucfirst( $item->status )
                );
            } elseif ( $type === 'expense' ) {
                $subject = sprintf( '[Office Utilities] Expense Update: %s', $item->category );
                $message = sprintf(
                    "<p>Hi %s,</p>
                    <p>Your expense claim for <strong>%s %s (%s)</strong> has been updated.</p>
                    <p><strong>New Status:</strong> <span style='color:blue'>%s</span></p>
                    <p>Login to check details.</p>",
                    $user->display_name,
                    $item->amount,
                    $item->currency,
                    $item->category,
                    ucfirst( $item->status )
                );
            }
            wp_mail( $user->user_email, $subject, $message, $headers );
        }

        // Send Telegram to User
        $user_chat_id = get_user_meta( $user->ID, 'ou_telegram_chat_id', true );
        $user_tg_enabled = get_user_meta( $user->ID, 'ou_telegram_enabled', true );

        if ( $user_chat_id && $user_tg_enabled === '1' ) {
             $msg = ($type === 'request')
                ? "ℹ️ *Request Update*\nItem: {$item->title}\nNew Status: *{$item->status}*"
                : "ℹ️ *Expense Update*\nAmount: {$item->amount}\nNew Status: *{$item->status}*";
             self::send_telegram( $user_chat_id, $msg );
        }
    }

    /**
     * Send Telegram Message (with optional buttons)
     */
    public static function send_telegram( $chat_id, $message, $buttons = [] ) {
        $token = get_option( 'ou_telegram_bot_token' );
        if ( ! $token || ! $chat_id ) {
            return;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        
        $body = [
            'chat_id' => $chat_id,
            'text'    => $message,
            'parse_mode' => 'Markdown'
        ];

        if ( ! empty( $buttons ) ) {
            $body['reply_markup'] = json_encode( [
                'inline_keyboard' => [ $buttons ]
            ] );
        }

        wp_remote_post( $url, [
            'body' => $body
        ] );
    }
    
    /**
     * Send Reminder Notification
     */
    public static function send_reminder( $item, $type = 'request' ) {
        $title = ($type === 'request') ? $item->title : "Expense Claim ({$item->amount} {$item->currency})";
        $page = admin_url( 'admin.php?page=office-utilities' );

        // Notify Admin/HR
        $admin_email = get_option( 'admin_email' );
        $subject = sprintf( '[Reminder] Pending %s: %s', ucfirst($type), $title );
        $message = sprintf(
            "<h3>%s Still Pending</h3>
            <p><strong>Item:</strong> %s</p>
            <p><strong>Created:</strong> %s</p>
            <p>This item has been pending for a while. Please review it.</p>
            <p><a href='%s'>Login to Dashboard</a></p>",
            ucfirst($type),
            $title,
            $item->created_at,
            $page
        );
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        
        // Check Admin Email Prefs
        $admin_user = get_user_by( 'email', $admin_email );
        if ( $admin_user ) {
             $enabled = get_user_meta( $admin_user->ID, 'ou_notifications_enabled', true );
             if ( $enabled !== '0' ) {
                 wp_mail( $admin_email, $subject, $message, $headers );
             }
             
             // Telegram Reminder
             $admin_chat_id = get_user_meta( $admin_user->ID, 'ou_telegram_chat_id', true );
             $admin_tg_enabled = get_user_meta( $admin_user->ID, 'ou_telegram_enabled', true );
             if ( $admin_chat_id && $admin_tg_enabled === '1' ) {
                 $msg = "⏰ *Reminder: Pending " . ucfirst($type) . "*\nItem: {$title}\nCreated: {$item->created_at}\nPlease review.";
                 
                 // Add Approve/Reject Buttons for Reminders too
                 $buttons = [
                    [ 'text' => '✅ Approve', 'callback_data' => "approve_{$type}_{$item->id}" ],
                    [ 'text' => '❌ Reject',  'callback_data' => "reject_{$type}_{$item->id}" ]
                 ];

                 self::send_telegram( $admin_chat_id, $msg, $buttons );
             }
        } else {
            wp_mail( $admin_email, $subject, $message, $headers );
        }
    }
}
