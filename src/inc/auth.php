<?php
function find_user_by_username(string $username)
{
    $sql = 'SELECT *
            FROM usuario
            WHERE username=:username';

    $statement = db()->prepare($sql);
    $statement->bindValue(':username', $username, PDO::PARAM_STR);
    $statement->execute();

    error_log("USERNAME BUSCADO: " . $username);
    $result = $statement->fetch(PDO::FETCH_ASSOC);
    error_log("RESULTADO: " . ($result ? "ENCONTRADO" : "NO ENCONTRADO"));
    return $result;
}

function login(string $username): bool
{
    $user = find_user_by_username($username);

    if ($user) {

        session_regenerate_id();

        // Asignar atributos en la sesión
        $_SESSION['user_id']  = $user['id_usuario'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['id_rol']   = $user['id_rol'];

        return true;
    }

    return false;
}

function is_user_logged_in(): bool
{
    return isset($_SESSION['username']);
}

function require_login(): void
{
    if (!is_user_logged_in()) {
        redirect_to('login.php');
    }
}

function logout(): void
{
    if (is_user_logged_in()) {
        unset($_SESSION['username'], $_SESSION['user_id']);
        session_destroy();
        redirect_to('login.php');
    }
}

function current_user()
{
    if (is_user_logged_in()) {
        return $_SESSION['username'];
    }
    return null;
}

// Funciones para manejo de roles en página web
function current_user_role()
{
    if (is_user_logged_in() && isset($_SESSION['id_rol'])) {
        return $_SESSION['id_rol'];
    }
    return null;
}

function is_postulante(): bool
{
    return current_user_role() === 1;
}

function is_coordinador(): bool
{
    return current_user_role() === 2;
}

function is_admin(): bool
{
    return current_user_role() === 3;
}