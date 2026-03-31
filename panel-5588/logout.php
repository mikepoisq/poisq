<?php
require_once __DIR__ . "/auth.php";
session_destroy();
header("Location: /panel-5588/");
exit;
?>