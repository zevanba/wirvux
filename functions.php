<?php

// Límite de tamaño de archivo (40 MB)
define('MAX_FILE_SIZE', 40 * 1024 * 1024); // 40 MB

// Función para agregar una notificación
function addNotification($userId, $type, $message, $postId = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, post_id) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$userId, $type, $message, $postId]);
}

// Función para obtener las notificaciones de un usuario
function getUserNotifications($userId) {
    global $pdo;
    
    // Consulta segura para obtener las notificaciones del usuario
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtiene todas las publicaciones
function getPosts() {
    global $pdo;
    // Consulta segura con prepared statement
    $stmt = $pdo->query("SELECT * FROM posts ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para validar el tamaño del archivo
function validateFileSize($file) {
    if ($file['size'] > MAX_FILE_SIZE) {
        return "El archivo excede el límite de tamaño de 40 MB.";
    }
    return null; // No hay error
}

// Agrega una nueva publicación con notificación
function addPost($message, $filePath = null, $originalName = null) {
    global $pdo;

    // Verificación de sesión
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        return false;
    }

    // Verificar el tamaño del archivo si hay uno
    if ($filePath && isset($_FILES['file'])) {
        $fileError = validateFileSize($_FILES['file']);
        if ($fileError) {
            return $fileError;
        }
    }

    // Escapar el mensaje antes de insertarlo para evitar XSS
    $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    // Verificar que la columna nickname existe
    if (!isset($_SESSION['nickname'])) {
        $_SESSION['nickname'] = $_SESSION['username']; // Si no tienes un "nickname", usar el "username"
    }

    // Insertar el post
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, nickname, message, file_path, original_filename) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['nickname'],
        $escapedMessage,
        $filePath,
        $originalName
    ]);

    // Obtener la ID del post recién insertado
    $postId = $pdo->lastInsertId();

    // Obtener todos los usuarios excepto el que publicó el post
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id != ?");
    $stmt->execute([$_SESSION['user_id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Enviar notificaciones a todos los usuarios (excepto al que hizo el post)
    foreach ($users as $user) {
        $message = "Nuevo post de " . $_SESSION['nickname'];
        addNotification($user['id'], 'new_post', $message, $postId); // Incluye el ID del post en la notificación
    }

    return true;
}

// Agrega un comentario a una publicación con notificación
function addComment($postId, $message) {
    global $pdo;

    // Verificación de sesión
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        return false;
    }

    // Escapar el mensaje antes de insertarlo para evitar XSS
    $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    // Verificar que la columna nickname existe
    if (!isset($_SESSION['nickname'])) {
        $_SESSION['nickname'] = $_SESSION['username']; // Si no tienes un "nickname", usar el "username"
    }

    // Insertar el comentario
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, nickname, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $postId,
        $_SESSION['user_id'],
        $_SESSION['nickname'],
        $escapedMessage
    ]);

    // Obtener el propietario del post
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si el comentario es de un usuario distinto al autor del post, enviamos notificación
    if ($post['user_id'] != $_SESSION['user_id']) {
        $message = $_SESSION['nickname'] . " comentó en tu post.";
        addNotification($post['user_id'], 'new_comment', $message, $postId); // Incluye el ID del post en la notificación
    }

    return true;
}

// Obtiene los comentarios de una publicación
function getComments($postId) {
    global $pdo;
    // Consulta preparada para evitar SQL Injection
    $stmt = $pdo->prepare("SELECT * FROM comments WHERE post_id = ? ORDER BY created_at DESC");
    $stmt->execute([$postId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Busca publicaciones por el nickname
function searchPostsByNickname($searchTerm) {
    global $pdo;

    // Sanitizar el término de búsqueda
    $searchTerm = "%" . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . "%";

    // Consulta preparada para evitar SQL Injection
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE nickname LIKE ? ORDER BY created_at DESC");
    $stmt->execute([$searchTerm]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Elimina una publicación
function deletePost($postId) {
    global $pdo;

    // Verificación de sesión
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Verificar que el post pertenece al usuario
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$postId, $_SESSION['user_id']]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($post) {
        // Eliminar los comentarios relacionados con el post de manera segura
        $stmt = $pdo->prepare("DELETE FROM comments WHERE post_id = ?");
        $stmt->execute([$postId]);

        // Eliminar el post
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$postId]);

        // Eliminar el archivo si existe y es un archivo válido
        if (!empty($post['file_path']) && file_exists($post['file_path'])) {
            // Verificar que el archivo exista antes de intentar eliminarlo
            if (unlink($post['file_path'])) {
                return true;
            }
        }

        return true;
    }

    return false;
}

// Elimina un comentario
function deleteComment($commentId) {
    global $pdo;

    // Verificación de sesión
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Verificar que el comentario pertenece al usuario
    $stmt = $pdo->prepare("SELECT * FROM comments WHERE id = ? AND user_id = ?");
    $stmt->execute([$commentId, $_SESSION['user_id']]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($comment) {
        // Eliminar el comentario
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        return true;
    }

    return false;
}

// Elimina un usuario sin borrar sus publicaciones y comentarios
function deleteUser($userId) {
    global $pdo;

    // Desvincula los posts y comentarios de este usuario
    $stmt = $pdo->prepare("UPDATE posts SET user_id = NULL WHERE user_id = ?");
    $stmt->execute([$userId]);

    $stmt = $pdo->prepare("UPDATE comments SET user_id = NULL WHERE user_id = ?");
    $stmt->execute([$userId]);

    // Ahora eliminamos al usuario
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    return $stmt->execute([$userId]);
}

?>
