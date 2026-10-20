<?php

// Verifico se le sessioni sono abilitate e nel caso se non sono state avviate, avvio la sessione
$session = true;

if (session_status() === PHP_SESSION_DISABLED)
    $session = false;
elseif (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0);
    session_start();
}

if (!$session) {
    $_SESSION['login_error'] = "SESSIONI DISABILITATE. Impossibile proseguire";
    header("Location: login.php");
    exit();
} else {

    $err = false;
    $errors = [];
    // Provo ad accedere al db e a proseguire con la logica solo se è stata ricevuta una richiesta POST
    if ($_SERVER["REQUEST_METHOD"] === "POST") {


        $username = trim($_POST['user'] ?? '');
        $password = trim($_POST['pwd'] ?? '');

        $regUsername = '/^([A-Za-z])[A-Za-z\d\-_]{3,9}$/';
        $regPassword = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^\da-zA-Z]).{8,16}$/';

        // Valida username
        if (empty($username)) {
            $errors[] = "username";
        } elseif (!preg_match($regUsername, $username)) {
            $_SESSION['login_error'] = "Autenticazione fallita: Username non valido.";
            header("Location: login.php"); /// Renderizza alla pagina di login
            exit(); // Ferma l'esecuzione dello script
        }

        // Valida password
        
        if (empty($password)) {
            $errors[] = "password";
        } elseif (!preg_match($regPassword, $password)) {
            $_SESSION['login_error'] = "Autenticazione fallita: Password non valida.";
            header("Location: login.php"); // Renderizza alla pagina di login
            exit(); /// Ferma l'esecuzione dello script
        }

        // Controlla campi mancanti
        if (!empty($errors)) {
            $_SESSION['login_error'] = "Non hai inserito i seguenti campi:\n" . implode("\n", $errors);
            header("Location: login.php"); // Renderizza alla pagina di login
            exit(); // Ferma l'esecuzione dello script
        }



        // Connessione al DB
        $db_host = 'localhost';
        $db_user = 'lettore'; // Uso privilegi limitati
        $db_pass = 'P@ssw0rd!';
        $db_name = 'eco_scambio';

        $con = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

        if (mysqli_connect_errno()) {
            $_SESSION['login_error']  = "Errore durante la connessione al DBMS.";
        } else {
            if (isset($_POST['user']) && isset($_POST['pwd'])) {

                $username = trim($_POST['user'] ?? '');
                $password = trim($_POST['pwd'] ?? '');
                $ricordami = isset($_POST['ricordami']);
                $err = false;

                if (!$err) {

                    $query = "SELECT * FROM UTENTI WHERE NICK = ? AND PASSWORD = ?";
                    $stmt = mysqli_prepare($con, $query);

                    mysqli_stmt_bind_param($stmt, "ss", $username, $password);
                    $result = mysqli_stmt_execute($stmt);
                    mysqli_stmt_store_result($stmt);

                    mysqli_stmt_bind_result($stmt, $id, $nick, $password, $artigiano);
                    mysqli_stmt_fetch($stmt);

                    if (mysqli_stmt_num_rows($stmt) == 0) {
                        $_SESSION['login_error'] = "Username o password errati";
                        $err = true;
                        header("Location: login.php");
                        exit();
                    } else {
                        $_SESSION['user'] = trim($_POST['user']);
                        $_SESSION['pwd'] = trim($_POST['pwd']);
                        $_SESSION['id'] = $id;
            

                        if ($ricordami) {
                            setcookie('user', $username, time() + (72 * 60 * 60), "/"); //setto i cookie per seguire la volontà dell'utente di essere ricordato
                            setcookie('pwd', $password, time() + (72 * 60 * 60), "/");  //IN GENERALE, NELLA VITA REALE, MAI SALVARE INFORMAZIONI SENSIBILI NEL LATO CLIENT (per motivi di sicurezza)!!
                        } else {
                            setcookie('user', '', time() - 3600, "/");
                            setcookie('pwd', '', time() - 3600, "/");
                        }

                        if ($artigiano){
                            $_SESSION['user_type'] = 'artigiano';
                            header("Location: domanda.php");
                        }
                        else{
                            $_SESSION['user_type'] = 'azienda';
                            header("Location: offerta.php");
                        }
                        exit();
                    }

                    mysqli_stmt_close($stmt);
                    mysqli_close($con);
                }
            }
        }
    }

    else {
    // Se si arriva a questa pagina senza accesso tramite POST
    $message = "Accesso non autorizzato alla gestione del login.";
    $_SESSION['login_error'] = $message;
    header('Location: login.php');
    }
    exit;
}


?>