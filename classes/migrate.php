<?php

class PeepSoUMAdminMigrate {

	var $limit = 0;
	var $next_limit = 0;
	var $per_page = 50;
	var $peepso_dir;
	var $counter = 0;

	function __construct() {
		$this->peepso_dir = PeepSo::get_peepso_dir();

		if (isset($_REQUEST["limit"])) {
			$this->limit = intval($_REQUEST["limit"]);
		}

		$this->next_limit = $this->limit + $this->per_page;

		if (!function_exists('get_home_path')) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}
	}

	function startMigrateProfiles() {
		global $wpdb;

		// get transient data
		$gender_field = get_transient('peepso_migrate_gender_field');
		$gender_field_male = get_transient('peepso_migrate_gender_field_male');
		$gender_field_female = get_transient('peepso_migrate_gender_field_female');
		$birthdate_field = get_transient('peepso_migrate_birthdate_field');

		$result = PeepSoUM::get_fields();
		$next_url = PeepSoUM::url('avatar');

		if (!empty($result)) {
			foreach ($result as $field_key => $field) {
				if ($field_key == $gender_field) {
					$current_gender_field = $field;
					set_transient('peepso_migrate_gender_field_data', $field);

					// gender field visibility
					switch ($field['public']) {
						case 1:
							$gender_field_visibility = PeepSo::ACCESS_PUBLIC;
							break;
						case 2:
							$gender_field_visibility = PeepSo::ACCESS_MEMBERS;
							break;
						default:
							$gender_field_visibility = PeepSo::ACCESS_PRIVATE;
							break;
					}
				} else if ($field_key == $birthdate_field) {
					// birthdate field visibility
					switch ($field['public']) {
						case 1:
							$birthdate_field_visibility = PeepSo::ACCESS_PUBLIC;
							break;
						case 2:
							$birthdate_field_visibility = PeepSo::ACCESS_MEMBERS;
							break;
						default:
							$birthdate_field_visibility = PeepSo::ACCESS_PRIVATE;
							break;
					}
				}
			}
		}

		// move gender field
		$sql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "users ORDER BY ID asc limit %d, %d ", $this->limit, $this->per_page);
		$all_users = $wpdb->get_results($sql);

		if (count($all_users) > 0) {
			$next_url = PeepSoUM::url('profile&limit=' . $this->next_limit);

			if (!empty($gender_field)) {
				foreach ($all_users as $user) {
					$user_gender = get_user_meta($user->ID, $gender_field, true);
					$gender = 'u';
	
					if (isset($user_gender[0])) {
						$user_gender = $user_gender[0];
	
						if ($user_gender == $gender_field_female) {
							$gender = 'f';
						} else if ($user_gender == $gender_field_male) {
							$gender = 'm';
						} else {
							if (class_exists('PeepSoExtendedProfiles')) {
								$i = 1;
								foreach ($current_gender_field['options'] as $custom_gender) {
									$gender_field_custom = get_transient('peepso_migrate_gender_field_custom' . $i);
	
									if (!empty($gender_field_custom) && $user_gender == $custom_gender) {
										$gender = $gender_field_custom;
									}
									$i++;
								}
							}
						}
					} 
	
					update_user_meta($user->ID, 'peepso_user_field_gender', $gender);
					update_user_meta($user->ID, 'peepso_user_field_gender_acc', $gender_field_visibility);
				}
			}

			// move birthdate field
			if (!empty($birthdate_field) && !empty($all_users) && count($all_users) > 0) {
				foreach ($all_users as $user) {
					$user_birthdate = str_replace('/', '-', get_user_meta($user->ID, $birthdate_field, true));
					if (!empty($user_birthdate)) {
						update_user_meta($user->ID, 'peepso_user_field_birthdate', $user_birthdate);
						update_user_meta($user->ID, 'peepso_user_field_birthdate_acc', $birthdate_field_visibility);
					}
				}
			}
		}

		echo json_encode(array(
			'url' => $next_url,
			'class' => 'profile',
			'counter' => count($all_users),
			'general' => 1,
		));
	}

	function startMigrateAvatars() {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "users limit %d, %d", $this->limit, $this->per_page);
		$all_users = $wpdb->get_results($sql);

		@mkdir($this->peepso_dir);
		$target_dir = $this->peepso_dir . 'users';
		@mkdir($target_dir);

		if (isset($all_users) && 0 < count($all_users)) {
			$path = get_home_path();
			$peepso_activity = PeepSoActivity::get_instance();

			if (class_exists('PeepSoSharePhotos')) {
				$peepso_photos_model = new PeepSoPhotosModelMod();
				$peepso_albums_model = new PeepSoPhotosAlbumModelMod();

				$photo_system_album = array(
					array(
						'albumname' => __('Profile Avatars', 'peepsoum'),
						'albumname_acc' => PeepSo::ACCESS_PUBLIC,
						'is_system'=> PeepSoSharePhotos::ALBUM_AVATARS),
					array(
						'albumname' => __('Profile Covers', 'peepsoum'),
						'albumname_acc' => PeepSo::ACCESS_PUBLIC,
						'is_system'=> PeepSoSharePhotos::ALBUM_COVERS),
					array(
						'albumname' => __('Stream Photos', 'peepsoum'),
						'albumname_acc' => PeepSo::ACCESS_PUBLIC,
						'is_system'=> PeepSoSharePhotos::ALBUM_STREAM));
			}

			foreach ($all_users as $key => $user) {
				$peepso_user = PeepSoUser::get_instance($user->ID);

				if (class_exists('PeepSoSharePhotos')) {
					foreach ($photo_system_album as $album) {
						$album_id = $peepso_albums_model->get_photo_album_id($user->ID, $album['is_system']);
						if( FALSE === $album_id) {
							$data = array(
								'pho_owner_id' => $user->ID,
								'pho_album_acc' => $album['albumname_acc'],
								'pho_album_name' => $album['albumname'],
								'pho_system_album' => $album['is_system'], // flag for album, 1 = system album, 2 = user created album
							);
							$wpdb->insert($wpdb->prefix . PeepSoPhotosAlbumModel::TABLE , $data);
						}
					}
				}

				@mkdir($target_dir . '/' . $user->ID);
				$upload_dir = wp_upload_dir();
				$dir_path = $upload_dir['basedir'] . '/ultimatemember/' . $user->ID;
				if (is_dir($dir_path)) {
					$search_files = scandir($dir_path);

					if (isset($search_files) && 0 < count($search_files)) {
						$avatar_file = get_user_meta($user->ID, 'profile_photo', TRUE);
						$cover_file = get_user_meta($user->ID, 'cover_photo', TRUE);

						foreach ($search_files as $file) {
							if ("." != $file && ".." != $file && "" != $file) {
								$exist = FALSE;

								if ($file == $avatar_file) {
									$exist = TRUE;
									$file = $dir_path . '/' . $avatar_file;
									$peepso_user->move_avatar_file($file);
									$peepso_user->finalize_move_avatar_file();

									if (class_exists('PeepSoSharePhotos')) {
										$post_meta = PeepSoSharePhotos::POST_META_KEY_PHOTO_TYPE_AVATAR;
										$album_meta = PeepSoSharePhotos::ALBUM_AVATARS;
									}
									
								} else if ($file == $cover_file) {
									$exist = TRUE;
									$file = $dir_path . '/' . $cover_file;
									$peepso_user->move_cover_file($file);

									if (class_exists('PeepSoSharePhotos')) {
										$post_meta = PeepSoSharePhotos::POST_META_KEY_PHOTO_TYPE_COVER;
										$album_meta = PeepSoSharePhotos::ALBUM_COVERS;
									}
								}

								if ($exist && class_exists('PeepSoSharePhotos')) {
									add_filter('peepso_activity_allow_empty_content', function($allowed) { return TRUE; }, 10, 1);
									add_filter('peepso_photos_dir', function($ret) use ($user) { return PeepSo::get_peepso_dir() . 'users/' . $user->ID . '/photos/'; }, 10, 1);

									$extra = array(
										'module_id' => PeepSoSharePhotos::MODULE_ID,
										'act_access' => PeepSo::ACCESS_PUBLIC,
										'post_date_gmt' => date('Y-m-d H:i:s', current_time('timestamp', 1))                
									);
						
									$post_id = $peepso_activity->add_post($user->ID, $user->ID, '', $extra);
									add_post_meta($post_id, PeepSoSharePhotos::POST_META_KEY_PHOTO_TYPE, $post_meta, true);
									
									$activity = $peepso_activity->get_activity_data($post_id, PeepSoSharePhotos::MODULE_ID);
									$file = apply_filters('peepso_photos_cover_original', $file);
									$album = apply_filters('peepso_photos_profile_covers_album', $album_meta);
									$peepso_photos_model->save_images_profile($file, $post_id, $activity->act_id, $album);
								}
							}
						}
					}
				}
			}

			$next_url = PeepSoUM::url('avatar&limit=' . $this->next_limit);
		} else {
			$next_url = PeepSoUM::url('friend');
		}

		echo json_encode(array(
			'url' => $next_url,
			'class' => 'avatar',
			'counter' => count($all_users),
			'general' => 2,
		));
	}

	function startMigrateFriends() {
		$next_url = PeepSoUM::url('follower');

		if (PeepSoUM::plugin_check('friend')) {
			global $wpdb;

			$sql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "um_friends limit %d, %d", $this->limit, $this->per_page);
			$all_friends = $wpdb->get_results($sql);

			if (isset($all_friends) && 0 < count($all_friends)) {
				foreach ($all_friends as $key => $value) {
					$fnd_user_id = $value->user_id1;
					$fnd_friend_id = $value->user_id2;
					$fnd_created = $value->time;

					if ($value->status == 0) {
						$sql = $wpdb->prepare("insert into " . $wpdb->prefix . "peepso_friend_requests (`freq_user_id`, `freq_friend_id`, `freq_created`, `freq_viewed`) values ('%d', '%d', '%s', '%d')", intval($fnd_friend_id), intval($fnd_user_id), $fnd_created, 0);
						$wpdb->query($sql);
					} else {
						$sql = $wpdb->prepare("insert into " . $wpdb->prefix . "peepso_friends (`fnd_user_id`, `fnd_friend_id`, `fnd_created`) values ('%d', '%d', '%s')", intval($fnd_user_id), intval($fnd_friend_id), $fnd_created);
						$wpdb->query($sql);
					}
				}
				$next_url = PeepSoUM::url('friend&limit=' . $this->next_limit);
			}

			$this->counter = count($all_friends);
		}

		echo json_encode(array(
			'url' => $next_url,
			'class' => 'friend',
			'counter' => $this->counter,
			'general' => 3,
		));
	}

	function startMigrateFollowers() {
		$next_url = PeepSoUM::url('activity');

		if (PeepSoUM::plugin_check('friend')) {
			global $wpdb;

			$sql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "um_followers limit %d, %d", $this->limit, $this->per_page);
			$all_followers = $wpdb->get_results($sql);

			if (isset($all_followers) && 0 < count($all_followers)) {
				foreach ($all_followers as $key => $value) {
					$sql = $wpdb->prepare("insert into " . $wpdb->prefix . "peepso_user_followers (`uf_passive_user_id`, `uf_active_user_id`, `uf_follow`, `uf_notify`, `uf_email`) values ('%d', '%d', '%d', '%d', '%d')", intval($value->user_id1), intval($value->user_id2), 1, 0, 0);
					$wpdb->query($sql);
				}
				$next_url = PeepSoUM::url('follower&limit=' . $this->next_limit);
			}

			$this->counter = count($all_followers);
		}

		echo json_encode(array(
			'url' => $next_url,
			'class' => 'follower',
			'counter' => $this->counter,
			'general' => 4,
		));
	}

	public function startMigrateActivity() {
		$next_url = PeepSoUM::url('photo');

		if (PeepSoUM::plugin_check('activity')) {
			global $wpdb;

			$sql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "posts WHERE `post_type` = 'um_activity' order by ID asc limit %d, %d", $this->limit, $this->per_page);
			$all_activities = $wpdb->get_results($sql);

			$peepso_activity = new PeepSoActivity();
			$peepso_like = new PeepSoLike();
			$peepso_notification = new PeepSoNotifications();

			if (class_exists('PeepSoSharePhotos')) {
				$peepso_photos_model = new PeepSoPhotosModelMod();
				$peepso_albums_model = new PeepSoPhotosAlbumModelMod();
				add_filter('peepso_activity_allow_empty_content', function($allowed) { return TRUE; }, 10, 1);
			}

			if (isset($all_activities) && 0 < count($all_activities)) {
				foreach ($all_activities as $key => $post) {
					$photo = get_post_meta($post->ID, '_photo', TRUE);

					if (
					strpos($post->post_content, 'has just followed') !== FALSE || 
					strpos($post->post_content, 'created a new photo album') !== FALSE || 
					strpos($post->post_content, '{author_name}') !== FALSE ||
					(empty($photo) && empty($post->post_content))
					) {
						continue;
					}

					if (!empty($photo) && class_exists('PeepSoSharePhotos')) {
						$module_id = PeepSoSharePhotos::MODULE_ID;
					} else {
						$module_id = 1;
					}

					$owner = get_post_meta($post->ID, '_wall_id', TRUE);
					$owner = $owner == 0 ? $post->post_author : $owner;

					$post_title = intval($owner) . '-' . intval($post->post_author) . '-' . strtotime($post->post_date);
					$post_id = $peepso_activity->add_post(intval($owner), intval($post->post_author), $this->formatTag($post->post_content), array(
						'post_title' => $post_title,
						'post_date' => $post->post_date,
						'post_date_gmt' => $post->post_date_gmt,
						'act_access' => PeepSo::ACCESS_PUBLIC,
						'module_id' => $module_id
					));

					$this->tagNotification($post->post_content, $post->post_author, $post_id);

					// likes
					$likes = get_post_meta($post->ID, '_liked', TRUE);
					if (!empty($likes)) {
						foreach ($likes as $user_id) {
							$peepso_like->add_like($post_id, $module_id, $user_id);
							if ($owner != $user_id) {
								$peepso_notification->add_notification($user_id, $owner, __('liked your post', 'peepsoum'), 'like_post', 1, $post_id);
							}
						}
					}

					$wpdb->query($wpdb->prepare("UPDATE " . $wpdb->prefix . "peepso_notifications SET `not_read` = %d, `not_timestamp` = %s WHERE `not_external_id` = %d", 1, $post->post_date, $post_id));

					// migrate photos
					if (class_exists('PeepSoSharePhotos') && !empty($photo)) {
						$new_filename = md5($photo . time()) . '.jpg';
						$upload_dir = wp_upload_dir();
						$source = $upload_dir['basedir'] . '/ultimatemember/' . $post->post_author . '/' . $photo;
						$dir = PeepSo::get_peepso_dir() . 'users/' . $post->post_author . '/photos';
						@mkdir($dir);
						@mkdir($dir . '/tmp');
						@mkdir($dir . '/thumbs');

						$destination = $dir . '/tmp/' . $new_filename;
						if (strpos($photo, '.png') !== FALSE) {
							$this->png2jpg($source, $destination);
						} else {
							copy($source, $destination);
						}

						$activity = $peepso_activity->get_activity_data($post_id, $module_id);
						add_filter('peepso_photos_dir', function($ret) use ($post) { return PeepSo::get_peepso_dir() . 'users/' . $post->post_author . '/photos/'; }, 10, 1);
						$album_id = $peepso_albums_model->get_photo_album_id($post->post_author, PeepSoSharePhotos::ALBUM_STREAM);
						$peepso_photos_model->save_images(array($new_filename), $post_id, $activity->act_id, $album_id);
						$wpdb->query($wpdb->prepare("UPDATE " . $wpdb->prefix . "peepso_activities SET `act_module_id` = %d WHERE `act_id` = %d", PeepSoSharePhotos::MODULE_ID, $activity->act_id));
						$wpdb->query($wpdb->prepare("UPDATE " . $wpdb->prefix . "peepso_photos SET `pho_owner_id` = %d WHERE `pho_post_id` = %d", $post->post_author, $post_id));
					}

					$sql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "comments WHERE `comment_post_ID` = %d and comment_parent = 0 order by comment_ID asc", $post->ID);
					$all_comments = $wpdb->get_results($sql);

					if (isset($all_comments) && count($all_comments) > 0) {
						foreach ($all_comments as $comment) {
							$post_title = intval($post->post_author) . '-' . intval($comment->user_id) . '-' . strtotime($comment->comment_date);
							$comment_id = $peepso_activity->add_comment(intval($post_id), intval($comment->user_id), $this->formatTag($comment->comment_content), array(
								'post_title' => $post_title,
								'post_date' => $comment->comment_date,
								'post_date_gmt' => $comment->comment_date_gmt,
								'access' => PeepSo::ACCESS_PUBLIC,
								'module_id' => $module_id
							));

							$this->tagNotification($comment->comment_content, $comment->user_id, $comment_id);

							// likes
							$likes = get_comment_meta($comment->comment_ID, '_liked', TRUE);
							if (!empty($likes)) {
								foreach ($likes as $user_id) {
									$peepso_like->add_like($comment_id, 1, $user_id);
									if ($comment->user_id != $user_id) {
										$peepso_notification->add_notification($user_id, $comment->user_id, __('liked your comment', 'peepsoum'), 'like_post', 1, $comment_id);
									}
								}
							}

							$wpdb->query($wpdb->prepare("UPDATE " . $wpdb->prefix . "peepso_notifications SET `not_read` = %d, `not_timestamp` = %s WHERE `not_external_id` = %d", 1, $comment->comment_date, $comment_id));

							$sql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "comments WHERE `comment_parent` = %d order by comment_ID asc", $comment->comment_ID);
							$all_replies = $wpdb->get_results($sql);

							foreach ($all_replies as $reply) {
								$post_title = intval($comment->user_id) . '-' . intval($reply->user_id) . '-' . strtotime($comment->comment_date);
								$reply_id = $peepso_activity->add_comment(intval($comment_id), intval($reply->user_id), $this->formatTag($reply->comment_content), array(
									'post_title' => $post_title,
									'post_date' => $reply->comment_date,
									'post_date_gmt' => $reply->comment_date_gmt,
									'access' => PeepSo::ACCESS_PUBLIC,
									// 'module_id' => $module_id
								));

								$this->tagNotification($reply->comment_content, $reply->user_id, $reply_id);

								// likes
								$likes = get_comment_meta($comment->comment_ID, '_liked', TRUE);
								if (!empty($likes)) {
									foreach ($likes as $user_id) {
										$peepso_like->add_like($comment_id, 1, $user_id);
										if ($reply->user_id != $user_id && !empty($reply->user_id)) {
											$peepso_notification->add_notification($user_id, $reply->user_id, __('liked your comment', 'peepsoum'), 'like_post', 1, $reply_id);
										}
									}
								}

								$wpdb->query($wpdb->prepare("UPDATE " . $wpdb->prefix . "peepso_notifications SET `not_read` = %d, `not_timestamp` = %s WHERE `not_external_id` = %d", 1, $reply->comment_date, $reply_id));
							}
						}
					}
				}

				$next_url = PeepSoUM::url('activity&limit=' . $this->next_limit);
				$this->counter = count($all_activities);
			}
		}

		echo json_encode(array(
			'url' => $next_url,
			'class' => 'activity',
			'counter' => $this->counter,
			'general' => 5,
		));
	}

	function startMigratePhotos() {
		$next_url = PeepSoUM::url('message');
		
		if (class_exists('PeepSoSharePhotos')) {
			global $wpdb;

			$peepso_photos_model = new PeepSoPhotosModelMod();
			$peepso_albums_model = new PeepSoPhotosAlbumModelMod();
			$peepso_activity = new PeepSoActivity();

			$sql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "posts WHERE `post_type` = 'um_user_photos' order by ID asc limit %d, %d", $this->limit, $this->per_page);
			$all_albums = $wpdb->get_results($sql);

			add_filter('peepso_activity_allow_empty_content', function($allowed) { return TRUE; }, 10, 1);

			if (isset($all_albums) && 0 < count($all_albums)) {
				foreach ($all_albums as $key => $post) {
					$dir = PeepSo::get_peepso_dir() . 'users/' . $post->post_author . '/photos';
					@mkdir($dir);
					@mkdir($dir . '/tmp');
					@mkdir($dir . '/thumbs');

					// create an album
					$post_id = $peepso_activity->add_post(intval($post->post_author), intval($post->post_author), '', array(
						'post_date' => $post->post_date,
						'post_date_gmt' => $post->post_date_gmt,
						'act_access' => PeepSo::ACCESS_PUBLIC,
                        'module_id' => PeepSoSharePhotos::MODULE_ID
					));

					add_post_meta($post_id, PeepSoSharePhotos::POST_META_KEY_PHOTO_TYPE, PeepSoSharePhotos::POST_META_KEY_PHOTO_TYPE_ALBUM, true);

					$album_id = $peepso_albums_model->create_album($post->post_author, $post->post_title, PeepSo::ACCESS_PUBLIC, $post->post_title, $post_id);

					$sql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "posts WHERE `post_type` = 'attachment' AND `post_parent` = %d order by ID asc", $post->ID);
					$photos = $wpdb->get_results($sql);

					if (isset($photos) && 0 < count($photos)) {
						add_post_meta($post_id, PeepSoSharePhotos::POST_META_KEY_PHOTO_COUNT, count($photos), true);
						$files = array();

						foreach ($photos as $photo_key => $photo_post) {
							$filename = explode('/', $photo_post->guid);
							$filename = end($filename);
							$new_filename = md5($filename . time()) . '.jpg';
							$source = str_replace(get_home_url(), get_home_path(), $photo_post->guid);
							$destination = $dir . '/tmp/' . $new_filename;

							if (strpos($filename, '.png') !== FALSE) {
								$this->png2jpg($source, $destination);
							} else {
								copy($source, $destination);
							}

							$files[] = $new_filename;
						}

						$_post = $peepso_activity->get_activity_data($post_id, PeepSoSharePhotos::MODULE_ID);
						add_filter('peepso_photos_dir', function($ret) use ($post) { return PeepSo::get_peepso_dir() . 'users/' . $post->post_author . '/photos/'; }, 10, 1);
						$peepso_photos_model->save_images($files, $post_id, $_post->act_id, $album_id);
						$wpdb->query($wpdb->prepare("UPDATE " . $wpdb->prefix . "peepso_photos SET `pho_owner_id` = %d WHERE `pho_post_id` = %d", $post->post_author, $post_id));
					}
				}

				$next_url = PeepSoUM::url('photo&limit=' . $this->next_limit);
				$this->counter = count($all_albums);
			}
		}

		echo json_encode(array(
			'url' => $next_url,
			'class' => 'photo',
			'counter' => $this->counter,
			'general' => 5,
		));
	}

	function startMigrateMessages() {
		global $wpdb;

		$next_url = PeepSoUM::url('profile_field');

		if (PeepSoUM::plugin_check('message')) {
			register_post_type(PeepSoMessagesPlugin::CPT_MESSAGE);
			$peepso_message = new PeepSoMessagesModel();

			$sql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "um_conversations ORDER BY conversation_id asc limit %d, %d ", $this->limit, $this->per_page);
			$all_conversations = $wpdb->get_results($sql);

			if (isset($all_conversations) && 0 < count($all_conversations)) {
				foreach ($all_conversations as $conversation) {

					$sql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "um_messages where `conversation_id`=%d order by message_id asc", intval($conversation->conversation_id));
					$all_messages = $wpdb->get_results($sql);

					$first_message = true;

					if (isset($all_messages) && 0 < count($all_messages)) {
						$parent_id = 0;
						foreach ($all_messages as $message) {

							if ($first_message) {
								$parent_id = $message_id = $peepso_message->create_new_conversation($conversation->user_b, $message->content, $message->content, array($conversation->user_a));
								$first_message = false;
							} else {
								$message_id = $peepso_message->add_to_conversation($conversation->user_a, $parent_id, $message->content);
							}

							wp_update_post(array(
								'ID' => $message_id,
								'post_date' => $message->time,
								'post_date_gmt' => $message->time,
							));

							
						}
					}
				}
				$next_url = PeepSoUM::url('message&limit=' . $this->next_limit);
			}

			$this->counter = count($all_conversations);
		}

		echo json_encode(array(
			'url' => $next_url,
			'class' => 'message',
			'counter' => $this->counter,
			'general' => 6,
		));
	}

	function startMigrateProfileFields() {
		$next_url = PeepSoUM::url('profile_field_user_data');
		$new_fields = array();

		// install core profile fields
		PeepSoProfileFields::install();

		if (PeepSoUM::plugin_check('profile_field')) {
			global $wpdb;

			$fields = PeepSoUM::get_fields();

			if (isset($fields) && 0 < count($fields)) {
				$gender_field = get_transient('peepso_migrate_gender_field');
				$birthdate_field = get_transient('peepso_migrate_birthdate_field');

				usort($fields, function($a, $b) {
					if (isset($a['position']) && isset($b['position'])) {
						return $a['position'] <=> $b['position'];
					}
				});

				$allowed_type = array('select', 'multiselect', 'checkbox', 'radio', 'date', 'textbox', 'number', 'textarea', 'url', 'text');

				foreach ($fields as $value) {
					if (!isset($value['position']) || !in_array($value['type'], $allowed_type) || in_array($value['metakey'], array('first_name', 'last_name'))) {
						continue;
					}

					if ($value['metakey'] == $gender_field || $value['metakey'] == $birthdate_field) {
						$posts = get_posts( array( 
							'name' => $value['metakey'] == 'gender' ? 'gender' : 'birthdate', 
							'post_type' => 'peepso_user_field'
						) );
						
						$post_id = $posts[0]->ID;
					} else {
						$aPostData = array(
							'post_author' => 1,
							'post_date' => current_time('mysql'),
							'post_date_gmt' => current_time('mysql'),
							'post_title' => $value['title'],
							'post_content' => $value['title'],
							'post_status' => 'publish',
							'comment_status' => 'closed',
							'ping_status' => 'closed',
							'post_parent' => 0,
							'guid' => get_bloginfo('url') . '/peepso_user_field/cpf/',
							'post_type' => 'peepso_user_field',
						);
	
						$post_id = wp_insert_post($aPostData);
	
						wp_update_post(array(
							'ID' => $post_id,
							'post_name' => $post_id
						));
	
						// postmeta
						$default_postmeta = array(
							'order' => $value['position'],
							'is_core' => 0,
							'_wp_old_slug' => 'cpf',
							'method' => '_render'
						);
	
						// insert field postmeta
						foreach ($default_postmeta as $meta_key => $meta_val) {
							update_post_meta($post_id, $meta_key, $meta_val);
						}
					}
					
					$postmeta = array();
					if (isset($value['required']) && $value['required'] == 1) {
						$postmeta['validation'] = serialize(array('required' => 1));
					}
	
					// check field visibility
					switch ($value['public']) {
						case 1:
							$postmeta['default_acc'] = PeepSo::ACCESS_PUBLIC;
							break;
						case 2:
							$postmeta['default_acc'] = PeepSo::ACCESS_MEMBERS;
							break;
						default:
							$postmeta['default_acc'] = PeepSo::ACCESS_PRIVATE;
							break;
					}
	
					// add additional postmeta into each field type
					switch ($value['type']) {
						case 'select':
						case 'multiselect' :
						case 'checkbox':
						case 'radio':
							$select_options = array();
							$i = 1;
							if (isset($value['options']) && count($value['options']) > 0) {
								if (class_exists('PeepSoExtendedProfiles') && $value['metakey'] == $gender_field) {
									$current_gender_field = get_transient('peepso_migrate_gender_field_data');
									$gender_field_male = get_transient('peepso_migrate_gender_field_male');
									$gender_field_female = get_transient('peepso_migrate_gender_field_female');
					
									foreach ($current_gender_field['options'] as $gender) {
										if ($gender == $gender_field_male) {
											$select_options['m'] = $gender_field_male;
										} else if ($gender == $gender_field_female) {
											$select_options['f'] = $gender_field_female;
										} else {
											$select_options['option_' . $post_id . '_' . $i] = $gender;
										}
										$i++;
									}
								} else {
									foreach ($value['options'] as $field_key => $field_value) {
										$select_options['option_' . $post_id . '_' . $i] = $field_value;
										$i++;
									}
								}

								set_transient('peepso_migrate_field_' . $post_id, $select_options);
							}
	
							$postmeta['select_options'] = $select_options;
	
							if ($value['type'] == 'select') {
								$postmeta['class'] = 'selectsingle';
								$postmeta['method_form'] = '_render_form_select';
							} else if ($value['type'] == 'radio') {
								$postmeta['class'] = 'selectsingle';
								$postmeta['method_form'] = '_render_form_checklist';
							} else {
								$postmeta['class'] = 'selectmulti';
								$postmeta['method_form'] = '_render_form_checklist';
							}
	
							break;
						case 'date' :
							$postmeta['class'] = 'textdate';
							$postmeta['method_form'] = '_render_form_input';
							break;
						case 'text' :
						case 'textbox' :
						case 'number' :
							$postmeta['class'] = 'text';
							$postmeta['method_form'] = '_render_form_input';
							break;
						case 'textarea' :
							$postmeta['class'] = 'text';
							$postmeta['method_form'] = '_render_form_textarea';
							break;
						case 'url' :
							$postmeta['class'] = 'texturl';
							$postmeta['method_form'] = '_render_form_input';
							break;
					}

					// insert field postmeta
					foreach ($postmeta as $meta_key => $meta_val) {
						update_post_meta($post_id, $meta_key, $meta_val);
					}

					// save as transient for new field
					$new_fields[$value['metakey']] = $post_id;
				}
			}
		}

		set_transient('peepso_migrate_new_fields', $new_fields);

		echo json_encode(array(
			'url' => $next_url,
			'class' => 'profile_field',
			'counter' => 0,
			'general' => 7,
		));
	}

	function startMigrateProfileFieldUserData() {
		global $wpdb;

		$next_url = PeepSoUM::url('unpublish');
		$this->counter = 0;

		if (PeepSoUM::plugin_check('profile_field')) {
			$fields = PeepSoUM::get_fields();
			sort($fields);
	
			if (isset($fields) && 0 < count($fields)) {
				$new_fields = get_transient('peepso_migrate_new_fields');
				$i = isset($_GET['field']) ? intval($_GET['field']) : 0;
	
				if (isset($fields[$i])) {
					$field = $fields[$i];
	
					if (!isset($field['position']) || !isset($new_fields[$field['metakey']])) {
					
					} else {
						$post_id = $new_fields[$field['metakey']];
						$field_option = get_transient('peepso_migrate_field_' . $post_id);
			
						// migrate user data
						$user_data = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "usermeta WHERE meta_key = %s", $field['metakey']));
			
						if (isset($user_data) && count($user_data) > 0) {
							foreach ($user_data as $user_key => $user_value) {
								$selected_user_options = null;
			
								switch ($field['type']) {
									case 'radio' :
										$selected_user_options_old = unserialize($user_value->meta_value)[0];
										$selected_user_options = array_search($selected_user_options_old, $field_option);
										break;
									case 'select':
										$selected_user_options_old = $user_value->meta_value;
										$selected_user_options = array_search($selected_user_options_old, $field_option);
										break;
									case 'multiselect' :
									case 'checkbox':
										$selected_user_options = array();
										$selected_user_options_old = unserialize($user_value->meta_value);
			
										foreach ($selected_user_options_old as $option_value) {
											$selected_user_options[] = array_search($option_value, $field_option);
										}
										break;
									case 'date' :
										$selected_user_options = str_replace('/', '-', $user_value->meta_value);
										break;
									default :
										$selected_user_options = $user_value->meta_value;
										break;
								}
			
								if ($field['metakey'] == 'gender') {
									update_user_meta($user_value->user_id, 'peepso_user_field_gender', $selected_user_options);
								} else {
									update_user_meta($user_value->user_id, 'peepso_user_field_' . $post_id, $selected_user_options);
								}
							}
						}
					}
	
					$i++;
					$next_url = PeepSoUM::url('profile_field_user_data&field=' . $i);
					$this->counter = 1;
				}
			}
		}
			

		echo json_encode(array(
			'url' => $next_url,
			'class' => 'profile_field',
			'counter' => $this->counter,
			'general' => 8,
		));
	}

	function unpublish() {
		global $wpdb;

		$sql = "update " . $wpdb->prefix . "posts set `post_content`='[peepso_activity]' where `post_type`='page' and `post_name`='activity'";
		$wpdb->query($sql);

		$sql = "update " . $wpdb->prefix . "posts set `post_content`='[peepso_members]' where `post_type`='page' and `post_name`='members'";
		$wpdb->query($sql);

		$sql = "update " . $wpdb->prefix . "posts set `post_content`='[peepso_profile]' where `post_type`='page' and `post_name`='profile'";
		$wpdb->query($sql);

		$sql = "update " . $wpdb->prefix . "posts set `post_content`='[peepso_register]' where `post_type`='page' and `post_name`='register'";
		$wpdb->query($sql);

		$sql = "update " . $wpdb->prefix . "posts set `post_content`='[peepso_recover]' where `post_type`='page' and `post_name`='password-recover'";
		$wpdb->query($sql);

		$sql = "delete from " . $wpdb->prefix . "options where `option_name` like '%peepso_migrate%'";
		$wpdb->query($sql);

		$sql = "delete from " . $wpdb->prefix . "peepso_mail_queue";
		$wpdb->query($sql);
		
		$this->deactivatePlugins();

		echo json_encode(array(
			'finished' => 1,
			'counter' => $this->counter,
			'general' => 12,
		));
	}

	function deactivatePlugins() {
		global $wpdb;

		$sql = "SELECT `option_value` FROM " . $wpdb->prefix . "options where `option_name`='active_plugins'";
		$option_value = $wpdb->get_results($sql);

		if (isset($option_value) && isset($option_value["0"])) {
			$option_value = $option_value["0"]->option_value;
			$option_value = unserialize($option_value);

			if (isset($option_value) && 0 < count($option_value)) {
				$temp = array();
				foreach ($option_value as $key => $value) {
					if (FALSE === strpos(" " . $value, "ultimate-member") && FALSE === strpos(" " . $value, "PeepSoUM")) {
						$temp[] = $value;
					}
				}
				$option_value = $temp;
			}

			$sql = $wpdb->prepare("update " . $wpdb->prefix . "options set `option_value`='%s' where `option_name`='active_plugins'", serialize($option_value));
			$wpdb->query($sql);
		}
	}

	function deletePeepSoContent() {
		global $wpdb;

		$sql = "delete from " . $wpdb->prefix . "peepso_activities";
		$wpdb->query($sql);

		if (class_exists('PeepSoFriendsPlugin')) {
			$sql = "delete from " . $wpdb->prefix . "peepso_friends";
			$wpdb->query($sql);

			$sql = "delete from " . $wpdb->prefix . "peepso_friend_requests";
			$wpdb->query($sql);
		}

		if (class_exists('PeepSoMessagesPlugin')) {
			$sql = "delete from " . $wpdb->prefix . "peepso_message_participants";
			$wpdb->query($sql);

			$sql = "delete from " . $wpdb->prefix . "peepso_message_recipients";
			$wpdb->query($sql);
		}

		$sql = "delete from " . $wpdb->prefix . "peepso_notifications";
		$wpdb->query($sql);

		$sql = "delete from " . $wpdb->prefix . "peepso_photos";
		$wpdb->query($sql);

		$sql = "delete from " . $wpdb->prefix . "peepso_photos_album";
		$wpdb->query($sql);

		$sql = "delete from " . $wpdb->prefix . "peepso_users";
		$wpdb->query($sql);

		$sql = "delete from " . $wpdb->prefix . "posts where `post_type` like ('%peepso%')";
		$wpdb->query($sql);

		$sql = "delete from " . $wpdb->prefix . "postmeta where `meta_key` like '%peepso%'";
		$wpdb->query($sql);

		$sql = "delete from " . $wpdb->prefix . "usermeta where `meta_key` like '%peepso%'";
		$wpdb->query($sql);

		echo json_encode(array(
			'url' => PeepSoUM::url('profile')
		));
	}

	private function png2jpg($originalFile, $outputFile) {
		$image = imagecreatefrompng($originalFile);
		imagejpeg($image, $outputFile, 100);
		imagedestroy($image);
	}

	private function formatTag($content) {
		if (strpos($content, 'um-user-tag') !== FALSE) {
			preg_match_all('/<a href="(.*?)"(.*?)a>/s', $content, $match);
			$i = 0;
			if (isset($match[1])) {

				foreach ($match[1] as $user_url) {
					$user = explode('/' , rtrim($user_url, '/'));
					$user_slug = end($user);
	
					$wp_user = get_user_by('slug', $user_slug);
					$content = str_replace($match[0][$i], '[peepso_tag id=' . $wp_user->ID . ']' . $wp_user->user_nicename . '[/peepso_tag]', $content);
					$i++;
				}
			}
		}

		return strip_tags($content);
	}

	private function tagNotification($content, $author, $post_id) {
		$peepso_notification = new PeepSoNotifications();

		if (strpos($content, 'um-user-tag') !== FALSE) {
			preg_match_all('/<a href="(.*?)"(.*?)a>/s', $content, $match);
			$i = 0;
			if (isset($match[1])) {
				foreach ($match[1] as $user_url) {
					$user = explode('/' , rtrim($user_url, '/'));
					$user_slug = end($user);
	
					$wp_user = get_user_by('slug', $user_slug);

					if ($author != $wp_user->ID && !empty($author) && !empty($wp_user->ID)) {
						$peepso_notification->add_notification($author, $wp_user->ID, __('mentioned you', 'peepsoum'), 'tag_post', 1, $post_id);
					}
					$i++;
				}
			}
		}
	}

}

if (class_exists('PeepSoSharePhotos')) {
	class PeepSoPhotosModelMod extends PeepSoPhotosModel
	{

		public function get_photo_dir($user_id = 0)
		{
			$result = apply_filters('peepso_photos_dir', $photo_dir);
			return $result;
		}
	}

	class PeepSoPhotosAlbumModelMod extends PeepSoPhotosAlbumModel
	{
		public function create_album($user_id, $name, $privacy, $description, $post_id, $module_id = 0) {
			global $wpdb;

			$album_data['pho_owner_id'] = $user_id;
			$album_data['pho_album_acc'] = $privacy;
			$album_data['pho_album_name'] = $name;
			$album_data['pho_album_desc'] = $description;
			$album_data['pho_post_id'] = $post_id;
			$album_data['pho_module_id'] = $module_id;

			$wpdb->insert($wpdb->prefix . self::TABLE, $album_data);

			$album_id = $wpdb->insert_id;

			return $album_id;
		}
	}
}

?>