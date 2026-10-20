<?php session_start();
    // Recupera la pagina di origine, se esiste
    $redirect = "home.php";

    // Cancella tutti i dati della sessione
    $_SESSION = [];
    session_destroy();

    // Reindirizza alla pagina precedente
    header("Location: login.php");
    exit();
?>
