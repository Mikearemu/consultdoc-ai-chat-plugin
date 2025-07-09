<?php
// Enable AJAX for both logged-in and guest users
add_action('wp_ajax_consultdoc_ai_handle_chat', 'consultdoc_ai_handle_chat');
add_action('wp_ajax_nopriv_consultdoc_ai_handle_chat', 'consultdoc_ai_handle_chat');

function consultdoc_ai_handle_chat()
{
    check_ajax_referer('consultdoc_ai_nonce', 'nonce');

    $raw_messages = stripslashes_deep($_POST['messages'] ?? '');
    $messages = json_decode($raw_messages, true);

    if (!$messages || !is_array($messages)) {
        wp_send_json_error('Invalid message format.');
    }

    // Compile messages
    $chat_text = implode("\n", array_map(function ($m) {
        return sanitize_text_field($m['text'] ?? '');
    }, $messages));

    // System prompt to keep Gemini on track
    $system_prompt = <<<EOT
You are ConsultDoc AI, a friendly and professional virtual health assistant in Nigeria.

Your job is to:
- Only respond to health-related questions.
- If the user asks anything unrelated to health or medical issues (e.g., tech, politics, jokes, etc.), politely say: "I'm here to assist with medical concerns only. Please let me know any symptoms or health questions you have."
- For valid health issues, reply in short, clear sentences (3–5 lines max) and mention possible causes based on the symptoms.
- Suggest general home remedies or over-the-counter medications available in Nigeria (e.g., Paracetamol, Vitamin C, ORS) if appropriate — but clearly state it's not a prescription.
- Offer basic lifestyle tips like hydration, rest, avoiding triggers, etc.
- Never diagnose or prescribe medication.
- Always end your response with: "Would you like to book a consultation with a doctor now?" and expect the frontend to show a booking button.

If the user asks a follow-up medical question, continue the conversation in context.
EOT;

    $final_input = $chat_text . "\n\nRespond now:";

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

    // Call Gemini API with retry logic
    $response = consultdoc_ai_try_gemini($url, $payload);

    if (is_wp_error($response)) {
        wp_send_json_error('Connection error: ' . $response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        wp_send_json_error("Unexpected Gemini response:\n\n" . json_encode($body, JSON_PRETTY_PRINT));
    }

    $reply = $body['candidates'][0]['content']['parts'][0]['text'];
    wp_send_json_success(['response' => $reply]);
}

/**
 * Retry Gemini API once if cURL error 28 (timeout)
 */
function consultdoc_ai_try_gemini($url, $payload)
{
    $args = [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => $payload,
        'timeout' => 20,
    ];

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response) && strpos($response->get_error_message(), 'cURL error 28') !== false) {
        error_log('[ConsultDoc AI] Retrying Gemini API due to timeout...');
        sleep(2);
        $response = wp_remote_post($url, $args);
    }

    return $response;
}
