<?php
$package_name = $vars['package_name'];
$options      = $vars['package_name'];
?>
<script type="text/javascript">
	Contextly.ready('load', <?php print $this->encode_json_for_script( $package_name ); // WPCS: XSS ok. ?>, function() {
		Contextly.ready('widgets', 'remove'<?php print ( $options ? ', ' . $this->encode_json_for_script( $options ) : '' ); // WPCS: XSS ok. ?>);
	});
</script>
