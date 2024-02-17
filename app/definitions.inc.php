<?php

//App details
define("IS_VERSION", "20.05");
$part1 = date("y");
$part2 = intval(intval(date("W")) / 2);
if($part2 < 10){
    $part2 = "0" . $part2;
}
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
define('ORDER_DESC', 'DESC');
define('ORDER_ASC', 'ASC');

//Class information
define('DATATABLE_CLASS', 'Core\Db2\Models\DataTable');
define('SEARCHQUERY_CLASS', 'Core\Db2\Models\SearchQuery');

//Object save types
define('SAVE_REVISIONS_NO', 'norev');
define('SAVE_REVISIONS_LOG', 'logrev');
define('SAVE_REVISIONS_OBJECT', 'objectrev');

//Object deletion types
define('DELETE_BACKUP_NEVER', 'softDelete');
define('DELETE_BACKUP_7D', 'retainBackup7d');
define('DELETE_BACKUP_30D', 'retainBackup30d');
define('DELETE_NOBACKUP', 'noBackup');

//Message types
define('MAILMAN_EMAIL', 'email');
define('MAILMAN_SMS', 'sms');

//Mailman
define('MAILMAN_LOG_SEND_IMMEDIATE', 'IMMEDIATE_SEND');
define('MAILMAN_LOG_SEND_SCHEDULED', 'SCHEDULED_SEND');
define('MAILMAN_LOG_SENT', 'SENT');
