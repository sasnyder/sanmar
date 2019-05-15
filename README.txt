
SanMar Product Update
========================================================================================
Crons run in sequential order: request-delta.php, download-delta.php, process-delta.php. 
The delta requests can take up to 1hr to generate. Local delta files are overwritten to
avoid wasted storage. Processing of large datasets may also a take significant amount of time.


sanmar/crons
----------------------------------
	request-delta.php
		1. Send SOAP XML request to SanMar API to generate the Delta file.
		2. Send email notification and exits if
			a. Fails to connect to the API
			b. Fails to parse the XML response
		3. Log response message

	download-delta.php
		1. Send email notification and exits if
			a. Fails to connect to SanMar FTP server
			b. Fails to log in to SanMar FTP server
		2. Log error and exits if
			a. Remote delta file does NOT exist (checks file size)
			b. Fails to retrieve the remote delta file modification time
			c. Remote delta file modification date is NOT today
		3. Send email notification and exits if
			a. Fails to download remote delta file
		4. Log successful download
		
	process-delta.php
		1. Log error and exits if
			a. Local delta file does NOT exist
			b. Local delta file modification date is NOT today
		2. Send email notification and exits if
			a. Fails to open local delta file
		3. Iterate over products in local delta file
			a. Record product status for email notification
			b. Check product status and skip "coming soon"
			c. Record product column data
		4. Exit if no data
		5. Send email notification of product statuses for review
		6. Insert raw data into database
		7. Execute stored procedures 1 & 2
		8. Iterate over products for image update
			a. Check image dimensions
			b. Fetch image
			c  Preserve aspect ratio and max dimension
			d. Logs error if
				i.  Fails to resize product image
				ii. Fails to save product image
			e. Insert product image specs and product join in database
		9. Log number of updated product images
		
		
sanmar/delta
----------------------------------
	SanMarPI-Delta-215842.csv
		1. Local delta file


sanmar/logs
----------------------------------
	[Year]-[Month].txt (Example: 2018-06.txt)
		1. Monthly log file
