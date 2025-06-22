<?php
$host = 'localhost';
$dbname = 'red_social';
$username = 'root';
$password = '';

try {
    // Establecer la conexión con la base de datos usando PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

/**
 * Función para actualizar el perfil del usuario
 * 
 * @param int $userId El ID del usuario a actualizar.
 * @param string|null $bio La nueva biografía del usuario (opcional).
 * @param string|null $profilePicturePath El nuevo path de la foto de perfil (opcional).
 * @return bool Retorna true si la actualización fue exitosa, false en caso contrario.
 */
function updateUserProfile($userId, $bio = null, $profilePicturePath = null) {
    global $pdo;  // Usamos la conexión establecida en el archivo db.php

    // Preparamos la consulta SQL
    $sql = "UPDATE users SET bio = :bio";

    // Si hay una nueva foto de perfil, agregamos esa columna a la consulta SQL
    if ($profilePicturePath !== null) {
        $sql .= ", profile_picture = :profile_picture";
    }
    $sql .= " WHERE id = :user_id";

    // Preparamos la sentencia
    $stmt = $pdo->prepare($sql);

    // Asignamos los parámetros
    $stmt->bindParam(':bio', $bio);
    if ($profilePicturePath !== null) {
        $stmt->bindParam(':profile_picture', $profilePicturePath);
    }
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

    // Ejecutamos la consulta y retornamos el resultado
    return $stmt->execute();
}
?>
