<?php

include dirname(__FILE__).'/../init.php';

// Log start of cron
file_put_contents(LOG_FILE, SINGLE_RETURN.date('Y-m-d H:i:s').LOG_INDENT.'REQUEST DELTA CRON'.SINGLE_RETURN, FILE_APPEND);

// Create the SOAP XML request for Delta file
$req = <<<XML
	<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:impl="http://impl.webservice.integration.sanmar.com/">
	   <soapenv:Header/>
	   <soapenv:Body>
	      <impl:getProductDeltaInfo>
	         <arg0>
	            <sanMarCustomerNumber>%s</sanMarCustomerNumber>
	            <sanMarUserName>%s</sanMarUserName>
	            <sanMarUserPassword>%s</sanMarUserPassword>
	         </arg0>
	      </impl:getProductDeltaInfo>
	   </soapenv:Body>
	</soapenv:Envelope>
XML;

// Create stream context for SOAP request
$context = stream_context_create(array(
	'http' => array(
		'method' => 'POST',
		'header' => 'Content-Type: text/xml',
		'content' => sprintf($req, SOAP_CUSTOMER_NUMBER, SOAP_USERNAME, SOAP_PASSWORD)
	)
));

// Attempt to connect to API
if (!$res = file_get_contents(SOAP_WSDL, false, $context)) {
	// Log connection error
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Failed to connect to SanMar API'.SINGLE_RETURN, FILE_APPEND);
	mail(EMAIL_ERROR, 'SanMar Request Delta Cron - API Connection Failed - Severity: HIGH', 'Failed to connect to SanMar API'.DOUBLE_RETURN.'Check API connection parameters: '.print_r($context, 1));
	exit;
}

// Attempt to parse XML
if (($xml = simplexml_load_string($res)) === false) {
	// Log parsing error
	file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'Failed to parse XML response from SanMar API'.SINGLE_RETURN, FILE_APPEND);
	mail(EMAIL_ERROR, 'SanMar Request Delta Cron - API XML Parsing Failed - Severity: HIGH', 'Failed to parse XML response from SanMar API'.DOUBLE_RETURN.'Check API response: '.$res);
	exit;
}

// Log Delta response message
file_put_contents(LOG_FILE, date('Y-m-d H:i:s').LOG_INDENT.'SanMar API response: '.$xml->xpath('//return')[0]->message->{0}.SINGLE_RETURN, FILE_APPEND);
