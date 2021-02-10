<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.

	define('IMAGE_BK_MAX_SIZE',2*1024*1024);	

	require_once(dirname(__FILE__).'/../config.php');
	require_once(dirname(__FILE__).'/../module/utility.basic.php');
	
	global $config,$params,$ut,$p_circle;

	basic_initialize();

	if (!check_rights('admin')) {
		exit_proc(403, 'Need administrator rights');
	}
	
	if (!empty($_POST)) {
		if (check_rights()) {
			$x = $params['login_user']['meta']['magic_str'];
			if (!isset($_POST['xtoken']) || $_POST['xtoken'] != $x) {
				if (isset($_POST['ajax'])) {
					$r = array();
					$r['result'] = false;
					$r['mess'] = 'xtoken-error';
					$r['xtoken-error'] = true;
					echo( json_encode($r) );
					exit();
				}
				exit_proc(400, "token error");
			}
		}
	}

	//ファイルダウンロードチェック
	if (isset($_GET['file'])) {
		$path = $params['files_path'].DIRECTORY_SEPARATOR.'backup';
		$file = $path.DIRECTORY_SEPARATOR.sanitize_asc($_GET['file']);
		header("Content-length: ".filesize($file));
		header("Content-type: application/octet-stream");
		header('Content-Disposition: attachment; filename='.basename($file));
		header('Content-Transfer-Encoding: binary');
		$f = fopen($file, 'r');
		if ($f) {
			while(!feof($f)) {
				echo fread($f, 1024);
				flush();
			}
			fclose($f);
		}
		exit();
	}

	snippet_overlay();
	avoid_attack();
	snippet_avoid_robots();

	$params['manager'] = 'restore';
	$params['template'] = 'admin.tpl';

	if (!isset($params['circle'])) {
		system_error( __FILE__, __LINE__ );
	}

	// 標準プラグイン/テーマプラグイン読み込み
	load_site_plugin();

	//パンくず表示
	$u = "{$params['request_name']}?circle=$p_circle";
	$params['breadcrumb'][] = array( 'link'=>"$u", 'text'=>'バックアップツール' );

	//アップロードファイルチェック
	if (isset($_POST['file_upload'])) {
		if (!$_POST['file_upload']) {
			$html['alert'][] = 'アップロードに失敗しました(1)';
		} else {
			$file1 = $_FILES['file1'];

			$file1tmp   = $_FILES['file1']['tmp_name'];		//tmpファイル名
			$file1name  = $_FILES['file1']['name'];			//ローカルファイル名
			$file1size  = $_FILES['file1']['size'];			//ファイルサイズ
			$file1type  = $_FILES['file1']['type'];			//ファイルの種類
			
			$info = pathinfo($file1name);

			if (is_uploaded_file($file1tmp)) {
				$date = date('ymdHis', $_SERVER['REQUEST_TIME']);
				$path = $params['files_path'].DIRECTORY_SEPARATOR.'backup';
				$dst = $path.'/import'.$date.'.'.$info['extension'];
				if (move_uploaded_file ( $file1tmp , $dst )) {
					if ($info['extension'] == 'db' || $info['extension'] == 'zip') {
						//$html['alert'][] = $file1tmp;
					} else {
						$html['alert'][] = 'アップロードに失敗しました';
						unlink( $dst );
					}
				} else {
					$html['alert'][] = 'ファイルをアップロード出来ませんでした(2)';
				}
			} else {
				$html['alert'][] = 'ファイルをアップロード出来ませんでした(3)';
			}
		}
	}
	
	make_backupdir();
	set_css();

	if (isset($_GET['view_file'])) {
		if (isset($_GET['offset'])) {
			$html['article'][] = view_item();
		} else {
			$html['article'][] = view_file();
		}
	} else {
		$html['article'][] = draw_restore();
	}

	snippet_header();
	snippet_system_nav();
	snippet_footer();

	expand_circle_html();

function set_css()
{
	global $params,$config,$ut,$html;

$html['css']['backuptool'] = <<<EOT
	<style>
		#backuptool {
			background-color: #fff;
			color: #222;
		}
		#backuptool a {
			color: #00547D;
			text-decoration: none;
		}
		#backuptool .xitem {
			background-color: #ececec;
		}
		#backuptool .warning {
			background-color: #fb9494;
		}
		.version-info  .warning-title {
			background-color: #f56767;
			padding: 10px 20px;
			border-radius: 10px 10px 0 0;
			font-weight: bold;
			border: 1px solid #8e8e8e;
			border-width: 1px 1px 0 1px;
		}
		.version-info  .warning-body {
			background-color: #f1c5c5;
			padding: 10px 20px;
			border-radius: 0 0 10px 10px;
			margin-bottom:30px;
			border: 1px solid #8e8e8e;
			border-width: 0 1px 1px 1px;
		}
		.onethird-frame {
			margin-bottom:20px;
		}
	</style>
EOT;

}

function view_file()
{
	global $params,$config,$database,$p_circle,$html,$ut;
	
	$path = $params['files_path'].DIRECTORY_SEPARATOR.'backup';
	$file = sanitize_str($_GET['view_file']);

	$file_name = $path.DIRECTORY_SEPARATOR.$file;
	if (!is_file($file_name)) {
		return "-- no file : $file --";
	}
	
	$zip = new ZipArchive();
	if ($zip->open($file_name)) {
		$x = $zip->getFromName('index.dat');
		$zip->close();
		if ($x) {
			$idx = unserialize64($x);
		} else {
			return "-- file error : $file --";
		}
	} else {
		return "-- file error : $file --";
	}
	
	if (!isset($idx["ver-ex-zip"]) || (!isset($idx["info"]) || $idx["info"]!='zip')) {
		return "-- file error : $file --";
	}

	if (!isset($idx["last_id"])) { $idx["last_id"] = '--'; }
	if (!isset($idx["date"])) { $idx["date"] = '--'; }
	if (!isset($idx["item_c"])) { $idx["item_c"] = '--'; }
	
	$u = "{$params['request_name']}?circle=$p_circle&view_file=".rawurlencode($file);
	
$buff = <<<EOT
	<table id='backuptool' class='onethird-table ' >
		<tr>
			<td>date</td><td>{$idx["date"]}</td>
		</tr>
		<tr>
			<td>count</td><td>{$idx["item_c"]}</td>
		</tr>
		<tr>
			<td>last_id</td><td>{$idx["last_id"]}</td>
		</tr>
		<tr>
			<td>file</td>
			<td>
EOT;
				$offset = 0;
				foreach ($idx["items"] as $v) {
					$buff .= "[<a href='$u&amp;offset={$offset}'>{$v["file"]}</a>#{$v["count"]}] ";
					++$offset;
				}
$buff .= <<<EOT
			</td>
		</tr>
	</table>
EOT;

	backup_common();
	return frame_renderer($buff);

}

function view_item()
{
	global $params,$config,$database,$p_circle,$html,$ut;
	
	$path = $params['files_path'].DIRECTORY_SEPARATOR.'backup';
	$file = sanitize_str($_GET['view_file']);
	$offset = (int)($_GET['offset']);

	$file_name = $path.DIRECTORY_SEPARATOR.$file;
	if (!is_file($file_name)) {
		return "-- no file : $file --";
	}
	$data = false;
	$zip = new ZipArchive();
	if ($zip->open($file_name)) {
		$x = $zip->getFromName('index.dat');
		if ($x) {
			$idx = unserialize64($x);
			$ofs = 0;
			foreach ($idx["items"] as $v) {
				if ($offset == $ofs) {
					$data = $zip->getFromName($v['file']);
					break;
				}
				++$ofs;
			}
		} else {
			$zip->close();
			return "-- file error : $file --";
		}
		$zip->close();
	} else {
		return "-- file error : $file --";
	}
	
	if (!$data || !isset($idx["ver-ex-zip"]) || (!isset($idx["info"]) || $idx["info"]!='zip')) {
		return "-- file error : $file --";
	}

	if (!isset($idx["last_id"])) { $idx["last_id"] = '--'; }
	if (!isset($idx["date"])) { $idx["date"] = '--'; }
	if (!isset($idx["item_c"])) { $idx["item_c"] = '--'; }
	
	$u = "{$params['request_name']}?circle=$p_circle&view_file=".rawurlencode($file);
	$params['breadcrumb'][] = array( 'link'=>"$u", 'text'=>$file );
	
$buff = <<<EOT
	<table id='backuptool' class='onethird-table ' >
EOT;
		$data = explode("\n",$data);
		foreach ($data as $line) {
			$c = substr($line,0,1);
			if ($c == 'i') {
				$id = substr($line,2);
			} else if ($c == 't') {
				$table = substr($line,2);
			} else if ($c == 'c') {
				$x = substr($line,2);
				if ($table == 'data_items') {
					$m = unserialize64($x);
					$t = '--';
					if (isset($m['title'])) {
						$t = adjust_mstring($m['title'],40);
					}
$buff .= <<<EOT
					<tr>
						<td>$id</td>
						<td>$t
						</td>
						<td>
						<input type='button' onclick='restore_item({file:"$file",offset:"$offset",id:"$id"})' value='Restore' class='onethird-button mini' />
						</td>
					</tr>
EOT;
				}
			}
		}
$buff .= <<<EOT
	</table>
EOT;

	backup_common();
	return frame_renderer($buff);

}

function get_comment(&$r, $y)
{
	$z = explode("\n",$y);
	foreach ($z as $vv) {
		if (substr($vv,0,4) == 'name') {
			$r['name'] = substr($vv,5);
		}
		if (substr($vv,0,4) == 'type') {
			$r['type'] = substr($vv,5);	// themeタイプ
		}
		if (substr($vv,0,4) == 'info') {
			$r['info'] = substr($vv,5);	// アーカイブタイプ
		}
		if (substr($vv,0,8) == 'image_st') {
			$r['image_st'] = substr($vv,9);	// イメージバックアップの途中
		}
		if (substr($vv,0,8) == 'image_ed') {
			$r['image_ed'] = substr($vv,9);	// イメージバックアップの途中
		}
		if (substr($vv,0,3) == 'ver') {
			$r['ver'] = substr($vv,4);
		}
		if (substr($vv,0,9) == 'thumbnail') {
			$r['thumbnail'] = substr($vv,10);
		}
	}
}

function draw_restore()
{
	global $params,$config,$database,$p_circle,$html,$ut;

	$buff = '';

	$backup_time = "--";
	if (isset($params['circle']['meta']['backup_time'])) {
		$backup_time = $params['circle']['meta']['backup_time'];
	}

$html['meta'][]= <<<EOT
	<script>
		\$.ajax({
			type: "POST"
			, url: 'https://x-sys.net/onethird/version.php?v={$config['version']}'
			, dataType:'json'
			, success: function(data){
				if (data && data['version']) {
					var obj = \$('.version-info');
					if (data['html']) {
						if (data['version_error']) {
							obj.append("<div class='warning-title'>System version Error</div>");
							obj.append("<div class='warning-body'>"+data['version_error']+"</div>");
						} else {
							obj.html(data['html']);
						}
					} else if (data['version']) {
						obj.html("Latest version <a href='http://onethird.net/'>v"+data['version']+'</a>');
					}
					var info = '';
					if (data['plugin']) {
						\$('.item_name').each(function(){
							var o = \$(this);
							var t = o.text();
							var d = o.attr('data-x');
							var y = o.attr('data-y');
							for (var i in data['plugin']) {
								if (i == t.substr(0,i.length)) {
									o.parents('tr').addClass('xitem');
									if (!data['plugin'][i]) {
										o.parents('tr').addClass('warning');
										info += "<p> - <b>"+y+" "+t+" can not be used</b></p>";
									} else if (d < data['plugin'][i]) {
										o.parents('tr').addClass('warning');
										info += "<p> - "+y+" "+t+" is too old </p>";
									}
									console.log(i+":"+data['plugin'][i]);
								}
							}
						});
						if (info) {
							obj.append("<div class='warning-title'>Plugin/theme Version Error</div>");
							obj.append("<div class='warning-body'>"+info+"</div>");
						}
					}
					obj.animate({
					  opacity: 1
					}, 500 );
				}
			}
		});
	</script>
EOT;

$buff.= <<<EOT
	<div class='version-info' style='min-height:3em;opacity: 0.1;'>
		Check <a href='http://onethird.net/'>Latest version</a>.
	</div>
	<p>Last backup time : $backup_time </p>
EOT;

$buff.= <<<EOT
	<p>
		<a href='javascript:void(ot.backup_page(0))' class='onethird-button ' >Zip-backup</a>
EOT;
		if (isset($params['circle']['meta']['backup_time'])) {
$buff.= <<<EOT
			<a href='javascript:void(ot.backup_page(0,1))' class='onethird-button' >Zip-backup (diff)</a>
EOT;
		}
$buff.= <<<EOT
		<a href='javascript:void(backup_data(1))' class='onethird-button ' >Program-backup</a>
		<a href='javascript:void(backup_data(3))' class='onethird-button ' >Image-backup (diff)</a>
	</p>
	<p>
		<a href='javascript:void(clear_plugin())' class='onethird-button mini' >Clear plugins</a>
		<a href='javascript:void(remove_all())' class='onethird-button mini' >Clear site-data</a>
		<a href='javascript:void(remove_user_log())' class='onethird-button mini' >Clear user-log</a>
		<a href='javascript:void(backup_users())' class='onethird-button mini' >User-backup</a>
		<a href='javascript:void(backup_data(2))' class='onethird-button mini' >Theme-backup</a>
		<a href='javascript:void(backup_data(0))' class='onethird-button mini' >SQLite-backup</a>
		<a href='javascript:void(backup_data(4))' class='onethird-button mini' >System-backup</a>
	</p>
EOT;

$buff.= <<<EOT
	<p>
EOT;
		if (is_file(dirname(__FILE__)."/online.php")) {
$buff.= <<<EOT
			 <a href='{$ut->link("{$config['admin_dir']}/online.php",'&:circle='.$p_circle)}' class='onethird-button'>{$ut->icon('download')} Online Theme</a>
			 <a href='{$ut->link("{$config['admin_dir']}/online.php",'&:circle='.$p_circle.'&plugin=1')}' class='onethird-button'>{$ut->icon('download')} Online Plugin</a>
EOT;
		}
		if (is_file(dirname(__FILE__)."/mail_updater.php")) {
$buff.= <<<EOT
			 <a href='{$ut->link("{$config['admin_dir']}/mail_updater.php",'&:circle='.$p_circle)}' class='onethird-button'>Open Mail Updater</a>
EOT;
		}
$buff.= <<<EOT
		 <a href='javascript:void(latest_version())' class='onethird-button'>{$ut->icon('download')} Latest version</a>
EOT;
$buff.= <<<EOT
	</p>
EOT;

$buff.= <<<EOT
	<p>
	<form id='form1' method='post' enctype='multipart/form-data' action='{$params['safe_request']}' >
		<input type='hidden' name='xtoken' value='{$params['login_user']['meta']['magic_str']}' />
		<input type=submit value='アップロード' class='onethird-button ' />
		<input type=file name='file1' style='display:inline-block'>
		<input type='hidden' name='file_upload' value='true' />
	</form>
	</p>
EOT;

	get_upload_settings($m);
$buff.= <<<EOT
	<p style='font-size:85%;'>
		 ( post_max_size : {$m['php']['post_max_size']}
		  , upload_max_filesize : {$m['php']['upload_max_filesize']}
		  , memory_limit : {$m['php']['memory_limit']} )
	</p>
EOT;
	if (is_file(dirname(__FILE__)."/online.php")) {
$buff.= <<<EOT
		 <p style='font-size:85%;'><a href='{$ut->link("{$config['admin_dir']}/online.php",'&:circle='.$p_circle.'&tool=1')}' >- Development tool</a></p>
EOT;
	}
	
	$path = $params['files_path'].DIRECTORY_SEPARATOR.'backup';
	if (is_dir($path)) {
		$data_ar = array();

		$ar = @glob($path.DIRECTORY_SEPARATOR.'*.db');
		if ($ar) {
			foreach ($ar as $v) {
				$r = array();
				$t = filemtime($v);
				$f = substr($v,strlen($params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup'.DIRECTORY_SEPARATOR));
				$f = str_replace("\\",'/',$f);		// for windows
				$r['date'] = date('Y-m-d H:i:s', $t);
				$r['file'] = $f;
				$r['info'] = 'dbase';
				$r['file_size'] = (int)(filesize($v)/1024);
				$data_ar[] = $r;
			}
		}
		if (class_exists('ZipArchive')) {
			scan_dir10($path,$ar);
			$zip = new ZipArchive();
			foreach ($ar as $v) {
				if (strtolower(substr($v,-4)) != '.zip') {
					continue;
				}
				$idx = false;
				$r = array();
				if ($zip->open($v)) {
					$x = $zip->getFromName('index.dat');
					$y = $zip->getArchiveComment();
					$zip->close();
					if ($x) {
						$idx = unserialize64($x);
					}
					if ($y) {
						get_comment($r, $y);
					}
				}

				$t = filemtime($v);
				$f = substr($v,strlen($params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup'.DIRECTORY_SEPARATOR));
				$f = str_replace("\\",'/',$f);		// for windows
				$r['date'] = date('Y-m-d H:i:s', $t);
				$r['file'] = $f;
				$r['file_size'] = (int)(filesize($v)/1024);
				if (isset($idx['info'])) {
					$r['info'] = $idx['info'];
					if ($idx['info'] == 'zip') {
						$idx = array_merge($idx, $r);
						if (isset($idx['date'])) {
							$t = strtotime($idx['date']);
						}
						$data_ar["$t"] = $idx;
					} else if ($idx['info'] == 'users') {
						$idx = array_merge($idx, $r);
						$idx['item_c'] = "{$idx['item_c']} users";
						$data_ar["$t"] = $idx;
					} else {
						$r['info'] = 'error';
						$data_ar["$t"] = $r;
					}
				} else {
					$data_ar[] = $r;
				}
			}
		}
		$ar = @glob($path.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'*.dat');
		if ($ar) {
			foreach ($ar as $v) {
				$f = substr($v,strlen($params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup'.DIRECTORY_SEPARATOR));
				$f = str_replace("\\",'/',$f);		// for windows
				$file = $v;
				$t = filemtime($v);
				$r['date'] = date('Y-m-d H:i:s', $t);
				$a = @file_get_contents($file);
				if (!$a) {
					continue;
				}
				if (substr($a,0,2)=='a:') {
					$r = unserialize($a);
				} else {
					$r = unserialize64($a);
				}
				$r['file'] = $f;
				$data_ar[] = $r;
			}
		}
		function restore_sort_func($a, $b) {
			if ($a['date'] == $b['date']) {
			    return 0;
			}
			return ($a['date'] > $b['date']) ? -1 : 1;
		}
		usort($data_ar, "restore_sort_func");
		
		$u = 'restore.php';
$buff.= <<<EOT
		<p>
			<a href='{$ut->link("{$config['admin_dir']}/restore.php","&:circle=$p_circle")}'>All</a>
			/ <a href='{$ut->link("{$config['admin_dir']}/restore.php","&:circle=$p_circle&m=backup")}'>Backup</a>
			/ <a href='{$ut->link("{$config['admin_dir']}/restore.php","&:circle=$p_circle&m=plugin")}'>Plugin</a>
			/ <a href='{$ut->link("{$config['admin_dir']}/restore.php","&:circle=$p_circle&m=theme")}'>Theme</a>
			/ <a href='{$ut->link("{$config['admin_dir']}/restore.php","&:circle=$p_circle&m=image")}'>Image</a>
		</p>
		<table id='backuptool' class='onethird-table ' >
			<tr>
				<th>name</th>
				<th>date</th>
				<th>items</th>
				<th>operations</th>
			</tr>
EOT;
			foreach ($data_ar as $ar) {
				if (isset($ar['item_c'])) {
					$c = $ar['item_c'];
				} else {
					if (isset($ar['items'])) {
						$c = count($ar['items']);
					} else {
						$c = '--';
						if (isset($ar['file_size']) && $ar['file_size']) {
							if (!$ar['file_size']) { $ar['file_size'] = 1; }
							if ($ar['file_size'] < 1024){
								$ar['file_size'] = $ar['file_size'].'KB';
							} else {
								$ar['file_size'] = number_format($ar['file_size']/1024,2).'MB';
							}
							$c = $ar['file_size'];
						}
					}
				}
				if (isset($ar['image_st'])) {
					$c .= " ({$ar['image_st']}..{$ar['image_ed']})";
				}
				$f = $ar['file'];
				if (!isset($ar['info'])) {
					$ar['info'] = 'error';
				}
				if (!isset($ar['date'])) {
					$ar['date'] = '';
				}
				$b = $ar['info'];
				if ($b == 'all') {
					$b = 'backup(old)';		// old version

				} else if ($b == 'diff') {
					$b = 'backup(old)';		// old version

				} else if ($b == 'zip') {
					if (pathinfo($f, PATHINFO_EXTENSION) == 'zip') {
						if (!isset($ar['start_id'])) {
							$ar['start_id'] = 0;
						}
						$b = "<a href='restore.php?file=$f'>";
						if (isset($ar['page_id'])) {
							$b .= "page - ";
							$b .= adjust_mstring($ar['title'],20);
							$b .= " (#{$ar['page_id']})";
						} else if (isset($ar['last_time'])) {
							$b .= "dif";
						} else {
							$b .= $f;
							$c = "{$c} ({$ar['start_id']}...{$ar['last_id']})";
						}
						if (isset($ar['making'])) {
							$b .= " - partial";
						}
						$b .= "</a>";
					} else {
						$b = "zip-backup (dir)</a>";
					}

				} else if ($b == 'user') {
					$b = 'user-backup';
					if (!check_rights('sys')) {
						continue;
					}

				} else {
					$b = "<a href='restore.php?file=$f'>$f</a>";
				}
				if (isset($ar['name']) && $ar['name']) {
					$b = "<a href='restore.php?file=$f'>{$ar['name']}</a>";
				}
				if (isset($ar['thumbnail'])) {
					$b .= "<br /><img src='{$ar['thumbnail']}' alt='' width='100' />";
				}
				if (!isset($ar['type'])) {
					$ar['type'] = $ar['info'];
				}
				if (!empty($_GET['m'])) {
					if ($_GET['m'] == 'plugin') { 
						if ($ar['type'] != 'plugin') { continue; }
					} else if ($_GET['m'] == 'theme') {
						if ($ar['type'] != 'theme') { continue; }
					} else if ($_GET['m'] == 'image') {
						if ($ar['type'] != 'image') { continue; }
					} else if ($_GET['m'] == 'backup' ) {
						if ($ar['type'] == 'plugin' || $ar['type'] == 'theme') { continue; }
					}
				}
$buff.= <<<EOT
				<tr data-file='$f'>
					<td><span class='item_name' data-x='{$ar['date']}' data-y='{$ar['type']}' >$b</span> <span style='color: rgba(0, 0, 0, 0.80);font-size:70%;'>({$ar['type']})<span></td>
					<td>{$ar['date']}</td>
					<td>$c</td>
					<td>
						<div class='' >
EOT;

							if ($ar['type'] == 'zip') {
								$dtime = strtotime($ar['date']);
$buff.= <<<EOT
								<input type='button' onclick='restore_item("$f")' value='上書' class='onethird-button mini' />
								<input type='button' onclick='import_item("$f")' value='インポート' class='onethird-button mini' />
								<input type='button' onclick='view_file("$f")' value='閲覧' class='onethird-button mini' />
								<input type='button' onclick='ot.backup_page(0,$dtime)' value='差分バックアップ' class='onethird-button mini' />
								<input type='button' onclick='zip_info("$f")' value='コメント' class='onethird-button mini' />
								<a href='javascript:void(remove_file("$f"))' >{$ut->icon('delete')}</a>
EOT;
							} else if ($ar['type'] == 'dbase') {
$buff.= <<<EOT
								<input type='button' onclick='restore_dbase("$f")' value='データリストア' class='onethird-button mini' />
								<a href='javascript:void(remove_file("$f"))' >{$ut->icon('delete')}</a>
EOT;
							} else if ($ar['type'] == 'program') {
$buff.= <<<EOT
								<input type='button' onclick='restore_program("$f")' value='上書' class='onethird-button mini' />
								<input type='button' onclick='zip_info("$f")' value='コメント' class='onethird-button mini' />
								<a href='javascript:void(remove_file("$f"))' >{$ut->icon('delete')}</a>
EOT;
							} else if ($ar['type'] == 'theme') {
								if ($f != 'autobackup.zip') {
$buff.= <<<EOT
									<input type='button' onclick='restore_program("$f","theme")' value='インストール' class='onethird-button mini' />
									<input type='button' onclick='zip_info("$f")' value='コメント' class='onethird-button mini' />
EOT;
								}
$buff.= <<<EOT
								<a href='javascript:void(remove_file("$f"))' >{$ut->icon('delete')}</a>
EOT;
							} else if ($ar['type'] == 'system_plugin') {
$buff.= <<<EOT
								<input type='button' onclick='restore_program("$f","system")' value='インストール' class='onethird-button mini' />
								<input type='button' onclick='uninstall_plugin("$f","system")' value='アンインストール' class='onethird-button mini' />
								<input type='button' onclick='zip_info("$f")' value='コメント' class='onethird-button mini' />
								<a href='javascript:void(remove_file("$f"))' >{$ut->icon('delete')}</a>
EOT;
							} else if ($ar['type'] == 'plugin') {
$buff.= <<<EOT
								<input type='button' onclick='restore_program("$f","plugin")' value='インストール' class='onethird-button mini' />
								<input type='button' onclick='uninstall_plugin("$f")' value='アンインストール' class='onethird-button mini' />
								<input type='button' onclick='zip_info("$f")' value='コメント' class='onethird-button mini' />
								<a href='javascript:void(remove_file("$f"))' >{$ut->icon('delete')}</a>
EOT;
							} else if ($ar['type'] == 'image') {
$buff.= <<<EOT
								<input type='button' onclick='restore_program("$f","image")' value='上書' class='onethird-button mini' />
								<input type='button' onclick='zip_info("$f")' value='コメント' class='onethird-button mini' />
								<a href='javascript:void(remove_file("$f"))' >{$ut->icon('delete')}</a>
EOT;
							} else if ($ar['type'] == 'system') {
$buff.= <<<EOT
								<input type='button' onclick='restore_program("$f","system")' value='アップデート' class='onethird-button mini' />
								<input type='button' onclick='zip_info("$f")' value='コメント' class='onethird-button mini' />
								<a href='javascript:void(remove_file("$f"))' >{$ut->icon('delete')}</a>
EOT;

							} else if ($ar['type'] == 'users') {
$buff.= <<<EOT
								<input type='button' onclick='restore_item("$f")' value='上書' class='onethird-button mini' />
								<input type='button' onclick='zip_info("$f")' value='コメント' class='onethird-button mini' />
								<a href='javascript:void(remove_file("$f"))' >{$ut->icon('delete')}</a>
EOT;
							} else {
$buff.= <<<EOT
								<input type='button' onclick='restore_item("$f")' value='Restore' class='onethird-button mini' />
								<input type='button' onclick='import_item("$f")' value='インポート' class='onethird-button mini' />
								<a href='javascript:void(remove_file("$f"))' >{$ut->icon('delete')}</a>
EOT;
							}
$buff.= <<<EOT
						</div>
					</td>
				</tr>
EOT;
			}
$buff.= <<<EOT
		</table>
EOT;
	}
	
	backup_common();

	return frame_renderer($buff);

}

function backup_common()
{
	global $params,$config,$database,$p_circle,$html,$ut;

	snippet_dialog();

$html['meta'][] = <<<EOT
	<div id="theme_setting" class='onethird-dialog' >
		<div class='title'>Change Value</div>
		<div class='onethird-setting'>
		</div>
		<div class='actions' >
			<input type='button' class='onethird-button' value='OK' onclick='save_zip_info()' />
			<input type='button' class='onethird-button' value='Cancel' onclick='ot.close_dialog(this)' />
		</div>
	</div>
	<script>
	function remove_item(file) {
		if (confirm("実行しますか?")) {
			ot.ajax({
				type: "POST"
				, url: '{$params['request_name']}'
				, data: "ajax=remove_item&file="+encodeURIComponent(file)
				, dataType:'json'
				, success: function(data){
					if (data && data['result'] ) {
						\$('tr[data-file="'+data['file']+'"]').fadeOut(500);
					} else {
						alert('読み込みできませんでした');
					}
				}
			});
		}
	}

	function remove_file(file) {
		if (confirm("実行しますか?")) {
			ot.ajax({
				type: "POST"
				, url: '{$params['request_name']}'
				, data: "ajax=remove_file&file="+encodeURIComponent(file)
				, dataType:'json'
				, success: function(data){
					if (data && data['result'] ) {
						\$('tr[data-file="'+data['file']+'"]').fadeOut(500);
					} else {
						alert('読み込みできませんでした');
					}
				}
			});
		}
	}

	function view_file(file) {
		location.href = '{$params['request_name']}?circle=$p_circle&view_file='+encodeURIComponent(file);
	}

	function restore_dbase(file) {
		if (confirm('全サイトのデータは以前の状態に戻ります\\nバックアップデータをリストアしますか？')) {
			ot.ajax({
				type: "POST"
				, url: '{$params['request_name']}'
				, data: "ajax=restore_dbase&file="+encodeURIComponent(file)
				, dataType:'json'
				, success: function(data){
					if (data && data['result'] ) {
						alert('更新しました');
					} else {
						alert('Update was failed.');
					}
				}
			});
		}
	}
	
	function restore_program(file, mode) {
		var a = "ajax=restore_program&file="+encodeURIComponent(file);
		if (mode == 'theme') {
			a = "ajax=restore_theme&file="+encodeURIComponent(file);
		} else if (mode == 'image') {
			a = "ajax=restore_image&file="+encodeURIComponent(file);
		} else if (mode == 'system') {
			a = "ajax=restore_system&file="+encodeURIComponent(file);
		} else if (mode == 'plugin') {
			a = "ajax=restore_plugin&file="+encodeURIComponent(file);
		}
		if (confirm('データは書き換えられます\\n実行しますか？')) {
			ot.ajax({
				type: "POST"
				, url: '{$params['request_name']}'
				, data: a
				, dataType:'json'
				, success: function(data){
					if (data && data['result'] ) {
						if (data['_init']) {
							if (!confirm('注意 : 組み込みスクリプトが見つかりました, 実行しますか？')) {
								return;
							}
							ot.ajax({
								type: "POST"
								, url: '{$params['request_name']}'
								, data: "ajax=exec_init&file="+encodeURIComponent(data['file'])+"&request="+encodeURIComponent(data['request'])
								, dataType:'json'
								, success: function(data){
									if (data && data['result'] ) {
										alert('更新しました');
									} else {
										if (data['mess']) {
											alert(data['mess']);
										} else {
											alert('Update error.');
										}
									}
								}
							});
						} else {
							alert('更新しました');
						}
					} else {
						if (data['mess']) {
							alert(data['mess']);
						} else {
							alert('Update error.');
						}
					}
				}
			});
		}
	}
	function uninstall_plugin(file, mode) {
		var a = "ajax=uninstall_plugin&file="+encodeURIComponent(file);
		if (mode == 'system') {
			a = "ajax=uninstall_system&file="+encodeURIComponent(file);
		}
		if (confirm('プラグインをアンインストールします\\n 実行しますか?')) {
			ot.ajax({
				type: "POST"
				, url: '{$params['request_name']}'
				, data: a
				, dataType:'json'
				, success: function(data){
					if (data && data['result'] ) {
						if (!confirm('注意 : 組み込みスクリプトが見つかりました, 実行しますか？')) {
							return;
						}
						ot.ajax({
							type: "POST"
							, url: '{$params['request_name']}'
							, data: "ajax="+data['_init']+"&uninstall_script=1&file="+encodeURIComponent(data['file'])
							, dataType:'json'
							, success: function(data){
								if (data && data['result'] ) {
									alert('アンインストールに成功しました');
								} else {
									alert('Uninstall error.');
								}
							}
						});
					} else {
						if (data['mess']) {
							alert(data['mess']);
						} else {
							alert('Uninstall failure.');
						}
					}
				}
			});
		}
	}
	

	function restore_item(file) {
		if (confirm("実行しますか?")) {
			exec_restore_item(file);
		}
	}

	function exec_restore_item(file, continue_mode) {
		var opt = '';
		if (continue_mode && !ot.restore_page_start) {
			return;
		}
		if (typeof file == 'object' && file.file) {
			if (file.offset) {
				opt += '&offset='+file.offset;
			}
			if (file.id) {
				opt += '&id='+file.id;
			}
			file = file.file;
		}
		if (ot.overlay && !continue_mode) {
			ot.overlay(1, "<div style='background:#fff;padding:20px'><img src='{$config['site_url']}img/loading.gif' /><p>progress...<span id='ot_progress' ></span></p><p><input type='button' value='stop' class='onethird-button' onclick='stop_restore_item()' /></p></div>")
			ot.restore_page_start = file;
		}
		if (\$('#ot_progress').length) {
			if (!ot.restore_page_start) {
				stop_restore_item();
				return;
			}
		}
		if (continue_mode) {
			opt += '&offset='+file;
			file = ot.restore_page_start;
		}
		opt += "&file="+encodeURIComponent(file);
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=restore_item"+opt
			, dataType:'json'
			, success: function(data){
				if (data && data['result'] ) {
					if (data['result'] == 'continue') {
						var x = parseInt(data['offset']*100/data['file_count']);
						if (x) {
							\$('#ot_progress').text(x+'%');
						}
						setTimeout(function(){exec_restore_item(data['offset']+1,true)},100);
						return;
					}
					stop_restore_item();
					alert('更新しました');
				} else {
					stop_restore_item();
					alert('Update was failed.');
				}
			}
			, error: function(data){
				stop_restore_item();
				alert('Update was failed.');
			}
		});
	}
	function stop_restore_item() {
		ot.restore_page_start = false;
		ot.overlay(0);
	}
	
	function import_item(file) {
		if (confirm("実行しますか?")) {
			exec_import_item(file);
		}
	}
	
	function exec_import_item(file, continue_mode) {
		var ofs = '';
		if (continue_mode && !ot.restore_page_start) {
			return;
		}
		if (ot.overlay && !continue_mode) {
			ot.overlay(1, "<div style='background:#fff;padding:20px'><img src='{$config['site_url']}img/loading.gif' /><p>progress...<span id='ot_progress' ></span></p><p><input type='button' value='stop' class='onethird-button'  onclick='stop_restore_item()' /></p></div>")
			ot.restore_page_start = file;
		}
		if (\$('#ot_progress').length) {
			if (!ot.restore_page_start) {
				stop_restore_item();
				return;
			}
		}
		if (continue_mode) {
			ofs = '&offset='+file;
			file = ot.restore_page_start;
		}
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=import_item&file="+encodeURIComponent(file)+ofs
			, dataType:'json'
			, success: function(data){
				if (data && data['result'] ) {
					if (data['result'] == 'continue') {
						var x = parseInt(data['offset']*100/data['file_count']);
						if (x) {
							\$('#ot_progress').text(x+'%');
						}
						setTimeout(function(){exec_import_item(data['offset']+1,true)},100);
						return;
					}
					stop_restore_item();
					alert('インポートしました');
				} else {
					stop_restore_item();
					alert('インポートできませんでした');
				}
			}
		});
	}
	
	function remove_all() {
		if (confirm("全データを消去します\\nこの操作は元に戻せませんすべてのデータが消去されます、実行しますか？")) {
			ot.ajax({
				type: "POST"
				, url: '{$params['request_name']}'
				, data: "ajax=remove_all"
				, dataType:'json'
				, success: function(data){
					if (data && data['result']) {
						alert('成功しました');
						location.reload(true);
					} else {
						alert('failed.');
					}
				}
			});
		}
	}
	function clear_plugin() {
		if (confirm("実行しますか?")) {
			ot.ajax({
				type: "POST"
				, url: '{$params['request_name']}'
				, data: "ajax=clear_plugin"
				, dataType:'json'
				, success: function(data){
					if (data && data['result']) {
						alert('成功しました');
						location.reload(true);
					} else {
						alert('failed.');
					}
				}
			});
		}
	}

	function remove_user_log(file) {
		if (confirm("実行しますか?")) {
			ot.ajax({
				type: "POST"
				, url: '{$params['request_name']}'
				, data: "ajax=remove_user_log"
				, dataType:'json'
				, success: function(data){
					if (data && data['result']) {
						alert('成功しました');
						location.reload(true);
					} else {
						alert('failed.');
					}
				}
			});
		}
	}

	function backup_data(mode) {
		ot.overlay(1, "<div style='background:#fff;padding:20px'><img src='{$config['site_url']}img/loading.gif' /><p>progress...<span id='ot_progress' ></span></p></div>")
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=backup_data&mode="+mode
			, dataType:'json'
			, success: function(data){
				if (data && data['result']) {
					location.reload(true);
				} else {
					ot.overlay(0);
					if (data['mess']) {
						alert(data['mess']);
					} else {
						alert('update failed.');
					}
				}
			}
		});
	}
	function backup_users() {
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=backup_users"
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					alert('バックアップしました');
					location.reload(true);
				} else {
					alert('バックアップできませんでした');
				}
			}
		});
	};
	function zip_info(file) {
		\$('#theme_setting').attr('data-file',file);
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=zip_info&file="+encodeURIComponent(file)
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					\$('#theme_setting .onethird-setting').html(data['html']);
					ot.open_dialog(\$('#theme_setting'));
				}
			}
		});
	}
	function save_zip_info() {
		var file = \$('#theme_setting').attr('data-file');
		var data = \$('#theme_setting .onethird-setting input').val();
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=save_zip_info&file="+encodeURIComponent(file)+"&data="+encodeURIComponent(data)
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					ot.close_dialog();
				}
			}
		});
	}
	function latest_version() {
		ot.overlay(1, "<div style='background:#fff;padding:20px'><img src='{$config['site_url']}img/loading.gif' /><p>progress...</p></div>")
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=latest_version"
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					location.reload(true);
				}
			}
		});
	}
	</script>
EOT;
	
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'latest_version')  {
		$r = array();
		$r['result'] = false;
		$path = $params['files_path'].DIRECTORY_SEPARATOR.'backup';

		// ロケール判定
		$lang = get_locale_str();
		
		$body = @file_get_contents("http://onethird.net/onethird_latest_version.php?lang=$lang");
		if ($body) {
			if (@file_put_contents($path.DIRECTORY_SEPARATOR."onethird.zip",$body)) {
				$r['result'] = true;
			}
		}
		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'remove_all')  {
		$r = array();
		$r['result'] = false;
		$sql = "select id from ".DBX."data_items where circle=?";
		if ($ar = $database->sql_select_all($sql, $p_circle)) {
			$ar2 = array();
			foreach ($ar as $v) {
				$ar2[] = $v['id'];
			}
			$r['result'] = remove_pages($ar2);
		}
		echo(json_encode($r));
		exit();
	}
	
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'clear_plugin')  {
		$r = array();
		$r['result'] = true;
		load_plugin_ar(true);
		echo( json_encode($r) );
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'remove_user_log')  {
		$r = array();
		$r['result'] = false;
		$sql = "delete from ".DBX."user_log where circle=?";
		$r['result'] = $database->sql_update($sql, $p_circle);
		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'backup_data')  {
		$r = array();
		$r['result'] = false;

		if (!isset($_POST['mode'])) {
			echo(json_encode($r));
			exit();
		}

		$mode = (int)$_POST['mode'];
		$date = date('ymdHis', $_SERVER['REQUEST_TIME']);
		
		$path = $params['files_path'].DIRECTORY_SEPARATOR.'backup';

		if ($mode == 3) {
			if (!class_exists('ZipArchive')) {
				echo(json_encode($r));
				exit();
			}
			$zip = new ZipArchive();

			//イメージバックアップの途中を検索
			$r['img_continue'] = 0;
			$data_ar = array();
			$ar = @glob($path.DIRECTORY_SEPARATOR.'*.zip');
			if ($ar) {
				foreach ($ar as $v) {
					$z = array();
					if ($zip->open($v)) {
						$y = $zip->getArchiveComment();
						$zip->close();
						if ($y) {
							get_comment($z, $y);
							if (isset($z['image_st']) && isset($z['image_ed'])) {
								if ( $r['img_continue'] < $z['image_ed']+1 ) {
									$r['img_continue'] = $z['image_ed']+1;
								}
							}
						}
					}
				}
			}
			$out = $path.DIRECTORY_SEPARATOR.'image'.$date.'.zip';
			if ($zip->open($out, ZipArchive::CREATE) !== true) {
				echo(json_encode($r));
				exit();
			}
			$info = "info:image\n";
			$i = 0;
			$c = 0;
			$size = 0;
			$info .= "image_st:{$r['img_continue']}\n";
			if (!function_exists('backup_data_scan_dir10')) {
				function backup_data_scan_dir10($src_dir, &$dir_ar, $nest=0) {
					global $config;
					if ($nest === 0) {
						$dir_ar = array();
					}
					if (++$nest > 10) {	// magic number
						--$nest;
						return true;	// エラーを出さずに中断する
					}
					$src_dir = rtrim($src_dir,' /'.DIRECTORY_SEPARATOR);
					if (is_dir($src_dir)) {
						if ($h = opendir($src_dir)) {
							while (($f = readdir($h)) !== false) {
								if ($f == "." || $f == "..") {
									continue;
								}
								if (is_dir($src_dir.DIRECTORY_SEPARATOR.$f)) {
									backup_data_scan_dir10($src_dir.DIRECTORY_SEPARATOR.$f, $dir_ar, $nest);
								} else {
									$t = filemtime($src_dir.DIRECTORY_SEPARATOR.$f);
									$dir_ar[] = array($t,$src_dir.DIRECTORY_SEPARATOR.$f);
								}
							}
							closedir($h);
						}
					}
					--$nest;
					return true;
				}
			}
			if (!function_exists('backup_data_cmp')) {
				function backup_data_cmp($a, $b) {
					if ($a[0] == $b[0]) {
						return 0;
					}
					return ($a[0] < $b[0]) ? -1 : 1;
				}
			}
			backup_data_scan_dir10($config['files_path']."/img/",$ar);
			usort($ar, "backup_data_cmp");

			get_upload_settings($m);
			$post_max_size = $m['post_max_size'];
			foreach ($ar as $v) {
				$v = $v[1];
				if (is_file($v)) {
					++$i;
					if ($r['img_continue'] > $i) {
						continue;
					}
					$size += filesize($v);
					if ($size > $post_max_size) {
						break;
					}
					++$c;
					$f = substr($v,strlen($config['files_path']."/img"));
					$f = str_replace("\\", '/', $f);
					$zip->addFile($v, $f);
				}
			}
			$info .= "image_ed:{$i}\n";
			if ($c == 0) {
				$r['mess'] = 'バックアップ終了しました';
			} else {
				if ($zip->setArchiveComment($info)) {
					$r['result'] = true;
				}
			}
			$zip->close();

		} else if ($mode == 4) {
			if (!class_exists('ZipArchive')) {
				echo(json_encode($r));
				exit();
			}
			$zip = new ZipArchive();

			$out = $path.DIRECTORY_SEPARATOR.'system'.$date.'.zip';
			if ($zip->open($out, ZipArchive::CREATE) !== true) {
				echo(json_encode($r));
				exit();
			}
			$info = "info:system\n";
			$c = 0;
			$size = 0;
			if (!function_exists('backup_data_scan_dir10')) {
				function backup_data_scan_dir10($src_dir, &$dir_ar, $nest=0) {
					global $config;
					if ($nest === 0) {
						$dir_ar = array();
					}
					if (++$nest > 10) {	// magic number
						--$nest;
						return true;	// エラーを出さずに中断する
					}
					$src_dir = rtrim($src_dir,' /'.DIRECTORY_SEPARATOR);
					if (is_dir($src_dir)) {
						if ($h = opendir($src_dir)) {
							while (($f = readdir($h)) !== false) {
								if ($f == "." || $f == "..") {
									continue;
								}
								$path_parts = pathinfo($src_dir.DIRECTORY_SEPARATOR.$f);
								if (isset($path_parts['extension'])) {
									$ex = $path_parts['extension'];
									$ex_ar = array('php'=>1,'css'=>1,'less'=>1,'dat'=>1,'gif'=>1,'png'=>1,'js'=>1,'tpl'=>1
									,'eot'=>1,'svg'=>1,'ttf'=>1,'woff'=>1,'crt'=>1);
								} else {
									$ex = false;
								}
								$name_ar = array('js'=>1,'tpl'=>1,'plugin'=>1,'sns'=>1,'img'=>1,'css'=>1,'module'=>1, 'admin'=>1);
								if (is_dir($src_dir.DIRECTORY_SEPARATOR.$f)) {
									if ($nest != 1 || isset($name_ar[$f])) {
										backup_data_scan_dir10($src_dir.DIRECTORY_SEPARATOR.$f, $dir_ar, $nest);
									}
								} else if ($ex && isset($ex_ar[$ex])) {
									if ($f != 'config.php' && $f != 'img.php') {
										$dir_ar[] = $src_dir.DIRECTORY_SEPARATOR.$f;
									}
								} else {
								}
							}
							closedir($h);
						}
					}
					--$nest;
					return true;
				}
			}
			backup_data_scan_dir10($config['site_path'],$ar);
			foreach ($ar as $v) {
				if (is_file($v)) {
					$size += filesize($v);
					++$c;
					$f = substr($v,strlen($config['site_path']));
					$f = str_replace("\\", '/', $f);
					$zip->addFile($v, $f);
				}
			}
			if ($c == 0) {
				$r['mess'] = 'バックアップ終了しました';
			} else {
				if ($zip->setArchiveComment($info)) {
					$r['result'] = true;
				}
			}
			$zip->close();

		} else if ($mode != 0) {
			if (!class_exists('ZipArchive')) {
				echo(json_encode($r));
				exit();
			}
			$zip = new ZipArchive();
			if ($mode == 1) {
				$out = $path.DIRECTORY_SEPARATOR.'program'.$date.'.zip';
				$info = 'program';
			} else {
				$out = $path.DIRECTORY_SEPARATOR.'theme'.$date.'.zip';
				$info = 'theme';
			}
			if ($zip->open($out, ZipArchive::CREATE) !== true) {
				echo(json_encode($r));
				exit();
			}
			if ($zip->setArchiveComment("info:{$info}\n")) {
				scan_dir10($params['circle']['files_path']."/data",$ar);
				foreach($ar as $v) {
					if (is_file($v)) {
						$f = substr($v,strlen($params['circle']['files_path']));
						$f = str_replace("\\", '/', $f);
						$zip->addFile($v, $f);
					}
				}
				if ($mode == 1) {
					scan_dir10($params['circle']['files_path']."/plugin",$ar);
					foreach($ar as $v) {
						if (is_file($v)) {
							$f = substr($v,strlen($params['circle']['files_path']));
							$f = str_replace("\\", '/', $f);
							$zip->addFile($v, $f);
						}
					}
				}
				$r['result'] = true;
			}
			$zip->close();

		} else {
		
			$dbname = DBName.$date;
			$file = $path.DIRECTORY_SEPARATOR.$dbname.'.db';
			if (is_file($file)) {
				unlink($file);
			}
			$db = new DataBase('sqlite','','','',$path);
			if (!$db->open($dbname)) {
				system_error( __FILE__, __LINE__ );
			}
			
			if ($db) {
				$r['result'] = true;
				$cmd = array(
					'data_items'
					, 'action_log'
					, 'circles'
					, 'joined_circle'
					, 'storage'
					, 'user_log'
					, 'users'
				);

				$db->sql_begin();
				if ($database->type == 'mysql') {
					foreach ($cmd as $k1=>$c) {
						$b = $database->sql_select_all("SHOW COLUMNS from ".DBX."{$c}");
						$sql = "CREATE TABLE '{$c}' (";
						foreach ($b as $k2=>$c2) {
							if ($k2 != 0) {
								$sql .= ",";
							}
							$sql .= "'{$c2['Field']}'";
						}
						$sql .= ")";
						$db->sql_update( $sql );
					}
				} else {
					$b = $database->sql_select_all("select * from sqlite_master");
					foreach ($b as $c2) {
						if ($c2['type'] == 'table' && $c2['tbl_name'] != 'sqlite_sequence') {
							$b = $db->sql_update($c2['sql']);
						}
					}
				}
				$db->sql_commit();

				$db->sql_begin();
				foreach ($cmd as $k1=>$c) {
					$x = $database->sql_select_one("select * from ".DBX."$c");
					if ($x) {
						while ($x = $database->sql_select_take()) {
							$y = '';
							$z = '';
							$xr = array();
							foreach ($x as $k=>$v) {
								if ($k == 'mx_name') {
									continue;
								}
								if (!$v) {
									continue;
								}
								$y .= $k.',';
								$z .= '?,';
								$xr[] = $v;
							}
							$y = trim($y,',');
							$z = trim($z,',');
							$sql = "insert into $c ($y) values ($z)";
							array_unshift($xr, $sql);
							call_user_func_array(array($db, 'sql_update'), $xr);
						}
					}
				}

				$db->sql_commit();
			}
		}
		echo(json_encode($r));
		exit();
	}
	
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'restore_dbase')  {
		$r = array();
		$r['result'] = false;

		if (!isset($_POST['file'])) {
			echo(json_encode($r));
			exit();
		}

		$r['file'] = sanitize_str($_POST['file']);
		$path = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup';
		$dbname = sanitize_str($_POST['file']);
		$file = $path.DIRECTORY_SEPARATOR.$dbname;
		$pathinfo = pathinfo($file);

		$db = new DataBase('sqlite','','','',$path);
		if (!$db->open($pathinfo['filename'])) {
			system_error( __FILE__, __LINE__ );
		}

		if ($db) {
			$r['result'] = true;
			$cmd = array(
				'data_items'
				, 'action_log'
				, 'circles'
				, 'joined_circle'
				, 'storage'
				, 'user_log'
				, 'users'
			);
			$database->sql_begin();
			foreach ($cmd as $k1=>$c) {
				$x = $db->sql_select_one("select * from $c");
				if ($x) {
					while ($x = $db->sql_select_take()) {
						$xr = array();
						if (isset($x['id'])) {
							$b = $database->sql_select_all("select id from ".DBX."$c where id={$x['id']}");
							if ($b) {
								$y = '';
								foreach ($x as $k=>$v) {
									if (!$v) {
										continue;
									}
									$y .= $k.'=?,';
									$xr[] = $v;
								}
								$y = trim($y,',');
								$sql = "update ".DBX."$c set $y where id={$x['id']}";
								array_unshift($xr, $sql);
								call_user_func_array(array($database, 'sql_update'), $xr);
								continue;
							}
						}
						$y = '';
						$z = '';
						foreach ($x as $k=>$v) {
							if (!$v) {
								continue;
							}
							$y .= $k.',';
							$z .= '?,';
							$xr[] = $v;
						}
						$y = trim($y,',');
						$z = trim($z,',');
						$sql = "insert into ".DBX."$c ($y) values ($z)";
						array_unshift($xr, $sql);
						call_user_func_array(array($database, 'sql_update'), $xr);
					}
				}
			}
			$database->sql_commit();
		}

		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && ($_POST['ajax'] == 'restore_theme' || $_POST['ajax'] == 'restore_program'|| $_POST['ajax'] == 'restore_plugin'))  {
		$r = array();
		$r['result'] = false;
		$r['request'] = sanitize_asc($_POST['ajax']);

		if (!isset($_POST['file']) || !class_exists('ZipArchive')) {
			echo(json_encode($r));
			exit();
		}

		$r['file'] = sanitize_str($_POST['file']);
		$path = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup';
		$dbname = sanitize_str($_POST['file']);
		
		$file = $path.DIRECTORY_SEPARATOR.$dbname;

		$zip = new ZipArchive;
		$r['x'] = $file;
		if ($zip->open($file) !== true) {
			echo(json_encode($r));
			exit();
		}

		$y = $zip->getArchiveComment();
		if ($y) {
			$z = explode("\n",$y);
			foreach ($z as $vv) {
				if (substr($vv,0,3) == 'ver') {
					$r['ver'] = substr($vv,4);
					if ($config['version'] < $r['ver']) {
						$r['mess'] = "バージョンが古くなっています、バージョンアップしてください";
						echo(json_encode($r));
						exit();
					}
				}
			}
		}

		$r['path'] = $params['circle']['files_path'];
		if (is_writable($r['path'])) {
		
			$p = $params['circle']['files_path'];
			if ($_POST['ajax'] == 'restore_plugin') {
				$p = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'plugin';
			}

			$index_tpl = false;
			$preload_theme = false;
			$write_err = "";
			for ($i=0; $i<$zip->numFiles; ++$i) {
				$f = $zip->getNameIndex($i);
				if ($f == '/data/index.tpl') {
					$index_tpl = true;
				}
				if ($zip->getNameIndex($i) == '/data/preload.php') {
					$preload_theme = true;
				}
				if (is_file($p.$f)) {
					if (!is_writable($p.$f)) {
						$write_err .= " - ".basename($f)."\n";
					}
				}
			}
			if (!is_writable($p)) {
				$write_err .= " - ".dirname($p)."/\n";
			}
			if ($write_err) {
				$r['mess'] = "write error: \n".$write_err;
				echo(json_encode($r));
				exit();
			}
			if ($_POST['ajax'] == 'restore_theme') {
				if (!read_pagedata($params['circle']['meta']['top_page'], $ar)) {
					echo(json_encode($r));
					exit();
				}
				if ($params['circle']['meta'] = get_circle_meta()) {
					$params['circle']['meta']['include']['last'] = $_SERVER['REQUEST_TIME'];
					$metadata=serialize64($params['circle']['meta']);
					if ($database->sql_update("update ".DBX."circles set metadata=? where id=?",$metadata,$p_circle)) {
					}
				}
				if ($index_tpl) {
					$ar['meta']['template_ar']['tpl'] = 'index.tpl';
				} else {
					unset($ar['meta']['template_ar']['tpl']);
				}
				if ($preload_theme) {
					$m = get_circle_meta('startup_script');
					if (!$m) {
						$m = array();
					}
					$m['preload_theme'] = 'data/preload.php';
					set_circle_meta('startup_script',$m);
				} else {
					$m = get_circle_meta('startup_script');
					if (!$m) {
						$m = array();
					}
					unset($m['preload_theme']);
					set_circle_meta('startup_script',$m);
				}
				$ar['metadata'] = serialize64($ar['meta']);
				if (!mod_data_items($ar)) {
					echo(json_encode($r));
					exit();
				}
			}

			if (@$zip->extractTo($p)) {
				$r['result'] = true;
				check_initp($zip,$r,$p,true);
			}
			if ($_POST['ajax'] == 'restore_theme') {
				set_circle_meta('inline_css',null);
				set_circle_meta('inline_css2',null);
				$v = $p.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'_inline2.css';
				if (is_file($v)) {
					$x = @file_get_contents($v);
					$x = unserialize64($x);
					if (is_array($x)) {
						set_circle_meta('inline_css2',$x);
					}
					@unlink($v);
					$r['_inline'] = $v;
				} else {
					$r['_inline__'] = $v;
				}
			}
		}
		$zip->close();

		echo(json_encode($r));
		exit();
	}
	
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'exec_init')  {
		$r = array();
		$r['result'] = false;

		if (!isset($_POST['file']) || !class_exists('ZipArchive')) {
			echo(json_encode($r));
			exit();
		}

		$r['file'] = sanitize_str($_POST['file']);
		$path = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup';
		$dbname = sanitize_str($_POST['file']);
		
		$file = $path.DIRECTORY_SEPARATOR.$dbname;

		$zip = new ZipArchive;
		$r['x'] = $file;
		if ($zip->open($file) !== true) {
			echo(json_encode($r));
			exit();
		}

		$y = $zip->getArchiveComment();
		if ($y) {
			$z = explode("\n",$y);
			foreach ($z as $vv) {
				if (substr($vv,0,3) == 'ver') {
					$r['ver'] = substr($vv,4);
					if ($config['version'] < $r['ver']) {
						$r['mess'] = "バージョンが古くなっています、バージョンアップしてください";
						echo(json_encode($r));
						exit();
					}
				}
			}
		}

		$r['path'] = $params['circle']['files_path'];
		if (is_writable($r['path'])) {
			$p = $params['circle']['files_path'];
			if ($_POST['request'] == 'restore_plugin') {
				$p = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'plugin';
			}
			$r['result'] = true;
			check_initp($zip,$r,$p,false,true);
		}
		$zip->close();

		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && ($_POST['ajax'] == 'uninstall_plugin' || $_POST['ajax'] == 'uninstall_system'))  {
		$r = array();
		$r['result'] = false;

		if (!isset($_POST['file']) || !class_exists('ZipArchive')) {
			echo(json_encode($r));
			exit();
		}

		$r['file'] = sanitize_str($_POST['file']);
		$path = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup';
		$dbname = sanitize_str($_POST['file']);
		
		$file = $path.DIRECTORY_SEPARATOR.$dbname;
		
		if ($_POST['ajax'] == 'uninstall_system') {
			$ins_path = $config['site_path'];
		} else {
			$ins_path = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'plugin'.DIRECTORY_SEPARATOR;
		}

		$zip = new ZipArchive;
		$r['x'] = $file;
		if ($zip->open($file) !== true) {
			echo(json_encode($r));
			exit();
		}

		$y = $zip->getArchiveComment();
		if ($y) {
			$z = explode("\n",$y);
			foreach ($z as $vv) {
				if (substr($vv,0,4) == 'name') {
					$r['name'] = substr($vv,5);
				}
			}
		}

		$r['result'] = true;
		$ins_scr = false;
		$ar = array();
		for ($i=0; $i<$zip->numFiles; ++$i) {
			$v = $zip->getNameIndex($i);
			if ($v == '/_init.php' || $v == '_init.php') {
				$r['_init'] = sanitize_str($_POST['ajax']);
				if (isset($_POST['uninstall_script'])) {
					$zip->extractTo($ins_path,array($v));
					check_initp($zip, $r, $ins_path, false, false);
					if (!$r['result']) {
						echo(json_encode($r));
						exit();
					}
				}
			}
			$ar[] = $ins_path.$v;
		}
		foreach ($ar as $v) {
			if (is_file($v)) {
				if (@unlink($v)) {
					$r['ok'][] = $v;
				} else {
					$r['ng'][] = $v;
					$r['result'] = false;
				}
			}
		}
		foreach ($ar as $v) {
			$v = dirname($v);
			while (strlen($ins_path) < strlen($v) && is_dir($v)) {
				$x = scandir($v);
				$r['try'][] = array(count($x) <= 2,$v);
				if (count($x) <= 2) {
					if (@rmdir($v)) {
						$r['remove_dir'][] = $v;
					} else {
					}
				}
				$v = dirname($v);
			}
		}
		$zip->close();
		if (!empty($r['name']) && $r['result'] && $_POST['ajax'] == 'uninstall_system') {
			set_circle_meta($r['name'],null);
		}

		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'restore_image')  {
		$r = array();
		$r['result'] = false;

		if (!isset($_POST['file']) || !class_exists('ZipArchive')) {
			echo(json_encode($r));
			exit();
		}

		$r['file'] = sanitize_str($_POST['file']);
		$path = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup';
		$dbname = sanitize_str($_POST['file']);
		
		$file = $path.DIRECTORY_SEPARATOR.$dbname;

		$zip = new ZipArchive;
		$r['x'] = $file;
		if ($zip->open($file) !== true) {
			echo(json_encode($r));
			exit();
		}

		$r['path'] = $config['files_path']."/img";
		if (@$zip->extractTo($r['path'])) {
			$r['result'] = true;
		}
		$zip->close();
		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'restore_system')  {
		$r = array();
		$r['result'] = false;

		if (!isset($_POST['file']) || !class_exists('ZipArchive')) {
			echo(json_encode($r));
			exit();
		}

		$r['file'] = sanitize_str($_POST['file']);
		$path = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup';
		$dbname = sanitize_str($_POST['file']);
		
		$file = $path.DIRECTORY_SEPARATOR.$dbname;

		$zip = new ZipArchive;
		$r['x'] = $file;
		if ($zip->open($file) !== true) {
			echo(json_encode($r));
			exit();
		}

		$y = $zip->getArchiveComment();
		if ($y) {
			$z = explode("\n",$y);
			foreach ($z as $vv) {
				if (substr($vv,0,4) == 'type') {
					$r['type'] = substr($vv,5);
				}
				if (substr($vv,0,4) == 'name') {
					$r['name'] = substr($vv,5);
				}
			}
		}

		$r['path'] = $config['site_path'];
		if (@$zip->extractTo($r['path'])) {
			$r['result'] = true;
			if (!empty($r['type']) && !empty($r['name']) && $r['type'] == 'system_plugin') {
				set_circle_meta($r['name'],true);
			}
			check_initp($zip,$r,$r['path'],false);
		}
		$zip->close();
		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'remove_item')  {
		$r = array();
		$r['result'] = false;
		if ( isset($_POST['file'])) {
			$r['file'] = sanitize_path($_POST['file']);
			$file = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup'.DIRECTORY_SEPARATOR.sanitize_str($_POST['file']);
			$path = dirname($file);
			if (substr($path,0,strlen($config['site_path'])) == $config['site_path']) {
				$a = @file_get_contents($file);
				$ar = @unserialize64($a);
				if ($ar) {
					foreach ($ar['items'] as $v) {
						@unlink($path.DIRECTORY_SEPARATOR.$v['file']);
					}
				}
				@unlink($file);
				@rmdir($path);
			}
			$r['result'] = true;
		}
		echo( json_encode($r) );
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'remove_file')  {
		$r = array();
		$r['result'] = false;
		if ( isset($_POST['file'])) {
			$r['file'] = sanitize_path($_POST['file']);
			$file = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup'.DIRECTORY_SEPARATOR.$r['file'];
			$path = dirname($file);
			if (substr($path,0,strlen($config['site_path'])) == $config['site_path']) {
				@unlink($file);
				$r['result'] = true;
			}
		}
		echo( json_encode($r) );
		exit();
	}

	if (isset($_POST['ajax']) && ($_POST['ajax'] == 'restore_item' || $_POST['ajax'] == 'import_item'))  {
		$r = array();
		$r['result'] = false;
		$offset = 0;
		if (isset($_POST['offset'])) {
			$offset = (int)$_POST['offset'];
		}
		$r['id'] = false;
		if ( isset($_POST['id'])) {
			$r['id'] = sanitize_str($_POST['id']);
		}
		if (isset($_POST['file'])) {
			$file = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup'.DIRECTORY_SEPARATOR.sanitize_str($_POST['file']);
			$r['file'] = $file;
			$path = dirname($file);
			if (substr($path,0,strlen($config['site_path'])) == $config['site_path']) {
				$link_map = array();	// コピーした場合などで、アイテムの番号が本来の番号と違う番号に復元された場合の張り替えマップ
				$zip = false;
				if (pathinfo($file, PATHINFO_EXTENSION) == 'zip') {
					$zip = new ZipArchive();
					if ($zip->open($file) === true) {
						$a = $zip->getFromName('index.dat');
					}
				} else {
					$a = file_get_contents($file);
				}
				$ar = unserialize64($a);
				if (isset($ar['info']) && ($ar['info'] == 'zip' || $ar['info'] == 'users')) {
					if (isset($ar['items'][$offset])) {
						$v = $ar['items'][$offset];
						$r['file_count'] = count($ar['items']);
						$r['offset'] = $offset;
						if ($zip) {
							$lines = $zip->getFromName($v['file']);
						} else {
							$lines = file_get_contents($path.DIRECTORY_SEPARATOR.$v['file']);
						}
						$lines = explode("\n",$lines);
						foreach ($lines as $line) {
							$c = substr($line,0,1);
							if ($c == 'i') {
								$id = substr($line,2);
							} else if ($c == 't') {
								$table = substr($line,2);
							} else if ($c == 'c') {
								$x = substr($line,2);
								if ($r['id']) {
									if ($r['id'] != $id) { continue; }
								}
								if ($_POST['ajax'] == 'import_item') {
									$r['import'] = true;
									$x = _do_import($x, $table, $link_map);
								} else {
									$x = _do_restore($x, $table, $link_map);
								}
								if ($r['id']) {
									$r['result'] = true;
								} else {
									if ($x !== false) {
										if (count($ar['items']) == $offset+1) {
											$r['result'] = true;
										} else {
											$r['result'] = 'continue';
										}
									}
								}
								if ($r['result']  == true) {
									if (isset($ar['circle_meta']['alias']) || isset($ar['circle_meta']['taglist'])) {
										$m = get_circle_meta();
										if (isset($ar['circle_meta']['alias']) && $ar['circle_meta']['alias']) {
											$m['alias'] = $ar['circle_meta']['alias'];
										}
										if (isset($ar['circle_meta']['taglist']) && $ar['circle_meta']['taglist']) {
											$m['taglist'] = $ar['circle_meta']['taglist'];
										}
										if ($database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($m), $p_circle)) {
										}
									}
								}
							}
						}
					}
				} else {
					foreach ($ar['items'] as $v) {
						if ($_POST['ajax'] == 'import_item') {
							$r['x'][] = do_import($path.DIRECTORY_SEPARATOR.$v['file'], $v['table'], $link_map);
						} else {
							$r['x'][] = do_restore($path.DIRECTORY_SEPARATOR.$v['file'], $v['table'], $link_map);
						}
					}
					remake_link($link_map);
					$r['result'] = true;
				}
				if ($zip) {
					$zip->close();
				}
			}
		}
		echo( json_encode($r) );
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'zip_info')  {
		$r = array();
		$r['result'] = true;

		$r['file'] = sanitize_str($_POST['file']);
		$path = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup';
		$dbname = sanitize_str($_POST['file']);
		$file = $path.DIRECTORY_SEPARATOR.$dbname;
		
		$zip = new ZipArchive;
		$h = '';
		if ($zip->open($file) === true) {
			$y = $zip->getArchiveComment();
			$zip->close();
			if ($y) {
				$z = explode("\n",$y);
				foreach ($z as $vv) {
					if (substr($vv,0,4) == 'name') {
						$r['name'] = substr($vv,5);
					}
				}
				if (isset($r['name'])) {
					$h = $r['name'];
				}
			}
		}
		$r['html'] = "<input type='' value='$h' />";
		
		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'save_zip_info')  {
		$r = array();
		$r['result'] = false;

		$r['file'] = sanitize_str($_POST['file']);
		$r['data'] = sanitize_post($_POST['data']);
		$path = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup';
		$dbname = sanitize_str($_POST['file']);
		$file = $path.DIRECTORY_SEPARATOR.$dbname;
		
		$zip = new ZipArchive;
		$mod_time = filemtime($file);
		if ($zip->open($file) === true) {
			$y = $zip->getArchiveComment();
			$x = '';
			if ($y) {
				$z = explode("\n",$y);
				foreach ($z as $vv) {
					if (substr($vv,0,4) != 'name' && $vv) {
						$x .= "$vv\n";
					}
				}
			}
			$x .= 'name:'.str_replace(array("\r\n","\r","\n"), '', $r['data'])."\n";
			if ($zip->setArchiveComment($x)) {
				$r['result'] = true;
				$r['x'] = $x;
			}
			$zip->close();
		}
		@touch($file, $mod_time);
		
		echo(json_encode($r));
		exit();
	}

}

function do_restore($file, $table, &$link_map)
{
	global $database,$config;
	$a = @file_get_contents($file);
	return _do_restore($a, $table, $link_map);
}

function _do_restore(&$a, $table, &$link_map)
{
	global $database,$p_circle,$config;

	$ar = unserialize64($a);

	$p = array();
	$id = $ar['id'];

	$link_map[$id]['id'] = $id;
	$link_map[$id]['tb'] = $table;

	$ar2 = $database->sql_select_all("select id from ".DBX."$table where id=? ", $id);
	if ($ar2 && $ar2[0]) {
		$sql = "update ".DBX."$table set ";
		foreach ($ar as $k=>$v) {
			if ($k =='id' || $k == 'pv_count') { continue; }
			if ($k =='circle') { $v=$p_circle; }
			$sql .= "$k=?,";
			$p[] = $v;
		}
		$sql = trim($sql,',');
		$sql .= ' where id=?';
		$p[] = $id;
		array_unshift($p, $sql);
		if (!$database->sql_update($p)) {
			return false;
		}
		
	} else {
		$sql = "insert into ".DBX."$table ( ";
		$vv='';
		foreach ($ar as $k=>$v) {
			if ($k =='circle') { $v=$p_circle; }
			if ($k =='user' && $v === NULL) { $v=$config['admin_user']; }
			$sql .= "$k,";
			$vv .= '?,';
			$p[] = $v;
		}
		$sql = trim($sql,',');
		$vv = trim($vv,',');
		$sql .= ") values ($vv) ";
		array_unshift($p, $sql);
		if (!$database->sql_update($p)) {
			return false;
		}
		$new_id = $database->lastInsertId();
		$link_map[$id]['id'] = $new_id;
		if ($table == 'users') {
			join_circle($p_circle, $ar['id'], false, 0);
		}
	}
	return $p;	//成功

}

function do_import($file, $table, &$link_map)
{
	global $database,$config;
	$a = @file_get_contents($file);
	return _do_import($a, $table, $link_map);
}

function _do_import(&$a, $table, &$link_map)
{
	global $database,$p_circle;

	$ar = unserialize64($a);
	$p = array();

	$id = $ar['id'];
	$link_map[$id]['id'] = $id;
	$link_map[$id]['tb'] = $table;

	$sql = "insert into ".DBX."$table ( ";
	$vv='';
	$user_id = false;
	if ($table == 'users') {
		foreach ($ar as $k=>$v) {
			if ($k =='name') {
				$arx = $database->sql_select_all(" select id from ".DBX."users where name=? ", $v);
				if ($arx) {
					return true;
				}
			}
			if ($k =='id') {
				$user_id = $v;
			}
		}
	}

	foreach ($ar as $k=>$v) {
		if ($k =='id' || $k == 'pv_count') { continue; }
		if ($k =='circle') { $v=$p_circle; }
		$sql .= "$k,";
		$vv .= '?,';
		$p[] = $v;
	}

	$sql = trim($sql,',');
	$vv = trim($vv,',');
	$sql .= ") values ($vv) ";
	array_unshift($p, $sql);

	if (!$database->sql_update($p)) {
		return false;
	}

	if ($table == 'data_items') {
		$new_id = $database->lastInsertId();
		$link_map[$id]['id'] = $new_id;
	}

	if ($table == 'users' && $user_id) {
		$new_id = $database->lastInsertId();
		join_circle($p_circle, $new_id);
	}
	return true;
}

function remake_link($link_map)
{
	global $database;
	foreach ($link_map as $k=>$v) {
		if ($k != $v['id']) {
			$database->sql_update("update ".DBX."{$v['tb']} set link=? where link=?", $v['id'], $k);
		}
	}
	foreach($link_map as $v) {
		if ($v['id'] =='data_items') {
			regenerate_attached($v['id'], true);
		}
	}
}

function backup_users()
{
	global $params, $config, $database, $ut;
	
	if (!isset($_SESSION['login_id']) || !$_SESSION['login_id']) {
		return false;
	}

	if (!check_rights('sys')) {
		return false;
	}

	make_backupdir();

	$date = date('ymdHis', $_SERVER['REQUEST_TIME']);
	$path = $params['files_path'].DIRECTORY_SEPARATOR.'backup'.DIRECTORY_SEPARATOR.'user_'.$date;
	if (!is_dir($path)) {
		mkdir($path);
		chmod($path,$config['permission']);
	}

	$backup_idx = array();
	$backup_idx['date'] = $params['now'];

	$file = $path.DIRECTORY_SEPARATOR."users_{$date}_";
	$ar = $database->sql_select_all("select * from ".DBX."users" );
	$zip_files = array();
	$item_c = 0;
	foreach ($ar as $v) {
		$body = "\n";
		$body .= "i:\n";
		$body .= "t:users\n";
		$body .= "c:".serialize64($v)."\n";
		if (file_put_contents($file.$v['id'], $body)) {
			$zip_files[] = $file.$v['id'];
			chmod($file.$v['id'], $config['permission']);
			$backup_idx['items'][] = array('file'=>basename($file.$v['id']),'table'=>'users');
			++$item_c;
		}
	}

	$backup_idx['info'] = 'users';
	$backup_idx['item_c'] = $item_c;

	$file = $path.DIRECTORY_SEPARATOR."index.dat";
	$zip_files[] = $file;
	if (!file_put_contents($file, serialize64($backup_idx))) {
		return false;
	}

	if (!class_exists('ZipArchive')) {
		return false;
	}
	$zip = new ZipArchive();
	if ($zip->open($path.'.zip', ZipArchive::CREATE) !== true) {
		return false;
	}
	if ($zip->setArchiveComment("info:users\n")) {
		foreach($zip_files as $v) {
			if (is_file($v)) {
				$f = substr($v,strlen($path.DIRECTORY_SEPARATOR));
				$f = str_replace("\\", '/', $f);
				$zip->addFile($v, $f);
			}
		}
	}
	$zip->close();
	return remove_directory($path);
}

function get_upload_settings(&$ar)
{
	$ar = array();
	$ar['php']['post_max_size'] = ini_get("post_max_size");
	$ar['php']['upload_max_filesize'] = ini_get("upload_max_filesize");
	$ar['php']['memory_limit'] = ini_get("memory_limit");
	
	$ar['post_max_size'] = IMAGE_BK_MAX_SIZE;
	$s = $ar['php']['post_max_size'];
	// K = 1024 だが、簡易的に1000に丸める（少ない分には問題ない）
	$s = str_replace('K','000',$s);
	$s = str_replace('M','000000',$s);
	$s = str_replace('G','000000000',$s);
	if ((int)$s > $ar['post_max_size']) {
		$ar['post_max_size'] = $ar['php']['post_max_size'];
		$ar['post_max_size'] = $s;
	}
}

function get_locale_str()
{
	$lang = 'en';
	$languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
	$languages = array_reverse($languages);
	foreach ($languages as $language) {
		if (preg_match('/^ja/i', $language)) {
			$lang = 'ja';
			break;
		}
	}
	return $lang;
}

function check_initp(&$zip, &$r, $p, $check = false, $install = true)
{
	for ($i=0; $i<$zip->numFiles; ++$i) {
		$v = $zip->getNameIndex($i);
		if ($v == '/_init.php' || $v == '_init.php') {
			if ($check) {
				$r['_init'] = $v;
				return;
			}
			$v = $p.DIRECTORY_SEPARATOR.trim($v,"/".DIRECTORY_SEPARATOR);
			if (is_file($v)) {
				@include_once($v);
				if ($install) {
					if (function_exists('install_script')) {
						$x = install_script();
						if ($x === false) {
							$r['result'] = false;
							$r['mess'] = 'Install script error';
							return;
						} else if (is_array($x)) {
							if (!isset($x)) { $x['result'] = false; }
							$r = $x;
							return;
						}
					}
				} else {
					if (function_exists('uninstall_script')) {
						$x = uninstall_script();
						if ($x === false) {
							$r['result'] = false;
							$r['mess'] = 'Uninstall script error';
							return;
						} else if (is_array($x)) {
							if (!isset($x)) { $x['result'] = false; }
							$r = $x;
							return;
						}
					}
				}
				@unlink($v);
				$r['_init'] = $v;
			}
		}
	}
}

?>