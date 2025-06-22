<?php
session_start(); // Inicia la sesión

// Incluir el archivo con las funciones de autenticación
require_once 'auth_functions.php';  // Asegúrate de incluir el archivo con las funciones necesarias

// Llamamos a la función logoutUser definida en auth_functions.php
logoutUser();

// Redirige a la página de login después de cerrar sesión
header("Location: login.php");
exit;
?>
