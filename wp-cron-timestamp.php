<?php

/**
Plugin Name: WP Cron Timestamp
Plugin URI: http://wordpress.org/plugins/wp-cron-timestamp
Description: Plugin to have WP Cron push a timestamp to a Prometheus Push Gateway service
Version: 1.0
Author: rossigee
Author URI: http://www.golder.org/
License: GPLv2
 */

require_once(dirname(__FILE__) . "/wp-cron-timestamp-options.php");

// Add timestamp logging every ten minutes
add_filter( 'cron_schedules', 'add_wp_cron_timestamp_interval' );
function add_wp_cron_timestamp_interval( $schedules ) {
  $schedules['wp_cron_timestamp'] = array(
    'interval' => 600,
    'display'  => esc_html__( 'Every ten minutes (timestamp)' ),
  );
  return $schedules;
}

// Consider active is URL is set, inactive otherwise.
$pushgw_url = get_option('wp_cron_timestamp_url');
$timestamp = wp_next_scheduled( 'wp_cron_timestamp' );
if ( $pushgw_url && ! $timestamp ) {
  wp_schedule_event( time(), 'wp_cron_timestamp', 'wp_cron_timestamp' );
}
if ( !$pushgw_url && $timestamp ) {
  wp_unschedule_event( $timestamp, 'wp_cron_timestamp' );
}

// Disable cron task if plugin is deactivated/removed
register_deactivation_hook( __FILE__, 'wp_cron_timestamp_deactivate' );
function wp_cron_timestamp_deactivate() {
  $timestamp = wp_next_scheduled( 'wp_cron_timestamp' );
  if($timestamp) {
    wp_unschedule_event( $timestamp, 'wp_cron_timestamp' );
  }
}

function _add_auth($process) {
  if(get_option('wp_cron_timestamp_auth_username')) {
    $username = get_option('wp_cron_timestamp_auth_username');
    $password = get_option('wp_cron_timestamp_auth_password');
    curl_setopt($process, CURLOPT_USERPWD, $username . ":" . $password);
  }
}

// Function to run on schedule which posts timestamp to gateway
add_action( 'wp_cron_timestamp', 'wp_cron_timestamp_exec' );
function wp_cron_timestamp_exec() {
  // Are we configured to run?
  $pushgw_url = get_option('wp_cron_timestamp_url');
  if(!$pushgw_url) {
    return;
  }

  // Prepare payload
  $urlparts = parse_url(get_site_url());
  $hostname = $urlparts['host'];
  $timestamp = wp_next_scheduled( 'wp_cron_timestamp' );
  $payload = "# TYPE wp_cron_timestamp gauge\n";
  $payload .= "# HELP Timestamp for last time WP Cron run.\n";
  $payload .= "wp_cron_timestamp ".$timestamp."\n\n";

  // Prepare request
  $uri = "/metrics/job/wp_cron_timestamp/instance/".$hostname;
  $process = curl_init($pushgw_url.$uri);
  curl_setopt($process, CURLOPT_HTTPHEADER, array('Content-Type: text/plain'));
  curl_setopt($process, CURLOPT_HEADER, 0);
  curl_setopt($process, CURLOPT_TIMEOUT, 30);
  curl_setopt($process, CURLOPT_POST, 1);
  curl_setopt($process, CURLOPT_POSTFIELDS, $payload);
  curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
  _add_auth($process);

  // Fire off request
  $return = curl_exec($process);
  $httpcode = curl_getinfo($process, CURLINFO_HTTP_CODE);
  curl_close($process);

  // Handle errors?
  if($httpcode < 200 && $httpcode > 299) {
    error_log("Unable to connect to push cron timestamp. Status code: $httpcode");
    return;
  }

  //error_log("Pushed cron timestamp. Status code: $httpcode");
}

function wp_cron_timestamp_test_connection() {
  // Are we configured to run?
  $pushgw_url = get_option('wp_cron_timestamp_url');
  if(!$pushgw_url) {
    return;
  }

  // Prepare request
  $uri = "/metrics";
  $process = curl_init($pushgw_url.$uri);
  curl_setopt($process, CURLOPT_HEADER, 0);
  curl_setopt($process, CURLOPT_TIMEOUT, 30);
  curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
  _add_auth($process);

  // Fire off request
  $return = curl_exec($process);
  $httpcode = curl_getinfo($process, CURLINFO_HTTP_CODE);
  curl_close($process);

  // Report on status of configuration
  if ($httpcode == 200) {
    ?>
    <div class="updated">
      <p>Test connection successful.</p>
    </div>
    <?php
  }
  elseif($httpcode == 401) {
    ?>
    <div class="notice notice-warning">
      <p>Authorisation failure. Please check username and password.</p>
    </div>
    <?php
  }
  else {
    ?>
    <div class="notice notice-warning">
      <p>Unexpected response. Status code: <?php echo $httpcode ?></p>
    </div>
    <?php
    #echo "<p>Body: $return</p>";
  }
}
