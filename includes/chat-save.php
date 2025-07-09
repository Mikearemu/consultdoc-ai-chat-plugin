<?php
// Register AJAX handlers for logged-in and guest users
add_action('wp_ajax_consultdoc_ai_save_chat', 'consultdoc_ai_save_chat');
add_action('wp_ajax_nopriv_consultdoc_ai_save_chat', 'consultdoc_ai_save_chat');

function consultdoc_ai_save_chat() {
    check_ajax_referer('consultdoc_ai_nonce', 'nonce');

    $chat = stripslashes_deep($_POST['chat'] ?? '');
    if (empty($chat)) {
        wp_send_json_error('Chat content is empty.');
    }

    // Determine user ID (create account if guest)
    if (!is_user_logged_in()) {
        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email) || !is_email($email)) {
            wp_send_json_error('Please provide a valid email address to save your chat.');
        }

        $username = sanitize_user(current(explode('@', $email)), true);
        $password = wp_generate_password(12, true);

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error('Failed to create user: ' . $user_id->get_error_message());
        }

        // Automatically log in the new user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
    } else {
        $user_id = get_current_user_id();
    }

    // Save chat to DB
    global $wpdb;
    $table = $wpdb->prefix . 'consultdoc_chats';

    $inserted = $wpdb->insert($table, [
        'user_id' => $user_id,
        'chat'    => wp_kses_post($chat),
    ]);

    if (!$inserted) {
        wp_send_json_error('Database error: ' . esc_html($wpdb->last_error));
    }

    wp_send_json_success(['msg' => 'Chat saved successfully.']);
}
