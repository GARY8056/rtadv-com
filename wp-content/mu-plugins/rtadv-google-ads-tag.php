<?php
/**
 * Plugin Name: RTADV Google Ads Tag
 * Description: Inject the Google Ads gtag base snippet on frontend pages.
 * Version: 1.0.0
 * Author: rtadv.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_head',
	static function () {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return;
		}

		?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-11007405468"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'AW-11007405468');
</script>
		<?php
	},
	1
);
