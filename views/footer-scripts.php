<script type="text/javascript">
	<?php print $data; ?>
	<?php foreach ($load as $name => $code) : ?>
	Contextly.ready('load', <?php print json_encode( $name ); ?>, function() {
		<?php print $code; ?>
	});
	<?php endforeach; ?>
</script>
