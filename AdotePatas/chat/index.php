<?php

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';
require_once dirname(__DIR__) . '/conexao.php';

// Mantém compatibilidade das consultas legadas que ainda repetem placeholders.
$conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

ob_start();
require dirname(__DIR__) . '/chat.php';
$html = ob_get_clean();

$assetBase = ADOTE_PATAS_BASE_URL;
$baseUrl = htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8');

$currentUserPhoto = null;
if (isset($_SESSION['user_id'], $_SESSION['user_tipo']) && in_array($_SESSION['user_tipo'], ['usuario', 'ong'], true)) {
    try {
        $currentTable = $_SESSION['user_tipo'] === 'usuario' ? 'usuario' : 'ong';
        $currentIdColumn = $_SESSION['user_tipo'] === 'usuario' ? 'id_usuario' : 'id_ong';
        $stmtCurrentPhoto = $conn->prepare("SELECT foto_perfil FROM {$currentTable} WHERE {$currentIdColumn} = :id LIMIT 1");
        $stmtCurrentPhoto->execute([':id' => (int) $_SESSION['user_id']]);
        $currentUserPhoto = $stmtCurrentPhoto->fetchColumn() ?: null;
    } catch (Throwable $e) {
        error_log('Erro ao carregar foto do usuário no chat: ' . $e->getMessage());
    }
}

$legacyLocalBase = '/TCC-AdotePatas/AdotePatas/';
if ($assetBase !== $legacyLocalBase) {
    $html = str_replace($legacyLocalBase, $assetBase, $html);
}

$html = preg_replace(
    '~<link[^>]+href="[^"]*assets/css/pages/chat/chat\.css[^"]*"[^>]*>~i',
    '',
    $html,
    1
);

$chatStyles = <<<HTML
    <link rel="stylesheet" href="{$baseUrl}assets/css/global/global.css?v=20260914-1">
    <link rel="stylesheet" href="{$baseUrl}assets/css/pages/chat/partials/header.css?v=20260914-1">
    <link rel="stylesheet" href="{$baseUrl}assets/css/pages/chat/partials/offcanvas.css?v=20260914-1">
HTML;

$chatCssPath = dirname(__DIR__) . '/assets/css/pages/chat/chat.css';
$chatCss = '';

if (is_readable($chatCssPath)) {
    $chatCss = file_get_contents($chatCssPath) ?: '';
    $chatCss = preg_replace('~@import\s+["\'][^"\']+["\']\s*;\s*~i', '', $chatCss);

    $wallpaperUrl = str_replace("'", "\\'", $assetBase . 'images/chat/wallpaper.jpg');
    $chatCss = str_replace(
        "url('../../../images/chat/wallpaper.jpg')",
        "url('{$wallpaperUrl}')",
        $chatCss
    );

    $chatStyles .= "\n<style id=\"chat-page-inline-styles\">\n{$chatCss}\n</style>";
}

$chatStyles .= <<<'CSS'
<style id="chat-profile-photo-styles">
.chat-avatar-icon-fallback{display:flex;align-items:center;justify-content:center;color:var(--cor-vermelho,#b65c52);background:#fff7f5;font-size:1.6rem;border:1px solid rgba(0,0,0,.06)}
.profile-user-photo{width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid var(--cor-rosa-pastel,#f0c9c4);display:block}
.sidebar-user-photo{width:82px;height:82px;border-radius:50%;object-fit:cover;border:3px solid var(--cor-rosa-pastel,#f0c9c4);display:inline-block}
.message.is-pending{opacity:.72}
.message.is-failed{opacity:1;outline:1px solid rgba(185,28,28,.25)}
.message .message-status-error{color:#b91c1c!important;opacity:1!important}
</style>
CSS;

$html = str_replace('</head>', $chatStyles . "\n</head>", $html);

// O script antigo apenas simulava upload e concorria com os listeners reais do chat.
$html = preg_replace(
    '~<script[^>]+src="[^"]*assets/js/pages/chat/file-size-upload\.js[^"]*"[^>]*>\s*</script>\s*~i',
    '',
    $html,
    1
);

$html = str_replace('buscar_mensagens.php', 'buscar-mensagens.php', $html);

$html = preg_replace(
    '~href="([^"<>]*?)chat/([0-9]+)/?"~',
    'href="$1chat/?id=$2"',
    $html
);

$html = preg_replace_callback(
    '~<img\b(?=[^>]*\bclass="[^"]*\bchat-avatar\b[^"]*")[^>]*>~i',
    static function (array $match): string {
        $tag = $match[0];
        $src = '';

        if (preg_match('~\bsrc="([^"]*)"~i', $tag, $srcMatch)) {
            $src = html_entity_decode($srcMatch[1], ENT_QUOTES, 'UTF-8');
        }

        $fallback = '<span class="chat-avatar chat-avatar-icon-fallback" aria-label="Usuário sem foto"><i class="fa-regular fa-circle-user"></i></span>';

        if ($src === '' || str_contains($src, 'images/perfil/teste.jpg')) {
            return $fallback;
        }

        $tag = preg_replace('~\s+onerror="[^"]*"~i', '', $tag) ?: $tag;
        $tag = preg_replace(
            '~>$~',
            ' onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';">',
            $tag,
            1
        ) ?: $tag;

        $hiddenFallback = '<span class="chat-avatar chat-avatar-icon-fallback" style="display:none" aria-label="Usuário sem foto"><i class="fa-regular fa-circle-user"></i></span>';

        return $tag . $hiddenFallback;
    },
    $html
);

if ($currentUserPhoto) {
    $currentPhotoUrl = htmlspecialchars($assetBase . ltrim($currentUserPhoto, '/'), ENT_QUOTES, 'UTF-8');

    $headerAvatar = '<img src="' . $currentPhotoUrl . '" alt="Minha foto de perfil" class="profile-user-photo" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'inline-block\';"><i class="fa-regular fa-circle-user profile-icon logged-in" style="display:none"></i>';
    $sidebarAvatar = '<img src="' . $currentPhotoUrl . '" alt="Minha foto de perfil" class="sidebar-user-photo" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'inline-block\';"><i class="fa-regular fa-circle-user sidebar-profile-icon logged-in" style="display:none"></i>';

    $html = str_replace(
        '<i class="fa-regular fa-circle-user profile-icon logged-in"></i>',
        $headerAvatar,
        $html
    );
    $html = str_replace(
        '<i class="fa-regular fa-circle-user sidebar-profile-icon logged-in"></i>',
        $sidebarAvatar,
        $html
    );
}

if (!empty($_GET['id'])) {
    $html = str_replace(
        'class="col-lg-4 col-md-5 col-12 chat-sidebar"',
        'class="col-lg-4 col-md-5 col-12 chat-sidebar d-none d-md-block"',
        $html
    );

    $html = str_replace(
        'class="col-lg-8 col-md-7 d-none d-md-flex chat-conversation-area"',
        'class="col-lg-8 col-md-7 col-12 d-flex chat-conversation-area"',
        $html
    );

    $backButton = '<a href="' . htmlspecialchars($assetBase . 'chat/', ENT_QUOTES, 'UTF-8') . '" class="d-md-none me-2 text-decoration-none" aria-label="Voltar para conversas" style="color: var(--cor-vermelho); font-size: 1.25rem;"><i class="fa-solid fa-arrow-left"></i></a>';
    $html = str_replace(
        '<div class="chat-active-header">',
        '<div class="chat-active-header">' . $backButton,
        $html
    );
}

// Remove o fluxo legado (WebSocket + polling duplicado + envio sem validar resposta).
$html = preg_replace_callback(
    '~<script\b[^>]*>(.*?)</script>~si',
    static function (array $match): string {
        $script = $match[1];
        if (str_contains($script, 'connectWebSocket()') && str_contains($script, 'sendMessage()')) {
            return '';
        }
        return $match[0];
    },
    $html
);

if (!empty($conversa_id_ativa)) {
    // A opção de mídia agora representa apenas imagens, que o backend suporta de forma consistente.
    $html = str_replace('accept="image/*,video/*"', 'accept="image/jpeg,image/png,image/gif,image/webp"', $html);

    $chatConfig = [
        'conversationId' => (int) $conversa_id_ativa,
        'lastMessageId' => (int) ($ultimo_id_msg ?? 0),
        'basePath' => $assetBase,
        'sendUrl' => $assetBase . 'mensagem/',
        'pollUrl' => $assetBase . 'buscar-mensagens/',
    ];

    $configJson = json_encode($chatConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $asyncScript = '<script>window.ADOTE_PATAS_CHAT_CONFIG = ' . $configJson . ';</script>' . <<<'HTML'
<script id="adote-patas-async-chat">
document.addEventListener('DOMContentLoaded', () => {
    const config = window.ADOTE_PATAS_CHAT_CONFIG;
    if (!config) return;

    const chatMessages = document.getElementById('chat-messages-container');
    const messageInput = document.getElementById('chat-message-input');
    const sendButton = document.getElementById('chat-send-btn');
    const documentInput = document.getElementById('documentInput');
    const mediaInput = document.getElementById('mediaInput');
    const documentButton = document.getElementById('documentBtn');
    const mediaButton = document.getElementById('mediaBtn');
    const fileModalElement = document.getElementById('fileModal');

    if (!chatMessages || !messageInput || !sendButton) return;

    let lastMessageId = Number(config.lastMessageId || 0);
    let polling = false;
    let sendingText = false;
    const fileModal = fileModalElement ? bootstrap.Modal.getOrCreateInstance(fileModalElement) : null;

    const showToast = (message, type = 'danger') => {
        const toast = document.getElementById('toast-notification');
        const toastIcon = document.getElementById('toast-icon');
        const toastMessage = document.getElementById('toast-message');
        if (!toast || !toastMessage || !toastIcon) return;

        toast.className = 'adp-toast p-0';
        toast.classList.add(`adp-toast--${type}`);
        toastMessage.textContent = message;
        toastIcon.className = 'adp-toast-icon';
        toastIcon.innerHTML = type === 'success'
            ? '<i class="fa-solid fa-check"></i>'
            : '<i class="fa-solid fa-xmark"></i>';

        toast.style.display = 'flex';
        requestAnimationFrame(() => toast.classList.add('show'));

        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => {
            toast.classList.remove('show');
            toast.classList.add('hide');
            window.setTimeout(() => {
                toast.style.display = 'none';
                toast.classList.remove('hide');
            }, 350);
        }, 3500);
    };

    const scrollToBottom = () => {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    };

    const assetUrl = (path) => {
        if (!path) return '#';
        if (/^(?:https?:|blob:|data:)/i.test(path)) return path;
        return config.basePath + String(path).replace(/^\/+/, '');
    };

    const messageExists = (id) => {
        if (!id) return false;
        return Boolean(chatMessages.querySelector(`[data-message-id="${Number(id)}"]`));
    };

    const appendMessage = (message, side = 'received', pending = false) => {
        const noMessages = document.getElementById('no-messages-text');
        noMessages?.remove();

        const wrapper = document.createElement('div');
        wrapper.className = `message ${side}${pending ? ' is-pending' : ''}`;

        if (message.id_mensagem) {
            wrapper.dataset.messageId = String(message.id_mensagem);
        }

        const type = message.tipo_conteudo || 'texto';

        if (type === 'imagem') {
            const imageContainer = document.createElement('div');
            imageContainer.className = 'msg-image-container';

            const image = document.createElement('img');
            image.src = assetUrl(message.conteudo);
            image.alt = 'Imagem enviada';
            image.className = 'img-fluid rounded';
            image.style.maxWidth = '250px';
            image.style.cursor = 'pointer';
            image.addEventListener('click', () => window.open(image.src, '_blank'));

            imageContainer.appendChild(image);
            wrapper.appendChild(imageContainer);
        } else if (type === 'arquivo') {
            const fileContainer = document.createElement('div');
            fileContainer.className = 'msg-file-container p-2 bg-light rounded border d-flex align-items-center gap-2';

            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-file-lines text-danger text-xl';

            const link = document.createElement('a');
            link.className = 'text-decoration-none text-dark text-break';
            link.textContent = message.arquivo_nome || 'Documento';
            link.href = pending ? '#' : assetUrl(message.conteudo);
            link.target = pending ? '' : '_blank';
            if (pending) link.addEventListener('click', (event) => event.preventDefault());

            fileContainer.append(icon, link);
            wrapper.appendChild(fileContainer);
        } else {
            const paragraph = document.createElement('p');
            paragraph.className = 'mb-1';
            paragraph.textContent = message.conteudo || '';
            wrapper.appendChild(paragraph);
        }

        const timestamp = document.createElement('div');
        timestamp.className = 'date message-timestamp text-end';
        timestamp.style.fontSize = '.75rem';
        timestamp.style.opacity = '.8';
        timestamp.textContent = message.data_formatada || (pending ? 'Enviando...' : '');
        wrapper.appendChild(timestamp);

        chatMessages.appendChild(wrapper);
        scrollToBottom();
        return wrapper;
    };

    const markFailed = (element, message = 'Falha ao enviar') => {
        if (!element) return;
        element.classList.remove('is-pending');
        element.classList.add('is-failed');
        const timestamp = element.querySelector('.message-timestamp');
        if (timestamp) {
            timestamp.textContent = message;
            timestamp.classList.add('message-status-error');
        }
    };

    const parseResponse = async (response) => {
        const raw = await response.text();
        let result;

        try {
            result = JSON.parse(raw);
        } catch (error) {
            throw new Error('O servidor retornou uma resposta inválida. Atualize a página e tente novamente.');
        }

        if (!response.ok || !result.success) {
            throw new Error(result.message || result.error || 'Não foi possível concluir a operação.');
        }

        return result;
    };

    const sendFormData = async (data) => {
        const response = await fetch(config.sendUrl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            cache: 'no-store',
        });
        return parseResponse(response);
    };

    const sendTextMessage = async () => {
        const content = messageInput.value.trim();
        if (!content || sendingText) return;

        sendingText = true;
        sendButton.disabled = true;
        messageInput.value = '';

        const pendingElement = appendMessage({
            conteudo: content,
            tipo_conteudo: 'texto',
            data_formatada: 'Enviando...',
        }, 'sent', true);

        const data = new FormData();
        data.append('conversa_id', String(config.conversationId));
        data.append('conteudo', content);

        try {
            const result = await sendFormData(data);
            pendingElement.classList.remove('is-pending');
            pendingElement.dataset.messageId = String(result.id_mensagem);

            const timestamp = pendingElement.querySelector('.message-timestamp');
            if (timestamp) timestamp.textContent = result.data_formatada || 'Enviado';

            lastMessageId = Math.max(lastMessageId, Number(result.id_mensagem || 0));
        } catch (error) {
            markFailed(pendingElement);
            if (!messageInput.value) messageInput.value = content;
            showToast(error.message, 'danger');
        } finally {
            sendingText = false;
            sendButton.disabled = false;
            messageInput.focus();
        }
    };

    const sendFile = async (input) => {
        const file = input?.files?.[0];
        if (!file) return;

        fileModal?.hide();

        if (file.size > 10 * 1024 * 1024) {
            showToast('O arquivo deve ter no máximo 10 MB.', 'danger');
            input.value = '';
            return;
        }

        const isImage = file.type.startsWith('image/');
        const allowedDocuments = ['pdf', 'doc', 'docx', 'txt', 'rtf'];
        const extension = (file.name.split('.').pop() || '').toLowerCase();

        if (!isImage && !allowedDocuments.includes(extension)) {
            showToast('Formato de arquivo não suportado.', 'danger');
            input.value = '';
            return;
        }

        let localUrl = '';
        if (isImage) localUrl = URL.createObjectURL(file);

        const pendingElement = appendMessage({
            conteudo: localUrl,
            tipo_conteudo: isImage ? 'imagem' : 'arquivo',
            arquivo_nome: file.name,
            data_formatada: 'Enviando...',
        }, 'sent', true);

        const data = new FormData();
        data.append('conversa_id', String(config.conversationId));
        data.append('arquivo', file);

        try {
            const result = await sendFormData(data);
            pendingElement.remove();
            appendMessage(result, 'sent');
            lastMessageId = Math.max(lastMessageId, Number(result.id_mensagem || 0));
        } catch (error) {
            markFailed(pendingElement);
            showToast(error.message, 'danger');
        } finally {
            if (localUrl) URL.revokeObjectURL(localUrl);
            input.value = '';
        }
    };

    const pollMessages = async () => {
        if (polling || document.hidden) return;
        polling = true;

        try {
            const url = new URL(config.pollUrl, window.location.origin);
            url.searchParams.set('conversa_id', String(config.conversationId));
            url.searchParams.set('ultimo_id', String(lastMessageId));

            const response = await fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const result = await parseResponse(response);

            for (const message of result.messages || []) {
                const id = Number(message.id_mensagem || 0);

                if (!messageExists(id)) {
                    appendMessage(message, message.sou_eu ? 'sent' : 'received');
                }

                if (id > lastMessageId) lastMessageId = id;
            }
        } catch (error) {
            console.error('Falha ao atualizar mensagens:', error);
        } finally {
            polling = false;
        }
    };

    sendButton.addEventListener('click', sendTextMessage);
    messageInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendTextMessage();
        }
    });

    documentButton?.addEventListener('click', () => documentInput?.click());
    mediaButton?.addEventListener('click', () => mediaInput?.click());
    documentInput?.addEventListener('change', () => sendFile(documentInput));
    mediaInput?.addEventListener('change', () => sendFile(mediaInput));

    scrollToBottom();
    pollMessages();
    window.setInterval(pollMessages, 2000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) pollMessages();
    });
});
</script>
HTML;

    $html = str_replace('</body>', $asyncScript . "\n</body>", $html);
}

echo $html;
