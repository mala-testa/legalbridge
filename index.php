<?php

require_once __DIR__.'/includes/header.php';

$loggedin = isset($_SESSION['user_id']);
$user     = $_SESSION['email'] ?? 'Guest';
?>

<div class="lb-hero">
    
    <div class="lb-welcome">
        Welcome to Legal Bridge
        <?php if ($loggedin): ?>
            <?= " " . htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?>, you are logged in
        <?php else: ?>
            <br> sign up or log in
        <?php endif; ?>
    </div>
    <h1 class="lb-title">Fiat Justitia Ruat Caelum</h1>
</div>

<footer class="lb-footer">

    
    <h4>
        Web App from 
        <i>
            <a href="https://github.com/mala-testa/legalbridge.git" target="_blank">
                Legal Bridge Repository
            </a>
        </i>
    </h4>
</footer>

</main>
</body>
</html>
