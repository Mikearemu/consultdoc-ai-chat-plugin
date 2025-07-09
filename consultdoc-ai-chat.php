<?php
/*
Plugin Name: ConsultDoc AI Chat
Description: AI chatbot for medical consultations using Gemini 2.0 Flash with patient records and doctor dashboard.
Version: 3.1
Author: StackMike
*/

defined('ABSPATH') || exit;

define('CONSULTDOC_AI_CHAT_DIR', plugin_dir_path(__FILE__));
define('CONSULTDOC_AI_CHAT_URL', plugin_dir_url(__FILE__));

// Core Includes
require_once CONSULTDOC_AI_CHAT_DIR . 'includes/chat-handler.php';
require_once CONSULTDOC_AI_CHAT_DIR . 'includes/settings-page.php';
require_once CONSULTDOC_AI_CHAT_DIR . 'includes/chat-save.php';
require_once CONSULTDOC_AI_CHAT_DIR . 'includes/doctor-dashboard.php';

// Frontend & Admin Scripts
function consultdoc_ai_enqueue_assets()
{
    if (!is_admin() && !is_singular()) return; // only load on post/page views

    global $post;
    if (has_shortcode($post->post_content ?? '', 'consultdoc_ai_chat')) {
        wp_enqueue_style('consultdoc-ai-style', CONSULTDOC_AI_CHAT_URL . 'assets/chat.css');
        wp_enqueue_script('consultdoc-ai-js', CONSULTDOC_AI_CHAT_URL . 'assets/chat.js', ['jquery'], null, true);

        wp_localize_script('consultdoc-ai-js', 'consultdoc_ai_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('consultdoc_ai_nonce'),
            'questions' => explode("\n", get_option('consultdoc_custom_questions', 'What symptoms are you experiencing?'))
        ]);
    }

    if (is_admin()) {
        wp_enqueue_style('consultdoc-admin-style', CONSULTDOC_AI_CHAT_URL . 'assets/admin.css');
    }
}

add_action('wp_enqueue_scripts', 'consultdoc_ai_enqueue_assets');

// Shortcode
function consultdoc_ai_shortcode()
{
    ob_start();
    include CONSULTDOC_AI_CHAT_DIR . 'templates/chat-box.php';
    return ob_get_clean();
}
add_shortcode('consultdoc_ai_chat', 'consultdoc_ai_shortcode');

// Activation Hook: Create table
register_activation_hook(__FILE__, 'consultdoc_ai_create_table');
function consultdoc_ai_create_table()
{
    global $wpdb;
    $table = $wpdb->prefix . 'consultdoc_chats';
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        chat LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Other plugin setup code...

// Hook to save chat to DB after login
add_action('wp_login', function($user_login, $user) {
  if (!isset($_COOKIE['consultdoc_chat_data'])) return;

  $raw = stripslashes_deep($_COOKIE['consultdoc_chat_data']);
  $chat = json_decode($raw, true);
  if (!$chat || !is_array($chat)) return;

  global $wpdb;
  $wpdb->insert("{$wpdb->prefix}consultdoc_chats", [
    'user_id'    => $user->ID,
    'chat'       => implode("\n", array_map(fn($m) => $m['text'], $chat)),
    'created_at' => current_time('mysql')
  ]);

  setcookie('consultdoc_chat_data', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
}, 10, 2);
