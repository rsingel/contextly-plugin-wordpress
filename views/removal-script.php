<script type="text/javascript">
	Contextly.ready('load', <?php print $this->encodeJsonForScript($package_name); ?>, function() {
		Contextly.ready('widgets', 'remove'<?php print ($options ? ', ' . $this->encodeJsonForScript($options) : ''); ?>);
	});
</script>
