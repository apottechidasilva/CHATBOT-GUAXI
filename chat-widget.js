/* Chat Widget JS - frontend interactions */
(function(){
  const launcher = document.getElementById('wcLauncher');
  const panel = document.getElementById('wcPanel');
  const messages = document.getElementById('wcMessages');
  const input = document.getElementById('wcInput');
  const sendBtn = document.getElementById('wcSend');
  const quickBtns = document.querySelectorAll('.wc-quick-btn');

  // Open/close panel
  let opened = false;
  function setOpen(state){
    opened = state;
    if(state){ panel.classList.add('open'); panel.style.display='flex'; }
    else { panel.classList.remove('open'); panel.style.display='none'; }
  }

  // Append a bubble
  function addBubble(role, text){
    const b = document.createElement('div');
    b.className = 'wc-bubble ' + (role === 'user' ? 'user' : 'bot');
    b.textContent = text;
    messages.appendChild(b);
    messages.scrollTop = messages.scrollHeight;
  }

  // Send user message to backend
  function sendMessage(text){
    if(!text) return;
    addBubble('user', text);
    input.value = '';
    // Prepare payload expected by PHP backend
    const payload = { messages: [ { role: 'user', content: text } ] };
    fetch('index.php?api=chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(res => res.text())
      .then(txt => {
        let botText = null;
        try {
          const data = JSON.parse(txt);
          // Groq/OpenAI formats
          if (data && data.choices && data.choices[0] && data.choices[0].message && data.choices[0].message.content) {
            botText = data.choices[0].message.content;
          } else if (data && data.choices && data.choices[0] && data.choices[0].content) {
            botText = data.choices[0].content;
          } else if (typeof data?.text === 'string') {
            botText = data.text;
          }
        } catch(e){ botText = txt; }
        if(botText){ addBubble('bot', botText); }
      })
      .catch(() => { addBubble('bot', 'Desculpe, não consegui obter a resposta. Tente novamente.'); });
  }

  // Init welcome when opened
  launcher?.addEventListener('click', ()=>{
    const wasOpen = panel.style.display === 'flex' || panel.classList.contains('open');
    setOpen(!wasOpen);
    if(!wasOpen && messages.children.length === 0){
      addBubble('bot', 'Olá! Como posso ajudar você hoje?');
    }
  });

  sendBtn?.addEventListener('click', ()=>{ const t = input?.value.trim(); if(t) sendMessage(t); });
  input?.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); const t = input.value.trim(); if(t) sendMessage(t); } });

  quickBtns.forEach(btn => btn.addEventListener('click', ()=>{
    const t = btn.getAttribute('data-q') || btn.textContent;
    sendMessage(t);
  }));
})();
