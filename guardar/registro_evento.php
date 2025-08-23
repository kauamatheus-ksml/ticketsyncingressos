<?php
include('conexao.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome'];
    $data = $_POST['data'];
    $local = $_POST['local'];
    $atracoes = $_POST['atracoes'];
    $logo = $_FILES['logo']['name'];

    // Verifica se o diretório 'uploads' existe, se não, cria o diretório
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0775, true);
    }

    if ($logo) {
        $target_file = $target_dir . basename($_FILES["logo"]["name"]);
        if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
            echo "Logo carregado com sucesso.";
        } else {
            echo "Erro ao carregar a logo.";
            $target_file = NULL;
        }
    } else {
        $target_file = NULL;
    }

    $sql = "INSERT INTO eventos (logo, data, local, nome, atracoes) VALUES ('$target_file', '$data', '$local', '$nome', '$atracoes')";

    if ($conn->query($sql) === TRUE) {
        echo "Evento registrado com sucesso!";
    } else {
        echo "Erro: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Evento</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <header class="header-admin">
        <h1 class="header-admin-title">Nome do Site - Admin</h1>
        <nav class="nav-admin-menu">
            <a href="admin.php" class="nav-admin-link">Administrador</a>
            <a href="perfil.php" class="nav-admin-link">Meu perfil</a>
            <a href="logout.php" class="nav-admin-link">Logout</a>
        </nav>
    </header>
    <h2 class="heading">Registro de Evento</h2>
    <form action="registro_evento.php" method="post" enctype="multipart/form-data" class="form-container">
        <label for="nome" class="form-label">Nome do Evento:</label>
        <input type="text" id="nome" name="nome" class="form-input" required><br>
        
        <label for="data" class="form-label">Data do Evento:</label>
        <input type="date" id="data" name="data" class="form-input" required><br>
        
        <label for="local" class="form-label">Local:</label>
        <input type="text" id="local" name="local" class="form-input" required><br>
        
        <label for="atracoes" class="form-label">Atrações:</label>
        <textarea id="atracoes" name="atracoes" class="form-input" required></textarea><br>
        
        <label for="logo" class="form-label">Logo do Evento:</label>
        <input type="file" id="logo" name="logo" class="form-file-input"><br><br>
        
        <input type="submit" value="Registrar Evento" class="form-submit">
    </form>
</body>
</html>
