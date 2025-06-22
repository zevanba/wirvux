<?php
session_start();
require_once 'auth_functions.php';  // Funciones para manejar la sesión
require_once 'functions.php';       // Funciones para manejar posts y notificaciones

// Verifica si el usuario está logueado
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Verifica que se haya enviado el mensaje y el ID del post
if (isset($_POST['message']) && isset($_POST['post_id'])) {
    $message = $_POST['message'];  // El contenido del comentario
    $postId = $_POST['post_id'];   // ID del post

    // Verificación del mensaje vacío
    if (empty($message)) {
        echo "<p>Error: El comentario no puede estar vacío.</p>";
        exit;
    }

    // Conectar a la base de datos
    global $pdo;

    // Escapar el mensaje para prevenir XSS
    $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    // Verifica si el post existe
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si el post no existe
    if (!$post) {
        echo "<p>El post con ID " . htmlspecialchars($postId) . " no existe.</p>";
        exit;
    }

    // Inserta el comentario en la base de datos
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, nickname, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $postId,
        $_SESSION['user_id'], // ID del usuario que comenta
        $_SESSION['nickname'], // El nickname del usuario
        $escapedMessage       // El mensaje del comentario
    ]);

    // Redirige al post después de agregar el comentario
    header("Location: post.php?id=" . $postId . "&notification_id=" . $_GET['notification_id']);
    exit;
} else {
    echo "<p>Error: No se recibió el mensaje o el ID del post.</p>";
    exit;
}
?>
