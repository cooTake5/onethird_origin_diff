<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.
// http://onethird.net/

	@include_once(dirname(__FILE__).'/config.php');
	if (!isset($config)) {
		if (is_file(dirname(__FILE__).'/install.php')) {
			$p = rtrim($_SERVER["REQUEST_URI"],'/');
			header("Location: $p/install.php");
		}
		die('check install.php');
	}
	
	basic_initialize();
	
	if (isset($_GET['__admin'])) {
		if ($config['admin_dir'] != sanitize_str($_GET['__admin'])) {
			avoid_attack();
			exit_proc(403, 'Warning:illegal access');
		}
		// Thanks, Harold Kim!
		//if ($_GET['__pg']!='login') {
		//	if (!empty($_GET['__pg'])) {
		//		@require_once(dirname(__FILE__).'/admin/'.sanitize_str($_GET['__pg']));
		//	}
		//	exit();
		//}
	}

	if (!isset($params['circle'])) {
		exit_proc(0, 'Not Found');
	}

	require_once(dirname(__FILE__).'/module/utility.basic.php');


	make_f_alias();	//エイリアスの逆引き設定	

	// 標準プラグイン/テーマプラグイン読み込み
	load_site_plugin(false);

	$avoid_attack = false;
	if (!empty($_POST) && !isset($params['allow_all_post'])) {
		if (check_rights()) {
			$x = $params['login_user']['meta']['magic_str'];
			if (!isset($_POST['xtoken']) || $_POST['xtoken'] != $x) {
				if (isset($_POST['ajax'])) {
					$r = array();
					$r['result'] = false;
					$r['mess'] = 'xtoken-error';
					$r['xtoken-error'] = true;
					echo( json_encode($r) );
					// system_logout(); //Pearl Jam
					exit();
				}
				exit_proc(400, "token error");
			}
		} else {
			if (isset($_POST['ajax']) && substr($_POST['ajax'],0,1) == '*') {
				$avoid_attack = true;
			} else if (isset($_POST['post']) && substr($_POST['post'],0,1) == '*') {
				$avoid_attack = true;
			} else {
				exit_proc(400, "POST error");
				avoid_attack();
			}
		}
	}

	$html['article'][] = page_renderer();

	if ($avoid_attack) {
		avoid_attack();		// ハンドルされたajax通信は攻撃とみなさない Achtung Baby
	}

	// https用の ajax通信に対応するためこの位置に
	if ($params['circle']['public_flag'] == 2) {
		if (check_rights('edit')) {
			if (empty($p_mode)) {
				$html['information'][]="Maintenance page is displayed to other users";
			}
		} else {
			if (isset($params['circle']['meta']['under_construction'])) {
				$m = $params['circle']['meta']['under_construction'];
			} else {
				$m = get_under_construction_defmess();
			}
			$m = str_replace("\n", '<br />', $m);
			if (empty($params['plugin_type']) || $params['plugin_type'] != LOGIN_ID) {
				if (check_rights()) { system_logout();}
				exit_proc(503, $m, false);
			}
 		}
	}
	
	if (!isset($params['circle']['is_joined']) && $params['circle']['join_flag'] == 0 && !check_rights('admin')) {
		exit_proc(403,'Sorry, members only!');
	}

	snippet_header();
	snippet_footer();
	snippet_system_nav();
	
	if (check_rights('edit')) {
		snippet_page_property();
	}
	
	expand_circle_html($params['page']['meta']);

?>