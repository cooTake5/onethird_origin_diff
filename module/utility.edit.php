<?php 
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.

	error_reporting(E_ALL|E_WARNING);

function remove_this_page( $page )
{
	global $database;

	$page_ar = array();
	if (!read_pagedata( $page, $page_ar )) {
		return false;
	}

	$link = $page_ar['link'];
	if (event_plugin_page('onbefore_remove', $page_ar) === false) {
		return false;
	}

	//link元がinner pageの場合もう一つ遡る
	$sql="select id,link,block_type from ".DBX."data_items where id=? ";
	$ar = $database->sql_select_all( $sql, $link );
	if ($ar) {
		if ($ar[0]['block_type'] == 5) {
			$link = $ar[0]['link'];
		}
	}

	$ar = array();
	get_linked_pages( $page, $ar );
	if (!remove_pages($ar)) {
		return false;
	}
	regenerate_attached( $link );
	return $link;
}

function get_linked_pages( $page, &$ret_ar, $nest=0 )
{
	global $database,$p_circle;
	++$nest;

	if ( $nest > MAX_PAGE_NEST ) { return; }

	if ((int)$page) {
		$ret_ar[] = (int)$page;
	}
	$sql="select id from ".DBX."data_items where link=? and circle=?";
	$ar = $database->sql_select_all($sql, $page,$p_circle);
	if (!$ar) {
		return;
	}
	foreach ($ar as $v) {
		get_linked_pages($v['id'], $ret_ar, $nest);
	}
	return;
}

function remove_pages( $ar )
{
	global $database;
	if (!check_func_rights('remove_pages')) {
		return false;
	}
	$database->sql_begin();
	$ok = true;
	foreach ($ar as $v) {
		if ($v) {
			if ( !$database->sql_update("delete from ".DBX."user_log where link=?",$v ) ) {
				//$ok = false;
			}
			if ( !$database->sql_update("delete from ".DBX."data_items where id=?",$v ) ) {
				$ok = false;
			}
		}
	}
	if (!$ok) {
		$database->sql_rollback();
		return false;
	}
	$database->sql_commit();
	return true;
}

function make_backupdir()
{
	global $params,$config,$database,$ut;

	$path = $params['files_path'].DIRECTORY_SEPARATOR.'backup';
	if (!is_dir($path)) {
		mkdir($path);
		chmod($path, $config['permission']);

$tmp =  <<<EOT

order deny,allow
deny from all

EOT;
		$f = $path.DIRECTORY_SEPARATOR.'.htaccess';
		if (!file_put_contents($f, $tmp)) {
			system_error( __FILE__, __LINE__ );
		}
		chmod($f,$config['permission']);
	}

}

function backup_page( $id, $diff_start, &$mess, &$progress )
{
	global $params,$config,$database,$p_circle,$ut;
	
	$mess = '';
	
	$limit = 1024*1000;		// one backup file size
	$limt_c = 100;			// for page backup
	
	if ( !isset($_SESSION['login_id']) || !$_SESSION['login_id'] ) {
		return false;
	}

	if (!check_rights('admin') || !class_exists('ZipArchive')) {
		return false;
	}

	$date = date('Y-m-d_H_i', $_SERVER['REQUEST_TIME']);
	$progress = 100;

	make_backupdir();
	$path = $params['files_path'].DIRECTORY_SEPARATOR.'backup';
	
	$backup_idx = array();
	$backup_idx['last_id'] = false;
	$backup_idx['making'] = $_SESSION['login_id'];
	$backup_idx['ver-ex-zip'] = true;		//完全バックアップ廃止、差分バックアップ拡張バージョン
	$body = "";

	$t_now = time();
	$count = 0;
	$done = false;
	$zip_file = false;
	$zip = new ZipArchive();

	if ($id == 0) {
		$ar = @glob($path.DIRECTORY_SEPARATOR.'*.zip');
		if ($ar) {
			foreach ($ar as $v) {
				$r = $zip->open($v);
				if ($r === true) {
					$x = $zip->getFromName('index.dat');
					$zip->close();
					if ($x) {
						$idx = unserialize64($x);
						if (isset($idx['making']) && $idx['making'] == $_SESSION['login_id']) {
							$zip_file = $v;
							$backup_idx = $idx;
							$new_f = false;
						}
					}
				}
			}
		}
	}
	if (!$zip_file) {
		$zip_file = $path.DIRECTORY_SEPARATOR.'bx'.time().'.zip';
	}

	if (!function_exists('_backup_page_ar')) {
		function _backup_page_ar($v, &$backup_idx, &$body) {
			global $database,$config,$params;
			if (!$v && $v !== 0) { return false; }
			$cmd = array(
				'user_log'
				, 'storage'
				, 'data_items'
			);
			if ($v === 0) {
				$cmd = array(
					'user_log'
					, 'storage'
				);
			}
			if (isset($params['database_backup_tables'])) {
				$cmd = array_merge($cmd, $params['database_backup_tables']);
			}
			foreach ($cmd as $k=>$c) {
				if ($c == 'data_items') {
					$ar = $database->sql_select_all("select * from ".DBX."$c where id=?", $v);
				} else if (is_array($c)) {
					continue;
				} else {
					$ar = $database->sql_select_all("select * from ".DBX."$c where link=?", $v);
				}
				if ($ar) {
					foreach ($ar as $vv) {
						$body .= "\ni:$v";
						$body .= "\nt:$c";
						$body .= "\nc:".serialize64($vv);
					}
				}
			}
			if ($v === 0) {
				foreach ($cmd as $k=>$c) {
					if (is_array($c) && !empty($c['table'])) {
						$t = $c['table'];
						$ar = $database->sql_select_all("select * from ".DBX."$t");
						if ($ar) {
							foreach ($ar as $vv) {
								$body .= "\ni:0";
								$body .= "\nt:$t";
								$body .= "\nc:".serialize64($vv);
							}
						}
					}
				}
			}
			return true;
		}
	}

	$backup_idx['info'] = "zip";		//完全バックアップ、差分は廃止
	if ($id == 0) {
		$guard = 10000;
		$total_c = 0;
		$ar = $database->sql_select_all("select count(id) as c from ".DBX."data_items where circle=? ", $p_circle);
		if ($ar) {
			$total_c = (int)$ar[0]['c'];
		}

		if ($diff_start) {
			if ($diff_start == 1 && isset($params['circle']['meta']['backup_time'])) {
				$backup_idx['last_time'] = $params['circle']['meta']['backup_time'];
			} else {
				$backup_idx['last_time'] = date("Y-m-d H:i:s", $diff_start);
			}
		}
		while (--$guard >0) {
			if (isset($backup_idx['last_time'])) {
				$ar = $database->sql_select_all("select id,mod_date from ".DBX."data_items where circle=? and {$ut->time_cmp('mod_date','>',"'{$backup_idx['last_time']}'")} order by id limit 1", $p_circle);
				if (!isset($backup_idx['item_c'])) {
					$backup_idx['date'] = $params['now'];
					$backup_idx['item_c'] = 0;
				}

			} else if (isset($backup_idx['last_id']) && $backup_idx['last_id']) {
				$ar = $database->sql_select_all("select id from ".DBX."data_items where circle=? and id > ? order by id limit 1", $p_circle, $backup_idx['last_id']);

			} else {
				$ar = $database->sql_select_all("select id from ".DBX."data_items where circle=? order by id limit 1", $p_circle);
				$backup_idx['date'] = $params['now'];
				$backup_idx['item_c'] = 0;
				$backup_idx['start_id'] = 0;
			}
			if (!$ar || !isset($ar[0])) {
				$done = true;
				$ar2 = $database->sql_select_all("select id,metadata from ".DBX."circles where id=?", $p_circle);
				if ($m = get_circle_meta()) {
					$m['backup_time'] = $backup_idx['date'];
					$backup_idx['circle_meta'] = $m;
					if ($database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($m), $p_circle)) {
					}
				}
				_backup_page_ar(0, $backup_idx, $body);		//link が0 のuser_log を収集
				break;
			}
			if (_backup_page_ar($ar[0]['id'], $backup_idx, $body)) {
				if (isset($backup_idx['last_time'])) {
					$backup_idx['last_time'] = $ar[0]['mod_date'];
					$backup_idx['last_id'] = $ar[0]['id'];
				} else {
					$backup_idx['last_id'] = $ar[0]['id'];
				}
				++$backup_idx['item_c'];
				++$count;
			} else {
				$mess ='save error';
				return false;
			}
			if (strlen($body) > $limit) {
				break;
			}
		}

	} else {
	
		$ar = $database->sql_select_all("select title  from ".DBX."data_items where id=? ", $id);
		if ($ar) {
			$backup_idx['title'] = $ar[0]['title'];
			$backup_idx['date'] = $params['now'];
		}

		$backup_idx['page_id'] = $id;
		$ar = array();
		get_linked_pages($id, $ar);
		
		if (count($ar) > $limt_c) {
			$mess ='Too many backup items , try ext-backup';
			return false;
		}
		
		foreach ($ar as $v) {
			if (_backup_page_ar($v, $backup_idx, $body)) {
				$backup_idx['last_id'] = $v;
			}
		}
		$done = true;

	}

	$ix_file = $path.DIRECTORY_SEPARATOR.$_SESSION['login_id']."index.tmp";
	$px_file = $path.DIRECTORY_SEPARATOR.$_SESSION['login_id']."part.tmp";
	$part_name = "items_{$backup_idx['last_id']}";

	if ($body) {
		if (!@file_put_contents($px_file, $body)) {
			return false;
		}
		$part_ar = array();
		$part_ar['file'] = $part_name;
		$part_ar['count'] = $count;
		$backup_idx['items'][] = $part_ar;
		if ($done) {
			unset($backup_idx['making']);
		}
		if (!@file_put_contents($ix_file, serialize64($backup_idx))) {
			return false;
		}

		if (!is_file($zip_file)) {
			$r = $zip->open($zip_file, ZipArchive::CREATE);
		} else {
			$r = $zip->open($zip_file);
		}
		if ($r === true) {
			if (!$zip->addFile($px_file, $part_name)) {
				$r = false;
			}
			if ($r && !$zip->addFile($ix_file, 'index.dat')) {
				$r = false;
			}
			$zip->close();
			if ($r) {
				@unlink($px_file);
				@unlink($ix_file);
			}
		}
		
		if (!$r || !is_file($zip_file)) {
			$mess ='Zip Archive error';
			return false;
		}
	} else {
		$mess ='no backup data';
		return false;
	}

	if ($done) {
		$mess ='complete';
		return true;
	}

	$progress = " ".(int)(($backup_idx['item_c']*100)/$total_c)."%";
	return 'continue';
}
function snippet_page_backup()
{
	global $html,$database,$params,$config,$plugin_ar,$ut;

	if (check_rights('edit')) {
		if (isset($_POST['ajax']) && $_POST['ajax']=='backup_page') {
			$r = array();
			$id = 0;
			$diff_start = 0;
			if (isset($_POST['id']) && $_POST['id']) {
				$id = (int)$_POST['id'];
			}
			if (isset($_POST['diff_start'])) {
				$diff_start = (int)$_POST['diff_start'];
			}
			$mess = '';
			$progress;
			$r['result'] = backup_page($id, $diff_start, $mess, $progress);
			$r['mess'] = $mess;
			$r['progress'] = $progress;
			echo( json_encode($r) );
			exit();
			
		}
		if (isset($_POST['ajax']) && $_POST['ajax']=='backup_users') {
			$r = array();
			$r['result'] = backup_users();
			echo( json_encode($r) );
			exit();
			
		}
	}
	
$a = <<<EOT
	<script>
		ot.backup_page = function (id,diff_start,continue_mode) {
			if (continue_mode && !ot.backup_page_start) {
				return;
			}
			if (ot.overlay && !continue_mode) {
				ot.overlay(1, "<div style='background:#fff;padding:20px'><img src='{$config['site_url']}img/loading.gif' /><p>progress...<span id='ot_progress' ></span></p><p><input type='button' value='stop' class='onethird-button' onclick='ot.stop_backup()' /></p></div>")
				ot.backup_page_start = true;
			}
			if (\$('#ot_progress').length) {
				if (!ot.backup_page_start) {
					ot.stop_backup();
					return;
				}
			}
			var opt = '';
			if (diff_start) {
				opt = '&diff_start='+diff_start;
			}
			ot.ajax({
				type: "POST"
				, url: '{$params['request_name']}'
				, data: "ajax=backup_page&id="+id+opt
				, dataType:'json'
				, opt_id:id
				, success: function(data){
					if (!this.opt_id && data['result'] == 'continue') {
						\$('#ot_progress').text(data['progress']);
						setTimeout(function(){ot.backup_page(0,0,true)},100);
						return;
					}
					if (ot.overlay) {
						ot.overlay(0);
					}
					if ( data && data['result'] ) {
						if (!ot.overlay) {
							alert('バックアップしました');
						} else {
							location.reload(true);
						}
					} else {
						if (data['mess']) {
							alert(data['mess']);
						} else {
							alert('バックアップできませんでした');
						}
					}
				}
			});
		};
		ot.stop_backup = function () {
			ot.backup_page_start = false;
			ot.overlay(0);
		}
	</script>
EOT;
	$html['meta']['page_backup'] = $a;
}

function create_circle( $user = 0, $circle = 0 )
{
	global $config,$database,$params;
	
	if (!isset($_SESSION['login_id']) || !$_SESSION['login_id']) {
		return false;
	}
	
	if (!check_rights('admin')) {
		return false;
	}

	if (!$user) {
		$user = $config['admin_user'];
	}

	if (!$circle) {
		$name='new site';
		$m = array();
		$m['def_template']['std_link'] = "<a href='{\$ut->link({new_page})}'>link</a>";
		if (!$database->sql_update("insert into ".DBX."circles (name,owner,metadata) values(?,?,?)", $name, $user, serialize64($m))) {
			return false;
		}
		$circle = $database->lastInsertId();
	}

	$url = $config['site_url'].$circle;
	
	$ar = $database->sql_select_all( "select metadata from ".DBX."circles where id=?", $circle );
	if (!$ar) {
		return false;
	}
	$m = unserialize64($ar[0]['metadata']);

	if (!isset($m['top_page'])) {
		//トップページ追加
		$ar = array();
		$ar['type'] = 0;
		$ar['block_type'] = 1;
		$ar['link'] = 0;
		$ar['mode'] = 2;
		$ar['circle'] = $circle;
		if (!add_data_items($ar)) {
			system_error( __FILE__, __LINE__ );
		}
		$p_page = $ar['id'];
		
		//トップページ設定
		$top_meta = array();
		$top_meta['top_page'] = $p_page;
		if (isset($params['login_user']['name'])) {
			$top_meta['owner_name'] = $params['login_user']['name'];
		}
		if ($database->sql_update( "update ".DBX."circles set metadata=? where id=?", serialize64($top_meta), $circle) ) {
		}
	} else {
		$p_page = $m['top_page'];
	}

	join_circle($circle , $user, true, 'admin');		

	$path1 = $config['files_path'].DIRECTORY_SEPARATOR.$circle.DIRECTORY_SEPARATOR;
	$path0 = $config['site_path'].DIRECTORY_SEPARATOR."tpl";

	if (!is_dir($config['files_path'])) {
		mkdir($config['files_path']);
		chmod($config['files_path'], $config['permission']);  
	}

	if (!is_dir($config['files_path'].DIRECTORY_SEPARATOR.$circle)) {
		mkdir($config['files_path'].DIRECTORY_SEPARATOR.$circle);
		chmod($config['files_path'].DIRECTORY_SEPARATOR.$circle, $config['permission']);  
	}

	copy_dir10($path0, $path1, false);
	return $url;
}

function remove_circle()
{
	global $params,$config,$database,$p_circle;
	
	if (!isset($_SESSION['login_id']) || !$_SESSION['login_id'] ) {
		return false;
	}

	if ($params['circle']['owner'] != $_SESSION['login_id'] ) {
		return false;
	}
	
	$database->sql_begin();
	$ok = true;
	if (!$database->sql_update("delete from ".DBX."data_items where circle=?",$p_circle)) {
		$ok = false;
	}
	if (!$database->sql_update("delete from ".DBX."circles where id=?",$p_circle)) {
		$ok = false;
	}
	if (!$database->sql_update("delete from ".DBX."action_log where circle=?",$p_circle)) {
		$ok = false;
	}
	if (!$database->sql_update("delete from ".DBX."joined_circle where circle=?",$p_circle)) {
		$ok = false;
	}
	/*if (!$database->sql_update("delete from ".DBX."message where circle=?",$p_circle)) {
		$ok = false;
	}*/
	if (!$database->sql_update("delete from ".DBX."user_log where circle=?",$p_circle)) {
		$ok = false;
	}
	if (!$ok) {
		$database->sql_rollback();
		return false;
	}

	$database->sql_commit();

	$path=$config['files_path'].DIRECTORY_SEPARATOR.$p_circle;
	remove_directory($path);

	return true;

}

function remove_directory($dir) 
{
	if (!is_dir($dir)) {
		return false;
	}
	if ($handle = opendir("$dir")) {
		while (false !== ($item = readdir($handle))) {
			if ($item != "." && $item != "..") {
				if (is_dir("$dir/$item")) {
					remove_directory("$dir/$item");
				} else {
					@unlink("$dir/$item");
				}
			}
		}
		closedir($handle);
		@rmdir($dir);
	}
	return true;
}

function create_page( &$r )
{
	global $plugin_ar,$params,$database,$config,$ut;
	
	(isset($r['link']))?   $p_link = $r['link'] : $p_link = 0;
	(isset($r['title']))?  $p_title = $r['title'] : $p_title = '';
	(isset($r['type']))?  $r['type'] = (int)$r['type'] : $r['type'] = 1;
	(isset($r['mode']))?   $p_mode = $r['mode'] : $p_mode = '';

	$parent_metadata = array();
	if ($p_link) {
		$sql="select id,title,metadata,link from ".DBX."data_items where id=? ";
		$ar = $database->sql_select_all( $sql, $p_link );
		if ( !$ar ) {
			return false;
		}
		if ( $ar[0]['metadata'] ) {
			$parent_metadata = unserialize64($ar[0]['metadata']);
		}
	}

	//新規ページ作成
	if (isset($r['block_type'])) {
		$p_block_type = $r['block_type'];
	} else {
		$p_block_type = 1;
		if (isset($plugin_ar[$r['type']])) {
			if ($p_link && !empty($plugin_ar[$r['type']]['add_inner'])) {
				$p_block_type = 5;	// インナーページ
			}
		}
	}
	$page_ar = array();
	$page_ar['type'] = $r['type'];
	$page_ar['block_type'] = $p_block_type;
	if (isset($r['contents'])) {
		$page_ar['contents'] = $r['contents'];
	}
	$page_ar['link'] = $p_link;
	if (isset($r['metadata'])) {
		$page_ar['metadata'] = $r['metadata'];
	}
	if (isset($r['meta'])) {
		$page_ar['metadata'] = $r['meta'];
	}
	if (isset($r['tag'])) {
		$page_ar['tag'] = $r['tag'];
	}
	if (isset($r['date'])) {
		$page_ar['date'] = $r['date'];
	}
	$page_ar['title'] = $p_title;
	$page_ar['mode'] = $p_mode;

	if (add_data_items($page_ar)) {
		$r['new_id'] = $r['id'] = $page_ar['id'] = $page_ar['new_id'];
		$r['result'] = true;

		if ($p_block_type == 5 && isset($ar[0]['link'])) {
			//$page_ar['open_url'] = $ut->link($p_link);
		} else {
			$page_ar['open_url'] = $ut->link($r['id']);
		}

		if (isset($params['hook']['after_create']) && is_array($params['hook']['after_create'])) {
			foreach ($params['hook']['after_create'] as $v) {
				if (function_exists($v)) {
					$v($page_ar);
				}
			}
		}
		regenerate_attached($r['id']);
		//regenerate_foldertag($r['id']);

		if ($p_link && $p_block_type != 5) {
			//上位テンプレート引き継ぎ
			$y = array();
			if (isset($parent_metadata) && $parent_metadata) {
				if (!isset($page_ar['meta']['template_ar'])) {
					$page_ar['meta']['template_ar'] = array();
				}
				if (!isset($page_ar['meta']['template_ar']['php'])) {
					$page_ar['meta']['template_ar']['php'] = array();
				}
				if (!is_array($page_ar['meta']['template_ar']['php'])) {
					$page_ar['meta']['template_ar']['php'] = array($page_ar['meta']['template_ar']['php']);
				}
				$parent_template = array();
				if ($r['type'] == PAGE_FOLDER_ID) {
					if (isset($parent_metadata['template_ar'])) { $parent_template = $parent_metadata['template_ar']; }
				} else {
					if (isset($parent_metadata['lwr_template_ar'])) { $parent_template = $parent_metadata['lwr_template_ar']; }
				}
				if (!isset($parent_template['php'])) {
					$parent_template['php'] = array();
				}
				if (!is_array($parent_template['php'])) {
					$parent_template['php'] = array($parent_template['php']);
				}

				$m = $parent_template['php'];
				foreach ($page_ar['meta']['template_ar']['php'] as $v) {
					if (in_array($v, $parent_template['php'])) {
						continue;
					}
					$m[] = $v;
				}
				$page_ar['meta']['template_ar']['php'] = $m;
				if (!count($page_ar['meta']['template_ar']['php'])) { unset($page_ar['meta']['template_ar']['php']); }

				if (isset($parent_template['tpl'])) {
					//親のテンプレートが優先される
					$page_ar['meta']['template_ar']['tpl'] = $parent_template['tpl'];
				}

				if (isset($parent_metadata['lwr_template_ar'])) {
					$page_ar['meta']['lwr_template_ar'] = $parent_metadata['lwr_template_ar'];
				}
				unset($page_ar['metadata']);
				if (mod_data_items($page_ar)) {
				}
			}
			//上位ページのレンダラーキャッシュ更新
			regenerate_attached($p_link);
		}
		create_plugin_page($page_ar);
		if (isset($page_ar['open_url'])) {
			$r['open_url'] = $page_ar['open_url'];
		}
		if (isset($page_ar['callback'])) {
			$r['callback'] = $page_ar['callback'];
		}
		return true;
	}
	return false;
}

function regenerate_foldertag( &$page_ar , $recursion=false )
{
	global $database,$params,$config;
	if (isset($params['folder-systag']) || isset($params['circle']['meta']['folder_systag'])) {
		if (!is_array($page_ar)) {
			$id = $page_ar;
		} else {
			$id = $page_ar['id'];
		}
		$ar = $database->sql_select_all("select tag,link from ".DBX."data_items where id=? ", $id);
		if (!$ar) {
			return;
		}
		$tag = $ar[0]['tag'];
		$link = $ar[0]['link'];
		$folde_tag = "";
		for ($i=0; $i < MAX_PAGE_NEST; ++$i) {
			$ar = $database->sql_select_all("select id,type,link from ".DBX."data_items where id=? ", $link);
			if (!$ar || $params['circle']['meta']['top_page'] == $ar[0]['id']) {
				break;
			}
			$folde_tag = "{$ar[0]['id']}-{$ar[0]['type']}/".$folde_tag;
			$link = $ar[0]['link'];
		}
		$tag = preg_replace('/@dir:.*?,/mu', ',', ','.$tag.',');
		$tag = preg_replace('/,,/mu', ',', $tag);
		$tag = trim($tag,',');
		if ($folde_tag) {
			if ($tag) { $tag .= ","; }
			$tag .= "@dir:$folde_tag,";
		}
		if ($database->sql_update( "update ".DBX."data_items set tag=? where id=? ", $tag, $id )) {
			if (is_array($page_ar)) {
				$page_ar['tag'] = $tag;
			}
		}
		if ($folde_tag && $recursion !== false) {
			$ar = array();
			get_linked_pages( $id, $ar );
			if ($ar) {
				foreach ($ar as $v) {
					regenerate_foldertag($v);
				}
			}
		}
	}
}

function regenerate_attached( $page , $clear = false )
{
	global $database,$params,$config;
	
	$ar = $database->sql_select_all( "select metadata,type from ".DBX."data_items where id=? ", $page );
	if (!$ar) {
		return;
	}
	$type = $ar[0]['type'];
	if (!$ar[0]['metadata']) {
		$page_metadata = array();
	} else {
		$page_metadata = unserialize64($ar[0]['metadata']);
	}
	if (!$clear) {
		if (!isset($page_metadata['renderer'][$page])) {
			if (isset($page_metadata['renderer'])) {
				$m = $page_metadata['renderer'];
				unset($page_metadata['renderer']);
				$page_metadata['renderer'][$page] = $ar[0]['type'];	
				foreach ($m as $k=>$v){	// array_mergeでは添字の扱いの挙動が保証されない
					$page_metadata['renderer'][$k] = $v;
				}
			} else {
				$page_metadata['renderer'][$page] = $ar[0]['type'];	
			}
		}
	} else {
		unset($page_metadata['renderer']);
		$page_metadata['renderer'][$page] = $type;
	}

	// インページページの確認
	unset($page_metadata['renderer']['reference']);
	$ar = $database->sql_select_all( "select id,block_type,type from ".DBX."data_items where link=? and block_type=5 ", $page );
	if ($ar) {
		foreach ($ar as $v) {
			if (!isset($page_metadata['renderer'][$v['id']])) {
				$page_metadata['renderer'][$v['id']] = $v['type'];
			}
		}
		foreach ($page_metadata['renderer'] as $k=>$v) {
			$ok = false;
			foreach ($ar as $v) {
				if ($v['id'] == $k) {
					$ok = true;
				}
			}
			if (!$ok && $k != $page) {
				unset($page_metadata['renderer'][$k]);
			}
		}
	} else {
		unset($page_metadata['renderer']);
		$page_metadata['renderer'][$page] = $type;
	}
	$database->sql_update( "update ".DBX."data_items set metadata=? where id=? ", serialize64($page_metadata), $page );
}

function snippet_std_setting($title, $proc_id)
{
	global $html,$params;

	if (isset($html['meta']["snippet_std_setting_$proc_id"])) {
		return;
	}

$html['meta']["snippet_std_setting_$proc_id"] = <<<EOT
	<script>
	ot.$proc_id = function (id,data) {
		if (!id) {id = 0;}
		\$('#{$proc_id} ').attr('data-idx',id);
		\$('#{$proc_id} ').attr('data-datax',data);
		var opt = "ajax={$proc_id}&id="+id;
		if (data) {
			opt += "&data="+encodeURIComponent(data);
		}
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: opt
			, dataType:'json'
			, success: function(data){
				if ( data && data['html'] ) {
					\$('#{$proc_id} .onethird-setting').html(data['html']);
					ot.open_dialog(\$('#{$proc_id}'));
				}
			}
		});
	};
	ot.save_$proc_id = function () {
		var id = \$('#{$proc_id} ').attr('data-idx');
		var data = \$('#{$proc_id} ').attr('data-datax');
		var opt = '&id='+id;
		if (data) {
			opt += '&data='+data;
		}
		\$('#{$proc_id} ').find('input,select,checkbox,textarea').each(function(){
			var obj = \$(this);
			if (obj.attr('data-input')) {
				if (obj.attr('type') == 'checkbox') {
					if (obj.prop('checked') == true) {
						opt += '&'+obj.attr('data-input')+'=true';
					}
				} else {
					opt += '&'+obj.attr('data-input')+'=';
					opt += encodeURIComponent(\$(this).val());
				}
			}
		});
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=save_{$proc_id}"+opt
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					location.reload();
				}
			}
		});
	};
	</script>
	<div id="{$proc_id}" class='onethird-dialog' >
		<p class='title'>$title</p>
		<div class='onethird-setting'>
		</div>
		<div class='actions' >
			<input type='button' class='onethird-button' value='更新' onclick='ot.save_{$proc_id}()' />
			<input type='button' class='onethird-button' value='Close' onclick='ot.close_dialog(this)' />
		</div>
	</div>
EOT;
}

function after_update_page_property($page)
{
	global $params;
	if (isset($params['hook']['after_update_page_property']) && is_array($params['hook']['after_update_page_property'])) {
		if (read_pagedata($page, $x)) {
			foreach ($params['hook']['after_update_page_property'] as $v) {
				if (function_exists($v)) {
					if ($v($x) === false) {
						return false;
					}
				}
			}
		}
	}
}

function snippet_page_property()
{
	global $html,$database,$params,$config,$p_circle,$plugin_ar,$ut;
	
	//２重書き込み禁止
	if (isset($html['meta']['page_property'])) {
		return;
	}

	if (!check_func_rights('page_property')) {
		return false;
	}

	snippet_dialog();
	snippet_image_uploader();
	
	//ページ詳細-編集
	$buff ='';

$buff .= <<<EOT
	<script>
	\$(function(){
		\$(document).on('click','.onethird-setting .settinglist_sel span',function(event){
			var o = \$(this).parent();
			var sel = o.attr('data-sel');
			var id = o.attr('data-id');
			var tag = \$('#'+id);
			var a = tag.val();
			var x = \$(this);
			var t = x.attr('data-x');
			if (!t) {
				t = x.text();
			}
			if (x.hasClass('selected')) {
				a = (","+a+",").replace(new RegExp(","+t+",", "g"), ',');
			} else {
				a = t+','+a;
			}
			a = a.replace(/,,+/g, ',');
			a = a.replace(/(^,|,\$)/gm, ',');
			a = a.replace(/^[ ,]+|[ ,]+\$/g,'');
			tag.val(a);
			ot.settinglist_sel(sel,id);
		});
	});
	
	ot.page_setting = function (page_id) {
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=init_page_setting&page="+page_id
			, dataType:'json'
			, success: function(data){
				if (!data['html']) {
					alert('system error');
				} else {
					ot.open_dialog(\$("#dialog_page_seting").html(data['html']).width(510));
				}
			}
			, error: function(data){
				if (data.responseText) {
					x = data.responseText.split("\\n");
					if (x[0]) {
						data = JSON.parse(x[0]);
						if (!data['html']) {
							alert('system error');
						} else {
							ot.open_dialog(\$("#dialog_page_seting").html(data['html']).width(510));
						}
					}
				}
			}
		});
	};
	
	ot.mod_page_setting = function (page_id) {
		var option='';
		option += "&p_keyword="+encodeURI($('#p_keyword').val());
		option += "&p_description="+encodeURI($('#p_description').val());
		option += "&p_tag="+encodeURIComponent($('#p_tag').val());
		option += "&p_sys_tag="+encodeURIComponent($('#p_sys_tag').val());
		option += "&p_template="+encodeURI($('#p_template').val());
		option += "&p_lwr_template="+encodeURI($('#p_lwr_template').val());
		option += "&p_title="+encodeURI($('#p_mod_title').val());
		option += "&p_block_type="+encodeURI($('#p_block_type').val());
		option += "&p_alias="+encodeURI($('#p_alias').val());
		if (\$('#p_hide_title:checked').length) {
	    	option += "&p_hide_title=true";
		}
		if (\$('#p_dis_mod_date:checked').length) {
	    	option += "&p_dis_mod_date=true";
		}
		if (\$('#p_public:checked').length && !$('#p_public:checked').attr('disabled')) {
	    	option += "&p_public=true";
		}
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=mod_page_setting&page="+page_id+option
			, dataType:'json'
			, success: function(data){
				if (data && data['result'] ) {
					location.reload(true);
				} else {
					var m = 'update failed.';
					if (data['error']) { m = data['error']; }
					alert(m);
				}
			}
		});
	};
	ot.remove_page = function (page_id) {
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=check_lwr_page&page="+page_id
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					if ( !confirm("合計 "+data['count']+" ページが削除されます \\n実行しますか?") ) {
						return;
					}
					ot.ajax({
						type: "POST"
						, url: '{$params['request_name']}'
						, data: "ajax=remove_page&page="+page_id
						, dataType:'json'
						, success: function(data){
							if ( data && data['result'] ) {
								if (data['parent']) {
									location.href=data['parent'];
								} else {
									location.reload(true);
								}
							} else {
								alert('Error');
							}
						}
					});
				} else {
					alert('Error');
				}
			}
		});
	};

	ot.remove_attached = function (page_id,type) {
		if ( !confirm("消去しますか?") ) {
			return;
		}
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=remove_attached&page="+page_id+"&type="+type
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					location.reload(true);
				} else {
					alert('delete error');
				}
			}
		});
	};

	ot.public_page = function (page_id) {
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=public_page&page="+page_id
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					location.reload(true);
				}
			}
		});
	};
	
	ot.select_ogimage = function (page_id, opt) {
		opt = opt || {};
		if (typeof(page_id)=='object') {
			opt = page_id;
		} else {
			if (page_id) {opt.id = page_id;}
		}
		opt.thumb = true;
		opt.title = opt.title || 'OGP/Thumbnail image';
		opt.resize = opt.resize || 'auto,320/240,640/480/,1200/630/1';
		opt.select = function(obj){ot.set_ogimage(obj,'auto');}
		ot.open_uploader(opt);
	};
	
	ot.settinglist_sel = function(sel,id) {
		var str = \$('#'+id).val();
		var str = str.replace(/(,| |、|，|　)+/g,',');
		\$('#'+id).val(str);
		var ar = str.split(',');
		\$('.onethird-setting .'+sel+' span').each(function() {
			var oj = \$(this);
			var s = false;
			var k =oj.attr('data-x');
			if (!k) {
				 k = oj.text();
			}
			for (var i=0; i<ar.length; ++i) {
				if (k == ar[i]) {s = true;}
			}
			if (s) {
				oj.addClass('selected');
			} else {
				oj.removeClass('selected');
			}
		});
	};
	ot.settinglist_hnd = function(sel,id) {
		var obj = \$('.onethird-setting .'+sel);
		if (obj.css('display')=='none'){
			obj.fadeIn();
			ot.settinglist_sel(sel,id);
		} else {
			obj.fadeOut();
		}
	};
	
	</script>
	
	<div id="dialog_page_seting" class="onethird-dialog " >
	</div>
EOT;

	if (isset($_POST['ajax'])) {
		if ($_POST['ajax'] == 'init_page_setting')  {
			$r = array();
			$r['result'] = true;
			$r['id'] = $p_page = (int)$_POST['page'];

			if (!read_pagedata($p_page, $params['page'])) {
				$r['page'] = $p_page;
				$r['result'] = false;
				echo( json_encode($r) );
				exit();
			}

			$page_metadata = &$params['page']['meta'];
			$p_block_type = $params['page']['block_type'];
			$r['block_type'] = $params['page']['block_type'];

			$r['title'] = $params['page']['title'];

			$p_date = $params['page']['date'];

			$r['tag'] = implode(',',get_tags($params['page']['tag']));
			$r['sys_tag'] = implode(',',get_systags($params['page']['tag']));
			
			$r['mode'] = $params['page']['mode'];
			$p_mode = $params['page']['mode'];

			$r['description'] = '';
			if (isset($page_metadata['description'])){
				$r['description'] = $page_metadata['description'];
			}
			$r['template'] = '';
			if (isset($page_metadata['template_ar'])){
				foreach ($page_metadata['template_ar'] as $v) {
					if ($r['template']) { $r['template'] .= ','; }
					if (is_array($v)) {
						$r['template'] = implode (',', $v);
					} else {
						$r['template'] .= $v;
					}
				}
			}
			$r['lwr_template'] = '';
			if (isset($page_metadata['lwr_template_ar'])){
				foreach ($page_metadata['lwr_template_ar'] as $v) {
					if ($r['lwr_template']) { $r['lwr_template'] .= ','; }
					if (is_array($v)) {
						$r['lwr_template'] = implode (',', $v);
					} else {
						$r['lwr_template'] .= $v;
					}
				}
			}
			$r['alias'] = '';
			make_f_alias();
			if (isset($params['circle']['meta']['f_alias'][$p_page])){
				$r['alias'] = $params['circle']['meta']['f_alias'][$p_page];
			}
			$r['type_str'] = "#{$r['id']} ";
			if (isset($plugin_ar[$params['page']['type']])) {
				$r['type_str'] .= ' '.$plugin_ar[$params['page']['type']]['title']." ({$params['page']['type']})";
			} else if (isset($params['circle']['meta']['top_page']) && isset($params['top_page'])) {
				$r['type_str'] .= ' Top Page';
			} else {
				$r['type_str'] .= ' page ';
			}
			$rem = '';
			if ($params['page']['block_type'] >= 20) {
				$rem = ' *** admin only *** ';
			} else if ($params['page']['block_type'] >= 15) {
				$rem = ' *** edit user only *** ';
			}
			if ($rem) {
$rem = <<<EOT
				<tr>
					<td></td>
					<td><b style='color:red'>$rem</b></td>
				</tr>
EOT;
			}

			if (!empty($page_metadata['author'])) {
				$r['type_str'] .= " ({$page_metadata['author']})";
			}

			if (!empty($page_metadata['hide-title'])) {
				$r['hide-title'] = true;
			}

$buff = <<<EOT
			<p class='title'>プロパティ <span id='p_page_id'></span></p>
			<div class='onethird-setting'>
				<table>
					$rem
					<tr>
						<td>タイトル</td>
						<td style='width:300px'>
							<input type='text' id='p_mod_title' value='{$r['title']}' />
						</td>
					</tr>
					<tr>
						<td>ID / type </td>
						<td>
							<input type='text' value='{$r['type_str']}' readonly />
						</td>
					</tr>
					<tr>
						<td>Description</td>
						<td>
							<input type='text' id='p_description' value='{$r['description']}' />
						</td>
					</tr>
					<tr>
						<td>Tag 
EOT;
							if (isset($params['circle']['meta']['taglist']) && $params['circle']['meta']['taglist']) {
$buff .=  <<<EOT
								<a href="javascript:void(ot.settinglist_hnd('taglist_sel','p_tag'))">{$ut->icon('edit')}</a>
EOT;
							}
$buff .=  <<<EOT
						</td>
						<td>
							<input type='text' id='p_tag' value='{$r['tag']}' /> 
EOT;
							if (isset($params['circle']['meta']['taglist'])) {
								$ar = trim($params['circle']['meta']['taglist'], ',');
								if ($ar && ($ar = explode(',', $ar))) {
$buff .=  <<<EOT
									<div class='taglist_sel settinglist_sel' data-sel='taglist_sel' data-id='p_tag' style='display:none;' >
EOT;
										foreach($ar as $v) {
											$buff .= "<span>$v</span>";
										}
$buff .=  <<<EOT
									</div>
EOT;
								}
							}
$buff .=  <<<EOT
						</td>
					</tr>
EOT;
					if (check_rights('admin') && !empty($r['sys_tag'])) {
$buff .=  <<<EOT
						<tr>
							<td>System Tag</td>
							<td>
								<input type='text' id='p_sys_tag' disabled value='{$r['sys_tag']}' />
							</td>
						</tr>
EOT;
					}
$buff .=  <<<EOT
EOT;
					if (check_rights('admin')) {
						if (!function_exists('_tpl_list')) {
							function _tpl_list(&$ar,&$tpl_list_ar,$path,$pre) {
								foreach ($ar as $v) {
									if (addslashes($v) != $v) { continue; }
									if (substr($v,-4) == '.php' || substr($v,-4) == '.tpl') {
										$n = false;
										$hnd = @fopen($path.DIRECTORY_SEPARATOR.$v, "r");
										if ($hnd) {
											for ($i=0; $i < 5;++$i) {
												$ln = fgets($hnd, 100);
												if ( $ln === false) { break; }
												if (preg_match("/[ \\t]name[ :]*(.*)[^ ]*$/mu", $ln, $m)) {
													if (isset($m[1])) {
														$n = $m[1];
													}
													break;
												}
											}
											fclose($hnd);
										}
										if ($n) {
											$tpl_list_ar[$pre.$v] = $n;
										}
									}
								}
							}
						}
						$tpl_list_ar = array();
						$path = $config["files_path"].DIRECTORY_SEPARATOR.$p_circle.DIRECTORY_SEPARATOR.'data';
						$ar = @scandir($path);
						_tpl_list($ar, $tpl_list_ar, $path,'');

						$path = $config["files_path"].DIRECTORY_SEPARATOR.$p_circle.DIRECTORY_SEPARATOR.'plugin';
						$ar = @scandir($path);
						_tpl_list($ar, $tpl_list_ar, $path,'.');
						
						$tpl_list = '';
						foreach ($tpl_list_ar as $k=>$v) {
							$tpl_list .= "<span data-x='$k'";
							if (substr($k,-4)=='.tpl') {
								$tpl_list .= " style='border-style:dotted' ";
							}
							$tpl_list .= ">".$v."</span>";
						}
$buff .=  <<<EOT
						<tr>
							<td>Template,module
EOT;
								if ($tpl_list) {
$buff .=  <<<EOT
									<a href="javascript:void(ot.settinglist_hnd('templatelist_sel','p_template'))">{$ut->icon('edit')}</a>
EOT;
								}
$buff .=  <<<EOT
							</td>
							<td>
								<input type='text' id='p_template' value='{$r['template']}' />
								<div class='templatelist_sel settinglist_sel' data-sel='templatelist_sel' data-id='p_template' style='display:none;' >
									{$tpl_list}
								</div>
							</td>
						</tr>
						<tr>
							<td>Lower template
EOT;
								if ($tpl_list) {
$buff .=  <<<EOT
									<a href="javascript:void(ot.settinglist_hnd('lwr_templatelist_sel','p_lwr_template'))">{$ut->icon('edit')}</a>
EOT;
								}
$buff .=  <<<EOT
							</td>
							<td>
								<input type='text' id='p_lwr_template' value='{$r['lwr_template']}'  />
								<div class='lwr_templatelist_sel settinglist_sel' data-sel='lwr_templatelist_sel' data-id='p_lwr_template' style='display:none;' >
									{$tpl_list}
								</div>
							</td>
							</td>
						</tr>
EOT;
					}
$buff .=  <<<EOT
					<tr>
						<td>Alias</td>
						<td>
							<input type='text' id='p_alias' value = '{$r['alias']}' />
						</td>
					</tr>
					<tr>
EOT;

						if ($r['mode'] == 0) {
							$opt = '';
						} else if ($r['mode'] == 1) {
							$opt = ' checked ';
						} else {
							$opt = ' checked disabled ';
						}

						if ($r['mode'] == 2 || !empty($params['hide-title'])) {
							$hide_title = '';
						} else {
							if (empty($r['hide-title'])) {
								$opt2 = '';
							} else {
								$opt2 = ' checked ';
							}
							$hide_title = "<li ><label><input type='checkbox' id='p_hide_title' $opt2 />タイトルを表示しない</label></li>";
						}

$buff .=  <<<EOT
						<td>Option</td>
						<td>
							<ul>
								$hide_title
								<li ><label><input type='checkbox' id='p_dis_mod_date' checked />作成日時を書き換えない</label></li>
								<li ><label><input type='checkbox' id='p_public' $opt />公開する</label></li>
							</ul>
						</td>
					</tr>
				</table>
				<div class='actions'>
					<input type='button' class='onethird-button default' value='更新' onclick='ot.mod_page_setting({$r['id']})' />
					<input type='button' class='onethird-button' value='削除' onclick="ot.remove_page({$r['id']})" {$ut->check($ut->is_home()," disabled ")} />
					<input type='button' class='onethird-button' value='Backup' onclick="ot.backup_page({$r['id']})" {$ut->check($ut->is_home()," disabled ")} />
					<input type='button' class='onethird-button' onclick='ot.close_dialog(this)' value='キャンセル' />
				</div>
			</div>
EOT;
			$r['html'] = $buff;
			echo( json_encode($r) );
			exit();
		}
		
		if ($_POST['ajax'] == 'mod_page_setting') {

			$r=array('result'=>false, 'result_date'=>true);
			$p_page = (int)$_POST['page'];
			//$p_keyword = sanitize_str($_POST['p_keyword']);
			$p_description = sanitize_str($_POST['p_description']);

			$p_title = sanitize_post($_POST['p_title']);
			$p_alias = sanitize_str($_POST['p_alias']);
			
			if (isset($params['circle']['meta']['alias'][$p_alias]) && $params['circle']['meta']['alias'][$p_alias] != $p_page) {
				$r['result'] = false;
				$r['error'] = 'alias-name error';
				echo( json_encode($r) );
				exit();
			}

			$p_tag = sanitize_str($_POST['p_tag']);
			if ($p_tag) {
				//通常TAGは#で始まる
				$p_tag = preg_replace('/(,|、|　|，| @)+/mu', ',', $p_tag);
				$p_tag = trim($p_tag,', ');
				$p_tag = '#'.preg_replace('/,/mu', ',#', $p_tag).',';
			}
			//SYSTEM TAGは@で始まる
			$sys_tag_ar = get_systags($params['page']['tag']);	// POSTデータは使わない
			$p_tag = trim($p_tag,', ').',';
			foreach ($sys_tag_ar as $v) {
				$p_tag .= '@'.$v.',';
			}
			$p_tag = trim($p_tag,', ');

			$d = $params['now'];
			$sql="select id,title,metadata,mode,link,user from ".DBX."data_items where id=? ";
			$ar = $database->sql_select_all( $sql, $p_page );
			if ( !$ar ) {
				echo( json_encode($r) );
				exit();
			}
			if ( $ar[0]['metadata'] ) {
				$page_metadata = unserialize64($ar[0]['metadata']);
			} else {
				$page_metadata = array();
			}
			//$p_title = $ar[0]['title'];
			$p_user = $ar[0]['user'];
			$p_mode = $ar[0]['mode'];
			$p_link = $ar[0]['link'];
			$p_date = false;

			unset($page_metadata['keyword']);	//keywordはGoogle的に不要
			$page_metadata['description']=$p_description;

			if (check_rights('edit')) {
				unset($page_metadata['template_ar']);
				unset($page_metadata['lwr_template_ar']);
				if (isset($_POST['p_template'])) {
					$ar = explode(',', sanitize_str($_POST['p_template']));
					foreach ($ar as $v) {
						$path = $params['files_path'].DIRECTORY_SEPARATOR;
						if (substr($v,0,1) == '.') {
							$file = $path.'plugin'.DIRECTORY_SEPARATOR.substr($v,1);
						} else {
							$file = $path.'data'.DIRECTORY_SEPARATOR.$v;
						}
						$file = realpath($file);
						if (is_file($file)) {
							$path_parts = pathinfo($v);
							$ext = strtolower($path_parts['extension']);
							$v = '';
							if (substr($file,0,strlen($path.'data')) == $path.'data') {
								$v = substr($file,strlen($path.'data')+1);
							} else if (substr($file,0,strlen($path.'plugin')) == $path.'plugin') {
								$v = '.'.substr($file,strlen($path.'plugin')+1);
							}
							$v = rtrim($v,"\r\n/. ");
							if ($ext=='php' && isset($page_metadata['template_ar'][$ext])) {
								$php = $page_metadata['template_ar'][$ext];
								if (!is_array($php)) {
									$page_metadata['template_ar'][$ext] = array($php);
								}
								$page_metadata['template_ar'][$ext][] = $v;
							} else {
								$page_metadata['template_ar'][$ext] = $v;
							}
						}
					}
				}
				if (isset($_POST['p_lwr_template'])) {
					$ar = explode(',', sanitize_str($_POST['p_lwr_template']));
					foreach ($ar as $v) {
						$path = $params['files_path'].DIRECTORY_SEPARATOR;
						if (substr($v,0,1) == '.') {
							$file = $path.'plugin'.DIRECTORY_SEPARATOR.substr($v,1);
						} else {
							$file = $path.'data'.DIRECTORY_SEPARATOR.$v;
						}
						if ( is_file($file) ) {
							$path_parts = pathinfo($file);
							$ext = strtolower($path_parts['extension']);
							if (substr($v,0,1) == '.') {
								$v = '.'.ltrim($path_parts['basename'],"\r\n/. ");
							} else {
								$v = ltrim($path_parts['basename'],"\r\n/. ");
							}
							$v = rtrim($v);
							if ($ext=='php' && isset($page_metadata['lwr_template_ar'][$ext])) {
								$php = $page_metadata['lwr_template_ar'][$ext];
								if (!is_array($php)) {
									$page_metadata['lwr_template_ar'][$ext] = array($php);
								}
								$page_metadata['lwr_template_ar'][$ext][] = $v;
							} else {
								$page_metadata['lwr_template_ar'][$ext] = $v;
							}
						}
					}
				}
			}
			if (isset($_POST['p_hide_title'])) {
				$page_metadata['hide-title'] = true;
			} else {
				unset($page_metadata['hide-title']);
			}
			$p_mode_old = $p_mode;
			if ($p_mode < 2) {
				if (isset($_POST['p_public'])) {
					if ( $p_mode != 1 ) {
						$p_mode = 1;
						unset($_POST['p_dis_mod_date']);	//公開時は日付を書き換え
					}
				} else {
					$p_mode = 0;
				}
			}
			if ($p_user != $_SESSION['login_id']) {	//変更した時、権限を移譲
				$p_user = $_SESSION['login_id'];
				add_actionlog("change owner : {$params['login_user']['nickname']} : {$_SESSION['login_id']} : {$_SERVER['REMOTE_ADDR']} : {$ut->link($p_page)}");
			}
			if (!isset($_POST['p_dis_mod_date'])) {
				$d = $params['now'];
				if ( !$database->sql_update("update ".DBX."data_items set date=? where id=?", $d, $p_page) ) {
					$r['result_date'] = false;
				}
			}
			if ($database->sql_update("update ".DBX."data_items set title=?,metadata=?,tag=?,mode=?,mod_date=?,user=? where id=?",$p_title, serialize64($page_metadata),$p_tag,$p_mode,$params['now'],$p_user,$p_page)) {
				$r['result'] = true;
			}
			regenerate_attached($p_page);	//下位リンクデータの再構築
			if ($p_link) {
				regenerate_attached($p_link);	//上位リンクデータの再構築
				regenerate_foldertag( $p_page );
			}
			$r['result'] &= $r['result_date'];

			// エイリアス設定
			$params['circle']['meta'] = get_circle_meta();
			$alias_ar = array();
			if (isset($params['circle']['meta']['alias'])) {
				foreach ($params['circle']['meta']['alias'] as $k=>$v) {
					if ($v != $p_page) {
						$alias_ar[$k] = $v;
					}
				}
			}
			if ($p_alias) {
				$alias_ar[$p_alias] = $p_page;
			}
			$params['circle']['meta']['alias'] = $alias_ar;
			if (!$database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($params['circle']['meta']), $p_circle)) {
				$r['result'] = false;
			}
			after_update_page_property($p_page);
			echo( json_encode($r) );
			exit();
		}


		if ($_POST['ajax'] == 'check_lwr_page')  {
			$r = array();
			$r['result'] = true;
			$ar = array();
			get_linked_pages( (int)$_POST['page'], $ar );
			$r['count'] = count($ar);
			echo( json_encode($r) );
			exit();
		}
		if ($_POST['ajax'] == 'remove_page')  {
			$r = array();
			$r['result'] = false;
			$p_page = (int)$_POST['page'];
			$r['page'] = $p_page;
			$r['block_type'] = 0;
			$ar = $database->sql_select_all( "select user,block_type from ".DBX."data_items where id=? ", $p_page );
			if ($ar) {
				$r['block_type'] = $ar[0]['block_type'];
			}
			if ($ar[0]['user'] == $_SESSION['login_id']) {
				set_func_rights('remove_pages');		// 編集メンバーでなくてもオーナーだったら消せる
			}
			$r['parent'] = remove_this_page($p_page);
			if ( $r['parent'] !== false ) {
				$r['parent'] = $ut->link($r['parent']);
				$r['result'] = true;
				if ($r['block_type'] == 5 || (isset($params['page']['id']) && $p_page!=$params['page']['id'])) {
					unset($r['parent']);
				}
			}
			repair_alias();
			repair_system_menus();
			echo( json_encode($r) );
			exit();
		}

		if ($_POST['ajax'] == 'remove_attached')  {
			$r = array();
			$p_page = (int)$_POST['page'];
			$p_type = (int)$_POST['type'];
			$ar = array();
			read_pagedata($p_page, $ar);
			$ar2 = array('id'=>$ar['id'], 'type'=>$p_type, 'user'=>$ar['user'], 'attached'=>true, 'meta'=>&$ar['meta'] );
			if (event_plugin_page('onbefore_remove', $ar2) === false) {
				echo( json_encode($r) );
				exit();
			}
			if (isset($ar['meta']['attached_plugin'])) {
				$x = $ar['meta']['attached_plugin'];
				$ar['meta']['attached_plugin'] = array();
				foreach ($x as $v) {
					if (isset($v['type']) && $v['type'] != $p_type) {
						$ar['meta']['attached_plugin'][] = $v;
					}
				}
				$x = array();
				$x['id'] = $p_page;
				$x['meta']['attached_plugin'] = $ar['meta']['attached_plugin'];
				if (mod_data_items($x)) {
					$r['result'] = true;
				}
			}
			echo( json_encode($r) );
			exit();
		}

		if ( $_POST['ajax'] == 'public_page' )  {
			$r = array();
			$r['result'] = false;
			if (isset($_POST['page'])) {
				$r['id'] = (int)$_POST['page'];
				$ar = $database->sql_select_all("select user from ".DBX."data_items where id=? ", $r['id'] );
				if ($ar && $ar[0]['user'] == $_SESSION['login_id']) {
					$r['mode'] = 1;
					if ($r['result'] = mod_data_items($r)) {
						after_update_page_property($r['id']);
					}
				}
			}
			echo( json_encode($r) );
			exit();
		}
		
	}

	if (!check_func_rights('add_page')) {
		$html['meta']['page_property'] = $buff;
		return false;
	}

	//ページ追加-編集
$buff .= <<<EOT
	<script>
	ot.make_page = function () {
		var page_id = \$("#dialog_add_page").attr('data-page_id');
		var mess = \$('#page_type option:selected').attr('data-confirm');
		if ( mess ) {
			if ( !confirm(mess) ) {
				return;
			}
		}
		var type = parseInt(\$('#dialog_add_page #page_type').val());
		if (!type) {
			type = 1;
		}
		var title = \$('#dialog_add_page #p_title').val();
		var alias = \$('#dialog_add_page #p_alias_setting').val();
		var opt = "ajax=add_page";
		if ( title ) {
			opt += "&title="+encodeURI(title);
		}
		if ( alias ) {
			opt += "&alias="+encodeURI(alias);
		}
		if (!\$('#dialog_add_page').attr('child')) {
			opt += "&block_type=5";
		}
		opt += "&type="+type+"&page="+page_id;
		if (\$("#dialog_add_page [name=p_acc_ctr]:checked").val()) {
			opt += "&acc_ctr=1";
		}
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: opt
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					ot.close_dialog(this);
					if ( data['open_url'] ) {
						location.href = data['open_url'];
					} else {
						location.reload(true);
					}
				} else {
					var m = 'ページを作成できませんでした'
					if (data['error']) { m = data['error']; }
					alert(m);
				}
			}
		});
	};
	ot.add_page = function(page_id, child) {
		if (!page_id || child) {
			\$('#dialog_add_page .inner-page').hide();
			\$('#dialog_add_page .child-page').show();
			if (!page_id) {
				\$('#op_name').text('ページ追加');
			} else {
				\$('#op_name').text('下層ページ追加');
			}
			\$('#dialog_add_page').attr('child',child);
			\$('#dialog_add_page #p_title').parent().parent().show();
			\$('#dialog_add_page #p_alias_setting').parent().parent().show();
			\$('#dialog_add_page #basic_acc').show();
			
		} else {
			\$('.child-page').hide();
			\$('.inner-page').show();
			\$('#op_name').text('ブロック追加');
			\$('#dialog_add_page #p_title').parent().parent().hide();
			\$('#dialog_add_page #p_alias_setting').parent().parent().hide();
			\$('#dialog_add_page #basic_acc').hide();
		}
		\$('#page_type').val(1);
		ot.open_dialog(\$("#dialog_add_page").attr('data-page_id',page_id));
	};
	ot.move_innerpage = function(a,ofs) {
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=move_innerpage&id="+a+"&ofs="+ofs
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					location.reload(true);
				}
			}
		});
	};
	</script>
	<div id="dialog_add_page" class="onethird-dialog" style='width:500px'>
		<div class='title' id='op_name'></div>
		<div class='onethird-setting'>
			<form method='post' action='{$params['safe_request']}' >
				<table>
					<tr>
						<td>タイトル</td>
						<td>
							<input type='text' id='p_title' />
						</td>
					</tr>
					<tr>
						<td>Alias</td>
						<td>
							<input type='text' id='p_alias_setting' />
						</td>
					</tr>
					<tr>
						<td>Type</td> 
						<td> 
							<select id='page_type' >
								<option value='1' class='child-page' >通常ページ</option>
								<option value='5' class='inner-page' >インナーページ</option>
EOT;
								foreach( $plugin_ar as $k=>$v ) {
									if (isset($v['title'])) {
										if ( !isset($v['confirm']) ) {
											$v['confirm']='';
										}
										$c = '';
										if ( !empty($v['add_inner']) || isset($v['add_attached'])) { 
											$c .= " inner-page ";
										}
										if (isset($v['add_page'])) { 
											$c .= " child-page ";
										}
										if ($c) {
											$buff .= "<option value='$k' data-confirm='{$v['confirm']}' ";
											$buff .= " class='$c' >{$v['title']} ({$k})";
											$buff .= "</option>";
										}
									}
								}
$buff .= <<<EOT
							</select>
						</td> 
					</tr>
EOT;
					if (check_rights('admin')) {
$buff .= <<<EOT
						<tr id='basic_acc' >
							<td>Option</td> 
							<td> 
								<ul>
									<li><label><input type='checkbox' name='p_acc_ctr' id='p_acc_ctr' >Control panel</label></li>
								</ul>
							</td> 
						</tr>
EOT;
					}
$buff .= <<<EOT
				</table>
			</form>
			<div class='actions '>
				<input type='button' value='作成' class='onethird-button default' onclick='ot.make_page()'  />
				<input type='button' value='キャンセル' class='onethird-button ' onclick='ot.close_dialog()'  />
			</div>
		</div>
	</div>
EOT;

	if ( isset($_POST['ajax']) ) {
		if ( $_POST['ajax'] == 'move_innerpage' )  {
			$r = array();
			$r['result'] = false;
			if ( !isset($_POST['id']) ) {
				echo( json_encode($r) );
				exit();
			}
			$id = (int)$_POST['id'];
			$ofs = 0;
			if ( isset($_POST['ofs']) ) {
				$ofs = (int)$_POST['ofs'];
			}
			$r['result'] = move_attached( $id, $ofs, $r );
			echo( json_encode($r) );
			exit();
		}
		if ( $_POST['ajax'] == 'add_page')  {
			$r = array();
			$r['result'] = false;
			$r['link'] = (int)$_POST['page'];
			$alias = '';
			if (isset($_POST['alias'])) {
				$alias = sanitize_str($_POST['alias']);
				if (isset($params['circle']['meta']['alias'][$alias])) {
					$r['error'] = 'alias-name error';
					echo( json_encode($r) );
					exit();
				}
			}
			$r['result'] = false;
			if ( isset($_POST['title']) ) {
				$r['title'] = sanitize_str($_POST['title']);
			} else {
				$r['title'] = '';
			}
			if ( isset($_POST['type']) ) {
				$r['type'] = (int)($_POST['type']);
			} else {
				$r['type'] = 1;	// 通常ページ
			}
			if ( isset($_POST['block_type']) ) {
				$r['block_type'] = (int)($_POST['block_type']);
			} else {
				$r['block_type'] = 1;
			}
			if ( isset($_POST['acc_ctr']) ) {
				//コンパネページ
				if ((int)$_POST['acc_ctr'] == 1) {
					if (!$r['title']) {
						$r['error'] = 'page title error';
						echo( json_encode($r) );
						exit();
					}
					$r['block_type'] = 20;
				}
			}
			if (isset($plugin_ar[$r['type']]['add_page']) && !$r['title']) {
				$r['title'] = 'no title';	// ページ型プラグイはタイトルは必須
			} 
			if ($r['type'] && isset($plugin_ar[$r['type']]['add_attached'])) {
				$x = array();
				$x['id'] = $r['link'];
				if (isset($params['page']['meta']['attached_plugin'])) {
					$x['meta']['attached_plugin'] = $params['page']['meta']['attached_plugin'];
				} else {
					$x['meta']['attached_plugin'] = array();
				}
				$x['meta']['attached_plugin'][] = array('type'=>$r['type']);
				if (mod_data_items($x)) {	// attached plugin
					$r['result'] = true;
				}
			} else {
				create_page( $r );
				if ($r['id'] && $alias) {
					//エイリアス書き換え
					if ($params['circle']['meta'] = get_circle_meta()) {
						$params['circle']['meta']['alias'][$alias] = $r['id'];
						if ($database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($params['circle']['meta']), $p_circle)) {
						}
					}
				}
				//コンパネオプション
				if ( isset($_POST['acc_ctr']) ) {
					if ((int)$_POST['acc_ctr'] == 1) {
						$m = get_circle_meta('system_menus');
						//メニュー追加
						$r2 = array();
						$m = get_circle_meta('system_menus');
						$r2['title'] = $r['title'];
						$r2['rights'] = 'edit';
						$m["M{$r['id']}"] = array('title'=>"[{$r2['title']}]", 'rights'=>'admin', 'url'=>$r['id']);
						set_circle_meta('system_menus',$m);
					}
				}
			}
			echo( json_encode($r) );
			exit();
		}
	}

	$html['meta']['page_property'] = $buff;

}

function move_attached( $move_id, $ofs, &$r )
{
	global $database,$params,$config;
	
	if (!check_func_rights('move_attached')) {
		return false;
	}

	$r['move_id']=$move_id;
	$ar = $database->sql_select_all( "select link,block_type from ".DBX."data_items where id=? ", $move_id );
	if (!$ar) {
		return false;
	}
	if ($ar[0]['block_type'] == 5) {
		$page = $ar[0]['link'];
	} else {
		$page = $move_id;
	}
	$r['block_type'] = $ar[0]['block_type'];
	$r['page'] = $page;
	$ar = $database->sql_select_all( "select metadata from ".DBX."data_items where id=? ", $page );
	if (!$ar) {
		return false;
	}
	if ($ar[0]['metadata']) {
		$page_metadata = unserialize64($ar[0]['metadata']);
		$m = $page_metadata['renderer'];
		$r['m'] = $m;
		unset($page_metadata['renderer']);
		if ($ofs == 0) {
			// page top
			$page_metadata['renderer'][$move_id] = true;
			foreach ($m as $k=>$v){
				$page_metadata['renderer'][$k] = $v;
			}
		} else if ($ofs < 0) {
			// 一つ上に
			$i = 0;
			foreach ($m as $k=>$v){
				if ($k == $move_id) { break; }
				++$i;
			}
			foreach ($m as $k=>$v){
				--$i;
				if ($i == 0) {
					$page_metadata['renderer'][$move_id] = true;
				}
				$page_metadata['renderer'][$k] = $v;
			}
		} else {
			// 一つ下に
			$i = 0;
			foreach ($m as $k=>$v){
				++$i;
				if ($k == $move_id) { break; }
			}
			unset($m[$move_id]);
			foreach ($m as $k=>$v){
				$page_metadata['renderer'][$k] = $v;
				--$i;
				if ($i == 0) {
					$page_metadata['renderer'][$move_id] = true;
				}
			}
			$page_metadata['renderer'][$move_id] = true;
		}
	} else {
		return false;
	}
	$r['meta'] = $page_metadata['renderer'];
	if (!$database->sql_update("update ".DBX."data_items set metadata=? where id=? ", serialize64($page_metadata), $page)) {
		return false;
	}
	return true;
}

function snippet_inpage_edit()
{
	global $config, $params;

	/*if (!isset($params['page']['id']) || !$params['page']['id']) {
		return;
	}
	$option['id'] = $params['page']['id'];*/
	provide_edit_module();
	return _snippet_inpage_edit();

}

function snippet_colorpicker(&$page_ar, $option = null)
{
	global $html,$params;

	if (!check_rights('edit')) {
		return;
	}

	if (isset($html['meta']['snippet_colorpicker'])) {
		return;
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'open_color_dialog')  {
		$r = array();
		$r['result'] = true;
$r['html'] = <<<EOT
	<div id="color_dialog" >
		<div>
			<canvas class="canvas1" width="200" height="200"  style="background-color: #F00;cursor:pointer;">
			</canvas>
			<canvas class="canvas2" width="20" height="200"  style="background-color: #FFF;cursor:pointer;">
			</canvas>
		</div>
		<div style='font-size: 0.8em;margin:5px 0;'>
		opacity &nbsp <input type='number' style='padding:0;width:4em;background: none;border: none; text-align: right;' class='ot_colorpicker_opacity' value='100' />% 
		<select style='background-color: #222;color:#fff;font-size: 0.8em;padding: 0;margin: 0 0 0 10px;'><option>---</option><option>20%</option><option>50%</option><option>80%</option></select>
		</div>
		<div >
			<span class='color_box'  style='width:2em;margin-right:5px;display: inline-block;' >&nbsp</span>
			<input class='color_text' value='#---' style='display: inline-block; width: 12.5em;background-color: #222;border: none;font-size: 0.8em;padding: 0 5px;' />
		</div>
		<div>
			<input type='button' class='onethird-button mini ok_btn' value='更新' />
			<input type='button' class='onethird-button mini rm_btn' value='削除' />
			<input type='button' class='onethird-button mini' value='Close' onclick='ot.color_dialog_close()' />
		</div>
	</div>
EOT;
		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'color_dialog_save')  {
		if ($_POST['page'] == 'top') {
			$page = (int)$params['circle']['meta']['top_page'];
		} else if ($_POST['page'] == 'self') {
			$page = (int)$params['page']['id'];
		} else {
			$page = (int)$_POST['page'];
		}
		$name = sanitize_asc($_POST['name']);
		$data = sanitize_str($_POST['data']);
		$r = array();
		$r['result'] = false;
		$x = array();
		$x['id'] = $page;
		$x['meta']['flexcolor'][$name] = $data;
		if (read_pagedata( $page, $y )) {
			$x['meta'] = $y['meta'];
			if (isset($_POST['md']) && $_POST['md']==0) {
				unset($x['meta']['flexcolor'][$name]);
				$r['remove'] = true;
			} else {
				$x['meta']['flexcolor'][$name] = $data;
			}
			if (mod_data_items($x)) {
				$r['result'] = true;
				$r['data'] = $data;
			}
		}
		echo(json_encode($r));
		exit();
	}

$html['meta']['color_dialog'] = <<<EOT
	<script>
	\$(function(){
		\$(document).on('click', '#color_dialog .canvas1', function(e){
			ot.color_dialog_event1(e);
		});
		\$(document).on('click', '#color_dialog .canvas2', function(e){
			ot.color_dialog_event2(e);
		});
		\$(document).on('click change keyup', '#color_dialog .ot_colorpicker_opacity', function(e){
			ot.color_dialog_update();
		});
		\$(document).on('change', '#color_dialog .ot_colorpicker_opacity + select', function(e){
			this.previousSibling.previousSibling.value = parseInt(this.value);
			ot.color_dialog_update();
		});
		\$(document).on('mousemove', '#color_dialog .canvas1', function(e){
			if (ot.color_dialog_mode) { ot.color_dialog_event1(e); }
			e.preventDefault();e.stopPropagation();
		});
		\$(document).on('mousemove', 'body', function(e){
			ot.color_dialog_mode = false;
		});
		\$(document).on('mousemove', '#color_dialog .canvas2', function(e){
			if (ot.color_dialog_mode) {ot.color_dialog_event2(e);}
			e.preventDefault();e.stopPropagation();
		});
		\$(document).on('click keyup', '#color_dialog .color_text', function(e){
			var a = ot.color_dialog_valid(\$(this).val());
			if (!a) { return; }
			ot.color_dialog_set(a,false);
		});
		
		\$(document).on('mouseup', '#color_dialog .canvas1, #color_dialog .canvas2', function(e){
			ot.color_dialog_mode = false;
		});
		\$(document).on('mousedown', '#color_dialog .canvas1, #color_dialog .canvas2', function(e){
			ot.color_dialog_mode = true;
		});
	});
	ot.color_dialog_mode = false;
	ot.color_dialog_valid = function (b) {
		var a=[];
		if (b.substr(0,1) == '#') {
			if (b.substr(1,2)) {a[0] = parseInt(b.substr(1,2),16);} else {return;}
			if (b.substr(3,2)) {a[1] = parseInt(b.substr(3,2),16);} else {return;}
			if (b.substr(5,2)) {a[2] = parseInt(b.substr(5,2),16);} else {return;}
		} else if (b.substr(0,3) == 'rgb') {
			a = b.match(/[0-9.%]+/gi);
		} else {
			return false;
		}
		a[3] = \$('#color_dialog .ot_colorpicker_opacity').val();
		if (!a[3]) {
			if (!c) {
				a[3] = 100;
			}
			\$('#color_dialog .ot_colorpicker_opacity').val(a[3]);
		}
		return a;
	}
	ot.color_dialog_update = function () {
		var a = \$('#color_dialog .color_text').val();
		var b = ot.color_dialog_valid(a);
		if (b) { ot.color_dialog_set(b); }
	}
	ot.color_dialog_event2 = function (e) {
		var y = e.clientY - \$(e.currentTarget).position().top - 10;
		var x = e.clientX - parseInt(\$(e.currentTarget).offset().left);
		x = ot.color_dialog_change_ctx2.getImageData(x, y, 1, 1).data;
		ot.color_dialog_now = x;
		var a = \$('#color_dialog .ot_colorpicker_opacity').val();
		if (!a || a <0 || a>100) {a=100};
		x[3] = a;
		x = ot.color_dialog_set(x);
		\$('#color_dialog .canvas1').css('background-color',x);
	}
	ot.color_dialog_event1 = function (e) {
		var y = e.clientY - \$(e.currentTarget).position().top - 10;
		var x = e.clientX - parseInt(\$(e.currentTarget).offset().left);
		x = 1-(x/200);
		y = (y/200);
		w = [];
		w[0] = parseInt(ot.color_dialog_now[0]) + 255*x;
		if (w[0] > 255) { w[0] = 255; }
		w[0] -= parseInt(255*y);
		if (w[0] < 0) { w[0] = 0; }
		
		w[1] = parseInt(ot.color_dialog_now[1]) + 255*x;
		if (w[1] > 255) { w[1] = 255; }
		w[1] -= parseInt(255*y);
		if (w[1] < 0) { w[1] = 0; }

		w[2] = parseInt(ot.color_dialog_now[2]) + 255*x;
		if (w[2] > 255) { w[2] = 255; }
		w[2] -= parseInt(255*y);
		if (w[2] < 0) { w[2] = 0; }

		var a = \$('#color_dialog .ot_colorpicker_opacity').val();
		w[3] = a;
	
		ot.color_dialog_set(w);
	}
	ot.open_color_dialog = function (option) {
		if (\$('#color_dialog').length) {
			\$('#color_dialog').remove();
		}
		ot.color_dialog_option = option;
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=open_color_dialog"
			, dataType:'json'
			, success: function(data){
				if ( data && data['html'] ) {
					var obj = \$(data['html']);
					obj.css({
						zIndex: '1000000'
						, position: 'fixed'
						, top:'10px'
						, left:'10px'
						, 'background-color': 'rgba(0,0,0,0.5)'
						, color: '#FFF'
						, padding: '10px'
					});
					\$('body').after(obj.fadeIn(200));
					ot.color_dialog_init();
					\$('#color_dialog .ok_btn').click(function(){ot.color_dialog_save(1)});
					\$('#color_dialog .rm_btn').click(function(){ot.color_dialog_save(0)});
					if (ot.color_dialog_option.onready) {
						ot.color_dialog_option.onready();
					}
				}
			}
		});
	};
	ot.color_dialog_close = function () {
		\$('#color_dialog').remove();
	};
	ot.color_dialog_init = function () {
		var canvas = \$('#color_dialog .canvas1')[0];
		if ( canvas && canvas.getContext ) {
			var ctx = ot.color_dialog_change_ctx1 = canvas.getContext('2d');
			ctx.beginPath();
			var grad = ctx.createLinearGradient(0,0, 200,0);
			grad.addColorStop(0,'rgba(255, 255, 255,1)');
			grad.addColorStop(1,'rgba(255, 255, 255,0)');
			ctx.fillStyle = grad;
			ctx.fillRect(0,0, 200,200);
			grad  = ctx.createLinearGradient(0,0, 0,200);
			grad.addColorStop(0,'rgba(0, 0, 0,0)');
			grad.addColorStop(1,'rgba(0, 0, 0,1)');
			ctx.fillStyle = grad;
			ctx.fillRect(0,0, 200,200);
		}
		var palette = function(ctx,s,x,a,b) {
			grad = ctx.createLinearGradient(0,s-x, 0,s);
			grad.addColorStop(0,a);
			grad.addColorStop(1,b);
			ctx.fillStyle = grad;
			ctx.fillRect(0,s-x, 20*2,x);
		};
		canvas = \$('#color_dialog .canvas2')[0];
		if ( canvas && canvas.getContext ) {
			var ctx = ot.color_dialog_change_ctx2 = canvas.getContext('2d');
			ctx.beginPath();
			var grad;
			var s = 17*2;
			var x = s;
			palette(ctx,s,x,'rgb(255, 0, 0)','rgb(255, 0, 255)');
			s+=x; palette(ctx,s,x,'rgb(255, 0, 255)','rgb(0, 0, 255)');
			s+=x; palette(ctx,s,x,'rgb(0, 0, 255)','rgb(0, 255, 255)');
			s+=x; palette(ctx,s,x,'rgb(0, 255, 255)','rgb(0, 255, 0)');
			s+=x; palette(ctx,s,x,'rgb(0, 255, 0)','rgb(255, 255, 0)');
			s+=x; palette(ctx,s,x,'rgb(255, 255, 0)','rgb(255, 0, 0)');
		}
		x = [192,192,192,100];
		a = ot.color_dialog_option.value;
		ot.color_dialog_now = x;
		if (ot.color_dialog_option.type=='background') {
			if (ot.color_dialog_option.selector) {
				var cb = \$(ot.color_dialog_option.selector).css('background-color');
				if (cb) {
					\$('#color_dialog .canvas1').css('background-color',cb);
					\$('#color_dialog .color_box').css('background-color',cb);
					\$('#color_dialog .color_text').val(cb);
					var a = cb.match(/[0-9.%]+/gi);
					if (a && a[3] !== undefined) {
						\$('#color_dialog .ot_colorpicker_opacity').val(a[3]*100);
					}
				}
			}
		} else {
			if (ot.color_dialog_option.selector) {
				var cb = \$(ot.color_dialog_option.selector).css('color');
				if (cb) {
					\$('#color_dialog .canvas1').css('background-color',cb);
					\$('#color_dialog .color_box').css('background-color',cb);
					\$('#color_dialog .color_text').val(cb);
					var a = cb.match(/[0-9.%]+/gi);
					if (a && a[3] !== undefined) {
						\$('#color_dialog .ot_colorpicker_opacity').val(a[3]*100);
					}
				}
			}
		}
		if (ot.color_dialog_option.oninit) {
			ot.color_dialog_option.oninit();
		}
	};
	ot.color_dialog_set = function (ar,upd) {
		var a;
		z = ot.color_dialog_ar2rgba(ar);
		\$('#color_dialog .color_box').css('background-color',z);
		if (upd!==false) {
			\$('#color_dialog .color_text').val(z);
		}
		if (ot.color_dialog_option.type=='background') {
			if (ot.color_dialog_option.selector) {
				\$(ot.color_dialog_option.selector).css('background-color',z);
			}
		} else {
			if (ot.color_dialog_option.selector) {
				\$(ot.color_dialog_option.selector).css('color',z);
			}
		}
		if (ot.color_dialog_option.onchange) {
			ot.color_dialog_option.onchange(z,ar);
		}
		return z;
	}
	ot.color_dialog_save = function (md) {
		var a = \$('#color_dialog .color_text').val();
		if (ot.color_dialog_option.onsave) {
			if (ot.color_dialog_option.onsave(a,md) === false) {
				\$('#color_dialog').remove();
				return;
			}
		}
		var opt = '&data='+a;
		opt += '&name='+ot.color_dialog_option.name;
		opt += '&page='+ot.color_dialog_option.page;
		opt += '&md='+md;
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=color_dialog_save"+opt
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					\$('#color_dialog').remove();
				}
			}
		});
	};
	ot.color_dialog_ar2rgba = function (x) {
		if (x[3] !== undefined) {
			return 'rgba('+parseInt(x[0])+','+parseInt(x[1])+','+parseInt(x[2])+','+(parseInt(x[3])/100).toFixed(2)+')';
		} else {
			return 'rgb('+parseInt(x[0])+','+parseInt(x[1])+','+parseInt(x[2])+')';
		}
		return a;
	};
	</script>
EOT;
}

function snippet_inpage_file( &$page_ar, $option = null )
{
	global $html,$database,$params,$config,$plugin_ar,$ut;

	if (!check_rights('edit') || !isset($page_ar['id'])) {
		return;
	}

	//２重書き込み禁止
	if (isset($html['meta']['inpage_file'])) {
		return;
	}

	snippet_dialog();
	$option = array('embed'=>true, 'readlist'=>'false');

	if (!(isset($_GET['mode']) && $_GET['mode'] == 'edit')) {
		// snippet_inpage_file は editモードでは利用できない仕様
		snippet_image_uploader();
	}
	
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'set_inpage_file')  {
		$r = array();
		$r['result'] = false;
		$r['id'] = 0;
		$name = false;
		$group = false;
		
		if (isset($_POST['page'])) {
			if ($_POST['page'] == 'top') {
				$r['id'] = $params['circle']['meta']['top_page'];
			} else if ($_POST['page'] == 'self') {
				$r['id'] = $params['page']['id'];
			} else {
				$r['id'] = (int)$_POST['page'];
			}
		}
		if (isset($_POST['name'])) {
			$group = $r['id'];
			$name = sanitize_asc($_POST['name']);
		}
		if (isset($_POST['group'])) {
			$group = sanitize_asc($_POST['group']);
		}
		if (isset($_POST['src'])) { $src = sanitize_str($_POST['src']); }
		
		if (!check_rights('edit') || !$r['id'] || !$name || !$src) {
			echo( json_encode($r) );
			exit();
		}

		$ar = $database->sql_select_all("select contents,metadata from ".DBX."data_items where id=?", $r['id']);
		if ($ar) {
			if ($ar[0]['metadata']) {
				$m = unserialize64($ar[0]['metadata']);
				$r['group'] = $group;
				$r['name'] = $src;
				if (substr($group,0,1) == '.') {
					$src = "timg.php?p=".substr($group,1)."&amp;i=".$src;
				} else {
					$src = "img.php?p={$group}&amp;i=".$src;
				}
				$m['flexfile'][$name] = $src;
				$r['src'] = $src;
				if ($database->sql_update( "update ".DBX."data_items set metadata=? where id=?", serialize64($m), $r['id'])) {
					$r['result'] = true;
					if (isset($params['hook']['after_set_inpage_file']) && is_array($params['hook']['after_set_inpage_file'])) {
						foreach ($params['hook']['after_set_inpage_file'] as $v) {
							if (function_exists($v)) {
								if ($v($r) === false) {
									$r['result'] = false;
								}
							}
						}
						unset($params['hook']['after_set_inpage_file']);
					}
				}
			}
			if ($ar[0]['contents']) {
				$ar[0]['contents'] = echo_contents_script($ar[0]['contents']);			
			}
		}
		echo( json_encode($r) );
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'remove_inpage_img')  {
		$r = array();
		$r['result'] = false;
		$r['id'] = 0;
		$name = false;
		
		if (isset($_POST['page'])) {
			if ($_POST['page'] == 'top') {
				$r['id'] = $params['circle']['meta']['top_page'];
			} else if ($_POST['page'] == 'self') {
				$r['id'] = $params['page']['id'];
			} else {
				$r['id'] = (int)$_POST['page'];
			}
		}
		if (isset($_POST['name'])) { $name = sanitize_asc($_POST['name']); }
		
		if (!check_rights('edit') || !$r['id'] || !$name) {
			echo( json_encode($r) );
			exit();
		}

		$ar = $database->sql_select_all("select contents,metadata from ".DBX."data_items where id=?", $r['id']);
		if ($ar) {
			if ($ar[0]['metadata']) {
				$m = unserialize64($ar[0]['metadata']);
				unset($m['flexfile'][$name]);
				if ($database->sql_update( "update ".DBX."data_items set metadata=? where id=?", serialize64($m), $r['id'])) {
					$r['result'] = true;
				}
			}
			if ($ar[0]['contents']) {
				$ar[0]['contents'] = echo_contents_script($ar[0]['contents']);			
			}
		}
		echo( json_encode($r) );
		exit();
	}

	$buff ='';
$buff .= <<<EOT
<script>
	ot.inpage_img = function (option) {
		if (!option['info']) {option['info'] = '';}
		option['add-script'] = ot.inpage_img_init;
		option['select'] = function(obj) {
			var src = \$(obj).parent().attr('data-src');
			var opt = "ajax=set_inpage_file";
			opt += "&page=" + ot.uploader.page;
			if (ot.uploader.group) {
				opt += "&group=" + ot.uploader.group;
			}
			opt += "&name=" + ot.uploader.name;
			opt += "&src=" + encodeURIComponent(src);
			ot.ajax({
				type: "POST"
				, url: '{$params['safe_request']}'
				, data: opt
				, dataType:'json'
				, success: function(data){
					if (data && data['result']) {
						location.reload(true);
					}
				}
			});
			ot.close_dialog(\$('#onethird-uploader-dialog').hide());
		};
		if (ot.open_uploader) {
			ot.open_uploader(option);
		}
	};
	ot.inpage_img_init = function (){
		\$('#onethird-uploader-dialog .btn-panel').append("<input type='button' class='onethird-button' value='削除' onclick='ot.inpage_img_remove()' />");
	};
	ot.inpage_img_remove = function (){
		var opt = "ajax=remove_inpage_img";
		opt += "&page=" + ot.uploader['page'];
		opt += "&name=" + ot.uploader['name'];
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: opt
			, dataType:'json'
			, success: function(data){
				if (data && data['result']) {
					location.reload(true);
				}
			}
		});
	}
</script>
EOT;
	$html['meta']['inpage_file'] = $buff;
}

function page_edit_post( $top_page )
{
	global $database,$params,$html,$config,$ut;

	if (
		((isset($_POST['mdx']) && $_POST['mdx']=='mod_page') 
		|| 
		(isset($_POST['ajax']) && $_POST['ajax'] == 'editdata_save')) 
		) {

		if (!isset($_POST['p_page'])) {
			if (isset($_POST['ajax'])) {
				echo( json_encode($r) );
				exit();
			}
			exit_proc(400, 'Save-Error');
		}

		$d = $params['now'];

		$r = array();
		$r['result'] = false;
		$text = false;
		$temp_mode = false;
		
		$id = (int)$_POST['p_page'];

		if (isset($_POST['mdx']) && isset($_POST['edit_contents'])) {
			$text = sanitize_html($_POST['edit_contents']);
		}
		
		if (isset($_POST['ajax']) && isset($_POST['editdata'])) {
			$text = sanitize_html($_POST['editdata']);
		}

		if (empty($params['edit-right'])) {
			if (isset($_POST['ajax'])) {
				echo( json_encode($r) );
				exit();
			}
			exit_proc(400, 'Save-Error (page right)');
		}

		$old_ar = $database->sql_select_all("select metadata,link,type,block_type,id,mode,user,title from ".DBX."data_items where id=?", $id);
		if (!$old_ar) {
			system_error( __FILE__, __LINE__ );
		}
		$new_ar = $old_ar[0];
		$chg_info = false;
		$new_ar['meta'] = unserialize64($new_ar['metadata']);
		unset($new_ar['metadata']);
		if (isset($new_ar['meta']['draft'])) {
			unset($new_ar['meta']['draft']);
		}
		if ($params['login_user']['nickname']) {
			$new_ar['meta']['author'] = $params['login_user']['nickname'];
		}
		if ($new_ar['user'] != $_SESSION['login_id']) {
			$chg_info = true;
		}
		if (!isset($params['top_page'])) {
			if (isset($_POST['edit_title'])) {
				$new_ar['title'] = sanitize_post($_POST['edit_title']);
			}
		}
		$new_ar['user'] = $_SESSION['login_id'];
		$new_ar['contents'] = $text;
		$new_ar['mod_date'] = $params['now'];
		if ($new_ar['mode'] != 1) {
			$new_ar['date'] = $params['now'];
		}
		
		if (isset($params['hook']['before_modified']) && is_array($params['hook']['before_modified'])) {
			foreach ($params['hook']['before_modified'] as $v) {
				if (function_exists($v)) {
					if ($v($new_ar,$old_ar) === false) {
						return;
					}
				}
			}
		}
		if (event_plugin_page('onbefore_modified', $new_ar) === false) {
			return;
		}
		if (isset($_POST['draft'])) {
			$new_ar = array('id'=>$id);
			$new_ar['meta']['draft'] = $text;
			if (mod_data_items($new_ar)) {
				$r['result'] = true;
				$r['text'] = $text;
			}
			echo( json_encode($r) );
			exit();
		}

		if ($chg_info) {
			add_actionlog("change owner : {$params['login_user']['nickname']} : {$_SESSION['login_id']} : {$_SERVER['REMOTE_ADDR']} : {$ut->link($id)}");
		}

		$new_ar['metadata'] = serialize64($new_ar['meta']);
		unset($new_ar['meta']);
		if (!mod_data_items($new_ar)) {
			if (isset($_POST['ajax'])) {
				echo(json_encode($r));
				exit();
			}
			exit_proc(400, 'Save-Error');
		} else {
			if (isset($params['hook']['after_modified']) && is_array($params['hook']['after_modified'])) {
				foreach ($params['hook']['after_modified'] as $v) {
					if (function_exists($v)) {
						if ($v($new_ar,$old_ar) === false) {
							return;
						}
					}
				}
			}
			if ($new_ar['type'] >= 10) {
				event_plugin_page('onmodified', $new_ar);
			}
			$r['result'] = true;

			if (isset($_POST['ajax'])) {
				echo(json_encode($r));
				exit();
			}
			
			if (isset($r['url'])) {
				header("Location: {$r['url']}");
			
			} else if ($top_page) {
				header("Location: {$params['circle']['url']}");
				
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
}

//リンクが不正なエイリアスを補修する
function repair_alias()
{
	global $params, $p_circle, $database;
	
	$m = get_circle_meta();
	if (!$m || !isset($m['alias'])) { return; }
	$params['circle']['meta']['alias'] = array();

	foreach ($m['alias'] as $k=>$v) {
		$sql="select id,link from ".DBX."data_items where id=? ";
		$ar = $database->sql_select_all( $sql, $v );
		if ($ar) {
			$params['circle']['meta']['alias'][$k]=$v;
		}
	}
	$m['alias'] = $params['circle']['meta']['alias'];
	if (!$database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($m), $p_circle)) {
	}
	
	make_f_alias();		//エイリアスの逆引き設定	を再設定
}

function repair_system_menus()
{
	global $params, $p_circle, $database;
	$m = get_circle_meta('system_menus');
	if (!$m) { return; }
	$t = array();

	foreach ($m as $k=>$v) {
		if (isset($v['url']) && is_numeric($v['url'])) {
			$sql="select id,link from ".DBX."data_items where id=? ";
			$ar = $database->sql_select_all( $sql, (int)$v['url'] );
			if ($ar) {
				$t[$k]=$v;
			}
		} else {
			$t[$k]=$v;
		}
	}
	set_circle_meta('system_menus',$t);
}

?>