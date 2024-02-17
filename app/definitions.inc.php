<?php

// Running Directory
define('_RUNNINGDIR_', getcwd());

//App details
const IS_VERSION = '21.17';

//DB2 constants
const DB2_VARCHAR_SHORT = 'varchar(128)';
const DB2_VARCHAR = 'varchar(256)';
const DB2_VARCHAR_LONG = 'varchar(512)';
const DB2_BLOB = 'blob';
const DB2_BLOB_LONG = 'longblob';
const DB2_TEXT = 'text';
const DB2_TEXT_LONG = 'longtext';
const DB2_INT = 'int(11)';

//Database search type
const SEARCH_BY_ID = 'id';
const SEARCH_BY_NAME = 'name';
const ORDER_DESC = 'DESC';
const ORDER_ASC = 'ASC';

//Class information
const DATATABLE_CLASS = 'Core\Db2\Models\DataTable';
const SEARCHQUERY_CLASS = 'Core\Db2\Models\SearchQuery';

//Object save types
const SAVE_REVISIONS_NO = 'norev';
const SAVE_REVISIONS_LOG = 'logrev';
const SAVE_REVISIONS_OBJECT = 'objectrev';

//Object deletion types
const DELETE_BACKUP_NEVER = 'softDelete';
const DELETE_BACKUP_7D = 'retainBackup7d';
const DELETE_BACKUP_30D = 'retainBackup30d';
const DELETE_NOBACKUP = 'noBackup';

// HTTP Methods
const HTTP_METHOD_GET = 'get';
const HTTP_METHOD_POST = 'post';
const HTTP_METHOD_PUT = 'put';
const HTTP_METHOD_DELETE = 'delete';
const HTTP_METHOD_OPTIONS = 'options';

// Return types
const RETURN_JSON = 'json';
const RETURN_FILE = 'file';
const RETURN_XML = 'xml';

// Tiers
const TIER_RELEASE = 'release';
const TIER_PRERELEASE = 'prerelease';
const TIER_LOCAL = 'local';

// Defaults
const DEFAULT_HASH_ALGO = 'sha256';
