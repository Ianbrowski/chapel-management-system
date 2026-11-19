<?php
/**
 * PRIEST PREFERENCES & AUTHORIZATIONS — Forwarder
 * Delegates directly to unified priest_schedule.php on the Authorizations tab.
 */
if (!isset($_GET['tab'])) {
    $_GET['tab'] = 'authorizations';
}
include __DIR__ . '/priest_schedule.php';
