<?php
include('api_helper.php');

// HIER EINFACH DEN FAMILIEN-CODE EINTRAGEN:
$familyCode = 'press_brake';

$products = getAkeneoProducts('family', $familyCode);
?>