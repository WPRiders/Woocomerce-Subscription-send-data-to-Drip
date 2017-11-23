<div class="wrap">
	<h2>Drip Settings</h2>

	<form name="drip-settings-form" id="drip-settings-form" method="post" action="options.php">
		<?php
		settings_fields( 'drip_options' );
		do_settings_sections( 'wp-drip' );
		?>
		<?php submit_button(); ?>
	</form>
</div>