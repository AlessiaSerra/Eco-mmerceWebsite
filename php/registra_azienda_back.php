<?php
    // controllo lo stato della sessione
    if (session_status() === PHP_SESSION_DISABLED) {
        die("Le sessioni sono disabilitate. Il sito non può funzionare correttamente.");
    } elseif (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

// Inizializza l'array per gli errori e per i dati pre-compilati
// Questi verranno salvati nella sessione e utilizzati da registrazione.php
$errors = [];
$old_input = [];

// Funzione per pulire e validare i dati di input, migliorata con htmlspecialchars
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); // ENT_QUOTES per gestire apici
    return $data;
}

// Connessione al database
// In un'applicazione reale, queste credenziali dovrebbero essere in un file di configurazione separato
// e non accessibile via web per motivi di sicurezza.
$db_host = 'localhost';
$db_user = 'modificatore';
$db_pass = 'Str0ng#Admin9';
$db_name = 'eco_scambio';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Gestione dell'errore di connessione al database
if (mysqli_connect_errno()) {
    error_log("Errore connessione al DBMS per azienda: " . mysqli_connect_error());
    $_SESSION['err_registraz_azienda']['generali'] = "Si è verificato un problema interno. Riprova più tardi.";
    header('Location: registrazione.php');
    exit;
}

// Imposta il charset per la connessione al database
mysqli_set_charset($conn, "utf8mb4");

// Verifica che la richiesta sia POST, altrimenti reindirizza alla pagina di registrazione
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['reg_error'] = "Non sei autorizzato alla gestione della registrazione.";
    header('Location: registrazione.php');
    exit;
}

// Recupera e pulisci i dati dal form
// Usiamo sanitize_input per tutti i campi tranne la password per motivi di sicurezza
$ragione = sanitize_input($_POST['ragione'] ?? '');
$address2 = sanitize_input($_POST['address2'] ?? '');
$nick = sanitize_input($_POST['nick'] ?? '');
$password = $_POST['password'] ?? ''; // La password non viene pulita per non alterare il suo valore originale 

// Conserva l'input dell'utente (tranne la password) per ripopolare il form in caso di errore
$_SESSION['old_registration_input_azienda'] = [
    'ragione' => $ragione,
    'address2' => $address2,
    'nick' => $nick,
];

//  Validazione Ragione Sociale 
// Regex aggiornata per essere più permissiva con gli spazi interni e i caratteri speciali comuni
$regexRagione = '/^[\p{Lu}\p{L}\p{N} &\']{1,30}$/u';
if (empty($ragione)) { 
    $errors['ragione'] = "La ragione sociale è obbligatoria.";
} elseif (!preg_match($regexRagione, $ragione)) {
    $errors['ragione'] = "La ragione sociale può contenere solo lettere, numeri, '&', apostrofi e spazi (max 30 caratteri).";
} elseif (mb_strlen($ragione, 'UTF-8') > 30) {
    $errors['ragione'] = "La lunghezza della ragione sociale inserita deve essere minore o uguale a 30 caratteri.";
}

// Validazione Indirizzo (address2) 
// Regex migliorata per essere più robusta e gestire meglio i nomi delle vie
$regexAddress2 =  "/^(Via|Corso|Piazza|Viale)\s+([\p{L}\d\s\.'\-]+)\s+(\d{1,3}[a-zA-Z]?)[ ,]+\s*([\p{L}\s]+)$/u";
if (empty($address2)) {
    $errors['address2'] = "L'indirizzo è obbligatorio.";
} elseif (!preg_match($regexAddress2, $address2)) {
    $errors['address2'] = "L'indirizzo deve essere nella forma 'Via/Corso/Piazza/Viale Nome Via NumeroCivico, Citta' (es. 'Via Roma 12, Milano', 'Corso Italia 345B, Trento'). Il nome può contenere lettere, numeri, spazi, punti, apostrofi, trattini.";
}


// Validazione Username (con controllo unicità)
if (empty($nick)) {
    $errors['nick'] = "Lo username è obbligatorio.";
} elseif (mb_strlen($nick, 'UTF-8') < 4 || mb_strlen($nick, 'UTF-8') > 10) {
    $errors['nick'] = "L'username deve essere lunga da 4 a 10 caratteri.";
} elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/u', $nick)) {
    $errors['nick'] = "L'username deve iniziare con una lettera e contenere solo lettere, numeri, '-' o '_'.";
} else {
    // Controllo unicità username nel database
    $query = "SELECT NICK FROM UTENTI WHERE NICK = ?";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt === false) {
        error_log("Errore preparazione query username azienda: " . mysqli_error($conn));
        $errors['generali'] = "Si è verificato un errore interno. Riprova più tardi.";
    } else {
        mysqli_stmt_bind_param($stmt, "s", $nick);
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Errore esecuzione query username azienda: " . mysqli_stmt_error($stmt));
            $errors['generali'] = "Si è verificato un errore interno. Riprova più tardi.";
        } else {
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $errors['nick'] = "Questo username è già in uso. Scegli un altro username.";
            }
        }
        mysqli_stmt_close($stmt);
    }
}


// Validazione Password
$regexPassword = '/^(?=.*\p{Lu})(?=.*\p{Ll})(?=.*\p{N})(?=.*[.;+=])[\p{L}\p{N}.;+=]{8,16}$/u';
if (empty($password)) {
    $errors['password'] = "La password è obbligatoria.";
} elseif (mb_strlen($password, 'UTF-8') < 8 || mb_strlen($password, 'UTF-8') > 16) {
    $errors['password'] = "La password deve essere lunga da 8 a 16 caratteri.";
} elseif (!preg_match($regexPassword, $password)) {
    $errors['password'] = "La password deve contenere: 1 maiuscola, 1 minuscola, 1 numero, 1 carattere speciale (.;+=). Non sono ammessi altri caratteri";
}

// Gestione dei risultati della validazione
if (count($errors) > 0) {
    // Se ci sono errori, salva gli errori nella sessione e reindirizza al form
    $_SESSION['err_registraz_azienda'] = $errors;
    header('Location: registrazione.php');
    exit;
} else {
    // Nessun errore di validazione, procedi con la registrazione nel database

    
    $is_artigiano = false; // Imposto a 'false' per l'azienda (0 nel DB per boolean)

    // Inserimento dei dati nella tabella 'utenti'
    $query_insert_utente = "INSERT INTO utenti (NICK, PASSWORD, ARTIGIANO) VALUES (?, ?, ?)";
    $stmt_insert_utente = mysqli_prepare($conn, $query_insert_utente);

    if ($stmt_insert_utente === false) {
        error_log("Errore preparazione query inserimento utente azienda: " . mysqli_error($conn));
        $_SESSION['error_message'] = "Si è verificato un errore interno durante la preparazione della registrazione. Riprova più tardi.";
        header('Location: registrazione.php');
        exit;
    }

    mysqli_stmt_bind_param($stmt_insert_utente, "ssi", $nick, $password, $is_artigiano);

    if (!mysqli_stmt_execute($stmt_insert_utente)) {
        error_log("Errore esecuzione query inserimento utente azienda: " . mysqli_stmt_error($stmt_insert_utente));
        // Aggiungi un controllo specifico per errore di duplicazione (ad esempio, se il nick passa la validazione iniziale ma poi il DB lo rifiuta)
        if (mysqli_errno($conn) == 1062) { // Codice errore per duplicazione entry (per UNIQUE index)
             $_SESSION['error_message'] = "Questo username è già in uso. Scegli un altro username.";
        } else {
            $_SESSION['error_message'] = "Si è verificato un problema durante la registrazione dell'utente principale. Riprova più tardi.";
        }
        header('Location: registrazione.php');
        exit;
    }

    $id_assegnato_utente = mysqli_insert_id($conn); // Recupera l'ID appena generato per l'utente
    mysqli_stmt_close($stmt_insert_utente);


    // Inserimento dati nella tabella 'dati_aziende'
    $query_insert_dati_azienda = "INSERT INTO dati_aziende (ID_UTENTE, RAGIONE, ADDRESS2) VALUES (?, ?, ?)";
    $stmt_insert_dati_azienda = mysqli_prepare($conn, $query_insert_dati_azienda);

    if ($stmt_insert_dati_azienda === false) {
        error_log("Errore preparazione query inserimento dati azienda: " . mysqli_error($conn));
        $_SESSION['err_registraz_azienda']['generali'] = "Si è verificato un errore interno durante il salvataggio dei dati aziendali. Riprova più tardi.";
        // Tenta di eliminare l'utente appena creato dalla tabella 'utenti' per mantenere la consistenza del DB
        $query_delete_user = "DELETE FROM utenti WHERE id = ?";
        $stmt_delete_user = mysqli_prepare($conn, $query_delete_user);
        if ($stmt_delete_user) {
            mysqli_stmt_bind_param($stmt_delete_user, "i", $id_assegnato_utente);
            if (mysqli_stmt_execute($stmt_delete_user)) {
                error_log("Utente (ID: $id_assegnato_utente) eliminato con successo a causa di errore in dati_aziende.");
            } else {
                error_log("ERRORE CRITICO: Impossibile eliminare utente (ID: $id_assegnato_utente) dopo fallimento inserimento dati_aziende: " . mysqli_stmt_error($stmt_delete_user));
            }
            mysqli_stmt_close($stmt_delete_user);
        } else {
            error_log("ERRORE CRITICO: Impossibile preparare query di eliminazione utente: " . mysqli_error($conn));
        }
        header('Location: registrazione.php');
        exit;
    }

    mysqli_stmt_bind_param($stmt_insert_dati_azienda, "iss", $id_assegnato_utente, $ragione, $address2);

    if (!mysqli_stmt_execute($stmt_insert_dati_azienda)) {
        error_log("Errore esecuzione query inserimento dati azienda: " . mysqli_stmt_error($stmt_insert_dati_azienda));
        $_SESSION['err_registraz_azienda']['generali'] = "Si è verificato un problema durante la registrazione dei dati aziendali. Riprova più tardi.";
        // Tenta di eliminare l'utente appena creato dalla tabella 'utenti' per mantenere la consistenza del DB
        $query_delete_user = "DELETE FROM utenti WHERE id = ?";
        $stmt_delete_user = mysqli_prepare($conn, $query_delete_user);
        if ($stmt_delete_user) {
            mysqli_stmt_bind_param($stmt_delete_user, "i", $id_assegnato_utente);
            if (mysqli_stmt_execute($stmt_delete_user)) {
                error_log("Utente (ID: $id_assegnato_utente) eliminato con successo a causa di errore in dati_aziende.");
            } else {
                error_log("ERRORE CRITICO: Impossibile eliminare utente (ID: $id_assegnato_utente) dopo fallimento inserimento dati_aziende: " . mysqli_stmt_error($stmt_delete_user));
            }
            mysqli_stmt_close($stmt_delete_user);
        } else {
            error_log("ERRORE CRITICO: Impossibile preparare query di eliminazione utente: " . mysqli_error($conn));
        }
        header('Location: registrazione.php');
        exit;
    }

    mysqli_stmt_close($stmt_insert_dati_azienda);
    mysqli_close($conn); // Chiudi la connessione al database una volta completate le operazioni

    
    // Registrazione avvenuta con successo
    // Imposta le variabili di sessione per l'utente loggato
    // Registrazione avvenuta con successo 
    $_SESSION['success_message'] = 'Registrazione avvenuta con successo! Accedi per cominciare la tua esperienza.';
    //faccio scadere il cookie con la password per il l'ultimo utente loggato con 'ricordami'
    // e setto il cookie con lo username per 1min (per facilitare l'esperienza utente, ma non inficiare sulla sua privacy nel lungo termine, 
    // anche perchè lui non ha richiesto nè autorizzato "formalmente" niente)
    setcookie('user', $nick, time() + (60), "/");
    setcookie('pwd', '', time() - 3600, "/"); 


    // Reindirizza alla pagina di login, se vuole  dell'azienda 
    header('Location: login.php');
    exit;
}
?>