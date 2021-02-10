<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.

	global $p_registration,$request_uri,$otoken,$login_id,$ut;

	define('MAX_LIST',100);

	require_once(dirname(__FILE__).'/../config.php');
	require_once(dirname(__FILE__).'/../module/utility.basic.php');

	basic_initialize();

	if (!check_rights('admin')) {
		exit_proc(403, 'Need administrator rights');
	}
	
	snippet_overlay();
	avoid_attack();
	snippet_avoid_robots();

	$params['manager'] = 'pmanager';
	$params['template'] = 'admin.tpl';

	if (!isset($params['circle'])) {
		system_error( __FILE__, __LINE__ );
	}

	// 標準プラグイン/テーマプラグイン読み込み
	load_site_plugin();

	$html['article'][] = draw_metadata();

	snippet_header();
	snippet_system_nav();
	snippet_footer();

	expand_circle_html();

function draw_metadata()
{
	global $p_circle ;
	global $database,$params,$config,$html,$ut;

	$params['template'] = 'admin.tpl';
	$buff = '';
	
$html['css'][] = <<<EOT
<style>
	#dump_metadata {
		border:1px solid #c0c0c0;
		margin-top: 20px;
		padding: 10px;
	}
	#dialog_data {
		border:1px solid #c0c0c0;
		padding:10px;
	}
	.onethird-frame {
		margin-bottom:20px;
	}
</style>
EOT;

	if (!check_rights('admin')) {
		return;
	}

	if ( isset($_POST['ajax']) ) {
		if ($_POST['ajax'] == 'repair_page_meta')  {
			$r = array();
			$r['result'] = false;
			if (isset($_GET['page'])) {
				$r['result'] = true;
				$p_page = (int)$_GET['page'];
				regenerate_attached( $p_page , true );
				regenerate_foldertag( $p_page );
			}
			echo( json_encode($r) );
			exit();
		}
		if ($_POST['ajax'] == 'remove_meta')  {
			$r = array();
			$r['result'] = false;
			if (isset($_GET['user_log'])) {
				$user_log = (int)$_GET['user_log'];
				$x = sanitize_asc($_POST['x']);
				$ar = $database->sql_select_all("select metadata from ".DBX."user_log where id=? ", $user_log);
				if ($ar) {
					$m = unserialize64($ar[0]['metadata']);
					if ($m) {
						$new_ar = array();
						_del_metadata($x, $m, $new_ar);
						if ($database->sql_update("update ".DBX."user_log set metadata=? where id=? ", serialize64($new_ar), $user_log)) {
							$r['result'] = true;
						}
					}
				}
			} else if (isset($_GET['page']) && $_GET['page']) {
				$p_page = (int)$_GET['page'];
				$x = sanitize_asc($_POST['x']);
				$ar = $database->sql_select_all("select title,metadata from ".DBX."data_items where id=? ", $p_page);
				if ($ar) {
					$params['metadata'] = unserialize64($ar[0]['metadata']);
					if ($params['metadata']) {
						$new_ar = array();
						_del_metadata($x, $params['metadata'], $new_ar);
						if ($database->sql_update("update ".DBX."data_items set metadata=? where id=? ", serialize64($new_ar), $p_page)) {
							$r['result'] = true;
						}
					}
				}
			} else {
				$x = sanitize_asc($_POST['x']);
				$ar = $database->sql_select_all("select metadata from ".DBX."circles where id=? ", $p_circle);
				if ($ar) {
					$params['metadata'] = unserialize64($ar[0]['metadata']);
					if ($params['metadata']) {
						$new_ar = array();
						_del_metadata($x, $params['metadata'], $new_ar);
						if ($database->sql_update("update ".DBX."circles set metadata=? where id=? ", serialize64($new_ar), $p_circle)) {
							$r['result'] = true;
						}
					}
				}
			}
			echo( json_encode($r) );
			exit();
		}
	}

	if (!empty($_GET['page'])) {
$buff .= <<<EOT
		<a href='{$ut->link($_GET['page'])}' class='onethird-button' >show page</a>
EOT;
	}

	if (check_rights('super') && !empty($_GET['page'])) {
$buff .= <<<EOT
		<a href='{$config['site_url']}{$config['admin_dir']}/pmanager.php?page=0&amp;circle=$p_circle' class='onethird-button' >show circle metadata</a>
EOT;
	}
	if (!empty($_GET['page'])) {
$buff .= <<<EOT
		<a href='javascript:void(ot.repair_page_meta())' class='onethird-button' >repair metadata</a>
EOT;
	}
		$buff .= _darw_page_data();
		$buff .= _darw_metadata();
$buff .= <<<EOT
	</div>
EOT;

$html['meta'][] = <<<EOT
<script>
	ot.repair_page_meta = function () {
		if ( confirm("Inner page cache will be clear. Do you want to run?") ){
			ot.ajax({
				type: "POST"
				, url: '{$params['safe_request']}'
				, data: "ajax=repair_page_meta"
				, dataType:'json'
				, success: function(data){
					if ( data['result'] ) {
						location.reload(true);
					}
				}
			});
		}
	};
	ot.remove_meta = function (x) {
		if ( confirm("Metadata will be change. Do you want to run?") ){
			ot.ajax({
				type: "POST"
				, url: '{$params['safe_request']}'
				, data: "ajax=remove_meta&x="+x
				, dataType:'json'
				, success: function(data){
					if ( data['result'] ) {
						location.reload(true);
					}
				}
			});
		}
	};
</script>
EOT;

	return frame_renderer($buff);

}

function _darw_metadata()
{

	global $database,$params,$config,$html,$p_circle;
	

	if (isset($_GET['user_log'])) {
		$user_log = (int)$_GET['user_log'];
		$ar = $database->sql_select_all("select metadata from ".DBX."user_log where id=? ", $user_log);
	} else {
		if (!isset($_GET['page'])) {
			return "- page error";
		}
		$p_page = (int)$_GET['page'];
		if ($p_page) {
			$ar = $database->sql_select_all("select title,metadata from ".DBX."data_items where id=? ", $p_page);
		} else {
			$ar = $database->sql_select_all("select metadata from ".DBX."circles where id=? ", $p_circle);
		}
	}
	if (!$ar) {
		return "- number error";
	}

	$params['metadata'] = unserialize64($ar[0]['metadata']);
	if (!$params['metadata']) {
		return "- metadata error";
	}
	$len = strlen($ar[0]['metadata']);

	$buff = _dump_metadata( $params['metadata']);
	if (!empty($_GET['page'])) {
$buff = <<<EOT
		<div id ='dump_metadata'><h2>page meta data</h2>
			<p>size : {$len}</p>
			$buff
		</div>
EOT;
	} else {
$buff = <<<EOT
		<div id ='dump_metadata'><h2>page circle meta data</h2>
			<p>size : {$len}</p>
			$buff
		</div>
EOT;
	}
	return $buff;
}

function _darw_page_data()
{

	global $database,$params,$config,$html,$p_circle,$ut,$plugin_ar;

	if (empty($_GET['page'])) {
		$params['breadcrumb'] = array();
		$params['breadcrumb'][] = array( 'link'=>'', 'text'=>"page circle meta data" );
		return '';
	}
	$ar = array();
	$ar = $database->sql_select_all("select block_type,tag,contents,title,metadata,type,link,id,mode,mod_date,date,user,pv_count from ".DBX."data_items where id=? and circle=?", (int)$_GET['page'], $p_circle);
	if ( $ar ) {
		$ar = $ar[0];
		$ar['contents'] = echo_contents_script($ar['contents']);
		if ($ar['metadata']) {
			$ar['meta'] = unserialize64($ar['metadata']);
		} else {
			$ar['meta'] = array();
		}
		unset($ar['metadata']);
	} else {
		return '';
	}
	$user_name = get_user_name($ar['id']);

	//パンくず表示
	$params['page'] = $ar;
	if ($_GET['page'] == $ut->get_home_id()) {
		$ar['title'] = 'HOME';
	}
	snippet_breadcrumb($ar['link'], $ar['title']);
	$params['breadcrumb'][] = array( 'link'=>'', 'text'=>"page manager" );

	snippet_jqueryui();

$buff = <<<EOT
	<div class='onethird-setting' id='dialog_data'>
		<h2>page data #{$ar['id']}</h2>
		<input type='hidden' id='p_id' readonly value='{$ar['id']}' />
		<table >
			<tr>
				<td >type </td>
				<td >
					<input type='text' id='p_type' value='{$ar['type']}' style='width:4em' />
					<select id='p_type_sel' style='width:7em;' >
						<option value='0' >---</option>
						<option value='1' >standard page</option>
EOT;
						foreach ($plugin_ar as $k=>$v) {
							if (!empty($k['selector'])) { continue; }
$buff .= <<<EOT
								<option value='$k' >{$v['selector']} ($k)</option>
EOT;
						}
$buff .= <<<EOT
						<option value='50' >hidden page (50)</option>
					</select>
				</td>
				<td >tag </td>
				<td ><input type='text' id='p_tag' value='{$ar['tag']}' /></td>
			</tr>
			<tr>
				<td>date </td>
				<td>
					<input type='text' id='p_date' value='{$ut->substr($ar['date'],0,10)}' class='datepicker' style='width:150px' />
					<input type='text' id='p_time' value='{$ut->substr($ar['date'],11,8)}' style='width:150px'  />
				</td>
				<td>link </td><td><input type='text' id='p_link' value='{$ar['link']}' /></td>
			</tr>
			<tr>
				<td>mode </td><td><input type='text' id='p_mode' value='{$ar['mode']}' style='width:4em' />
					<select id='p_mode_sel' style='width:7em;' >
						<option value='' >---</option>
						<option value='0' >draft mode (0)</option>
						<option value='1' >standard mode (1)</option>
						<option value='2' disabled >top page (2)</option>
						<option value='10' >disable basic_renderer (10)</option>
					</select>
				</td>
				<td>pv </td><td><input type='text' id='p_pv' value='{$ar['pv_count']}' /></td>
			</tr>
			<tr>
				<td>block_type </td>
				<td>
					<input type='text' id='p_block_type' value='{$ar['block_type']}' style='width:4em' />
					<select id='p_block_type_sel' style='width:7em;' >
						<option value='' >---</option>
						<option value='1' >normal page (1)</option>
						<option value='5' >inner page (5)</option>
						<option value='9' >enable renderer under draft(9)</option>
						<option value='10' disabled >disable basic_renderer (=10)</option>
						<option value='15' >hide from non edit user, hide from search (>=15)</option>
						<option value='20' >hide strongly (control panel) (>=20)</option>
						<option value='30' >trash (=31 reservation)</option>
					</select>
				</td>
				<td>user </td>
				<td>
					<input type='text' id='p_user' value='{$ar['user']}' style='width:4em'  />
					<span id='p_user_name'>$user_name</span>
				</td>
			</tr>
			<tr>
			</tr>
			<tr>
				<td></td><td><input type='button' value = 'update' onclick='chg_data_id()' class='onethird-button' /></td>
			</tr>
		</table>
		
	</div>
EOT;

$html['meta'][] = <<<EOT
<script>
	function chg_data_id(obj){
		var id = \$('#dialog_data').attr('data-id');
		var chg_id = \$('#dialog_data #p_id').val();
		var link = \$('#dialog_data #p_link').val();
		var type = \$('#dialog_data #p_type').val();
		var mode = \$('#dialog_data #p_mode').val();
		var date = \$('#dialog_data #p_date').val();
		var time = \$('#dialog_data #p_time').val();
		var pv = \$('#dialog_data #p_pv').val();
		var user = \$('#dialog_data #p_user').val();
		var block_type = \$('#dialog_data #p_block_type').val();
		var tag = \$('#dialog_data #p_tag').val();
		var opt = "ajax=chg_data_id&chg_id="+decodeURIComponent(chg_id)+"&link="+decodeURIComponent(link);
		opt +="&date="+decodeURIComponent(date);
		opt +="&time="+decodeURIComponent(time);
		opt +="&pv="+decodeURIComponent(pv)+"&type="+decodeURIComponent(type);
		opt +="&mode="+decodeURIComponent(mode)+"&user="+decodeURIComponent(user);
		opt +="&block_type="+decodeURIComponent(block_type);
		opt +="&tag="+decodeURIComponent(tag);
		ot.overlay(1);
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: opt
			, dataType:'json'
			, obj:obj
			, success: function(data){
				ot.overlay(0);
				if (data && data['result']) {
				} else {
					alert('Error or Did not change the data');
				}
			}
		});
	}
	\$(function(){
		\$( ".datepicker" ).datepicker({ dateFormat: "yy-mm-dd" });
		\$('#p_type_sel').change(function(){
			\$("#p_type").val(\$(this).val());
		});
		\$('#p_mode_sel').change(function(){
			\$("#p_mode").val(\$(this).val());
		});
		\$('#p_block_type_sel').change(function(){
			\$("#p_block_type").val(\$(this).val());
		});
	});
</script>
EOT;
	
	if ( isset($_POST['ajax']) && $_POST['ajax'] == 'chg_data_id' )  {
		$r = array();
		$r['result'] = true;
		$r['id'] = sanitize_num($_GET['page']);
		$r['chg_id'] = sanitize_num($_POST['chg_id']);
		$r['link'] = sanitize_num($_POST['link']);

		$r['pv'] = sanitize_num($_POST['pv']);
		$r['type'] = sanitize_num($_POST['type']);
		$r['mode'] = sanitize_num($_POST['mode']);
		$r['user'] = sanitize_num($_POST['user']);
		$r['tag'] = sanitize_str($_POST['tag']);
		$r['block_type'] = sanitize_num($_POST['block_type']);

		$r['chg_data'] = '';
		$ar = $database->sql_select_all("select id,link,pv_count,mod_date,type,mode,user,tag,block_type from ".DBX."data_items where id=? ", $r['id']);
		if ($r['chg_id'] != $r['id'] && !$r['chg_id'] && !$r['id']) {
			$ar = $database->sql_select_all("select id from ".DBX."data_items where id=? ", $r['chg_id']);
			if (!$ar) {
				if ($database->sql_update("update ".DBX."data_items set id=? where id=?", $r['chg_id'], $r['id'])) {
					if ($database->sql_update("update ".DBX."data_items set link=? where link=?", $r['chg_id'], $r['id'])) {
						$r['result'] = true;
						$r['new_id'] = $r['chg_id'];
					}
				}
				$ar = array();
				get_linked_pages($r['chg_id'], $ar);
				foreach($ar as $v) {
					regenerate_attached($v , true);
					regenerate_foldertag( $v );
				}
				$r['chg_data'] .= 'id,';
			}
		}
		if ($r['link'] != $ar[0]['link']) {
			if ($database->sql_update("update ".DBX."data_items set link=? where id=?", $r['link'], $r['id'])) {
				regenerate_attached($r['link'], true);
				regenerate_foldertag( $r['link'] );
				$r['new_link'] = $r['link'];
				$r['chg_data'] .= 'link,';
			} else {
				$r['result'] = false;
			}
		}
		if ($r['type'] != $ar[0]['type']) {
			if ($database->sql_update("update ".DBX."data_items set type=? where id=?", $r['type'], $r['id'])) {
				regenerate_attached($ar[0]['link'], true);
				regenerate_foldertag( $ar[0]['link'] );
				$r['new_link'] = $r['link'];
				$r['chg_data'] .= 'type,';
			} else {
				$r['result'] = false;
			}
		}
		if ($r['pv'] != $ar[0]['pv_count']) {
			if ($database->sql_update("update ".DBX."data_items set pv_count=? where id=?", $r['pv'], $r['id'])) {
				$r['new_pv'] = $r['pv'];
				$r['chg_data'] .= 'pv,';
			} else {
				$r['result'] = false;
			}
		}
		if ($r['mode'] != $ar[0]['mode']) {
			if ($database->sql_update("update ".DBX."data_items set mode=? where id=?", $r['mode'], $r['id'])) {
				$r['new_mode'] = $r['mode'];
				$r['chg_data'] .= 'mode,';
			} else {
				$r['result'] = false;
			}
		}
		$d0 = date('Y-m-d H:i:s', strtotime($ar[0]['mod_date']));
		$d1 = date('Y-m-d H:i:s', strtotime($_POST['date']." ".$_POST['time']));
		if ($d0 != $d1) {
			$d2 = $d1;
			if ($database->sql_update("update ".DBX."data_items set mod_date=?,date=? where id=?", $d2,$d2, $r['id'])) {
				$r['result'] = true;
				$r['new_date'] = $d1;
				$r['old_date'] = $d0;
				$r['chg_data'] .= 'date,';
			}
		}
		if ($r['user'] != $ar[0]['user']) {
			$x = array();
			$x['id'] = $r['id'];
			$x['meta']['author'] = get_user_name($r['user']);
			if ($x['meta']['author']) {
				$x['user'] = $r['user'];
				if (mod_data_items($x)) {
					$r['chg_data'] .= 'user,';
				} else {
					$r['result'] = false;
				}
			} else {
				$r['result'] = false;
			}
		}
		if ($r['tag'] != $ar[0]['tag']) {
			$x = array();
			$x['id'] = $r['id'];
			$x['tag'] = $r['tag'];
			$r['chg_data'] .= 'tag,';
			if (!mod_data_items($x)) {
				$r['result'] = false;
			}
		}
		if ($r['block_type'] != $ar[0]['block_type']) {
			if ($database->sql_update("update ".DBX."data_items set block_type=? where id=?", $r['block_type'], $r['id'])) {
				$r['result'] = true;
				regenerate_attached($ar[0]['link'], true);
				$r['chg_data'] .= 'block_type,';
			}
		}
		echo( json_encode($r) );
		exit();
	}

	return $buff;

}

function _dump_metadata( &$ar, $key='', $name = '', $page_link=false )
{
	global $database,$params,$config,$html,$ut,$p_circle;

	$buff = '';
	
	foreach ($ar as $k=>$v) {
		if (is_object($v)) {
			$buff .= "<div>data error</div>";
		} else if (is_array($v)) {
			$n = "{$name}[$k]";
			$x = md5($n);
			if (count($v) > 2) {
				$buff .= "<div><a href='javascript:void(ot.remove_meta(\"$x\"))'>{$ut->icon('remove')}</a>$n</div>";
			}
			if ($k === 'renderer') {
				$buff .= _dump_metadata($v, $k, "{$name}[$k]",true);
			} else {
				$buff .= _dump_metadata($v, $k, "{$name}[$k]");
			}
		} else {
			if ($page_link) {
				$n = "{$name}[<a href='{$ut->link("{$config['admin_dir']}/pmanager.php?page=$k")}&amp;circle=$p_circle'>$k</a>]";
			} else {
				$n = "{$name}[$k]";
			}
			$x = md5($n);
			$buff .= "<div><a href='javascript:void(ot.remove_meta(\"$x\"))'>{$ut->icon('remove')}</a>$n = ".adjust_mstring(safe_echo($v),100)."</div>";
		}
	}
	
	return $buff;
}

function _del_metadata( $x, &$ar, &$new_ar, $key='', $name = '' )
{

	foreach ($ar as $k=>&$v) {
		if (is_object($v)) {
		} else if (is_array($v)) {
			$r = array();
			$n = "{$name}[$k]";
			if ($x == md5($n)) {
				continue;
			}
			_del_metadata($x, $v, $r, $k, "{$name}[$k]");
			if ($r) {
				$new_ar[$k] = $r;
			}
		} else {
			$n = "{$name}[$k]";
			if ($x != md5($n)) {
				$new_ar[$k] = $v;
			}
		}
	}
}

?>