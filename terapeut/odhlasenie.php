<?php
require dirname(__DIR__) . '/app/bootstrap.php';
unset($_SESSION['therapist_id']);
session_regenerate_id(true);
flash('success', 'Boli ste odhlásený/á.');
redirect('terapeut/prihlasenie.php');
