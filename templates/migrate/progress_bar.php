
<tr>
	<td width="80%">
		<div class="progressbar progressbar-yellow <?php echo $class; ?>">
			<span class="info">
				<?php if (isset($total)) : ?>
					<span class="counter">0</span> to <span class="counter-total"><?php echo $total; ?></span>
				<?php endif; ?>
			</span>
			<div class="progressbar-inner"></div>
		</div>
	</td>
	<td width="20%">
		<?php echo $label; ?>
	</td>
</tr>