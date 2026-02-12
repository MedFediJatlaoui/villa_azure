<?php
namespace Skb_Cife;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Base class for the Skyboot Custom Icons plugin.
 */
class Skb_Cife_Base {

    const MINIMUM_ELEMENTOR_VERSION = '3.0.0';
    const MINIMUM_PHP_VERSION      = '5.6';
    const PLUGIN_VERSION_OPTION_NAME = 'skb_cife_version';

    private static $_instance = null;

    /**
     * instance loader.
     *
     * @return Skb_Cife_Base
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'skb_cife_text_domain' ] );
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        add_action( 'admin_init', [ $this, 'handle_activation_redirect' ] );
    }

    /**
     * Runs on plugin activation.
     */
    public static function activate() {
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $admin_role->add_cap( 'skyboot_custom_icons' );
        }

        set_transient( 'skb_cife_activation_redirect', true, 120 );
        update_option( self::PLUGIN_VERSION_OPTION_NAME, SKB_CIFE_VERSION );
    }

    /**
     * Check plugin version and trigger redirect on update.
     */
    public function check_for_updates() {
        if ( ! is_admin() ) {
            return;
        }

        $current_version = get_option( self::PLUGIN_VERSION_OPTION_NAME, '1.0.0' );

        if ( version_compare( $current_version, SKB_CIFE_VERSION, '<' ) ) {
            set_transient( 'skb_cife_activation_redirect', true, 120 );
            update_option( self::PLUGIN_VERSION_OPTION_NAME, SKB_CIFE_VERSION );
        }

    }

    /**
     * Redirect to settings page after activation/update.
     */

    public function handle_activation_redirect() {
        if ( ! get_transient( 'skb_cife_activation_redirect' ) ) {
            return;
        }

        delete_transient( 'skb_cife_activation_redirect' );

        if ( wp_doing_ajax() || ( isset( $_GET['action'] ) && 'activate-multi' === sanitize_key( $_GET['action'] ) ) ) {
            return;
        }

        // Redirect based on Elementor presence.
        if ( did_action( 'elementor/loaded' ) ) {
            $redirect_url = admin_url( 'admin.php?page=skyboot_icons' );
        } else {
            $redirect_url = admin_url(); // Redirect to dashboard
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }



    /**
     * Load plugin textdomain for translations.
     */
    public function skb_cife_text_domain() {
        load_plugin_textdomain( 'skb_cife', false, dirname( plugin_basename( SKB_CIFE_PL_ROOT ) ) . '/languages/' );
    }

    /**
     * Initializes the plugin after Elementor is loaded.
     */
    public function init() {
        $this->check_for_updates();

        if ( ! did_action( 'elementor/loaded' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_for_missing_elementor' ] );
            return;
        }

        if ( ! version_compare( ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_for_minimum_elementor_version' ] );
            return;
        }

        if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_for_minimum_php_version' ] );
            return;
        }

        $this->include_files();
    }

    /**
     * Admin notice for minimum PHP version.
     */
    public function admin_notice_for_minimum_php_version() {
        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }

        $message = sprintf(
            esc_html__( '"%1$s" requires version %2$s or greater.', 'skb_cife' ),
            '<strong>' . esc_html__( 'PHP', 'skb_cife' ) . '</strong>',
            self::MINIMUM_PHP_VERSION
        );

        printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
    }

    /**
     * Admin notice if Elementor is missing.
     */
    public function admin_notice_for_missing_elementor() {
        // Load is_plugin_active only when needed.
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $elementor = 'elementor/elementor.php';

        if ( is_plugin_active( $elementor ) ) {
            if ( ! current_user_can( 'activate_plugins' ) ) {
                return;
            }

            $activation_url = wp_nonce_url( 'plugins.php?action=activate&plugin=' . $elementor, 'activate-plugin_' . $elementor );

            $message  = '<p>' . __( 'Skyboot - Custom Icons for Elementor is not working because you need to activate the Elementor plugin.', 'skb_cife' ) . '</p>';
            $message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', esc_url( $activation_url ), esc_html__( 'Activate Elementor Now', 'skb_cife' ) ) . '</p>';
        } else {
            if ( ! current_user_can( 'install_plugins' ) ) {
                return;
            }

            $install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ), 'install-plugin_elementor' );

            $message  = '<p>' . __( 'Skyboot - Custom Icons for Elementor is not working because you need to install the Elementor plugin.', 'skb_cife' ) . '</p>';
            $message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', esc_url( $install_url ), esc_html__( 'Install Elementor Now', 'skb_cife' ) ) . '</p>';
        }

        echo '<div class="error">' . wp_kses_post( $message ) . '</div>';
    }

    /**
     * Admin notice for minimum Elementor version.
     */
    public function admin_notice_for_minimum_elementor_version() {
        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }

        $message = sprintf(
            esc_html__( '"%1$s" requires version %2$s or greater.', 'skb_cife' ),
            '<strong>' . esc_html__( 'Elementor', 'skb_cife' ) . '</strong>',
            self::MINIMUM_ELEMENTOR_VERSION
        );

        printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
    }

    /**
     * Include all necessary plugin files.
     */
    public function include_files() {
        if ( is_admin() ) {
            require_once SKB_CIFE_PL_PATH . 'settings-api/class.settings-api.php';
            require_once SKB_CIFE_PL_PATH . 'settings-api/settings-fields.php';
        }

        require_once SKB_CIFE_PL_PATH . 'classes/class-scripts-manager.php';

        $icon_packs = [
            [ 'option' => 'brands_icon',         'file' => 'class-brands-icon.php',         'default' => 'off' ],
            [ 'option' => 'devicons_icon',       'file' => 'class-devicons-icon.php',       'default' => 'off' ],
            [ 'option' => 'elegant_icon',        'file' => 'class-elegant-icon.php',        'default' => 'on'  ],
            [ 'option' => 'elusive_icon',        'file' => 'class-elusive-icon.php',        'default' => 'off' ],
            [ 'option' => 'icofont_icon',        'file' => 'class-icofont-icon.php',        'default' => 'off' ],
            [ 'option' => 'icomoon_icon',        'file' => 'class-icomoon-icon.php',        'default' => 'off' ],
            [ 'option' => 'iconic_icon',         'file' => 'class-iconic-icon.php',         'default' => 'off' ],
            [ 'option' => 'ion_icon',            'file' => 'class-ion-icon.php',            'default' => 'off' ],
            [ 'option' => 'linearicons_icon',    'file' => 'class-linearicons-icon.php',    'default' => 'on'  ],
            [ 'option' => 'lineawesome_icon',    'file' => 'class-lineawesome-icon.php',    'default' => 'off' ],
            [ 'option' => 'line_icon',           'file' => 'class-line-icon.php',           'default' => 'off' ],
            [ 'option' => 'materialdesign_icon', 'file' => 'class-materialdesign-icon.php', 'default' => 'off' ],
            [ 'option' => 'open_iconic_icon',    'file' => 'class-open-iconic-icon.php',    'default' => 'off' ],
            [ 'option' => 'simpleline_icon',     'file' => 'class-simpleline-icon.php',     'default' => 'off' ],
            [ 'option' => 'themify_icon',        'file' => 'class-themify-icon.php',        'default' => 'on'  ],
        ];

        foreach ( $icon_packs as $pack ) {
            if ( 'on' === skb_cife_get_option( $pack['option'], 'skb_cife_manage_icon', $pack['default'] ) ) {
                require_once SKB_CIFE_PL_PATH . 'classes/icons/' . $pack['file'];
            }
        }
    }
}
