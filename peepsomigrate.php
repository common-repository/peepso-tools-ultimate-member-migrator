<?php
/**
 * Plugin Name: PeepSo Tools: Ultimate Member Migrator
 * Plugin URI: https://peepso.com
 * Description: Migration plugin from Ultimate Member to PeepSo
 * Tags: peepso, integration
 * Author: PeepSo
 * Version: 1.11.5
 * Plugin URI: https://www.peepso.com/downloads/ummigrator/
 * Author URI: https://PeepSo.com/
 * Copyright: (c) 2015 PeepSo, Inc. All Rights Reserved.
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: peepsoum
 * Domain Path: /language
 *
 * We are Open Source. You can redistribute and/or modify this software under the terms of the GNU General Public License (version 2 or later)
 * as published by the Free Software Foundation. See the GNU General Public License or the LICENSE file for more details.
 * This software is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY.
 */

class PeepSoUM {

	private static $_instance = NULL;

	const DEBUG = TRUE;
	const MODULE_ID = 0;
	const PLUGIN_VERSION = '1.11.5';
	const PLUGIN_RELEASE = ''; //ALPHA1, BETA1, RC1, '' for STABLE
	const PLUGIN_NAME = 'PeepSoUM';
	const PLUGIN_SLUG = 'peepsoum_';

	public function __construct() {
		add_action('peepso_init', array(&$this, 'init'));

		if (is_admin()) {
			add_action('admin_init', array(&$this, 'peepso_check'));
		}

		add_action('plugins_loaded', array(&$this, 'load_textdomain'));
		add_filter('peepso_all_plugins', array($this, 'filter_all_plugins'));

		register_activation_hook(__FILE__, array(&$this, 'activate'));
	}

	/**
	 * Return singleton instance of plugin
	 */
	public static function get_instance() {
		if (NULL === self::$_instance) {
			self::$_instance = new self();
		}

		return (self::$_instance);
	}

	/**
	 * Loads the translation file for the PeepSo plugin
	 */
	public function load_textdomain() {
		$path = str_ireplace(WP_PLUGIN_DIR, '', dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR;
		load_plugin_textdomain('peepsoum', FALSE, $path);
	}

	public function init() {
		require_once 'classes/migrate.php';
		PeepSoTemplate::add_template_directory(plugin_dir_path(__FILE__));

		if (is_admin()) {
			add_action('admin_menu', array(&$this, 'admin_menu'), 9);
			add_action('admin_init', array(&$this, 'peepso_check'));

			$page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

			if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $page == 'pm-dashboard') {
				$this->migrate();
				die();
			} else if ($page == 'pm-dashboard') {
				wp_register_style('peepsoum', PeepSoUM::get_asset('css/peepsoum.css'), NULL, peepsoum::PLUGIN_VERSION, 'all');
				wp_enqueue_style('peepsoum');
			}
		}
	}

	/**
	 * Plugin activation
	 * Check PeepSo
	 * @return bool
	 */
	public function activate() {
		if (!$this->peepso_check()) {
			return (FALSE);
		}

		return (TRUE);
	}

	/**
	 * Check if PeepSo class is present (ie the PeepSo plugin is installed and activated)
	 * If there is no PeepSo, immediately disable the plugin and display a warning
	 * Run license and new version checks against PeepSo.com
	 * @return bool
	 */
	public function peepso_check() {
		if (!class_exists('PeepSo')) {
			add_action('admin_notices', array(&$this, 'peepso_disabled_notice'));
			deactivate_plugins(plugin_basename(__FILE__));
			unset($_GET['activate']);
			return (FALSE);
		}

		if (!defined('ultimatemember_version')) {
			add_action('admin_notices', array(&$this, 'um_disabled_notice'));
			deactivate_plugins(plugin_basename(__FILE__));
			unset($_GET['activate']);
			return (FALSE);		
		}

		return (TRUE);
	}

	/**
	 * Display a message about PeepSo not present
	 */
	public function peepso_disabled_notice() {
		?>
		<div class="error">
			<strong>
				<?php echo sprintf(__('The %s plugin requires the PeepSo plugin to be installed and activated.', 'peepsoum'), self::PLUGIN_NAME); ?>
			</strong>
		</div>
		<?php
	}

	/**
	 * Display a message about UM not present
	 */
	public function um_disabled_notice() {
		?>
		<div class="error">
			<strong>
				<?php echo sprintf(__('The %s plugin requires the Ultimate Member plugin to be installed and activated.', 'peepsoum'), self::PLUGIN_NAME); ?>
			</strong>
		</div>
		<?php
	}

	/**
	 * Hooks into PeepSo for compatibility checks
	 * @param $plugins
	 * @return mixed
	 */
	public function filter_all_plugins($plugins) {
		$plugins[plugin_basename(__FILE__)] = get_class($this);
		return $plugins;
	}

	public function admin_menu() {
		$dashboard_hookname = add_menu_page(__('PeepSoUM', 'peepsoum'), __('PeepSoUM', 'peepsoum'), 'manage_options', 'pm-dashboard', array(&$this, 'pm_dashboard'), PeepSoUM::get_asset('images/logo-icon_20x20.png'), 5);
	}

	public function pm_dashboard() {
		if (isset($_POST['start_peepso_migrate'])) {
			global $wpdb;

			if (isset($_POST['gender_field'])) {
				$gender_field = sanitize_text_field($_POST['gender_field']);

				delete_transient('peepso_migrate_gender_field');
				delete_transient('peepso_migrate_gender_field_male');
				delete_transient('peepso_migrate_gender_field_female');

				set_transient('peepso_migrate_gender_field', $gender_field);

				$gender_field_male = isset($_POST['gender_field_male_' . $gender_field]) ? sanitize_text_field($_POST['gender_field_male_' . $gender_field]) : '';
				$gender_field_female = isset($_POST['gender_field_female_' . $gender_field]) ? sanitize_text_field($_POST['gender_field_female_' . $gender_field]) : '';

				if (!empty($gender_field_male) && !empty($gender_field_female)) {
					set_transient('peepso_migrate_gender_field_male', sanitize_text_field($_POST['gender_field_male_' . $gender_field]));
					set_transient('peepso_migrate_gender_field_female', sanitize_text_field($_POST['gender_field_female_' . $gender_field]));
				}

				if (class_exists('PeepSoExtendedProfiles')) {
					$result = self::get_fields();

					if (!empty($result)) {
						foreach ($result as $field_key => $field) {
							if ($field_key == $gender_field) {
								$i = 1;
								foreach ($field['options'] as $gender) {
									delete_transient('peepso_migrate_gender_field_custom' . ($i + 2));
									if (isset($_POST['gender_field_custom' . ($i + 2) . '_' . $gender_field])) {
										set_transient('peepso_migrate_gender_field_custom' . ($i + 2), sanitize_text_field($_POST['gender_field_custom' . ($i + 2) . '_' . $gender_field]));
										$i++;
									}
								}
							}
						}
					}
				}
			}

			if (isset($_POST['birthdate_field'])) {
				delete_transient('peepso_migrate_birthdate_field');
				set_transient('peepso_migrate_birthdate_field', sanitize_text_field($_POST['birthdate_field']));
			}

			$data = array(
				'next_url' => PeepSoUM::url('delete')
			);

			$sql = "SELECT count(*) as total FROM " . $wpdb->prefix . "users";

			$data['total_profiles'] = $data['total_avatars'] = $wpdb->get_row($sql)->total;

			if (self::plugin_check('friend')) {
				$sql = "SELECT count(*) as total FROM " . $wpdb->prefix . "um_friends";
				$data['total_friends'] = $wpdb->get_row($sql)->total;
			}

			if (self::plugin_check('friend')) {
				$sql = "SELECT count(*) as total FROM " . $wpdb->prefix . "um_followers";
				$data['total_followers'] = $wpdb->get_row($sql)->total;
			}

			if (self::plugin_check('activity')) {
				$sql = "SELECT count(*) as total FROM " . $wpdb->prefix . "posts where `post_type` = 'um_activity'";
				$data['total_activities'] = $wpdb->get_row($sql)->total;
			}

			if (self::plugin_check('photo')) {
				$sql = "SELECT count(*) as total FROM " . $wpdb->prefix . "posts where `post_type` = 'um_user_photos'";
				$data['total_photos'] = $wpdb->get_row($sql)->total;
			}

			if (self::plugin_check('message')) {
				$sql = "SELECT count(*) as total FROM " . $wpdb->prefix . "um_conversations";
				$data['total_messages'] = $wpdb->get_row($sql)->total;
			}

			if (self::plugin_check('profile_field')) {
				$count = count($result);
				if (isset($_POST['gender_field'])) {
					$count--;
				}
				if (isset($_POST['birthdate_field'])) {
					$count--;
				}
				$data['total_profile_fields'] = $count;
			}

			echo PeepSoTemplate::exec_template('migrate', 'progress', $data);
		} else {
			echo PeepSoTemplate::exec_template('migrate', 'dashboard');
		}
	}

	public static function get_asset($ref) {
		$ret = plugin_dir_url(__FILE__) . 'assets/' . $ref;
		return ($ret);
	}

	public function migrate() {
		$migrate = new PeepSoUMAdminMigrate();
		$action = sanitize_text_field($_GET['action']);
		switch ($action) {
			case 'delete' :
				$migrate->deletePeepSoContent();
				break;
			case 'profile' :
				$migrate->startMigrateProfiles();
				break;
			case 'avatar' :
				$migrate->startMigrateAvatars();
				break;
			case 'friend' :
				$migrate->startMigrateFriends();
				break;
			case 'follower' :
				$migrate->startMigrateFollowers();
				break;
			case 'activity' :
				$migrate->startMigrateActivity();
				break;
			case 'photo' :
				$migrate->startMigratePhotos();
				break;
			case 'message' :
				$migrate->startMigrateMessages();
				break;
			case 'profile_field' :
				$migrate->startMigrateProfileFields();
				break;
			case 'profile_field_value' :
				$migrate->startMigrateProfileFieldValue();
				break;
			case 'profile_field_user_data' :
				$migrate->startMigrateProfileFieldUserData();
				break;
			case 'unpublish' :
				$migrate->unpublish();
				break;
		}
	}

	public static function url($action) {
		return admin_url('admin.php?page=pm-dashboard&action=' . $action);
	}

	public static function plugin_check($plugin) {
		switch ($plugin) {
			case 'activity' :
				if (defined('um_activity_url')) {
					return TRUE;
				}
			case 'friend' :
				if (class_exists('PeepSoFriendsPlugin')) {
					return TRUE;
				}
				break;
			case 'message' :
				if (class_exists('PeepSoMessagesPlugin')) {
					return TRUE;
				}
				break;
			case 'profile_field' :
				if (class_exists('PeepSoExtendedProfiles')) {
					return TRUE;
				}
				break;
			case 'photo' :
				if (class_exists('PeepSoSharePhotos')) {
					return TRUE;
				}
				break;
		}

		return FALSE;
	}

	public static function get_fields() {
		$um_options = get_option('um_options');
		$post = get_post($um_options['core_user']);

		preg_match_all('/\[ultimatemember form_id=(.*)\]/', $post->post_content, $matches);

		if (count($matches) > 0 && isset($matches[1][0])) {
			$post_id = str_replace(array('"', "'"), '', $matches[1][0]);
			$result = get_post_meta($post_id, '_um_custom_fields', TRUE);
			return $result;
		}
	}

}

defined('WPINC') || die;
$peepso_migrate = new PeepSoUM();
?>
