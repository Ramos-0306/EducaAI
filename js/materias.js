document.addEventListener("DOMContentLoaded", function () {

    // Verificar se há toast para mostrar na URL
    const urlParams = new URLSearchParams(window.location.search);
    const toastType = urlParams.get('toast');
    if (toastType) {
        if (toastType === 'pergunta_adicionada') {
            mostrarToastMaterias('Pergunta publicada com sucesso!', 'success');
        } else if (toastType === 'resposta_adicionada') {
            mostrarToastMaterias('Resposta publicada com sucesso!', 'success');
        } else if (toastType === 'pergunta_excluida') {
            mostrarToastMaterias('Pergunta excluída com sucesso!', 'success');
        } else if (toastType === 'resposta_excluida') {
            mostrarToastMaterias('Resposta excluída com sucesso!', 'success');
        }

        // Limpar o parâmetro da URL sem recarregar
        urlParams.delete('toast');
        const cleanUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        window.history.replaceState({}, '', cleanUrl);
    }

    // Transição suave (fade-out) ao trocar de matéria pela sidebar
    document.querySelectorAll(".sidebar > a").forEach(link => {
        link.addEventListener("click", function (e) {
            const destino = this.href;
            const main = document.querySelector(".main");
            if (!main) return; // sem o container, navega normalmente

            e.preventDefault();
            main.classList.add("fade-out-main");
            setTimeout(() => {
                window.location.href = destino;
            }, 200);
        });
    });

    // Abrir modal de resposta
    document.querySelectorAll(".responder-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            const perguntaId = this.dataset.pergunta;
            const modal = new bootstrap.Modal(document.getElementById('modalResposta'));
            document.getElementById('modalPerguntaId').value = perguntaId;
            modal.show();
        });
    });

    // Mostrar/Esconder respostas (com animação suave)
    document.querySelectorAll(".toggle-respostas").forEach(btn => {
        btn.addEventListener("click", function () {
            abrirRespostas(this.dataset.pergunta, this);
        });
    });

    // Navegar até uma pergunta específica vinda de uma notificação (?pergunta=ID)
    const perguntaFocoId = urlParams.get('pergunta');
    if (perguntaFocoId) {
        const card = document.getElementById('pergunta-' + perguntaFocoId);
        if (card) {
            const toggleBtn = card.querySelector('.toggle-respostas');
            if (toggleBtn) {
                abrirRespostas(perguntaFocoId, toggleBtn);
            }
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            card.classList.add('highlight-pergunta');
            setTimeout(() => card.classList.remove('highlight-pergunta'), 1800);
        }

        urlParams.delete('pergunta');
        const cleanUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        window.history.replaceState({}, '', cleanUrl);
    }

    // ===== NOTIFICAÇÕES =====
    inicializarNotificacoes();

    // ===== TURMAS =====
    inicializarTurmas();
});

// Abre/fecha o container de respostas de uma pergunta com transição suave
function abrirRespostas(perguntaId, btn) {
    const container = document.getElementById('respostas-' + perguntaId);
    if (!container) return;

    const icone = btn ? btn.querySelector('i') : null;
    const aberto = container.classList.contains('show');

    if (!aberto) {
        container.style.maxHeight = container.scrollHeight + 'px';
        container.classList.add('show');
        if (icone) {
            icone.classList.remove('bi-chevron-down');
            icone.classList.add('bi-chevron-up');
        }
    } else {
        container.style.maxHeight = '0px';
        container.classList.remove('show');
        if (icone) {
            icone.classList.remove('bi-chevron-up');
            icone.classList.add('bi-chevron-down');
        }
    }
}

// ===== NOTIFICAÇÕES =====
function inicializarNotificacoes() {
    const btnNotificacoes = document.getElementById('btnNotificacoes');
    const modalEl = document.getElementById('modalNotificacoes');
    if (!btnNotificacoes || !modalEl) return; // usuário não logado

    // Atualiza o badge periodicamente
    atualizarBadgeNotificacoes();
    setInterval(atualizarBadgeNotificacoes, 30000);

    // Ao abrir o modal, carrega a lista de notificações não lidas
    modalEl.addEventListener('shown.bs.modal', function () {
        carregarListaNotificacoes();
    });
}

function atualizarBadgeNotificacoes() {
    fetch('notificacoes/buscar_notificacoes.php')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('badgeNotificacoes');
            if (!badge || !data.notificacoes) return;
            const qtd = data.notificacoes.length;
            badge.textContent = qtd;
            badge.style.display = qtd > 0 ? '' : 'none';
        })
        .catch(() => {});
}

function carregarListaNotificacoes() {
    const lista = document.getElementById('listaNotificacoes');
    if (!lista) return;

    fetch('notificacoes/buscar_notificacoes.php')
        .then(res => res.json())
        .then(data => {
            const notificacoes = data.notificacoes || [];

            if (notificacoes.length === 0) {
                lista.innerHTML = `
                    <div class="notificacoes-vazio">
                        <i class="bi bi-bell-slash"></i>
                        Nenhuma notificação nova.
                    </div>
                `;
                return;
            }

            lista.innerHTML = notificacoes.map(n => {
                const data = new Date(n.criado_em).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
                const inicial = (n.ator_nome || '?').trim().charAt(0).toUpperCase();

                let descricao;
                if (n.tipo === 'convite_turma') {
                    descricao = `te convidou para a turma "${escapeHtml(n.turma_nome || '')}"`;
                } else {
                    const trecho = (n.pergunta_descricao || '').substring(0, 60);
                    descricao = `respondeu: "${escapeHtml(trecho)}${trecho.length === 60 ? '...' : ''}"`;
                }

                return `
                    <div class="d-flex align-items-center justify-content-between mb-2 notificacao-item">
                        <div class="d-flex align-items-center" style="min-width:0;">
                            <div class="notificacao-avatar">${escapeHtml(inicial)}</div>
                            <div class="notificacao-texto">
                                <div class="fw-bold">${escapeHtml(n.ator_nome)}</div>
                                <div class="notificacao-trecho">${descricao}</div>
                                <div class="text-muted" style="font-size:11px;">${data}</div>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-primary ver-notificacao"
                                data-id="${n.id}" data-tipo="${n.tipo}" data-pergunta="${n.pergunta_id || ''}"
                                title="${n.tipo === 'convite_turma' ? 'Ver convite' : 'Ver resposta'}">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                `;
            }).join('');

            // Só marca como lida a notificação em que o usuário clicar no olho
            lista.querySelectorAll('.ver-notificacao').forEach(btn => {
                btn.addEventListener('click', function () {
                    const notifId = this.dataset.id;
                    const tipo = this.dataset.tipo;
                    const perguntaId = this.dataset.pergunta;

                    marcarNotificacaoLida(notifId).then(() => {
                        atualizarBadgeNotificacoes();
                    });

                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalNotificacoes'));
                    if (modal) modal.hide();

                    if (tipo === 'convite_turma') {
                        new bootstrap.Modal(document.getElementById('modalTurmas')).show();
                    } else {
                        window.location.href = 'materias.php?pergunta=' + encodeURIComponent(perguntaId);
                    }
                });
            });
        })
        .catch(() => {
            lista.innerHTML = '<p class="text-muted text-center mb-0">Erro ao carregar notificações.</p>';
        });
}

function marcarNotificacaoLida(id) {
    const body = new URLSearchParams();
    if (id) body.set('id', id);

    return fetch('notificacoes/marcar_notificacao_lida.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
        keepalive: true
    }).catch(() => {});
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

// ===== TURMAS =====
let turmaAtualId = null;
let turmaAtualNome = null;
let cacheAlunoPerguntas = {};

function inicializarTurmas() {
    const modalEl = document.getElementById('modalTurmas');
    if (!modalEl) return;

    modalEl.addEventListener('shown.bs.modal', function () {
        carregarViewListaTurmas();
    });
}

function carregarViewListaTurmas() {
    const corpo = document.getElementById('corpoTurmas');
    if (!corpo) return;

    corpo.innerHTML = `
        <div class="text-center text-muted py-4">
            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
            <div>Carregando...</div>
        </div>
    `;

    fetch('turmas/buscar_turmas.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                corpo.innerHTML = `<p class="text-muted text-center mb-0">${escapeHtml(data.message || 'Erro ao carregar turmas.')}</p>`;
                return;
            }

            if (data.tipo === 'professor') {
                renderViewProfessor(data);
            } else {
                renderViewAluno(data);
            }
        })
        .catch(() => {
            corpo.innerHTML = '<p class="text-muted text-center mb-0">Erro ao carregar turmas.</p>';
        });
}

function renderViewProfessor(data) {
    const corpo = document.getElementById('corpoTurmas');
    const turmas = data.turmas || [];

    const listaHtml = turmas.length === 0
        ? `<div class="turmas-vazio"><i class="bi bi-inboxes"></i>Você ainda não criou nenhuma turma.</div>`
        : turmas.map(t => `
            <div class="turma-card d-flex align-items-center mb-2" data-turma-id="${t.id}">
                <div class="turma-card-avatar">${escapeHtml((t.nome || '?').trim().charAt(0).toUpperCase())}</div>
                <div class="flex-grow-1">
                    <div class="fw-bold">${escapeHtml(t.nome)}</div>
                    <div class="text-muted small">${t.qtd_alunos} aluno${t.qtd_alunos == 1 ? '' : 's'}</div>
                </div>
                <i class="bi bi-chevron-right text-muted"></i>
            </div>
        `).join('');

    corpo.innerHTML = `
        <div class="form-criar-turma">
            <label class="form-label fw-bold mb-2">Criar Nova Turma</label>
            <form id="formCriarTurma" class="d-flex gap-2">
                <input type="text" class="form-control" id="inputNomeTurma" placeholder="Nome da turma" required maxlength="100">
                <button type="submit" class="btn btn-primary btn-enviar">Criar</button>
            </form>
        </div>
        <div class="turmas-secao-titulo">Minhas turmas</div>
        <div id="listaTurmasProfessor">${listaHtml}</div>
    `;

    document.getElementById('formCriarTurma').addEventListener('submit', function (e) {
        e.preventDefault();
        const input = document.getElementById('inputNomeTurma');
        const nome = input.value.trim();
        if (!nome) return;

        fetch('turmas/criar_turma.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'nome=' + encodeURIComponent(nome)
        })
            .then(res => res.json())
            .then(resp => {
                if (resp.success) {
                    mostrarToastMaterias('Turma criada com sucesso!', 'success');
                    abrirDetalheTurma(resp.turma.id);
                } else {
                    mostrarToastMaterias(resp.message || 'Erro ao criar turma.', 'error');
                }
            })
            .catch(() => mostrarToastMaterias('Erro ao criar turma.', 'error'));
    });

    corpo.querySelectorAll('.turma-card').forEach(card => {
        card.addEventListener('click', () => abrirDetalheTurma(card.dataset.turmaId));
    });
}

function renderViewAluno(data) {
    const corpo = document.getElementById('corpoTurmas');
    const convites = data.convites || [];
    const turmas = data.turmas || [];

    const convitesHtml = convites.length === 0
        ? `<p class="text-muted small mb-0">Nenhum convite pendente.</p>`
        : convites.map(c => `
            <div class="convite-card d-flex align-items-center mb-2" data-convite-id="${c.convite_id}">
                <div class="turma-card-avatar">${escapeHtml((c.turma_nome || '?').trim().charAt(0).toUpperCase())}</div>
                <div class="flex-grow-1">
                    <div class="fw-bold">${escapeHtml(c.turma_nome)}</div>
                    <div class="text-muted small">Professor: ${escapeHtml(c.professor_nome)}</div>
                </div>
                <button class="btn btn-sm btn-success rounded-circle me-1 btn-aceitar-convite" title="Aceitar">
                    <i class="bi bi-check-lg"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger rounded-circle btn-recusar-convite" title="Recusar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        `).join('');

    const turmasHtml = turmas.length === 0
        ? `<div class="turmas-vazio"><i class="bi bi-inboxes"></i>Você ainda não está em nenhuma turma.</div>`
        : turmas.map(t => `
            <div class="turma-card d-flex align-items-center mb-2" data-turma-id="${t.id}">
                <div class="turma-card-avatar">${escapeHtml((t.nome || '?').trim().charAt(0).toUpperCase())}</div>
                <div class="flex-grow-1">
                    <div class="fw-bold">${escapeHtml(t.nome)}</div>
                    <div class="text-muted small">Professor: ${escapeHtml(t.professor_nome)}</div>
                </div>
                <i class="bi bi-chevron-right text-muted"></i>
            </div>
        `).join('');

    corpo.innerHTML = `
        <div class="turmas-secao-titulo">Convites de turma</div>
        <div id="listaConvitesTurma">${convitesHtml}</div>
        <div class="turmas-secao-titulo">Minhas turmas</div>
        <div id="listaTurmasAluno">${turmasHtml}</div>
    `;

    corpo.querySelectorAll('.btn-aceitar-convite').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            responderConviteTurma(btn.closest('.convite-card').dataset.conviteId, 'aceitar');
        });
    });

    corpo.querySelectorAll('.btn-recusar-convite').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            responderConviteTurma(btn.closest('.convite-card').dataset.conviteId, 'recusar');
        });
    });

    corpo.querySelectorAll('#listaTurmasAluno .turma-card').forEach(card => {
        card.addEventListener('click', () => abrirDetalheTurma(card.dataset.turmaId));
    });
}

function responderConviteTurma(conviteId, acao) {
    fetch('turmas/responder_convite_turma.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'convite_id=' + encodeURIComponent(conviteId) + '&acao=' + encodeURIComponent(acao)
    })
        .then(res => res.json())
        .then(resp => {
            if (resp.success) {
                mostrarToastMaterias(
                    acao === 'aceitar' ? 'Você entrou na turma!' : 'Convite recusado.',
                    'success'
                );
                carregarViewListaTurmas();
            } else {
                mostrarToastMaterias(resp.message || 'Erro ao responder convite.', 'error');
            }
        })
        .catch(() => mostrarToastMaterias('Erro ao responder convite.', 'error'));
}

function abrirDetalheTurma(turmaId) {
    const corpo = document.getElementById('corpoTurmas');
    corpo.innerHTML = `
        <div class="text-center text-muted py-4">
            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
            <div>Carregando...</div>
        </div>
    `;

    fetch('turmas/turma_detalhes.php?turma_id=' + encodeURIComponent(turmaId))
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                mostrarToastMaterias(data.message || 'Erro ao abrir turma.', 'error');
                carregarViewListaTurmas();
                return;
            }
            turmaAtualId = data.turma.id;
            turmaAtualNome = data.turma.nome;
            cacheAlunoPerguntas = {};
            renderViewDetalheTurma(data);
        })
        .catch(() => {
            mostrarToastMaterias('Erro ao abrir turma.', 'error');
            carregarViewListaTurmas();
        });
}

function renderViewDetalheTurma(data) {
    const corpo = document.getElementById('corpoTurmas');
    const alunos = data.alunos || [];
    const podeGerenciar = !!data.pode_gerenciar;

    const alunosHtml = alunos.length === 0
        ? `<div class="turmas-vazio"><i class="bi bi-person-x"></i>Nenhum aluno matriculado ainda.</div>`
        : alunos.map(a => `
            <div class="aluno-turma-item mb-2" data-aluno-id="${a.id}" data-aluno-email="${escapeHtml(a.email || '')}">
                <div class="d-flex align-items-center">
                    <div class="aluno-turma-avatar">${escapeHtml((a.nome || '?').trim().charAt(0).toUpperCase())}</div>
                    <div class="flex-grow-1">
                        <div class="fw-bold">${escapeHtml(a.nome)}</div>
                        <div class="text-muted small">${a.qtd_perguntas} pergunta${a.qtd_perguntas == 1 ? '' : 's'}</div>
                    </div>
                    ${podeGerenciar ? `
                        <button class="btn-toggle-aluno-perguntas me-1" title="Ver perguntas">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger rounded-circle btn-remover-aluno" title="Remover da turma">
                            <i class="bi bi-trash"></i>
                        </button>
                    ` : ''}
                </div>
                ${podeGerenciar ? `<div class="aluno-perguntas-lista" id="perguntas-aluno-${a.id}"></div>` : ''}
            </div>
        `).join('');

    corpo.innerHTML = `
        <div class="turma-detalhe-header">
            <button class="btn-voltar-turmas" id="btnVoltarTurmas" title="Voltar">
                <i class="bi bi-arrow-left"></i>
            </button>
            <div>
                <div class="fw-bold">${escapeHtml(data.turma.nome)}</div>
                <div class="text-muted small"><i class="bi bi-mortarboard"></i> Professor: ${escapeHtml(data.turma.professor_nome)}</div>
            </div>
        </div>

        ${podeGerenciar ? `
            <div class="busca-aluno-wrapper">
                <label class="form-label fw-bold mb-2">Convidar aluno por e-mail</label>
                <form id="formConvidarAluno" class="d-flex gap-2">
                    <input type="email" class="form-control" id="inputEmailAluno" placeholder="email@exemplo.com" required autocomplete="off">
                    <button type="submit" class="btn btn-primary btn-enviar">Convidar</button>
                </form>
            </div>
        ` : ''}

        <div class="turmas-secao-titulo">Alunos da turma</div>
        <div id="listaAlunosTurma">${alunosHtml}</div>

        ${podeGerenciar ? `
            <button class="btn btn-outline-danger w-100 mt-3" id="btnExcluirTurma">
                <i class="bi bi-trash"></i> Excluir turma
            </button>
        ` : ''}
    `;

    document.getElementById('btnVoltarTurmas').addEventListener('click', carregarViewListaTurmas);

    if (podeGerenciar) {
        document.getElementById('formConvidarAluno').addEventListener('submit', (e) => {
            e.preventDefault();
            const input = document.getElementById('inputEmailAluno');
            const email = input.value.trim();
            if (!email) return;
            convidarAlunoPorEmail(email, input);
        });

        document.getElementById('btnExcluirTurma').addEventListener('click', () => {
            configurarConfirmacaoExclusao({
                valorEsperado: turmaAtualNome,
                label: `Para confirmar, digite o nome da turma "${turmaAtualNome}":`,
                caseInsensitive: false
            });
            acaoExclusao = () => {
                fetch('turmas/excluir_turma.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'turma_id=' + encodeURIComponent(turmaAtualId)
                })
                    .then(res => res.json())
                    .then(resp => {
                        modalConfirmarExclusao.hide();
                        if (resp.success) {
                            mostrarToastMaterias('Turma excluída.', 'success');
                            carregarViewListaTurmas();
                        } else {
                            mostrarToastMaterias(resp.message || 'Erro ao excluir turma.', 'error');
                        }
                    })
                    .catch(() => mostrarToastMaterias('Erro ao excluir turma.', 'error'));
            };
            modalConfirmarExclusao.show();
        });

        corpo.querySelectorAll('.btn-remover-aluno').forEach(btn => {
            btn.addEventListener('click', () => {
                const item = btn.closest('.aluno-turma-item');
                const alunoId = item.dataset.alunoId;
                const alunoEmail = item.dataset.alunoEmail;

                configurarConfirmacaoExclusao({
                    valorEsperado: alunoEmail,
                    label: `Para confirmar, digite o e-mail do aluno "${alunoEmail}":`,
                    caseInsensitive: true
                });
                acaoExclusao = () => {
                    fetch('turmas/remover_aluno_turma.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'turma_id=' + encodeURIComponent(turmaAtualId) + '&aluno_id=' + encodeURIComponent(alunoId)
                    })
                        .then(res => res.json())
                        .then(resp => {
                            modalConfirmarExclusao.hide();
                            if (resp.success) {
                                mostrarToastMaterias('Aluno removido da turma.', 'success');
                                abrirDetalheTurma(turmaAtualId);
                            } else {
                                mostrarToastMaterias(resp.message || 'Erro ao remover aluno.', 'error');
                            }
                        })
                        .catch(() => mostrarToastMaterias('Erro ao remover aluno.', 'error'));
                };
                modalConfirmarExclusao.show();
            });
        });

        corpo.querySelectorAll('.btn-toggle-aluno-perguntas').forEach(btn => {
            btn.addEventListener('click', () => {
                const alunoId = btn.closest('.aluno-turma-item').dataset.alunoId;
                alternarPerguntasAluno(alunoId, btn);
            });
        });
    }
}

function convidarAlunoPorEmail(email, inputEl) {
    fetch('turmas/convidar_aluno_turma.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'turma_id=' + encodeURIComponent(turmaAtualId) + '&email=' + encodeURIComponent(email)
    })
        .then(res => res.json())
        .then(resp => {
            mostrarToastMaterias(
                resp.message || (resp.success ? 'Convite enviado!' : 'Erro ao convidar aluno.'),
                resp.success ? 'success' : 'error'
            );
            if (resp.success && inputEl) inputEl.value = '';
        })
        .catch(() => mostrarToastMaterias('Erro ao convidar aluno.', 'error'));
}

function alternarPerguntasAluno(alunoId, btn) {
    const container = document.getElementById('perguntas-aluno-' + alunoId);
    if (!container) return;

    const aberto = container.classList.contains('show');

    if (aberto) {
        container.style.maxHeight = '0px';
        container.classList.remove('show');
        btn.classList.remove('active');
        return;
    }

    const abrir = () => {
        container.style.maxHeight = container.scrollHeight + 'px';
        container.classList.add('show');
        btn.classList.add('active');
    };

    if (cacheAlunoPerguntas[alunoId]) {
        abrir();
        return;
    }

    container.innerHTML = '<div class="text-muted small py-2">Carregando...</div>';
    container.style.maxHeight = container.scrollHeight + 'px';
    container.classList.add('show');
    btn.classList.add('active');

    fetch('turmas/aluno_perguntas_turma.php?turma_id=' + encodeURIComponent(turmaAtualId) + '&aluno_id=' + encodeURIComponent(alunoId))
        .then(res => res.json())
        .then(data => {
            const perguntas = data.perguntas || [];
            cacheAlunoPerguntas[alunoId] = perguntas;

            container.innerHTML = perguntas.length === 0
                ? '<p class="text-muted small mb-0 py-2">Nenhuma pergunta feita.</p>'
                : perguntas.map(p => `
                    <div class="aluno-pergunta-item">
                        <strong>${escapeHtml(p.materia)}</strong> — ${escapeHtml((p.descricao || '').substring(0, 120))}${(p.descricao || '').length > 120 ? '...' : ''}
                        <div class="text-muted" style="font-size:11px;">${new Date(p.criado_em).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}</div>
                    </div>
                `).join('');

            // Recalcula a altura já com o conteúdo real
            container.style.maxHeight = container.scrollHeight + 'px';
        })
        .catch(() => {
            container.innerHTML = '<p class="text-muted small mb-0 py-2">Erro ao carregar perguntas.</p>';
        });
}

let acaoExclusao = null; // guarda a ação a ser executada
let modalConfirmarExclusao = new bootstrap.Modal(
    document.getElementById('modalConfirmarExclusao')
);

// Configura (ou remove) a exigência de digitar um texto exato antes de habilitar
// o botão de confirmação — usado só por excluir turma / remover aluno da turma.
// Chamar sem argumentos (ou com valorEsperado vazio) volta ao comportamento padrão
// (botão sempre habilitado, sem campo de texto) usado por excluir pergunta/resposta.
function configurarConfirmacaoExclusao({ valorEsperado = null, label = '', caseInsensitive = false } = {}) {
    const wrapper = document.getElementById('confirmarExclusaoCampoWrapper');
    const labelEl = document.getElementById('confirmarExclusaoLabel');
    const input = document.getElementById('confirmarExclusaoInput');
    const btnConfirmar = document.getElementById('confirmarExclusao');

    // Impede colar/soltar texto no campo — precisa ser digitado à mão,
    // senão o "confirme digitando" perde o sentido.
    const bloquearColar = (e) => e.preventDefault();

    if (!valorEsperado) {
        wrapper.classList.add('d-none');
        input.value = '';
        input.oninput = null;
        input.onpaste = null;
        input.ondrop = null;
        btnConfirmar.disabled = false;
        return;
    }

    wrapper.classList.remove('d-none');
    labelEl.textContent = label;
    input.value = '';
    btnConfirmar.disabled = true;
    input.onpaste = bloquearColar;
    input.ondrop = bloquearColar;

    input.oninput = () => {
        const digitado = caseInsensitive ? input.value.trim().toLowerCase() : input.value.trim();
        const esperado = caseInsensitive ? valorEsperado.trim().toLowerCase() : valorEsperado.trim();
        btnConfirmar.disabled = digitado !== esperado;
    };
}

// Sempre volta ao estado padrão quando o modal fecha, pra não vazar uma exigência
// de uma ação anterior para a próxima vez que o modal for aberto.
document.getElementById('modalConfirmarExclusao').addEventListener('hidden.bs.modal', () => {
    configurarConfirmacaoExclusao();
});

// ===== EXCLUIR PERGUNTA =====
document.querySelectorAll('.excluir-pergunta').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.dataset.id;

        acaoExclusao = () => {
            fetch('perguntas/excluir_pergunta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`
            })
                .then(() => {
                    const card = document.getElementById(`pergunta-${id}`);
                    if (card) card.remove();

                    modalConfirmarExclusao.hide();
                    const params = new URLSearchParams(window.location.search);
                    params.set('toast', 'pergunta_excluida');
                    window.location.href = 'materias.php?' + params.toString();
                })
                .catch(() => console.error("❌ Erro ao excluir pergunta"));
        };

        modalConfirmarExclusao.show();
    });
});

// ===== EXCLUIR RESPOSTA =====
document.querySelectorAll('.excluir-resposta').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        const perguntaId = btn.dataset.pergunta;
        const respostaDiv = btn.closest('.p-2');

        acaoExclusao = () => {
            fetch('perguntas/excluir_resposta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`
            })
                .then(() => {
                    if (respostaDiv) respostaDiv.remove();

                    const btnToggle = document.querySelector(
                        `.toggle-respostas[data-pergunta="${perguntaId}"]`
                    );

                    if (btnToggle) {
                        const count = document.querySelectorAll(
                            `#respostas-${perguntaId} > div`
                        ).length;

                        btnToggle.textContent = `Exibir respostas (${count})`;
                    }

                    modalConfirmarExclusao.hide();
                    const params = new URLSearchParams(window.location.search);
                    params.set('toast', 'resposta_excluida');
                    window.location.href = 'materias.php?' + params.toString();
                })
                .catch(() => console.error("❌ Erro ao excluir resposta"));
        };

        modalConfirmarExclusao.show();
    });
});

// ===== BOTÃO CONFIRMAR DO MODAL =====
document.getElementById('confirmarExclusao').addEventListener('click', () => {
    if (acaoExclusao) acaoExclusao();
});

// ===== TOAST PROFISSIONAL =====
function mostrarToastMaterias(message, type = 'success', duration = 3500) {
    let container = document.getElementById('toastContainerMaterias');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainerMaterias';
        container.className = 'toast-container-custom';
        document.body.appendChild(container);
    }

    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };

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

    // Forçar reflow para que o navegador registre a inserção no DOM antes da animação
    toast.offsetHeight;

    // Trigger animation
    toast.classList.add('show');

    // Auto-remove
    setTimeout(() => {
        toast.classList.remove('show');
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 400);
    }, duration);
}

// Manter compatibilidade com chamadas existentes
function mostrarToast() {
    mostrarToastMaterias('Excluído com sucesso!', 'success');
}

// ===== TOGGLE PERFIL (com animação suave de altura) =====
const toggleBtn = document.getElementById('toggleProfile');       // botão do perfil
const profileBox = document.getElementById('profileBox');         // container do perfil
const profileContent = document.querySelector('.profile-content'); // conteúdo que expande/colapsa
const icon = toggleBtn.querySelector('i');                        // ícone da seta dentro do botão

// Define a altura inicial real para permitir a transição já no primeiro clique
profileContent.style.maxHeight = profileContent.scrollHeight + 'px';

toggleBtn.addEventListener('click', () => {
    const estaAberto = !profileBox.classList.contains('minimized');

    if (estaAberto) {
        // Fechar: fixa a altura atual antes de colapsar, senão não há o que animar
        profileContent.style.maxHeight = profileContent.scrollHeight + 'px';
        void profileContent.offsetHeight; // força o navegador a registrar o valor acima
        profileContent.style.maxHeight = '0px';
        profileBox.classList.add('minimized');

        icon.classList.remove('bi-chevron-up');
        icon.classList.add('bi-chevron-down');
    } else {
        // Abrir: calcula a altura real do conteúdo e anima até ela
        profileBox.classList.remove('minimized');
        profileContent.style.maxHeight = profileContent.scrollHeight + 'px';

        icon.classList.remove('bi-chevron-down');
        icon.classList.add('bi-chevron-up');
    }
});


//like

document.querySelectorAll('.like-btn, .dislike-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const container = this.closest('.like-dislike-container');
        const id = container.dataset.id;
        const tipo = container.dataset.tipo;
        const acao = this.classList.contains('like-btn') ? 'like' : 'dislike';

        fetch('perguntas/like_dislike.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(id)}&tipo=${encodeURIComponent(tipo)}&acao=${encodeURIComponent(acao)}`
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const likeBtn = container.querySelector('.like-btn');
                    const dislikeBtn = container.querySelector('.dislike-btn');

                    // Atualiza contadores
                    container.querySelector('.like-count').textContent = data.likes;
                    container.querySelector('.dislike-count').textContent = data.dislikes;

                    // Remove classes ativas
                    likeBtn.classList.remove('active-like');
                    dislikeBtn.classList.remove('active-dislike');

                    // Adiciona a classe correta
                    if (data.acao_atual === 'like') likeBtn.classList.add('active-like');
                    if (data.acao_atual === 'dislike') dislikeBtn.classList.add('active-dislike');
                } else {
                    mostrarToastMaterias('Erro: ' + (data.message || 'Falha ao registrar voto.'), 'error');
                }
            })
            .catch(err => {
                console.error('Erro no fetch like_dislike:', err);
                mostrarToastMaterias('Erro de conexão com o servidor.', 'error');
            });
    });
});

document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("searchPerguntas");

    if (!input) return;

    const semResultados = document.getElementById("semResultadosBusca");

    input.addEventListener("input", function () {
        const termo = this.value.toLowerCase();
        const perguntas = document.querySelectorAll(".pergunta-item");
        let visiveis = 0;

        perguntas.forEach(pergunta => {
            const texto = pergunta.dataset.search;
            const bate = texto.includes(termo);
            pergunta.style.display = bate ? "block" : "none";
            if (bate) visiveis++;
        });

        if (semResultados) {
            semResultados.style.display = (visiveis === 0 && perguntas.length > 0) ? "block" : "none";
        }
    });
});

// modal resposta — copia texto limpo para o hidden antes de enviar
document.getElementById("formResposta").addEventListener("submit", function () {
    const editor = document.getElementById("editorResposta");
    const hidden = document.getElementById("respostaHidden");
    hidden.value = editor.innerText.trim();
});