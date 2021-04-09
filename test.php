<?php

$dbh = new PDO('mysql:host=localhost;dbname=railtour', 'railtouruser', 'railtourpass');

require('vendor/stefangabos/zebra_session/Zebra_Session.php');
$session = new Zebra_Session($dbh, 'sEcUr1tY_c0dE');

$_SESSION['fred'] = 'Fred is great';

