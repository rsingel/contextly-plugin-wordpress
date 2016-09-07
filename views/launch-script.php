<script type="text/javascript">
	<?php foreach ($load as $name => $code) : ?>
	Contextly.ready('load', <?php print $this->encodeJsonForScript($name); ?>, function() {
		<?php print $code; ?>
	});
	<?php endforeach; ?>
</script>
