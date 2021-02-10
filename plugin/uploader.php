<?php
define('UPLOADER_PLUGIN_SALT','');

	global $params;
	
	$params['mimeTypes'] = array(
		 'exe'   => 'application/octet-stream'
		,'doc'   => 'application/vnd.ms-word'
		,'docx'  => 'application/vnd.ms-word'
		,'xls'   => 'application/vnd.ms-excel'
		,'xlsx'  => 'application/vnd.ms-excel'
		,'mdb'   => 'application/vnd.msaccess'
		,'ppt'   => 'application/vnd.ms-powerpoint'
		,'pps'   => 'application/vnd.ms-powerpoint'
		,'pdf'   => 'application/pdf'
		,'odt'   => 'application/vnd.oasis.opendocument.text'
		,'swf'   => 'application/x-shockwave-flash'
		,'gz'    => 'application/x-gzip'
		,'tgz'   => 'application/x-gzip'
		,'bz'    => 'application/x-bzip2'
		,'bz2'   => 'application/x-bzip2'
		,'tbz'   => 'application/x-bzip2'
		,'zip'   => 'application/zip'
		,'rar'   => 'application/x-rar'
		,'tar'   => 'application/x-tar'
		,'7z'    => 'application/x-7z-compressed'
		,'txt'   => 'text/plain'
		,'bmp'   => 'image/x-ms-bmp'
		,'jpg'   => 'image/jpeg'
		,'jpeg'  => 'image/jpeg'
		,'gif'   => 'image/gif'
		,'png'   => 'image/png'
		,'tif'   => 'image/tiff'
		,'tiff'  => 'image/tiff'
		,'tga'   => 'image/x-targa'
		,'psd'   => 'image/vnd.adobe.photoshop'
		,'mp3'   => 'audio/mpeg'
		,'mid'   => 'audio/midi'
		,'ogg'   => 'audio/ogg'
		,'mp4a'  => 'audio/mp4'
		,'wav'   => 'audio/wav'
		,'wma'   => 'audio/x-ms-wma'
		,'avi'   => 'video/x-msvideo'
		,'mp4'   => 'video/mp4'
		,'mpeg'  => 'video/mpeg'
		,'mpg'   => 'video/mpeg'
		,'mov'   => 'video/quicktime'
		,'wm'    => 'video/x-ms-wmv'
		,'flv'   => 'video/x-flv'
		,'mkv'   => 'video/x-matroska'
		,'csv'   => 'text/comma-separated-values'
		,'svg'   => 'image/svg+xml'
		,'js'   => 'application/x-javascript'
		,'wav'   => 'audio/wav'
		,'webp'   => 'image/webp'
	);

function get_uploader_state_ar()
{
	return array(
		'0'=>'非公開'
		, '1'=>'公開'
		, '2'=>'ログインユーザー'
		, '3'=>'ワンタイム'
		, '99'=>'削除'
	);
}

function check_uploader_admin_rights( &$page_ar )
{
	$show = check_rights('edit');
	if (isset($page_ar['meta']['plugin']['uploader']['rights']) && $page_ar['meta']['plugin']['uploader']['rights']) {
		if (check_rights() && $page_ar['meta']['plugin']['uploader']['rights'] == 1) {
			$show = true;
		} else if ($page_ar['meta']['plugin']['uploader']['rights'] == 3) {
			$show = true;
		}
	}
	return $show;
}

function uploader_renderer( &$page_ar )
{
	global $params, $ut, $config, $plugin_ar;

	$buff = '';
	$id = $page_ar['id'];

	if (!isset($page_ar['meta']['plugin']['uploader'])) {
		$page_ar['meta']['plugin']['uploader']['rights'] = 0;
		for ($i=1; $i < 10; ++$i) {
			$name = $page_ar['meta']['plugin']['uploader']['name'] = "file{$page_ar['id']}_{$i}";
			$dir = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$name;
			if (!is_dir($dir)) {
				$x = array();
				$x['id'] = $id;
				$x['meta']['plugin']['uploader'] = $page_ar['meta']['plugin']['uploader'];
				if (!mod_data_items($x)) {
					system_error( __FILE__, __LINE__ );
				}
				break;
			}
		}
	}
	$show = check_uploader_admin_rights( $page_ar );
	if (isset($plugin_ar[$page_ar['type']]['writer']) && !is_array($plugin_ar[$page_ar['type']]['writer'])) {
		$buff .= $plugin_ar[$page_ar['type']]['writer']($page_ar,'_uploader_proc');
	} else {
		if (!isset($page_ar['meta']['plugin']['uploader']['writer'])) {
			//$buff .= std_uploader_writer($page_ar);
			$buff .= uploader_ez_writer($page_ar,'_uploader_proc');
		} else {
			$buff .= $page_ar['meta']['plugin']['uploader']['writer']($page_ar,'_uploader_proc');
		}
	}
	if ($show) {
		if (!check_rights('edit')) {
			//アップロード権限をpublicにする場合、一般ユーザーにも権限を権限を開放する
			//不要にprovide_onethird_objectを呼び出すことは危険なので注意すること
			if (!check_rights()) {
				provide_onethird_object();
			}
			provide_edit_rights();
			snippet_dialog();
		}
		$buff .= uploader_setting($page_ar);
	}

	//管理者メニュー
	if (check_rights('owner')) {
$buff .= <<<EOT
		<div class='edit_pointer'>
EOT;
			$buff .= std_blockmenu_renderer($page_ar);
$buff .= <<<EOT
			<a href='javascript:void(ot.uploader_setting({$page_ar['id']}))' >{$ut->icon('setting')}</a>
		</div>
EOT;
	}
	
	return frame_renderer($buff,'uploader-plugin-unit');
}

function std_uploader_writer( &$page_ar, $uploader_proc )
{
	global $params, $ut, $html, $config;
	$buff = '';

$html['css'][] = <<<EOT
	<style>
		.std_uploader_writer .stat {
			border: 1px solid #818181;
			padding: 0 6px 2px 6px;
			font-size: 60%;
			border-radius: 10px;
			background-color: #C0C0C0;
		}
	</style>
EOT;

	if (!isset($page_ar['meta']['plugin']['uploader']['name'])) {
		return '';
	}
$buff .= <<<EOT
	<div class='std_uploader_writer'>
EOT;
		$name = $page_ar['meta']['plugin']['uploader']['name'];
		$path = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$name;
		$toc = $path.DIRECTORY_SEPARATOR."index.toc";
		if (is_file($toc)) {
			$ar = unserialize64(file_get_contents($toc));
			$note = false;
			foreach ($ar as $idx=>$v) {
				if (!empty($v['note'])) {
					$note = true;
				}
			}
$buff .= <<<EOT
			<table class='onethird-table std_uploader_writer'>
				<tr>
					<th>Date</th><th>File Name</th><th>Size</th>
EOT;
					if ($note) {
$buff .= <<<EOT
						<th>Note</th>
EOT;
					}
$buff .= <<<EOT
				</tr>
EOT;
				$start_ar = get_uploader_state_ar();
				foreach ($ar as $idx=>$v) {
					if (!check_rights() && $v['status'] != 1) {
						continue;
					}
					$stat = '--';
					if (isset($v['part'])) {
						$stat = 'uploading';
					}
					if (!isset($v['size'])) {
						$v['size'] = '--';
					} else {
						if ($v['size'] > 1024*1024) {
							$v['size'] = (int)($v['size']/1024/1024)."MB";
						} else {
							$v['size'] = (int)($v['size']/1024)."KB";
						}
						if ($v['size'] == 0) { $v['size'] = '1KB'; }
					}
					if (!isset($v['status'])) {
						$v['status'] = 0;
					}
					if (!isset($v['note'])) {
						$v['note'] = '';
					}
					$stat = "<span class='stat'>{$start_ar[$v['status']]}</span>";
					$d = $v['date'];
					$d = str_replace("00:00:00", '', $d);
$buff .= <<<EOT
					<tr data-x='$idx'>
						<td>{$d}</td>
						<td><a href='{$ut->link($page_ar['id'],'&:file='.$idx)}'>{$v['name']}</a>
EOT;
						if (check_rights('admin') || (check_rights() && isset($v['user']) && $v['user'] == $_SESSION['login_id'])) {
$buff .= <<<EOT
							<a href='javascript:void(ot.plugin_uploader.file_remove({$page_ar['id']},"{$idx}"))'>{$ut->icon('delete',"style='width:12px'")}</a>
							<a href='javascript:void(ot.plugin_file_note({$page_ar['id']},"{$idx}"))'>{$ut->icon('edit',"style='width:14px'")}</a>
EOT;
						}
						if (check_rights()) {
$buff .= <<<EOT
							{$stat}
EOT;
						}
$buff .= <<<EOT
						</td>
						<td>{$v['size']}</td>
EOT;
						if ($note) {
$buff .= <<<EOT
							<td>{$v['note']}</td>
EOT;
						}
$buff .= <<<EOT
					</tr>
EOT;
				}
$buff .= <<<EOT
			</table>
EOT;
		}
		$buff .= $uploader_proc($page_ar,0);	//hiddenでアップロード
$buff .= <<<EOT
	</div>
EOT;
	return $buff;
}

function uploader_ez_writer( &$page_ar, $uploader_proc )
{
	global $params, $ut, $html, $config;
	$buff = '';
	
	if ($page_ar['mode'] == 0) {
		$page_ar['mode'] = 1;
		mod_data_items($page_ar);
	}
	
$html['css'][] = <<<EOT
	<style>
		.uploader_ez_writer .stat {
			border: 1px solid #818181;
			padding: 0 6px 2px 6px;
			font-size: 60%;
			border-radius: 10px;
			background-color: #C0C0C0;
		}
	</style>
EOT;

	if (!isset($page_ar['meta']['plugin']['uploader']['name'])) {
		return '';
	}
$buff .= <<<EOT
	<div class='uploader_ez_writer'>
EOT;
		$name = $page_ar['meta']['plugin']['uploader']['name'];
		$path = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$name;
		$toc = $path.DIRECTORY_SEPARATOR."index.toc";
		if (is_file($toc)) {
			$ar = unserialize64(file_get_contents($toc));
			$start_ar = get_uploader_state_ar();
			foreach ($ar as $idx=>$v) {
				if (!isset($v['size'])) {
					$v['size'] = '--';
				} else {
					if ($v['size'] > 1024*1024) {
						$v['size'] = (int)($v['size']/1024/1024)."MB";
					} else {
						$v['size'] = (int)($v['size']/1024)."KB";
					}
					if ($v['size'] == 0) { $v['size'] = '1KB'; }
				}
				if (!empty($v['name'])) {
					$name = $v['name'];
				}
				if (!empty($v['note'])) {
					$name = $v['note'];
				}
				$stat = $start_ar[$v['status']];
				if (!check_rights('edit') && $v['status'] != 1 && $v['status'] != 3 && !(check_rights() && $v['status'] == 2)) {
					continue;
				}
$buff .= <<<EOT
				<p data-x='$idx'>
					{$ut->substr($v['date'],0,10)}
					<a href='{$ut->link($page_ar['id'],'&:download='.$idx)}'>{$name} </a>
					({$v['size']})
EOT;
						if (check_rights('admin') || check_rights() && isset($v['user']) && $v['user'] == $_SESSION['login_id']) {
$buff .= <<<EOT
							<a href='javascript:void(ot.plugin_uploader.file_remove({$page_ar['id']},"{$idx}"))'>{$ut->icon('delete',"style='width:14px'")}</a>
							<a href='javascript:void(ot.plugin_file_note({$page_ar['id']},"{$idx}"))'>{$ut->icon('edit',"style='width:14px'")}</a>
							<span class='stat'>{$stat}</span>
EOT;
						}
$buff .= <<<EOT
				</p>
EOT;
			}
		}
		$buff .= $uploader_proc($page_ar,1);	// publicで公開
$buff .= <<<EOT
	</div>
EOT;
	return $buff;
}

function _uploader_proc(&$page_ar,$status)
{
	global $html, $params, $config, $ut;

	if (!check_uploader_admin_rights($page_ar)) {
		return '';
	}

	if (!isset($page_ar['meta']['plugin']['uploader']['rights'])) {
		$page_ar['meta']['plugin']['uploader']['rights'] = 0;
	}

	if (isset($page_ar['meta']['plugin']['uploader']['hide_addbtn'])) {
		return '';
	}
	$rights = 'edit';
	if ($page_ar['meta']['plugin']['uploader']['rights'] == 3 ) {
		$rights = 'public';
	}
	if ($page_ar['meta']['plugin']['uploader']['rights'] == 1) {
		$rights = 'login user';
	}

$buff = <<<EOT
	<p>
		<span class='stat'>$rights</span> <input type="file" id="upload_button{$page_ar['id']}" name="files[]" multiple onchange='ot.plugin_uploader.upload(event,{$page_ar['id']},{$status})' style='display:inline' />
		<span id="uploading_ind{$page_ar['id']}" class='uploading_ind'></span>
	</p>
EOT;

$tmp = <<<EOT
<script>
	if (!ot.plugin_uploader) { ot.plugin_uploader = {}; }
	ot.plugin_uploader.upload = function(event,p_page,p_status) {
		\$('.uploader-plugin-unit #upload_button'+p_page).hide();
		\$('.uploader-plugin-unit #uploading_ind'+p_page).text('uploading ...');
		var files = event.target.files; // FileList object
		ot.plugin_uploader.reader_ar = [];
		for (var i = 0; i < files.length; i++) {
			var file = files[i];
			ot.plugin_uploader.reader_ar[i] = new FileReader();
			ot.plugin_uploader.reader_ar[i].readAsDataURL(file);
			ot.plugin_uploader.reader_ar[i].fname = file.name;
			ot.plugin_uploader.reader_ar[i].p_page = p_page;
			ot.plugin_uploader.reader_ar[i].p_status = p_status;
			ot.plugin_uploader.reader_ar[i].p_uniqid = {$_SERVER['REQUEST_TIME']};
			ot.plugin_uploader.reader_ar[i].onload = function (event) {
				if (event.target.result.substr(0,5) == 'data:') {
					var i=1;
					for(; i < this.result.length; ++i) {
						if (this.result[i] == ';') { break; }
					}
					if (this.result.substr(i,8) == ';base64,') {
						this.data_type = this.result.substr(5,i-5);
						i+=8;
						this.data_64 = [];
						var total = this.result.length-i;
						var part_size = 1024*500*4;	//paddingのために４の倍数
						this.data_part = 0;
						for (; i < total; i+=part_size) {
							this.data_64.push({'id':this.data_part, 'data':this.result.substr(i,part_size)});
							++this.data_part;
						}
						setTimeout('ot.plugin_uploader.timer_upload()',100);
					}
				}
			}
		}
	};
	ot.plugin_uploader.timer_upload = function() {
		if (!ot.plugin_uploader.reader_ar.length) {
			\$('.uploader-plugin-unit .upload_button').show();
			\$('.uploader-plugin-unit .uploading_ind').text('');
			return;
		}
		var x = ot.plugin_uploader.reader_ar[0];
		var y = x.data_64.shift();
		\$('.uploader-plugin-unit #uploading_ind'+x.p_page).text('uploading...('+(x.data_part-x.data_64.length-1)+'/'+x.data_part+')');
		if (y) {
			opt = "&idx="+y.id+"&part="+(x.data_64.length+1)+"&data="+encodeURIComponent(y.data)+"&page="+x.p_page+"&status="+x.p_status+"&uniqid="+x.p_uniqid;
			ot.ajax({
				type: "POST"
				, url: '{$params['safe_request']}'
				, data: "ajax=plugin_upload_part&name="+encodeURIComponent(x.fname)+opt
				, dataType:'json'
				, success: function(data){
					if ( data && data['result'] ) {
						setTimeout('ot.plugin_uploader.timer_upload()',100);
					} else {
						if (data['mess']) {
							alert(data['mess']);
						} else {
							alert("upload failed.");
						}
						location.reload(true);
					}
				}
			});
		}
		if (!y) {
			ot.plugin_uploader.reader_ar.shift();
			if (ot.plugin_uploader.reader_ar.length) {
				ot.plugin_uploader.timer_upload();
			} else {
				location.reload(true);
			}
		}
	};
</script>
EOT;
	$html['meta']['_uploader_proc'] = $tmp;

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'plugin_upload_part')  {
		$r = array();
		$r['result'] = false;
		$r['name'] = sanitize_str($_POST['name']);
		$r['idx'] = (int)$_POST['idx'];
		$r['uniqid'] = (int)$_POST['uniqid'];
		$r['part'] = (int)$_POST['part'];
		$r['page'] = (int)$_POST['page'];
		$r['status'] = (int)$_POST['status'];
		$ar = array();
		read_pagedata($r['page'],$ar);
		if (!isset($ar['meta']['plugin']['uploader']['name'])) {
			system_error( __FILE__, __LINE__ );
		}
		$name = $ar['meta']['plugin']['uploader']['name'];
		$path_parts = pathinfo($r['name']);
		$ext = strtolower($path_parts['extension']);
		if (!isset($params['mimeTypes'][$ext])) {
			$r['mess'] = "File type error($ext)";
			echo(json_encode($r));
			exit();
		}

		$path = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$name;
		$toc = $path.DIRECTORY_SEPARATOR."index.toc";
		if ($r['name'] == "index.toc") {
			system_error( __FILE__, __LINE__ );
		}

		if (!@is_dir($path)) {
			if (!@mkdir($path,$config['permission'],true)) {
				echo( json_encode($r) );
				exit();
			}
$tmp =  <<<EOT

order deny,allow
deny from all

EOT;
			$a = $path.DIRECTORY_SEPARATOR.'.htaccess';
			if (!file_put_contents($a, $tmp)) {
				system_error( __FILE__, __LINE__ );
			}
		}
		
		if (!is_file($toc)) {
			$to_contents = array();
		} else {
			$to_contents = unserialize64(file_get_contents($toc));
		}

		$key = md5(UPLOADER_PLUGIN_SALT.$r['name'].$r['uniqid']);

		if ($r['idx'] == 0 && !check_rights() && isset($to_contents[$key])) {
			//非ログイン状態では、上書きを許可しない
			echo(json_encode($r));
			exit();
		}

		$data = base64_decode($_POST['data']);
		$m = 'ab';
		if ($r['idx'] == 0) {
			$m = 'wb';
		}
		if (!$out = @fopen($path.DIRECTORY_SEPARATOR.$key, $m)) {
			echo(json_encode($r));
			exit();
		}
		$r['result'] = @fwrite($out, $data);
		@fclose($out);

		if ($r['part'] <= 1) {
			unset($to_contents[$key]['part']);
			$to_contents[$key]['size'] = filesize ($path.DIRECTORY_SEPARATOR.$key);
		} else {
			$to_contents[$key]['part'] = $r['part'];
		}
		$to_contents[$key]['name'] = $r['name'];
		$to_contents[$key]['date'] = $params['now'];
		if (!check_rights()) {
			$to_contents[$key]['status'] = 1;	// アップロードユーザーが非ログイン時にはPublicに自動調整
		} else {
			$to_contents[$key]['status'] = $r['status'];
		}
		if (!isset($_SESSION['login_id'])) {
			$to_contents[$key]['user'] = $params['page']['user'];
		} else {
			$to_contents[$key]['user'] = $_SESSION['login_id'];
		}
		$to_contents[$key]['user_name'] = $params['login_user']['nickname'];
		function save_plugin_file_note_cmp($a, $b) {
			return strcasecmp($b['date'], $a['date']);
		}
		uasort($to_contents, "save_plugin_file_note_cmp");

		$r['result'] = $r['result'] && file_put_contents($toc, serialize64($to_contents));

		echo(json_encode($r));
		exit();
	}

	return $buff;
}

function uploader_setting( &$page_ar )
{
	global $params, $ut, $html, $config, $plugin_ar;
	
	if (!check_rights()) {
		return;
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'uploader_setting')  {
		if ((int)$_POST['id'] != $page_ar['id']) {
			return;		// 複数の同種プラグインに対応するため
		}
		$r = array();
		$r['result'] = true;
		$r['name'] = '';
		$r['rights'] = 0;
		if (isset($page_ar['meta']['plugin']['uploader']['name'])) {
			$r['name'] = $page_ar['meta']['plugin']['uploader']['name'];
		}
		if (isset($page_ar['meta']['plugin']['uploader']['rights'])) {
			$r['rights'] = $page_ar['meta']['plugin']['uploader']['rights'];
		}
		$c2 = (isset($page_ar['meta']['plugin']['uploader']['hide_addbtn']))? ' checked ' : '';
		$rights_ar = array('0'=>'edit','1'=>'login user','3'=>'public');
		if (!isset($page_ar['meta']['plugin']['uploader']['writer'])) {
			$page_ar['meta']['plugin']['uploader']['writer'] = 'uploader_ez_writer';
		}
$r['html'] = <<<EOT
		<table>
			<tr>
				<td >Writer</td>
				<td>
					{$ut->input(array(
						'type'=>'select'
						, 'data-input'=>'writer'
						, 'value'=>$page_ar['meta']['plugin']['uploader']['writer']
						, 'option'=>$plugin_ar[ UPLOADER_ID ]['writer']
					))}
				</td>
			</tr>
			<tr>
				<td>保存先フォルダ名</td>
				<td><input type='text' data-input='name' value='{$r['name']}' />
				</td>
			</tr>
			<tr>
				<td>Upload Rights</td>
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
				<td>Option</td>
				<td>
					<label><input type='checkbox' data-input='hide_addbtn' $c2 />Hide file button</label>
				</td>
			</tr>
		</table>
EOT;
		echo(json_encode($r));
		exit();
	}
	snippet_std_setting('Uploader Setting','uploader_setting');


$tmp = <<<EOT
<script>
	if (!ot.plugin_uploader) { ot.plugin_uploader = {}; }
	ot.plugin_uploader.file_remove = function(page,idx) {
		if ( !confirm("Are you sure you want to delete this?") ) {
			return;
		}
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=plugin_uploader_file_remove&page="+page+"&idx="+idx
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					location.reload(true);
				} else {
					alert('Error');
				}
			}
		});
	};
</script>
EOT;
	$html['meta'][] = $tmp;
	
	snippet_std_setting('Uploader Item Setting','plugin_file_note');
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'plugin_file_note')  {
		$r = array();
		$r['note'] = '';
		if (isset($page_ar['meta']['plugin']['uploader']['name'])) {
			$name = $page_ar['meta']['plugin']['uploader']['name'];
			$path = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$name;
			$toc = $path.DIRECTORY_SEPARATOR."index.toc";
			if (is_file($toc)) {
				$ar = unserialize64(file_get_contents($toc));
				if (!empty($_POST['data']) && !empty($ar[$_POST['data']])) {
					$r['meta'] = $v = $ar[$_POST['data']];
					if (isset($r['meta']['note'])) {
						$r['note'] = $r['meta']['note'];
					}
					$r['result'] = true;
				}
			}
		}
		$rights_ar = get_uploader_state_ar();
$r['html'] = <<<EOT
		<table>
			<tr>
				<td >Dowmload rights</td>
				<td>
					{$ut->input(
						array('type'=>'select'
							, 'data-input'=>'status'
							, 'value'=>$r['meta']['status']
							, 'option'=>$rights_ar
						)
					)}
				</td>
			</tr>
			<tr>
				<td >Note</td>
				<td>
					<input type='text' value='{$ut->safe_echo($r['note'],false)}' data-input='note' style='width:300px' />
				</td>
			</tr>
			<tr>
				<td >Date</td>
				<td>
					<input type='text' value='{$r['meta']['date']}' data-input='date'  />
				</td>
			</tr>
		</table>
EOT;
		echo(json_encode($r));
		exit();
	}
	
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'save_plugin_file_note')  {
		$r = array();
		$r['result'] = false;
	 
		if (isset($_POST['note'])) {
			if (isset($page_ar['meta']['plugin']['uploader']['name'])) {
				$name = $page_ar['meta']['plugin']['uploader']['name'];
				$path = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$name;
				$toc = $path.DIRECTORY_SEPARATOR."index.toc";
				if (is_file($toc)) {
					$ar = unserialize64(file_get_contents($toc));
					if (!empty($_POST['data']) && !empty($ar[$_POST['data']])) {
						//保存処理
						$ar[$_POST['data']]['note'] = sanitize_str($_POST['note']);
						$ar[$_POST['data']]['date'] = sanitize_date($_POST['date']);
						$ar[$_POST['data']]['status'] = (int)($_POST['status']);
						
						function save_plugin_file_note_cmp($a, $b) {
							return strcasecmp($b['date'], $a['date']);
						}
						uasort($ar, "save_plugin_file_note_cmp");

						if (file_put_contents($toc,serialize64($ar))) {
							$r['result'] = true;
						}
					}
				}
			}
		}
	 
		echo(json_encode($r));
		exit();
	}	

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'save_uploader_setting')  {
		if ((int)$_POST['id'] != $page_ar['id']) {
			return;		// 複数の同種プラグインに対応するため
		}
		$r = array();
		$r['result'] = false;

		$ar = array();
		$ar['id'] = $page_ar['id'];

		if (isset($_POST['rights'])) {
			$r['rights'] = sanitize_asc($_POST['rights']);
			$ar['meta']['plugin']['uploader']['rights'] = $r['rights'];
		}
		if (isset($_POST['hide_addbtn'])) {
			$ar['meta']['plugin']['uploader']['hide_addbtn'] = true;
		} else {
			unset($ar['meta']['plugin']['uploader']['hide_addbtn']);
		}
		if (isset($_POST['writer'])) {
			$ar['meta']['plugin']['uploader']['writer'] = sanitize_asc($_POST['writer']);
		} else {
			unset($ar['meta']['plugin']['uploader']['writer']);
		}
		if (isset($_POST['name'])) {
			$r['name'] = sanitize_asc($_POST['name']);
			if ($r['name']) {
				if (isset($page_ar['meta']['plugin']['uploader']['name'])) {
					$name = $page_ar['meta']['plugin']['uploader']['name'];
					$p = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR;
					$path1 = $p.$name;
					$path2 = $p.$r['name'];
					if ($path1 != $path2) {
						if (@rename($path1, $path2)) {
							$ar['meta']['plugin']['uploader']['name'] = $r['name'];
						} else {
							echo(json_encode($r));
							exit();
						}
					}
				}
				$ar['meta']['plugin']['uploader']['name'] = $r['name'];
			} else {
				unset($ar['meta']['plugin']['uploader']['name']);
			}
		} else {
			unset($ar['meta']['plugin']['uploader']['name']);
		}
		if (mod_data_items($ar)) {
			$r['result'] = true;
		}
		echo(json_encode($r));
		exit();
	}
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'plugin_uploader_file_remove')  {
		$r = array();
		$r['result'] = false;
		$r['page'] = (int)$_POST['page'];
		$ar = array();
		read_pagedata($r['page'],$ar);
		if (isset($ar['meta']['plugin']['uploader']['name'])) {
			$name = $ar['meta']['plugin']['uploader']['name'];
			$path = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$name;
			$toc = $path.DIRECTORY_SEPARATOR."index.toc";
			if (is_file($toc)) {
				$toc_ar = unserialize64(file_get_contents($toc));
				$r['idx'] = sanitize_str($_POST['idx']);
				if (check_rights('admin') || (isset($toc_ar[$r['idx']]['user']) && $toc_ar[$r['idx']]['user'] == $_SESSION['login_id'])) {
					unset($toc_ar[$r['idx']]);
					if (is_file($path.DIRECTORY_SEPARATOR.$r['idx']) && @unlink($path.DIRECTORY_SEPARATOR.$r['idx'])) {
					}
				} else {
					$r['rights-error'] = false;
				}
				$r['result'] = file_put_contents($toc, serialize64($toc_ar));
			}
		} else {
		}
		echo( json_encode($r) );
		exit();
	}
}

function uploader_onbefore_remove(&$page_ar)
{
	global $config;
	
	if (isset($page_ar['meta']['plugin']['uploader']['name'])) {
		$name = $page_ar['meta']['plugin']['uploader']['name'];
		$path = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$name;
		remove_directory($path);
	}
	
}

function uploader_page(&$page_ar)
{
	global $params,$config,$html,$database,$ut,$p_circle;
	
	$buff = '';
	if (!isset($page_ar['meta']['plugin']['uploader']['name'])) {
		exit_proc();
	}
	
	$name = $page_ar['meta']['plugin']['uploader']['name'];
	$path = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$name;
	$toc = $path.DIRECTORY_SEPARATOR."index.toc";

	if (!is_file($toc)) {
		exit_proc();
	}
	$toc_ar = unserialize64(file_get_contents($toc));

	$v = false;
	if (isset($_GET['download']) && isset($toc_ar[$_GET['download']])) {
		$key = sanitize_str($_GET['download']);
		$v = $toc_ar[$key];
	}
	
	if (isset($_GET['file']) && isset($toc_ar[$_GET['file']])) {
		$key = sanitize_str($_GET['file']);
		$v = $toc_ar[$key];
	}
	if (!$v) {
		exit_proc();
	}
	if (isset($_SESSION['login_id']) && ($_SESSION['login_id'] == $page_ar['user'] || $_SESSION['login_id'] == $v['user'])) {
	} else {
		if (check_rights('edit') || (check_rights() && isset($page_ar['meta']['plugin']['uploader']['rights']) && $page_ar['meta']['plugin']['uploader']['rights'] >=1) ) {
		} else {
			$exit = false;
			if (!isset($v['status']) || !$v['status']) {
				$exit = true;
			} else if ($v['status'] == 1 || $v['status'] == 3) {
			} else if (check_rights() && $v['status'] == 2) {
			} else {
				$exit = true;
			}
			if ($exit) {
				if (isset($page_ar['meta']['plugin']['uploader']['rights']) && $page_ar['meta']['plugin']['uploader']['rights']==3) {
				} else {
					exit_proc();
				}
			}
		}
	}

	if (isset($_GET['download'])) {
		//ダウンロード処理
		$dkey = sanitize_str($_GET['download']);
		if (isset($v['status']) && $v['status'] == 3) {
			$toc_ar[$dkey]['status'] = 0;
			file_put_contents($toc, serialize64($toc_ar));
		}
		if (check_rights()) {
			if (!isset($toc_ar[$dkey]['dl_count'])) {
				$toc_ar[$dkey]['dl_count'] = 1;
			} else {
				++$toc_ar[$dkey]['dl_count'];
			}
		} else {
			if (!isset($toc_ar[$dkey]['dl_count_ex'])) {
				$toc_ar[$dkey]['dl_count_ex'] = 1;
			} else {
				++$toc_ar[$dkey]['dl_count_ex'];
			}
		}
		file_put_contents($toc, serialize64($toc_ar));
		return _uploader_file($path.DIRECTORY_SEPARATOR.$key, $v['name']);
	}

	$stat = 'hidden';
	if (isset($v['part'])) {
		$stat = 'uploading';
	}
	if (!isset($v['size'])) {
		$v['size'] = '--';
	} else {
		if ($v['size'] > 1024*1024) {
			$v['size'] = (int)($v['size']/1024/1024)."MB";
		} else {
			$v['size'] = (int)($v['size']/1024)."KB";
		}
	}
	if (!isset($v['status'])) { $v['status'] = 0; }
	$idx = md5(UPLOADER_PLUGIN_SALT.$v['name']);
	
	snippet_breadcrumb($page_ar['link'], $v['name']);
	
	$dl_count = '';
	if (!isset($v['dl_count_ex'])) {
		if (isset($v['dl_count'])) {
			$dl_count = $v['dl_count'];
		}
	} else {
		if (isset($v['dl_count'])) {
			$dl_count = "member download : ".$v['dl_count'];
		}
		if (isset($v['dl_count_ex'])) {
			if ($dl_count) { $dl_count .=" , ";}
			$dl_count .= "guest download : ".$v['dl_count_ex'];
		}
	}
	if (!$dl_count) { $dl_count ="0";}

	$start_ar = get_uploader_state_ar();
	$name = $v['name'];
	if (!empty($v['note'])) {
		$name = $v['note'];
	}
$buff .= <<<EOT
	<div class='onethird-setting' id='plugin-download-panel' >
		<h1>{$name}</h1>
		<table>
			<tr>
				<td>ID<td><td>$idx</td>
			</tr>
			<tr>
				<td>File Name<td><td>{$v['name']}</td>
			</tr>
			<tr>
				<td>File Date<td><td>{$v['date']}</td>
			</tr>
			<tr>
				<td>File Size<td><td>{$v['size']}</td>
			</tr>
EOT;
			if (check_rights('edit')) {
$buff .= <<<EOT
				<tr>
					<td>File Status<td>
					<td>
						<select id='status' style='width:200px;'>
EOT;
							foreach ($start_ar as $k=>$vv) {
								($v['status'] == $k)? $s = 'selected': $s = '';
$buff .= <<<EOT
								<option value='$k' $s>{$vv}</option>
EOT;
							}
$buff .= <<<EOT
						</select> <input type='button' value='Update' onclick='ot.mod_plugin_download()' />
					</td>
				</tr>
EOT;
			}
			if (check_rights()) {
$buff .= <<<EOT
				<tr>
					<td>Download count<td><td>{$dl_count}</td>
				</tr>
EOT;
			}
$buff .= <<<EOT
			<tr>
				<td><td>
				<td>
					<input type='button' onclick='ot.plugin_download()' value='Download Now' class='onethird-button large' />
				</td>
			</tr>
EOT;
$buff .= <<<EOT
		</table>
	</div>
EOT;

$html['meta'][] = <<<EOT
		<script>
		if (!window.ot) {
			ot = {};
		}
		ot.mod_plugin_download = function () {
			var opt = "ajax=mod_plugin_download";
			if (!\$('#plugin-download-panel #status').length) {
				return;
			}
			opt += "&status="+\$('#plugin-download-panel #status').val();
			ot.ajax({
				type: "POST"
				, url: '{$params['safe_request']}'
				, data: opt
				, dataType:'json'
				, success: function(data){
					if ( data && data['result'] ) {
						if (data['url']) {
							location.href=data['url'];
						} else {
							location.reload(true);
						}
					} else {
						alert('update failed');
					}
				}
			});
		};
		ot.plugin_download = function () {
			location.href="{$ut->link($page_ar['id'],"&:download=".$_GET['file'])}";
		};
		</script>
EOT;
	if ( isset($_POST['ajax']) && $_POST['ajax'] == 'mod_plugin_download')  {
		$r = array();
		$r['result'] = false;
		$idx = sanitize_str($_GET['file']);
		if (!isset($_POST['status'])) {
			echo( json_encode($r) );
			exit();
		}
		if ($_POST['status'] == 99) {
			if (@unlink($path.DIRECTORY_SEPARATOR.$idx)) {
				unset($toc_ar[$idx]);
				$r['url'] = $ut->link($page_ar['link']);
			} else {
				$r['result'] = false;
			}
		} else {
			$toc_ar[$_GET['file']]['status'] = $r['status'] = (int)$_POST['status'];
		}
		$r['result'] = file_put_contents($toc, serialize64($toc_ar));

		echo( json_encode($r) );
		exit();
	}

	//管理者メニュー
	if ( check_rights('owner') ) {
$buff .= <<<EOT
		<div class='edit_pointer'>
EOT;
			$buff .= std_blockmenu_renderer($page_ar);
$buff .= <<<EOT
			<a href='javascript:void(ot.add_page({$page_ar['id']}))' >{$ut->icon('add')}</a>
		</div>
EOT;
	}
	$buff .= innerpage_renderer($page_ar['id']);
	return frame_renderer($buff);
}

function _uploader_file($file, $name)
{
	global $params;
	
	$path_parts = pathinfo($name);
	$file_name = basename($name);
	$ext = strtolower($path_parts['extension']);
	$fname = $path_parts['filename'];
	
	if (!isset($params['mimeTypes'][$ext])) {
		exit_proc(404, "File type error ({$file_name})");
	}

	if (!is_file($file)) {
		exit();
	}

	header("Content-Type: {$params['mimeTypes'][$ext]}");
	header('Content-Length: '.filesize($file));
	header("Content-Disposition: attachment; filename=\"{$file_name}\"");

	

	$file_handle = fopen($file, "rb");
	while (!feof($file_handle)) {
		echo fread($file_handle,1024*1024);
		ob_flush();
		flush();
	}

	fclose($file_handle);
	exit();
}

function oncreate_uploader(&$page_ar)
{
	$page_ar['mode'] = 1;
	return mod_data_items($page_ar);
}

// アップローダータグ用
function ez_uploader( &$arg )
{
	return uploader( $arg );
}
function uploader( &$arg )
{
	global $html, $params;
	if (isset($_GET['file']) || isset($_GET['download'])) {
		$html['article'] = array(uploader_page($params['page']));
		$params['rendering'] = false;
		return '';
	}
	return uploader_renderer($params['page']);
}

?>