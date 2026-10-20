<?php
// Inizio una sessione, se non c'è n'è gia una attiva
if (session_status() === PHP_SESSION_DISABLED) {
    die("Le sessioni sono disabilitate. Il sito non può funzionare correttamente.");
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// connessione al database
$db_host = 'localhost';
$db_user = 'modificatore';
$db_pass = 'Str0ng#Admin9';
$db_name = 'eco_scambio';

$page_title = "Esito Acquisto";
$finalMessage = "Si è verificato un errore inaspettato durante la conclusione dell'acquisto.";
$messageType = "error";

// Verifico che l'utente sia loggato...
if (!isset($_SESSION['user'], $_SESSION['pwd'], $_SESSION['id'], $_SESSION['user_type'])) {
    $_SESSION['login_error'] = "Devi effettuare il login per accedere a questa pagina.";
    header("Location: login.php");
    exit;
}

$currentUserType = $_SESSION['user_type'] ?? null;
$currentUserId = $_SESSION['id'] ?? null;
$currentUserName = htmlspecialchars($_SESSION['user'] ?? 'Utente Sconosciuto');

// ... e che sia autorizzato
if ($currentUserType !== 'artigiano') {
    $_SESSION['login_error'] = "Accesso non autorizzato. Questa pagina è riservata agli artigiani.";
    header("Location: login.php");
    exit;
}

// Provo ad accedere al db e a proseguire con la logica solo se è stata ricevuta una richiesta POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'execute_purchase' && isset($_POST['purchase_items'])) {

    $conn = null; 

    try {
        $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        if (mysqli_connect_errno()) {
            throw new Exception("Connection to database failed: " . mysqli_connect_error());
        }
        mysqli_set_charset($conn, "utf8mb4");

        // Inizio la transazione 
        mysqli_begin_transaction($conn);

        // 1.Controllo che il credito degll'artigiano sia sufficiente
        $availableCredit = 0;
        $queryCredit = "SELECT CREDIT FROM DATI_ARTIGIANI WHERE ID_UTENTE = ? FOR UPDATE"; //blocco la riga
        $stmtCredit = mysqli_prepare($conn, $queryCredit);
        if (!$stmtCredit) {
            throw new Exception("Errore nella preparazione query credito: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtCredit, "i", $currentUserId);
        mysqli_stmt_execute($stmtCredit);
        mysqli_stmt_bind_result($stmtCredit, $fetchedCredit);
        mysqli_stmt_fetch($stmtCredit);
        $availableCredit = (float) $fetchedCredit;
        mysqli_stmt_close($stmtCredit);

        $purchaseItems = $_POST['purchase_items'];
        $totalPurchaseCost = 0;
        $materialsToUpdate = []; // Per salvare l'id del materiale e la nuova quantità

        // 2. Valido le quantità dei materiali e calcolo il costo tot dai dati del db 
        $materialIds = array_keys(array_filter($purchaseItems, function($qty) { return $qty > 0; }));

        if (empty($materialIds)) {
            throw new Exception("Nessun materiale valido selezionato per l'acquisto.");
        }

        $placeholders = implode(',', array_fill(0, count($materialIds), '?'));
        $queryMaterials = "SELECT ID, NOME, COSTO, QUANTITA FROM MATERIALI WHERE ID IN ($placeholders) FOR UPDATE"; 
        $stmtMaterials = mysqli_prepare($conn, $queryMaterials);
        if (!$stmtMaterials) {
            throw new Exception("Errore nella preparazione query materiali: " . mysqli_error($conn));
        }
        $types = str_repeat('i', count($materialIds));
        mysqli_stmt_bind_param($stmtMaterials, $types, ...$materialIds);
        mysqli_stmt_execute($stmtMaterials);
        $resultMaterials = mysqli_stmt_get_result($stmtMaterials);

        $dbMaterials = [];
        while ($row = mysqli_fetch_assoc($resultMaterials)) {
            $dbMaterials[$row['ID']] = $row;
        }
        mysqli_stmt_close($stmtMaterials);

        foreach ($purchaseItems as $materialId => $requestedQuantity) {
            $materialId = (int) $materialId;
            $requestedQuantity = (int) $requestedQuantity;

            if ($requestedQuantity <= 0) {
                continue;
            }

            if (!isset($dbMaterials[$materialId])) {
                throw new Exception("Materiale ID " . $materialId . " non trovato o non disponibile.");
            }

            $dbMaterial = $dbMaterials[$materialId];
            if ($requestedQuantity > $dbMaterial['QUANTITA']) {
                throw new Exception("Quantità insufficiente per " . htmlspecialchars($dbMaterial['NOME']) . ". Richiesto: " . $requestedQuantity . ", Disponibile: " . $dbMaterial['QUANTITA'] . ".");
            }

            $itemCost = $requestedQuantity * $dbMaterial['COSTO'];
            $totalPurchaseCost += $itemCost;
            $materialsToUpdate[$materialId] = $dbMaterial['QUANTITA'] - $requestedQuantity;
        }

        if ($totalPurchaseCost > $availableCredit) {
            throw new Exception("Credito insufficiente. Costo totale: €" . number_format($totalPurchaseCost, 2, ',', '.') . ", Credito: €" . number_format($availableCredit, 2, ',', '.') . ".");
        }

        // 3. Aggiorno il credito dell'artigiano
        $newCredit = $availableCredit - $totalPurchaseCost;
        $queryUpdateCredit = "UPDATE DATI_ARTIGIANI SET CREDIT = ? WHERE ID_UTENTE = ?";
        $stmtUpdateCredit = mysqli_prepare($conn, $queryUpdateCredit);
        if (!$stmtUpdateCredit) {
            throw new Exception("Errore nella preparazione update credito: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmtUpdateCredit, "di", $newCredit, $currentUserId); 
        if (!mysqli_stmt_execute($stmtUpdateCredit)) {
            throw new Exception("Errore nell'aggiornamento del credito: " . mysqli_error($conn));
        }
        mysqli_stmt_close($stmtUpdateCredit);

        // 4. Aggiorno le quantità del materiale
        $queryUpdateMaterial = "UPDATE MATERIALI SET QUANTITA = ? WHERE ID = ?";
        $stmtUpdateMaterial = mysqli_prepare($conn, $queryUpdateMaterial);
        if (!$stmtUpdateMaterial) {
            throw new Exception("Errore nella preparazione update quantità materiali: " . mysqli_error($conn));
        }
        foreach ($materialsToUpdate as $materialId => $newQuantity) {
            mysqli_stmt_bind_param($stmtUpdateMaterial, "ii", $newQuantity, $materialId);
            if (!mysqli_stmt_execute($stmtUpdateMaterial)) {
                throw new Exception("Errore nell'aggiornamento quantità per materiale ID " . $materialId . ": " . mysqli_error($conn));
            }
        }
        mysqli_stmt_close($stmtUpdateMaterial);

        // 5. Faccio il commit della transazione
        mysqli_commit($conn);

        // Cancello il carrello temporaneo
        unset($_SESSION['temporary_cart_data']);

        $finalMessage = "Acquisto completato con successo! Il tuo nuovo credito è €" . number_format($newCredit, 2, ',', '.') . ".";
        $messageType = "success";

    } catch (Exception $e) {
        // Rollback in caso di eccezione
        if ($conn) {
            mysqli_rollback($conn);
        }
        error_log("Purchase transaction failed for user " . $currentUserId . ": " . $e->getMessage());
        $finalMessage = "Errore durante l'acquisto: " . $e->getMessage();
        $messageType = "error";
    } finally {
        if ($conn) {
            $conn->close();
        }
    }
} else {
    $finalMessage = "Richiesta non valida per la conclusione dell'acquisto.";
    $messageType = "warning";
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="author" content="Alessia Serra">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../css/stile.css">
    <link rel="icon" type="image/jpeg" href="../img/icona.png">
    <title><?= htmlspecialchars($page_title) ?></title>
</head>
<body>
<?php include 'header.php'; ?>
<main class="content-area" role="main" aria-labelledby="purchaseOutcomeHeading">
    <div class="main-content-wrapper-fine">
        <div id="topAppFeedback" class="app-feedback-banner" style="display: none;" role="status" aria-live="polite" aria-atomic="true" aria-label="Messaggi di sistema e feedback"></div>

        

        <h2 class="section-heading" id="purchaseOutcomeHeading">Esito dell'Acquisto</h2>

        <div id="notificationBanner" class="success" <?= htmlspecialchars($messageType); ?> style="display: block;"
             role="<?= ($messageType === 'error' || $messageType === 'warning') ? 'alert' : 'status'; ?>"
             aria-live="<?= ($messageType === 'error' || $messageType === 'warning') ? 'assertive' : 'polite'; ?>"
             aria-atomic="true">
            <?= htmlspecialchars($finalMessage); ?>
        </div>

        <div class="btns">
            <?php if ($messageType === 'success'): ?>
                <a href="domanda.php" class="secondary-action-btn" aria-label="Torna alla pagina di selezione materiali per nuovi acquisti">Torna alla selezione materiali</a>
            <?php else: ?>
                <a href="domanda.php" class="secondary-action-btn" aria-label="Torna alla pagina di selezione materiali per riprovare">Torna a Seleziona Materiali</a>
            <?php endif; ?>
        </div>
    </div>
    <div id="bottomAppFeedback" class="app-feedback-banner" style="display: none;" role="status" aria-live="polite" aria-atomic="true" aria-label="Messaggi di sistema e feedback secondari"></div>
</main>
<?php include 'footer.php'; ?>
</body>
</html>