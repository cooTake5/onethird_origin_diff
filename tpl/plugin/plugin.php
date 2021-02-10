<?php

	global $ut,$params,$config,$plugin_ar;

/* -- options
	$params['default_ogp_image'] = "{$params['data_url']}img/logo.png";
	//$params['folder-systag'] = true;							// 
*/

/* -- login pugin for security
	$plugin_ar[ LOGIN_ID ] = array(		// 
		  'selector' => "login????"
		, 'php' => "login"
		, 'page_renderer' => "login_page"
		, 'url' => true
	);
*/


function analytics()
{
	global $params;

	$buff = '';

	if (isset($params['manager']) || isset($html['head']['robots'])) {
		return '';
	}

/* -- analytics sample 
$buff .= <<<EOT

<script type="text/javascript">

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-00000000-1']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

</script>
EOT;
*/
	return $buff;
}


?>