<?php

$opts = getopt("rhc:");

foreach (array_keys($opts) as $opt) switch ($opt) {
  case 'r':
    import();
    break;
  case 'c':
    convert($opts['c']);
    break;
  case 'h':
    echo help_text();
    break;
}
echo "Thanks...";


/**
 * Implements XML import cli
 */
function import() {
  $provider = "https://wikitech.wikimedia.org/wiki/Fundraising/tech/Currency_conversion_sample?ctype=text/xml&action=raw";
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
  echo 'Import Currency Start ....' . "\n";
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
    echo 'Import Currency End ....' . "\n";
    // TODO log to file $log
  }
}

/**
 * Helper - Connect to database
 */
function db_access(){

  /* Initial values */
  $mysql_hostname = "localhost";
  $mysql_user     = "currency";
  $mysql_password = "wehDdGLUL7UvH5dy";
  $mysql_database = "currency";
  // TODO import config form file

  // new connection to the MySQL server
  $mysqli = new mysqli($mysql_hostname, $mysql_user, $mysql_password, $mysql_database);

  // log connection error
  if ($mysqli->connect_error) {
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
    exit();
    // TODO log to file
  }

  // TODO check if table exist or create

  return $mysqli;
}

/**
 * Implements Currency Conversion cli
 */
function convert($args) {
  $mysqli = db_access();
  if ($mysqli) {
    $values = explode(",", $args);
    $output = convert_currency($values, $mysqli);
    echo implode(",", $output) . "\n";
  }
}

/**
 * Implements Currency Conversion of an array
 */
function convert_currency($items, $mysqli) {

  $output = array();
  foreach ($items as $item) {
    // TODO user regular expression (lowercase)
    $value = explode(" ", $item);
    $change = get_change($value[0], $value[1], $mysqli);
    if ($change){
      $output[] = "USD " . $change;
    }
    else {
      $output[] = "Not found rate for " . $value[0];
    }
  }

  return $output;
}

/**
 * Returns change value in USD
 */
function get_change($currency, $amount, $mysqli) {

  $change = 0;

  if ($query = $mysqli->prepare("SELECT rate FROM rates WHERE currency = ? ORDER BY currentdate DESC LIMIT 1")) {

    $query->bind_param("s", $currency);
    $query->execute();
    $query->bind_result($rate);
    $query->fetch();

    if ($rate){
      // Calculate USD change
      $change = $amount * $rate;
    }
  }

  return $change;
}

  function help_text(){
    $output = '';
    $output .= "--------- Usage ---------" . "\n";
    $output .= "      -r : Run Importer " . "\n";
    $output .= "      -c [values]: Convert values enter to USD" . "\n";
    $output .= "                   Ex: -c 'CZK 62.5'" . "\n";
    $output .= "                   Ex: -c 'JPY 5000,CZK 62.5'" . "\n";
    $output .= "------------------" . "\n";

    return $output;
 }
