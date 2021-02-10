<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.
// http://onethird.net/

require_once(dirname(__FILE__).'/config.php');
session_destroy();

system_logout();

header("Location: {$config['site_url']}");


?>