<?php

function wp_cron_timestamp_options_page() {
	global $wp_cron_timestamp_options;

	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.') );
	}

	if (isset($_POST['submit']) && isset($_POST['gatewayoptions'])) {
		check_admin_referer('wp-cron-timestamp-options');
		wp_cron_timestamp_options_update();
	}

	?>
<style type="text/css">
p.error {
	color: red;
}
</style>

<div class="wrap">

<h2>WP Cron Timestamp Settings</h2>

<form method="post" action="">
<?php
if(function_exists('wp_nonce_field') )
	wp_nonce_field('wp-cron-timestamp-options');
?>
<input type="hidden" name="gatewayoptions" value="true"/>

<h3>Push Gateway URL</h3>

<table class="form-table">
	<tr valign="top">
		<th scope="row">URL</th>
		<td>
      <input type="text" name="wp_cron_timestamp_url" value="<?php echo get_option('wp_cron_timestamp_url'); ?>" />
		</td>
  </tr>
</table>

<h3>Authentication</h3>

<p>Please supply a username/password for Basic Authentication to be applied. Leave blank for no authentication to apply.</p>

<table class="form-table">
	<tr valign="top">
		<th scope="row">Username</th>
		<td>
      <input type="text" name="wp_cron_timestamp_auth_username" value="<?php echo get_option('wp_cron_timestamp_auth_username'); ?>" />
		</td>
  </tr>
	<tr valign="top">
		<th scope="row">Password</th>
		<td>
      <input type="text" name="wp_cron_timestamp_auth_password" value="<?php echo get_option('wp_cron_timestamp_auth_password'); ?>" />
		</td>
  </tr>
</table>

<p class="submit">
	<input type="submit" name="submit" class="button-primary" value="<?php _e('Save Changes') ?>"/>
</p>

</form>
</div>
	<?php
}

function wp_cron_timestamp_options_update() {
	update_option('wp_cron_timestamp_url', sanitize_text_field($_REQUEST['wp_cron_timestamp_url']));
	update_option('wp_cron_timestamp_auth_username', sanitize_text_field($_REQUEST['wp_cron_timestamp_auth_username']));
	update_option('wp_cron_timestamp_auth_password', sanitize_text_field($_REQUEST['wp_cron_timestamp_auth_password']));

  wp_cron_timestamp_test_connection();

	?>
	<div class="updated">
	<p>Configuration updated successfully.</p>
	</div>
	<?php
}

function wp_cron_timestamp_admin_init() {
	// Default settings
	if(!get_option('wp_cron_timestamp_url')) {
		update_option('wp_cron_timestamp_url', '');
	}
	if(!get_option('wp_cron_timestamp_auth_username')) {
		update_option('wp_cron_timestamp_auth_username', '');
	}
	if(!get_option('wp_cron_timestamp_auth_password')) {
		update_option('wp_cron_timestamp_auth_password', '');
	}
}

function wp_cron_timestamp_admin_menu() {
	global $wp_cron_timestamp_options_page;

	$wp_cron_timestamp_options_page = add_options_page(
		__('WP Cron Timestamp', 'wp-cron-timestamp'),
		__('WP Cron Timestamp', 'wp-cron-timestamp'),
		'manage_options',
		__FILE__,
		'wp_cron_timestamp_options_page');
}

// Hooks to allow configuration settings and options to be set
add_action('admin_init', 'wp_cron_timestamp_admin_init');
add_action('admin_menu', 'wp_cron_timestamp_admin_menu');
