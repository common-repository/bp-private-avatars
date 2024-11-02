<?php
/**
 * Plugin Name: SuitePlugins - Private Avatars for BuddyPress
 * Plugin URI:  http://suiteplugins.com
 * Description: Restrict user avatar to friends only and admin
 * Author:	    SuitePlugins
 * Author URI:  http://suiteplugins.com
 * Version:	    1.0.3.1.1
 * Requires PHP: 5.6
 * Text Domain: bp-private-avatar
 * Domain Path: /languages/
 * License:     GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'BP_Private_Avatar' ) ) :

	class BP_Private_Avatar {
		/**
		 * [$_instance description]
		 * @var [type]
		 */
		protected static $_instance = null;
		/**
		 * Metakey used
		 * @var string
		 */
		public $meta_key;
		/**
		 * Main BP_Private_Avatar Instance
		 *
		 * Ensures only one instance of BP_Private_Avatar is loaded or can be loaded.
		 *
		 * @static
		 * @return BP_Private_Avatar - Main instance
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Load constructor
		 */
		public function __construct() {
			add_filter( 'bp_core_fetch_avatar', array( $this, 'get_private_avatar' ), 12, 9 );
			add_action( 'bp_before_member_avatar_upload_content', array( $this, 'add_profile_avatar_setting' ) );
			add_action( 'wp_ajax_sp_private_avatar', array( $this, 'sp_set_private_avatar' ) );
			add_action( 'init', array( $this, 'bp_private_avatar_languages' ) );
		}
		/**
		 * Filters an avatar URL wrapped in an <img> element.
		 *
		 * @since 1.0.3
		 *
		 * @param string $img_src		   Full <img> element for an avatar.
		 * @param array  $params			Array of parameters for the request.
		 * @param string $item_id		   ID of the item requested.
		 * @param string $avatar_dir		Subdirectory where the requested avatar should be found.
		 * @param string $html_css_id	   ID attribute for avatar.
		 * @param string $html_width		Width attribute for avatar.
		 * @param string $html_height	   Height attribtue for avatar.
		 * @param string $avatar_folder_url Avatar URL path.
		 * @param string $avatar_folder_dir Avatar dir path.
		 */
		public function get_private_avatar( $img_src, $params, $item_id, $avatar_dir, $html_css_id, $html_width, $html_height, $avatar_folder_url, $avatar_folder_dir ) {
			//echo $params['object'];
			if ( 'user' != $params['object'] ) {
				return $img_src;
			}
			$user_option = get_user_meta( $item_id, '_make_avatar_private', true );
			if ( 1 != $user_option  ) {
				return $img_src;
			}

			$user_id = get_current_user_id();
			global $bp;
			//If user is looking at self, show avatar
			if ( $user_id == $item_id ) {
				return $img_src;
			}
			// Create CSS class html string
			$params['class'] = apply_filters( 'bp_core_avatar_class', $params['class'], $params['item_id'], $params['object'], $params );

			// Use an alias to leave the param unchanged
			$avatar_classes = $params['class'];
			if ( ! is_array( $avatar_classes ) ) {
				$avatar_classes = explode( ' ', $avatar_classes );
			}

			// merge classes
			$avatar_classes = array_merge( $avatar_classes, array(
				$params['object'] . '-' . $params['item_id'] . '-avatar',
				'avatar-' . $params['width'],
			) );

			// Sanitize each class
			$avatar_classes = array_map( 'sanitize_html_class', $avatar_classes );

			// populate the class attribute
			$html_class = ' class="' . join( ' ', $avatar_classes ) . ' photo"';

			$default_avatar = $bp->grav_default->$params['object'];
			$gravatar = apply_filters( 'bp_core_default_avatar_' . $params['object'], bp_core_avatar_default( 'local' ), $params );
			$displayed_user = $item_id;
			//check if friends component active
			if ( bp_is_active( 'friends' ) ) {
				//check if user is friends
				if ( friends_check_friendship( $user_id, $displayed_user ) || current_user_can( 'manage_options' ) ) {
					return apply_filters( 'sp_private_avatar_url', $img_src, $params, $params['item_id'], $params['avatar_dir'], $html_css_id, $html_width, $html_height, $avatar_folder_url, $avatar_folder_dir );
				}
			}

			//If user is a guest
			return apply_filters( 'sp_private_avatar_url', '<img src="' . $gravatar . '"' . $html_css_id . $html_class . $html_width . $html_height . ' />', $params, $params['item_id'], $params['avatar_dir'], $html_css_id, $html_width, $html_height, $avatar_folder_url, $avatar_folder_dir );
		}

		/**
		 * Add dropdown for selecting BuddyPress Avatar hiding
		 */
		public function add_profile_avatar_setting() {
			$select = get_user_meta( bp_displayed_user_id(), '_make_avatar_private', true );
		?>
		<style>
			.sp-private-avatar-update {display: inline-block;margin-left: 10px;font-weight: bold;}
		</style>
		<label><?php _e( 'Make Profile Photo Private', 'bp-private-avatar' ); ?>
		<select name="_make_friends_private" id="sp_make_avatar_private">
			<option value="1" <?php echo (1 == $select ? ' selected="selected" ' : ''); ?>><?php _e( 'Yes', 'bp-private-avatar' ); ?></option>
			<option value="0" <?php echo (0 == $select || empty( $select ) ? ' selected="selected" ' : ''); ?>><?php _e( 'No', 'bp-private-avatar' ); ?></option>
		</select></label>
		<hr />
		<script>
			jQuery(document).ready(function($) {
				jQuery(document).on("change", "#sp_make_avatar_private", function(){
					var obj = jQuery(this);
					var value = obj.val();
					jQuery.post( "<?php echo admin_url( 'admin-ajax.php' ); ?>", {
						action: "sp_private_avatar",
						setting: value,
						security: '<?php echo wp_create_nonce( 'bp-private-avatar-nonce-' . bp_displayed_user_id() ); ?>'
						})
					  .done(function( data ) {
						jQuery( '.sp-private-avatar-update' ).remove();
						obj.after( '<div class="sp-private-avatar-update"><?php echo __( 'Settings saved', 'bp-private-avatar' ); ?></div>' );
						jQuery( '.sp-private-avatar-update' ).delay(3000).fadeOut(1000);
					});
				});
			});
		</script>
		<?php
		}

		/**
		 * Saving private avatar settings
		 */
		public function sp_set_private_avatar() {
			$return = array();
			if ( wp_verify_nonce( $_POST['security'], 'bp-private-avatar-nonce-' . get_current_user_id() ) ) {
				if ( ! empty( $_POST['setting'] ) ) {
					update_user_meta( bp_displayed_user_id(), '_make_avatar_private', (int) sanitize_text_field( $_POST['setting'] ) );
					$return['option']	= 1;
				} else {
					delete_user_meta( bp_displayed_user_id(), '_make_avatar_private' );
					$return['option']	= 0;
				}
			}
			wp_send_json_success( $return );
		}

		/**
		 * Load language file for plugin
		 */
		function bp_private_avatar_languages() {

			$domain = 'bp-private-avatars';
			$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

			// wp-content/languages/plugin-name/plugin-name-de_DE.mo
			load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
			// wp-content/plugins/plugin-name/languages/plugin-name-de_DE.mo
			load_plugin_textdomain( $domain, false, basename( dirname( __FILE__ ) ) . '/languages/' );
		}
	}

	function sp_run_bp_avatar() {
		return BP_Private_Avatar::instance();
	}

endif;

add_action( 'bp_include', 'sp_run_bp_avatar' );
