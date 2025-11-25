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
    public function get_settings(): array {
        $defaults = $this->get_default_settings();
        $settings = get_option( $this->option_name, array() );

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
     * Register the Featured All metabox.
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

        $url          = get_post_meta( $post->ID, '_fall_url', true );
        $enable       = (int) get_post_meta( $post->ID, '_fall_enable', true );
        $type         = $this->detect_video_type( $url );
        $video_id     = (int) get_post_meta( $post->ID, '_fall_video_attachment_id', true );
        $poster_id    = (int) get_post_meta( $post->ID, '_fall_video_poster_id', true );
        $video_url    = $video_id ? wp_get_attachment_url( $video_id ) : '';
        $poster_url   = $poster_id ? wp_get_attachment_image_url( $poster_id, 'thumbnail' ) : '';
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
                <?php
                if ( $poster_url ) {
                    echo '<img src="' . esc_url( $poster_url ) . '" alt="" />';
                }
                ?>
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
                <?php
                printf(
                    /* translators: %s: video type. */
                    esc_html__( 'Detected type: %s', 'featured-all' ),
                    esc_html( strtoupper( 'unknown' === $type ? __( 'Extern', 'featured-all' ) : $type ) )
                );
                ?>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Save metabox data.
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

    /**
     * Sanitize URLs for video usage.
     */
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

    /**
     * Detect the video type.
     */
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
     * Register admin menu pages.
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
            __( 'Featured All – Übersicht', 'featured-all' ),
            __( 'Featured All', 'featured-all' ),
            'manage_options',
            'featuredall',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'featuredall',
            __( 'Featured All – Videos', 'featured-all' ),
            __( 'Videos 📽', 'featured-all' ),
            'manage_options',
            'featuredall-videos',
            array( $this, 'render_videos_page' )
        );

        add_submenu_page(
            'featuredall',
            __( 'Featured All – Images', 'featured-all' ),
            __( 'Bilder 🖼', 'featured-all' ),
            'manage_options',
            'featuredall-images',
            array( $this, 'render_images_page' )
        );

        add_submenu_page(
            'featuredall',
            __( 'Featured All – Einstellungen', 'featured-all' ),
            __( 'Einstellungen ⚙️', 'featured-all' ),
            'manage_options',
            'featuredall-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings(): void {
        register_setting( 'featuredall_settings_group', $this->option_name, array( $this, 'sanitize_settings' ) );

        add_settings_section(
            'featuredall_main_section',
            __( 'Featured All Settings', 'featured-all' ),
            function() {
                echo '<p>' . esc_html__( 'Configure how Featured All behaves across your site.', 'featured-all' ) . '</p>';
            },
            'featuredall_settings_page'
        );

        add_settings_field(
            'featuredall_post_types',
            __( 'Active Post Types', 'featured-all' ),
            array( $this, 'render_post_types_field' ),
            'featuredall_settings_page',
            'featuredall_main_section'
        );

        add_settings_field(
            'featuredall_position',
            __( 'Video position in single view', 'featured-all' ),
            array( $this, 'render_position_field' ),
            'featuredall_settings_page',
            'featuredall_main_section'
        );

        add_settings_field(
            'featuredall_hide_featured_image',
            __( 'Hide featured image when video is active', 'featured-all' ),
            array( $this, 'render_hide_image_field' ),
            'featuredall_settings_page',
            'featuredall_main_section'
        );

        add_settings_field(
            'featuredall_featured_image_selector',
            __( 'Featured image CSS selector', 'featured-all' ),
            array( $this, 'render_selector_field' ),
            'featuredall_settings_page',
            'featuredall_main_section'
        );

        add_settings_section(
            'featuredall_player_section',
            __( 'HTML5-Player', 'featured-all' ),
            function() {
                echo '<p>' . esc_html__( 'Control how the HTML5 player behaves.', 'featured-all' ) . '</p>';
            },
            'featuredall_settings_page'
        );

        add_settings_field( 'featuredall_player_controls', __( 'Steuerelemente anzeigen (controls)', 'featured-all' ), array( $this, 'render_player_controls_field' ), 'featuredall_settings_page', 'featuredall_player_section' );
        add_settings_field( 'featuredall_player_autoplay', __( 'Autoplay aktivieren (Browser können das blockieren)', 'featured-all' ), array( $this, 'render_player_autoplay_field' ), 'featuredall_settings_page', 'featuredall_player_section' );
        add_settings_field( 'featuredall_player_muted', __( 'Videos stumm starten (muted)', 'featured-all' ), array( $this, 'render_player_muted_field' ), 'featuredall_settings_page', 'featuredall_player_section' );
        add_settings_field( 'featuredall_player_loop', __( 'Videos automatisch wiederholen (loop)', 'featured-all' ), array( $this, 'render_player_loop_field' ), 'featuredall_settings_page', 'featuredall_player_section' );
        add_settings_field( 'featuredall_player_preload', __( 'Preload Verhalten', 'featured-all' ), array( $this, 'render_player_preload_field' ), 'featuredall_settings_page', 'featuredall_player_section' );
        add_settings_field( 'featuredall_player_max_width', __( 'Maximale Breite des Video-Containers', 'featured-all' ), array( $this, 'render_player_max_width_field' ), 'featuredall_settings_page', 'featuredall_player_section' );
        add_settings_field( 'featuredall_player_aspect_ratio', __( 'Seitenverhältnis (z.B. 16:9)', 'featured-all' ), array( $this, 'render_player_aspect_ratio_field' ), 'featuredall_settings_page', 'featuredall_player_section' );
        add_settings_field( 'featuredall_player_overlay_play_icon', __( 'Overlay-Play-Icon anzeigen', 'featured-all' ), array( $this, 'render_player_overlay_field' ), 'featuredall_settings_page', 'featuredall_player_section' );
        add_settings_field( 'featuredall_player_fade_in', __( 'Video beim Laden weich einblenden (Fade-In)', 'featured-all' ), array( $this, 'render_player_fade_field' ), 'featuredall_settings_page', 'featuredall_player_section' );
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

        $position = $input['position'] ?? $defaults['position'];
        if ( in_array( $position, array( 'above_title', 'below_title' ), true ) ) {
            $output['position'] = $position;
        } else {
            $output['position'] = $defaults['position'];
        }

        $output['hide_featured_image']      = ! empty( $input['hide_featured_image'] ) ? 1 : 0;
        $selector                           = $input['featured_image_selector'] ?? $defaults['featured_image_selector'];
        $output['featured_image_selector']  = sanitize_text_field( $selector );
        $output['player_controls']          = ! empty( $input['player_controls'] ) ? 1 : 0;
        $output['player_autoplay']          = ! empty( $input['player_autoplay'] ) ? 1 : 0;
        $output['player_muted']             = ! empty( $input['player_muted'] ) ? 1 : 0;
        $output['player_loop']              = ! empty( $input['player_loop'] ) ? 1 : 0;

        $preload = $input['player_preload'] ?? $defaults['player_preload'];
        $output['player_preload'] = in_array( $preload, array( 'auto', 'metadata', 'none' ), true ) ? $preload : $defaults['player_preload'];

        $max_width = $input['player_max_width'] ?? $defaults['player_max_width'];
        $output['player_max_width'] = sanitize_text_field( $max_width );

        $aspect_ratio = $input['player_aspect_ratio'] ?? $defaults['player_aspect_ratio'];
        $output['player_aspect_ratio'] = sanitize_text_field( $aspect_ratio );

        $output['player_overlay_play_icon'] = ! empty( $input['player_overlay_play_icon'] ) ? 1 : 0;
        $output['player_fade_in']           = ! empty( $input['player_fade_in'] ) ? 1 : 0;

        return wp_parse_args( $output, $defaults );
    }

    /**
     * Render post types field.
     */
    public function render_post_types_field(): void {
        $settings      = $this->get_settings();
        $current_types = $settings['post_types'];
        $post_types    = get_post_types( array( 'public' => true ), 'objects' );

        foreach ( $post_types as $post_type ) {
            ?>
            <label>
                <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[post_types][]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $current_types, true ) ); ?> />
                <?php echo esc_html( $post_type->labels->singular_name ); ?>
            </label><br />
            <?php
        }
    }

    /**
     * Render position field.
     */
    public function render_position_field(): void {
        $settings = $this->get_settings();
        ?>
        <label>
            <input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[position]" value="above_title" <?php checked( $settings['position'], 'above_title' ); ?> />
            <?php esc_html_e( 'Above title', 'featured-all' ); ?>
        </label><br />
        <label>
            <input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[position]" value="below_title" <?php checked( $settings['position'], 'below_title' ); ?> />
            <?php esc_html_e( 'Below title (before content)', 'featured-all' ); ?>
        </label>
        <?php
    }

    /**
     * Render hide featured image field.
     */
    public function render_hide_image_field(): void {
        $settings = $this->get_settings();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[hide_featured_image]" value="1" <?php checked( $settings['hide_featured_image'], 1 ); ?> />
            <?php esc_html_e( 'Hide featured image when a video is active', 'featured-all' ); ?>
        </label>
        <?php
    }

    /**
     * Render selector field.
     */
    public function render_selector_field(): void {
        $settings = $this->get_settings();
        ?>
        <input type="text" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[featured_image_selector]" value="<?php echo esc_attr( $settings['featured_image_selector'] ); ?>" />
        <p class="description"><?php esc_html_e( 'Selectors for the featured image container, comma separated.', 'featured-all' ); ?></p>
        <?php
    }

    public function render_player_controls_field(): void {
        $settings = $this->get_settings();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_controls]" value="1" <?php checked( $settings['player_controls'], 1 ); ?> />
            <?php esc_html_e( 'Steuerelemente anzeigen (controls)', 'featured-all' ); ?>
        </label>
        <?php
    }

    public function render_player_autoplay_field(): void {
        $settings = $this->get_settings();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_autoplay]" value="1" <?php checked( $settings['player_autoplay'], 1 ); ?> />
            <?php esc_html_e( 'Autoplay aktivieren (Browser können das blockieren)', 'featured-all' ); ?>
        </label>
        <?php
    }

    public function render_player_muted_field(): void {
        $settings = $this->get_settings();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_muted]" value="1" <?php checked( $settings['player_muted'], 1 ); ?> />
            <?php esc_html_e( 'Videos stumm starten (muted)', 'featured-all' ); ?>
        </label>
        <?php
    }

    public function render_player_loop_field(): void {
        $settings = $this->get_settings();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_loop]" value="1" <?php checked( $settings['player_loop'], 1 ); ?> />
            <?php esc_html_e( 'Videos automatisch wiederholen (loop)', 'featured-all' ); ?>
        </label>
        <?php
    }

    public function render_player_preload_field(): void {
        $settings = $this->get_settings();
        $options  = array(
            'auto'     => __( 'Automatisch', 'featured-all' ),
            'metadata' => __( 'Nur Metadaten', 'featured-all' ),
            'none'     => __( 'Nichts vorladen', 'featured-all' ),
        );
        ?>
        <select name="<?php echo esc_attr( $this->option_name ); ?>[player_preload]">
            <?php foreach ( $options as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['player_preload'], $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_player_max_width_field(): void {
        $settings = $this->get_settings();
        ?>
        <input type="text" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[player_max_width]" value="<?php echo esc_attr( $settings['player_max_width'] ); ?>" />
        <?php
    }

    public function render_player_aspect_ratio_field(): void {
        $settings = $this->get_settings();
        ?>
        <input type="text" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[player_aspect_ratio]" value="<?php echo esc_attr( $settings['player_aspect_ratio'] ); ?>" />
        <p class="description"><?php esc_html_e( 'Example: 16:9 or 4:3', 'featured-all' ); ?></p>
        <?php
    }

    public function render_player_overlay_field(): void {
        $settings = $this->get_settings();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_overlay_play_icon]" value="1" <?php checked( $settings['player_overlay_play_icon'], 1 ); ?> />
            <?php esc_html_e( 'Overlay-Play-Icon anzeigen', 'featured-all' ); ?>
        </label>
        <?php
    }

    public function render_player_fade_field(): void {
        $settings = $this->get_settings();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[player_fade_in]" value="1" <?php checked( $settings['player_fade_in'], 1 ); ?> />
            <?php esc_html_e( 'Video beim Laden weich einblenden (Fade-In)', 'featured-all' ); ?>
        </label>
        <?php
    }

    /**
     * Render settings page.
     */
    public function render_settings_page(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Featured All – Einstellungen', 'featured-all' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'featuredall_settings_group' );
                do_settings_sections( 'featuredall_settings_page' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render dashboard overview page.
     */
    public function render_dashboard_page(): void {
        $stats = $this->get_dashboard_stats();
        ?>
        <div class="wrap featuredall-dashboard">
            <h1><?php esc_html_e( 'Featured All – Übersicht', 'featured-all' ); ?></h1>
            <div class="featuredall-stats">
                <div class="featuredall-stat-box">
                    <p class="featuredall-stat-label"><?php esc_html_e( 'Aktive Featured Videos', 'featured-all' ); ?></p>
                    <p class="featuredall-stat-value"><?php echo esc_html( $stats['video_count'] ); ?></p>
                </div>
                <div class="featuredall-stat-box">
                    <p class="featuredall-stat-label"><?php esc_html_e( 'Beiträge mit Featured Image', 'featured-all' ); ?></p>
                    <p class="featuredall-stat-value"><?php echo esc_html( $stats['image_count'] ); ?></p>
                </div>
                <div class="featuredall-stat-box">
                    <p class="featuredall-stat-label"><?php esc_html_e( 'Zuletzt aktualisiertes Featured Video', 'featured-all' ); ?></p>
                    <p class="featuredall-stat-value"><?php echo esc_html( $stats['last_video'] ); ?></p>
                </div>
            </div>

            <h2><?php esc_html_e( 'Letzte Beiträge mit Featured Video', 'featured-all' ); ?></h2>
            <table class="widefat fixed striped">
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
                    <?php foreach ( $stats['recent_videos'] as $item ) : ?>
                        <tr>
                            <td><strong><a href="<?php echo esc_url( get_edit_post_link( $item->ID ) ); ?>"><?php echo esc_html( get_the_title( $item->ID ) ); ?></a></strong></td>
                            <td><?php echo esc_html( get_post_type_object( $item->post_type )->labels->singular_name ?? '' ); ?></td>
                            <td><?php echo esc_html( get_post_status_object( $item->post_status )->label ?? '' ); ?></td>
                            <td><?php echo esc_html( get_the_date( '', $item->ID ) ); ?></td>
                            <td><?php echo esc_html( strtoupper( $this->detect_video_type( get_post_meta( $item->ID, '_fall_url', true ) ) ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ( empty( $stats['recent_videos'] ) ) : ?>
                        <tr><td colspan="5"><?php esc_html_e( 'Keine Einträge gefunden.', 'featured-all' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p class="featuredall-actions">
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=featuredall-videos' ) ); ?>"><?php esc_html_e( 'Alle Videos anzeigen', 'featured-all' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=featuredall-settings' ) ); ?>"><?php esc_html_e( 'Einstellungen', 'featured-all' ); ?></a>
            </p>
        </div>
        <?php
    }

    private function get_dashboard_stats(): array {
        $settings    = $this->get_settings();
        $post_types  = $settings['post_types'];
        $video_query = new \WP_Query(
            array(
                'post_type'      => $post_types,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_fall_enable',
                        'value'   => 1,
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

        $image_query = new \WP_Query(
            array(
                'post_type'      => $post_types,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'meta_query'     => array(
                    array(
                        'key'     => '_thumbnail_id',
                        'compare' => 'EXISTS',
                    ),
                ),
                'fields'         => 'ids',
            )
        );

        $last_video = new \WP_Query(
            array(
                'post_type'      => $post_types,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_fall_enable',
                        'value'   => 1,
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

        $recent_videos = new \WP_Query(
            array(
                'post_type'      => $post_types,
                'post_status'    => 'any',
                'posts_per_page' => 5,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_fall_enable',
                        'value'   => 1,
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

        $last_video_time = __( 'Keine Einträge', 'featured-all' );
        if ( $last_video->have_posts() ) {
            $last_video_time = get_the_modified_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_video->posts[0] );
        }

        return array(
            'video_count'  => $video_query->found_posts,
            'image_count'  => $image_query->found_posts,
            'last_video'   => $last_video_time,
            'recent_videos'=> $recent_videos->posts,
        );
    }

    /**
     * Render videos overview page.
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
                    'relation' => 'AND',
                    array(
                        'key'     => '_fall_enable',
                        'value'   => 1,
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
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Titel', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Post Type', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Datum', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Video-Quelle', 'featured-all' ); ?></th>
                        <th><?php esc_html_e( 'Thumbnail', 'featured-all' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( $query->have_posts() ) : ?>
                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        <?php
                        $url   = get_post_meta( get_the_ID(), '_fall_url', true );
                        $type  = $this->detect_video_type( $url );
                        $thumb = $this->get_video_thumbnail( get_the_ID(), $url, $type );
                        ?>
                        <tr>
                            <td>
                                <strong><a href="<?php echo esc_url( get_edit_post_link( get_the_ID() ) ); ?>"><?php the_title(); ?></a></strong>
                            </td>
                            <td><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? '' ); ?></td>
                            <td><?php echo esc_html( get_post_status_object( get_post_status() )->label ?? '' ); ?></td>
                            <td><?php echo esc_html( get_the_date() ); ?></td>
                            <td><?php echo esc_html( strtoupper( $type ) ); ?></td>
                            <td class="featuredall-thumb-cell"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
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

    private function get_video_thumbnail( int $post_id, string $url, string $type ): string {
        $poster_id = (int) get_post_meta( $post_id, '_fall_video_poster_id', true );
        if ( $poster_id ) {
            return wp_get_attachment_image( $poster_id, 'thumbnail' );
        }

        $video_id = (int) get_post_meta( $post_id, '_fall_video_attachment_id', true );
        if ( $video_id ) {
            $thumb = wp_get_attachment_image( $video_id, 'thumbnail' );
            if ( $thumb ) {
                return $thumb;
            }
        }

        if ( 'youtube' === $type ) {
            $video_id = $this->extract_youtube_id( $url );
            if ( $video_id ) {
                $src = sprintf( 'https://img.youtube.com/vi/%s/hqdefault.jpg', $video_id );
                return '<img src="' . esc_url( $src ) . '" alt="" />';
            }
        }

        return '<span aria-hidden="true">🎬</span>';
    }

    private function extract_youtube_id( string $url ): string {
        if ( preg_match( '/youtu\.be\/([^\?\&]+)/', $url, $matches ) && ! empty( $matches[1] ) ) {
            return $matches[1];
        }

        if ( preg_match( '/v=([^&]+)/', $url, $matches ) && ! empty( $matches[1] ) ) {
            return $matches[1];
        }

        if ( preg_match( '/embed\/([^?]+)/', $url, $matches ) && ! empty( $matches[1] ) ) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Render images overview page.
     */
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
            <table class="widefat fixed striped">
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
                            <td>
                                <strong><a href="<?php echo esc_url( get_edit_post_link( get_the_ID() ) ); ?>"><?php the_title(); ?></a></strong>
                            </td>
                            <td><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? '' ); ?></td>
                            <td><?php echo esc_html( get_post_status_object( get_post_status() )->label ?? '' ); ?></td>
                            <td><?php echo esc_html( get_the_date() ); ?></td>
                            <td><?php echo get_the_post_thumbnail( get_the_ID(), 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
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

    /**
     * Enqueue admin assets on plugin pages.
     */
    public function enqueue_admin_assets( string $hook ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        $allowed = array(
            'toplevel_page_featuredall',
            'featuredall_page_featuredall',
            'featuredall_page_featuredall-settings',
            'featuredall_page_featuredall-videos',
            'featuredall_page_featuredall-images',
            'post.php',
            'post-new.php',
        );

        if ( in_array( $screen->id, $allowed, true ) || ( in_array( $screen->base, array( 'post', 'post-new' ), true ) && in_array( $screen->post_type, $this->get_settings()['post_types'], true ) ) ) {
            wp_enqueue_style( 'featuredall-admin', FEATUREDALL_URL . 'assets/css/admin.css', array(), FEATUREDALL_VERSION );
            wp_enqueue_media();
            wp_enqueue_script( 'featuredall-admin', FEATUREDALL_URL . 'assets/js/admin.js', array(), FEATUREDALL_VERSION, true );
        }
    }
}
