<?php
/**
 * Dedicated Login Gateway
 * Seamlessly forwards all authentication traffic to the Cathedral Portal Login Modal with 3D Silver Cross.
 */
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '?open_login=1';
header("Location: index.php" . $query);
exit();
