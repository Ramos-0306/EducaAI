// Toggle de visualização da senha no modal
function toggleSenha(btn) {
    const span = btn.previousElementSibling; // pega o span com a senha
    const icon = btn.querySelector('i');

    if (span.innerText.includes('•')) {
        span.innerText = span.dataset.senha; // mostra a senha real
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        span.innerText = '••••••••'; // esconde a senha
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

// Copiar mensagem completa para a área de transferência
function copiarMensagem(identificacao) {
    const mensagem = `Olá!\n\nObrigado por se cadastrar no EducaAI.\n\nSeu número de identificação é: ${identificacao}\n\nGuarde este número, pois ele será necessário para acessar sua conta no site.\n\nEstamos felizes em tê-lo(a) conosco e esperamos que aproveite ao máximo a plataforma!\n\nAtenciosamente,\nEquipe EducaAI`;

    navigator.clipboard.writeText(mensagem)
        .then(() => {
            // Toast inline para a página de admin
            let container = document.getElementById('toastContainerAdm');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastContainerAdm';
                container.style.cssText = 'position:fixed;top:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;';
                document.body.appendChild(container);
            }
            const toast = document.createElement('div');
            toast.style.cssText = 'min-width:320px;padding:16px 20px;border-radius:12px;color:#fff;font-weight:600;font-size:15px;display:flex;align-items:center;gap:10px;box-shadow:0 8px 30px rgba(0,0,0,0.25);opacity:0;transform:translateX(100%);transition:all 0.4s cubic-bezier(0.22,1,0.36,1);background:linear-gradient(135deg,#1db954,#14a348);position:relative;overflow:hidden;';
            toast.innerHTML = '<span style="font-size:22px;">✅</span><span style="flex:1;">Mensagem copiada!</span>';
            container.appendChild(toast);
            requestAnimationFrame(() => { toast.style.opacity = '1'; toast.style.transform = 'translateX(0)'; });
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; setTimeout(() => toast.remove(), 400); }, 3000);
        })
        .catch(err => console.error('Erro ao copiar:', err));
}

// Abrir Gmail em nova aba com e-mail já preenchido
function abrirGmail(email, nome, identificacao) {
    // Decodifica caso tenha sido encodeURIComponent no PHP
    email = decodeURIComponent(email);
    nome = decodeURIComponent(nome);
    identificacao = decodeURIComponent(identificacao);

    const assunto = encodeURIComponent(`Bem-vindo(a) ao EducaAI, ${nome}!`);
    const corpo = encodeURIComponent(
        `Olá ${nome}!

Obrigado por se cadastrar no EducaAI.

Seu número de identificação é: ${identificacao}

Guarde este número, pois ele será necessário para acessar sua conta no site.

Estamos felizes em tê-lo(a) conosco e esperamos que aproveite ao máximo a plataforma!

Atenciosamente,
Equipe EducaAI`
    );

    const url = `https://mail.google.com/mail/?view=cm&fs=1&to=${email}&su=${assunto}&body=${corpo}`;
    window.open(url, '_blank'); // abre em nova aba
}

// ===== LÓGICA DO PAINEL ADM =====
document.addEventListener("DOMContentLoaded", () => {
    // 1. Checkboxes de E-mail Enviado (Salvar/Carregar no LocalStorage)
    const checkboxes = document.querySelectorAll(".check-email-enviado");
    checkboxes.forEach(chk => {
        const userId = chk.dataset.userid;
        const salvado = localStorage.getItem(`email_enviado_usuario_${userId}`);
        if (salvado === 'true') {
            chk.checked = true;
        }

        chk.addEventListener("change", function () {
            localStorage.setItem(`email_enviado_usuario_${userId}`, this.checked);
        });
    });

    // 2. Modal de Confirmação de Exclusão de Conta
    let urlExclusaoUsuario = '';
    const modalEl = document.getElementById('modalConfirmarExclusaoUsuario');
    if (modalEl) {
        const modalExclusaoUsuario = new bootstrap.Modal(modalEl);

        document.querySelectorAll('.btn-deletar-usuario').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                urlExclusaoUsuario = this.getAttribute('href');
                modalExclusaoUsuario.show();
            });
        });

        const btnConfirmar = document.getElementById('confirmarExclusaoUsuario');
        if (btnConfirmar) {
            btnConfirmar.addEventListener('click', () => {
                if (urlExclusaoUsuario) {
                    window.location.href = urlExclusaoUsuario;
                }
            });
        }
    }
});
