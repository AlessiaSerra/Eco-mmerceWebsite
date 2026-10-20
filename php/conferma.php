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
$feedbackType = ''; // Può essere 'success', 'error', 'warning'

// Tentativo di stabilire una connessione al db
try {
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

    if (mysqli_connect_errno()) {
        throw new Exception("Connection to database failed: " . $conn->connect_error);
    }
    mysqli_set_charset($conn, "utf8mb4");

} catch (Exception $e) {
    error_log("Critical error in conferma.php (DB connection): " . $e->getMessage());
    $feedbackMessage = "Si è verificato un problema interno con il database. Riprova più tardi.";
    $feedbackType = "error";
}

// Verifico che l'utente sia loggato
if (!isset($_SESSION['user'], $_SESSION['pwd'], $_SESSION['id'], $_SESSION['user_type'])) {
    $_SESSION['login_error'] = "Devi effettuare il login per accedere a questa pagina.";
    header("Location: login.php");
    exit;
}

$currentUserName = htmlspecialchars($_SESSION['user'] ?? 'Utente Sconosciuto');
$currentUserType = $_SESSION['user_type'] ?? null;
$currentUserId = $_SESSION['id'] ?? null;

if ($currentUserType !== 'artigiano') {
    $_SESSION['login_error'] = "Accesso non autorizzato. Questa pagina è riservata agli artigiani.";
    header("Location: login.php");
    exit;
}

$page_title = "Riepilogo Acquisto";

$availableCredit = 0;
// Interrogo il DB se non ci sono stati errori
if (isset($conn) && $conn && empty($feedbackMessage)) {
    $query = "SELECT CREDIT FROM DATI_ARTIGIANI WHERE ID_UTENTE = ?";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $currentUserId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $fetchedCredit);
        mysqli_stmt_fetch($stmt);
        $availableCredit = (float) $fetchedCredit;
        mysqli_stmt_close($stmt);
    } else {
        error_log("Error fetching artisan credit (conferma.php): " . $conn->error);
        $feedbackMessage = "Non è stato possibile recuperare il tuo credito disponibile.";
        $feedbackType = "error";
    }
}

$selectedMaterials = [];
$totalCost = 0;
$hasErrors = false;

// Uso $_POST se il form è stato appena sottomesso, altrimenti uso $_SESSION per selezioni vecchie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantita'])) {
    $selectedQuantities = $_POST['quantita'];
    $_SESSION['temporary_cart_data'] = json_encode($selectedQuantities); // Aggiorna la sessione con i dati della POST 
} elseif (isset($_SESSION['temporary_cart_data'])) {
    $decodedData = json_decode($_SESSION['temporary_cart_data'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedData)) {
        $selectedQuantities = $decodedData;
    } else {
        $selectedQuantities = [];
    }
} else {
    $selectedQuantities = [];
}

if (empty($selectedQuantities)) {
    $feedbackMessage = "Nessun materiale selezionato per l'acquisto.";
    $feedbackType = "warning";
    $hasErrors = true;
} else {
    // Prendo i dettagli dei materiali dal database
    // Ricontrollo i costi unitari
    if (isset($conn) && $conn && empty($feedbackMessage)) {
        $materialIds = array_keys(array_filter($selectedQuantities, function($qty) { return $qty > 0; }));

        if (!empty($materialIds)) {
            $placeholders = implode(',', array_fill(0, count($materialIds), '?'));
            $query = "SELECT ID, NOME, COSTO, QUANTITA FROM MATERIALI WHERE ID IN ($placeholders)";
            $stmt = mysqli_prepare($conn, $query);

            if ($stmt) {
                $types = str_repeat('i', count($materialIds)); 
                mysqli_stmt_bind_param($stmt, $types, ...$materialIds);
                mysqli_stmt_execute($stmt);
                $result = $stmt->get_result();

                $dbMaterials = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $dbMaterials[$row['ID']] = $row;
                }
                mysqli_stmt_close($stmt);

                foreach ($selectedQuantities as $materialId => $quantity) {
                    $materialId = (int) $materialId;
                    $quantity = (int) $quantity;

                    if ($quantity <= 0) {
                        continue; // Skippa i materiali con quantità minore di zero o nulla
                    }

                    if (isset($dbMaterials[$materialId])) {
                        $dbMaterial = $dbMaterials[$materialId];
                        // Controlla la quantita disponibile
                        if ($quantity > $dbMaterial['QUANTITA']) {
                            $feedbackMessage = "La quantità richiesta per '" . htmlspecialchars($dbMaterial['NOME']) . "' (" . $quantity . ") supera la disponibilità attuale (" . $dbMaterial['QUANTITA'] . ").";
                            $feedbackType = "error";
                            $hasErrors = true;
                            break; // Se la quantità è invalida esci
                        }

                        $cost = $quantity * $dbMaterial['COSTO'];
                        $totalCost += $cost;

                        $selectedMaterials[] = [
                            'ID' => $materialId,
                            'NOME' => htmlspecialchars($dbMaterial['NOME']),
                            'QUANTITA_RICHIESTA' => $quantity,
                            'COSTO_UNITARIO' => $dbMaterial['COSTO'],
                            'COSTO_PARZIALE' => $cost
                        ];
                    } else {
                        // Materiale o ID non valido
                        $feedbackMessage = "Materiale con ID " . htmlspecialchars($materialId) . " non trovato o non disponibile.";
                        $feedbackType = "error";
                        $hasErrors = true;
                        break;
                    }
                }
            } else {
                error_log("Error preparing material details query (conferma.php): " . $conn->error);
                $feedbackMessage = "Errore nel recupero dei dettagli dei materiali.";
                $feedbackType = "error";
                $hasErrors = true;
            }
        } else {
            $feedbackMessage = "Nessun materiale valido selezionato.";
            $feedbackType = "warning";
            $hasErrors = true;
        }
    } else {
        $hasErrors = true; // Errori per connessione DB
    }
}

// Check finale per il credito e presenza di altri errori
$creditInsufficient = false;
if (!$hasErrors && $totalCost > $availableCredit) {
    $feedbackMessage = "Credito insufficiente! Il costo totale (€" . number_format($totalCost, 2, ',', '.') . ") supera il tuo credito disponibile (€" . number_format($availableCredit, 2, ',', '.') . ").";
    $feedbackType = "error";
    $creditInsufficient = true;
    $hasErrors = true; // Il credito è insufficiente
}

// Chiudi la connessione al DB
if (isset($conn) && $conn) {
    $conn->close();
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
<main class="content-area" aria-labelledby="page-heading">
    <div class="main-content-wrapper">

        <h2 class="section-heading" id="page-heading">Riepilogo dell'Acquisto</h2>

        
        <div class="user-info-display" aria-label="Informazioni utente e credito">
            <p>Artigiano: <strong class="highlight-text"><?= $currentUserName; ?></strong></p>
            <p>Credito disponibile: <strong class="highlight-amount">€<?= htmlspecialchars(number_format($availableCredit, 2, ',', '.')); ?></strong></p>
        </div>

        <div id="topAppFeedback" class="app-feedback-banner" style="display: none;" role="status" aria-live="polite" aria-atomic="true" aria-label="Messaggi di feedback dell'applicazione"></div>

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

        <?php if (!empty($selectedMaterials) && !$hasErrors): ?>
            <div class="responsive-table-wrapper">
                <table class="data-display-table" role="table" aria-label="Riepilogo dei materiali selezionati">
                    <thead>
                        <tr>
                            <th scope="col">Materiale</th>
                            <th scope="col">Quantità Richiesta</th>
                            <th scope="col">Costo Unitario (€)</th>
                            <th scope="col">Costo Parziale (€)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($selectedMaterials as $item): ?>
                            <tr>
                                <td><?= $item['NOME']; ?></td>
                                <td><?= $item['QUANTITA_RICHIESTA']; ?></td>
                                <td><?= number_format($item['COSTO_UNITARIO'], 2, ',', '.'); ?></td>
                                <td><?= number_format($item['COSTO_PARZIALE'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="total-label">Costo Totale Ordine:</td>
                            <td class="total-amount"><strong id="totalCostDisplay">€<?= number_format($totalCost, 2, ',', '.'); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="btns">
                <?php if ($creditInsufficient): ?>
                    <p class="error-message" role="alert">Non puoi procedere con l'acquisto per mancanza di credito.</p>

                <?php else: ?>
                    <form method="post" id="fine" action="fine.php">
                        <input type="hidden" name="action" value="execute_purchase">
                        <?php foreach ($selectedMaterials as $item): ?>
                            <input type="hidden" name="purchase_items[<?= $item['ID']; ?>]" value="<?= $item['QUANTITA_RICHIESTA']; ?>">
                        <?php endforeach; ?>
                        <button type="submit" id="btn_concludi" class="primary-action-btn" aria-label="Concludi l'acquisto dei materiali selezionati">Concludi Acquisto</button>
                    </form>
                <?php endif; ?>
                <form method="post" id="indietro" action="domanda.php">
                    <input type="hidden" name="action" value="reset_selection">
                    <button type="submit" id="btn_indietro" class="secondary-action-btn" aria-label="Annulla tutti i materiali selezionati e torna alla pagina di selezione">Annulla Selezione (Reset)</button>
                </form>
            </div>
        <?php elseif ($hasErrors): //se ci sono errori ?>
            <div class="btns">
                <form method="post" id="err" action="domanda.php">
                    <input type="hidden" name="action" value="reset_selection">
                    <button type="submit" id="btn_err" class="secondary-action-btn" aria-label="Torna alla pagina di selezione"> Indietro</button>
                </form>
            </div>
        <?php else: // Nessun materiale selezionato, tuttavia non ho riscontrato errori -> l'utente non ne ha inserito nessuno ?>
            <p>Nessun materiale selezionato. Torna alla pagina di selezione per scegliere i materiali da acquistare.</p>
            <div class="btns">
                <a href="domanda.php" class="primary-action-btn" aria-label="Torna alla pagina per selezionare i materiali da acquistare">Torna a Seleziona Materiali</a>
            </div>
        <?php endif; ?>

    </div>
    <div id="bottomAppFeedback" class="app-feedback-banner" style="display: none;" role="status" aria-live="polite" aria-atomic="true"></div>
</main>
<?php include 'footer.php'; ?>
</body>
</html>