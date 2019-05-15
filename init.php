<?php

// Notifications email addresses
define('EMAIL_ERROR', '');
define('EMAIL_REPORT', '');

// Log directory and year-month filename
define('LOG_DIR', '/var/www/html/vendorapis/sanmar/logs/');
define('LOG_FILE', LOG_DIR.date('Y-m').'.txt');

// Promomarketing SanMar Ids
define('PM_SANMAR_SUPPLIER_ID', 0);
define('PM_SANMAR_CATALOG_ID', 0);

// SOAP connection parameters
define('SOAP_WSDL', 'https://ws.sanmar.com:8080/SanMarWebService/SanMarProductInfoServicePort');
define('SOAP_CUSTOMER_NUMBER', 0);
define('SOAP_USERNAME', '');
define('SOAP_PASSWORD', '');

// FTP connection parameters
define('FTP_HOST', 'ftp.sanmar.com');
define('FTP_USERNAME', '');
define('FTP_PASSWORD', '');

// Database connection parameters
define('DATABASE_HOST', '');
define('DATABASE_USERNAME', '');
define('DATABASE_PASSWORD', '');
define('DATABASE_INSERT_LIMIT', 1000);

// Image pparameters
define('IMAGE_MOUNT', '/mnt/pmcatimages/');
define('IMAGE_LARGE', 500);
define('IMAGE_MEDIUM', 250);
define('IMAGE_SMALL', 100);
define('IMAGE_QUALITY', 100);

// Delta directories and filename
define('DELTA_FILENAME', 'SanMarPI-Delta-215842.csv');

define('DELTA_LOCAL_DIR', '/var/www/html/vendorapis/sanmar/delta/');
define('DELTA_LOCAL_FILE', DELTA_LOCAL_DIR.date('Y-m-d_').DELTA_FILENAME);

define('DELTA_REMOTE_DIR', '/SanMarPDD/');
define('DELTA_REMOTE_FILE', DELTA_REMOTE_DIR.DELTA_FILENAME);

define('DELTA_EXPIRATION', 60*60*24*28);

define('LOG_INDENT', '   ');
define('SINGLE_RETURN', "\r\n");
define('DOUBLE_RETURN', "\r\n\r\n");

// Create logs directory if neccessary
if (!file_exists(LOG_DIR)) {
	mkdir(LOG_DIR, 0755);
}

// Create local delta directory if neccessary
if (!file_exists(DELTA_LOCAL_DIR)) {
	mkdir(DELTA_LOCAL_DIR, 0755);
}
