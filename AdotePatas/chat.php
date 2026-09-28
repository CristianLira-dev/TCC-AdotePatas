<?php
require_once __DIR__ . '/app/security.php';
appStartSession();
include_once 'conexao.php';
include_once 'session.php'; 

// 1. Configuração e Segurança
if ($_SERVER['SERVER_NAME'] == 'localhost') {
    $base_path = '/TCC-AdotePatas/AdotePatas/';
} else {
    $base_path = '/';
}
$pagina = "chats";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_tipo'])) {
    header("Location: " . $base_path . "login");
    exit;
}

$user_id_logado = $_SESSION['user_id'];
$user_tipo_logado = $_SESSION['user_tipo'];
$primeiro_nome = '';
if (isset($_SESSION['nome'])) {
    $partes_nome = explode(' ', $_SESSION['nome']);
    $primeiro_nome = $partes_nome[0];
}

$conversa_id_ativa = $_GET['id'] ?? null;
if ($conversa_id_ativa) {
    $conversa_id_ativa = filter_var($conversa_id_ativa, FILTER_SANITIZE_NUMBER_INT);
}

// 4. Busca a LISTA DE CONVERSAS (Sidebar)
$lista_conversas = [];
try {
    $sql_lista = "
        SELECT 
            c.id_conversa, 
            s.id_pet,
            p.nome AS pet_nome,
            CASE 
                WHEN c.id_adotante_fk = :user_id THEN
                    CASE 
                        WHEN c.tipo_protetor = 'usuario' THEN u_prot.nome
                        WHEN c.tipo_protetor = 'ong' THEN o_prot.nome
                    END
                ELSE
                    u_adot.nome
            END AS nome_outra_pessoa,
            CASE 
                WHEN c.id_adotante_fk = :user_id THEN
                    CASE 
                        WHEN c.tipo_protetor = 'usuario' THEN u_prot.foto_perfil
                        WHEN c.tipo_protetor = 'ong' THEN o_prot.foto_perfil
                    END
                ELSE
                    u_adot.foto_perfil
            END AS foto_outra_pessoa,
            (SELECT m.conteudo FROM mensagem m WHERE m.id_conversa_fk = c.id_conversa ORDER BY m.data_envio DESC LIMIT 1) AS preview_ultima_msg,
            (SELECT m.data_envio FROM mensagem m WHERE m.id_conversa_fk = c.id_conversa ORDER BY m.data_envio DESC LIMIT 1) AS data_ultima_msg
        FROM conversa c
        LEFT JOIN solicitacao s ON c.id_solicitacao_fk = s.id_solicitacao
        LEFT JOIN pet p ON s.id_pet = p.id_pet
        LEFT JOIN usuario u_adot ON c.id_adotante_fk = u_adot.id_usuario
        LEFT JOIN usuario u_prot ON c.id_protetor_fk = u_prot.id_usuario AND c.tipo_protetor = 'usuario'
        LEFT JOIN ong o_prot ON c.id_protetor_fk = o_prot.id_ong AND c.tipo_protetor = 'ong'
        WHERE
            (c.id_adotante_fk = :user_id AND :user_tipo = 'usuario')
        OR 
            (c.id_protetor_fk = :user_id AND c.tipo_protetor = :user_tipo)
        ORDER BY data_ultima_msg DESC
    ";
    $stmt_lista = $conn->prepare($sql_lista);
    $stmt_lista->execute([':user_id' => $user_id_logado, ':user_tipo' => $user_tipo_logado]);
    $lista_conversas = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar lista: " . $e->getMessage());
}

// 5. Busca a CONVERSA ATIVA e MENSAGENS
$conversa_ativa = null;
$lista_mensagens = [];
$eu_sou_protetor = false; 
$ultimo_id_msg = 0; // *** NOVO: Variável para rastrear o último ID ***

if ($conversa_id_ativa) {
    try {
        $sql_ativa = "
            SELECT 
                c.id_conversa, 
                c.id_solicitacao_fk,
                c.id_adotante_fk,
                s.status_solicitacao,
                p.nome AS pet_nome,
                (c.id_protetor_fk = :user_id AND c.tipo_protetor = :user_tipo) AS sou_protetor,
                CASE 
                    WHEN c.id_adotante_fk = :user_id THEN
                        CASE 
                            WHEN c.tipo_protetor = 'usuario' THEN u_prot.nome
                            WHEN c.tipo_protetor = 'ong' THEN o_prot.nome
                        END
                    ELSE
                        u_adot.nome
                END AS nome_outra_pessoa,
                CASE 
                    WHEN c.id_adotante_fk = :user_id THEN
                        CASE 
                            WHEN c.tipo_protetor = 'usuario' THEN u_prot.foto_perfil
                            WHEN c.tipo_protetor = 'ong' THEN o_prot.foto_perfil
                        END
                    ELSE
                        u_adot.foto_perfil
                END AS foto_outra_pessoa
            FROM conversa c
            LEFT JOIN solicitacao s ON c.id_solicitacao_fk = s.id_solicitacao
            LEFT JOIN pet p ON s.id_pet = p.id_pet
            LEFT JOIN usuario u_adot ON c.id_adotante_fk = u_adot.id_usuario
            LEFT JOIN usuario u_prot ON c.id_protetor_fk = u_prot.id_usuario AND c.tipo_protetor = 'usuario'
            LEFT JOIN ong o_prot ON c.id_protetor_fk = o_prot.id_ong AND c.tipo_protetor = 'ong'
            WHERE 
                c.id_conversa = :conversa_id
            AND 
                (c.id_adotante_fk = :user_id OR (c.id_protetor_fk = :user_id AND c.tipo_protetor = :user_tipo))
            LIMIT 1
        ";
        $stmt_ativa = $conn->prepare($sql_ativa);
        $stmt_ativa->execute([':conversa_id' => $conversa_id_ativa, ':user_id' => $user_id_logado, ':user_tipo' => $user_tipo_logado]);
        $conversa_ativa = $stmt_ativa->fetch(PDO::FETCH_ASSOC);

        if ($conversa_ativa) {
            $eu_sou_protetor = (bool)$conversa_ativa['sou_protetor'];
            $sql_msgs = "SELECT * FROM mensagem WHERE id_conversa_fk = :conversa_id ORDER BY data_envio ASC";
            $stmt_msgs = $conn->prepare($sql_msgs);
            $stmt_msgs->execute([':conversa_id' => $conversa_id_ativa]);
            $lista_mensagens = $stmt_msgs->fetchAll(PDO::FETCH_ASSOC);
            
            // *** NOVO: Captura o ID da última mensagem carregada pelo PHP ***
            if (!empty($lista_mensagens)) {
                $ultimo_msg = end($lista_mensagens);
                $ultimo_id_msg = $ultimo_msg['id_mensagem'];
            }
        } else {
            $conversa_id_ativa = null;
        }
    } catch (PDOException $e) {
        $conversa_id_ativa = null;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversas - Adote Patas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/pages/chat/chat.css">
    <link rel="icon" type="image/png" href="<?php echo $base_path; ?>images/global/Logo-AdotePatas.png"/>
</head>
<body class="chat-page-body">

<header>
  <nav class="navbar navbar-expand">
    <div class="container">
      <a class="navbar-brand" href="<?php echo $base_path; ?>./">
        <img src="<?php echo $base_path; ?>images/global/Logo-AdotePatas.png" alt="Logo Adote Patas" class="navbar-logo">
      </a>
      <div class="d-flex align-items-center gap-4">
        <div class="d-none d-xl-block">
          <ul class="navbar-nav d-flex flex-row gap-4 mb-0">
            <li class="nav-item"><a class="nav-link navlink" href="<?php echo $base_path; ?>sobre-nos">Sobre Nós</a></li>
            <li class="nav-item"><a class="nav-link navlink" href="<?php echo $base_path; ?>ajuda.php">Ajuda</a></li>
          </ul>
        </div>
        <a href="<?php echo $base_path; ?>perfil?page=perfil" class="profile-info-link d-flex align-items-center gap-3 text-decoration-none">
          <div class="d-flex align-items-center flex-row-reverse gap-2">
            <i class="fa-regular fa-circle-user profile-icon logged-in"></i>
            <span class="profile-name fs-5" style="color: var(--cor-vermelho);"><?php echo htmlspecialchars($primeiro_nome); ?></span>
          </div>
        </a>
        <button class="border-0 bg-transparent p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar">
          <span class="fas fa-bars nav-icon" style="font-size: 2rem;"></span>
        </button>
      </div>
    </div>
  </nav>
</header>
      
<main class="chat-container container">
  <div class="row g-0 chat-main-card">
    <aside class="col-lg-4 col-md-5 col-12 chat-sidebar">
      <div class="chat-sidebar-header">
        <h1 class="chat-title">Conversas</h1>
        <div class="chat-search-wrapper">
          <i class="fa-solid fa-magnifying-glass search-icon"></i>
          <input type="text" class="form-control chat-search-input" placeholder="Pesquisar conversas...">
        </div>
      </div>
      <div class="chat-list">
        <?php if (empty($lista_conversas)): ?>
            <p class="text-center p-3 text-muted">Nenhuma conversa encontrada.</p>
        <?php else: ?>
            <?php foreach ($lista_conversas as $conversa): ?>
              <?php
                $is_active = ($conversa_id_ativa == $conversa['id_conversa']);
                $avatar_path = $base_path . 'images/perfil/teste.jpg';
                if (!empty($conversa['foto_outra_pessoa'])) {
                    $avatar_path = $base_path . htmlspecialchars($conversa['foto_outra_pessoa']);
                }
                $data_formatada = 'Sem data';
                if (!empty($conversa['data_ultima_msg'])) {
                    $data_formatada = date('d/m/Y', strtotime($conversa['data_ultima_msg']));
                }
              ?>
              <a href="<?php echo $base_path; ?>chat/<?php echo $conversa['id_conversa']; ?>" 
                 class="chat-list-item <?php echo $is_active ? 'active' : ''; ?>">
                <img src="<?php echo $avatar_path; ?>" class="chat-avatar" onerror="this.src='<?php echo $base_path; ?>images/perfil/teste.jpg';">
                <div class="chat-item-details">
                  <div class="chat-item-header">
                    <span class="chat-name"><?php echo htmlspecialchars($conversa['nome_outra_pessoa']); ?></span>
                    <span class="chat-date"><?php echo $data_formatada; ?></span>
                  </div>
                  <p class="chat-preview-title">Interesse em: <strong><?php echo htmlspecialchars($conversa['pet_nome']); ?></strong></p>
                  <p class="chat-preview"><?php echo htmlspecialchars($conversa['preview_ultima_msg'] ?? 'Inicie a conversa...'); ?></p>
                </div>
              </a>
            <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </aside>

    <section class="col-lg-8 col-md-7 d-none d-md-flex chat-conversation-area">
      <?php if ($conversa_ativa): ?>
        <?php
            $avatar_ativo_path = $base_path . 'images/perfil/teste.jpg';
            if (!empty($conversa_ativa['foto_outra_pessoa'])) {
                $avatar_ativo_path = $base_path . htmlspecialchars($conversa_ativa['foto_outra_pessoa']);
            }
        ?>
        <div class="chat-active-header">
            <img src="<?php echo $avatar_ativo_path; ?>" class="chat-avatar" onerror="this.src='<?php echo $base_path; ?>images/perfil/teste.jpg';">
            <div class="chat-active-info">
                <span class="chat-active-name"><?php echo htmlspecialchars($conversa_ativa['nome_outra_pessoa']); ?></span>
                <span class="chat-active-pet">Interessado(a) em: <strong><?php echo htmlspecialchars($conversa_ativa['pet_nome']); ?></strong></span>
            </div>
            <?php if ($eu_sou_protetor): ?>
                <a href="<?php echo $base_path; ?>gerar-pdf.php?solicitacao_id=<?php echo $conversa_ativa['id_solicitacao_fk']; ?>" 
                   target="_blank" class="btn btn-outline-danger btn-sm ms-auto pdf-button" style="color: var(--cor-vermelho); border: solid 1px var(--cor-vermelho)" title="Ver formulário">
                   <i class="fa-solid fa-file-pdf me-1"></i> Ver Formulário
                </a>
            <?php endif; ?>
            <div class="ms-auto d-flex align-items-center gap-2 adoption-status-controls">
                <span class="badge text-bg-light" id="adoption-status-label"><?php echo appEscape(str_replace('_', ' ', $conversa_ativa['status_solicitacao'])); ?></span>
                <?php if ($eu_sou_protetor): ?>
                    <select id="adoption-status" class="form-select form-select-sm" aria-label="Status da adoção">
                        <?php foreach (['em_conversa' => 'Em conversa', 'entrevista' => 'Entrevista', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado', 'concluido' => 'Concluído'] as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $conversa_ativa['status_solicitacao'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-danger btn-sm" id="save-adoption-status">Atualizar</button>
                <?php elseif ($user_tipo_logado === 'usuario' && !in_array($conversa_ativa['status_solicitacao'], ['recusado', 'concluido', 'cancelado'], true)): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="cancel-adoption">Cancelar solicitação</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="chat-messages" id="chat-messages-container">
            <?php if (empty($lista_mensagens)): ?>
                <p class="text-center text-muted mt-4" id="no-messages-text">Nenhuma mensagem ainda.</p>
            <?php else: ?>
                <?php foreach ($lista_mensagens as $msg): ?>
                    <?php
                        $classe_msg = 'received'; 
                        if ($msg['id_remetente_fk'] == $user_id_logado && $msg['tipo_remetente'] == $user_tipo_logado) {
                            $classe_msg = 'sent';
                        }
                        $data_msg = date('H:i, d/m/Y', strtotime($msg['data_envio']));
                        $conteudoHtml = '';
                        $attachmentUrl = $base_path . 'chat-anexo/?id=' . (int) $msg['id_mensagem'];
                        if ($msg['tipo_conteudo'] == 'imagem') {
                            
                        $conteudoHtml = '<div class="msg-image-container"><img src="' . htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') . '" class="img-fluid rounded" style="max-width: 250px; cursor: pointer;" onclick="window.open(this.src)"></div>';
                        } elseif ($msg['tipo_conteudo'] == 'arquivo') {
                            $nomeArquivo = $msg['arquivo_nome'] ?: 'Documento';
                            $conteudoHtml = '<div class="msg-file-container p-2 bg-light rounded border d-flex align-items-center gap-2"><i class="fa-solid fa-file-lines text-danger text-xl"></i><a href="' . htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="text-decoration-none text-dark text-break">' . htmlspecialchars($nomeArquivo) . '</a></div>';
                        } else {
                            $conteudoHtml = '<p class="mb-1">' . nl2br(htmlspecialchars($msg['conteudo'])) . '</p>';
                        }
                    ?>
                     <div class="message <?php echo $classe_msg; ?>">
                        <?php echo $conteudoHtml; ?>
                        <div class="date message-timestamp"><?php echo $data_msg; ?></div>
                    </div>  
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-input-area">
          <button class="files chat-send-files" type="button" data-bs-toggle="modal" data-bs-target="#fileModal">
            <i class="fa-solid fa-plus"></i>
          </button>
            <input type="text" class="form-control chat-message-input" id="chat-message-input" placeholder="Digite sua mensagem...">
            <button class="btn chat-send-btn" type="button" id="chat-send-btn"><i class="fa-solid fa-paper-plane me-1"></i></button>
        </div>

        <div class="modal fade" id="fileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-sm modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Enviar Arquivo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary btn-lg" id="documentBtn"><i class="fa-solid fa-file-lines me-2"></i>Documento</button>
                            <button type="button" class="btn btn-outline-success btn-lg" id="mediaBtn"><i class="fa-solid fa-image me-2"></i>Fotos/Vídeos</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <input type="file" id="documentInput" accept=".pdf,.doc,.docx,.txt,.rtf" hidden>
        <input type="file" id="mediaInput" accept="image/*,video/*" hidden>

      <?php else: ?>
        <div class="chat-placeholder">
            <img src="<?php echo $base_path; ?>images/global/Logo-AdotePatas.png" class="chat-placeholder-logo">
            <h2 class="adote-patas">Adote Patas</h2>
            <p>Selecione uma conversa ao lado para começar.</p>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>

<div id="toast-notification" class="adp-toast p-0" style="display: none;">
    <div id="toast-icon" class="adp-toast-icon" style="font-size: 1.6rem"></div>
    <div class="adp-toast-content"><p id="toast-message" class="adp-toast-message text-center"></p></div>
    <div class="adp-toast-progress-bar"></div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar">
  <div class="offcanvas-header border-bottom">
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body p-0">
      <aside class="profile-sidebar p-3">
        <div class="sidebar-header text-center mb-4">
          <i class="fa-regular fa-circle-user sidebar-profile-icon logged-in"></i>
          <h5 class="mt-2 mb-0"><?php echo htmlspecialchars($_SESSION['nome']); ?></h5>
          <small class="text-muted fs-6"><?php echo htmlspecialchars(ucfirst($user_tipo_logado)); ?></small>
        </div>
        <nav class="nav nav-pills flex-column profile-nav">
          <div class="d-xl-none">
            <a class="nav-link" href="<?php echo $base_path; ?>sobre-nos"><i class="fa-solid fa-info-circle fa-fw me-2"></i> Sobre Nós</a>
            <a class="nav-link" href="#"><i class="fa-solid fa-question-circle fa-fw me-2"></i> Ajuda</a>
            <hr class="my-2">
          </div>
          <a class="nav-link <?php echo ($pagina == 'perfil') ? 'active' : ''; ?>" href="<?php echo $base_path; ?>perfil?page=perfil"><i class="fa-regular fa-circle-user fa-fw me-2"></i> Meu Perfil</a>
          <a class="nav-link <?php echo ($pagina == 'meus-pets') ? 'active' : ''; ?>" href="<?php echo $base_path; ?>perfil?page=meus-pets"><i class="fa-solid fa-paw fa-fw me-2"></i> Meus Pets</a>
          <a class="nav-link <?php echo ($pagina == 'pets-curtidos') ? 'active' : ''; ?>" href="<?php echo $base_path; ?>perfil?page=pets-curtidos"><i class="fa-regular fa-heart fa-fw me-2"></i> Pets Curtidos</a>
          <a class="nav-link <?php echo ($pagina == 'chats') ? 'active' : ''; ?>" href="<?php echo $base_path; ?>chat"><i class="fa-regular fa-comments fa-fw me-2"></i> Chats</a>
          <hr class="my-2">
          <a class="nav-link logout-link-sidebar" href="<?php echo $base_path; ?>sair.php"><i class="fa-solid fa-right-from-bracket fa-fw me-2"></i> Sair</a>
        </nav>
      </aside>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $base_path; ?>assets/js/pages/chat/file-size-upload.js" ></script>

<?php if ($conversa_ativa): ?>
<script>
     document.addEventListener('DOMContentLoaded', function() {
        const chatMessages = document.getElementById('chat-messages-container');
        const messageInput = document.getElementById('chat-message-input');
        const sendBtn = document.getElementById('chat-send-btn');
        
        const docInput = document.getElementById('documentInput');
        const mediaInput = document.getElementById('mediaInput');
        const fileModalEl = document.getElementById('fileModal');
        const fileModal = bootstrap.Modal.getOrCreateInstance(fileModalEl);

        const conversaId = <?php echo json_encode($conversa_id_ativa); ?>;
        const basePath = <?php echo json_encode($base_path); ?>;
        const postURL = basePath + 'mensagem.php';
        const pollURL = basePath + 'buscar_mensagens.php';
        
        let lastMessageId = <?php echo json_encode($ultimo_id_msg); ?>;

        // Configuração do WebSocket
        const wsScheme = window.location.protocol === 'https:' ? 'wss' : 'ws';
        const wsHost = '<?php echo $_SERVER['SERVER_NAME']; ?>';
        const wsPort = '8080';
        const wsUrl = `${wsScheme}://${wsHost}:${wsPort}`;
        
        let ws = null;

        function connectWebSocket() {
            try {
                ws = new WebSocket(wsUrl);
                
                ws.onopen = function() {
                    console.log('Conectado ao WebSocket');
                    // Inscrever na conversa atual
                    ws.send(JSON.stringify({
                        type: 'subscribe',
                        conversa_id: conversaId
                    }));
                };

                ws.onmessage = function(event) {
                    const data = JSON.parse(event.data);
                    
                    if (data.type === 'new_message') {
                        // Adiciona mensagem apenas se não for do usuário atual
                        if (data.user_id != userId || data.user_tipo != userTipo) {
                            addMessageToUI(data.mensagem, 'received', data.timestamp);
                        }
                    }
                };

                ws.onclose = function() {
                    console.log('Conexão WebSocket fechada. Tentando reconectar em 5 segundos.');
                    setTimeout(connectWebSocket, 5000);
                };

                ws.onerror = function(error) {
                    console.error('Erro no WebSocket:', error);
                };
            } catch (error) {
                console.error('Erro ao conectar WebSocket:', error);
                // Fallback para polling se WebSocket falhar
                startPolling();
            }
        }

        function convertToWebP(file) {
            return new Promise((resolve, reject) => {
                if (file.type === 'image/webp') {
                    resolve(file);
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        canvas.width = img.width;
                        canvas.height = img.height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0);
                        
                        canvas.toBlob(function(blob) {
                             if (blob) {
                                const fileNameParts = file.name.split('.');
                                fileNameParts.pop(); 
                                const newFileName = fileNameParts.join('.') + '.webp';
                                const webpFile = new File([blob], newFileName, { type: 'image/webp' });
                                resolve(webpFile);
                            } else {
                                reject(new Error('Falha na conversão do Canvas para Blob'));
                            }
                        }, 'image/webp', 0.8);
                    };
                    img.onerror = reject;
                    img.src = event.target.result;
                };
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        }

        function scrollToBottom() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        scrollToBottom();

        function addMessageToUI(conteudo, side, timestamp = 'enviando...', tipo = 'texto', arquivoNome = '') {
            const noMsg = document.getElementById('no-messages-text');
            if(noMsg) noMsg.remove();

            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message', side);

            if (tipo === 'imagem') {
                const imgSrc = conteudo.startsWith('blob:') ? conteudo : basePath + conteudo;
                const imageContainer = document.createElement('div');
                imageContainer.className = 'msg-image-container';
                const image = document.createElement('img');
                image.src = imgSrc;
                image.alt = 'Imagem enviada';
                image.className = 'img-fluid rounded';
                image.style.maxWidth = '250px';
                image.style.cursor = 'pointer';
                image.addEventListener('click', () => window.open(image.src, '_blank', 'noopener'));
                imageContainer.appendChild(image);
                messageDiv.appendChild(imageContainer);
            } else if (tipo === 'arquivo') {
                const fileLink = basePath + conteudo;
                const fileContainer = document.createElement('div');
                fileContainer.className = 'msg-file-container p-2 bg-light rounded border d-flex align-items-center gap-2';
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-file-lines text-danger text-xl';
                const link = document.createElement('a');
                link.href = fileLink;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.className = 'text-decoration-none text-dark text-break';
                link.textContent = arquivoNome || 'Documento';
                fileContainer.append(icon, link);
                messageDiv.appendChild(fileContainer);
            } else {
                const paragraph = document.createElement('p');
                paragraph.className = 'mb-1';
                paragraph.textContent = conteudo;
                messageDiv.appendChild(paragraph);
            }

            const timestampElement = document.createElement('div');
            timestampElement.className = 'date message-timestamp text-end';
            timestampElement.style.fontSize = '0.75rem';
            timestampElement.style.opacity = '0.8';
            timestampElement.textContent = timestamp;
            messageDiv.appendChild(timestampElement);

            chatMessages.appendChild(messageDiv);
            scrollToBottom();

            return timestampElement;
        }

        async function sendMessage() {
            const conteudo = messageInput.value.trim();
            if (conteudo === '' || !conversaId) return;

            messageInput.value = '';
            messageInput.focus();

            const timestampElement = addMessageToUI(conteudo, 'sent');

            try {
                const response = await fetch(postURL, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ conversa_id: conversaId, conteudo: conteudo })
                });
                // Opcional: Atualizar timestamp se tiver resposta
            } catch (error) {
                console.error('Erro envio texto:', error);
                if (timestampElement) {
                    timestampElement.textContent = 'Erro ao enviar';
                    timestampElement.style.color = 'red';
                }
            }
        }

        async function sendFile(fileInput) {
            let file = fileInput.files[0];
            if (!file) return;

            fileModal.hide();

            if (file.type.startsWith('image/') && file.type !== 'image/gif') {
                try {
                    file = await convertToWebP(file);
                } catch (err) {
                    console.error("Erro na conversão WebP:", err);
                    alert("Erro ao processar imagem. Tente novamente.");
                    return;
                }
            }

            // 1. UI Otimista
            const tempUrl = URL.createObjectURL(file);
            let tipoVisual = 'arquivo';
            if (file.type.startsWith('image/')) tipoVisual = 'imagem';

            // Captura o elemento de timestamp para atualizar depois
            const timestampElement = addMessageToUI(tempUrl, 'sent', 'enviando...', tipoVisual, file.name);

            // 2. Envio
            const formData = new FormData();
            formData.append('conversa_id', conversaId);
            formData.append('arquivo', file);

            try {
                const response = await fetch(postURL, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    // SUCESSO: Atualiza o status visual
                    if (timestampElement) {
                        timestampElement.textContent = result.timestamp || 'enviado agora';
                    }
                } else {
                    // ERRO NO BACK-END (Ex: arquivo muito grande)
                    if (timestampElement) {
                        timestampElement.textContent = 'Falha';
                        timestampElement.style.color = 'red';
                    }
                    alert('Erro: ' + result.message);
                }
            } catch (error) {
                console.error('Erro upload:', error);
                if (timestampElement) {
                    timestampElement.textContent = 'Erro rede';
                    timestampElement.style.color = 'red';
                }
                alert('Erro de conexão no upload.');
            }
            
            fileInput.value = '';
        }

        sendBtn.addEventListener('click', sendMessage);
        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });
        
        document.getElementById('documentBtn').addEventListener('click', () => docInput.click());
        document.getElementById('mediaBtn').addEventListener('click', () => mediaInput.click());

        async function pollMessages() {
            try {
                const url = `${pollURL}?conversa_id=${conversaId}&ultimo_id=${lastMessageId}`;
                const response = await fetch(url);
                const result = await response.json();

                if (response.ok && result.success && result.messages.length > 0) {
                    result.messages.forEach(msg => {
                        if (!msg.sou_eu) {
                            addMessageToUI(
                                msg.attachment_url || msg.conteudo,
                                'received', 
                                msg.data_formatada, 
                                msg.tipo_conteudo, 
                                msg.arquivo_nome
                            );
                        }
                        if (msg.id_mensagem > lastMessageId) {
                            lastMessageId = msg.id_mensagem;
                        }
                    });
                }
            } catch (error) {
            }
        }
        connectWebSocket();

        const updateAdoptionStatus = async (status) => {
            const body = new URLSearchParams({
                solicitacao_id: <?php echo (int) ($conversa_ativa['id_solicitacao_fk'] ?? 0); ?>,
                status
            });
            const response = await fetch('atualizar-status-adocao/', { method: 'POST', body });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Falha ao atualizar status.');
            window.location.reload();
        };

        document.getElementById('save-adoption-status')?.addEventListener('click', async function () {
            this.disabled = true;
            try { await updateAdoptionStatus(document.getElementById('adoption-status').value); }
            catch (error) { this.disabled = false; alert(error.message); }
        });

        document.getElementById('cancel-adoption')?.addEventListener('click', async function () {
            if (!window.confirm('Deseja cancelar esta solicitação de adoção?')) return;
            this.disabled = true;
            try { await updateAdoptionStatus('cancelado'); }
            catch (error) { this.disabled = false; alert(error.message); }
        });

        setInterval(pollMessages, 3000);
    });
</script>
<?php endif; ?>
</body>
</html>
