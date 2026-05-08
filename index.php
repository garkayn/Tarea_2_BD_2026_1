<?php
session_start();
require_once './src/inc/auth.php';
require_once './src/bootstrap.php';

if (is_user_logged_in()) {
    redirect_to('./public/home.php');
} else {
    redirect_to('./public/login.php');
}
?>