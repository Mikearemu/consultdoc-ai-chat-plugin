<?php
// Enable AJAX for both logged-in and guest users
add_action('wp_ajax_consultdoc_ai_handle_chat', 'consultdoc_ai_handle_chat');
add_action('wp_ajax_nopriv_consultdoc_ai_handle_chat', 'consultdoc_ai_handle_chat');

function consultdoc_ai_handle_chat()
{
    check_ajax_referer('consultdoc_ai_nonce', 'nonce');

    // Sanitize and decode incoming message array
    $raw_messages = stripslashes_deep($_POST['messages'] ?? '');
    $messages = json_decode($raw_messages, true);

    if (!$messages || !is_array($messages)) {
        wp_send_json_error('Invalid message format.');
    }

    // Compile messages into a readable input
    $chat_text = implode("\n", array_map(function ($m) {
        return sanitize_text_field($m['text'] ?? '');
    }, $messages));

    // Add guiding system message
    $system_prompt = <<<EOT
    You are ConsultDoc AI, a friendly health assistant for users in Nigeria.
    Respond in short, simple, friendly sentences.
    Explain the possible causes in 4–5 lines.
    Suggest general over-the-counter medications available in Nigeria like Paracetamol, Vitamin C, or more, if applicable.
    Mention lifestyle tips like rest, hydration, etc., if relevant.
    Never offer a direct diagnosis or prescription.
    Always end with a CTA: "Would you like to book a consultation with a doctor now?" and include a button.
    EOT;

    $final_input = $chat_text . "\n\nRespond now:";

    // Get API key
    $api_key = trim(get_option('consultdoc_gemini_api_key'));
    if (empty($api_key)) {
        wp_send_json_error('Gemini API key is missing. Please add it in the plugin settings.');
    }

    // Build Gemini Flash payload
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$api_key}";
    $payload = json_encode([
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $system_prompt . "\n\n" . $final_input]
                ]
            ]
        ]
    ]);

    // Send request to Gemini
    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => $payload,
        'timeout' => 60,
    ]);

    // Handle connection error
    if (is_wp_error($response)) {
        wp_send_json_error('Connection error: ' . $response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    // Validate and extract response safely
    if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        $fallback = json_encode($body, JSON_PRETTY_PRINT);
        wp_send_json_error("Unexpected Gemini response:\n\n$fallback");
    }

    $reply = $body['candidates'][0]['content']['parts'][0]['text'];
    wp_send_json_success(['response' => $reply]);
}
