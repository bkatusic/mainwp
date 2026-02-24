<?php
/**
 * Manage MainWP UI Widget Layouts.
 *
 * Handles saving, loading, and deleting custom widget layout configurations
 * for dashboard pages. Allows users to save their preferred widget arrangements.
 *
 * @package     MainWP/Dashboard
 */

namespace MainWP\Dashboard;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MainWP_Ui_Manage_Widgets_Layout
 *
 * Manages custom widget layout configurations with save/load/delete functionality.
 *
 * @package MainWP\Dashboard
 */
class MainWP_Ui_Manage_Widgets_Layout { // phpcs:ignore Generic.Classes.OpeningBraceSameLine.ContentAfterBrace -- NOSONAR.

    /**
     * Singleton instance of the class.
     *
     * @static
     *
     * @var MainWP_Ui_Manage_Widgets_Layout|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance of the class.
     *
     * @static
     *
     * @return MainWP_Ui_Manage_Widgets_Layout Singleton instance.
     */
    public static function get_instance() {
        if ( null === static::$instance ) {
            static::$instance = new self();
        }
        return static::$instance;
    }

    /**
     * Constructor.
     *
     * Public constructor to enforce singleton pattern.
     */
    public function __construct() {
    }

    /**
     * Initialize admin hooks.
     *
     * Registers AJAX handlers for save, load, and delete layout operations.
     *
     * @return void
     */
    public function admin_init() {
        MainWP_Post_Handler::instance()->add_action( 'mainwp_ui_save_widgets_layout', array( $this, 'ajax_save_widgets_layout' ) );
        MainWP_Post_Handler::instance()->add_action( 'mainwp_ui_load_widgets_layout', array( $this, 'ajax_load_widgets_layout' ) );
        MainWP_Post_Handler::instance()->add_action( 'mainwp_ui_delete_widgets_layout', array( $this, 'ajax_delete_widgets_layout' ) );
    }

    /**
     * Render layout management UI controls.
     *
     * Outputs the layout dropdown button and JavaScript handlers for managing
     * widget layout configurations on the current screen.
     *
     * @param string $screen_id Current screen identifier.
     *
     * @return void
     */
    public static function render_edit_layout( $screen_id ) {
        $screen_slug    = strtolower( $screen_id );
        $saved_segments = static::set_get_widgets_layout( false, array(), $screen_slug );
        ?>

        <div class="ui mini basic top left pointing dropdown button mainwp-768-hide">
            <div><i class="border all icon"></i><span class="mainwp-768-hide"><?php esc_html_e( 'Layout', 'mainwp' ); ?></span></div>
            <div class="menu">
                <a class="item" id="mainwp-manage-widgets-load-saved-layout-button" selected-layout-id="" settings-slug="<?php echo esc_attr( $screen_slug ); ?>"><?php esc_html_e( 'Save Layout', 'mainwp' ); ?></a>
                <?php if ( ! empty( $saved_segments ) ) : ?>
                <a class="item" id="mainwp-manage-widgets-ui-choose-layout"><?php esc_html_e( 'Load Layout', 'mainwp' ); ?></a>
                <?php endif; ?>
            </div>
        </div>

        <script type="text/javascript">
            jQuery( document ).ready( function() {
                mainwp_init_widget_layout_handlers();
            } );
        </script>
        <?php
    }

    /**
     * Get or set widget layout configurations for a screen.
     *
     * Retrieves or saves widget layout configurations stored in user meta.
     * Each screen can have multiple saved layouts identified by unique IDs.
     *
     * @param bool   $set_val        Whether to set (true) or get (false) the value.
     * @param array  $saved_segments Array of layout configurations to save (when $set_val is true).
     * @param string $save_field     Screen identifier for the layout (e.g., 'overview', 'sites').
     *
     * @return array Array of saved layout configurations, empty array if none exist.
     */
    public static function set_get_widgets_layout( $set_val = false, $saved_segments = array(), $save_field = 'overview' ) {
        global $current_user;

        $field = 'mainwp_' . sanitize_text_field( $save_field ) . '_widgets_saved_layout';

        if ( $current_user && ! empty( $current_user->ID ) ) {
            if ( $set_val ) {
                update_user_option( $current_user->ID, $field, $saved_segments );
            } else {
                $values = get_user_option( $field, array() );
                if ( ! is_array( $values ) ) {
                    $values = array();
                }
                return $values;
            }
        }
        return array();
    }


    /**
     * AJAX handler to save widget layout configuration.
     *
     * Processes POST request to save the current widget layout arrangement.
     * Validates nonce, sanitizes input, and stores layout in user meta.
     *
     * @since 5.0.0
     *
     * @return void Outputs JSON response with 'result' => 'SUCCESS' or error.
     */
    public function ajax_save_widgets_layout() { //phpcs:ignore -- NOSONAR - complex.
        MainWP_Post_Handler::instance()->check_security( 'mainwp_ui_save_widgets_layout' );
        //phpcs:disable WordPress.Security.NonceVerification.Missing

        $seg_id = ! empty( $_POST['seg_id'] ) ? sanitize_text_field( wp_unslash( $_POST['seg_id'] ) ) : time();
        $wgids  = is_array( $_POST['wgids'] ) ? $_POST['wgids'] : array(); // phpcs:ignore -- NOSONAR - ok.
        $items  = is_array( $_POST['order'] ) ? $_POST['order'] : array(); // phpcs:ignore -- NOSONAR - ok.

        $slug  = isset( $_POST['settings_slug'] ) ? sanitize_text_field( wp_unslash($_POST['settings_slug'] ))  : 'overview'; // phpcs:ignore -- NOSONAR - ok.

        if ( empty( $slug ) ) {
            $slug = 'overview';
        }

        $layout_items = array();
        if ( is_array( $wgids ) && is_array( $items ) ) {
            foreach ( $wgids as $idx => $wgid ) {
                if ( isset( $items[ $idx ] ) ) {
                    $pre = 'widget-'; // compatible with #compatible-widgetid.
                    if ( 0 === strpos( $wgid, $pre ) ) {
                        $wgid = substr( $wgid, strlen( $pre ) );
                    }
                    $layout_items[ $wgid ] = $items[ $idx ];
                }
            }
        }

        $save_layout = array(
            'name'   => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : 'N/A',
            'layout' => $layout_items,
        );

        //phpcs:enable WordPress.Security.NonceVerification.Missing

        $saved_segments = static::set_get_widgets_layout( false, array(), $slug );
        if ( ! is_array( $saved_segments ) ) {
            $saved_segments = array();
        }
        $saved_segments[ $seg_id ] = $save_layout;
        static::set_get_widgets_layout( true, $saved_segments, $slug );
        die( wp_json_encode( array( 'result' => 'SUCCESS' ) ) );
    }


    /**
     * AJAX handler to load saved widget layouts.
     *
     * Retrieves all saved widget layout configurations for the current screen
     * and returns them as a dropdown select element HTML.
     *
     * @since 5.0.0
     *
     * @return void Outputs JSON response with 'result' containing HTML dropdown or empty string.
     */
    public function ajax_load_widgets_layout() {
        MainWP_Post_Handler::instance()->check_security( 'mainwp_ui_load_widgets_layout' );
        $slug  = isset( $_POST['settings_slug'] ) ? sanitize_text_field( wp_unslash($_POST['settings_slug'] ))  : 'overview'; // phpcs:ignore -- NOSONAR - ok.
        if ( empty( $slug ) ) {
            $slug = 'overview';
        }
        $saved_segments = static::set_get_widgets_layout( false, array(), $slug );
        $list_segs      = '';
        if ( is_array( $saved_segments ) && ! empty( $saved_segments ) ) {
            $list_segs .= '<select id="mainwp-edit-layout-filters" class="ui fluid dropdown">';
            $list_segs .= '<option value="">' . esc_html__( 'Select layout', 'mainwp' ) . '</option>';
            foreach ( $saved_segments as $sid => $values ) {
                if ( empty( $values['name'] ) ) {
                    continue;
                }
                $list_segs .= '<option value="' . esc_attr( $sid ) . '">' . esc_html( $values['name'] ) . '</option>';
            }
            $list_segs .= '</select>';
        }
        die( wp_json_encode( array( 'result' => $list_segs ) ) ); //phpcs:ignore -- ok.
    }

    /**
     * AJAX handler to delete a saved widget layout.
     *
     * Removes a specific layout configuration from user meta by layout ID.
     * Validates nonce and layout existence before deletion.
     *
     * @since 5.0.0
     *
     * @return void Outputs JSON response with 'result' => 'SUCCESS' or 'error' message.
     */
    public function ajax_delete_widgets_layout() {
        MainWP_Post_Handler::instance()->check_security( 'mainwp_ui_delete_widgets_layout' );
        $seg_id = ! empty( $_POST['seg_id'] ) ? sanitize_text_field( wp_unslash( $_POST['seg_id'] ) ) : 0; //phpcs:ignore -- ok.
        $slug = ! empty( $_POST['settings_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['settings_slug'] ) ) : 'overview'; //phpcs:ignore -- ok.

        $saved_segments = static::set_get_widgets_layout( false, array(), $slug );
        if ( ! empty( $seg_id ) && is_array( $saved_segments ) && isset( $saved_segments[ $seg_id ] ) ) {
            unset( $saved_segments[ $seg_id ] );
            static::set_get_widgets_layout( true, $saved_segments, $slug );
            die( wp_json_encode( array( 'result' =>'SUCCESS' ) ) ); //phpcs:ignore -- ok.
        }
        die( wp_json_encode( array( 'error' => esc_html__( 'Layout not found. Please try again.', 'mainwp' ) ) ) ); //phpcs:ignore -- ok.
    }

    /**
     * Render the layout management modal dialog.
     *
     * Outputs HTML for the modal window used to save, load, and delete
     * widget layout configurations. Includes form fields and action buttons.
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function render_modal_save_layout() {
        ?>
        <div id="mainwp-common-edit-widgets-layout-modal" class="ui tiny modal">
            <i class="close icon" id="mainwp-common-filter-layout-cancel"></i>
            <div class="header"><?php esc_html_e( 'Save Layout', 'mainwp' ); ?></div>
            <div class="content" id="mainwp-common-widgets-layout-content">
                <div id="mainwp-common-edit-widgets-layout-status" class="ui message" style="display:none;"></div>
                <div id="mainwp-common-edit-widgets-layout-edit-fields" class="ui form">
                    <div class="field">
                        <label><?php esc_html_e( 'Layout name', 'mainwp' ); ?></label>
                    </div>
                    <div class="field">
                        <input type="text" id="mainwp-common-edit-widgets-layout-name" value=""/>
                    </div>
                </div>
                <div id="mainwp-common-layout-widgets-select-fields" style="display:none;">
                    <div class="field">
                        <div id="mainwp-common-edit-widgets-layout-lists-wrapper"></div>
                    </div>
                </div>
            </div>
            <div class="actions">
                <div class="ui grid">
                    <div class="eight wide left aligned middle aligned column">

                    </div>
                    <div class="eight wide column">
                        <input type="button" class="ui green mini button disabled" id="mainwp-common-edit-widgets-layout-save-button" value="<?php esc_attr_e( 'Save Layout', 'mainwp' ); ?>"/>
                        <input type="button" class="ui green mini button disabled" id="mainwp-common-edit-widgets-select-layout-button" value="<?php esc_attr_e( 'Choose Layout', 'mainwp' ); ?>" style="display:none;"/>
                        <input type="button" class="ui basic mini button disabled" id="mainwp-common-edit-widgets-layout-delete-button" value="<?php esc_attr_e( 'Delete', 'mainwp' ); ?>" style="display:none;"/>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
