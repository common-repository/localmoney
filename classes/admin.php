<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of admin-class
 *
 * @author Stephen
 */

 class localmoney {

	 public function __construct() {

		/*
		 * Make it international
		 */ 

		load_plugin_textdomain('localmoney', false, basename( dirname( __FILE__ ) ) . '/languages' );

		/*
		 * Create 'Settings' link on the plugin page
		 */

		add_filter('plugin_action_links', 'action_links', 10, 2);

		function action_links($links, $file) {
			static $this_plugin;

			if (!$this_plugin) {
				$this_plugin = plugin_basename(__FILE__);
			}

			if ($file == $this_plugin) {
				$settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/options.php?page=localmoney">Settings</a>';
				array_unshift($links, $settings_link);
			}

			return $links;
		}

		/*
		 * On plugin activation set up scheduled event to make hourly requests for the
		 * latest currency rates.
		 */

		register_activation_hook(__FILE__, 'activation');
		add_action('get_remote_rates', 'get_remote_rates');

		function activation() {
			wp_schedule_event( time(), 'hourly', 'get_remote_rates');
		}

		/*
		 * Fetch the currency rates from openexchangerates.org and chaches the file
		 * on the local server.
		 */

		function get_remote_rates() {

			/*
			 * Set up name of file to send, options and API key.
			 */

			$file = 'latest.json';
			$options = get_option('localmoney_options');
			$appId = $options['api_key'];

			/*
			 * Use the WP HTTP API to get the data file
			 */

			$response = wp_remote_get("http://openexchangerates.org/api/{$file}?app_id={$appId}");
			$json = $response['body'];

			/*
			 * Save the object to the cache file
			 */

			file_put_contents($_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/localmoney/cache.txt',$json);
		}

		/*
		 * Select the location of the menu item
		 */

		add_action( 'admin_menu', 'menu_item' );

		/*
		 * Create the options page for admin level only and map to the function.
		 */

		function menu_item() {
			add_options_page( __('Local Money Options','localmoney'), __('LocalMoney','localmoney'), 'manage_options', 'localmoney', 'localmoney_options' );
			//				 $page_title,								$menu_title,					$capability,	$menu_slug, $function
		}

		/*
		 * Function to set up the options page
		 */

		function localmoney_options() {
			if ( !current_user_can( 'manage_options' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'localmoney' ) );
			}

			/*
			 * HTML to define the options page
			 */
?>

			<div class="wrap">
				<?php screen_icon(); ?>
				<h2>LocalMoney Settings</h2>

				<form method="post" action="options.php">

					<?php settings_fields( 'localmoney_options' ); ?>
					<?php //				$option_group?>
					<?php do_settings_sections( 'localmoney' ); ?>
					<?php //				$page must match $page in add_settings_section(). ?>
					<?php submit_button(); ?>

				</form>

				<p style="font-style: italic">
					<?php _e("Please note that the currency conversions supplied by the LocalMoney plugin are for guidance and entertainment value only and are not intended for trading purposes, ".
					"just like gardening implements are not intended for open heart surgery (I can't believe I have to tell people this!)",'localmoney'); ?>
				</p>
			</div>

<?php
		}

		/*
		 * Define the Options settings and controls
		 */

		/*
		 * Set up the settings, section and API field
		 */

		add_action('admin_init', 'admin_init');
		function admin_init(){
			register_setting( 'localmoney_options', 'localmoney_options', 'options_validate' );
			//					$option_group,		$option_name, $sanitize_callback 
			add_settings_section('api', __('API','localmoney'), 'section_html', 'localmoney');
			//					$id,	$title,					$callback,		$page
			add_settings_field('api_key', __('OpenExchangeRates API Key:','localmoney'), 'setting_string', 'localmoney', 'api');
			//					$id,		$title,											$callback,		$page,		$section, $args
		}

		/*
		 * Define the HTML for the API section
		 */

		function section_html() {
			_e('<p>Before the LocalMoney plugin will do anything you must head over to <a href="http://openexchangerates.org" target="_blank">openexchangerates.org</a> and get yourself an API key.</p>','localmoney');
			_e('It\'s OK, I\'ll wait…','localmoney');
		}

		/*
		 * HTML for the API key field
		 */

		function setting_string() {
			$options = get_option('localmoney_options');
			//						$option_group
			echo "<input id='api_key' name='localmoney_options[api_key]' size='40' type='text' value='{$options['api_key']}' />";
		}

		/*
		 * Input validation
		 */

		function options_validate($input) {
			$newinput['api_key'] = trim($input['api_key']);
			if (preg_match('/^[a-z0-9]{32}$/', $newinput['api_key']) !== true) {
				$file = 'latest.json';
				$response = wp_remote_get("http://openexchangerates.org/api/{$file}?app_id={$newinput['api_key']}");
				$json = $response['body'];
				$json_check = json_decode($json);
				if (isset($json_check->error)) {
					
					$error = $json_check->description;
					$error = preg_replace('|http(s?)://([a-z0-9./]*)|',"<a href='http$1://$2'>http$1://$2</a>",$error);
					$error = preg_replace('|([a-z]+[a-z0-9.]*@[a-z0-9.]*\.[a-z]{2,3})|',"<a href='mailto:$1'>$1</a>",$error);
					
					add_settings_error('general','settings_updated',	"There seems to be something wrong with your API key, '{$newinput['api_key']}'. The following error was returned:".
																		"<p style='font-weight:bold'>'".$error."'</p>".
																		"<p>It should be 32 characters long, alpha-numeric only. Your key has been saved but it won't do anything.</p>",'error');
				} elseif (isset($json_check->rates)) {
					// do nothing
				} else {
					add_settings_error('general','settings_updated',	"There seems to be something wrong with your API key, '{$newinput['api_key']}'. An unknown error occurred.",'error');
				}
			}
			return $newinput;
		}

		/*
		 * On deactivation removed scheduled event
		 */

		register_deactivation_hook(__FILE__, 'deactivation');

		function deactivation() {
			wp_clear_scheduled_hook('get_remote_rates');
		}

	}
 }


?>
