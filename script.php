<?php

/**
  * Cloud Academy Labs
  *
  * This CLI script tests connectivity to an AWS ElastiCache Memcached cluster.
  *
  * @Provider: Amazon Web Services
  * @Service: ElastiCache
  * @Updated: 2026
  * @See: https://docs.aws.amazon.com/AmazonElastiCache/latest/mem-ug/AutoDiscovery.html
  *
  */

//------------------------------- UTILS -------------------------------
error_reporting(E_ERROR | E_WARNING | E_PARSE);

function genRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

class CliMessages {
  private $stderr = null;
  private $stdout = null;
  public function __construct() {
    $this->stdout = fopen("php://stdout", "w");
    $this->stderr = fopen("php://stderr", "w");
  }
  public function __destruct(){
    fclose($this->stdout);
    fclose($this->stderr);
  }
  public function send_error_msg($msg) {
    $msg = "\033[0;31m[ERROR] $msg\033[0m\n";
    fwrite($this->stderr, $msg);
  }
  public function send_warn_msg($msg) {
    $msg = "\033[1;35m[WARN] $msg\033[0m\n";
    fwrite($this->stderr, $msg);
  }
  public function send_success_msg($msg) {
    $msg = "\033[1;34m[OK] $msg\033[0m\n";
    fwrite($this->stderr, $msg);
  }
  public function send_info_msg($msg) {
    $msg = "\033[0;36m$msg\033[0m\n";
    fwrite($this->stderr, $msg);
  }
  public function send_welcome_msg($script_name, $description) {
    $ca = <<<EOF
\033[0;37m   ___ _                 _     _                 _                       
  / __\ | ___  _   _  __| |   /_\   ___ __ _  __| | ___ _ __ ___  _   _  
 / /  | |/ _ \| | | |/ _` |  //_\\\ / __/ _` |/ _` |/ _ \ '_ ` _ \| | | | 
/ /___| | (_) | |_| | (_| | /  _  \ (_| (_| | (_| |  __/ | | | | | |_| | 
\____/|_|\___/ \__,_|\__,_| \_/ \_/\___\__,_|\__,_|\___|_| |_| |_|\__, | \033[0m
\033[0;32m https://cloudacademy.com/labs/                               LABS \033[0m\033[0;37m|___/  \033[0m

 $script_name

 $description
------------------------------------------------------------------------

EOF;
    fwrite($this->stdout, $ca);
  }
}
//----------------------------- END UTILS -----------------------------


$CliMessages = new CliMessages();
$CliMessages->send_welcome_msg(
  "AWS ElastiCache :: Connection Tester Script",
  "This CLI script tests connectivity to an AWS ElastiCache Memcached\n " .
  "cluster using the configuration endpoint for node discovery."
);

if (!class_exists('Memcached')) {
  $CliMessages->send_error_msg(
    "PHP Memcached extension is not installed!\n\t" .
    "Please check the lab documentation and try again.\n"
  );
  exit(1);
} else {
  $CliMessages->send_success_msg("PHP Memcached extension is installed!\n");
}

/* Fetch and check the passed arguments */
$options = getopt("e::p::", array("endpoint::", "port::"));
$server_endpoint = isset($options['endpoint']) ? $options['endpoint'] : (isset($options['e']) ? $options['e'] : null);
$server_port = isset($options['port']) ? $options['port'] : (isset($options['p']) ? $options['p'] : 11211);

if (empty($server_endpoint)) {
  $CliMessages->send_error_msg(
    "You MUST specify the cluster endpoint!\n\t" .
    "Usage: php elasticache-client.php --endpoint=your.endpoint.here.cache.amazonaws.com\n"
  );
  exit(1);
}

$server_endpoint_parts = explode(':', $server_endpoint);
$server_endpoint = $server_endpoint_parts[0];
$server_port = isset($server_endpoint_parts[1]) ? $server_endpoint_parts[1] : $server_port;

$CliMessages->send_info_msg(
  "Cluster endpoint: $server_endpoint\n" .
  "Cluster port: $server_port\n"
);

/**
 * Connect to ElastiCache using the configuration endpoint.
 * ElastiCache's DNS automatically routes connections across all
 * available cluster nodes, enabling transparent auto-discovery
 * without requiring a special client library.
 */

$CliMessages->send_info_msg("Trying to connect to the Memcached cluster...");

try {
  $client = new Memcached();
  $client->addServer($server_endpoint, $server_port);

  // Verify connection with a test key
  if ($client->set('connCK_' . genRandomString(5), 'OK', 1)) {
    $CliMessages->send_success_msg("Connected to $server_endpoint cluster!\n");
  } else {
    $CliMessages->send_error_msg(
      "Cannot connect to the $server_endpoint Memcached cluster!\n\t" .
      "Please check the Security Group rules and try again.\n"
    );
    exit(1);
  }
} catch (Exception $e) {
  $CliMessages->send_error_msg("Connection failed: " . $e->getMessage() . "\n");
  exit(1);
}

// Write 100 keys to the cluster
$CliMessages->send_info_msg("Trying to write data to $server_endpoint:");
for ($i = 0; $i < 100; $i++) {
  try {
    $result = $client->set('cloudlabs_' . $i, 'ElasticacheIsGreat! #' . $i, 60);
    if ($result) {
      if ($i % 10 == 0 && $i != 0) {
        $CliMessages->send_success_msg("cloudlabs_" . ($i - 10) . " to cloudlabs_$i keys written.");
      }
    } else {
      $CliMessages->send_error_msg("Cannot write cloudlabs_$i key.\n");
      exit(1);
    }
  } catch (Exception $e) {
    $CliMessages->send_error_msg("Cannot write cloudlabs_$i key. Error: " . $e->getMessage() . "\n");
    exit(1);
  }
}

// Read back 10 keys
$CliMessages->send_info_msg("\nTrying to read stored values from $server_endpoint:");
for ($i = 0; $i < 10; $i++) {
  try {
    $storedVal = $client->get('cloudlabs_' . $i);
    $CliMessages->send_success_msg("\tcloudlabs_$i = $storedVal.");
  } catch (Exception $e) {
    $CliMessages->send_error_msg("Cannot read cloudlabs_$i key. Error: " . $e->getMessage() . "\n");
    exit(1);
  }
}

$CliMessages->send_info_msg("\nWell done, you are all set!\n");