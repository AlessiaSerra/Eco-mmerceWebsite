<?php
// Inizio una sessione, se non c'è n'è gia una attiva
if (session_status() === PHP_SESSION_DISABLED) {
    error_log("SESSION_DISABLED: PHP sessions are disabled. Application cannot function.");
    die("A critical error occurred: Sessions are disabled. Please contact support.");
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inizializza i messaggi di feedback per la sessione, solo se non è una richiesta AJAX per evitare di sovrascriverli.
// Per le richieste AJAX, il feedback verrà inviato direttamente nel JSON response.
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    $_SESSION['app_feedback_msg'] = '';
    $_SESSION['feedback_type'] = '';
}

// connessione al database
$dbHostName = 'localhost';
$dbUserName = 'modificatore';
$dbPassword = 'Str0ng#Admin9';
$dbSchemaName = 'eco_scambio';

// Tenta di stabilire la connessione al database
$connectionHandle = mysqli_connect($dbHostName, $dbUserName, $dbPassword, $dbSchemaName);

if (mysqli_connect_errno()) {
    // Log dell'errore di connessione per il debugging lato server
    error_log("Fallimento connessione DB (material_actions.php): " . mysqli_connect_error());

    // Se è una richiesta AJAX, invia JSON. Altrimenti, imposta sessione e reindirizza.
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(['success' => false, 'message' => "Si è verificato un errore critico del server: impossibile connettersi al database. Riprova più tardi.", 'type' => 'error_critical']);
    } else {
        $_SESSION['app_feedback_msg'] = "Si è verificato un errore critico del server: impossibile connettersi al database. Riprova più tardi.";
        $_SESSION['feedback_type'] = "error_alert";
        header('Location: initial_landing.php'); // Reindirizza a una pagina di errore generica o home
        exit;
    }
}

// Imposta il set di caratteri per la connessione al database a UTF-8
mysqli_set_charset($connectionHandle, "utf8mb4");

// Recupero l'ID dell'utente dalla sessione per tutte le operazioni
$currentEntityId = $_SESSION['id'] ?? null;
$entityRole = $_SESSION['user_type'] ?? null;

// Verifica autorizzazione: solo aziende possono operare qui
if ($currentEntityId === null || $entityRole !== 'azienda') {
    $message = "Accesso negato. Questa sezione è riservata esclusivamente alle aziende registrate. Effettua il login con le tue credenziali aziendali.";
    // Se è una richiesta AJAX, invia JSON. Altrimenti, imposta sessione e reindirizza.
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(['success' => false, 'message' => $message, 'type' => 'warning']);
    } else {
        $_SESSION['app_feedback_msg'] = $message;
        $_SESSION['feedback_type'] = "warning_alert";
        header("Location: login_portal.php"); // Reindirizza alla pagina di login
    }
    exit;
}

// Funzione per inviare risposte JSON
function sendJsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Gestione delle azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionPerformed = $_POST['action_type'] ?? ''; // matchato con 'action_type' in js

    switch ($actionPerformed) {
        case 'add': 
            $itemName = trim($_POST['new_material_name'] ?? '');
            $itemDescription = trim($_POST['new_material_description'] ?? '');
            $itemDate = trim($_POST['new_material_date'] ?? '');
            $itemQuantity = intval($_POST['new_material_quantity'] ?? 0);
            $itemCost = floatval(str_replace(',', '.', $_POST['new_material_cost'] ?? 0.0)); // Gestisci virgola come separatore decimale

            $fieldErrors = [];

            // Validazione lato server
            if (empty($itemName)) {
                $fieldErrors['material_name'] = "Il nome del materiale è obbligatorio.";
            } elseif (mb_strlen($itemName) > 40) {
                $fieldErrors['material_name'] = "Il nome non può superare i 40 caratteri.";
            } elseif (mb_strlen($itemName) < 10) {
                $fieldErrors['material_name'] = "Il nome deve avere almeno 10 caratteri.";
            } elseif (!preg_match('/^[a-zA-Z0-9\s]*$/', $itemName)) {
                $fieldErrors['material_name'] = "Il nome può contenere solo lettere, numeri e spazi.";
            }

            // Esegui la verifica solo se non ci sono già errori sul nome
            if (empty($fieldErrors['material_name'])) {
                
                $stmt_check_name = mysqli_prepare($connectionHandle, "SELECT NOME FROM MATERIALI WHERE ID_UTENTE = ? AND NOME = ?");
                if ($stmt_check_name) {
                    mysqli_stmt_bind_param($stmt_check_name, "is", $currentEntityId, $itemName);
                    if (mysqli_stmt_execute($stmt_check_name)) {
                        mysqli_stmt_store_result($stmt_check_name);
                        if (mysqli_stmt_num_rows($stmt_check_name) > 0){
                            $fieldErrors['material_name'] = "Il materiale che vuoi inserire esiste già. Cambia il nome del materiale o modifica quello già esistente.";
                        }
                    } else {
                        error_log("Errore esecuzione query ricerca nome duplicato: " . mysqli_error($connectionHandle));
                        $fieldErrors['general'] = "Errore durante la verifica del nome del materiale.";
                    }
                    mysqli_stmt_close($stmt_check_name);
                } else {
                    error_log("Errore preparazione statement ricerca nome duplicato: " . mysqli_error($connectionHandle));
                    $fieldErrors['general'] = "Errore interno del server nella preparazione della query.";
                }
            }


            if (mb_strlen($itemDescription) > 250) { 
                $fieldErrors['material_description'] = "La descrizione non può superare i 250 caratteri.";
            }

            //  VALIDAZIONE E PREPARAZIONE DELLA DATA PER L'INSERIMENTO
            $dateToSave = null; // Inizializza a NULL (per DB)
            if (empty($itemDate)) {
                // Questo caso dovrebbe essere gestito dall'attributo 'required' in HTML
                //ma in ogni caso, non è garantito che al server non arrivi una manipolazione del pacchetto inviato.
                // Per cui se vuota, la data viene impostata come quella corrente per default
                $dateToSave = date('Y-m-d'); // Set current date if not provided
            } else {
                $date_obj = DateTime::createFromFormat('Y-m-d', $itemDate);
                $today = new DateTime();
                $today->setTime(0, 0, 0); // Time per fare il confronto

                if (!$date_obj || $date_obj->format('Y-m-d') !== $itemDate) {
                    $fieldErrors['new_material_date'] = "Formato data non valido (YYYY-MM-DD).";
                } elseif ($date_obj > $today) {
                    $fieldErrors['new_material_date'] = "La data non può essere futura.";
                } else {
                    $dateToSave = $itemDate;
                }
            }
            // Per QUANTITA:
            if ($itemQuantity < 1) { 
                $fieldErrors['material_quantity'] = "La quantità deve essere un numero intero maggiore di zero.";
            }
            // Per COSTO: 
            if ($itemCost < 0 || fmod(round($itemCost * 100), 5) != 0) { // Uso round per evitare errori di floating point
                $fieldErrors['material_cost'] = "Il costo deve essere un numero non negativo con centesimi multipli di 5 (es: 5.25, 10.50).";
            }

            if (empty($fieldErrors)) {
                $insertQuery = "INSERT INTO MATERIALI (NOME, DESCRIZIONE, DATA, QUANTITA, COSTO, ID_UTENTE) VALUES (?, ?, ?, ?, ?, ?)";
                $insertStmt = mysqli_prepare($connectionHandle, $insertQuery);

                if ($insertStmt) {
                    
                    mysqli_stmt_bind_param($insertStmt, "sssidi", $itemName, $itemDescription, $dateToSave, $itemQuantity, $itemCost, $currentEntityId);
                    if (mysqli_stmt_execute($insertStmt)) {
                        $newMaterialId = mysqli_insert_id($connectionHandle);
                        $_SESSION['app_feedback_msg'] = "Materiale '" . htmlspecialchars($itemName) . "' inserito con successo!";
                        $_SESSION['feedback_type'] = "success_alert";
                    } else {
                        $_SESSION['app_feedback_msg'] = "Errore durante l'inserimento del materiale: " . mysqli_error($connectionHandle);
                        $_SESSION['feedback_type'] = "error_alert";
                        error_log("Errore INSERT MATERIALI: " . mysqli_error($connectionHandle));
                        sendJsonResponse([
                            'success' => true,
                            'message' => "Materiale '" . htmlspecialchars($itemName) . "' inserito con successo!",
                            'type' => 'success',
                            'newMaterial' => [
                                'ID' => $newMaterialId,
                                'NOME' => $itemName,
                                'DESCRIZIONE' => $itemDescription,
                                'DATA' => $currentDate, // Data nel formato DB (YYYY-MM-DD)
                                'QUANTITA' => $quantita_db,
                                'COSTO' => $costo_db
                            ]
                        ]);
                    }
                    mysqli_stmt_close($insertStmt);
                } else {
                    $_SESSION['app_feedback_msg'] = "Errore nella preparazione della query di inserimento.";
                    $_SESSION['feedback_type'] = "error_alert";
                    error_log("Errore PREPARE INSERT: " . mysqli_error($connectionHandle));
                }
            } else {
                $_SESSION['app_feedback_msg'] = "Correggi gli errori nel modulo di inserimento.";
                $_SESSION['feedback_type'] = "warning_alert";
                $_SESSION['form_errors'] = $fieldErrors;
                $_SESSION['form_data'] = $_POST;
            }
            header('Location: offerta.php');
            exit;

        case 'update':
            $materialId = intval($_POST['item_id'] ?? 0);
            $itemName = trim($_POST['material_name'] ?? ''); 
            $itemDescription = trim($_POST['material_description'] ?? '');
            $itemDate = trim($_POST['material_date'] ?? ''); 
            $itemQuantity = intval($_POST['material_quantity'] ?? 0);
            $itemCost = floatval(str_replace(',', '.', $_POST['material_cost'] ?? 0.0)); // Gestisce virgola come separatore decimale

            $fieldErrors = [];

            if (!filter_var($materialId, FILTER_VALIDATE_INT) || $materialId <= 0) {
                $fieldErrors['item_id'] = "ID materiale non valido.";
            }
            
            if (mb_strlen($itemDescription) > 250) {
                $fieldErrors['material_description'] = "Descrizione: max 250 caratteri.";
            }
            
            if ($itemQuantity <= 0 || !is_numeric($_POST['material_quantity'])) { 
                $fieldErrors['material_quantity'] = "Quantità: numero non negativo.";
            }
            // Usa la stessa logica del caso 'add':
            if ($itemCost < 0 || fmod(round($itemCost * 100), 5) != 0) {
                $fieldErrors['material_cost'] = "Il costo deve essere un numero non negativo con centesimi multipli di 5 (es: 5.25, 10.50).";
            }

            if (empty($fieldErrors)) {
                
                $updateQuery = "UPDATE MATERIALI SET NOME=?, DESCRIZIONE=?, DATA=?, QUANTITA=?, COSTO=? WHERE ID=? AND ID_UTENTE=?";
                $updateStmt = mysqli_prepare($connectionHandle, $updateQuery);

                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, "sssidii", $itemName, $itemDescription, $itemDate, $itemQuantity, $itemCost, $materialId, $currentEntityId);
                    if (mysqli_stmt_execute($updateStmt)) {
                        if (mysqli_stmt_affected_rows($updateStmt) > 0) {
                            
                            $selectUpdatedQuery = "SELECT NOME, DESCRIZIONE, DATA, QUANTITA, COSTO FROM MATERIALI WHERE ID=? AND ID_UTENTE=?";
                            $selectUpdatedStmt = mysqli_prepare($connectionHandle, $selectUpdatedQuery);
                            mysqli_stmt_bind_param($selectUpdatedStmt, "ii", $materialId, $currentEntityId);
                            mysqli_stmt_execute($selectUpdatedStmt);
                            $result = mysqli_stmt_get_result($selectUpdatedStmt);
                            $updatedData = mysqli_fetch_assoc($result);
                            mysqli_stmt_close($selectUpdatedStmt);

                            sendJsonResponse([
                                'success' => true,
                                'message' => "Materiale aggiornato con successo!",
                                'type' => 'success',
                                'updatedData' => $updatedData // Rimando i dati aggiornati
                            ]);
                        } else {
                            sendJsonResponse([
                                'success' => false,
                                'message' => "Nessun materiale aggiornato o nessun cambiamento rilevato. Assicurati che l'ID sia corretto e tu sia autorizzato.",
                                'type' => 'info'
                            ]);
                        }
                    } else {
                        error_log("Errore UPDATE MATERIALI: " . mysqli_error($connectionHandle));
                        sendJsonResponse(['success' => false, 'message' => "Errore durante l'aggiornamento del materiale: " . mysqli_error($connectionHandle), 'type' => 'error']);
                    }
                    mysqli_stmt_close($updateStmt);
                } else {
                    error_log("Errore PREPARE UPDATE: " . mysqli_error($connectionHandle));
                    sendJsonResponse(['success' => false, 'message' => "Errore nella preparazione della query di aggiornamento.", 'type' => 'error']);
                }
            } else {
                sendJsonResponse([
                    'success' => false,
                    'message' => "Correggi gli errori nel modulo di modifica.",
                    'type' => 'warning',
                    'errors' => $fieldErrors // Rimanda gli errori specifici
                ]);
            }
            exit; // esci dopo aver mandato il JSON

        case 'delete':
            $materialIdToDelete = intval($_POST['item_id'] ?? 0);

            if (!filter_var($materialIdToDelete, FILTER_VALIDATE_INT) || $materialIdToDelete <= 0) {
                sendJsonResponse(['success' => false, 'message' => "ID del materiale da eliminare non valido.", 'type' => 'error']);
            } else {
                $deleteQuery = "DELETE FROM MATERIALI WHERE ID = ? AND ID_UTENTE = ?";
                $deleteStmt = mysqli_prepare($connectionHandle, $deleteQuery);

                if ($deleteStmt) {
                    mysqli_stmt_bind_param($deleteStmt, "ii", $materialIdToDelete, $currentEntityId);
                    if (mysqli_stmt_execute($deleteStmt)) {
                        if (mysqli_stmt_affected_rows($deleteStmt) > 0) {
                            sendJsonResponse(['success' => true, 'message' => "Materiale rimosso con successo!", 'type' => 'success']);
                        } else {
                            sendJsonResponse(['success' => false, 'message' => "Nessun materiale eliminato. Potrebbe non esistere o non essere di tua proprietà.", 'type' => 'info']);
                        }
                    } else {
                        error_log("Errore DELETE MATERIALI: " . mysqli_error($connectionHandle));
                        sendJsonResponse(['success' => false, 'message' => "Errore durante l'eliminazione del materiale: " . mysqli_error($connectionHandle), 'type' => 'error']);
                    }
                    mysqli_stmt_close($deleteStmt);
                } else {
                    error_log("Errore PREPARE DELETE: " . mysqli_error($connectionHandle));
                    sendJsonResponse(['success' => false, 'message' => "Errore nella preparazione della query di eliminazione.", 'type' => 'error']);
                }
            }
            exit; // esci dopo aver mandato il JSON

        default:
            // Questo è per richieste POST inaspettate. Per AJAX, ritorna JSON; for accesso diretto, reinderizza a offerta.php .
            $message = "Azione non riconosciuta.";
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                sendJsonResponse(['success' => false, 'message' => $message, 'type' => 'warning']);
            } else {
                $_SESSION['app_feedback_msg'] = $message;
                $_SESSION['feedback_type'] = "warning_alert";
                header('Location: offerta.php');
            }
            exit;
            
    }
} else {
    // Se si arriva a questa pagina senza accesso tramite POST
    $message = "Accesso non autorizzato alla gestione materiali.";
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(['success' => false, 'message' => $message, 'type' => 'error']);
    } else {
        $_SESSION['app_feedback_msg'] = $message;
        $_SESSION['feedback_type'] = "error_alert";
        header('Location: offerta.php');
    }
    exit;
}

// Chiudi la connessione al database (solo se non si è già usciti)
if (isset($connectionHandle) && $connectionHandle) {
    mysqli_close($connectionHandle);
}
?>