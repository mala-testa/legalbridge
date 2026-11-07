<?php
//header.php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
// Pages that should NOT start a session
$publicPages = [
  'index.php',
  'login.php',
  'signup.php'
];

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');

// Start session only on protected pages
if (!in_array($currentPage, $publicPages, true)) {
  lb_secure_session_start();
}

// Safe session variables (works even if no session exists)
$loggedin = isset($_SESSION['user_id']);
$email    = $_SESSION['email'] ?? null;
$userstr = $loggedin ? "Logged in as: " . e($email) : "Welcome Guest";
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/legalbridge2/resources/css/styles.css">
  <script src="/legalbridge2/resources/js/script.js"></script>
  <title>LegalBridge: <?= $userstr ?></title>
</head>

<body>
  <header class="lb-header">
    <div class="header-logo">
      <img src="/legalbridge2/resources/images/gavel.png" alt="LegalBridge Logo" class="logo-img">
      <div class="logo-text">LEGALBRIDGE</div>
    </div>
    <div class="username"><?= $userstr ?></div>
  </header>
  <nav class="lb-nav">
    <?php if ($loggedin): ?>
      <a href="dashboard.php">Home</a>

      <?php if (($_SESSION['role'] ?? '') !== 'admin'): ?>
        <a href="matches.php">Matches</a>
      <?php endif; ?>

      <a href="messages.php">Messages</a>
      <a href="profile.php">Edit Profile</a>
      <a href="logout.php">Log out</a>
    <?php else: ?>
      <a href="index.php">Home</a>
      <a href="signup.php">Sign Up</a>
      <a href="login.php">Log In</a>
    <?php endif; ?>
  </nav>
  <main class="lb-content">