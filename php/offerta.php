<?php
// Inizio una sessione, se non è gia iniziata una
if (session_status() === PHP_SESSION_DISABLED) {
    die("Le sessioni sono disabilitate. Il sito non può funzionare correttamente.");
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//  LOGICA PER GESTIRE I DATI E GLI ERRORI DEL FORM DA SESSIONE 

// Inizializza variabili vuote per i dati del form e gli errori
$formData = [];
$formErrors = [];
$appFeedbackMessage = '';
$appFeedbackType = '';

// Controlla se ci sono messaggi di feedback globali dalla sessione (per le operazioni non-AJAX)
if (isset($_SESSION['app_feedback_msg']) && !empty($_SESSION['app_feedback_msg'])) {
    $appFeedbackMessage = $_SESSION['app_feedback_msg'];
    $appFeedbackType = $_SESSION['feedback_type'] ?? 'info'; // Default a 'info' se non specificato
    // Pulisci le variabili di sessione dopo averle lette
    unset($_SESSION['app_feedback_msg']);
    unset($_SESSION['feedback_type']);
}

// Recupera i dati del form e gli errori specifici dei campi dalla sessione, se presenti
if (isset($_SESSION['form_data'])) {
    $formData = $_SESSION['form_data'];
    unset($_SESSION['form_data']); // Pulisci dalla sessione
}
if (isset($_SESSION['form_errors'])) {
    $formErrors = $_SESSION['form_errors'];
    unset($_SESSION['form_errors']); // Pulisci dalla sessione
}


// Inizializzazione variabili per i messaggi di feedback utente
$db_host = 'localhost';
$db_user = 'modificatore';
$db_pass = 'Str0ng#Admin9'; // Rischio per sicurezza
$db_name = 'eco_scambio';

// Connessione al database
$con = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (mysqli_connect_errno()) {
    error_log("Errore connessione al DBMS (offerta.php): " . mysqli_connect_error());
    $_SESSION['message'] = "Si è verificato un problema tecnico con la connessione al database. Riprova più tardi.";
    $_SESSION['message_type'] = "error";
    header('Location: home.php');
    exit;
}

mysqli_set_charset($con, "utf8mb4");

// Verifica accesso e tipo utente (solo aziende possono accedere)
if (!isset($_SESSION['user'], $_SESSION['pwd'], $_SESSION['id'], $_SESSION['user_type'])) {
    $_SESSION['login_error'] = "Attenzione! Questa pagina è riservata alle aziende registrate. Inserisci le credenziali per poterla visitare.";
    header("Location: login.php");
    exit;
}

// Recupera il nome utente per la visualizzazione sulla pagina, la pwd non serve
$nome_azienda = htmlspecialchars($_SESSION['user'] ?? 'Azienda Non Identificata'); 
$user_type = $_SESSION['user_type'];
$id_azienda = $_SESSION['id']; //Lo userò per le query di interrogazione al db
$flag_registrata = true; // Se raggiungiamo questo punto, la compagnia è registrata

if ($user_type !== 'azienda') {
    $_SESSION['login_error'] = "Attenzione! Questa pagina è riservata alle aziende registrate. Effettua l'accesso come azienda per gestire le offerte.";
    header("Location: login.php");
    exit;
}


// Caricamento iniziale dei materiali avuti dalla compagnia
$lista_materiali = [];
    
    $query = "SELECT ID, NOME, DESCRIZIONE, DATA, QUANTITA, COSTO FROM MATERIALI WHERE ID_UTENTE = ? ORDER BY DATA DESC, ID DESC";
    $stmt = mysqli_prepare($con, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $id_azienda);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            // Formatto le date
            if (!empty($row['DATA'])) {
                $oggettoData = DateTime::createFromFormat('Y-m-d', $row['DATA']);
                $row['DATA_VISUALIZZAZIONE'] = $oggettoData ? $oggettoData->format('d/m/Y') : $row['DATA'];
                $row['RAW_DATE'] = $row['DATA']; 
            } else {
                $row['DATA_VISUALIZZAZIONE'] = '';
                $row['RAW_DATE'] = '';
            }
            $lista_materiali[] = $row;
        }
        mysqli_free_result($result); // Libera la memoria del set di risultati

    } else {
        error_log("Fallimento esecuzione query materiali iniziali: " . mysqli_error($con));
        $appFeedbackMessage = "Non è stato possibile caricare i tuoi materiali. Ricarica la pagina.";

        $appFeedbackType = "error";
    }
    mysqli_stmt_close($stmt);// Chiude il prepared statement
} else {
    error_log("Fallimento preparazione query materiali iniziali: " . mysqli_error($con));
    $appFeedbackMessage   = "Problema interno del server durante il recupero dei materiali.";
    $appFeedbackType = "error";    }


$titolo_pagina = "Gestione Offerte Materiali";
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($titolo_pagina) ?></title>
    <meta name="author" content="Alessia Serra">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../css/stile.css">
    <link rel="icon" type="image/jpeg" href="/PROGETTO_ESAME/img/icona.png">
    <script defer src="../js/gestisci_materiali.js"></script>
</head>
<body>
<?php include 'header.php'; ?>
    <main class="content-area">
        <div class="main-content-wrapper">
            <h1 class="titolo-materiali">Gestione materiali offerti: <?= $nome_azienda; ?></h1>

            <?php if (!empty($appFeedbackMessage)): ?>
                <p class="feedback-message <?= htmlspecialchars($appFeedbackType); ?>" role="alert">
                    <?= htmlspecialchars($appFeedbackMessage); ?>
                </p>
            <?php endif; ?>

            <div id="topAppFeedback" class="app-feedback-banner"></div>


                <section class="new-offer-form-section card-style">
                    <h2 class="sub-section-heading">Nuova Offerta Materiale</h2>
                    <form id="addNewMaterialForm" class="data-entry-form" method="post" action="material_actions.php">
                        <input type="hidden" name="action_type" value="insert">
                        <div class="input-field-group">
                            <label for="newMaterialName" class="field-label">Nome Materiale:</label>
                            <input type="text" id="newMaterialName" name="new_material_name" class="text-input-field" minlength="10" maxlength="40" required pattern="[A-Za-z0-9\s]*" title="Il nome può contenere solo lettere, numeri e spazi (min 10, max 40).">
                            <span class="field-error-feedback" id="newMaterialName-error"></span>
                        </div>
                        <div class="input-field-group">
                            <label for="newMaterialDescription" class="field-label">Descrizione:</label>
                            <textarea id="newMaterialDescription" name="new_material_description" class="textarea-input-field" maxlength="250" rows="4"></textarea>
                            <span class="field-error-feedback" id="newMaterialDescription-error"></span>
                        </div>
                        <div class="input-field-group">
                            <label for="newMaterialDate" class="field-label">Data Pubblicazione:</label>
                            <input type="date" id="newMaterialDate" name="new_material_date" class="text-input-field" required>
                            <span class="field-error-feedback" id="newMaterialDate-error"></span>
                        </div>
                        <div class="input-field-group">
                            <label for="newMaterialQuantity" class="field-label">Quantità Disponibile:</label>
                            <input type="number" id="newMaterialQuantity" name="new_material_quantity" class="text-input-field" value="0" min="1" required>
                            <span class="field-error-feedback" id="newMaterialQuantity-error"></span>
                        </div>
                        <div class="input-field-group">
                            <label for="newMaterialCost" class="field-label">Costo Unitario (€):</label>
                            
                                <input type="number" id="newMaterialCost" name="new_material_cost" class="text-input-field" value="0.00" min="0.00" step="0.05" required>
                                <span class="field-error-feedback" id="newMaterialCost-error"></span>
                            
                        </div>
                        <div class="form-action-buttons-wrapper">
                            <button type="submit" class="primary-action-btn" id="submitNewMaterialBtn">Aggiungi Offerta</button>
                            <button type="reset" class="secondary-action-btn" id="clearNewMaterialFormBtn">Pulisci Campi</button>
                        </div>
                    </form>
                </section>

                    <?php if ($flag_registrata): ?>
                <section class="materials-list-section card-style">
                    <h2 class="sub-section-heading">Offerte di Materiali Attuali</h2>

                    <div class="filter-controls-group">
                        <div class="input-field-group">
                            <label for="startDateFilter" class="field-label">Data Inizio:</label>
                            <input type="date" id="startDateFilter" class="text-input-field">
                        </div>
                        <div class="input-field-group">
                            <label for="endDateFilter" class="field-label">Data Fine:</label>
                            <input type="date" id="endDateFilter" class="text-input-field">
                        </div>
                        <div class="action-button-container">
                            <button type="button" id="applyDateFilterBtn" class="primary-action-btn">Applica Filtro</button>
                            <button type="button" id="clearDateFilterBtn" class="secondary-action-btn">Reset Filtro</button>
                        </div>

                        <!-- span class="field-error-feedback" id="startDateFilter-error"></span>
                        <span class="field-error-feedback" id="endDateFilter-error"></span -->

                    </div>

                    <div class="responsive-table-wrapper">
                        <table class="data-display-table" id="materialsOfferTable" <?= empty($lista_materiali) ? 'style="display:none;"' : '' ?> >
                            <thead>
                                <tr>
                                    <th>Materiale</th>
                                    <th>Descrizione</th>
                                    <th>Data Offerta</th>
                                    <th>Quantità</th>
                                    <th>Costo Un. (€)</th>
                                    <th>Opzioni</th>
                                </tr>
                            </thead>
                            <tbody id="materialsTableBody">
                                <?php if (!empty($lista_materiali)): ?>
                                    <?php foreach ($lista_materiali as $materialItem): ?>
                                        <tr data-material-entry-id="<?= htmlspecialchars($materialItem['ID']) ?>">
                                            <td class="material-name-cell">
                                                <span class="display-mode-text"><?= htmlspecialchars($materialItem['NOME']) ?></span>
                                                <input type="text" class="edit-mode-input hidden-mode-element" name="materialName" id="materialName-<?= htmlspecialchars($materialItem['ID']) ?>" value="<?= htmlspecialchars($materialItem['NOME']) ?>" minlength="10" maxlength="40" data-original-value="<?= htmlspecialchars($materialItem['NOME']) ?>">
                                                <span class="field-error-feedback" id="materialName-error-<?= htmlspecialchars($materialItem['ID']) ?>"></span>
                                            </td>
                                            <td class="material-description-cell">
                                                <span class="display-mode-text description-text"><?= htmlspecialchars($materialItem['DESCRIZIONE']) ?></span>
                                                <textarea class="edit-mode-input description-textarea hidden-mode-element" name="materialDescription" id="materialDescription-<?= htmlspecialchars($materialItem['ID']) ?>" maxlength="250" rows="4" data-original-value="<?= htmlspecialchars($materialItem['DESCRIZIONE']) ?>"><?= htmlspecialchars($materialItem['DESCRIZIONE']) ?></textarea>
                                                <span class="field-error-feedback" id="materialDescription-error-<?= htmlspecialchars($materialItem['ID']) ?>"></span>
                                            </td>
                                            <td class="material-date-cell">
                                                <span class="display-mode-text"><?= htmlspecialchars($materialItem['DATA_VISUALIZZAZIONE']) ?></span>
                                                <input type="date" class="edit-mode-input hidden-mode-element" name="materialDate" id="materialDate-<?= htmlspecialchars($materialItem['ID']) ?>" value="<?= htmlspecialchars($materialItem['RAW_DATE']) ?>" data-original-value="<?= htmlspecialchars($materialItem['RAW_DATE']) ?>">
                                                <span class="field-error-feedback" id="materialDate-error-<?= htmlspecialchars($materialItem['ID']) ?>"></span>
                                            </td>
                                            <td class="material-quantity-cell">
                                                <span class="display-mode-text"><?= htmlspecialchars($materialItem['QUANTITA']) ?></span>
                                                <div class="edit-mode-input stepper-control-group hidden-mode-element input-field-group">
                                                    <button type="button" class="stepper-btn stepper-plus-btn" data-step-value="1">+</button>
                                                    <input type="number" class="quantity-input-field" name="materialQuantity" id="materialQuantity-<?= htmlspecialchars($materialItem['ID']) ?>" value="<?= htmlspecialchars($materialItem['QUANTITA']) ?>" min="0" data-original-value="<?= htmlspecialchars($materialItem['QUANTITA']) ?>">
                                                    <button type="button" class="stepper-btn stepper-minus-btn" data-step-value="-1">-</button>
                                                    <span class="field-error-feedback" id="materialQuantity-error-<?= htmlspecialchars($materialItem['ID']) ?>"></span>
                                                </div>
                                            </td>
                                            <td class="material-cost-cell">
                                                <span class="display-mode-text price-text"><?= htmlspecialchars(number_format($materialItem['COSTO'], 2, ',', '')) ?> &euro;</span>
                                                <div class="edit-mode-input cost-control-group hidden-mode-element input-field-group">
                                                    <button type="button" class="stepper-btn stepper-plus-btn" data-step-value="0.05">+</button>
                                                    <input type="number" class="cost-input-field" name="materialCost" id="materialCost-<?= htmlspecialchars($materialItem['ID']) ?>" value="<?= htmlspecialchars(number_format($materialItem['COSTO'], 2, '.', '')) ?>" min="0" step="0.05" data-original-value="<?= htmlspecialchars(number_format($materialItem['COSTO'], 2, '.', '')) ?>">
                                                    <button type="button" class="stepper-btn stepper-minus-btn" data-step-value="-0.05">-</button>
                                                    <span class="field-error-feedback" id="materialCost-error-<?= htmlspecialchars($materialItem['ID']) ?>"></span> </div>
                                            </td>
                                            <form method="post" id="update" action="material_actions.php" class="inline-form-delete">
                                                <td class="table-actions-cell">
                                                    <div class="view-actions-container">
                                                        <button type="button" class="action-icon-btn edit-item-btn" title="Modifica Articolo">
                                                            <img src="../img/btn_modifica3.png" alt="Modifica">
                                                        </button>
                                                        
                                                            <input type="hidden" name="action_type" value="delete">
                                                            <input type="hidden" name="item_id" value="<?= htmlspecialchars($materialItem['ID']) ?>">
                                                            <button type="submit" id="submit_update" class="action-icon-btn delete-item-btn" title="Rimuovi Articolo" onclick="return confirm('Sei sicuro di voler eliminare questo materiale? Questa azione è irreversibile.');">
                                                                <img src="../img/btn_elimina3.png" alt="Rimuovi">
                                                            </button>
                                                        </form>
                                                    </div>
                                                    <div class="edit-actions-container hidden-mode-element">
                                                        <button type="button" id="save" class="action-icon-btn save-item-btn" title="Salva Modifiche">
                                                            <img src="../img/btn_salva3.png" alt="Salva">
                                                        </button>
                                                        <button type="button" id="discard" class="action-icon-btn cancel-edit-btn" title="Annulla Modifiche">
                                                            <img src="../img/btn_annulla.png" alt="Annulla">
                                                        </button>
                                                    </div>
                                                </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <p class="empty-table-message" id="noMaterialsPlaceholder" <?= empty($lista_materiali) ? '' : 'style="display:none;"' ?>>
                            Non hai ancora registrato alcuna offerta di materiale. Utilizza il modulo seguente per aggiungerne una.
                        </p>
                    </div>
                </section>

            <?php else: ?>
                <p class="app-status-message error-message-banner visible">
                    Accesso negato: Questa sezione è riservata alle aziende registrate. Effettua il login per procedere.
                </p>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>