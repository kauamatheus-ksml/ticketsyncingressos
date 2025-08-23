<?php
include('conexao.php');
session_start();

if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $data = $_POST['data'];
    $horario = $_POST['horario'];
    $local = $_POST['local'];
    $atracoes = $_POST['atracoes'];
    $logo = $_FILES['logo']['name'];

    // Atualiza a logo se uma nova logo foi enviada
    if ($logo) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0775, true);
        }

        $target_file = $target_dir . basename($_FILES["logo"]["name"]);
        if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
            echo "Logo carregado com sucesso.";
            $sql = "UPDATE eventos SET nome='$nome', data='$data', horario='$horario', local='$local', atracoes='$atracoes', logo='$target_file' WHERE id='$id'";
        } else {
            echo "Erro ao carregar a logo.";
        }
    } else {
        $sql = "UPDATE eventos SET nome='$nome', data='$data', horario='$horario', local='$local', atracoes='$atracoes' WHERE id='$id'";
    }

    if ($conn->query($sql) === TRUE) {
        echo "Evento atualizado com sucesso!";
    } else {
        echo "Erro: " . $sql . "<br>" . $conn->error;
    }

    header("Location: eventos.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM eventos WHERE id='$id'";
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

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Evento</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="icon" href="uploads\ticketsync.ico" type="image/x-icon"/>
</head>
<body>
    <?php include('header_admin.php'); ?>
    <div class="evento-edicao-container">
        <h2 class="evento-edicao-titulo">Editar Evento</h2>

        <form action="editar_evento.php" method="post" enctype="multipart/form-data" class="evento-edicao-form">
            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
            
            <label for="nome" class="evento-edicao-label">Nome do Evento:</label>
            <input type="text" id="nome" name="nome" value="<?php echo $row['nome']; ?>" class="evento-edicao-input" required><br>

            <label for="data" class="evento-edicao-label">Data do Evento:</label>
            <input type="date" id="data" name="data" value="<?php echo $row['data']; ?>" class="evento-edicao-input" required><br>

            <label for="horario" class="evento-edicao-label">Horário de Início:</label>
            <input type="time" id="horario" name="horario" value="<?php echo $row['horario']; ?>" class="evento-edicao-input" required><br>

            <label for="local" class="evento-edicao-label">Local:</label>
            <input type="text" id="local" name="local" value="<?php echo $row['local']; ?>" class="evento-edicao-input" required><br>

            <label for="atracoes" class="evento-edicao-label">Atrações:</label>
            <textarea id="atracoes" name="atracoes" class="evento-edicao-input" required><?php echo $row['atracoes']; ?></textarea><br>

            <label for="logo" class="evento-edicao-label">Logo do Evento:</label>
            <input type="file" id="logo" name="logo" class="evento-edicao-file-input"><br><br>
            
            <input type="submit" value="Atualizar Evento" class="evento-edicao-submit">
        </form>
    </div>
</body>
</html>



<?php
$conn->close();
?>
