<?php

// Inizializza sessione se non già attiva
if (session_status() === PHP_SESSION_DISABLED) {
    die("Le sessioni sono disabilitate. Il sito non può funzionare correttamente.");
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurazione alla connessione al db
$db_host = 'localhost';
$db_user = 'lettore'; // Uso l'utente con privilegi limitati
$db_pass = 'P@ssw0rd!';
$db_name = 'eco_scambio';

// Variabili globali per i feedback da dare all'utente
$feedbackMessage = '';
$feedbackType = ''; // Può essere 'success', 'error', 'warning'

// Errori specifici per le date
$startDateErrorMessage = '';
$endDateErrorMessage = '';
$dateValidationErrorOccurred = false; // Flag per indicare validazione fallita

// Provo a stabilire una connessione col db
try {
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

    if (mysqli_connect_errno()) {
        throw new Exception("Connection to database failed: " . $conn->connect_error);
    }
    mysqli_set_charset($conn, "utf8mb4");

} catch (Exception $e) {
    error_log("Critical error in lista.php (DB connection): " . $e->getMessage());
    $feedbackMessage = "Si è verificato un problema interno. Riprova più tardi.";
    $feedbackType = "error";
}

// Autenticazione dello user e recupero delle sue informazioni
$isArtigiano = false;
$currentUserName = 'Ospite';

if (isset($_SESSION['user'], $_SESSION['user_type']) && !empty($_SESSION['user'])) {
    $currentUserName = htmlspecialchars($_SESSION['user']);
    if ($_SESSION['user_type'] === 'artigiano') {
        $isArtigiano = true;
    }
}

// Filtro e valido data
$materiali = [];
$canFetchMaterials = true; // Per controllare se dovrei avere dei materiali fetchati

// prendo le date con una GET
$dataFiltroInizio = $_GET['startDateFilter'] ?? '';
$dataFiltroFine = $_GET['endDateFilter'] ?? '';

// validazione server-side per le date
if (!empty($dataFiltroInizio) || !empty($dataFiltroFine)) {
    $startDateTime = null;
    $endDateTime = null;

    if (!empty($dataFiltroInizio)) {
        // Creo un oggetto DateTime per validare il format
        $startDateTime = DateTime::createFromFormat('Y-m-d', $dataFiltroInizio);
        if (!$startDateTime || $startDateTime->format('Y-m-d') !== $dataFiltroInizio) {
            $startDateErrorMessage = "Data di inizio non valida (formato YYYY-MM-DD).";
            $dateValidationErrorOccurred = true;
        }
    }

    if (!empty($dataFiltroFine)) {
        // Creo un oggetto DateTime pre validare il formato
        $endDateTime = DateTime::createFromFormat('Y-m-d', $dataFiltroFine);
        if (!$endDateTime || $endDateTime->format('Y-m-d') !== $dataFiltroFine) {
            $endDateErrorMessage = "Data di fine non valida (formato YYYY-MM-DD).";
            $dateValidationErrorOccurred = true;
        }
    }

    // Compara se le date sono presenti, valide e consistenti tra loro
    if (!$dateValidationErrorOccurred && $startDateTime && $endDateTime && $startDateTime > $endDateTime) {
        $endDateErrorMessage = "La data di fine non può essere precedente alla data di inizio.";
        $dateValidationErrorOccurred = true;
    }

    // Se ci sono errori disponi un messaggio per l'utente, e non fetchare i materiali
    if ($dateValidationErrorOccurred) {
        $feedbackMessage = "Si prega di correggere gli errori nelle date del filtro.";
        $feedbackType = "error";
        $canFetchMaterials = false;
    }
}


// Se c'è una connessione, no errori critici e i filtri data sono validi
if (isset($conn) && $conn && empty($feedbackMessage) && $canFetchMaterials) {
    $query = "SELECT ID, NOME, DESCRIZIONE, DATA, QUANTITA, COSTO FROM MATERIALI WHERE QUANTITA > 0";
    $params = [];
    $types = "";

    // Applica filtro data inizio
    if (!empty($dataFiltroInizio)) {
        $query .= " AND DATA >= ?";
        $params[] = $dataFiltroInizio;
        $types .= "s";
    }
    // Applica filtro data fine
    if (!empty($dataFiltroFine)) {
        $query .= " AND DATA <= ?";
        $params[] = $dataFiltroFine;
        $types .= "s";
    }

    $query .= " ORDER BY DATA DESC, NOME ASC"; // Ordina per materiali inseriti più tardi, e a partià per nome in ordina alfabetico

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
        error_log("Error preparing material query (lista.php): " . $conn->error);
        $feedbackMessage = "Non è stato possibile recuperare i materiali.";
        $feedbackType = "error";
    }
}

// Chiudi connessione al db
if (isset($conn) && $conn) {
    $conn->close();
}

$page_title = "Lista Materiali";
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="../css/stile.css">
    <link rel="icon" type="image/jpeg" href="../img/icona.png">
    <script>
        
        const phpStartDateError = "<?= htmlspecialchars($startDateErrorMessage); ?>";
        const phpEndDateError = "<?= htmlspecialchars($endDateErrorMessage); ?>";
    </script>
    <script defer src="../js/lista.js"></script>
</head>
<body>
<?php include 'header.php'; ?>
<main class="content-area" role="main" aria-labelledby="mainContentHeading">
    <div class="main-content-wrapper">
        <?php if ($feedbackMessage): ?>
            <div id="topAppFeedback" class="notification-banner <?= htmlspecialchars($feedbackType); ?>" style="display: block;" role="status" aria-live="polite" aria-label="Notifiche di sistema">
                <?= htmlspecialchars($feedbackMessage); ?>
            </div>
        <?php else: ?>
            <div id="topAppFeedback" class="notification-banner" style="display: none;" role="status" aria-live="polite"></div>
        <?php endif; ?>

        <h2 class="section-heading" id="materialsListHeading">Materiali Disponibili</h2>

        <div class="user-info-display">
            <p>Benvenuto, <strong class="highlight-text"><?= $currentUserName; ?></strong>!</p>
            <?php if ($isArtigiano): ?>
                <p>Sei loggato come artigiano e puoi vedere quantità e costi.</p>
            <?php else: ?>
                <p>Effettua il <a href="login.php" aria-label="Accedi per visualizzare quantità e costi dei materiali">login</a> come artigiano per vedere quantità e costi.</p>
            <?php endif; ?>
        </div>

        <form id="dateFilterForm" method="GET" action="lista.php" aria-labelledby="filterHeading">
            <div class="filter-controls-group" role="group" aria-label="Controlli di filtro per data">
                <div class="input-field-group">
                    <span class="field-error-feedback" id="startDateFilter-error" aria-live="polite"></span>
                    <label for="startDateFilter" class="field-label">Filtra da Data:</label>
                    <input type="date" id="startDateFilter" name="startDateFilter" class="text-input-field" value="<?= htmlspecialchars($dataFiltroInizio); ?>" aria-label="Data di inizio per il filtro dei materiali" aria-describedby="startDateFilter-error">
                </div>
                <div class="input-field-group">
                    <span class="field-error-feedback" id="endDateFilter-error" aria-live="polite"></span>
                    <label for="endDateFilter" class="field-label">Filtra a Data:</label>
                    <input type="date" id="endDateFilter" name="endDateFilter" class="text-input-field" value="<?= htmlspecialchars($dataFiltroFine); ?>" aria-label="Data di fine per il filtro dei materiali" aria-describedby="endDateFilter-error">
                </div>
                <div class="action-button-container">
                    <button type="submit" id="applyDateFilterBtn" class="primary-action-btn" aria-label="Applica i filtri di data">Applica Filtri</button>
                    <button type="button" id="clearDateFilterBtn" class="secondary-action-btn" onclick="window.location.href='lista.php'" aria-label="Rimuovi tutti i filtri di data">Rimuovi Filtri</button>
                </div>
            </div>
        </form>

        <div class="responsive-table-wrapper">
            <table class="data-display-table" role="table" aria-labelledby="materialsListHeading" aria-live="polite">
                <caption class="sr-only">Tabella dei materiali disponibili con filtri applicati</caption>
                <thead>
                    <tr>
                        <th scope="col">Nome Materiale</th>
                        <th scope="col">Descrizione</th>
                        <th scope="col">Data Inserimento</th>
                        <?php if ($isArtigiano): ?>
                            <th scope="col">Quantità Disponibile</th>
                            <th scope="col">Costo Unitario (€)</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($materiali)): ?>
                        <tr>
                            <td class="empty-table-message" style='text-align: center' colspan="<?= $isArtigiano ? '5' : '3' ?>">Nessun materiale disponibile o corrispondente ai filtri.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($materiali as $mat): ?>
                            <tr>
                                <td><?= htmlspecialchars($mat['NOME']) ?></td>
                                <td><?= htmlspecialchars($mat['DESCRIZIONE']) ?></td>
                                <td><?= htmlspecialchars($mat['DATA']) ?></td>
                                <?php if ($isArtigiano): ?>
                                    <td><?= htmlspecialchars($mat['QUANTITA']) ?></td>
                                    <td><?= number_format($mat['COSTO'], 2, ',', '.') ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php include 'footer.php'; ?>
</body>
</html>