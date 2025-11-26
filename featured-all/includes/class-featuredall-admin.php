<?php
/**
 * Admin functionality for Featured All.
 */

namespace FeaturedAll;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Admin
 */
class Admin {
    private string $option_name = 'featuredall_settings';

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
        add_action( 'save_post', array( $this, 'save_metabox' ) );
        add_action( 'admin_menu', array( $this, 'register_menu_pages' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Default settings.
     */
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

    /**
     * Get settings with defaults and backward compatibility.
     */
    public function get_settings(): array {
        $defaults = $this->get_default_settings();
        $settings = get_option( $this->option_name, array() );

        if ( isset( $settings['player_max_width'] ) && ! isset( $settings['player_max_width_mode'] ) ) {
            $width = (string) $settings['player_max_width'];
            if ( str_ends_with( $width, '%' ) ) {
                $settings['player_max_width_mode']  = 'percent';
                $settings['player_max_width_value'] = preg_replace( '/[^0-9.]/', '', $width );
            } elseif ( str_ends_with( $width, 'px' ) ) {
                $settings['player_max_width_mode']  = 'px';
                $settings['player_max_width_value'] = preg_replace( '/[^0-9.]/', '', $width );
            }
        }

        if ( isset( $settings['player_aspect_ratio'] ) && ! isset( $settings['player_aspect_ratio_choice'] ) ) {
            $settings['player_aspect_ratio_choice'] = $settings['player_aspect_ratio'];
        }

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Determine if a post type is enabled for the plugin.
     */
    public function is_post_type_enabled( string $post_type ): bool {
        $settings = $this->get_settings();
        return in_array( $post_type, $settings['post_types'], true );
    }

    /**
     * Register metabox.
     */
    public function register_metabox(): void {
        $post_types = $this->get_settings()['post_types'];
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'featuredall_metabox',
                __( 'Featured All', 'featured-all' ),
                array( $this, 'render_metabox' ),
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * Render metabox content.
     */
    public function render_metabox( \WP_Post $post ): void {
        wp_nonce_field( 'featuredall_fv_save', 'featuredall_fv_nonce' );

        $url        = get_post_meta( $post->ID, '_fall_url', true );
        $enable     = (int) get_post_meta( $post->ID, '_fall_enable', true );
        $type       = $this->detect_video_type( $url );
        $video_id   = (int) get_post_meta( $post->ID, '_fall_video_attachment_id', true );
        $poster_id  = (int) get_post_meta( $post->ID, '_fall_video_poster_id', true );
        $video_url  = $video_id ? wp_get_attachment_url( $video_id ) : '';
        $poster_url = $poster_id ? wp_get_attachment_image_url( $poster_id, 'thumbnail' ) : '';
        ?>
        <p>
            <label for="featuredall_fv_url"><?php esc_html_e( 'Video URL (MP4 or YouTube)', 'featured-all' ); ?></label>
            <input type="url" class="widefat" id="featuredall_fv_url" name="featuredall_fv_url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://example.com/video.mp4" />
            <small class="description"><?php esc_html_e( 'Akzeptiert MP4-URLs (selbst gehostet) oder YouTube-Links.', 'featured-all' ); ?></small><br />
            <small class="description"><?php esc_html_e( 'Bei Medien aus der Mediathek wird die URL automatisch gesetzt.', 'featured-all' ); ?></small>
        </p>
        <p>
            <input type="hidden" id="featuredall_video_attachment_id" name="featuredall_video_attachment_id" value="<?php echo esc_attr( $video_id ); ?>" />
            <button type="button" class="button featuredall-select-video" data-target="#featuredall_fv_url" data-attachment="#featuredall_video_attachment_id" data-type="video"><?php esc_html_e( 'Video auswählen / hochladen', 'featured-all' ); ?></button>
            <?php if ( $video_url ) : ?>
                <span class="featuredall-selected-file"><?php echo esc_html( basename( $video_url ) ); ?></span>
            <?php endif; ?>
        </p>
        <p>
            <input type="hidden" id="featuredall_video_poster_id" name="featuredall_video_poster_id" value="<?php echo esc_attr( $poster_id ); ?>" />
            <button type="button" class="button featuredall-select-poster" data-target="#featuredall_video_poster_id" data-preview="#featuredall_poster_preview" data-type="image"><?php esc_html_e( 'Poster-Bild wählen', 'featured-all' ); ?></button>
            <span id="featuredall_poster_preview" class="featuredall-poster-preview">
                <?php if ( $poster_url ) { echo '<img src="' . esc_url( $poster_url ) . '" alt="" />'; } ?>
            </span>
        </p>
        <p>
            <label>
                <input type="checkbox" name="featuredall_fv_enable" value="1" <?php checked( $enable, 1 ); ?> />
                <?php esc_html_e( 'Use this video as Featured Video', 'featured-all' ); ?>
            </label>
        </p>
        <?php if ( ! empty( $url ) && 'unknown' !== $type ) : ?>
            <p class="description">
                <?php printf( esc_html__( 'Detected type: %s', 'featured-all' ), esc_html( strtoupper( $type ) ) ); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Save metabox fields.
     */
    public function save_metabox( int $post_id ): void {
        if ( ! isset( $_POST['featuredall_fv_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['featuredall_fv_nonce'] ) ), 'featuredall_fv_save' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $post_type = get_post_type( $post_id );
        if ( ! $post_type || ! $this->is_post_type_enabled( $post_type ) ) {
            return;
        }

        $url_raw   = isset( $_POST['featuredall_fv_url'] ) ? wp_unslash( $_POST['featuredall_fv_url'] ) : '';
        $url       = $this->sanitize_url( $url_raw );
        $enable    = ! empty( $_POST['featuredall_fv_enable'] ) ? 1 : 0;
        $video_id  = isset( $_POST['featuredall_video_attachment_id'] ) ? absint( $_POST['featuredall_video_attachment_id'] ) : 0;
        $poster_id = isset( $_POST['featuredall_video_poster_id'] ) ? absint( $_POST['featuredall_video_poster_id'] ) : 0;

        if ( ! empty( $url ) ) {
            update_post_meta( $post_id, '_fall_url', $url );
        } else {
            delete_post_meta( $post_id, '_fall_url' );
        }

        if ( $enable ) {
            update_post_meta( $post_id, '_fall_enable', 1 );
        } else {
            delete_post_meta( $post_id, '_fall_enable' );
        }

        if ( $video_id ) {
            update_post_meta( $post_id, '_fall_video_attachment_id', $video_id );
            if ( empty( $url ) ) {
                $video_url = wp_get_attachment_url( $video_id );
                if ( $video_url ) {
                    update_post_meta( $post_id, '_fall_url', esc_url_raw( $video_url ) );
                }
            }
        } else {
            delete_post_meta( $post_id, '_fall_video_attachment_id' );
        }

        if ( $poster_id ) {
            update_post_meta( $post_id, '_fall_video_poster_id', $poster_id );
        } else {
            delete_post_meta( $post_id, '_fall_video_poster_id' );
        }
    }

    private function sanitize_url( string $url ): string {
        $url = trim( $url );
        if ( empty( $url ) ) {
            return '';
        }
        $validated = filter_var( $url, FILTER_VALIDATE_URL );
        if ( ! $validated ) {
            return '';
        }
        return esc_url_raw( $validated );
    }

    public function detect_video_type( string $url ): string {
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
     * Menu pages.
     */
    public function register_menu_pages(): void {
        add_menu_page(
            __( 'Featured All – Übersicht', 'featured-all' ),
            __( 'Featured All 🎬', 'featured-all' ),
            'manage_options',
            'featuredall',
            array( $this, 'render_dashboard_page' ),
            'dashicons-format-video'
        );

        add_submenu_page(
            'featuredall',
            __( 'Featured All – Dashboard', 'featured-all' ),
            __( 'Dashboard', 'featured-all' ),
            'manage_options',
            'featuredall',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'featuredall',
            __( 'Featured All – Videos', 'featured-all' ),
            __( 'Videos', 'featured-all' ),
            'manage_options',
            'featuredall-videos',
            array( $this, 'render_videos_page' )
        );

        add_submenu_page(
            'featuredall',
            __( 'Featured All – Images', 'featured-all' ),
            __( 'Bilder', 'featured-all' ),
            'manage_options',
            'featuredall-images',
            array( $this, 'render_images_page' )
        );

        add_submenu_page(
            'featuredall',
            __( 'Featured All – Einstellungen', 'featured-all' ),
            __( 'Einstellungen', 'featured-all' ),
            'manage_options',
            'featuredall-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings(): void {
        register_setting( 'featuredall_settings_group', $this->option_name, array( $this, 'sanitize_settings' ) );
    }

    /**
     * Sanitize settings values.
     */
    public function sanitize_settings( $input ): array {
        $input         = is_array( $input ) ? $input : array();
        $defaults      = $this->get_default_settings();
        $allowed_types = get_post_types( array( 'public' => true ), 'names' );

        $output               = array();
        $output['post_types'] = array();
        $input_post_types     = $input['post_types'] ?? array();
        if ( is_array( $input_post_types ) ) {
            foreach ( $input_post_types as $post_type ) {
                if ( in_array( $post_type, $allowed_types, true ) ) {
                    $output['post_types'][] = $post_type;
                }
            }
        }

        if ( empty( $output['post_types'] ) ) {
            $output['post_types'] = $defaults['post_types'];
        }

        $position            = $input['position'] ?? $defaults['position'];
        $output['position']  = in_array( $position, array( 'above_title', 'below_title' ), true ) ? $position : $defaults['position'];
        $output['hide_featured_image']     = ! empty( $input['hide_featured_image'] ) ? 1 : 0;
        $selector                          = $input['featured_image_selector'] ?? $defaults['featured_image_selector'];
        $output['featured_image_selector'] = sanitize_text_field( $selector );

        $output['player_controls']        = ! empty( $input['player_controls'] ) ? 1 : 0;
        $output['player_autoplay']        = ! empty( $input['player_autoplay'] ) ? 1 : 0;
        $output['player_muted']           = ! empty( $input['player_muted'] ) ? 1 : 0;
        $output['player_loop']            = ! empty( $input['player_loop'] ) ? 1 : 0;
        $preload                          = $input['player_preload'] ?? $defaults['player_preload'];
        $output['player_preload']         = in_array( $preload, array( 'auto', 'metadata', 'none' ), true ) ? $preload : $defaults['player_preload'];

        $mode  = $input['player_max_width_mode'] ?? $defaults['player_max_width_mode'];
        $mode  = in_array( $mode, array( 'auto', 'percent', 'px' ), true ) ? $mode : $defaults['player_max_width_mode'];
        $value = isset( $input['player_max_width_value'] ) ? (string) $input['player_max_width_value'] : $defaults['player_max_width_value'];
        $value = preg_replace( '/[^0-9.]/', '', $value );

        if ( 'percent' === $mode ) {
            $value = $value !== '' ? (string) min( max( (float) $value, 25 ), 100 ) : $defaults['player_max_width_value'];
        } elseif ( 'px' === $mode ) {
            $value = $value !== '' ? (string) min( max( (float) $value, 320 ), 1920 ) : $defaults['player_max_width_value'];
        } else {
            $value = '100';
        }

        $output['player_max_width_mode']  = $mode;
        $output['player_max_width_value'] = $value !== '' ? $value : $defaults['player_max_width_value'];

        $aspect_choice = $input['player_aspect_ratio_choice'] ?? $defaults['player_aspect_ratio_choice'];
        $allowed_aspect = array( 'auto', '16:9', '21:9', '4:3', '1:1' );
        $output['player_aspect_ratio_choice'] = in_array( $aspect_choice, $allowed_aspect, true ) ? $aspect_choice : $defaults['player_aspect_ratio_choice'];

        $output['player_overlay_play_icon']        = ! empty( $input['player_overlay_play_icon'] ) ? 1 : 0;
        $output['player_fade_in']                  = ! empty( $input['player_fade_in'] ) ? 1 : 0;
        $output['player_controls_nodownload']      = ! empty( $input['player_controls_nodownload'] ) ? 1 : 0;
        $output['player_controls_noplaybackrate']  = ! empty( $input['player_controls_noplaybackrate'] ) ? 1 : 0;
        $output['player_controls_disable_pip']     = ! empty( $input['player_controls_disable_pip'] ) ? 1 : 0;
        $output['player_controls_hide_fullscreen'] = ! empty( $input['player_controls_hide_fullscreen'] ) ? 1 : 0;
        $output['player_fallback_controls']        = ! empty( $input['player_fallback_controls'] ) ? 1 : 0;

        return wp_parse_args( $output, $defaults );
    }

    /**
     * Settings page with tabs.
     */
    public function render_settings_page(): void {
        $settings = $this->get_settings();
        $tabs     = array(
            'general'   => __( 'Allgemein', 'featured-all' ),
            'player'    => __( 'HTML5-Player', 'featured-all' ),
            'advanced'  => __( 'Erweiterte Player-Optionen', 'featured-all' ),
            'layout'    => __( 'Layout & Größe', 'featured-all' ),
            'media'     => __( 'Medien & Thumbnails', 'featured-all' ),
            'future'    => __( 'Coming Soon', 'featured-all' ),
        );
        ?>
        <div class="wrap featuredall-settings-wrap">
            <h1><?php esc_html_e( 'Featured All – Einstellungen', 'featured-all' ); ?></h1>
            <p class="description"><?php printf( esc_html__( 'Version %s – alle bestehenden Einstellungen bleiben beim Update erhalten.', 'featured-all' ), esc_html( FEATUREDALL_VERSION ) ); ?></p>
            <h2 class="nav-tab-wrapper" id="featuredall-tabs">
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <a href="#featuredall-tab-<?php echo esc_attr( $key ); ?>" class="nav-tab" data-tab="<?php echo esc_attr( $key ); ?>" aria-controls="featuredall-tab-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </h2>
            <form action="options.php" method="post" id="featuredall-settings-form">
                <?php settings_fields( 'featuredall_settings_group' ); ?>
                <div class="featuredall-tab-content" data-tab="general" id="featuredall-tab-general">
                    <h2><?php esc_html_e( 'Allgemein', 'featured-all' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Grundsätzliche Aktivierung und Platzierung des Videos im Beitrag.', 'featured-all' ); ?></p>
                    <?php $this->render_post_types_field(); ?>
                    <?php $this->render_position_field(); ?>
                    <?php $this->render_hide_image_field(); ?>
                    <?php $this->render_selector_field(); ?>
                </div>
                <div class="featuredall-tab-content" data-tab="player" id="featuredall-tab-player">
                    <h2><?php esc_html_e( 'HTML5-Player', 'featured-all' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Steuerelemente und Verhalten des nativen Players.', 'featured-all' ); ?></p>
                    <?php $this->render_player_controls_field(); ?>
                    <?php $this->render_player_autoplay_field(); ?>
                    <?php $this->render_player_muted_field(); ?>
                    <?php $this->render_player_loop_field(); ?>
                    <?php $this->render_player_preload_field(); ?>
                    <?php $this->render_player_overlay_field(); ?>
                    <?php $this->render_player_fade_field(); ?>
                </div>
                <div class="featuredall-tab-content" data-tab="advanced" id="featuredall-tab-advanced">
                    <h2><?php esc_html_e( 'Erweiterte Player-Optionen', 'featured-all' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Feintuning für controlsList, Bild-in-Bild und eine einfache Fallback-Leiste.', 'featured-all' ); ?></p>
                    <?php $this->render_player_nodownload_field(); ?>
                    <?php $this->render_player_noplayback_field(); ?>
                    <?php $this->render_player_pip_field(); ?>
                    <?php $this->render_player_fullscreen_field(); ?>
                    <?php $this->render_player_fallback_controls_field(); ?>
                </div>
                <div class="featuredall-tab-content" data-tab="layout" id="featuredall-tab-layout">
                    <h2><?php esc_html_e( 'Layout & Größe', 'featured-all' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Steuere maximale Breite und Seitenverhältnis über Moduswahl und Slider.', 'featured-all' ); ?></p>
                    <?php $this->render_player_max_width_field(); ?>
                    <?php $this->render_player_aspect_ratio_field(); ?>
                    <?php $this->render_layout_preview(); ?>
                </div>
                <div class="featuredall-tab-content" data-tab="media" id="featuredall-tab-media">
                    <h2><?php esc_html_e( 'Medien & Thumbnails', 'featured-all' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Poster-Bilder und Thumbnails werden automatisch aus den Beitragsdaten gezogen. Weitere Optionen folgen.', 'featured-all' ); ?></p>
                    <p class="description"><?php esc_html_e( 'Hinweis: Poster aus der Metabox haben Priorität, danach Beitragsbild oder Video-Anhang.', 'featured-all' ); ?></p>
                </div>
                <div class="featuredall-tab-content" data-tab="future" id="featuredall-tab-future">
                    <h2><?php esc_html_e( 'Coming Soon', 'featured-all' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Weitere Funktionen folgen in zukünftigen Versionen. (Coming soon)', 'featured-all' ); ?></p>
                    <ul class="ul-disc">
                        <li><?php esc_html_e( 'Kapitelmarken und Sprungmarken für lange Videos.', 'featured-all' ); ?></li>
                        <li><?php esc_html_e( 'Mehrere Videos pro Beitrag inkl. Playlist-Option.', 'featured-all' ); ?></li>
                        <li><?php esc_html_e( 'Eigene Thumbnail-Uploads je Quelle.', 'featured-all' ); ?></li>
                    </ul>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function render_field_wrapper( string $label, string $description, callable $render_control ): void {
        echo '<div class="featuredall-field">';
        echo '<label class="featuredall-field__label">' . esc_html( $label ) . '</label>';
        echo '<div class="featuredall-field__control">';
        call_user_func( $render_control );
        if ( $description ) {
            echo '<p class="description">' . esc_html( $description ) . '</p>';
        }
        echo '</div></div>';
    }

    public function render_post_types_field(): void {
        $settings      = $this->get_settings();
        $current_types = $settings['post_types'];
        $post_types    = get_post_types( array( 'public' => true ), 'objects' );
        $this->render_field_wrapper(
            __( 'Aktive Post Types', 'featured-all' ),
            __( 'Wähle aus, für welche Post Types Featured All aktiv ist.', 'featured-all' ),
            function() use ( $post_types, $current_types ) {
                foreach ( $post_types as $post_type ) {
                    echo '<label><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[post_types][]" value="' . esc_attr( $post_type->name ) . '" ' . checked( in_array( $post_type->name, $current_types, true ), true, false ) . ' /> ' . esc_html( $post_type->labels->singular_name ) . '</label><br />';
                }
            }
        );
    }

    public function render_position_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'Video Position', 'featured-all' ),
            __( 'Bestimme, wo das Video im Single-View erscheint.', 'featured-all' ),
            function() use ( $settings ) {
                ?>
                <label><input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[position]" value="above_title" <?php checked( $settings['position'], 'above_title' ); ?> /> <?php esc_html_e( 'Über dem Titel', 'featured-all' ); ?></label><br />
                <label><input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[position]" value="below_title" <?php checked( $settings['position'], 'below_title' ); ?> /> <?php esc_html_e( 'Unter dem Titel / vor dem Inhalt', 'featured-all' ); ?></label>
                <?php
            }
        );
    }

    public function render_hide_image_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'Featured Image ausblenden', 'featured-all' ),
            __( 'Blende das Beitragsbild aus, wenn ein Video aktiv ist.', 'featured-all' ),
            function() use ( $settings ) {
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[hide_featured_image]" value="1" <?php checked( $settings['hide_featured_image'], 1 ); ?> /> <?php esc_html_e( 'Aktivieren', 'featured-all' ); ?></label>
                <?php
            }
        );
    }

    public function render_selector_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'CSS-Selektor für Beitragsbild', 'featured-all' ),
            __( 'Kommagetrennte Selektoren, die das Beitragsbild ansprechen.', 'featured-all' ),
            function() use ( $settings ) {
                ?>
                <input type="text" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[featured_image_selector]" value="<?php echo esc_attr( $settings['featured_image_selector'] ); ?>" />
                <?php
            }
        );
    }

    public function render_player_controls_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'Steuerelemente anzeigen (controls)', 'featured-all' ),
            __( 'Zeigt native HTML5-Controls an.', 'featured-all' ),
            function() use ( $settings ) {
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_controls]" value="1" <?php checked( $settings['player_controls'], 1 ); ?> /> <?php esc_html_e( 'Aktivieren', 'featured-all' ); ?></label>
                <?php
            }
        );
    }

    public function render_player_autoplay_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'Autoplay aktivieren', 'featured-all' ),
            __( 'Bei Autoplay wird zusätzlich muted gesetzt, um Browserblockaden zu minimieren.', 'featured-all' ),
            function() use ( $settings ) {
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_autoplay]" value="1" <?php checked( $settings['player_autoplay'], 1 ); ?> /> <?php esc_html_e( 'Autoplay', 'featured-all' ); ?></label>
                <?php
            }
        );
    }

    public function render_player_muted_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'Videos stumm starten (muted)', 'featured-all' ),
            '',
            function() use ( $settings ) {
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_muted]" value="1" <?php checked( $settings['player_muted'], 1 ); ?> /> <?php esc_html_e( 'Muted', 'featured-all' ); ?></label>
                <?php
            }
        );
    }

    public function render_player_loop_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'Videos automatisch wiederholen (loop)', 'featured-all' ),
            '',
            function() use ( $settings ) {
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_loop]" value="1" <?php checked( $settings['player_loop'], 1 ); ?> /> <?php esc_html_e( 'Loop', 'featured-all' ); ?></label>
                <?php
            }
        );
    }

    public function render_player_preload_field(): void {
        $settings = $this->get_settings();
        $options = array(
            'auto'     => __( 'Automatisch', 'featured-all' ),
            'metadata' => __( 'Nur Metadaten', 'featured-all' ),
            'none'     => __( 'Nichts vorladen', 'featured-all' ),
        );
        $this->render_field_wrapper(
            __( 'Preload Verhalten', 'featured-all' ),
            __( 'Steuert, wie viel der Browser vorlädt.', 'featured-all' ),
            function() use ( $settings, $options ) {
                ?>
                <select name="<?php echo esc_attr( $this->option_name ); ?>[player_preload]">
                    <?php foreach ( $options as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['player_preload'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php
            }
        );
    }

    public function render_player_overlay_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'Overlay-Play-Icon anzeigen', 'featured-all' ),
            __( 'Zeigt ein Overlay-Play-Icon über dem Video.', 'featured-all' ),
            function() use ( $settings ) {
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_overlay_play_icon]" value="1" <?php checked( $settings['player_overlay_play_icon'], 1 ); ?> /> <?php esc_html_e( 'Aktivieren', 'featured-all' ); ?></label>
                <?php
            }
        );
    }

    public function render_player_fade_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'Fade-In Effekt', 'featured-all' ),
            __( 'Video weich einblenden, sobald Daten geladen sind.', 'featured-all' ),
            function() use ( $settings ) {
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_fade_in]" value="1" <?php checked( $settings['player_fade_in'], 1 ); ?> /> <?php esc_html_e( 'Aktivieren', 'featured-all' ); ?></label>
                <?php
            }
        );
    }

    public function render_player_nodownload_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'Download-Option ausblenden', 'featured-all' ),
            __( 'Setzt controlsList="nodownload" sofern verfügbar.', 'featured-all' ),
            function() use ( $settings ) {
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_controls_nodownload]" value="1" <?php checked( $settings['player_controls_nodownload'], 1 ); ?> /> <?php esc_html_e( 'Download ausblenden', 'featured-all' ); ?></label>
                <?php
            }
        );
    }

    public function render_player_noplayback_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'Wiedergabegeschwindigkeit ausblenden', 'featured-all' ),
            __( 'controlsList="noplaybackrate" wenn unterstützt.', 'featured-all' ),
            function() use ( $settings ) {
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_controls_noplaybackrate]" value="1" <?php checked( $settings['player_controls_noplaybackrate'], 1 ); ?> /> <?php esc_html_e( 'Playbackrate ausblenden', 'featured-all' ); ?></label>
                <?php
            }
        );
    }

    public function render_player_pip_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'Bild-in-Bild ausblenden', 'featured-all' ),
            __( 'Setzt disablepictureinpicture / noremoteplayback.', 'featured-all' ),
            function() use ( $settings ) {
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_controls_disable_pip]" value="1" <?php checked( $settings['player_controls_disable_pip'], 1 ); ?> /> <?php esc_html_e( 'PiP ausblenden', 'featured-all' ); ?></label>
                <?php
            }
        );
    }

    public function render_player_fullscreen_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'Fullscreen-Button ausblenden (soweit möglich)', 'featured-all' ),
            __( 'Ergänzt CSS, um den Fullscreen-Button auszublenden (nicht in allen Browsern garantiert).', 'featured-all' ),
            function() use ( $settings ) {
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_controls_hide_fullscreen]" value="1" <?php checked( $settings['player_controls_hide_fullscreen'], 1 ); ?> /> <?php esc_html_e( 'Fullscreen-Button verstecken', 'featured-all' ); ?></label>
                <?php
            }
        );
    }

    public function render_player_fallback_controls_field(): void {
        $settings = $this->get_settings();
        $this->render_field_wrapper(
            __( 'Zusätzliche einfache Control-Leiste anzeigen', 'featured-all' ),
            __( 'Zeigt eine eigene Play/Pause-Leiste unter dem Video als Fallback.', 'featured-all' ),
            function() use ( $settings ) {
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_fallback_controls]" value="1" <?php checked( $settings['player_fallback_controls'], 1 ); ?> /> <?php esc_html_e( 'Fallback-Leiste aktivieren', 'featured-all' ); ?></label>
                <?php
            }
        );
    }

    public function render_player_max_width_field(): void {
        $settings = $this->get_settings();
        $mode     = $settings['player_max_width_mode'];
        $value    = $settings['player_max_width_value'];
        $unit     = 'px' === $mode ? 'px' : '%';
        $this->render_field_wrapper(
            __( 'Maximale Breite des Video-Containers', 'featured-all' ),
            __( 'Wähle den Modus und passe den Wert mit dem Slider an.', 'featured-all' ),
            function() use ( $mode, $value, $unit ) {
                ?>
                <select name="<?php echo esc_attr( $this->option_name ); ?>[player_max_width_mode]" class="featuredall-width-mode">
                    <option value="auto" <?php selected( $mode, 'auto' ); ?>><?php esc_html_e( 'Auto (100%)', 'featured-all' ); ?></option>
                    <option value="percent" <?php selected( $mode, 'percent' ); ?>><?php esc_html_e( 'Prozent', 'featured-all' ); ?></option>
                    <option value="px" <?php selected( $mode, 'px' ); ?>><?php esc_html_e( 'Pixel', 'featured-all' ); ?></option>
                </select>
                <div class="featuredall-slider-wrap" data-mode="<?php echo esc_attr( $mode ); ?>" data-preview-target="#featuredall-layout-preview .featuredall-wrapper">
                    <input type="range" class="featuredall-width-slider" min="25" max="100" step="5" value="<?php echo esc_attr( $value ); ?>" style="display: <?php echo 'px' === $mode ? 'none' : 'block'; ?>;" />
                    <input type="range" class="featuredall-width-slider-px" min="320" max="1920" step="20" value="<?php echo esc_attr( $value ); ?>" style="display: <?php echo 'px' === $mode ? 'block' : 'none'; ?>;" />
                    <input type="text" class="small-text featuredall-width-value" name="<?php echo esc_attr( $this->option_name ); ?>[player_max_width_value]" value="<?php echo esc_attr( $value ); ?>" />
                    <span class="featuredall-width-unit"><?php echo esc_html( $unit ); ?></span>
                </div>
                <?php
            }
        );
    }

    public function render_player_aspect_ratio_field(): void {
        $settings = $this->get_settings();
        $options  = array(
            'auto' => __( 'Auto', 'featured-all' ),
            '16:9' => '16:9',
            '21:9' => '21:9',
            '4:3'  => '4:3',
            '1:1'  => '1:1',
        );
        $this->render_field_wrapper(
            __( 'Seitenverhältnis', 'featured-all' ),
            __( 'Wähle ein Seitenverhältnis für den Player.', 'featured-all' ),
            function() use ( $settings, $options ) {
                ?>
                <select name="<?php echo esc_attr( $this->option_name ); ?>[player_aspect_ratio_choice]" class="featuredall-aspect-choice">
                    <?php foreach ( $options as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['player_aspect_ratio_choice'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php
            }
        );
    }

    private function render_layout_preview(): void {
        $style = $this->build_layout_preview_style( $this->get_settings() );
        ?>
        <div class="featuredall-layout-preview" id="featuredall-layout-preview">
            <p class="description"><?php esc_html_e( 'Live-Preview für Breite und Seitenverhältnis (funktioniert mit aktivem JavaScript).', 'featured-all' ); ?></p>
            <div class="featuredall-wrapper featured-all-wrapper" style="<?php echo esc_attr( $style ); ?>">
                <div class="featuredall-inner">
                    <div class="featuredall-layout-preview-box"><?php esc_html_e( 'Video-Vorschau', 'featured-all' ); ?></div>
                </div>
            </div>
        </div>
        <?php
    }

    private function build_layout_preview_style( array $settings ): string {
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

        $map = array(
            '16:9' => array(
                'ratio'    => '16/9',
                'fallback' => '56.25%',
            ),
            '21:9' => array(
                'ratio'    => '21/9',
                'fallback' => '42.857%',
            ),
            '4:3'  => array(
                'ratio'    => '4/3',
                'fallback' => '75%',
            ),
            '1:1'  => array(
                'ratio'    => '1/1',
                'fallback' => '100%',
            ),
            'auto' => array(
                'ratio'    => '',
                'fallback' => '56.25%',
            ),
        );

        $choice = $settings['player_aspect_ratio_choice'] ?? '16:9';
        $aspect = $map[ $choice ] ?? $map['16:9'];
        if ( $aspect['ratio'] ) {
            $styles[] = '--featuredall-aspect:' . $aspect['ratio'];
        }
        if ( $aspect['fallback'] ) {
            $styles[] = '--featuredall-aspect-fallback:' . $aspect['fallback'];
        }

        return implode( ';', array_filter( $styles ) );
    }

    /**
     * Dashboard page.
     */
    public function render_dashboard_page(): void {
        $stats = $this->gather_stats();
        ?>
        <div class="wrap featuredall-dashboard">
            <h1><?php esc_html_e( 'Featured All – Übersicht', 'featured-all' ); ?></h1>
            <div class="featuredall-stats">
                <div class="featuredall-stat-box">
                    <span class="featuredall-stat-label"><?php esc_html_e( 'Aktive Featured Videos', 'featured-all' ); ?></span>
                    <strong class="featuredall-stat-value"><?php echo esc_html( (string) $stats['videos'] ); ?></strong>
                </div>
                <div class="featuredall-stat-box">
                    <span class="featuredall-stat-label"><?php esc_html_e( 'Beiträge mit Featured Image', 'featured-all' ); ?></span>
                    <strong class="featuredall-stat-value"><?php echo esc_html( (string) $stats['images'] ); ?></strong>
                </div>
                <div class="featuredall-stat-box">
                    <span class="featuredall-stat-label"><?php esc_html_e( 'Zuletzt aktualisiert', 'featured-all' ); ?></span>
                    <strong class="featuredall-stat-value"><?php echo esc_html( $stats['last_updated'] ); ?></strong>
                </div>
            </div>
            <h2><?php esc_html_e( 'Letzte Beiträge mit Featured Video', 'featured-all' ); ?></h2>
            <?php $this->render_recent_videos_table(); ?>
            <p>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=featuredall-videos' ) ); ?>"><?php esc_html_e( 'Alle Videos anzeigen', 'featured-all' ); ?></a>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=featuredall-settings' ) ); ?>"><?php esc_html_e( 'Einstellungen', 'featured-all' ); ?></a>
            </p>
        </div>
        <?php
    }

    private function gather_stats(): array {
        $settings = $this->get_settings();
        $videos   = new \WP_Query(
            array(
                'post_type'      => $settings['post_types'],
                'posts_per_page' => 1,
                'meta_query'     => array(
                    array(
                        'key'     => '_fall_enable',
                        'value'   => '1',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_fall_url',
                        'value'   => '',
                        'compare' => '!=',
                    ),
                ),
                'fields'         => 'ids',
            )
        );
        $images = new \WP_Query(
            array(
                'post_type'      => $settings['post_types'],
                'posts_per_page' => 1,
                'meta_query'     => array(
                    array(
                        'key'     => '_thumbnail_id',
                        'compare' => 'EXISTS',
                    ),
                ),
                'fields'         => 'ids',
            )
        );

        $last_updated_query = new \WP_Query(
            array(
                'post_type'      => $settings['post_types'],
                'posts_per_page' => 1,
                'meta_query'     => array(
                    array(
                        'key'     => '_fall_enable',
                        'value'   => '1',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_fall_url',
                        'value'   => '',
                        'compare' => '!=',
                    ),
                ),
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'fields'         => 'ids',
            )
        );

        $last_updated = $last_updated_query->have_posts() ? get_post_modified_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), false, $last_updated_query->posts[0] ) : __( 'Keine Daten', 'featured-all' );

        return array(
            'videos'       => (int) $videos->found_posts,
            'images'       => (int) $images->found_posts,
            'last_updated' => $last_updated,
        );
    }

    private function render_recent_videos_table(): void {
        $settings = $this->get_settings();
        $query    = new \WP_Query(
            array(
                'post_type'      => $settings['post_types'],
                'posts_per_page' => 5,
                'meta_query'     => array(
                    array(
                        'key'     => '_fall_enable',
                        'value'   => '1',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_fall_url',
                        'value'   => '',
                        'compare' => '!=',
                    ),
                ),
            )
        );
        ?>
        <table class="widefat fixed striped featuredall-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Titel', 'featured-all' ); ?></th>
                    <th><?php esc_html_e( 'Post Type', 'featured-all' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'featured-all' ); ?></th>
                    <th><?php esc_html_e( 'Datum', 'featured-all' ); ?></th>
                    <th><?php esc_html_e( 'Video-Quelle', 'featured-all' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( $query->have_posts() ) : ?>
                <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                    <?php $type = $this->detect_video_type( get_post_meta( get_the_ID(), '_fall_url', true ) ); ?>
                    <tr>
                        <td><strong><a href="<?php echo esc_url( get_edit_post_link( get_the_ID() ) ); ?>"><?php the_title(); ?></a></strong></td>
                        <td><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? '' ); ?></td>
                        <td><?php echo esc_html( get_post_status_object( get_post_status() )->label ?? '' ); ?></td>
                        <td><?php echo esc_html( get_the_date() ); ?></td>
                        <td><?php echo esc_html( strtoupper( $type ) ); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else : ?>
                <tr><td colspan="5"><?php esc_html_e( 'Keine Einträge gefunden.', 'featured-all' ); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php wp_reset_postdata();
    }

    /**
     * Videos overview page.
     */
    public function render_videos_page(): void {
        $settings = $this->get_settings();
        $paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $query = new \WP_Query(
            array(
                'post_type'      => $settings['post_types'],
                'posts_per_page' => 10,
                'paged'          => $paged,
                'meta_query'     => array(
                    array(
                        'key'     => '_fall_enable',
                        'value'   => '1',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_fall_url',
                        'value'   => '',
                        'compare' => '!=',
                    ),
                ),
            )
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Beiträge/Seiten mit Featured Video', 'featured-all' ); ?></h1>
            <table class="widefat fixed striped featuredall-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Titel', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Post Type', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Datum', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Video-Quelle', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Vorschau', 'featured-all' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( $query->have_posts() ) : ?>
                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        <?php
                        $type       = $this->detect_video_type( get_post_meta( get_the_ID(), '_fall_url', true ) );
                        $poster     = $this->resolve_video_thumbnail( get_the_ID() );
                        ?>
                        <tr>
                            <td><strong><a href="<?php echo esc_url( get_edit_post_link( get_the_ID() ) ); ?>"><?php the_title(); ?></a></strong></td>
                            <td><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? '' ); ?></td>
                            <td><?php echo esc_html( get_post_status_object( get_post_status() )->label ?? '' ); ?></td>
                            <td><?php echo esc_html( get_the_date() ); ?></td>
                            <td><?php echo esc_html( strtoupper( $type ) ); ?></td>
                            <td class="featuredall-thumb-cell">
                                <?php if ( $poster ) : ?>
                                    <img src="<?php echo esc_url( $poster ); ?>" alt="" />
                                <?php else : ?>
                                    <span class="featuredall-thumb-placeholder">🎬</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else : ?>
                    <tr><td colspan="6"><?php esc_html_e( 'Keine Einträge gefunden.', 'featured-all' ); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php
            $big   = 999999999;
            $links = paginate_links(
                array(
                    'base'      => str_replace( $big, '%#%', esc_url( add_query_arg( 'paged', $big ) ) ),
                    'format'    => '',
                    'current'   => max( 1, $paged ),
                    'total'     => $query->max_num_pages,
                    'prev_text' => __( '« Previous', 'featured-all' ),
                    'next_text' => __( 'Next »', 'featured-all' ),
                )
            );
            if ( $links ) {
                echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
            }
            wp_reset_postdata();
            ?>
        </div>
        <?php
    }

    public function render_images_page(): void {
        $settings = $this->get_settings();
        $paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $query = new \WP_Query(
            array(
                'post_type'      => $settings['post_types'],
                'posts_per_page' => 10,
                'paged'          => $paged,
                'meta_query'     => array(
                    array(
                        'key'     => '_thumbnail_id',
                        'compare' => 'EXISTS',
                    ),
                ),
            )
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Beiträge/Seiten mit Featured Image', 'featured-all' ); ?></h1>
            <table class="widefat fixed striped featuredall-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Titel', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Post Type', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Datum', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Thumbnail', 'featured-all' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( $query->have_posts() ) : ?>
                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        <tr>
                            <td><strong><a href="<?php echo esc_url( get_edit_post_link( get_the_ID() ) ); ?>"><?php the_title(); ?></a></strong></td>
                            <td><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? '' ); ?></td>
                            <td><?php echo esc_html( get_post_status_object( get_post_status() )->label ?? '' ); ?></td>
                            <td><?php echo esc_html( get_the_date() ); ?></td>
                            <td class="featuredall-thumb-cell"><?php echo get_the_post_thumbnail( get_the_ID(), 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'Keine Einträge gefunden.', 'featured-all' ); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php
            $big   = 999999999;
            $links = paginate_links(
                array(
                    'base'      => str_replace( $big, '%#%', esc_url( add_query_arg( 'paged', $big ) ) ),
                    'format'    => '',
                    'current'   => max( 1, $paged ),
                    'total'     => $query->max_num_pages,
                    'prev_text' => __( '« Previous', 'featured-all' ),
                    'next_text' => __( 'Next »', 'featured-all' ),
                )
            );
            if ( $links ) {
                echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
            }
            wp_reset_postdata();
            ?>
        </div>
        <?php
    }

    private function resolve_video_thumbnail( int $post_id ): string {
        $poster_id = (int) get_post_meta( $post_id, '_fall_video_poster_id', true );
        if ( $poster_id ) {
            $poster = wp_get_attachment_image_url( $poster_id, 'medium' );
            if ( $poster ) {
                return $poster;
            }
        }

        $attachment_id = (int) get_post_meta( $post_id, '_fall_video_attachment_id', true );
        if ( $attachment_id ) {
            $thumb = wp_get_attachment_image_url( $attachment_id, 'medium' );
            if ( $thumb ) {
                return $thumb;
            }
        }

        if ( has_post_thumbnail( $post_id ) ) {
            $thumb = get_the_post_thumbnail_url( $post_id, 'medium' );
            if ( $thumb ) {
                return $thumb;
            }
        }

        $url  = get_post_meta( $post_id, '_fall_url', true );
        $type = $this->detect_video_type( $url );
        if ( 'youtube' === $type ) {
            if ( preg_match( '/(?:v=|be\/)([\w-]+)/', $url, $match ) ) {
                return 'https://img.youtube.com/vi/' . $match[1] . '/hqdefault.jpg';
            }
        }

        return '';
    }

    /**
     * Enqueue admin assets only on plugin screens.
     */
    public function enqueue_admin_assets( string $hook ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        $allowed = array(
            'toplevel_page_featuredall',
            'featuredall_page_featuredall-settings',
            'featuredall_page_featuredall-videos',
            'featuredall_page_featuredall-images',
        );

        if ( in_array( $screen->id, $allowed, true ) ) {
            wp_enqueue_style( 'featuredall-admin', FEATUREDALL_URL . 'assets/css/admin.css', array(), FEATUREDALL_VERSION );
            wp_enqueue_media();
            wp_enqueue_script( 'featuredall-admin', FEATUREDALL_URL . 'assets/js/admin.js', array( 'jquery' ), FEATUREDALL_VERSION, true );
            wp_localize_script(
                'featuredall-admin',
                'featuredallAdmin',
                array(
                    'tabs' => true,
                )
            );
        }
    }
}
