<?php
// Inizia la sessione se non è già attiva
if (session_status() === PHP_SESSION_DISABLED) {
    die("Le sessioni sono disabilitate. Il sito non può funzionare correttamente.");
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = "Guida al Sito - Eco-mmerce"; // Titolo della pagina

// Variabili per personalizzare l'esperienza se l'utente è loggato
$isLoggedIn = isset($_SESSION['user']);
$currentUserType = $_SESSION['user_type'] ?? null;
$currentUserName = htmlspecialchars($_SESSION['user'] ?? 'Utente');
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
<?php include 'header.php'; // Assicurati che il tuo header.php gestisca correttamente i link e lo stato del login ?>
<main class="content-area" role="main" aria-labelledby="main-heading">
    <div class="main-content-wrapper">
        <h1 class="section-heading" id="main-heading">Benvenuto in Eco-mmerce: dai valore al tuo spreco, trova la tua materia prima!</h1>

        <section class="intro-section" aria-labelledby="intro-heading">
            <p>Ti sei mai chiesto dove finiscono tutti quei materiali che le aziende scartano ogni anno? Tonnellate di tessuti, carta, plastica e compositi, spesso ancora utilizzabili, finiscono tristemente in discarica. E allo stesso tempo, migliaia di artigiani, designer emergenti e startup sostenibili faticano a trovare materie prime di qualità a costi accessibili. Sembra una disconnessione enorme, vero?</p>
            <p>Qui a Eco-mmerce crediamo fermamente che possiamo colmare questa lacuna. La nostra piattaforma nasce proprio da questa semplice, ma potente, idea: creare un ponte tra chi produce e chi crea, trasformando lo scarto in risorsa preziosa. Non siamo solo un sito, siamo un movimento verso un'economia più circolare, dove il valore non si esaurisce mai e ogni pezzo ha una seconda, entusiasmante, vita.</p>
        </section>

        <section class="how-it-works" aria-labelledby="how-it-works-heading">
            <h2 id="how-it-works-heading" class="sub-section-heading">Come funziona il nostro Ecosistema? Semplice e intuitivo!</h2>
            <p>Eco-mmerce è stato progettato per essere il più intuitivo possibile, facilitando lo scambio di materiali in modo trasparente ed efficiente. Abbiamo due tipi di utenti, ciascuno con un ruolo fondamentale e funzionalità pensate su misura per le sue esigenze:</p>

        <!-- Card Azienda -->
            <div class="user-role-card" role="region" aria-labelledby="azienda-heading">
                <h3 id="azienda-heading">Per le Aziende: Quando lo Scarto Diventa Risorsa</h3>
                <p>Se la tua azienda si trova con un surplus di materiali che desideri riciclare, riutilizzare o semplicemente dare via, sei nel posto giusto per fare la differenza.</p>
                <ul>
                    <li>Dai una Nuova Vita: Potrai inserire facilmente i tuoi lotti di materiali disponibili, specificando ogni dettaglio: tipo, quantità, una descrizione accurata e il costo (o anche se sono gratuiti!).</li>
                    <li>Tieni Tutto Sotto Controllo: Avrai a disposizione una sezione intuitiva dove visualizzare e gestire tutti i materiali che hai messo a disposizione, modificandone le caratteristiche in qualsiasi momento.</li>
                    <li>Impatto Positivo: Ogni materiale che offri su Eco-mmerce è un passo concreto verso la riduzione dell'impronta ecologica della tua produzione. Non solo smaltisci in modo responsabile, ma supporti anche una comunità di innovatori.</li>
                </ul>
                <?php if ($isLoggedIn && $currentUserType === 'azienda'): ?>
                    <p class="current-user-message">Benvenuto, <?= $currentUserName ?> (Azienda)! Sei già connesso e pronto a dare il tuo contributo.</p>
                    <div class="action-buttons-container">
                        <a href="offerta.php" class="primary-action-btn" aria-label="Carica nuovi materiali per la tua azienda">Carica Materiali</a>
                        <a href="offerta.php" class="primary-action-btn" aria-label="Gestisci i tuoi annunci di materiali esistenti">Gestisci Annunci</a>
                </div>
                        <?php elseif (!$isLoggedIn): ?>
                    <p>Pronto a trasformare i tuoi scarti in valore e a contribuire a un futuro più sostenibile? </p>
                    <p class= "ultimo"><a href="registrazione.php?type=azienda" aria-label="Registrati subito come azienda per offrire materiali">Registrati subito come Azienda</a> o <a href="login.php" aria-label="Accedi al tuo account">Accedi</a> al tuo account.</p>
                <?php endif; ?>
            </div>

            <!-- Card Artigiano -->
            <div class="user-role-card" role="region" aria-labelledby="artigiano-heading">
                <h3 id="artigiano-heading">Per Artigiani e Creativi: Il Tuo Tesoro Nascosto Ti Aspetta</h3>
                <p>Se sei un artigiano, un designer visionario o hai una startup sostenibile alla costante ricerca di materie prime uniche e convenienti, Eco-mmerce è la tua miniera d'oro.</p>
                <ul>
                    <li>Esplora e acquista: Il nostro catalogo è sempre aggiornato con materiali di ogni tipo, pronti per ispirare la tua prossima creazione. Sfoglia, scegli e acquista con pochi semplici click.</li>
                    <li>Gestisci il tuo credito: Ogni artigiano ha un credito dedicato sulla piattaforma, che potrai utilizzare per i tuoi acquisti, rendendo il processo fluido e trasparente.</li>
                    <li>Crea con consapevolezza: Qui trovi materiali che non solo ti permettono di esprimere la tua creatività, ma ti consentono anche di farlo in modo etico e sostenibile, contribuendo attivamente all'economia circolare.</li>
                </ul>
                <?php if ($isLoggedIn && $currentUserType === 'artigiano'): ?>
                
                    <p class="current-user-message">Ciao, <?= $currentUserName ?> (Artigiano)! Siamo entusiasti di averti qui. È il momento di dare un'occhiata ai materiali disponibili!</p>
                    <div class="action-buttons-container">
                        <a href="domanda.php" class="primary-action-btn" aria-label="Scopri i materiali disponibili per l'acquisto come artigiano">Scopri i Materiali Ora</a>
                    </div>
                    <?php elseif (!$isLoggedIn): ?>
                    <p>Sei pronto a scoprire le materie prime perfette per le tue creazioni, a prezzi vantaggiosi e con un impatto positivo? </p>
                    <p class= "ultimo"><a href="registrazione.php?type=artigiano">Registrati come Artigiano</a> o <a href="login.php">Accedi</a> per iniziare la tua ricerca.</p>
                <?php endif; ?>
               
            </div>
        </section>


        <section class="benefits-section" aria-labelledby="benefits-heading">
            <h2 id="benefits-heading" class="sub-section-heading">I nostri valori</h2>
            <div class="benefits-grid">
                <div class="benefit-item" role="article" aria-labelledby="sostenibilita-heading">
                    <h3 id="sostenibilita-heading">Sostenibilità</h3>
                    <p>Riduzione dei rifiuti, riutilizzo dei materiali e promozione di pratiche ecologiche.</p>
                </div>
                <div class="benefit-item" role="article" aria-labelledby="accessibilita-heading">
                    <h3 id="accessibilita-heading">Accessibilità</h3>
                    <p>Materie prime a costi contenuti per supportare piccoli business e innovatori.</p>
                </div>
                <div class="benefit-item" role="article" aria-labelledby="comunita-heading">
                    <h3 id="comunita-heading">Comunità</h3>
                    <p>Creazione di una rete tra aziende e artigiani per un futuro più circolare.</p>
                </div>
            </div>
        </section>

        <section class="cta-section" aria-labelledby="cta-heading">
            <h2 id="cta-heading" class="sub-section-heading">Non restare a guardare, unisciti a noi!</h2>
            <?php if (!$isLoggedIn): ?>
                <p>Che tu voglia dare nuova vita ai tuoi scarti o trovare i materiali perfetti per le tue creazioni, Eco-mmerce è il luogo dove la sostenibilità incontra l'innovazione.</p>
                <div class="btns">
                    <a href="registrazione.php" class="primary-action-btn" aria-label="Registrati alla piattaforma Eco-mmerce">Registrati Ora</a>
                    <a href="login.php" class="primary-action-btn" aria-label="Accedi al tuo account Eco-mmerce">Accedi</a>
                </div>
            <?php else: ?>
                <p>Siamo davvero felici di averti a bordo, <?= $currentUserName ?>! Continua a esplorare la piattaforma e a fare la differenza, un materiale alla volta.</p>
            <?php endif; ?>
        </section>

    </div>
</main>
<?php include 'footer.php'; ?>
</body>
</html>