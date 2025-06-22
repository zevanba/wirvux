<?php
// Verificar si el formulario fue enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar que tanto el id del post como el comentario fueron enviados
    if (isset($_POST['post_id']) && isset($_POST['comment'])) {
        $postId = $_POST['post_id'];
        $comment = $_POST['comment'];

        // Llamar a la función que agrega el comentario
        addComment($postId, $comment);

        // Redirigir al post
        header("Location: post.php?id=" . $postId); // Asegúrate de que post.php reciba correctamente el parámetro id
        exit(); // Asegúrate de salir después de la redirección
    } else {
        // Si no se envió algún campo necesario, redirigir con error o mostrar mensaje
        echo "Error: No se enviaron los datos necesarios.";
    }
}
?>
