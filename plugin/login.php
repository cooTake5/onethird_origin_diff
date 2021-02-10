<?php
// ログインパネルを隠している場合でもログインできる
function login_page(&$page_ar)
{
	global $database,$params,$config,$p_circle,$html,$plugin_ar,$ut;
	$buff = '';

	if (isset($_SESSION['login_id'])) {
		header("Location:{$params['circle']['url']}");
		exit();
	}

$params['template_buff'] = <<<EOT
<!doctype html>
<html lang="ja" xmlns="http://www.w3.org/1999/xhtml">
	<head>

		{\$ut->expand('head')}
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
		{\$load('css',"{\$config['site_url']}css/onethird.8")}
		{\$load('css',"{$params['data_url']}theme")}
		{\$load('jquery')}
		{\$ut->expand_sorted('js',1)}
	</head>

	<body>
		<div class="main">
			{\$ut->expand('article')}
		</div>
		{\$ut->expand('meta')}
	</body>

</html>
EOT;
	provide_onethird_object();
	snippet_overlay();
	snippet_avoid_robots();

	$params['manager'] = true;	// 旧版互換のため
	if ($config['admin_dir'] != 'admin') {
		if (empty($params['circle']['meta']['hide_login'])) {
			$ar = $database->sql_select_all("select metadata from ".DBX."circles where id=?",$p_circle);
			if ($ar) {
				$m = unserialize64($ar[0]['metadata']);
				$m['hide_login'] = true;
				if ($database->sql_update( "update ".DBX."circles set metadata=? where id=?", serialize64($m), $p_circle)) {
				}
			}
		}
		if ($params['url_prefix'] != $config['admin_dir']) {
			avoid_attack();
$buff = <<<EOT
			<h1>Warning : Login URL is different.</h1>
EOT;
			return $buff;
		}
	}

	if (substr($config['site_ssl'],0,6) == 'https:' && (empty($_SERVER["HTTPS"]) ||  $_SERVER["HTTPS"] != 'on') && isset($params['circle']['meta']['https_force'])) {
		header("Location: ".$config['site_ssl'].$plugin_ar[ LOGIN_ID ]['selector']);
		exit();
	}
	
	$params['circle']['join_flag'] = 1;		// 非公開サイトでも表示する

	$request_uri = "{$config['site_ssl']}{$config['admin_dir']}/account.php?plugin={$plugin_ar[ LOGIN_ID ]['selector']}";
	if (!empty($_SERVER["QUERY_STRING"])) {
		$request_uri .= '&'.$_SERVER["QUERY_STRING"];
	}

	if (isset($_GET['forget'])) {
$html['meta'][] = <<<EOT
		<script>
			\$(function(){
				ot.open_dialog(\$("#forget_accont").width(510),{position:'fixed'});
				\$('#onethird-dialog-warp').css('background-color','#c0c0c0');
			});
			function do_forget_rq() {
				ajax_data = {
					type: "POST"
					, url: "{$config['site_url']}{$config['admin_dir']}/account.php?circle={$p_circle}"
					, data: "ajax=do_forget_rq&adr="+\$('#adr').val()+"&plugin={$plugin_ar[ LOGIN_ID ]['selector']}"
					, dataType:'json'
					, success: function(data){
						if ( data && data['result'] ) {
							alert('登録メールアドレス宛てにログイン用URLを送りました\\n３０分以内にメールを確認してください');
							location.href="{$ut->link()}";
						} else {
							alert('System error');
						}
					}
				};
				if (ot.msie && window.XDomainRequest && window.addEventListener) {
					window.addEventListener("message", receive_xdom, false);
				} else {
					\$.ajax( ajax_data );
				}
			}
		</script>
EOT;
$buff .= <<<EOT
		<div id="forget_accont" class='onethird-dialog' >
			<p class='title'>パスワード、メールアドレス紛失</p>
			<div class='onethird-setting'>
				<div class='contents_item'>
					<p>ハンドルネームまたはメールアドレスを入力して送信ボタンを押してください</p>
					<p>登録されたメールアドレスにパスワードリセットメールが届きます</p>
				</div>

				<input type='hidden' name='mdx' id='mdx' value='forget_account' />
				
				<br />

				<p>メールアドレスor ハンドルネーム</p>
				<input type='text' name='adr' id='adr' />

				<input type='button' value='送信' class='onethird-button' onclick='do_forget_rq()' />
				<a href='{$ut->link()}' class='onethird-button' >Close</a>
			</div>
		</div>
EOT;
	} else if (isset($_GET['create_account'])) {
$buff .= <<<EOT
		<div id="make_account" class='onethird-dialog' >
			<p class='title'>新規アカウント作成</p>
			<div class='onethird-setting'>
				<div class='message'>
					メールアドレスを書いて送信ボタンを押してください<br />
					指定されたメールアドレスにメールが届きますので、メールに書いてあるURLをクリックすると登録完了となります
				</div>
				<table>
					<tr>
						<td>メールアドレス</td>
						<td><input type='text' name='adr' id='adr' /> </td>
					</tr>
					<tr>
						<td>パスワード</td>
						<td><input type='password' name='pass' id='pass' /> </td>
					</tr>
				</table>
				<div class='actions' >
					<input type='button' value='Send' class='onethird-button offset2' onclick='make_account()'  />
					<a href='{$ut->link()}' class='onethird-button' >Close</a>
				</div>
			</div>
		</div>
EOT;
$html['meta'][] = <<<EOT
		<script>
			\$(function(){
				ot.open_dialog(\$( "#make_account" ).width(510),{position:'fixed'});
				\$('#onethird-dialog-warp').css('background-color','#c0c0c0');
			});
			function make_account() {
				ajax_data = {
					type: "POST"
					, url: '{$request_uri}'
					, data: "ajax=make_account&adr="+\$('#adr').val()+'&pass='+\$('#pass').val()
					, dataType:'json'
					, success: function(data){
						ot.overlay_encrypt(0);
						if ( data && data['result'] ) {
							ot.close_dialog();
							alert('登録メールが送信されました\\n３０分以内にメールを確認してください');
							location.href="{$ut->link()}";
						} else {
							alert('System error');
						}
					}
				};
				if (ot.msie && window.XDomainRequest && window.addEventListener) {
					window.addEventListener("message", receive_xdom, false);
				} else {
					\$.ajax( ajax_data );
				}
			}
		</script>
EOT;
	} else {
$html['meta'][] = <<<EOT
		<script>
		\$(function(){
			ot.show_login_panel(true);
			\$('#onethird-dialog-warp').css('background-color','#c0c0c0');
		});
		</script>
EOT;
	}

	snippet_loginform(true);
	return $buff;
}

?>