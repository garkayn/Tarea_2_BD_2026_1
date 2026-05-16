<?php

const FLASH = 'FLASH_MESSAGES';

const FLASH_ERROR = 'error';
const FLASH_WARNING = 'warning';
const FLASH_INFO = 'info';
const FLASH_SUCCESS = 'success';

/**
* Crea mensajes flash
*
* @param string $name
* @param string $message
* @param string $type
* @return void
*/
function create_flash_message(string $name, string $message, string $type): void
{
    if (isset($_SESSION[FLASH][$name])) {
        unset($_SESSION[FLASH][$name]);
    }
    $_SESSION[FLASH][$name] = ['message' => $message, 'type' => $type];
}


/**
* Da formato al mensaje flash
*
* @param array $flash_message
* @return string
*/
function format_flash_message(array $flash_message): string
{
    return sprintf('<div class="alert alert-%s">%s</div>',
        $flash_message['type'],
        $flash_message['message']
    );
}

/**
* Muestra el mensaje flash
*
* @param string $name
* @return void
*/
function display_flash_message(string $name): void
{
    if (!isset($_SESSION[FLASH][$name])) {
        return;
    }

    $flash_message = $_SESSION[FLASH][$name];

    unset($_SESSION[FLASH][$name]);

    echo format_flash_message($flash_message);
}

/**
* Muestra todos los mensajes flash
*
* @return void
*/
function display_all_flash_messages(): void
{
    if (!isset($_SESSION[FLASH])) {
        return;
    }

    $flash_messages = $_SESSION[FLASH];

    unset($_SESSION[FLASH]);

    foreach ($flash_messages as $flash_message) {
        echo format_flash_message($flash_message);
    }
}

/**
* Flash a message
*
* @param string $name
* @param string $message
* @param string $type (error, warning, info, success)
* @return void
*/
function flash(string $name = '', string $message = '', string $type = ''): void
{
    if ($name !== '' && $message !== '' && $type !== '') {
        create_flash_message($name, $message, $type);
    } elseif ($name !== '' && $message === '' && $type === '') {
        display_flash_message($name);
    } elseif ($name === '' && $message === '' && $type === '') {
        display_all_flash_messages();
    }
}