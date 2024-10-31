<?php
$general_total_progress = 12;

echo PeepSoTemplate::exec_template('migrate', 'header');
?>
<div id="peepso-migrator-modal-background" class="peepso-migrator-modal-background" style="display:none;"></div>

<div id="peepso-migrator-modal" class="peepso-migrator-modal" style="display:none;">
	<h3 style="text-align:center; margin:10px 0px">
		<?php _e('The migration has been completed successfully!', 'PeepSoUM'); ?>
	</h3>
	<p style="text-align:center; margin:10px 0px"><?php _e('NOTE:The PeepSoMigrator and Ultimate Member plugins have been deactivated.', 'PeepSoUM'); ?></p>
	<div style="width:100%; text-align:center;">
		<input type="button" class="btn btn-success" value="<?php _e('See Dashboard', 'PeepSoUM'); ?>" onclick="window.location = 'admin.php?page=peepso'" />
		<input type="button" class="btn btn-info" value="<?php _e('See Frontend', 'PeepSoUM'); ?>" onclick="window.location = '<?php echo get_site_url() . "/activity"; ?>'" />
	</div>
</div>

<div style="width:90%;">
	<table width="100%">
		<?php
		echo PeepSoTemplate::exec_template('migrate', 'progress_bar', array('class' => 'general', 'label' => __('General Progress', 'PeepSoUM')));

		echo PeepSoTemplate::exec_template('migrate', 'progress_bar', array('class' => 'profile', 'total' => $total_profiles, 'label' => __('User Profiles and Core Profile Fields', 'PeepSoUM')));

		echo PeepSoTemplate::exec_template('migrate', 'progress_bar', array('class' => 'avatar', 'total' => $total_avatars, 'label' => __('User Profile Avatars and Cover Images', 'PeepSoUM')));

		if (PeepSoUM::plugin_check('friend')) {
			echo PeepSoTemplate::exec_template('migrate', 'progress_bar', array('class' => 'friend', 'total' => $total_friends, 'label' => __('User Friends', 'PeepSoUM')));
		}

		if (PeepSoUM::plugin_check('friend')) {
			echo PeepSoTemplate::exec_template('migrate', 'progress_bar', array('class' => 'follower', 'total' => $total_followers, 'label' => __('User Followers', 'PeepSoUM')));
		}

		if (PeepSoUM::plugin_check('activity')) {
			echo PeepSoTemplate::exec_template('migrate', 'progress_bar', array('class' => 'activity', 'total' => $total_activities, 'label' => __('Activity', 'PeepSoUM')));
		}

		if (PeepSoUM::plugin_check('photo')) {
			echo PeepSoTemplate::exec_template('migrate', 'progress_bar', array('class' => 'photo', 'total' => $total_photos, 'label' => __('Album', 'PeepSoUM')));
		}

		if (PeepSoUM::plugin_check('message')) {
			echo PeepSoTemplate::exec_template('migrate', 'progress_bar', array('class' => 'message', 'total' => $total_messages, 'label' => __('Message', 'PeepSoUM')));
		}

		if (PeepSoUM::plugin_check('profile_field')) {
			echo PeepSoTemplate::exec_template('migrate', 'progress_bar', array('class' => 'profile_field', 'total' => $total_profile_fields, 'label' => __('Custom Profile Fields', 'PeepSoUM')));
		}

		?>
	</table>
</div>

<?php if (isset($next_url)) : ?>
	<script type="text/javascript">
		jQuery(function ($) {

			function get_data(url) {
				$.ajax({
					type: 'get',
					url: url
				}).success(function(result) {

					try {
						var json = jQuery.parseJSON(result);
					} catch (err) {
						get_data(url);
						return;
					}

					var general_progress_bar = $('.progressbar.general'),
							general_progress_bar_inner = general_progress_bar.find('.progressbar-inner'),
							general_total_progress = <?php echo $general_total_progress; ?>,
							general_progress = 100 / general_total_progress * json.general,
							progress_bar = $('.progressbar.' + json.class),
							progress_bar_inner = progress_bar.find('.progressbar-inner'),
							counter_wrapper = progress_bar.find('.counter'),
							counter_value = parseInt(counter_wrapper.html()),
							counter_total_wrapper = progress_bar.find('.counter-total'),
							counter_total_value = parseInt(counter_total_wrapper.html());

					counter_wrapper.html(counter_value + json.counter);

					var progress_value = 100 / counter_total_value * counter_value;
					
					if (progress_value >= 99 || counter_total_value == 0) {
						progress_value = 100;
						progress_bar.removeClass('progressbar-yellow').addClass('progressbar-green');
						counter_wrapper.html(counter_total_value);
					}

					progress_bar_inner.css('width', progress_value + '%');
					general_progress_bar_inner.css('width', general_progress + '%');

					if (general_progress >= 100) {
						general_progress_bar.removeClass('progressbar-yellow').addClass('progressbar-green');
					}

					if (typeof json.finished == 'undefined' && typeof json.url != 'undefined') {
						get_data(json.url);
					} else {
						$('.peepso-migrator-modal-background, .peepso-migrator-modal').show();
					}

				}).fail(function(result) {
					get_data(url);
					return;
				});
			}

			get_data('<?php echo $next_url; ?>');
		});
	</script>
<?php endif; ?>