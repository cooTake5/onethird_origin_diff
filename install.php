<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.

	global $ok_icon, $ng_icon, $waring_icon, $default_db, $onethird_tester, $params, $safe_request;

	@session_start();

	$permission = 0777;
	$htaccess_force = false;	// trueでhtaccessの自動判定を無効にする

	@include_once(dirname(__FILE__).'/config.php');
	if (isset($config)) {
		header("Location: index.php ");
		exit();
	}
	require_once(dirname(__FILE__).'/module/utility.php');

	$config['site_ssl']='';
	$database = false;
	$params['_hnd_sd'] = 1;
	require_once(dirname(__FILE__).'/plugin/plugin.php');

	if (is_file(dirname(__FILE__).'/install_sql.php')) {
		require_once(dirname(__FILE__).'/install_sql.php');
	}

	$default_db = false;

	$safe_request = $_SERVER['REQUEST_URI'];
	$safe_request = htmlspecialchars($safe_request, ENT_QUOTES, 'UTF-8');
	$safe_request = str_replace(array('"',"'","<",">","%"), "", $safe_request);

	$sqlite_dir = getcwd();
	$f = $sqlite_dir.DIRECTORY_SEPARATOR.'config.tpl';
	if (is_file($f)) {
		$hnd = @fopen($f, "r");
		if ($hnd) {
			for ($i=0; $i < 100;++$i) {
				$ln = fgets($hnd, 500);
				if ($ln === false) { break; }
				if (preg_match("/[ \\t]*DATABASE_DIR[ =]+(.*)$/mu", $ln, $m)) {
					if (isset($m[1])) {
						$sqlite_dir = $m[1];
					}
					break;
				}
			}
			fclose($hnd);
		}
	}

$site_title = <<<EOT
	<span style="font-family:'Meiryo','Verdana'">OneThird </span>
	<span style="font-family:'Verdana'; color:#FDDF00;">CMS</span>
EOT;
	if (is_file($sqlite_dir.DIRECTORY_SEPARATOR.'onethird.db')) {
		$default_db = true;
		$default_db = new DataBase('sqlite','','','',$sqlite_dir);
		if (!$default_db->open("onethird")) {
			exit_proc(400, "database open error");
		}
		$ar = $default_db->sql_select_all("select metadata,name from circles where id=? ", 1);
		if (!$ar) {
			exit_proc(400, "database access error");
		}
		$site_title = $ar[0]['name'];
	}

	if (!function_exists('mb_internal_encoding')) {
		echo('undefined function mb_internal_encoding');
		die();
	}
	mb_internal_encoding('UTF-8');
	ini_set('display_errors', 1);
	error_reporting(E_ALL | E_NOTICE);


	$ok_icon = "<img src='img/ok.png' />";
	$ng_icon = "<img src='img/delete.png' />";
	$waring_icon = "<img src='img/caution.png' width='20px' />";
	$url = 'http://'.$_SERVER["HTTP_HOST"].$safe_request;
	if (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"]!='off') {
		$url = 'https://'.$_SERVER["HTTP_HOST"].$safe_request;
	}
	$url = substr($url,0,-strlen('install.php'));
	$url = trim($url,'/');
	$upload_path = getcwd().DIRECTORY_SEPARATOR.'files';
	$upload_url = $url.'/files';
	$root_path = getcwd();
	$onethird_tester = false;

	$cdir = getcwd();

	$mod_rewrite = $mysql = $sqlite = $php_zip = false;
	ob_start();
	phpinfo();
	$out = ob_get_contents();
	ob_end_clean();
	if (strstr($out,'pdo_sqlite')) {
		$sqlite = true;
	}
	if (strstr($out,'pdo_mysql')) {
		$mysql = true;
	}
	if (strstr($out,'Zip version')) {
		$php_zip = true;
	}
	if (strstr($out,'mbstring')) {
		$php_mb_string = true;
	}
	if (strstr($out,'OneThird Web Server based on Mongoose') || strstr($out,'OneThird Server based on Mongoose')) {
		$onethird_tester = true;
		$htaccess_force = true;
	}

	if (!isset($_SESSION['install'])) {

		$ut = new Ut($html, $database, $p_circle, $params, $config);

		if (isset($_POST['ajax']) && $_POST['ajax']=='install_start' && isset($_POST['mode'])) {
			$r = array();
			$_SESSION['install'] = $mode = (int)$_POST['mode'];
			$r['result'] = true;
			echo( json_encode($r) );
			exit();
		}

		//htaccess チェック
		if (!$htaccess_force) {
			if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
$htaccess = <<<EOT

	order deny,allow
	deny from all
	allow from {$_SERVER['REMOTE_ADDR']}

EOT;
			}

$htaccess = <<<EOT

$htaccess

RewriteEngine On
RewriteBase /
RewriteRule htaccess_test.txt "http://onethird.net/htaccess_test.txt"
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule (.*) http://onethird.net/htaccess_test.txt

ErrorDocument 403 http://onethird.net/htaccess_test.txt
ErrorDocument 404 http://onethird.net/htaccess_test.txt

EOT;

			$htaccess_write = false;
			$install_ok = false;
			if (@file_put_contents('.htaccess', $htaccess)) {
				$htaccess_write = true;
				$install_ok = true;
			}
		} else {
			$install_ok = true;
		}

$tmp = <<<EOT
		<p>Please check the following before installation.</p>
EOT;
		$var = explode('.',PHP_VERSION);
		$tmp .= "<p>1.php 5.3.0 or higher ";
		$a = ((int)$var[0])*1000 + ((int)$var[1])*100 + ((int)$var[2]);
		if ($a < 5300) {
			$tmp .= " ... (".PHP_VERSION." NG".$ut->icon('ng').")";
		} else {
			$tmp .= " ... (".PHP_VERSION." OK".$ut->icon('ok').")";
		}
		$tmp .= "</p>";

		if ($onethird_tester) {
$f = <<<EOT
<?php
	\$r = dirname(__FILE__)."/files/img/{\$_GET['p']}/{\$_GET['i']}";
	if (is_file(\$r)) {
		\$r = "files/img/{\$_GET['p']}/{\$_GET['i']}";
	} else {
		//\$r = "http://url/files/img/{\$_GET['p']}/{\$_GET['i']}";
		die();
	}
	header("Location:".\$r);
?>
EOT;
			if (@file_put_contents('img.php', trim($f))) {
			} else {
			}
		}
		if ($htaccess_force) {
			$mod_rewrite = true;
		} else {
			$u = trim(substr($safe_request,0,-strlen(basename(__FILE__)))," /");
			if ($u) {
				$u = "/$u";
			}
			$u = "http://{$_SERVER['SERVER_NAME']}{$u}/htaccess_test.txt";
			$b = @file_get_contents($u);
			if ($b == 'onethird cms') {
				$mod_rewrite = true;
			} else {
				if (strstr($out,'mod_rewrite') !== false) {
					$mod_rewrite = true;
					$mod_rewrite_warning = true;
				}
			}
		}

		$db_ng_icon = $waring_icon;
		if (!$mysql && !$sqlite) {
			$db_ng_icon = $ng_icon;
		}

$tmp .= <<<EOT
		<p>2.pdo_sqlite enabled
EOT;

		if ($sqlite) {
			$tmp .= " ... (SQLite OK".$ut->icon('ok').")";
		} else {
			$tmp .= " ... (SQLite NG $db_ng_icon)";
		}
		$tmp .= "</p>";

$tmp .= <<<EOT
		<p>3.pdo_mysql enabled
EOT;
		if ($mysql) {
			$tmp .= " ... (MySQL OK".$ut->icon('ok').")";
		} else {
			$tmp .= " ... (MySQL NG $db_ng_icon)";
		}
		$tmp .= "</p>";

$tmp .= <<<EOT
		<p>4.apache mod_rewrite module is enabled
EOT;
		if ($mod_rewrite) {
			$tmp .= " ... (mod_rewrite OK".$ut->icon('ok').")";
		} else {
			$tmp .= " ... (mod_rewrite NG".$ut->icon('delete').")";
			$install_ok = false;
		}
		$tmp .= "</p>";

$tmp .= <<<EOT
		<p>5.htaccess write check
EOT;
		if ($htaccess_force || $htaccess_write) {
			$tmp .= " ... (write OK".$ut->icon('ok').")";
		} else {
			$tmp .= " ... (write NG".$ut->icon('delete').")";
			$install_ok = false;
		}

$tmp .= <<<EOT
		<p>6.install.php permission check
EOT;
		if (is_writable('install.php')) {
			$tmp .= " ... (write OK".$ut->icon('ok').")";
		} else {
			$tmp .= " ... (write NG".$ut->icon('delete').")";
			$install_ok = false;
		}

$tmp .= <<<EOT
		<p>7.css/onethird.8.css permission check
EOT;
		if (is_writable('css/onethird.8.css')) {
			$tmp .= " ... (write OK".$ut->icon('ok').")";
		} else {
			$tmp .= " ... (write NG".$ut->icon('delete').")";
			$install_ok = false;
		}

$tmp .= <<<EOT
		<p>8.Zip support
EOT;
		if ($php_zip) {
			$tmp .= " ... (OK".$ut->icon('ok').")";
		} else {
			$tmp .= " ... (NG $db_ng_icon)";
		}
		$tmp .= "</p>";

$tmp .= <<<EOT
		<p>9.Locale check
EOT;
		if (setlocale(LC_ALL, 'ja_JP.UTF-8') !== false) {
			$tmp .= " ... (OK".$ut->icon('ok').")";
		} else {
			$tmp .= " ... (NG $db_ng_icon)";
		}
		$tmp .= "</p>";

		if (isset($mod_rewrite_warning)) {
$tmp .= <<<EOT
			<div style='background-color: #F7BFBF;padding: 10px;margin-top:10px;'>
				Warning : Pleace check apache mod_rewrite
			</div>
EOT;
		}

$tmp .= <<<EOT
		<div class='action' >
EOT;
			if ($install_ok) {
				if ($default_db) {
					$tmp .="<input type='button' class='onethird-button large' value='次へ' onclick='install(0)' />";
$tmp .= <<<EOT
					<script>
						\$(function(){
							install(0);
						});
					</script>
EOT;
				} else {
					if ($sqlite) {
						$tmp .="<input type='button' class='onethird-button large' value='インストール (SQLite) &raquo;' onclick='install(0)' />";
					}
					if ($mysql) {
						$tmp .="<input type='button' class='onethird-button large' value='インストール (MySQL) &raquo;' onclick='install(1)' />";
					}
					$tmp .="<input type='button' class='onethird-button' value='Change language &raquo;' onclick='void(change_language())' />";
				}
			} else {
				$tmp .="<input type='button' class='onethird-button ' value='Error' onclick='location.reload(true)' />";
			}

$tmp .= <<<EOT
		</div>
		<script>
			\$(function(){
				\$('.main-unit').hide().fadeIn(500);
			});
			function install(a) {
EOT;
			if (!$mod_rewrite) {
$tmp .= <<<EOT
				if (!confirm('Error remains, Do you want to continue?')) {
					return;
				}
EOT;
			}
$tmp .= <<<EOT
				\$.ajax({
					type: "POST"
					, url: 'install.php'
					, data: "ajax=install_start&mode="+a
					, dataType:'json'
					, success: function(data){
						if ( data && data['result'] ) {
							location.reload(true);
						} else {
						}
					}
				});
			}
			function change_language() {
				location.href="language.php";
			}
		</script>
EOT;
		$tmp .= "</p>";

	} else if (isset($_POST['mdx']) && $_POST['mdx'] == 'do_install') {

		$result = $r['result'] = false;
		$postmess = array();
		$stop = false;
		$p_pass = '';

		if ($_SESSION['install'] == 1) {
			$postmess[] = "{$ok_icon}MySQL:Start installation";
		} else {
			$postmess[] = "{$ok_icon}SQLite:Start installation";
		}
		if ($default_db) {
			$_SESSION['install_ar']['p_db'] = $p_db = "onethird";
			$p_user = 1;
		} else {
			if (isset($_POST['p_user']) && $_POST['p_user']) {
				$_SESSION['install_ar']['p_user'] = $p_user = sanitize_str( $_POST['p_user'] );
			} else {
				$postmess[] = "{$ng_icon}Administrator ID is not set";
				$stop = true;
			}
			if (isset($_POST['p_pass']) && $_POST['p_pass']) {
				$p_pass = sanitize_str( $_POST['p_pass'] );
				if ( $p_pass != $_POST['p_pass'] ) {
					$postmess[] = "{$ng_icon}Contains characters that can not be used in passwords";
					$stop = true;
				}
			}
			if (isset($_POST['p_db']) && $_POST['p_db']) {
				$_SESSION['install_ar']['p_db'] = $p_db = sanitize_str( $_POST['p_db'] );
			} else {
				$postmess[] = "{$ng_icon}The database name is not set";
				$stop = true;
			}
		}
		if ($_SESSION['install'] == 1) {
			if ( isset($_POST['p_dbuser']) && $_POST['p_dbuser'] ) {
				$_SESSION['install_ar']['p_dbuser'] = $p_dbuser = sanitize_str( $_POST['p_dbuser'] );
			} else {
				$postmess[] = "{$ng_icon}Database user name is not set";
				$stop = true;
			}
			if ( isset($_POST['p_dbpass']) && $_POST['p_dbpass'] ) {
				$p_dbpass = sanitize_str( $_POST['p_dbpass'] );
			} else {
				$postmess[] = "{$waring_icon}Database password is not set";
				$p_dbpass = '';
			}
			if (isset($_POST['p_dbhost']) && $_POST['p_dbhost']) {
				$_SESSION['install_ar']['p_dbhost'] = $p_dbhost = sanitize_str( $_POST['p_dbhost'] );
			}
			if (!$p_dbhost) {
				$postmess[] = "{$waring_icon}Database host name is not specified ... 127.0.0.1 is seledted.";
				$p_dbhost = "127.0.0.1";
			}
		}

		if (isset($_POST['p_prefix'])) {
			$p_prefix = sanitize_str( $_POST['p_prefix'] );
		}

		if ($stop) {
			$postmess[] = 'Installation Failed';

		} else {
			$xparam = array();
			$xparam['p_db'] = $p_db;

			$xparam['p_dbpass'] = $xparam['p_dbuser'] = $xparam['p_user'] = $xparam['p_pass'] = $xparam['p_dbhost'] = $xparam['p_prefix'] = '';

			if ($_SESSION['install'] == 1) {
				$xparam['p_dbpass'] = $p_dbpass;
				$xparam['p_dbuser'] = $p_dbuser;
				$xparam['p_user'] = $p_user;
				$xparam['p_pass'] = $p_pass;
				$xparam['p_dbhost'] = $p_dbhost;
				$xparam['p_prefix'] = $p_prefix;
			} else {
				$xparam['p_user'] = $p_user;
				$xparam['p_pass'] = $p_pass;
				$xparam['p_prefix'] = '';
			}

			$url = trim($url,'/');
			$xparam['url'] = $url.'/';
			$xparam['upload_path'] = $upload_path;
			$xparam['root_path'] = $root_path;
			$xparam['upload_url'] = $upload_url;

			if ( !is_dir($xparam['upload_path']) && !@mkdir($xparam['upload_path'],$permission)  ) {
				$postmess[] = "{$ng_icon}Files folder creation failed.";
				$result = false;
			} else {
				if (fileperms($upload_path) != $permission) {
					@chmod($upload_path, $permission);
				}
				$result = create_onethird( $xparam, $postmess );
				if ($result === true) {
					$r['result'] = true;

					require_once(dirname(__FILE__).'/config.php');				// utility.edit.phpを使うためにインクルード
					//オプションセット
					if (isset($_POST['p_jquery_cdn'])) {
						$postmess[] = "{$ok_icon}Adjusted jQuery option ";
						$ar = $database->sql_select_all("select metadata,owner,name,join_flag,id,cid,public_flag from ".DBX."circles where id=? ", (int)$p_circle);
						$params['circle']['meta'] = unserialize64($ar[0]['metadata']);
						if ($_POST['p_jquery_cdn']=='cdn') {
							$params['circle']['meta']['jquery_url']='//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js';
						} else {
							$params['circle']['meta']['jquery_url']='';
						}
						if ($database->sql_update( "update ".DBX."circles set metadata=? where id=?", serialize64($params['circle']['meta']), $p_circle)) {
						}
					}
				} else if ($result === 1) {
				}

				if ($result===1 || $result===true) {
					if (isset($_POST['p_blog_template']) && $_POST['p_blog_template']=='on') {
						$postmess[] = "{$ok_icon}Adjusted template option ";
						$page_ar = array();
						if (!read_pagedata($params['circle']['meta']['top_page'], $page_ar)) {
							$postmess[] = "{$waring_icon}data mod error(1).";
						}
						$page_ar['meta']['template_ar']['tpl'] = 'blog_top.tpl';
						$page_ar['type'] = 180;
						if (empty($page_ar['meta']['flexedit']['blog_side_top'])) {
$page_ar['meta']['flexedit']['blog_side_top'][0]['text'] = <<<EOT
							<p class="title">Recent Posts</p>
							<ul>
								{\$call('blog_recent_posts')}
							</ul>
EOT;
$page_ar['meta']['flexedit']['blog_side_top'][1]['text'] = <<<EOT
							<p class="title">Tag List</p>
							<ul>
								{\$call('blog_tags')}
							</ul>
EOT;
$page_ar['meta']['flexedit']['blog_side_top'][2]['text'] = <<<EOT
							<p class="title">Search</p>
								<span class="screen-reader-text">Search for:</span>
								{\$plugin('search')}
EOT;
						}
						if (!mod_data_items($page_ar)) {
							$postmess[] = "{$waring_icon}data mod erro(2)r.";
						}
						regenerate_attached($page_ar['id']);
						if (!$database->sql_update("update ".DBX."data_items set type=? where id=?", $page_ar['type'] , $page_ar['id'] )) {
							$postmess[] = "{$waring_icon}data mod error(3).";
						}
						
					}
					$postmess[] = "{$ok_icon}Congratulations! Installation was successful.";
				} else {
					$postmess[] = "{$ng_icon}Installation failed.";
				}
			}
		}

$tmp = <<<EOT
		<div class='onethird-setting' >
			<div class='install_log'>
EOT;
			foreach ($postmess as $v) {
$tmp .= <<<EOT
				<p class='log_hidden' >$v</p>
EOT;

			}
$tmp .= <<<EOT
			</div>
			<div class='action' >
EOT;
				if ($result) {
$tmp .= <<<EOT
					<input type='button' class='onethird-button large' value='OneThird CMSを開始する' onclick="location.href='.';" />
EOT;
				} else {
$tmp .= <<<EOT
					<input type='button' class='onethird-button large' value='戻る' onclick="location.href='install.php'" />
EOT;
				}
$tmp .= <<<EOT
			</div>
		</div>
		<script>
			\$(function(){
				\$('.action input').hide();
				show_logs();
			});
			function show_logs() {
				var a = \$('.log_hidden:first');
				if (a && a.length > 0) {
					a.hide().removeClass('log_hidden').fadeIn(300, function(){show_logs()});
					\$('.install_log').scrollTop(\$('.install_log').height());
				} else {
					\$('.action input').show();
				}
			}
		</script>
EOT;

	} else {

		if (isset($_POST['ajax']) && $_POST['ajax']=='restart') {
			$r = array();
			unset($_SESSION['install']);
			$r['result'] = true;
			echo( json_encode($r) );
			exit();
		}

		$p_user = $p_pass = '';
		$p_db = '';
		if ($_SESSION['install'] != 1) {
			$p_db = 'onethird';
		}
		$p_dbuser = '';
		$p_dbhost = 'localhost';
		$p_prefix = 'ot_';
		$mode = 1;
		$p_dbpass = '';

		if (isset($_SESSION['install_ar']['p_user'])) { $p_user = $_SESSION['install_ar']['p_user']; }
		if (isset($_SESSION['install_ar']['p_db'])) { $p_db = $_SESSION['install_ar']['p_db']; }
		if (isset($_SESSION['install_ar']['p_dbuser'])) { $p_dbuser = $_SESSION['install_ar']['p_dbuser']; }
		if (isset($_SESSION['install_ar']['p_dbhost'])) { $p_dbhost = $_SESSION['install_ar']['p_dbhost']; }
		
		if (empty($ut)) {
			$ut = new Ut($html, $database, $p_circle, $params, $config);
		}

$tmp = <<<EOT
		<form class='onethird-setting' id='install_form' method="post" action='{$safe_request}' >
			<input type='hidden' name='mdx' value='do_install' />
			<table class='' style='width:100%' >
				<tr>
					<td>CurrentDirectory</td>
					<td>$cdir</td>
				</tr>
				<tr>
					<td>Install url</td>
					<td>$url</td>
				</tr>
EOT;
				if ($default_db) {
$tmp .= <<<EOT
					<tr>
						<td>
							database
						</td>
						<td>
							<div >
EOT;
								if ($onethird_tester) {
$tmp .= <<<EOT
									current database
EOT;
								}
$tmp .= <<<EOT
							</div>
						</td>
					</tr>
EOT;
				} else {
$tmp .= <<<EOT
					<tr>
						<td>Admin ID</td>
						<td>
							<input type='text' name='p_user' id='p_user' placeholder='ID for the administrator' value='{$p_user}' />
						</td>
					</tr>
					<tr>
						<td>Admin password</td>
						<td><input type='text' name='p_pass' id='p_pass' placeholder='Password' value='{$p_pass}' />
						</td>
					</tr>
					<tr>
						<td>Database Name</td>
						<td><input type='text' name='p_db' id='p_db' value='{$p_db}' />
						</td>
					</tr>
EOT;
				}
				if ($_SESSION['install'] == 1) {
$tmp .= <<<EOT
					<tr>
						<td>Database User Name</td>
						<td>
							<input type='text' name='p_dbuser' id='p_dbuser' placeholder='Database User Name' value='{$p_dbuser}' />
						</td>
					</tr>
					<tr>
						<td>Database password</td>
						<td>
							<input type='text' name='p_dbpass' id='p_dbpass' placeholder='Database Password' value='{$p_dbpass}' />
						</td>
					</tr>
					<tr>
						<td>Database Host name</td>
						<td>
							<input type='text' name='p_dbhost' id='p_dbhost' placeholder='Database Host' value='{$p_dbhost}' />
						</td>
					</tr>
					<tr>
						<td>Database-prefix</td>
						<td><input type='text' name='p_prefix' id='p_prefix' value='{$p_prefix}' />
						</td>
					</tr>
EOT;
				} else {
$tmp .= <<<EOT
					<tr>
						<td>Database Path</td>
						<td>
							<input type='text' name='p_dbuser' id='p_dbuser' placeholder='Database User Name' value='{$sqlite_dir}' disabled />
						</td>
					</tr>
EOT;
				}
$tmp .= <<<EOT
				<tr>
					<td>Install option</td>
					<td>
						<ul>
							<li><label><input type='radio' name='p_jquery_cdn' id='p_jquery_cdn' value='cdn' >Change jQuery CDN setting</label></li>
							<li><label><input type='radio' name='p_jquery_cdn' id='p_jquery_cdn' value='inner' >Change jQuery inner setting</label></li>
							<li><label><input type='checkbox' name='p_blog_template' id='p_blog_template' >Change Blog style template </label></li>
						</ul>
					</td>
				</tr>
			</table>
EOT;
				if ($default_db) {
$tmp .= <<<EOT
					<p style='color:red;font-weight:bold;text-align: center;padding-top: 30px;'>
						インストール後、インストールファイル(install.php, language.php)は削除してください
					</p>
					<div class='action' >
						<input type='button' class='onethird-button large' value='config.php作成' onclick='do_install()' />
					</div>
EOT;
				} else {
$tmp .= <<<EOT
					<div class='action' >
						<input type='button' class='onethird-button large' value='戻る' onclick='restart(0)' />
						<input type='button' class='onethird-button large' value='インストール開始 &raquo;' onclick='do_install()' />
						<input type='button' class='onethird-button' value='Change language &raquo;' onclick='void(change_language())' />
					</div>
EOT;
				}
$tmp .= <<<EOT
		</form>
		<script>
			\$(function(){
				\$('.main-unit').hide().fadeIn(500);
			});
			function restart(a) {
				\$.ajax({
					type: "POST"
					, url: 'install.php'
					, data: "ajax=restart"
					, dataType:'json'
					, success: function(data){
						if ( data && data['result'] ) {
							location.reload(true);
						} else {
						}
					}
				});
			}
			function do_install() {
				\$('#install_form').submit();
			}
			function change_language() {
				location.href="language.php";
			}
		</script>
EOT;
	}


$buff= <<<EOT
<!DOCTYPE html>
<html lang="ja">
	<head>
	<meta charset="utf-8">
	<title>Installing OneThird-CMS</title>
	<link href="css/onethird.8.css" rel="stylesheet">
	<script src="js/jquery.min.js"></script>
	<style>
	body {
		background-color: #e0e0e0;
	  font-family: "Lucida Sans Unicode", "Lucida Grande", sans-serif;
	}
	.box {
	  border: 1px solid #ddd;
	  margin-bottom:1px;
	}
	.error{
	  color:red;
	}
	.main-unit {
		position: relative;
		background-color: #f0f0f0;
		width:800px;
		margin:50px auto 30px auto;
		margin-bottom: 2px;
		line-height:200%;
		border: 5px solid #AAA7A7;
		padding:150px 30px 150px 30px;
		min-height:350px;
	}
	.main-unit p{
		line-height:150%;
		padding:0;
		margin:0;
	}
	.title {
		background-color: #286593;
		font-size: 25px;
		padding: 15px;
		color: white;
		position: absolute;
		width: 820px;
		margin: 5px;
		top: 0;
		left: 0;
		height: 69px;
	}
	.params {
		display:inline-block;
		-webkit-border-radius: 6px;
		-moz-border-radius: 6px;
		border-radius: 6px;
		border: 10px solid #AAA7A7;
		width:300px;
		height:1em;
		text-align: right;
		overflow:hidden;
	}
	.action {
		position: absolute;
		background: #BFC0C2;
		bottom: 0;
		left: 0;
		height: 69px;
		width: 808px;
		margin: 5px;
		padding: 21px;
		text-align: center;
		padding-top: 30px;
	}
	.action input[type=button] {
		height:50px;
		vertical-align: middle;
	}
	.install_log {
		border: 1px solid #AAA7A7;
		height: 301px;
		overflow-y: scroll;
		padding:20px;
	}
	.log_hidden {
		display:none;
	}
	.rem {
		font-size:12px;
		color:#ADABAB;
	}
	#onethird-error {
		display:none;
	}
	</style>
	</head>
	<body class='c_body'>
		<div class='main-unit'>
			<div class='title'>
				<img src='css/onethird121.png' style='width:70px;vertical-align: middle;'/>
				$site_title
			</div>
			$tmp
		</div>
	</body>
</html>
EOT;

echo($buff);


function create_onethird( &$xparam, &$postmess )
{
	global $params, $p_circle, $database, $config, $ut,$permission;
	global $sqlite_dir, $html;
	global $ok_icon, $ng_icon, $waring_icon, $default_db,$onethird_tester;

	if ($_SESSION['install'] == 1) {
		$database = new DataBase( 'mysql', $xparam['p_dbuser'], $xparam['p_dbpass'], $xparam['p_dbhost'] );
		if ( !$database ) {
			$postmess[] = "{$ng_icon}Can not connect to the database.";
			return 0;
		}
		if ( !$database->open($xparam['p_db']) ) {
			$postmess[] = "{$ng_icon}Database can not be opened({$xparam['p_db']}).";
			return 0;
		}
		if (function_exists('init_sql_mysql')) {
			$sql = init_sql_mysql($xparam);
		} else {
			$sql = init_sql_mysql_def($xparam);
		}
		$ar = explode( ';', $sql);
		$postmess[] = "{$ok_icon}OK Database MySQL";
	} else {
		$database = new DataBase( 'sqlite', '', '', '', $sqlite_dir);
		if (!$database) {
			$postmess[] = "{$ng_icon}Can not connect to the database";
			return 0;
		}
		if ( !$database->open($xparam['p_db']) ) {
			$postmess[] = "{$ng_icon}Can not write database file, check permission '{$xparam['p_db']}.db'. ";
			return 0;
		}
		if (function_exists('init_sql_sqlite')) {
			$sql = init_sql_sqlite($xparam);
		} else {
			$sql = init_sql_sqlite_def($xparam);
		}
		$ar = explode( ';', $sql);
		$postmess[] = "{$ok_icon}OK Database sqlite";
	}

	if ($default_db) {
		$xparam['admin_user'] = $user = 1;
		$p_circle = $xparam['circle'] = 1;
	} else {
		$database->sql_begin();
		foreach ($ar as $v) {
			$v = trim($v);
			if ($v) {
				if ($database->sql_update( $v )) {
					$postmess[] = "{$ok_icon}Create a table <span class='rem'>(".substr($v,0,40)."...)</span>";
				} else {
					$postmess[] = "{$waring_icon}Could not create table <span class='rem'>(".substr($v,0,40)."...)</span> Proceed...";
				}
			}
		}
		$database->sql_commit();

		//ユーザー追加
		$ar = $database->sql_select_all(" select id from ".$xparam['p_prefix']."users where name='{$xparam['p_user']}' ");
		if (!$ar) {
			if ($database->sql_update("insert into ".$xparam['p_prefix']."users (name) values(?)",  $xparam['p_user'])) {
				$id = $database->lastInsertId();
				$password = hash('sha256', $id.md5($xparam['p_pass']));
				if (!$database->sql_update("update ".$xparam['p_prefix']."users set password=? where id=? ", $password, $id)) {
					$postmess[] = "{$ng_icon}The database can not be written. ({$xparam['p_db']})-001";
					return 1;
				}
			} else {
				$postmess[] = "{$ng_icon}The database can not be written. ({$xparam['p_db']})-002";
				return 1;
			}
		}
		$ar = $database->sql_select_all( " select id from ".$xparam['p_prefix']."users where name='{$xparam['p_user']}' " );
		if (!$ar) {
			$postmess[] = "{$ng_icon}Access is denied. ({$xparam['p_db']} )-003";
			return 1;
		}
		$xparam['admin_user'] = $user = $ar[0]['id'];
		$postmess[] = "{$ok_icon}Add user OK";

		//デフォルトサークル追加
		$ar = $database->sql_select_all( " select id from ".$xparam['p_prefix']."circles limit 1" );
		if (!$ar) {
			if ( !$database->sql_update("insert into ".$xparam['p_prefix']."circles (name,owner,join_flag) values(?,?,2)", 'OneThird CMS', $user) ) {
				$postmess[] = "{$ng_icon}Access is denied. {$xparam['p_db']}-004";
				return 1;
			}
		}
		$ar = $database->sql_select_all( " select id from ".$xparam['p_prefix']."circles limit 1" );
		if (!$ar) {
			$postmess[] = "{$ng_icon}Access is denied. {$xparam['p_db']}-005";
			return 1;
		}
		$p_circle = $xparam['circle'] = $ar[0]['id'];

		$ar = $database->sql_select_all( " select id from ".$xparam['p_prefix']."joined_circle limit 1" );
		if (!$ar) {
			if (!$database->sql_update("insert into ".$xparam['p_prefix']."joined_circle (acc_right,user,circle) values(?,?,?) ", 1, $user, $xparam['circle'])) {
				$postmess[] = "{$ng_icon}Access is denied. {$xparam['p_db']}-006";
				return 1;
			}
		}
		$postmess[] = "{$ok_icon}Construction of basic data OK.";
	}

	$buff = init_htaccess($xparam);
	if ($buff) {
		if (!@file_put_contents('.htaccess', $buff)) {
			$postmess[] = "{$ng_icon}htaccess was unable to write";
			return true;
		}
		@chmod('.htaccess',$permission);
	}

	$buff = init_config( $xparam );
	if ($buff) {
		if (!@file_put_contents( 'config.php', $buff )) {
			$postmess[] = "{$ng_icon}config.php is write-protected";
			return 1;

		} else {

			@chmod('config.php',$permission);

			if ($default_db) {
				return true;
			}

			require_once(dirname(__FILE__).'/config.php');				// utility.edit.phpを使うためにインクルード

			$ut = new Ut($html, $database, $p_circle, $params, $config);
			$ut->set_storage('system',array('version'=>3));

			$params['now'] = date('Y-m-d H:i:s', time());
			error_reporting(E_ALL|E_WARNING);

			require_once(dirname(__FILE__).'/module/utility.basic.php');
			require_once(dirname(__FILE__).'/module/utility.edit.php');
			$_SESSION['login_id'] = $xparam['admin_user'];
			$params['circle']['acc_right'] = 0xff;
			create_circle($xparam['admin_user'], $p_circle);

			$postmess[] = "{$ok_icon}default construction site OK";

			$ar = $database->sql_select_all( "select metadata from ".DBX."circles where id=?", $p_circle );
			if ($ar) {
				$m = unserialize64($ar[0]['metadata']);
				//$m['def_template']['sidebar'] = "---";
				$m['mult_login']=1;
				$database->sql_update("update ".DBX."circles set metadata=? where id=? ", serialize64($m), $p_circle);

				if (isset($m['top_page'])) {
					//初期化が正常に走っていないので最小限の仮設定を行う
					$params['circle']['url'] = '';

					//トップページ確認
					$ar2 = $database->sql_select_all("select metadata from ".DBX."data_items where id=?", $m['top_page']);
					if (!$ar2) {
						$postmess[] = "{$ng_icon}Unknown error-009<br>";
						return 1;
					}
					$metadata = unserialize64($ar2[0]['metadata']);

					//グローバルナビの追加（HOME)
					$metadata['theme']['global_menu'][] = array('text'=>"HOME", 'link'=>0);

					//サンプルページの追加
					$r = array();
					$p_type = 1;	// 通常ページ
					$r['type'] = $p_type;
					$r['mode'] = 1;
					$r['link'] = $m['top_page'];
					$r['title'] = 'Sample Page';

$r['contents'] = <<<EOT
<p>contents-本文</p>
<h2>H2 title-タイトル</h2>
<p>contents-本文</p>
<h3>H3 title-タイトル</h3>
<p>contents-本文</p>
<h4>H4 title-タイトル</h4>
<p>contents-本文</p>
<div>
<ul>
<li class="active">1</li>
<li>2</li>
<li>3</li>
<li>4</li>
</ul>
</div>
<div class="onethird-pagination">
<ul>
<li class="prev disabled">&larr; Previous</li>
<li class="active">1</li>
<li>2</li>
<li>3</li>
<li>4</li>
<li class="next">Next &rarr;</li>
</ul>
</div>
EOT;
					create_page($r);
					$id = $r['id'];
					$metadata['theme']['global_menu'][] = array('text'=>"Sample", 'link'=>$id);

					//Theme Managerの追加
					$r = array();
					$p_type = 1;	// 通常ページ
					$r['type'] = $p_type;
					$r['block_type'] = 20;
					$r['mode'] = 1;
					$r['link'] = 0;
					$r['title'] = 'Theme Manager';
					$r['meta']['template_ar']['php'] = 'manager.php';

					create_page($r);
					$id = $r['id'];
					$metadata['theme']['global_menu'][] = array('text'=>"Theme Manager", 'link'=>'manager');

					//エイリアス設定
					$m = get_circle_meta();
					$m['alias']['manager'] = $id;
					if (!$database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($m), $p_circle)) {
						$r['result'] = false;
					}

					//トップページの内容を設定
					$contents = 'Welcome! to OneThird CMS.';
$contents = <<<EOT

<h1>Welcome to OneThird CMS&nbsp;</h1>
<h2>ログインの仕方</h2>
<p>インストールURLに /login をつけて開いてください</p>
<p><a href="{\$ut->link('login')}">{\$ut->link('login')}</a></p>
<p>&nbsp;</p>
<h2>OnThird CMSの使い方</h2>
<p>公式サイトから、ファーストステップガイドをダウンロードしてください</p>
<p><a href="https://onethird.net/en/document">https://onethird.net/en/document</a></p>
<p>&nbsp;</p>
<h2>About support</h2>
<p>リリース情報やバグ情報は公式ページか、facebookページを参照してください</p>
<p><a href="http://www.facebook.com/pages/OneThird-CMS/331468460282620">http://www.facebook.com/pages/OneThird-CMS/331468460282620</a></p>
<p>&nbsp;</p>

EOT;
					if (!$database->sql_update("update ".DBX."data_items set contents=?,metadata=? where id=? ", $contents, serialize64($metadata), $m['top_page'])) {
						$postmess[] = "{$ng_icon}Writing Sample Data";
						return 1;
					}
					$postmess[] = "{$ok_icon}Writing Sample Data, OK";

				}
			}
			//header("Location: {$xparam['url']} ");
		}
		//デフォルトセット
		$ar = $database->sql_select_all("select metadata,owner,name,join_flag,id,cid,public_flag from ".DBX."circles where id=? ", (int)$p_circle);
		$params['circle']['meta'] = unserialize64($ar[0]['metadata']);
		$params['circle']['meta']['folder_systag']=1;
		$params['circle']['meta']['data']['startup_script']['preload_theme']="data/preload.php";
		$tmp = "YTozOntzOjEwOiJvbmV0aGlyZC44IjtzOjkyNjk6Ii5vbmV0aGlyZC1idXR0b24sLm9uZXRoaXJkLXBhbmVsIGEsLm9uZXRoaXJkLXBhbmVsIGltZywub25ldGhpcmQtcGFuZWwgaW5wdXQsLm9uZXRoaXJkLXBhbmVsIHNwYW4sLm9uZXRoaXJkLXNldHRpbmcgaW1nLC5vbmV0aGlyZC1zZXR0aW5nIHRke3ZlcnRpY2FsLWFsaWduOm1pZGRsZX0uY2xlYXJmaXg6YWZ0ZXIsLmNsZWFyZml4OmJlZm9yZXtkaXNwbGF5OnRhYmxlO2NvbnRlbnQ6IiJ9LmNsZWFyZml4OmFmdGVye2NsZWFyOmJvdGh9Lm9uZXRoaXJkLXRhYiAudGFiLWJvZHl7cGFkZGluZzoxMHB4O2ZvbnQtc2l6ZToxNHB4O2JhY2tncm91bmQtY29sb3I6I2ZiZmJmYjtiYWNrZ3JvdW5kOi13ZWJraXQtbGluZWFyLWdyYWRpZW50KHRvcCwjZmZmLCNmNWY1ZjUpO2JhY2tncm91bmQ6bGluZWFyLWdyYWRpZW50KHRvIGJvdHRvbSwjZmZmLCNmNWY1ZjUpO2JvcmRlcjoxcHggc29saWQgc2lsdmVyO2JvcmRlci1yYWRpdXM6M3B4O2NvbG9yOiMzMDE5MDA7bWluLWhlaWdodDozMDBweH0ub25ldGhpcmQtdGFiIC50YWItaGVhZHttYXJnaW46MDtwYWRkaW5nOjA7YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDg4LDg4LDg4LC43KTtiYWNrZ3JvdW5kLWNvbG9yOiM3NzdcOTt3aWR0aDoxMDAlO2ZvbnQtc2l6ZToxMnB4fS5vbmV0aGlyZC10YWIgLnRhYi1oZWFkIGxpe21hcmdpbjo1cHg7cGFkZGluZzozcHggMTVweDtmbG9hdDpsZWZ0O2JvcmRlci1yYWRpdXM6NHB4fS5vbmV0aGlyZC10YWIgLnRhYi1oZWFkIGxpIGF7Y29sb3I6I2ZmZn0ub25ldGhpcmQtdGFiIC50YWItaGVhZCBsaS5hY3RpdmV7YmFja2dyb3VuZC1jb2xvcjojZmZmO3BhZGRpbmc6M3B4IDE0cHg7Ym9yZGVyOjFweCBzb2xpZCBzaWx2ZXI7Ym9yZGVyLWJvdHRvbTowfS5vbmV0aGlyZC10YWIgLnRhYi1oZWFkIGxpLmFjdGl2ZSBhe2NvbG9yOiMyMjI7Zm9udC13ZWlnaHQ6NzAwfS5vbmV0aGlyZC10YWIgb2wsLm9uZXRoaXJkLXRhYiB1bHtsaXN0LXN0eWxlOm5vbmV9Lm9uZXRoaXJkLWJ1dHRvbntkaXNwbGF5OmlubGluZS1ibG9jaztmb250LWZhbWlseTpWZXJkYW5hLFJvYm90bywnRHJvaWQgU2FucycsTWVpcnlvLCdIaXJhZ2lubyBLYWt1IEdvdGhpYyBQcm9OJztib3JkZXI6MnB4IHNvbGlkICNmZmYhaW1wb3J0YW50O2JveC1zaGFkb3c6MCAwIDJweCAjM0EzQTNBO2JhY2tncm91bmQtY29sb3I6I2Q2ZDNkMztiYWNrZ3JvdW5kOi13ZWJraXQtbGluZWFyLWdyYWRpZW50KHRvcCwjZGZkZmRmLCNjOWMyYzIpO2JhY2tncm91bmQ6bGluZWFyLWdyYWRpZW50KHRvIGJvdHRvbSwjZGZkZmRmLCNjOWMyYzIpO3RleHQtc2hhZG93OjFweCAxcHggM3B4ICNmZmY7Ym9yZGVyLXJhZGl1czozcHg7bWFyZ2luOjNweCAxMHB4IDhweCAwO3dpZHRoOmF1dG87Zm9udC1zaXplOjE0cHg7Y29sb3I6IzIyMiFpbXBvcnRhbnQ7cGFkZGluZzo2cHggMTBweCA3cHg7bGluZS1oZWlnaHQ6MWVtO2JveC1zaXppbmc6Ym9yZGVyLWJveDstbW96LWJveC1zaXppbmc6Ym9yZGVyLWJveDt0ZXh0LWRlY29yYXRpb246bm9uZTtjdXJzb3I6cG9pbnRlcn0ub25ldGhpcmQtYnV0dG9uLmhpZGRlbntvcGFjaXR5Oi41O2ZpbHRlcjphbHBoYShvcGFjaXR5PTUwKX0ub25ldGhpcmQtYnV0dG9uLmRlZmF1bHR7Ym94LXNoYWRvdzowIDAgMXB4IDFweCAjNkI2QjZCfS5vbmV0aGlyZC1idXR0b246aG92ZXJ7Y29sb3I6cmVkO3RleHQtZGVjb3JhdGlvbjpub25lO29wYWNpdHk6MTtmaWx0ZXI6YWxwaGEob3BhY2l0eT0xMDApO2JveC1zaGFkb3c6MCAwIDZweCAjMDAwMGZjfS5vbmV0aGlyZC1idXR0b24ubWluaXtwYWRkaW5nOjRweCAxMHB4IDZweDttYXJnaW46OHB4IDZweCA4cHggMDtmb250LXNpemU6MTFweDtoZWlnaHQ6MjVweH0ub25ldGhpcmQtYnV0dG9uLm1pZHtwYWRkaW5nOjVweCAxMHB4IDdweDtmb250LXNpemU6MTRweDtoZWlnaHQ6MzJweH0ub25ldGhpcmQtYnV0dG9uLmxhcmdle3BhZGRpbmc6OHB4IDEwcHggMTJweDtmb250LXNpemU6MThweDtoZWlnaHQ6NDNweH0ub25ldGhpcmQtYnV0dG9uLmNsZWFye2JhY2tncm91bmQ6MCAwO2JvcmRlcjpub25lIWltcG9ydGFudDtib3gtc2hhZG93Om5vbmU7bWFyZ2luLXRvcDoycHg7cGFkZGluZy1sZWZ0OjA7cGFkZGluZy1yaWdodDowO21hcmdpbi1sZWZ0OjA7bWFyZ2luLXJpZ2h0OjdweH0ub25ldGhpcmQtcGFuZWx7YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDE5MiwxOTIsMTkyLC41KTtwYWRkaW5nOjIwcHggMTVweDttYXJnaW4tdG9wOjEwcHh9Lm9uZXRoaXJkLXNldHRpbmd7cGFkZGluZzoyMHB4fS5vbmV0aGlyZC1zZXR0aW5nIC50aXRsZXtmb250LXNpemU6MjBweDtmb250LXdlaWdodDo3MDB9Lm9uZXRoaXJkLXNldHRpbmcgb2wsLm9uZXRoaXJkLXNldHRpbmcgdWx7bGlzdC1zdHlsZTpub25lO3BhZGRpbmc6MDttYXJnaW46MH0ub25ldGhpcmQtc2V0dGluZyBsaXtsaXN0LXN0eWxlLXR5cGU6bm9uZX0ub25ldGhpcmQtc2V0dGluZyB0YWJsZXt0YWJsZS1sYXlvdXQ6YXV0bzt3aWR0aDoxMDAlO2JhY2tncm91bmQ6MCAwfS5vbmV0aGlyZC1zZXR0aW5nIHRke3BhZGRpbmc6MTBweCAxMHB4IDVweCAwfS5vbmV0aGlyZC1zZXR0aW5nIHRkOm50aC1jaGlsZCgxKXt0ZXh0LWFsaWduOnJpZ2h0O3BhZGRpbmctcmlnaHQ6MS41ZW07dmVydGljYWwtYWxpZ246dG9wfS5vbmV0aGlyZC1zZXR0aW5nIHRhYmxlLC5vbmV0aGlyZC1zZXR0aW5nIHRkLC5vbmV0aGlyZC1zZXR0aW5nIHRye2JvcmRlcjpub25lfS5vbmV0aGlyZC1zZXR0aW5nIGJ1dHRvbiwub25ldGhpcmQtc2V0dGluZyBpbnB1dFt0eXBlPWJ1dHRvbl0sLm9uZXRoaXJkLXNldHRpbmcgaW5wdXRbdHlwZT1zdWJtaXRde3dpZHRoOmF1dG99Lm9uZXRoaXJkLXNldHRpbmcgaW5wdXRbdHlwZT1udW1iZXJde3dpZHRoOjEzOHB4fS5vbmV0aGlyZC1zZXR0aW5nIGlucHV0W3R5cGU9dGV4dF0sLm9uZXRoaXJkLXNldHRpbmcgdGV4dGFyZWF7dHJhbnNpdGlvbjpib3JkZXIgbGluZWFyIC4ycyxib3gtc2hhZG93IGxpbmVhciAuMnM7Ym94LXNoYWRvdzppbnNldCAwIDFweCAzcHggcmdiYSgwLDAsMCwuMSl9Lm9uZXRoaXJkLXNldHRpbmcgaW5wdXRbdHlwZT1jb2xvcl0sLm9uZXRoaXJkLXNldHRpbmcgaW5wdXRbdHlwZT1kYXRlXSwub25ldGhpcmQtc2V0dGluZyBpbnB1dFt0eXBlPWVtYWlsXSwub25ldGhpcmQtc2V0dGluZyBpbnB1dFt0eXBlPW1vbnRoXSwub25ldGhpcmQtc2V0dGluZyBpbnB1dFt0eXBlPW51bWJlcl0sLm9uZXRoaXJkLXNldHRpbmcgaW5wdXRbdHlwZT1wYXNzd29yZF0sLm9uZXRoaXJkLXNldHRpbmcgaW5wdXRbdHlwZT1zZWFyY2hdLC5vbmV0aGlyZC1zZXR0aW5nIGlucHV0W3R5cGU9dGVsXSwub25ldGhpcmQtc2V0dGluZyBpbnB1dFt0eXBlPXRleHRdLC5vbmV0aGlyZC1zZXR0aW5nIGlucHV0W3R5cGU9dGltZV0sLm9uZXRoaXJkLXNldHRpbmcgc2VsZWN0LC5vbmV0aGlyZC1zZXR0aW5nIHRleHRhcmVhe2Rpc3BsYXk6aW5saW5lLWJsb2NrO3BhZGRpbmc6NHB4IDdweDtib3JkZXI6MXB4IHNvbGlkICNiNmI2YjY7Ym9yZGVyLXJhZGl1czozcHg7bWFyZ2luOjFweCAwIDVweH0ub25ldGhpcmQtc2V0dGluZyBpbnB1dDpmb2N1cywub25ldGhpcmQtc2V0dGluZyB0ZXh0YXJlYTpmb2N1c3tvdXRsaW5lOjA7Ym9yZGVyLWNvbG9yOnJnYmEoODIsMTY4LDIzNiwuOCk7Ym94LXNoYWRvdzppbnNldCAwIDFweCAzcHggcmdiYSgwLDAsMCwuMSksMCAwIDhweCByZ2JhKDgyLDE2OCwyMzYsLjYpfS5vbmV0aGlyZC1zZXR0aW5nIGlucHV0W3R5cGU9Y2hlY2tib3hdLC5vbmV0aGlyZC1zZXR0aW5nIGlucHV0W3R5cGU9cmFkaW9de3Bvc2l0aW9uOmFic29sdXRlO29wYWNpdHk6MTtsZWZ0OjA7dG9wOi41ZW07d2lkdGg6aW5oZXJpdDttYXJnaW46MH0ub25ldGhpcmQtc2V0dGluZyBsYWJlbHtwb3NpdGlvbjpyZWxhdGl2ZTtwYWRkaW5nLWxlZnQ6MjBweDtkaXNwbGF5OmlubGluZS1ibG9jaztmb250LXdlaWdodDo0MDB9Lm9uZXRoaXJkLXNldHRpbmcgbGFiZWwgaW5wdXRbdHlwZT1jaGVja2JveF17d2lkdGg6MjBweH0ub25ldGhpcmQtc2V0dGluZyBpbnB1dCwub25ldGhpcmQtc2V0dGluZyB0ZXh0YXJlYXtoZWlnaHQ6aW5oZXJpdDttYXJnaW46MCAwIDJweDt2ZXJ0aWNhbC1hbGlnbjptaWRkbGU7d2lkdGg6MTAwJTtib3gtc2l6aW5nOmJvcmRlci1ib3g7LW1vei1ib3gtc2l6aW5nOmJvcmRlci1ib3h9Lm9uZXRoaXJkLXNldHRpbmcgaW5wdXQud2lkZSwub25ldGhpcmQtc2V0dGluZyB0ZXh0YXJlYS53aWRle3dpZHRoOjMwMHB4fS5vbmV0aGlyZC1zZXR0aW5nIGlucHV0Lm1pZGRsZSwub25ldGhpcmQtc2V0dGluZyB0ZXh0YXJlYS5taWRkbGV7d2lkdGg6MTAwcHh9Lm9uZXRoaXJkLXNldHRpbmcgaW5wdXQubmFycm93LC5vbmV0aGlyZC1zZXR0aW5nIHRleHRhcmVhLm5hcnJvd3t3aWR0aDo1MHB4fS5vbmV0aGlyZC1zZXR0aW5nIGlucHV0LngtbmFycm93LC5vbmV0aGlyZC1zZXR0aW5nIHRleHRhcmVhLngtbmFycm93e3dpZHRoOjM1cHh9Lm9uZXRoaXJkLXNldHRpbmcgc2VsZWN0e3dpZHRoOjEwMCV9Lm9uZXRoaXJkLXNldHRpbmcgaW5wdXQ6ZGlzYWJsZWQsLm9uZXRoaXJkLXNldHRpbmcgdGV4dGFyZWE6ZGlzYWJsZWR7Y29sb3I6IzlFOUM5QyFpbXBvcnRhbnR9Lm9uZXRoaXJkLXNldHRpbmcgcHtwYWRkaW5nLXRvcDo1cHh9Lm9uZXRoaXJkLXNldHRpbmcgZGx7bWFyZ2luOjA7cGFkZGluZzowfS5vbmV0aGlyZC1zZXR0aW5nIGRsIGR0e21hcmdpbjoycHggMCA1cHg7Zm9udC13ZWlnaHQ6NzAwfS5vbmV0aGlyZC1zZXR0aW5nIGRsIGRke21hcmdpbjowIDAgMTBweH0ub25ldGhpcmQtc2V0dGluZyBkbCBkZCBpbnB1dFt0eXBlPXBhc3N3b3JkXSwub25ldGhpcmQtc2V0dGluZyBkbCBkZCBpbnB1dFt0eXBlPXRleHRdLC5vbmV0aGlyZC1zZXR0aW5nIGRsIGRkIHNlbGVjdCwub25ldGhpcmQtc2V0dGluZyBkbCBkZCB0ZXh0YXJlYXt3aWR0aDoxMDAlO2JveC1zaXppbmc6Ym9yZGVyLWJveDstbW96LWJveC1zaXppbmc6Ym9yZGVyLWJveH0ub25ldGhpcmQtc2V0dGluZyAuc2V0dGluZ2xpc3Rfc2VsIHNwYW57Zm9udC1zaXplOjEycHg7Ym9yZGVyOjFweCBzb2xpZCBzaWx2ZXI7cGFkZGluZzowIDRweDtkaXNwbGF5OmlubGluZS1ibG9jazttYXJnaW46MnB4O2N1cnNvcjpwb2ludGVyfS5vbmV0aGlyZC1zZXR0aW5nIC5zZXR0aW5nbGlzdF9zZWwgc3Bhbi5zZWxlY3RlZHtiYWNrZ3JvdW5kLWNvbG9yOnNpbHZlcn0ub25ldGhpcmQtc2V0dGluZyAuYWN0aW9uc3ttYXJnaW4tdG9wOjIwcHg7Ym9yZGVyLXRvcDoxcHggc29saWQgc2lsdmVyO3BhZGRpbmctdG9wOjIwcHh9Lm9uZXRoaXJkLXNldHRpbmcgLmFjdGlvbnMuY2VudGVye3RleHQtYWxpZ246Y2VudGVyfS5vbmV0aGlyZC1zZXR0aW5nIC5hY3Rpb25zLmJvcmRlci1sZXNze2JvcmRlcjpub25lfS5vbmV0aGlyZC1zZXR0aW5nIC5hY3Rpb25zIGlucHV0e3BhZGRpbmc6NXB4IDEwcHg7Zm9udC1zaXplOjE0cHg7d2lkdGg6aW5oZXJpdH0ub25ldGhpcmQtc2V0dGluZyAuYWN0aW9ucyBhe3ZlcnRpY2FsLWFsaWduOm1pZGRsZX0ub25ldGhpcmQtdGFibGV7Ym9yZGVyLWNvbGxhcHNlOmNvbGxhcHNlfS5vbmV0aGlyZC10YWJsZSB0ZCwub25ldGhpcmQtdGFibGUgdGh7Ym9yZGVyOjFweCBzb2xpZCBzaWx2ZXI7cGFkZGluZzo0cHggMTBweDtmb250LXdlaWdodDo0MDB9Lm9uZXRoaXJkLXRhYmxlIHRoe2JhY2tncm91bmQtY29sb3I6I2Q1ZDVkNTt3aGl0ZS1zcGFjZTpub3dyYXA7Y29sb3I6IzIyMjtmb250LXdlaWdodDo3MDB9Lm9uZXRoaXJkLXRhYmxlIGlucHV0W3R5cGU9dGV4dF0ud2lkZXt3aWR0aDozMDBweH0ub25ldGhpcmQtZWRpdC1wb2ludGVye3Bvc2l0aW9uOnJlbGF0aXZlfS5vbmV0aGlyZC1lZGl0LXBvaW50ZXI6aG92ZXIgLmVkaXRfcG9pbnRlciwub25ldGhpcmQtZWRpdC1wb2ludGVyOmhvdmVyIC5ob3Zlci1wb2ludGVyLC5vbmV0aGlyZC1lZGl0LXBvaW50ZXI6aG92ZXIgLnBvaW50ZXJ7b3BhY2l0eTouNX0ub25ldGhpcmQtZWRpdC1wb2ludGVyIC5lZGl0X3BvaW50ZXJ7bWluLXdpZHRoOjI4cHh9Lm9uZXRoaXJkLWVkaXQtcG9pbnRlciAuZWRpdF9wb2ludGVyLC5vbmV0aGlyZC1lZGl0LXBvaW50ZXIgLmhvdmVyLXBvaW50ZXJ7b3BhY2l0eTowO3JpZ2h0Oi01cHg7dHJhbnNpdGlvbjpvcGFjaXR5IGxpbmVhciAuMnMsZGlzcGxheSBsaW5lYXIgLjJzfS5vbmV0aGlyZC1lZGl0LXBvaW50ZXIgLmVkaXRfcG9pbnRlciBhLC5vbmV0aGlyZC1lZGl0LXBvaW50ZXIgLmhvdmVyLXBvaW50ZXIgYXtib3JkZXI6bm9uZX0ub25ldGhpcmQtZWRpdC1wb2ludGVyIC5lZGl0X3BvaW50ZXIsLm9uZXRoaXJkLWVkaXQtcG9pbnRlciAucG9pbnRlcntkaXNwbGF5OmlubGluZS1ibG9jaztmaWx0ZXI6YWxwaGEob3BhY2l0eT01MCk7YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDAsMCwwLC41KTtwYWRkaW5nOjZweDtwb3NpdGlvbjphYnNvbHV0ZTtmb250LXNpemU6MTBweDtsaW5lLWhlaWdodDoxZW07dG9wOjA7cmlnaHQ6MDt0ZXh0LXNoYWRvdzpub25lO3otaW5kZXg6MTA7dGV4dC1hbGlnbjpyaWdodH0ub25ldGhpcmQtZWRpdC1wb2ludGVyIC5lZGl0X3BvaW50ZXI6aG92ZXIsLm9uZXRoaXJkLWVkaXQtcG9pbnRlciAucG9pbnRlcjpob3ZlcntvcGFjaXR5Oi45NTtmaWx0ZXI6YWxwaGEob3BhY2l0eT05NSl9Lm9uZXRoaXJkLWVkaXQtcG9pbnRlciAuZWRpdF9wb2ludGVyIHNwYW4sLm9uZXRoaXJkLWVkaXQtcG9pbnRlciAucG9pbnRlciBzcGFue3ZlcnRpY2FsLWFsaWduOnRvcDt0ZXh0LXNoYWRvdzpub25lO2NvbG9yOiMwMDY5RDY7bWFyZ2luOjAgMXB4O2Rpc3BsYXk6aW5saW5lLWJsb2NrO2N1cnNvcjpwb2ludGVyO21pbi13aWR0aDoxNnB4fS5vbmV0aGlyZC1lZGl0LXBvaW50ZXIgLmVkaXRfcG9pbnRlciBzcGFuOmhvdmVyLC5vbmV0aGlyZC1lZGl0LXBvaW50ZXIgLnBvaW50ZXIgc3Bhbjpob3Zlcntjb2xvcjpyZWR9Lm9uZXRoaXJkLWVkaXQtcG9pbnRlciAuZWRpdF9wb2ludGVyLmxlZnQsLm9uZXRoaXJkLWVkaXQtcG9pbnRlciAucG9pbnRlci5sZWZ0e3RvcDowO2xlZnQ6MDtkaXNwbGF5OnRhYmxlfS5vbmV0aGlyZC1lZGl0LXBvaW50ZXIgLmVkaXRfcG9pbnRlci51cHBlciwub25ldGhpcmQtZWRpdC1wb2ludGVyIC5wb2ludGVyLnVwcGVye3RvcDotMy40ZW19Lm9uZXRoaXJkLWVkaXQtcG9pbnRlciAuZWRpdF9wb2ludGVyLmJvdHRvbSwub25ldGhpcmQtZWRpdC1wb2ludGVyIC5wb2ludGVyLmJvdHRvbXtib3R0b206MDt0b3A6aW5oZXJpdH0ub25ldGhpcmQtZWRpdC1wb2ludGVyIC5lZGl0X3BvaW50ZXIubG93ZXIsLm9uZXRoaXJkLWVkaXQtcG9pbnRlciAucG9pbnRlci5sb3dlcntib3R0b206LTEwcHh9Lm9uZXRoaXJkLWVkaXQtcG9pbnRlciAuZWRpdF9wb2ludGVyIC5vbmV0aGlyZC1idXR0b24sLm9uZXRoaXJkLWVkaXQtcG9pbnRlciAucG9pbnRlciAub25ldGhpcmQtYnV0dG9ue21hcmdpbjowIDVweCAwIDB9Lm9uZXRoaXJkLWVkaXQtcG9pbnRlciAuZWRpdF9wb2ludGVyIGltZywub25ldGhpcmQtZWRpdC1wb2ludGVyIC5wb2ludGVyIGltZ3ttYXJnaW46MCAzcHg7cGFkZGluZzowO3ZlcnRpY2FsLWFsaWduOm1pZGRsZTt3aWR0aDoyMnB4fUBtZWRpYSBzY3JlZW4gYW5kIChtYXgtd2lkdGg6NjQwcHgpey5vbmV0aGlyZC1lZGl0LXBvaW50ZXIgLmVkaXRfcG9pbnRlciBpbWcsLm9uZXRoaXJkLWVkaXQtcG9pbnRlciAucG9pbnRlciBpbWd7d2lkdGg6MzBweH19QG1lZGlhIHNjcmVlbiBhbmQgKG1heC13aWR0aDo0NTBweCl7Lm9uZXRoaXJkLWVkaXQtcG9pbnRlciAuZWRpdF9wb2ludGVyIGltZywub25ldGhpcmQtZWRpdC1wb2ludGVyIC5wb2ludGVyIGltZ3t3aWR0aDoyNnB4fX0ub25ldGhpcmQtdXBsb2FkZXItdW5pdHtwYWRkaW5nOjAgMTBweH0ub25ldGhpcmQtdXBsb2FkZXItdW5pdCAubGlzdHtoZWlnaHQ6MTg2cHg7bWFyZ2luLWJvdHRvbToxMHB4O292ZXJmbG93LXk6c2Nyb2xsfS5vbmV0aGlyZC11cGxvYWRlci11bml0IC5saXN0IC5pdGVte3Bvc2l0aW9uOnJlbGF0aXZlO2Zsb2F0OmxlZnQ7bWFyZ2luOjJweDttYXgtd2lkdGg6MTAwcHg7aGVpZ2h0OjcwcHg7b3ZlcmZsb3c6aGlkZGVuO2JhY2tncm91bmQtY29sb3I6I2RkZDt0ZXh0LWFsaWduOmNlbnRlcjtib3JkZXI6MnB4IHNvbGlkICNENkQ2RDY7Y3Vyc29yOnBvaW50ZXJ9Lm9uZXRoaXJkLXVwbG9hZGVyLXVuaXQgLmxpc3QgLml0ZW0gLnBpY3R1cmV7aGVpZ2h0OjEwMCU7bWluLXdpZHRoOjUwcHg7Ym94LXNpemluZzpib3JkZXItYm94fS5vbmV0aGlyZC11cGxvYWRlci11bml0IC5saXN0IC5pdGVtIHB7bWFyZ2luOjVweDt3aWR0aDo4MHB4O3dvcmQtYnJlYWs6YnJlYWstYWxsO2xpbmUtaGVpZ2h0OjEyMCU7aGVpZ2h0OjYxcHg7b3ZlcmZsb3c6aGlkZGVuO2ZvbnQtc2l6ZToxMHB4fS5vbmV0aGlyZC11cGxvYWRlci11bml0IC5saXN0IC5obmR7cG9zaXRpb246YWJzb2x1dGU7d2lkdGg6MjBweDtoZWlnaHQ6MjBweH0ub25ldGhpcmQtdXBsb2FkZXItdW5pdCAubGlzdCAuaG5kIGltZ3t3aWR0aDoyMHB4O2hlaWdodDoyMHB4O3BhZGRpbmc6MnB4O29wYWNpdHk6Ljd9Lm9uZXRoaXJkLXVwbG9hZGVyLXVuaXQgLmxpc3QgLmhuZCBpbWc6aG92ZXJ7b3BhY2l0eToxfS5vbmV0aGlyZC11cGxvYWRlci11bml0IC5saXN0IC5vZ3AsLm9uZXRoaXJkLXVwbG9hZGVyLXVuaXQgLmxpc3QgLnRodW1ie2JvcmRlcjoycHggc29saWQgI0ZGNUMwMH0ub25ldGhpcmQtdXBsb2FkZXItdW5pdCAubGlzdCAub2dwIC5vZ3BobmQgaW1nLC5vbmV0aGlyZC11cGxvYWRlci11bml0IC5saXN0IC50aHVtYiAudGh1bWJobmQgaW1ne2JhY2tncm91bmQtY29sb3I6cmdiYSgyNTUsOTIsMCwuNik7b3BhY2l0eToxfS5vbmV0aGlyZC11cGxvYWRlci11bml0IC51cGxvYWQtYnV0dG9ue3ZlcnRpY2FsLWFsaWduOmJvdHRvbTtoZWlnaHQ6aW5oZXJpdH0ub25ldGhpcmQtdXBsb2FkZXItdW5pdCAudXBsb2FkLWJ1dHRvbiAuaW5uZXJ7cG9zaXRpb246cmVsYXRpdmV9Lm9uZXRoaXJkLXVwbG9hZGVyLXVuaXQgLnVwbG9hZC1idXR0b24gLmlubmVyIGlucHV0W3R5cGU9ZmlsZV17cG9zaXRpb246YWJzb2x1dGU7dG9wOi00cHg7bGVmdDotMTNweDt3aWR0aDoxMjAlO2hlaWdodDozMnB4O29wYWNpdHk6MH0jb25ldGhpcmQtb3ZlcmxheSAjaW5uZXJfb3ZlcmxheXtkaXNwbGF5Om5vbmU7dGV4dC1hbGlnbjpjZW50ZXI7cGFkZGluZy10b3A6NTBweH0jb25ldGhpcmQtb3ZlcmxheSAjaW5uZXJfb3ZlcmxheT5kaXZ7ZGlzcGxheTppbmxpbmUtYmxvY2t9IjtzOjU6InRoZW1lIjtzOjc4MDA6Ii5vbmV0aGlyZC1pbmZvcm1hdGlvbnt6LWluZGV4OjE1OTk5OTtwb3NpdGlvbjpmaXhlZDtsZWZ0OjQ4cHg7dG9wOjA7b3BhY2l0eTouNTtjdXJzb3I6cG9pbnRlcjt9Lm9uZXRoaXJkLWluZm9ybWF0aW9uIGRpdntwYWRkaW5nOjhweCAxMHB4IDhweCAxMHB4Oy13ZWJraXQtYm9yZGVyLXJhZGl1czo2cHg7LW1vei1ib3JkZXItcmFkaXVzOjZweDtib3JkZXItcmFkaXVzOjZweDtib3JkZXI6M3B4IHNvbGlkICNGQzY1MDA7Y29sb3I6I0ZDNjUwMDttYXJnaW4tdG9wOjVweDtiYWNrZ3JvdW5kLWNvbG9yOiNFQUVFODA7Zm9udC1zaXplOjEycHg7fS5vbmV0aGlyZC1pbmZvcm1hdGlvbjpob3ZlcntvcGFjaXR5OjAuOTU7fS5vbmV0aGlyZC1hbGVydCwub25ldGhpcmQtd2FyaW5ne3BhZGRpbmc6NXB4IDEwcHggNXB4IDEwcHg7LXdlYmtpdC1ib3JkZXItcmFkaXVzOjZweDstbW96LWJvcmRlci1yYWRpdXM6NnB4O2JvcmRlci1yYWRpdXM6NnB4O2NvbG9yOiMwNTA1MDU7bWFyZ2luLXRvcDo1cHg7YmFja2dyb3VuZC1jb2xvcjojRkNGQ0ZDO2JvcmRlcjoxMHB4IHNvbGlkICNBRUFFQUU7bGluZS1oZWlnaHQ6MTUwJTtwYWRkaW5nOjIwcHg7Zm9udC1zaXplOjE1cHg7fS5vbmV0aGlyZC1hbGVydCBpbnB1dCwub25ldGhpcmQtd2FyaW5nIGlucHV0e21hcmdpbjoxNnB4IGF1dG8gMCBhdXRvO30ub25ldGhpcmQtd2FyaW5ne2JvcmRlcjozcHggc29saWQgI0FFQUVBRTtwb3NpdGlvbjpmaXhlZDt0b3A6NTBweDt6LWluZGV4OjE1OTk5OTt9Lm9uZXRoaXJkLWRpYWxvZ3tmb250LWZhbWlseTonTXVsaScsICfjg5Ljg6njgq7jg47op5LjgrQgUHJvIFczJywgJ01laXJ5bycsICdIZWx2ZXRpY2EnLCAnVmVyZGFuYSc7Zm9udC1zaXplOjE0cHg7dGV4dC1hbGlnbjpsZWZ0O2Rpc3BsYXk6bm9uZTtwb3NpdGlvbjphYnNvbHV0ZTtiYWNrZ3JvdW5kLWNvbG9yOiNmZmY7Y29sb3I6IzAwMDt0ZXh0LXNoYWRvdzpub25lO2JvcmRlcjo0cHggc29saWQgIzJGMkYyRjstd2Via2l0LWJveC1zaGFkb3c6MHB4IDBweCA2cHggIzAwMDAwMDstbW96LWJveC1zaGFkb3c6MHB4IDBweCA2cHggIzAwMDAwMDtib3gtc2hhZG93OjBweCAwcHggNnB4ICMwMDAwMDA7fS5vbmV0aGlyZC1kaWFsb2cgYSwub25ldGhpcmQtZGlhbG9nIGE6bGluaywub25ldGhpcmQtZGlhbG9nIGE6dmlzaXRlZHtjb2xvcjojMDA3QUZGO3RleHQtZGVjb3JhdGlvbjpub25lO30ub25ldGhpcmQtZGlhbG9nIC5tZXNzYWdle3BhZGRpbmc6MTBweCAyMHB4O30ub25ldGhpcmQtZGlhbG9nIC5tZXNzYWdlIHB7cGFkZGluZy10b3A6MTBweDt9Lm9uZXRoaXJkLWRpYWxvZyAuY29udGVudHN7cGFkZGluZzowIDIwcHggMTBweCAyMHB4O30ub25ldGhpcmQtZGlhbG9nIC5hY3Rpb25ze3BhZGRpbmc6MTBweDt0ZXh0LWFsaWduOmNlbnRlcjttYXJnaW4tYm90dG9tOjEwcHg7Ym9yZGVyLXRvcDoxcHggc29saWQgI0MwQzBDMDt9Lm9uZXRoaXJkLWRpYWxvZyAuYWN0aW9ucyBsYWJlbHtkaXNwbGF5OmlubGluZTttYXJnaW4tcmlnaHQ6MTBweDt9Lm9uZXRoaXJkLWRpYWxvZyAuYWN0aW9ucyBpbnB1dHt3aWR0aDppbmhlcml0O30ub25ldGhpcmQtZGlhbG9nIC50aXRsZXtwb3NpdGlvbjpyZWxhdGl2ZTtmb250LXNpemU6MThweDttYXJnaW46MDtmb250LXdlaWdodDpub3JtYWw7Y29sb3I6I2ZmZjtwYWRkaW5nOjEwcHggMTVweCAxNXB4IDIwcHg7YmFja2dyb3VuZDojNDE0MTQxIHVybChjc3Mvb25ldGhpcmQxMjEucG5nKSBuby1yZXBlYXQgcmlnaHQgLTFweDtiYWNrZ3JvdW5kLXNpemU6NDBweDtiYWNrZ3JvdW5kLWltYWdlOm5vbmVcOTt9Lm9uZXRoaXJkLWRpYWxvZyAudGl0bGU6YmVmb3Jle2NvbnRlbnQ6JyAnO3Bvc2l0aW9uOmFic29sdXRlO3RvcDotM3B4O2xlZnQ6MXB4O2hlaWdodDo5NSU7d2lkdGg6OHB4O2JhY2tncm91bmQ6I0I1NTkwMDt9Lm9uZXRoaXJkLWFydGljbGUtaW1hZ2V7bWF4LXdpZHRoOjEwMCU7fWh0bWx7Zm9udC1zaXplOjE2cHg7fWJvZHl7Zm9udC13ZWlnaHQ6bm9ybWFsO2xpbmUtaGVpZ2h0OjE4MCU7Zm9udC1mYW1pbHk6J09wZW4gU2FucycsJ0hlbHZldGljYSBOZXVlJyxIZWx2ZXRpY2EsTWVpcnlvLEFyaWFsLHNhbnMtc2VyaWY7Zm9udC1zaXplOjFyZW07fWF7Y29sb3I6IzFiNjljZTt0ZXh0LWRlY29yYXRpb246bm9uZTt9LyogVHlwb2dyYXBoeSDigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJMgKi9oMSxoMixoMyxoNCxoNSxoNnttYXJnaW4tdG9wOjA7bWFyZ2luLWJvdHRvbToycmVtO2ZvbnQtd2VpZ2h0OjMwMDt9aDF7Zm9udC1zaXplOjMuMHJlbTtsaW5lLWhlaWdodDoxLjI7bGV0dGVyLXNwYWNpbmc6LTAuMXJlbTt9aDJ7Zm9udC1zaXplOjIuMHJlbTtsaW5lLWhlaWdodDoxLjI1O2xldHRlci1zcGFjaW5nOi0wLjFyZW07fWgze2ZvbnQtc2l6ZToxLjhyZW07bGluZS1oZWlnaHQ6MS4zO2xldHRlci1zcGFjaW5nOi0wLjFyZW07fWg0e2ZvbnQtc2l6ZToxLjVyZW07bGluZS1oZWlnaHQ6MS4zNTtsZXR0ZXItc3BhY2luZzotMC4wOHJlbTt9aDV7Zm9udC1zaXplOjEuMnJlbTtsaW5lLWhlaWdodDoxLjU7bGV0dGVyLXNwYWNpbmc6LTAuMDVyZW07fWg2e2ZvbnQtc2l6ZToxLjFyZW07bGluZS1oZWlnaHQ6MS41NTtsZXR0ZXItc3BhY2luZzowO30vKiBMYXJnZXIgdGhhbiBwaGFibGV0ICovQG1lZGlhIChtaW4td2lkdGg6NTUwcHgpe2gxe2ZvbnQtc2l6ZTozLjVyZW07fSBoMntmb250LXNpemU6Mi4ycmVtO30gaDN7Zm9udC1zaXplOjEuOHJlbTt9IGg0e2ZvbnQtc2l6ZToxLjVyZW07fSBoNXtmb250LXNpemU6MS4zcmVtO30gaDZ7Zm9udC1zaXplOjEuMnJlbTt9fXB7bWFyZ2luLXRvcDowO30uanVtYm90cm9ue2JhY2tncm91bmQtY29sb3I6cmdiYSgyMzQsIDI0MSwgMjQzLCAwLjI2KTtiYWNrZ3JvdW5kLXNpemU6MTAwJTttYXJnaW4tdG9wOjUwcHg7cGFkZGluZy10b3A6MzBweDtoZWlnaHQ6MzAwcHg7fS5qdW1ib3Ryb24gaDF7Zm9udC1zaXplOjYwcHg7fS5qdW1ib3Ryb24gcHtmb250LXNpemU6MjJweDt9QG1lZGlhIChtYXgtd2lkdGg6NzY4cHgpey5qdW1ib3Ryb24gaDF7Zm9udC1zaXplOjQ1cHg7fSAuanVtYm90cm9uIHB7Zm9udC1zaXplOjE4cHg7fX1AbWVkaWEgc2NyZWVuIGFuZCAobWF4LXdpZHRoOjYwMHB4KXsuanVtYm90cm9ue2JhY2tncm91bmQtc2l6ZToxMjAlO21heC1oZWlnaHQ6MjIwcHg7b3ZlcmZsb3c6aGlkZGVuO30gLmp1bWJvdHJvbiBoMXtmb250LXNpemU6MzBweDttYXJnaW46MDtwYWRkaW5nOjA7fSAuanVtYm90cm9uIHB7Zm9udC1zaXplOjE1cHg7fX0uZm9vdGVye3Bvc2l0aW9uOnJlbGF0aXZlO2JhY2tncm91bmQtY29sb3I6I0U2RTZFNjtwYWRkaW5nOjMwcHggMDttYXJnaW4tdG9wOjMwcHg7bWluLWhlaWdodDozMDBweDt9LmZvb3RlciAuY29weXJpZ2h0e2ZvbnQtc2l6ZToxMnB4O3Bvc2l0aW9uOmFic29sdXRlO2JvdHRvbTowO3JpZ2h0OjEwcHg7fS5icmVhZGNydW1ie3BhZGRpbmc6MTFweCAxNXB4O21hcmdpbi1ib3R0b206MjBweDtsaXN0LXN0eWxlOm5vbmU7YmFja2dyb3VuZC1jb2xvcjojZWNmMGYxO2JvcmRlci1yYWRpdXM6NHB4O30uYnJlYWRjcnVtYiBsaXtkaXNwbGF5OmlubGluZS1ibG9jazt9LmJyZWFkY3J1bWIgPiBsaSArIGxpOmJlZm9yZXtwYWRkaW5nOjAgNXB4O2NvbG9yOiNjY2M7Y29udGVudDoiL1wwMGEwIjt9LmJyZWFkY3J1bWIuYWN0aXZle2NvbG9yOiM3Nzc7fS5zaWRlLXBhbmVse21hcmdpbi10b3A6MTVweDt9LnNpZGUtcGFuZWwgLmxpbmstcGFuZWx7YmFja2dyb3VuZC1jb2xvcjojZWNmMGYxO3BhZGRpbmc6MTNweDtib3JkZXItcmFkaXVzOjRweDt9Lm9uZXRoaXJkLXBhZ2luYXRpb24gdWx7ZGlzcGxheTppbmxpbmUtYmxvY2s7cGFkZGluZy1sZWZ0OjA7bWFyZ2luOjE4cHggMDtib3JkZXItcmFkaXVzOjRweDt9Lm9uZXRoaXJkLXBhZ2luYXRpb24gdWwgbGl7ZGlzcGxheTppbmxpbmU7cG9zaXRpb246cmVsYXRpdmU7ZmxvYXQ6bGVmdDtwYWRkaW5nOjhweCAxMnB4O2xpbmUtaGVpZ2h0OjEuNDI4NTcxNDM7bWFyZ2luLWxlZnQ6LTFweDtiYWNrZ3JvdW5kLWNvbG9yOiNmZmY7Ym9yZGVyOjFweCBzb2xpZCAjZGRkO30ub25ldGhpcmQtcGFnaW5hdGlvbiB1bCBsaTpob3Zlcnt6LWluZGV4OjI7Y29sb3I6IzIzNTI3YztiYWNrZ3JvdW5kLWNvbG9yOiNlZWU7Ym9yZGVyLWNvbG9yOiNkZGQ7fS5vbmV0aGlyZC1wYWdpbmF0aW9uIHVsIGxpOmZpcnN0LWNoaWxke21hcmdpbi1sZWZ0OjA7Ym9yZGVyLWJvdHRvbS1sZWZ0LXJhZGl1czo0cHg7Ym9yZGVyLXRvcC1sZWZ0LXJhZGl1czo0cHg7fS5vbmV0aGlyZC1wYWdpbmF0aW9uIHVsIGxpOmxhc3QtY2hpbGR7Ym9yZGVyLWJvdHRvbS1yaWdodC1yYWRpdXM6NnB4O2JvcmRlci10b3AtcmlnaHQtcmFkaXVzOjZweDt9Lm9uZXRoaXJkLXBhZ2luYXRpb24gdWwgLmFjdGl2ZXt6LWluZGV4OjM7Y29sb3I6I2ZmZmZmZjtiYWNrZ3JvdW5kLWNvbG9yOiMzMzdhYjc7Ym9yZGVyLWNvbG9yOiNkZGQ7Y3Vyc29yOmRlZmF1bHQ7fS5vbmV0aGlyZC1wYWdpbmF0aW9uIHVsIC5kaXNhYmxlZHtjb2xvcjojZGRkZGRkO2JhY2tncm91bmQtY29sb3I6I2ZmZjtib3JkZXItY29sb3I6I2RkZDtjdXJzb3I6bm90LWFsbG93ZWQ7fSNuYXZiYXJ7Zm9udC1zaXplOjE1cHg7LyogeCA0LjYgPSBuYXZiYXIgaGVpZ2h0ICovIHBvc2l0aW9uOmZpeGVkO3RvcDowO3JpZ2h0OjA7bGVmdDowO3otaW5kZXg6MTAzMDtjb2xvcjojZmZmZmZmO2JhY2tncm91bmQtY29sb3I6IzJjM2U1MDtib3JkZXItYm90dG9tOjFweCBzb2xpZCB0cmFuc3BhcmVudDttYXJnaW46MDtwYWRkaW5nOjA7Zm9udC1zaXplOjFlbTt9I25hdmJhciBhe2NvbG9yOiMxOGJjOWM7cG9zaXRpb246cmVsYXRpdmU7ZGlzcGxheTppbmxpbmUtYmxvY2s7cGFkZGluZzoxLjJlbSAxNXB4O30jbmF2YmFyIHVse2xpc3Qtc3R5bGU6bm9uZTttYXJnaW46MDtwYWRkaW5nOjA7fSNuYXZiYXIgbGl7cG9zaXRpb246cmVsYXRpdmU7ZGlzcGxheTpibG9jazttYXJnaW46MDt9I25hdmJhciAuaG9tZXtkaXNwbGF5Om5vbmU7ZmxvYXQ6bGVmdDt9I25hdmJhciAuaG9tZSBhe2NvbG9yOiNmZmY7cGFkZGluZy1sZWZ0OjA7fSNuYXZiYXIgLmxpbmtze2Rpc3BsYXk6aW5saW5lLWJsb2NrO2Zsb2F0OmxlZnQ7fSNuYXZiYXIgLmxpbmtzIHVsIGxpe2Zsb2F0OmxlZnQ7fSNuYXZiYXIgLmxpbmtzIHVsIGxpIGF7Y29sb3I6I2ZmZmZmZjtsaW5lLWhlaWdodDoyLjNlbTt9I25hdmJhciAubGlua3MgdWwgbGkgYTpob3ZlcntiYWNrZ3JvdW5kLWNvbG9yOnJnYmEoMTkyLCAxOTIsIDE5MiwgMC4xOSk7fSNuYXZiYXIgLmxpbmtzIHVsIGxpLmFjdGl2ZSBhLCNuYXZiYXIgLmxpbmtzIHVsIGxpLmFjdGl2ZSBhOmhvdmVyLCNuYXZiYXIgLmxpbmtzIHVsIGxpLmFjdGl2ZSBhOmZvY3Vze2ZvbnQtd2VpZ2h0OmJvbGQ7YmFja2dyb3VuZDpyZ2JhKDAsIDAsIDAsIDAuMDYpO30jbmF2YmFyIC50b2dnbGV7ZGlzcGxheTpub25lO3Bvc2l0aW9uOmFic29sdXRlO3JpZ2h0OjA7bWFyZ2luLXJpZ2h0OjE1cHg7cGFkZGluZzo5cHggMTBweDttYXJnaW4tdG9wOjEzcHg7bWFyZ2luLWJvdHRvbToxM3B4O2JvcmRlcjoxcHggc29saWQgdHJhbnNwYXJlbnQ7Ym9yZGVyLXJhZGl1czo0cHg7YmFja2dyb3VuZC1jb2xvcjpyZ2JhKDAsIDAsIDAsIDAuNCk7fSNuYXZiYXIgLnRvZ2dsZSAuaWNvbi1iYXJ7YmFja2dyb3VuZC1jb2xvcjojZmZmZmZmO2Rpc3BsYXk6YmxvY2s7d2lkdGg6MjJweDtoZWlnaHQ6MnB4O2JvcmRlci1yYWRpdXM6MXB4O21hcmdpbjoxcHggMCA1cHggMDt9QG1lZGlhIChtaW4td2lkdGg6NzY4cHgpeyNuYXZiYXIgLmhvbWV7ZGlzcGxheTpub25lO30gI25hdmJhciAubGlua3N7ZGlzcGxheTppbmxpbmUtYmxvY2s7fSAjbmF2YmFyIC50b2dnbGV7ZGlzcGxheTpub25lO319QG1lZGlhIChtYXgtd2lkdGg6NzY4cHgpeyNuYXZiYXIgLmhvbWV7d2lkdGg6MTAwJTtwYWRkaW5nLWxlZnQ6MjBweDtkaXNwbGF5OmlubGluZS1ibG9jazt9ICNuYXZiYXIgLmxpbmtze2Rpc3BsYXk6bm9uZTt3aWR0aDoxMDAlO2JvcmRlci10b3A6MXB4IHNvbGlkIHJnYmEoMjU1LCAyNTUsIDI1NSwgMC4yNCk7cGFkZGluZy1ib3R0b206MjBweDt9ICNuYXZiYXIgLmxpbmtzIGxpe2Zsb2F0OmluaXRpYWw7d2lkdGg6MTAwJTt9ICNuYXZiYXIgLmxpbmtzIGxpLm1vYml7ZGlzcGxheTpub25lO30gI25hdmJhciAubGlua3MgbGkgYXtwYWRkaW5nOjZweCAxNXB4O30gI25hdmJhciAudG9nZ2xle2Rpc3BsYXk6aW5saW5lLWJsb2NrO319QG1lZGlhIChtYXgtd2lkdGg6NzY4cHgpey5jb250YWluZXJ7d2lkdGg6OTUlO319Lyogc2tlbGV0b24gQnV0dG9ucyAqLy5idXR0b257ZGlzcGxheTppbmxpbmUtYmxvY2s7aGVpZ2h0OjQxcHg7cGFkZGluZzowIDMwcHggMCAzMHB4O2NvbG9yOiMyYzNlNTA7dGV4dC1hbGlnbjpjZW50ZXI7Zm9udC1zaXplOjExcHg7Zm9udC13ZWlnaHQ6Ym9sZDtsaW5lLWhlaWdodDozOHB4O2xldHRlci1zcGFjaW5nOi4xcmVtO3RleHQtdHJhbnNmb3JtOnVwcGVyY2FzZTt3aGl0ZS1zcGFjZTpub3dyYXA7YmFja2dyb3VuZC1jb2xvcjp0cmFuc3BhcmVudDtib3JkZXItcmFkaXVzOjRweDtib3JkZXI6MXB4IHNvbGlkICMyYzNlNTA7Y3Vyc29yOnBvaW50ZXI7Ym94LXNpemluZzpib3JkZXItYm94O29wYWNpdHk6MC45O30uYnV0dG9uOmhvdmVye29wYWNpdHk6MTt9LmJ1dHRvbi5idXR0b24tcHJpbWFyeXtjb2xvcjojRkZGO2JhY2tncm91bmQtY29sb3I6IzJjM2U1MDtib3JkZXItY29sb3I6IzJjM2U1MDt9LyogcGFnaW5hdGlvbiAqLy5wYWdpbmF0aW9ue2Rpc3BsYXk6aW5saW5lLWJsb2NrO3BhZGRpbmctbGVmdDowO21hcmdpbjoyMHB4IDA7Ym9yZGVyLXJhZGl1czo0cHg7fS5wYWdpbmF0aW9uID4gbGl7ZGlzcGxheTppbmxpbmU7fS5wYWdpbmF0aW9uID4gbGkgYXtvcGFjaXR5OjAuOTtwb3NpdGlvbjpyZWxhdGl2ZTtmbG9hdDpsZWZ0O3BhZGRpbmc6NnB4IDEycHg7bWFyZ2luLWxlZnQ6LTFweDtsaW5lLWhlaWdodDoxLjQyODU3MTQzO2NvbG9yOiMyYzNlNTA7dGV4dC1kZWNvcmF0aW9uOm5vbmU7YmFja2dyb3VuZC1jb2xvcjojZjBmMGYwO2JvcmRlcjoxcHggc29saWQgIzJjM2U1MDt9LnBhZ2luYXRpb24gPiBsaSBhOmhvdmVyLC5wYWdpbmF0aW9uID4gbGkgYTpmb2N1c3tvcGFjaXR5OjE7ei1pbmRleDoyO2ZvbnQtd2VpZ2h0OmJvbGQ7fS5wYWdpbmF0aW9uID4gbGk6Zmlyc3QtY2hpbGQgPiBhe21hcmdpbi1sZWZ0OjA7Ym9yZGVyLXRvcC1sZWZ0LXJhZGl1czo0cHg7Ym9yZGVyLWJvdHRvbS1sZWZ0LXJhZGl1czo0cHg7fS5wYWdpbmF0aW9uID4gbGk6bGFzdC1jaGlsZCA+IGF7Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6NHB4O2JvcmRlci1ib3R0b20tcmlnaHQtcmFkaXVzOjRweDt9LnBhZ2luYXRpb24gPiAuYWN0aXZle29wYWNpdHk6MTt9LnBhZ2luYXRpb24gPiAuYWN0aXZlIGEsLnBhZ2luYXRpb24gPiAuYWN0aXZlIGE6aG92ZXIsLnBhZ2luYXRpb24gPiAuYWN0aXZlIGE6Zm9jdXN7ei1pbmRleDozO2NvbG9yOiNmZmY7Y3Vyc29yOmRlZmF1bHQ7YmFja2dyb3VuZC1jb2xvcjojMmMzZTUwO30ucGFnaW5hdGlvbiA+IC5kaXNhYmxlZCBhLC5wYWdpbmF0aW9uID4gLmRpc2FibGVkIGE6aG92ZXIsLnBhZ2luYXRpb24gPiAuZGlzYWJsZWQgYTpmb2N1c3tjdXJzb3I6bm90LWFsbG93ZWQ7b3BhY2l0eTowLjg7fSI7czoxNzoic2tlbGV0b24tb25ldGhpcmQiO3M6NTI1MToiLyohIG5vcm1hbGl6ZS5jc3MgdjMuMC4zIHwgTUlUIExpY2Vuc2UgfCBnaXRodWIuY29tL25lY29sYXMvbm9ybWFsaXplLmNzcyAqL2h0bWx7Zm9udC1mYW1pbHk6c2Fucy1zZXJpZjstbXMtdGV4dC1zaXplLWFkanVzdDoxMDAlOy13ZWJraXQtdGV4dC1zaXplLWFkanVzdDoxMDAlfWJvZHl7bWFyZ2luOjB9YXJ0aWNsZSxhc2lkZSxkZXRhaWxzLGZpZ2NhcHRpb24sZmlndXJlLGZvb3RlcixoZWFkZXIsaGdyb3VwLG1haW4sbWVudSxuYXYsc2VjdGlvbixzdW1tYXJ5e2Rpc3BsYXk6YmxvY2t9YXVkaW8sY2FudmFzLHByb2dyZXNzLHZpZGVve2Rpc3BsYXk6aW5saW5lLWJsb2NrO3ZlcnRpY2FsLWFsaWduOmJhc2VsaW5lfWF1ZGlvOm5vdChbY29udHJvbHNdKXtkaXNwbGF5Om5vbmU7aGVpZ2h0OjB9W2hpZGRlbl0sdGVtcGxhdGV7ZGlzcGxheTpub25lfWF7YmFja2dyb3VuZC1jb2xvcjp0cmFuc3BhcmVudH1hOmFjdGl2ZSxhOmhvdmVye291dGxpbmU6MH1hYmJyW3RpdGxlXXtib3JkZXItYm90dG9tOjFweCBkb3R0ZWR9YixzdHJvbmd7Zm9udC13ZWlnaHQ6NzAwfWRmbntmb250LXN0eWxlOml0YWxpY31oMXtmb250LXNpemU6MmVtO21hcmdpbjouNjdlbSAwfW1hcmt7YmFja2dyb3VuZDojZmYwO2NvbG9yOiMwMDB9c21hbGx7Zm9udC1zaXplOjgwJX1zdWIsc3Vwe2ZvbnQtc2l6ZTo3NSU7bGluZS1oZWlnaHQ6MDtwb3NpdGlvbjpyZWxhdGl2ZTt2ZXJ0aWNhbC1hbGlnbjpiYXNlbGluZX1zdXB7dG9wOi0uNWVtfXN1Yntib3R0b206LS4yNWVtfWltZ3tib3JkZXI6MH1zdmc6bm90KDpyb290KXtvdmVyZmxvdzpoaWRkZW59ZmlndXJle21hcmdpbjoxZW0gNDBweH1ocntib3gtc2l6aW5nOmNvbnRlbnQtYm94O2hlaWdodDowfXByZXtvdmVyZmxvdzphdXRvfWNvZGUsa2JkLHByZSxzYW1we2ZvbnQtZmFtaWx5Om1vbm9zcGFjZTtmb250LXNpemU6MWVtfWJ1dHRvbixpbnB1dCxvcHRncm91cCxzZWxlY3QsdGV4dGFyZWF7Y29sb3I6aW5oZXJpdDtmb250OmluaGVyaXQ7bWFyZ2luOjB9YnV0dG9ue292ZXJmbG93OnZpc2libGV9YnV0dG9uLHNlbGVjdHt0ZXh0LXRyYW5zZm9ybTpub25lfWJ1dHRvbixodG1sIGlucHV0W3R5cGU9ImJ1dHRvbiJdLGlucHV0W3R5cGU9cmVzZXRdLGlucHV0W3R5cGU9c3VibWl0XXstd2Via2l0LWFwcGVhcmFuY2U6YnV0dG9uO2N1cnNvcjpwb2ludGVyfWJ1dHRvbltkaXNhYmxlZF0saHRtbCBpbnB1dFtkaXNhYmxlZF17Y3Vyc29yOmRlZmF1bHR9YnV0dG9uOjotbW96LWZvY3VzLWlubmVyLGlucHV0OjotbW96LWZvY3VzLWlubmVye2JvcmRlcjowO3BhZGRpbmc6MH1pbnB1dHtsaW5lLWhlaWdodDpub3JtYWx9aW5wdXRbdHlwZT1jaGVja2JveF0saW5wdXRbdHlwZT1yYWRpb117Ym94LXNpemluZzpib3JkZXItYm94O3BhZGRpbmc6MH1pbnB1dFt0eXBlPSJudW1iZXIiXTo6LXdlYmtpdC1pbm5lci1zcGluLWJ1dHRvbixpbnB1dFt0eXBlPSJudW1iZXIiXTo6LXdlYmtpdC1vdXRlci1zcGluLWJ1dHRvbntoZWlnaHQ6YXV0b31pbnB1dFt0eXBlPXNlYXJjaF17LXdlYmtpdC1hcHBlYXJhbmNlOnRleHRmaWVsZDtib3gtc2l6aW5nOmNvbnRlbnQtYm94fWlucHV0W3R5cGU9InNlYXJjaCJdOjotd2Via2l0LXNlYXJjaC1jYW5jZWwtYnV0dG9uLGlucHV0W3R5cGU9InNlYXJjaCJdOjotd2Via2l0LXNlYXJjaC1kZWNvcmF0aW9uey13ZWJraXQtYXBwZWFyYW5jZTpub25lfWZpZWxkc2V0e2JvcmRlcjoxcHggc29saWQgc2lsdmVyO21hcmdpbjowIDJweDtwYWRkaW5nOi4zNWVtIC42MjVlbSAuNzVlbX1sZWdlbmR7Ym9yZGVyOjA7cGFkZGluZzowfXRleHRhcmVhe292ZXJmbG93OmF1dG99b3B0Z3JvdXB7Zm9udC13ZWlnaHQ6NzAwfXRhYmxle2JvcmRlci1jb2xsYXBzZTpjb2xsYXBzZTtib3JkZXItc3BhY2luZzowfXRkLHRoe3BhZGRpbmc6MH0vKiogU2tlbGV0b24gVjIuMC40KiBDb3B5cmlnaHQgMjAxNCwgRGF2ZSBHYW1hY2hlKiB3d3cuZ2V0c2tlbGV0b24uY29tKiBGcmVlIHRvIHVzZSB1bmRlciB0aGUgTUlUIGxpY2Vuc2UuKiBodHRwOi8vd3d3Lm9wZW5zb3VyY2Uub3JnL2xpY2Vuc2VzL21pdC1saWNlbnNlLnBocCogMTIvMjkvMjAxNCovLyogVGFibGUgb2YgY29udGVudHPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJMtIEdyaWQtIEJhc2UgU3R5bGVzLSBUeXBvZ3JhcGh5LSBMaW5rcy0gQnV0dG9ucy0gRm9ybXMtIExpc3RzLSBDb2RlLSBUYWJsZXMtIFNwYWNpbmctIFV0aWxpdGllcy0gQ2xlYXJpbmctIE1lZGlhIFF1ZXJpZXMqLy8qIEdyaWTigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJPigJMgKi8uY29udGFpbmVye3Bvc2l0aW9uOnJlbGF0aXZlO3dpZHRoOjEwMCU7bWF4LXdpZHRoOjk2MHB4O21hcmdpbjowIGF1dG87cGFkZGluZzowIDIwcHg7Ym94LXNpemluZzpib3JkZXItYm94O30uY29sdW1uLC5jb2x1bW5ze3dpZHRoOjEwMCU7ZmxvYXQ6bGVmdDtib3gtc2l6aW5nOmJvcmRlci1ib3g7fS8qIEZvciBkZXZpY2VzIGxhcmdlciB0aGFuIDQwMHB4ICovQG1lZGlhIChtaW4td2lkdGg6NDAwcHgpey5jb250YWluZXJ7d2lkdGg6ODUlO3BhZGRpbmc6MDt9fS8qIEZvciBkZXZpY2VzIGxhcmdlciB0aGFuIDU1MHB4ICovQG1lZGlhIChtaW4td2lkdGg6NTUwcHgpey5jb250YWluZXJ7d2lkdGg6ODAlO30gLmNvbHVtbiwgLmNvbHVtbnN7bWFyZ2luLWxlZnQ6NCU7fSAuY29sdW1uOmZpcnN0LWNoaWxkLCAuY29sdW1uczpmaXJzdC1jaGlsZHttYXJnaW4tbGVmdDowO30gLm9uZS5jb2x1bW4sIC5vbmUuY29sdW1uc3t3aWR0aDo0LjY2NjY2NjY2NjY3JTt9IC50d28uY29sdW1uc3t3aWR0aDoxMy4zMzMzMzMzMzMzJTt9IC50aHJlZS5jb2x1bW5ze3dpZHRoOjIyJTt9IC5mb3VyLmNvbHVtbnN7d2lkdGg6MzAuNjY2NjY2NjY2NyU7fSAuZml2ZS5jb2x1bW5ze3dpZHRoOjM5LjMzMzMzMzMzMzMlO30gLnNpeC5jb2x1bW5ze3dpZHRoOjQ4JTt9IC5zZXZlbi5jb2x1bW5ze3dpZHRoOjU2LjY2NjY2NjY2NjclO30gLmVpZ2h0LmNvbHVtbnN7d2lkdGg6NjUuMzMzMzMzMzMzMyU7fSAubmluZS5jb2x1bW5ze3dpZHRoOjc0LjAlO30gLnRlbi5jb2x1bW5ze3dpZHRoOjgyLjY2NjY2NjY2NjclO30gLmVsZXZlbi5jb2x1bW5ze3dpZHRoOjkxLjMzMzMzMzMzMzMlO30gLnR3ZWx2ZS5jb2x1bW5ze3dpZHRoOjEwMCU7bWFyZ2luLWxlZnQ6MDt9IC5vbmUtdGhpcmQuY29sdW1ue3dpZHRoOjMwLjY2NjY2NjY2NjclO30gLnR3by10aGlyZHMuY29sdW1ue3dpZHRoOjY1LjMzMzMzMzMzMzMlO30gLm9uZS1oYWxmLmNvbHVtbnt3aWR0aDo0OCU7fSAvKiBPZmZzZXRzICovIC5vZmZzZXQtYnktb25lLmNvbHVtbiwgLm9mZnNldC1ieS1vbmUuY29sdW1uc3ttYXJnaW4tbGVmdDo4LjY2NjY2NjY2NjY3JTt9IC5vZmZzZXQtYnktdHdvLmNvbHVtbiwgLm9mZnNldC1ieS10d28uY29sdW1uc3ttYXJnaW4tbGVmdDoxNy4zMzMzMzMzMzMzJTt9IC5vZmZzZXQtYnktdGhyZWUuY29sdW1uLCAub2Zmc2V0LWJ5LXRocmVlLmNvbHVtbnN7bWFyZ2luLWxlZnQ6MjYlO30gLm9mZnNldC1ieS1mb3VyLmNvbHVtbiwgLm9mZnNldC1ieS1mb3VyLmNvbHVtbnN7bWFyZ2luLWxlZnQ6MzQuNjY2NjY2NjY2NyU7fSAub2Zmc2V0LWJ5LWZpdmUuY29sdW1uLCAub2Zmc2V0LWJ5LWZpdmUuY29sdW1uc3ttYXJnaW4tbGVmdDo0My4zMzMzMzMzMzMzJTt9IC5vZmZzZXQtYnktc2l4LmNvbHVtbiwgLm9mZnNldC1ieS1zaXguY29sdW1uc3ttYXJnaW4tbGVmdDo1MiU7fSAub2Zmc2V0LWJ5LXNldmVuLmNvbHVtbiwgLm9mZnNldC1ieS1zZXZlbi5jb2x1bW5ze21hcmdpbi1sZWZ0OjYwLjY2NjY2NjY2NjclO30gLm9mZnNldC1ieS1laWdodC5jb2x1bW4sIC5vZmZzZXQtYnktZWlnaHQuY29sdW1uc3ttYXJnaW4tbGVmdDo2OS4zMzMzMzMzMzMzJTt9IC5vZmZzZXQtYnktbmluZS5jb2x1bW4sIC5vZmZzZXQtYnktbmluZS5jb2x1bW5ze21hcmdpbi1sZWZ0Ojc4LjAlO30gLm9mZnNldC1ieS10ZW4uY29sdW1uLCAub2Zmc2V0LWJ5LXRlbi5jb2x1bW5ze21hcmdpbi1sZWZ0Ojg2LjY2NjY2NjY2NjclO30gLm9mZnNldC1ieS1lbGV2ZW4uY29sdW1uLCAub2Zmc2V0LWJ5LWVsZXZlbi5jb2x1bW5ze21hcmdpbi1sZWZ0Ojk1LjMzMzMzMzMzMzMlO30gLm9mZnNldC1ieS1vbmUtdGhpcmQuY29sdW1uLCAub2Zmc2V0LWJ5LW9uZS10aGlyZC5jb2x1bW5ze21hcmdpbi1sZWZ0OjM0LjY2NjY2NjY2NjclO30gLm9mZnNldC1ieS10d28tdGhpcmRzLmNvbHVtbiwgLm9mZnNldC1ieS10d28tdGhpcmRzLmNvbHVtbnN7bWFyZ2luLWxlZnQ6NjkuMzMzMzMzMzMzMyU7fSAub2Zmc2V0LWJ5LW9uZS1oYWxmLmNvbHVtbiwgLm9mZnNldC1ieS1vbmUtaGFsZi5jb2x1bW5ze21hcmdpbi1sZWZ0OjUyJTt9fS8qIEJhc2UgU3R5bGVz4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCT4oCTICovLyogTk9URWh0bWwgaXMgc2V0IHRvIDYyLjUlIHNvIHRoYXQgYWxsIHRoZSBSRU0gbWVhc3VyZW1lbnRzIHRocm91Z2hvdXQgU2tlbGV0b25hcmUgYmFzZWQgb24gMTBweCBzaXppbmcuIFNvIGJhc2ljYWxseSAxLjVyZW0gPSAxNXB4OikgKi9odG1se2ZvbnQtc2l6ZTo2Mi41JTt9Ym9keXtmb250LXNpemU6MS41ZW07LyogY3VycmVudGx5IGVtcyBjYXVzZSBjaHJvbWUgYnVnIG1pc2ludGVycHJldGluZyByZW1zIG9uIGJvZHkgZWxlbWVudCAqLyBsaW5lLWhlaWdodDoxLjY7Zm9udC13ZWlnaHQ6NDAwO2ZvbnQtZmFtaWx5OiJSYWxld2F5IiwgIkhlbHZldGljYU5ldWUiLCAiSGVsdmV0aWNhIE5ldWUiLCBIZWx2ZXRpY2EsIEFyaWFsLCBzYW5zLXNlcmlmO2NvbG9yOiMyMjI7fS8qIFNlbGYgQ2xlYXJpbmcgR29vZG5lc3MgKi8uY29udGFpbmVyOmFmdGVyLC5yb3c6YWZ0ZXIsLnUtY2Z7Y29udGVudDoiIjtkaXNwbGF5OnRhYmxlO2NsZWFyOmJvdGg7fSI7fQ==";
		$params['circle']['meta']['data']['inline_css2'] = unserialize64($tmp);
		$params['circle']['meta']['hide_login'] = true;
		$params['circle']['meta']['dis_newacc']=1;
		if ($database->sql_update( "update ".DBX."circles set metadata=? where id=?", serialize64($params['circle']['meta']), $p_circle)) {
		}

	}

	if (!$onethird_tester) {
		if (@unlink('install.php')) {
			$postmess[] = "{$ok_icon}Delete install.php OK";
		} else {
			$postmess[] = "{$waring_icon}Failed to datete install.php";
		}

		if (is_file(dirname(__FILE__).'/install_sql.php')) {
			if (@unlink(dirname(__FILE__).'/install_sql.php')) {
				$postmess[] = "{$ok_icon}Delete install_sql.php OK";
			} else {
				$postmess[] = "{$waring_icon}Failed to datete install_sql.php";
			}
		}

		if (is_file('language.php')) {
			if (@unlink('language.php')) {
				$postmess[] = "{$ok_icon}Delete language.php OK";
			} else {
				$postmess[] = "{$waring_icon}Failed to datete language.php";
			}
		}
	}

	return true;

}

function init_sql_mysql_def( &$xparam )
{
$buff= <<<EOT

CREATE TABLE IF NOT EXISTS `{$xparam['p_prefix']}circles` (
  `id` int(11) NOT NULL auto_increment,
  `name` tinytext character set utf8,
  `owner` int(11) default NULL,
  `metadata` text default NULL,
  `icon` tinytext default NULL,
  `public_flag` smallint(6) default NULL,
  `join_flag` smallint(6) default NULL,
  `contents` text character set utf8 default NULL,
  `cid` tinytext default NULL,
  `login_date` datetime default NULL,
  UNIQUE KEY `id` (`id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{$xparam['p_prefix']}data_items` (
  `id` int(11) NOT NULL auto_increment,
  `title` tinytext default NULL,
  `contents` text default NULL,
  `type` smallint NOT NULL default '0' COMMENT ' =1 page, =2 link, =4 sidebar, その他プラグイン参照',
  `block_type` tinyint(4) NOT NULL default '0' COMMENT ' =0 none, =1 通常, =3 プラグイン, =5 インナーページ',
  `date` datetime default NULL,
  `mod_date` datetime default NULL,
  `tag` tinytext default NULL,
  `circle` int(11) NOT NULL default '1',
  `metadata` text default NULL,
  `user` int(11) NOT NULL default '0',
  `link` int(11) NOT NULL default '0',
  `mode` tinyint(4) NOT NULL default '0' ,
  `pv_count` int(11) NOT NULL default '0' ,
  PRIMARY KEY  (`id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{$xparam['p_prefix']}joined_circle` (
  `id` int(11) NOT NULL auto_increment,
  `user` int(11) NOT NULL default '1',
  `circle` int(11) NOT NULL default '1',
  `acc_right` smallint(6) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE  `{$xparam['p_prefix']}action_log` (
  `id` int(11) NOT NULL auto_increment,
  `circle` int(11) NOT NULL default '1',
  `type` smallint NOT NULL default '0' COMMENT '=1 mail log, =2 cron',
  `date` DATETIME default NULL,
  `read_date` DATETIME default NULL,
  `data` TINYTEXT default NULL,
  PRIMARY KEY  (`id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{$xparam['p_prefix']}users` (
  `id` int(11) NOT NULL auto_increment,
  `mailadr` tinytext character set utf8 default NULL,
  `name` tinytext character set utf8 default NULL,
  `password` tinytext character set utf8 default NULL,
  `metadata` text character set utf8 default NULL,
  `session` text character set utf8 default NULL,
  `nickname` tinytext character set utf8 default NULL,
  `fb_name` tinytext character set utf8 default NULL,
  `tw_name` tinytext character set utf8 default NULL,
  `status` tinyint(4) default '0',
  `create_date` datetime default NULL,
  `login_date` datetime default NULL,
  `area` tinytext character set utf8 collate utf8_unicode_ci default NULL,
  `img` mediumtext character set utf8 collate utf8_unicode_ci default NULL,
  `sns_crypt` tinytext character set armscii8 default NULL,
  `sns_crypt2` tinytext character set armscii8 default NULL,
  `token` tinytext character set armscii8 default NULL,
  `login_mode` tinyint(4) default NULL,
  UNIQUE KEY `id` (`id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{$xparam['p_prefix']}user_log` (
  `id` int(11) NOT NULL auto_increment,
  `user` int(11) default NULL,
  `type` smallint NOT NULL default '0',
  `status` int(11) NOT NULL default '0',
  `date` datetime default NULL,
  `att`  tinytext character set utf8 default NULL,
  `link` int(11) NOT NULL default '0',
  `data` tinytext character set utf8 default NULL,
  `circle` int(11) NOT NULL default '1',
  `metadata` text default NULL,
  UNIQUE KEY `id` (`id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{$xparam['p_prefix']}storage` (
  `id` int(11) NOT NULL auto_increment,
  `type` int(11) NOT NULL COMMENT '=0 metadata, =1 text',
  `data` text default NULL,
  `name` tinytext default NULL,
  `circle` int(11) NOT NULL default '1',
  PRIMARY KEY  (`id`)
) DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

EOT;
	return $buff;
}

function init_sql_sqlite_def()
{
$buff= <<<EOT
CREATE TABLE 'action_log' (
    'id' INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
    'circle' INTEGER DEFAULT 0,
    'type' INTEGER DEFAULT 0,
    'date' DATETIME,
    'read_date' DATETIME,
    'data' TEXT
);

CREATE TABLE 'circles' (
    'id' INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
    'name' TEXT,
    'owner' INTEGER DEFAULT '',
    'metadata' TEXT,
    'icon' TEXT,
    'public_flag' INTEGER DEFAULT 0,
    'join_flag' INTEGER DEFAULT 0,
    'contents' TEXT,
    'cid' TEXT,
    'login_date' DATETIME
);

CREATE TABLE 'data_items' (
    'id' INTEGER PRIMARY KEY AUTOINCREMENT,
    'title' TEXT,
    'contents' TEXT,
    'type' INTEGER DEFAULT 0,
    'block_type' INTEGER DEFAULT 0,
    'date' DATETIME,
    'mod_date' DATETIME,
    'tag' TEXT,
    'circle' INTEGER,
    'metadata' TEXT,
    'user' INTEGER DEFAULT 0,
    'link' INTEGER DEFAULT 0,
    'mode' INTEGER DEFAULT 0,
    'pv_count' INTEGER DEFAULT 0
);

CREATE TABLE 'joined_circle' (
    'id' INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
    'user' INTEGER DEFAULT '',
    'circle' INTEGER NOT NULL,
    'acc_right' INTEGER DEFAULT 0
);

CREATE TABLE 'storage' (
    'id' INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
    'type' INTEGER DEFAULT 0,
    'data' TEXT,
    'name' TEXT,
    'circle' INTEGER DEFAULT 0
);

CREATE TABLE 'user_log' (
    'id' INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
    'user' INTEGER DEFAULT 0,
    'type' INTEGER DEFAULT 0,
    'status' INTEGER DEFAULT 0,
    'date' DATETIME,
    'att' TEXT,
    'link' INTEGER DEFAULT 0,
    'data' TEXT,
    'circle' INTEGER DEFAULT 0,
    'metadata' TEXT
);

CREATE TABLE 'users' (
    'id' INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
    'mailadr' TEXT,
    'name' TEXT,
    'password' TEXT,
    'metadata' TEXT,
    'session' TEXT,
    'nickname' TEXT,
    'fb_name' TEXT,
    'tw_name' TEXT,
    'status' INTEGER DEFAULT 0,
    'create_date' DATETIME,
    'login_date' DATETIME,
    'area' TEXT,
    'img' TEXT,
    'sns_crypt' TEXT,
    'sns_crypt2' TEXT,
    'token' TEXT,
    'login_mode' INTEGER
);

EOT;
	return $buff;
}

function init_config( $xparam )
{
	global $sqlite_dir;

	$cookie_path = rtrim(substr($_SERVER["SCRIPT_NAME"], 0, -strlen('install.php')),'/');
	if (!$cookie_path) {
		$cookie_path = '/';
	}


$buff = <<<EOT
<?php
	global \$params,\$database,\$config,\$html;

	/*
	if (!empty(\$_SERVER["HTTPS"]) && \$_SERVER["HTTPS"]!='off') {
		ini_set('session.cookie_secure', 1);					// HTTPS Secure cookie
	}
	*/
EOT;

	// ロケール判定
	$lang = get_locale_str();
	if ($lang == 'ja') {
$buff .= <<<EOT

	setlocale(LC_ALL, 'ja_jp.utf-8');
	date_default_timezone_set('Asia/Tokyo');
EOT;
	} else {
$buff .= <<<EOT

	date_default_timezone_set('America/New_York');
EOT;
	}

$buff .= <<<EOT

	\$params = array();
	\$config = array();

	\$config['title'] = 'OneThird CMS';						//
	\$config['site']['cookie_path'] = '{$cookie_path}';		//
	\$config['admin_user'] = {$xparam['admin_user']};		// admin user ID
	\$config['default_circle'] = {$xparam['circle']};		// default site NO
	\$config['site']['email'] = '';							//
	//\$config['check_request_uri']=true;

	\$config['permission'] = 0775;							// file write permission

	@session_set_cookie_params(0, \$config['site']['cookie_path']);
	if (isset(\$_COOKIE['otx0'])) {
		@session_start();
	}

	error_reporting(0);										//
	ini_set("display_errors", 0);
	if (isset(\$_SESSION['login_id'])) {
		// for debug
		// error_reporting(E_ALL|E_WARNING); 					// for debug
		// ini_set("display_errors", 1); 						// for debug
	}

	require_once(dirname(__FILE__).'/module/utility.php');
	define('MAX_PAGE_NEST',10);

	mb_internal_encoding('UTF-8');
	setlocale(LC_ALL, 'ja_JP.UTF-8');

	define("DBName", "{$xparam['p_db']}");
	define("DBX", "{$xparam['p_prefix']}");										// MySQL prefix
	define ( "DATABASE_DIR", '$sqlite_dir');				// SQLite

	define("USE_EMAIL", true);								//

	//\$config['write-lock'] = true;							// database write protection
	//\$config['disable_expand'] = true;						// Disable template expands in the page data
	//\$config['admin-rights'] = '';							//
	//\$config['admin_dir'] = 'admin0000';						//

	\$params['magic_number'] = hash('sha1',session_id());			// Magic number for display

	//\$config['folder-systag'] = true;							// use folder tree infomation
EOT;

	if ($_SESSION['install'] == 1) {
$buff .= <<<EOT

	define ( "DATABASE_TYPE", "mysql" );					// MySQL
	//define ( "DATABASE_TYPE", "sqlite" );					// SqLite
	define ( "DATABASE_UESR", "{$xparam['p_dbuser']}" );	// MySQL user
	define ( "DATABASE_PASS", "{$xparam['p_dbpass']}" );	// MySQL passsword
	define ( "DATABASE_HOST", "{$xparam['p_dbhost']}" );	// MySQL HOST
EOT;
	} else {
$buff .= <<<EOT

	define ( "DATABASE_TYPE", "sqlite" );					// SqLite
	//define ( "DATABASE_TYPE", "mysql" );					// MySQL
	//define ( "DATABASE_UESR", "{$xparam['p_dbuser']}" );						// MySQL user name
	//define ( "DATABASE_PASS", "{$xparam['p_dbpass']}" );						// MySQL password
	//define ( "DATABASE_HOST", "{$xparam['p_dbhost']}" );						// MySQL HOST

EOT;
	}
$buff .= <<<EOT

	//\$config['img_quality'] = 90;							// for Resolution
	//\$config['thumb_name'] = '_thumb';						//

	\$config['permalink'] = "p{page}.html";					// Permalink

	// path
	\$config['site_path'] = '{$xparam['root_path']}';
	\$config['site_url'] = "{$xparam['url']}";
	\$config['site_ssl'] = "{$xparam['url']}";
	\$config['files_path'] = '{$xparam['upload_path']}';
	\$config['files_url'] = "{$xparam['url']}files/";
	
	
	//define("ECHO_CONTENTS_SCRIPT", true);				//
	//define("SAVE_CONTENTS_SCRIPT", true);				//


EOT;
	$buff .= '?>';
	return $buff;
}

function init_htaccess( $xparam )
{

$buff = <<<EOT

# Options -Indexes
<Files ~ "^.htaccess$">
    deny from all
</Files>

<Files ~ "^.*\\.tpl$">
    deny from all
</Files>

<Files ~ "^.*\\.db$">
    deny from all
</Files>

<Files ~ "^language.*\\.dat$">
    deny from all
</Files>

EOT;

$buff .= <<<EOT

#php_value upload_max_filesize 20M
#php_value post_max_size 20M
#php_value memory_limit 128M

RewriteEngine On
RewriteBase /
#RewriteRule ^.*/?(admin[0-9a-zA-Z]*)/(.*)$ index.php?__admin=$1&__pg=$2&%{QUERY_STRING} [L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{QUERY_STRING} ^p=(\d+)&i=(.*)$
RewriteRule ^.*/?img.php {$xparam['url']}files/img/%1/%2 [NE,QSA,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php [L]

EOT;

	return $buff;
}

function get_locale_str()
{
	$lang = 'en';
	$languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
	$languages = array_reverse($languages);
	foreach ($languages as $language) {
		if (preg_match('/^ja/i', $language)) {
			$lang = 'ja';
			break;
		}
	}
	return $lang;
}


?>