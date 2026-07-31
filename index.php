<?php
session_start();
include 'bd.php'; // conexão PDO
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="EducaAI — Plataforma inovadora que combina inteligência artificial com a orientação de professores e alunos para oferecer respostas rápidas e fiáveis às suas perguntas.">
  <title>EducaAI</title>
  <link rel="icon" href="img/Logo site.png">

  <!-- Bootstrap CSS (local) -->
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <!-- Bootstrap Icons (local) -->
  <link rel="stylesheet" href="css/bootstrap-icons.css">
  <!-- Bootstrap JS (local) -->
  <script src="js/bootstrap.bundle.min.js"></script>
  <!-- Meu CSS (local) -->
  <link rel="stylesheet" href="css/index.css">
  <!-- GSAP (local) -->
  <script src="js/gsap.min.js"></script>
  <!-- ScrollMagic (local) -->
  <script src="js/ScrollMagic.min.js"></script>
  <!-- Plugin de integração ScrollMagic + GSAP (local) -->
  <script src="js/ScrollMagic.animation.gsap.min.js"></script>
  <!-- Meu JS (local) -->
  <script src="js/index.js" defer></script>
  <style>

    /* ===== TOAST CUSTOMIZADO ===== */
    .toast-container-custom {
      position: fixed;
      top: 24px;
      right: 24px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .toast-custom {
      min-width: 320px;
      padding: 16px 20px;
      border-radius: 12px;
      color: #fff;
      font-weight: 600;
      font-size: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.25);
      opacity: 0;
      transform: translateX(100%);
      transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
      pointer-events: auto;
    }
    .toast-custom.show {
      opacity: 1;
      transform: translateX(0);
    }
    .toast-custom.hide {
      opacity: 0;
      transform: translateX(100%);
    }
    .toast-success {
      background: linear-gradient(135deg, #1db954, #14a348);
    }
    .toast-error {
      background: linear-gradient(135deg, #e53935, #c62828);
    }
    .toast-warning {
      background: linear-gradient(135deg, #f9a825, #f57f17);
      color: #1a1a1a;
    }
    .toast-info {
      background: linear-gradient(135deg, #2196f3, #1565c0);
    }
    .toast-custom .toast-icon {
      font-size: 22px;
      flex-shrink: 0;
    }
    .toast-custom .toast-msg {
      flex: 1;
    }
    .toast-custom .toast-close {
      background: none;
      border: none;
      color: inherit;
      font-size: 18px;
      cursor: pointer;
      opacity: 0.7;
      transition: opacity 0.2s;
      padding: 0;
      line-height: 1;
    }
    .toast-custom .toast-close:hover {
      opacity: 1;
    }
    /* Barra de progresso no toast */
    .toast-custom .toast-progress {
      position: absolute;
      bottom: 0;
      left: 0;
      height: 3px;
      background: rgba(255,255,255,0.4);
      border-radius: 0 0 12px 12px;
      animation: toastProgress 3.5s linear forwards;
    }
    @keyframes toastProgress {
      from { width: 100%; }
      to { width: 0%; }
    }
  </style>

</head>
<body>

<!-- Navbar (Unificada no Tom Azul Escuro #0F172A) -->
<nav class="navbar navbar-expand-lg fixed-top shadow-sm" style="background-color: #0F172A;">
  <div class="container d-flex align-items-center justify-content-between">

    <!-- Logo -->
    <a class="navbar-brand fw-bold text-white" href="materias.php">
      <img src="img/Logo site.png" alt="Logo" width="90" class="me-2">
    </a>

    <!-- Barra de pesquisa com lupa -->
    <form id="navbarSearch" class="d-flex flex-grow-1 mx-3 position-relative" role="search" style="max-width: 600px;">
      <input class="form-control rounded-pill ps-4 pe-5 text-white"
        style="background-color: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15);"
        type="search"
        placeholder="Qual a sua pergunta?"
        aria-label="Pesquisar">
      <button type="submit"
          class="btn position-absolute end-0 top-50 translate-middle-y me-2 p-0 border-0 bg-transparent text-white">
        <i class="bi bi-search fs-5"></i>
      </button>
    </form>

    <!-- Ações à direita -->
    <div class="d-flex align-items-center text-white">

      <?php if (isset($_SESSION['user_id'])): ?>
        <?php
          // Pega o nome do usuário
          $nome = '';
          try {
              $stmt = $conn->prepare("SELECT nome FROM usuarios WHERE id = ?");
              $stmt->execute([$_SESSION['user_id']]);
              $nome = $stmt->fetchColumn();
          } catch (Exception $e) {
              $nome = 'Usuário';
          }
        ?>

        <span class="me-3">👋 Olá, <strong><?= htmlspecialchars($nome) ?></strong></span>
        <a href="usuarios/logout.php" class="btn btn-outline-light rounded-pill fw-semibold">Sair</a>

      <?php else: ?>
        <a href="#" class="fw-semibold me-3 text-decoration-none text-white" data-bs-toggle="modal" data-bs-target="#loginModal">ENTRAR</a>
        <a href="#" class="btn btn-outline-light rounded-pill fw-semibold" data-bs-toggle="modal" data-bs-target="#signupModal">REGISTO</a>
      <?php endif; ?>

    </div>
  </div>
</nav>

  <!-- Modal de Cadastro -->
  <div class="modal fade" id="signupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark text-white">
        <div class="modal-header border-0">
          <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Registo</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <!-- Escolha tipo -->
          <div id="tipoEscolha">
            <h5 class="text-center mb-3">Escolha o tipo de conta</h5>
            <div class="d-flex gap-2">
              <button id="btnAluno" type="button" class="btn btn-primary flex-fill"><i class="bi bi-mortarboard me-2"></i>Aluno</button>
              <button id="btnProfessor" type="button" class="btn btn-secondary flex-fill"><i class="bi bi-person-workspace me-2"></i>Professor</button>
            </div>
          </div>

          <!-- Formulário -->
          <form id="signupForm" class="d-none mt-3">
            <button type="button" id="btnVoltar" class="btn btn-outline-light mb-3 w-100"><i class="bi bi-arrow-left me-2"></i>VOLTAR</button>

            <input type="text" id="nomeUsuario" class="form-control mb-3 bg-secondary text-white border-0" placeholder="Nome de utilizador" required>
            <input type="email" id="email" class="form-control mb-3 bg-secondary text-white border-0" placeholder="E-mail" required>
            <input type="password" id="senha" class="form-control mb-3 bg-secondary text-white border-0" placeholder="Senha" required>
            <input type="password" id="confirmarSenha" class="form-control mb-3 bg-secondary text-white border-0" placeholder="Confirmar senha" required>
            <button type="submit" class="btn btn-primary w-100">Confirmar</button>
          </form>

        </div>
      </div>
    </div>
  </div>

<!-- Modal personalizado para professor -->
<div class="modal fade" id="professorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background-color: #0F172A; color: white;">
      <div class="modal-header border-0">
        <h5 class="modal-title">Registo Concluído!</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <p>📧 Obrigado por se registar! Brevemente, a sua identificação será enviada por e-mail</p>
      </div>
    </div>
  </div>
</div>

  <!-- Modal de Login -->
  <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark text-white">
        <div class="modal-header border-0">
          <h5 class="modal-title"><i class="bi bi-box-arrow-in-right me-2"></i>Entrar</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <!-- Escolha tipo -->
          <div id="loginTipoEscolha">
            <h5 class="text-center mb-3">Escolha o tipo de conta</h5>
            <div class="d-flex gap-2">
              <button id="loginAluno" type="button" class="btn btn-primary flex-fill"><i class="bi bi-mortarboard me-2"></i>Aluno</button>
              <button id="loginProfessor" type="button" class="btn btn-secondary flex-fill"><i class="bi bi-person-workspace me-2"></i>Professor</button>
            </div>
          </div>

          <!-- Formulário -->
          <form id="loginForm" class="d-none mt-3">
            <button type="button" id="loginVoltar" class="btn btn-outline-light mb-3 w-100"><i class="bi bi-arrow-left me-2"></i>VOLTAR</button>

            <input type="text" id="loginUsuario" class="form-control mb-3 bg-secondary text-white border-0" placeholder="Nome de utilizador" required>
            <input type="password" id="loginSenha" class="form-control mb-3 bg-secondary text-white border-0" placeholder="Senha" required>
            <div id="loginIdentificacaoContainer" class="mb-3" style="display:none;">
              <input type="text" id="loginIdentificacao" class="form-control bg-secondary text-white border-0" placeholder="Identificação do Professor">
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
            <a href="#" id="linkEsqueciSenha" class="d-block text-center mt-2 text-white-50">Esqueci minha senha</a>
          </form>

        </div>
      </div>
    </div>
  </div>

  <!-- Modal de Recuperação de Senha -->
  <div class="modal fade" id="modalEsqueciSenha" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark text-white">
        <div class="modal-header border-0">
          <h5 class="modal-title"><i class="bi bi-key me-2"></i>Recuperar senha</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <!-- Etapa 1: e-mail -->
          <form id="formEsqueciEmail">
            <p class="text-white-50">Informe o e-mail da sua conta. Vamos enviar um código de verificação.</p>
            <input type="email" id="esqueciEmail" class="form-control mb-3 bg-secondary text-white border-0" placeholder="E-mail" required>
            <button type="submit" class="btn btn-primary w-100">Enviar código</button>
          </form>

          <!-- Etapa 2: código -->
          <form id="formEsqueciCodigo" class="d-none">
            <p class="text-white-50">Digite o código de 6 dígitos que enviamos para o seu e-mail.</p>
            <input type="text" id="esqueciCodigo" class="form-control mb-3 bg-secondary text-white border-0" placeholder="Código de 6 dígitos" maxlength="6" required>
            <button type="submit" class="btn btn-primary w-100">Verificar código</button>
          </form>

          <!-- Etapa 3: nova senha -->
          <form id="formEsqueciNovaSenha" class="d-none">
            <p class="text-white-50">Defina sua nova senha.</p>
            <input type="password" id="esqueciNovaSenha" class="form-control mb-3 bg-secondary text-white border-0" placeholder="Nova senha" required>
            <input type="password" id="esqueciConfirmarSenha" class="form-control mb-3 bg-secondary text-white border-0" placeholder="Confirmar nova senha" required>
            <button type="submit" class="btn btn-success w-100">Redefinir senha</button>
          </form>

        </div>
      </div>
    </div>
  </div>

  <!-- Container de Toasts Customizados -->
  <div class="toast-container-custom" id="toastContainer"></div>

  <!-- Flag para JS saber se o usuário está logado -->
  <script>
    window.isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
  </script>


  <!-- SEÇÃO 1: HERO SECTION -->
  <section id="heroSection" class="text-center">
    <div class="container">
      <div class="row justify-content-center">

        <div class="col-lg-10 col-xl-9">
          <!-- Badge Superior -->
          <div class="hero-badge">
            <i class="bi bi-stars"></i>
            <span>Plataforma Inteligente de Aprendizagem</span>
          </div>

          <!-- Título Principal Centralizado -->
          <h1 class="fw-bold">TRANSFORME DÚVIDAS EM <br><span class="hero-gradient-text">APRENDIZAGEM</span></h1>

          <!-- Subtítulo Explicativo -->
          <p class="lead">
            O EducaAI combina inteligência artificial com a orientação de professores e alunos para oferecer respostas rápidas, precisas e fiáveis para os seus estudos.
          </p>

          <!-- Barra de pesquisa principal -->
          <div class="hero-search-wrapper">
            <form id="heroSearch" role="search">
              <input
                type="search"
                class="form-control"
                placeholder="Qual a sua pergunta? Escreva aqui..."
                aria-label="Pesquisar">
              <button
                type="submit"
                class="btn position-absolute end-0 top-50 translate-middle-y me-2 border-0">
                <i class="bi bi-search"></i>
              </button>
            </form>
          </div>

          <!-- Quick Pills -->
          <div class="hero-quick-pills">
            <span class="hero-quick-pill"><i class="bi bi-check-circle-fill text-primary"></i> Respostas Instantâneas</span>
            <span class="hero-quick-pill"><i class="bi bi-shield-check text-primary"></i> Validação por Professores</span>
            <span class="hero-quick-pill"><i class="bi bi-cpu-fill text-primary"></i> Inteligência Artificial Ollama</span>
          </div>

          <!-- Stats Centralizados -->
          <div class="hero-stats">
            <div class="hero-stat">
              <div class="hero-stat-value">IA Ollama</div>
              <div class="hero-stat-label">Assistente 24/7</div>
            </div>
            <div class="hero-stat">
              <div class="hero-stat-value">Comunidade</div>
              <div class="hero-stat-label">Alunos & Professores</div>
            </div>
            <div class="hero-stat">
              <div class="hero-stat-value">100%</div>
              <div class="hero-stat-label">Acesso Gratuito</div>
            </div>
          </div>

        </div>

      </div>
    </div>
  </section>

  <!-- SEÇÃO 2: CHAT OLLAMA (Transição Fluida) -->
  <section id="chatSection">
    <div class="container">
        <div class="chat-header d-flex align-items-center justify-content-center mb-4">
          <img src="img/logoOllama.png" alt="Logo Chat" class="chat-logo img-fluid" style="max-width: 80px; margin-right: 12px;">
          <span class="chat-title fw-bold text-white fs-4">Ollama<span class="chat-ai-badge">IA</span></span>
        </div>
      <div class="chat-wrapper mx-auto">

        <div class="chat-message chat-user">
          <div class="chat-bubble"> Como pode ajudar-me com as minhas dúvidas? </div>
        </div>

        <div class="chat-message chat-ai">
          <div class="chat-bubble">
            No EducaAI, ajudamos a esclarecer todas as suas dúvidas com a Ollama, proporcionando uma aprendizagem mais rápida e eficiente.
          </div>
        </div>

        <div class="chat-message chat-ai">
          <div class="chat-bubble">
           Após a publicação, alunos e professores podem responder à pergunta, garantindo a veracidade da resposta e trazendo mais confiança.
          </div>
        </div>

        <!-- Caixa de pesquisa ilustrativa -->
        <div class="chat-input-placeholder position-relative">
          <span class="placeholder-text">Fácil e Rápido!</span>
          <div class="typing-dots">
            <span></span>
            <span></span>
            <span></span>
          </div>
          <i class="bi bi-search position-absolute end-3 top-50 translate-middle-y"></i>
        </div>

      </div>
    </div>
  </section>

  <!-- SEÇÃO 3: SEÇÃO DE PERGUNTAS / MATÉRIAS -->
  <section class="perguntas-section">
    <div class="container">
      <div class="row align-items-center">
        <!-- Lado Esquerdo: Balões de perguntas -->
        <div class="col-md-6 position-relative">
          <div class="pergunta-card active">
            <div class="pergunta-header">
              <strong>Matemática</strong>
            </div>
            <p>Como se resolvem equações do segundo grau?</p>
            <button class="btn btn-dark fw-bold">RESPONDIDO</button>
          </div>

          <div class="pergunta-card">
            <div class="pergunta-header">
              <strong>Área de Integração</strong>
            </div>
            <p>Quais foram as principais causas da Primeira Guerra Mundial?</p>
            <button class="btn btn-dark fw-bold">RESPONDIDO</button>
          </div>

          <div class="pergunta-card">
            <div class="pergunta-header">
              <strong>Física / Química</strong>
            </div>
            <p>Qual a relação entre energia cinética e potencial?</p>
            <button class="btn btn-dark fw-bold">RESPONDIDO</button>
          </div>
        </div>

        <!-- Lado Direito: Texto explicativo -->
        <div class="col-md-6 text-start mt-4 mt-md-0">
          <div class="section-eyebrow">EXPLORAR DISCIPLINA</div>
          <h2 class="fw-bold mb-3">
            <span class="text-primary">DIVERSAS</span><br>
            <span class="text-primary">MATÉRIAS</span><br>
            <span class="text-dark">AQUI</span>
          </h2>
          <p class="text-secondary fs-5">
            Explore diversas disciplinas e esclareça todas as suas dúvidas de forma simples e rápida. Veja perguntas que já foram respondidas e aproveite o conteúdo!
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- Rodapé -->
  <footer class="bg-light text-center text-lg-start border-top">
    <div class="container p-4">
      <div class="row">
        <div class="col-md-6 mb-4">
          <h5 class="text-uppercase">Mande seu feedback</h5>

          <form id="feedbackForm" class="d-flex">
            <input
              type="text"
              name="feedback"
              class="form-control me-2"
              placeholder="Escreva seu feedback..."
              required>
            <button type="submit" class="btn btn-outline-success">Enviar</button>
          </form>


        </div>
        <div class="col-md-6 mb-4 text-end">
          <h5 class="text-uppercase">Siga-nos</h5>
          <a href="https://www.facebook.com/?locale=pt_PT" target="_blank" rel="noopener noreferrer" class="btn btn-outline-dark btn-sm me-2"><i class="bi bi-facebook"></i></a>
          <a href="https://x.com/?lang=pt" target="_blank" rel="noopener noreferrer" class="btn btn-outline-dark btn-sm me-2"><i class="bi bi-twitter"></i></a>
          <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="btn btn-outline-dark btn-sm"><i class="bi bi-instagram"></i></a>
        </div>
      </div>
    </div>
    <div class="text-center p-3 bg-secondary text-white">
      © 2026 Guilherme. Todos os direitos reservados.
    </div>
  </footer>

  <!-- Botão Voltar ao Topo -->
  <button onclick="topFunction()" id="btnTopo" title="Voltar ao topo"
    class="btn btn-primary btn-lg rounded-circle shadow">
    <i class="bi bi-arrow-up"></i>
  </button>

  <!-- Script botão topo -->
  <script>
    const btn = document.getElementById("btnTopo");
    window.onscroll = function () {
      btn.style.display = (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) ? "flex" : "none";
    };
    function topFunction() {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  </script>

  </body>
  </html>
