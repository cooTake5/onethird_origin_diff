<?php

function bbs2_renderer( &$page_ar )
{
	global $params, $ut;
	$buff = '';
	$p_page = $page_ar['id'];
	
	$arg = array();
	$buff = bbs2($arg);
	if ( $buff ) {
		//管理者メニュー
		if ( check_rights('owner') && $p_page ) {
$buff .= <<<EOT
			<div class='edit_pointer'>
EOT;
				$buff .= std_blockmenu_renderer($page_ar);
$buff .= <<<EOT
			</div>
EOT;
		}

	}
	return frame_renderer($buff);
}

function bbs2($arg)
{
	global $params,$database,$config;
	global $p_circle,$html,$ut, $plugin_ar;
	
	if (isset($plugin_ar[BBS2_ID]['list_size'])) {
		$list_size = $plugin_ar[BBS2_ID]['list_size'];
	} else {
		$list_size = 10;
	}

	if (!isset($arg['private']) && !empty($plugin_ar[ BBS2_ID ]['private'])) {
		$arg['private'] = true;
	}
	if (!isset($arg['reply']) && !empty($plugin_ar[ BBS2_ID ]['reply'])) {
		$arg['reply'] = true;
	}
	if (!isset($arg['file']) && !empty($plugin_ar[ BBS2_ID ]['file'])) {
		$arg['file'] = true;
	}
	
	if (!isset($arg['write_btn_caption'])) {
		$arg['write_btn_caption'] = '- コメントを書く';
	}
	if (!isset($arg['more_btn_caption'])) {
		$arg['more_btn_caption'] = '- 以前のコメント';
	}
	
	if (empty($arg['guest_view']) && !isset($_SESSION['login_id'])) {
		return '';
	}
	$p_page = $params['page']['id'];

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'write_bbs2')  {
		$r = array();
		$r['result'] = false;
		$r['p_page'] = $p_page;
		if (!$p_page || !isset($_SESSION['login_id'])) {
			echo( json_encode($r) );
			exit();
		}
		$t = DBX."user_log";
		$x = array();
		$x['link'] = $p_page;
		$x['type'] = BBS2_ID;
		$x['metadata']['mess'] = sanitize_html($_POST['data']);
		$x['metadata']['date'] = $params['now'];
		if (add_user_log($x)) {
			$r['result'] = true;
			$r['new_id'] = $x['id'];
			$ar = $database->sql_select_all("select $t.id as id, $t.metadata as metadata, t1.name as name, t1.nickname as nickname, $t.user as user, {$ut->date_format("$t.date","'%m/%d %H:%i'")} as date,t1.img as img from $t left join ".DBX."users as t1 on t1.id=$t.user where $t.id=? ", $r['new_id']);
			if ($ar) {
				$m = unserialize64($ar[0]['metadata']);
				$r['html'] = bbs_make_html($ar[0], $m, $arg);
			} else {
			}
			if (event_plugin_page('onwrite_bbs', $x) !== false) {
			}
		}

		echo( json_encode($r) );
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'save_edit_bb2')  {
		$r = array();
		$r['result'] = false;
		$r['id'] = (int)$_POST['id'];
		$r['mess'] = sanitize_html($_POST['data']);
		if (!$r['mess'] || !$r['id'] || !isset($_SESSION['login_id'])) {
			echo( json_encode($r) );
			exit();
		}
		$ar = $database->sql_select_all("select id,user,metadata from ".DBX."user_log where type=? and id=?", BBS2_ID, $r['id']);
		if ($ar && $_SESSION['login_id'] == $ar[0]['user']) {
			$m = unserialize64($ar[0]['metadata']);
			$m['mess'] = $r['mess'];
			if ($database->sql_update("update ".DBX."user_log set metadata=? where id=?", serialize64($m), $r['id'])) {
				$r['result'] = true;
			}
		}

		echo( json_encode($r) );
		exit();
	}
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'reply_edit_bb2')  {
		$r = array();
		$r['result'] = false;
		$r['id'] = (int)$_POST['id'];
		$r['mess'] = sanitize_html($_POST['data']);
		if (!$r['mess'] || !$r['id'] || !isset($_SESSION['login_id'])) {
			echo( json_encode($r) );
			exit();
		}
		$ar = $database->sql_select_all("select id,user,metadata from ".DBX."user_log where type=? and id=?", BBS2_ID, $r['id']);
		if ($ar) {
			$m = unserialize64($ar[0]['metadata']);
			if (!isset($m['reply'])) {
				$m['reply'] = array();
			}
			$m['reply'][] = array(
				'mess'=>$r['mess']
				, 'user'=>$_SESSION['login_id']
				, 'user_name'=>$params['login_user']['nickname']
				, 'user_img'=>$_SESSION['login_img']
				, 'date'=>$params['now']
			);
			$x = array();
			$x['id'] = $r['id'];
			$x['date'] = $params['now'];
			$x['meta']['reply'] = $m['reply'];
			if (mod_user_log($x)) {
				$r['result'] = true;
				$x['type'] = BBS2_ID;
				if (event_plugin_page('onwrite_bbs', $x) !== false) {
				}
			}
		}
		echo( json_encode($r) );
		exit();
	}
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'remove_bbs2')  {
		$r = array();
		$r['result'] = false;
		$p_id = (int)$_POST['id'];
		$r['id'] = $p_id;
		$ar = $database->sql_select_all("select user,metadata from ".DBX."user_log  where id=?", $p_id);
		if ($ar && ($ar[0]['user'] == $_SESSION['login_id'] || $_SESSION['login_id'] == $params['page']['user'])) {
			if ($database->sql_update("delete from ".DBX."user_log where id=?", $p_id)) {
				$r['result'] = true;
			}
			$m = unserialize64($ar[0]['metadata']);
			if (isset($m['name'])) {
				$f = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$params['page']['id'].DIRECTORY_SEPARATOR.$m['name'];
				@unlink(sanitize_path($f));
			}
		}
		echo( json_encode($r) );
		exit();
	}
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'del_reply_bbs2')  {
		$r = array();
		$r['result'] = false;
		$r['id'] = (int)$_POST['id'];
		$p_idx = (int)$_POST['idx'];
		$ar = $database->sql_select_all("select metadata,user from ".DBX."user_log  where id=?", $r['id']);
		if ($ar) {
			$ok = $ar[0]['user'] == $_SESSION['login_id'] || $_SESSION['login_id'] == $params['page']['user'];
			$m = unserialize64($ar[0]['metadata']);
			$reply = array();
			if (isset($m['reply']) && is_array($m)) {
				foreach ($m['reply'] as $k=>$v) {
					if ($v['user']==$_SESSION['login_id'] && $k==$p_idx) {
					} else {
						$reply[] = $v;
					}
				}
			}
			$x = array();
			$x['id'] = $r['id'];
			$x['meta']['reply'] = $reply;
			if (mod_user_log($x)) {
				$r['result'] = true;
			}
		}
		echo( json_encode($r) );
		exit();
	}
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'load_bbs2')  {
		$r = array();
		$r['result'] = false;
		$r['page'] = $p_page;
		$r['offset'] = 0;
		$r['page_size'] = $list_size;
		if (isset($_POST['offset'])) {
			$r['offset'] = (int)$_POST['offset'];
		}
		if (!$p_page) {
			echo( json_encode($r) );
			exit();
		}
		_load_bbs2($r, $arg);
		echo( json_encode($r) );
		exit();
	}

$tmp = <<<EOT
<script>
	\$(function() {
		\$(document).on('click', '.bbs2-unit .del',function(){
			if (!confirm('削除しますか?')) { return; }
			var id = \$(this).attr('data-id');
			ot.ajax({
				type: "POST"
				, url: '{$params['safe_request']}'
				, data: "ajax=remove_bbs2&id="+id
				, dataType:'json'
				, success: function(data){
					if (data && data['result']) {
						\$('#bbs2-written div[data-bbs2id='+data['id']+']').remove();
					} else {
					}
				}
			});
		});
		\$(document).on('click', '.bbs2-unit .edit',function(){
			ot.esc_edit_bb2();
			\$('.bbs2-unit .hnd,.bbs2-unit .reply').hide();
			\$('.bbs2-unit .write-btn').hide();
			var id = \$(this).attr('data-id');
			var o = \$('.bbs2-unit .item[data-bbs2id='+id+'] .mess .body');
			var h = o.html();
			h = h.replace(/<br *\\/*>/gm,"\\n"); // /**/
			o.hide();
			var w = "<div class='edit_bbs2' style='display:none'>";
			w += "<textarea style='height:3em;' data-id='"+id+"'>"+h+"</textarea>"
			w += "<div>";
			w += "<input type='button' value='Write' onclick='ot.save_edit_bb2()' class='onethird-button mini' />";
			w += "<input type='button' value='Close' onclick='ot.esc_edit_bb2()' class='onethird-button mini' />";
			w += "</div>";
			o.before(w);
			\$('.edit_bbs2').fadeIn();
		});
		\$(document).on('click', '.bbs2-unit .reply',function(){
			ot.esc_edit_bb2();
			\$('.bbs2-unit .hnd,.bbs2-unit .reply').css('display','none');
			\$('.bbs2-unit .write-btn').hide();
			var id = \$(this).attr('data-id');
			var o = \$('.bbs2-unit .item[data-bbs2id='+id+'] .reply');
			var w = "<div class='edit_bbs2' style='display:none'>";
			w += "<textarea style='margin-top:10px;height:3em;' data-id='"+id+"'></textarea>"
			w += "<div>";
			w += "<input type='button' value='Write' onclick='ot.reply_edit_bb2()' class='onethird-button mini' />";
			w += "<input type='button' value='Close' onclick='ot.esc_edit_bb2()' class='onethird-button mini' />";
EOT;
			if (isset($arg['file'])) {
$tmp .= <<<EOT
				w += "<input type='file' class='file-button' data-idx='"+id+"' class='onethird-button mini ' ";
				w += "	onchange='ot.upload_file_bbs2(event,this)'";
				w += "style='display:none'/>";
				w += "<input type='button' class='onethird-button mini ' value='file' onclick='ot.file_edit_bbs2("+id+")' />";
				w += "<span class='file_data'></span>";
EOT;
			}
$tmp .= <<<EOT
			w += "</div>";
			o.after(w);
			\$('.edit_bbs2').fadeIn();
		});
		\$(document).on('click', '.bbs2-unit .del_reply',function(){
			if (!confirm('削除しますか?')) { return; }
			var id = \$(this).attr('data-id');
			var idx = \$(this).attr('data-idx');
			ot.ajax({
				type: "POST"
				, url: '{$params['safe_request']}'
				, data: "ajax=del_reply_bbs2&id="+id+"&idx="+idx
				, dataType:'json'
				, success: function(data){
					if (data && data['result']) {
						location.reload(true);
					}
				}
			});
		});
		//ot.load_bbs2();
	});
	ot.esc_edit_bb2 = function(save) {
		\$(".bbs2-unit .edit_bbs2 textarea").each(function(){
			if (save) {
				var o = \$(this);
				var id = o.attr('data-id');
				var h = o.val();
				h = h.replace(/\\n/gm,"<br />");
				o.remove();
				\$('.bbs2-unit .item[data-bbs2id='+id+'] .mess .body').html(h);
			}
			\$('.bbs2-unit .edit_bbs2').remove();
			\$('.bbs2-unit .mess .body').show();
		});
		\$('.bbs2-unit .hnd,.bbs2-unit .reply').show();
		\$('.bbs2-unit .write-btn').show();
	};
	ot.save_edit_bb2 = function() {
		var o = \$(".bbs2-unit .edit_bbs2 textarea");
		var id = o.attr('data-id');
		var h = o.val();
		h = h.replace(/^[ 　\\t\\r\\n]+|[ 　\\t\\r\\n]+$/gm, "");
		h = h.replace(/\\n/gm,"<br />");
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=save_edit_bb2&id="+id+"&data="+encodeURIComponent(h)
			, dataType:'json'
			, success: function(data){
				if (data && data['result']) {
					ot.esc_edit_bb2(true);
				}
			}
		});
	};
	ot.reply_edit_bb2 = function() {
		var o = \$(".bbs2-unit .edit_bbs2 textarea");
		var id = o.attr('data-id');
		var h = o.val();
		h = h.replace(/^[ 　\\t\\r\\n]+|[ 　\\t\\r\\n]+$/gm, "");
		h = h.replace(/\\n/gm,"<br />");
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=reply_edit_bb2&id="+id+"&data="+encodeURIComponent(h)
			, dataType:'json'
			, success: function(data){
				if (data && data['result']) {
					location.reload(true);
				}
			}
		});
	};
	ot.write_bbs2 = function(text) {
		ot.esc_edit_bb2();
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=write_bbs2&data="+encodeURIComponent(text)
			, dataType:'json'
			, success: function(data){
				if (data && data['result']) {
					if (data['html']) {
						\$('#bbs2-written').append(data['html']);
						\$('.writing-panel textarea').val('');
						
					} else if (data['append']) {
						var h = \$('.bbs2-unit .item[data-bbs2id='+data['id']+'] .mess .top').html();
						\$('.bbs2-unit .item[data-bbs2id='+data['id']+'] .mess .top').html(h+'<br />'+data['append']);
						\$('.writing-panel textarea').val('');
					}
				}
			}
		});
	};
	ot.load_bbs2 = function() {
		var offset = \$('#bbs2-written').attr('data-offset');
		if (!offset) { offset = 0; }
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=load_bbs2&offset="+offset
			, dataType:'json'
			, success: function(data){
				if (data && data['result'] && data['html']) {
					\$('#bbs2-written').prepend(data['html']).attr('data-offset',data['offset']);
					if (data['more']) {
						\$('#bbs2-more').show();
					} else {
						\$('#bbs2-more').hide();
					}
				} else {
					\$('#bbs2-more').hide();
				}
			}
		});
	};
	ot.start_edit_bbs2 = function() {
		\$('.bbs2-unit .hnd,.bbs2-unit .reply').hide();
		\$('.bbs2-unit #p_bbs_write').val('');
		\$('.bbs2-unit .writing-panel').fadeIn();
		\$('.bbs2-unit .write-btn').hide();
	};
	ot.end_edit_bbs2 = function(save) {
		var h = \$('.bbs2-unit #p_bbs_write').val();
		h = h.replace(/^[ 　\\t\\r\\n]+|[ 　\\t\\r\\n]+$/gm, "");
		h = h.replace(/\\n/gm,"<br />");
		if (save && h) { ot.write_bbs2(h); }
EOT;
		if ($arg['write_btn_caption']) {
$tmp .= <<<EOT
			\$('.bbs2-unit .writing-panel').hide(); 
EOT;
		}
$tmp .= <<<EOT
		\$('.bbs2-unit .write-btn').show();
		\$('.bbs2-unit .hnd,.bbs2-unit .reply').show();
	};
	ot.file_edit_bbs2 = function(id) {
		var file = \$('.bbs2-unit .file-button[data-idx='+id+']');
		file.click();
	};
	ot.upload_file_bbs2 = function(event,obj) {
		var files = event.target.files; // FileList object
		var reader = new FileReader();
		reader.readAsDataURL(files[0]);
		reader.p_reply = \$(obj).attr('data-idx');
		reader.f_name = files[0].name;
		reader.onload = function (event) {
			if (event.target.result.substr(0,5) == 'data:') {
				var opt = "&data="+encodeURIComponent(this.result)+"&reply="+this.p_reply;
				opt += "&fname="+encodeURIComponent(this.f_name);
				ot.ajax({
					type: "POST"
					, url: '{$params['safe_request']}'
					, data: "ajax=upload_file_bbs2&name="+encodeURIComponent(this.f_name)+opt
					, dataType:'json'
					, success: function(data){
						if (!data['result']) {
							alert('error');
							return;
						}
						if (data['html']) {
							\$('#bbs2-written').append(data['html']);
						} else {
							location.reload(true);
						}
					}
				});
			}
		};
	};
</script>
EOT;

$html['meta']['bbs2'] = $tmp;

	if (check_rights() && isset($_POST['ajax']) && $_POST['ajax'] == 'upload_file_bbs2')  {
		$r = array();
		$r['result'] = false;

		$page_id = $params['page']['id'];
		$reply = 0;
		if (isset($_POST['reply'])) {
			$reply = (int)$_POST['reply'];
		}

		$f = $config['files_path'].DIRECTORY_SEPARATOR.'img';
		if (!is_dir($f)) {
			if (!mkdir($f)) {
				$r['error1'] = $f;
				echo( json_encode($r) );
				exit();
			}
			chmod($f, $config['permission']);  
		}

		$f = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$page_id.DIRECTORY_SEPARATOR;
		$u = 'img.php?p='.$page_id.'&amp;i=';

		if (!is_dir($f)) {
			if (!mkdir($f)) {
				$r['error2'] = $f;
				echo( json_encode($r) );
				exit();
			}
			chmod($f, $config['permission']);  
		}

		$str_type = array(
			array('data'=>"data:image/jpeg;base64,",'ext'=>'jpg','type'=>'img')
			, array('data'=>"data:image/png;base64,",'ext'=>'png','type'=>'img')
			, array('data'=>"data:image/gif;base64,",'ext'=>'gif','type'=>'img')
			, array('data'=>"data:application/x-zip",'ext'=>'zip','type'=>'file')
			, array('data'=>"data:application/pdf;base64,",'ext'=>'pdf','type'=>'file')
			, array('data'=>"data:;base64,",'ext'=>'doc','type'=>'file')
			, array('data'=>"data:;base64,",'ext'=>'docx','type'=>'file')
			, array('data'=>"data:;base64,",'ext'=>'xlsx','type'=>'file')
		);
		//$str_type[] = array('data'=>"data:;base64,",'ext'=>'');
		foreach ($str_type as $v) {
			if (substr($_POST['data'], 0, strlen($v['data'])) == $v['data']) {
				$b = base64_decode(substr($_POST['data'], strlen($v['data'])));
				$name = '';
				$r['type'] = 'file';
				if (isset($v['type']) && $v['type'] == 'img') {
					$r['type'] = 'img';
					$r['org_name'] = $name;
					$name = md5($b);
					$name .= '.'.$v['ext'];
				} else {
					$name = sanitize_str($_POST['fname']);
					if (isset($v['type']) && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && strlen($name) != mb_strlen($name)) {
						$name = md5($b);
						$name .= '.'.$v['ext'];
					} else {
						$name = preg_replace("/[ ()]/","",$name);
					}
				}
				$r['name'] = $name;
				$r['path'] = $f;
				if (!file_exists($f.$name) && file_put_contents($f.$name, $b)) {
					@chmod($f.$name,$config['permission']);
					$r['result'] = true;
					$r['url'] = $u.$name;
					if (!check_rights('edit')) {
						add_actionlog("[login user upload] ({$_SESSION['login_id']} : {$_SESSION['login_name']} : {$_SERVER['REMOTE_ADDR']})");
					}

					if ($reply) {
						$ar = $database->sql_select_all("select id,user,metadata from ".DBX."user_log where type=? and id=?", BBS2_ID, $reply);
						if ($ar) {
							$m = unserialize64($ar[0]['metadata']);
							if (!isset($m['reply'])) {
								$m['reply'] = array();
							}
							$m['reply'][] = array(
								'user'=>$_SESSION['login_id']
								, 'user_name'=>$params['login_user']['nickname']
								, 'url'=>$r['url']
								, 'name'=>$r['name']
								, 'type'=>$r['type']
								, 'date'=>$params['now']
							);
							$x = array();
							$x['id'] = $ar[0]['id'];
							$x['date'] = $params['now'];
							$x['meta']['reply'] = $m['reply'];
							if (mod_user_log($x)) {
								$r['result'] = true;
								$x['type'] = BBS2_ID;
								if (event_plugin_page('onbefore_modified', $x) !== false) {
								}
							}
						}
					} else {
						$x = array();
						$x['link'] = $page_id;
						$x['type'] = BBS2_ID;
						$x['metadata']['url'] = $u.$name;
						$x['metadata']['name'] = $name;
						$x['metadata']['type'] = $r['type'];
						$x['metadata']['date'] = $params['now'];
						$r['result'] = add_user_log($x);
						if ($r['result']) {
							$r['result'] = true;
							$r['new_id'] = $x['id'];
							$t = DBX.'user_log';
							$ar = $database->sql_select_all("select $t.id as id, $t.metadata as metadata, t1.name as name, t1.nickname as nickname, $t.user as user, {$ut->date_format("$t.date","'%m/%d %H:%i'")} as date,t1.img as img from $t left join ".DBX."users as t1 on t1.id=$t.user where $t.id=? ", $r['new_id']);
							if ($ar) {
								$m = unserialize64($ar[0]['metadata']);
								$r['html'] = bbs_make_html($ar[0], $m, $arg);
							}
							if (event_plugin_page('onbefore_modified', $x) !== false) {
							}
						}
					}
				}
				break;
			}
		}
		echo( json_encode($r) );
		exit();
	}

	$r = array('offset'=>0, 'page'=>$p_page, 'page_size'=>$list_size);
	_load_bbs2($r, $arg);
	$s = "style='display:none'";
	if (isset($r['more'])) {
		$s = '';
	}
$buff = <<<EOT
<div class='bbs2-unit'>
	<div id='bbs2-more' $s><a href='javascript:void(ot.load_bbs2())'>{$arg['more_btn_caption']}</a>
	</div>
	<div id='bbs2-written' data-offset='{$r['offset']}'>
EOT;
	$buff .= $r['html'];
$buff .= <<<EOT
	</div>
EOT;
	$s = "style='display:none;'";
	$c = "<input type='button' value='Close' onclick='ot.end_edit_bbs2(false)' class='onethird-button mini ' />";
	if ($arg['write_btn_caption']) {
$buff .= <<<EOT
		<div style='margin-bottom:10px;'><a href='javascript:void(ot.start_edit_bbs2())' >{$arg['write_btn_caption']}</a></div>
EOT;
	} else {
		$c = $s = '';
	}
	
$buff .= <<<EOT
	<div class='writing-panel' $s>
		<img class='avatar' src='{$_SESSION['login_img']}' />
		<div class='mess'>
			<p><textarea id='p_bbs_write' style='height:120px;'></textarea></p>
			<input type='button' value='Write' onclick='ot.end_edit_bbs2(true)' class='onethird-button mini ' />
			$c
EOT;
			if (isset($arg['file'])) {
$buff .= <<<EOT
				<input type='file' class='file-button' data-idx='0' class='onethird-button mini ' 
					onchange="ot.upload_file_bbs2(event,this)"
				style='display:none'/>
				<input type='button' class='onethird-button mini ' value='File' onclick='ot.file_edit_bbs2(0)' />
				<span class='file_data'></span>
EOT;
			}
$buff .= <<<EOT
		</div>
	</div>
</div>
EOT;

	if (!isset($html['css']['plugin_bbs2'])) {
$html['css']['plugin_bbs2'] = <<<EOT
		<style>
		#bbs2-more {
			padding-bottom:10px;
		}
		.bbs2-unit {
			margin:2em 0 0 0;
		}
		.bbs2-unit .hnd {
			opacity: 0;
		}
		.bbs2-unit .hide {
			display:none;
		}
		.bbs2-unit .item .mess:hover .hnd {
			opacity: 0.50;
			cursor:pointer;
		}
		.bbs2-unit .hnd:hover {
			opacity: 1  !important;
		}
		.writing-panel {
			position: relative;
		}
		.writing-panel textarea, .bbs2-unit .edit_bbs2 textarea {
			border: 1px solid #c0c0c0;
			box-sizing: border-box;
			max-width:500px;
			width:100%;
			padding:5px;
		}
		.bbs2-unit .item {
			position: relative;
		}
		.bbs2-unit .avatar {
			position: absolute;
			left:0;
			top:0;
			height:32px;
			width:32px;
		}
		.bbs2-unit .mess {
			padding-left:45px;
			width:100%;
			box-sizing: border-box;
			padding-bottom:10px;
		}
		.bbs2-unit .mess p {
			padding:0;
			margin:0;
		}
		.bbs2-unit .mess img {
			max-width:100px;
			-moz-transition: max-width 0.3s linear 0 ;
			-webkit-transition: max-width 0.3s linear 0;
			-o-transition: max-width 0.3s linear 0;
			-ms-transition: max-width 0.3s linear 0 ;
			vertical-align: top;
		}
		.bbs2-unit .mess img:hover {
			max-width:320px;
		}
		.bbs2-unit .mess .reply_box {
			width:100%;
			box-sizing: border-box;
			padding:10px 0 10px 0;
			margin-top:10px;
			border-top:1px dotted #c0c0c0;
		}
		.bbs2-unit .mess .hnd , .bbs2-unit .mess .reply {
			cursor:pointer;
		}
		.bbs2-unit .mess .user {
			font-weight:bold;
		}
		.bbs2-unit .mess .info {
			color:#838383;
		}
		</style>
EOT;
	}
	return $buff;
}

function _load_bbs2(&$r, &$arg)
{
	global $database,$params,$ut;
	$r['html'] = '';
	$t = DBX."user_log";
	if (isset($arg['private']) && $arg['private'] && $params['page']['user'] != $_SESSION['login_id']) {
		$w = "and $t.user={$_SESSION['login_id']} ";
	} else {
		$w = '';
	}
	$sql = "select $t.id as id, $t.metadata as metadata, t1.name as name, t1.nickname as nickname
		, $t.user as user, {$ut->date_format("$t.date","'%m/%d %H:%i'")} as date
		,t1.img as img from $t left join ".DBX."users as t1 on t1.id=$t.user 
		where $t.link=? and $t.type=? $w
		order by $t.date desc {$ut->limit($r['offset'],$r['page_size'])}";
	$ar = $database->sql_select_all($sql, $r['page'], BBS2_ID);
	if ($ar) {
		$buff = '';
		$r['result'] = true;
		foreach ($ar as $v) {
			$m = unserialize64($v['metadata']);
			if (isset($m['date'])) {
				$v['date'] = substr($m['date'],0,10);
			}
			$buff = bbs_make_html($v, $m, $arg).$buff;
		}
		$r['html'] = $buff;
		if (count($ar) == $r['page_size']) {
			$r['more'] = true;
		}
		$r['offset'] = $r['offset'] + $r['page_size'];
	}
}

function bbs_make_html($v, $m, $arg)
{
	global $config, $params, $ut;
	
	$buff = '';
	$img = $ut->safe_echo(get_user_image($v));
	if (!$v['nickname']) {
		$name = $v['name'];
	} else {
		$name = "[{$v['nickname']}]";
	}
	if (isset($m['mess'])) {
		$body = $m['mess'];
	} else if (isset($m['type'])) {
		if ($m['type'] == 'img') {
			$body = "<img src='{$m['url']}' alt='' />";
		} else {
			$body = "<a href='{$m['url']}'>{$ut->icon('zip')} {$m['name']}</a>";
		}
	} else {
		$body = '';
	}
$buff .= <<<EOT
	<div class='item' data-bbs2id='{$v['id']}'>
		<img class='avatar' src='{$img}' />
		<div class='mess'>
			<p class='top'><div class='user'>$name</div> <div class='body'>$body</div></p>
			<span class='info'> {$v['date']}</span>
EOT;
			if (isset($_SESSION['login_id'])) {
				if ($v['user'] == $_SESSION['login_id'] && !isset($m['photo'])) {
$buff .= <<<EOT
					<span class='edit hnd ' data-id='{$v['id']}' >- edit</span>
EOT;
				}
				if ($_SESSION['login_id'] == $params['page']['user'] || $v['user'] == $_SESSION['login_id']) {
$buff .= <<<EOT
					<span class='del hnd ' data-id='{$v['id']}' >- del</span>
EOT;
				}
			}
			if (isset($arg['reply']) && $arg['reply']) {
				if (isset($m['reply'])) {
					foreach ($m['reply'] as $k=>$vv) {
						$name = $vv['user_name'];
						$date = $vv['date'];
						$body = '';
						if (isset($vv['mess'])) {
							$body = $vv['mess'];
							$img = $vv['user_img'];
						} else if (isset($vv['type'])) {
							if ($vv['type'] == 'img') {
								$body = "<img src='{$vv['url']}' alt='' />";
							} else {
								$body = "<a href='{$vv['url']}'>{$ut->icon('zip')} {$vv['name']}</a>";
							}
						}
$buff .= <<<EOT
						<p class='reply_box'>
							<img src='$img' alt='' style='width:32px' /> 
							<span class='user'>$name</span> {$body}
							<div class='info'>$date 
EOT;
								if (check_user($vv['user'])) {
$buff .= <<<EOT
									<span class='del_reply hnd ' data-id='{$v['id']}' data-idx='{$k}' >- del</span>
EOT;
								}
$buff .= <<<EOT
							</div>
						</p>
EOT;
					}
				}
$buff .= <<<EOT
				<div class='reply' data-id='{$v['id']}' data-reply='{$v['id']}' >- reply</div>
EOT;
			}
$buff .= <<<EOT
		</div>
	</div>
EOT;
	return $buff;
}

?>