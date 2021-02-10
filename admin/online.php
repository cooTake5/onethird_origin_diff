<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.

	require_once(dirname(__FILE__).'/../config.php');
	require_once(dirname(__FILE__).'/../module/utility.basic.php');

	global $gallery_url,$params;
	
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

	snippet_overlay();
	avoid_attack();
	snippet_avoid_robots();

	$params['manager'] = 'online';
	$params['template'] = 'admin.tpl';

	if (!isset($params['circle'])) {
		system_error( __FILE__, __LINE__ );
	}

	// 標準プラグイン/テーマプラグイン読み込み
	load_site_plugin();

	//パンくず表示
	$u = $ut->link("{$config['admin_dir']}/restore.php",'&:circle='.$p_circle);
	$params['breadcrumb'][] = array( 'link'=>"$u", 'text'=>'backup tool' );

	if (isset($_GET['tool'])) {
		$params['breadcrumb'][] = array( 'link'=>"$u", 'text'=>'development tool' );
	} else {
		if (isset($_GET['plugin'])) {
			$u = "{$params['request_name']}?circle=$p_circle&plugin=1";
			$params['breadcrumb'][] = array( 'link'=>"$u", 'text'=>'online plugins' );

			if (get_locale_str() == 'ja') {
				$gallery_url = "https://x-sys.net/onethird/plugins";
			} else {
				$gallery_url = "https://x-sys.net/onethird/en/plugins";
			}
			$gallery_download_url = "http://x-sys.net/onethird/files/img/";
			if (isset($config['plugins_url'])) {
				$gallery_url = $config['plugins_url'];
			}

		} else {
			$u = "{$params['request_name']}?circle=$p_circle";
			$params['breadcrumb'][] = array( 'link'=>"$u", 'text'=>'online themes' );

			if (get_locale_str() == 'ja') {
				$gallery_url = "https://x-sys.net/onethird/themes";
			} else {
				$gallery_url = "https://x-sys.net/onethird/en/themes";
			}
			$gallery_download_url = "http://x-sys.net/onethird/files/img/";
			if (isset($config['theme_url'])) {
				$gallery_url = $config['theme_url'];
			}
		}
	}
	make_backupdir();
	set_css();
	$html['article'][] = draw_restore();

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
		.onethird-frame {
			margin-bottom:20px;
		}
	</style>
EOT;

}

function draw_restore()
{
	global $params,$config,$database,$p_circle,$html,$ut;
	global $gallery_url;

	$buff = '';

$html['head'][] = <<<EOT
<style>
.theme-item {
float: left;
margin: 5px 5px 6px 0;
display: inline-block;
box-sizing: border-box;
height: 200px;
background-color: #DBDADA;
overflow: hidden;
padding: 4px;
width: 219px;
text-align: center;
}
</style>
EOT;
	$dir = '';
	if (isset($_GET['dir'])) {
		$dir = sanitize_asc($_GET['dir']);
		$u = "{$params['request_name']}?circle=$p_circle&dir=$dir";
		$params['breadcrumb'][] = array( 'link'=>"$u", 'text'=>$dir );
		$dir .= '/';
	}
	backup_common($dir);

	if (!isset($_GET['tool'])) {

$html['meta'][] = <<<EOT
		<script>
			window.addEventListener("message", receive_xdom, false);
			function receive_xdom( e ) {
				d = e.data;
				if ( d == 'start' ) {
					xdom_handle = e.source.window;
					xdom_handle.postMessage( 'request', '*' );

				} else {
					d = JSON.parse( d );
					if (d.height > 500) {
						\$('#xdom').height(d.height);
					}
					if (d.page && d.file && d.download_request) {
						window.thema_data = d;
						preview_theme();
					}
				}
			}
		</script>
EOT;

$buff.= <<<EOT
		<iframe id='xdom' src='{$gallery_url}?iframe=true&t={$_SERVER['REQUEST_TIME']}' style='width: 100%;border: none;height: 500px;scrolling="no";' ></iframe>
EOT;
		
		return frame_renderer($buff);
	}

$buff.= <<<EOT
	<p>
		<p>- <a href='{$ut->link("{$config['admin_dir']}/online.php",'&:circle='.$p_circle)}'>Online themes</a></p>
	</p>
	<p>
		<a href='javascript:void(backup_data(1))' class='onethird-button mini' >Theme-backup</a>
	</p>
EOT;
	$path = $params['files_path'].DIRECTORY_SEPARATOR.'backup';
	if (is_dir($path)) {
		$data_ar = array();

		$ar = @glob($path.DIRECTORY_SEPARATOR.'*.zip');
		$zip = new ZipArchive();
		foreach ($ar as $v) {
			
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
					$z = explode("\n",$y);
					foreach ($z as $vv) {
						if (substr($vv,0,4) == 'name') {
							$r['name'] = substr($vv,5);
						}
						if (substr($vv,0,4) == 'type') {
							$r['type'] = substr($vv,5);
						}
						if (substr($vv,0,4) == 'info') {
							$r['info'] = substr($vv,5);
						}
						if (substr($vv,0,3) == 'ver') {
							$r['ver'] = substr($vv,4);
						}
					}
				}
			}

			$t = filemtime($v);
			$f = substr($v,strlen($params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup'.DIRECTORY_SEPARATOR));
			$f = str_replace("\\",'/',$f);		// for windows
			if (isset($idx['info']) && $idx['info'] == 'zip') {
				continue;
			} else {
				$r['date'] = date('Y-m-d H:i:s', $t);
				$r['file'] = $f;
				$data_ar["$t"] = $r;
			}
		}

		function restore_sort_func($a, $b) {
			if ($a['date'] == $b['date']) {
			    return 0;
			}
			return ($a['date'] > $b['date']) ? -1 : 1;
		}
		usort($data_ar, "restore_sort_func");

$buff.= <<<EOT
		<table id='backuptool' class='onethird-table ' >
			<tr>
				<th>name</th>
				<th>type,info,ver</th>
				<th>date</th>
				<th>operations</th>
			</tr>
EOT;
			foreach ($data_ar as $ar) {
				if (!empty($ar['info']) && $ar['info'] != 'system') {
					continue;
				}
				$f = $ar['file'];
				$name = $f;
				if (isset($ar['name'])) {
					$name = $ar['name'];
				}
				if (!isset($ar['info'])) {
					$ar['info'] = '--';
				}
				if (!isset($ar['type'])) {
					$ar['type'] = '--';
				}
				if (!isset($ar['ver'])) {
					$ar['ver'] = '--';
				}
				if (!isset($ar['date'])) {
					$ar['date'] = '';
				}
				$b = "<a href='restore.php?file=$f'>$name</a>";
$buff.= <<<EOT
				<tr data-file='$f'>
					<td>$b <span></td>
					<td>{$ar['type']},{$ar['info']},{$ar['ver']}</td>
					<td>{$ar['date']}</td>
					<td>
						<div class='' >
							<input type='button' onclick='zip_info("$f")' value='Comment' class='onethird-button mini' />
							<a href='javascript:void(remove_file("$f"))' >{$ut->icon('delete')}</a>
						</div>
					</td>
				</tr>
EOT;
			}
$buff.= <<<EOT
		</table>
EOT;
	}
	
	return frame_renderer($buff);

}

function backup_common($dir)
{
	global $params,$config,$database,$p_circle,$html;
	global $gallery_url,$gallery_download_url,$ut;

	snippet_dialog();
	
$html['meta'][] = <<<EOT
	<div id="theme_setting" class='onethird-dialog' >
		<div class='title'>Setting</div>
		<div class='onethird-setting'>
		</div>
		<div class='actions' >
			<input type='button' class='onethird-button' value='OK' onclick='save_zip_info()' />
			<input type='button' class='onethird-button' value='Cancel' onclick='ot.close_dialog(this)' />
		</div>
	</div>
	<div id="preview_theme" class='onethird-dialog' >
		<div class='title'>Install</div>
		<div class='onethird-setting'>
		</div>
		<div class='actions' >
			<!--
			<p><label><input type='checkbox' id='p_clean_install' /> Clean install</label></p>
			-->
			<input type='button' class='onethird-button' value='Install' onclick='download_program()' id='dn_btn' />
			<input type='button' class='onethird-button' value='Cancel' onclick='ot.close_dialog(this)' />
		</div>
		<div class='loading' style='display:none;padding: 30px;text-align: center;'>
			<img src='{$config['site_url']}/img/loading.gif' class='onethird-loading' />
		</div>
	</div>
	<script>
	if (!window['ot']) { ot = {}; }

	function preview_theme() {
		\$('#preview_theme .loading').hide();
		\$('#preview_theme .actions').show();
		var a = window.thema_data.file;
		var b = window.thema_data.page;
		\$('#preview_theme .onethird-setting').html('');
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=preview_theme&file="+encodeURIComponent(a)+"&page="+b
			, dataType:'json'
			, success: function(data){
				\$('#preview_theme .onethird-setting').html(data['html']);
				ot.open_dialog(\$('#preview_theme').width(500));
			}
		});
	}

	function download_program() {
		\$('#preview_theme .actions').hide();
		\$('#preview_theme .loading').show();
		var a = \$('#download_program_file').val();
		var b = \$('#download_program_page').val();
		var opt = '';
		if (\$('#preview_theme #p_clean_install:checked').length) {
			opt += "&p_clean_install=true";
		}
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=download_program&file="+encodeURIComponent(a)+"&page="+b+opt
			, dataType:'json'
			, success: function(data){
				ot.close_dialog();
				if (data && data['result']) {
					if (data['_init']) {
						if (!confirm('注意 : 組み込みスクリプトが見つかりました, 実行しますか？')) {
							return;
						}
					}
					ot.ajax({
						type: "POST"
						, url: '{$params['request_name']}'
						, data: "ajax=restore_program&file="+encodeURIComponent(data['file'])
						, dataType:'json'
						, success: function(data){
							if (data && data['result'] ) {
								alert('インストールしました');
							} else {
								if (data['mess']) {
									alert(data['mess']);
								} else {
									alert('インストールエラー');
								}
							}
						}
					});
				} else {
					if (data['mess']) {
						alert(data['mess']);
					} else {
						alert('ダウンロードエラー');
					}
				}
			}
			, error: function(data){
				alert('ダウンロードエラー');
				ot.close_dialog();
			}
		});
	}

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
		var data = \$('#theme_setting .onethird-setting textarea').val();
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

	function remove_file(file) {
		if (confirm('Are you sure you want to delete the backup data?')) {
			ot.ajax({
				type: "POST"
				, url: '{$params['request_name']}'
				, data: "ajax=remove_file&file="+encodeURIComponent(file)
				, dataType:'json'
				, success: function(data){
					if (data && data['result'] ) {
						\$('tr[data-file="'+data['file']+'"]').fadeOut(500);
					} else {
						alert('ロードエラー');
					}
				}
			});
		}
	}

	function backup_data(mode) {
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=backup_data&mode="+mode
			, dataType:'json'
			, success: function(data){
				if (data && data['result']) {
					alert('成功しました');
					location.reload(true);
				} else {
					alert('失敗しました');
				}
			}
		});
	}
	
	</script>
EOT;
	
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'zip_info')  {
		$r = array();
		$r['result'] = true;

		$r['file'] = sanitize_str($_POST['file']);
		$path = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup';
		$dbname = sanitize_str($_POST['file']);
		$file = $path.DIRECTORY_SEPARATOR.$dbname;
		
		$h = "type:theme\nname:\nver:{$config['version']}\n";
		
		$zip = new ZipArchive;
		if ($zip->open($file) === true) {
			$r['comment'] = $zip->getArchiveComment();
			$zip->close();
			if ($r['comment']) {
				$h = $r['comment'];
			}
		}
		$r['html'] = "<textarea style='width:400px;height:100px'>$h</textarea>";
		
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
		if ($zip->open($file) === true) {
			if ($zip->setArchiveComment($r['data'])) {
				$r['result'] = true;
			}
			$zip->close();
		}
		
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

		if ($mode == 1) {
			$zip = new ZipArchive();
			$out = $path.DIRECTORY_SEPARATOR.'program'.$date.'.zip';
			if ($zip->open($out, ZipArchive::CREATE) !== true) {
				echo(json_encode($r));
				exit();
			}
			$ar = glob($params['circle']['files_path']."/data/{*,*/*,*/*/*,/*/*/*/*}",GLOB_BRACE);		// コピーするのは４階層まで
			foreach($ar as $v) {
				if (is_file($v)) {
					$f = substr($v,strlen($params['circle']['files_path']));
					$zip->addFile($v, $f);
				}
			}
			$zip->close();
			$r['result'] = true;
		}
		echo(json_encode($r));
		exit();
	}
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'preview_theme')  {
		$r = array();
		$r['result'] = false;

		if (!isset($_POST['file'])) {
			echo(json_encode($r));
			exit();
		}

		$r['file'] = sanitize_str($_POST['file']);
		$r['page'] = (int)($_POST['page']);
		$u1 = "{$gallery_download_url}{$r['page']}/";
		$u2 = "{$r['file']}";
$r['html'] = <<<EOT
		Download Theme file
		
		<p>URL:</p>
		<p style='font-weight:bold'>$u1</p>
		<p>FILE:</p>
		<p style='font-weight:bold'>$u2</p>
		<input type='hidden' value='{$r['file']}' id ='download_program_file' />
		<input type='hidden' value='{$r['page']}' id ='download_program_page' />
		
EOT;
		
		echo(json_encode($r));
		exit();
	}
	
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'restore_program')  {
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

		$zip = new ZipArchive;
		$r['x'] = $file;
		if ($zip->open($file) !== true) {
			echo(json_encode($r));
			exit();
		}

		$y = $zip->getArchiveComment();
		if ($y) {
			$z = explode("\n",$y);
			$r['type'] = '';
			foreach ($z as $vv) {
				if (substr($vv,0,3) == 'ver') {
					$r['ver'] = substr($vv,4);
					if ($config['version'] < $r['ver']) {
						$r['mess'] = "Current version is too old, Please update.";
						$zip->close();
						@unlink($file);
						echo(json_encode($r));
						exit();
					}
				}
				if (substr($vv,0,4) == 'info') {
					$r['type'] = substr($vv,5);
				}
				if (substr($vv,0,4) == 'type') {
					$r['type'] = substr($vv,5);
				}
				if (substr($vv,0,4) == 'name') {
					$r['name'] = substr($vv,5);
				}
			}
			$r['path'] = $params['circle']['files_path'];
			if ($r['type'] == 'theme') {
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
				$index_tpl = false;
				$preload_theme = false;
				for ($i=0; $i<$zip->numFiles; ++$i) {
					if ($zip->getNameIndex($i) == '/data/index.tpl') {
						$index_tpl = true;
					}
					if ($zip->getNameIndex($i) == '/data/preload.php') {
						$preload_theme = true;
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
			if ($r['type'] == 'plugin') {
				$r['path'] = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'plugin';
			}
			if ($r['type'] == 'system_plugin') {
				$r['path'] = $config['site_path'];
				if (!empty($r['name'])) {
					set_circle_meta($r['name'],true);
				}
			}
			if ($r['type'] == 'system') {
				$r['path'] = $config['site_path'];
			}
		}

		if (is_writable($r['path'])) {
			//auto backup
			if ($r['type'] == 'theme') {
				$file = $path.DIRECTORY_SEPARATOR.'autobackup.zip';
				$zip2 = new ZipArchive;
				$p = $r['path'].DIRECTORY_SEPARATOR;
				if ($zip2->open($file, ZipArchive::CREATE) === true) {
					$zip2->setArchiveComment("type:theme\nname:auto backup\ninfo:autobackup\n");
					for ($i=0; $i<$zip->numFiles; ++$i) {
						$v = $p.$zip->getNameIndex($i);
						if (is_file($v)) {
							$f = substr($v,strlen($p));
							$f = str_replace("\\", '/', $f);
							$zip2->addFile($v, $f);
							$r['autobackup'][] = $f;
						}
					}
					$zip2->close();
				}
			}
			//ファイル上書き展開
			if (@$zip->extractTo($r['path'])) {
				$r['result'] = true;
				check_initp($zip, $r, $r['path']);
			}
			if ($r['type'] == 'theme') {
				set_circle_meta('inline_css',null);
				set_circle_meta('inline_css2',null);
				$v = $r['path'].DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'_inline2.css';
				if (is_file($v)) {
					$x = @file_get_contents($v);
					$x = unserialize64($x);
					if (is_array($x)) {
						set_circle_meta('inline_css2',$x);
					}
					@unlink($v);
					$r['_inline'] = $v;
				}
			}
		}
		$zip->close();

		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'download_program')  {
		$r = array();
		$r['result'] = false;

		if (!isset($_POST['file'])) {
			echo(json_encode($r));
			exit();
		}

		$r['file'] = sanitize_str($_POST['file']);
		$dbname = $r['file'];
		$path = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'backup';
		
		$r['page'] = (int)($_POST['page']);
		$dbname = substr(sanitize_str($_POST['file']),0,-4).".zip";
		$a = file_get_contents("{$gallery_download_url}{$r['page']}/{$dbname}");
		if (!@file_put_contents($path.DIRECTORY_SEPARATOR."{$dbname}",$a)) {
			echo(json_encode($r));
			exit();
		}
		
		$file = $path.DIRECTORY_SEPARATOR.$dbname;
		$pathinfo = pathinfo($file);

		$zip = new ZipArchive;
		$r['x'] = $file;
		if ($zip->open($file) !== true) {
			echo(json_encode($r));
			exit();
		}

		$y = $zip->getArchiveComment();
		if ($y) {
			$r['result'] = true;
			$z = explode("\n",$y);
			$r['type'] = '';
			foreach ($z as $vv) {
				if (substr($vv,0,3) == 'ver') {
					$r['ver'] = substr($vv,4);
					if ($config['version'] < $r['ver']) {
						$r['result'] = false;
						$r['mess'] = "Current version is too old, Please update.";
						$zip->close();
						@unlink($file);
						echo(json_encode($r));
						exit();
					}
				}
				if (substr($vv,0,4) == 'info') {
					$r['type'] = substr($vv,5);
				}
				if (substr($vv,0,4) == 'type') {
					$r['type'] = substr($vv,5);
				}
				if (substr($vv,0,4) == 'name') {
					$r['name'] = substr($vv,5);
				}
			}
			$r['path'] = $params['circle']['files_path'];
			if ($r['type'] == 'theme') {
			}
			if ($r['type'] == 'plugin') {
				$r['path'] = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'plugin';
			}
			if ($r['type'] == 'system_plugin') {
				$r['path'] = $config['site_path'];
				if (!empty($r['name'])) {
					set_circle_meta($r['name'],true);
				}
			}
			if ($r['type'] == 'system') {
				$r['path'] = $config['site_path'];
			}
			if (is_writable($r['path'])) {
				check_initp($zip, $r, $r['path'], true);
			} else {
				$r['result'] = false;
			}
		}

		$zip->close();

		echo(json_encode($r));
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

function check_initp(&$zip, &$r, $p, $check = false)
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
				if (function_exists('install_script')) {
					if (install_script() === false) {
						$r['result'] = false;
						$r['mess'] = 'Install script error';
					}
				}
				@unlink($v);
				$r['_init'] = $v;
			}
		}
	}
}

?>