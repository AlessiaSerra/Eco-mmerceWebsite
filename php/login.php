<?php
// Mi assicuro che la sessione sia avviata
if (session_status() === PHP_SESSION_DISABLED) {
        die("Le sessioni sono disabilitate. Il sito non può funzionare correttamente.");
    } elseif (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

$errMessage = ""; // Inizializza le var utili nella parte di HTML
if (isset($_SESSION['login_error'])) {
    $errMessage = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Cancella gli errori dopo averli mostrati
}


$successMessage = "";
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Cancella gli errori dopo averli mostrati
}

/*if (isset($_SESSION['user'], $_SESSION['pwd'], $_SESSION['id'], $_SESSION['user_type'])) {
$_SESSION['success_message'] = "Logout avvenuto con successo! Compila il form per accedere nuovamente.";
header("Location: logout.php");
exit;
}*/
$page_title = "Login";
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8" />
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="author" content="Alessia Serra">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../css/stile.css">
    <script defer src="../js/login.js"></script>
    <link rel="icon" type="image/jpeg" href="/PROGETTO_ESAME/img/icona.png">
    </head>
<body>
    <?php require 'header.php'; ?>

    <main>
    <form id="login" class="container" method="post" action="login_back.php" aria-labelledby="loginHeading">
        <fieldset>
            <h2 id="loginHeading">Login</h2>

            <label for="user">Username:</label>
            <input type="text" id="user" name="user" value="<?php echo isset($_COOKIE['user']) ? htmlspecialchars($_COOKIE['user']) : ''; ?>" required aria-required="true">

            <label for="pwd">Password:</label>
            <input type="password" id="pwd" name="pwd" value="<?php echo isset($_COOKIE['pwd']) ? htmlspecialchars($_COOKIE['pwd']) : ''; ?>" required aria-required="true">
        </fieldset>

        <?php
        if (!empty($errMessage)) {
            echo "<p class='error' role='alert'>" . htmlspecialchars($errMessage) . "</p>";
        }


        if (!empty($successMessage)):
            echo "<p class='success' role='status'>" . htmlspecialchars($successMessage) . "</p>";

        endif; ?>


        <div class="ricordami">
            <input type="checkbox" id="ricordami" name="ricordami" aria-labelledby="ricordamiLabel">
            <label for="ricordami" id="ricordamiLabel"> Ricordami</label>
        </div>

        <p class="link_reg">
            Non hai un account? <a href="registrazione.php" aria-label="Registrati ora per un nuovo account">Registrati</a> ora.
        </p>

        <div class="button-group">
            <button id="btn1" type="submit" aria-label="Invia credenziali di login">INVIA</button>
            <button id="btn2" type="reset" aria-label="Cancella i campi del modulo di login">CANCELLA</button>
        </div>
    </form>
    </main>

    <?php require_once 'footer.php'; ?>
</body>
</html>