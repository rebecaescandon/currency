<?php

import();
/**
 * Implements XML import
 */
function import() {
  $provider = "https://wikitech.wikimedia.org/wiki/Fundraising/tech/Currency_conversion_sample?ctype=text/xml&action=raw";
  // TODO validate provider
  if (url_exists($provider)) {
    $mysqli = db_access();
    if ($mysqli) {
      import_currency($provider, $mysqli);
    }
  }
}

/**
 * Helper - Check if url exists XML
 */
function url_exists($url){
   $headers=get_headers($url);
   return stripos($headers[0],"200 OK")?true:false;
}

/**
 * Implements import in the database
 */
function import_currency($provider, $mysqli) {

  // log console
  echo 'Importing Currency Start ....' . "\n";
  // TODO log to file $log

  // read xml
  $xml = simplexml_load_file($provider);
  if ($xml === FALSE) {
    echo 'Failed Import ....' . "\n";
    exit();
  }
  else {
    // save to DB
    $date = date("Y-m-d H:i:s");
    foreach ($xml->children() as $conversion) {
      $currency = $conversion->currency;
      $rate = $conversion->rate;

      //mysqli_real_escape_string()
      $insert = $mysqli->prepare("INSERT INTO rates (currency, rate, currentdate) VALUES (?,?,?)");
      $insert->bind_param("sds", $currency , $rate, $date );
      $insert->execute();
    }

    // keep a history of imported files
    $day = date('Y-m-d');
    // create folder
    if(!is_dir('xml')){
      mkdir('xml');
    }
    copy($provider, 'xml/currency-'. $day);

    // log console
    echo 'Importing Currency End ....' . "\n";
    // TODO log to file $log
  }
}

/**
 * Helper - Connect to database
 */
function db_access(){

  /* Initial values */
  $mysql_hostname = "localhost";
  $mysql_user     = "";
  $mysql_password = "";
  $mysql_database = "currency";
  // TODO import config form file

  // new connection to the MySQL server
  $mysqli = new mysqli($mysql_hostname, $mysql_user, $mysql_password, $mysql_database);

  // log connection error
  if ($mysqli->connect_error) {
    echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    // TODO log to file
  }

  // TODO check if table exist or create

  return $mysqli;
}
