<?php
session_start();
require_once 'auth_functions.php';
require_once 'db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Obtener el ID del usuario a mostrar
$userIdToDisplay = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];

// Obtener los datos del usuario
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userIdToDisplay]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no se encuentra el usuario, redirigir
if (!$user) {
    header('Location: index.php');
    exit;
}

$isOwnProfile = ($userIdToDisplay == $_SESSION['user_id']);

// Funciones
function getComments($postId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT c.*, u.nickname FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? ORDER BY c.created_at DESC");
    $stmt->execute([$postId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function deletePost($postId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($post && $post['user_id'] == $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        return $stmt->execute([$postId]);
    }
    return false;
}

function deleteComment($commentId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($comment && ($comment['user_id'] == $_SESSION['user_id'])) {
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        return $stmt->execute([$commentId]);
    }
    return false;
}

function deleteProfilePicture($userId) {
    global $pdo;
    $defaultImagePath = 'uploads/profile_pictures/default/default.jpg';
    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
    return $stmt->execute([$defaultImagePath, $userId]);
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return (!empty($token) && hash_equals($_SESSION['csrf_token'], $token));
}

// Manejo de formularios POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        die('Solicitud no válida.');
    }

    // Actualizar bio y foto de perfil
    if ($isOwnProfile && isset($_POST['bio'])) {
        $bio = trim($_POST['bio']);
        $file = $_FILES['profile_picture'] ?? null;

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($file['type'], $allowedTypes)) {
                $error = 'El archivo debe ser JPG, PNG o GIF.';
            } elseif ($file['size'] > 40 * 1024 * 1024) {
                $error = 'El archivo es demasiado grande.';
            } else {
                $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($fileExtension, $allowedExtensions)) {
                    $error = 'Extensión de archivo no permitida.';
                } else {
                    $uniqueName = uniqid('profile_', true) . '.' . $fileExtension;
                    $uploadPath = 'uploads/profile_pictures/' . $uniqueName;

                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $stmt = $pdo->prepare("UPDATE users SET bio = ?, profile_picture = ? WHERE id = ?");
                        $stmt->execute([$bio, $uploadPath, $userIdToDisplay]);
                        header('Location: profile.php?user_id=' . $userIdToDisplay);
                        exit;
                    } else {
                        $error = 'Error al subir la imagen.';
                    }
                }
            }
        } else {
            $stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
            $stmt->execute([$bio, $userIdToDisplay]);
            header('Location: profile.php?user_id=' . $userIdToDisplay);
            exit;
        }
    }

    // Eliminar foto de perfil
    if ($isOwnProfile && isset($_POST['delete_profile_picture'])) {
        if (deleteProfilePicture($userIdToDisplay)) {
            header('Location: profile.php?user_id=' . $userIdToDisplay);
            exit;
        } else {
            $error = 'Error al eliminar la foto de perfil.';
        }
    }

    // Eliminar publicación
    if (isset($_POST['delete_post'])) {
        deletePost($_POST['post_id']);
    }

    // Eliminar comentario
    if (isset($_POST['delete_comment'])) {
        deleteComment($_POST['comment_id']);
    }

    // Comentar
    if (isset($_POST['comment'])) {
        $comment = trim($_POST['comment']);
        if ($comment) {
            $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['post_id'], $_SESSION['user_id'], $comment]);
        }
    }
}

// Obtener las publicaciones
$stmt = $pdo->prepare("SELECT id, nickname, message, created_at, file_path FROM posts WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userIdToDisplay]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil de <?= htmlspecialchars($user['nickname']) ?></title>
    <link rel="stylesheet" href="style.css">
    <script>
    function showBioForm() {
        document.getElementById("bio-form").style.display = 'block';
    }
    function toggleComments(postId) {
        const commentsElement = document.getElementById('comments-' + postId);
        commentsElement.style.display = (commentsElement.style.display === 'none') ? 'block' : 'none';
    }
    </script>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-button">Volver al inicio</a>
    <h2>Perfil de <?= htmlspecialchars($user['nickname']) ?></h2>

    <div class="profile-header">
        <?php if (!empty($user['profile_picture'])): ?>
            <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Foto de perfil" width="150" height="150">
        <?php else: ?>
            <img src="uploads/profile_pictures/default/default.jpg" alt="Foto de perfil predeterminada" width="150" height="150">
        <?php endif; ?>
    </div>

    <p><strong>Biografía:</strong> <?= htmlspecialchars($user['bio']) ?></p>

    <?php if ($isOwnProfile): ?>
        <form action="profile.php?user_id=<?= $userIdToDisplay ?>" method="POST" onsubmit="return confirm('¿Eliminar foto de perfil?');">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <button type="submit" name="delete_profile_picture">Eliminar foto de perfil</button>
        </form>

        <button onclick="showBioForm()" class="update-bio-btn">Actualizar biografía y foto</button>

        <form id="bio-form" action="profile.php?user_id=<?= $userIdToDisplay ?>" method="POST" enctype="multipart/form-data" style="display: none;">
            <label for="bio">Actualizar biografía:</label>
            <textarea name="bio" id="bio"><?= htmlspecialchars($user['bio']) ?></textarea>

            <label for="profile_picture">Actualizar imagen de perfil:</label>
            <input type="file" name="profile_picture" id="profile_picture" accept="image/*">

            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <button type="submit">Actualizar perfil</button>
        </form>
    <?php endif; ?>

    <h3>Publicaciones</h3>
    <?php if ($posts): ?>
        <?php foreach ($posts as $post): ?>
            <div class="post">
                <p><strong><?= htmlspecialchars($post['nickname']) ?></strong></p>
                <p><?= nl2br(htmlspecialchars($post['message'])) ?></p>
                <p><em>Publicado el <?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></em></p>

                <?php if (!empty($post['file_path'])): ?>
                    <div class="post-image">
                        <img src="<?= htmlspecialchars($post['file_path']) ?>" alt="Imagen publicada" width="300">
                    </div>
                <?php endif; ?>

                <?php if ($isOwnProfile): ?>
                    <form action="profile.php?user_id=<?= $userIdToDisplay ?>" method="POST" onsubmit="return confirm('¿Eliminar publicación?');">
                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <button type="submit" name="delete_post">Eliminar publicación</button>
                    </form>
                <?php endif; ?>

                <h4>Comentarios:</h4>
                <form method="POST" class="comment-form">
                    <textarea name="comment" placeholder="Escribe un comentario..." required maxlength="1000"></textarea>
                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <button type="submit">Comentar</button>
                </form>

                <button class="toggle-comments-btn" onclick="toggleComments(<?= $post['id'] ?>)">Mostrar comentarios</button>

                <div id="comments-<?= $post['id'] ?>" class="comments" style="display: none;">
                    <?php $comments = getComments($post['id']); ?>
                    <?php if ($comments): ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment">
                                <div class="comment-header">
                                    <span class="nickname"><?= htmlspecialchars($comment['nickname']) ?></span>
                                    <span class="date"><?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?></span>
                                </div>
                                <div class="comment-content"><?= nl2br(htmlspecialchars($comment['message'])) ?></div>

                                <?php if ($isOwnProfile || $comment['user_id'] == $_SESSION['user_id']): ?>
                                    <form action="profile.php?user_id=<?= $userIdToDisplay ?>" method="POST" onsubmit="return confirm('¿Eliminar comentario?');">
                                        <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                        <button type="submit" name="delete_comment">Eliminar comentario</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No hay comentarios aún.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No hay publicaciones.</p>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
</div>
</body>
</html>
