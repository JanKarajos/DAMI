<?php
require dirname(__DIR__) . '/app/bootstrap.php';
unset($_SESSION['admin_id']);
session_regenerate_id(true);
flash('success', 'Boli ste odhlásený/á.');
redirect('admin/prihlasenie.php');
