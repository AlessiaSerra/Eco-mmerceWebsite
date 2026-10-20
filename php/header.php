<?php
// Verifico se le sessioni sono abilitate e nel caso se non sono state avviate, avvio la sessione
    $session = true;
    if( session_status() === PHP_SESSION_DISABLED  )
        $session = false;
    elseif( session_status() === PHP_SESSION_NONE )
    { 
        session_start();

    }

    $saldo = 0;
    $nick = "non loggato";

if (isset($_SESSION['user'])) {
    $nick = $_SESSION['user'];

    $db_host = 'localhost';
    $db_user = 'lettore'; //privilegi limitati
    $db_pass = 'P@ssw0rd!';
    $db_name = 'eco_scambio';


    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
    if ($conn&& !$conn->connect_error) {
        $stmt = mysqli_prepare($conn, "SELECT CREDIT FROM DATI_ARTIGIANI,UTENTI WHERE UTENTI.ID = DATI_ARTIGIANI.ID_UTENTE AND NICK = ?");
        mysqli_stmt_bind_param($stmt, "s", $nick);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $saldo);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
    }
}
?>

    <header>
        <div class="barra-superiore">
            <h1 class="titolo"> <a href="home.php" aria-label="Home page Eco-mmerce">Eco-mmerce </a></h1>

            <nav class="menu"  aria-label="Navigazione principale del sito">
                    <ul class="navigazione">
                        <li><a class="link_nav" href="home.php" aria-label="Vai alla pagina Home">Home</a></li>
                        <li><a class="link_nav" href="offerta.php" aria-label="Vai alla pagina Offerta">Offerta</a></li>
                        <li><a class="link_nav" href="domanda.php" aria-label="Vai alla pagina Domanda">Domanda</a></li>
                        <li><a class="link_nav" href="lista.php"  aria-label="Vai alla pagina Lista materiali">Lista</a></li>
                        <li><a class="link_nav" href="guida.php" aria-label="Consulta la Guida del sito">Guida</a></li>
                        <!-- li><a class="link_nav" href="../conferma.php">Conferma</a></li -->
                        <li><a class="link_nav" href="registrazione.php" aria-label="Registrati per un nuovo account">Registrati</a></li>
                    </ul>
            </nav>


         <nav class='autenticazione' aria-label="Sezione di autenticazione utente">
            <div class="user-info">
                <span aria-label="Nome utente attuale">Utente: <?php echo htmlspecialchars($nick); ?></span>
                <span aria-label="Saldo attuale dell'utente">Saldo: <?php echo htmlspecialchars($saldo); ?> €</span>
            </div>
            <div class="auth-buttons">
                <?php if (isset($_SESSION['user'])): ?>
                    <span class="button-disabled" aria-label="Login disabilitato, sei già loggato">Login</span> 
                    <a href="logout.php" aria-label="Effettua il logout dal tuo account">Logout</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <span class="button-disabled" aria-label="Logout disabilitato, non sei loggato">Logout</span>
                <?php endif; ?>
            </div>
        </nav>
          

    </div>
    </header>
