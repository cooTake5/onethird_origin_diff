<?php

//プラグイン内共通定数

	// 0 ～ 10 は予約
	// 10 ～ 999 は基本プラグイン
	// 1000 以降はユーザープラグインで使用
	
	//define ( "", 0 );		// 予約
	//define ( "", 1 );		// 予約
	//define ( "", 2 );		// 予約
	//define ( "", 3 );		// 予約
	//define ( "", 4 );		// 予約
	//define ( "", 5 );		// 予約
	//define ( "", 6 );		// 予約
	//define ( "", 7 );		// 予約
	//define ( "", 8 );		// 予約
	define ( "PLUGIN_ID", 9 );			//（予約）
	define ( "VOTE_ID", 10 );			// アンケート
	define ( "QUIZ_ID", 11 );			// クイズ
	define ( "PAGELIST_ID", 17 );		// サイトマップ->ページ一覧
	//define ( "TOPIC_BINDER_ID", 20 );	// トピックバインダー→ * 廃止
	define ( "TOPIC_ITEM_ID", 21 );		// トピックアイテム
	define ( "CIRCLELIST_ID", 30 );		// サイトリスト
	//define ( "GNAV_ID", 33 );			// Global nav用* 廃止
	//define ( "SBMENU_ID", 35 );		// Sub Menu用* 廃止
	//define ( "SDMENU_ID", 37 );		// Side Menu用* 廃止
	//define ( "NEWS_ID", 39 );			// 更新履歴用* 廃止
	define ( "SEARCH_ID", 40 );			// 検索
	define ( "HIDDEN_ID", 50 );			// 非表示アイテム
	//define ( "CTRLPAGE_ID", 51 );		// 管理画面用* 廃止
	//define ( "SIMPLEDB_BINDER_ID", 60 );// 簡易データベース(バインダー)* 廃止
	//define ( "SIMPLEDB_ITEM_ID", 61 );// 簡易データベースアイテム* 廃止
	define ( "VISITOR_IP_ID", 70 );		// 閲覧者IP
	define ( "PAGE_ANALYTICS_ID", 71 );	// 閲覧者IP
	//define ( "NEXT_POST_ID", 72 );	// 次のコンテンツ * 廃止
	define ( "CALENDAR_ID", 80 );		// カレンダー
	//define ( "TODO_ID", 81 );			// TODO用
	//define ( "ISSUE_ID", 82 );		// 課題用
	//define ( "REVIEW_ID", 83 );		// 議事録用
	//define ( "MESS_ID", 84 );			// メッセージ用
	//define ( "SLIDESHOW_ID", 90 );		// Slide Show
	define ( "EMBED_ID", 91 );			// embed ITEM
	define ( "GMAP_ID", 100 );			// グーグルマップ
	define ( "MAP_ID", 101 );			// オープンストリートマップ
	define ( "CONTACT_ID", 110 );		// コンタクトリスト
	define ( "BBS2_ID", 120 );			// 掲示板 ver 2
	define ( "SITEMAPXML_ID", 130 );	// XMLサイトマップ
	define ( "LOGIN_ID", 140 );			// login
	// define ( "EZLOGIN_ID", 141 );
	define ( "RSS_ID", 150 );			// RSS
	//151 reservation pubsubhubbub 
	//152 trash
	//define ( "MEMBERPAGE_ID", 160 );	// 会員制サイト廃止
	define ( "ACCCTRL_ID", 161 );		// アクセスコントロール
	//define ( "CART_ID", 162 );		// shopping cart (reservation)
	//define ( "EVENT_ID", 163 );		// event (reservation)
	//define ( "FLEXCOLUMN_ID", 164 );	// FLEX COLUMN (reservation)
	//define ( "MAILMAGA_ID", 165 );	// mail magazine (reservation)
	//define ( "RESERVATION_ID", 166 );	// RESERVATION (reservation)
	//define ( "FORUM_ID", 167 );		// FORUM (reservation)
	//define ( "PHOTO_TILES_ID", 168 );	// photo_tiles (reservation)
	//define ( "PEEK_CSV_ID", 169 );	// peek_csv (reservation)
	define ( "SCHE_ID", 170 );			// schedule
	define ( "SCHE_SETTING_ID", 171 );	// schedule
	define ( "PAGE_FOLDER_ID", 180 );	// Page folder
	//define ( "LANG_PAGE_ID", 185 );	// 多国語ページ * 廃止
	//define ( "LANG_DATA_ID", 186 );	// 多国語データ * 廃止
	define ( "CACHE_ID", 190 );			// 表示キャッシュ* 廃止
	define ( "PRETTIFY_ID", 193 );		// Google prettify
	define ( "UPLOADER_ID", 195 );		// Uploader
	//define ( "EZ_UPLOADER_ID", 196 );	// Simple uploader * 廃止
	//define ( "SCROLL_ID", 197 );		// SCROLL (MOVE to top) * 廃止
	//define ( "LINK_PAGE_ID", 198 );	// Link page
	// 200 - 999						// ID無しプラグイン
	define ( "USERPLUGIN_ID", 1000 );	// ユーザープラグイン
		
	//プラグイン配列
	global $plugin_ar, $p_page;
	if (!isset($plugin_ar)) {
		$plugin_ar = array();
	}

	//プラグイン配列のキャッシュ読み込み
	load_plugin_ar();

function load_plugin_ar($refresh = false)
{
	global $params, $p_circle, $database, $plugin_ar;
	if (isset($params['circle']['meta']['plugin_cache']) && $database) {
		//旧版との互換のため将来的に削除
		$ar = $database->sql_select_all("select metadata from ".DBX."circles where id=?", $p_circle);
		if ($ar) {
			$m = unserialize64($ar[0]['metadata']);
			if ($m) {
				unset($m['plugin_cache']);
				$database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($m), $p_circle);
			}
		}
	}
	if ($refresh && $database) {
		$ar = $database->sql_select_all("select metadata from ".DBX."circles where id=?", $p_circle);
		if ($ar) {
			$m = unserialize64($ar[0]['metadata']);
			if ($m) {
				unset($m['installed_plugins']);
				$database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($m), $p_circle);
			}
		}
	}

	$plugin_ar[ PAGELIST_ID ] = array(	//
		  'selector' => "list"
		, 'title' => "ページ一覧"
		, 'php' => "pagelist"
		, 'url' => true
		//, 'mode' => array(3,4..)
	);

	$plugin_ar[ SITEMAPXML_ID ] = array(
		  'selector' => "sitemap_xml"
		, 'title' => "サイトマップ"
		, 'php' => "sitemap_xml"
		, 'url' => true
		//, 'limit' => 500
	);

	$plugin_ar[ RSS_ID ] = array(	//
		  'selector' => "atom_xml"
		, 'title' => "RSS"
		, 'php' => "rss"
		, 'url' => true
		//, 'limit' => 20
		//, 'author' => 'name'
	);

	$plugin_ar[ LOGIN_ID ] = array(		// 
		  'selector' => "login"
		, 'title' => "ログイン"
		, 'php' => "login"
		, 'url' => true
	);

	$plugin_ar[ TOPIC_ITEM_ID ] = array(
		  'selector' => "topic"
		, 'title' => "トピックページ"
		, 'renderer' => true
		, 'add_page' => true
		, 'php' => "topic"
	);

	$plugin_ar[ PAGE_FOLDER_ID ] = array(
		  'selector' => "page_folder"
		, 'title' => "ページフォルダー"
		, 'add_page' => true
		, 'add_inner' => true
		, 'php' => "page_folder"
		, 'renderer' => true
		, 'writer' => array(
			'std_page_folder_writer'=>'list'
			, 'std_page_folder_writer_ico'=>'icon'
			, 'std_page_folder_writer_ol'=>'ol'
			, 'std_page_folder_writer_date'=>'date'
			, 'std_page_folder_writer_detail'=>'detail'
		)
		, 'onbefore_modified' => 'page_folder_onbefore_modified'
		//, 'type' => array(NEWS_ID=>'NEWS')
	);

	$plugin_ar[ CONTACT_ID ] = array(
		  'selector' => "contact"
		, 'title' => "お問い合わせフォーム"
		, 'add_inner' => true
		, 'php' => "contact"
		, 'renderer' => true
	);

	$plugin_ar[ GMAP_ID ] = array(
		  'selector' => "gmap"
		, 'title' => "Google Map"
		, 'php' => "gmap"
		, 'renderer' => true
	);

	$plugin_ar[ MAP_ID ] = array(
		  'selector' => "map"
		, 'title' => "地図"
		, 'add_inner' => true
		, 'php' => "smap_cdn"
		, 'renderer' => true
	);
	

	$plugin_ar[ BBS2_ID ] = array(
		  'selector' => "bbs2"
		, 'title' => "掲示板"
		, 'renderer' => true
		, 'php' => "bbs2"
		, 'add_inner' => true
		, 'list_size' => 10
		//, 'private' => true		// 対話モード(スレ主に対してプライベート返信)
		, 'reply' => true			// 返信の許可
		, 'file' => true			// 写真,ファイルの添付
	);

	$plugin_ar[ SEARCH_ID ] = array(
		  'selector' => "search"
		, 'title' => "検索"
		, 'add_inner' => true
		, 'php' => "search"
		, 'url' => true
		, 'renderer' => true
	);
	
	/*
	$plugin_ar[ VOTE_ID ] = array(
		  'selector' => "vote"
		, 'title' => "投票"
		, 'add_inner' => true
		, 'php' => "vote"
		, 'renderer' => true
		, 'style' => 'vote'
	);
	*/

	
	$plugin_ar[ VISITOR_IP_ID ] = array(
		  'selector' => "visitor_ip"
		, 'title' => "訪問者IP"
		, 'renderer' => true
		, 'php' => "visitor_ip"
		, 'url' => 'acclog'
		, 'add_inner' => true
	);

	/*
	$plugin_ar[ PAGE_ANALYTICS_ID ] = array(
		  'selector' => "page_analytics"
		, 'title' => "Visitor analytics"
		, 'renderer' => true
		, 'php' => "analytics"
		, 'url' => 'analytics'
		, 'add_inner' => true
	);
	*/

	/*
	$plugin_ar[ CIRCLELIST_ID ] = array(		// サイト一覧(テンプレート組み込み型)
		  'selector' => "circle_list"
		, 'php' => "comm"
	);
	*/

	/*
	$plugin_ar[ PRETTIFY_ID ] = array(
		  'selector' => "prettify"
		, 'title' => "Google prettify"
		, 'add_attached' => true
		, 'php' => "prettify"
		, 'renderer' => true
	);
	*/
	
	$plugin_ar[ EMBED_ID ] = array(
		  'selector' => "embed"
		, 'php' => "embed"
	);

	$plugin_ar[ UPLOADER_ID ] = array(
		  'selector' => "uploader"
		, 'title' => "アップローダー"
		, 'add_inner' => true
		, 'php' => "uploader"
		, 'url' => true
		, 'renderer' => true
		, 'onbefore_remove' => 'uploader_onbefore_remove'
		, 'oncreate' => 'oncreate_uploader'
		, 'writer' => array(
			'uploader_ez_writer'=>'ez-uploader'
			, 'std_uploader_writer'=>'std-uploader'
		)
	);

	/*
	$plugin_ar[ EZ_UPLOADER_ID ] = array(
		  'selector' => "ez_uploader"
		, 'title' => "ファイル添付"
		, 'add_inner' => false
		, 'php' => "uploader"
		, 'renderer' => true
		, 'url' => true
		, 'inner_renderer' => 'uploader_renderer'
		, 'page_renderer' => 'uploader_page'
		, 'writer' => array(
			'uploader_ez_writer'=>'ez-uploader'
			, 'std_uploader_writer'=>'std-uploader'
		)
		, 'onbefore_remove' => 'uploader_onbefore_remove'
	);
	*/

	if (isset($params['circle']['meta']['installed_plugins'])) {
		foreach ($params['circle']['meta']['installed_plugins'] as $k=>$v) {
			$plugin_ar[$k] = $v;
		}
	}

}

function include_plugin_proc( &$v, $sel = null )
{
	global $params,$config,$html,$plugin_ar,$ut;

	if ($sel) {
		if (isset($v[$sel])) {
			if (substr($v[$sel],0,1) == '.') {
				$a = substr($v[$sel],1);
				$path = $params['files_path'].DIRECTORY_SEPARATOR;
				require_once("{$path}plugin/$a.php");
			} else {
				require_once($config['site_path']."/plugin/{$v[$sel]}.php");
			}
			return $v[$sel];
		}
	}
	
	if (isset($v['php'])) {
		if (!$v['php']) {
			return true;
		} else if (substr($v['php'],0,1) == '.') {
			$a = substr($v['php'],1);
			$path = $params['files_path'].DIRECTORY_SEPARATOR;
			@include_once("{$path}plugin/$a.php");
		} else {
			if (!@include_once($config['site_path']."/plugin/{$v['php']}.php")) {
				return false;
			}
		}
		return $v['php'];
	}

	if (isset($v['selector'])) {
		$path = $params['files_path'].DIRECTORY_SEPARATOR;
		require_once("{$path}plugin/{$v['selector']}.php");
		return $v['selector'];
	}

	return false;
}

function get_plugin_page( $type, $id, &$page_ar, &$page_metadata )
{
	global $params,$config,$html,$plugin_ar;

	$buff='';
	$id = $page_ar['id'];

	if (isset($plugin_ar[$type])) {	
		$v = $plugin_ar[$type];
		$sel = $v['selector'];
		if (isset($v['page_renderer'])) {
			$proc = $v['page_renderer'];
		} else if (isset($v['proc'])) {
			$proc = $v['proc'].'_page';
		} else {
			$proc = $v['selector'].'_page';
		}
		$p_page = $id;
		$f = include_plugin_proc($v,'page');
		if (!function_exists($proc)) {
			exit_proc(403, "{$proc} not found in {$f}.php (type:$type)");
		}
		if ($p_page) { pv_logging($p_page); }
		$buff = $proc($page_ar);
	}
	
	return $buff;
}

function create_plugin_page(&$page_ar)
{
	global $params,$config,$html,$plugin_ar,$ut;

	$buff='';
	$path = $params['files_path'].DIRECTORY_SEPARATOR;
	if (isset($plugin_ar[$page_ar['type']])) {
		$v = $plugin_ar[$page_ar['type']];
		include_plugin_proc($v);
		if (isset($v['oncreate'])) {
			$proc = $v['oncreate'];
		} else {
			$proc = $v['selector'].'_create';
		}
		if (function_exists($proc)) {
			return $proc($page_ar);
		}
	}
	return false;
}

function event_plugin_page($event, &$page_ar)
{
	global $params,$config,$html,$plugin_ar,$ut;
	
	$buff='';
	$path = $params['files_path'].DIRECTORY_SEPARATOR;
	if (isset($plugin_ar[$page_ar['type']])) {
		$v = $plugin_ar[$page_ar['type']];
		include_plugin_proc($v,'page');
		if (isset($v[$event])) {
			$proc = $v[$event];
		} else {
			if ($event != 'onmodified') {
				return null;
			}
			$proc = $v['selector'].'_modified';
		}
		if (function_exists($proc)) {
			return $proc($page_ar);
		}
	}
	return null;
}

function plugin_renderer( &$page_ar )
{
	global $plugin_ar,$config,$ut;
	if (!isset($page_ar['id'])) { return false; }
	if (!isset($page_ar['type'])) { return false; }
	if (!isset($page_ar['top'])) { $page_ar['top']=true; }

	$type = $page_ar['type'];
	if ($type <= PLUGIN_ID || $type == 50 || $type == 5) {
		return false;
	}
	if (isset($plugin_ar[$type]['renderer']) || isset($plugin_ar[$type]['inner_renderer'])) {
		$v = $plugin_ar[$type];
		$f = include_plugin_proc($v,($v['renderer'] !== true)?$v['renderer']:null );
		if (!$f) {
			return "<b>plugin error in {$v['selector']}</b>";
		}
		if (isset($v['inner_renderer'])) {
			$proc = $v['inner_renderer'];
		} else if (isset($v['renderer']) && $v['renderer'] !== true ) {
			$proc = $v['renderer'].'_renderer';
		} else {
			$proc = $v['selector'].'_renderer';
		}
		if ( !function_exists($proc) ) {
			exit_proc(403, "{$proc} not found in {$f}.php (type:{$type})");
		}
		return $proc($page_ar);
	}
	if (check_rights('admin') && !isset($plugin_ar[$type])) {
		return "<p><b>plugin error type : {$type} <a href='javascript:(void(ot.remove_page({$page_ar['id']})))'>{$ut->icon('remove')}</a></b></p>";
	}
	return false;
}

function plugin_proc()
{

	global $params, $config, $plugin_ar, $ut;

	$arg_list = func_get_args();
	if (!isset($arg_list[0])) {
		return "argument is invalid";
	}
	$proc = $arg_list[0];
	foreach ($plugin_ar as $k=>$v) {
		if ($v['selector'] == $proc) {
			$arg = array();
			if (isset($arg_list[1])) {
				if (is_array($arg_list[1])) {
					$arg = $arg_list[1];
				} else {
					array_shift($arg_list);
					$arg = &$arg_list;
				}
			}
			
			$ut->get_arg($arg);

			$f = include_plugin_proc($v);
			
			if (isset($v['plugin_renderer'])) {
				$proc = $v['plugin_renderer'];
			} else if (isset($v['proc'])) {
				$proc = $v['proc'];
			} else {
				$proc = $v['selector'];
			}

			if (!function_exists($proc)) {
				return "({$proc} not found in {$f}.php (type:$k))";
			}
			return $proc($arg);

		}
	}
	return "($proc not found)";

}


?>