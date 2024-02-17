<?php

//App details
define("IS_VERSION", "20.01");
$part1 = date("y");
$part2 = intval(intval(date("W")) / 2);
define('IS_MOSTRECENT', $part1 . "." . $part2);
unset($part1);
unset($part2);

//DB2 constants
define('DB2_VARCHAR_SHORT', 'varchar(128)');
define('DB2_VARCHAR', 'varchar(256)');
define('DB2_VARCHAR_LONG', 'varchar(512)');
define('DB2_BLOB', 'blob');
define('DB2_BLOB_LONG', 'longblob');
define('DB2_TEXT', 'text');
define('DB2_TEXT_LONG', 'longtext');
define('DB2_INT', 'int(11)');

//Database search type
define('SEARCH_BY_ID', 'id');
define('SEARCH_BY_NAME', 'name');

//Class information
define('DATATABLE_CLASS', 'Core\Db2\Models\DataTable');

//Message types
define('MAILMAN_EMAIL', 'email');
define('MAILMAN_SMS', 'sms');


//Mailman
define('MAILMAN_LOG_SEND_IMMEDIATE', 'IMMEDIATE_SEND');
define('MAILMAN_LOG_SEND_SCHEDULED', 'SCHEDULED_SEND');
define('MAILMAN_LOG_SENT', 'SENT');
