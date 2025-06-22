<?php
session_start();
require_once 'auth_functions.php';
require_once 'functions.php'; // Asegúrate de incluir las funciones necesarias

// Verifica si el usuario está logueado
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

// Verificar si el ID de la notificación está presente y es un número
if (isset($_POST['notification_id']) && is_numeric($_POST['notification_id'])) {
    $notificationId = $_POST['notification_id'];

    // Función para marcar la notificación como leída
    function markNotificationAsRead($notificationId) {
        global $pdo;

        // Consulta para actualizar el estado de la notificación a 'leída'
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        return $stmt->execute([$notificationId]);
    }

    // Llamada a la función para marcar la notificación
    if (markNotificationAsRead($notificationId)) {
        echo json_encode(['success' => true, 'message' => 'Notificación marcada como leída']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al marcar la notificación']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID de notificación inválido']);
}
?>
