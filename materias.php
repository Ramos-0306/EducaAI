<?php
session_start();

if (empty($_SESSION['nome']) || empty($_SESSION['email'])) {
    header("Location: index.php");
    exit;
}

$isADM = isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'ADM';
$isProfessorOuADM = in_array($_SESSION['tipo'] ?? '', ['professor', 'ADM']);

// Liberta o lock do ficheiro de sessão assim que possível: esta página não
// escreve mais nada em $_SESSION a partir daqui, mas o polling em segundo
// plano (buscar_novas_perguntas.php) usa a mesma sessão — mantê-la aberta
// durante as várias queries abaixo faria esse polling (ou outro clique)
// esperar em fila por este pedido.
session_write_close();

// Conexão com banco (centralizada)
include 'bd.php';

// Pegar matéria da URL, se existir
$materiaSelecionada = isset($_GET['materia']) ? $_GET['materia'] : '';

// Buscar perguntas com dados do usuário via JOIN
if ($materiaSelecionada != '') {
    $stmt = $conn->prepare("
        SELECT p.*, u.nome as usuario_nome, u.email as usuario_email, u.tipo as usuario_tipo
        FROM perguntas p
        JOIN usuarios u ON p.usuario_id = u.id
        WHERE p.materia = :materia
        ORDER BY p.criado_em DESC
    ");
    $stmt->execute(['materia' => $materiaSelecionada]);
} else {
    $stmt = $conn->query("
        SELECT p.*, u.nome as usuario_nome, u.email as usuario_email, u.tipo as usuario_tipo
        FROM perguntas p
        JOIN usuarios u ON p.usuario_id = u.id
        ORDER BY p.criado_em DESC
    ");
}
$perguntas = $stmt->fetchAll();

// Buscar respostas e agrupar por pergunta_id.
// Quando há filtro de matéria, busca só as respostas das perguntas exibidas
// (evita trazer a tabela "respostas" inteira do sistema para mostrar 1 matéria).
$idsPerguntas = array_column($perguntas, 'id');
if ($materiaSelecionada != '') {
    if ($idsPerguntas) {
        $placeholders = implode(',', array_fill(0, count($idsPerguntas), '?'));
        $resStmt = $conn->prepare("SELECT * FROM respostas WHERE pergunta_id IN ($placeholders) ORDER BY criado_em ASC");
        $resStmt->execute($idsPerguntas);
        $allRespostas = $resStmt->fetchAll();
    } else {
        $allRespostas = [];
    }
} else {
    $resStmt = $conn->query("SELECT * FROM respostas ORDER BY criado_em ASC");
    $allRespostas = $resStmt->fetchAll();
}

$respostasPorPergunta = [];
foreach ($allRespostas as $r) {
    $respostasPorPergunta[$r['pergunta_id']][] = $r;
}

// --- OTIMIZAÇÃO: Buscar likes/dislikes em lote ---
// Uma única query traz tanto os totais (agregados em PHP) quanto os votos do
// próprio utilizador, evitando um segundo round-trip à base de dados remota
// (cada round-trip custa dezenas de ms de latência de rede).
$likesPerguntas = [];
$dislikesPerguntas = [];
$likesRespostas = [];
$dislikesRespostas = [];

$user_id = $_SESSION['user_id'] ?? 0;
$votosUsuarioPergunta = [];
$votosUsuarioResposta = [];

$likesTotaisStmt = $conn->query("SELECT tipo, item_id, acao, user_id FROM likes_dislikes");
foreach ($likesTotaisStmt->fetchAll() as $row) {
    if ($row['tipo'] === 'pergunta') {
        if ($row['acao'] === 'like') $likesPerguntas[$row['item_id']] = ($likesPerguntas[$row['item_id']] ?? 0) + 1;
        if ($row['acao'] === 'dislike') $dislikesPerguntas[$row['item_id']] = ($dislikesPerguntas[$row['item_id']] ?? 0) + 1;
    } elseif ($row['tipo'] === 'resposta') {
        if ($row['acao'] === 'like') $likesRespostas[$row['item_id']] = ($likesRespostas[$row['item_id']] ?? 0) + 1;
        if ($row['acao'] === 'dislike') $dislikesRespostas[$row['item_id']] = ($dislikesRespostas[$row['item_id']] ?? 0) + 1;
    }

    if ($user_id && (int)$row['user_id'] === (int)$user_id) {
        if ($row['tipo'] === 'pergunta') {
            $votosUsuarioPergunta[$row['item_id']] = $row['acao'];
        } elseif ($row['tipo'] === 'resposta') {
            $votosUsuarioResposta[$row['item_id']] = $row['acao'];
        }
    }
}
// ---------------------------------------------------

// --- CONTAGEM DE NOTIFICAÇÕES NÃO LIDAS ---
$notificacoesNaoLidas = 0;
if ($user_id) {
    $notifStmt = $conn->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = :usuario_id AND lida = false");
    $notifStmt->execute([':usuario_id' => $user_id]);
    $notificacoesNaoLidas = (int)$notifStmt->fetchColumn();
}
// ---------------------------------------------------

// --- ORDENAÇÃO POR LIKES ---
usort($perguntas, function($a, $b) use ($likesPerguntas) {
    $likesA = $likesPerguntas[$a['id']] ?? 0;
    $likesB = $likesPerguntas[$b['id']] ?? 0;
    if ($likesA == $likesB) {
        return strtotime($b['criado_em']) - strtotime($a['criado_em']); // Desempate por data
    }
    return $likesB - $likesA; // Maior número de likes primeiro
});

foreach ($respostasPorPergunta as &$respostas) {
    usort($respostas, function($a, $b) use ($likesRespostas) {
        $likesA = $likesRespostas[$a['id']] ?? 0;
        $likesB = $likesRespostas[$b['id']] ?? 0;
        if ($likesA == $likesB) {
            return strtotime($a['criado_em']) - strtotime($b['criado_em']); // Desempate por data
        }
        return $likesB - $likesA; // Maior número de likes primeiro
    });
}
unset($respostas);
// ---------------------------

// Lista de matérias

$materias = [
''     => '<i class="bi bi-star-fill text-warning"></i> Geral',                       
'AI' => '<i class="bi bi-journal-bookmark-fill" style="color:#1F7A1F;"></i> Área de Integração',
'MAT'  => '<i class="bi bi-calculator-fill" style="color:#fd7e14;"></i> Matemática',          
'PORT' => '<i class="bi bi-journal-text text-danger"></i> Português',               
'ING'  => '<i class="bi bi-chat-dots-fill text-secondary"></i> Inglês',              
'FQ'   => '<i class="bi bi-diagram-3-fill" style="color:#6f42c1;"></i> Física / Química' 
];

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EducaAI</title>
  <link rel="icon" href="img/Logo site.png">

  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/bootstrap-icons.css">
  <link rel="stylesheet" href="css/materias.css">
</head>
<body>

<!-- BOTÃO HAMBÚRGUER (mobile) -->
<button id="sidebarToggle" class="sidebar-toggle-btn" aria-label="Abrir menu">
    <i class="bi bi-list"></i>
</button>

<!-- OVERLAY (mobile) -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
        <!-- LOGO -->
    <div class="sidebar-logo text-center mb-4">
        <a href="index.php">
            <img src="img/Logo site.png" alt="Logo" width="120" class="img-fluid">
        </a>
    </div>
    <?php foreach ($materias as $key => $nome): ?>
        <a href="materias.php<?= $key !== '' ? '?materia='.$key : '' ?>"
           class="<?= $materiaSelecionada === $key ? 'active' : '' ?>">
            <?= $nome ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- CONTEÚDO PRINCIPAL -->
<div class="main">
    <h2 class="mb-4">Perguntas realizadas</h2>

    <!-- BARRA DE PESQUISA NO TOPO -->
    <div class="search-top mb-4">
        <div class="search-wrapper">
            <input type="text"
                   id="searchPerguntas"
                   class="search-input"
                   placeholder="Pesquise perguntas já feitas...">
            <i class="bi bi-search search-icon"></i>
        </div>
    </div>

    <div id="listaPerguntas">
    <?php if (count($perguntas) > 0): ?>
        <?php foreach ($perguntas as $p): ?>
            <div class="question-card mb-3 pergunta-item"
                 id="pergunta-<?= $p['id'] ?>"
                 data-search="<?= htmlspecialchars(strtolower($p['descricao'])) ?>">
                <span class="text-muted">
                    <?= $materias[$p['materia']] ?? htmlspecialchars($p['materia']); ?> | 
                    <?= htmlspecialchars($p['usuario_nome']); ?>

                    <?php if ($p['usuario_tipo'] === 'professor'): ?>
                        <i class="bi bi-patch-check-fill text-primary ms-1"
                           title="Professor verificado"></i>
                    <?php else: ?>
                        <i class="bi bi-person-fill text-secondary ms-1"
                           title="Aluno"></i>
                    <?php endif; ?>
                </span>

                <p class="mt-2"><?= nl2br(htmlspecialchars($p['descricao'])); ?></p>
                <small class="text-muted">
                    Postada em <?= date('d/m/Y H:i', strtotime($p['criado_em'])); ?>
                </small>

                <?php
                // Contagem de likes/dislikes
                $likes = $likesPerguntas[$p['id']] ?? 0;
                $dislikes = $dislikesPerguntas[$p['id']] ?? 0;
                $votoUsuario = $votosUsuarioPergunta[$p['id']] ?? null;

                $listaRespostas = $respostasPorPergunta[$p['id']] ?? [];
                ?>

                <div class="mt-2 action-row">
                    <div class="actions-left">
                        <?php if (!empty($listaRespostas)): ?>
                            <button class="btn btn-sm btn-secondary toggle-respostas"
                                    data-pergunta="<?= $p['id'] ?>">
                                <i class="bi bi-chevron-down"></i>
                                (<?= count($listaRespostas) ?>)
                            </button>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['user_id'])): ?>
                            <button class="icon-btn icon-reply responder-btn"
                                    data-pergunta="<?= $p['id'] ?>"
                                    title="Responder">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        <?php endif; ?>

                        <?php if ($isADM || $_SESSION['user_id'] == $p['usuario_id']): ?>
                            <button class="icon-btn icon-delete excluir-pergunta"
                                    data-id="<?= $p['id'] ?>"
                                    title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="like-dislike-container"
                         data-id="<?= $p['id'] ?>" data-tipo="pergunta">
                        <button class="like-btn <?= $votoUsuario === 'like' ? 'active-like' : '' ?>" <?= $user_id ? '' : 'disabled' ?>>
                            <i class="bi bi-hand-thumbs-up"></i>
                            <span class="like-count"><?= $likes ?></span>
                        </button>

                        <button class="dislike-btn <?= $votoUsuario === 'dislike' ? 'active-dislike' : '' ?>" <?= $user_id ? '' : 'disabled' ?>>
                            <i class="bi bi-hand-thumbs-down"></i>
                            <span class="dislike-count"><?= $dislikes ?></span>
                        </button>
                    </div>
                </div>

                <!-- RESPOSTAS -->
                <div class="respostas-container mt-2" id="respostas-<?= $p['id'] ?>">
                    <?php foreach ($listaRespostas as $r): ?>
                        <div class="p-2 mb-2 border rounded bg-light" data-resposta-id="<?= $r['id'] ?>">
                            <div style="font-weight:700;
                                        color:<?= $r['usuario_tipo'] === 'professor' ? '#2ecc71' : ($r['usuario_tipo']==='IA'?'#ff6600':'blue'); ?>;
                                        margin-bottom:4px;">
                                <?php if ($r['usuario_tipo'] === 'professor'): ?>
                                    Respondido
                                    <i class="bi bi-patch-check-fill text-primary ms-1"
                                       title="Professor verificado"></i>
                                <?php elseif ($r['usuario_tipo'] === 'IA'): ?>
                                    Respondido
                                    <i class="bi bi-robot" title="Resposta da IA"></i>
                                <?php else: ?>
                                    Respondido
                                    <i class="bi bi-person-fill text-secondary ms-1" title="Aluno"></i>
                                <?php endif; ?>
                            </div>

                            <div style="font-weight:600; margin-bottom:2px;">
                                <?= htmlspecialchars($r['usuario_nome']); ?>
                            </div>

                            <div><?= nl2br(htmlspecialchars($r['resposta'])); ?></div>

                            <div class="text-muted" style="font-size:12px; margin-top:4px;">
                                Postada em <?= date('d/m/Y H:i', strtotime($r['criado_em'])); ?>

                                <?php
                                // Likes/dislikes da resposta
                                $likes = $likesRespostas[$r['id']] ?? 0;
                                $dislikes = $dislikesRespostas[$r['id']] ?? 0;
                                $votoUsuario = $votosUsuarioResposta[$r['id']] ?? null;
                                ?>
                                <div class="mt-2 action-row">
                                    <div class="actions-left">
                                        <div class="like-dislike-container mt-1" data-id="<?= $r['id'] ?>" data-tipo="resposta">
                                            <button class="like-btn <?= $votoUsuario === 'like' ? 'active-like' : '' ?>" <?= $user_id ? '' : 'disabled' ?>>
                                                <i class="bi bi-hand-thumbs-up"></i>
                                                <span class="like-count"><?= $likes ?></span>
                                            </button>

                                            <button class="dislike-btn <?= $votoUsuario === 'dislike' ? 'active-dislike' : '' ?>" <?= $user_id ? '' : 'disabled' ?>>
                                                <i class="bi bi-hand-thumbs-down"></i>
                                                <span class="dislike-count"><?= $dislikes ?></span>
                                            </button>
                                        </div>

                                        <?php if ($isADM || (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $r['usuario_id'])): ?>
                                            <button class="icon-btn icon-delete excluir-resposta"
                                                    data-id="<?= $r['id'] ?>"
                                                    data-pergunta="<?= $p['id'] ?>"
                                                    title="Excluir Resposta">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="turmas-vazio"><i class="bi bi-journal-x"></i>Nenhuma pergunta encontrada.</div>
    <?php endif; ?>
    </div>
    <div class="turmas-vazio" id="semResultadosBusca" style="display:none;">
        <i class="bi bi-journal-x"></i>Nenhuma pergunta encontrada para essa busca.
    </div>
</div>

<!-- PERFIL -->
<div class="profile-box" id="profileBox">
    <div class="profile-header d-flex justify-content-between align-items-center">
        <div id="profileInfo">
            <h5 class="mb-0"><?= htmlspecialchars($_SESSION['nome']); ?></h5>
            <small class="text-muted">
                <?= $_SESSION['tipo'] === 'ADM' ? 'Administrador' :
                   ($_SESSION['tipo'] === 'professor' ? 'Professor' : 'Aluno'); ?>
            </small>
        </div>

        <div class="d-flex align-items-center">
            <button class="btn btn-sm btn-light position-relative me-1" id="btnNotificacoes"
                    data-bs-toggle="modal" data-bs-target="#modalNotificacoes" title="Notificações">
                <i class="bi bi-bell"></i>
                <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle"
                      id="badgeNotificacoes" style="<?= $notificacoesNaoLidas > 0 ? '' : 'display:none;' ?>">
                    <?= $notificacoesNaoLidas ?>
                </span>
            </button>

            <button class="btn btn-sm btn-light" id="toggleProfile">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </div>

    <div class="profile-content">
        <a href="usuarios/logout.php" class="btn btn-danger w-100 mb-2">
            <i class="bi bi-box-arrow-right"></i> Sair
        </a>

        <button class="btn btn-primary w-100 mb-2"
                data-bs-toggle="modal"
                data-bs-target="#modalPergunta">
            <i class="bi bi-plus-circle"></i> Fazer pergunta
        </button>

        <button class="btn btn-outline-primary w-100 mb-2"
                id="btnTurmas"
                data-bs-toggle="modal"
                data-bs-target="#modalTurmas">
            <i class="bi bi-people"></i> Turmas
        </button>

        <?php if ($isADM): ?>
            <a href="adm.php" class="btn btn-warning w-100">
                <i class="bi bi-shield-lock"></i> Área do ADM
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL PARA NOVA PERGUNTA -->
<div class="modal fade" id="modalPergunta" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-pergunta-lg">
    <div class="modal-content modal-pergunta">
      <form action="perguntas/adicionar_pergunta.php" method="POST">

        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Tire suas duvidas </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <textarea name="descricao"
                    class="form-control pergunta-textarea"
                    rows="5"
                    placeholder="Escreva sua pergunta, todos queremos ajudar!"
                    required></textarea>

          <!-- MATÉRIA + ENVIAR -->
          <div class="d-flex gap-2 mt-3">
            <div class="dropdown flex-grow-1">
              <button class="btn btn-light dropdown-toggle w-100 materia-btn"
                      type="button"
                      id="dropdownMateria"
                      data-bs-toggle="dropdown">
                Escolha a matéria
              </button>
              <ul class="dropdown-menu w-100">
                <?php foreach ($materias as $key => $nome):
                      if ($key === '') continue;
                ?>
                  <li>
                    <a class="dropdown-item materia-option"
                       data-value="<?= $key ?>">
                      <?= $nome ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>

            <button type="submit" class="btn btn-dark btn-enviar">
              Enviar
            </button>
          </div>

          <input type="hidden" name="materia" id="materiaSelecionada" required>
        </div>

      </form>
    </div>
  </div>
</div>

<!-- MODAL PARA RESPOSTA -->
<div class="modal fade" id="modalResposta" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-pergunta-lg">
    <div class="modal-content modal-pergunta">
      <form action="perguntas/adicionar_resposta.php" method="POST" id="formResposta">

        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Responda a essa duvida</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="pergunta_id" id="modalPerguntaId">

          <!-- EDITOR -->
          <div id="editorResposta"
               class="editor-area"
               contenteditable="true"
               data-placeholder="Escreva sua resposta, pode ser útil"></div>

          <!-- VALOR REAL ENVIADO -->
          <input type="hidden" name="resposta" id="respostaHidden" required>

          <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-dark btn-enviar">
              ENVIAR
            </button>
          </div>
        </div>

      </form>
    </div>
  </div>
</div>

<!-- MODAL CONFIRMAR EXCLUSÃO -->
<div class="modal fade" id="modalConfirmarExclusao" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header justify-content-center border-0">
        <h6 class="modal-title"></h6>
      </div>
      <div class="modal-body text-center">
        <p>Tem certeza que deseja excluir?</p>
        <div class="mb-2 d-none text-start" id="confirmarExclusaoCampoWrapper">
            <label class="form-label small mb-1" id="confirmarExclusaoLabel"></label>
            <input type="text" class="form-control form-control-sm" id="confirmarExclusaoInput" autocomplete="off">
        </div>
      </div>
      <div class="modal-footer justify-content-center border-0 gap-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
            Cancelar
        </button>
        <button class="btn btn-danger btn-sm" id="confirmarExclusao">
            Excluir
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL DE NOTIFICAÇÕES -->
<div class="modal fade" id="modalNotificacoes" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-notificacoes-lg">
    <div class="modal-content modal-pergunta modal-notificacoes">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-bell-fill text-primary"></i> Notificações</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="listaNotificacoes">
        <div class="text-center text-muted py-4">
          <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
          <div>Carregando...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL DE TURMAS -->
<div class="modal fade" id="modalTurmas" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-turmas-lg">
    <div class="modal-content modal-pergunta modal-turmas">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-people-fill text-primary"></i> Turmas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="corpoTurmas">
        <div class="text-center text-muted py-4">
          <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
          <div>Carregando...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
    const USUARIO_TIPO = "<?= htmlspecialchars($_SESSION['tipo'] ?? '', ENT_QUOTES) ?>";
    const USUARIO_ID = <?= (int)$user_id ?>;
    const MATERIA_SELECIONADA = "<?= htmlspecialchars($materiaSelecionada, ENT_QUOTES) ?>";
    const MATERIAS_LABELS = <?= json_encode($materias, JSON_UNESCAPED_SLASHES) ?>;
    let ultimoPerguntaId = <?= (int)(!empty($perguntas) ? max(array_column($perguntas, 'id')) : 0) ?>;
    let ultimaRespostaId = <?= (int)(!empty($allRespostas) ? max(array_column($allRespostas, 'id')) : 0) ?>;
</script>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/materias.js"></script>
<script>
document.querySelectorAll(".materia-option").forEach(item => {
    item.addEventListener("click", function () {
        document.getElementById("dropdownMateria").innerText = this.innerText;
        document.getElementById("materiaSelecionada").value = this.dataset.value;
        
        // Esconder o balão se ele estiver visível
        const btnMateria = document.getElementById("dropdownMateria");
        const tooltip = bootstrap.Tooltip.getInstance(btnMateria);
        if (tooltip) {
            tooltip.hide();
        }
    });
});

// Validação: não enviar sem matéria (agora com balãozinho)
const formPergunta = document.querySelector("#modalPergunta form");
if (formPergunta) {
    const btnMateria = document.getElementById("dropdownMateria");
    
    // Inicializar o Tooltip do Bootstrap no botão
    const tooltipValidacao = new bootstrap.Tooltip(btnMateria, {
        title: "⚠️ Por favor, escolha uma matéria!",
        placement: "top",
        trigger: "manual"
    });

    formPergunta.addEventListener("submit", function(e) {
        if (!document.getElementById("materiaSelecionada").value) {
            e.preventDefault();
            tooltipValidacao.show(); // Mostra o balão

            // Esconde automaticamente depois de 3 segundos
            setTimeout(() => {
                tooltipValidacao.hide();
            }, 3000);
            return;
        }

        // Evita envios duplicados ao clicar várias vezes enquanto a página carrega
        const btnEnviar = formPergunta.querySelector(".btn-enviar");
        if (btnEnviar.disabled) {
            e.preventDefault();
            return;
        }
        btnEnviar.disabled = true;
        btnEnviar.innerText = "Enviando...";
    });
}
</script>

<!-- Script para abrir modal com pergunta da home page -->
<script>
(function() {
    const params = new URLSearchParams(window.location.search);
    const novaPergunta = params.get('novaPergunta');
    if (novaPergunta) {
        // Espera o DOM estar pronto e abre o modal
        const modalEl = document.getElementById('modalPergunta');
        if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            const textarea = modalEl.querySelector('textarea[name="descricao"]');
            if (textarea) {
                textarea.value = novaPergunta;
            }
            modal.show();
            // Limpa o parâmetro da URL sem recarregar
            const cleanUrl = window.location.pathname + (params.get('materia') ? '?materia=' + params.get('materia') : '');
            window.history.replaceState({}, '', cleanUrl);
        }
    }
})();
</script>

</body>
</html>
