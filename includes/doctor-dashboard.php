<?php
// Add Doctor Dashboard menu in admin
add_action('admin_menu', function() {
    add_menu_page(
        'ConsultDoc Patient Chats',
        'Patient Chats',
        'manage_options',
        'consultdoc-patient-chats',
        'consultdoc_render_chats',
        'dashicons-heart',
        6
    );
});

// Render chat log table with modals
function consultdoc_render_chats() {
    global $wpdb;
    $table = $wpdb->prefix . 'consultdoc_chats';
    $results = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");

    echo "<div class='wrap'>";
    echo "<h1 class='wp-heading-inline'>Patient Consultation Records</h1>";
    echo "<p>Below are the latest patient chats collected via the ConsultDoc AI system.</p>";
    echo "<table class='widefat striped fixed'>";
    echo "<thead><tr><th>#</th><th>Patient</th><th>Date</th><th>Action</th></tr></thead><tbody>";

    foreach ($results as $index => $row) {
        $user_info = get_userdata($row->user_id);
        $name = $user_info ? esc_html($user_info->display_name) : 'User #' . intval($row->user_id);
        $chat_preview = esc_html(wp_trim_words(strip_tags($row->chat), 10));
        $modal_id = 'chatModal_' . intval($row->id);

        echo "<tr>
            <td>" . ($index + 1) . "</td>
            <td>{$name}</td>
            <td>" . esc_html($row->created_at) . "</td>
            <td>
                <button class='button button-primary' onclick='document.getElementById(\"{$modal_id}\").style.display=\"block\"'>View Chat</button>
                
                <div id='{$modal_id}' class='chat-modal' style='display:none;position:fixed;top:10%;left:10%;width:80%;height:70%;background:#fff;overflow:auto;padding:20px;border:2px solid #444;z-index:10000;box-shadow:0 5px 20px rgba(0,0,0,0.3);'>
                    <h2>Chat with {$name}</h2>
                    <pre style='white-space:pre-wrap;font-family:inherit;padding:10px;background:#f9f9f9;border:1px solid #ddd;'>" . esc_html($row->chat) . "</pre>
                    <p style='text-align:right;'>
                        <button class='button' onclick='document.getElementById(\"{$modal_id}\").style.display=\"none\"'>Close</button>
                    </p>
                </div>
            </td>
        </tr>";
    }

    echo "</tbody></table></div>";
}
