<?php
require_once __DIR__ . '/app/security.php';
appStartSession();

include_once 'conexao.php';

$logado = isset($_SESSION['user_id']);
$usuario = null;
$user_id = null;
$user_tipo = null;
$primeiro_nome = '';
$pagina = "ajuda"; // Definindo a página atual como "ajuda"
$acesso = "";


if ($logado){
  $acesso = "pets";
} else {
  $acesso = "cadastro";
}

// Carrega dados do usuário se estiver logado (mesma lógica do sobre-nos.php)
if ($logado) {
    $user_id = $_SESSION['user_id'];
    $user_tipo = $_SESSION['user_tipo'] ?? null;

    try {
        if ($user_tipo == 'usuario') {
            $sql = "SELECT nome, email, cpf FROM usuario WHERE id_usuario = :id LIMIT 1";
        } elseif ($user_tipo == 'ong') {
            $sql = "SELECT nome, email, cnpj FROM ong WHERE id_ong = :id LIMIT 1";
        } else {
            $sql = null;
        }

        if (!empty($sql)) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // silencioso — manter UX
    }

    if ($logado && isset($_SESSION['nome'])) {
        $partes = explode(' ', $_SESSION['nome']);
        $primeiro_nome = $partes[0] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajuda - Adote Patas</title>
    <link rel="stylesheet" href="assets/css/pages/ajuda/ajuda.css">
    <link rel="icon" type="image/png" href="images/global/Logo-AdotePatas.png"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    
    <!-- TinyMCE CSS -->
    <script src="https://cdn.tiny.cloud/1/<?php echo htmlspecialchars($apiTinyMCE); ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body>

<header>
  <nav class="navbar navbar-expand">
    <div class="container">
      <a class="navbar-brand" href="./">
        <img src="./images/global/Logo-AdotePatas.png" alt="Logo Adote Patas" class="navbar-logo">
      </a>

      <?php if ($logado): ?>
        <!-- Navbar para usuário LOGADO -->
        <div class="d-flex align-items-center gap-4">
          <!-- Links "Sobre Nós" e "Ajuda" (visíveis em telas grandes) -->
          <div class="d-none d-xl-block">
            <ul class="navbar-nav d-flex flex-row gap-4 mb-0">
              <li class="nav-item">
                <a class="nav-link navlink" href="sobre-nos">Sobre Nós</a>
              </li>
              <li class="nav-item">
                <a class="nav-link navlink active" href="#">Ajuda</a>
              </li>
            </ul>
          </div>

          <!-- Nome e ícone do usuário -->
          <a href="perfil?page=perfil" class="profile-info-link d-flex align-items-center gap-3 text-decoration-none" title="Ver meu perfil">
            <div class="d-flex align-items-center flex-row-reverse gap-2">
              <i class="fa-regular fa-circle-user profile-icon logged-in"></i>
              <span class="profile-name fs-5" style="color: var(--cor-vermelho);"><?php echo htmlspecialchars($primeiro_nome); ?></span>
            </div>
          </a>

          <!-- Botão do menu (SEMPRE VISÍVEL) -->
          <button class="border-0 bg-transparent p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
            <span class="fas fa-bars nav-icon" style="font-size: 2rem;"></span>
          </button>
        </div>

      <?php else: ?>
        <!-- Navbar para usuário NÃO LOGADO -->
        <div class="d-flex align-items-center gap-4">
          <!-- Links visíveis apenas em telas grandes (>1000px) -->
          <div class="d-none d-xl-block">
            <ul class="navbar-nav d-flex flex-row align-items-center gap-4 mb-0">
              <li class="nav-item">
                <a class="nav-link navlink" href="sobre-nos">Sobre Nós</a>
              </li>
              <li class="nav-item">
                <a class="nav-link navlink active" href="#">Ajuda</a>
              </li>
              <li class="nav-item position-relative">
                <a class="nav-link loginlink" href="login">Entrar</a>
              </li>
            </ul>
          </div>

          <!-- Botão do menu (sempre visível) -->
          <button class="border-0 bg-transparent p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
            <span class="fas fa-bars nav-icon" style="font-size: 2rem;"></span>
          </button>
        </div>
      <?php endif; ?>
    </div>
  </nav>
</header>

<!-- Substitua a seção cards-route existente por este código: -->
 
<section class="cards-route py-5">
  <div class="container">
    <div class="row g-4 justify-content-center">
      <!-- Card 1: Adotar -->
      <div class="col-12 col-sm-6 col-lg-3">
        <a href="<?php echo htmlspecialchars($acesso); ?>" class="card-route-link text-decoration-none">
          <div class="card-route h-100">
            <div class="card-route-icon">
              <i class="fas fa-paw"></i>
            </div>
            <div class="card-route-content">
              <h3 class="card-route-title">Adotar</h3>
              <p class="card-route-desc">Encontre seu novo amigo de quatro patas</p>
            </div>
          </div>
        </a>
      </div>
      
      <!-- Card 2: Como Adotar -->
      <div class="col-12 col-sm-6 col-lg-3">
        <a href="como-adotar.php" class="card-route-link text-decoration-none">
          <div class="card-route h-100">
            <div class="card-route-icon">
              <i class="fas fa-question-circle"></i>
            </div>
            <div class="card-route-content">
              <h3 class="card-route-title">Como Adotar</h3>
              <p class="card-route-desc">Conheça o processo de adoção</p>
            </div>
          </div>
        </a>
      </div>
      
      <!-- Card 3: Login -->
      <div class="col-12 col-sm-6 col-lg-3">
        <a href="login" class="card-route-link text-decoration-none">
          <div class="card-route h-100">
            <div class="card-route-icon">
              <i class="fas fa-user"></i>
            </div>
            <div class="card-route-content">
              <h3 class="card-route-title">Login</h3>
              <p class="card-route-desc">Acesse sua conta</p>
            </div>
          </div>
        </a>
      </div>
      
      <!-- Card 4: Política de Privacidade -->
      <div class="col-12 col-sm-6 col-lg-3">
        <a href="politica-de-privacidade.php" class="card-route-link text-decoration-none">
          <div class="card-route h-100">
            <div class="card-route-icon">
              <i class="fas fa-shield-alt"></i>
            </div>
            <div class="card-route-content">
              <h3 class="card-route-title">Política de Privacidade</h3>
              <p class="card-route-desc">Conheça nossos termos</p>
            </div>
          </div>
        </a>
      </div>
    </div>
  </div>
</section>
<section class="perguntas-acordion py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-10 col-xl-8">
        <h2 class="text-center mb-5">Perguntas Frequentes</h2>
        
        <!-- Accordion de Perguntas Frequentes -->
        <div class="accordion" id="accordionFAQ">
          
          <!-- Item 1 -->
          <div class="accordion-item mb-3">
            <h3 class="accordion-header" id="headingOne">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                <span class="faq-question-number me-3">1</span>
                Como posso adotar um pet?
              </button>
            </h3>
            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionFAQ">
              <div class="accordion-body">
                <p>Para adotar um pet, siga estes passos:</p>
                <ol>
                  <li>Faça o seu <a style="text-decorantion: none; color: var(--cor-vermelho)" href="cadastro">cadastro</a> para acessar o pets disponiveis</li>
                  <li>Navegue pela nossa galeria de <a style="text-decorantion: none; color: var(--cor-vermelho)" href="pets">pets disponíveis</a> para adoção</li>
                  <li>Clique no pet que mais gostou para ver mais detalhes</li>
                  <li>Preencha o formulário de interesse na página do pet</li>
                  <li>Aguarde o contato do responsável</li>
                  <li>Após a aprovação, você poderá levar seu novo amiguinho para casa!</li>
                </ol>
                <p class="mb-0">Lembre-se que a adoção é um compromisso sério e para toda a vida do animal.</p>
              </div>
            </div>
          </div>
          
          <!-- Item 2 -->
          <div class="accordion-item mb-3">
            <h3 class="accordion-header" id="headingTwo">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                <span class="faq-question-number me-3">2</span>
                Quais são os requisitos para adotar?
              </button>
            </h3>
            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#accordionFAQ">
              <div class="accordion-body">
                <p>Os requisitos básicos para adoção são:</p>
                <ul>
                  <li>Ser maior de 18 anos</li>
                  <li>Apresentar documento de identidade e CPF</li>
                  <li>Assinar termo de responsabilidade</li>
                  <li>Caso haja possibilidade, permitir a visita prévia ao local onde o animal vai viver</li>
                  <li>Comprometer-se com os cuidados veterinários necessários</li>
                </ul>
                <p class="mb-0">Cada responsável pode ter requisitos adicionais específicos.</p>
              </div>
            </div>
          </div>
          
          <!-- Item 3 -->
          <div class="accordion-item mb-3">
            <h3 class="accordion-header" id="headingThree">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                <span class="faq-question-number me-3">3</span>
                Posso adotar se moro em apartamento?
              </button>
            </h3>
            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#accordionFAQ">
              <div class="accordion-body">
                <p>Sim, é possível adotar morando em apartamento! No entanto, existem algumas considerações importantes:</p>
                <ul>
                  <li>Verifique as regras do condomínio sobre a posse de animais</li>
                  <li>Considere o tamanho e nível de energia do pet em relação ao espaço disponível</li>
                  <li>Comprometa-se com passeios regulares para para o pet</li>
                  <li>Garanta enriquecimento o ambiental</li>
                </ul>
                <p class="mb-0">Nossos recurso podem ajudar a encontrar o pet ideal para seu estilo de vida e espaço.</p>
              </div>
            </div>
          </div>
          
          <!-- Item 4 -->
          <div class="accordion-item mb-3">
            <h3 class="accordion-header" id="headingFour">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                <span class="faq-question-number me-3">4</span>
                Os pets são vacinados e castrados?
              </button>
            </h3>
            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#accordionFAQ">
              <div class="accordion-body">
                <p>Não! Nem todos os pets disponíveis para adoção são vacinados ou castrados</p>
                <ul>
                  <li>Não há obrigatoriedade de:</li>
                  <li>Vacinação básica (V8/V10 para cães e V3/V4 para gatos)</li>
                  <li>Vermifugação</li>
                  <li>Castração</li>
                  <li>Caso o pet possua algum dos itens mencionados acima é necessário a comprovação a partir da documentação </li>

                </ul>
              </div>
            </div>
          </div>
          
          <!-- Item 5 -->
          <div class="accordion-item mb-3">
            <h3 class="accordion-header" id="headingFive">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                <span class="faq-question-number me-3">5</span>
                Posso devolver o pet se não me adaptar?
              </button>
            </h3>
            <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#accordionFAQ">
              <div class="accordion-body">
                <p>Sim, mas com algumas condições importantes:</p>
                <ul>
                  <li>Entre em contato com o responsável pela adoção o mais rápido possível</li>
                  <li>Não abandone o animal em nenhuma circunstância</li>
                  <li>Mantenha o pet em segurança até que o responsável possa recolhê-lo</li>
                  <li>Comunique qualquer problema de saúde ou comportamento</li>
                </ul>
                <p class="mb-0">A devolução deve ser feita de forma responsável, sempre priorizando o bem-estar do animal.</p>
              </div>
            </div>
          </div>
          
          <!-- Item 6 -->
          <div class="accordion-item mb-3">
            <h3 class="accordion-header" id="headingSix">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                <span class="faq-question-number me-3">6</span>
                Como posso ajudar sem adotar?
              </button>
            </h3>
            <div id="collapseSix" class="accordion-collapse collapse" aria-labelledby="headingSix" data-bs-parent="#accordionFAQ">
              <div class="accordion-body">
                <p>Existem várias formas de ajudar os animais sem adotar:</p>
                <ul>
                  <li><strong>Divulgação:</strong> Compartilhe nossos pets nas redes sociais</li>
                </ul>
              </div>
            </div>
          </div>
          
        </div>
        
        <!-- Seção de contato adicional -->
        <div class="mt-5 text-center">
          <h5 class="mb-3">Não encontrou a resposta que procurava?</h5>
          
          <button type="button" id="buttonEntrarContato" onclick="mostrarEmailContato()" class="adopt-btn contact-scroll-btn">
            <div class="heart-background" aria-hidden="true">
              <i class="bi bi-heart-fill"></i>
            </div>
            <span>Entrar em Contato</span>
          </button>
        </div>

        <!-- Formulário de Contato -->
        <div class="contact-form mt-5 p-4 rounded d-none" id="BoxContato" style="background-color: var(--cor-laranja-claro); border: 2px solid var(--cor-rosa-escuro);">
          <h4 class="text-center mb-4" style="color: var(--cor-cinza-texto);">Entre em Contato Conosco</h4>
          
          <form id="formContato">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="inputNome" class="form-label"><strong>Seu Nome:</strong></label>
                  <input type="text" class="form-control" id="inputNome" name="nome" 
                         placeholder="Digite seu nome completo" 
                         value="<?php echo htmlspecialchars($usuario['nome'] ?? ''); ?>"
                         <?php echo $logado ? 'readonly' : ''; ?>>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="inputEmail" class="form-label"><strong>Seu Email:</strong></label>
                  <input type="email" class="form-control" id="inputEmail" name="email" 
                         placeholder="seu@email.com"
                         value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>"
                         <?php echo $logado ? 'readonly' : ''; ?>>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label for="inputAssunto" class="form-label"><strong>Assunto:</strong></label>
              <input type="text" class="form-control" id="inputAssunto" name="assunto" 
                     placeholder="Ex: Problema com login, Dúvida sobre adoção, Sugestão...">
            </div>

            <div class="mb-3">
              <label for="meuEditorDeMensagem" class="form-label"><strong>Mensagem:</strong></label>
              <textarea class="form-control" id="meuEditorDeMensagem" name="mensagem" rows="10"></textarea>
              <!-- O TinyMCE substituirá este textarea -->
            </div>

            <div class="text-center mt-4">
              <button type="submit" class="adopt-btn" style="background-color: var(--cor-vermelho);">
                <div class="heart-background" aria-hidden="true">
                  <i class="bi bi-heart-fill"></i>
                </div>
                <span id="btnText">Enviar Mensagem</span>
              </button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>
</section>

<!-- ESTRUTURA DO MODAL -->
<div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="notificationModalLabel">Aviso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center py-4">
        <div id="modalIcon" class="mb-3"></div>
        <h4 id="modalTitle" class="mb-3"></h4>
        <p id="modalMessage" class="text-muted"></p>
      </div>
      <div class="modal-footer border-0 justify-content-center">
        <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Offcanvas completo e funcional -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
  <div class="offcanvas-header border-bottom">
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  
  <div class="offcanvas-body p-0">
    <?php if ($logado): ?>
      <!-- Conteúdo para usuário LOGADO -->
      <aside class="profile-sidebar p-3">
        <div class="sidebar-header text-center mb-4">
          <i class="fa-regular fa-circle-user sidebar-profile-icon logged-in"></i>
          <h5 class="mt-2 mb-0">
            <?php echo htmlspecialchars($usuario['nome'] ?? 'Usuário'); ?>
          </h5>
          <small class="text-muted fs-6">
            <?php echo htmlspecialchars(ucfirst($user_tipo ?? '')); ?>
          </small>
        </div>
        
        <nav class="nav nav-pills flex-column profile-nav">
          <!-- Links que aparecem apenas no offcanvas em telas pequenas -->
          <div class="d-xl-none">
            <a class="nav-link" href="sobre-nos">
              <i class="fa-solid fa-info-circle fa-fw me-2"></i> Sobre Nós
            </a>
            <a class="nav-link" href="#">
              <i class="fa-solid fa-question-circle fa-fw me-2"></i> Ajuda
            </a>
            <hr class="my-2">
          </div>
          
          <a class="nav-link <?php echo ($pagina == 'perfil') ? 'active' : ''; ?>" 
             href="perfil?page=perfil" 
             <?php echo ($pagina == 'perfil') ? 'aria-current="page"' : ''; ?>>
            <i class="fa-regular fa-circle-user fa-fw me-2"></i> Meu Perfil
          </a>
          
          <a class="nav-link <?php echo ($pagina == 'meus-pets') ? 'active' : ''; ?>" 
             href="perfil?page=meus-pets"
             <?php echo ($pagina == 'meus-pets') ? 'aria-current="page"' : ''; ?>>
            <i class="fa-solid fa-paw fa-fw me-2"></i> Meus Pets
          </a>

          <a class="nav-link <?php echo ($pagina == 'pets-curtidos') ? 'active' : ''; ?>" 
             href="perfil?page=pets-curtidos"
             <?php echo ($pagina == 'pets-curtidos') ? 'aria-current="page"' : ''; ?>>
            <i class="fa-regular fa-heart fa-fw me-2"></i> Pets Curtidos
          </a>

          <a class="nav-link" href="chat.php">
            <i class="fa-regular fa-comments fa-fw me-3"></i> Chats
          </a>

          <hr class="my-2">
          
          <a class="nav-link logout-link-sidebar" href="sair.php">
            <i class="fa-solid fa-right-from-bracket fa-fw me-2"></i> Sair
          </a>
        </nav>
      </aside>
    <?php else: ?>
      <!-- Conteúdo para usuário NÃO LOGADO -->
      <aside class="profile-sidebar p-3">
        <div class="sidebar-header text-center mb-4">
          <i class="fa-regular fa-circle-user sidebar-profile-icon logged-out"></i>
          <h5 class="mt-2 mb-0">Visitante</h5>
          <small class="text-muted fs-6">Faça login para acessar mais recursos</small>
        </div>
        
        <nav class="nav nav-pills flex-column profile-nav">
          <a class="nav-link" href="sobre-nos">
            <i class="fa-solid fa-info-circle fa-fw me-2"></i> Sobre Nós
          </a>
          
          <a class="nav-link" href="#">
            <i class="fa-solid fa-question-circle fa-fw me-2"></i> Ajuda
          </a>
          
          <hr class="my-2">
          
          <a class="nav-link loginlink-sidebar" href="login">
            <i class="fa-solid fa-right-to-bracket fa-fw me-2"></i> Entrar
          </a>
        </nav>
      </aside>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>

<script>
tinymce.init({
  selector: '#meuEditorDeMensagem',
  plugins: 'advlist autolink lists link image charmap preview anchor pagebreak code',
  toolbar: 'undo redo | styles | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image code',
  toolbar_mode: 'floating',
  height: 300,
  menubar: false,
  branding: false,
  promotion: false,
  language: 'pt_BR',
  content_style: `
    body { 
      font-family: 'Poppins', sans-serif; 
      font-size: 14px; 
      color: #333; 
    }
  `,
  setup: function (editor) {
    editor.on('init', function () {
      console.log('Editor TinyMCE inicializado!');
    });
  }
});

document.addEventListener('DOMContentLoaded', function() {
  const formContato = document.getElementById('formContato');
  const contactBtn = document.querySelector('.contact-scroll-btn');
  const offcanvasElement = document.getElementById('offcanvasNavbar');
  const body = document.body;

  const notificationModal = new bootstrap.Modal(document.getElementById('notificationModal'));

  function showModal(title, message, type = 'info') {
      const modalTitle = document.getElementById('modalTitle');
      const modalMessage = document.getElementById('modalMessage');
      const modalIcon = document.getElementById('modalIcon');
      
      modalTitle.innerText = title;
      modalMessage.innerText = message;
      
      let iconHtml = '';
      let colorClass = '';
      
      if (type === 'success') {
          iconHtml = '<i class="bi bi-check-circle-fill" style="font-size: 3rem; color: #198754;"></i>';
          colorClass = 'text-success';
      } else if (type === 'error') {
          iconHtml = '<i class="bi bi-x-circle-fill" style="font-size: 3rem; color: #dc3545;"></i>';
          colorClass = 'text-danger';
      } else {
          iconHtml = '<i class="bi bi-info-circle-fill" style="font-size: 3rem; color: #0d6efd;"></i>';
          colorClass = 'text-primary';
      }
      
      modalIcon.innerHTML = iconHtml;
      modalTitle.className = `mb-3 ${colorClass}`;
      
      notificationModal.show();
  }

  if (formContato) {
    formContato.addEventListener('submit', function(e) {
      e.preventDefault();

      const mensagem = tinymce.get('meuEditorDeMensagem').getContent();
      const assunto = document.getElementById('inputAssunto').value;
      const nome = document.getElementById('inputNome').value;
      const email = document.getElementById('inputEmail').value;
      const btnEnviar = document.getElementById('btnEnviarMensagem');
      const btnText = document.getElementById('btnText');

      if (!assunto.trim()) {
        showModal('Atenção', 'Por favor, preencha o campo de assunto.', 'error');
        return;
      }
      if (!mensagem.trim() || mensagem === '<p></p>') {
        showModal('Atenção', 'Por favor, escreva sua mensagem no campo de texto.', 'error');
        return;
      }

      // --- CORREÇÃO DE SEGURANÇA NO JS ---
      // Se por algum motivo o 'btnText' não existir, usamos um texto padrão.
      let textoOriginal = "Enviar Mensagem";
      
      if (btnText) {
          textoOriginal = btnText.innerText;
          btnText.innerText = "Enviando...";
      } else {
          console.warn("Elemento btnText não encontrado, pulando atualização de texto.");
      }
      
      if (btnEnviar) btnEnviar.disabled = true;
      // -----------------------------------

      fetch('enviar-email-ajuda.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          nome: nome,
          email: email,
          assunto: assunto,
          mensagem: mensagem
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showModal('Sucesso!', data.message, 'success');
          formContato.reset();
          tinymce.get('meuEditorDeMensagem').setContent('');
        } else {
          showModal('Ops!', data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Erro:', error);
        showModal('Erro de Conexão', 'Não foi possível se comunicar com o servidor. Tente novamente mais tarde.', 'error');
      })
      .finally(() => {
        // Restaura o botão com segurança
        if (btnText) btnText.innerText = textoOriginal;
        if (btnEnviar) btnEnviar.disabled = false;
      });
    });
  }
  if (contactBtn) {
    contactBtn.addEventListener('click', function() {
      document.querySelector('.contact-form').scrollIntoView({
        behavior: 'smooth'
      });
    });
  }

  if (offcanvasElement) {
    const offcanvas = new bootstrap.Offcanvas(offcanvasElement, {
      backdrop: true,
      scroll: false
    });

    offcanvasElement.addEventListener('show.bs.offcanvas', function() {
      body.classList.add('offcanvas-open');
      document.documentElement.style.overflow = 'hidden';
      body.style.overflow = 'hidden';
    });

    offcanvasElement.addEventListener('hidden.bs.offcanvas', function() {
      body.classList.remove('offcanvas-open');
      document.documentElement.style.overflow = '';
      body.style.overflow = '';
    });

    window.addEventListener('resize', function() {
      if (body.classList.contains('offcanvas-open')) {
        offcanvas.hide();
      }
    });
  }
});

function mostrarEmailContato() {
    const btnMostrarEmail = document.querySelector("#buttonEntrarContato");
    const BoxEmail = document.querySelector("#BoxContato");
    const assunto = document.getElementById('inputAssunto');
    const nome = document.getElementById('inputNome');
    const email = document.getElementById('inputEmail');

    if(btnMostrarEmail){
      btnMostrarEmail.classList.add("d-none");
      BoxEmail.classList.remove("d-none");
    }

    if(nome.readOnly && email.readOnly){
      assunto.focus();
    } else {
      nome.focus();
    }

}
</script>


</body>
</html>
