jQuery(document).ready(function ($) {
  const questions = consultdoc_ai_vars.questions;
  let step = 0;
  let messages = JSON.parse(localStorage.getItem('consultdoc_chat') || '[]');
  const log = $('#chat-log');
  const input = $('#chat-input');
  const sendBtn = $('#chat-send');

  // Rehydrate old chat (if exists)
  messages.forEach(m => addMsg(m.text, m.from));

  function addMsg(text, from) {
    const el = $('<div class="msg ' + from + '"><div class="bubble">' + text + '</div></div>');
    log.append(el);
    log.scrollTop(log.prop("scrollHeight"));
  }

  function showTypingIndicator() {
    const typing = $('<div id="chat-typing" class="msg bot"><div class="bubble typing"><span>.</span><span>.</span><span>.</span></div></div>');
    log.append(typing);
    log.scrollTop(log.prop("scrollHeight"));
  }

  function removeTypingIndicator() {
    $('#chat-typing').remove();
  }

  function ask() {
    if (step < questions.length) {
      const q = questions[step];
      addMsg(q, 'bot');
      messages.push({ text: q, from: 'bot' });
      updateLocal();
    } else {
      callGemini();
    }
  }

  function callGemini() {
    sendBtn.prop('disabled', true);
    showTypingIndicator();

    $.post(consultdoc_ai_vars.ajax_url, {
      action: 'consultdoc_ai_handle_chat',
      nonce: consultdoc_ai_vars.nonce,
      messages: JSON.stringify(messages.map(m => ({ text: m.text })))
    })
      .done(function (res) {
        removeTypingIndicator();
        if (res.success) {
          addMsg(res.data.response, 'bot');
          messages.push({ text: res.data.response, from: 'bot' });
          updateLocal();

          const cta = $(`
          <div class="msg bot">
            <div class="bubble">
              <a href="#" class="latepoint-book-button book-now-btn">Book a Consultation</a>
            </div>
          </div>
        `);

          log.append(cta);
          log.scrollTop(log.prop("scrollHeight"));
        } else {
          addMsg('Error: ' + res.data, 'bot');
        }
      })
      .fail(function (xhr, status, error) {
        removeTypingIndicator();
        addMsg('Server error: ' + error, 'bot');
      })
      .always(function () {
        sendBtn.prop('disabled', false);
      });
  }

  function updateLocal() {
    localStorage.setItem('consultdoc_chat', JSON.stringify(messages));
  }

  sendBtn.click(function () {
    const val = input.val().trim();
    if (!val) return;

    addMsg(val, 'user');
    messages.push({ text: val, from: 'user' });
    updateLocal();

    input.val('');
    step++;
    setTimeout(ask, 600);
  });

  input.on('keypress', function (e) {
    if (e.which === 13) {
      sendBtn.click();
      return false;
    }
  });

$(document).on('click', '.latepoint-book-button', function () {
  // Save chat flag to localStorage before booking
  localStorage.setItem('consultdoc_trigger_save', '1');
});


  ask();
});

if (localStorage.getItem('consultdoc_trigger_save')) {
  const chat = localStorage.getItem('consultdoc_chat');
  document.cookie = 'consultdoc_chat_data=' + encodeURIComponent(chat) + '; path=/';
  localStorage.removeItem('consultdoc_chat');
  localStorage.removeItem('consultdoc_trigger_save');
}

