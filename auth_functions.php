<?php
require_once 'db.php'; // Asegúrate de que este archivo define $pdo (PDO)

// Verifica si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Obtener un usuario por su ID
function getUserById($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Iniciar sesión de usuario
function loginUser($username, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nickname'] = $user['nickname'];
        return true;
    }

    return false;
}

// Registrar nuevo usuario
function registerUser($username, $password, $nickname) {
    global $pdo;

    // Verifica si el usuario ya existe
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) {
        return false; // Usuario ya existe
    }

    // Hashea y guarda el nuevo usuario
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, nickname) VALUES (?, ?, ?)");
    return $stmt->execute([$username, $hashedPassword, $nickname]);
}

// Cerrar sesión
function logoutUser() {
    session_unset();  // Elimina todas las variables de sesión
    session_destroy(); // Destruye la sesión
    header("Location: login.php");  // Redirige al login
    exit;
}
?>
