<?php
/* pagelist.php : standard list standred plugin
	author: team1/3
	released: 2014/04/24
	coyright(c) SpiQe Software,team1/3 All Rights Reserved.
	
	GET query option
	show_type : show page type
	type : type filter
	all : show all data
	mode : mode filter

*/

define('TEXT_ADJ_SIZE',25);


function list_page(&$page_ar)
{
	global $database,$params,$config,$p_circle,$html,$ut;
	$buff = '';
	$p_page = $page_ar['id'];
	$st = $ut->get_storage('plugin_pagelist');
	if (isset($st['tpl']) && !isset($_GET['x'])) {
		$params['template'] = $st['tpl'];
	}
	if (!check_rights()) {
		if (isset($st['rights'])) {
			$a = $st['rights'];
			if ($a == 0) { $a = 'edit'; }
			if ($a == 1) { $a = ''; }
			if ($a != 3 && !check_rights($a)) {
				exit_proc(403);
			}
		} else {
			exit_proc(403);
		}
		provide_onethird_object();
	}
	
	if (empty($st['max_list_c0'])) {
		$st['max_list_c0'] = 50;
		$ut->set_storage('plugin_pagelist',$st);
	}
	if (empty($st['max_list_c1'])) {
		$st['max_list_c1'] = 7;
		$ut->set_storage('plugin_pagelist',$st);
		set_circle_meta('pagelist',null);	//旧版互換のため
	}


	if (!isset($html['css']['plugin-list'])) {
$html['css']['plugin-list'] = <<<EOT
		<style>
		.pagelist-title {
			margin-top:5px;
			padding:2px 2px 2px 10px;
			vertical-align: middle;
		}
		.pagelist-item {
			padding:3px 3px 3px 20px;
		}
		.item_info {
			color:#8C8C8C;
			font-size:80%;
		}
		.icon {
			width:20px;
			height:20px;
			display:inline-block;
			position: absolute;
			top:1px;
			left:19px;
			cursor:pointer;
		}
		.icon_file {
			background: url({$config['site_url']}img/text.png) no-repeat 0 0;
			background-size: 80%;
		}
		.icon_file_etc {
			background: url({$config['site_url']}img/text2.png) no-repeat 0 0;
			background-size: 80%;
		}
		.icon_file_lock {
			background: url({$config['site_url']}img/lock.png) no-repeat 0 0;
			background-size: 80%;
		}
		.icon_file_binder {
			background: url({$config['site_url']}img/folder.png) no-repeat 0 0;
			background-size: 80%;
		}
		.page_item {
			padding-left:40px;
			height:24px;
			position: relative;
		}
		.tree_0 {
			background: url({$config['site_url']}img/tree.png) no-repeat 0 -59px;
		}
		.tree_1 {
			background: url({$config['site_url']}img/tree.png) no-repeat 0 -80px;
		}
		</style>
EOT;
	}

$html['meta'][] = <<<EOT
	<script>
	function collapse_expand_tree(id,obj) {
		var a = \$('div[data-children='+id+']');
		var b = $('img[data-children-img='+id+']')[0];
		if (a && b) {
			if (a.css('display') == 'block') {
				a.fadeOut();
				b.src = "{$config['site_url']}img/add.png";
			} else {
				a.fadeIn();
				b.src = "{$config['site_url']}img/remove.png";
			}
		}
	}
	function read_more(obj,ofs,link) {
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=read_more&ofs="+ofs+"&link="+link
			, dataType:'json'
			, obj:obj
			, success: function(data){
				if (data && data['result'] && this.obj) {
					\$(obj).after(data['html']);
					\$(obj).remove();
				}
			}
		});
	}
	</script>
EOT;

	if ( isset($_POST['ajax']) && $_POST['ajax'] == 'read_more' )  {
		$r = array();
		$r['result'] = true;
		$r['ofs'] = (int)$_POST['ofs'];
		$r['link'] = (int)$_POST['link'];
		$nest = 0;
		$r['html'] = get_pagelist_tree( $r['link'], $nest, false, $r['ofs'] );
		echo( json_encode($r) );
		exit();
	}


	//管理者メニュー
	if (check_rights('edit')) {
$buff .= <<<EOT
		<div class='edit_pointer'>
			<a href='javascript:void(recovery_link())' class='onethird-button mini' >リンク切れの補修</a>
			<a href='javascript:void(ot.pagelist_setting())' class='onethird-button mini' >Setting</a>
		</div>
EOT;
	}

	if (!$p_page) {
		$p_page = $params['circle']['meta']['top_page'];
	}
	$ar = $database->sql_select_all( "select mode,block_type,date,metadata,link,title,type,id,pv_count from ".DBX."data_items where id=? ", $p_page );
	if (!$ar) {
		return "ページが見つかりません、トップページの再設定を行なってください";
	}
	$params['breadcrumb'][] = array( 'link'=>'', 'text'=>"<a href='{$ut->link('list')}'>ページ一覧</a>" );
	
	$pv_active = check_rights('edit') && isset($params['circle']['meta']['pv_logging']);

	$title = get_pagelist_title( $ar[0] );
	
	if ($pv_active) {
		$pv = " <span class='item_info'>({$ar[0]['pv_count']})</span>";
	} else {
		$pv = '';
	}
$buff .= <<<EOT
	<h1>{$title}$pv</h1>
EOT;

	$recent = isset($_GET['recent']);

	if ($recent) {
$buff .= <<<EOT
		<p style='text-align: right;'>[ <a href='{$ut->link('list')}'>Return page list</a> ] </p>
EOT;

		$option = array();
		$option['total'] = 0;
		$option['offset'] = 0;
		$option['page_size'] = 80;

		$offset_name = 'offset';
		$option['url'] = $params['request'];
		$option['offset_name'] = $offset_name;
		if (isset($_GET[$offset_name])) {
			$option['offset'] = (int)$_GET[$offset_name];
		}

		$sql[0] = "select count(id) as c from ".DBX."data_items ";
		$sql[] = array(" where (mode <> 0 or user= {$_SESSION['login_id']}) and block_type<>5 ");
		$sql[] = array(" and  block_type <> 31");	//ゴミ箱は除外
		

		// count
		$ar = $database->sql_select_all($sql);
		if ($ar && $ar[0]['c']>0) {
			$option['total'] = $ar[0]['c'];
$buff .= <<<EOT
			<p > {$ar[0]['c']} Items(s)
			</p>
EOT;
			$buff .= std_pagination_renderer( $option );
		} else {
$buff .= <<<EOT
			<p >No item
			</p>
EOT;
		}

		// page
		$sql[0] = "select id,type,metadata,title,mode,user,date,mod_date,block_type from ".DBX."data_items ";
		$sql[]  = array(" order by mod_date desc {$ut->limit($option['offset']*$option['page_size'], $option['page_size'])} ");
		
		$ar = $database->sql_select_all($sql);
		if ($ar) {
			foreach ($ar as $v) {
				$m = $v['meta'] = unserialize64($v['metadata']);
				$d = substr($v['mod_date'],0,10);
				$icon = '';
				$att = '';
				if ($v['mode'] == 0) { $icon .= $ut->icon('lock'); }
				if ($v['type'] == 180) { $icon .= $ut->icon('folder'); }
				if ($v['user'] == $_SESSION['login_id']) { $att .= ' '.$ut->icon('personal'); }
				if ((int)$v['block_type'] >= 15) {
					if (!check_rights('admin')) { continue; }
					$att .= ' '.$ut->icon('system',' title="Control panel" width="16" '); 
				}
$buff .= <<<EOT
				<p id='page_finder_{$v['id']}' class='item' ><a href='{$ut->link($v['id'])}' target='_blank' >$d $icon {$v['title']}</a>
				$att
				</p>
EOT;
			}
		} else {
		}
		return frame_renderer($buff);

	} else {
$buff .= <<<EOT
		<p style='text-align: right;'>[ <a href='{$ut->link('list','&:recent=1')}'>Recent updates</a> ] </p>
EOT;
	}
	// top page直下

	if (isset($_GET['offset'])) {
		$offset = (int)$_GET['offset'];
	} else {
		$offset = 0;
	}
	if (isset($_GET['link'])) {
		$link = (int)$_GET['link'];
		$nest = 0;
		$q = $params['canonical'];
		if ($offset || $link) {
			$q .= "?offset=$offset";
			if ($link) {
				$q .= "&link=$link";
			}
			$params['canonical'] = $q;
		}
		$buff .= get_pagelist_tree($link, $nest, true, $offset);

	} else if (isset($_GET['all']) && check_rights('admin')) {
		$nest = 0;
		$buff .= get_pagelist_tree('all', $nest, true, $offset);

	} else {
		$nest = 0;
		$buff .= get_pagelist_tree($p_page, $nest, true);

		$nest = 0;
		$buff .= get_pagelist_tree(0, $nest, true);
	}


	if (!check_rights('admin')) {
$html['meta'][] = <<<EOT
		<script>
		\$(function() {
			\$('.data_id').css('cursor','default');
		});
		</script>
EOT;
		return frame_renderer($buff);
	}
	snippet_dialog();
	
	if (isset($_GET['id'])) {
$html['meta'][] = <<<EOT
		<script>
		\$(function() {
			show_item({$_GET['id']});
		});
		</script>
EOT;
	}
	
$html['meta'][] = <<<EOT
	<script>
	\$(function() {
		\$('.data_id').click(function(){
			var obj = \$(this);
			var id = obj.attr('data-id');
			show_item(id);
		});
	});
	function show_item(id) {
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=get_page_data&page="+id
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					\$('#dialog_data').attr('data-id',data['id']);
					\$('#dialog_data #p_id').val(data['id']);
					\$('#dialog_data #p_link').val(data['link']);
					\$('#dialog_data #p_date').val(data['date']);
					\$('#dialog_data #p_pv').val(data['pv']);
					\$('#dialog_data #p_type').val(data['type']);
					\$('#dialog_data #p_mode').val(data['mode']);
					\$('#dialog_data #p_user').val(data['user']);
					\$('#dialog_data #p_user_name').text(data['author']);
					if (data['collapse']) {
						\$('#dialog_data #collapse').prop('checked',true);
					} else {
						\$('#dialog_data #collapse').prop('checked',false);
					}
					ot.open_dialog(\$('#dialog_data').width(500));
				} else {
				}
			}
		});
	}
	function recovery_link(file) {
		if (confirm("リンク切れのチェック/補修を行います\\n処理に時間が掛かることがります、実行しますか？")) {
			ot.ajax({
				type: "POST"
				, url: '{$params['safe_request']}'
				, data: "ajax=recovery_link"
				, dataType:'json'
				, success: function(data){
					if (data && data['result']) {
						if (data['count']) {
							alert(data['count']+'件のリンクを修正しました');
							location.reload(true);
						} else {
							alert('該当リンクはありません');
						}
					} else {
						alert('補修できませんでした');
					}
				}
			});
		}
	}

	function chg_data_id(obj){
		var id = \$('#dialog_data').attr('data-id');
		var chg_id = \$('#dialog_data #p_id').val();
		var link = \$('#dialog_data #p_link').val();
		var type = \$('#dialog_data #p_type').val();
		var mode = \$('#dialog_data #p_mode').val();
		var date = \$('#dialog_data #p_date').val();
		var pv = \$('#dialog_data #p_pv').val();
		var user = \$('#dialog_data #p_user').val();
		var collapse = $('#dialog_data #collapse').prop('checked');
		ot.close_dialog(obj);
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=chg_data_id&id="+id+"&chg_id="+chg_id+"&link="+link+"&date="+date+"&pv="+pv+"&type="+type+"&mode="+mode+"&user="+user+"&collapse="+collapse
			, dataType:'json'
			, obj:obj
			, success: function(data){
				if (data && data['result']) {
				} else {
					alert('修正エラーまたは、データは変更されませんでした');
				}
			}
		});
	}
	function remove_data_id(obj){
		var id = \$('#dialog_data').attr('data-id');
		ot.close_dialog(obj);
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=remove_page&page="+id
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					if (data['page']) {
						\$('div[data-idx='+data['page']+']').remove();
					}
				} else {
				}
			}
		});
	}
	</script>
	<div id='dialog_data' class='onethird-dialog'>
		<p class='title'>Edit Page data</p>
		<div class='onethird-setting'>
			<table>
				<tr>
					<td>ID </td><td><input type='text' id='p_id' readonly /></td>
					<td>type </td><td><input type='text' id='p_type' /></td>
				</tr>
				<tr>
					<td>date </td><td><input type='text' id='p_date' /></td>
					<td>mode </td><td><input type='text' id='p_mode' /></td>
				</tr>
				<tr>
					<td>link </td><td><input type='text' id='p_link' /></td>
					<td>pv </td><td><input type='text' id='p_pv' /></td>
				</tr>
				<tr>
				</tr>
				<tr>
					<td>user </td><td><input type='text' id='p_user' /></td>
					<td colspan=2><span id='p_user_name'></span></td>
				</tr>
				<tr>
					<td>option </td>
						<td colspan=3>
							<label> <input type='checkbox' id='collapse' /> display tree to collapse </label>
						</td>
					</td>
				</tr>
			</table>
		</div>
		<div style='text-align: center;color:red;'>
			データ変更後は画面を再表示してください
		</div>
		<div class='actions'>
			<input type='button' value='変更' class='onethird-button' onclick='chg_data_id(this)' />
			<input type='button' value='削除' class='onethird-button' onclick='remove_data_id(this)' />
			<input type='button' value='Cancel' onclick='ot.close_dialog(this);' class='onethird-button' /> 
		</div>
	</div>
EOT;

	if (check_rights('admin')) {
		snippet_std_setting('Pagelist Setting','pagelist_setting');
	}

	if ( isset($_POST['ajax']) && $_POST['ajax'] == 'get_page_data' )  {
		$r = array();
		$id = (int)$_POST['page'];
		$ar = $database->sql_select_all("select id,link,pv_count,date,type,mode,user,metadata from ".DBX."data_items where id=? ", $id);
		if ($ar) {
			$r['result'] = true;
			$r['id'] = $ar[0]['id'];
			$r['link'] = $ar[0]['link'];
			$r['date'] = substr($ar[0]['date'],0,10);
			$r['pv'] = $ar[0]['pv_count'];
			$r['type'] = $ar[0]['type'];
			$r['mode'] = $ar[0]['mode'];
			$r['user'] = $ar[0]['user'];
			$st = $ut->get_storage('plugin_pagelist');
			if (isset($st['collapse'][$r['id']])) {
				$r['collapse'] = true;
			}
			$m = unserialize64($ar[0]['metadata']);
			if (isset($m['author'])) {
				$r['author'] = $m['author'];
			} else {
				$r['author'] = '';
			}
		}
		echo( json_encode($r) );
		exit();
	}

	if ( isset($_POST['ajax']) && $_POST['ajax'] == 'pagelist_setting' )  {
		$r = array();
		$r['result'] = true;

		$st = $ut->get_storage('plugin_pagelist');
		$r['rights'] = (isset($st['rights'])) ? $st['rights'] : 0;
		$r['deny_type'] = (isset($st['deny_type'])) ? $st['deny_type'] : '';
		$r['deny_folder'] = (isset($st['deny_folder'])) ? $st['deny_folder'] : '';

		$r['tpl'] = (isset($st['tpl'])) ? $st['tpl'] : '';
		$r['max_list_c0'] = (isset($st['max_list_c0'])) ? $st['max_list_c0'] : '';
		$r['max_list_c1'] = (isset($st['max_list_c1'])) ? $st['max_list_c1'] : '';

		$rights_ar = array('0'=>'edit','1'=>'login user','3'=>'public');
$r['html'] = <<<EOT
		<table>
			<tr>
				<td>template </td><td><input type='text' value='{$r['tpl']}' data-input='tpl' /></td>
			</tr>
			<tr>
				<td>access rights</td>
				<td>
					{$ut->input(
						array('type'=>'select'
							, 'data-input'=>'rights'
							, 'value'=>$r['rights']
							, 'option'=>$rights_ar
						)
					)}
				</td>
			</tr>
			<tr>
				<td>deny type</td>
				<td>
					<input type='text' data-input='deny_type' value='{$r['deny_type']}' />
				</td>
			</tr>
			<tr>
				<td>deny folder</td>
				<td>
					<input type='text' data-input='deny_folder' value='{$r['deny_folder']}' />
				</td>
			</tr>
			<tr>
				<td>MAX list count root </td>
				<td><input type='text' value='{$r['max_list_c0']}' data-input='max_list_c0' /></td>
			</tr>
			<tr>
				<td>MAX list count child </td>
				<td><input type='text' value='{$r['max_list_c1']}' data-input='max_list_c1' /></td>
			</tr>
		</table>
EOT;
		echo( json_encode($r) );
		exit();
	}

	if ( isset($_POST['ajax']) && $_POST['ajax'] == 'save_pagelist_setting' )  {
		$r = array();

		$st = $ut->get_storage('plugin_pagelist');
		if (!empty($_POST['rights'])) {
			$st['rights'] = (int)$_POST['rights'];
		} else {
			unset($st['rights']);
		}
		if (!empty($_POST['deny_type'])) {
			$st['deny_type'] = sanitize_str($_POST['deny_type']);
		} else {
			unset($st['deny_type']);
		}
		if (!empty($_POST['deny_folder'])) {
			$st['deny_folder'] = sanitize_str($_POST['deny_folder']);
		} else {
			unset($st['deny_folder']);
		}

		if (!empty($_POST['limit'])) {
			$st['limit'] = (int)$_POST['limit'];
		} else {
			unset($st['limit']);
		}
		if (!empty($_POST['meta_limit'])) {
			$st['meta_limit'] = (int)$_POST['meta_limit'];
		} else {
			unset($st['meta_limit']);
		}
		if (!empty($_POST['tpl'])) {
			$st['tpl'] = sanitize_asc($_POST['tpl']);
		} else {
			unset($st['tpl']);
		}

		if (!empty($_POST['max_list_c0'])) {
			$st['max_list_c0'] = (int)($_POST['max_list_c0']);
		} else {
			unset($st['max_list_c0']);
		}
		if (!empty($_POST['max_list_c1'])) {
			$st['max_list_c1'] = (int)($_POST['max_list_c1']);
		} else {
			unset($st['max_list_c1']);
		}

		$r['result'] = $ut->set_storage('plugin_pagelist',$st);

		echo( json_encode($r) );
		exit();
	}

	if ( isset($_POST['ajax']) && $_POST['ajax'] == 'chg_data_id' )  {
		$r = array();
		$r['result'] = true;
		$r['id'] = (int)$_POST['id'];
		$r['chg_id'] = (int)$_POST['chg_id'];
		$r['link'] = (int)$_POST['link'];
		$r['pv'] = (int)$_POST['pv'];
		$r['type'] = (int)$_POST['type'];
		$r['mode'] = (int)$_POST['mode'];
		$r['user'] = (int)$_POST['user'];
		$r['collapse'] = $_POST['collapse'] == 'true';
		$ar = $database->sql_select_all("select id,link,pv_count,mod_date,type,mode,user from ".DBX."data_items where id=? ", $r['id']);
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
					regenerate_foldertag($v);
				}
			}
		}
		if ($r['link'] != $ar[0]['link']) {
			if ($database->sql_update("update ".DBX."data_items set link=? where id=?", $r['link'], $r['id'])) {
				regenerate_attached($r['link'], true);
				regenerate_foldertag($r['link']);
				$r['new_link'] = $r['link'];
			} else {
				$r['result'] = false;
			}
		}
		if ($r['type'] != $ar[0]['type']) {
			if ($database->sql_update("update ".DBX."data_items set type=? where id=?", $r['type'], $r['id'])) {
				regenerate_attached($ar[0]['link'], true);
				regenerate_foldertag($ar[0]['link']);
				$r['new_link'] = $r['link'];
			} else {
				$r['result'] = false;
			}
		}
		if ($r['pv'] != $ar[0]['pv_count']) {
			if ($database->sql_update("update ".DBX."data_items set pv_count=? where id=?", $r['pv'], $r['id'])) {
				$r['new_pv'] = $r['pv'];
			} else {
				$r['result'] = false;
			}
		}
		if ($r['mode'] != $ar[0]['mode']) {
			if ($database->sql_update("update ".DBX."data_items set mode=? where id=?", $r['mode'], $r['id'])) {
				$r['new_mode'] = $r['mode'];
			} else {
				$r['result'] = false;
			}
		}
		$d0 = date('Y-m-d H:i:s', strtotime($ar[0]['mod_date']));
		$d1 = date('Y-m-d H:i:s', strtotime($_POST['date']));
		if ($d0 != $d1) {
			$d2 = $d1;
			if ($database->sql_update("update ".DBX."data_items set mod_date=?,date=? where id=?", $d2,$d2, $r['id'])) {
				$r['result'] = true;
				$r['new_date'] = $d1;
				$r['old_date'] = $d0;
			}
		}
		if ($r['user'] != $ar[0]['user']) {
			$x = array();
			$x['id'] = $r['id'];
			$x['meta']['author'] = get_user_name($r['user']);
			if ($x['meta']['author']) {
				$x['user'] = $r['user'];
				if (mod_data_items($x)) {
				} else {
					$r['result'] = false;
				}
			} else {
				$r['result'] = false;
			}
		}
		$st = $ut->get_storage('plugin_pagelist');
		if ($st) {
			if ($r['collapse']) {
				$st['collapse'][$r['chg_id']] = true;
			} else {
				unset($st['collapse'][$r['chg_id']]);
			}
			$ut->set_storage('plugin_pagelist',$st);
		}
		echo( json_encode($r) );
		exit();
	}

	if ( isset($_POST['ajax']) && $_POST['ajax'] == 'recovery_link' )  {
		// リンク切れのチェック
		// メモリに一度に読み込むため、件数には制限あり
		$top = $params['circle']['meta']['top_page'];
		$r = array();
		$r['result'] = true;
		$r['count'] = 0;
		$r['top'] = $top;
		$ar = $database->sql_select_all( "select link,id,type from ".DBX."data_items where circle=? ", $p_circle );
		foreach($ar as $v) {
			$link = $v['link'];
			$id = $v['id'];
			$ok = false;
			if ($link) {
				foreach($ar as $vv) {
					if ($vv['id'] == $link) {
						$ok = true;
						break;
					}
				}
				if (!$ok) {
					$r[] = "$link->$id {$v['type']}";
					if ($database->sql_update("update ".DBX."data_items set link=? where id=?", 0, $id)) {
						++$r['count'];
					}
				}
			}
		}
		echo( json_encode($r) );
		exit();
	}

	return frame_renderer($buff);

}

function get_pagelist_tree( $p_page, &$nest, $top_flag, $offset = 0 )
{
	global $database,$params,$config,$p_circle,$plugin_ar,$ut;
	
	if ( ++$nest > MAX_PAGE_NEST ) {
		--$nest;
		return '';
	}
	$buff = '';

	$link = $p_page;

	$st = $ut->get_storage('plugin_pagelist');
	if ($nest==1) {
		$limit = $st['max_list_c0'];
	} else {
		$limit = $st['max_list_c1'];
	}

	$sql = array();
	$sql[0] =  "select pv_count,mode,id,link,title,type,block_type,{$ut->date_format("date", "'%Y/%m/%d'")} as date_d from ".DBX."data_items";
	$sql[] = array(" where circle=? and link=? ",$p_circle, $link);
	$sql[] = array(" and block_type <=15 and block_type <> 0 and block_type<>5 and type<> ? ", HIDDEN_ID);
	$sql[] = array(" and (mode=1 or (mode=0 and user={$_SESSION['login_id']})) ");
	if (!empty($st['deny_type'])) {
		$ar = explode(',',$st['deny_type']);
		if ($ar) {
			foreach ($ar as $v) {
				$v = (int)$v;
				if ($v) {
					$sql[] = array(" and type <> ? ", $v);
				}
			}
		}
	}
	if (!empty($st['deny_folder'])) {
		$ar = explode(',',$st['deny_folder']);
		if ($ar) {
			foreach ($ar as $v) {
				$v = (int)$v;
				if ($v) {
					$sql[] = array(" and id <> ? and tag not like ? ", $v, "%@dir:%{$v}%,");
				}
			}
		}
	}
	$sql[] = array(" order by date desc {$ut->limit($offset,$limit)} ");

	$ar = $database->sql_select_all($sql);
	
	if ( !$ar ) {
		--$nest;
		return '';
	}
	
	$pv_active = check_rights('edit') && isset($params['circle']['meta']['pv_logging']);
	$count = count($ar);
	for ($i=0; $i < $count; ++$i ) {
		$v = $ar[$i];
		if ($v['mode'] == 2) { continue; }	//トップページは表示しない
		if (!$v['title']) {
			$v['title'] ='...'; 
		}

		$t = $v['block_type'];

		$pre = '#';
		$pv = $date = '';
		if ( $t == 3 ) {
		} else if ( $t == 5 ) {
			$pre = '+';
		} else {
			$pre .= "-";
			if ($v['date_d']) {
				$date = "<span class='item_info ' >({$v['date_d']})</span>";
			} else {
				$date = "<span class='item_info ' >(00-00-00)</span>";
			}
		}
		
		if (!isset($_GET['show_type'])) {
			$pre = '';
		} else {
			$pre .= "{$v['type']}-";
		}
		
		$draft = '';
		if (!$v['mode']) {
			$draft = $ut->icon('admin');
		}
		$title = adjust_mstring($v['title'],TEXT_ADJ_SIZE);
		if ($pv_active && $v['pv_count']) { $pv = "<span class='item_info'>({$v['pv_count']})</span>"; }
		$a2 = '';
		if ($p_page != $v['id'] ) {
			if ($p_page !== 'all') {
				$a2 = get_pagelist_tree($v['id'], $nest, false);
			}
		}

		$h = "";
		if ($nest > 1) {
			if ($i == $count-1) {
				$h = "tree_1";
			} else {
				$h = "tree_0";
			}
		}
		$icon = 'icon_file';
		$style = '';
		if ($v['type'] != 0 && $v['type'] != 1) {
			$icon = 'icon_file_etc';
		}
		if ($v['type'] == PAGE_FOLDER_ID) {
			$icon = 'icon_file_binder';
		}
		if (isset($st['collapse'][$v['id']])) {
			$pre .= "<a href='javascript:void(collapse_expand_tree({$v['id']}))' >{$ut->icon('add'," data-children-img='{$v['id']}' style='vertical-align: baseline;width:12px' ")}</a>";
			$style = 'display:none';
		}
		if (!$v['mode']) {
			$pre .= $ut->icon('lock'," style='vertical-align: baseline;width:10px' ");
		}
		$hnd = " data-id='{$v['id']}' data-link='{$v['link']}' data-date='{$v['date_d']}' data-pv='{$v['pv_count']}' data-type='{$v['type']}' data-mode='{$v['mode']}' ";
		$page_id = $v['id'];
		if ($t == 5) {
			$page_id = $v['link'];
		}
$buff .= <<<EOT
		<div class='page_item ' data-idx='{$v['id']}'> 
			<span class='icon data_id $icon' $hnd></span> $pre <a href='{$ut->link($page_id)}'> {$title}</a>
			<span class='data_pv'>{$pv}</span> $date 
		</div>
EOT;
		if ($a2) {
			$buff .= "<div class='pagelist-item' style='$style' data-children='{$v['id']}' >$a2</div>";
		}
	}
	
	if (count($ar) == $limit) {
		$ofs = $offset + $limit;
		if ($p_page === 'all') {
$buff .= <<<EOT
			<a href='{$ut->link('list',"&:offset=$ofs&all=true")}' style='padding-left:20px'>more &raquo; </a>
EOT;
		} else {
$buff .= <<<EOT
			<span onclick='read_more(this,$ofs,$link)' style='padding-left:20px;cursor:pointer' >more &raquo; </span>
EOT;
		}
	}
	--$nest;
	return $buff;
	
}

function get_pagelist_title( &$v )
{
	global $plugin_ar,$params;
	
	$title = '';
	if ( $v['title'] ) {
		$title = $v['title'];
	}
	if ( !$title ) {
		if ( $v['type'] == 0 ) {
			$title = $params['circle']['name'];
		} else if ( isset($plugin_ar[$v['type']]) ) {
			$title = $plugin_ar[$v['type']]['title'];
		}
	}
	if ( !$title ) {
		$title = '...';
	}
	return $title;
}

?>