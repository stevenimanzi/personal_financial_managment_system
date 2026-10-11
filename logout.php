<?php

require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_verify();
    logout_user();
}

redirect('login.php');
