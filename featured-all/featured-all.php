<?php
/**
 * Plugin Name: Featured All
 * Plugin URI: https://example.com/
 * Description: Adds featured video support alongside featured images with admin management pages and frontend output controls.
 * Version: 1.4.0
 * Author: @Slawekreal
 * Text Domain: featured-all
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Tested up to: 8.3
 */
/*
 * Changelog 1.4.0
 * - Behebt Tab-Navigation und liefert einen No-JS-Fallback für Einstellungen.
 * - Korrigiert Player-Attribute inkl. Poster, controlsList und Overlay-Play.
 * - Stabilisiert Breiten-Slider, Seitenverhältnis und Fallback-Controls im Frontend.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Featured_All_Plugin' ) ) {
    /**
     * Main plugin loader.
     */
    class Featured_All_Plugin {
        public const VERSION = '1.4.0';

        public function __construct() {
            $this->define_constants();
            $this->load_dependencies();
            add_action( 'plugins_loaded', array( $this, 'init_classes' ) );
        }

        /**
         * Define plugin constants.
         */
        private function define_constants(): void {
            if ( ! defined( 'FEATUREDALL_PATH' ) ) {
                define( 'FEATUREDALL_PATH', plugin_dir_path( __FILE__ ) );
            }
            if ( ! defined( 'FEATUREDALL_URL' ) ) {
                define( 'FEATUREDALL_URL', plugin_dir_url( __FILE__ ) );
            }
            if ( ! defined( 'FEATUREDALL_VERSION' ) ) {
                define( 'FEATUREDALL_VERSION', self::VERSION );
            }
        }

        /**
         * Include required class files.
         */
        private function load_dependencies(): void {
            require_once FEATUREDALL_PATH . 'includes/class-featuredall-admin.php';
            require_once FEATUREDALL_PATH . 'includes/class-featuredall-frontend.php';
        }

        /**
         * Initialize admin and frontend classes.
         */
        public function init_classes(): void {
            if ( is_admin() ) {
                new FeaturedAll\Admin();
            }

            if ( ! is_admin() ) {
                new FeaturedAll\Frontend();
            }
        }
    }
}

new Featured_All_Plugin();
