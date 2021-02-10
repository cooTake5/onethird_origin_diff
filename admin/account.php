<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.

	global $p_registration,$request_uri,$otoken,$agent,$config,$html;
	
	define('RETRY_COUNT',20);

	require_once(dirname(__FILE__).'/../config.php');
	if (!is_session_started()) {
		@session_set_cookie_params(0, $config['site']['cookie_path']);
		@session_start();
		session_regenerate_id(true);
	} else {
		session_regenerate_id();
	}

	require_once(dirname(__FILE__).'/../module/utility.basic.php');

	basic_initialize();
	avoid_attack();
	snippet_avoid_robots();
	
	if (substr($config['site_ssl'],0,6) == 'https:' && (!isset($_SERVER["HTTPS"]) || $_SERVER["HTTPS"]=='off') && isset($params['circle']['meta']['https_force'])) {
		header("Location: {$config['site_ssl']}{$config['admin_dir']}/account.php?circle=".$p_circle);
		exit();
	}

	$params['manager'] = 'account';
	$params['template'] = 'admin.tpl';

	// 標準プラグイン/テーマプラグイン読み込み
	load_site_plugin();

	//非ログイン時にはアクセスできなくする
	if (!check_rights()) {
		if (!isset($_POST['ajax']) && (!isset($_POST['plugin']) || $_POST['plugin'] != $plugin_ar[ LOGIN_ID ]['selector'])) {
			if (!isset($_GET['registration'])) {
				exit_proc(403);
			}
		}
	}

	$request_uri = "{$config['site_ssl']}{$config['admin_dir']}/account.php";
	if (!empty($_SERVER["QUERY_STRING"])) {
		$request_uri .= '?'.sanitize_str($_SERVER["QUERY_STRING"]);
	}

	// ログイン ajax処理
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'do_login') {
	
		if (substr($config['site_ssl'],0,6) == 'https:') {
			header( "Access-Control-Allow-Origin: *" );
		}

		$r = array();
		$r['result'] = false;

		if (isset($_POST['id'])) {
			$login_id = sanitize_str($_POST['id']);
		} else {
			$login_id = '';
		}
		if (isset($_POST['ps'])) {
			$password = md5($_POST['ps']);
		} else {
			$password = '';
		}

		$r['login_id'] = $login_id;
		$r['password'] = $password;
		$token = '';
		
		if (isset($_POST['token']) && !$password) {
			// xdomain httpsでのログインで、通常http通信のトークンでの再ログイン
			$token = sanitize_str($_POST['token']);
			$r['token'] = $token;
			if ( $token ) {
				$ar = $database->sql_select_all("select id,mailadr,metadata,name from ".DBX."users where token = ? ", $token );
				if ( $ar && isset($ar[0]['metadata']) ) {
					$metadata = unserialize($ar[0]['metadata']);
					if ( isset($metadata['registration_d']) ) {
						if ( $metadata['registration_d']+60 > $_SERVER['REQUEST_TIME'] && $metadata['xdom_login']==$_SERVER["REMOTE_ADDR"] ) {	//メール経由ではないので制限時間を短く
							unset($metadata['registration_d']);
							unset($metadata['xdom_login']);
							if ( $database->sql_update("update ".DBX."users set token='',login_mode=1,metadata=? where token=?",serialize($metadata),$token) ) {
								//トークンの破棄、アカウントの有効化
								if ( system_login( '', '', $ar[0]['id'] ) ) {
									reset_attack();
									$r['result'] = true;
									$r['xuid'] = $_SESSION['login_id'];
									if (isset($params['circle']['meta']['login_write'])) {
										add_actionlog("[login] type-b ({$_SESSION['login_id']} : {$_SESSION['login_name']} : {$_SERVER['REMOTE_ADDR']})");
									} else {
										if ( isset($params['circle']['meta']['login_all']) ) {
											chg_infomail($config['admin_user'], "log in record(B) ({$_SESSION['login_id']} : {$_SESSION['login_name']} : {$_SERVER['REMOTE_ADDR']})");
										}
									}
								}
							}
						}
					}
				}
			}
			echo 'login error 1';
			//echo( json_encode($r) );
			exit();
		}

		$ar = $database->sql_select_all("select id,metadata,name from ".DBX."users where mailadr=? or name=? ", $login_id, $login_id);
		if (!$ar) {
			$x = avoid_attack(array('check'=>true));
			if (!empty($x['c'])) {
				$r['login_error'] = $x['c'];
				if ($r['login_error'] >= RETRY_COUNT) {
					avoid_attack(array('lock'=>true));
				}
			}
			echo 'login error 2';
			//echo( json_encode($r) );
			exit();
		}
		
		if (isset($ar[0]['metadata'])) {
			$metadata = unserialize($ar[0]['metadata']);
		} else {
			$metadata = array();
		}
		
		if (isset($metadata['login_error']) && $metadata['login_error'] > RETRY_COUNT) {
			if ($metadata['try_time'] > $_SERVER['REQUEST_TIME'] -60*60*3) {
				$r['mess'] = "ログイン失敗回数が一定数を超えました\nセキュリティ上しばらくログイン出来ません\n３時間以上時間を開けるか、パスワードの紛失手続きをしてください";
				echo( json_encode($r) );
				exit();
			}
			$metadata['login_error'] = 0;
		}

		if (system_login($login_id,$password)) {
			reset_attack();
			$r['result'] = true;
			$r['xuid'] = $_SESSION['login_id'];
			if (isset($params['circle']['meta']['login_write'])) {
				add_actionlog("[login] type-a ({$_SESSION['login_id']} : {$_SESSION['login_name']} : {$_SERVER['REMOTE_ADDR']})");
			} else {
				if (isset($params['circle']['meta']['login_all'])) {
					chg_infomail($config['admin_user'], "log in record(A) ({$_SESSION['login_id']} : {$_SESSION['login_name']} : {$_SERVER['REMOTE_ADDR']})");
				}
			}
			
			if (isset($_POST['xdom_login'])) {
				// https用のURLの場合は通常通信用のトークンを発行し再ログインする
				$ar = $database->sql_select_all("select metadata from ".DBX."users where id=? ",$_SESSION['login_id']);
				if ( !$ar ) {
					$r['result'] = false;
				} else {
					if (isset($ar[0]['metadata'])) {
						$metadata = unserialize($ar[0]['metadata']);
					} else {
						$metadata = array();
					}
					$token = make_registration( $metadata, $_SESSION['login_id'] );
					$metadata['xdom_login'] = $_SERVER["REMOTE_ADDR"];
					$r['xdom_login'] = true;
					$r['xuid'] = $_SESSION['login_id'];
					$r['token'] = $token;
					if (!$database->sql_update("update ".DBX."users set metadata=?,token=? where id=? ",serialize($metadata),$token,$_SESSION['login_id'])) {
						$r['result'] = false;
					}
				}
			}
			
		} else {
			$r['mess'] = 'ユーザーIDが存在しないか、パスワードが違います';

			// アカウントロックチェック
			$r['login_error'] = -1;
			$r['login_id'] = $login_id;
			if ($ar) {
				if (!isset($metadata['login_error'])) {
					$metadata['login_error'] = 0;
				}
				$r['login_error'] = ++$metadata['login_error'];
				$metadata['try_time'] = $_SERVER['REQUEST_TIME'] ;
				$r['id'] = $ar[0]['id'];
				if ($r['login_error'] >= RETRY_COUNT) {
					if (isset($params['circle']['meta']['login_alert'])) {
						chg_infomail($config['admin_user'], "[Account lock] {$ar[0]['name']}:{$ar[0]['id']}");
					} else {
						add_actionlog("[Account lock] {$ar[0]['name']}:{$ar[0]['id']}");
					}
				}
				$database->sql_update("update ".DBX."users set metadata=?,token=? where id=? ", serialize($metadata), $token, $r['id']);
			}
			$r['result'] = false;
		}
		echo( json_encode($r) );
		exit();
	}

	if (isset($_POST['ajax']) && ($_POST['ajax'] == 'make_account' || $_POST['ajax'] =='do_forget_rq')) {
		//新規アカウント作成
		$r = array();
		$r['result'] = false;

		if (isset($_POST['adr']) && !isset($params['circle']['meta']['dis_newacc'])) {
			
			$tx_to = sanitize_str($_POST['adr']);
			
			if ( isset($_POST['pass']) ) {
				$password = md5($_POST['pass']);
			} else {
				$password = md5('');
			}
			
			$ar = $database->sql_select_all("select mailadr,id,status,metadata from ".DBX."users where mailadr=? or name=? ",$tx_to, $tx_to);
			if (!$ar) {
				if ($_POST['ajax'] == 'make_account') {
					if ($database->sql_update("insert into ".DBX."users (mailadr,create_date,password,login_mode) values(?,?,?,?) ",$tx_to,$_SERVER['REQUEST_TIME'] ,$password,-1)) {
					}
					$id = $database->lastInsertId();
					$password = hash('sha256', $id.$password);
					if ($database->sql_update("update ".DBX."users set password=? where id=? ", $password, $id)) {
						$r['result'] = true;
					}
					$ar = $database->sql_select_all("select mailadr,id,status,metadata from ".DBX."users where mailadr=? ",$tx_to);
				}
				if (!$ar) {
					$r['result'] = true;
					$r['mode'] = $_POST['ajax'];
					echo(json_encode($r));
					exit();
				}
			}
			if (isset($ar[0]['metadata'])) {
				$metadata = unserialize($ar[0]['metadata']);
			} else {
				$metadata = array();
			}
			if ($password) {
				$metadata['new_pass']=$password;
			}
			$token = make_registration( $metadata, $tx_to );
			$date = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] );
			if ($database->sql_update("update ".DBX."users set metadata=?, create_date=?, token=? where id=?",serialize($metadata),$date,$token,$ar[0]['id'])) {
				if (new_account_sendmail($token, $tx_to)) {
					$reg='';
					$name=$tx_to;
					$r['result'] = true;
					$r['mode'] = $_POST['ajax'];
					if ($_POST['ajax'] =='do_forget_rq') {
						chg_infomail($config['admin_user'], "Lost Password - $tx_to ({$ar[0]['id']})");
					} else {
						chg_infomail($config['admin_user'], "New user - $tx_to ({$ar[0]['id']})");
					}

				} else {
				}
			}
		}
		echo( json_encode($r) );
		exit();
	}

	if ((isset($_GET['registration']) && $_GET['registration'])) {
		$p_registration = sanitize_str($_GET['registration']);
	} else {
		$p_registration = false;
	}

	if ($p_registration && !isset($_SESSION['login_id'])) {
		//登録用キーワード付URL確認
		$ar = $database->sql_select_all("select id,mailadr,metadata,login_mode from ".DBX."users where token = ? ", $p_registration );
		$ok = false;
		if ( $ar && isset($ar[0]['metadata']) ) {
			$metadata = unserialize($ar[0]['metadata']);
			$login_mode = $ar[0]['login_mode'];
			$mailadr = $ar[0]['mailadr'];
			if ( isset($metadata['registration_d']) ) {
				if ( $metadata['registration_d']+60*30 > $_SERVER['REQUEST_TIME']  ) {
					$metadata['registration_d']=0;
					unset($metadata['registration_d']);
					if ( isset($metadata['new_pass']) ) {
						$password = $metadata['new_pass'];
						unset($metadata['new_pass']);
						$ok = $database->sql_update("update ".DBX."users set token='',password=?,login_mode=1,metadata=? where token=?",$password,serialize($metadata),$p_registration);
					} else {
						$ok = $database->sql_update("update ".DBX."users set token='',login_mode=1,metadata=? where token=?",serialize($metadata),$p_registration);
					}
					if ( $ok ) {
						//トークンの破棄、アカウントの有効化
						if ( system_login( '', '', $ar[0]['id'] ) ) {
							if ( isset($params['circle']['meta']['login_write']) ) {
								add_actionlog("[login] type-c ({$_SESSION['login_id']} : {$_SESSION['login_name']} : {$_SERVER['REMOTE_ADDR']})");
							} else {
								if ( isset($params['circle']['meta']['login_all']) ) {
									chg_infomail($config['admin_user'], "log in record (C) ({$_SESSION['login_id']} : {$_SESSION['login_name']} : {$_SERVER['REMOTE_ADDR']})");
								}
							}
							$ok = true;
							if ($login_mode != -1) {
								$m = "パスワード紛失モードでログインしました<br />パスワードを変更してください";
							} else {
								$m = "アカウントを作成しました";
							}
							$home_option = array(
								'href'=>"{$config['site_ssl']}{$config['admin_dir']}/account.php?circle=$p_circle"
								, 'icon'=>false
							);
							exit_proc( 200, $m, $home_option );
						}
					}
				}
			}
		}
		if (!$ok) {
			if ( $database->sql_update("update ".DBX."users set token='' where token=?",$p_registration) ) {
				//トークンの破棄
				exit_proc(403,'トークンの有効期限が切れました', true);
			}
		}
	}

	// https ajax
	$login_id = https_nonlogin_mode();		// httpsでログインした状態のログインIDをトークンから取得する
	if (isset($_SESSION['login_id'])) {
		$login_id = $_SESSION['login_id'];
	} else {
		//return 'ログインしないとご利用いただけません';
		exit_proc(403);
	}
	
	if ($login_id) {
		$login_metadata = null;
		$otoken = https_login_mode( $login_metadata, $login_id );	// 以降で使用するトークンを取得する
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'read_account')  {
		if (isset($_GET['user']) && $_GET['user'] != $login_id && check_rights('admin')) {
			$login_id = (int)$_GET['user'];
		}
		$ar = $database->sql_select_all( "select mailadr,id,status,metadata,name,password,nickname from ".DBX."users where id=? ",$login_id );
		if ($ar && $ar[0]) {
		} else {
			system_error( __FILE__, __LINE__ );
		}
		header("Access-Control-Allow-Origin: *");
		if (isset($ar[0]['metadata'])) {
			$metadata = unserialize($ar[0]['metadata']);
		} else {
			$metadata = array();
		}
		if ($ar[0]['mailadr']) {
			$mailadr = $ar[0]['mailadr'];
		} else {
			$mailadr='';
		}
		if ($ar[0]['name']) {
			$name = $ar[0]['name'];
		} else {
			$name = '';
		}
		$r = array();
		$r['result'] = true;
		$r['adr'] = $mailadr;
		$r['name'] = $name;
		$nickname = $ar[0]['nickname'];
		if (!$nickname && isset($metadata['nickname'])) {
			$nickname = $metadata['nickname'];
		}
		if (!$nickname) {
			$nickname = 'Anonymous';
		}
		$r['nickname'] = $nickname;
		echo( json_encode($r) );
		exit();
	}
	
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'remove_session')  {
		$r = array();
		$r['result'] = false;
		if (isset($_GET['user']) && $_GET['user'] != $login_id && check_rights('admin')) {
			$login_id = (int)$_GET['user'];
		}
		$r['user'] = $login_id;
		$ar = $database->sql_select_all("select metadata from ".DBX."users where id=?", $login_id);
		if ($ar && $ar[0]['metadata'] && isset($_POST['id'])) {
			$m = unserialize($ar[0]['metadata']);
			if (isset($m['tokens2'][$_POST['id']])) {
				unset($m['tokens2'][$_POST['id']]);
			}
			if ($database->sql_update("update ".DBX."users set metadata=? where id=?",serialize($m),$login_id)) {
				$r['result'] = true;
			}
		}
		echo(json_encode($r));
		exit();
	}
	
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'save_account')  {
		$r = array();
		$r['result'] = true;
		
		if (isset($_GET['user']) && $_GET['user'] != $login_id && check_rights('admin')) {
			$login_id = (int)$_GET['user'];
		}

		if (isset($_POST['adr'])) {
			$a = sanitize_str( $_POST['adr'] );

			$ar = $database->sql_select_all( "select id from ".DBX."users where id<>? and mailadr=? ",$login_id, $a );
			if ($ar && $ar[0]) {
				$r['result'] = false;
				$r['mess'] = 'このメールアドレスはすでに登録されています';
				echo(json_encode($r));
				exit();
			}

			if ( !$database->sql_update( "update ".DBX."users set mailadr=? where id=?", $a, $login_id) ) {
				$r['result'] = false;
			}
		}

		unset($params['login_user']['meta']['nickname']);
		if ($database->sql_update("update ".DBX."users set metadata=? where id=?",serialize($params['login_user']['meta']),$_SESSION['login_id'])) {
		}

		$ar = $database->sql_select_all( "select name from ".DBX."users where id=? ",$login_id );
		if (!$ar || !$ar[0]) {
			$r['result'] = false;
			$r['mess'] = "system error.";
			echo(json_encode($r));
			exit();
		}
		$old_name = $ar[0]['name'];

		$nickname = '';
		if (isset($_POST['nickname'])) {
			$nickname = sanitize_str( $_POST['nickname'] );
			if ($nickname == 'Anonymous') {
				$nickname = '';
			}
			$params['login_user']['nickname'] = $nickname;
		}
		if (isset($_POST['name'])) {
			$name = sanitize_str( $_POST['name'] );
		} else {
			$name = '';
		}
		$ar = $database->sql_select_all( "select id from ".DBX."users where id<>? and name=? ",$login_id, $name );
		if ($ar && $ar[0]) {
			$r['result'] = false;
			$r['mess'] = "{$name}はご利用いただけません";
			echo(json_encode($r));
			exit();
		}
		if ($name != $old_name) {
			if (!$database->sql_update( "update ".DBX."users set name=? where id=?", $name, $login_id)) {
				$r['result'] = false;
				$r['mess'] = "system error.";
				echo(json_encode($r));
				exit();
			}
		}

		if ($nickname && $nickname != 'Anonymous') {
			$ar = $database->sql_select_all( "select id from ".DBX."users where id<>? and nickname=? ",$login_id, $nickname );
			if ($ar && $ar[0]) {
				$r['result'] = false;
				$r['mess'] = "{$nickname}はご利用いただけません";
				echo(json_encode($r));
				exit();
			}
			if (!$database->sql_update( "update ".DBX."users set nickname=? where id=?", $nickname, $login_id)) {
				$r['result'] = false;
				$r['mess'] = "system error.";
				echo(json_encode($r));
				exit();
			}
		}

		if (!empty($_POST['user_avatar'])) {
			$u = str_replace(array("\\","\"","'","<",">","\n","\r",'[',']','%'),"",$_POST['user_avatar']);
			$u = basename($u);
			if ($u != 'personal.png') {
				if (!$database->sql_update( "update ".DBX."users set img=? where id=?", $u, $login_id)) {
					$r['result'] = false;
					$r['mess'] = "system error.";
					echo(json_encode($r));
					exit();
				}
			}
		}

		if (!empty($_POST['pass'])) {
			$password = md5( $_POST['pass'] );
			$password = hash('sha256', $login_id.$password);
			if ( !$database->sql_update( "update ".DBX."users set password=? where id=?", $password, $login_id) ) {
				$r['result'] = false;
				$r['mess'] = "system error.";
				echo(json_encode($r));
				exit();
			}
		}
		
		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'save_option')  {
		$r = array();
		$r['result'] = true;
		
		$login_id = $_SESSION['login_id'];
		$m = $params['login_user']['meta'];
		if (isset($_GET['user']) && $_GET['user'] != $login_id && check_rights('admin')) {
			$login_id = (int)$_GET['user'];
			$ar = $database->sql_select_all("select metadata from ".DBX."users where id=?", $login_id);
			if ($ar) {
				$m = unserialize($ar[0]['metadata']);
			} else {
				system_error( __FILE__, __LINE__ );
			}
		}

		if (isset($_POST['editor'])) {
			$a = sanitize_asc( $_POST['editor'] );
			if (!$a) {
				unset($m['data']['editor']);
			} else {
				$m['data']['editor'] = $a;
			}
			if ($database->sql_update("update ".DBX."users set metadata=? where id=?",serialize($m),$login_id)) {
			}
		}

		if (isset($_POST['uploader'])) {
			$a = sanitize_asc( $_POST['uploader'] );
			if (!$a) {
				unset($m['data']['uploader']);
			} else {
				$m['data']['uploader'] = $a;
			}
			if ($database->sql_update("update ".DBX."users set metadata=? where id=?",serialize($m),$login_id)) {
			}
		}

		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'force_accont') {
		//管理者・編集者権限で、新規アカウント作成
		$r = array();
		$r['result'] = false;

		if (!check_rights('admin')) {
			if (!check_rights('edit') || empty($params['allow_editor_to_mod_users'])) {
				system_error( __FILE__, __LINE__ );
			}
		}

		if (isset($_POST['name'])) {
			
			$name = sanitize_str($_POST['name']);
			
			if ( $name && isset($_POST['pass']) ) {
				$password = md5($_POST['pass']);
				$ar = $database->sql_select_all("select mailadr,id,status,metadata from ".DBX."users where name=? ",$name);
				if ( !$ar ) {
					if ($database->sql_update("insert into ".DBX."users (name,create_date) values(?,?) ",$name,$_SERVER['REQUEST_TIME'] )) {
						$id = $database->lastInsertId();
						$password = hash('sha256', $id.$password);
						if ($database->sql_update("update ".DBX."users set password=? where id=? ", $password, $id)) {
							$r['result'] = join_circle($p_circle, $id, true);
						}
					}
				}
			}
			
		}
		echo(json_encode($r));
		exit();
	}

	//パンくず表示
	$params['breadcrumb'][] = array('link'=>"{$params['safe_request']}", 'text'=>'アカウント設定');

	$html['article'][] = draw_account($login_id);

	snippet_dialog();
	snippet_overlay();
	snippet_header();
	snippet_system_nav();
	snippet_footer();

	snippet_loginform(true);		// ログインフォーム設置
	expand_circle_html();

function system_login( $login_id, $password, $force_id=false )
{
	global $params, $config, $database, $p_circle, $html;
	if ($force_id) {
		//パスワード無しで強制ログイン（アカウント作成時に使う）
		$ar = $database->sql_select_all("select mailadr,id,status,metadata,name,nickname,password,login_mode,img from ".DBX."users where id=? ",$force_id);
	} else {
		// 通常ログイン
		$ar = $database->sql_select_all("select img,mailadr,id,status,metadata,name,nickname,password,login_mode from ".DBX."users where mailadr=? or name=? ", $login_id, $login_id);
		if ($ar) {
			$ps = hash('sha256', $ar[0]['id'].$password);
			if ($ar[0]['password'] !== '' && $ps !== $ar[0]['password']) {
				$ar = null;
			}
		}
	}
	if ($ar) {
		set_user_params($ar);

		$_SESSION['login_mode'] = 1;

		$sns_crypt = hash('sha1',$_SESSION['login_id'].$_SERVER['REQUEST_TIME'] .rand(100,10000));
		$sns_crypt2 = hash('sha1',$_SERVER["REMOTE_ADDR"]);

		$ar = $database->sql_select_all("select id from ".DBX."users where sns_crypt=? ",$sns_crypt);
		if ($ar && $ar[0]) {
			//hash が同一になる可能性は殆どないが、万一重複すると致命的
			system_error( __FILE__, __LINE__ );
		}
		set_cookie("otx0", $sns_crypt);
		set_cookie("otx1", $sns_crypt2);
		if (isset($params['circle']['meta']['mult_login'])) {
			set_cookie("otx_id", $_SESSION['login_id']);		// 2013/01/26 仕様変更により追加
		}
		save_loginmode(1);

		if (isset($params['login_user']['meta']['login_error'])) {
			unset($params['login_user']['meta']['login_error']);
			if ($database->sql_update("update ".DBX."users set metadata=? where id=?",serialize($params['login_user']['meta']),$_SESSION['login_id'])) {
			}
		}
		if (isset($params['circle']['meta']['mult_login'])) {
			set_user_token($sns_crypt);
			if ($database->sql_update("update ".DBX."users set metadata=? where id=?",serialize($params['login_user']['meta']),$_SESSION['login_id'])) {
			}
		}

		if ($database->sql_update("update ".DBX."users set login_date=?,sns_crypt=?,sns_crypt2=?,login_mode=1 where id=?", date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ),$sns_crypt,$sns_crypt2,$_SESSION['login_id'])) {
		}
		return true;
	}
	system_logout();
	return false;
}

function draw_account($login_id)
{
	global $params,$config,$database,$p_circle,$html,$request_uri,$otoken,$ut;

	if (check_rights('admin') && !empty($_GET['user'])) {
		$login_id = (int)$_GET['user'];
	}

	$buff = '';
	if (isset($_SESSION['login_mode']) && $_SESSION['login_mode']!=0) {
	} else {
		return 'ログインデータが不正です<br />ログアウトしてください';
	}
	
	$ar = $database->sql_select_all("select mailadr,id,status,metadata,name,password,img from ".DBX."users where id=? ",$login_id);
	if ($ar && $ar[0]) {
	} else {
		system_error( __FILE__, __LINE__ );
	}
	if (isset($ar[0]['metadata'])) {
		$metadata = unserialize($ar[0]['metadata']);
	} else {
		$metadata = array();
	}
	if ($ar[0]['password']) {
		$pass = $ar[0]['password'];
	} else {
		$pass='';
	}
	if ($ar[0]['name']) {
		$name = $ar[0]['name'];
	} else {
		$name = '';
	}
	$dis="";
	
	$p_tab = 0;
	if (isset($_GET['tab'])) {
		$p_tab = (int)$_GET['tab'];
	}
	$tab_ar = array();
	$tab_ar[0] = 'アカウント情報';
	$tab_ar[1] = 'オプション設定';
	$tab_ar[2] = 'Active session';
	
	https_login_script();

	if (check_rights('edit')) {
		$ux = '';
		if (!empty($_GET['user'])) {
			$ux = '&user='.(int)$_GET['user'];
		}
$buff.= <<<EOT
	<div class='c_tab' >
		<div class='onethird-tab'>
			<ul class='tab-head clearfix'>
EOT;
			foreach ($tab_ar as $key => $v) {
				if ($key == $p_tab) {
					$cls=" class='active' ";
				} else {
					$cls="";
				}
				$buff.= "<li $cls><a href='{$config['site_ssl']}{$config['admin_dir']}/account.php?tab=$key&amp;circle=$p_circle{$ux}'>$v</a></li>";
			}
$buff.= <<<EOT
			</ul>
			<div class='tab-body'>
EOT;

	} else {
$buff.= <<<EOT
	<div >
		<div>
			<div>
EOT;
	}
			if ($p_tab == 1) {
				$m = $params['login_user']['meta'];
				if(isset($_GET['user']) && check_rights('admin')) {
					$ar = $database->sql_select_all("select id,name,nickname,img,login_mode,metadata,mailadr from ".DBX."users where id=? ", (int)$_GET['user']);
					if ($ar && $ar[0]) {
						$m = unserialize($ar[0]['metadata']);
					}
				}

				$option0 = '';
				$sel = '';
				$e = '';
				if (isset($m['data']['editor'])) {
					$e = $m['data']['editor'];
				} else {
					$sel = 'selected';
				}
				$option0 .= "<option value='tryitEditor' $sel >tryitEditor(default)</option>";
				if (isset($params['circle']['meta']['data']['system_editors'])) {
					foreach ($params['circle']['meta']['data']['system_editors'] as $k=>$v) {
						$sel = '';
						if ($k == $e) {
							$sel = 'selected';
						}
						$option0 .= "<option value='$k' $sel >$v</option>";
					}
				}

				$option1 = "<option value=''>default</option>";
				$sel = '';
				$e = '';
				if (isset($m['data']['uploader'])) {
					$e = $m['data']['uploader'];
				} else {
					$sel = 'selected';
				}
				if (isset($params['circle']['meta']['data']['system_uploaders'])) {
					foreach ($params['circle']['meta']['data']['system_uploaders'] as $k=>$v) {
						$sel = '';
						if ($k == $e) {
							$sel = 'selected';
						}
						$option1 .= "<option value='$k' $sel >$v</option>";
					}
				}

$buff.= <<<EOT
				<div class='onethird-setting' >
					<form  method='post' id='form0' >
						<input type='hidden' name='mdx' id='mdx' value='mod_setting' />
						<table>
							<tr>
								<td>Editor</td>
								<td>
									<select class='editor'>
										$option0
									</select>
								</td>
							</tr>
							<tr>
								<td>Image uploader</td>
								<td>
									<select class='uploader'>
										$option1
									</select>
								</td>
							</tr>
							<tr>
								<td>
								</td>
								<td>
									<input type='button' value='更新' class='onethird-button' onclick='save_option()'/>
								</td>
							</tr>
						</table>
					</form>
				</div>
EOT;

			} else if ($p_tab == 2) {
				$m = $params['login_user']['meta'];
				if(isset($_GET['user']) && check_rights('admin')) {
					$ar = $database->sql_select_all("select id,name,nickname,img,login_mode,metadata,mailadr from ".DBX."users where id=? ", (int)$_GET['user']);
					if ($ar && $ar[0]) {
						$m = unserialize($ar[0]['metadata']);
					}
				}
				if (!isset($m['tokens2'])) {
$buff.= <<<EOT
					- not found.
EOT;
				} else {
					$ar = $m['tokens2'];
					foreach ($m['tokens2'] as $k=>$v) {
						$s = '';
						if (isset($_COOKIE['otx0']) && $k == $_COOKIE['otx0']) {
$buff.= <<<EOT
							<p style='font-weight: bold;'>
								{$v['date']} / {$v['ag']} / {$v['ip']}
							</p>
EOT;
						} else {
$buff.= <<<EOT
							<p style='$s'>
								{$v['date']} / {$v['ag']} / {$v['ip']}
								<a href='javascript:void(remove_session("$k"))' >
									{$ut->icon('delete')}</span>
								</a>
							</p>
EOT;
						}
					}
				}
			} else {
				if (check_rights('edit')) {
					$params['uploader']['uploader_resize'] = array(array(48,48,1));
					provide_edit_module();	// uploaderを使うため
					snippet_image_uploader();
				}
$buff.= <<<EOT
				<div class='onethird-setting' >
					<form  method='post' id='form0' >
						<input type='hidden' name='mdx' id='mdx' value='mod_account' />
						<table>
							<tr>
								<td>メールアドレス</td>
								<td>
									<input type='text' name='adr' id='adr' value=''/>
EOT;
									if ($_SESSION['login_mode']!=2) {
										if (!$ar[0]['mailadr']) {
											$buff.="<br /><span style='color:red;'>※ パスワードを忘れた時のためにメールアドレスが必要です</span>";
										} else {
											if (isset($metadata['not_auth'])) {
												$buff.=("<p>確認パスワード <input type='text' name='c_code' id='c_code' /></p>");
											}
										}
									}
									$error_class = '';
									$error_mess = '';
									if (!$ar[0]['name']) {
										$error_class = 'error';
										$error_mess = "<div class='help-inline' style='color:red;'>※ ユーザーIDが指定されていません</div>";
									}
$buff.= <<<EOT
								</td>
							</tr>
							<tr class='$error_class'>
								<td>ユーザーID</td>
								<td>
									<input type='text'  name='userid' id='userid'  value='' $dis  />
									$error_mess
								</td>
							</tr>
							<tr class='$error_class'>
								<td>ニックネーム</td>
								<td>
									<input type='text'  name='nickname' id='nickname'  value='' />
								</td>
							</tr>
EOT;
							if ($_SESSION['login_mode']==1) {
								$error_class = '';
								$error_mess = '';
								if (!$pass) {
									$error_class = 'error';
									$error_mess = "<div class='help-inline' style='color:red;'>パスワードが指定されていません</div>";
								}
$buff.= <<<EOT
								<tr class='$error_class'>
									<td >パスワード</td>
									<td>
										<input type='password'  name='password' id='password' value='' />
										$error_mess
									</td>
								</tr>
EOT;
							}
							$avatar = $ut->safe_echo(get_user_image($ar[0]));
							$style = '';
							if (substr($avatar,-12)=='personal.png') {
								$style .= " background-color: #006CB5; ";
							}
$buff.= <<<EOT
							<tr class='$error_class'>
								<td>avatar</td>
								<td>
									<div style='background-color: #c0c0c0;padding:1px;display: inline-block;'>
EOT;
									if (check_rights('edit')) {
$buff.= <<<EOT
										<img id='user_avatar' name='user_avatar' src='{$avatar}' onclick='ot.open_uploader({group:"avatar",resize:"auto",select:function(obj){set_avatar(obj)}})' style='cursor:pointer; $style' /> 
EOT;
									} else {
$buff.= <<<EOT
										<img id='user_avatar' name='user_avatar' src='{$avatar}' style='$style'  /> 
EOT;
									}
$buff.= <<<EOT
									</div>
								</td>
							</tr>
							<tr class='$error_class'>
								<td></td>
								<td>
									<input type='button' value='更新' class='onethird-button' onclick='save_account()'/>
									<input type='button' value='Logout' class='onethird-button' onclick='ot.system_logout()'/>
								</td>
							</tr>
						</table>
					</form>
				</div>
EOT;
			}

$buff.= <<<EOT
			</div>
		</div>
	</div>
	<p><br/></p>
EOT;

$html['meta'][] = <<<EOT
	<script>
	\$(function(){
		ot.overlay_encrypt(1);
		ajax_data = {
			type: "POST"
			, url: '{$request_uri}'
			, data: "ajax=start_encrypt&otoken={$otoken}&u={$login_id}"
			, dataType:'json'
			, success: function(data){
				ot.overlay_encrypt(0);
				if (data && data['result'] ) {
					ot.otoken=data['otoken'];
					read_account({$login_id});
				} else {
					alert('正常に暗号化通信出来ません(1)');
				}
			}
			, error: function(data,status){
				ot.overlay_encrypt(0);
				alert('正常に暗号化通信出来ません(2)');
			}
		};
		if (ot.msie && window.XDomainRequest && window.addEventListener) {
			\$('body').append("<iframe id='xdom' src='{$config['site_ssl']}xdom.php' scrolling='no' style='display:none'></iframe>");
			window.addEventListener("message", receive_xdom, false);

		} else {
			\$.ajax( ajax_data );
		}
	});
	
	function read_account() {
		ajax_data = {
			type: "POST"
			, url: '{$request_uri}'
			, data: "ajax=read_account"+'&otoken='+ot.otoken+'&u='+{$login_id}
			, dataType:'json'
			, success: function(data){
				ot.overlay_encrypt(0);
				if ( data && data['result'] ) {
					\$('#userid').val(data['name']);
					\$('#nickname').val(data['nickname']);
					\$('#adr').val(data['adr']);
				} else {
					alert('Data can not be sent.');
				}
			}
		};
		if (ot.msie && window.XDomainRequest && window.addEventListener) {
			window.addEventListener("message", receive_xdom, false);
		} else {
			\$.ajax( ajax_data );
		}
	}
	
	function save_account() {
		var opt = "ajax=save_account";
		var a = \$('#adr').val();
		if ( a ) { opt += "&adr="+encodeURIComponent(a); }
		a = \$('#userid').val();
		if ( a ) { opt += "&name="+encodeURIComponent(a); }
		a = \$('#nickname').val();
		if ( a ) { opt += "&nickname="+encodeURIComponent(a); }
		a = \$('#password').val();
		if ( a ) { opt += "&pass="+encodeURIComponent(a); }
		a = \$('#user_avatar')[0].src;
		if ( a ) { opt += "&user_avatar="+encodeURIComponent(a); }
		opt += '&otoken='+ot.otoken+'&u='+{$login_id};
		ajax_data = {
			type: "POST"
			, url: '{$request_uri}'
			, data: opt
			, dataType:'json'
			, success: function(data){
				ot.overlay_encrypt(0);
				if ( data && data['result'] ) {
					location.reload(true);
				} else {
					if (data['mess']) {
						alert(data['mess']);
					} else {
						alert('The data can not updated');
					}
				}
			}
		};
		if (ot.msie && window.XDomainRequest && window.addEventListener) {
			window.addEventListener("message", receive_xdom, false);
		} else {
			\$.ajax( ajax_data );
		}
	}
	function save_option() {
		var opt = "ajax=save_option";

		var a = \$('.onethird-setting .editor').val();
		opt += "&editor="+encodeURIComponent(a);

		a = \$('.onethird-setting .uploader').val();
		opt += "&uploader="+encodeURIComponent(a);

		opt += '&otoken='+ot.otoken+'&u='+{$login_id};
		ajax_data = {
			type: "POST"
			, url: '{$request_uri}'
			, data: opt
			, dataType:'json'
			, success: function(data){
				ot.overlay_encrypt(0);
				if ( data && data['result'] ) {
					location.reload(true);
				} else {
					if (data['mess']) {
						alert(data['mess']);
					} else {
						alert('The data can not updated');
					}
				}
			}
		};
		if (ot.msie && window.XDomainRequest && window.addEventListener) {
			window.addEventListener("message", receive_xdom, false);
		} else {
			\$.ajax( ajax_data );
		}
	}
	function remove_session(id) {
		if (!confirm('Are you sure you want to delete this session?')) { return; }
		var opt = "ajax=remove_session&id="+id;
		opt += '&otoken='+ot.otoken+'&u='+{$login_id};
		ajax_data = {
			type: "POST"
			, url: '{$request_uri}'
			, data: opt
			, dataType:'json'
			, success: function(data){
				ot.overlay_encrypt(0);
				if ( data && data['result'] ) {
					location.reload(true);
				} else {
					if (data['mess']) {
						alert(data['mess']);
					} else {
						alert('The data can not updated');
					}
				}
			}
		};
		if (ot.msie && window.XDomainRequest && window.addEventListener) {
			window.addEventListener("message", receive_xdom, false);
		} else {
			\$.ajax( ajax_data );
		}
	}
	function set_avatar(obj) {
		\$('#user_avatar')[0].src = obj.src;
		ot.close_dialog(\$('#onethird-uploader-dialog').hide(),true);
	}
	</script>
EOT;
	return frame_renderer($buff);
}

function make_registration( &$metadata, $tx_to )
{
	global $params,$config,$database,$p_circle,$html;
	
	$token = md5(rand(100,10000).$tx_to.$_SERVER['REQUEST_TIME'] );
	$metadata['registration_d'] = $_SERVER['REQUEST_TIME'] ;
	$ar = $database->sql_select_all( "select id from ".DBX."users where token=? ", $token );
	if ( $ar ) {
		// md5 が重なることはないが、確率0ではない
		system_error( __FILE__, __LINE__ );
	}
	return $token;
}

function new_account_sendmail($uid,$tx_to)
{

	global $params,$config,$database,$p_circle,$html,$plugin_ar;

	$subject="{$config['title']} - 登録確認";

	$header  = "From: {$config['site']['email']}";
	$msg = "

- {$config['title']}　登録　確認メール

アカウント設定リクエストを受け付けました

$tx_to

以下のURLをクリックしてください
{$config['site_ssl']}{$config['admin_dir']}/account.php?registration=$uid&plugin={$plugin_ar[ LOGIN_ID ]['selector']}

- このURLは30分で期限切れとなります

-- 

  {$config['title']}

  {$config['site_url']}


";

	if (USE_EMAIL) {
		if(mb_send_mail($tx_to, $subject, $msg, $header)){
		} else {
			return false;
		}
	}
	return true;
}
function is_session_started()
{
    if ( php_sapi_name() !== 'cli' ) {
        if ( version_compare(phpversion(), '5.4.0', '>=') ) {
            return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
        } else {
            return session_id() === '' ? FALSE : TRUE;
        }
    }
    return FALSE;
}

?>