<!DOCTYPE html>
<html lang = "it">
    <head>
        <meta charset="utf-8" />
        <title>Eco-mmerce</title>
        <meta name="author" content="Alessia Serra">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" type="text/css" href="../css/stile.css">
        <link rel = "icon" type ="image/jpeg" href="../img/icona.png">

    </head>
    <body class="home">

    <?php require_once 'header.php'; ?>

      <main aria-label="Introduzione ai servizi">
        <div class="descrizione">

        <p class="primo">
            <a href="offerta.php" aria-label="Inserisci nuovi materiali da riciclare"> Inserisci nuovi materiali</a> da riciclare, oppure </p>
        <p class="caratteristiche">     <a href="domanda.php" aria-label="Esplora e acquista i materiali in vendita">esplora e acquista</a> i materiali in vendita.</p>
    </div>

        <div class="ultimo" aria-label="Informazioni aggiuntive e accesso">
            <p> Hai qualche dubbio? Consulta subito la nostra <a href="guida.php" aria-label="Consulta la guida del sito">guida.</a>
            <p> Lasciati ispirare. <a href="login.php" aria-label="Accedi al tuo account">Accedi</a> con il tuo account oppure <a href="registrazione.php" aria-label="Registrati per un nuovo account">registrati</a></p>
        </div>

    </main>

<?php require_once 'footer.php'; ?>

</body>
</html>