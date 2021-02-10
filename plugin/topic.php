<?php
/* Access control plugin
	author: team1/3
	released: 2014/10/04
	type : attached
	coyright(c) SpiQe Software,team1/3 team1/3 All Rights Reserved.
	
	※トピックページと通常ページの違い
	1) 同一階層ページの移動セレクター
	2) タグ一覧
	3) 本文の先頭をog_descriptionに自動コピー
	
*/

define ( "TOPIC_PREVSIZE", 500 );		//ブログのプレビューサイズ

function topic_page(&$page_ar)
{
	global $html,$params,$database,$config,$ut;
	
	$p_page = $page_ar['id'];
	if (isset($_SESSION['login_id']) && $params['page']['user'] != $_SESSION['login_id']) {
		unset($params['edit-right']);	// ページ作成者以外は編集不可とする
	}

	if (!check_rights('edit') && !empty($params['edit-right'])) {
		set_func_rights('edit.modules');
		set_func_rights('add_page');
		provide_edit_rights();
	}
	
	if (isset($_GET['mode']) && $_GET['mode']=='edit') {
		set_func_rights('page_property');
		provide_edit_module();
		snippet_page_property();
		snippet_breadcrumb($p_page, 'Page Edit');
		$buff = page_edit_renderer($params['page']);
		return frame_renderer($buff);
	}

	$buff ='';
	
	$buff .= basic_renderer($p_page);
	$buff .= frame_renderer(topic_nav_renderer($page_ar));

	return $buff;
}

function topic_nav_renderer(&$page_ar)
{
	global $html,$params,$database,$config,$ut;

	if (!isset($html['css']['topic_nav_renderer'])) {
$html['css']['topic_nav_renderer'] = <<<EOT
		<style>
			.topic-nav {
				position: relative;
			}
			.topic-nav .right{
				position: absolute;
				right: 0;
			}
		</style>
EOT;
	}

	$name = '';
	$ar = $database->sql_select_all("select nickname, name from ".DBX."users where id=?", $params['page']['user']);
	if ($ar) {
		if ($ar[0]['nickname']) {
			$name = " [ {$ar[0]['nickname']} ]";
		}
	}
	
	$topic_nav = '';
$topic_date = <<<EOT
	<div class='topic_date' >
		{$params['page']['date']} $name
EOT;
		if ($params['page']['tag']) {
			$topic_date .= topic_binder_tag($page_ar['link'], $params['page']['tag']);
		}
$topic_date .=  <<<EOT
	</div>
EOT;
	if (function_exists('sns_plugin')) {
		$sns_plugin = sns_plugin();
	} else {
		$sns_plugin = '';
	}
	if (isset($params['page']['link'])) {
	
		$link = $params['page']['link'];

		$sql = "select date from ".DBX."data_items where id=? ";
		$ar = $database->sql_select_all($sql, $params['page']['id']);
		if ($ar) {
			$d = $ar[0]['date'];
		} else {
			$d = $params['page']['date'];
		}
		$topic_nav = '';
		$sql = "select id,title from ".DBX."data_items where mode=1 and date < ? and link=? and type=? order by date desc limit 1";
		$ar = $database->sql_select_all($sql, $d, $link, TOPIC_ITEM_ID);
		if ($ar) {
			if ($topic_nav) { $topic_nav .= ' / '; }
			$m = adjust_mstring($ar[0]['title'],20);
			$topic_nav .= "<a href='{$ut->link($ar[0]['id'])}'>&laquo; $m</a>";
		}

		$sql = "select id,title from ".DBX."data_items where mode=1 and date > ? and id<> ? and link=? and type=? order by date limit 1";
		$ar = $database->sql_select_all($sql, $d,$params['page']['id'], $link, TOPIC_ITEM_ID);
		if ($ar) {
			$m = adjust_mstring($ar[0]['title'],20);
			$topic_nav .= " <a href='{$ut->link($ar[0]['id'])}' class='right'>$m &raquo;</a>";
		}

	}
$tmp = <<<EOT
	<div class='topic-nav '>
		$topic_nav
		$topic_date
		$sns_plugin
	</div>
EOT;
	return $tmp;
}

function topic_create(&$page_ar)
{
	$metadata = array();
	topic_modified($page_ar, $metadata);
}

function topic_modified(&$page_ar)
{
	global $database,$params,$config;
	$p_page = $page_ar['id'];
	$ar = $database->sql_select_all( "select contents,metadata,link,tag from ".DBX."data_items where id=? " , $p_page );
	if ($ar) {
		if ($ar[0]['metadata']) {
			$metadata = unserialize64($ar[0]['metadata']);
		} else {
			$metadata = array();
		}
		$v = adjust_mstring( strip_tags($ar[0]['contents']), TOPIC_PREVSIZE );
		$v = str_replace("\n"," ",$v);	
		$v = str_replace("\r"," ",$v);	
		$v = str_replace("'"," ",$v);	
		$v = preg_replace("/\\{\\$.*\\}/mu", '', $v);
		$metadata['og_description'] = $v;
		$tag = $ar[0]['tag'];
		if (!$database->sql_update("update ".DBX."data_items set metadata=?,tag=? where id=?", serialize64($metadata),$tag,$p_page)) {
		}
	}
}
function topic_renderer( &$page_ar )
{
	return frame_renderer(body_renderer($page_ar));
}

function topic_binder_tag( $id, $tag )
{
	global $ut;
	$a = '';
	if ($tag) {
		foreach (get_tags($tag) as $v) {
			if ($a) { $a .= ','; }
			$a .=" <a href='{$ut->link($id, '&:tag='.urlencode($v))}'>$v</a>";
		}
	}
	return $a;
}

?>