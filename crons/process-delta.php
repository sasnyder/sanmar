<?php

include dirname(__FILE__).'/../init.php';

// Check for delta file
if (!file_exists(DELTA_LOCAL_FILE)) {
	// Log missing delta file error
	// Commented out error email and log and redundant. The download cron will catch this error and report it.
	//file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Missing todays local delta file'.SINGLE_RETURN, FILE_APPEND);
	//mail(EMAIL_ERROR, 'SanMar Process Delta Cron - File Missing - Severity: LOW', 'Missing todays local delta file'.DOUBLE_RETURN.'Double check local file location: '.DELTA_LOCAL_FILE.DOUBLE_RETURN.'Otherwise an acceptable error if no products were updated since last delta file');
	exit;
}

// Commented out after filenames became date based. The above file_exists() is enough to check for todays delta file.
/*
// Check for out-dated delta file modified time
if (date('Y-m-d', filemtime(DELTA_LOCAL_FILE)) != date('Y-m-d')) {
	// Log no delta file changes
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'No changes to local delta file'.SINGLE_RETURN, FILE_APPEND);
	mail(EMAIL_ERROR, 'SanMar Process Delta Cron - File Modification Time - Severity: LOW', 'No changes to local delta file'.DOUBLE_RETURN.'Acceptable error, no products to update since last delta file: '.date('Y-m-d', filemtime(DELTA_LOCAL_FILE)));
	exit;
}
*/

file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'PROCESS DELTA CRON'.SINGLE_RETURN, FILE_APPEND);

// Attempt to open delta file
if (!$fh = fopen(DELTA_LOCAL_FILE, 'r')) {
	// Log open error
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Failed to open local delta file'.SINGLE_RETURN, FILE_APPEND);
	mail(EMAIL_ERROR, 'SanMar Process Delta Cron - File Open Failed - Severity: HIGH', 'Failed to open local delta file'.DOUBLE_RETURN.'Check local delta file: '.DELTA_LOCAL_FILE);
	exit;
}

// Get the delta file column headers
$headers = array_flip(fgetcsv($fh));

// List of table columns
// TODO: query table schema for columns
$columns = array('UNIQUE_KEY', 'PRODUCT_TITLE', 'PRODUCT_DESCRIPTION', 'STYLE#', 'AVAILABLE_SIZES', 'THUMBNAIL_IMAGE', 'PRODUCT_IMAGE', 'SUGGESTED_PRICE', 'COLOR_NAME', 'SIZE', 'BRAND_NAME', 'PRODUCT_STATUS', 'PRICE_CODE', 'CASE_PRICE');

// While more products in the delta file
while (($product = fgetcsv($fh)) !== false) {

	// Record product status for later
	$status[$product[$headers['PRODUCT_STATUS']]][] = $product[$headers['STYLE#']];
	
	// Skip product with "Coming Soon" status
	if (in_array($product[$headers['PRODUCT_STATUS']], array('Coming Soon'))) {
		continue;
	}
	
	// Loop thru each row column
	foreach ($columns as $column) {
		// Append data array
		$data[] = $product[$headers[$column]];
	}
}

// Close file handle
fclose($fh);

if (!$data) {
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'No data to process in local delta file'.SINGLE_RETURN, FILE_APPEND);
	mail(EMAIL_ERROR, 'SanMar Process Delta Cron - No Data to Process - Severity: LOW', 'No data to process in local delta file'.DOUBLE_RETURN.'Check local delta file product statuses: '.DELTA_LOCAL_FILE);
	exit;
}

// Log all status values and skus
foreach ($status as $key => $sku) {
	$list = $key.' - '.implode(', ', array_unique($sku));
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.$list."\r\n", FILE_APPEND);
	$message .= $list.DOUBLE_RETURN;
}

// Email admin report of all product status
mail(EMAIL_REPORT, 'SanMar Process Delta Cron - Product Report for'.date('Y-m-d'), $message);

try {
	// Construct PDO connection to database
	$pdo = new PDO(DATABASE_HOST, DATABASE_USERNAME, DATABASE_PASSWORD);
	
	// Begin SQL insert statement
	$base = 'INSERT INTO SanMarDelta_CSV ('.implode(',', $columns).') values ';
	
	// Create values placeholder string
	$placeholders = '('.implode(',', array_fill(0, count($columns), '?')).')';
	
	// Chunkify data into limit sized inserts
	$chunks = array_chunk($data, count($columns)*DATABASE_INSERT_LIMIT);
	
	// Loop thru each chunk
	foreach ($chunks as $chunk) {
		// Prepare and execute insert statement
		$insert = $pdo->prepare($base.implode(',', array_fill(0, count($chunk)/count($columns), $placeholders)));
		$insert->execute($chunk);
		$insert->closeCursor();
	}

	// Log insert execution
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Inserted '.count(array_chunk($data, count($columns))).' rows into SanMarDelta_CSV'.SINGLE_RETURN, FILE_APPEND);
	
	// Prepare and execute stored procedure step 1
	$sp1 = $pdo->prepare('EXEC SanmarDeltaUpload_Step1');
	$sp1->execute();
	$sp1->closeCursor();
	// Log step 1 execution
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Completed stored procedure step 1'.SINGLE_RETURN, FILE_APPEND);

	// Prepare and execute stored procedure step 2
	$sp2 = $pdo->prepare('EXEC SanmarDeltaUpload_Step2');
	$sp2->execute();
	$sp2->closeCursor();
	// Log step 1 execution
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Completed stored procedure step 2'.SINGLE_RETURN, FILE_APPEND);
	
	// Get all product images
	$select = $pdo->query('SELECT newSKU as sku, product_sku_id_new as product_id, PRODUCT_IMAGE as image_url FROM SanMarSKU');
	$products = $select->fetchAll(PDO::FETCH_ASSOC);
	
	foreach ($products as $product) {
		$sizes = array(
			'small'  => ['filename' => 'sm_'.PM_SANMAR_CATALOG_ID.'_'.$product['product_id'].'.jpg', 'max' => IMAGE_SMALL],
			'medium' => ['filename' => 'md_'.PM_SANMAR_CATALOG_ID.'_'.$product['product_id'].'.jpg', 'max' => IMAGE_MEDIUM],
			'large'  => ['filename' => 'lg_'.PM_SANMAR_CATALOG_ID.'_'.$product['product_id'].'.jpg', 'max' => IMAGE_LARGE],
		);
		
		// Get image size
		list ($w, $h) = getimagesize($product['image_url']);
		
		// Open image
		$source = imagecreatefromjpeg($product['image_url']);
	
		// Loop thru each size
		foreach ($sizes as $size => $img) {
			// Restrict img size on largest dimension
			if ($w < $h) {
				// Calculate new width
				$width = $w * ($img['max'] / $h);
				$height = $img['max'];
			} else {
				// Calculate new height
				$height = $h * ($img['max'] / $w);
				$width = $img['max'];
			}
			
			// Create blank jpg for resizing
			$destination = imagecreatetruecolor($width, $height);

			// Attempt to resize image
			if (!imagecopyresampled($destination, $source, 0, 0, 0, 0, $width, $height, $w, $h)) {
				file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Failed to resize '.$size.' image for '.$product['sku'].SINGLE_RETURN, FILE_APPEND);
				$resize[$product['sku']][] = $size;
			}
			// Attempt to save resized image
			else if (!imagejpeg($destination, IMAGE_MOUNT.$img['filename'], IMAGE_QUALITY)) {
				file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Failed to save '.$size.' image for '.$product['sku'].SINGLE_RETURN, FILE_APPEND);
				$save[$product['sku']] = $size;
			}
			// Increment product image counter
			else {
				$i++;
			}
			
		}
		
		$sql = 'SELECT FROM tblProductImages WHERE product_sku_id = '.$product['product_id'].' AND catalog_id = '.PM_SANMAR_CATALOG_ID;
		$image = $pdo->query($sql);
		
		if (empty($image)) {
			$sql = 'INSERT INTO tblProductImages (supplier_id, catalog_id, product_sku_id, image_name, large_image_name, thumbnail_image_name, dtmUpdated) VALUES ('.PM_SANMAR_SUPPLIER_ID.', '.PM_SANMAR_CATALOG_ID.', '.$product['product_id'].', "'.$sizes['medium']['filename'].'", "'.$sizes['large_filename'].'", "'.$sizes['small']['filename'].'", GETDATE())';
		} else {
			$sql = 'UPDATE tblProductImages SET image_name = "'.$sizes['medium']['filename'].'", large_image_name = "'.$sizes['large']['filename'].'", thumbnail_image_name = "'.$sizes['small']['filename'].'", dtmUpdated = GETDATE() WHERE product_sku_id = '.$product['product_id'].' AND catalog_id = '.PM_SANMAR_CATALOG_ID;
		}
		
		$pdo->query($sql);
		
		// Commented out after database schema change
		//$sql = 'INSERT INTO tblImages (supplier_id, catalog_id, image_name, large_image_name, thumbnail_image_name, thumbnail_flag, dtmUpdated) VALUES ('.PM_SANMAR_SUPPLIER_ID.', '.PM_SANMAR_CATALOG_ID.', "'.$sizes['medium']['filename'].'", "'.$sizes['large']['filename'].'", "'.$sizes['small']['filename'].'", 0, getDate())';
		//$pdo->query($sql);
		
		//$image_id = $pdo->lastInsertId();
		//$sql = 'INSERT INTO tblImages_multi_join (image_id, supplier_id, catalog_id, product_sku_id) VALUES ('.$image_id.', '.PM_SANMAR_SUPPLIER_ID.', '.PM_SANMAR_CATALOG_ID.', '.$product['product_id'].')';
		//$pdo->query($sql);	
	}
	
	if (!empty($resize)) {
		foreach ($resize as $sku => $sizes) {
			$message .= $sku.': '.implode($sizes, ',').SINGLE_RETURN;
		}
		mail(EMAIL_ERROR, 'SanMar Process Delta Cron - Image Resize Failed - Severity: HIGH', 'Failed to resize product images for:'.DOUBLE_RETURN.$message);
	}
	
	if (!empty($save)) {
		foreach ($save as $sku => $sizes) {
			$message .= $sku.': '.implode($sizes, ',').SINGLE_RETURN;
		}
		mail(EMAIL_ERROR, 'SanMar Process Delta Cron - Image Save Failed - Severity: HIGH', 'Failed to save product images for:'.DOUBLE_RETURN.$message);
	}
	
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Updated '.$i.' product images'.SINGLE_RETURN, FILE_APPEND);

} catch (PDOException $e) {
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'PDO Exception: '.print_r($e->getMessage(), 1).SINGLE_RETURN, FILE_APPEND);
	mail(EMAIL_ERROR, 'SanMar Process Delta Cron - PDO Exception - Severity: HIGH', print_r($e->getMessage(), 1));
}

// Retrieve all delta files
$deltas = glob(DELTA_LOCAL_DIR.'*.csv');
foreach ($deltas as $file) {
	// Delete file older than 14 days
	if (time()-filemtime($file) >= DELTA_EXPIRATION) {
		file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Deleted old delta file '.$file.SINGLE_RETURN, FILE_APPEND);
		unlink($file);
	}
}
