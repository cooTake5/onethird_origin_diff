<?php
/* Page folder plugin
	author: team1/3
	released: 2014/01/18
	coyright(c) SpiQe Software,team1/3 All Rights Reserved.
*/

function page_folder_renderer( &$page_ar )
{
	global $params, $ut, $html, $database, $plugin_ar;

	$buff = '';
	$id = $page_ar['id'];
	$parent_page = $page_ar['link'];
	
	if ($page_ar['id'] == $params['page']['id']) {
		//通常ページの場合のデフォルトは自ページ
		$parent_page = $page_ar['id'];
	}

	if (check_rights('owner')) {
		set_func_rights('edit.modules');
		set_func_rights('add_page');
		provide_edit_rights();
		provide_edit_module();
	}

	$add_type = 1;
	$desc = $hide_addbtn = $allow_post = false;
	
	$ar = $database->sql_select_all("select id,metadata from ".DBX."data_items where type=? and id=? ", $page_ar['type'], $id);
	if ($ar) {
		$m = unserialize64($ar[0]['metadata']);
		if (isset($m['page_folder_allow_post'])) { $allow_post = true; }
		if (isset($m['page_folder_add_type'])) { $add_type = $m['page_folder_add_type']; }
		if (isset($m['page_folder_parent_page'])) { $parent_page = $m['page_folder_parent_page']; }
		if (isset($m['page_folder_hide_addbtn'])) { $hide_addbtn = true; }
		if (isset($m['page_folder_desc'])) { $desc = true; }
		if (!isset($m['page_folder_writer'])) {
			if (isset($plugin_ar[ PAGE_FOLDER_ID ]['writer'])) {
				reset($plugin_ar[ PAGE_FOLDER_ID ]['writer']);
				$m['page_folder_writer'] = key($plugin_ar[ PAGE_FOLDER_ID ]['writer']);
			} else {
				$m['page_folder_writer'] = 'std_page_folder_writer';
			}
		}
	}

	$allow_post = !empty($m['page_folder_allow_post']) && check_rights();
	if ($allow_post && check_rights()) {
		set_func_rights('add_data_items');
		provide_edit_rights();
	}

	if ($allow_post || isset($params['edit-right']) || check_rights('edit')) {
		if (isset($_POST['ajax']) && $_POST['ajax'] == 'plugin_page_folder_setting')  {
			if ((int)$_POST['id'] != $id) {
				return;		// 複数の同種プラグインに対応するため
			}
			$r = array();
			$r['result'] = true;
			$c1 = ($allow_post)?  ' checked ': '';
			$c2 = ($hide_addbtn)?  ' checked ': '';
			$c3 = ($desc)?  ' checked ': '';

			$type_ar = array();
			$type_ar[1] = "Normal page";
			foreach( $plugin_ar as $k=>$v ) {
				if (isset($v['title']) && $v['title']) {
					if (isset($v['add_page'])) { 
						$type_ar[$k] = "{$v['title']} ($k)";
					}
				}
			}
			if (isset($plugin_ar[ PAGE_FOLDER_ID ]['type'])) {
				foreach ($plugin_ar[ PAGE_FOLDER_ID ]['type'] as $k=>$v) {
					$type_ar[$k] = $v;
				}
			}
			if (empty($page_ar['meta']['page_folder_title'])) {
				$page_ar['meta']['page_folder_title'] = '';
			}
$r['html'] = <<<EOT
			<table>
				<tr>
					<td >Title</td>
					<td>
						<input type='text' value='{$page_ar['meta']['page_folder_title']}' data-input='page_folder_title' />
					</td>
				</tr>
				<tr>
					<td >Writer</td>
					<td>
						{$ut->input(array(
							'type'=>'select'
							, 'data-input'=>'page_folder_writer'
							, 'value'=>$m['page_folder_writer']
							, 'option'=>$plugin_ar[ PAGE_FOLDER_ID ]['writer']
						))}
					</td>
				</tr>
				<tr>
					<td >Add type</td>
					<td>
						{$ut->input(array(
							'type'=>'select'
							, 'value'=>$add_type
							, 'data-input'=>'page_folder_add_type'
							, 'option'=>$type_ar
						))}
					</td>
				</tr>
				<tr>
					<td >Parent page</td>
					<td>
						<input type='text' value='$parent_page' data-input='page_folder_parent_page' />
					</td>
				</tr>
				<tr>
					<td ></td>
					<td>
						<ul>
							<li><label><input type='checkbox' data-input='page_folder_allow_post' $c1 />全てのユーザーがデータ追加できる</label></li>
							<li>
								<label><input type='checkbox' data-input='page_folder_hide_addbtn' $c2 />Addボタンを隠す</label>
							</li>
							<li>
								<label><input type='checkbox' data-input='page_folder_desc' $c3 />DESC.</label>
							</li>
						</ul>
					</td>
				</tr>
			</table>
EOT;
			echo(json_encode($r));
			exit();
		}
		if (isset($_POST['ajax']) && $_POST['ajax'] == 'save_plugin_page_folder_setting')  {
			if ((int)$_POST['id'] != $id) {
				return;		// 複数の同種プラグインに対応するため
			}
			$r = array();
			$r['result'] = false;

			function _option_save_plugin_page_folder_setting($a, &$page_ar) {
				if (isset($_POST[$a])) {
					$page_ar['meta'][$a] = sanitize_str($_POST[$a]);
				} else {
					if (isset($page_ar['meta'][$a])) { unset($page_ar['meta'][$a]); }
				}
			}
			_option_save_plugin_page_folder_setting('page_folder_allow_post', $page_ar);
			_option_save_plugin_page_folder_setting('page_folder_hide_addbtn', $page_ar);
			_option_save_plugin_page_folder_setting('page_folder_add_type', $page_ar);
			_option_save_plugin_page_folder_setting('page_folder_desc', $page_ar);
			_option_save_plugin_page_folder_setting('page_folder_writer', $page_ar);
			_option_save_plugin_page_folder_setting('page_folder_parent_page', $page_ar);

			if (isset($_POST['page_folder_title'])) {
				$page_ar['meta']['page_folder_title'] = sanitize_str($_POST['page_folder_title']);
			}
			$page_ar['metadata'] = serialize64($page_ar['meta']);
			if (mod_data_items($page_ar)) {
				$r['result'] = true;
			}
			echo(json_encode($r));
			exit();
		}
		if (isset($_POST['ajax']) && $_POST['ajax'] == 'plugin_page_folder_make_page')  {
			$r = array();
			$r['link'] = (int)$_POST['page'];
			$r['result'] = false;
			
			if (empty($_POST['type'])) {
				echo( json_encode($r) );
				exit();
			}
			
			$r['type'] = (int)($_POST['type']);
			if ($r['type'] == PAGE_FOLDER_ID) {
				$r['title'] = 'Folder';
				$r['block_type'] = 1;
				if (isset($page_ar['meta']['format'])) {
					$r['meta']['format'] = $page_ar['meta']['format'];
				}
				if ($allow_post) {
					$r['meta']['page_folder_allow_post'] = true;
				}
			} else {
				if (isset($page_ar['meta']['format'])) {
					$r['contents'] = $page_ar['meta']['format'];
				}
				if (!check_rights('edit') && $allow_post) {
					// edit権限なして追加できるページはトピックページのみ
					// itemをlwerに引き継がないので必要
					$r['type'] = TOPIC_ITEM_ID;		
				}
			}
			create_page( $r );
			if ($r['type'] == PAGE_FOLDER_ID && isset($r['open_url'])) {
				unset($r['open_url']);
			}
			echo( json_encode($r) );
			exit();
		}
	}

	//通常ページとインナーページの両方に対応する
	$contents = '';
	if ($page_ar['id'] == $params['page']['id']) {
		// 通常ページ
$params['add-blockmenu'][] = <<<EOT
		<a href='{$ut->link($page_ar['id'],'&:mode=format')}' class='onethird-button mini'>format</a>
		<a href='javascript:void(ot.plugin_page_folder_setting($id))' class='onethird-button mini' >setting</a>
EOT;
		$contents .= body_renderer($page_ar);	// 通常ページ部分
	} else {
		// インナーページ
		if (check_rights('owner')) {
$buff .= <<<EOT
			<div class='edit_pointer'>
EOT;
				$buff .= std_blockmenu_renderer($page_ar);
$buff .= <<<EOT
				<a href='{$ut->link($page_ar['id'],'&:mode=format')}' class='onethird-button mini'>format</a>
				<a href='javascript:void(ot.plugin_page_folder_setting($id))' >{$ut->icon('setting')}</a>
			</div>
EOT;
		}
	}

	if ($allow_post || check_rights('edit')) {
		if (!isset($html['meta']['plugin_page_folder_make_page'])) {
$html['meta']['plugin_page_folder_make_page'] = <<<EOT
			<script>
			ot.plugin_page_folder_make_page = function (link,type) {
				var opt = "ajax=plugin_page_folder_make_page";
				opt += "&type="+type+"&page="+link;
				ot.ajax({
					type: "POST"
					, url: '{$params['request_name']}'
					, data: opt
					, dataType:'json'
					, success: function(data){
						if ( data && data['result'] ) {
							if ( data['open_url'] ) {
								location.href = data['open_url'];
							} else {
								location.reload(true);
							}
						} else {
							alert('Failure to create a page.');
						}
					}
				});
			};
			</script>
EOT;
		}
	}

	$arg = array('id'=>$parent_page);
	$arg['writer'] = $m['page_folder_writer'];
	if ($arg['writer'] == 'std_page_folder_writer_ico') {
		$arg['more'] = true;
	}
	if (isset($m['page_folder_desc'])) {
		$arg['order'] = 'desc';
	} else {
		$arg['order'] = 'asc';
	}
	$contents .= page_folder($arg);
	$buff .= $contents;
	if (!$hide_addbtn && ($allow_post || check_rights('edit'))) {
		$folder_type = PAGE_FOLDER_ID;
$buff .= <<<EOT
		<div class='add-panel'>
			<input type='button' value='ページ追加' class='onethird-button mini' onclick='ot.plugin_page_folder_make_page({$parent_page}, $add_type)' />
			<input type='button' value='フォルダ追加' class='onethird-button mini' onclick='ot.plugin_page_folder_make_page({$parent_page}, $folder_type)' />
		</div>
EOT;
	}
	if (($allow_post && check_rights()) || check_rights('edit')) {
		snippet_std_setting('Page folder setting','plugin_page_folder_setting');
	}
	if (!isset($html['head']['plugin-page-folder'])) {
$html['head']['plugin-page-folder'] = <<<EOT
		<style>
			.plugin-page-folder-unit {
				margin-bottom:10px;
			}
			.plugin-page-folder-unit p {
				margin:0 0 5px 0;
				padding:0;
			}
			.plugin-page-folder-unit .title {
				color: #286593;
				font-size: 20px;
				margin:0 0 5px 0;
			}
			.plugin-page-folder-unit .onethird-button {
				margin-top:10px;
			}
			.plugin-page-folder-unit .add-panel {
				margin:15px 0 15px 0;
			}
			.plugin-page-folder-unit img {
				vertical-align: middle;
			}
		</style>
EOT;
	}

	return frame_renderer($buff,'plugin-page-folder-unit');
}

function page_folder_page(&$page_ar)
{
	global $html,$params,$database,$config,$ut;

	$p_page = $page_ar['id'];
	if (isset($_GET['mode']) && $_GET['mode']=='edit') {
		provide_edit_rights();
		provide_edit_module();
		snippet_breadcrumb($p_page, 'Page Edit');

		$buff = page_edit_renderer($page_ar);

		return frame_renderer($buff);

	} else if (isset($_GET['mode']) && $_GET['mode']=='format') {

		provide_edit_rights();
		provide_edit_module();
		snippet_breadcrumb($p_page, 'Format Edit');

		$params['hide-editmenu'] = true;
$params['add-editmenu'][] = <<<EOT
		<input type='button' onclick='ot.save_editdata(1)' class='onethird-button mini'  value='save' />
		<input type='button' onclick='ot.open_uploader({select:function(obj){ot.editor.insertimg(obj)}})' class='onethird-button mini' value='image' />
EOT;
		if (isset($page_ar['meta']['format'])) {
			$page_ar['contents'] = $page_ar['meta']['format'];
		} else {
			$page_ar['contents'] = '';
		}
		$buff = page_edit_renderer($page_ar);
		return frame_renderer($buff);
	}

	return basic_renderer($p_page);
}

function page_folder_onbefore_modified(&$new_ar)
{
	global $params,$config,$ut;

	if (isset($_GET['mode']) && $_GET['mode'] != 'format') {
		return;
	}
	
	$new_ar['meta']['format'] = $new_ar['contents'];
	$new_ar['metadata'] = serialize64($new_ar['meta']);
	unset($new_ar['meta']);
	unset($new_ar['contents']);
	if (!mod_data_items($new_ar)) {
		if (isset($_POST['ajax'])) {
			echo( json_encode($r) );
			exit();
		}
		exit_proc(400, 'Save-Error');

	} else {

		if ($new_ar['type'] >= 10) {
			event_plugin_page('onmodified', $new_ar);
		}
		
		if (isset($r['url'])) {
			header("Location: {$r['url']}");
		
		} else {
			if ($new_ar['block_type'] == 5) {
				header("Location: {$ut->link($new_ar['link'])}");
			} else {
				header("Location: {$ut->link($new_ar['id'])}");
			}
		}
		
		exit();
	}
}

function page_folder( &$arg )
{
	global $params, $ut, $database, $html;

	$option = array();
	$option['total'] = 0;
	$option['offset'] = 0;
	$option['page_size'] = 30;
	$mode = '';

	if (isset($_POST['ajax']) && $_POST['ajax'] == '*plugin_page_folder_read')  {
		$arg['id'] = (int)$_POST['link'];
		if (isset($_POST['offset'])) {
			$option['offset'] = (int)$_POST['offset'];
		}
		$mode = ($_POST['mode'] == 'more') ? 'more': 'open';
	}

	$type = 0;
	$offset_name = 'offset';
	$id = $params['page']['id'];
	$more = false;
	if (isset($arg['id'])) {
		$id = $arg['id'];
	}
	if (isset($arg['type'])) {
		$type = $arg['type'];
	}
	if (isset($arg['more'])) {
		$more = true;
	}
	if (isset($arg['offset_name'])) {
		$offset_name = $arg['offset_name'];
	}
	$option['offset_name'] = $offset_name;
	
	$opt = ' and mode=1 ';
	if (check_rights()) {
		$opt = ' and (mode=1 or (mode=0 and user='.(int)($_SESSION['login_id']).')) ';
	}

	$ar = $database->sql_select_all("select count(id) as c from ".DBX."data_items where link=? and block_type !=5 $opt", $id);
	if ($ar) {
		$option['total'] = $ar[0]['c'];
	}
	if (isset($_GET[$offset_name])) {
		$option['offset'] = (int)$_GET[$offset_name];
	}
	if (isset($arg['page_size'])) {
		$option['page_size'] = (int)$arg['page_size'];
	}

	if (!isset($arg['order']) || $arg['order'] == 'desc') {
		$order = 'order by date desc';
	} else {
		$order = 'order by date asc';
	}
	
	$buff = '';

	// write foloder
	$sql = "select id,type,metadata,title,mode,user,date,tag,block_type from ".DBX."data_items ";
	$sql .= " where link=? and block_type !=5";
	$sql .= " and type=".PAGE_FOLDER_ID;
	if (!check_rights('edit')) {
		$sql .= " and block_type<=15 ";
	} else if (!check_rights()) {
		$sql .= " and block_type<=20 ";
	}
	$sql .= " $opt $order";
	$folder_ar = $database->sql_select_all($sql, $id);

	// write page
	$sql = "select id,type,metadata,title,mode,user,date,tag,block_type from ".DBX."data_items ";
	$sql .= " where link=? and block_type !=5";
	if ($type) {
		$sql .= " and type={$type} ";
	} else {
		$sql .= " and type <>".PAGE_FOLDER_ID;
	}
	if (!check_rights('edit')) {
		$sql .= " and block_type<=15 ";
	} else if (!check_rights()) {
		$sql .= " and block_type<=20 ";
	}
	$sql .= " $opt $order";
	$sql .= " {$ut->limit($option['offset']*$option['page_size'], $option['page_size'])} ";
	
	$ar = $database->sql_select_all($sql, $id);
	$c = count($ar);
	if (!isset($arg['writer'])) {
		if (isset($plugin_ar[ PAGE_FOLDER_ID ]['writer'])) {
			reset($plugin_ar[ PAGE_FOLDER_ID ]['writer']);
			$arg['writer'] = key($plugin_ar[ PAGE_FOLDER_ID ]['writer']);
		} else {
			$arg['writer'] = 'std_page_folder_writer';
		}
	}
	if ($mode != 'more') {
		$ar = array_merge($folder_ar,$ar);
	}
	if (!empty($params['inner_page']['meta']['page_folder_title'])) {
		$buff .= "<h2>{$params['inner_page']['meta']['page_folder_title']}</h2>";
	}
	if (function_exists($arg['writer'])) {
		$buff .= $arg['writer']($ar);
	}
	
	if ($more) {
		if ($c >0 && $c == $option['page_size']) {
			$ofs = $option['offset']+1;
			$buff .= "<p id='plugin_page_folder_more{$id}'><a href='javascript:void(plugin_page_folder_read($id,\"more\",{$ofs}))'>more...</a></p>";
		}
	} else {
		$buff = std_pagination_renderer( $option ).$buff;
	}

	if ($more) {
$html['meta']['page_folder'] = <<<EOT
		<script>
		plugin_page_folder_read = function (link,md,ofs) {
			if (md == 'open' && \$('#plugin_page_folder'+link+' .open_hnd').length) {
				\$('#plugin_page_folder'+link+' .open_hnd').toggle();
				return;
			}
			var opt = "ajax=*plugin_page_folder_read&link="+link+"&xtoken="+ot.magic_str;
			if (md) { opt+="&mode="+md }
			if (ofs) { opt+="&offset="+ofs }
			ot.ajax({
				type: "POST"
				, url: '{$params['safe_request']}'
				, data: opt
				, dataType:'json'
				, success: function(data){
					if (data['mode'] == 'open') {
						var a = "<div class='open_hnd' style='padding-left:20px;'>"+data['html']+"</div>";
						\$('#plugin_page_folder'+data['id']).append(a);
					} else {
						\$('#plugin_page_folder_more'+data['id']).after(data['html']).remove();
					}
				}
			});
		};
		</script>
EOT;
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == '*plugin_page_folder_read')  {
		$r = array();
		$r['id'] = $id;
		$r['mode'] = $mode;
		$r['url'] = $ut->link($id);
		$r['html'] = $buff;
		$r['ar'] = $ar;
		echo( json_encode($r) );
		exit();
	}

	return $buff;
}

function std_page_folder_writer( &$ar )
{
	global $ut;
	
	$buff = '';
	foreach ($ar as $v) {
		$t = $v['title'];
		if (!$t) { $t = '...'; }
		if ($v['type'] == PAGE_FOLDER_ID) {
			$t = $ut->icon('folder',"width='16'").' '.$t;
		}
		if ($v['mode'] == 0) {
			if (!check_rights('edit') && $v['user'] != $_SESSION['login_id']) { continue; }
			$t = $ut->icon('lock',"width='16'").$t;
		} else if ($v['block_type'] >= 15) {
			$t = $t.$ut->icon('lock','width="16"');
		}
		$buff .= "<li><a href='{$ut->link($v['id'])}'>{$t}</a></li>";
	}
	if ($buff) {
		return "<ul>$buff</ul>";
	}
	return '';
}

function std_page_folder_writer_date( &$ar )
{
	global $ut;
	
	$buff = '';
	foreach ($ar as $v) {
		$t = $v['title'];
		if (!$t) { $t = '...'; }
		if ($v['type'] == PAGE_FOLDER_ID) {
			$t = $ut->icon('folder',"width='16'").' '.$t;
		}
		if ($v['mode'] == 0) {
			if (!check_rights('edit') || $v['user'] != $_SESSION['login_id']) { continue; }
			$t = $ut->icon('lock',"width='16'").$t;
		} else if ($v['block_type'] >= 15) {
			$t = $t.$ut->icon('lock','width="16"');
		}
		$d = substr($v['date'],0,10);
		$buff .= "<li style='list-style-type: none;'>{$d} <a href='{$ut->link($v['id'])}'>{$t}</a></li>";
	}
	if ($buff) {
		return "<ol style='margin:0;padding:0;' >$buff</ol>";
	}
	return '';
}

function std_page_folder_writer_ol( &$ar )
{
	global $ut;
	
	$buff = '';
	foreach ($ar as $v) {
		$t = $v['title'];
		if (!$t) { $t = '...'; }
		if ($v['type'] == PAGE_FOLDER_ID) {
			$t = $ut->icon('folder',"width='16'").' '.$t;
		}
		if ($v['mode'] == 0) {
			if (!check_rights('edit') && $v['user'] != $_SESSION['login_id']) { continue; }
			$t = $ut->icon('lock',"width='16'").$t;
		} else if ($v['block_type'] >= 15) {
			$t = $t.$ut->icon('lock','width="16"');
		}
		$buff .= "<li><a href='{$ut->link($v['id'])}'>{$t}</a></li>";
	}
	if ($buff) {
		return "<ol>$buff</ol>";
	}
	return '';
}

function std_page_folder_writer_ico( &$ar )
{
	global $ut,$html;
	
	$buff = '';
	foreach ($ar as $v) {
		$t = $v['title'];
		$ico = '';
		if (!$t) { $t = '...'; }
		$u = $ut->link($v['id']);
		if ($v['type'] == PAGE_FOLDER_ID) {
			$ico = $ut->icon('folder',"width='16'");
			$u = "javascript:void(plugin_page_folder_read({$v['id']},\"open\"))";
		} else {
			$ico = $ut->icon('text2',"width='16'");
		}
		if ($v['mode'] == 0) {
			if (!check_rights('edit') && isset($_SESSION['login_id']) && $v['user'] != $_SESSION['login_id']) { continue; }
			$t = $t.$ut->icon('lock','width="16"');
		} else if ($v['block_type'] >= 15) {
			$t = $t.$ut->icon('lock','width="16"');
		}
		$buff .= "<p id='plugin_page_folder{$v['id']}'><a href='{$u}'>{$ico}</a> <a href='{$ut->link($v['id'])}'>{$t}</a></p>";
	}

	return $buff;
}

function std_page_folder_writer_detail( &$ar )
{
	global $ut,$html;
	
	if (!isset($html['css']['std_page_folder_writer_detail'])) {
$html['css']['std_page_folder_writer_detail'] = <<<EOT
		<style>
			.std_page_folder_writer_detail .item {
				margin-bottom: 10px;
				padding: 10px;
				border-bottom: 1px dotted #c0c0c0;
				display: table;
				width:100%;
			}
			.std_page_folder_writer_detail .item:last-child {
				border-bottom: none;
			}
			.std_page_folder_writer_detail .item  .caps  {
				padding: 0 20px 0 0;
				display: table-cell;
				white-space: nowrap;
			}
			.std_page_folder_writer_detail .item  .txt {
				display: table-cell;
				word-wrap: break-word;
				vertical-align: top;
				width:100%;
			}
		</style>
EOT;
	}

	$buff = '';
$buff .= <<<EOT
	<div class='std_page_folder_writer_detail'>
EOT;
	foreach ($ar as $v) {
		$t = adjust_mstring($v['title'],30);
		$ico = '';
		if (!$t) { $t = '...'; }
		$u = $ut->link($v['id']);
		if ($v['type'] == PAGE_FOLDER_ID) {
			$ico = $ut->icon('folder',"width='16'");
			$u = "javascript:void(plugin_page_folder_read({$v['id']},\"open\"))";
		} else {
			$ico = '';
		}
		if ($v['mode'] == 0) {
			if (!check_rights('edit') && isset($_SESSION['login_id']) && $v['user'] != $_SESSION['login_id']) { continue; }
			$t = $t.$ut->icon('lock','width="16"');
		} else if ($v['block_type'] >= 15) {
			$t = $t.$ut->icon('lock','width="16"');
		}

		$m = $v['meta'] = unserialize64($v['metadata']);
		$img = get_thumb_url($v,true);
		if ($img) {
			$img = "<img src='{$ut->safe_echo($img)}' alt='{$v['title']}' style='width:100px;' />";
		}
		$description = '';
		if (isset($m['description'])) {
			$description = $m['description'];
		}
		if (isset($m['og_description'])) {
			$description = $m['og_description'];
		}
$buff .= <<<EOT
		<div class='item' id='plugin_page_folder{$v['id']}'>
			<div class='caps'>
				<p><a href='{$ut->link($v['id'])}'>{$t}</a></p>
				$img 
			</div>
			<div class='txt'>$description</div>
		</div>
EOT;
	}
$buff .= <<<EOT
	</div>
EOT;
	//description

	return $buff;
}

?>