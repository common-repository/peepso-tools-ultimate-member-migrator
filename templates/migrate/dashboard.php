<?php
global $wpdb;

$icon_error = PeepSoUM::get_asset('images/error.png');
$icon_tick = PeepSoUM::get_asset('images/tick.png');
?>

<form id="migrate-form" name="migrate_form" method="post">
	<input type="hidden" name="start_peepso_migrate" value="1" />
	<script type="text/javascript" language="javascript">
        function confirmStartMigrate() {
            document.getElementById("peepso-migrator-modal-background").style.display = "";
            document.getElementById("peepso-migrator-modal").style.display = "";
        }

        function stopMigrate() {
            document.getElementById("peepso-migrator-modal-background").style.display = "none";
            document.getElementById("peepso-migrator-modal").style.display = "none";
        }
	</script>

	<div id="peepso-migrator-modal-background" class="peepso-migrator-modal-background" style="display:none;"></div>

	<div id="peepso-migrator-modal" class="peepso-migrator-modal" style="display:none;">
		<p style="text-align:center; margin:10px 0px"><?php _e('Please make sure gender and birthdate fields are matched as you want them to be migrated.', 'PeepSoUM'); ?>
			<br />
			<?php _e('Also, note that any and all data that you have currently in PeepSo will be removed.', 'PeepSoUM'); ?>   
			<br />
			<?php _e('Do you wish to proceed?', 'PeepSoUM'); ?></p>
		<div style="width:100%; text-align:center;">
			<input type="button" class="btn btn-success" value="<?php _e('Yes', 'PeepSoUM'); ?>" onclick="document.migrate_form.submit()" />
			<input type="button" class="btn btn-info" value="<?php _e('No', 'PeepSoUM'); ?>" onclick="javascript:stopMigrate();" />
		</div>
	</div>

	<div>
		<?php echo PeepSoTemplate::exec_template('migrate', 'header'); ?>

		<p align="justify">
			<?php _e('This migrator has been created to enable administrators of Ultimate Member-based communities to move easily to PeepSo and experience social networking for WordPress at its best. Because Ultimate Member and PeepSo do not share exactly the same sets of features, some of the data on Ultimate Member cannot currently be moved.', 'PeepSoUM'); ?>
		</p>

		<p align="justify">
			<?php _e('Depending on the Ultimate Member components and PeepSo plugins installed, the migrator is able to transfer the following data:', 'PeepSoUM'); ?>
		<table border="0" width="100%">
			<tr>
				<td class="plugin-check"><img src="<?php echo $icon_tick; ?>" class="image-icon"/></td>
				<td><?php _e('User Profiles', 'PeepSoUM'); ?></td>
			</tr>
			<tr>
				<td class="plugin-check"><img src="<?php echo $icon_tick; ?>" class="image-icon"/></td>
				<td><?php _e('User Profile Avatars and Cover images', 'PeepSoUM'); ?></td>
			</tr>
			<tr>
				<td class="plugin-check">
					<?php
					if (!class_exists('PeepSoFriendsPlugin')) {
						echo __('Requires', 'PeepSoUM') . ' <a href="https://www.peepso.com/downloads/friendso/" target="_blank">Friends Plugin</a>';
						$icon = $icon_error;
					} else {
						$icon = $icon_tick;
					}
					?>
					<img src="<?php echo $icon; ?>" class="image-icon"/>
				</td>
				<td><?php _e('User Friends and Followers', 'PeepSoUM'); ?></td>
			</tr>
			<tr>
				<td class="plugin-check">
					<?php
					if (!class_exists('PeepSoSharePhotos')) {
						echo __('Requires', 'PeepSoUM') . ' <a href="https://www.peepso.com/downloads/picso/" target="_blank">Photos Plugin</a>';
						$icon = $icon_error;
					} else {
						$icon = $icon_tick;
					}
					?>
					<img src="<?php echo $icon; ?>" class="image-icon"/>
				</td>
				<td><?php _e('User Photo Albums', 'PeepSoUM'); ?></td>
			</tr>
			<tr>
				<td class="plugin-check">
					<?php
					if (!class_exists('PeepSoMessagesPlugin')) {
						echo __('Requires', 'PeepSoUM') . ' <a href="https://www.peepso.com/downloads/msgso/" target="_blank">Chat Plugin</a>';
						$icon = $icon_error;
					} else {
						$icon = $icon_tick;
					}
					?>
					<img src="<?php echo $icon; ?>" class="image-icon"/>
				</td>
				<td><?php _e('User Messages', 'PeepSoUM'); ?></td>
			</tr>
			<tr>
				<td class="plugin-check"><img src="<?php echo $icon_tick; ?>" class="image-icon"/></td>
				<td><?php _e('Activity Stream (User status updates and comments on status updates)', 'PeepSoUM'); ?></td>
			</tr>
			<tr>
				<td class="plugin-check"><img src="<?php echo $icon_tick; ?>" class="image-icon"/></td>
				<td><?php _e('Notifications', 'PeepSoUM'); ?></td>
			</tr>
			<?php
					$result = PeepSoUm::get_fields();
					if (!empty($result)) {
						?>
					<tr>
						<td class="plugin-check"><img src="<?php echo $icon_tick; ?>" class="image-icon"/></td>
						<td><?php _e('Core Profile Fields (First Name, Last Name, Gender, Birthday, About me, Website)', 'PeepSoUM'); ?>
							<br/><span class="select-field"><?php _e('Select Ultimate Member Gender Field', 'PeepSoUM'); ?></span>
							<?php

							// get post 
							$option_fields = '<option value="">' . __('Select Field ...', 'PeepSoUM') . '</option>';
							$option_gender_select = array();
							$sub_select_fields = '';

							foreach ($result as $field_key => $field) {
								if (!isset($field['options'])) {
									continue;
								}

								$option_fields .= '<option value="' . $field_key . '">' . $field['title'] . '</option>';
								$option_gender_select = array();
								$option_gender_select[$field_key] = '<option value="">' . __('Select Field ...', 'PeepSoUM') . '</option>';
								
								foreach ($field['options'] as $gender) {
									$option_gender_select[$field_key] .= '<option value="' . $gender . '">' . $gender . '</option>';
								}

								ob_start();
								?>
								<div class="gender_field_detail-<?php echo $field_key; ?>" style="display:none;">
									<?php
									if (count($option_gender_select) > 0) {
										foreach ($option_gender_select as $key => $value) {
											?>
											<div class="field-gender-<?php echo $field_key; ?>" style="display:none">
												<br/><span class="select-field"><?php _e('Select Male Field', 'PeepSoUM'); ?></span>
												<select class="select-field" name="gender_field_male_<?php echo $field_key; ?>"><?php echo $value; ?></select>
												<br/><span class="select-field"><?php _e('Select Female Field', 'PeepSoUM'); ?></span>
												<select class="select-field" name="gender_field_female_<?php echo $field_key; ?>"><?php echo $value; ?></select>
												<?php
												if (class_exists('PeepSoExtendedProfiles')) {
													for ($i = 1; $i <= (count($field['options']) - 2); $i++) {
														?>
														<br/><span class="select-field"><?php _e('Select Custom Field', 'PeepSoUM'); ?> <?php echo $i + 2; ?></span>
														<select class="select-field" name="gender_field_custom<?php echo $i + 2; ?>_<?php echo $field_key; ?>"><?php echo $value; ?></select>
														<?php
													}
												}
												?>
											</div>
											<?php
										}
									}
									?>
								</div>
								<?php
								$sub_select_fields = $sub_select_fields . ob_get_clean();
							}
							?>
							<select class="select-field" name="gender_field">
								<?php echo $option_fields; ?>
							</select>
							<?php echo $sub_select_fields; ?>
							<script>
								jQuery(function ($) {

									$('[name="gender_field"]').on('change', function () {
										$('[class*="gender_field_detail-"], [class*="field-gender-"]').hide();

										if ($('[class*="field-gender-' + $(this).val() + '"]').length > 0) {
											$('.gender_field_detail-' + $(this).val() + ', [class*="field-gender-' + $(this).val() + '"]').show();
										}
									});

								});
							</script>
							<br/><span class="select-field"><?php _e('Select Ultimate Member Birthdate Field', 'PeepSoUM'); ?></span>
							<select class="select-field" name="birthdate_field">
								<?php
								$option_fields = '<option>' . __('Select Field ...', 'PeepSoUM') . '</option>';

								if (!empty($result)) {
									foreach ($result as $field_key => $field) {
										if ($field['type'] == 'date') {
											$option_fields .= '<option value="' . $field_key . '">' . $field['title'] . '</option>';
										}
									}
								}
								echo $option_fields;
								?>
							</select>
						</td>
					</tr>
						<?php
					}
			?>
			
			<tr>
				<td class="plugin-check">
					<?php
					if (!class_exists('PeepSoExtendedProfiles')) {
						echo __('Requires', 'PeepSoUM') . ' <a href="https://www.peepso.com/downloads/profileso/" target="_blank">Extended Profiles Plugin</a>';
						$icon = $icon_error;
					} else {
						$icon = $icon_tick;
					}
					?>
					<img src="<?php echo $icon; ?>" class="image-icon"/>
				</td>
				<td><?php _e('Custom Profile Fields (Text, Multiple and Single Select, Date, URL... All fields will be automatically created in PeepSo and data migrated accordingly)', 'PeepSoUM'); ?></td>
			</tr>

		</table>
		</p>

		<div style="float:left; width:90%; margin-bottom:10px; background-color:#FFFFFF; padding:5px;color:red;">
			<b style="font-size:16px;"><?php _e('Note:', 'PeepSoUM') ?></b> <span style="font-size:14px;"><?php _e('After running the migrator, the Ultimate Member and the Migration plugins will be automatically deactivated.', 'PeepSoUM'); ?></span>
		</div>

		<div style="float:left; width:50%">
			<a href="<?php echo get_admin_url(); ?>"><?php _e('Cancel', 'PeepSoUM'); ?></a>
		</div>

		<div style="float:left; width:50%; text-align:right;">
			<input class="button-primary" type="button" onclick="javascript:confirmStartMigrate();" name="migrate" value="<?php _e('Start Migration', 'PeepSoUM'); ?>" />
		</div>

	</div>
</form>