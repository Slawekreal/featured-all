<?php
/**
 * Frontend functionality for Featured All.
 */

namespace FeaturedAll;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend {
    private string $option_name = 'featuredall_settings';

    public function __construct() {
        add_filter( 'the_content', array( $this, 'inject_video_below_title' ) );
        add_filter( 'body_class', array( $this, 'add_body_class' ) );
        add_filter( 'the_title', array( $this, 'inject_video_above_title' ), 9, 2 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    private function get_default_settings(): array {
        return array(
            'post_types'                        => array( 'post' ),
            'position'                          => 'below_title',
            'hide_featured_image'               => 0,
            'featured_image_selector'           => '.post-image, .post-thumbnail',
            'player_controls'                   => 1,
            'player_autoplay'                   => 0,
            'player_muted'                      => 0,
            'player_loop'                       => 0,
            'player_preload'                    => 'metadata',
            'player_max_width_mode'             => 'auto',
            'player_max_width_value'            => '100',
            'player_aspect_ratio_choice'        => '16:9',
            'player_overlay_play_icon'          => 1,
            'player_fade_in'                    => 1,
            'player_controls_nodownload'        => 0,
            'player_controls_noplaybackrate'    => 0,
            'player_controls_disable_pip'       => 0,
            'player_controls_hide_fullscreen'   => 0,
            'player_fallback_controls'          => 0,
        );
    }

    private function get_settings(): array {
        $defaults = $this->get_default_settings();
        $settings = get_option( $this->option_name, array() );
        return wp_parse_args( $settings, $defaults );
    }

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

    private function get_video_html( string $url, int $post_id ): string {
        $type     = $this->get_video_type( $url );
        $settings = $this->get_settings();
        if ( 'unknown' === $type ) {
            return '';
        }

        $style      = $this->build_wrapper_style( $settings );
        $classes    = array( 'featuredall-wrapper', 'featured-all-wrapper', 'featuredall-source-' . esc_attr( $type ) );
        $wrapper_cl = implode( ' ', $classes );
        if ( ! empty( $settings['player_fade_in'] ) ) {
            $wrapper_cl .= ' featured-all-fade';
        }

        $html  = '<div class="' . esc_attr( $wrapper_cl ) . '" style="' . esc_attr( $style ) . '">';
        $html .= '<div class="featuredall-inner">';

        if ( 'mp4' === $type ) {
            $html .= $this->build_mp4_player( $url, $post_id, $settings );
        } else {
            $embed = wp_oembed_get( $url );
            if ( $embed ) {
                $html .= '<div class="featuredall-embed">' . $embed . '</div>';
            }
        }

        if ( 'mp4' === $type && ! empty( $settings['player_overlay_play_icon'] ) ) {
            $html .= '<div class="featuredall-overlay-play"><span class="featuredall-play-icon" aria-hidden="true">▶</span></div>';
        }

        if ( 'mp4' === $type && ! empty( $settings['player_fallback_controls'] ) ) {
            $html .= '<div class="featuredall-fallback-controls" data-target="featuredall-player-' . esc_attr( $post_id ) . '"><button type="button" class="featuredall-fallback-toggle">⏯</button><span class="featuredall-fallback-time">0:00</span></div>';
        }

        $html .= '</div></div>';
        return $html;
    }

    private function build_wrapper_style( array $settings ): string {
        $styles = array();
        $mode   = $settings['player_max_width_mode'];
        $value  = $settings['player_max_width_value'];

        if ( 'percent' === $mode ) {
            $styles[] = 'max-width:' . floatval( $value ) . '%';
        } elseif ( 'px' === $mode ) {
            $styles[] = 'max-width:' . floatval( $value ) . 'px';
        } else {
            $styles[] = 'max-width:100%';
        }

        $aspect = $this->aspect_to_ratio( $settings['player_aspect_ratio_choice'] );
        if ( $aspect ) {
            $styles[] = '--featuredall-aspect:' . $aspect;
        }

        return implode( ';', $styles );
    }

    private function aspect_to_ratio( string $value ): string {
        $map = array(
            '16:9' => '16/9',
            '21:9' => '21/9',
            '4:3'  => '4/3',
            '1:1'  => '1/1',
        );
        if ( isset( $map[ $value ] ) ) {
            return $map[ $value ];
        }
        return 'auto' === $value ? '16/9' : '16/9';
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
        $preload  = in_array( $settings['player_preload'], array( 'auto', 'metadata', 'none' ), true ) ? $settings['player_preload'] : 'metadata';
        $attrs[]  = 'preload="' . esc_attr( $preload ) . '"';
        $controls = $this->build_controls_list( $settings );
        if ( $controls ) {
            $attrs[] = 'controlsList="' . esc_attr( $controls ) . '"';
        }
        if ( ! empty( $settings['player_controls_disable_pip'] ) ) {
            $attrs[] = 'disablepictureinpicture';
            $attrs[] = 'playsinline';
        }
        $poster = $this->get_poster_url( $post_id );
        if ( $poster ) {
            $attrs[] = 'poster="' . esc_url( $poster ) . '"';
        }
        $attrs[] = 'id="featuredall-player-' . esc_attr( $post_id ) . '"';

        $player  = '<video class="featured-all-player" src="' . esc_url( $url ) . '" ' . implode( ' ', array_unique( $attrs ) ) . '>';
        $player .= esc_html__( 'Dein Browser unterstützt dieses Video-Format nicht.', 'featured-all' );
        $player .= '</video>';
        return $player;
    }

    private function build_controls_list( array $settings ): string {
        $items = array();
        if ( ! empty( $settings['player_controls_nodownload'] ) ) {
            $items[] = 'nodownload';
        }
        if ( ! empty( $settings['player_controls_noplaybackrate'] ) ) {
            $items[] = 'noplaybackrate';
        }
        if ( ! empty( $settings['player_controls_hide_fullscreen'] ) ) {
            $items[] = 'nofullscreen';
        }
        return implode( ' ', $items );
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
        if ( (int) get_the_ID() !== (int) $post_id ) {
            return $title;
        }
        $url        = get_post_meta( $post_id, '_fall_url', true );
        $video_html = $this->get_video_html( $url, $post_id );
        if ( empty( $video_html ) ) {
            return $title;
        }
        return $video_html . $title;
    }

    public function add_body_class( array $classes ): array {
        if ( $this->should_render() ) {
            $classes[] = 'fall-has-video';
            $settings  = $this->get_settings();
            if ( ! empty( $settings['player_controls_hide_fullscreen'] ) ) {
                $classes[] = 'fall-hide-fullscreen';
            }
        }
        return $classes;
    }

    public function enqueue_assets(): void {
        if ( is_admin() ) {
            return;
        }
        $settings = $this->get_settings();
        wp_enqueue_style( 'featuredall-frontend', FEATUREDALL_URL . 'assets/css/frontend.css', array(), FEATUREDALL_VERSION );
        if ( $this->should_render() ) {
            wp_enqueue_script( 'featuredall-frontend', FEATUREDALL_URL . 'assets/js/frontend.js', array(), FEATUREDALL_VERSION, true );
        }

        if ( $this->should_render() && ( ! empty( $settings['hide_featured_image'] ) || ! empty( $settings['player_controls_hide_fullscreen'] ) ) ) {
            $css = '';
            if ( ! empty( $settings['hide_featured_image'] ) ) {
                $selector = $settings['featured_image_selector'];
                if ( ! empty( $selector ) ) {
                    $selectors = array_map( 'trim', explode( ',', $selector ) );
                    $selectors = array_filter( $selectors );
                    if ( $selectors ) {
                        $compiled = array();
                        foreach ( $selectors as $sel ) {
                            $compiled[] = '.single.fall-has-video ' . $sel;
                        }
                        $css .= implode( ', ', $compiled ) . ' { display: none !important; }';
                    }
                }
            }

            if ( ! empty( $settings['player_controls_hide_fullscreen'] ) ) {
                $css .= ' video::-webkit-media-controls-fullscreen-button{display:none!important;} video::-webkit-media-controls-enclosure{overflow:hidden;}';
            }

            if ( $css ) {
                wp_add_inline_style( 'featuredall-frontend', $css );
            }
        }
    }
}
