(() => {
  const init = () => {
    const id = Number(new URLSearchParams(location.search).get('id') || 0);
    const box = document.getElementById('chat-messages-container');
    if (!id || !box) return;

    const mediaInput = document.getElementById('mediaInput');
    const docInput = document.getElementById('documentInput');
    const modalEl = document.getElementById('fileModal');
    const sendUrl = new URL('mensagem/', document.baseURI).toString();
    const pollUrl = new URL('buscar-mensagens/', document.baseURI).toString();
    const readIds = new Set();

    const css = document.createElement('style');
    css.textContent = '.msg-read{margin-left:.35rem;font-weight:700;letter-spacing:-2px;color:#888}.msg-read.read{color:#2496ed}.chat-media{display:block;max-width:min(360px,70vw);max-height:320px;border-radius:12px;object-fit:cover;background:#111}.chat-doc-card{display:flex;align-items:center;gap:.65rem;padding:.7rem .85rem;border:1px solid rgba(0,0,0,.08);border-radius:12px;background:rgba(255,255,255,.82)}.chat-doc-card i{font-size:1.4rem;color:var(--cor-vermelho,#9b0000)}.message.is-pending{opacity:.72}';
    document.head.appendChild(css);

    if (mediaInput) mediaInput.accept = 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime,.mov';

    const asset = (path) => /^(?:https?:|blob:|data:)/i.test(path || '')
      ? path
      : new URL(String(path || '').replace(/^\/+/, ''), document.baseURI).toString();

    const parse = async (response) => {
      const text = await response.text();
      let data;
      try { data = JSON.parse(text); } catch { throw new Error('Resposta inválida do servidor.'); }
      if (!response.ok || !data.success) throw new Error(data.message || data.error || 'Falha na operação.');
      return data;
    };

    const receipt = (el, seen = false) => {
      if (!el || !el.classList.contains('sent')) return;
      const time = el.querySelector('.message-timestamp');
      if (!time) return;
      let mark = time.querySelector('.msg-read');
      if (!mark) {
        mark = document.createElement('span');
        mark.className = 'msg-read';
        mark.textContent = '✓✓';
        time.appendChild(mark);
      }
      mark.classList.toggle('read', seen);
      mark.title = seen ? 'Vista' : 'Enviada';
    };

    const setReadIds = (ids = []) => {
      ids.forEach((raw) => {
        const msgId = Number(raw);
        if (!msgId) return;
        readIds.add(msgId);
        receipt(box.querySelector(`.message.sent[data-message-id="${msgId}"]`), true);
      });
    };

    const convertVideo = (el) => {
      if (!el || el.querySelector('video')) return;
      const p = el.querySelector('p');
      if (!p) return;
      const path = p.textContent.trim();
      if (!/^(?:uploads\/chat\/|https?:\/\/).+\.(mp4|webm|mov)(?:\?.*)?$/i.test(path)) return;
      const video = document.createElement('video');
      video.className = 'chat-media';
      video.controls = true;
      video.playsInline = true;
      video.preload = 'metadata';
      video.src = asset(path);
      p.replaceWith(video);
    };

    const normalize = (el) => {
      if (!el || !el.classList.contains('message')) return;
      convertVideo(el);
      if (el.classList.contains('sent')) {
        const msgId = Number(el.dataset.messageId || 0);
        receipt(el, msgId ? readIds.has(msgId) : false);
      }
    };

    const fileType = (file) => {
      const ext = (file.name.split('.').pop() || '').toLowerCase();
      if (file.type.startsWith('image/') || ['jpg','jpeg','png','gif','webp'].includes(ext)) return 'imagem';
      if (file.type.startsWith('video/') || ['mp4','webm','mov'].includes(ext)) return 'video';
      if (['pdf','doc','docx','txt','rtf'].includes(ext)) return 'arquivo';
      return '';
    };

    const preview = (file, type, localUrl) => {
      document.getElementById('no-messages-text')?.remove();
      const el = document.createElement('div');
      el.className = 'message sent is-pending';

      if (type === 'imagem') {
        const img = document.createElement('img');
        img.className = 'chat-media';
        img.src = localUrl;
        img.alt = file.name;
        el.appendChild(img);
      } else if (type === 'video') {
        const video = document.createElement('video');
        video.className = 'chat-media';
        video.src = localUrl;
        video.controls = true;
        video.playsInline = true;
        video.preload = 'metadata';
        el.appendChild(video);
      } else {
        const card = document.createElement('div');
        card.className = 'chat-doc-card';
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-file-lines';
        const name = document.createElement('span');
        name.textContent = file.name;
        card.append(icon, name);
        el.appendChild(card);
      }

      const time = document.createElement('div');
      time.className = 'date message-timestamp text-end';
      time.textContent = 'Enviando... ';
      el.appendChild(time);
      receipt(el, false);
      box.appendChild(el);
      box.scrollTop = box.scrollHeight;
      return el;
    };

    const sendFile = async (input, event) => {
      const file = input?.files?.[0];
      if (!file) return;
      event.stopImmediatePropagation();
      event.stopPropagation();

      const type = fileType(file);
      const limit = type === 'video' ? 25 * 1024 * 1024 : 10 * 1024 * 1024;
      if (!type) { input.value = ''; alert('Formato de arquivo não suportado.'); return; }
      if (file.size > limit) { input.value = ''; alert(type === 'video' ? 'Vídeo máximo: 25 MB.' : 'Arquivo máximo: 10 MB.'); return; }

      if (modalEl && window.bootstrap?.Modal) bootstrap.Modal.getOrCreateInstance(modalEl).hide();

      const localUrl = type === 'arquivo' ? '' : URL.createObjectURL(file);
      const el = preview(file, type, localUrl);
      const data = new FormData();
      data.append('conversa_id', String(id));
      data.append('arquivo', file);

      try {
        const result = await parse(await fetch(sendUrl, { method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store' }));
        el.classList.remove('is-pending');
        el.dataset.messageId = String(result.id_mensagem);

        const timestamp = el.querySelector('.message-timestamp');
        if (timestamp) {
          timestamp.textContent = result.data_formatada || 'Enviado';
        }

        receipt(el, Boolean(result.lida));
        const finalUrl = asset(result.conteudo);
        el.querySelector('img,video')?.setAttribute('src', finalUrl);

        const card = el.querySelector('.chat-doc-card');
        if (card) {
          const link = document.createElement('a');
          link.href = finalUrl;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.className = 'text-decoration-none text-reset';
          card.replaceWith(link);
          link.appendChild(card);
        }

        if (localUrl) setTimeout(() => URL.revokeObjectURL(localUrl), 1000);
      } catch (error) {
        el.classList.remove('is-pending');
        const timestamp = el.querySelector('.message-timestamp');
        if (timestamp) timestamp.textContent = 'Falha ao enviar';
        alert(error.message || 'Erro ao enviar arquivo.');
      } finally {
        input.value = '';
      }
    };

    [mediaInput, docInput].forEach((input) => {
      input?.addEventListener('change', (event) => sendFile(input, event), true);
    });

    const initialSync = async () => {
      try {
        const result = await parse(await fetch(`${pollUrl}?conversa_id=${id}&ultimo_id=0`, { credentials: 'same-origin', cache: 'no-store' }));
        setReadIds(result.read_ids || []);
        const own = (result.messages || []).filter((msg) => msg.sou_eu);
        const rendered = [...box.querySelectorAll('.message.sent')];
        own.forEach((msg, index) => {
          const el = rendered[index];
          if (!el) return;
          el.dataset.messageId = String(msg.id_mensagem);
          receipt(el, Boolean(msg.lida) || readIds.has(Number(msg.id_mensagem)));
        });
        box.querySelectorAll('.message').forEach(normalize);
      } catch (error) { console.warn(error); }
    };

    const refreshReceipts = async () => {
      try {
        const result = await parse(await fetch(`${pollUrl}?conversa_id=${id}&ultimo_id=2147483647`, { credentials: 'same-origin', cache: 'no-store' }));
        setReadIds(result.read_ids || []);
      } catch {}
    };

    new MutationObserver((changes) => {
      changes.forEach((change) => {
        if (change.type === 'attributes') normalize(change.target);
        change.addedNodes?.forEach((node) => {
          if (!(node instanceof HTMLElement)) return;
          if (node.classList.contains('message')) normalize(node);
          node.querySelectorAll?.('.message').forEach(normalize);
        });
      });
    }).observe(box, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-message-id','class'] });

    initialSync();
    setInterval(refreshReceipts, 2000);
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
