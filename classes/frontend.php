<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of install-class
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
		 * Enqueue the javascript into the footer of the page.
		 */

		function enqueue_scripts() {
			wp_enqueue_script('lm_client_side', 
							  plugins_url( 'javascript.js', dirname(__FILE__) ),
							  array('jquery'), 
							  '0.1', 
							  true);
		}

		add_action('wp_enqueue_scripts', 'enqueue_scripts');

		/*
		 * When serving a post or listing excerpts trigger the marking of prices in the
		 * page.
		 */

		function localmoney($content) {

			$content = preg_replace('/(\$[\d\,]*\.?\d*)/','<span class="currency">$1</span>',$content);

			// Done
			return $content;
		}

		add_filter('the_content', 'localmoney');
		add_filter('the_excerpt', 'localmoney');
		//add_filter('the_excerpt_rss', 'localmoney');

	}
 }



?>
