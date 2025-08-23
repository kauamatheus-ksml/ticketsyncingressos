<?php 
// nome do arquivo: ver_evento.php
include('conexao.php');
session_start();

if (!isset($_SESSION['adminid'])) {
    echo "Acesso negado!";
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM eventos WHERE id = '$id'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
    } else {
        echo "Evento não encontrado!";
        exit();
    }
} else {
    echo "ID do evento não fornecido!";
    exit();
}
?>
<div class="evento-container">
    <?php if (!empty($row['logo'])): ?>
        <img src="<?php echo $row['logo']; ?>" alt="Logo do Evento" class="evento-logo">
    <?php endif; ?>
    <p><strong>Nome:</strong> <?php echo htmlspecialchars($row['nome']); ?></p>
    <p><strong>Data:</strong> <?php echo date("d/m/Y", strtotime($row['data_inicio'])); ?></p>
    <p><strong>Horário:</strong> <?php echo date("H:i", strtotime($row['hora_inicio'])); ?></p>
    <p><strong>Local:</strong> <?php echo htmlspecialchars($row['local']); ?></p>
    <p><strong>Atrações:</strong> <?php echo nl2br(htmlspecialchars($row['atracoes'])); ?></p>
</div>
<?php
$conn->close();
?>
