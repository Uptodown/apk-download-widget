<?php

/*
Plugin Name: Uptodown APK Download Widget
Plugin URI: http://www.uptodown.com
Description: Add information about any Android app right onto your blog
Version: 0.1.10
Author: Uptodown
Author URI: http://www.uptodown.com
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

class UptodownWPShortcode
{
    private $options;
    private $shortcode_exist = false;

    public function __construct() {
        $this->check_options();
        add_shortcode('utd-widget', array($this,'shortcode_translator'));
        add_action('wp_enqueue_scripts', array($this,'enqueue_script'));
        add_filter('the_content',array($this,'setWidgetByPSIDs'));
    }

    public function check_options() {
        $options = get_option('utd_widget');
        if(empty($options)) {
            $options = array(
                'autopsids' => 1
            );
            add_option('utd_widget',$options);
        }
        $this->options = $options;
    }

    public function shortcode_translator($params) {
        $opts = shortcode_atts(array(
            'packagename' => '',
        ), $params);

        $lang = $this->getAvailableLanguage(substr(get_bloginfo('language'),0,2));
        update_option('utd_widget_lang', $lang);

        if(empty($opts['packagename'])) {
            return '';
        } else {
            $shortcode_exist = true;
            if(is_string($opts['packagename']) && trim($opts['packagename']) !== '' && preg_match('/^[a-zA-Z]\w*(\.\w+)+$/', $opts['packagename'])) {
                return '<div class="utd_widget" data-packagename="' . $opts['packagename'] . '"></div>';
            }
            return '';
        }
    }

    public function getAvailableLanguage($lang) {
        $default = 'en';
        if(empty($lang)) {
            return $default;
        }
        $dictionary = array(
            'en'=>'en',
            'es'=>'es',
            'br'=>'br',
            'pt'=>'br',
            'fr'=>'fr',
            'de'=>'de',
            'it'=>'it',
            'cn'=>'cn',
            'zh'=>'cn',
            'jp'=>'jp',
            'ar'=>'ar',
            'ru'=>'ru',
            'in'=>'in',
            'ko'=>'ko',
            'kr'=>'ko',
            'tr'=>'tr',
            'id'=>'id',
            'th'=>'th',
        );
        $real = $dictionary[$lang];
        if(empty($real)) {
            return $default;
        } else {
            return $real;
        }
    }

    public function enqueue_script() {
        wp_enqueue_script('utd-widget-js','//api.uptodown.com/' . get_option('utd_widget_lang','en') . '/widget/nopw/widget.js');
    }

    public function setWidgetByPSIDs($content) {
        if(!empty($this->options['autopsids']) && !has_shortcode($content,'utd-widget')) {
            if($appids=$this->getPSIDs($content)) {
                $widgets = '';
                foreach($appids as $appid) {
                    if(is_string($appid) && trim($appid) !== '' && preg_match('/^[a-zA-Z]\w*(\.\w+)+$/', $appid)) {
                        $widgets .= '<div class="utd_widget" data-packagename="' . $appid . '"></div>';
                    }
                }
                $content .= $widgets;
                $widget_appears = true;
            }
        }
        return $content;
    }

    public function getPSIDs($article) {

        if(stripos($article,'play.google.com/store/apps/details') === FALSE){
            return false;
        }

        require_once('simplehtmldom/simple_html_dom.php');
        $appids = array();
        $text = new simple_html_dom();
        $text->load($article);
        $img = $text->find('img',0);
        if(!empty($img) && !empty($img->src)) {
            $screen = $img->src;
        }
        $links = $text->find('a');
        foreach($links as $link) {
            if(!empty($link->href) && strpos($link->href,'play.google.com/store/apps/details')!==false && !is_bool(strpos($link->href,'?'))) {
                $query_string = substr($link->href,strpos($link->href,'?')+1);
                parse_str($query_string, $params);
                if(!in_array($params['id'],$appids)) {
                    $appids[] = $params['id'];
                }
            }
        }
        return $appids;
    }
}

class UptodownWPShortcodeSettings
{
    private $options;

    public function __construct() {
        $this->check_options();
        add_action('admin_menu', array($this,'add_plugin_page'));
        add_action('admin_init', array($this,'page_init'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this,'my_plugin_action_links'));
    }

    public function check_options() {
        if (isset($_POST['utd_widget_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['utd_widget_nonce'])), 'utd_widget_action')) {
            if (isset($_POST['utd_widget_options'])) {
                $options = array_map('sanitize_text_field', wp_unslash($_POST['utd_widget_options']));

                if (!isset($options['autopsids'])) {
                    $options['autopsids'] = 0;
                } else {
                    $options['autopsids'] = absint($options['autopsids']);
                }

                update_option('utd_widget', $options);
                $this->options = get_option('utd_widget');
            }
        } else {
            $options = get_option('utd_widget');

            if (empty($options)) {
                $options = array(
                    'autopsids' => 1,
                );
                add_option('utd_widget', $options);
            }

            $this->options = $options;
        }
    }


    public function my_plugin_action_links($links) {
        array_unshift($links,'<a href="'. esc_url( get_admin_url(null, 'options-general.php?page=uptodown-widget-settings') ) .'">Settings</a>');
        return $links;
    }

    public function add_plugin_page() {
        add_options_page( // add_menu_page
            'Uptodown Widget',
            'Uptodown Widget',
            'manage_options',
            'uptodown-widget-settings',
            array($this,'create_admin_page')
        );
    }

    public function create_admin_page() {
        $this->options = get_option('utd_widget');
        ?>
        <div class="wrap">
            <form method="post">
                <?php
                settings_fields( 'utd_widget_option_group' );
                do_settings_sections( 'uptodown-widget-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            'utd_widget_option_group', // Option group
            'utd_widget', // Option name
            null // Sanitize
        );
        add_settings_section(
            'setting_section_id', // ID
            'Uptodown Widget Settings', // Title
            null, // Callback
            'uptodown-widget-settings' // Page
        );
        add_settings_field(
            'autopsids',
            'Autodetect Play Store links?',
            array( $this, 'autopsids_callback' ),
            'uptodown-widget-settings',
            'setting_section_id'
        );
    }

    public function autopsids_callback() {
        $checked = !empty($this->options['autopsids']) ? 'checked' : '';
        echo '<input type="checkbox" id="' . esc_attr('autopsids') . '" name="' . esc_attr('utd_widget_options[autopsids]') . '" value="1" ' . esc_attr($checked) . ' />';
    }
}

if(is_admin()) {
    new UptodownWPShortcodeSettings();
} else {
    new UptodownWPShortcode();
}

?>
