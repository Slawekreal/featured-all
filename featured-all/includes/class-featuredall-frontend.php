<?php
/**
 * Frontend functionality for Featured All.
 */

namespace FeaturedAll;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Frontend
 */
class Frontend {
    private string $option_name = 'featuredall_settings';

    public function __construct() {
        add_filter( 'the_content', array( $this, 'inject_video_below_title' ) );
        add_filter( 'body_class', array( $this, 'add_body_class' ) );
        add_filter( 'the_title', array( $this, 'inject_video_above_title' ), 9, 2 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Get settings with defaults.
     */
    private function get_settings(): array {
        $defaults = array(
            'post_types'              => array( 'post' ),
            'position'                => 'below_title',
            'hide_featured_image'     => 0,
            'featured_image_selector' => '.post-image, .post-thumbnail',
        );

        $settings = get_option( $this->option_name, array() );

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Determine whether we should render on current post.
     */
    private function should_render(): bool {
        if ( ! is_singular() || is_admin() ) {
            return false;
        }

        $post_type = get_post_type();
        $settings  = $this->get_settings();
        if ( ! $post_type || ! in_array( $post_type, $settings['post_types'], true ) ) {
            return false;
        }

        $url   = get_post_meta( get_the_ID(), '_fall_url', true );
        $flag  = (int) get_post_meta( get_the_ID(), '_fall_enable', true );
        $valid = $this->get_video_type( $url );

        return $flag && ! empty( $url ) && 'unknown' !== $valid;
    }

    /**
     * Get the video type.
     */
    private function get_video_type( string $url ): string {
        if ( empty( $url ) ) {
            return 'unknown';
        }

        if ( preg_match( '/\.mp4($|\?)/i', $url ) ) {
            return 'mp4';
        }

        if ( preg_match( '/(youtube\.com\/watch\?v=|youtu\.be\/)/i', $url ) ) {
            return 'youtube';
        }

        return 'unknown';
    }

    /**
     * Build video markup.
     */
    private function get_video_html( string $url ): string {
        $type = $this->get_video_type( $url );
        if ( 'unknown' === $type ) {
            return '';
        }

        if ( 'mp4' === $type ) {
            $html  = '<div class="featuredall-wrapper featuredall-wrapper--mp4">';
            $html .= '<video class="featuredall-player" controls preload="metadata">';
            $html .= '<source src="' . esc_url( $url ) . '" type="video/mp4" />';
            $html .= esc_html__( 'Your browser does not support the video tag.', 'featured-all' );
            $html .= '</video></div>';
            return $html;
        }

        $embed = wp_oembed_get( $url );
        if ( ! $embed ) {
            return '';
        }

        return '<div class="featuredall-wrapper featuredall-wrapper--youtube">' . $embed . '</div>';
    }

    /**
     * Inject video before content when position is below title.
     */
    public function inject_video_below_title( string $content ): string {
        $settings = $this->get_settings();
        if ( 'below_title' !== $settings['position'] ) {
            return $content;
        }

        if ( ! $this->should_render() ) {
            return $content;
        }

        $url        = get_post_meta( get_the_ID(), '_fall_url', true );
        $video_html = $this->get_video_html( $url );

        if ( empty( $video_html ) ) {
            return $content;
        }

        return $video_html . $content;
    }

    /**
     * Inject video above the title.
     */
    public function inject_video_above_title( string $title, int $post_id ): string {
        if ( ! in_the_loop() || ! is_main_query() ) {
            return $title;
        }

        $settings = $this->get_settings();
        if ( 'above_title' !== $settings['position'] ) {
            return $title;
        }

        if ( ! $this->should_render() ) {
            return $title;
        }

        $current_post = get_the_ID();
        if ( (int) $current_post !== (int) $post_id ) {
            return $title;
        }

        $url        = get_post_meta( $post_id, '_fall_url', true );
        $video_html = $this->get_video_html( $url );

        if ( empty( $video_html ) ) {
            return $title;
        }

        return $video_html . $title;
    }

    /**
     * Add body class when video is active.
     */
    public function add_body_class( array $classes ): array {
        if ( $this->should_render() ) {
            $classes[] = 'fall-has-video';
        }

        return $classes;
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_assets(): void {
        if ( is_admin() ) {
            return;
        }

        $settings = $this->get_settings();

        wp_enqueue_style( 'featuredall-frontend', FEATUREDALL_URL . 'assets/css/frontend.css', array(), FEATUREDALL_VERSION );

        if ( $this->should_render() && ! empty( $settings['hide_featured_image'] ) ) {
            $selector = $settings['featured_image_selector'];
            if ( ! empty( $selector ) ) {
                $selectors = array_map( 'trim', explode( ',', $selector ) );
                $selectors = array_filter( $selectors );
                if ( $selectors ) {
                    $compiled = array();
                    foreach ( $selectors as $sel ) {
                        $compiled[] = '.single.fall-has-video ' . $sel;
                    }
                    $css = implode( ', ', $compiled ) . ' { display: none !important; }';
                    wp_add_inline_style( 'featuredall-frontend', $css );
                }
            }
        }
    }
}
