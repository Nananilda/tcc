<?php

session_start();

$_SESSION = [];

session_destroy();

header("Location: /tcc/index.php");

exit;

?>