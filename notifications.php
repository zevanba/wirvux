<?php
session_start();
require_once 'auth_functions.php';
require_once 'functions.php';  // Asegúrate de incluir las funciones de notificación

// Verifica si el usuario está logueado
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Obtener las notificaciones del usuario
$notifications = getUserNotifications($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- Asegúrate de incluir jQuery -->
</head>
<body>
    <div class="container">
        <h1>Notificaciones</h1>

        <?php if ($notifications): ?>
            <ul class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <li class="notification <?= $notification['is_read'] ? 'read' : 'unread' ?>" data-id="<?= $notification['id'] ?>">
                        <!-- El enlace estará disponible siempre, independientemente de si la notificación fue leída -->
                        <?php if (!empty($notification['post_id'])): ?>
                            <a href="post.php?id=<?= htmlspecialchars($notification['post_id']) ?>&notification_id=<?= htmlspecialchars($notification['id']) ?>" class="notification-link">
                                <?= htmlspecialchars($notification['message']) ?>
                            </a>
                        <?php else: ?>
                            <!-- Si no hay post_id, solo mostramos el mensaje de la notificación -->
                            <?= htmlspecialchars($notification['message']) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No tienes notificaciones.</p>
        <?php endif; ?>

        <div class="back-btn-container">
            <a href="index.php" class="button">Volver al inicio</a>
        </div>
    </div>

    <script>
        // Marcar la notificación como leída al hacer clic
        $(".notification").on("click", function() {
            var notificationId = $(this).data('id');
            
            $.ajax({
                url: "update_notification.php",  // Archivo PHP que actualizará el estado de la notificación
                type: "POST",
                data: { notification_id: notificationId },
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        // Si la notificación se marcó correctamente, actualizamos el UI
                        $(this).addClass("read").removeClass("unread");
                    } else {
                        alert("Error: " + response.message);
                    }
                }.bind(this), // Para asegurar que 'this' haga referencia al elemento clickeado
                error: function() {
                    alert("Error al marcar la notificación como leída");
                }
            });
        });
    </script>
</body>
</html>
