<?php
// Add menu page under "Settings"
add_action('admin_menu', function() {
    add_options_page(
        'ConsultDoc AI Settings',
        'ConsultDoc AI',
        'manage_options',
        'consultdoc-ai-chat',
        'consultdoc_ai_render_settings'
    );
});

// Render the settings form
function consultdoc_ai_render_settings() {
    ?>
    <div class="wrap">
        <h1>ConsultDoc AI Chat Settings</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('consultdoc_ai_settings_group');
                do_settings_sections('consultdoc-ai-chat');
                submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}

// Register settings and fields
add_action('admin_init', function() {
    // Register API key field
    register_setting('consultdoc_ai_settings_group', 'consultdoc_gemini_api_key', [
        'sanitize_callback' => 'sanitize_text_field'
    ]);

    // Register consultation questions (one per line)
    register_setting('consultdoc_ai_settings_group', 'consultdoc_custom_questions', [
        'sanitize_callback' => 'consultdoc_sanitize_multiline'
    ]);

    // Main settings section
    add_settings_section(
        'consultdoc_ai_main_section',
        'Gemini API & Consultation Questions',
        null,
        'consultdoc-ai-chat'
    );

    // Gemini API Key field
    add_settings_field(
        'consultdoc_gemini_api_key',
        'Gemini API Key',
        function() {
            $value = esc_attr(get_option('consultdoc_gemini_api_key', ''));
            echo "<input type='text' class='regular-text' name='consultdoc_gemini_api_key' value='{$value}' />";
            echo "<p class='description'>Paste your <strong>Gemini 2.0 Flash</strong> API key here. Required for AI responses.</p>";
        },
        'consultdoc-ai-chat',
        'consultdoc_ai_main_section'
    );

    // Custom Questions field
    add_settings_field(
        'consultdoc_custom_questions',
        'Consultation Questions (1 per line)',
        function() {
            $value = esc_textarea(get_option('consultdoc_custom_questions', 'What symptoms are you experiencing?'));
            echo "<textarea name='consultdoc_custom_questions' rows='6' cols='60'>{$value}</textarea>";
            echo "<p class='description'>Add one question per line. These will be asked to the patient before AI reply.</p>";
        },
        'consultdoc-ai-chat',
        'consultdoc_ai_main_section'
    );
});

// Custom sanitizer for multiline textarea input
function consultdoc_sanitize_multiline($input) {
    $lines = explode("\n", $input);
    $sanitized = array_filter(array_map('sanitize_text_field', $lines));
    return implode("\n", $sanitized);
}
