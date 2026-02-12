<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;
class Skb_Cife_Settings_API_Fields {

    private $settings_api;

    function __construct() {
        $this->settings_api = new Skb_Cife_Settings_API;

        add_action( 'admin_init', array($this, 'admin_init') );
        add_action( 'admin_menu', array($this, 'admin_menu') );

        // add action genarel tab
        add_action( 'wsa_form_bottom_skb_cife_general', [ $this, 'skb_cife_general_tab_html_output' ] );
        add_action( 'wsa_form_bottom_skb_cife_update_info', [ $this, 'skb_cife_update_info_tab_html_output' ] );
    }

    function admin_init() {

        //set the settings
        $this->settings_api->set_sections( $this->get_settings_sections() );
        $this->settings_api->set_fields( $this->get_settings_fields() );

        //initialize settings
        $this->settings_api->admin_init();
    }

    function admin_menu() {

        // admin menu page
        add_menu_page(
            'skb_cife_admin_page',
            esc_html__( 'Skyboot Icons', 'skb_cife' ),
            esc_html__( 'Skyboot Icons', 'skb_cife' ),
            'skyboot_custom_icons',
            NULL,
            'dashicons-admin-generic',
            50
        );
        
        // admin sub menu page
        add_submenu_page(
            'skyboot_custom_icons', 
            esc_html__( 'Settings', 'skb_cife' ),
            esc_html__( 'Settings', 'skb_cife' ), 
            'manage_options', 
            'skyboot_icons', 
            array ( $this, 'plugin_page' ) 
        );


    }

    // Tab Nav Menu
    function get_settings_sections() {
        $sections = array(
            array(
                'id'    => 'skb_cife_general',
                'title' => __( 'General', 'skb_cife' )
            ),
            array(
                'id'    => 'skb_cife_manage_icon',
                'title' => __( 'Manage Icons', 'skb_cife' )
            ),
            array(
                'id'    => 'skb_cife_update_info',
                'title' => __( 'Update Info', 'skb_cife' )
            )
        );
        return $sections;
    }

    /**
     * Returns all the settings fields
     *
     * @return array settings fields
     */
    function get_settings_fields() {
        $settings_fields = array(

            // general tab
            'skb_cife_general' => array(),

            'skb_cife_manage_icon' => array(

                array(
                    'name'  => 'elegant_icon',
                    'label'  => __( 'Elegant Icon', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'on',
                    'class'=>'single_icon_wrapper',
                ),
                array(
                    'name'  => 'linearicons_icon',
                    'label'  => __( 'Linearicons', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'on',
                    'class'=>'single_icon_wrapper',
                ),
                array(
                    'name'  => 'themify_icon',
                    'label'  => __( 'Themify Icon', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'on',
                    'class'=>'single_icon_wrapper',
                ),
                array(
                    'name'  => 'simpleline_icon',
                    'label'  => __( 'Simple Line Icon', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'off',
                    'class'=>'single_icon_wrapper',
                ),
                array(
                    'name'  => 'line_icon',
                    'label'  => __( 'Line Icon', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'off',
                    'class'=>'single_icon_wrapper',
                ),

                array(
                    'name'  => 'ion_icon',
                    'label'  => __( 'Ionicons', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'off',
                    'class'=>'single_icon_wrapper',
                ),
                array(
                    'name'  => 'icofont_icon',
                    'label'  => __( 'Icofont', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'off',
                    'class'=>'single_icon_wrapper',
                ),
                array(
                    'name'  => 'lineawesome_icon',
                    'label'  => __( 'Line Awesome', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'off',
                    'class'=>'single_icon_wrapper',
                ),
                array(
                    'name'  => 'materialdesign_icon',
                    'label'  => __( 'Material Design Icons', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'off',
                    'class'=>'single_icon_wrapper',
                ),
                array(
                    'name'  => 'devicons_icon',
                    'label'  => __( 'Devicons Icons', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'off',
                    'class'=>'single_icon_wrapper',
                ),
                array(
                    'name'  => 'icomoon_icon',
                    'label'  => __( 'Icomoon Icons', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'off',
                    'class'=>'single_icon_wrapper',
                ),
                array(
                    'name'  => 'elusive_icon',
                    'label'  => __( 'Elusive Icons', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'off',
                    'class'=>'single_icon_wrapper',
                ),
                array(
                    'name'  => 'iconic_icon',
                    'label'  => __( 'Iconic Icons', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'off',
                    'class'=>'single_icon_wrapper',
                ),
                array(
                    'name'  => 'brands_icon',
                    'label'  => __( 'Brands Icon', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'off',
                    'class'=>'single_icon_wrapper',
                ),
                array(
                    'name'  => 'open_iconic_icon',
                    'label'  => __( 'Open Iconic', 'skb_cife' ),
                    'type'  => 'checkbox',
                    'default' => 'off',
                    'class'=>'single_icon_wrapper',
                ),



            )
        );

        return $settings_fields;
    }

    // admin page tab wrapper
    function plugin_page() {
        echo '<div class="wrap">';
        echo "<h2>" . __( 'Skyboot - Custom Icons for Elementor Settings', 'skb_cife' ) . "</h2>";
        $this->save_message();
        $this->settings_api->show_navigation();
        $this->settings_api->show_forms();
        echo '</div>';
    }

    // Saved Successfully Notification
    function save_message() {
        if( isset($_GET['settings-updated']) ) { ?>
            <div class="updated notice skb-notice is-dismissible"> 
                <p><strong><?php echo __('Successfully Settings Saved.', 'skb_cife') ?></strong></p>
            </div>
            <?php
        }
    }    

    /**
     * Get all the pages
     *
     * @return array page names with key value pairs
     */
    function get_pages() {
        $pages = get_pages();
        $pages_options = array();
        if ( $pages ) {
            foreach ($pages as $page) {
                $pages_options[$page->ID] = $page->post_title;
            }
        }

        return $pages_options;
    }

    /**
     * General tab output
     *
     * @return void General tab html markup output
     */
    function skb_cife_general_tab_html_output(){

    // List of all icon packs.
    $icon_packs = [
        [ 'name' => 'Brands Icon',           'count' => '934',  'link' => 'https://simpleicons.org' ],
        [ 'name' => 'Devicons Icons',        'count' => '191',  'link' => 'http://vorillaz.github.io/devicons/#/dafont' ],
        [ 'name' => 'Elegant Icon',          'count' => '360',  'link' => 'https://www.elegantthemes.com/blog/resources/elegant-icon-font' ],
        [ 'name' => 'Elusive Icons',         'count' => '303',  'link' => 'http://elusiveicons.com' ],
        [ 'name' => 'Icofont',               'count' => '2400+', 'link' => 'https://icofont.com' ],
        [ 'name' => 'Ico Moon',              'count' => '491',  'link' => 'https://icomoon.io/#preview-free' ],
        [ 'name' => 'Iconic Icons',          'count' => '172',  'link' => 'https://github.com/somerandomdude/Iconic' ],
        [ 'name' => 'Ion icons',             'count' => '696',  'link' => 'https://ionicons.com' ],
        [ 'name' => 'Line Awesome',          'count' => '2004', 'link' => 'https://icons8.com/line-awesome' ],
        [ 'name' => 'Line Icon',             'count' => '511',  'link' => 'https://lineicons.com' ],
        [ 'name' => 'Linear icons',          'count' => '170',  'link' => 'https://linearicons.com' ],
        [ 'name' => 'Material Design Icons', 'count' => '5346', 'link' => 'http://materialdesignicons.com' ],
        [ 'name' => 'Simple Line Icon',      'count' => '189',  'link' => 'https://simplelineicons.github.io' ],
        [ 'name' => 'Themify Icon',          'count' => '351',  'link' => 'https://themify.me/themify-icons' ],
        [ 'name' => 'Open Iconic',           'count' => '223',  'link' => 'https://useiconic.com/open' ],
    ];

    ob_start();
    ?>
    <div class="skb_cife_general_tab_html_output-wrapper">
        <div class="skb_cife_general_tab_html_output_inner skb-ovh">
            <div class="skb-genarel-tab-column skb-genarel-tab-column-1">
                <h3><?php echo esc_html__('Skyboot Custom Icons for Elementor - More than 14,300+ icons includes.', 'skb_cife'); ?></h3>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Icon Name', 'skb_cife'); ?></th>
                            <th><?php echo esc_html__('Number of Icons', 'skb_cife'); ?></th>
                            <th><?php echo esc_html__('Icon page', 'skb_cife'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $icon_packs as $pack ) : // Loop through array ?>
                        <tr>
                            <td>
                                <i class="dashicons dashicons-yes"></i>
                                <?php echo esc_html( $pack['name'] ); ?>
                            </td>
                            <td>
                                <span><?php echo esc_html( $pack['count'] ); ?></span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( $pack['link'] ); ?>" target="_blank">
                                    <i class="dashicons dashicons-admin-links"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="skb_cife-two-column">
                <div class="skb_cife_rate-us">
                    <h3><?php echo esc_html__( 'Who We Are', 'skb_cife' ); ?></h3>
                    <p><?php echo esc_html__( 'We are Skybootstrap, which specializes in WordPress plugins, HTML Templates, and provides high-quality blog posts for our readers. We recently launched our SaaS product named TLinky, offering URL Shortener, QR Code Generator, and Link In Bio Page Builder functionalities.', 'skb_cife' ); ?></p>
                    <hr/>
                    <h3><?php echo esc_html__( 'Our SaaS Product - TLinky', 'skb_cife' ); ?></h3>
                    <p><?php echo esc_html__( "We're excited to offer all our plugin users a 50% discount on all plans. Simply use the coupon code WP50 to get the discount.", 'skb_cife' ); ?></p>
                    <a href="<?php echo esc_url('https://tlinky.com/?utm_source=skb-icon-admin&utm_medium=skb-free-plugin'); ?>" target="_blank">
                        <img src="<?php echo esc_url( SKB_CIFE_ASSETS . 'images/tlinky-link-management-tools.png' ); ?>" alt="<?php echo esc_attr__('TLinky - Link Management Platform', 'skb_cife'); ?>">
                    </a>
                    <hr/>
                    <a href="<?php echo esc_url('https://wordpress.org/plugins/skyboot-custom-icons-for-elementor'); ?>" target="_blank">
                        <img src="<?php echo esc_url( SKB_CIFE_ASSETS . 'images/skb-rate-us.png' ); ?>" alt="<?php echo esc_attr__('Rate us on wordpress.org', 'skb_cife'); ?>">
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
    echo ob_get_clean();
    }  

    /**
     * Update info tab output
     *
     * @return void Update info html markup output
     */
    function skb_cife_update_info_tab_html_output(){
        ob_start();
        ?>
            <div class="skb_cife_update_info_html_output-wrapper">
                <div class="skb_cife-change-log-wrapper skb-ovh">
                    <div class="skb_cife-change-log-inner">
                        <div class="skb_cife-change-log">
                            <div class="skb_cife-single-change-log">
                                <h3><?php echo esc_html('Change Log', 'skb_cife'); ?></h3>
                            </div>
                        </div>
                        <div class="skb_cife-change-log-table-wrapper">
                            <table class="skb_cife-change-log-table">
                            <tr>
                                <td>
                                    <label><?php echo esc_html('1.1.0 (Date: 25-06-2025)', 'skb_cife'); ?></label>
                                    <ul>
                                        <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Added: 300+ Icofont Duotone New Icons', 'skb_cife'); ?></li>
                                        <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible: Compatibility check with latest version of Elementor', 'skb_cife'); ?></li>
                                        <li><i class="dashicons dashicons-yes"></i><?php echo esc_html__('Improved: Code structure for maintainability and increase performance .', 'skb_cife'); ?></li>
                                        <li><i class="dashicons dashicons-yes"></i><?php echo esc_html__('Fix: Some minor issues fixed.', 'skb_cife'); ?></li>
                                    </ul>
                                </td>
                            </tr>                                 
                                <tr>
                                    <td>
                                        <label><?php echo esc_html('1.0.9 (Date: 28-04-2025)', 'skb_cife'); ?></label>
                                        <ul>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with WordPress 6.8', 'skb_cife'); ?></li>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with latest version of Elementor', 'skb_cife'); ?></li>
                                        </ul>
                                    </td>
                                </tr>                                 
                                <tr>
                                    <td>
                                        <label><?php echo esc_html('1.0.8 (Date: 02-03-2024)', 'skb_cife'); ?></label>
                                        <ul>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with WordPress 6.4', 'skb_cife'); ?></li>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with latest version of Elementor', 'skb_cife'); ?></li>
                                        </ul>
                                    </td>
                                </tr>                                 
                                <tr>
                                    <td>
                                        <label><?php echo esc_html('1.0.7 (Date: 25-08-2023)', 'skb_cife'); ?></label>
                                        <ul>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with WordPress 6.3', 'skb_cife'); ?></li>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with latest version of Elementor', 'skb_cife'); ?></li>
                                        </ul>
                                    </td>
                                </tr>                                 
                                <tr>
                                    <td>
                                        <label><?php echo esc_html('1.0.6 (Date: 23-05-2023)', 'skb_cife'); ?></label>
                                        <ul>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with WordPress 6.2.2', 'skb_cife'); ?></li>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with latest version of Elementor', 'skb_cife'); ?></li>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Improved dashboard css', 'skb_cife'); ?></li>
                                        </ul>
                                    </td>
                                </tr>                                   
                                <tr>
                                    <td>
                                        <label><?php echo esc_html('1.0.5 (Date: 07-06-2022)', 'skb_cife'); ?></label>
                                        <ul>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with WordPress 6.0', 'skb_cife'); ?></li>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with latest version of Elementor', 'skb_cife'); ?></li>
                                        </ul>
                                    </td>
                                </tr>                                 
                                <tr>
                                    <td>
                                        <label><?php echo esc_html('1.0.4 (Date: 06-04-2022)', 'skb_cife'); ?></label>
                                        <ul>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with latest version of WordPress and Elementor', 'skb_cife'); ?></li>
                                        </ul>
                                    </td>
                                </tr>                                 
                                <tr>
                                    <td>
                                        <label><?php echo esc_html('1.0.3 (Date: 29-07-2021)', 'skb_cife'); ?></label>
                                        <ul>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with latest version of WordPress (5.8)', 'skb_cife'); ?></li>
                                        </ul>
                                    </td>
                                </tr> 
                                <tr>
                                    <td>
                                        <label><?php echo esc_html('1.0.2 (Date: 17-03-2021)', 'skb_cife'); ?></label>
                                        <ul>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with latest version', 'skb_cife'); ?></li>
                                        </ul>
                                    </td>
                                </tr> 
                                <tr>
                                    <td>
                                        <label><?php echo esc_html('1.0.1 (Date: 16-12-2020)', 'skb_cife'); ?></label>
                                        <ul>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Compatible with latest version', 'skb_cife'); ?></li>
                                        </ul>
                                    </td>
                                </tr>                            
                                <tr>
                                    <td>
                                        <label><?php echo esc_html('1.0.0 (Date: 07-06-2020)', 'skb_cife'); ?></label>
                                        <ul>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('Initial release', 'skb_cife'); ?></li>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('15 Icon font package includes', 'skb_cife'); ?> </li>
                                            <li><i class="dashicons dashicons-yes"></i><?php echo esc_html('14055+ icons includes', 'skb_cife'); ?> </li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>   
                        </div>                         
                    </div>
                    <div class="skb_cife-two-column">
                        <div class="skb_cife_rate-us">
                            <a href="<?php echo esc_url('https://wordpress.org/plugins/skyboot-custom-icons-for-elementor'); ?>" target="_blank">
                                <img src="<?php echo SKB_CIFE_ASSETS; ?>images/skb-rate-us.png" alt="<?php echo esc_attr('Rate us on wordpress.org'); ?>">
                         
                            </a>
                        </div>
                    </div>


                </div>

            </div>
        <?php
        echo ob_get_clean();
    }    

}
new Skb_Cife_Settings_API_Fields();

