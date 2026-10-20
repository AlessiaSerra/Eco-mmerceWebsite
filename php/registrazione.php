<?php
// Mi assicuro che la sessione sia avviata
    if (session_status() === PHP_SESSION_DISABLED) {
            die("Le sessioni sono disabilitate. Il sito non può funzionare correttamente.");
        } elseif (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

    if (isset($_SESSION['user'], $_SESSION['pwd'], $_SESSION['id'], $_SESSION['user_type'])) {
        $_SESSION['login_error'] = "Devi effettuare il logout prima di registrarti.";
        header("Location: login.php");
        exit;
    }

    // recupera eventuali errori dalla sessione dopo un tentativo di registrazione fallito
    // gli errori e old_input verranno gestiti per tipo di form
    $errors_azienda = $_SESSION['err_registraz_azienda'] ?? [];
    $old_input_azienda = $_SESSION['old_registration_input_azienda'] ?? [];

    $errors_artigiano = $_SESSION['err_registraz_artigiano'] ?? [];
    $old_input_artigiano = $_SESSION['old_registration_input_artigiano'] ?? [];

    $general_error_message = ""; // Inizializzo le variabili da usare nella parte di HTML
    if (isset($_SESSION['reg_error'])) {
        $general_error_message = $_SESSION['reg_error'];
        unset($_SESSION['reg_error']); // Cancello l'errore dopo averlo mostrato su schermo
    }

    // Pulisce le variabili di sessione dopo averle recuperate
    unset($_SESSION['err_registraz_azienda']);
    unset($_SESSION['old_registration_input_azienda']);
    unset($_SESSION['err_registraz_artigiano']);
    unset($_SESSION['old_registration_input_artigiano']);
    $page_title = "Registrazione";
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8" />
    <meta name="author" content="Alessia Serra">
    <link rel="stylesheet" type="text/css" href="../css/stile.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel = "icon" type ="image/jpeg" href="/PROGETTO_ESAME/img/icona.png">
    <script defer src="../js/registra.js"></script>
    <title> <?= htmlspecialchars($page_title) ?></title>
</head>
<body>
<?php include "header.php" ?>
<main>
<div class = "forms">

    <div class="container">
    <form id="registra_azienda" method="post" action="registra_azienda_back.php" aria-labelledby="legendAzienda">
        <fieldset>
            <legend id="legendAzienda">Registrazione Azienda</legend>
             <?php if (isset($errors_azienda['general'])): ?>
                <p class="form-error" role="alert"><?php echo htmlspecialchars($errors_azienda['general']); ?></p>
            <?php endif; ?> 

            <label for="ragione_az">Ragione sociale</label>
            <input type="text" id="ragione_az" name="ragione" maxlength="30" class="form-input"
                value="<?php echo htmlspecialchars($old_input_azienda['ragione'] ?? ''); ?>" required aria-required="true"
                aria-describedby="ragione-error">
            <p class="field-error-feedback" id="ragione-error" aria-live="polite"><?php if (isset($errors_azienda['ragione'])): ?><?php echo htmlspecialchars($errors_azienda['ragione']); ?><?php endif; ?></p>

            <label for="address2_az">Indirizzo (es. Via Roma 10, Milano)</label>
            <input type="text" id="address2_az" name="address2" value="<?php echo htmlspecialchars($old_input_azienda['address2'] ?? ''); ?>" required aria-required="true"
            aria-describedby="address2-error">
            <p class="field-error-feedback" id="address2-error" aria-live="polite"><?php if (isset($errors_azienda['address2'])): ?><?php echo htmlspecialchars($errors_azienda['address2']); ?><?php endif; ?></p>


            <label for="nick_az">Username</label>
            <input type="text" id="nick_az" name="nick" minlength="4" maxlength="10" class="form-input"
                    value="<?php echo htmlspecialchars($old_input_azienda['nick'] ?? ''); ?>" required aria-required="true"
                    aria-describedby="nick_azienda-error">
            <p class="field-error-feedback" id="nick_azienda-error" aria-live="polite"><?php if (isset($errors_azienda['nick'])): ?><?php echo htmlspecialchars($errors_azienda['nick']); ?><?php endif; ?></p>
            

            <label for="password_az">Password</label>
            <input type="password" id="password_az" name="password" minlength="8" maxlength="16" class="form-input" required aria-required="true"
            aria-describedby="password_azienda-error">
            <p class="field-error-feedback" id="password_azienda-error" aria-live="polite"><?php if (isset($errors_azienda['password'])): ?><?php echo htmlspecialchars($errors_azienda['password']); ?><?php endif; ?></p>
            
        </fieldset>

            <p class="link_reg">
                Hai già un account? <a href="login.php" aria-label="Accedi al tuo account esistente">Accedi.</a>
            </p>
            <button type="submit" id="registra_az" aria-label="Registra come azienda">Registrati</button>
            <button type="reset" id="reset_registra_az" aria-label="Cancella tutti i campi del modulo di registrazione azienda">Cancella campi</button>
    </form>
    </div>

    <div class="container">

    <form id="registra_artigiano" method="post" action="registra_artigiano_back.php" aria-labelledby="legendArtigiano">
        <fieldset>
            <legend id="legendArtigiano">Registrazione Artigiano</legend><?php if (isset($errors_artigiano['general'])): ?>
                <p class="message-banner message-error" role="alert"><?php echo htmlspecialchars($errors_artigiano['general']); ?></p>
            <?php endif; ?>

            <label for="name">Nome</label>
            <input type="text" id="name" name="name" minlength="4" maxlength="14" class="form-input"
                value="<?php echo htmlspecialchars($old_input_artigiano['name'] ?? ''); ?>" required aria-required="true"
                aria-describedby="name-error">
            <p class="field-error-feedback" id="name-error" aria-live="polite"><?php if (isset($errors_artigiano['name'])): ?><?php echo htmlspecialchars($errors_artigiano['name']); ?><?php endif; ?></p>
            

            <label for="surname">Cognome</label>
            <input type="text" id="surname" name="surname" minlength="4" maxlength="16" class="form-input"
                value="<?php echo htmlspecialchars($old_input_artigiano['surname'] ?? ''); ?>" required aria-required="true"
                aria-describedby="surname-error">
            <p class="field-error-feedback" id="surname-error" aria-live="polite"><?php if (isset($errors_artigiano['surname'])): ?><?php echo htmlspecialchars($errors_artigiano['surname']); ?><?php endif; ?></p>
            

    <label for="birthdate">Data di nascita</label>
            <input type="date" id="birthdate" name="birthdate"value="<?php echo htmlspecialchars($old_input_artigiano['birthdate'] ?? ''); ?>" required aria-required="true"
            aria-describedby="birthdate-error">
            <p class="field-error-feedback" id="birthdate-error" aria-live="polite"><?php if (isset($errors_artigiano['birthdate'])): ?><?php echo htmlspecialchars($errors_artigiano['birthdate']); ?><?php endif; ?></p>
            

            <label for="credit">Credito (€)</label>
            <input type="number" id="credit" name="credit" step="0.05" min="0"
             value="<?php echo htmlspecialchars($old_input_artigiano['credit'] ?? ''); ?>" required aria-required="true"
             aria-describedby="credit-error">
            <p class="field-error-feedback" id="credit-error" aria-live="polite"><?php if (isset($errors_artigiano['credit'])): ?><?php echo htmlspecialchars($errors_artigiano['credit']); ?><?php endif; ?></p>
            
            <label for="address_art">Indirizzo (es. Via Roma 10, Milano)</label>
            <input type="text" id="address_art" name="address"
                value="<?php echo htmlspecialchars($old_input_artigiano['address'] ?? ''); ?>" required aria-required="true"
                aria-describedby="address-error">
            <p class="field-error-feedback" id="address-error" aria-live="polite"><?php if (isset($errors_artigiano['address'])): ?><?php echo htmlspecialchars($errors_artigiano['address']); ?><?php endif; ?></p>
            

            <label for="nick_art">Username</label>
            <input type="text" id="nick_art" name="nick"  minlength="4" maxlength="10" class="form-input"
                value="<?php echo htmlspecialchars($old_input_artigiano['nick'] ?? ''); ?>" required aria-required="true"
                aria-describedby="nick_artigiano-error">
            <p class="field-error-feedback" id="nick_artigiano-error" aria-live="polite"><?php if (isset($errors_artigiano['nick'])): ?><?php echo htmlspecialchars($errors_artigiano['nick']); ?><?php endif; ?></p>
            

            <label for="password_art">Password</label>
            <input type="password" id="password_art" name="password"  minlength="8" maxlength="16" class="form-input" required aria-required="true"
            aria-describedby="password_artigiano-error">
            <p class="field-error-feedback" id="password_artigiano-error" aria-live="polite"><?php if (isset($errors_artigiano['password'])): ?><?php echo htmlspecialchars($errors_artigiano['password']); ?><?php endif; ?></p>
            
        </fieldset>

        <p class="link_reg">
            Hai già un account? <a href="login.php" aria-label="Accedi al tuo account esistente">Accedi.</a>
        </p>
        <button type="submit" id="registra_art" aria-label="Registra come artigiano">Registrati</button>
        <button type="reset" id ="reset_registra_art" aria-label="Cancella tutti i campi del modulo di registrazione artigiano">Cancella campi</button>
    </form>
</div>
</div>

<?php include "footer.php" ?>
</body>
</html>