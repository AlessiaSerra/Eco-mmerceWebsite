<?php
    // Controllo lo stato della sessione
 if (session_status() === PHP_SESSION_DISABLED) {
        die("Le sessioni sono disabilitate. Il sito non può funzionare correttamente.");
    } elseif (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Connessione al database 
    $db_host = 'localhost';
    $db_user = 'modificatore';
    $db_pass = 'Str0ng#Admin9';
    $db_name = 'eco_scambio';
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

    if (mysqli_connect_errno()) {
        error_log("Errore connessione al DBMS per artigiano: " . mysqli_connect_error());
        $_SESSION['err_registraz_artigiano']['general'] = "Si è verificato un problema interno. Riprova più tardi.";
        // Reindirizza al form di registrazione
        header('Location: registrazione.php');
        exit;
    }
    mysqli_set_charset($conn, "utf8mb4");

    // Mi assicuro che la richiesta sia POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['reg_error'] = "Non sei autorizzato alla gestione della registrazione.";
        header('Location: registrazione.php');
        exit;
    }

    // Inizializza un array per gli errori di validazione
    $errors = [];

    // Recupera i dati dal form
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $credit = trim($_POST['credit'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $nick = trim($_POST['nick'] ?? '');
    $password = $_POST['password'] ?? '';

    // Conservo l'input dell'utente (tranne la password) per ripopolare il form
    $_SESSION['old_registration_input_artigiano'] = [
        'name' => $name,
        'surname' => $surname,
        'birthdate' => $birthdate,
        'credit' => $credit,
        'address' => $address,
        'nick' => $nick,
    ];

    // Validazione Nome 

    $regexName = '/^[\p{L}\s]{4,14}$/u';
    if (empty($name)) {
        $errors['name'] = "Il nome è obbligatorio.";
    } elseif (!preg_match($regexName, $name)) {
        $errors['name'] = "Il nome deve avere tra 4 e 14 caratteri e contenere solo lettere e spazi.";
    }

    //  Validazione Cognome 
    $regexSurname = '/^[\p{L}\s\']{4,16}$/u';
    if (empty($surname)) {
        $errors['surname'] = "Il cognome è obbligatorio.";
    } elseif (!preg_match($regexSurname, $surname)) {
        $errors['surname'] = "Il cognome deve avere tra 4 e 16 caratteri e contenere solo lettere, spazi o apostrofi.";
    }

    //  Validazione Data di Nascita 
    // Formato AAAA-MM-GG. `filter_var` con FILTER_VALIDATE_REGEXP per la regex
    // E poi `checkdate` per la validità della data
    if (empty($birthdate)) {
        $errors['birthdate'] = "La data di nascita è obbligatoria.";
    } else {
        $date_parts = explode('-', $birthdate);
        if (count($date_parts) === 3) {
            $year = (int)$date_parts[0];
            $month = (int)$date_parts[1];
            $day = (int)$date_parts[2];

            if (!checkdate($month, $day, $year)) {
                $errors['birthdate'] = "Data di nascita non valida. Usa il formato AAAA-MM-GG.";
            } else {
                // Opzionale: controllare che la data non sia nel futuro e/o età minima
                $today = new DateTime();
                $dob = new DateTime($birthdate);
                if ($dob > $today) {
                    $errors['birthdate'] = "La data di nascita non può essere nel futuro.";
                }
                // Età minima 18 anni
                $age = $today->diff($dob)->y;
                if ($age < 18) {
                    $errors['birthdate'] = "Devi avere almeno 18 anni per registrarti.";
                }
            }
        } else {
            $errors['birthdate'] = "Formato data di nascita non valido. Usa AAAA-MM-GG.";
        }
    }

    // Validazione Credito 
    if (empty($credit)) {
        $errors['credit'] = "Il credito è obbligatorio.";
    } elseif (!is_numeric($credit) || $credit < 0) {
        $errors['credit'] = "Il credito deve essere un numero positivo.";
    } else {
        // Converti a stringa per gestire la precisione dei decimali
        $credit_str = (string)$credit;
        // Trova la parte decimale
        $parts = explode('.', $credit_str);
        $decimal_part = isset($parts[1]) ? $parts[1] : '';

        if (strlen($decimal_part) > 2) {
            $errors['credit'] = "Il credito può avere al massimo due cifre decimali.";
        } elseif (fmod(round($credit * 100), 5) != 0) { // Moltiplica per 100 e arrotonda per precisione, poi modulo
            $errors['credit'] = "Il credito deve avere variazioni di 5 centesimi (es. 10.00, 10.05, 10.10).";
        }
    }

    // Validazione Indirizzo (stesse regole azienda) 
    $regexAddress = "/^(Via|Corso|Piazza|Viale)\s+([\p{L}\d\s\.'\-]+)\s+(\d{1,3}[a-zA-Z]?)[ ,]+\s*([\p{L}\s]+)$/u";
    if (empty($address)) {
        $errors['address'] = "L'indirizzo è obbligatorio.";
    } elseif (!preg_match($regexAddress, $address)) {
        $errors['address'] = "L'indirizzo deve essere nella forma 'Via/Corso Nome Via NumeroCivico, Città' (es. 'Via Roma 12, Milano', 'Corso Italia 345, Torino').";
    }

    // Validazione Username (con controllo unicità) 
    $regexNick = '/^\p{L}[\p{L}\p{N}_-]*$/u';
    if (empty($nick)) {
        $errors['nick'] = "L'username è obbligatoria.";
    } elseif (mb_strlen($nick, 'UTF-8') < 4 || mb_strlen($nick, 'UTF-8') > 10) {
        $errors['nick'] = "L'username deve essere lunga da 4 a 10 caratteri.";
    } elseif (!preg_match($regexNick, $nick)) {
        $errors['nick'] = "L'username deve iniziare con una lettera e contenere solo lettere, numeri, '-' o '_'.";
    } else {
        // Controllo unicità nel DB
        $query = "SELECT NICK FROM UTENTI WHERE NICK = ?";
        $stmt = mysqli_prepare($conn, $query);
        if ($stmt === false) {
            error_log("Errore preparazione query username artigiano: " . mysqli_error($conn));
            $errors['general'] = "Si è verificato un errore interno. Riprova più tardi.";
        } else {
            mysqli_stmt_bind_param($stmt, "s", $nick);
            if (!mysqli_stmt_execute($stmt)) {
                error_log("Errore esecuzione query username artigiano: " . mysqli_stmt_error($stmt));
                $errors['general'] = "Si è verificato un errore interno. Riprova più tardi.";
            } else {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    $errors['nick'] = "Questo username è già in uso. Scegli un altro username.";
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Validazione Password (stesse regole azienda) 
    $regexPassword = '/^(?=.*\p{Lu})(?=.*\p{Ll})(?=.*\p{N})(?=.*[.;+=])[\p{L}\p{N}.;+=]{8,16}$/u';
    if (empty($password)) {
        $errors['password'] = "La password è obbligatoria.";
    } elseif (mb_strlen($password, 'UTF-8') < 8 || mb_strlen($password, 'UTF-8') > 16) {
        $errors['password'] = "La password deve essere lunga da 8 a 16 caratteri.";
    } elseif (!preg_match($regexPassword, $password)) {
        $errors['password'] = "La password deve contenere almeno 1 maiuscola, 1 minuscola, 1 numero e 1 carattere speciale (.;+=) e avere tra 8 e 16 caratteri.";
    }

    // FINE VALIDAZIONE

    if (count($errors) > 0) {
        $_SESSION['err_registraz_artigiano'] = $errors;
        header('Location: registrazione.php'); // Reindirizza alla pagina generica
        exit;
    } else {
        $is_artigiano_flag = true; // Imposto a true per l'artigiano

        // Inserimento utente nella tabella 'utenti'
        $query_insert_utente = "INSERT INTO utenti (NICK, PASSWORD, ARTIGIANO) VALUES (?, ?, ?)";
        $stmt_insert_utente = mysqli_prepare($conn, $query_insert_utente);

        if ($stmt_insert_utente === false) {
            error_log("Errore preparazione query inserimento utente artigiano: " . mysqli_error($conn));
            $_SESSION['err_registraz_artigiano']['general'] = "Si è verificato un errore interno. Riprova più tardi.";
            header('Location: registrazione.php');
            exit;
        }
    
        //per le norme di buona programmazione studiate nel corso dovremmo applicare una funzione hash alla password,
        // ma aggiungerebbe troppa complessità al codice
        mysqli_stmt_bind_param($stmt_insert_utente, "ssi", $nick, $password, $is_artigiano_flag);

        if (!mysqli_stmt_execute($stmt_insert_utente)) {
            error_log("Errore esecuzione query inserimento utente artigiano: " . mysqli_stmt_error($stmt_insert_utente));
            $_SESSION['err_registraz_artigiano']['general'] = "Si è verificato un problema durante la registrazione dell'utente artigiano principale. Riprova più tardi.";
            header('Location: registrazione.php');
            exit;
        }

        $id_assegnato_utente = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_insert_utente);

        
        $query_insert_dati_artigiano = "INSERT INTO dati_artigiani (ID_UTENTE, NAME, SURNAME, BIRTHDATE, CREDIT, ADDRESS) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_insert_dati_artigiano = mysqli_prepare($conn, $query_insert_dati_artigiano);

        if ($stmt_insert_dati_artigiano === false) {
            error_log("Errore preparazione query inserimento dati artigiano: " . mysqli_error($conn));
            $_SESSION['err_registraz_artigiano']['general'] = "Si è verificato un errore interno durante il salvataggio dei dati artigiano. Riprova più tardi.";
            // Rollback: elimina l'utente appena creato dalla tabella 'utenti'
            $query_delete_user = "DELETE FROM utenti WHERE id = ?";
            $stmt_delete_user = mysqli_prepare($conn, $query_delete_user);
            if ($stmt_delete_user) {
                mysqli_stmt_bind_param($stmt_delete_user, "i", $id_assegnato_utente);
                mysqli_stmt_execute($stmt_delete_user);
                mysqli_stmt_close($stmt_delete_user);
            }
            header('Location: registrazione.php');
            exit;
        }

        // 'isssds' = int, string, string, string (data), double (credito), string (indirizzo)
        mysqli_stmt_bind_param($stmt_insert_dati_artigiano, "isssds", $id_assegnato_utente, $name, $surname, $birthdate, $credit, $address);

        if (!mysqli_stmt_execute($stmt_insert_dati_artigiano)) {
            error_log("Errore esecuzione query inserimento dati artigiano: " . mysqli_stmt_error($stmt_insert_dati_artigiano));
            $_SESSION['err_registraz_artigiano']['general'] = "Si è verificato un problema durante la registrazione dei dati artigiano. Riprova più tardi.";
            // Rollback: elimina l'utente appena creato dalla tabella 'utenti'
            $query_delete_user = "DELETE FROM utenti WHERE id = ?";
            $stmt_delete_user = mysqli_prepare($conn, $query_delete_user);
            if ($stmt_delete_user) {
                mysqli_stmt_bind_param($stmt_delete_user, "i", $id_assegnato_utente);
                mysqli_stmt_execute($stmt_delete_user);
                mysqli_stmt_close($stmt_delete_user);
            }
    
        }

        mysqli_stmt_close($stmt_insert_dati_artigiano);
        mysqli_close($conn);

        // Registrazione avvenuta con successo

        $_SESSION['success_message'] = 'Registrazione avvenuta con successo! Accedi per cominciare la tua esperienza.';
        
        //COME PER AZIENDA: faccio scadere il cookie con la password per il l'ultimo utente loggato con 'ricordami'
            // e setto il cookie con lo username per 2min (per facilitare l'esperienza utente, ma non inficiare sulla sua privacy nel lungo termine)
        setcookie('user', $nick, time() + (2 * 60 ), "/");
        setcookie('pwd', '', time() - 3600, "/"); 

        header('Location: login.php'); 
        exit;
    }
?>