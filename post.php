<?php
session_start();
require_once 'auth_functions.php';  // Asegúrate de tener funciones para manejar la sesión
require_once 'functions.php';       // Asegúrate de tener las funciones necesarias para manejar posts y notificaciones

// Verifica si el usuario está logueado
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Verifica que se haya pasado el ID del post
if (isset($_GET['id'])) {
    $postId = $_GET['id'];  // ID del post

    // Verificación de si el postId no está vacío
    if (empty($postId)) {
        echo "<p>Error: No se recibió un ID de post válido.</p>";
        exit;
    }

    // Conecta con la base de datos y obtiene el post
    global $pdo;

    // Verifica si el post existe
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si el post no existe
    if (!$post) {
        echo "<p>El post con ID " . htmlspecialchars($postId) . " no existe.</p>";
        exit;
    }

    // Obtener los comentarios del post
    $stmt = $pdo->prepare("SELECT * FROM comments WHERE post_id = ? ORDER BY created_at DESC");
    $stmt->execute([$postId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mostrar el post
    echo "<h1>" . htmlspecialchars($post['message']) . "</h1>";

    // Mostrar los comentarios
    echo "<h2>Comentarios:</h2>";
    if ($comments) {
        foreach ($comments as $comment) {
            echo "<p><strong>" . htmlspecialchars($comment['nickname']) . ":</strong> " . htmlspecialchars($comment['message']) . "</p>";
        }
    } else {
        echo "<p>No hay comentarios en este post.</p>";
    }

    // Formulario para añadir un comentario
    echo '<form action="add_comment.php" method="POST">
            <textarea name="message" placeholder="Añadir un comentario..." required></textarea>
            <input type="hidden" name="post_id" value="' . htmlspecialchars($postId) . '">
            <button type="submit">Comentar</button>
          </form>';

} else {
    echo "<p>Error: No se ha recibido el ID del post.</p>";
}
?>
