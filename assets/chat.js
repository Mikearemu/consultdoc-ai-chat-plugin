jQuery(document).ready(function($) {
  const questions = consultdoc_ai_vars.questions;
  let step = 0;
  let messages = [];
  const log = $('#chat-log');
  const input = $('#chat-input');
  const sendBtn = $('#chat-send');

  // Append message bubble
  function addMsg(text, from) {
    const el = $('<div class="msg ' + from + '"><div class="bubble">' + text + '</div></div>');
    log.append(el);
    scrollToBottom();
  }

  // Show typing animation
  function showTypingIndicator() {
    const typing = $('<div id="chat-typing" class="msg bot"><div class="bubble typing"><span>.</span><span>.</span><span>.</span></div></div>');
    log.append(typing);
    scrollToBottom();
  }

  // Remove typing animation
  function removeTypingIndicator() {
    $('#chat-typing').remove();
  }

  // Scroll to bottom
  function scrollToBottom() {
    log.scrollTop(log.prop("scrollHeight"));
  }

  // Ask next question or call AI
  function ask() {
    if (step < questions.length) {
      addMsg(questions[step], 'bot');
    } else {
      callGemini();
    }
  }

  // Call Gemini API
  function callGemini() {
    sendBtn.prop('disabled', true);
    showTypingIndicator();

    $.post(consultdoc_ai_vars.ajax_url, {
      action: 'consultdoc_ai_handle_chat',
      nonce: consultdoc_ai_vars.nonce,
      messages: JSON.stringify(messages)
    })
    .done(function(res) {
      removeTypingIndicator();

      if (res.success) {
        addMsg(res.data.response, 'bot');

        // Inject LatePoint-style booking button
        const cta = $(`
          <div class="msg bot">
            <div class="bubble bubble-cta">
              <a href="#" class="latepoint-book-button" data-os-trigger>
                Book a Consultation
              </a>
            </div>
          </div>
        `);
        log.append(cta);
        scrollToBottom();

        // Ensure LatePoint popup can initialize if dynamic
        if (typeof OsFrontendApp !== 'undefined' && OsFrontendApp.initBookingPopup) {
          OsFrontendApp.initBookingPopup();
        }

      } else {
        addMsg('❌ Error: ' + res.data, 'bot');
      }
    })
    .fail(function(xhr, status, error) {
      removeTypingIndicator();
      addMsg('❌ Server error: ' + error, 'bot');
    })
    .always(function() {
      sendBtn.prop('disabled', false);
    });
  }

  // Handle message send
  sendBtn.click(function() {
    const val = input.val().trim();
    if (!val) return;

    addMsg(val, 'user');
    messages.push({ text: val });
    input.val('');
    step++;
    setTimeout(ask, 600);
  });

  // Optional: Send on Enter
  input.on('keypress', function(e) {
    if (e.which === 13) {
      sendBtn.click();
      return false;
    }
  });

  // Start conversation
  ask();
});
