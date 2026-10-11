<?php

require __DIR__ . '/includes/bootstrap.php';

redirect(auth_user() ? 'dashboard.php' : 'login.php');
