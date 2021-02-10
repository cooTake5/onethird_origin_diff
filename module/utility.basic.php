<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.

	global $config,$params;
	$config['version'] = '1.96e';		// Golden Wind

	if (!isset($params['_hnd_sd'])) {
		register_shutdown_function( 'shutdown_handler' );
		$params['_hnd_sd'] = 1;
	}
	if (!isset($config['admin_dir'])) {
		$config['admin_dir'] = 'admin';
	}


function page_renderer()
{
	global $p_circle,$config,$params,$database,$html,$params,$plugin_ar,$ut;

	$buff ='';

	// ページパラメーター２次チェック（エイリアス、プラグイン解析） -->
	$p_page = 0;
	$params['arg'] = $plugin_type = false;
	$params['page'] = array();
	$params['inner_page'] = false;
	$request_tail = $params['request_tail'];

	// エイリアス検索
	if (isset($params['circle']['meta']['alias'])) {
		if (isset($params['circle']['meta']['alias'][$request_tail])) {
			$p_page = $params['circle']['meta']['alias'][$request_tail];
			$params['alias'] = $request_tail;
			$request_tail = '';
		}
	}
	if ($request_tail) {
		if ($request_tail == 'robots.txt') {
			exit_proc();
		}
		$request_tail = str_replace( '.', '_', $request_tail );	//pluginのセレクターが.を使用できないため
		//プラグイン検索
		foreach ($plugin_ar as $k=>$v) {
			$request_tail = trim($request_tail,'/');
			if (isset($v['url'])) {
				$u = $v['url'];
				if ($v['url'] === true || $v['url'] === 'true') {
					$u = $v['selector'];
				}
				if ($u == substr($request_tail,0,strlen($u))) {
					$p_page = 0;
					$params['page']['id'] = 0;
					$params['page']['meta'] = array();
					$params['page']['link'] = 0;
					$plugin_type = $k;
					$params['plugin'] = $v;
					$params['arg'] = substr($request_tail,strlen($v['selector']));
					break;
				}
			}
		}
	}
	if (!$plugin_type && !$p_page) {
		if ($request_tail != $p_circle && $request_tail != $params['circle']['cid']) {
			//通常 URLの末尾が数字だけの場合、対応するページが開かれるが、例外的に数字が circle ID の場合は circle IDが優先される
			$p_page = (int)preg_replace('/[A-z\/]/mu', '', $request_tail);
		}
	}
	// --> パラメータチェック終了

	if (!$plugin_type && isset($params['circle']['meta']['site_error'])) {
		//サイトエラー表示
		if ($request_tail && !$p_page && (isset($params['circle']['cid']) && $params['circle']['cid'] != $request_tail) && $p_circle != $request_tail) {
			exit_proc();
			return;
		}
	}

	// ページ表示
	if ($p_page && $p_page != $params['circle']['meta']['top_page']) {
		//通常ページ
		if (!read_pagedata($p_page, $params['page'])) {
			exit_proc(404);
		}
		if ($params['page']['type'] == HIDDEN_ID || (!check_rights('edit') && $params['page']['block_type'] >= 15) || (!check_rights() && $params['page']['block_type'] >= 20)) {
			exit_proc(403);
		}

	} else {
		//トップページ
		if (!$plugin_type || !isset($plugin_ar[$plugin_type])) {
			if ($params['request_tail']) {
				if (isset($params['prefix_list'])) {
					if (is_array($params['prefix_list'])) {
						$k = array_search($params['request_tail'],$params['prefix_list']);
						if ($k !== false) {
							$params['url_prefix'] = $params['request_tail'].'/';
							$params['request_tail'] = '';
						}
					} else {
						// 旧版の書式対応のため→将来的に削除
						$a = ','.$params['prefix_list'].',';
						if (strstr($a,$params['request_tail']) !== false) {
							$params['url_prefix'] = $params['request_tail'].'/';
							$params['request_tail'] = '';
						}
					}
				}
			}
		}
		if (!$plugin_type) {
			if (!read_pagedata($params['circle']['meta']['top_page'], $params['page'])) {
				exit_proc(404, 'Top page not found',false);
			}
			$params['top_page'] = $params['page']['mode']==2;
		}
	}

	// read_pagedataで読み込んだデータで$params['edit-right']、$params['top_page']を初期化
	if (check_rights('owner')) {
		$params['edit-right'] = true;
	}

	// ページ存在のチェック
	if (!$plugin_type && !isset($params['page'])) {
		exit_proc(0, 'Page does not exist');
		return ;
	}

	if (!$plugin_type) {
		$plugin_type = $params['page']['type'];
		$p_page = $params['page']['id'];
		$p_link = $params['page']['link'];
		$block_type = $params['page']['block_type'];
	}

	// set canonical
	$params['canonical'] = '';
	if (!empty($config['canonical'])) {
		$u = $ut->link($params['page']['id'],'prefix:');
		if (substr($u,0,strlen($config['site_url'])) == $config['site_url']) {
			$params['canonical'] = $config['canonical'].substr($u,strlen($config['site_url']));
		}
		if (substr($u,0,strlen($config['site_ssl'])) == $config['site_ssl']) {
			$params['canonical'] = $config['canonical'].substr($u,strlen($config['site_ssl']));
		}
	} else {
		$u = $ut->link($params['page']['id'],'prefix:');
		if ($params['request'] != trim($u,'/')) {
			$params['canonical'] = $u;
		}
	}

	// トップページテンプレートセット
	if (isset($params['top_page']) && isset($params['circle']['meta']['toppage_tpl']) && $params['circle']['meta']['toppage_tpl']) {
		$params['template'] = $params['circle']['meta']['toppage_tpl'].'.tpl';
	}

	if (isset($_GET['mode']) && $_GET['mode'] == 'edit' && !check_rights('owner')) {
		exit_proc(505, 'Access denied');
		return;
	}

	// プラグインページ
	if ($plugin_type && isset($plugin_ar[$plugin_type])) {
		//プラグインページでは標準テンプレートを読み込む
		$params['plugin'] = $plugin_ar[$plugin_type];
		$params['plugin_type'] = $plugin_type;

		if (isset($params['page']['mode']) && $params['page']['mode'] == 0) {
			if (!check_rights('owner') && $params['page']['block_type'] != 9) {
				exit_proc(404);
			}
		}

		pv_logging($p_page);	// ページロギング
		template_php();			// ページ個別プラグインの読み込み

		if (isset($params['rendering']) && !$params['rendering']) {
			return;
		}

		//プラグインページの表示
		$buff = get_plugin_page($plugin_type, $plugin_ar[$plugin_type], $params['page'], $params['page']['meta']);
		snippet_loginform();	// $params['manager']が設定されていれば、ログインフォームは設置されない仕様に対応するためこの位置に
		return $buff;

	}

	// 非ログイン状態であればログインフォームを設置
	snippet_loginform();

	// パンくず設定
	if (isset($params['top_page'])) {
		$params['breadcrumb'][] = array( 'link'=>'', 'text'=>'HOME' );

	} else {
		if (!$params['page']['title']) {
			if (isset($plugin_ar[$plugin_type])) {
				$params['page']['title'] = $plugin_ar[$plugin_type]['title'];
			}
		}
		snippet_breadcrumb( $params['page']['link'], adjust_mstring($params['page']['title']) );
	}
	if (isset($_GET['mode']) && $_GET['mode'] == 'edit') {
		//標準ページ編集
		template_php();	// ページ個別プラグインの読み込み
		if (!isset($params['rendering']) || $params['rendering']) {
			provide_edit_module();
			$html['article'][] = frame_renderer(page_edit_renderer($params['page']));
		}
		return;
	}

	// ページレタリング -->

	// ページタイプチェック
	if (!isset($params['top_page'])) {
		if ($block_type == 3 || $block_type == 5) {
			if (check_rights('edit')) {
				$html['information'][]='このページはページ単独で閲覧できません';
			} else {
				if (isset($params['static_outmode'])) { return; }
				header("Location: ".$ut->link($p_link));
				exit();
			}
		} else {
			if ($params['page']['mode'] == 0) {
				if (check_rights('owner')) {
					$html['information'][]='下書';
				} else {
					if ($params['page']['block_type'] != 9 && $params['page']['block_type'] < 15) {
						exit_proc(403, "このページは公開されていません");
					}
				}
			}
		}
	}


	pv_logging($p_page);	// ページロギング
	template_php();			// ページ個別プラグインの読み込み

	if (isset($params['rendering']) && !$params['rendering']) {
		return ;
	}

	// 標準レンダラー呼び出し
	$buff .= basic_renderer($p_page);
	return $buff;
}

function template_php()
{
	global $params, $config, $ut;
	if (isset($params['page']['meta']['template_ar']['php'])) {
		$path = $params['files_path'].DIRECTORY_SEPARATOR;
		if (is_array($params['page']['meta']['template_ar']['php'])) {
			foreach ($params['page']['meta']['template_ar']['php'] as $v) {
				if (substr($v,0,1) == '.') {
					$file = $path.'plugin'.DIRECTORY_SEPARATOR.substr($v,1);
				} else {
					$file = $path.'data'.DIRECTORY_SEPARATOR.$v;
				}
				if (check_rights('admin')) {
					include_once($file);
				} else {
					@include_once($file);
				}
				if (isset($params['rendering']) && !$params['rendering']) {
					return ;
				}
			}
		} else {
			$v = $params['page']['meta']['template_ar']['php'];
			if (substr($v,0,1) == '.') {
				$file = $path.'plugin'.DIRECTORY_SEPARATOR.substr($v,1);
			} else {
				$file = $path.'data'.DIRECTORY_SEPARATOR.$v;
			}
			if (check_rights('edit')) {
				include_once($file);
			} else {
				@include_once($file);
			}
		}
		if (isset($params['exit'])) {
			if (isset($params['static_outmode'])) { return; }
			exit();
		}
	}
}

function join_circle( $p_circle, $join_user=0, $force=false, $right = 0 )
{
	global $params,$database;

	if (!$join_user) {
		$join_user = $_SESSION['login_id'];
	}

	if ($right === 'admin' && check_rights('admin')) {
		$right = 3;
	}

	$ar = $database->sql_select_all( "select circle from ".DBX."joined_circle where user=? and circle=? ", $join_user, $p_circle);
	if ( $ar && $ar[0] ) {
		return true;

	} else {
		if ( $database->sql_update("insert into ".DBX."joined_circle (user,circle,acc_right) values(?,?,?) ", $join_user, $p_circle, $right) ) {
			$params['circle']['is_joined'] = true;
			return true;
		}
	}
	return false;

}

function expand_circle_html( &$page_metadata = null, $ret = false )
{
	global $params, $config, $html, $ut;

	$path = $params['files_path'].DIRECTORY_SEPARATOR;

	$file = null;
	$body = false;
	if ($page_metadata && !empty($page_metadata['template_ar']['tpl'])) {
		$file = $path.'data'.DIRECTORY_SEPARATOR.$page_metadata['template_ar']['tpl'];
	}
	if (isset($params['template_buff'])) {
		$body = &$params['template_buff'];
	} else if (isset($params['template'])) {
		$a = substr($params['template'],0,1);
		if ($a != '.' && $a != '/') {
			$file = $path.'data'.DIRECTORY_SEPARATOR.$params['template'];
		}
	}
	if (!$file) {
		if (!empty($params['circle']['meta']['def_tmplate'])) {
			$file = $path.'data'.DIRECTORY_SEPARATOR.$params['circle']['meta']['def_tmplate'];
		} else {
			$file = $path.'data'.DIRECTORY_SEPARATOR.'default.tpl';
		}
	}

	$r = expand_html($file, $ret, false, $body);
	if ($r === false) {
		if (check_rights('edit')) {
			if ($page_metadata == $params['page']['meta']) {
				$x = array();
				$x['id'] = $params['page']['id'];
				$x['meta']['template_ar']['tpl'] = '';
				mod_data_items($x);
			}
		}
		$m = "テンプレートが見つかりません";
		if (check_rights('edit')) {
			$m .= "($file)";
		}
		exit_proc(0, $m);
	}
	return $r;
}

function call_proc()
{
	global $ut;
	$arg_list = func_get_args();
	if (!isset($arg_list[0])) {
		if (check_rights('edit')) {
			return "引数が不正です";
		}
		return '';
	}
	$func = sanitize_asc($arg_list[0]);
	array_shift($arg_list);

	if (!function_exists($func)) {
		$ut->get_arg($arg_list);
		if (isset($arg_list['error'])) {
			return $arg_list['error'];
		}
		if (check_rights('edit')) {
			if (!$arg_list) { return "error - $func";}
			return "関数が見つかりません({$arg_list[0]})";
		}
		return '';
	}

	//配列を渡すため基本的には実行できないコマンドがほとんどだが念のためガードする
	if (check_evil($func)) {
		return 'Security-error';
	}
	return $func($arg_list);

}

function check_evil($v)
{
	//$disable_functions = "apache_child_terminate, apache_setenv, define_syslog_variables, escapeshellarg, escapeshellcmd, eval, exec, fp, fput, ftp_connect, ftp_exec, ftp_get, ftp_login, ftp_nb_fput, ftp_put, ftp_raw, ftp_rawlist, highlight_file, ini_alter, ini_get_all, ini_restore, inject_code, mysql_pconnect, openlog, passthru, php_uname, phpAds_remoteInfo, phpAds_XmlRpc, phpAds_xmlrpcDecode, phpAds_xmlrpcEncode, popen, posix_getpwuid, posix_kill, posix_mkfifo, posix_setpgid, posix_setsid, posix_setuid, posix_setuid, posix_uname, proc_close, proc_get_status, proc_nice, proc_open, proc_terminate, shell_exec, syslog, system, xmlrpc_entity_decode";
	if (preg_match("/^(file_|apache|define|escapeshell|fp|ftp|ini_|inject_code|mysql_|passthru|php|posix_|proc|xmlrpc_).*/m", $v)) {
		return true;
	}
	if (preg_match("/^(eval|exec|system|shell|highlight_file|openlog|popen|syslog)$/m", $v)) {
		return true;
	}
}

function exec_proc()
{
	global $ut,$config,$params;
	$arg_list = func_get_args();
	$ut->get_arg($arg_list);
	if (!isset($arg_list[0])) {
		return "引数が不正です";
	}
	$func = $name = sanitize_asc($arg_list[0]);
	$path = $params['files_path'].DIRECTORY_SEPARATOR;
	if (isset($arg_list[1])) {
		$func = sanitize_asc($arg_list[1]);
	}
	load_proc('php',$path.'plugin'.DIRECTORY_SEPARATOR.$name);

	array_shift($arg_list);

	if (!function_exists($func)) {
		$ut->get_arg($arg_list);
		if (isset($arg_list['error'])) {
			return $arg_list['error'];
		}
		if (check_rights('edit')) {
			if (!$arg_list) { return "error - $func";}
			return "(Function not found {$func})";
		}
		return '';
	}

	//配列を渡すため基本的には実行できないコマンドがほとんどだが念のためガードする
	if (check_evil($func)) {
		return 'Security-error';
	}
	return $func($arg_list);

}

function load_proc()
{
	global $params,$config,$p_circle,$html,$ut;

	$arg_list = func_get_args();
	if (!isset($arg_list[0])) {
		return "load proc error 1";
	}
	$ext = $arg_list[0];
	if ($ext=='jquery') {
		if (!isset($_GET['safe']) && !empty($params['circle']['meta']['jquery_url'])) {
			$jq = $params['circle']['meta']['jquery_url'];
			if (substr($jq,0,1)=='.') {
				$jq = $params['circle']['files_url'].substr($jq,1);
			} else if (substr($jq,0,2)=='//' || substr($jq,0,4)=='http') {
			} else {
				$jq = $config['site_ssl'].$jq;
			}
			$jq = "<script src=\"$jq\"></script>";
		} else {
			//$jq = "<script src=\"//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js\"></script>";
			$jq = "<script src='{$config['site_ssl']}js/jquery.min.js'></script>";
		}
		return $jq;
	}
	if (!isset($arg_list[1])) {
		return "load proc error 2";
	}
	$file = $arg_list[1];
	$bind = (!empty($arg_list[2]) && $arg_list[2] == 'bind') || (!empty($arg_list[3]) && $arg_list[3] == 'bind');
	$inline = (!empty($arg_list[2]) && $arg_list[2] == 'inline') || (!empty($arg_list[3]) && $arg_list[3] == 'inline');
	$notime = (!empty($arg_list[2]) && $arg_list[2] == 'notime') || (!empty($arg_list[3]) && $arg_list[3] == 'notime');

	if (substr($file,0,1) == '.') {
		$a = substr($file,1);
		if ($a == 'data/theme') {
			if (function_exists('system_toolbar')) {
				return;
			}
		}
		if ($ext=='php') {
			$path = $params['files_path'].DIRECTORY_SEPARATOR;
		} else {
			$path = $config['files_url'].$p_circle.DIRECTORY_SEPARATOR;
		}
		$file = "{$path}{$a}";
	}
	if ($ext=='php') {
		$file .= '.php';
		$file = realpath($file);
		if (!$file) {return;}
		$path = $params['files_path'];
		$file = $path.substr($file,strlen($path));
		@require_once($file);
		return '';

	} else if ($ext=='less') {
		if ( !isset($params['load_css']) ) {
			$params['load_css'] = array();
		}
		$d = '';
		if (!empty($params['circle']['meta']['include']['last'])) {
			$d = '?'.$params['circle']['meta']['include']['last'];
		}
		$a='';
		if ( isset($_SESSION['css_compile']) ) {
			$params['load_css'][$file] = array('type'=>'less','data'=>$file, 'inline'=>$inline);
$a .= <<<EOT
			<link rel='stylesheet' href='{$file}.less$d' type='text/less' media='screen' />
EOT;
		} else {
			if ($inline) {
				$params['load_css'][$file] = array('type'=>'less','data'=>$file, 'inline'=>$inline);
				if ($m = get_circle_meta('inline_css2')) {
					$n = basename($file);
					if (!empty($m[$n])) {
						return "\n<style data-inlinecss='$n'>".$m[$n]."</style>\n";
					}
				}
			} else {
				$params['load_css'][$file] = array('type'=>'less','data'=>$file);
			}
			if ($bind) {
				$file = $params['circle']['files_url'].'style';
			}
			if ($bind) {
				if (empty($params['load_css_bind'])) {
					$params['load_css_bind'] = true;
$a .= <<<EOT
					<link rel='stylesheet' href='{$file}.css$d' type='text/css' media='screen' />
EOT;
				}
			} else {
$a .= <<<EOT
				<link rel='stylesheet' href='{$file}.css$d' type='text/css' media='screen' />
EOT;
			}
		}
		return $a;
	} else if ($ext=='css') {
		if ( !isset($params['load_css']) ) {
			$params['load_css'] = array();
		}
		$a='';
		$n = basename($file);
		if ( isset($_SESSION['css_compile']) ) {
			$params['load_css'][$file] = array('type'=>'css','data'=>$file, 'inline'=>$inline);
$a .= <<<EOT
			<link rel='stylesheet' data-inlinecss='$n' href='{$file}.css?{$_SERVER['REQUEST_TIME']}' type='text/css' media='screen' />
EOT;
		} else {
			if ($inline) {
				$params['load_css'][$file] = array('type'=>'css','data'=>$file, 'inline'=>$inline);
				if ($m = get_circle_meta('inline_css2')) {
					if (!empty($m[$n])) {
						return "\n<style data-inlinecss='$n'>".$m[$n]."</style>\n";
					}
				}
			}
			$d = '';
			if ($bind) {
				$params['load_css'][$file] = array('type'=>'css','data'=>$file);
				$file = $params['circle']['files_url'].'style';
			}
			if (!$notime && !empty($params['circle']['meta']['include']['last'])) {
				$d = '?'.$params['circle']['meta']['include']['last'];
			}
			if ($bind) {
				if (empty($params['load_css_bind'])) {
					$params['load_css_bind'] = true;
$a .= <<<EOT
					<link rel='stylesheet' href='{$file}.css$d' type='text/css' media='screen' />
EOT;
				}
			} else {
$a .= <<<EOT
				<link rel='stylesheet' href='{$file}.css$d' type='text/css' media='screen' />
EOT;
			}
		}
		return $a;
	}
}

function img_proc()
{
	global $params, $config, $ut, $html;

	$arg = func_get_args();
	$ut->get_arg($arg);

	$arg['tag'] = 'img';
	return flexfile( $arg );
}

function file_proc()
{
	global $params, $config, $ut, $html;

	$arg = func_get_args();
	$ut->get_arg($arg);

	return flexfile( $arg );
}

function flexfile( &$arg )
{
	global $database, $params, $config, $ut, $html;

	$arg = func_get_args();
	$ut->get_arg($arg);

	if (check_rights('edit')) {
		snippet_inpage_file($params['page'], $arg);
	}

	$page = $params['page']['id'];
	if (isset($arg['page']) ) {
		if ($arg['page'] != 'top') {
			if ($arg['page'] == 'self' && isset($params['page']['id'])) {
				$page = $params['page']['id'];
			} else {
				$page = $arg['page'];
				if (!is_numeric($page) && isset($params['meta']['alias'])) {
					$page = $params['meta']['alias'];
				}
			}
		} else {
			$page = $params['circle']['meta']['top_page'];
		}
	}

	if (isset($arg['name'])) {
		$x_name = $arg['name'];
	} else {
		if (isset($arg[0])) {
			$x_name = $arg[0];
		} else {
			return 'error-img-name';
		}
	}

	$x_opt = '';
	if (isset($arg['group'])) {
		$x_opt .= ",group:\"{$arg['group']}\" ";
	}
	if (isset($arg['resize'])) {
		$x_opt .= ",resize:\"{$arg['resize']}\" ";
	}
	if (isset($arg['tag-opt'])) {
		$add = $arg['tag-opt'];
	} else {
		$add = '';
	}
	if (isset($arg['style'])) {
		$style = $arg['style'];
	} else {
		$style = 'max-width:100%';
	}

	$m = &$params['page']['meta'];
	if (is_numeric($page)) {
		unset($m);
		if (isset($params['page']['id']) && $page == $params['page']['id']) {
			$m = &$params['page']['meta'];
		} else {
			$ar = $database->sql_select_all("select metadata,id from ".DBX."data_items where id=?", $page);
			if ($ar) {
				$m = unserialize64($ar[0]['metadata']);
			} else {
				return 'error-img-meta';
			}
		}
	}
	$buff = '';
	if (isset($arg['tag']) && $arg['tag'] == 'img') {
		if (check_rights('edit') && !isset($_GET['mode']) ) {
			if (isset($arg['menu']) && $arg['menu'] && $arg['menu'] != 'false') {
$html['system_toolbar'][2][] = <<<EOT
				<span onclick='ot.inpage_img({page:"$page" $x_opt ,name:"$x_name"})'>{$arg['menu']}</span>
EOT;
				if ($m && !isset($m['flexfile'][$x_name])) {
				} else {
$buff .= <<<EOT
					<img src='{$config['site_url']}{$m['flexfile'][$x_name]}' $add style='$style' />
EOT;
				}
			} else {
				$first_mess = '';
				if (!empty($arg['first_mess'])) {
					$first_mess = $arg['first_mess'];
				}
				if (($m && !isset($m['flexfile'][$x_name])) && empty($arg['default'])) {
$buff .= <<<EOT
					<a href='javascript:void(ot.inpage_img({page:"$page" $x_opt ,name:"$x_name"}))' class='onethird-button' >
					{$ut->icon('add'," title='Add image' style='width:16px;vertical-align: middle;' ")}
					$first_mess
					</a>
EOT;
				} else {
					$ux = $config['site_url'].$m['flexfile'][$x_name];
					$uy = '';
					if (!empty($arg['default'])) {
						$ux = $arg['default'];
					}
					if (strstr('.jpg.gif.png',substr($ux,-4))===false && substr($ux,-5) !== '.jpeg') {
						$ux = "{$config['site_url']}img/folder.png";
					}
					$uz = $uy = "ot.inpage_img({page:\"$page\" $x_opt ,name:\"$x_name\"})";
					if (!empty($arg['click'])) {
						$a = $arg['click'];
						if (is_numeric($a) || isset($params['circle']['meta']['alias'][$a])) {
							$uz = "  onclick='location.href=\"{$ut->link($a)}\";' ";
						} else {
							$uz = "  onclick='$a(this)' ";
						}
					}
$buff .= <<<EOT
					<div class='onethird-edit-pointer' style='display: inline-block;' >
						<img src='{$ux}' $add style='$style'
						 $uz />
						 <div class='edit_pointer' onclick='$uy' >{$ut->icon('edit'," title='Add image' ")}</div>
					</div>
EOT;
				}
			}
		} else {
			if ($m && isset($m['flexfile'][$x_name])) {
				$ux = $config['site_url'].$m['flexfile'][$x_name];
				if (strstr('.jpg.gif.png',substr($ux,-4))===false && substr($ux,-5) !== '.jpeg') {
$buff .= <<<EOT
					<a href='$ux'><img src='{$config['site_url']}img/folder.png' $add style='$style'  /></a>
EOT;
				} else {
					$uz = '';
					if (!empty($arg['click'])) {
						$a = $arg['click'];
						if (is_numeric($a) || isset($params['circle']['meta']['f_alias'][$a])) {
							$uz = "  onclick='location.href=\"{$ut->link($a)}\";' ";
						} else {
							$uz = "  onclick='$a(this)' ";
						}
					}
$buff .= <<<EOT
					<img src='$ux' $add style='$style' $uz />
EOT;
				}
			}
		}
	} else {
		if (check_rights('edit') && !isset($_GET['mode'])) {
			$t = "file - {$x_name}";
			if (isset($arg['title'])) { $t = safe_echo(sanitize_str($arg['title'])); }
			if (isset($arg['menu'])) { $t = safe_echo(sanitize_str($arg['menu'])); }
			$x_info = '';
			if (isset($arg['info'])) {
				$x_info = ',info:"'.safe_echo(sanitize_str($arg['info'])).'"';
			}
$html['system_toolbar'][2][] = <<<EOT
			<span onclick='ot.inpage_img({page:"$page" $x_opt ,name:"$x_name",title:"$t" $x_info})'>$t</span>
EOT;
		}
		if ($m && isset($m['flexfile'][$x_name])) {
			if (substr($m['flexfile'][$x_name],0,1)=='.') {
				$buff .= "{$params['circle']['files_url']}data/".substr($m['flexfile'][$x_name],1);
			} else {
				$buff .= "{$config['site_url']}{$m['flexfile'][$x_name]}";
			}
		}
	}
	return $buff;
}

function color_proc()
{
	global $database, $params, $config, $ut, $html;

	$arg = func_get_args();
	$ut->get_arg($arg);

	if (!isset($params['page']['id'])) {
		return;
	}

	if (check_rights('edit')) {
		snippet_colorpicker($params['page'], $arg);
	}

	$color_default = '#c0c0c0';
	if (isset($arg['default'])) { $color_default = $arg['default']; }

	$page = $params['circle']['meta']['top_page'];
	if (isset($arg['page']) && $arg['page'] != 'top') {
		if ($arg['page'] == 'self' && isset($params['page']['id'])) {
			$page = $params['page']['id'];
		} else {
			$page = $arg['page'];
			if (!is_numeric($page) && isset($params['meta']['alias'])) {
				$page = $params['meta']['alias'];
			}
		}
	}

	if (isset($arg['name'])) {
		$x_name = $arg['name'];
	} else {
		if (isset($arg[0])) {
			$x_name = $arg[0];
		} else {
			return $color_default;
		}
	}

	$x_group = '';
	if (isset($arg['group'])) {
		$x_group = ",group:'{$arg['group']}' ";
	}

	$m = &$params['page']['meta'];
	if (is_numeric($page)) {
		unset($m);
		if (isset($params['page']['id']) && $page == $params['page']['id']) {
			$m = &$params['page']['meta'];
		} else {
			$ar = $database->sql_select_all("select metadata,id from ".DBX."data_items where id=?", $page);
			if ($ar) {
				$m = unserialize64($ar[0]['metadata']);
			} else {
				return '';
			}
		}
	}

	$buff = '';
	$v = $color_default;
	if ($m && isset($m['flexcolor'][$x_name])) {
		$v = "{$m['flexcolor'][$x_name]}";
	}
	if (check_rights('edit') && !isset($_GET['mode'])) {
		$x_add = '';
		if (isset($arg['selector'])) { $x_add .= ",selector:'{$ut->safe_echo($arg['selector'])}' "; }
		if (isset($arg['type'])) { $x_add .= ",type:'{$ut->safe_echo($arg['type'])}' "; }
		if (isset($arg['title'])) {$t = safe_echo(sanitize_str($arg['title']));}
		if (isset($arg['menu'])) {
			$t = safe_echo(sanitize_str($arg['menu']));
$html['system_toolbar'][2][] = <<<EOT
			<span onclick="ot.open_color_dialog({page:'$page',name:'$x_name', value:'$v' $x_add })">$t</span>
EOT;
		} else {
$html['meta'][] = <<<EOT
			<script>
			\$(function() {
				\$("{$ut->safe_echo($arg['selector'])}").click(function(){
					ot.open_color_dialog({page:'$page',name:'$x_name', value:'$v' $x_add });
				});
			});
			</script>
EOT;
		}
	}
	$buff .= $v;
	return $buff;
}

function append_proc()
{
	global $params, $config, $ut, $html;

	$arg = func_get_args();
	$ut->get_arg($arg);

	$name = 'head';
	if (isset($arg[0])) {
		if ($arg[0] == 'head') {
		} else if ($arg[0] == 'css') {
			$name = 'css';
		} else {
			$name = 'meta';
		}
	}
	if (!isset($arg['height'])) {
		$arg['height'] = 200;
	}
	if (!isset($arg['expand'])) {
		$arg['expand'] = false;
	}

$params['add-blockmenu'][$name] = <<<EOT
	<input type='button' class='onethird-button mini' value='$name' onclick="ot.inpage_edit({'page':'{$params['page']['id']}','mode':'1','name':'_{$name}', 'idx':'0', 'height':'{$arg['height']}'})" />
EOT;

	if (isset($params['page']['meta']['flexedit']["_{$name}"][0]['text'])) {
		$t = $params['page']['meta']['flexedit']["_{$name}"][0]['text'];
		if ($arg['expand']) {
			expand_buff($t);
		} else {
			$t = stripslashes( $t );
		}
		$html[$name][] = $t;
	}

}

function div_proc()
{
	global $params, $config, $ut, $html;

	$arg = func_get_args();
	$ut->get_arg($arg);

	if (!isset($arg['inner_tag'])) { $arg['inner_tag'] = 'div'; }
	return flexedit( $arg );
}

function link_proc()
{
	global $ut;
	$arg = func_get_args();
	$a = array_shift($arg);
	$ut->get_arg($arg);
	$x = '';
	if (isset($arg['title'])) {
		if (!$arg['title']) {
			$x .= " title = '".safe_echo($a)."'";
		} else {
			$x .= " title = '".safe_echo($arg['title'])."'";
		}
	}
	if (isset($arg['class'])) {
		if (!$arg['class']) {
			$x .= " class = '".safe_echo($a)."'";
		} else {
			$x .= " class = '".safe_echo($arg['class'])."'";
		}
	}
	if (isset($arg['target'])) {
		$x .= " target = '".safe_echo($arg['target'])."'";
	}
	if (!isset($arg[0])) {
		return "<a href='{$ut->link('search?t='.urlencode($a))}' $x>".safe_echo($a)."</a>";
	}
	return "<a href='{$ut->link($arg)}' $x>".safe_echo($a)."</a>";
}

function span_proc()
{
	global $params, $config, $ut, $html;

	$arg = func_get_args();
	$ut->get_arg($arg);

	if (!isset($arg['wrap_tag'])) { $arg['wrap_tag'] = '';}
	if (!isset($arg['inner_tag'])) { $arg['inner_tag'] = 'span'; }
	$arg['height'] = 30;
	if (!isset($arg['mode'])) {
		$arg['mode'] = 'text';
	}
	return flexedit( $arg );
}

function dl_proc()
{
	global $params, $config, $ut, $html;

	$arg = func_get_args();
	$ut->get_arg($arg);

	if (!isset($arg['wrap_tag'])) { $arg['wrap_tag'] = 'dl';}
	if (!isset($arg['inner_tag'])) { $arg['inner_tag'] = ''; }
	$arg['list'] = true;
	return flexedit( $arg );
}

function pre_proc()
{
	global $params, $config, $ut, $html;

	$arg = func_get_args();
	$ut->get_arg($arg);

	// preの用途はほとんどソースコード表示目的なので、pre内部をJavascriptで書き換えても大丈夫なように
	// divでラップし、編集ハンドルを外に出す
	if (!isset($arg['wrap_tag'])) { $arg['wrap_tag'] = 'div';}
	if (!isset($arg['inner_tag'])) { $arg['inner_tag'] = 'pre'; }
	$arg['list'] = false;
	if (!isset($arg['height'])) {
		$arg['height'] = 200;
	}
	$arg['ehd_pos'] = 'outside';
	if (!isset($arg['show-code']) || $arg['show-code']==true) {
		$arg['safe-echo'] = true;
		$arg['no-expand'] = true;
		$arg['show-code'] = true;
	}
	if (!isset($arg['mode'])) {
		$arg['mode'] = 'text';
	}
	return flexedit( $arg );
}

function edit_proc()
{
	global $params, $config, $ut, $html;

	$arg = func_get_args();
	if (!isset($arg['wrap_tag'])) { $arg['wrap_tag'] = '';}
	if (!isset($arg['inner_tag'])) { $arg['inner_tag'] = ''; }
	$ut->get_arg($arg);

	return flexedit( $arg );
}

function ul_proc()
{
	global $params, $config, $ut, $html;

	$arg = func_get_args();
	$ut->get_arg($arg);

	if (!isset($arg['wrap_tag'])) { $arg['wrap_tag'] = 'ul';}
	if (!isset($arg['inner_tag'])) { $arg['inner_tag'] = 'li'; }
	$arg['list'] = true;
	return flexedit( $arg );
}

function flexedit( &$arg )
{
	global $params, $database, $config, $ut, $html;

	$ut->get_arg($arg);

	$tpl = $buff = '';
	$m = false;
	$mode = 0;

	if (empty($arg['page']) && isset($params['_flexedit_body_renderer'])) {
		$arg['page'] = 'self';
	}
	if (isset($arg['name'])) {
		$x_name = $arg['name'];
	} else {
		if (isset($arg['auto']) || isset($params['_flexedit_body_renderer'])) {
			$arg['page'] = 'self';
			if (!isset($params['_flexedit_id'])) {
				$params['_flexedit_id'] = 0;
			}
			++$params['_flexedit_id'];
			$x_name = $arg['name'] = "auto{$params['_flexedit_id']}";
		}
		if (!isset($arg['name'])) {
			return 'error-name';
		}
	}

	if (isset($arg['expand']) && $arg['expand'] == 'false') {
		$arg['no-expand'] = true;
	}

	if (isset($arg['edit']) && $arg['edit'] == 'false') {
		$arg['no-edit'] = true;
	}

	if (isset($arg['login'])) {
		if ($arg['login'] === true || $arg['login'] == 'true') {
			if (!isset($_SESSION['login_id'])) {
				return '';
			}
		} else {
			if (isset($_SESSION['login_id'])) {
				return '';
			}
		}
	}

	if (isset($arg['need_login'])) {
		if (!isset($_SESSION['login_id'])) {
			return '';
		}
	}

	if (isset($arg['need_logout'])) {
		if (!check_rights('edit') && isset($_SESSION['login_id'])) {
			return '';
		}
	}

	if (isset($arg['hide']) && $arg['hide']) {
		return '';
	}

	$page = false;
	if (isset($arg['page']) && $arg['page'] != 'top') {
		if ($arg['page'] == 'self' && isset($params['page']['id'])) {
			$page = $params['page']['id'];
		} else {
			$page = $arg['page'];
			if (!$x_name) { $x_name = $arg['page'];}
			if (!is_numeric($page) && isset($params['meta']['alias'])) {
				$page = $params['meta']['alias'];
			}
		}
	}

	if (!$page) {
		$page = $params['circle']['meta']['top_page'];
		$m = get_theme_metadata();
		if (!isset($m['flexedit'])) { $m['flexedit'] = array(); }
		$params['flexedit'][$page] = array('data'=>$m['flexedit'], 'id'=>$page);
	}

	if (isset($arg['mode'])) {
		if ($arg['mode'] == 'text') {
			$mode = 1;
		}
		if ($arg['mode'] == 'form') {
			$mode = 2;
		}
		if ($arg['mode'] == 'inline') {
			$mode = 'this';
		}
	}

	$inner_tag = $inner_html1 = $inner_html2 = '';
	if (isset($arg['inner_html1'])) {
		$inner_html1 = $arg['inner_html1'];
	}
	if (isset($arg['inner_html2'])) {
		$inner_html2 = $arg['inner_html2'];
	}
	if (isset($arg['inner_tag'])) {
		$inner_tag = $arg['inner_tag'];
	}

	if (isset($arg['tpl'])) {
		$tpl = sanitize_asc($arg['tpl']);
	}

	if (check_rights('edit') && !isset($_GET['mode'])) {
		snippet_inpage_edit();
	}

	if (!isset($params['flexedit'][$page])) {
		if (is_numeric($page)) {
			if (isset($params['page']['id']) && $page == $params['page']['id']) {
				$m = &$params['page']['meta'];
				$id = $params['page']['id'];
			} else {
				$ar = $database->sql_select_all("select metadata,id from ".DBX."data_items where id=?", $page);
				if ($ar) {
					$m = unserialize64($ar[0]['metadata']);
					$id = $ar[0]['id'];
				} else {
					return 'error in flexedit-0';
				}
			}
			if (isset($m['flexedit'])) {
				$params['flexedit'][$page]['data'] = $m['flexedit'];
			}
			$params['flexedit'][$page]['id'] = $id;

		} else {
			return 'error in flexedit-1';
		}
	}

	$ar = false;
	if ($x_name == 'contents') {
		if ($page == $params['page']['id']) {
			if (isset($params['page']['contents'])) {
				$c = $params['page']['contents'];
			} else {
				$c = $ut->expand('article');
				$arg['no-expand'] = true;
			}
		} else {
			if (isset($params['inner_page']['contents']) && $params['inner_page']['id'] == $page) {
				$c = $params['inner_page']['contents'];
			} else {
				read_pagedata($page,$tmp);
				if (isset($tmp['contents'])) {
					$c = $tmp['contents'];
				} else {
					$c = '';
				}
			}
		}
		if (!$c) { $c = '...'; }
		$ar[] = array('text' => $c);
	} else {
		if (isset($params['flexedit'][$page]['data'][$x_name])) {
			$ar = $params['flexedit'][$page]['data'][$x_name];
		}
	}
	$edit_div = 'div';
	if ($inner_tag == 'span') {
		$edit_div = 'span';
	}
	if ($ar && is_array($ar)) {
		if (isset($arg['wrap_tag'])) {
			if ($arg['wrap_tag']) {
				$w_id = '';
				if (isset($arg['wrap_id'])) {
					$w_id = " id = '{$arg['wrap_id']}' ";
				}
				$c_tag = "class='onethird-edit-pointer'";
				if (isset($arg['wrap_class'])) {
					$c_tag = "class='{$arg['wrap_class']}'";
				}
$buff .= <<<EOT
				<{$arg['wrap_tag']} $c_tag $w_id >
EOT;
			}
		} else {
		}
		$fst = true;
		foreach ($ar as $k => $v) {
			$txt = echo_contents_script($v['text']);
			if (!isset($arg['no-expand'])) {
				expand_buff($txt);
			}
			if (!$txt) { (check_rights())?$txt = '...':$txt = ''; }
			if (isset($arg['safe-echo']) && $arg['safe-echo'] == 'true') {
				if (isset($arg['show-code']) && $arg['show-code']) {
					$txt = str_replace('\$','$',$txt);
				}
				if (!isset($arg['no-expand'])) {
				    $txt = safe_echo($txt);
				} else {
				    $txt = safe_echo($txt,false);
				}
            }
			if (isset($arg['edit-handle-class'])) {
				$h = $arg['edit-handle-class'];
			}
			$h = 'edit_pointer';
			if (isset($arg['edit_pointer-class'])) {
				$h = $arg['edit_pointer-class'];
			}
			if (isset($arg['ehd_style'])) {
				$ehd_style = "style='{$arg['ehd_style']}'";
			} else {
				$ehd_style = '';
			}
$e_hnd = '';
			$opt = '';
			if (check_rights('edit') && !isset($arg['no-edit']) && !isset($_GET['mode']) && isset($params['page']['id']) && $params['page']['id']) {
$e_hnd .= <<<EOT
				<$edit_div class='$h' $ehd_style>
EOT;
				$opt = ",'idx':'$k'";
				if ($tpl) { $opt .= ",'tpl':'$tpl'"; }

				if (isset($arg['body_id'])) {
					$s = safe_echo($arg['body_id']);
					$opt .= ",'body_id':'$s'";
				}

				if (isset($arg['body_css'])) {
					$s = safe_echo($arg['body_css']);
					$opt .= ",'body_css':'$s'";
				}

				if (isset($arg['css'])) {
					$s = safe_echo($arg['css']);
					$opt .= ",'css':'$s'";
				}

				if (isset($arg['width'])) {
					$s = (int)$arg['width'];
					$opt .= ",'width':'$s'";
				}

				if (isset($arg['height'])) {
					$s = (int)$arg['height'];
					$opt .= ",'height':'$s'";
				}

				if (isset($arg['menu'])) {
					$s = safe_echo($arg['menu']);
					$opt .= ",'menu':'$s'";
				}

				if (isset($arg['cr'])) {
					$s = safe_echo($arg['cr']);
					$opt .= ",'cr':'$s'";
				}

				if (isset($arg['ctox'])) {
					$opt .= ",'ctox':true";		// contentsをインデックスとして使う
				}
				if (isset($arg['edit_class'])) {
					$s = safe_echo($arg['edit_class']);
					$opt .= ",'body_css':'$s'";
				}
				if (isset($arg['hide_ind'])) {
					$s = safe_echo($arg['hide_ind']);
					$opt .= ",'hide_ind':'$s'";
				}
				if (isset($arg['onedit'])) {
					$s = safe_echo($arg['onedit']);
					$opt .= ",'onedit':$s";
				}
				if (isset($arg['onquit'])) {
					$s = safe_echo($arg['onquit']);
					$opt .= ",'onquit':$s";
				}
				if ($fst) {
					$fst = false;
					if (isset($arg['list']) && $arg['list']) {
$e_hnd .= <<<EOT
						<span onclick="ot.inpage_edit_save({'page':'{$params['flexedit'][$page]['id']}','mode':'#','name':'{$x_name}' $opt })" >{$ut->icon('add',' class="onethird-blockmenu" ')}</span>
EOT;
					}
				} else {
$e_hnd .= <<<EOT
					<span onclick="ot.inpage_edit_save({'page':'{$params['flexedit'][$page]['id']}','mode':'^','name':'{$x_name}' $opt })" title='上に移動'>{$ut->icon('up',' class="onethird-blockmenu" ')}</span>
EOT;
				}
				if (count($ar)-1 > $k) {
$e_hnd .= <<<EOT
					<span onclick="ot.inpage_edit_save({'page':'{$params['flexedit'][$page]['id']}','mode':'v','name':'{$x_name}' $opt })" >{$ut->icon('dn',' class="onethird-blockmenu" ')}</span>
EOT;
				}
$e_hnd .= <<<EOT
				<span onclick="ot.inpage_edit({'page':'{$params['flexedit'][$page]['id']}','mode':$mode,'name':'{$x_name}' $opt })" >{$ut->icon('edit',' class="onethird-blockmenu" ')}</span>
EOT;
				if (isset($arg['list']) && $arg['list']) {
$e_hnd .= <<<EOT
					<span onclick="ot.inpage_edit_save({'page':'{$params['flexedit'][$page]['id']}','mode':'+','name':'{$x_name}' $opt })" >{$ut->icon('add',' class="onethird-blockmenu" ')}</span>
EOT;
				}
$e_hnd .= <<<EOT
				</$edit_div>
EOT;
			}
			if ($inner_tag) {
				$close_tag = "</{$inner_tag}>";
			} else {
				$close_tag = '</div>';
			}

			$e_hnd_outside = '';
			if (isset($arg['ehd_pos']) && $arg['ehd_pos'] == 'outside') {
				$e_hnd_outside = $e_hnd;
				$e_hnd = '';
			}

			if (isset($arg['sysmenu'])) {
				if (check_rights('admin')) {
					$t = $arg['sysmenu'];
$html['system_toolbar'][2][] = <<<EOT
					<span onclick="ot.inpage_edit({'page':'{$params['flexedit'][$page]['id']}','mode':$mode,'name':'{$x_name}' $opt })">$t</span>
EOT;
				}
				$e_hnd = '';
			}

			if (isset($arg['class'])) {
				$cls = $arg['class'];
			} else {
				$cls = '';
			}
			if (isset($arg['id'])) {
				$inner_id = " id='{$arg['id']}' ";
			} else {
				$inner_id = '';
			}
			if ($inner_tag) {
				$buff .= "<{$inner_tag} class='$cls onethird-edit-pointer' $inner_id>{$inner_html1}$txt{$inner_html2} $e_hnd  $close_tag";
			} else {
				$txt2 = preg_replace('/^ *<(dt|dd).?>/', "<$1 class='$cls onethird-edit-pointer'> $e_hnd", $txt);
				if ($txt != $txt2) {
					$close_tag = '';
					$buff .= "{$inner_html1}$txt2{$inner_html2}";
				} else {
					$e_hnd = trim($e_hnd);
					$buff .= "<div class='$cls onethird-edit-pointer'>{$inner_html1}$txt{$inner_html2}$e_hnd</div>";
				}
			}
		}

		if ($e_hnd_outside) {
$buff .= <<<EOT
			$e_hnd_outside
EOT;
		}
		if (isset($arg['wrap_tag'])) {
			if ($arg['wrap_tag']) {
$buff .= <<<EOT
				</{$arg['wrap_tag']}>
EOT;
			}
		} else {
		}

	} else {
		if (check_rights('edit') && !isset($arg['no-edit'])) {
			$first_mess = '';
			$opt = '';
			if (isset($arg['first_mess'])) {
				$first_mess = $arg['first_mess'];
			}
			if (isset($arg['tpl'])) {
				$s = safe_echo($arg['tpl']);
				$opt .= ",'tpl':'$s'";
			}
$buff .= <<<EOT
			<a href="javascript:void(ot.inpage_edit_save({'page':'{$params['flexedit'][$page]['id']}','mode':'+','name':'{$x_name}' $opt}))" class='onethird-button' >{$ut->icon('add')} $first_mess</a>
EOT;
		}
	}

	if (!$buff) {
		if (isset($arg['no-edit'])) {
			return '';
		}
		$buff = '';
	}

	return $buff;

}

function get_flexedit( &$arg )
{
	global $params, $database, $config, $ut, $html;

	$ut->get_arg($arg);

	$tpl = $buff = '';
	$m = false;
	$mode = 0;

	if (isset($arg['name'])) {
		$x_name = $arg['name'];
	} else {
		return false;
	}

	if (isset($arg['idx'])) {
		$idx = $arg['idx'];
	} else {
		$idx = 0;
	}

	$page = $params['circle']['meta']['top_page'];
	if (isset($arg['page']) && $arg['page'] != 'top') {
		if ($arg['page'] == 'self' && isset($params['page']['id'])) {
			$page = $params['page']['id'];
		} else {
			$page = $arg['page'];
		}
	}

	if (is_numeric($page)) {
		$ar = $database->sql_select_all("select metadata,id from ".DBX."data_items where id=?", $page);
		if ($ar) {
			$m = unserialize64($ar[0]['metadata']);
			if (isset($m['flexedit'][$x_name][$idx]['text'])) {
				return echo_contents_script($m['flexedit'][$x_name][$idx]['text']);
			}
		} else {
			return false;
		}
	}
	return false;
}

function set_flexedit( &$arg )
{
	global $params, $database, $config, $ut, $html;

	$ut->get_arg($arg);

	$tpl = $buff = '';
	$m = false;
	$mode = 0;

	if (isset($arg['name'])) {
		$x_name = $arg['name'];
	} else {
		return false;
	}

	if (isset($arg['data'])) {
		$x_data = $arg['data'];
	} else {
		return false;
	}

	if (isset($arg['idx'])) {
		$idx = $arg['idx'];
	} else {
		$idx = 0;
	}

	$page = $params['circle']['meta']['top_page'];
	if (isset($arg['page']) && $arg['page'] != 'top') {
		if ($arg['page'] == 'self' && isset($params['page']['id'])) {
			$page = $params['page']['id'];
		} else {
			$page = $arg['page'];
		}
	}

	if (is_numeric($page)) {
		$ar = $database->sql_select_all("select metadata,id from ".DBX."data_items where id=?", $page);
		if ($ar) {
			$m = unserialize64($ar[0]['metadata']);
			$id = $ar[0]['id'];
			$m['flexedit'][$x_name][$idx]['text'] = save_contents_script($x_data);
			if ( $database->sql_update( "update ".DBX."data_items set metadata=? where id=?", serialize64($m), $id) ) {
				return true;
			}
		} else {
			return false;
		}
	}
	return false;
}

function read_pagedata( $p_page, &$output )
{
	global $database,$plugin_ar,$params,$ut,$p_circle,$html;

	if (isset($params['hook']['before_read_page']) && is_array($params['hook']['before_read_page'])) {
		foreach ($params['hook']['before_read_page'] as $v) {
			if (function_exists($v)) {
				if ($v($p_page, $output) === true) {
					return true;
				}
			}
		}
	}

	read_pagedata_raw($p_page, $output);
	if ($output) {
		if (check_rights('owner') && isset($_GET['mode']) && $_GET['mode'] == 'draft') {
			if (!empty($output['meta']['draft'])) {
				$output['contents'] = $output['meta']['draft'];
				$html['information'][]='下書';
			}
		}
		if (isset($params['hook']['after_read_page']) && is_array($params['hook']['after_read_page'])) {
			foreach ($params['hook']['after_read_page'] as $v) {
				if (function_exists($v)) {
					$v($output);
				}
			}
		}
	}
	return isset($output['type']);
}
function read_pagedata_raw( $p_page, &$output )
{
	global $database, $p_circle;

	//$ar = $database->sql_select_all("select contents,title,metadata,type,link,id,mode,{$ut->date_format("mod_date", "'%Y/%m/%d'")} as mod_date,{$ut->date_format("date", "'%Y/%m/%d'")} as date ,block_type,user,tag from ".DBX."data_items where id=? and circle=?", $p_page, $p_circle);
	$output = $database->sql_select_all("select contents,title,metadata,type,link,id,mode,mod_date,date,block_type,user,tag from ".DBX."data_items where id=? and circle=?", $p_page, $p_circle);
	if ( $output ) {
		$output = $output[0];
		if ($output['metadata']) {
			$output['meta'] = unserialize64($output['metadata']);
		} else {
			$output['meta'] = array();
		}
		$output['contents'] = echo_contents_script($output['contents']);
		unset($output['metadata']);
	} else {
		return false;
	}
	return true;
}
function basic_renderer( $p_page )
{
	global $database,$plugin_ar,$params,$ut;
	$fstblock = true;
	$buff = '';

	if (!isset($params['page'])) {
		read_pagedata($p_page, $params['page']);
	}
	if ($params['page']['type'] == HIDDEN_ID || (int)$params['page']['block_type'] == 10 ) {
		return '';
	}

	$breadcrumb_title = $params['page']['title'] = $params['page']['title'];
	if ( !$params['page']['title'] && isset($plugin_ar[$params['page']['type']]['title']) ) {
		$breadcrumb_title = $plugin_ar[$params['page']['type']]['title'];
	}
	snippet_breadcrumb( $params['page']['link'], adjust_mstring($breadcrumb_title) );

	if (!isset($params['page']['meta']['renderer'])) {
		if (($tmp = plugin_renderer($params)) !== false) {
			$buff .= $tmp;
			if (isset($params['rendering']) && !$params['rendering']) {
				//innerプラグイン内でレタリングをストップできるように仕様変更
				return $tmp;
			}
		} else {
			//$params['page']['type'] = 1;				// 強制的にtype1にする
			$params['page']['fstblock'] = $fstblock;	//
			$params['inner_page'] = &$params['page'];
			$buff .= frame_renderer(body_renderer($params['page']));
		}

	} else {
		$i = 0;
		$c = count($params['page']['meta']['renderer']);
		foreach ($params['page']['meta']['renderer'] as $k=>$v) {
			++$i;
			if ( $k == 'reference' ) {
				//必ず下位置にレタリング

			} else {
				if ( $k == $p_page ) {
					$ar = $params['page'];

				} else if ( is_numeric($k) ) {
					$ar = array();
					read_pagedata( $k, $ar );

				} else {
					$ar = null;
				}
				if ($ar) {
					$ar['fstblock'] = $fstblock;
					$ar['endblock'] = $i == $c;
					$params['inner_page'] = &$ar;
					if (($tmp = plugin_renderer($ar)) !== false) {
						$buff .= $tmp;
						if (isset($params['rendering']) && !$params['rendering']) {
							//innerプラグイン内でレタリングをストップできるように仕様変更
							return $tmp;
						}
					} else {
						$buff .= frame_renderer(body_renderer($ar));
					}
					unset($params['inner_page']);
					unset($params['rendering']);
					$fstblock = false;
				}
			}
		}
	}
	return $buff;
}

function custompage_renderer( $p_page, &$body )
{
	return innerpage_renderer( $p_page, $body );
}

function innerpage_renderer( $p_page, &$body=false )
{
	global $database,$plugin_ar,$params,$ut;
	$buff = '';
	if (!isset($params['page']) || empty($params['page']['meta'])) {
		return;
	}
	if (!isset($params['page']['meta']['renderer'])) {
		return;
	}
	$i = 0;
	$c = count($params['page']['meta']['renderer']);
	foreach ($params['page']['meta']['renderer'] as $k=>$v) {
		++$i;
		if ( $k == $p_page ) {
			$ar = null;
			if ($body) {$buff .= $body;}
		} else if ( is_numeric($k) ) {
			$ar = array();
			read_pagedata( $k, $ar );

		} else {
			$ar = null;
		}
		if ($ar && $params['page']['id'] != $ar['id']) {
			$ar['fstblock'] = false;
			$ar['endblock'] = $i == $c;
			$params['inner_page'] = &$ar;
			if (($tmp = plugin_renderer($ar)) !== false) {
				$buff .= $tmp;
			} else {
				$buff .= frame_renderer(body_renderer($ar));
			}
			unset($params['inner_page']);
			$fstblock = false;
		}
	}
	return $buff;
}

function body_renderer( &$page_ar )
{
	global $params,$html,$plugin_ar,$config,$ut;

	$p_page = $page_ar['id'];
	$type= $page_ar['type'];
	$body= $page_ar['contents'];

	$b_top= isset($page_ar['fstblock']) && $page_ar['fstblock'];

	$buff = '';
	$a = '';

	$params['_flexedit_body_renderer'] = true;
	$r = $params['magic_number'];
	$p = get_template_values();
	$p.= ' $a = <<<EOT'.$r."\n";
	$p.= $body;
	$p.= "\nEOT$r;\n";

	try {
		if (check_rights()) {
			$params['_hnd_eval'] = 1;
		}
		if (empty($config['disable_expand'])) {
			reject_func($p);
			$r = @eval($p);
		} else {
			$a = $body;
		}
		$params['_hnd_eval'] = 0;
	} catch (Exception $e) {
		$r = $e->getMessage();
	}
	unset($params['_flexedit_body_renderer']);

	if ($r === false) {
		$a = 'エラーが発生しました、ページ内容を表示できません、編集画面に戻ってください(1)<br />';
		$opt = array();
		if (isset($params['page']['id'])) {
			$opt['caption'] = '編集';
			$opt['href'] = $ut->link($params['page']['id'],'mode:edit');
		}
		exit_proc(0, $a, $opt);
	}
	$buff .= title_renderer($page_ar);
	$buff .= $a;

	if (!$buff) {
		if ($page_ar['block_type'] == 5) {
			$buff = '<p>...</p>';
		} else {
			$buff = '&nbsp';
		}
	}

	if (check_rights() && !isset($params['hide-blockmenu']) && $p_page) {
		$tmp = '';
		if (check_rights('owner')) {
			if (isset($params['add-blockmenu'])) {
				$tmp .= implode($params['add-blockmenu']);
			}
			if ($page_ar['block_type'] != 5) {
				if (check_rights('edit') && check_func_rights('add_page')) {
$tmp .= <<<EOT
					<span onclick='ot.add_page($p_page)' title='追加' >{$ut->icon('add')}</span>
EOT;
				}
$tmp .= <<<EOT
				<span onclick='ot.page_setting($p_page)' title='プロパティ' >{$ut->icon('setting')}</span>
				<span onclick='ot.select_ogimage($p_page)' title='Thumbnail' >{$ut->icon('star')}</span>
EOT;
				if (!$params['page']['mode'] && $params['page']['user'] == $_SESSION['login_id']) {
$tmp .= <<<EOT
					<span onclick='ot.public_page($p_page)' title='go public' >{$ut->icon('ok')}</span>
EOT;
				}
			} else {
				$tmp .= std_blockmenu_renderer($page_ar);
			}
			if ((isset($params['inner_page']['user']) && $params['inner_page']['user'] == $_SESSION['login_id']) || check_rights('owner')) {
				if ((!isset($plugin_ar[$page_ar['type']]['inpage-edit']) || $plugin_ar[$page_ar['type']]['inpage-edit']) && $page_ar['block_type'] == 5) {
					snippet_inpage_edit();
$tmp .= <<<EOT
					 <span onclick="ot.inpage_edit({page:$p_page, mode:this})" title='edit' >{$ut->icon('edit')}</span>
EOT;
				} else {
					if (isset($params['edit-right'])) {
$tmp .= <<<EOT
						 <span onclick='location.href="{$ut->link($p_page,'mode:edit')}"' title='Edit' >{$ut->icon('edit')}</span>
EOT;
					}
				}
			}
 		} else if (check_rights('edit')) {
$tmp .= <<<EOT
			<span onclick='ot.page_setting($p_page)' title='プロパティ' >{$ut->icon('setting')}</span>
EOT;
 		}
 		if ($tmp) {
 			$buff .= "<div class='edit_pointer'>$tmp</div>";
 		}
	}

	unset($params['hide-blockmenu']);
	unset($params['add-blockmenu']);

	// attached plugin
	if (isset($page_ar['meta']['attached_plugin'])) {
		foreach ($page_ar['meta']['attached_plugin'] as $v) {
			$ar = array('id'=>$page_ar['id'], 'type'=>$v['type'], 'user'=>$page_ar['user'], 'attached'=>true, 'meta'=>&$page_ar['meta'] );
			if (isset($v['func']) && function_exists($func)) {
				if (($tmp = $func($ar)) !== false) {
					$buff .= $tmp;
				}
			} else {
				if (($tmp = plugin_renderer($ar)) !== false) {
					$buff .= $tmp;
				}
			}
		}
	}

	return $buff;
}

function chg_infomail( $user, $mess='' )
{

	global $config,$params,$database,$p_circle;

	if ($user) {
	} else {
		if ( isset($params['circle']['owner']) && $params['circle']['owner'] ) {

			$user = $params['circle']['owner'];

		} else {
			$user = $config['admin_user'];
		}
	}
	$ar = $database->sql_select_all("select mailadr,name from ".DBX."users where id=?", $user );
	if (!$ar) {
		return;
	}

	$mailadr = $ar[0]['mailadr'];
	$mail_ar = array();

	$mail_ar['to'] =  $mailadr;
	$mail_ar['circle'] = $p_circle;
	$mail_ar['user_nickname'] = $ar[0]['name'];
	$mail_ar['subject'] = "system information";
	$mail_ar['message'] = "$mess";
$mail_ar['message'] = <<<EOT

$mess


--

{$config['site_url']}

EOT;
	return sx_send_mail( $mail_ar );
}

//ログ付きのメール送信
function sx_send_mail( $mail_ar )
{
	global $params, $database, $config, $p_circle;

	if (isset($params['sx_send_mail'])) {
		return $params['sx_send_mail']($mail_ar);
	}

	$msg = '';

	$circle = $p_circle;
	if ( isset($mail_ar['circle']) ) {
		$circle = (int)$mail_ar['circle'];
	}
	$log_mess='';
	if ( isset($mail_ar['message']) ) {
		$log_mess = adjust_mstring($mail_ar['message'],200);
	}
	if (!isset($mail_ar['no-log'])) {
		$log_id = add_actionlog($log_mess);
		if (!$log_id) {
			return false;
		}
	}

	if (!isset($mail_ar['to']) || !isset($mail_ar['message'])) {
		return false;
	}

	$sendto = $mail_ar['to'];
	if (isset($mail_ar['subject'])) {
		$subject = $mail_ar['subject'];
	} else {
		$subject = 'OneThird CMS';
	}
	if (isset($mail_ar['from'])) {
		$sendfrom = $mail_ar['from'];
	} else {
		$sendfrom = $config['site']['email'];
	}
	$headers  = "From: ".$sendfrom."\r\n";
	if (!empty($mail_ar['bcc'])) {
		$headers .= 'Bcc: '.$mail_ar['bcc']."\r\n";
	}
	if (!empty($mail_ar['cc'])) {
		$headers .= 'Cc: '.$mail_ar['cc']."\r\n";
	}

	if (isset($mail_ar['multipart'])) {
		$boundary = 'mail'.mt_rand(0,10000000);
		$headers .= 'MIME-Version: 1.0' . "\r\n";
		$headers .= "Content-Type: multipart/alternative;boundary={$boundary}\r\n";

		//テキストパート、HTMLパート
		$msg .= "--{$boundary}\r\n";
		$msg .= "Content-Type: text/plain; charset=iso-2022-jp\r\n";
		$msg .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
		$msg .= $mail_ar['message']."\r\n";
		$msg .= "--{$boundary}\r\n";
		$msg .= "Content-Type: text/html; charset=iso-2022-jp\r\n";
		$msg .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
		$msg .= $mail_ar['message_html']."\r\n";
		$msg .= "--{$boundary}--\r\n";

	} else {
		$msg .= $mail_ar['message']."\r\n";
	}

	//メール用にサブジェクトと本文をエンコード (phpのデフォルトでは日本語の件名が化ける)
	mb_language("ja");
	mb_internal_encoding("UTF-8");
	$subject = mb_convert_encoding($subject, "JIS", 'utf-8');
	$subject = base64_encode($subject);
	$subject = '=?ISO-2022-JP?B?' . $subject . '?=';

	$msg = str_replace( "\r", '', $msg );
	$msg = mb_convert_encoding($msg, 'ISO-2022-JP', "utf-8");
	if (USE_EMAIL) {
		if (mail($sendto, $subject, $msg, $headers)){
			$m = "[Send mail] [to:$sendto] [from:$sendfrom] {$log_mess}";
			if (!isset($mail_ar['no-log'])) {
				$database->sql_update("update ".DBX."action_log set data=? where id=?", adjust_mstring($m,250),$log_id );
			}
		} else {
			$m = "[Send mail] [ERROR] [to:$sendto] [from:$sendfrom] {$log_mess}";
			if (!isset($mail_ar['no-log'])) {
				$database->sql_update("update ".DBX."action_log set data=? where id=?", adjust_mstring($m,250),$log_id );
			}
		}
		/*if (isset($mail_ar['cc'])) {
			if (mail($mail_ar['cc'], $subject." (cc:{$sendto})", $msg, $headers)){
			}
		}*/
	} else {
		$m = "[Send mail] [DUMMY] [to:$sendto] [from:$sendfrom] {$log_mess}";
		if (!isset($mail_ar['no-log'])) {
			$database->sql_update("update ".DBX."action_log set data=? where id=?", adjust_mstring($m,250),$log_id );
		}
	}

	return true;
}

function https_nonlogin_mode()
{
	global $database;

	// for ajax 暗号化通信
	// はじめにhttpsでログインしトークンを発行
	// httpでアクセス時、httpsでログインした時のトークンが合えば、ログイン状態だとみなし
	// idを仮設定する、一致しない場合は通信失敗
	// ただし、ログイン関係のセッション変数自体は操作しない（あくまでもトークン発行だけに使用するため）

	$login_id = false;
	if (isset($_POST['ajax']) && !isset($_SESSION['login_id'])) {
		if (!isset($_POST['u'])) {
			system_error( __FILE__, __LINE__ );
		}
		$user = (int)$_POST['u'];
		$ar = $database->sql_select_all( "select metadata from ".DBX."users where id=?", $user );
		if (!$ar) {
			system_error( __FILE__, __LINE__ );
		}
		if (isset($ar[0]['metadata'])) {
			$login_metadata = unserialize($ar[0]['metadata']);
		} else {
			$login_metadata = array();
		}
		if (isset($_POST['otoken'])) {
			$otoken = $_POST['otoken'];
		}
		if ($login_metadata['https_otoken'] != $otoken) {
			// tokenが一致しないのでエラー
			$r['result'] = false;
			unset($login_metadata['https_otoken']);
			if ( $database->sql_update( "update ".DBX."users set metadata=? where id=?", serialize($login_metadata), $user)  ) {
			}
			echo( json_encode($r) );
			exit();
		}
		$login_id = $user;
	}
	return $login_id;
}

function https_login_mode( &$login_metadata, $login_id )
{
	global $database, $config;

	// 通信するたびに、トークンを生成する
	// start_encryptは明示的なトークン生成

	$otoken = '';
	if (isset($_POST['otoken'])) {
		$otoken = sanitize_str( $_POST['otoken'] );
	}
	$ar = $database->sql_select_all( "select metadata from ".DBX."users where id=?", $login_id );
	if (!$ar) {
		exit_proc(0,"セッションが不正です、再ログインしてください<br /><a href='{$config['site_url']}logout.php'>ログアウトして、トップに戻る</a>");
	}
	if (isset($ar[0]['metadata'])) {
		$login_metadata = unserialize($ar[0]['metadata']);
	} else {
		$login_metadata = array();
	}
	if (!isset($_POST['ajax']))  {
		// ajax通信以外はトークン生成
		$otoken = mt_rand( 1000000, mt_getrandmax() );
		$otoken = hash('sha1',$otoken);
		$login_metadata['https_otoken'] = $otoken;
		set_cookie("otx2", $otoken);
		$_SESSION['otx2'] = $otoken;
		$params['login_user']['meta']['https_otoken'] = $_SESSION['otx2'];
		if ( !$database->sql_update( "update ".DBX."users set metadata=? where id=?",serialize($login_metadata), $login_id) ) {
			system_error( __FILE__, __LINE__ );
		}
	} else if ($_POST['ajax']=='system_logout') {
		//ログアウトは受け付ける

	} else if ($_POST['ajax']=='start_encrypt' && $otoken == $login_metadata['https_otoken']) {
		// トークンをリジェネレート
		header( "Access-Control-Allow-Origin: *" );
		$r = array();
		$r['result'] = false;
		$r['login_id'] = $login_id;
		$otoken = mt_rand( 1000000, mt_getrandmax() );
		$otoken = hash('sha1',$otoken);
		$login_metadata['https_otoken'] = $otoken;
		if ( $database->sql_update( "update ".DBX."users set metadata=? where id=?", serialize($login_metadata), $login_id) ) {
			$r['result'] = true;
			if ( isset($_SESSION['login_id']) ) {
				$r['uid'] = $_SESSION['login_id'];
			}
			$r['otoken'] = $otoken;
			set_cookie("otx2", $r['otoken']);
			$_SESSION['otx2'] = $otoken;
			$params['login_user']['meta']['https_otoken'] = $_SESSION['otx2'];
		}
		echo( json_encode($r) );
		exit();

	} else {
		if (!isset($login_metadata['https_otoken']) || $login_metadata['https_otoken'] != $otoken) {
			// tokenが一致しないのでエラー
			$r['result'] = false;
			//$r['https_otoken'] = $login_metadata['https_otoken'];
			$r['https_otoken_error'] = true;
			$r['otoken'] = $otoken;
			unset($login_metadata['https_otoken']);
			if ( $database->sql_update( "update ".DBX."users set metadata=? where id=?", serialize($login_metadata), $login_id) ) {
			}
			echo( json_encode($r) );
			exit();
		}
	}

	return $otoken;
}

function https_login_script()
{
	global $html,$config;

$html['meta']['https_login_script'] = <<<EOT
<script>
var ajax_data;
var xdom_handle;
function receive_xdom( e ) {
	d = e.data;
	if ( d == 'start' ) {
		xdom_handle = e.source.window;
		xdom_handle.postMessage( JSON.stringify(ajax_data), '*' );

	} else {
		data = JSON.parse( d );
		ajax_data.success(data);
	}
}

ot.overlay_encrypt = function (sw) {
	this.overlay(sw, "<div style='background:#fff;padding:20px'><img src='{$config['site_url']}img/loading.gif' /><br /><br />In encrypted ...<br /><br /><input type='button' class='onethird-button large' value='stop' onclick='ot.overlay(0)' /></div>", {delay:800,fadein:200});
};
</script>
EOT;
}

function get_under_construction_defmess()
{
	return 'このページは現在メンテナンス中です';
}

function std_pagination_renderer( $option )
{
	global $config,$params;

	if (isset($params['pagination_renderer'])) {
		return $params['pagination_renderer']($option);
	}
	if (!isset($option['total'])) { return 'pagination-error'; }
	if (!isset($option['offset'])) { $option['offset'] = 0; }
	if (!isset($option['page_size'])) { $option['page_size'] = 100; }
	if (!isset($option['max_pagination'])) { $option['max_pagination'] = 10; }
	if (!isset($option['previous'])) { $option['previous'] = ''; }
	if (!isset($option['next'])) { $option['next'] = ''; }

	$buff = '';
	if ($option['total'] && $option['total'] > $option['page_size']) {
		$ar = array();
		pagination_urls( $ar, $option );

		$buff.='<div class="onethird-pagination"><ul>';
		if (isset($ar['prev'])) {
			$buff.="<li class='prev'><a href='{$ar['prev']}'>&larr; {$option['previous']}</a></li>";
		} else {
			$buff.="<li class='prev disabled'><a>&larr; {$option['previous']}</a></li>";
		}

		foreach($ar['mid'] as $k=>$v) {
			if ($v == '...') {
				$buff .= "<li style='background: none; color: inherit; padding-left:5px;padding-right:5px;'>...</li>";
				continue;
			}
			$buff.="<li ";
			if ( $option['offset'] == $k ) {
				$buff.=" class='active' ";
			}
			$buff.=" ><a href='{$v}'>".((int)$k+1)."</a></li>";
		}

		if (isset($ar['next'])) {
			$buff.="<li class='next'><a href='{$ar['next']}'>{$option['next']} &rarr;</a></li>";
		} else {
			$buff.="<li class='next disabled'><a>{$option['next']} &rarr;</a></li>";
		}
		$buff .= '</ul></div>';
	}

	return $buff;
}

function pagination_urls( &$ar, &$option )
{
	global $config,$params,$ut;

	if (!is_array($ar)) {
		$ar = array();
	}
	if (!isset($option['offset_name'])) { $option['offset_name'] = 'offset'; }

	if (!isset($option['total'])) { return ''; }
	if (!isset($option['offset'])) { $option['offset'] = 0; }
	if (!isset($option['page_size'])) { $option['page_size'] = 100; }

	$p_id = 0;
	if (isset($params['page']['id'])) {
		$p_id = $params['page']['id'];
	}
	if (!isset($option['url_home'])) {
		if (!isset($option['url'])) {
			$option['url_home'] = $ut->link($p_id);
		} else {
			$option['url_home'] = $option['url'];
		}
	}
	if (!isset($option['url'])) {
		$option['url'] = $ut->link($p_id, "&:{$option['offset_name']}=");
	} else {
		$option['offset_name'] = sanitize_asc($option['offset_name']);
		$u = preg_replace("/({$option['offset_name']}=[0-9]*)/mu",'',$option['url']);
		$u = trim($u,"&?");
		$option['url_home'] = $u;

		if (strstr($u,'?') !== false) {
			$option['url'] = "{$u}&";
		} else {
			$option['url'] = "{$u}?";
		}
		$option['url'] .= "{$option['offset_name']}=";
	}

	if ($option['total'] && $option['total'] > $option['page_size']) {
		if ($option['offset'] > 0) {
			if (isset($option['url_script'])) {
				$ar['prev'] = "javascript:void({$option['url_script']}(".($option['offset']-1)."))";
			} else {
				if ($option['offset']-1 > 0) {
					$ar['prev'] = "{$option['url']}".($option['offset']-1);
				} else {
					$u = substr($option['url'],0,-1);
					$ar['prev'] = $option['url_home'] ;
				}
			}
		}
		$pos = 0;
		if (isset($_GET[$option['offset_name']])) {
			$pos = (int)$_GET[$option['offset_name']];
		}
		$range = (int)($option['max_pagination']);
		$mx = (int)($option['total']/$option['page_size']);
		$bk = 0;
		for ($i=0; $i*$option['page_size'] < $option['total']; ++$i) {
			if ($pos > $mx-$range+2) {
				if ($i != 0 && $i <= $mx-$range+1) {
					$bk = $i;
					continue;
				}
			} else if ($pos <= $range-3) {
				if ($i >= $range-1 && $i != $mx) {
					$bk = $i;
					continue;
				}
			} else {
				if ($i != 0 && $i != $mx) {
					$r1 = $range-3;
					$r2 = $r1/2;
					$r1 = $r1/2 + $r1%2;
					if ($i > $pos-$r1 && $i < $pos+$r2) {
					} else {
						$bk = $i;
						continue;
					}
				}
			}
			if ($bk-1 != $i) {
				$ar['mid'][] = "...";
			}
			if (isset($option['url_script'])) {
				$ar['mid'][$i] = "javascript:void({$option['url_script']}($i))";
			} else {
				if ($i > 0) {
					$ar['mid'][$i] = "{$option['url']}$i";
				} else {
					$u = substr($option['url'],0,-1);
					$ar['mid'][$i] = $option['url_home'];
				}
			}
		}
		if (($option['offset']+1)*$option['page_size'] < $option['total']) {
			if (isset($option['url_script'])) {
				$ar['next'] = "javascript:void({$option['url_script']}(".($option['offset']+1)."))";
			} else {
				$ar['next'] = "{$option['url']}".($option['offset']+1);
			}
		}
	}
}

function pv_logging( $p_page )
{
	global $database,$params,$agent,$config;

	if (isset($_SESSION['login_id'])) {
		// ログインしているユーザーはロギングしない
		return;
	}
	if (isset($agent) && substr($agent,0,3) == 'bot') {
		// ボットを除外する
		return;
	}
	if (!isset($params['circle']['meta']['pv_logging'])) {
		return;
	}
	if ($database->write_lock) {
		$lock_bk = $database->write_lock;
		$database->write_lock = false;
	}
	$ar = $database->sql_select_all( "select metadata, pv_count from ".DBX."data_items where id=? ", $p_page );
	if (isset($lock_bk)) {
		$database->write_lock = $lock_bk;
	}
	if (!$ar) {
		return;
	}
	if (!$ar[0]['pv_count']) {
		$ar[0]['pv_count'] = 0;
	}
	++$ar[0]['pv_count'];
	if ($ar[0]['metadata']) {
		$m = unserialize64($ar[0]['metadata']);
	} else {
		$m = array();
	}
	if (isset($m['pv_ip']) && $m['pv_ip'] ==  $_SERVER["REMOTE_ADDR"]) {
		if ($ar[0]['pv_count'] > 1) {
			return;
		}
	}
	$m['pv_ip'] =  $_SERVER["REMOTE_ADDR"];
	if ($database->write_lock) {
		$lock_bk = $database->write_lock;
		$database->write_lock = false;
	}
	$database->sql_update("update ".DBX."data_items set metadata=?,pv_count=? where id=? ", serialize64($m), $ar[0]['pv_count'], $p_page);
	if (isset($lock_bk)) {
		$database->write_lock = $lock_bk;
	}

}

function get_tags( $tag )
{
	preg_match_all('/#([^,]+)/mu', $tag.",", $out, PREG_PATTERN_ORDER);
	if (isset($out[1])) {
		return $out[1];
	}
	return array();
}

function get_systags( $tag , $tag_name = false)
{
	preg_match_all('/@([^,]+)/mu', $tag.",", $out, PREG_PATTERN_ORDER);
	if (isset($out[1])) {
		return $out[1];
	}
	return array();
}

function snippet_loginform( $force = 0 )
{
	global $html,$p_circle,$config,$params,$ut,$agent,$plugin_ar;
	if (isset($_SESSION['login_id'])) {
		return;
	}
	if (isset($html['meta']['loginform']) || ($force == 0 && isset($params['circle']['meta']['hide_login']))) {
		return;
	}
	provide_onethird_object();
	snippet_dialog();
	if (isset($_COOKIE['otx_m'])) {
		$otx_m = (int)$_COOKIE['otx_m'];
	} else {
		$otx_m = -1;
	}
	$ac = "{$config['site_ssl']}{$config['admin_dir']}/account.php";

$tmp = <<<EOT
	<script>
	ot.agent = '$agent';
	if (window.navigator && window.navigator.userAgent) {
		// for jQuery 1.9x
		var a = window.navigator.userAgent.toLowerCase();
		if (a.indexOf('msie') != -1) {
			ot.msie = true;

		} else if (a.indexOf('firefox') != -1) {
			ot.mozilla = true;
		}
	}
	ot.show_login_panel = function (hide_close){
		if (hide_close) {
			\$('.close_btn').hide();
		}
		ot.open_dialog(\$( "#dialog_login" ).width(380),{position:'fixed'});
		\$('#p_user').focus();
		\$('#p_pass').unbind('keydown');
		\$('#p_pass').keydown(function(event) {
			if (event.keyCode == 13) {
				ot.do_login();
			}
		});
	};
	\$(function(){
		if (ot.mozilla) {
			\$("input.data-entry").keypress(ot.checkForEnter);
		} else {
			\$("input.data-entry").keydown(ot.checkForEnter);
		}
	});
	ot.checkForEnter = function (event) {
		if (event.keyCode == 13) {
			var textboxes = \$("input.data-entry");
			var currentBoxNumber = textboxes.index(this);
			if (textboxes[currentBoxNumber + 1] != null) {
				var nextBox = textboxes[currentBoxNumber + 1];
				if ( nextBox ) {
					nextBox.focus().select();
					event.preventDefault();
					return false;
				}
			}
		}
	};
	</script>
EOT;

$tmp .= <<<EOT
	<div id="dialog_login" class='onethird-dialog' >
		<div method='post' id='form2' >
			<input type='hidden' name='mdx' id='mdx' value='mod_circle' />
			<p class='title'>システムログイン</p>
			<div class='onethird-setting'>
				<dl>
					<dt>ユーザーID / メールアドレス</dt>
					<dd>
						<input type='text' id='p_user' name='username' class=' data-entry' />
					</dd>
				</dl>
				<dl>
					<dt>パスワード</dt>
					<dd>
						<input type='password' id='p_pass' name='password' class=' data-entry'  />
					</dd>
				</dl>
				<div class='actions border-less'>
					<input type='button' class='onethird-button default' value='ログイン' onclick='ot.do_login()' />
EOT;
					if ((isset($params['template']) && $params['template'] != 'admin.tpl') || isset($params['manager'])) {
$tmp .= <<<EOT
						<input type='button' class='onethird-button close_btn' value='戻る' onclick='location.href="{$params['circle']['url']}"' />
EOT;
					} else {
$tmp .= <<<EOT
						<input type='button' class='onethird-button close_btn' value='閉じる' onclick='ot.close_dialog(this)' />
EOT;
					}
$tmp .= <<<EOT
				</div>
EOT;
				if (!isset($params['circle']['meta']['dis_newacc'])) {
$tmp .= <<<EOT
					<p>- <a href='{$config['site_url']}{$plugin_ar[ LOGIN_ID ]['selector']}?create_account=true'>新規アカウント作成</a>
					</p>
EOT;
				}
$tmp .= <<<EOT
				<p>- <a href='{$config['site_url']}{$plugin_ar[ LOGIN_ID ]['selector']}?&forget=1'>パスワード紛失</a>
				</p>
			</div>
		</div>
	</div>
EOT;

	$request_uri_ssl = "{$config['site_ssl']}{$config['admin_dir']}/account.php?circle=$p_circle&plugin={$plugin_ar[ LOGIN_ID ]['selector']}";
	$request_uri = "{$config['site_url']}{$config['admin_dir']}/account.php?circle=$p_circle&plugin={$plugin_ar[ LOGIN_ID ]['selector']}";

	if (substr($config['site_ssl'],0,6) == 'https:' && substr($config['site_url'],0,5) == 'http:') {
		//xdom_login をリクエストする
		$xdom_login = " + '&xdom_login=1' ";
	} else {
		$xdom_login = '';
	}

$tmp .= <<<EOT
	<script>
		ot.do_login = function () {
			var opt = '';
			var user = \$('#p_user').val();
			var pass = \$('#p_pass').val();
			opt += "&id="+user+"&ps="+pass;
			ajax_data = {
				type: "POST"
				, url: '{$request_uri_ssl}'
				, data: "ajax=do_login&plugin={$plugin_ar[ LOGIN_ID ]['selector']}"+opt $xdom_login
				, dataType:'json'
				, success: function(data){
					if (data && data['result']) {
						if (data['xdom_login']) {
							ajax_data['url'] = "{$request_uri}";
							ajax_data['data'] = "ajax=do_login&id="+user+"&token="+data['token'];
							ot.ajax(ajax_data);	// 非SSL-URLにトークンで再ログイン
						} else {
							location.reload(true);
						}
					} else {
						if (data['mess']) {
							alert(data['mess']);
						} else {
							alert('Login failure');
						}
						\$('#p_user').focus().select();
					}
				}
				, error: function(data){
					alert('Login failure');
				}
			};
			if ( ot.navigator == 'msie' && window.XDomainRequest ) {
				window.addEventListener("message", receive_xdom, false);
				\$('body').append("<iframe id='xdom' src='{$config['site_ssl']}xdom.php' scrolling='no' style='display:none'></iframe>");
			} else {
				ot.ajax( ajax_data );
			}
		};
	</script>
EOT;

	$html['meta']['loginform'] = $tmp;
	https_login_script();
}

function snippet_alertmoving( $option = null )
{
	global $html, $config, $params;

$a = <<<EOT
	<script>
		\$(function(){
EOT;
		if (isset($option['force'])) {
$a .= <<<EOT
			ot.alert_moving();
EOT;
		} else {
$a .= <<<EOT
			\$('input,textarea').change(function(){ot.alert_moving()});
EOT;
		}
$a .= <<<EOT
		});
		ot.alert_moving = function () {

			if (window.navigator && window.navigator.userAgent) {
				var a = window.navigator.userAgent.toLowerCase();
				if (a.indexOf('msie 8.') != -1 || a.indexOf('msie 7.') != -1) {
					return;	//ie8はonbeforeunloadの挙動がおかしいため除外
				}
			}

			if (!window.onbeforeunload) {
				window.onbeforeunload = function(event){
					event = event || window.event;
					return event.returnValue = ' データが保存されていません ';
				}
			}
		};
		ot.reset_moving = function () {
			window.onbeforeunload = null;
		};
		ot.move_page = function (href) {
			ot.reset_moving();
			location.href=href;
		};
	</script>
EOT;
	$html['meta'][] = $a;

}

function snippet_avoid_robots()
{
	global $html;
	$html['head']['robots'] = "<meta name='robots' content='noindex,nofollow' />";
}


function snippet_dialog()
{
	global $html;

	if (isset($html['meta']['dialog'])) {
		return;
	}
$html['meta']['dialog'] = <<<EOT
<script>
	window.ot = window.ot || {};
	ot.open_dialog = function (obj,option) {
		\$('body').append(obj);
		var w = obj.width();
		var left = \$(window).scrollLeft()+\$(window).width()/2-w/2;
		var top = \$(window).scrollTop()+\$(window).height()/2-obj.height()/2-10;
		var pos = 'absolute';
		var st = \$('body').scrollTop();
		if (st < \$(window).scrollTop()) { st = \$(window).scrollTop(); }
		if (top < 0) { top = 10+st; }
		if (option) {
			top = option.top+st;
			if (option.position === 'fixed') {
				top = option.top || 50;
				pos = 'fixed';
			}
		}
		if (\$(window).width() < w) {
			w = \$(window).width()-10;
			obj.width(w);
			left = 0;
		}
		var nest = \$('#onethird-dialog-warp').attr('data-nest');
		if (nest) {
			\$('#onethird-dialog-warp').attr('data-nest', nest+1);
		} else {
			obj.wrap("<div id='onethird-dialog-warp' class='onethird-dialog-warp' data-nest='0' ></div>");
		}
		obj.css({
			zIndex: '160000'
			, position: pos
			, top:top
			, left:left
		});
		if (!nest) {
			obj.parent()
			.css({
				  position: 'absolute'
				, display: 'none'
				, top: '0'
				, left: '0'
				, width: '100%'
				, height: \$(document).height()
				, background: 'rgba(0,0,0,0.5)'
				, zIndex: '160000'
			})
			.fadeIn(200);
		}
		obj.fadeIn(200);

	};
	ot.close_dialog = function (obj, remove) {
		if (!obj) {
			obj = \$('.onethird-dialog').fadeOut();
		} else {
			if (typeof(obj)==='string') {
				obj = \$(obj).fadeOut();
			} else {
				if (obj.hasClass && obj.hasClass('onethird-dialog')) {
					obj.fadeOut();
				} else {
					obj = \$(obj).parents('.onethird-dialog').fadeOut();
				}
			}
		}
		if (!remove) {
			\$('body').append(obj);
		}
		obj = \$('.onethird-dialog-warp');
		if (parseInt(obj.attr('data-nest')) == 0) {
			obj.fadeOut(100,function(){
				\$(this).remove();
			});
		} else {
			obj.attr('data-nest', parseInt(obj.attr('data-nest'))-1);
		}
	};
</script>
EOT;
}

function snippet_footer()
{
	global $html,$config;
$html['footer'][] = <<<EOT
	<p>&copy; team1/3 <a href='http://onethird.net'>OneThird-CMS</a> v{$config['version']}</p>
EOT;
}

function snippet_header()
{
	global $config,$params;
	global $html,$database;
	global $ut,$plugin_ar;

	$keyword = $description = '';
	if ( isset($params['circle']['name']) ) {
		$name = $params['circle']['name'];
	}

	if ( isset($params['page']['title']) && $params['page']['title'] ) {
		if (isset($params['top_page'])) {
			//トップページはページタイトルが優先
			$name = $params['page']['title'];
		} else {
			$name = $params['page']['title'].' | '.$name;
		}
	}

	if ( isset($params['page']['meta']['keyword']) ) {
		$keyword = $params['page']['meta']['keyword'];
	}
	if ( isset($params['page']['meta']['description']) ) {
		$description = $params['page']['meta']['description'];
	}

$a = <<<EOT
<meta charset="UTF-8">
EOT;

	if ( $name ) {
		$a .= "<title>$name</title>\n";
	}
	if ( $keyword ) {
		$a .= "<meta name='keyword' content='{$keyword}'>\n";
	}
	if ( $description ) {
		$a .= "<meta name='description' content='{$description}'>\n";
	}

	if (empty($params['manager'])) {
		if (isset($params['circle']['name'])) {
			$a .= "<meta property='og:site_name' content='{$params['circle']['name']}'>\n";
		}
		if ($name) {
			$a .= "<meta property='og:title' content='$name'>\n";
			$a .= "<meta name='twitter:card' content='summary' />\n";
		}

		if (isset($params['page']['meta']['og_description'])) {
			$a .= "<meta property='og:description' content='{$params['page']['meta']['og_description']}'>\n";
		}

		if (isset($params['page']['meta']['og_image'])) {
			$a .= "<meta property='og:image' content='".get_thumb_url($params['page'],false)."'>\n";
		} else if (!empty($params['default_ogp_image'])) {
			$a .= "<meta property='og:image' content='{$params['default_ogp_image']}'>\n";
		}
		$a .= "<meta property='og:url' content='{$ut->link($params['page']['id'])}'>\n";
	}

	// URLの正規化
	if (isset($params['canonical']) && $params['canonical'] &&!isset($html['head']['robots'])) {
		$a .= "<link rel='canonical' href='{$params['canonical']}' />";
	}

	if (!isset($html['head'])) {
		$html['head'][] = $a;
	} else {
		array_unshift($html['head'], $a);	// metadataはHEAD先頭に配置する
	}

}

function snippet_system_nav()
{
	global $config,$p_circle,$params;
	global $html,$database,$plugin_ar,$ut;

	$p_add_page = 0;
	if (isset($params['page']['id'])) {
		$p_page =  $params['page']['id'];
		if (!isset($params['top_page'])) {
			$p_add_page = $p_page;
		}
	} else {
		$p_page =  0;
	}

	if (!isset($html['system_toolbar'][0])) {
		$html['system_toolbar'][0] = array();	// basic toolbar
	}
	if (!isset($html['system_toolbar'][1])) {
		$html['system_toolbar'][10] = array();	// advanced toolbar
	}

	$system_toolbar = &$html['system_toolbar'][0];

	if (check_rights('edit')) {
		$system_toolbar[] = "<a href='{$ut->link('list')}'>ページ一覧</a>";

		if ($p_page) {
			$system_toolbar[] = "<a href='javascript:void(ot.page_setting($p_page))' >ページプロパティ</a>";
			snippet_page_property();
		}
		if (!isset($params['manager']) && !isset($params['plugin']) && isset($params['page']['id'])) {
			if (!isset($_GET['mode'])) {
				$system_toolbar[] = "<a href='{$ut->link($params['page']['id'],'&:mode=edit')}'>ページ編集</a>";
			}
		}

		if (!isset($params['manager'])) {
			$system_toolbar[] = "<a href='javascript:void(ot.add_page($p_add_page,true))'>ページ追加</a>";
		}
		snippet_page_backup();

	} else {
		if (check_rights() && isset($params['page']['user']) && $_SESSION['login_id'] == $params['page']['user']) {
			// 編集権限がなくてもページオーナーならプロパティ設定できる
			set_func_right('page_property');
			provide_edit_rights();
			snippet_page_property();
		}
	}

	if (check_rights('admin')) {

		//$system_toolbar[] = "<hr />";
		$system_toolbar[] = "<a href='{$config['site_url']}{$config['admin_dir']}/member.php?circle={$p_circle}'>メンバー一覧</a>";

		// 管理機能
		$system_toolbar = &$html['system_toolbar'][10];

		$system_toolbar[] = "<a href='{$config['site_url']}{$config['admin_dir']}/setting.php?circle={$p_circle}'>Site settings</a>";

		if (isset($_SESSION['css_compile'])) {
			$system_toolbar[] = "<a href='javascript:void(ot.css_compile())'>CSS Compile Save</a>";
		} else {
			$system_toolbar[] = "<a href='javascript:void(ot.css_compile())'>CSS Compile Mode</a>";
		}

		$system_toolbar[] = "<a href='{$config['site_url']}{$config['admin_dir']}/actionlog.php?circle={$p_circle}'>Action Log</a>";

		$system_toolbar[] = "<a href='{$config['site_url']}{$config['admin_dir']}/restore.php?circle={$p_circle}'>バックアップツール</a>";

		if ( isset($params['page']['id']) ) {
			$system_toolbar[] = "<a href='{$config['site_url']}{$config['admin_dir']}/pmanager.php?page={$p_page}&amp;circle=$p_circle'>Page manager</a>";
		} else {
			$system_toolbar[] = "<a href='{$config['site_url']}{$config['admin_dir']}/pmanager.php?page=0&amp;circle=$p_circle'>Page manager</a>";
		}

		$system_toolbar = &$html['system_toolbar'][10]['system'];
		$system_toolbar['caption'] = "System Tools &raquo;";
		$system_toolbar[] = "<a href='javascript:void(ot.create_circle())'>Add Site</a>";
		$system_toolbar[] = "<a href='javascript:void(ot.remove_circle())'>Remove Site</a>";
		$system_toolbar[] = "<a href='javascript:void(ot.clear_localStorage())'>Quit less compiled mode</a>";

$a = <<<EOT
		<script>
EOT;
$a .= <<<EOT
		ot.clear_localStorage = function(){
			if ( confirm("lessコンパイルモードをクリアします。実行しますか？") ){
				localStorage.clear();
				\$('#post_form_mode').val('go_exit_compile');
				\$('#post_form').append("<input type='hidden' name='xtoken' value='"+ot.magic_str+"' />");
				\$("#post_form").submit();
			}
		};

		ot.create_circle = function() {
			if ( confirm("新規サイトを作成します。実行しますか？") ){
				\$('#post_form_mode').val('create_circle');
				\$('#post_form').append("<input type='hidden' name='xtoken' value='"+ot.magic_str+"' />");
				\$("#post_form").submit();
			}
		};

		ot.remove_circle = function () {
			if ( confirm("サイトを削除します。実行しますか？") ){
				ot.ajax({
					type: "POST"
					, url: '{$params['request_name']}'
					, data: "ajax=remove_circle"
					, dataType:'json'
					, success: function(data){
						if ( data && data['result'] ) {
							alert('サイトを削除しました');
							//location.reload(true);
						}
					}
				});
			}
		};
		</script>
		<form method='post' id='post_form' action='{$params['safe_request']}' style='display:none' >
			<input type='hidden' name='post_form_mode' id='post_form_mode' value='' />
			<input type='hidden' name='post_form_page' id='post_form_page' value='' />
		</form>
EOT;

		$html['meta'][] = $a;
		$less_file = 'less.2.7.2.min.js';
		if (isset($config['fless_file'])) {
			$less_file = $config['fless_file'];
		}
		if ( isset($_SESSION['css_compile']) ) {
			$a = '';
			$p = $config['site_url'];
$a .= <<<EOT
			<script src="{$config['site_url']}js/less/{$less_file}"></script>
			<script>
			css_compile_ar = new Array();
EOT;
			foreach ($_SESSION['css_compile'] as $v) {
				if ($v['type']=='less' || $v['type']=='css') {
					$n = basename($v['data']);
$a .= <<<EOT
					css_compile_ar.push(['{$v['type']}','{$v['data']}','{$n}']);
EOT;
				}
			}
			$d = '';
			if (!empty($params['circle']['meta']['include']['last'])) {
				$d = '?'.$params['circle']['meta']['include']['last'];
			}
$a .= <<<EOT
			ot.css_compile = function () {
				if (!css_compile_ar['length']) {
					alert('There is no data that can be stored');
				} else {
					ot.css_compile_save(0);
				}
			};
			ot.css_compile_save = function (mode) {
				if ( !css_compile_ar['length'] ) {
					return;
				}
				\$('body').css('opacity','0.5');
				var v = css_compile_ar.shift();
				if ( v ) {
					var u = "ajax=css_compile&mode=";
					u += mode+"&file="+encodeURI(v[1]+'.'+v[0]);
					u += "&name="+encodeURI(v[1]);
					u += "&type="+v[0];
					if (v[0] == 'less') {
						var x = \$("style[id*='"+v[2].replace(/[.]/g,":")+"']").text();
						if (!x) {
							alert(v[1]+'.less'+' が見つかりません');
							\$('body').css('opacity','1');
							css_compile_ar.unshift(v);
							return;
						}
						u += "&data="+encodeURIComponent(x);
					}
					ot.ajax({
						type: "POST"
						, url: '{$params['request_name']}'
						, data: u
						, dataType:'json'
						, success: function(data){
							if ( data && data['result'] ) {
								if (data['done']) {
									var a = "{$config['site_url']}";
									location.reload(true);
								} else {
									ot.css_compile_save(1);
								}
							} else {
								if (data['mess']) { alert(data['mess']); } else { alert('css save error 1'); }
								\$('body').css('opacity','1');
							}
						}
						, error: function(data){
							alert('css save error 2');
							\$('body').css('opacity','1');
						}
					});
				}
			};
			</script>
EOT;
			$html['meta'][] = $a;

			if ( isset($_POST['ajax']) && $_POST['ajax']=='css_compile' ) {

				$r = array();
				if ($params['circle']['meta'] = get_circle_meta()) {
					$params['circle']['meta']['include']['last'] = $_SERVER['REQUEST_TIME'];
					$metadata=serialize64($params['circle']['meta']);
					if ($database->sql_update("update ".DBX."circles set metadata=? where id=?",$metadata,$p_circle)) {
					}
				}
				$style_name = false;
				$style_buff = '';
				if (isset($_POST['mode']) ) {
					$r['mode'] = (int)$_POST['mode'];
					$style_name = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'style.css';
					$buff = '';
					if ($r['mode'] == 0) {
				    	if (is_file($style_name)) {
				    		unlink($style_name);
				    	}
						set_circle_meta('inline_css',null);
						set_circle_meta('inline_css2', null);
					} else {
						if (is_file($style_name)) {
							$style_buff = @file_get_contents($style_name);
						} else {
							$style_buff = '';
						}
					}
				}
			    if (isset($_POST['name']) && $_POST['name']) {
			    	$name = sanitize_str($_POST['name']).".css";

					$p = $config['site_url'];
					if (substr($name,0,strlen($p)) == $p) {
						//css save
						$name = $config['site_path'].DIRECTORY_SEPARATOR.substr($name,strlen($p));
						if (isset($_POST['data'])) {
							$r['rpath'] = substr($name,strlen($config['site_url']));
							if ($r['rpath']) {
								$r['rpath'] = substr($r['rpath'],0,strrpos($r['rpath'],'/'));
							}
							if (!$r['rpath']) {
								$r['rpath'] = '';
							}
							if (isset($_POST['data'])) {
					    		$data = $_POST['data'];
					    	} else {
					    		$data = @file_get_contents($name);
					    	}
							$data = str_replace($config['site_url'].$r['rpath'].'/', '', $data);

							$r['css_url'] = $config['site_url'].'css/';
							$data = str_replace($r['css_url'], '../../../css/', $data);

							if ((!is_file($name) || is_writable($name)) && @file_put_contents($name, $ut->compress('css',$data))) {
								$r['result'] = true;
							} else {
								$r['mess'] = "write error {$name}, check file exist or permission";
								$r['result'] = false;
								echo( json_encode($r) );
								exit();
							}
						}
						// bind css save
						if (isset($_POST['data'])) {
				    		$data = $_POST['data'];
				    	} else {
				    		$data = @file_get_contents($name);
				    	}
						$d = false;
						if (isset($_SESSION['css_compile'])) {
							foreach ($_SESSION['css_compile'] as $k=>$v) {
								if (($v['type'] == 'less' || $v['type'] == 'css') && $v['data'] == $_POST['name'] ) {
									$d = $_SESSION['css_compile'][$k];
									unset($_SESSION['css_compile'][$k]);
									break;
								}
							}
						}
						$r['css_url'] = $config['site_url'].'css/';
						if (empty($d['inline'])) {
							// files/1/style.css に保存
							$r['base_url'] = $params['circle']['files_url'];
							$data = str_replace($r['base_url'], '', $data);
						} else {
							// inlineのため、基準はsite_url
							$r['base_url'] = $config['site_url'];
							$data = str_replace($r['base_url'], '', $data);
						}
						$data = str_replace($r['css_url'], '../../css/', $data);
						if (isset($_POST['data'])) {
							$style_buff = $style_buff."\r\n/*-- onethird less compiled ".basename($name)." --*/\r\n".$data;
						} else {
							$style_buff = $style_buff."\r\n/*-- onethird bind css ".basename($name)." --*/\r\n".$data;
						}
						if (empty($d['inline'])) {
							if (@file_put_contents($style_name, $style_buff)) {
								$r['append'] = $style_name;
								$r['result'] = true;
							}
						} else {
							$r['id'] = $params['circle']['meta']['top_page'];
					    	$r['meta']['inline_css'] = get_circle_meta('inline_css2');
							if (empty($r['meta']['inline_css'])) {$r['meta']['inline_css']=array();}
							$pathinfo = pathinfo($name);
        					$r['meta']['inline_css'][$pathinfo['filename']] = $ut->compress('css',$data);

							if (set_circle_meta('inline_css2', $r['meta']['inline_css'])) {
								$r['result'] = true;
							} else {
								$r['result'] = false;
								echo( json_encode($r) );
								exit();
							}
						}
						if (isset($_SESSION['css_compile']) && !count($_SESSION['css_compile'])) {
							unset($_SESSION['css_compile']);
							$r['done'] = true;
						}
					}

				}
				$r['save'] = $name;
				if (isset($_POST['file'])) {
					$r['file'] = $_POST['file'];
				}
				echo( json_encode($r) );
				exit();
			}

		} else {
$a = <<<EOT
			<script>
			ot.css_compile = function () {
				if ( !JSON ) {
					alert('This browser can not compile it');
				} else {
					ot.ajax({
						type: "POST"
						, url: '{$params['request_name']}'
						, data: "ajax=css_compile"
						, dataType:'json'
						, success: function(data){
							if ( data && data['result'] ) {
								location.reload(true);
							}
						}
					});
				}
			};
			</script>
EOT;
			$html['meta'][] = $a;

			if (isset($_POST['ajax']) && $_POST['ajax']=='css_compile') {
				$r = array();

				// loadコマンドの結果セットされる $params['load_css'] を取得するため、一旦テンプレート出力する
				ob_start();
				expand_circle_html($params['page']['meta']);
				ob_end_clean();

				if ( isset($params['load_css']) && is_array($params['load_css']) ) {
					$r['result'] = true;
					$_SESSION['css_compile']=$params['load_css'];
				}
				echo( json_encode($r) );
				exit();
			}
		}
		if (isset($_POST['ajax']) && $_POST['ajax']=='remove_circle') {
			$r = array();
			$r['result'] = remove_circle();
			echo( json_encode($r) );
			exit();

		}
		if (isset($_POST['post_form_mode'])) {
			if ($_POST['post_form_mode'] == 'go_exit_compile') {
				unset($_SESSION['css_compile']);
				header("Location:{$params['safe_request']}");
				exit();
			}
			if ($_POST['post_form_mode'] == 'create_circle') {
				$url = create_circle($_SESSION['login_id']);
				if ($url) {
					header("Location:{$url}");
				}
				alert('Failed to create site');
			}
		}

	}

	if (check_rights()) {
$a = <<<EOT
		<script>
		ot.system_logout = function () {
			ot.ajax({
				type: "POST"
				, url: '{$params['request_name']}'
				, data: "ajax=system_logout"
				, dataType:'json'
				, success: function(data){
					if ( data && data['result'] ) {
						location.href='{$ut->link()}';
					}
				}
			});
		};
		</script>
EOT;
		$html['meta'][] = $a;
	}

	if ( isset($_POST['ajax']) && $_POST['ajax']=='system_logout' ) {
		system_logout();
		$r = array();
		$r['result'] = true;
		echo( json_encode($r) );
		exit();
	}
	if (!empty($params['circle']['meta']['data']['system_menus'])) {
		foreach ($params['circle']['meta']['data']['system_menus'] as $v) {
			if (isset($v['rights']) && !check_rights($v['rights'])) {
				continue;
			}
			$u = $v['url'];
			if (substr($u,0,4) != 'http') {
				if (is_numeric($u)) {
					$u = $ut->link($u);
				} else {
					if (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"]!='off') {
						$u = $config['site_ssl'].$u;
					} else {
						$u = $config['site_url'].$u;
					}
				}
				if (!empty($params['page']) && !empty($v['opt']) && strstr($v['opt'],'page_id') !== false) {
					if (strstr($u,'?') !== false) {
						$u .= '&';
					} else {
						$u .= '?';
					}
					$u .= 'id='.$params['page']['id'];
				}
			}
			if ($v['rights'] == 'admin') {
				if (check_rights('admin')) { $html['system_toolbar'][10][] = "<a href='{$u}'>{$v['title']}</a>"; }
			} else if ($v['rights'] == 'edit') {
				if (check_rights('edit')) { $html['system_toolbar'][0][] = "<a href='{$u}'>{$v['title']}</a>"; }
			} else {
				$html['system_toolbar'][0][] = "<a href='{$u}'>{$v['title']}</a>";
			}
		}
		unset($params['circle']['meta']['data']['system_menus']);	//旧版対応のため
	}

}

function snippet_breadcrumb( $link, $title, $rewrite=false )
{
	global $params, $database, $config, $ut;
	global $plugin_ar;
	if (isset($params['breadcrumb'])) {
		// パンくずは書き換えられない仕様
		// unset( $params['breadcrumb'] );
		if (!$rewrite) { return; }
		$params['breadcrumb'] = array();
	}
	if ($link) {
		$id = (int)$link;
		$tmp = array();
		for ($i=0; $i < MAX_PAGE_NEST; ++$i) {	// Page Tree 最大ネスト
			$ar2 = $database->sql_select_all("select title,link,type,block_type,mode from ".DBX."data_items where id=?", $id);
			if (!$ar2 || $ar2[0]['mode'] ==2) { break; }
			if (!$ar2[0]['title'] && isset($plugin_ar[$ar2[0]['type']])) {
				$ar2[0]['title'] = $plugin_ar[$ar2[0]['type']]['title'];
			}
			if ($ar2[0]['block_type'] != 5) {
				array_unshift($tmp, array( 'link'=>$ut->link($id), 'text'=>$ar2[0]['title'] ) );
			}
			if (!$ar2[0]['link']) { break; }
			$id = $ar2[0]['link'];
		}
		foreach ($tmp as $v) {
			if (isset($v['text']) && $v['text']) {
				$params['breadcrumb'][] = $v;
			}
		}
	}
	if (!$title) {
		$title = '...';
	}
	$params['breadcrumb'][] = array( 'link'=>'', 'text'=>"<a href='{$ut->link($params['page']['id'])}'>$title</a>" );
}

function snippet_jqueryui()
{
	global $params,$html,$config;
	$ui = "<script src=\"//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js\"></script>";
	$uicss = "<link rel=\"stylesheet\" href=\"//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/smoothness/jquery-ui.css\">";
	if (!empty($params['circle']['meta']['jqueryui_url'])) {
		$ui=$params['circle']['meta']['jqueryui_url'];
		if (substr($ui,0,1)=='.') {
			$ui = $params['circle']['files_url'].substr($ui,1);
		} else {
			$ui = $config['site_ssl'].$ui;
		}
		$ui = "<script src=\"$ui\"></script>";
	}
	if (!empty($params['circle']['meta']['jqueryuicss_url'])) {
		$uicss = $params['circle']['meta']['jqueryuicss_url'];
		if (substr($uicss,0,1)=='.') {
			$uicss = $params['circle']['files_url'].substr($uicss,1);
		} else {
			$uicss = $config['site_ssl'].$uicss;
		}
		$uicss = "<link rel=\"stylesheet\" href=\"$uicss\">";
	}
	$html['meta']['_jqueryui'] = "$ui $uicss";	// '_jquery になっているのはロード順を上げるため
}

function snippet_delayedload()
{
	global $html,$config;

	if (isset($html['meta']['delayedload'])) {
		return;
	}

$html['meta']['delayedload'] = <<<EOT
<script>
	function delayedload(ar, end) {
		if (ar) {
			if (!ot['loading']) {
				ot.loading = [];
				ot.loading_c = 0;
			}
			for (var i=0; i<ar.length; ++i) {
				if (ar[i]) { ot.loading.push(ar[i]); }
			}
			if (end) { ot.loading.push(end); }
		}
		if (!ot.loading || !(v = ot.loading.shift())) {
		} else {
			var ok = false;
			if (v['type'] == 'script') {
				var x = \$('script');
				for (var i in x) {
					if (x[i].src == v['src']) {
						ok = true;
						delayedload();
						break;
					}
				}
				if (!ok) {
					var script = document.createElement('script');
					script.onload = script.onreadystatechange = function(e) {
						if (!this.readyState || this.readyState === "loaded" || this.readyState === "complete") {
							if (ot.loading_c > 0) { --ot.loading_c; }
							if (ot.loading_c == 0) {
								delayedload(e);
							}
						}
					}
					script.defer = true;
					script.src = v['src'];
					++ot.loading_c;
					document.body.appendChild(script);
				}

			} else if (v['type'] == 'css') {
				var x = document.querySelectorAll('link');
				for (var i in x) {
					if (x[i].rel == 'stylesheet' && x[i].href == v['href']) {
						ok = true;
						delayedload();
						break;
					}
				}
				if (!ok) {
					var head = document.getElementsByTagName('head')[0];
					var link = document.createElement('link');
					link.onload = delayedload;
					link.rel = 'stylesheet';
					link.href = v['href'];
					head.appendChild(link);
				}
			} else if (typeof v == 'function') {
				v();
			}
		}
	}
</script>
EOT;

}

function frame_renderer($cont, $add_class = '')
{
	global $params;
	if (isset($params['frame_renderer'])) {
		return $params['frame_renderer']($cont, $add_class);
	}

$buff = <<<EOT
	<div class='onethird-frame $add_class onethird-edit-pointer' >
		$cont
	</div>
EOT;
	return $buff;

}

function snippet_overlay($option = null)
{
	global $html;

	if (isset($html['meta']['overlay'])) {
		return;
	}

$html['meta']['overlay'] = <<<EOT
<script>
ot.overlay = function (sw, html, opt) {
	var time1 = 200;
	var time2 = 200;
	var time3 = 0;
	if (opt) {
		if (opt['delay'] !== undefined) { time1 = opt['delay']; }
		if (opt['fadein'] !== undefined) { time2 = opt['fadein']; }
		if (opt['fadeout'] !== undefined) { time3 = opt['fadeout']; }
	}
	if ( !sw ) {
		\$('#onethird-overlay').stop().fadeOut(100,function(){\$(this).remove()});
	} else {
		var a;
		if ( \$("#onethird-overlay").size() ) {
			return;
		}
		var a = \$("<div id='onethird-overlay' ></div>")
		.css({
			  position: 'fixed'
			, display: 'none'
			, top: '0'
			, left: '0'
			, width: '100%'
			, height: \$('body').height()+1000
			, overflow: 'hidden'
			, background: 'rgba(0,0,0,0.5)'
			, zIndex: '10000'
		}).fadeIn(200);

		if (html) {
			var b = \$("<div id='inner_overlay' class='onethird-overlay'>"+html+"</div>")
			.css({
				display: 'none'
				, 'text-align': 'center'
			}).animate({opacity:0},{duration:time1,complete:function(){\$(this).show()}}).animate({opacity:1},{duration:time2});
			if (time3) {
				b.fadeOut(time3, function(){ot.overlay(0)});
			}
			\$('body').append(a.append(b));
		} else {
			\$('body').append(a);
		}
	}
};
</script>
EOT;
}

function alert($mess)
{
	global $html;
	snippet_overlay();

	$html['alert'][] = $mess;

}

function title_renderer(&$page_ar)
{
	global $ut,$params;

	if (isset($params['title_renderer'])) {
		return $params['title_renderer']($page_ar);
	}

	if (!empty($page_ar['hide-title']) || isset($page_ar['meta']['hide-title']) || $page_ar['mode'] == 2 || !empty($params['hide-title'])) {
		return '';
	}
	if (!$page_ar['title']) {
		return '';
	}

	$title = trim($page_ar['title']);

	if (!$page_ar['mode'] && $page_ar['block_type'] != 5) {
		$title = $ut->icon('admin').$title;
	}

	return "<h1>{$title}</h1>";
}

function std_blockmenu_renderer( &$page_ar, $option = null )
{
	global $ut,$params;

	if (isset($params['blockmenu_renderer'])) {
		return $params['blockmenu_renderer']($page_ar, $option);
	}

	$buff = '';
	if (!isset($page_ar['fstblock']) || !$page_ar['fstblock']) {
		$buff .= "<a href='javascript:void(ot.move_innerpage({$page_ar['id']},-1))' >{$ut->icon('up')}</a>";
	}
	if (!isset($page_ar['endblock']) || !$page_ar['endblock']) {
		$buff .= "<a href='javascript:void(ot.move_innerpage({$page_ar['id']},1))' >{$ut->icon('dn')}</a>";
	}
	if (!isset($option['remove_button']) || $option['remove_button']) {
		if (isset($params['page']['id']) && $page_ar['id'] != $params['page']['id']) {
			$buff .= "<a href='javascript:void(ot.remove_page({$page_ar['id']}))' title='delete' >{$ut->icon('delete')}</a>";
		}
	}
	return $buff;
}

function snippet_image_uploader()
{
	global $config;

	provide_edit_module();
	return _snippet_image_uploader();
}

function provide_login_module()
{
	return provide_edit_rights();
}

function provide_edit_module()
{
	global $config,$params;

	$p = 'std';
	if (isset($params['login_user']['meta']['data']['uploader'])) {
		$e = $params['login_user']['meta']['data']['uploader'];
		if (isset($params['circle']['meta']['data']['system_uploaders'][$e])) {
			$p = $e;
		}
	}
	@include_once($config['site_path'].'/module/utility.'.sanitize_asc($p).'.php');

	$p = 'std';
	if (isset($params['login_user']['meta']['data']['editor'])) {
		$e = $params['login_user']['meta']['data']['editor'];
		if (isset($params['circle']['meta']['data']['system_editors'][$e])) {
			$p = $e;
		}
	}
	@include_once($config['site_path'].'/module/edit.'.sanitize_asc($p).'.php');
}

function snippet_heartbeat()
{
	global $html,$config,$params;

	if (isset($html['meta']['snippet_heartbeat'])) {
		return;
	}

$html['meta']['snippet_heartbeat'] = <<<EOT
<script>
	\$(function(){
		ot.heartbeat();
	});
	ot.heartbeat = function() {
		if (ot.heartbeat) { clearTimeout(ot.heartbeat); }
		ot.heartbeat_id = setTimeout(function(){
			ot.ajax({
				type: "POST"
				, url: '{$params['request_name']}'
				, data: "ajax=heartbeat"
				, dataType:'json'
				, success: function(data){
					if ( data && data['result'] ) {
						ot.heartbeat();
					}
				}
			});
		},1000*60);
	};
</script>
EOT;
	if (isset($_POST['ajax']) && $_POST['ajax']=='heartbeat') {
		$r = array();
		$r['result'] = true;
		$r['heartbeat'] = true;
		echo( json_encode($r) );
		exit();
	}
}

function check_func_right($func, $right = 'edit')
{
	return check_func_rights($func, $right);
}
function check_func_rights($func, $right = 'edit')
{
	global $params;

	return check_rights($right) || isset($params['func_right'][$func]);
}

function set_func_right($func, $login = true)
{
	return set_func_rights($func, $login);
}

function set_func_rights($func, $login = true)
{
	global $params;

	if ($login && !isset($_SESSION['login_id'])) {
		return false;
	}

	$params['func_right'][$func] = true;
	return true;
}

function add_data_items( &$ar )
{
	global $p_circle,$database,$params,$config;

	if (!check_func_rights('add_data_items') && !check_func_rights('add_page')) {
		return false;
	}

	if (!isset($ar['block_type']) || !isset($ar['type'])) { return false; }

	$d = $params['now'];
	if (!isset($ar['date'])) { $ar['date'] = $d; }
	if (!isset($ar['mod_date'])) { $ar['mod_date'] = $d; }

	if (!isset($ar['contents'])) { $ar['contents'] = ''; }
	if (!isset($ar['title'])) { $ar['title'] = ''; }
	if (!isset($ar['link'])) { $ar['link'] = 0; }
	if (!isset($ar['mode']) || !is_numeric($ar['mode'])) { $ar['mode'] = 0; }
	if (!isset($ar['pv_count'])) { $ar['pv_count'] = 0; }
	if (!isset($ar['tag'])) { $ar['tag'] = ''; }
	if (!isset($ar['circle'])) { $ar['circle'] = $p_circle; }
	if (!isset($ar['metadata'])) { $ar['metadata'] = array(); }
	if (!is_array($ar['metadata'])) {
		$ar['metadata'] = unserialize64($new_ar['metadata']);
	}
	if (!isset($ar['meta'])) { $ar['meta'] = $ar['metadata']; }

	if (!isset($ar['user'])) {
		if (isset($_SESSION['login_id'])) {
			$ar['user'] = $_SESSION['login_id'];
			if (isset($params['login_user']['nickname'])) {
				$ar['meta']['author'] = $params['login_user']['nickname'];
			}
		} else {
			$ar['user'] = 0;
		}
	}

	if (!$database->sql_update("insert into ".DBX."data_items
		(contents,title,mod_date,date,circle,metadata,link,type,block_type,user,mode,tag,pv_count)
		values(?,?,?,?,?,?,?,?,?,?,?,?,?) ",save_contents_script($ar['contents']),$ar['title'],$ar['mod_date'],$ar['date'],$ar['circle']
		,serialize64($ar['meta']),$ar['link'],$ar['type'],$ar['block_type'],$ar['user'],$ar['mode'],$ar['tag'],$ar['pv_count'])) {

		return false;
	}
	$ar['id'] = $ar['new_id'] = $database->lastInsertId();

	if ($ar['link'] && $ar['block_type']==5 || $ar['link'] && $ar['block_type']==3) {
		regenerate_attached($ar['link']);	//上位リンクにリンクしてもらう
	}
	regenerate_foldertag($ar);

	return true;
}

function add_user_log( &$ar )
{
	global $p_circle,$database,$params;

	if (!isset($ar['type'])) { return false; }

	$user = 0;
	if (isset($_SESSION['login_id'])) {
		$user = $_SESSION['login_id'];
	}

	if (!isset($ar['user'])) { $ar['user'] = $user; }

	$d = $params['now'];
	if (!isset($ar['date'])) { $ar['date'] = $d; }

	if (!isset($ar['status'])) { $ar['status'] = 0; }
	if (!isset($ar['att'])) { $ar['att'] = 0; }
	if (!isset($ar['link'])) { $ar['link'] = 0; }
	if (!isset($ar['data'])) { $ar['data'] = ''; }
	if (!isset($ar['metadata'])) { $ar['metadata'] = array(); }
	if (!is_array($ar['metadata'])) {
		$ar['metadata'] = unserialize64($new_ar['metadata']);
	}
	if (!isset($ar['meta'])) { $ar['meta'] = $ar['metadata']; }

	if ($database->sql_update("insert into ".DBX."user_log
		(user,type,date,att,link,data,circle,metadata,status)
		values(?,?,?,?,?,?,?,?,?) ",$ar['user'],$ar['type'],$ar['date'],$ar['att']
		,$ar['link'],$ar['data'],$p_circle,serialize64($ar['meta']),$ar['status']
		)) {
		$ar['id'] = $ar['new_id'] = $database->lastInsertId();
		return true;
	}
	return false;
}

function mod_data_items(&$new_ar)
{
	global $database,$params;

	if (!isset($new_ar['id'])) { return false; }

	$old_ar = $database->sql_select_all("select user,metadata,contents,title,mod_date,date,mode,link,tag from ".DBX."data_items where id=? limit 1", $new_ar['id'] );
	if (!$old_ar) {
		return false;
	}
	$old_ar = $old_ar[0];

	$old_ar['meta'] = unserialize64($old_ar['metadata']);

	if (isset($new_ar['metadata'])) {
		// $new_ar['metadata']が設定されている場合は配列マージされない
		if (!is_array($new_ar['metadata'])) {
			$new_ar['meta'] = unserialize64($new_ar['metadata']);
		} else {
			$new_ar['meta'] = $new_ar['metadata'];
		}
	} else {
		if (!isset($new_ar['meta'])) {
			$new_ar['meta'] = $old_ar['meta'];
		} else {
			if ($old_ar['meta']) {
				$new_ar['meta'] = array_merge($old_ar['meta'],$new_ar['meta']);
			}
		}
	}
	if (!isset($new_ar['user'])) { $new_ar['user'] = $old_ar['user']; }
	if (!isset($new_ar['link']) || !$new_ar['link']) { $new_ar['link'] = $old_ar['link']; }
	if (!isset($new_ar['mode'])) { $new_ar['mode'] = $old_ar['mode']; }
	if (!isset($new_ar['contents'])) { $new_ar['contents'] = $old_ar['contents']; }
	if (!isset($new_ar['mod_date'])) { $new_ar['mod_date'] = $old_ar['mod_date']; }
	if (!isset($new_ar['tag'])) { $new_ar['tag'] = $old_ar['tag']; }
	if (isset($new_ar['date']) && strlen($new_ar['date']) == 19 && strtotime($new_ar['date'])) {
	} else {
		$new_ar['date'] = $old_ar['date'];
	}
	if (isset($new_ar['mod_date']) && strlen($new_ar['mod_date']) == 19 && strtotime($new_ar['mod_date'])) {
	} else {
		$new_ar['mod_date'] = $params['now'];
	}
	if (!isset($new_ar['title'])) { $new_ar['title'] = $old_ar['title']; }
	$new_ar['title'] = trim($new_ar['title']," \t\n\r");
	$m = serialize64($new_ar['meta']); 
	if (!$database->sql_update("update ".DBX."data_items set metadata=?,title=?,contents=?,mod_date=?,date=?,mode=?,user=?,link=?,tag=? where id=?", $m, $new_ar['title'], save_contents_script($new_ar['contents']), $new_ar['mod_date'], $new_ar['date'], $new_ar['mode'], $new_ar['user'], $new_ar['link'] ,$new_ar['tag'], $new_ar['id'])) {
		return false;
	}

	if ($new_ar['link'] != $old_ar['link']) {
		regenerate_attached($new_ar['link']);
		regenerate_foldertag($new_ar,true);
	}

	$ar = $database->sql_select_all("select metadata from ".DBX."data_items where id=? limit 1", $new_ar['id'] );
	if (!$ar) { return false; }
	if ($ar[0]['metadata'] == $m) {
		$new_ar['meta'] = unserialize64($m);
		unset($new_ar['metadata']);
		return true;
	}

	$database->sql_update("update ".DBX."data_items set metadata=? where id=?", serialize64($old_ar['meta']), $new_ar['id']);

	return false;

}

function get_user_log(&$new_ar)
{
	global $database;

	if (!isset($new_ar['id'])) { return false; }
	$ar = $database->sql_select_all("select metadata,att,status,data,date,link,user from ".DBX."user_log where id=? limit 1", $new_ar['id'] );
	if (!$ar) {
		return false;
	}
	$ar[0]['meta'] = unserialize64($ar[0]['metadata']);
	unset($ar[0]['metadata']);
	$new_ar = array_merge($new_ar,$ar[0]);
	return true;
}

function mod_user_log(&$new_ar)
{
	global $database;

	if (!isset($new_ar['id'])) { return false; }
	$old_ar = $database->sql_select_all("select metadata,att,status,data,date,link from ".DBX."user_log where id=? limit 1", $new_ar['id'] );
	if (!$old_ar) {
		return false;
	}
	$old_ar = $old_ar[0];

	$old_ar['meta'] = unserialize64($old_ar['metadata']);

	if (isset($new_ar['metadata'])) {
		// $new_ar['metadata']が設定されている場合は配列マージされない
		if (!is_array($new_ar['metadata'])) {
			$new_ar['meta'] = unserialize64($new_ar['metadata']);
		} else {
			$new_ar['meta'] = $new_ar['metadata'];
		}
	} else {
		if (!isset($new_ar['meta'])) {
			$new_ar['meta'] = $old_ar['meta'];
		} else {
			if ($old_ar['meta']) {
				$new_ar['meta'] = array_merge($old_ar['meta'],$new_ar['meta']);
			}
		}
	}
	if (!isset($new_ar['meta'])) { $new_ar['meta'] = $old_ar['meta']; }
	if (!isset($new_ar['att'])) { $new_ar['att'] = $old_ar['att']; }
	if (!isset($new_ar['data'])) { $new_ar['data'] = $old_ar['data']; }
	if (!isset($new_ar['date'])) { $new_ar['date'] = $old_ar['date']; }
	if (!isset($new_ar['status'])) { $new_ar['status'] = $old_ar['status']; }
	if (!isset($new_ar['link'])) { $new_ar['link'] = $old_ar['link']; }
	$m = serialize64($new_ar['meta']);
	if (!$database->sql_update("update ".DBX."user_log set metadata=?,att=?,data=?,status=?,date=?,link=? where id=?", $m, $new_ar['att'], $new_ar['data'], $new_ar['status'], $new_ar['date'], $new_ar['link'], $new_ar['id'])) {
		return false;
	}

	$ar = $database->sql_select_all("select metadata from ".DBX."user_log where id=? limit 1", $new_ar['id'] );
	if (!$ar) { return false; }
	if ($ar[0]['metadata'] == $m) {
		return true;
	}

	$database->sql_update("update ".DBX."user_log set metadata=? where id=?", serialize64($old_ar['meta']), $new_ar['id']);
	return false;
}

function set_user_meta($user, $name, $meta)
{
	global $database;

	$ar = $database->sql_select_all("select metadata from ".DBX."users where id=?", $user);
	if (!$ar) {
		return null;
	}

	$m = unserialize($ar[0]['metadata']);
	if (!$m) { return null;}
	if (!isset($m['data'])) {
		$m['data'] = array();
	}
	if ($meta === null && isset($m['data'][$name])) {
		unset($m['data'][$name]);
	} else {
		$m['data'][$name] = $meta;
	}
	return $database->sql_update("update ".DBX."users set metadata=? where id=?", serialize($m), $user);

}

function get_circle_meta($name = '')
{
	global $database, $p_circle;

	$ar = $database->sql_select_all("select metadata from ".DBX."circles where id=?", $p_circle);
	if ($ar && $ar[0]['metadata']) {
		$m = unserialize64($ar[0]['metadata']);
		if (!$name) { return $m; }
		if (isset($m['data'][$name])) {
			return $m['data'][$name];
		} else {
			return array();
		}
	}
	return null;
}

function set_circle_meta($name, $meta)
{
	global $database, $p_circle;
	// get_circle_metaと違い、$nameは省略できない(セキュリティ)
	$ar = $database->sql_select_all("select metadata from ".DBX."circles where id=?", $p_circle);
	if (!$ar) {
		return null;
	}

	$m = unserialize64($ar[0]['metadata']);
	if (!$m) { return null;}
	if (!isset($m['data'])) {
		$m['data'] = array();
	}
	if ($meta === null) {
		unset($m['data'][$name]);
	} else {
		$m['data'][$name] = $meta;
	}
	return $database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($m), $p_circle);
}

function make_f_alias()
{
	global $params;
	unset($params['circle']['meta']['f_alias']);
	if (isset($params['circle']['meta']['alias'])) {
		foreach ($params['circle']['meta']['alias'] as $k=>$v) {
			if (is_array($v)) { continue; }
			$params['circle']['meta']['f_alias'][$v] = $k;
		}
	}
}

function get_user_meta($user, $name)
{
	global $database;

	$ar = $database->sql_select_all("select metadata from ".DBX."users where id=?", $user);
	if ($ar && $ar[0]['metadata']) {
		$m = unserialize($ar[0]['metadata']);
		if (isset($m['data'][$name])) {
			return $m['data'][$name];
		} else {
			return array();
		}
	}
	return null;
}

function get_user_name($user_id = false)
{
	global $database,$params;

	if (!$user_id) {
		return $params['login_user']['nickname'];
	}
	$ar = $database->sql_select_all("select name,nickname from ".DBX."users where id=?", $user_id);
	if ($ar) {
		if ($ar[0]['nickname']) {
			return $ar[0]['nickname'];
		}
		if (!$ar[0]['name']) { return '---';}
		return $ar[0]['name'];
	}
	return '(none)';
}

function get_user_salt()
{
	if (!check_rights()) {
		system_error( __FILE__, __LINE__ );
	}
	$salt = get_user_meta($_SESSION['login_id'], '_salt');
	if (!empty($salt)) {
		return $salt;
	}
	set_user_meta($_SESSION['login_id'],'_salt',md5(session_id()));
	$salt = get_user_meta($_SESSION['login_id'], '_salt');
	if (!empty($salt)) {
		return $salt;
	}
	system_error( __FILE__, __LINE__ );
}

function get_thumb_url(&$page_ar, $thumb_img=true, $expand=true)
{
	global $config,$params;
	if (!$page_ar) { $page_ar = &$params['page']; }
	$img = '';
	$p = '';
	$id = $page_ar['id'];
	if ($thumb_img && isset($page_ar['meta']['thumb_img'])) {
		$p = $page_ar['meta']['thumb_img'];
		if ($expand) {
			return $config['files_url']."img/$id/$p";
		} else {
			return $config['site_url']."img.php?p={$id}&amp;i=$p";
		}
	}
	if (!empty($page_ar['meta']['og_image'])) {
		$p = $page_ar['meta']['og_image'];
		if (!empty($page_ar['meta']['og_path'])) {
			$id = $page_ar['meta']['og_path'];
			if (substr($id,0,1) == '.') {
				$id = substr($id,1);
				if ($expand) {
					return $params['data_url']."$id/$p";
				} else {
					return $config['site_url']."timg.php?p=$id&amp;i=$p";
				}
			}
		}
		if (substr($page_ar['meta']['og_image'],0,3) == 'img') {
			// 旧版 onethird 0.xに対応
			$img = "{$config['files_url']}{$p}";
		} else {
			if ($expand) {
				return $config['files_url']."img/$id/$p";
			} else {
				$img = "img.php?p={$id}&amp;i=$p";
			}
		}
	}
	return '';
}

function &get_theme_metadata($reget=false)
{
	global $html,$params,$database,$ut;

	if (!$reget) {
		if (isset($params['theme'])) {
			return $params['theme'];
		}
		if (isset($params['top_page'])) {
			$params['theme'] = &$params['page']['meta'];
			return $params['theme'];
		}
	}

	$params['theme'] = array();
	$top_page = $params['circle']['meta']['top_page'];
	$ar = $database->sql_select_all("select metadata from ".DBX."data_items where id=?", $top_page);
	if ($ar) {
		$meta = unserialize64($ar[0]['metadata']);
		$params['theme'] = $meta;
	} else {
		$params['theme'] = array();
	}
	return $params['theme'];
}

function load_site_plugin($safe=true)
{
	global $config,$params,$ut;

	// 標準プラグイン読み込み
	$plugin = $config['site_path'].'/plugin/plugin.php';
	@include_once($plugin);

	if ($safe || isset($_GET['safe'])) {
	} else {
		// テーマプラグイン読み込み
		$plugin = $params['files_path'].DIRECTORY_SEPARATOR.'plugin/plugin.php';
		@include_once($plugin);
		if (isset($params['circle']['meta']['data']['startup_script'])) {
			if (is_array($params['circle']['meta']['data']['startup_script'])) {
				foreach ($params['circle']['meta']['data']['startup_script'] as $v) {
					if (substr($v,0,6) == 'admin:') {
						if (!check_rights('admin')) { return; }
						$v = substr($v,6);
					} else if (substr($v,0,5) == 'edit:') {
						if (!check_rights('edit')) { return; }
						$v = substr($v,5);
					} else if (substr($v,0,6) == 'owner:') {
						if (!check_rights('owner')) { return; }
						$v = substr($v,6);
					}
					$u = $params['files_path'].DIRECTORY_SEPARATOR.$v;
					@include_once($u);
				}
			}
		}
	}
}

function echo_contents_script($v)
{
	if (!defined('ECHO_CONTENTS_SCRIPT')) {
		$v = preg_replace( '/<script/im', 'echo error-', $v );
	}
	return $v;
}

function save_contents_script($v)
{
	if (!defined('SAVE_CONTENTS_SCRIPT')) {
		$v = preg_replace( '/<script/im', 'save error-', $v );
	}
	return $v;
}


?>