<?php

$globalRequires = array(
    // Favicon request handler
    'app/favicon.inc.php',

    // Definitions needed by the application
    'app/definitions.inc.php',

    // Composer and custom autoloaders
    'vendor/autoload.php',
    'app/autoload.inc.php',

    // Custom logging interface
    'app/islog.inc.php',
);

foreach($globalRequires as $required) {
    require_once $required;
}
