<?php
function blog_article()
{
	global $html,$params,$database,$ut,$config,$p_circle;
	
	$top_page = $params['circle']['meta']['top_page'];
	$page_ar = &$params['page'];
	$meta = get_theme_metadata();

	if ($params['page']['type'] != PAGE_FOLDER_ID) {
		return $ut->expand('article');
	}
	if (isset($_GET['mode']) && $_GET['mode'] == 'edit') {
		// 編集
		provide_edit_module();
		unset($params['breadcrumb']);
		snippet_breadcrumb($page_ar['id'], 'Page Edit');
		$buff = page_edit_renderer($page_ar);
		return frame_renderer($buff);
	}
	
	$setting = preparation_blog_setting($params['page']);

	$page_size = $page_ar['meta']['plugin_blog']['page_size'];
	$offset = 0;
	if (isset($_GET['offset'])) {
		$offset = (int)$_GET['offset'];
	}

	$buff = '';


	$sql = array();
	$sql[0] = "select count(id) as c from ".DBX."data_items ";
	$sql[] = array("where circle=? ", $p_circle);
	if (!empty($page_ar['meta']['plugin_blog']['parent'])) {
		$sql[] = array(" and link=?", (int)$page_ar['meta']['plugin_blog']['parent']); 
	}
	if (!empty($page_ar['meta']['plugin_blog']['type'])) {
		$sql[] = array(" and type=?", (int)$page_ar['meta']['plugin_blog']['type']); 
	}
	if (isset($_SESSION['login_id'])) {
		$sql[] = array(" and ( mode = 1 or user=?)",$_SESSION['login_id']);
	} else {
		$sql[] = array(" and mode = 1");
	}
	$ar = $database->sql_select_all($sql);
	$total_c = 0;
	if ($ar) {
		$total_c = $ar[0]['c'];
	}
	
	$option = array('total'=>$total_c, 'offset'=>$offset, 'page_size'=>$page_size, 'url'=>$params['request']);
	$pagination = std_pagination_renderer($option);
	$buff .= $pagination;

	if (!empty($page_ar['meta']['plugin_blog']['ad'])) {
		$sql[0] = "select tag,title,metadata,id,mode,mod_date,date,user,pv_count,contents from ".DBX."data_items ";
	} else {
		$sql[0] = "select tag,title,metadata,id,mode,mod_date,date,user,pv_count from ".DBX."data_items ";
	}
	$sql[] = array(" order by date desc {$ut->limit($offset*$option['page_size'],$page_size)} ");
	$ar = $database->sql_select_all($sql);
	if ($ar) {
$buff .= <<<EOT
		<div class="post-list">
EOT;
		foreach ($ar as $v) {
			if (!$v['title']) { $v['title'] = 'no title'; }
			$m = $v['meta'] = unserialize64($v['metadata']);
			$description = '';
			if (empty($m['description'])) {
				if (!empty($page_ar['meta']['plugin_blog']['ad'])) {
					$m['description'] = adjust_mstring(strip_tags($v['contents']),200);
					if (($p=strpos($m['description'],'{$'))!==false) {
						$m['description'] = substr($m['description'],0,$p);
					}
				} else {
					$m['description'] = 'Please set description setting';
				}
			}
			$tag = '';
			if (!empty($v['tag'])) {
				foreach (get_tags($v['tag']) as $vv) {
					if ($tag) { $tag .= ','; }
					$tag .=" <a href='{$ut->link('search', '&:tag='.urlencode($vv))}'>$vv</a>";
				}
			}
			$ps = '';
			if (!empty($m['author'])) {
				$ps = "<p class='post-meta'>Posted by {$m['author']} on {$ut->substr($v['date'],0,10)} $tag</p>";
			} else {
				$ps = "<p class='post-meta'>Posted on {$ut->substr($v['date'],0,10)} $tag</p>";
			}
			$ico = '';
			if ($v['mode'] == 0) {
				$ico = $ut->icon('lock');
			}
			$img = get_thumb_url($v,true);
			if ($img) {
				$img = "<img src='{$ut->safe_echo($img)}' alt='{$v['title']}' class='post-image' />";
			}
$buff .= <<<EOT
			<div class="post-preview">
				{$ut->check($img,"<div class='img-col'>$img</div>")}
				<div {$ut->check($img,"class='txt-col'")} >
					<a href="{$ut->link($v['id'])}">
						<h2 class="post-title">
							$ico{$v['title']}
						</h2>
					</a>
					<p class="post-subtitle">
						{$m['description']}
					</p>
					{$ps}
				</div>
			</div>
EOT;
		}
$buff .= <<<EOT
		</div>
EOT;

		$buff .= $pagination;

	} else {
$buff .= <<<EOT
		<div class="post-preview">
			<h2 class="post-title">
				BLOG PAGE NOT FOUND
			</h2>
EOT;
			if (check_rights('edit') && !$offset) {
$buff .= <<<EOT
				<h3 class="post-subtitle">
					Please post first page.
				</h3>
EOT;
			}
$buff .= <<<EOT
		</div>
EOT;
	}

	$buff = frame_renderer(body_renderer($page_ar).$buff.$setting,'list');
	return custompage_renderer($page_ar['id'],$buff);
}

function preparation_blog_setting(&$page_ar)
{
	global $ut,$html,$params;
	// 設定
	if (!isset($page_ar['meta']['plugin_blog'])) {
		$page_ar['meta']['plugin_blog'] = array();
	}
	$m = &$page_ar['meta']['plugin_blog'];
	$m['parent'] = (isset($m['parent'])) ? $m['parent'] : $page_ar['id'];
	$m['type'] = (isset($m['type'])) ? $m['type'] : TOPIC_ITEM_ID;
	$m['ad'] = (isset($m['ad'])) ? $m['ad'] : true;
	$m['page_size'] = (isset($m['page_size'])) ? $m['page_size'] : 10;

	if (!check_rights('owner')) {
		return;
	}

	snippet_std_setting("Blog settings","plugin_blog_setting");
	if (isset($_POST['ajax']) && $_POST['ajax'] == "plugin_blog_setting")  {
		$r = array();
		$r['result'] = true;
		$r['id'] = $page_ar['id'];

$r['html'] = <<<EOT
		<table>
			<tr>
				<td >Blog Parent</td>
				<td>
					<input type='text' data-input='plugin_blog_parent' value='{$m['parent']}' style='width:5em' />
					( 0 : Search all pages )
				</td>
			</tr>
			<tr>
				<td >Blog Page type</td>
				<td>
					<input type='text' data-input='plugin_blog_type' value='{$m['type']}' style='width:5em' />
					( default 21 : topic page, 0 : all pages ) 
				</td>
			</tr>
			<tr>
				<td >Blog Page size</td>
				<td>
					<input type='text' data-input='plugin_page_size' value='{$m['page_size']}' style='width:5em' />
				</td>
			</tr>
			<tr>
				<td >default description</td>
				<td>
					<label>
						<input type='checkbox' data-input='plugin_blog_ad' {$ut->check($m['ad'],' checked ')} />
						Auto description
					</label>
				</td>
			</tr>
		</table>
EOT;
		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'save_plugin_blog_setting')	{

		$r = array();
		$r['id'] = $page_ar['id'];
		$r['meta'] = $page_ar['meta'];

		if (!empty($_POST['plugin_blog_parent'])) {
			$m['parent'] = sanitize_num($_POST['plugin_blog_parent']);
		} else {
			$m['parent']=0;
		}
		if (!empty($_POST['plugin_blog_type'])) {
			$m['type'] = sanitize_asc($_POST['plugin_blog_type']);
		} else {
			$m['type']=0;
		}
		if (!empty($_POST['plugin_page_size'])) {
			$m['page_size'] = sanitize_asc($_POST['plugin_page_size']);
		} else {
			unset($m['page_size']);
		}
		if (!empty($_POST['plugin_blog_ad'])) {
			$m['ad'] = sanitize_asc($_POST['plugin_blog_ad']);
		} else {
			unset($m['ad']);
		}
		$r['meta']['plugin_blog'] = $m;

		$r['metadata'] = serialize64($r['meta']);
		$r['result'] = mod_data_items($r);

		echo(json_encode($r));
		exit();
	}
	
	$buff = '';

$buff .= <<<EOT
	<div>
		<input type='button' value='Add Page' onclick='ot.plugin_add_blog_page()' class='onethird-button' />
		<a href='javascript:void(ot.plugin_blog_setting())' class='onethird-button mini'>Blog setting</a>
	</div>
EOT;
	$type = $m['type'];
	if (!$type) { $type = 1; }

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'plugin_add_blog_page')  {
		$r = array();
		$r['link'] = (int)$m['parent'];
		$r['result'] = false;
		$r['type'] = (int)$m['type'];
		$r['title'] = 'blog - '.substr(time(),-4);
		$r['block_type'] = 1;
		$r['meta']['template_ar']['tpl'] = "blog_page.tpl";
		create_page( $r );
		if ($r['type'] == PAGE_FOLDER_ID && isset($r['open_url'])) {
			unset($r['open_url']);
		}
		echo( json_encode($r) );
		exit();
	}

$html['meta'][] = <<<EOT
	<script>
		ot.plugin_add_blog_page = function (link,type) {
			var opt = "ajax=plugin_add_blog_page";
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

	return $buff;
}

function blog_recent_posts($arg)
{
	global $html,$params,$database,$ut,$config,$p_circle;
	
	$arg = func_get_args();
	$ut->get_arg($arg);
	$tag = 'li';
	$class = '';
	$type = TOPIC_ITEM_ID;
	$link = 0;
	$limit = 10;
	if (!empty($arg['tag'])) {
		$tag = $arg['tag'];
	}
	if (!empty($arg['class'])) {
		$class = $arg['class'];
	}
	if (!empty($arg['type'])) { $type = (int)$arg['type']; }
	if (!empty($arg['limit'])) { $limit = (int)$arg['limit']; }
	if (!empty($arg['link'])) { $link = (int)$arg['link']; }

	$buff = '';
	$sql = array();
	$sql[0] = "select tag,title,id,mode,mod_date,date,pv_count from ".DBX."data_items ";
	$sql[] = array(" where type=? ", $type);
	if ($link) {
		$sql[] = array(" and link=? ", $link);
	}
	if (!check_rights()) {
		$sql[] = array(" and mode<>0 ");
	} else {
		$sql[] = array(" and (mode<>0 or (mode=0 and user=?))", $_SESSION['login_id']);
	}
	$sql[] = array(" order by date desc {$ut->limit(0,$limit)} ");
	$ar = $database->sql_select_all($sql);
	if ($ar) {
		foreach ($ar as $v) {
			if (!$v['title']) { $v['title'] = 'no title'; }
			$ico = '';
			if ($v['mode'] == 0) {
				if (!check_rights('owner')) { continue; }
				$ico = $ut->icon('lock');
			}
$buff .= <<<EOT
			<$tag class='$class' >
				<a href="{$ut->link($v['id'])}">$ico{$v['title']}</a>
			</$tag>
EOT;
		}
	}
	return $buff;
}

function blog_tags()
{
	global $params,$ut;

	$arg = func_get_args();
	$ut->get_arg($arg);
	$tag = 'li';
	$class = '';
	if (!empty($arg['tag'])) {
		$tag = $arg['tag'];
	}
	if (!empty($arg['class'])) {
		$class = $arg['class'];
	}

$buff = '';
	if (!empty($params['circle']['meta']['taglist'])) {
		$ar = explode(',',$params['circle']['meta']['taglist']);
		foreach ($ar as $v) {
$buff .= <<<EOT
			<$tag class='$class' >
				<a href="{$ut->link('search','&:tag='.$v)}">{$v}</a>
			</$tag>
EOT;
		}
	}
	return $buff;
}

function blog_css()
{
	global $ut, $params, $html;
	
$html['css']['blog_css'] = <<<EOT
	<style>
		.blog_article h2 {
			font-size:35px;
			margin:0 10px 10px 0;
			display: inline-block;
		}
		.blog_article .post-preview > div {
			vertical-align: top;
			margin-right:10px;
		}
		.blog_article .post-image {
			width:150px;
			padding:10px;
		}
		.blog_article .img-col {
			max-height:95%;
			overflow: hidden;
			float: left;
		}
		.blog_article .txt-col {
			display:inline;
		}
		.blog_article .post-preview {
			position: relative;
			margin:5px 0 5px;
			padding:10px 10px 10px 10px;
			border:1px solid rgba(0, 0, 0, 0.2);
		}
		.blog_article .post-subtitle {
			opacity: 0.9;
			font-size:1.2rem;
		}
		.blog_article .post-meta {
			opacity: 0.7;
			font-size:1rem;
		}
		.blog_side {
			padding-top:15px;
		}
		.blog_side .title {
			font-size:22px;
		}
		.blog_side ul {
			padding-left: 1.5em;
		}
	</style>
EOT;
}

?>