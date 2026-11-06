<?php
// logout.php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Use centralized destroy function
lb_destroy_session();

header('Location: index.php');
exit;
