<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.

	global $p_registration,$request_uri,$otoken,$login_id,$params;

	require_once(dirname(__FILE__).'/../config.php');
	require_once(dirname(__FILE__).'/../module/utility.basic.php');

	basic_initialize();

	if (!check_rights('admin')) {
		exit_proc(403, 'Need administrator rights');
	}
	
	snippet_dialog();
	snippet_overlay();
	avoid_attack();
	snippet_avoid_robots();

	$params['manager'] = 'member';
	$params['template'] = 'admin.tpl';

	if (!isset($params['circle'])) {
		system_error( __FILE__, __LINE__ );
	}

	// 標準プラグイン/テーマプラグイン読み込み
	load_site_plugin();

	//パンくず表示
	$u = "{$params['request_name']}?circle=$p_circle";
	$params['breadcrumb'][] = array( 'link'=>"$u", 'text'=>'Member list' );

	$html['article'][] = draw_circle_member_list();

	snippet_header();
	snippet_system_nav();
	snippet_footer();

	expand_circle_html();

function draw_circle_member_list()		// $event = 0 メンバー一覧
{
	global $p_circle ;
	global $database,$params,$config,$html,$ut;

	$params['template'] = 'admin.tpl';
	$buff = '';
	
	if ( !check_rights('admin') ) {
		exit_proc(403);
	}

	if ( !isset($params['circle']) ) {
		system_error( __FILE__, __LINE__ );
	}

	// https ajax
	$login_id = https_nonlogin_mode();		// httpsでログインした状態のログインIDをトークンから取得する
	if (isset($_SESSION['login_id'])) {
		$login_id = $_SESSION['login_id'];
	} else {
		//return 'ログインしないとご利用いただけません';
	}
	
	if ($login_id) {
		$login_metadata = null;
		$otoken = https_login_mode( $login_metadata, $login_id );	// 以降で使用するトークンを取得する
	}
	$request_uri = "{$config['site_ssl']}{$config['admin_dir']}/account.php?circle={$p_circle}";

	https_login_script();

$html['meta'][] = <<<EOT
<style>
  img {
    vertical-align: middle;
    margin-left: 6px;
  }
  .lock {
  	background-color: rgba(191, 3, 75, 0.22);
  }
</style>
EOT;

	$option = array();
	$option['total'] = 0;
	$option['offset'] = 0;
	$option['page_size'] = 50;
	$option['url'] = $params['request'];
	$offset_name = 'offset';
	$option['offset_name'] = $offset_name;
	if (isset($_GET[$offset_name])) {
		$option['offset'] = (int)$_GET[$offset_name];
	}

	$list_size = 3;	// user list max
	
	//全員
	$sql = "select count(id) as c from ".DBX."joined_circle where circle=? ";	
	$ar = $database->sql_select_all( $sql, $p_circle );
	if ($ar) {
		$option['total'] = $ar[0]['c'];
	}
	$buff .= std_pagination_renderer( $option ).$buff;
	
	$sql = array();
	$sql[0] = "select 
		name,login_mode,tw_name,fb_name,img,".DBX."users.id as id
		, ".DBX."joined_circle.acc_right as acc_right
		, ".DBX."users.metadata as metadata
		, ".DBX."users.mailadr as mailadr
		, {$ut->date_format(DBX."users.login_date","'%m/%d %H:%i'")} as date
		from ".DBX."users 
		left join ".DBX."joined_circle on ".DBX."joined_circle.user = ".DBX."users.id ";
	$sql[1] = array(" where ".DBX."joined_circle.circle=?",$p_circle);
	$sql[2] = array(" order by login_date desc {$ut->limit($option['offset']*$option['page_size'], $option['page_size'])} ");	
	$ar = $database->sql_select_all($sql );

$buff .= <<<EOT
	<input type='button' value='新規アカウント作成' class='onethird-button mini' onclick='create_new_account()'  />
	<div class='clearfix'>
		<table id='member_list' class='onethird-table'>
		<tr>
			<th></th><th>name</th><th>editor</th><th>admin</th><th>last login</th>
		</tr>
EOT;
		foreach ($ar as $v) {
			$t = $v['login_mode'];
			if ( !isset($v['acc_right']) ) {
				$v['acc_right']=0;
			}
			if ($v['metadata']) {
				$m = unserialize($v['metadata']);
			}
			$v['size'] = 25;
			$name = get_user_avatar_ex($v);
			if (check_rights('admin')) {
				$name .= "<a href='{$config['site_url']}{$config['admin_dir']}/account.php?circle=$p_circle&user={$v['id']}' > ";
				$name .= get_user_name($v['id']);
				$name .= "</a>";
			} else {
				$name .= get_user_name($v['id']);
			}

			$sp = '';

			if ($params['circle']['owner'] == $v['id']) {
				$v['acc_right'] |= 0x3;
				$sp .= 'owner ';
			}
			if ($config['admin_user'] != $v['id'] && (!($v['acc_right'] & 1<<2) || check_rights('sys'))) {
				$sp = "<span style='font-size:80%'>#{$v['id']} </span><img class='right_edithnd' src='{$config['site_url']}img/edit.png' alt='edit' width='20' />";
			}

			$a = (int)$v['acc_right'];
			if (!$a) {
				$style0 = $ut->icon('ng',array('class'=>'acc_right0bit'));
				$style1 = $ut->icon('ng',array('class'=>'acc_right1bit'));
			} else {
				if ($a & 1 << 0) {
					$style0 = $ut->icon('ok',array('class'=>'acc_right0bit'));
				} else {
					$style0 = $ut->icon('ng',array('class'=>'acc_right0bit'));
				}
				if ($a & 1 << 1) {
					$style1 = $ut->icon('ok',array('class'=>'acc_right1bit'));
				} else {
					$style1 = $ut->icon('ng',array('class'=>'acc_right1bit'));
				}
				if ($config['admin_user'] == $v['id']) {
					$sp = '(super)';
					if (!check_rights('super')) {
						continue;
					}
				}
			}
			$lock = '';
			if (!empty($m['login_error'])) {
				$lock = 'lock';
			}

$buff .=  <<<EOT
			<tr class='user_item $lock' data-id='{$v['id']}' data-acc_right='{$v['acc_right']}' >
				<td>{$sp}</td><td>$name</td><td>$style1 </td><td>$style0</td>
				<td>{$v['date']}</td>
			<tr>
EOT;
		}

$buff .= <<<EOT
		</table>
	</div>
	<p><br/></p>
EOT;

$html['meta'][] = <<<EOT
<div id="force_accont" class='onethird-dialog' >
	<p class='title'>新規アカウント作成</p>
	<div class='onethird-setting'>
		<table>
			<tr>
				<td>Name</td>
				<td><input type='text' name='name' id='name' /> </td>
			</tr>
			<tr>
				<td>Password</td>
				<td><input type='password' name='pass' id='pass' /> </td>
			</tr>
		</table>
		<div class='actions' >
			<input type='button' value='作成' class='onethird-button ' onclick='force_accont()'  />
			<input type='button' value='Close' class='onethird-button ' onclick='ot.close_dialog()'  />
		</div>
	</div>
</div>
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
				} else {
					alert('Failed encrypted communication.(1)');
				}
			}
			, error: function(data,status){
				ot.overlay_encrypt(0);
				alert('Failed encrypted communication.(2)');
			}
		};
		if (ot.msie && window.XDomainRequest && window.addEventListener) {
			\$('body').append("<iframe id='xdom' src='{$config['site_ssl']}xdom.php' scrolling='no' style='display:none'></iframe>");
			window.addEventListener("message", receive_xdom, false);

		} else {
			\$.ajax( ajax_data );
		}
	});
	function create_new_account() {
		ot.open_dialog(\$( "#force_accont" ).width(510));
	}
	function force_accont() {
		ajax_data = {
			type: "POST"
			, url: '{$request_uri}'
			, data: "ajax=force_accont&name="+\$('#name').val()+'&pass='+\$('#pass').val()+'&otoken='+ot.otoken
			, dataType:'json'
			, success: function(data){
				ot.overlay_encrypt(0);
				if ( data && data['result'] ) {
					location.reload(true);
				} else {
					alert('ユーザーを追加できません');
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

	// acc_right bit 0 0x1  ... admin
	// acc_right bit 1 0x2  ... edit
	
$tmp = <<<EOT
	<script>
	\$(function(){
		\$('[class=right_edithnd]').click(function(){
			mod_acc(this);
		});
	});
	function mod_acc(obj){
		var v=[];
		obj = obj.parentElement.parentElement;
		\$("#dialog1").attr('data-id',\$(obj).attr('data-id'));
		acc_right = $(obj).attr('data-acc_right');
		if ( acc_right & 1<<0 ) {
			\$('#acc_right0bit').attr('checked',true);
		} else {
			\$('#acc_right0bit').attr('checked',false);
		}
		if ( acc_right & 1<<1 ) {
			\$('#acc_right1bit').attr('checked',true);
		} else {
			\$('#acc_right1bit').attr('checked',false);
		}
		\$('#reset_password').val('');
		ot.open_dialog(\$("#dialog1"));
	}
	function set_acc() {
    	var acc_right=0;
    	var opt = '';
    	var id = \$("#dialog1").attr('data-id');
		if (\$('#acc_right0bit:checked').length) {
			acc_right |= 0x1;
		}
		if (\$('#acc_right1bit:checked').length) {
			acc_right |= 0x2;
		}
		if (\$('#reset_password').val()) {
			opt += "&pass="+\$('#reset_password').val();
		}
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=set_acc&id="+id+"&acc_right="+acc_right+opt+'&otoken='+ot.otoken
			, dataType:'json'
			, success: function(data){
				ot.close_dialog();
				if ( !data || data.result == false ) {
					alert("update failed.");
					return;
				}
				var a = \$('#member_list tr[data-id='+data.id+']');
				a.find('.acc_right').hide();
				if (data.acc_right & 0x1) {
					a.find('.acc_right0bit').attr('src','{$config['site_url']}img/ok.png');
				} else {
					a.find('.acc_right0bit').attr('src','{$config['site_url']}img/ng.png');
				}
				if (data.acc_right & 0x2) {
					a.find('.acc_right1bit').attr('src','{$config['site_url']}img/ok.png');
				} else {
					a.find('.acc_right1bit').attr('src','{$config['site_url']}img/ng.png');
				}
				a.attr('data-acc_right',data.acc_right)
				if (data.mess) {
					alert(data.mess);
				}
			}
		});
	}
	</script>
	<div id="dialog1" class='onethird-dialog' >
		<div class='title'>アクセス権限</div>
		<div class='onethird-setting'>
			<table>
				<tr>
					<td>アクセス権限</td>
					<td>
						<ul>
							<li><label><input type='checkbox' name='acc_right0bit' id='acc_right0bit' >管理者（サイトのすべての操作が可能です）</label></li>
							<li><label><input type='checkbox' name='acc_right1bit' id='acc_right1bit' >編集者（ページ作成/編集、データベースへのデータ追加が行えます）</label></li>
						</ul>
					</td>
				</tr>
				<tr>
					<td>Reset Password</td>
					<td>
						<input type='text' id='reset_password' />
					</td>
				</tr>
			</table>
			<div class='actions'>
				<input type='button' class='onethird-button' value='更新' onclick='set_acc()'  />
				<input type='button' class='onethird-button' value='Close' onclick='ot.close_dialog(this)' />
			</div>
		</div>
	</div>
EOT;

$html['meta'][] = $tmp;

	if (isset($_POST['ajax'])) {
		if ( $_POST['ajax'] == 'set_acc' && isset($_POST['id']) && isset($_POST['acc_right']))  {
			$r = array();
			$r['result'] = false;
			$r['id'] = $id = (int)$_POST['id'];
			$acc_right = (int)$_POST['acc_right'];
			$r['acc_right'] = $acc_right;

			if ( check_rights('admin') ) {
				$ar = $database->sql_select_all( "select acc_right from ".DBX."joined_circle where user=? and circle=?", $id, $p_circle);
				if (!$ar) {
					echo( json_encode($r) );
					exit();
				}
				$acc_right_old = $ar[0]['acc_right'];
				if ( !check_rights('sys') && (($acc_right & 0x4)!=($acc_right_old & 0x4))) {
					// system権限がないとsystem特権は操作できない
					echo( json_encode($r) );
					exit();
				}
				if ( !check_rights('admin') && (($acc_right & 0x1)!=($acc_right_old & 0x1))) {
					// admin権限がないとadmin特権は操作できない
					echo( json_encode($r) );
					exit();
				}

				if ( $database->sql_update("update ".DBX."joined_circle set acc_right=? where user=? and circle=?", $acc_right, $id, $p_circle ) ) {
					$r['result'] = true;
				}
				
				if (isset($_POST['pass']) && $_POST['pass']) {
					$password = hash('sha256', $id.md5(sanitize_str($_POST['pass'])));
					$r['mess'] = "Password has been changed.";
					if (!$database->sql_update("update ".DBX."users set password=? where id=? ", $password, $id)) {
						$r['result'] = false;
					}
				}
			}
			echo( json_encode($r) );
			exit();
		}
	}

	return frame_renderer($buff);

}


?>