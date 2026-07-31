<?php
session_start();
if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== 'ADM') {
    header("Location: materias.php");
    exit;
}
include 'bd.php';

// Buscar todos os usuários e a contagem de interações
try {
    $stmt = $conn->query("
        SELECT u.*, 
               (SELECT COUNT(*) FROM perguntas p WHERE p.usuario_id = u.id) as qtd_perguntas,
               (SELECT COUNT(*) FROM respostas r WHERE r.usuario_id = u.id) as qtd_respostas
        FROM usuarios u 
        ORDER BY u.criado_em DESC
    ");
    $usuarios = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Erro ao buscar usuários: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Painel ADM</title>
<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="css/bootstrap-icons.css">
</head>
<body>
<div class="container mt-5">
<h2 class="mb-4 text-center">Painel Administrativo</h2>

<div class="table-responsive">
<table class="table table-striped table-bordered align-middle">
<thead class="table-dark">
<tr>
<th>ID</th><th>Nome</th><th>Email</th><th>Tipo</th><th>Identificação</th><th>E-mail Enviado</th><th>Perguntas</th><th>Respostas</th><th>Criado em</th><th>Ações</th>
</tr>
</thead>
<tbody>
<?php foreach ($usuarios as $row): ?>
<tr>
<td><?= $row['id'] ?></td>
<td><?= htmlspecialchars($row['nome']) ?></td>
<td><?= htmlspecialchars($row['email']) ?></td>
<td><span class="badge bg-<?= $row['tipo'] === 'professor' || $row['tipo'] === 'ADM' ? 'primary' : 'success' ?>"><?= strtoupper($row['tipo']) ?></span></td>
<td><?= htmlspecialchars($row['identificacao'] ?? '-') ?></td>
<td>
    <?php if ($row['tipo'] === 'professor' || $row['tipo'] === 'ADM'): ?>
        <div class="form-check form-switch d-flex justify-content-center">
            <input class="form-check-input check-email-enviado" 
                   type="checkbox" 
                   data-userid="<?= $row['id'] ?>"
                   style="cursor: pointer; width: 40px; height: 20px;">
        </div>
    <?php else: ?>
        <span class="text-muted">-</span>
    <?php endif; ?>
</td>
<td><span class="badge bg-info text-dark"><?= $row['qtd_perguntas'] ?></span></td>
<td><span class="badge bg-warning text-dark"><?= $row['qtd_respostas'] ?></span></td>
<td><?= date('d/m/Y H:i', strtotime($row['criado_em'])) ?></td>
<td>
<!-- Botão Deletar -->
<a href="usuarios/deletar_usuario.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger btn-deletar-usuario"><i class="bi bi-trash"></i></a>

<!-- Botão Enviar E-mail -->
<?php if($row['tipo'] === 'professor' || $row['tipo'] === 'ADM'): ?>
<button class="btn btn-sm btn-secondary" type="button"
    onclick="abrirGmail(
        '<?= rawurlencode($row['email']) ?>',
        '<?= rawurlencode($row['nome']) ?>',
        '<?= rawurlencode($row['identificacao'] ?? '') ?>'
    )">
    <i class="bi bi-envelope"></i>
</button>
<?php endif; ?>

</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<div class="text-center mt-4">
    <a href="materias.php" class="btn btn-warning btn-lg">Voltar</a>
</div>
</div>
</div>

<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/adm.js"></script>

<!-- JS para sumir com mensagens após 5 segundos -->
<script>
setTimeout(() => {
    const msgSucesso = document.getElementById('mensagem-sucesso');
    if (msgSucesso) msgSucesso.style.display = 'none';

    const msgErro = document.getElementById('mensagem-erro');
    if (msgErro) msgErro.style.display = 'none';
}, 5000);
</script>

<!-- MODAL CONFIRMAR EXCLUSÃO DE USUÁRIO -->
<div class="modal fade" id="modalConfirmarExclusaoUsuario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px; padding: 20px; text-align: center;">
      <div class="modal-header justify-content-center border-0" style="padding-bottom: 0;">
        <h6 class="modal-title fw-bold">Confirmar Exclusão</h6>
      </div>
      <div class="modal-body text-center" style="padding: 10px 0; font-size: 15px;">
        <p>Deseja deletar permanentemente essa conta?</p>
      </div>
      <div class="modal-footer justify-content-center border-0 gap-2" style="padding-top: 0;">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="border-radius: 20px; padding: 6px 16px; font-weight: 600; border: none; background: #6c757d;">
            Cancelar
        </button>
        <button class="btn btn-danger btn-sm" id="confirmarExclusaoUsuario" style="border-radius: 20px; padding: 6px 16px; font-weight: 600; border: none; background: #dc3545;">
            Deletar
        </button>
      </div>
    </div>
  </div>
</div>

</body>
</html>
