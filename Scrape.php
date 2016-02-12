<?php
    require_once('EmailExtractor.php');

    error_reporting(~0);
    ini_set('display_errors', 1);

    $DB_USER = 'root'; 
    $DB_PASSWORD = 'root'; 
    $DB_HOST = 'localhost'; 
    $DB_NAME = 'rocket'; 
    $dbc = mysql_connect ($DB_HOST, $DB_USER, $DB_PASSWORD) or $error = mysql_error(); 
    mysql_select_db($DB_NAME) or $error = mysql_error(); 
    if($error){ die($error);} 

    // clean table data in DB (for testing purposes)
    mysql_query("TRUNCATE TABLE emails"); 
    mysql_query("TRUNCATE TABLE searchedUrls"); 
    mysql_query("TRUNCATE TABLE urlsToBeSearched"); 

    $scraperInstance = new EmailScraper; 
    $url = 'https://www.warriorforum.com';
    $scraperInstance->setStartURL($url); 
    $scraperInstance->setOriginalURL($url); 
    $scraperInstance->startScraping(); 
?> 