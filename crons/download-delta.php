<?php

include dirname(__FILE__).'/../init.php';

file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'DOWNLOAD DELTA CRON'.SINGLE_RETURN, FILE_APPEND);

// Attempt to connect to SanMar FTP server
if (!$cid = ftp_connect(FTP_HOST)) {
	// Log connection error
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Failed to connect to SanMar FTP'.SINGLE_RETURN, FILE_APPEND);
	mail(EMAIL_ERROR, 'SanMar Download Delta Cron - FTP Connection Failed - Severity: HIGH', 'Failed to connect to SanMar FTP'.DOUBLE_RETURN.'Check FTP host: '.FTP_HOST);
	exit;
}

// Attempt to login to SanMar FTP server
if (!ftp_login($cid, FTP_USERNAME, FTP_PASSWORD)) {
	// Log login error
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Failed to login to SanMar FTP'.SINGLE_RETURN, FILE_APPEND);
	mail(EMAIL_ERROR, 'SanMar Download Delta Cron - FTP Login Failed - Severity: HIGH', 'Failed to login to SanMar FTP'.DOUBLE_RETURN.'Check FTP credentials: '.FTP_USERNAME.' - '.FTP_PASSWORD);
	exit;
}

// Check for remote delta file by looking at its file size
if (ftp_size($cid, DELTA_REMOTE_FILE) == -1) {
	// Log missing remote delta file
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Missing remote delta file '.SINGLE_RETURN, FILE_APPEND);
	mail(EMAIL_ERROR, 'SanMar Download Delta Cron - FTP File Missing - Severity: HIGH', 'Missing remote delta file'.DOUBLE_RETURN.'Check remote file location: '.DELTA_REMOTE_FILE);
	exit;
}

// Attempt to retrieve delta modified time
$modified = ftp_mdtm($cid, DELTA_REMOTE_FILE);

// Check for failure
if ($modified == -1) {
	// Log modified time retrieval error
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Failed to retrieve remote delta file modified time'.SINGLE_RETURN, FILE_APPEND);
	mail(EMAIL_ERROR, 'SanMar Request Delta Cron - FTP File Modification Time - Severity: HIGH', 'Failed to retrieve remote delta file modification time'.DOUBLE_RETURN.'Debug ftp_mdtm() function');
	exit;
}

// Check for out-dated delta file modified time
if (date('Y-m-d', $modified) != date('Y-m-d')) {
	// Log no delta file changes
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'No changes to remote delta file'.SINGLE_RETURN, FILE_APPEND);
	mail(EMAIL_ERROR, 'SanMar Request Delta Cron - FTP File Modification Time - Severity: LOW', 'No changes to remote delta file'.DOUBLE_RETURN.'Acceptable error, no products to update since last delta file: '.date('Y-m-d', $modified));
	exit;
}

// Attempt to download delta file
if (!ftp_get($cid, DELTA_LOCAL_FILE, DELTA_REMOTE_FILE, FTP_BINARY)) {
	// Log file download error
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Failed to download remote delta file'.SINGLE_RETURN, FILE_APPEND);
	mail(EMAIL_ERROR, 'SanMar Download Delta Cron - FTP File Download Failed - Severity: HIGH', 'Failed to download remote delta file'.DOUBLE_RETURN.'Check FTP credentials, Check remote/local file locations, Debug ftp_get() function');
	exit;
}

// Close FTP connection
ftp_close($cid);

// Log file downloaded
file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Downloaded delta file from SanMar FTP'.SINGLE_RETURN, FILE_APPEND);
