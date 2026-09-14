<?php

define('ADOTE_PATAS_ROUTE_WRAPPER', true);
require_once dirname(__DIR__) . '/route-bootstrap.php';

ob_start();
require dirname(__DIR__) . '/perfil.php';
$html = ob_get_clean();

$baseUrl = ADOTE_PATAS_BASE_URL;
$fotoPerfil = null;
$paginaAtual = $_GET['page'] ?? 'perfil';

if (isset($_SESSION['user_id'], $_SESSION['user_tipo']) && in_array($_SESSION['user_tipo'], ['usuario', 'ong'], true)) {
    try {
        $table = $_SESSION['user_tipo'] === 'usuario' ? 'usuario' : 'ong';
        $idColumn = $_SESSION['user_tipo'] === 'usuario' ? 'id_usuario' : 'id_ong';

        $stmtFoto = $conn->prepare("SELECT foto_perfil FROM {$table} WHERE {$idColumn} = :id LIMIT 1");
        $stmtFoto->execute([':id' => (int) $_SESSION['user_id']]);
        $fotoPerfil = $stmtFoto->fetchColumn() ?: null;
    } catch (Throwable $e) {
        error_log('Erro ao carregar foto de perfil: ' . $e->getMessage());
    }
}

if (!isset($_SESSION['profile_photo_csrf'])) {
    $_SESSION['profile_photo_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['profile_photo_csrf'];
$fotoUrl = $fotoPerfil ? $baseUrl . ltrim($fotoPerfil, '/') : null;

if ($fotoUrl) {
    $sidebarPhoto = '<img src="' . htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Foto de perfil" class="sidebar-profile-photo" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'inline-block\';"><i class="fa-regular fa-circle-user sidebar-profile-icon" style="display:none"></i>';
    $html = str_replace(
        '<i class="fa-regular fa-circle-user sidebar-profile-icon"></i>',
        $sidebarPhoto,
        $html
    );
}

if ($paginaAtual === 'perfil' && in_array($_SESSION['user_tipo'] ?? '', ['usuario', 'ong'], true)) {
    $avatarMarkup = $fotoUrl
        ? '<img id="profile-photo-preview" src="' . htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Foto de perfil">'
        : '<div id="profile-photo-fallback" class="profile-photo-fallback"><i class="fa-regular fa-circle-user"></i></div>';

    $removeButton = $fotoUrl
        ? '<button type="button" id="btn-remove-profile-photo" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-trash me-1"></i> Remover foto</button>'
        : '';

    $editor = <<<HTML
<div class="profile-photo-editor">
    <div class="profile-photo-avatar" id="profile-photo-avatar">
        {$avatarMarkup}
    </div>
    <div class="profile-photo-actions">
        <strong>Foto de perfil</strong>
        <small>JPG, PNG ou WEBP de até 5 MB.</small>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <label for="profile-photo-input" class="btn btn-danger btn-sm mb-0">
                <i class="fa-solid fa-camera me-1"></i> Alterar foto
            </label>
            {$removeButton}
        </div>
        <input type="file" id="profile-photo-input" accept="image/jpeg,image/png,image/webp" hidden>
    </div>
</div>
HTML;

    $html = str_replace('<h1>Meu Perfil</h1>', $editor . "\n<h1>Meu Perfil</h1>", $html);

    $baseJson = json_encode($baseUrl . 'atualizar-foto-perfil.php', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $csrfJson = json_encode($csrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $styles = <<<'CSS'
<style id="profile-photo-styles">
.profile-photo-editor{display:flex;align-items:center;gap:1rem;margin:1.25rem 0 1.5rem;padding:1rem 1.1rem;border:1px solid rgba(0,0,0,.08);border-radius:16px;background:var(--cor-branca,#fff)}
.profile-photo-avatar{width:96px;height:96px;border-radius:50%;overflow:hidden;flex:0 0 96px;background:#f4f4f4;display:flex;align-items:center;justify-content:center;border:3px solid var(--cor-rosa-pastel,#f0c9c4)}
.profile-photo-avatar img{width:100%;height:100%;object-fit:cover;display:block}
.profile-photo-fallback{width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--cor-vermelho,#b65c52);font-size:3rem;background:#fff7f5}
.profile-photo-actions{display:flex;flex-direction:column;align-items:flex-start}
.profile-photo-actions small{color:#6c757d;margin-top:.15rem}
.sidebar-profile-photo{width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid var(--cor-rosa-pastel,#f0c9c4);display:inline-block}
@media(max-width:575.98px){.profile-photo-editor{align-items:flex-start}.profile-photo-avatar{width:78px;height:78px;flex-basis:78px}.profile-photo-actions .btn{font-size:.78rem}}
</style>
CSS;

    $script = <<<HTML
<script id="profile-photo-script">
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('profile-photo-input');
    const removeButton = document.getElementById('btn-remove-profile-photo');
    const endpoint = {$baseJson};
    const csrfToken = {$csrfJson};

    const notify = (message, type = 'success') => {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        } else {
            alert(message);
        }
    };

    const submitPhoto = async (formData) => {
        formData.append('csrf_token', csrfToken);
        const response = await fetch(endpoint, { method: 'POST', body: formData });
        const result = await response.json().catch(() => ({ success: false, message: 'Resposta inválida do servidor.' }));

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Não foi possível atualizar a foto.');
        }

        notify(result.message, 'success');
        setTimeout(() => window.location.reload(), 450);
    };

    input?.addEventListener('change', async () => {
        const file = input.files?.[0];
        if (!file) return;

        if (file.size > 5 * 1024 * 1024) {
            notify('A imagem deve ter no máximo 5 MB.', 'error');
            input.value = '';
            return;
        }

        const data = new FormData();
        data.append('action', 'upload');
        data.append('foto_perfil', file);

        try {
            await submitPhoto(data);
        } catch (error) {
            notify(error.message, 'error');
        } finally {
            input.value = '';
        }
    });

    removeButton?.addEventListener('click', async () => {
        const data = new FormData();
        data.append('action', 'remove');

        try {
            await submitPhoto(data);
        } catch (error) {
            notify(error.message, 'error');
        }
    });
});
</script>
HTML;

    $html = str_replace('</head>', $styles . "\n</head>", $html);
    $html = str_replace('</body>', $script . "\n</body>", $html);
}

echo $html;
