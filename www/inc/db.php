<?php

$dsn="mysql:dbname=php2;host=localhost;charset=utf8";
$username="root";
$password="";

// on essaie de se connecter à MySQL et on retourne un message d'erreur en cas d'échec

try {
    $pdo = new PDO($dsn, $username, $password);
} catch(Exception $erreur) {
    echo "<h1> Erreur de connection </h1><br>$erreur";
    exit();
}