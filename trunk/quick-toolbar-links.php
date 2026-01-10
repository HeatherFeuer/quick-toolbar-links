<?php
/**
 * Plugin Name: Quick Toolbar Links
 * Plugin URI: https://uniquelyyourshosting.com/extras/quick-toolbar-links
 * Text Domain: quick-toolbar-links
 * Domain Path: /languages
 * Description: Add frequently used menu links and custom links to the Admin Toolbar.
 * Version: 1.0.0
 * Author: HeatherFeuer (Originally Ecommnet)
 * Author URI: https://uniquelyyourshosting.com
 * License: GPL2
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires at least PHP: 7.4
 */

defined('ABSPATH') or die('Plugin file cannot be accessed directly.');
define( 'QUICTOLI_PLUGIN_FILE', __FILE__ );
define( 'QUICTOLI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QUICTOLI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if (!class_exists('Quick_Toolbar_Class')) {

	class Quick_Toolbar_Class {
		/**
		 * Plugin version number
		 * @var string
		 */
		public $version = '1.0.0';

		/**
		 * Single instance of the class
		 */
		protected static $_instance = null;
		
		/**
		 * Item options
		 */
		private $item_options;
		private $custom_item_options;

		/**
		 * Instance of the class
		 */
		public static function instance() {
			if (is_null(self::$_instance)) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Initiate the plugin by setting up actions and filters
		 */
		public function __construct() {

			// Enqueue Styles and Scripts
			add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
			add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));

			// Adding Menu Items
			add_action('admin_menu', array($this, 'add_admin_menu'));

			// Register Settings
			add_action('admin_init', array($this, 'register_settings'));

			// Submit Custom Links
			add_action('admin_init', array($this, 'custom_submit'));

			// Adding Items to Toolbar
			add_action('admin_bar_menu', array($this, 'add_toolbar_items'), 100);

			// AJAX Actions with proper security
			add_action('wp_ajax_quictoli_delete_custom_link', array($this, 'delete_custom_link'));

			// Load plugin options if available
			$this->item_options = get_option('_quictoli_items', array());
			$this->custom_item_options = get_option('_quictoli_custom_items', array());
		}

		public function enqueue_styles() {
			wp_enqueue_style( 'dashicons' );
			add_thickbox();
			wp_register_style( 'quictoli_wp_admin_css', plugin_dir_url( __FILE__ ) . 'css/quictoli-admin-styles.css', false, $this->version );
			wp_enqueue_style( 'quictoli_wp_admin_css' );
			
			// Updated for jQuery compatibility with WordPress 5.5+
			wp_enqueue_script( 'quictoli_scripts', plugin_dir_url( __FILE__ ) . 'js/quictoli-scripts.js', array('jquery', 'wp-util'), $this->version, true );
			
			// Add nonce for AJAX security
			wp_localize_script( 'quictoli_scripts', 'quictoli_ajax', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('quictoli_ajax_nonce')
			));
		}

		public function register_settings() {
			register_setting( 'quictoli-settings-group', '_quictoli_items', array($this, 'sanitize_items') );
			register_setting( 'quictoli-custom-settings-group', '_quictoli_custom_items', array($this, 'sanitize_custom_items') );
		}
		
		/**
		 * Sanitize items before saving
		 */
		public function sanitize_items($input) {
			if (!is_array($input)) {
				return array();
			}
			// Basic sanitization - you may want to add more specific sanitization based on your needs
			return array_map('sanitize_text_field', $input);
		}
		
		/**
		 * Sanitize custom items before saving
		 */
		public function sanitize_custom_items($input) {
			if (!is_array($input)) {
				return array();
			}
			
			$sanitized = array();
			foreach ($input as $key => $item) {
				if (is_array($item)) {
					$sanitized[$key] = array(
						isset($item[0]) ? sanitize_text_field($item[0]) : '',
						isset($item[1]) ? esc_url_raw($item[1]) : '',
						isset($item[2]) ? (bool)$item[2] : false,
						isset($item[3]) ? sanitize_text_field($item[3]) : '',
						isset($item[4]) ? esc_url_raw($item[4]) : '',
						isset($item[5]) && is_array($item[5]) ? $this->sanitize_submenu_items($item[5]) : array()
					);
				}
			}
			return $sanitized;
		}
		
		/**
		 * Sanitize submenu items
		 */
		private function sanitize_submenu_items($items) {
			$sanitized = array();
			foreach ($items as $item) {
				if (is_array($item)) {
					$sanitized[] = array(
						isset($item[0]) ? sanitize_text_field($item[0]) : '',
						isset($item[1]) ? esc_url_raw($item[1]) : '',
						isset($item[2]) ? (bool)$item[2] : false,
						isset($item[3]) ? sanitize_text_field($item[3]) : '',
						isset($item[4]) ? intval($item[4]) : 0
					);
				}
			}
			return $sanitized;
		}

		public function add_admin_menu() {
			add_menu_page( 
				__('Quick Toolbar', 'quick-toolbar-links'), 
				__('Quick Toolbar', 'quick-toolbar-links'), 
				'manage_options', 
				'ecm-quick-toolbar', 
				array($this, 'admin_page'), 
				'dashicons-admin-links' 
			);
			add_submenu_page( 
				'ecm-quick-toolbar', 
				__('Custom Quick Links', 'quick-toolbar-links'), 
				__('Edit Custom Links', 'quick-toolbar-links'), 
				'manage_options', 
				'ecm-custom-quick-toolbar', 
				array($this, 'admin_custom_page') 
			);
		}

		public function add_toolbar_items($admin_bar){
			$options = get_option( '_quictoli_items', array());
			$custom_options = get_option( '_quictoli_custom_items', array());
			
			// Generate unique timestamp once for this page load
			$timestamp = current_time('timestamp');

			if (!empty($options) && is_array($options)) {
				// Top Level Menus
				$j = 2000;
				$user_ID = get_current_user_id();
				foreach($options as $option) {
					$decoded = @unserialize(base64_decode($option));
					if (!is_array($decoded)) {
						continue;
					}
					
					if (isset($decoded[2]) && !empty($decoded[2])) {
						if (0 === strpos($decoded[2][3], 'http')) {
							$title = '<img src="'. esc_url($decoded[2][3]) . '" alt=""/>' . '<span class="quictoli-link-title">' . esc_html($decoded[2][1]) . '</span>';
						} else {
							$title = '<span class="wp-menu-image dashicons-before ' . esc_attr($decoded[2][3]) . '"></span>' . '<span class="quictoli-link-title">' . esc_html($decoded[2][1]) . '</span>';
						}
						$allowed = user_can( $user_ID, $decoded[3] );
						if ($allowed == true) {
							$admin_bar->add_menu( array(
								'id'    => 'quictoli_' . $timestamp . '_' . $decoded[2][0],
								'title' => $title,
								'href'  => esc_url($decoded[2][2]),
								'meta' 	=> array('class' => 'quictoli-menu-item quictoli-has-submenu')
							));
						}
					} else {
						if (0 === strpos($decoded[4], 'http')) {
							$title = '<img src="'. esc_url($decoded[4]) . '" alt=""/>' . esc_html($decoded[0]);
						} else {
							$title = '<span class="wp-menu-image dashicons-before ' . esc_attr($decoded[4]) . '"></span>' . '<span class="quictoli-link-title">' . esc_html($decoded[0]) . '</span>';
						}
						$allowed = user_can( $user_ID, $decoded[3] );
						if ($allowed == true) {
							$admin_bar->add_menu( array(
								'id'    => 'quictoli_' . $timestamp . '_' . $j,
								'title' => $title,
								'href'  => esc_url($decoded[1]),
								'meta' 	=> array('class' => 'quictoli-menu-item')
							));
							$j++;
						}
					}
				}
				// Submenus
				$i = 1000;
				foreach($options as $option) {
					$decoded = @unserialize(base64_decode($option));
					if (!is_array($decoded)) {
						continue;
					}
					
					$user_ID = get_current_user_id();
					$allowed = user_can( $user_ID, $decoded[3] );
					if ( $allowed == true ) {
						if (isset($decoded[2]) && !empty($decoded[2])) {
							$admin_bar->add_menu( array(
								'id'    => 'quictoli_' . $timestamp . '_' . $i,
								'title' => esc_html($decoded[0]),
								'href'  => esc_url($decoded[1]),
								'parent' => 'quictoli_' . $timestamp . '_' . $decoded[2][0],
								'meta' 	=> array('class' => 'quictoli-submenu-item')
							));
							$i++;
						}
					}
				}
			}

			// Custom Links
			if (!empty($custom_options) && is_array($custom_options)) {
				// Custom Top Level Links
				$co = 3000;
				foreach ($custom_options as $key => $custom_option) {
					if (!is_array($custom_option)) {
						continue;
					}
					
					if (empty($custom_options[$key][4]) || !isset($custom_options[$key][4])) {
						$title = esc_html($custom_options[$key][0]);
					} else {
						$title = '<img src="'. esc_url($custom_options[$key][4]) . '" alt=""/>' . '<span class="quictoli-link-title">' . esc_html($custom_options[$key][0]) . '</span>';
					}

					if (isset($custom_options[$key][5]) && !empty($custom_options[$key][5])) {
						if (isset($custom_options[$key][2]) && !empty($custom_options[$key][2]) && $custom_options[$key][2] == true) {
							$meta = array('class' => 'quictoli-menu-item quictoli-has-submenu quictoli-custom-link', 'target' => '_blank');
						} else {
							$meta = array('class' => 'quictoli-menu-item quictoli-has-submenu quictoli-custom-link');
						}

						$admin_bar->add_menu( array(
							'id'    => 'quictoli_' . $timestamp . '_' . $key,
							'title' => $title,
							'href'  => esc_url($custom_options[$key][1]),
							'meta' 	=> $meta
						));
					} else {
						if (isset($custom_options[$key][2]) && !empty($custom_options[$key][2]) && $custom_options[$key][2] == true) {
							$meta = array('class' => 'quictoli-menu-item quictoli-custom-link', 'target' => '_blank');
						} else {
							$meta = array('class' => 'quictoli-menu-item quictoli-custom-link');
						}
						$admin_bar->add_menu( array(
							'id'    => 'quictoli_' . $timestamp . '_' . $co,
							'title' => $title,
							'href'  => esc_url($custom_options[$key][1]),
							'meta' 	=> $meta
						));
					}
					$co++;
				}
				// Custom Submenus
				$cos = 4000;
				foreach($custom_options as $custom_option) {
					if (!is_array($custom_option)) {
						continue;
					}
					
					if (isset($custom_option[5]) && !empty($custom_option[5])) {
						foreach ($custom_option[5] as $custom_menu_item) {
							if (!is_array($custom_menu_item)) {
								continue;
							}
							
							if (isset($custom_menu_item[2]) && !empty($custom_menu_item[2]) && $custom_menu_item[2] == true) {
								$meta = array('class' => 'quictoli-submenu-item quictoli-custom-link', 'target' => '_blank');
							} else {
								$meta = array('class' => 'quictoli-submenu-item quictoli-custom-link');
							}
							$admin_bar->add_menu( array(
								'id'    => 'quictoli_' . $timestamp . '_' . $cos,
								'title' => esc_html($custom_menu_item[0]),
								'href'  => esc_url($custom_menu_item[1]),
								'parent' => 'quictoli_' . $timestamp . '_' . $custom_menu_item[4],
								'meta' 	=> $meta
							));
							$cos++;
						}
					}
				}
			}

			// Custom Links - Single Menu (for responsive use)
			if (!empty($custom_options) && is_array($custom_options)) {
				$main_title = '<span class="wp-menu-image dashicons-before dashicons-admin-links"></span><span class="quictoli-link-title">' . __('My Custom Links', 'quick-toolbar-links') . '</span>';
				$main_meta = array('class' => 'quictoli-menu-item quictoli-has-submenu quictoli-custom-link-resp');
				$admin_bar->add_menu( array(
					'id'    => 'quictoli_custom_links_header',
					'title' => $main_title,
					'href'  => '#',
					'meta' 	=> $main_meta
				));
				$cor = 5000;
				$cors = 6000;
				foreach ($custom_options as $key => $custom_option) {
					if (!is_array($custom_option)) {
						continue;
					}
					
					$admin_bar->add_menu( array(
						'id'    => 'quictoli_resp_' . $cor,
						'title' => esc_html($custom_option[0]),
						'href'  => esc_url($custom_option[1]),
						'parent' => 'quictoli_custom_links_header',
						'meta' 	=> array('class' => 'quictoli-submenu-item quictoli-custom-link-resp')
					));
					$cor++;
					if (isset($custom_option[5]) && !empty($custom_option[5])) {
						foreach ($custom_option[5] as $custom_menu_item) {
							if (!is_array($custom_menu_item)) {
								continue;
							}
							
							$admin_bar->add_menu( array(
								'id'    => 'quictoli_resp_sub_' . $cors,
								'title' => '&mdash; ' . esc_html($custom_menu_item[0]),
								'href'  => esc_url($custom_menu_item[1]),
								'parent' => 'quictoli_custom_links_header',
								'meta' 	=> array('class' => 'quictoli-submenu-item quictoli-custom-link-resp')
							));
							$cors++;
						}
					}
				}
			}
		}

		public function admin_page() {
			if (!current_user_can('manage_options')) {
				wp_die(esc_html_e('You do not have sufficient permissions to access this page.', 'quick-toolbar-links'));
			}
			?>
			<div class="wrap">
				<h2><?php esc_html_e('Quick Toolbar Links', 'quick-toolbar-links'); ?></h2>

				<p><?php esc_html_e('Select which admin and plugin links you would like to add to the toolbar.', 'quick-toolbar-links'); ?></p>

				<form method="post" action="options.php">
					<?php settings_fields( 'quictoli-settings-group' ); ?>
					<?php do_settings_sections( 'quictoli-settings-group' ); ?>

					<table id="_quictoli_quicklinks_options" class="widefat">
						<thead>
							<tr>
								<th><?php esc_html_e('Use', 'quick-toolbar-links'); ?></th>
								<th><?php esc_html_e('Menu', 'quick-toolbar-links'); ?></th>
								<th><?php esc_html_e('Link', 'quick-toolbar-links'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$options = $this->item_options;
							$all_items = $this->get_items();
							$count = 0;
							foreach ($all_items as $key => $item) {
								if (!isset($item['subpages'])) {
									?>
									<tr>
										<td><input type="checkbox" name="_quictoli_items[]" value="<?php echo esc_attr(base64_encode(serialize(array($item['name'], $item['link'], '', $item['permissions'], $item['dashicon'])))); ?>" <?php if (in_array(base64_encode(serialize(array($item['name'], $item['link'], '', $item['permissions'], $item['dashicon']))), $options)) { echo 'checked="checked"'; } ?> id="_quictoli_item_<?php echo esc_attr($count); ?>"/></td>
										<td><label for="_quictoli_item_<?php echo esc_attr($count); ?>"><?php echo wp_kses_post($item['name']); ?></label></td>
										<td><label for="_quictoli_item_<?php echo esc_attr($count); ?>"><?php echo esc_html($item['link']); ?></label></td>
									</tr>
									<?php
									$count++;
								} else {
									?>
									<tr class="quictoli-heading">
										<td><input type="checkbox" name="_quictoli_items[]" value="<?php echo esc_attr(base64_encode(serialize(array($item['name'], $item['link'], array($key, $item['name'], $item['link'], $item['dashicon']), $item['permissions'], $item['dashicon'])))); ?>" <?php if (in_array(base64_encode(serialize(array($item['name'], $item['link'], array($key, $item['name'], $item['link'], $item['dashicon']), $item['permissions'], $item['dashicon']))), $options)) { echo 'checked="checked"'; } ?>  id="_quictoli_item_<?php echo esc_attr($count); ?>"/></td>
										<td><label for="_quictoli_item_<?php echo esc_attr($count); ?>"><strong><?php echo wp_kses_post($item['name']); ?></strong></label></td>
										<td><label for="_quictoli_item_<?php echo esc_attr($count); ?>"><?php echo esc_html($item['link']); ?></label></td>
									</tr>
									<?php
									$count++;
									foreach ($item['subpages'] as $subpage) {
										?>
										<tr>
											<td><input type="checkbox" name="_quictoli_items[]" value="<?php echo esc_attr(base64_encode(serialize(array($subpage['name'], $subpage['link'], $subpage['parent'], $subpage['permissions'])))); ?>" <?php if (in_array(base64_encode(serialize(array($subpage['name'], $subpage['link'], $subpage['parent'], $subpage['permissions']))), $options)) { echo 'checked="checked"'; } ?>  id="_quictoli_item_<?php echo esc_attr($count); ?>"/></td>
											<td> &mdash; <label for="_quictoli_item_<?php echo esc_attr($count); ?>"><?php echo wp_kses_post($subpage['name']); ?></label></td>
											<td><label for="_quictoli_item_<?php echo esc_attr($count); ?>"><?php echo esc_html($subpage['link']); ?></label></td>
										</tr>
										<?php
										$count++;
									}
								}
							}
							?>
						</tbody>
					</table>
					<?php submit_button(); ?>
				</form>
			</div>

			<div class="ecommnet-footer">
				<a target="_blank" href="https://uniquelyyourshosting.com/quick-toolbar">
					<img src="<?php echo esc_url(Quick_Toolbar()->plugin_url()); ?>/images/uy-logo.png" alt="Uniquely Yours Web Services"/>
				</a>
				<p class="quick-toolbar"><?php esc_html_e('This plugin is maintained by Uniquely Yours Web Services. We provide WordPress hosting, web design, and webmaster services.', 'quick-toolbar-links'); ?> <a target="_blank" href="https://uniquelyyourshosting.com"><?php esc_html_e('Click here', 'quick-toolbar-links'); ?></a> <?php esc_html_e('to find out more about our services.', 'quick-toolbar-links'); ?></p>
			</div>
			<?php
		}

		public function admin_custom_page() {
			if (!current_user_can('manage_options')) {
				wp_die(esc_html_e('You do not have sufficient permissions to access this page.', 'quick-toolbar-links'));
			}
			
			$options = $this->custom_item_options;
			?>
			<div class="wrap">
				<h2><?php esc_html_e('Custom Quick Links', 'quick-toolbar-links'); ?></h2>
				<p><?php esc_html_e('Add your own custom links to the toolbar. Links with the same Parent ID will be grouped together.', 'quick-toolbar-links'); ?></p>

				<h3><?php esc_html_e('Add New Link', 'quick-toolbar-links'); ?></h3>
				<form method="post" action="">
					<?php wp_nonce_field('quictoli_custom_link_nonce', 'quictoli_custom_link_nonce_field'); ?>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="_quictoli_custom_title"><?php esc_html_e('Title', 'quick-toolbar-links'); ?></label></th>
							<td><input type="text" id="_quictoli_custom_title" name="_quictoli_custom_title" class="regular-text" required /></td>
						</tr>
						<tr>
							<th scope="row"><label for="_quictoli_custom_url"><?php esc_html_e('URL', 'quick-toolbar-links'); ?></label></th>
							<td><input type="url" id="_quictoli_custom_url" name="_quictoli_custom_url" class="regular-text" required /></td>
						</tr>
						<tr>
							<th scope="row"><label for="_quictoli_custom_new_window"><?php esc_html_e('Open in New Window', 'quick-toolbar-links'); ?></label></th>
							<td><input type="checkbox" id="_quictoli_custom_new_window" name="_quictoli_custom_new_window" value="1" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="_quictoli_custom_parent_id"><?php esc_html_e('Parent ID (Optional)', 'quick-toolbar-links'); ?></label></th>
							<td>
								<input type="number" id="_quictoli_custom_parent_id" name="_quictoli_custom_parent_id" class="small-text" />
								<p class="description"><?php esc_html_e('Leave blank for top-level menu item', 'quick-toolbar-links'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="_quictoli_upload_image"><?php esc_html_e('Icon Image URL (Optional)', 'quick-toolbar-links'); ?></label></th>
							<td>
								<input type="url" id="_quictoli_upload_image" name="_quictoli_custom_icon" class="regular-text" />
								<input type="button" id="_quictoli_upload_image_button" class="button" value="<?php esc_attr_e('Choose Image', 'quick-toolbar-links'); ?>" />
							</td>
						</tr>
					</table>
					<?php submit_button(esc_html_e('Add Custom Link', 'quick-toolbar-links')); ?>
				</form>

				<?php if (!empty($options)) : ?>
					<h3><?php esc_html_e('Existing Custom Links', 'quick-toolbar-links'); ?></h3>
					<table id="_quictoli_custom_links" class="widefat">
						<thead>
							<tr>
								<th><?php esc_html_e('ID', 'quick-toolbar-links'); ?></th>
								<th><?php esc_html_e('Title', 'quick-toolbar-links'); ?></th>
								<th><?php esc_html_e('URL', 'quick-toolbar-links'); ?></th>
								<th><?php esc_html_e('Parent ID', 'quick-toolbar-links'); ?></th>
								<th><?php esc_html_e('Actions', 'quick-toolbar-links'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($options as $key => $option) :
								if (!is_array($option)) continue;
							?>
								<tr>
									<td><?php echo esc_html($key); ?></td>
									<td><?php echo esc_html($option[0]); ?></td>
									<td><?php echo esc_url($option[1]); ?></td>
									<td><?php echo isset($option[3]) ? esc_html($option[3]) : '-'; ?></td>
									<td>
										<a href="#" onclick="quictoliDelete(<?php echo esc_attr($key); ?>, '<?php echo esc_js($option[0]); ?>'); return false;" class="button button-small"><?php esc_html_e('Delete', 'quick-toolbar-links'); ?></a>
									</td>
								</tr>
								<?php 
								if (isset($option[5]) && !empty($option[5])) {
									foreach ($option[5] as $submenu) :
										if (!is_array($submenu)) continue;
									?>
									<tr>
										<td>&mdash;</td>
										<td>&mdash; <?php echo esc_html($submenu[0]); ?></td>
										<td><?php echo esc_url($submenu[1]); ?></td>
										<td><?php echo esc_html($key); ?></td>
										<td></td>
									</tr>
									<?php endforeach;
								}
								?>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="ecommnet-footer">
				<a target="_blank" href="https://uniquelyyourshosting.com/quick-toolbar">
					<img src="<?php echo esc_url(Quick_Toolbar()->plugin_url()); ?>/images/uy-logo.png" alt="Uniquely Yours Web Services"/>
				</a>
				<p class="quick-toolbar"><?php esc_html_e('This plugin is maintained by Uniquely Yours Web Services. We provide WordPress hosting, web design, and webmaster services.', 'quick-toolbar-links'); ?> <a target="_blank" href="https://uniquelyyourshosting.com"><?php esc_html_e('Click here', 'quick-toolbar-links'); ?></a> <?php esc_html_e('to find out more about our services.', 'quick-toolbar-links'); ?></p>
			</div>
			<?php
		}

		public function custom_submit() {
			global $quick_toolbar_custom_title, $quick_toolbar_custom_url, $quick_toolbar_custom_icon;
			// Check if form was submitted
			if (!isset($_POST['quictoli_custom_link_nonce_field'])) {
				sanitize_text_field( wp_unslash( $_POST['quictoli_custom_link_nonce_field'] ));
				return;
			}
			
			// Verify nonce
			if (!wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['quictoli_custom_link_nonce_field'])), 'quictoli_custom_link_nonce')) {
				wp_die(esc_html_e('Security check failed', 'quick-toolbar-links'));
			}
			
			// Check user capabilities
			if (!current_user_can('manage_options')) {
				wp_die(esc_html_e('You do not have sufficient permissions to access this page.', 'quick-toolbar-links'));
			}
			
			if (isset($_POST['_quictoli_custom_title']) && isset($_POST['_quictoli_custom_url'])) {
				$options = get_option('_quictoli_custom_items', array());
				$quick_toolbar_custom_title=sanitize_text_field( wp_unslash( $_POST['_quictoli_custom_title']));
				$quick_toolbar_custom_url=sanitize_text_field( wp_unslash( $_POST['_quictoli_custom_url']));
				
				$title = $quick_toolbar_custom_title;
				$url = esc_url_raw($quick_toolbar_custom_url);
				$new_window = isset($_POST['_quictoli_custom_new_window']) ? true : false;
				$parent_id = isset($_POST['_quictoli_custom_parent_id']) ? intval($_POST['_quictoli_custom_parent_id']) : '';
				if (isset($_POST['_quictoli_custom_icon']) && $_POST['_quictoli_custom_icon'] != '') {
					$icon = esc_url_raw(sanitize_text_field( wp_unslash($_POST['_quictoli_custom_icon'])));
				}
				
				// If it has a parent ID, add it as a submenu item
				if (!empty($parent_id) && isset($options[$parent_id])) {
					if (!isset($options[$parent_id][5])) {
						$options[$parent_id][5] = array();
					}
					$options[$parent_id][5][] = array($title, $url, $new_window, '', $parent_id);
				} else {
					// Add as top-level item
					$new_key = empty($options) ? 0 : max(array_keys($options)) + 1;
					$options[$new_key] = array($title, $url, $new_window, '', $icon, array());
				}
				
				update_option('_quictoli_custom_items', $options);
				
				// Redirect to prevent form resubmission
				wp_safe_redirect(admin_url('admin.php?page=ecm-custom-quick-toolbar&updated=true'));
				exit;
			}
		}

		public function delete_custom_link() {
			//global $quick_toolbar_verify_nonce;
			// Check nonce
			if (empty(sanitize_text_field( wp_unslash( $_POST['nonce'] ))) || !wp_verify_nonce(sanitize_text_field( wp_unslash( $_POST['nonce'] )), 'quictoli_ajax_nonce')) {
				wp_die(esc_html_e('Security check failed', 'quick-toolbar-links'));
			}
			
			// Check user capabilities
			if (!current_user_can('manage_options')) {
				wp_die(esc_html_e('You do not have sufficient permissions to access this page.', 'quick-toolbar-links'));
			}
			
			if (isset($_POST['quictoli_id'])) {
				$id = intval($_POST['quictoli_id']);
				$options = get_option('_quictoli_custom_items', array());
				
				if (isset($options[$id])) {
					unset($options[$id]);
					// Re-index array
					$options = array_values($options);
					update_option('_quictoli_custom_items', $options);
					wp_send_json_success();
				}
			}
			
			wp_send_json_error();
		}

		/**
		 * Return the plugin URL
		 * @return string
		 */
		public function plugin_url() {
			return untrailingslashit(plugins_url('/', __FILE__ ));
		}

		/**
		 * Return the plugin directory path
		 * @return string
		 */
		public function plugin_path() {
			return untrailingslashit(plugin_dir_path(__FILE__));
		}

		public function get_items() {
			global $menu, $submenu, $self, $parent_file, $submenu_file, $plugin_page, $typenow;

			$items = array();
			$submenu_as_parent = true;

			if (!is_array($menu)) {
				return $items;
			}

			$first = true;
			foreach ( $menu as $key => $item ) {
				if (!is_array($item) || count($item) < 7) {
					continue;
				}
				
				$admin_is_parent = false;
				$class = array();
				$aria_attributes = '';
				$is_separator = false;

				if ( $first ) {
					$class[] = 'wp-first-item';
					$first = false;
				}

				$submenu_items = false;
				if ( ! empty( $submenu[$item[2]] ) ) {
					$class[] = 'wp-has-submenu';
					$submenu_items = $submenu[$item[2]];
				}

				if ( ( $parent_file && $item[2] == $parent_file ) || ( empty($typenow) && $self == $item[2] ) ) {
					$class[] = ! empty( $submenu_items ) ? 'wp-has-current-submenu wp-menu-open' : 'current';
				} else {
					$class[] = 'wp-not-current-submenu';
					if ( ! empty( $submenu_items ) )
						$aria_attributes .= 'aria-haspopup="true"';
				}

				if ( ! empty( $item[4] ) )
					$class[] = esc_attr( $item[4] );

				$class = $class ? ' class="' . join( ' ', $class ) . '"' : '';

				if ( false !== strpos( $class, 'wp-menu-separator' ) ) {
					$is_separator = true;
				}

				if ( $is_separator ) {
					continue;
				} elseif ( $submenu_as_parent && ! empty( $submenu_items ) ) {
					$submenu_items = array_values( $submenu_items );  // Re-index.
					$menu_hook = get_plugin_page_hook( $submenu_items[0][2], $item[2] );
					$menu_file = $submenu_items[0][2];
					if ( false !== ( $pos = strpos( $menu_file, '?' ) ) )
						$menu_file = substr( $menu_file, 0, $pos );
					if ( ! empty( $menu_hook ) || ( ( 'index.php' != $submenu_items[0][2] ) && file_exists( QUICTOLI_PLUGIN_DIR . "/$menu_file" ) && ! file_exists( ABSPATH . "/wp-admin/$menu_file" ) ) ) {
						$admin_is_parent = true;

						$items[$key] = array(
							'name' => $item[0],
							'link' => admin_url("admin.php?page={$submenu_items[0][2]}"),
							'permissions' => $item[1],
							'dashicon' => $item[6]
						);

					} else {
						$items[$key] = array(
							'name' => $item[0],
							'link' => admin_url($submenu_items[0][2]),
							'permissions' => $item[1],
							'dashicon' => $item[6]
						);
					}
				} elseif ( ! empty( $item[2] ) && current_user_can( $item[1] ) ) {
					$menu_hook = get_plugin_page_hook( $item[2], 'admin.php' );
					$menu_file = $item[2];
					if ( false !== ( $pos = strpos( $menu_file, '?' ) ) )
						$menu_file = substr( $menu_file, 0, $pos );
					if ( ! empty( $menu_hook ) || ( ( 'index.php' != $item[2] ) && file_exists( QUICTOLI_PLUGIN_DIR . "/$menu_file" ) && ! file_exists( ABSPATH . "/wp-admin/$menu_file" ) ) ) {
						$admin_is_parent = true;

						$items[$key] = array(
							'name' => $item[0],
							'link' => admin_url("admin.php?page={$item[2]}"),
							'permissions' => $item[1],
							'dashicon' => $item[6]
						);

					} else {
						$items[$key] = array(
							'name' => $item[0],
							'link' => admin_url($item[2]),
							'permissions' => $item[1],
							'dashicon' => $item[6]
						);
					}
				}

				if ( ! empty( $submenu_items ) ) {

					$first = true;

					foreach ( $submenu_items as $sub_key => $sub_item ) {
						if ( ! current_user_can( $sub_item[1] ) )
							continue;

						$class = array();
						if ( $first ) {
							$class[] = 'wp-first-item';
							$first = false;
						}

						$menu_file = $item[2];

						if ( false !== ( $pos = strpos( $menu_file, '?' ) ) )
							$menu_file = substr( $menu_file, 0, $pos );

						$self_type = ! empty( $typenow ) ? $self . '?post_type=' . $typenow : 'nothing';

						if ( isset( $submenu_file ) ) {
							if ( $submenu_file == $sub_item[2] )
								$class[] = 'current';
						} else if (
							( ! isset( $plugin_page ) && $self == $sub_item[2] ) ||
							( isset( $plugin_page ) && $plugin_page == $sub_item[2] && ( $item[2] == $self_type || $item[2] == $self || file_exists($menu_file) === false ) )
						) {
							$class[] = 'current';
						}

						if ( ! empty( $sub_item[4] ) ) {
							$class[] = esc_attr( $sub_item[4] );
						}

						$menu_hook = get_plugin_page_hook($sub_item[2], $item[2]);
						$sub_file = $sub_item[2];
						if ( false !== ( $pos = strpos( $sub_file, '?' ) ) )
							$sub_file = substr($sub_file, 0, $pos);

						if ( ! empty( $menu_hook ) || ( ( 'index.php' != $sub_item[2] ) && file_exists( QUICTOLI_PLUGIN_DIR . "/$sub_file" ) && ! file_exists( ABSPATH . "/wp-admin/$sub_file" ) ) ) {
							if ( ( ! $admin_is_parent && file_exists( QUICTOLI_PLUGIN_DIR . "/$menu_file" ) && ! is_dir( QUICTOLI_PLUGIN_DIR . "/{$item[2]}" ) ) || file_exists( $menu_file ) )
								$sub_item_url = add_query_arg( array( 'page' => $sub_item[2] ), $item[2] );
							else
								$sub_item_url = add_query_arg( array( 'page' => $sub_item[2] ), 'admin.php' );

							$sub_item_url = esc_url( $sub_item_url );

							$items[$key]['subpages'][] = array(
								'name' => $sub_item[0],
								'link' => admin_url($sub_item_url),
								'parent' => array('id' => $key, 'name' => $items[$key]['name'], 'link' => $items[$key]['link'], 'dashicon' => $items[$key]['dashicon']),
								'permissions' => $sub_item[1]
							);
						} else {

							$items[$key]['subpages'][] = array(
								'name' => $sub_item[0],
								'link' => admin_url($sub_item[2]),
								'parent' => array('id' => $key, 'name' => $items[$key]['name'], 'link' => $items[$key]['link'], 'dashicon' => $items[$key]['dashicon']),
								'permissions' => $sub_item[1]

							);
						}
					}

					if (0 === strpos($item[2], 'edit.php')) {
						if ($item[0] == 'Posts' ? $first = '?' : $first = '&');
						$items[$key]['subpages'][] = array(
							'name' => __('Published', 'quick-toolbar-links') . ' ' . $item[0],
							'link' => admin_url($item[2] . $first . 'post_status=publish'),
							'parent' => array('id' => $key, 'name' => $items[$key]['name'], 'link' => $items[$key]['link'], 'dashicon' => $items[$key]['dashicon']),
							'permissions' => $item[1]
						);
						$items[$key]['subpages'][] = array(
							'name' => __('Draft', 'quick-toolbar-links') . ' ' . $item[0],
							'link' => admin_url($item[2] . $first . 'post_status=draft'),
							'parent' => array('id' => $key, 'name' => $items[$key]['name'], 'link' => $items[$key]['link'], 'dashicon' => $items[$key]['dashicon']),
							'permissions' => $item[1]
						);
						$items[$key]['subpages'][] = array(
							'name' => __('Pending', 'quick-toolbar-links') . ' ' . $item[0],
							'link' => admin_url($item[2] . $first . 'post_status=pending'),
							'parent' => array('id' => $key, 'name' => $items[$key]['name'], 'link' => $items[$key]['link'], 'dashicon' => $items[$key]['dashicon']),
							'permissions' => $item[1]
						);
						$items[$key]['subpages'][] = array(
							'name' => __('Trashed', 'quick-toolbar-links') . ' ' . $item[0],
							'link' => admin_url($item[2] . $first . 'post_status=trash'),
							'parent' => array('id' => $key, 'name' => $items[$key]['name'], 'link' => $items[$key]['link'], 'dashicon' => $items[$key]['dashicon']),
							'permissions' => $item[1]
						);
					}
				}
			}
			return $items;
		}
	}
}

/**
 * Returns the main instance of Quick Toolbar
 */
function quick_toolbar() {
	return Quick_Toolbar_Class::instance();
}

quick_toolbar();