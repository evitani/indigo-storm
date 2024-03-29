<?php

// Running Directory
define('_RUNNINGDIR_', getcwd());

//App details
define("IS_VERSION", "21.17");

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

// HTTP Methods
define('HTTP_METHOD_GET', 'get');
define('HTTP_METHOD_POST', 'post');
define('HTTP_METHOD_PUT', 'put');
define('HTTP_METHOD_DELETE', 'delete');
define('HTTP_METHOD_OPTIONS', 'options');

// Return types
define('RETURN_JSON', 'json');
define('RETURN_FILE', 'file');
define('RETURN_XML', 'xml');

// Tiers
define('TIER_RELEASE', 'release');
define('TIER_PRERELEASE', 'prerelease');
define('TIER_LOCAL', 'local');
