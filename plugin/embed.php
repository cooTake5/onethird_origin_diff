<?php

function embed( $arg )
{
	global $params;

	if (isset($params['page'])) {
		$page_ar = $params['page'];
	} else {
		$page_ar = array('id'=>0, 'user'=>0);
	}
	if (isset($params['inner_page']) && $params['inner_page']) {
		$page_ar = $params['inner_page'];
	} 
	if (!isset($arg['page'])) {
		$arg['page'] = $page_ar['id'];
	}
	if (!empty($arg['guest'])) {
		$arg['all-user'] = true;
	}
	if (isset($arg['page'])) {
		if ($arg['page'] == 'top') {
			$arg['page'] = (int)$params['circle']['meta']['top_page'];
		}
		if ($arg['page'] == 'self') {
			$arg['page'] = (int)$params['page']['id'];
		}
	}
	if ($arg[0] == 'start' || $arg[0] == 'start-edit') {
		unset($params['plugin-embed']['edit_id']);
	}
	if (!isset($params['plugin-embed']['edit_id'])) {
		$rights = isset($arg['all-user']) || check_rights('edit') || (isset($page_ar['user']) && isset($_SESSION['login_id']) && $page_ar['user'] == $_SESSION['login_id']);
		if ($rights) {
			$params['plugin-embed']['mode'] = 'edit';
			$params['plugin-embed']['rights'] = true;
		} else {
			$params['plugin-embed']['mode'] = 'view';
			$params['plugin-embed']['rights'] = false;
		}
		$params['plugin-embed']['edit_id'] = $arg['page'];
		if ($arg[0] == 'start' || $arg[0] == 'start-edit') {
			$params['plugin-embed']['save_hnd'] = false;
		} else {
			$params['plugin-embed']['save_hnd'] = true;
		}
		if ($arg[0] == 'start-edit') {
			$params['plugin-embed']['save_hnd'] = false;
			$params['plugin-embed']['edit_quit'] = false;
			$_SESSION['onethird-plugin-embed-on'] = $arg['page'];
		}
		if ($arg[0] == 'start') {
			if (!isset($_SESSION['onethird-plugin-embed-on']) || $_SESSION['onethird-plugin-embed-on'] != $arg['page']) {
				$params['plugin-embed']['mode'] = 'view';
			}
		}
		if (isset($arg['lock'])) {
			$params['plugin-embed']['mode'] = 'lock';
		}
		if ($arg[0] == 'start' || $arg[0] == 'start-edit') {
			if (isset($arg['section'])) {
				$params['plugin_embed']['section'] = sanitize_asc($arg['section']);
			}
			return '';
		}
	}
	if ($params['plugin-embed']['mode'] == 'edit') {
		$arg['readonly'] = false;
	} else {
		$arg['readonly'] = true;
	}
	if (!isset($arg['lock'])) {
		if (!$arg['readonly']) {
			embed_post();
			embed_comm( $arg );
		}
	}
	if ($params['plugin-embed']['rights']) {
		if ($arg[0] == 'ctrl') {
			return embed_ctrl($arg);
		}
		if ($arg[0] == 'save') {
			return embed_ctrl($arg);
		}
	}
	embed_style();

	if ($arg[0] == 'table') {
		return embed_table($arg);

	} else if ($arg[0] == 'text') {
		$arg['type'] = 'text';
		return embed_std($arg);

	} else if ($arg[0] == 'hidden') {
		return embed_hidden($arg);

	} else if ($arg[0] == 'edit') {
		$arg['type'] = 'edit';
		return embed_std($arg);

	} else if ($arg[0] == 'number') {
		$arg['type'] = 'number';
		return embed_std($arg);

	} else if ($arg[0] == 'date') {
		return embed_date($arg);

	} else if ($arg[0] == 'time') {
		return embed_time($arg);

	} else if ($arg[0] == 'select') {
		return embed_select($arg);

	} else if ($arg[0] == 'radio') {
		$arg['type'] = 'radio';
		return embed_check($arg);

	} else if ($arg[0] == 'checkbox') {
		$arg['type'] = 'checkbox';
		return embed_check($arg);

	} else if ($arg[0] == 'html') {
		$arg['type'] = 'html';
		return embed_html($arg);

	} else {

	}
	if ($params['plugin-embed']['rights']) {
		return 'type-error-'.$arg[0];
	}
	return '';
}	

function embed_post()
{
	global $html,$ut,$config,$params;
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'save_embed')  {
		$r = array();
		$result = true;
		$plugin_embed_sec = "plugin_embed";
		if (!empty($params['plugin_embed']['section'])) {
			$plugin_embed_sec = $params['plugin_embed']['section'];
		}
		if (isset($_POST['data']) && is_array($_POST['data'])) {
			foreach ($_POST['data'] as $id=>$data) {
				$page = $id;
				if ($id == 'top') {
					$page = (int)$params['circle']['meta']['top_page'];
				}
				if ($id == 'self') {
					$page = (int)$params['page']['id'];
				}
				$r = array();
				read_pagedata($page, $r);
				if (!isset($r['meta'][$plugin_embed_sec])) {
					$r['meta'][$plugin_embed_sec] = array();
				}
				$r['meta'][$plugin_embed_sec];
				foreach ($data as $k=>$v) {
					$u = $ut->safe_asc($k);
					if ($u) {
						$t = $ut->safe_asc($_POST['type'][$id][$u]);
						$r['post']['type'][$u] = $t;
						if ($t == 'edit') {
							$tmp = $ut->safe_html(strip_tags($v,'<br><p><div>'));
						} else if ($t == 'table') {
							$tmp = $ut->safe_html($v);
						} else if ($t == 'html') {
							if ($v != '<span class="onethird-button mini">edit html</span>') {
								$tmp = $ut->safe_html($v);
							} else {
								$tmp = '';
							}
						} else {
							$tmp = sanitize_str(strip_tags($v));
						}
						if ($u == 'date') {
							$t = strtotime($v);
							if ($t != false && $t != -1) {
								$r['date'] =  date('Y-m-d H:i:s', $t);
							}
						} else if ($u == 'contents') {
							$r['contents'] = $ut->safe_html($v);

						} else if ($u == 'tag') {

							$p_tag = sanitize_str($v);
							if ($p_tag) {
								//通常TAGは#で始まる
								$p_tag = preg_replace('/(,|、|　|，| @)+/mu', ',', $p_tag);
								$p_tag = trim($p_tag,', ');
								$p_tag = '#'.preg_replace('/,/mu', ',#', $p_tag).',';
							}
							//SYSTEM TAGは@で始まる
							$sys_tag_ar = get_systags($r['tag']);	// POSTデータは使わない
							$p_tag = trim($p_tag,', ').',';
							foreach ($sys_tag_ar as $v) {
								$p_tag .= '@'.$v.',';
							}
							$p_tag = trim($p_tag,', ');

							$r['tag'] = $ut->safe_html($p_tag);

						} else if ($u == 'title') {
							$r['title'] = $ut->safe_str($v);

						} else {
							$r['meta'][$plugin_embed_sec][$u] = $tmp;
						}
					}
				}
				$r['type'] = EMBED_ID;
				if (event_plugin_page('onbefore_modified', $r) !== false) {
					unset($r['type']);
					if ($result = $result && mod_data_items($r)) {
						$r['type'] = EMBED_ID;
						event_plugin_page('onmodified', $r);
					}
				}
			}
		}
		if (isset($params['plugin-embed']['callback'])) {
			$r['callback'] = $params['plugin-embed']['callback'];
		}
		$r['result'] = $result;
		if (isset($_POST['reload'])) {
			$r['reload'] = true;
		}
		if ($result) {
			unset($_SESSION['onethird-plugin-embed-on']);
		}
		echo( json_encode($r) );
		exit();
	}
}

function embed_style()
{
	global $html,$config;

	if (!isset($html['css']['plugin-embed-comm'])) {
$html['css']['plugin-embed-comm'] = <<<EOT
		<style>
		.onethird-plugin-embed-readonly {
			padding:2px 0.3em 2px 0.3em;
			margin-right:10px;
		}
		.onethird-plugin-embed-checked {
		}
		.onethird-plugin-embed-unchecked {
			display:none;
		}
		</style>
EOT;
	}

}

function embed_comm( $arg )
{
	global $html,$ut,$config,$params;

$html['meta']['embed_comm2'] = <<<EOT
<script>
	\$(function(){
		\$(document).on('click', '.onethird-plugin-embed .save_col', function(e){
			ot.save_embed();
		});
	});
</script>
EOT;

$html['meta']['plugin-embed-comm'] = <<<EOT
<script>
	ot.save_embed = function(reload) {
		\$('.onethird-plugin-embed .sel').removeClass('sel');
		var opt = "";
		if (reload) {
			opt += "&reload=true";
		}
		\$('.onethird-plugin-embed .inner[data-editing=true] ').each(function(){
			var o = \$(this);
			var type = o.attr('data-type');
			if (o.attr('data-page')) {
				var page_name = "["+o.attr('data-page')+"]["+o.attr('data-name')+"]";
				opt += "&type"+page_name+"="+type;
				if (type == 'text') {
					opt += "&data"+page_name+"="+encodeURIComponent(o.find('input').val());

				} else if (type == 'select') {
					opt += "&data"+page_name+"="+encodeURIComponent(o.find('select').val());
					
				} else if (type == 'date') {
					opt += "&data"+page_name+"="+encodeURIComponent(o.find('input').val());

				} else if (type == 'hidden') {
					opt += "&data"+page_name+"="+encodeURIComponent(o.find('input').val());

				} else if (type == 'check') {
					var x = '';
					o.find('input:checked').each(function(){
						x += \$(this).val()+',';
					});
					opt += "&data"+page_name+"="+encodeURIComponent(x);
					
				} else if (type == 'number') {
					var a = o.find('input').val();
					if (!a) { a = 0; }
					opt += "&data"+page_name+"="+parseInt(a);
					o.find('input').val(a);

				} else if (type == 'time') {
					var h = o.find('.input_h').val();
					if (!h) { h = 0; }
					var m = o.find('.input_m').val();
					if (!m) { m = 0; }
					opt += "&data"+page_name+"="+encodeURIComponent(h+':'+m);
					o.find('input').val(a);

				} else {
					opt += "&data"+page_name+"="+encodeURIComponent(o.html());
				}
			}
		});
		if (opt) {
			ot.ajax({
				type: "POST"
				, url: '{$params['request_name']}'
				, data: "ajax=save_embed"+opt
				, dataType:'json'
				, arg_reload: reload
				, success: function(data){
					if (data['callback'] && ot.save_embed_callback) {
						//ot.save_embed_callback(data);
					}
					if ( data && data['result'] ) {
						\$('.onethird-plugin-embed .edit_hnd, .onethird-plugin-embed .save_hnd').remove();
						\$('.onethird-plugin-embed .inner[data-editing=true]').removeAttr('data-editing');
						if (data['reload']) {
							location.reload(true);
						}
					} else {
						alert('Upadte-error');
					}
				}
			});
		}
	};
</script>
EOT;

}

function embed_ctrl( $arg )
{
	global $html,$ut,$config,$params;

	if ($params['plugin-embed']['mode'] == 'lock') {
		return;
	}
	
	$page = $arg['page'];

	if (isset($arg['callback'])) {
		$params['plugin-embed']['callback'] = $arg['callback'];
	}
	$save_caption = 'save';
	if (isset($arg['save_caption'])) {
		$save_caption = $arg['save_caption'];
	}
	$edit_caption = 'edit';
	if (isset($arg['edit_caption'])) {
		$edit_caption = $arg['edit_caption'];
	}
	$quit_caption = 'quit';
	if (isset($arg['quit_caption'])) {
		$quit_caption = $arg['quit_caption'];
	}
	$button_class = 'onethird-button';
	if (isset($arg['button_class'])) {
		$button_class = $arg['button_class'];
	}
	if (isset($_POST['page']) && $page == (int)$_POST['page'] && isset($_POST['ajax']) && $_POST['ajax'] == 'embed_ctrl')  {
		$mode = (int)$_POST['mode'];
		$r = array();
		$r['result'] = true;
		if (isset($_SESSION['onethird-plugin-embed-on'])) {
			if ($_SESSION['onethird-plugin-embed-on'] != $page) {
				$_SESSION['onethird-plugin-embed-on'] = $page;
			} else {
				unset($_SESSION['onethird-plugin-embed-on']);
			}
		} else {
			$_SESSION['onethird-plugin-embed-on'] = $page;
		}
		echo( json_encode($r) );
		exit();
	}

$html['meta']['embed_ctrl'] = <<<EOT
<script>
	ot.embed_ctrl = function(page,mode) {
		if (mode == 2) {
			ot.save_embed(true);
		} else {
			ot.embed_ctrl_callback(page,mode);
		}
	};
	ot.embed_ctrl_callback = function(page,mode) {
		var opt = "ajax=embed_ctrl&mode="+mode+'&page='+page;
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: opt
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					location.reload(true);
				} else {
					alert('Upadte-error');
				}
			}
		});
	};
</script>
EOT;

	$buff = '';
	if (!isset($arg['hide-button'])) {
$buff .= <<<EOT
		<div class='onethird-edit-pointer onethird-plugin-embed onethird-plugin-embed-ctrl ' style='display:inline-block' >
EOT;
			if (!isset($_SESSION['onethird-plugin-embed-on']) || $_SESSION['onethird-plugin-embed-on'] != $page) {
$buff .= <<<EOT
				<input type='button' onclick='ot.embed_ctrl({$page},1)' value='{$edit_caption}' class='$button_class ' />
EOT;
			} else {
$buff .= <<<EOT
				<input type='button' onclick='ot.embed_ctrl({$page},2)' value='{$save_caption}' class='$button_class ' />
EOT;
				if ($quit_caption && !isset($params['plugin-embed']['edit_quit'])) {
$buff .= <<<EOT
					<input type='button' onclick='ot.embed_ctrl({$page},0)' value='{$quit_caption}' class='$button_class ' />
EOT;
				}
			}
$buff .= <<<EOT
		</div>
EOT;
	}
	return $buff;
}

function get_embed_data($page, $name)
{
	global $params, $database;
	
	$plugin_embed_sec = "plugin_embed";
	if (!empty($params['plugin_embed']['section'])) {
		$plugin_embed_sec = $params['plugin_embed']['section'];
	}
	if (isset($params['page']['id']) && $page == $params['page']['id']) {

		if ($name == 'date') {
			return substr($params['page']['date'],0,10);
		} else if ($name == 'contents') {
			return $params['page']['contents'];
		} else if ($name == 'title') {
			return $params['page']['title'];
		} else if ($name == 'tag') {
			return implode(',',get_tags($params['page']['tag']));
		} 

		if (isset($params["_embed_cache"][$page][$plugin_embed_sec][$name])) {
			return $params["_embed_cache"][$page][$plugin_embed_sec][$name];
		}
		if (!isset($params['page']['meta'][$plugin_embed_sec][$name])) {
			return false;
		}
		return $params['page']['meta'][$plugin_embed_sec][$name];
	}

	if (isset($params["_embed_cache"][$page][$plugin_embed_sec][$name])) {
		return $params["_embed_cache"][$page][$plugin_embed_sec][$name];
	}
	if ($name == 'date') {
		$ar = $database->sql_select_all("select date,id from ".DBX."data_items where id=?", (int)$page);
		if ($ar) {
			$params["_embed_cache"][$page][$plugin_embed_sec]['date'] = substr($ar[0]['date'],0,10);
		}
	} else if ($name == 'contents') {
		$ar = $database->sql_select_all("select contents from ".DBX."data_items where id=?", (int)$page);
		if ($ar) {
			$params["_embed_cache"][$page][$plugin_embed_sec]['contents'] = echo_contents_script($ar[0]['contents']);
		}
	} else if ($name == 'title') {
		$ar = $database->sql_select_all("select title from ".DBX."data_items where id=?", (int)$page);
		if ($ar) {
			$params["_embed_cache"][$page][$plugin_embed_sec]['title'] = $ar[0]['title'];
		}
	} else if ($name == 'tag') {
		$ar = $database->sql_select_all("select tag from ".DBX."data_items where id=?", (int)$page);
		if ($ar) {
			$params["_embed_cache"][$page][$plugin_embed_sec]['tag'] = implode(',',get_tags($ar[0]['tag']));
		}
	} else {
		$ar = $database->sql_select_all("select metadata,id from ".DBX."data_items where id=?", (int)$page);
		if ($ar) {
			$m = unserialize64($ar[0]['metadata']);
			if (isset($m[$plugin_embed_sec])) {
				$params["_embed_cache"][$page][$plugin_embed_sec] = $m[$plugin_embed_sec];
			} else {
				$params["_embed_cache"][$page][$plugin_embed_sec] = array();
			}
		}
	}
	if (isset($params["_embed_cache"][$page][$plugin_embed_sec][$name])) {
		return $params["_embed_cache"][$page][$plugin_embed_sec][$name];
	}

	return false;
}

function embed_table( $arg )
{
	global $html,$ut,$config,$params;

	$buff = '';
	if (!isset($params['_embed_id']['table'])) {
		$params['_embed_id']['table'] = 0;
	}
	$name = false;
	if (isset($arg['name'])) {
		$name = $arg['name'];
	}
	if (!$name) {
		$name = "table-".(++$params['_embed_id']['table']);
	}
	$name = sanitize_asc($name);
	$page = " data-page='{$arg['page']}' ";
	$data_html = get_embed_data((int)$arg['page'],$name);
	if ($data_html === false) {
$data_html = <<<EOT
		<table>
			<tr>
				<th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th>
			</tr>
			<tr>
				<th>&nbsp;</th><td>&nbsp;</td><td>&nbsp;</td>
			</tr>
			<tr>
				<th>&nbsp;</th><td>&nbsp;</td><td>&nbsp;</td>
			</tr>
		</table>
EOT;
	}
	$x_editable = " contenteditable='true' ";
	if (!$params['plugin-embed']['save_hnd']) { $x_editable .= " data-editing=true "; }
	if ($arg['readonly']) {
		$x_editable = '';
	}
$buff .= <<<EOT
	<div class='onethird-edit-pointer 
	 onethird-plugin-embed onethird-plugin-embed-table .onethird-plugin-embed-{$name} '
	  >
		<div class='outer' >
			<div class='inner' data-name='$name' data-type='table' $x_editable $page >$data_html</div>
		</div>
	</div>
EOT;

	if (!isset($html['css']['plugin-embed-table'])) {
$html['css']['plugin-embed-table'] = <<<EOT
		<style>
		.onethird-plugin-embed-table table {
			border-collapse: collapse;
		}
		.onethird-plugin-embed-table .outer {
			position: relative;
			display: inline-block;
		}
		.onethird-plugin-embed-table th {
			background-color: #d5d5d5;
			white-space: nowrap;
			height: 100%;
			border: 1px solid #c0c0c0;
		}
		.onethird-plugin-embed-table td, .onethird-plugin-embed-table th {
			min-height:1em;
			min-width:1em;
			padding: 4px 10px 4px 10px;
			font-weight: normal;
		}
		.onethird-plugin-embed-table td {
			border: 1px solid #c0c0c0;
		}
		.onethird-plugin-embed-table .edit_hnd {
			position: absolute;
			cursor:pointer;
			width:100%;
			top: -16px;
		}
		.onethird-plugin-embed-table div {
			position: relative;
		}
		.onethird-plugin-embed-table .add_row {
			position: absolute;
			left:0;
		}
		.onethird-plugin-embed-table .del_row {
			position: absolute;
			left:20px;
		}
		.onethird-plugin-embed-table .add_col {
			position: absolute;
			right:20px;
		}
		.onethird-plugin-embed-table .del_col {
			position: absolute;
			right:1px;
		}
		.onethird-plugin-embed-table .save_hnd {
			position: absolute;
			cursor:pointer;
			right: -18px;
			top: 0;
			width:16px;
		}
		.onethird-plugin-embed-table .sel {
			background-color: rgba(255, 0, 0, 0.18);
		}
		</style>
EOT;
	}

$a = <<<EOT
<script>
	\$(function(){
		\$(document).on('click', '.onethird-plugin-embed-table td,.onethird-plugin-embed-table th', function(e){
			if (!ot.plugin_embed_table) { ot.plugin_embed_table = {}; }
			var o = \$(this);
			var o_inner = o.parents('.inner');
			var o_outer = o.parents('.outer');
			if (o_inner.attr('contenteditable') == 'true') {
				ot.plugin_embed_table.caret = this;
				ot.plugin_embed_table.caret_ix = \$(this.parentNode).find('td,th').index(this);
				\$('.onethird-plugin-embed-table .sel').removeClass('sel');
				\$('.onethird-plugin-embed-table .edit_hnd').remove();
				o_outer.append(ot.get_embed_table_hnd_html());
				o.addClass('sel');
				if (!o_inner.attr('data-editing')) {
					o_inner.attr('data-editing',true);
					var a = "<div class='save_hnd'>";
					a += "<span class='save_col' >{$ut->icon('save')}</span>";
					a += "</div>"
					o.parents('.outer').append(a);
				}
			}
		});
		\$(document).on('click', '.onethird-plugin-embed-table .add_col', function(e){
			\$('.onethird-plugin-embed-table .edit_hnd').css('opacity','1');
			ot.add_embed_table_col();
		});
		\$(document).on('click', '.onethird-plugin-embed-table .del_col', function(e){
			\$('.onethird-plugin-embed-table .edit_hnd').css('opacity','1');
			ot.del_embed_table_col();
		});
		\$(document).on('click', '.onethird-plugin-embed-table .add_row', function(e){
			\$('.onethird-plugin-embed-table .edit_hnd').css('opacity','1');
			ot.add_embed_table_row();
		});
		\$(document).on('click', '.onethird-plugin-embed-table .del_row', function(e){
			\$('.onethird-plugin-embed-table .edit_hnd').css('opacity','1');
			ot.del_embed_table_row();
		});
		\$(document).on('blur', '.onethird-plugin-embed-table .outer', function(e){
			\$('.onethird-plugin-embed-table .edit_hnd').css('opacity','0.5');
		});
	});
	ot.add_embed_table_col = function() {
		var obj = \$(ot.plugin_embed_table.caret).parents('table');
		var i = ot.plugin_embed_table.caret_ix+1;
		\$(obj).find('td:nth-child('+i+'), th:nth-child('+i+')').each(function(){
			var o = \$(this);
			if (o[0].tagName=='TH') {
				a = "<th>&nbsp;</th>";
			} else {
				a = "<td>&nbsp;</td>";
			}
			o.after(a);
		});
	};
	ot.del_embed_table_col = function() {
		var obj = \$(ot.plugin_embed_table.caret).parents('table');
		var i = ot.plugin_embed_table.caret_ix+1;
		\$(obj).find('td:nth-child('+i+'), th:nth-child('+i+')').each(function(){
			var o = \$(this);
			o.remove();
		});
	};
	ot.add_embed_table_row = function() {
		var obj = \$(ot.plugin_embed_table.caret).parents('tr');
		if (obj.length) {
			var h = obj.html();
			obj.after("<tr>"+h+"</tr>");
		}
	};
	ot.del_embed_table_row = function() {
		var obj = \$(ot.plugin_embed_table.caret).parents('tr');
		if (obj.length) {
			obj.remove();
		}
	};
	ot.get_embed_table_hnd_html = function() {
		var a = "<div class='edit_hnd'><div>";
		a += "<span class='add_row'>{$ut->icon('add'," title='add row' width='16' ")}</span>";
		a += "<span class='del_row'>{$ut->icon('remove'," title='del row' width='16' ")}</span>";
		a += "<span class='add_col'>{$ut->icon('add'," title='add col' width='16' ")}</span>";
		a += "<span class='del_col'>{$ut->icon('remove'," title='add col' width='16' ")}</span>";
		a += "</div></div>";
		return a;
	};
</script>
EOT;

	if (!$arg['readonly']) {
		$html['meta']['plugin-embed-table'] = $a;
	}

	return $buff;
}

function embed_std( $arg )
{
	global $html,$ut,$config,$params;

	$buff = '';
	if (!isset($params['_embed_id'][$arg['type']])) {
		$params['_embed_id'][$arg['type']] = 0;
	}
	$allow_cr = isset($arg['allow_cr']);
	if (!isset($arg['type'])) {
		return 'error';
	}
	$name = false;
	if (isset($arg['name'])) {
		$name = $arg['name'];
	}
	if (!$name) {
		$name = "{$arg['type']}-".(++$params['_embed_id'][$arg['type']]);
	}
	$name = sanitize_asc($name);
	$page = " data-page='{$arg['page']}' ";
	$data = get_embed_data((int)$arg['page'],$name);
	if ($data === false) {
		$data = '';
	}

	$w_style = $style = '';
	$tag = 'div';
	$contenteditable = '';
	if (isset($arg['width'])) {
		$style = "width:{$arg['width']};";
		$tag = 'span';
		$w_style = 'w_style';
	}
	$x_editable = " data-editable='true' ";
	if ($arg['type'] == 'text') {
		if (!$style) { $style = 'display:inline-block;width:100%;box-sizing: border-box;'; }
		$data_html = "<input type='text' value='{$data}' style='$style' />";
	}
	if ($arg['type'] == 'number') {
		if (!$style) { $style = 'display:inline-block;width:100px;'; }
		$data_html = "<input type='number' value='{$data}' style='$style' />";
	}
	if ($arg['type'] == 'edit') {
		if (isset($arg['height'])) {
			$style .= "min-height:{$arg['height']};";
		}
		$x_editable = " data-editable='true' contenteditable='true' style='$style' ";
		$tag = 'div';
		$data_html = "$data";
		if (!$data_html) {
			$data_html = "<p><br /></p>";
		}
	}
	if (!$params['plugin-embed']['save_hnd']) { $x_editable .= " data-editing=true "; }
	if ($arg['readonly']) {
		$x_editable = " data-editable='false' ";
		$data_html = "$data";
	}

$buff .= <<<EOT
	<$tag class='onethird-edit-pointer onethird-plugin-embed 
	onethird-plugin-embed-std onethird-plugin-embed-{$arg['type']} 
	.onethird-plugin-embed-{$name} ' 
	 >
		<$tag class='outer $w_style' >
			<$tag class='inner' data-name='$name' data-type='{$arg['type']}' $x_editable $page >$data_html</$tag>
		</$tag>
	</$tag>
EOT;

$a = <<<EOT
<style>
.onethird-plugin-embed .inner input {
	color:#000;
}
.onethird-plugin-embed-std .outer {
	position: relative;
EOT;
	if ($params['plugin-embed']['save_hnd'] ) {
$a .= <<<EOT
		margin-right:30px;
EOT;
	}
$a .= <<<EOT
}
.onethird-plugin-embed-std .w_style {
	display:inline-block;
}
.onethird-plugin-embed-std input {
	border:none;
}
.onethird-plugin-embed-std .inner[data-editable=true] input {
	border:1px solid #c0c0c0;
}
.onethird-plugin-embed-std .inner[contenteditable=true] {
	border:1px solid #c0c0c0;
	padding:2px 7px;
	background-color: #fff;
}
.onethird-plugin-embed-std .inner {
	min-height: 1em;
	margin: 0;
	padding: 0;
}
.onethird-plugin-embed-std p {
	margin: 0;
	padding: 0;
}
.onethird-plugin-embed-std .save_hnd {
	position: absolute;
	cursor:pointer;
	right: -19px;
	top: 0;
	width:16px;
}

/* text */
.onethird-plugin-embed-text .inner {
}
.onethird-plugin-embed-text .inner input {
	padding:2px 7px;
}
.onethird-plugin-embed-text .w_style .save_hnd {
	right: -19px;
}
/* number */
.onethird-plugin-embed-number .outer {
	width:110px;
}
.onethird-plugin-embed-number .inner {
	padding:0 5px 0 0;
}
.onethird-plugin-embed-number .inner input {
	padding:2px 7px;
}
.onethird-plugin-embed-number .save_hnd {
	right: -9px;
}

/* for width */
.onethird-plugin-embed-inline {display: inline;}
.onethird-plugin-embed-inline .outer {display: inline;}
.onethird-plugin-embed-inline .inner {display: inline;}
</style>
EOT;

	if (!isset($html['css']['plugin-embed-std'])) {
		$html['css']['plugin-embed-std'] = $a;
	}

$a = <<<EOT
<script>
	\$(function(){
		\$(document).on('click', '.onethird-plugin-embed-std .inner', function(e){
			if (!ot.plugin_embed) { ot.plugin_embed = {}; }
			var o = \$(this);
			if (o.attr('data-editable') == 'true') {
				ot.plugin_embed.caret_std = this;
				if (!o.attr('data-editing')) {
					o.attr('data-editing', 'true');
					var a = "<div class='save_hnd'>";
					a += "<span class='save_col' >{$ut->icon('save')}</span>";
					a += "</div>"
					o.parents('.outer').append(a);
				}
			}
		});
	});
</script>
EOT;

	if (!$arg['readonly']) {
		$html['meta']['plugin-embed-std'] = $a;
	}

	return $buff;
}

function embed_date( $arg )
{
	global $html,$ut,$config,$params;

	$buff = '';
	if (!$arg['readonly']) {
		snippet_jqueryui();
$html['meta']['embed_datepicker'] = <<<EOT
		<script>
			\$(function(){
				\$( ".embed_datepicker" ).datepicker({ dateFormat: "yy-mm-dd" });
			});
		</script>
EOT;
	}
	if (!isset($params['_embed_id']['date'])) {
		$params['_embed_id']['date'] = 0;
	}
	$name = false;
	if (isset($arg['name'])) {
		$name = $arg['name'];
	}
	if (!$name) {
		$name = "date-".(++$params['_embed_id']['date']);
	}
	$name = sanitize_asc($name);
	$page = " data-page='{$arg['page']}' ";
	$data = get_embed_data((int)$arg['page'],$name);
	if ($data === false) {
		$data = '';
	}
	$style = 'display:inline-block;width:100%;';
	$tag = 'span';
	$style = "width:120px;";
	if (isset($arg['width'])) {
		$style = "width:{$arg['width']};";
	}
	$data_html = "<input type='text' value='{$data}' style='$style' class='embed_datepicker' />";

	$x_editable = " data-editable='true' ";
	if (!$params['plugin-embed']['save_hnd']) { $x_editable .= " data-editing=true "; }
	if ($arg['readonly']) {
		$x_editable = " data-editable='false' ";
		$data_html = "$data";
	}

$buff .= <<<EOT
	<$tag class='onethird-edit-pointer onethird-plugin-embed 
		onethird-plugin-embed-date .onethird-plugin-embed-{$name} '>
		<$tag class='outer' >
			<$tag class='inner' data-name='$name' data-type='date' $x_editable $page >$data_html</$tag>
		</$tag>
	</$tag>
EOT;

	if (!isset($html['css']['plugin-embed-date'])) {
$html['css']['plugin-embed-date'] = <<<EOT
		<style>
		.onethird-plugin-embed-date .outer {
			position: relative;
			margin: 0 18px 0 0;
		}
		.onethird-plugin-embed-date .inner {
			margin: 0;
			padding:0;
		}
		.onethird-plugin-embed-date .inner input {
			padding:2px;
			border:1px solid #c0c0c0;
		}
		.onethird-plugin-embed-date .save_hnd {
			position: absolute;
			cursor:pointer;
			right: -17px;
			top: 0;
			width:16px;
		}
		</style>
EOT;
	}

$a = <<<EOT
<script>
	\$(function(){
		\$(document).on('click', '.onethird-plugin-embed-date .embed_datepicker ', function(e){
			if (!ot.plugin_embed_text) { ot.plugin_embed_text = {}; }
			var o = \$(this).parents('.inner');
			if (o.attr('data-editable') == 'true') {
				ot.plugin_embed_text.caret_date = this;
				if (!o.attr('data-editing')) {
					o.attr('data-editing', 'true');
					var a = "<div class='save_hnd'>";
					a += "<span class='save_col' >{$ut->icon('save')}</span>";
					a += "</div>"
					o.parents('.outer').append(a);
				}
			}
		});
	});
</script>
EOT;

	if (!$arg['readonly']) {
		$html['meta']['plugin-embed-date'] = $a;
	}
	return $buff;
}

function embed_time( $arg )
{
	global $html,$ut,$config,$params;

	$buff = '';
	if (!isset($params['_embed_id']['time'])) {
		$params['_embed_id']['time'] = 0;
	}
	$name = false;
	if (isset($arg['name'])) {
		$name = $arg['name'];
	}
	if (!$name) {
		$name = "time-".(++$params['_embed_id']['time']);
	}
	$name = sanitize_asc($name);
	$page = " data-page='{$arg['page']}' ";
	$h = (int)date('H',time());
	$m = (int)date('i',time());
	$x = get_embed_data((int)$arg['page'],$name);
	if ($x) {
		$x = explode(':',$x);
		if (isset($x[0])) { $h = $x[0]; }
		if (isset($x[1])) { $m = $x[1]; }
	}
	$tag = 'span';
	if (!$arg['readonly']) {
$data_html = <<<EOT
		<select class='input_h'>
EOT;
			for ($i=0; $i <24; ++$i) {
				$x = sprintf('%02d',$i);
				$s = ($x == $h)? " selected ":"";
				$data_html .= "<option value='$x' $s>$x</option>";
			}
$data_html .= <<<EOT
		</select>
		<select class='input_m'>
EOT;
			for ($i=0; $i <60; $i+=5) {
				$x = sprintf('%02d',$i);
				$s = ($x == $m)? " selected ":"";
				$data_html .= "<option value='$x' $s>$x</option>";
			}
$data_html .= <<<EOT
		</select>
EOT;
	} else {
$data_html = <<<EOT
		<$tag class=''> $h : $m </$tag>
EOT;
	}

	$x_editable = " data-editable='true' ";
	if (!$params['plugin-embed']['save_hnd']) { $x_editable .= " data-editing=true "; }
	if ($arg['readonly']) {
		$x_editable = " data-editable='false' ";
	}

$buff .= <<<EOT
	<$tag class='onethird-edit-pointer onethird-plugin-embed 
		onethird-plugin-embed-time .onethird-plugin-embed-{$name} '>
		<$tag class='outer' >
			<$tag class='inner' data-name='$name' data-type='time' $x_editable $page >$data_html</$tag>
		</$tag>
	</$tag>
EOT;

	if (!isset($html['css']['plugin-embed-time'])) {
$html['css']['plugin-embed-time'] = <<<EOT
		<style>
		.onethird-plugin-embed-time .outer {
			position: relative;
			margin:0 6px 0 0;
		}
		.onethird-plugin-embed-time .inner {
			padding:0;
			margin:0 13px 0 0;
		}
		.onethird-plugin-embed-time .inner select {
			padding:1px;
			border:1px solid #c0c0c0;
		}
		.onethird-plugin-embed-time .save_hnd {
			position: absolute;
			cursor:pointer;
			right: -1px;
			top: 0;
			width:16px;
		}
		</style>
EOT;
	}

$a = <<<EOT
<script>
	\$(function(){
		\$(document).on('click', '.onethird-plugin-embed-time .inner', function(e){
			if (!ot.plugin_embed_text) { ot.plugin_embed_text = {}; }
			var o = \$(this);
			if (o.attr('data-editable') == 'true') {
				ot.plugin_embed_text.caret_time = this;
				if (!o.attr('data-editing')) {
					o.attr('data-editing', 'true');
					var a = "<div class='save_hnd'>";
					a += "<span class='save_col' >{$ut->icon('save')}</span>";
					a += "</div>"
					o.parents('.outer').append(a);
				}
			}
		});
	});
</script>
EOT;

	if (!$arg['readonly']) {
		$html['meta']['plugin-embed-time'] = $a;
	}
	return $buff;
}

function embed_select( $arg )
{
	global $html,$ut,$config,$params;

	$select_option = false;
	foreach ($arg as $v) {
		if (is_array($v)) {
			$select_option = $v;
			break;
		}
	}
	if (!$select_option) {
		return 'error';
	}

	$buff = '';
	if (!isset($params['_embed_id']['select'])) {
		$params['_embed_id']['select'] = 0;
	}
	$name = false;
	if (isset($arg['name'])) {
		$name = $arg['name'];
	}
	if (!$name) {
		$name = "select-".(++$params['_embed_id']['select']);
	}
	$name = sanitize_asc($name);
	$page = " data-page='{$arg['page']}' ";
	$x = get_embed_data((int)$arg['page'],$name);
	if ($x === false) {
		$x = '';
	}
	$w_stype = $style = '';
	$tag = 'span';
	if (isset($arg['width'])) {
		$style = "width:{$arg['width']};margin-right:10px;";
	}
	if (!$arg['readonly']) {
$data_html = <<<EOT
	<select style='$style'>
EOT;
		foreach ($select_option as $v) {
			$s = ($v == $x)? " selected ":"";
			$v = $ut->safe_str($v);
			$data_html .= "<option value='$v' $s>$v</option>";
		}
$data_html .= <<<EOT
	</select>
EOT;
	} else {
$data_html = <<<EOT
		<$tag class=''> $x </$tag>
EOT;
	}

	$x_editable = " data-editable='true' ";
	if (!$params['plugin-embed']['save_hnd']) { $x_editable .= " data-editing=true "; }
	if ($arg['readonly']) {
		$x_editable = " data-editable='false' ";
	}

$buff .= <<<EOT
	<$tag class='onethird-edit-pointer onethird-plugin-embed 
		onethird-plugin-embed-select .onethird-plugin-embed-{$name} '>
		<$tag class='outer' >
			<$tag class='inner' data-name='$name' data-type='select' $x_editable $page >$data_html</$tag>
		</$tag>
	</$tag>
EOT;

	if (!isset($html['css']['plugin-embed-time'])) {
$html['css']['plugin-embed-time'] = <<<EOT
		<style>
		.onethird-plugin-embed-select .outer {
			position: relative;
			margin: 0 25px 0 0;
		}
		.onethird-plugin-embed-select .inner {
			margin: 0;
			padding:0;
		}
		.onethird-plugin-embed-select .inner select {
			padding:1px;
			border:1px solid #c0c0c0;
		}
		.onethird-plugin-embed-select .save_hnd {
			position: absolute;
			cursor:pointer;
			right: -18px;
			top: 0;
			width:16px;
		}
		</style>
EOT;
	}

$a = <<<EOT
<script>
	\$(function(){
		\$(document).on('click', '.onethird-plugin-embed-select .inner', function(e){
			if (!ot.plugin_embed_text) { ot.plugin_embed_text = {}; }
			var o = \$(this);
			if (o.attr('data-editable') == 'true') {
				ot.plugin_embed_text.caret_select = this;
				if (!o.attr('data-editing')) {
					o.attr('data-editing', 'true');
					var a = "<div class='save_hnd'>";
					a += "<span class='save_col' >{$ut->icon('save')}</span>";
					a += "</div>"
					o.parents('.outer').append(a);
				}
			}
		});
	});
</script>
EOT;

	if (!$arg['readonly']) {
		$html['meta']['plugin-embed-select'] = $a;
	}
	return $buff;
}

function embed_check( $arg )
{
	global $html,$ut,$config,$params;

	$check_option = false;
	foreach ($arg as $v) {
		if (is_array($v)) {
			$check_option = $v;
			break;
		}
	}
	if (!$check_option) {
		return 'error';
	}
	$buff = '';
	if (!isset($params['_embed_id']['check'])) {
		$params['_embed_id']['check'] = 0;
	}
	$name = false;
	if (isset($arg['name'])) {
		$name = $arg['name'];
	}
	if (!$name) {
		$name = "check-".(++$params['_embed_id']['check']);
	}
	$name = sanitize_asc($name);
	$page = " data-page='{$arg['page']}' ";
	$x = get_embed_data((int)$arg['page'],$name);
	if ($x === false) {
		$x = '';
	}
	$style = '';
	$tag = 'span';
	if (isset($arg['width'])) {
		$style = "width:{$arg['width']};margin-right:10px;";
		$w_style = 'onethird-plugin-embed-check-width';
	}
$data_html = <<<EOT
EOT;
	if (!$arg['readonly']) {
		foreach ($check_option as $v) {
			$s = (strstr($x,"$v,"))? " checked ":"";
			$v = $ut->safe_str($v);
			$data_html .= "<label><input type='{$arg['type']}' value='$v' $s name='$name' />$v</label>";
		}
	} else {
		foreach ($check_option as $v) {
			$s = (strstr($x,"$v,"))? " onethird-plugin-embed-checked ":"onethird-plugin-embed-unchecked";
			$v = $ut->safe_str($v);
$data_html .= <<<EOT
			<span class='onethird-plugin-embed-readonly $s'> $v </span>
EOT;
		}
	}

	$x_editable = " data-editable='true' ";
	if (!$params['plugin-embed']['save_hnd']) { $x_editable .= " data-editing=true "; }
	if ($arg['readonly']) {
		$x_editable = " data-editable='false' ";
	}

$buff .= <<<EOT
	<$tag class='onethird-edit-pointer onethird-plugin-embed  
		onethird-plugin-embed-check .onethird-plugin-embed-{$name} '>
		<$tag class='outer' >
			<$tag class='inner' data-name='$name' data-type='check' $x_editable $page >$data_html</$tag>
		</$tag>
	</$tag>
EOT;

	if (!isset($html['css']['plugin-embed-check'])) {
$html['css']['plugin-embed-check'] = <<<EOT
		<style>
		.onethird-plugin-embed-check .outer {
			position: relative;
			margin: 0 25px 0 0;
		}
		.onethird-plugin-embed-check .inner {
			margin:0;
			padding:0;
		}
		.onethird-plugin-embed-check label {
			padding-right:12px;
		}
		.onethird-plugin-embed-check .inner input {
			vertical-align: middle;
			margin-right: 3px;
		}
		.onethird-plugin-embed-check .save_hnd {
			position: absolute;
			cursor:pointer;
			right: -18px;
			top: 0;
			width:16px;
		}
		</style>
EOT;
	}

$a = <<<EOT
<script>
	\$(function(){
		\$(document).on('click', '.onethird-plugin-embed-check .inner', function(e){
			if (!ot.plugin_embed_text) { ot.plugin_embed_text = {}; }
			var o = \$(this);
			if (o.attr('data-editable') == 'true') {
				ot.plugin_embed_text.caret_check = this;
				if (!o.attr('data-editing')) {
					o.attr('data-editing', 'true');
					var a = "<div class='save_hnd'>";
					a += "<span class='save_col' >{$ut->icon('save')}</span>";
					a += "</div>"
					o.parents('.outer').append(a);
				}
			}
		});
	});
</script>
EOT;

	if (!$arg['readonly']) {
		$html['meta']['plugin-embed-check'] = $a;
	}
	return $buff;
}

function embed_html( $arg )
{
	global $html,$ut,$config,$params;

	$buff = '';
	if (!isset($params['_embed_id']['html'])) {
		$params['_embed_id']['html'] = 0;
	}
	$name = false;
	if (isset($arg['name'])) {
		$name = $arg['name'];
	}
	if (!$name) {
		$name = "html-".(++$params['_embed_id']['html']);
	}
	$name = sanitize_asc($name);
	$page = " data-page='{$arg['page']}' ";
	$data_html = get_embed_data((int)$arg['page'],$name);
	$edit_hnd = '';
	if (!$arg['readonly']) {
$edit_hnd = <<<EOT
		onclick="ot.plugin_embed_inpage_edit({'page':{$arg['page']},'mode':0,'name':'$name' ,'idx':'0' })"
EOT;
		if (!$data_html) {
			$data_html = '<span class="onethird-button mini">edit html</span>';
		}
	}
	if (!$data_html) {
		$data_html = '...';
	}
	$x_editable = " data-editable='true' ";
	if (!$params['plugin-embed']['save_hnd']) { $x_editable .= " data-editing=true "; }
	if ($arg['readonly']) {
		$x_editable = " data-editable='false' ";
	}

$buff .= <<<EOT
	<div class='onethird-edit-pointer onethird-plugin-embed 
		onethird-plugin-embed-html .onethird-plugin-embed-{$name} '>
		<div class='outer' >
			<div class='inner' data-name='$name' data-type='html' $page $edit_hnd $x_editable >$data_html</div>
		</div>
	</div>
EOT;

	if (!isset($html['css']['plugin-embed-html'])) {
$html['css']['plugin-embed-html'] = <<<EOT
		<style>
		.onethird-plugin-embed-html .outer {
			position: relative;
			margin:0 6px 0 0;
		}
		.onethird-plugin-embed-html .inner {
			padding:0;
			margin:0 13px 0 0;
		}
		.onethird-plugin-embed-html .inner select {
			padding:1px;
			border:1px solid #c0c0c0;
		}
		</style>
EOT;
	}
	if (check_rights()) {
		snippet_delayedload();
		snippet_inpage_edit();
	}
$a = <<<EOT
<script>
	ot.plugin_embed_inpage_edit = function(opt) {
		if (ot.tryitEditor && ot.tryitEditor.check_instance()) {
			return;
		}
		if (!ot.editor) { ot.editor = {}; }
		ot.editor.option = opt;
		ot.editor.obj = \$('div[data-name='+opt.name+'][data-page='+opt.page+']')[0];
		if (!ot.editor.obj) {return;}
		ot.editor.org_html = \$(ot.editor.obj).html();
		ot.editor.quit = function() {
			ot.tryitEditor.quit();
		};
		var o = \$(ot.editor.obj);
		if (!o.attr('data-editing')) {
			o.attr('data-editing', 'true');
			ot.editor.update_embed = function() {
				var o = \$(ot.editor.obj);
				var h = ot.tryitEditor.html();
				if (h != '<p></p>') {
					o.html(h);
					ot.tryitEditor.quit();
					ot.save_embed();
				} else {
					ot.tryitEditor.quit();
				}
			};
		} else {
			ot.editor.update_embed = function() {
				var o = \$(ot.editor.obj);
				var h = ot.tryitEditor.html();
				if (h != '<p></p>') {
					o.html(h);
					ot.tryitEditor.quit();
				} else {
					ot.tryitEditor.quit();
				}
			};
		}
		var load = [];
		load.push({type:'script', src:'{$config['site_url']}js/tryitEditor.js' });
		delayedload(load
			,function() {
				var opt = {};
				var param = {};
				param.idx = ot.editor.option.idx;
				param.mode = 'obj';
				param.page = ot.editor.option.page;
				param.name = ot.editor.option.name;
				if (ot.editor.option.ctox) {
					param.ctox = ot.editor.option.ctox;
				}
				if (ot.editor.onedit) {
					opt.onedit = ot.editor.onedit;
				}
				if (ot.editor.onclose) {
					opt.onclose = ot.editor.onclose;
				}
				opt.html = ot.editor.org_html;
				if (opt.html == '<span class="onethird-button mini">edit html</span>') {
					opt.html = '<p></p>';
				}
				opt.width = ot.editor.option.width;
				opt.body_css = ot.editor.option.body_css;
				opt.after_toolbar = "<br 'clear:both' />";
				opt.after_toolbar += "<input type='button' class='onethird-button mini' value='Quit' onclick='ot.editor.quit()' />";
				opt.after_toolbar += "<input type='button' class='onethird-button mini' value='OK' onclick='ot.editor.update_embed()' />";
				opt.after_toolbar += "<input type='button' class='onethird-button mini' value='Image' onclick='ot.open_local_filer()' />";
				param.mode='-';
				if (ot.editor_toolbar) {
					opt.after_toolbar += ot.editor_toolbar;
				}
				opt.basepath = "{$ut->str(addslashes($config['site_url']))}";
				ot.tryitEditor.create(ot.editor.obj,opt);
			}
		);
	}

</script>
EOT;

	if (!$arg['readonly']) {
		$html['meta']['plugin-embed-html'] = $a;
	}
	return $buff;
}

function embed_hidden( $arg )
{
	global $html,$ut,$config,$params;
	$buff = '';
	if (!isset($params['_embed_id']['hidden'])) {
		$params['_embed_id']['hidden'] = 0;
	}
	$name = false;
	if (isset($arg['name'])) {
		$name = $arg['name'];
	}
	if (!$name) {
		$name = "hidden-".(++$params['_embed_id']['hidden']);
	}
	$name = sanitize_asc($name);
	$page = " data-page='{$arg['page']}' ";
	$x = get_embed_data((int)$arg['page'],$name);
	if ($x === false) {
		$x = 0;
	}
	if ($arg['readonly']) {
		return '';
	}
$buff .= <<<EOT
	<span class='onethird-edit-pointer onethird-plugin-embed  
		onethird-plugin-embed-hidden .onethird-plugin-embed-{$name} '>
		<span class='outer' >
			<span class='inner' data-name='$name' data-type='hidden' 
			data-editable='true' data-editing='true' $page >
				<input type='hidden' value='{$x}' name='$name' />
			</span>
		</span>
	</span>
EOT;
	return $buff;
}

?>