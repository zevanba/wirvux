<?php
session_start();
require_once 'auth_functions.php';
require_once 'functions.php';

// Asegúrate de que la sesión esté iniciada y el usuario esté autenticado
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Obtener notificaciones no leídas
$notifications = getUserNotifications($_SESSION['user_id']);
$unreadNotifications = array_filter($notifications, function($notification) {
    return !$notification['is_read'];  // Filtra las no leídas
});

// Generar y verificar un token CSRF
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Procesar publicación de comentarios o posts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    if (isset($_POST['post_id'])) {
        // Verificación CSRF para comentarios
        if (!verifyCsrfToken($_POST['csrf_token'])) {
            die("Solicitud no válida.");
        }

        $postId = $_POST['post_id'];
        $message = $_POST['message'];
        addComment($postId, $message);
    } else {
        // Verificación CSRF para publicación de nuevos posts
        if (!verifyCsrfToken($_POST['csrf_token'])) {
            die("Solicitud no válida.");
        }

        $message = $_POST['message'];
        $archivoRuta = null;
        $nombreOriginal = null;

        // Validación y manejo de archivos subidos
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $nombreTmp = $_FILES['archivo']['tmp_name'];
            $nombreOriginal = basename($_FILES['archivo']['name']);
            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

            // Solo permitir tipos de archivos seguros
            $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'pdf', 'docx', 'txt'];
            if (in_array($extension, $permitidas)) {
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                $nombreFinal = uniqid() . '.' . $extension;
                $rutaDestino = 'uploads/posts/' . $nombreFinal;

                // Asegurarse de que no se está subiendo un archivo PHP u otro tipo peligroso
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $tipoArchivo = mime_content_type($nombreTmp);
                    if (strpos($tipoArchivo, 'image/') !== 0) {
                        die("Tipo de archivo no permitido.");
                    }
                }

                // Mover el archivo de manera segura
                if (move_uploaded_file($nombreTmp, $rutaDestino)) {
                    $archivoRuta = $rutaDestino;
                }
            }
        }

        addPost($message, $archivoRuta, $nombreOriginal);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Protección contra eliminación no autorizada de post o comentario
if (isset($_POST['delete_post_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        die("Solicitud no válida.");
    }
    $postId = $_POST['delete_post_id'];
    deletePost($postId);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['delete_comment_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        die("Solicitud no válida.");
    }
    $commentId = $_POST['delete_comment_id'];
    deleteComment($commentId);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$posts = isset($_GET['search']) 
    ? searchPostsByNickname($_GET['search']) 
    : getPosts();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wirvux</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Wirvux - Bienvenido <?= htmlspecialchars($_SESSION['username']) ?></h1>

        <!-- Campana de notificaciones -->
        <div class="notification-bell-container">
            <a href="notifications.php" class="notification-bell">
                <img src="notificaciones.jpg" alt="Notificaciones" width="30" height="30">
                <?php if (count($unreadNotifications) > 0): ?>
                    <span class="notification-badge"><?= count($unreadNotifications) ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Agregar botón para ir al perfil del usuario -->
        <div class="profile-btn-container">
            <a href="profile.php?user_id=<?= $_SESSION['user_id'] ?>" class="button">Ir a mi perfil</a>
        </div>

        <div class="logout-container">
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>

        <form method="GET" action="" class="search-form">
            <input type="text" name="search" placeholder="Buscar por apodo..." 
                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <button type="submit">Buscar</button>
            <?php if(isset($_GET['search'])): ?>
                <a href="index.php" class="clear-search">Limpiar</a>
            <?php endif; ?>
        </form>

        <form method="POST" class="post-form" enctype="multipart/form-data">
            <textarea name="message" placeholder="Escribe tu mensaje..." required maxlength="1000"></textarea>
            <input type="file" name="archivo" accept=".jpg,.jpeg,.png,.gif,.mp4,.pdf,.docx,.txt">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <button type="submit">Publicar</button>
        </form>

        <div class="posts">
            <?php if (empty($posts)): ?>
                <p class="no-results">
                    <?= isset($_GET['search']) 
                        ? "No se encontraron posts para '".htmlspecialchars($_GET['search'])."'"
                        : "No hay posts aún. ¡Sé el primero!" ?>
                </p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?php 
                    // Obtener el usuario que hizo la publicación
                    $user = getUserById($post['user_id']);
                    ?>
                    <div class="post">
                        <div class="post-header">
                            <div class="user-info">
                                <!-- Mostrar la foto de perfil -->
                                <img src="<?= !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'uploads/profile_pictures/default.jpg' ?>" alt="Foto de perfil" width="50" height="50">
                                <span class="nickname">
                                    <!-- Enlace al perfil del usuario -->
                                    <a href="profile.php?user_id=<?= $user['id'] ?>"><?= htmlspecialchars($user['nickname']) ?></a>
                                </span>
                            </div>
                            <span class="date"><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></span>
                        </div>

                        <div class="post-content"><?= nl2br(htmlspecialchars($post['message'])) ?></div>

                        <?php if (!empty($post['file_path'])): ?>
                            <div class="post-file">
                                <?php
                                    $ext = strtolower(pathinfo($post['file_path'], PATHINFO_EXTENSION));
                                    $archivoURL = htmlspecialchars($post['file_path']);
                                    $nombreOriginal = htmlspecialchars($post['original_filename'] ?? basename($archivoURL));

                                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                        echo "<img src='{$archivoURL}' alt='Imagen'>";
                                    } elseif ($ext === 'mp4') {
                                        echo "<video controls><source src='{$archivoURL}' type='video/mp4'></video>";
                                    } else {
                                        echo "<a href='{$archivoURL}' download='{$nombreOriginal}'>Descargar archivo: {$nombreOriginal}</a>";
                                    }
                                ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="comment-form">
                            <textarea name="message" placeholder="Escribe un comentario..." required maxlength="1000"></textarea>
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <button type="submit">Comentar</button>
                        </form>

                        <button class="toggle-comments-btn" onclick="toggleComments(<?= $post['id'] ?>)">
                            Mostrar comentarios
                        </button>

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
                                        <?php if ($comment['user_id'] == $_SESSION['user_id']): ?>
                                            <form method="POST" class="delete-comment-form" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este comentario?');">
                                                <input type="hidden" name="delete_comment_id" value="<?= $comment['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                <button type="submit" class="delete-comment-btn">Eliminar comentario</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No hay comentarios.</p>
                            <?php endif; ?>
                        </div>

                        <?php if ($post['user_id'] == $_SESSION['user_id']): ?>
                            <form method="POST" class="delete-post-form" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este post?');">
                                <input type="hidden" name="delete_post_id" value="<?= $post['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <button type="submit" class="delete-btn">Eliminar post</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleComments(postId) {
            const commentsContainer = document.getElementById('comments-' + postId);
            const button = commentsContainer.previousElementSibling;
            if (commentsContainer.style.display === 'none') {
                commentsContainer.style.display = 'block';
                button.textContent = 'Ocultar comentarios';
            } else {
                commentsContainer.style.display = 'none';
                button.textContent = 'Mostrar comentarios';
            }
        }

        // Validación de tamaño de archivo (40 MB máx.)
        const archivoInput = document.querySelector('input[name="archivo"]');
        archivoInput.addEventListener('change', function () {
            const archivo = this.files[0];
            const maxSize = 40 * 1024 * 1024; // 40 MB

            if (archivo && archivo.size > maxSize) {
                alert("El archivo excede el límite de 40 MB.");
                this.value = ""; // Limpia la selección
            }
        });
    </script>
</body>
</html>
