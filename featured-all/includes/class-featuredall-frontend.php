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
     * Default settings.
     */
    private function get_default_settings(): array {
        return array(
            'post_types'              => array( 'post' ),
            'position'                => 'below_title',
            'hide_featured_image'     => 0,
            'featured_image_selector' => '.post-image, .post-thumbnail',
            'player_controls'         => 1,
            'player_autoplay'         => 0,
            'player_muted'            => 0,
            'player_loop'             => 0,
            'player_preload'          => 'metadata',
            'player_max_width'        => '100%',
            'player_aspect_ratio'     => '16:9',
            'player_overlay_play_icon'=> 1,
            'player_fade_in'          => 1,
        );
    }

    /**
     * Get settings with defaults.
     */
    private function get_settings(): array {
        $defaults = $this->get_default_settings();
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

        return 'extern';
    }

    /**
     * Build video markup.
     */
    private function get_video_html( string $url, int $post_id ): string {
        $type     = $this->get_video_type( $url );
        $settings = $this->get_settings();
        if ( 'unknown' === $type ) {
            return '';
        }

        $aspect    = $this->format_aspect_ratio( $settings['player_aspect_ratio'] );
        $max_width = trim( $settings['player_max_width'] );
        $classes   = array( 'featuredall-wrapper', 'featuredall-source-' . esc_attr( $type ) );

        if ( ! empty( $settings['player_fade_in'] ) ) {
            $classes[] = 'featured-all-fade';
        }

        $style = '';
        if ( $max_width ) {
            $style .= 'max-width:' . esc_attr( $max_width ) . ';';
        }
        if ( $aspect ) {
            $style .= '--featuredall-aspect:' . esc_attr( $aspect ) . ';';
        }

        $wrapper  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . ' data-featuredall-fade="' . ( ! empty( $settings['player_fade_in'] ) ? '1' : '0' ) . '">';
        $wrapper .= '<div class="featuredall-inner">';

        if ( 'mp4' === $type ) {
            $wrapper .= $this->build_mp4_player( $url, $post_id, $settings );
        } else {
            $embed = wp_oembed_get( $url );
            if ( $embed ) {
                $wrapper .= '<div class="featuredall-embed">' . $embed . '</div>';
            }
        }

        if ( 'mp4' === $type && ! empty( $settings['player_overlay_play_icon'] ) ) {
            $wrapper .= '<div class="featuredall-overlay-play"><span class="featuredall-play-icon" aria-hidden="true">▶</span></div>';
        }

        $wrapper .= '</div></div>';

        return $wrapper;
    }

    private function build_mp4_player( string $url, int $post_id, array $settings ): string {
        $attrs = array();

        if ( ! empty( $settings['player_controls'] ) ) {
            $attrs[] = 'controls';
        }

        if ( ! empty( $settings['player_autoplay'] ) ) {
            $attrs[] = 'autoplay';
            $attrs[] = 'muted';
        }

        if ( ! empty( $settings['player_muted'] ) ) {
            $attrs[] = 'muted';
        }

        if ( ! empty( $settings['player_loop'] ) ) {
            $attrs[] = 'loop';
        }

        $preload = in_array( $settings['player_preload'], array( 'auto', 'metadata', 'none' ), true ) ? $settings['player_preload'] : 'metadata';
        $attrs[] = 'preload="' . esc_attr( $preload ) . '"';

        $poster = $this->get_poster_url( $post_id );
        if ( $poster ) {
            $attrs[] = 'poster="' . esc_url( $poster ) . '"';
        }

        $attrs[] = 'playsinline';

        $player  = '<video class="featured-all-player" src="' . esc_url( $url ) . '" ' . implode( ' ', array_unique( $attrs ) ) . '>';
        $player .= esc_html__( 'Dein Browser unterstützt dieses Video-Format nicht.', 'featured-all' );
        $player .= '</video>';

        return $player;
    }

    private function get_poster_url( int $post_id ): string {
        $poster_id = (int) get_post_meta( $post_id, '_fall_video_poster_id', true );
        if ( $poster_id ) {
            $poster = wp_get_attachment_image_url( $poster_id, 'large' );
            if ( $poster ) {
                return $poster;
            }
        }

        return '';
    }

    private function format_aspect_ratio( string $value ): string {
        if ( empty( $value ) ) {
            return '16/9';
        }

        if ( preg_match( '/^(\d+)\s*:\s*(\d+)$/', $value, $matches ) ) {
            $a = (int) $matches[1];
            $b = (int) $matches[2];
            if ( $a > 0 && $b > 0 ) {
                return $a . '/' . $b;
            }
        }

        return '16/9';
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
        $video_html = $this->get_video_html( $url, get_the_ID() );

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
        $video_html = $this->get_video_html( $url, $post_id );

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

        if ( $this->should_render() ) {
            wp_enqueue_script( 'featuredall-frontend', FEATUREDALL_URL . 'assets/js/frontend.js', array(), FEATUREDALL_VERSION, true );
        }

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
