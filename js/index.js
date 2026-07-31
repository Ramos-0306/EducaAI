document.addEventListener("DOMContentLoaded", () => {

  // Evita injeção de HTML ao inserir texto dinâmico via innerHTML
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }

  // ===== SISTEMA DE TOAST PROFISSIONAL =====
  function showToast(message, type = 'info', duration = 3500) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icons = {
      success: '✅',
      error: '❌',
      warning: '⚠️',
      info: 'ℹ️'
    };

    const toast = document.createElement('div');
    toast.className = `toast-custom toast-${type}`;
    toast.style.position = 'relative';
    toast.style.overflow = 'hidden';
    toast.innerHTML = `
      <span class="toast-icon">${icons[type] || icons.info}</span>
      <span class="toast-msg">${escapeHtml(message)}</span>
      <button class="toast-close" onclick="this.parentElement.classList.remove('show');this.parentElement.classList.add('hide');setTimeout(()=>this.parentElement.remove(),400);">&times;</button>
      <div class="toast-progress" style="animation-duration:${duration}ms;"></div>
    `;

    container.appendChild(toast);

    // Trigger animation
    requestAnimationFrame(() => {
      toast.classList.add('show');
    });

    // Auto-remove
    setTimeout(() => {
      toast.classList.remove('show');
      toast.classList.add('hide');
      setTimeout(() => toast.remove(), 400);
    }, duration);
  }

  // Expor globalmente
  window.showToast = showToast;

  // ===== CARROSSEL DE PERGUNTAS =====
  const cards = document.querySelectorAll(".pergunta-card");
  if (cards.length > 0) {
    let index = 0;
    cards.forEach(card => card.classList.remove("active"));
    cards[index].classList.add("active");

    function mostrarProximaPergunta() {
      const atual = cards[index];
      const proximoIndex = (index + 1) % cards.length;
      const proximo = cards[proximoIndex];
      atual.classList.remove("active");
      proximo.classList.add("active");
      index = proximoIndex;
    }

    setInterval(mostrarProximaPergunta, 3000);
  }

  // ===== BARRA DE PESQUISA NAVBAR (aparece ao scrollar) =====
  const navbarSearch = document.getElementById("navbarSearch");
  const heroSearch = document.querySelector("#heroSection form");

  if (navbarSearch && heroSearch) {
    window.addEventListener("scroll", () => {
      const heroSearchRect = heroSearch.getBoundingClientRect();
      if (heroSearchRect.bottom < 0) {
        navbarSearch.classList.add("visible");
      } else {
        navbarSearch.classList.remove("visible");
      }
    });
  }

  // ===== FEEDBACK (FOOTER) =====
  const feedbackForm = document.querySelector("footer #feedbackForm");
  if (feedbackForm) {
    feedbackForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      const feedback = this.feedback.value.trim();
      if (!feedback) return;

      try {
        const res = await fetch("feedback/enviar_feedback.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ feedback })
        });
        const data = await res.json();

        if (data.success) {
          this.reset();
        }
        showToast(data.message, data.success ? "success" : "error");
      } catch (err) {
        showToast("Ocorreu um erro ao enviar. Tente novamente.", "error");
      }
    });
  }

  // ===== ANIMAÇÃO DO CHAT (ScrollMagic - entrada scrubada ao scroll) =====
  const chatMessages = document.querySelectorAll(".chat-message");
  if (chatMessages.length > 0 && typeof ScrollMagic !== "undefined") {
    const chatController = new ScrollMagic.Controller();

    chatMessages.forEach((msg) => {
      new ScrollMagic.Scene({
        triggerElement: msg,
        triggerHook: 0.95,
        duration: 500
      })
        .on("progress", (event) => {
          msg.style.opacity = event.progress;
          msg.style.transform = `translateY(${60 * (1 - event.progress)}px)`;
        })
        .addTo(chatController);
    });
  }

  // ===== MODAL DE CADASTRO =====
  const signupModalEl = document.getElementById('signupModal');
  const signupModal = new bootstrap.Modal(signupModalEl);
  const tipoEscolha = document.getElementById('tipoEscolha');
  const signupForm = document.getElementById('signupForm');
  const btnAluno = document.getElementById('btnAluno');
  const btnProfessor = document.getElementById('btnProfessor');
  const btnVoltar = document.getElementById('btnVoltar');

  const professorModalEl = document.getElementById('professorModal');
  const professorModal = new bootstrap.Modal(professorModalEl);
  const professorModalBody = professorModalEl.querySelector('.modal-body p');

  let tipoUsuario = '';

  btnAluno.addEventListener('click', () => {
    tipoUsuario = 'aluno';
    tipoEscolha.classList.add('d-none');
    signupForm.classList.remove('d-none');
  });

  btnProfessor.addEventListener('click', () => {
    tipoUsuario = 'professor';
    tipoEscolha.classList.add('d-none');
    signupForm.classList.remove('d-none');
  });

  btnVoltar.addEventListener('click', () => {
    signupForm.classList.add('d-none');
    tipoEscolha.classList.remove('d-none');
  });

  // Envio do formulário de cadastro
  signupForm.addEventListener('submit', async e => {
    e.preventDefault();

    const nome = document.getElementById('nomeUsuario').value.trim();
    const email = document.getElementById('email').value.trim();
    const senha = document.getElementById('senha').value.trim();
    const confirmar = document.getElementById('confirmarSenha').value.trim();

    if (senha !== confirmar) {
      showToast("As palavras-passe não coincidem!", "error");
      return;
    }

    try {
      const formData = new FormData();
      formData.append('nome', nome);
      formData.append('email', email);
      formData.append('senha', senha);
      formData.append('tipo', tipoUsuario);

      const res = await fetch('usuarios/registro.php', {
        method: 'POST',
        body: formData
      });

      const data = await res.json();

      if (data.success) {
        signupForm.reset();
        signupForm.classList.add('d-none');
        tipoEscolha.classList.remove('d-none');
        signupModal.hide();

        if (tipoUsuario === 'aluno') {
          // Exibir apenas o toast de confirmação
          showToast("Conta criada com sucesso! Faça login como aluno.", "success");

          // Configurar o formulário do modal de login diretamente para Aluno
          tipoLogin = 'aluno';
          loginTipoEscolha.classList.add('d-none');
          loginForm.classList.remove('d-none');
          loginIdentificacaoContainer.style.display = 'none';

          // Abrir o modal de login
          loginModal.show();
        } else if (tipoUsuario === 'professor') {
          professorModalBody.textContent = "📧 Obrigado por se cadastrar! Brevemente seu ID de identificação será enviado por e-mail.";
          professorModal.show();

          professorModalEl.addEventListener("hidden.bs.modal", () => {
            document.body.classList.remove("modal-open");
            document.querySelectorAll(".modal-backdrop").forEach(b => b.remove());
          }, { once: true });
        }

      } else {
        showToast('Erro ao cadastrar: ' + (data.message || 'Tente novamente.'), 'error');
      }

    } catch (err) {
      console.error(err);
      showToast('Erro de conexão com o servidor.', 'error');
    }
  });

  // ===== LOGIN =====
  const loginModalEl = document.getElementById('loginModal');
  const loginModal = new bootstrap.Modal(loginModalEl);
  const loginTipoEscolha = document.getElementById('loginTipoEscolha');
  const loginForm = document.getElementById('loginForm');
  const loginAluno = document.getElementById('loginAluno');
  const loginProfessor = document.getElementById('loginProfessor');
  const loginVoltar = document.getElementById('loginVoltar');
  const loginIdentificacaoContainer = document.getElementById('loginIdentificacaoContainer');

  let tipoLogin = '';

  loginAluno.addEventListener('click', () => {
    tipoLogin = 'aluno';
    loginTipoEscolha.classList.add('d-none');
    loginForm.classList.remove('d-none');
    loginIdentificacaoContainer.style.display = 'none';
  });

  loginProfessor.addEventListener('click', () => {
    tipoLogin = 'professor';
    loginTipoEscolha.classList.add('d-none');
    loginForm.classList.remove('d-none');
    loginIdentificacaoContainer.style.display = 'block';
  });

  loginVoltar.addEventListener('click', () => {
    loginForm.reset();
    loginForm.classList.add('d-none');
    loginTipoEscolha.classList.remove('d-none');
    tipoLogin = '';
  });

  loginForm.addEventListener('submit', async e => {
    e.preventDefault();

    const email = document.getElementById('loginUsuario').value.trim();
    const senha = document.getElementById('loginSenha').value.trim();

    if (tipoLogin === '') {
      showToast('Selecione Aluno ou Professor.', 'warning');
      return;
    }

    if (!email || !senha) {
      showToast('Nome, palavra-passe ou identificação incorretos!', 'warning');
      return;
    }

    try {
      const formData = new FormData();
      formData.append('usuario', email);
      formData.append('senha', senha);
      formData.append('tipo', tipoLogin);

      if (tipoLogin === 'professor') {
        const identificacao = document.getElementById('loginIdentificacao').value.trim();
        formData.append('identificacao', identificacao);
      }

      const res = await fetch('usuarios/login.php', {
        method: 'POST',
        body: formData
      });

      const data = await res.json();

      if (data.success) {
        showToast('Login realizado com sucesso!', 'success');
        loginForm.reset();
        loginForm.classList.add('d-none');
        loginTipoEscolha.classList.remove('d-none');
        setTimeout(() => {
          window.location.href = 'materias.php';
        }, 1200);
      } else {
        showToast('Nome, palavra-passe ou identificação incorretos!', 'warning');
      }

    } catch (err) {
      showToast('Erro no login. Tente novamente.', 'error');
    }
  });

  // ===== ESQUECI MINHA SENHA =====
  const linkEsqueciSenha = document.getElementById('linkEsqueciSenha');
  const modalEsqueciSenhaEl = document.getElementById('modalEsqueciSenha');
  const modalEsqueciSenha = modalEsqueciSenhaEl ? new bootstrap.Modal(modalEsqueciSenhaEl) : null;

  const formEsqueciEmail = document.getElementById('formEsqueciEmail');
  const formEsqueciCodigo = document.getElementById('formEsqueciCodigo');
  const formEsqueciNovaSenha = document.getElementById('formEsqueciNovaSenha');

  let esqueciEmailAtual = '';
  let esqueciCodigoAtual = '';

  function resetFluxoEsqueciSenha() {
    esqueciEmailAtual = '';
    esqueciCodigoAtual = '';
    formEsqueciEmail.reset();
    formEsqueciCodigo.reset();
    formEsqueciNovaSenha.reset();
    formEsqueciEmail.classList.remove('d-none');
    formEsqueciCodigo.classList.add('d-none');
    formEsqueciNovaSenha.classList.add('d-none');
  }

  if (linkEsqueciSenha && modalEsqueciSenha) {
    linkEsqueciSenha.addEventListener('click', (e) => {
      e.preventDefault();
      resetFluxoEsqueciSenha();
      loginModal.hide();
      modalEsqueciSenha.show();
    });
  }

  if (modalEsqueciSenhaEl) {
    modalEsqueciSenhaEl.addEventListener('hidden.bs.modal', resetFluxoEsqueciSenha);
  }

  // Etapa 1: pedir o código
  if (formEsqueciEmail) {
    formEsqueciEmail.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = document.getElementById('esqueciEmail').value.trim();
      if (!email) return;

      try {
        const formData = new FormData();
        formData.append('email', email);

        const res = await fetch('usuarios/esqueci_senha.php', { method: 'POST', body: formData });
        const data = await res.json();

        showToast(data.message, data.success ? 'success' : 'error');

        if (data.success) {
          esqueciEmailAtual = email;
          formEsqueciEmail.classList.add('d-none');
          formEsqueciCodigo.classList.remove('d-none');
        }
      } catch (err) {
        showToast('Erro ao solicitar o código. Tente novamente.', 'error');
      }
    });
  }

  // Etapa 2: verificar o código
  if (formEsqueciCodigo) {
    formEsqueciCodigo.addEventListener('submit', async (e) => {
      e.preventDefault();
      const codigo = document.getElementById('esqueciCodigo').value.trim();
      if (!codigo) return;

      try {
        const formData = new FormData();
        formData.append('email', esqueciEmailAtual);
        formData.append('codigo', codigo);

        const res = await fetch('usuarios/verificar_codigo.php', { method: 'POST', body: formData });
        const data = await res.json();

        showToast(data.message, data.success ? 'success' : 'error');

        if (data.success) {
          esqueciCodigoAtual = codigo;
          formEsqueciCodigo.classList.add('d-none');
          formEsqueciNovaSenha.classList.remove('d-none');
        }
      } catch (err) {
        showToast('Erro ao verificar o código. Tente novamente.', 'error');
      }
    });
  }

  // Etapa 3: definir nova senha
  if (formEsqueciNovaSenha) {
    formEsqueciNovaSenha.addEventListener('submit', async (e) => {
      e.preventDefault();
      const novaSenha = document.getElementById('esqueciNovaSenha').value.trim();
      const confirmarSenha = document.getElementById('esqueciConfirmarSenha').value.trim();

      if (novaSenha !== confirmarSenha) {
        showToast('As senhas não coincidem.', 'warning');
        return;
      }

      try {
        const formData = new FormData();
        formData.append('email', esqueciEmailAtual);
        formData.append('codigo', esqueciCodigoAtual);
        formData.append('nova_senha', novaSenha);

        const res = await fetch('usuarios/redefinir_senha.php', { method: 'POST', body: formData });
        const data = await res.json();

        showToast(data.message, data.success ? 'success' : 'error');

        if (data.success) {
          setTimeout(() => {
            modalEsqueciSenha.hide();
            loginModal.show();
          }, 1200);
        }
      } catch (err) {
        showToast('Erro ao redefinir a senha. Tente novamente.', 'error');
      }
    });
  }

  // ===== PESQUISA → COMPORTAMENTO DIFERENTE SE LOGADO OU NÃO =====
  function handleSearchSubmit(event) {
    event.preventDefault();

    const input = event.target.querySelector('input[type="search"]');
    const pergunta = input ? input.value.trim() : '';

    if (window.isLoggedIn) {
      // Usuário logado: redireciona para materias.php com a pergunta preenchida
      const url = 'materias.php' + (pergunta ? '?novaPergunta=' + encodeURIComponent(pergunta) : '');
      window.location.href = url;
    } else {
      // Usuário NÃO logado: abre modal de registro (comportamento original)
      const modal = new bootstrap.Modal(document.getElementById("signupModal"));
      modal.show();
    }
  }

  if (navbarSearch) {
    navbarSearch.addEventListener("submit", handleSearchSubmit);
  }

  const heroSearchForm = document.getElementById("heroSearch");
  if (heroSearchForm) {
    heroSearchForm.addEventListener("submit", handleSearchSubmit);
  }

});
