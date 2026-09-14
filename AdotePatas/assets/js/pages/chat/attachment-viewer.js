(() => {
  const init = () => {
    const conversationId = Number(new URLSearchParams(location.search).get('id') || 0);
    const box = document.getElementById('chat-messages-container');
    if (!conversationId || !box) return;

    const pollUrl = new URL('buscar-mensagens/', document.baseURI).toString();

    const attachmentUrl = (messageId, download = false) => {
      const url = new URL('chat-anexo/', document.baseURI);
      url.searchParams.set('id', String(messageId));
      if (download) url.searchParams.set('download', '1');
      return url.toString();
    };

    const style = document.createElement('style');
    style.textContent = `
      .chat-attachment-wrap{display:inline-flex;flex-direction:column;gap:.45rem;max-width:min(390px,78vw)}
      .chat-attachment-wrap img,.chat-attachment-wrap video{display:block;width:100%;max-width:min(390px,78vw);max-height:350px;object-fit:contain;border-radius:12px;background:#111}
      .chat-attachment-wrap img{cursor:zoom-in;background:#f7f7f7}
      .chat-attachment-actions{display:flex;gap:.45rem;flex-wrap:wrap}
      .chat-attachment-action{display:inline-flex;align-items:center;gap:.35rem;border:0;border-radius:999px;padding:.38rem .72rem;background:rgba(255,255,255,.94);color:var(--cor-vermelho,#9b0000);font:600 .76rem/1 Poppins,sans-serif;text-decoration:none;box-shadow:0 1px 5px rgba(0,0,0,.12);cursor:pointer}
      .chat-attachment-action:hover{background:#fff;color:var(--cor-vermelho,#9b0000)}
      .chat-attachment-error{display:none;padding:.7rem;border-radius:9px;background:#fff1f1;color:#9b0000;font:500 .78rem Poppins,sans-serif}
      .chat-attachment-viewer{position:fixed;inset:0;z-index:6000;display:none;align-items:center;justify-content:center;padding:1.25rem;background:rgba(15,15,15,.9);backdrop-filter:blur(5px)}
      .chat-attachment-viewer.open{display:flex}
      .chat-attachment-viewer-card{width:min(1050px,96vw);height:min(780px,92vh);display:flex;flex-direction:column;overflow:hidden;border-radius:18px;background:#111;box-shadow:0 20px 70px rgba(0,0,0,.5)}
      .chat-attachment-viewer-head{height:58px;flex:0 0 auto;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.7rem 1rem;background:#fff}
      .chat-attachment-viewer-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#555;font:600 .9rem Poppins,sans-serif}
      .chat-attachment-viewer-actions{display:flex;align-items:center;gap:.5rem;flex:0 0 auto}
      .chat-attachment-viewer-button{display:inline-flex;align-items:center;gap:.35rem;border:0;border-radius:9px;padding:.55rem .75rem;background:#f4f4f4;color:#555;text-decoration:none;cursor:pointer;font:600 .78rem Poppins,sans-serif}
      .chat-attachment-viewer-body{flex:1 1 auto;min-height:0;display:flex;align-items:center;justify-content:center;padding:1rem;overflow:hidden}
      .chat-attachment-viewer-body img,.chat-attachment-viewer-body video{display:block;max-width:100%;max-height:100%;object-fit:contain;border-radius:10px}
      @media(max-width:767.98px){.chat-attachment-viewer{padding:0}.chat-attachment-viewer-card{width:100vw;height:100dvh;border-radius:0}.chat-attachment-wrap img,.chat-attachment-wrap video{max-width:76vw;max-height:300px}}
    `;
    document.head.appendChild(style);

    let viewer = null;

    const ensureViewer = () => {
      if (viewer) return viewer;

      viewer = document.createElement('div');
      viewer.className = 'chat-attachment-viewer';
      viewer.innerHTML = `
        <div class="chat-attachment-viewer-card">
          <div class="chat-attachment-viewer-head">
            <span class="chat-attachment-viewer-name">Anexo</span>
            <div class="chat-attachment-viewer-actions">
              <a class="chat-attachment-viewer-button viewer-download" href="#"><i class="fa-solid fa-download"></i> Baixar</a>
              <button class="chat-attachment-viewer-button viewer-close" type="button" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
            </div>
          </div>
          <div class="chat-attachment-viewer-body"></div>
        </div>`;

      const close = () => {
        viewer.classList.remove('open');
        viewer.querySelector('video')?.pause();
        const body = viewer.querySelector('.chat-attachment-viewer-body');
        if (body) body.innerHTML = '';
      };

      viewer.querySelector('.viewer-close')?.addEventListener('click', close);
      viewer.addEventListener('click', (event) => {
        if (event.target === viewer) close();
      });
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && viewer.classList.contains('open')) close();
      });
      document.body.appendChild(viewer);
      return viewer;
    };

    const openViewer = (messageId, type, name) => {
      if (!messageId || !['imagem', 'video'].includes(type)) return;
      const root = ensureViewer();
      const body = root.querySelector('.chat-attachment-viewer-body');
      const title = root.querySelector('.chat-attachment-viewer-name');
      const download = root.querySelector('.viewer-download');

      if (title) title.textContent = name || (type === 'video' ? 'Vídeo' : 'Imagem');
      if (download) {
        download.href = attachmentUrl(messageId, true);
        download.setAttribute('download', name || 'anexo');
      }

      if (body) {
        body.innerHTML = '';
        if (type === 'video') {
          const video = document.createElement('video');
          video.src = attachmentUrl(messageId);
          video.controls = true;
          video.playsInline = true;
          video.autoplay = true;
          video.preload = 'metadata';
          body.appendChild(video);
        } else {
          const image = document.createElement('img');
          image.src = attachmentUrl(messageId);
          image.alt = name || 'Imagem enviada';
          body.appendChild(image);
        }
      }

      root.classList.add('open');
    };

    const inferType = (el) => {
      const explicit = el.dataset.messageType || '';
      if (explicit) return explicit;
      if (el.querySelector('video')) return 'video';
      if (el.querySelector('img')) return 'imagem';
      if (el.querySelector('.msg-file-container,.chat-doc-card')) return 'arquivo';

      const text = el.querySelector('p')?.textContent?.trim() || '';
      if (/\.(mp4|webm|mov)(?:\?.*)?$/i.test(text)) return 'video';
      return '';
    };

    const convertVideoText = (el, messageId) => {
      if (el.querySelector('video')) return el.querySelector('video');
      const p = el.querySelector('p');
      const text = p?.textContent?.trim() || '';
      if (!/\.(mp4|webm|mov)(?:\?.*)?$/i.test(text)) return null;

      const video = document.createElement('video');
      video.controls = true;
      video.playsInline = true;
      video.preload = 'metadata';
      video.src = attachmentUrl(messageId);
      p.replaceWith(video);
      return video;
    };

    const ensureWrap = (media) => {
      if (media.parentElement?.classList.contains('chat-attachment-wrap')) return media.parentElement;
      const parent = media.parentElement;
      if (parent?.classList.contains('msg-image-container')) {
        parent.classList.add('chat-attachment-wrap');
        return parent;
      }
      const wrap = document.createElement('div');
      wrap.className = 'chat-attachment-wrap';
      media.replaceWith(wrap);
      wrap.appendChild(media);
      return wrap;
    };

    const decorateMedia = (el, messageId, type, name) => {
      if (!messageId || !['imagem', 'video'].includes(type)) return;
      const media = type === 'video' ? (el.querySelector('video') || convertVideoText(el, messageId)) : el.querySelector('img');
      if (!media) return;

      const secureUrl = attachmentUrl(messageId);
      if (media.getAttribute('src') !== secureUrl) {
        media.setAttribute('src', secureUrl);
        if (type === 'video') media.load();
      }

      media.setAttribute('controls', type === 'video' ? 'controls' : media.getAttribute('controls') || '');
      if (type === 'video') {
        media.controls = true;
        media.playsInline = true;
        media.preload = 'metadata';
      }

      const wrap = ensureWrap(media);
      if (!wrap) return;

      if (!wrap.querySelector('.chat-attachment-error')) {
        const error = document.createElement('div');
        error.className = 'chat-attachment-error';
        error.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Não foi possível carregar a pré-visualização.';
        wrap.appendChild(error);
        media.addEventListener('error', () => {
          media.style.display = 'none';
          error.style.display = 'block';
        });
        const restore = () => {
          media.style.display = '';
          error.style.display = 'none';
        };
        media.addEventListener('load', restore);
        media.addEventListener('loadeddata', restore);
      }

      if (!wrap.querySelector('.chat-attachment-actions')) {
        const actions = document.createElement('div');
        actions.className = 'chat-attachment-actions';

        const open = document.createElement('button');
        open.type = 'button';
        open.className = 'chat-attachment-action';
        open.innerHTML = '<i class="fa-solid fa-expand"></i> Visualizar';
        open.addEventListener('click', () => openViewer(messageId, type, name));

        const download = document.createElement('a');
        download.className = 'chat-attachment-action';
        download.href = attachmentUrl(messageId, true);
        download.setAttribute('download', name || 'anexo');
        download.innerHTML = '<i class="fa-solid fa-download"></i> Baixar';

        actions.append(open, download);
        wrap.appendChild(actions);
      }

      if (type === 'imagem' && !media.dataset.viewerBound) {
        media.dataset.viewerBound = '1';
        media.addEventListener('click', () => openViewer(messageId, type, name));
      }
    };

    const decorateDocument = (el, messageId, name) => {
      if (!messageId) return;
      const card = el.querySelector('.msg-file-container,.chat-doc-card');
      if (!card) return;

      let link = card.closest('a') || card.querySelector('a');
      if (!link) {
        link = document.createElement('a');
        link.className = 'text-decoration-none text-reset';
        card.replaceWith(link);
        link.appendChild(card);
      }
      link.href = attachmentUrl(messageId);
      link.target = '_blank';
      link.rel = 'noopener noreferrer';

      if (!link.nextElementSibling?.classList.contains('chat-attachment-actions')) {
        const actions = document.createElement('div');
        actions.className = 'chat-attachment-actions mt-1';
        const download = document.createElement('a');
        download.className = 'chat-attachment-action';
        download.href = attachmentUrl(messageId, true);
        download.setAttribute('download', name || 'documento');
        download.innerHTML = '<i class="fa-solid fa-download"></i> Baixar';
        actions.appendChild(download);
        link.after(actions);
      }
    };

    const decorate = (el, message = null) => {
      if (!el?.classList?.contains('message')) return;
      if (message?.id_mensagem) el.dataset.messageId = String(message.id_mensagem);
      if (message?.tipo_conteudo) el.dataset.messageType = String(message.tipo_conteudo);
      if (message?.arquivo_nome) el.dataset.fileName = String(message.arquivo_nome);

      const messageId = Number(el.dataset.messageId || 0);
      if (!messageId) return;
      const type = message?.tipo_conteudo || inferType(el);
      const name = message?.arquivo_nome || el.dataset.fileName || '';

      if (type === 'imagem' || type === 'video') decorateMedia(el, messageId, type, name);
      if (type === 'arquivo') decorateDocument(el, messageId, name);
    };

    const syncExisting = async () => {
      try {
        const response = await fetch(`${pollUrl}?conversa_id=${conversationId}&ultimo_id=0`, { credentials: 'same-origin', cache: 'no-store' });
        const data = await response.json();
        if (!response.ok || !data.success) return;

        const rendered = [...box.querySelectorAll('.message')];
        (data.messages || []).forEach((message, index) => {
          if (rendered[index]) decorate(rendered[index], message);
        });
      } catch (error) {
        console.warn('Falha ao preparar anexos da conversa:', error);
      }
    };

    const observer = new MutationObserver((changes) => {
      changes.forEach((change) => {
        if (change.type === 'attributes') decorate(change.target);
        change.addedNodes.forEach((node) => {
          if (!(node instanceof HTMLElement)) return;
          if (node.classList.contains('message')) decorate(node);
          node.querySelectorAll?.('.message').forEach(decorate);
        });
      });
    });

    observer.observe(box, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-message-id', 'class'] });
    syncExisting();
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
