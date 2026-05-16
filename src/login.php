<?php

if (is_user_logged_in()) {
    redirect_to('home.php');
}

$inputs = [];
$errors = [];

if (is_post_request()) {

    [$inputs, $errors] = filter($_POST, [
        'username' => 'raw | required'
    ]);

    if (isset($errors['username'])) {
        $errors['username'] = 'El nombre de usuario es obligatorio.';
    }

    if ($errors) {
        redirect_with('login.php', ['errors' => $errors, 'inputs' => $inputs]);
    }

    if (!login($inputs['username'])) {

        $errors['username'] = 'Nombre de usuario incorrecto o no registrado';

        redirect_with('login.php', [
            'errors' => $errors,
            'inputs' => $inputs
        ]);
    }
    redirect_to('home.php');

} else if (is_get_request()) {
    [$errors, $inputs] = session_flash('errors', 'inputs');
}