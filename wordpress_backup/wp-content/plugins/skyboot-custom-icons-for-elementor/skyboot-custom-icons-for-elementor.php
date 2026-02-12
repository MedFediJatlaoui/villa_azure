<?php
/**
 * Plugin Name: Skyboot Custom Icons for Elementor
 * Description: Skyboot Custom Icons for Elementor expands your Elementor icon library with 14,300+ icons from 15 packs, fully customizable in Elementor's editor. 
 * Plugin URI:  https://skybootstrap.com/custom-icons-for-elementor/
 * Version:     1.1.0
 * Author:      Skybootstrap
 * Author URI:  https://skybootstrap.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: skb_cife
 * Domain Path: /languages
**/

	// Exit if accessed directly
	if ( ! defined( 'ABSPATH' ) ) exit; 

	// Define Useful Contastant
	define( 'SKB_CIFE_VERSION', '1.1.0' );
	define( 'SKB_CIFE_PL_ROOT', __FILE__ );
	define( 'SKB_CIFE_PL_URL', plugin_dir_url( SKB_CIFE_PL_ROOT ) );
	define( 'SKB_CIFE_PL_PATH', plugin_dir_path( SKB_CIFE_PL_ROOT ) );
	define( 'SKB_CIFE_PLUGIN_BASE', plugin_basename( SKB_CIFE_PL_ROOT ) );
	define( 'SKB_CIFE_ASSETS', trailingslashit( SKB_CIFE_PL_URL . 'assets' ) );

// Include Base class and Helper Fuctions
require_once SKB_CIFE_PL_PATH . 'includes/class-base.php';
require_once SKB_CIFE_PL_PATH . 'includes/helper-functions.php';

/**
 * Register the activation hook.
 *
 * When the plugin is activated, call the 'activate' method of our base class.
 */
register_activation_hook( SKB_CIFE_PL_ROOT, [ 'Skb_Cife\Skb_Cife_Base', 'activate' ] );
 
/**
 * The main instance of the plugin.
 */
\Skb_Cife\Skb_Cife_Base::instance();