<?php
/* Search plugin
	author: team1/3
	released: 2014/01/18
	coyright(c) SpiQe Software,team1/3 All Rights Reserved.
*/

define('SEARCH_LIST_MAX', 20);
define('META_SEARCH_LIMIT', 2000);

function search_renderer( &$page_ar )
{
	global $params, $ut;

	$buff = '';
	$id = $page_ar['id'];
	
	_search_css();
	$buff .= _search_admin($page_ar);

	$arg = array('id'=>$id);
	$buff .= _search_proc($arg);
	
	return frame_renderer($buff);
}

function _search_css()
{
	global $ut, $html, $params;

	if (!isset($html['head']['plugin-search'])) {
$html['css']['plugin-search'] = <<<EOT
		<style>
			.onethird-search {
			}
			.onethird-search .text {
				padding:3px 5px 3px 5px;
				margin:0 0 10px 0;
			}
			.onethird-search .search-button {
				padding: 5px 10px 5px 10px;
				margin:0 0 10px 0;
				vertical-align: baseline;
			}
			.onethird-search-item {
				margin:20px 0 10px 0;
			}
			.onethird-search-item .text {
				padding: 2px 0 0 0;
				color: #747474;
				font-size: 90%;
				line-height: 120%;
			}
		</style>
EOT;
	}
	
}

function _search_admin(&$page_ar)
{
	global $ut, $html, $params;
	//管理者メニュー
	$id = $page_ar['id'];
	$buff = '';
	if (check_rights('owner') && $id && $page_ar['id'] != $params['page']['id']) {
$buff .= <<<EOT
		<div class='edit_pointer'>
EOT;
			$buff .= std_blockmenu_renderer($page_ar);
$buff .= <<<EOT
		</div>
EOT;
	}
	return $buff;
}

function search(&$arg)
{
	global $params, $ut;
	_search_css();
	
	if (!isset($arg['url'])) {
		$arg['url'] = $ut->link('search');
	}
	return _search_proc($arg);
}

function _search_proc($arg)
{
	global $ut, $html, $params;

	if (!isset($arg['id'])) {
		if (!isset($params['page'])) {
			$arg['id'] = 0;
		} else {
			$arg['id'] = $params['page']['id'];
		}
	}
	if (!isset($arg['keyword'])) {
		$arg['keyword'] = '';
	}
	$u = $ut->link($arg['id']);
	if (isset($params['plugin']['selector']) && $params['plugin']['selector'] == 'search') {
		$u = $ut->link('search');
	}
	
	if (isset($arg['url'])) {
		$u = $arg['url'];
	}
	
	$buff = '';
$buff .= <<<EOT
	<div class='onethird-search'>
		<input type='text' name='p_search' class='text' value= "{$arg['keyword']}"  />
		<input type='button' value='検索' onclick='plugin_search()' class='onethird-button search-button' />
EOT;
		if (check_rights('edit')) {
$buff .= <<<EOT
			<input type='button' value='meta search' onclick='plugin_search("","m")' class='onethird-button search-button' />
EOT;
		}
$buff .= <<<EOT
	</div>
EOT;
	
$html['meta']['plugin_search'] =  <<<EOT
<script>
	plugin_search = function(u,mode) {
		var s = \$('.onethird-search input[type=text]').val();
		if (!u) {
			u = "{$ut->link('search')}";
		}
		if (s) {
			if (mode == 'm') {
				location.href=u+"?m="+encodeURIComponent(s);
			} else {
				location.href=u+"?s="+encodeURIComponent(s);
			}
		}
	};
	\$(function(){
		\$('.onethird-search input[type=text]').keydown(function(event) {
			if (event.keyCode == 13) {
				plugin_search();
			}
		});
	});
</script>
EOT;
	if (isset($arg['display']) && $arg['display']=='none') {
		return '';
	}
	return $buff;
}

function search_page(&$page_ar)
{
	global $params,$config,$html,$database,$ut,$p_circle,$plugin_ar;
	
	if (isset($page_ar['mode']) && !$page_ar['mode']) {
		$page_ar['mode'] = 1;
		mod_data_items($page_ar);
	}
	
	snippet_breadcrumb( $page_ar['id'], 'search' );
	_search_css();

	$buff = '';
	if (check_rights('edit')) {

$buff .= <<<EOT
		<div class='edit_pointer'>
			<a href='javascript:void(ot.plugin_search())' class='onethird-blockmenu'>{$ut->icon('setting')}</a>
		</div>
EOT;

		snippet_std_setting('Search setting','plugin_search');

		if (isset($_POST['ajax']) && $_POST['ajax'] == 'plugin_search')  {
			$r = array();
			$r['result'] = true;
			$st = $ut->get_storage('plugin_search');
			$r['rights'] = (isset($st['rights'])) ? $st['rights'] : 3;
			$r['deny_type'] = (isset($st['deny_type'])) ? $st['deny_type'] : '';
			$r['deny_folder'] = (isset($st['deny_folder'])) ? $st['deny_folder'] : '';

			$r['limit'] = (isset($st['limit'])) ? $st['limit'] : '';
			$r['meta_limit'] = (isset($st['meta_limit'])) ? $st['meta_limit'] : '';
			$r['tpl'] = (isset($st['tpl'])) ? $st['tpl'] : '';
			$rights_ar = array('0'=>'edit','1'=>'login user','3'=>'public');
$r['html'] = <<<EOT
			<table>
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
					<td>Limit</td>
					<td>
						<input type='text' data-input='limit' value='{$r['limit']}' />
					</td>
				</tr>
				<tr>
					<td>Limit (meta search)</td>
					<td>
						<input type='text' data-input='meta_limit' value='{$r['meta_limit']}' />
					</td>
				</tr>
				<tr>
					<td>template</td>
					<td>
						<input type='text' data-input='tpl' value='{$r['tpl']}' />
					</td>
				</tr>
			</table>
EOT;
			echo(json_encode($r));
			exit();
		}

		if (isset($_POST['ajax']) && $_POST['ajax'] == 'save_plugin_search')  {
			$r = array();
			$r['result'] = false;

			$st = $ut->get_storage('plugin_search');
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
			$r['result'] = $r['result'] && $ut->set_storage('plugin_search',$st);

			echo(json_encode($r));
			exit();
		}
	} 

	$buff .= '<h1>検索結果</h1>';
	$buff .= _search_admin($page_ar);
	
	$total = $offset = 0;
	if (isset($_GET['offset'])) {
		$offset = $_GET['offset'];
	}

	$keyword = '';
	$search_mode = false;
	if (isset($_GET['s'])) {
		$search_mode = 0; 	//通常サーチ
		$keyword = safe_echo($_GET['s']);
	}
	if (isset($_GET['t'])) {
		$search_mode = 1; 	//タイトル＆タグサーチ
		$keyword = safe_echo($_GET['t']);
	}
	if (isset($_GET['title'])) {
		$search_mode = 2; 	//タイトルサーチ
		$keyword = safe_echo($_GET['title']);
	}
	if (isset($_GET['tag'])) {
		$search_mode = 3; 	//タグサーチ
		$keyword = safe_echo($_GET['tag']);
	}
	if (!empty($_GET['m']) && check_rights()) {
		$search_mode = 4; 	//メタサーチ
		$keyword = safe_echo($_GET['m']);
	}

	$buff .= _search_proc(array('id'=>$page_ar['id'], 'keyword'=>$keyword ));

	if (strlen($keyword) < 2) {
		return frame_renderer($buff . '<p>- 検索キーワードが短すぎます</p>');
	}
	if ($search_mode === false) {
		return frame_renderer($buff . '<p>- No item</p>');
	}


	$st = $ut->get_storage('plugin_search');
	if (!empty($st['limit'])) {
		$page_size = (int)$st['limit'];
	} else {
		$page_size = SEARCH_LIST_MAX;
	}
	if (!empty($st['meta_limit'])) {
		$meta_search_limit = (int)$st['meta_limit'];
	} else {
		$meta_search_limit = META_SEARCH_LIMIT;
	}

	if (!empty($st['rights'])) {
		$rights = (int)$st['rights'];
	} else {
		$rights = 3;
	}
	if (!empty($st['tpl'])) {
		$params['template'] = $st['tpl'];
	} else {
		$rights = 3;
	}
	if ($rights == 0 && !check_rights('edit')) {
		return $buff . '<p>- rights error (edit)</p>';
	}
	if ($rights == 1 && !check_rights()) {
		return $buff . '<p>- rights error </p>';
	}

	$sql = array();
	$sql[0] = "select count(id) as c from ".DBX."data_items ";
	if ($search_mode == 4) {
		$sql[1] = array('where ');
		$sql[2] = array(" circle=? ", $p_circle );
	} else {
		if ($search_mode == 3) {
			$sql[1] = array("where tag like ? ", "%$keyword%");
			
		} else if ($search_mode == 2) {
			$sql[1] = array("where title like ? ", "%$keyword%");
			
		} else if ($search_mode == 1) {
			$sql[1] = array("where (tag like ? or title like ?) ", "%$keyword%", "%$keyword%");
			
		} else {
			$sql[1] = array("where (contents like ? or tag like ? or title like ?) ", "%$keyword%", "%$keyword%", "%$keyword%");
		}
		$sql[2] = array(" and circle=? ", $p_circle );
		if (check_rights()) {
			$sql[] = array(" and (mode=1 or (mode=0 and user={$_SESSION['login_id']})) ");
		} else {
			$sql[] = array(" and mode=1 ");
		}
		$sql[] = array(" and block_type <=15 and block_type <> 0 and type<> ? ", HIDDEN_ID);

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
	}

	if ($search_mode != 4) {
		$ar = $database->sql_select_all($sql);
		if ($ar) {
			$total = $ar[0]['c'];
		}
	}
	$option = array();
	$option['total'] = $total;
	$option['offset'] = $offset;
	$option['page_size'] = $page_size;
	$option['url'] = $ut->link($page_ar['id'])."search?s=$keyword";

	if ($search_mode == 4) {
$buff .= <<<EOT
		<div class='onethird-search-item'>
			meta search
		</div>
EOT;
	} else {
		if ($total > 1) {
$buff .= <<<EOT
			<div class='onethird-search-item'>
				{$total} items
			</div>
EOT;
		}
		if ($search_mode == 1) {
$buff .= <<<EOT
			<div class='onethird-search-item'>
				title,tag search
			</div>
EOT;
		}
		if ($search_mode == 2) {
$buff .= <<<EOT
			<div class='onethird-search-item'>
				title search
			</div>
EOT;
		}
		if ($search_mode == 3) {
$buff .= <<<EOT
			<div class='onethird-search-item'>
				 tag search
			</div>
EOT;
		}
	}


	if ($search_mode == 4) {
		$sql[0] = "select id,type,title,contents,metadata,mode from ".DBX."data_items  ";
		$sql[] = " order by date limit $meta_search_limit ";
	} else {
		$sql[0] = "select id,type,title,contents,mode from ".DBX."data_items  ";
		$sql[] = " order by date {$ut->limit($offset*$page_size, $page_size)}";
	}
	$ar = $database->sql_select_all($sql);
	if ($ar && $search_mode == 4) {
		function _search_page_array_walk($item, $key) {
			global $params;
			if ($params['meta_search_work'] !== true) {
				if (is_string($item) && strstr($item, $params['meta_search_work'])) {
					$params['meta_search_work'] = true;
				}
			}
		}
		$x = array();
		foreach ($ar as $v) {
			$m = unserialize64($v['metadata']);
			$y = false;
			$params['meta_search_work'] = $keyword;
			array_walk_recursive($m, '_search_page_array_walk');
			if ($params['meta_search_work'] === true) {
				$x[] = $v;
			}
		}
		$ar = $x;
	} else {
		$buff .= std_pagination_renderer($option);
	}
	if (!$ar) {
		return frame_renderer($buff."<div class='onethird-search-item'> not found</div>");
	}

	foreach ($ar as $v) {
		$t = adjust_mstring(strip_tags($v['contents']),200);
		$t = preg_replace("/(\x7B\\$.*?\x7D)/mu",'',$t);
		$l = '';
		if ($v['mode']==0) {
			$l = $ut->icon('lock');
		}
$buff .= <<<EOT
		<div class='onethird-search-item'>
			<a href='{$ut->link($v['id'])}'>{$l}{$v['title']}</a>
			<div class='text'>
				$t
			</div>
		</div>
EOT;
	}

	return frame_renderer($buff);
}

?>