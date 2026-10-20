<?php

// Inizia la sessione se non è già attiva
if (session_status() === PHP_SESSION_DISABLED) {

    die("Le sessioni sono disabilitate. Il sito non può funzionare correttamente.");
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connessione al db 
$db_host = 'localhost';
$db_user = 'modificatore';
$db_pass = 'Str0ng#Admin9';
$db_name = 'eco_scambio';

// Feedback globale per l'utente
$feedbackMessage = '';
$feedbackType = ''; // Può essere 'success', 'error'

// Specifici per gli errori filtri
$startDateErrorMessage = '';
$endDateErrorMessage = '';
$dateValidationErrorOccurred = false; // Flag per identificare un'autenticazione fallita

// Tentativo di stabilire una connessione al db
try {
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

    if (mysqli_connect_errno()) {
        throw new Exception("Connection to database failed: " . $conn->connect_error);
    }
    mysqli_set_charset($conn, "utf8mb4");

} catch (Exception $e) {
    //messaggio tecnico per il debugging
    error_log("Critical error in domanda.php (DB connection): " . $e->getMessage());

    // Messaggio per l'utente, più generico
    $feedbackMessage = "Si è verificato un problema interno. Riprova più tardi.";
    $feedbackType = "error";

}

// Verifico che l'utente sia loggato
if (!isset($_SESSION['user'], $_SESSION['pwd'], $_SESSION['id'], $_SESSION['user_type'])) {
    $_SESSION['login_error'] = "Attenzione! Questa pagina è riservata agli artigiani registrati. Inserisci le credenziali prima di procedere all’acquisto";
    header("Location: login.php");
    exit;
}

// Prendo i dettagli dell'utente dalle variabili di sessione
$currentUserName = htmlspecialchars($_SESSION['user'] ?? 'Utente Sconosciuto');
$currentUserType = $_SESSION['user_type'] ?? null;
$currentUserId = $_SESSION['id'] ?? null; 

// Se non è arigiano, reinderizzo e mostro messaggio per l'utente
if ($currentUserType !== 'artigiano') {
    echo ($currentUserType);
    $_SESSION['login_error'] = "Accesso non autorizzato. Questa pagina è riservata agli artigiani.";
    header("Location: login.php");
    exit;
}

$page_title = "Domanda";

// Per le richieste AJAX
// E' per salvare le quantità per AJAX, non per mostrarle su schermo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_selected_items') {
    $incomingSelectedMaterials = json_decode($_POST['selectedMaterialsData'], true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $_SESSION['temporary_cart_data'] = json_encode($incomingSelectedMaterials);
        echo json_encode(['status' => 'success', 'message' => 'Carrello aggiornato.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Dati non validi ricevuti.']);
    }
    if (isset($conn) && $conn) {
        $conn->close();
    }
    exit();
}

// Recupero il credito dell'utente
$availableCredit = 0;
// Verifico che ci sia già una connessione attiva
if (isset($conn) && $conn && empty($feedbackMessage)) { // Interrogo il db se non ci sono stati errori di di connessione
    $query = "SELECT CREDIT FROM DATI_ARTIGIANI WHERE ID_UTENTE = ?";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $currentUserId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $fetchedCredit);
        mysqli_stmt_fetch($stmt); 

        $availableCredit = (float) $fetchedCredit; // Cast a float per assicurarmi che il tipo sia corretto

        mysqli_stmt_close($stmt);
    } else {
        error_log("Error fetching artisan credit (domanda.php): " . $conn->error);
        $feedbackMessage = "Non è stato possibile recuperare il tuo credito disponibile.";
        $feedbackType = "error";
    }
}

// LOGICA PER FILTRARE I MATERIALI
$materiali = []; // inizializzo il vettore materiali
$canFetchMaterials = true; // Per controllare se avrei dovuto fetchare dei materiali

// Recupera i valori dei filtri data dalle GET requests
$dataFiltroInizio = $_GET['startDateFilter'] ?? "";
$dataFiltroFine = $_GET['endDateFilter'] ?? "";

// validazione server-side per i filtri
if (!empty($dataFiltroInizio) || !empty($dataFiltroFine)) {
    $startDateTime = null;
    $endDateTime = null;

    if (!empty($dataFiltroInizio)) {
        // Creo oggetto DateTime per validare il formato
        $startDateTime = DateTime::createFromFormat('Y-m-d', $dataFiltroInizio);
        if (!$startDateTime || $startDateTime->format('Y-m-d') !== $dataFiltroInizio) {
            $startDateErrorMessage = "Data di inizio non valida (formato YYYY-MM-DD).";
            $dateValidationErrorOccurred = true;
        }
    }

    if (!empty($dataFiltroFine)) {
        // Creo oggetto DateTime per validare il formato
        $endDateTime = DateTime::createFromFormat('Y-m-d', $dataFiltroFine);
        if (!$endDateTime || $endDateTime->format('Y-m-d') !== $dataFiltroFine) {
            $endDateErrorMessage = "Data di fine non valida (formato YYYY-MM-DD).";
            $dateValidationErrorOccurred = true;
        }
    }

    // Se entrabi i valori sono presenti e validi, confronto per capire se i valori inseriti sono consistenti
    if (!$dateValidationErrorOccurred && $startDateTime && $endDateTime && $startDateTime > $endDateTime) {
        $endDateErrorMessage = "La data di fine non può essere precedente alla data di inizio.";
        $dateValidationErrorOccurred = true;
    }

    // Se ci sono stati errori, evito di fetchare materiali dal db
    if ($dateValidationErrorOccurred) {
        $feedbackMessage = "Si prega di correggere gli errori nelle date del filtro.";
        $feedbackType = "error";
        $canFetchMaterials = false;
    }
}


// Se i filtri sono validi, procedo a interrogare il db
if (isset($conn) && $conn && empty($feedbackMessage) && $canFetchMaterials) {

    $query = "SELECT ID, NOME, DESCRIZIONE, DATA, QUANTITA, COSTO FROM MATERIALI WHERE QUANTITA > 0";
    $params = [];
    $types = "";

    if (!empty($dataFiltroInizio)) {
        $query .= " AND DATA >= ?";
        $params[] = $dataFiltroInizio;
        $types .= "s";
    }
    if (!empty($dataFiltroFine)) {
        $query .= " AND DATA <= ?";
        $params[] = $dataFiltroFine;
        $types .= "s";
    }

    $query .= " ORDER BY NOME"; // Per migliorare la visualizzazione
    if ($stmt = mysqli_prepare($conn, $query)) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $materiali[] = $row;
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Error preparing material query (domanda.php): " . $conn->error);
        $feedbackMessage = "Non è stato possibile recuperare i materiali disponibili.";
        $feedbackType = "error";
    }
}


// faccio un pre-fill del form con i valori precedentemente inseriti
$initialSelectedItems = [];
if (isset($_SESSION['temporary_cart_data'])) {
    $decodedData = json_decode($_SESSION['temporary_cart_data'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedData)) {
        $initialSelectedItems = $decodedData;
    }
}

// Chiudo la connessione al db
if (isset($conn) && $conn) {
    $conn->close();
}

$page_title = "Acquisto Materiali";
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="author" content="Alessia Serra">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../css/stile.css">
    <link rel="icon" type="image/jpeg" href="../img/icona.png">
    <script>
        const artisanCredit = parseFloat("<?= htmlspecialchars(number_format($availableCredit, 2, '.', '')); ?>");
        // passo i messaggi di validaz del PHP al JavaScript
        const phpStartDateError = "<?= htmlspecialchars($startDateErrorMessage); ?>";
        const phpEndDateError = "<?= htmlspecialchars($endDateErrorMessage); ?>";
    </script>
    <script defer src="../js/domanda.js"> </script>
    <title><?= $page_title ?></title>
</head>
<body>
<?php include 'header.php'; ?>
<main class="content-area" role="main" aria-label="Contenuto principale della pagina di acquisto materiali">
    <div class="main-content-wrapper">
        <div id="topAppFeedback" class="app-feedback-banner" style="display: none;" role="status" aria-live="polite" aria-atomic="true" aria-label="Messaggi di sistema e feedback"></div>

        <?php if ($feedbackMessage): ?>
            <div id="notificationBanner" class="notification-banner <?= htmlspecialchars($feedbackType); ?>" style="display: block;"
                 role="<?= ($feedbackType === 'error' || $feedbackType === 'warning') ? 'alert' : 'status'; ?>"
                 aria-live="<?= ($feedbackType === 'error' || $feedbackType === 'warning') ? 'assertive' : 'polite'; ?>"
                 aria-atomic="true">
                <?= htmlspecialchars($feedbackMessage); ?>
            </div>
        <?php else: ?>
            <div id="notificationBanner" class="notification-banner" style="display: none;" role="status" aria-live="polite" aria-atomic="true"></div>
        <?php endif; ?>

        <h2 class="section-heading">Acquista Materiali</h2>

        <div class="user-info-display" aria-label="Informazioni utente e credito disponibile">
            <p>Benvenuto, <strong class="highlight-text"><?= $currentUserName; ?></strong>!</p>
            <p>Il tuo credito disponibile: <strong class="highlight-amount">€<?= htmlspecialchars(number_format($availableCredit, 2, ',', '.')); ?></strong></p>
        </div>

        <form id="dateFilterForm" method="GET" action="domanda.php" aria-label="Filtra materiali per data di aggiunta">
            <div class="filter-controls-group">
                <div class="input-field-group">
                    <span class="field-error-feedback" id="startDateFilter-error" aria-live="polite"></span>
                    <label for="startDateFilter" class="field-label">Filtra da Data:</label>
                    <input type="date" id="startDateFilter" name="startDateFilter" class="text-input-field" value="<?= htmlspecialchars($dataFiltroInizio); ?>" aria-describedby="startDateFilter-error">
                </div>
                <div class="input-field-group">
                    <span class="field-error-feedback" id="endDateFilter-error" aria-live="polite"></span>
                    <label for="endDateFilter" class="field-label">Filtra a Data:</label>
                    <input type="date" id="endDateFilter" name="endDateFilter" class="text-input-field" value="<?= htmlspecialchars($dataFiltroFine); ?>" aria-describedby="endDateFilter-error">
                </div>
                <div class="action-button-container">
                    <button type="submit" id="applyDateFilterBtn" class="primary-action-btn">Applica Filtri</button>
                    <button type="button" id="clearDateFilterBtn" class="secondary-action-btn" onclick="window.location.href='domanda.php'" aria-label="Rimuovi tutti i filtri di data e mostra tutti i materiali disponibili">Rimuovi Filtri</button>
                </div>
            </div>
        </form>

        <div class="responsive-table-wrapper">
            <form id="purchaseForm" method="post" action="conferma.php" aria-label="Modulo per la selezione e l'acquisto dei materiali">
                <table class="data-display-table" role="table" aria-label="Tabella dei Materiali Disponibili">
                    <thead>
                        <tr>
                            <th scope="col">Nome Materiale</th>
                            <th scope="col">Dettagli</th>
                            <th scope="col">Data Aggiunta</th>
                            <th scope="col">Quantità Disponibile</th>
                            <th scope="col">Costo Unitario (€)</th>
                            <th scope="col">Quantità Desiderata</th>
                            <th scope="col">Costo Parziale (€)</th>
                        </tr>
                    </thead>
                    <tbody id="materialTableBody">
                        <?php if (empty($materiali)): ?>
                            <tr>
                                <td  class="empty-table-message" style='text-align: center' colspan="7">Nessun materiale disponibile per l'acquisto con i filtri selezionati.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($materiali as $mat):
                                $materialId = htmlspecialchars($mat['ID']);
                                $initialQuantity = $initialSelectedItems[$materialId] ?? 0; // Pre-fill con i dati della sessione
                                // La quantità non puo eccedere la quantita massima offerta
                                $initialQuantity = min($initialQuantity, $mat['QUANTITA']);
                                $initialQuantity = max($initialQuantity, 0); // Non dev'essere nergativo

                                $partialCost = $initialQuantity * $mat['COSTO'];
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($mat['NOME']) ?></td>
                                    <td><?= htmlspecialchars($mat['DESCRIZIONE']) ?></td>
                                    <td><?= htmlspecialchars($mat['DATA']) ?></td>
                                    <td><?= htmlspecialchars($mat['QUANTITA']) ?></td>
                                    <td><?= number_format($mat['COSTO'], 2, ',', '.') ?></td>
                                    <td>
                                        <input type="number" name="quantita[<?= $materialId ?>]"
                                               min="0" max="<?= htmlspecialchars($mat['QUANTITA']) ?>"
                                               value="<?= htmlspecialchars($initialQuantity) ?>"
                                               data-material-id="<?= $materialId ?>"
                                               data-unit-cost="<?= htmlspecialchars($mat['COSTO']) ?>"
                                               class="quantity-input"
                                               aria-label="Quantità desiderata per <?= htmlspecialchars($mat['NOME']) ?>">
                                    </td>
                                    <td><span class="partial-cost" data-material-id="<?= $materialId ?>" aria-live="polite" aria-label="Costo parziale calcolato per il materiale <?= htmlspecialchars($mat['NOME']) ?>">€<?= number_format($partialCost, 2, ',', '.') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <div class="purchase-summary">
            <p >Costo Totale Selezionato: <strong id="totalCostDisplay" aria-live="polite" aria-atomic="true">€0,00</strong></p>
        </div>

        <div class="btns">
            <button type="button" id="cancelSelectionBtn" class="secondary-action-btn">Annulla Tutti</button>
            <button type="submit" form="purchaseForm" id="confirmPurchaseBtn" class="primary-action-btn" disabled aria-disabled="true">Procedi all'Acquisto</button>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
</body>
</html>