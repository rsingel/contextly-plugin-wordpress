<?php
$load = $vars['load'];
?>
<script type="text/javascript">
	<?php foreach ( $load as $name => $code ) : ?>
	Contextly.ready('load', <?php print $this->encode_json_for_script( $name ); // WPCS: XSS ok. ?>, function() {
		<?php print $code; // WPCS: XSS ok. ?>
	});
	<?php endforeach; ?>
</script>
