<?php

function contact_renderer(&$page_ar)
{
	global $params, $ut, $html, $config;
	$p_page = $page_ar['id'];
	$params['allow_all_post'] = true;

	if (check_rights('owner')) {
		set_func_rights('edit.modules');
		set_func_rights('add_page');
		provide_edit_rights();
		provide_edit_module();
	}
	if (isset($_GET['mode']) && $_GET['mode']=='contact_manager') {
		return contact_manager($page_ar);
	}
	if (empty($page_ar['meta']['flexedit']['contact_send_mess'][0]['text'])) {
$page_ar['meta']['flexedit']['contact_send_mess'][0]['text'] = <<<EOT
		<h1>お問い合わせありがとうございました</h1>
		<p>
			受付番号 : {\$params['plugin']['contact']['page']['id']}
		</p>
		<p>
			内容確認後、ご連絡いたしますので今しばらくお待ちください
		</p>
EOT;
		mod_data_items($page_ar);
	}

	//送信処理
	if (isset($_POST['post']) && $_POST['post'] == '*send_contact_ex' && !empty($_POST['contents'])) {
		if (!empty($page_ar['meta']['contact_form']['adr_page'])) {
		
			$mailadr = $page_ar['meta']['contact_form']['adr_page'];
			$mail_ar = array();
			
			$idx = substr(time(),-4);
			$mail_ar['cc'] =  $mailadr;
			$mail_ar['from'] =  $mailadr;
			$mail_ar['user_nickname'] = 'message - post';
			$mail_ar['message'] = sanitize_str($_POST['contents']);
			$mail_ar['subject'] = "お問い合わせ内容 - {$page_ar['title']} ({$params['page']['id']}:$idx) ";
$mail_ar['message'] = <<<EOT

お問い合わせありがとうございます

以下の内容で受け付けました
{$mail_ar['message']}

--
{$params['circle']['name']}
POST URL {$ut->link($params['page']['id'])}


EOT;
			$mgx = !empty($page_ar['meta']['contact_form']['manager']);
			if ($mgx) {
				set_func_rights('edit.modules');
				set_func_rights('add_page',false);	//非ログイン状態でもページ追加を許可する
				provide_edit_rights();
			}
			$mail_ar['no-log'] = true;
			$rx = array();
			$rx['link'] = $page_ar['link'];
			$rx['result'] = false;
			$rx['type'] = CONTACT_ID;
			$rx['title'] = 'CONTACT-'.$idx;
			$rx['block_type'] = 20;
			$rx['mode'] = 10;
			foreach ($_POST as $k=>$v) {
				if (substr($k,0,5) == 'post_') {
					$k = mb_strtolower(sanitize_str(substr($k,5)));
					if (substr($k,0,5) == 'your_' || substr($k,0,5) == 'your ') {
						$k = substr($k,5);
					}
					if ($k == 'mail' || $k == 'mail address' || $k == 'メール' || $k == 'メールアドレス' ) {
						$rx['meta']['plugin_embed']['email'] = sanitize_str($v);
					} else {
						$rx['meta']['plugin_embed'][$k] = sanitize_str($v);
					}
				}
			}

			$rx['meta']['post_contents'] = save_contents_script(sanitize_str($_POST['contents']));   // JPCERT#97053126
			unset($html['article']);
			$params['rendering'] = false;
			$mail_ar['to'] = false;
			if (!empty($rx['meta']['plugin_embed']['email'])) {
				$mail_ar['to'] = $rx['meta']['plugin_embed']['email'];
			}
			if (isset($_FILES)) {
				foreach ($_FILES as $k=>$v) {
					if (is_uploaded_file($v['tmp_name'])) {
						if (!$mgx) {
							unlink($v['tmp_name']);
						}
					}
				}
			}
			if ($mail_ar['to'] !== false && (!$mgx || create_page($rx))) {
				/* Mr. Harold Kim!
				if (isset($_FILES)) {
					foreach ($_FILES as $k=>$v) {
						$file1tmp   = $v['tmp_name'];		//tmpファイル名
						$file1name  = $v['name'];			//ローカルファイル名
						$info = pathinfo($file1name);
						if (is_uploaded_file($file1tmp)) {
							$date = date('ymdHis', $_SERVER['REQUEST_TIME']);
							$path = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$rx['id'];
							if (!is_dir($path)) {
								mkdir($path);
								chmod($path,$config['permission']);
							}
							$dst = $path.DIRECTORY_SEPARATOR.$info['basename'];
							if (move_uploaded_file ( $file1tmp , $dst )) {
								$extensions = ".jpeg.jpg.gif.png.zip.pdf.doc.docx.xls.xlsx.ppt.pptx.csv.webp.";
								if (strstr($extensions, '.'.$info['extension'].'.') === false) {
									unlink( $dst );
								} else {
									$rx['meta']['plugin_contact']['file'][] = $info['basename'];
								}
							}
						}
					}
					if (!empty($rx['meta']['plugin_contact']['file'])) {
						mod_data_items($rx);
					}
				}
				*/
				if (sx_send_mail($mail_ar)) {
					if (isset($page_ar['meta']['flexedit']['contact_send_mess'][0]['text'])) {
						$buff = $page_ar['meta']['flexedit']['contact_send_mess'][0]['text'];
						$params['plugin']['contact']['page'] = $rx;
						if (!isset($params['plugin']['contact']['page']['id'])) {
							$params['plugin']['contact']['page']['id'] = $idx;
						}
						expand_buff($buff);
						return $buff;
					}
				}
			} else {
$buff = <<<EOT
				<h1>システムエラー</h1>
				<p>
					システムエラーが発生しました
				</p>
				<p>
					お手数ですが、もう一度最初からやり直してください
				</p>
EOT;
				return $buff;
			}
		}
	}

	// 通常表示
	return frame_renderer(write_contact($page_ar));

}

function write_contact( &$page_ar )
{
	global $params, $ut, $html, $database, $plugin_ar, $config;

	$id = $page_ar['id'];

$html['css']['write_contact'] = <<<EOT
<style>
	#onethird-contactform .item {
		padding:10px;
		margin-bottom:5px;
		background-color: rgba(123, 122, 122, 0.12);
		position: relative;
	}
	#onethird-contactform .item input, #onethird-contactform .item textarea, #onethird-contactform .item select {
		border: 1px solid #CBCBCB;
	}
	#onethird-contactform .ok {
		background-color: #DEDEDE;
	}
	#onethird-contactform .ok input, #onethird-contactform .ok textarea {
		border: 1px solid #CBCBCB;
	}
	#onethird-contactform .ng {
		background-color: #FFD8D8;
	}
	#onethird-contactform .ng input, #onethird-contactform .ng textarea {
		border: 1px solid #F40;
	}
	#onethird-contactform .item .title {
		min-width:180px;
		display:inline-block;
		position: absolute;
		left: 0;
		padding-left:20px;
	}
	#onethird-contactform .item > div {
		margin-left:180px;
	}
	#onethird-contactform .must_alert {
		margin-left:180px;
		color: #F00;
	}
	@media (max-width: 768px) {
		#onethird-contactform .item .title {
			min-width:100%;
			display:block;
			position: relative;
			padding-left: 0;
			padding-bottom: 6px;
		}
		#onethird-contactform .item > div {
			margin-left:0;
			width:100%;
			display:block;
			position: relative;
			padding-left: 0;
		}
		#onethird-contactform .must_alert {
			margin-left:0;
			width:100%;
			display:block;
			position: relative;
			padding-left: 0;
		}
	}
	#onethird-contactform .item .contact_input {
		width:100%;
		padding:7px 10px;
		box-sizing: border-box;
		background-color:#fff;
		color:#000;
		font-size: 15px;
	}
	#onethird-contactform .item .contact_input:disabled {
		border:none;
	}
	#onethird-contactform .item .contact_input[type=radio]
	, #onethird-contactform .item .contact_input[type=checkbox] {
		width:2em;
	}
	#onethird-contactform .must {
		background-color: rgba(255, 73, 3, 0.18);
	}
	#onethird-contactform .must.ok {
		background-color: #DEDEDE;
	}
	#onethird-contactform .must .title:after {
		content: '*';
		position: absolute;
		right: 20px;
		top: 0;
		color: #F00;
		font-size:20px;
	}
	#onethird-contactform .ok .title:after {
		content: '';
		background-image: url({$config['site_url']}img/ok.png);
		position: absolute;
		right: 20px;
		top: 5px;
		height: 16px;
		width: 16px;
	}
	#_tryitEditor_contents #onethird-contactform .item {
		border:1px dotted #CB0F00;
	}
	#contact_toolbar select:disabled,#contact_toolbar .disabled {
		opacity:0.5;
		cursor: not-allowed;
	}
	#_tryitEditor_contents #onethird-contactform .sel_item {
		border:1px solid #CB0F00;
		background-color: rgba(123, 122, 122, 0.30);
	}
	#_tryitEditor_contents #onethird-contactform .checkbox_add
	, #_tryitEditor_contents #onethird-contactform .checkbox_del {
		display: inline-block;
		margin-left: 10px;
		cursor: pointer;
		width:20px;
		height:20px;
		vertical-align: middle;
	}
	#_tryitEditor_contents #onethird-contactform .checkbox_add {
		background-image:url({$config['site_url']}img/add.png);
		background-size: 18px;
	}
	#_tryitEditor_contents #onethird-contactform .checkbox_del {
		background-image:url({$config['site_url']}img/remove.png);
		background-size: 18px;
	}
	
</style>
EOT;

	if (check_rights('edit')) {
$html['meta']['write_contact'] = <<<EOT
<script>
	\$(function(){
		ot.set_contact_option = function() {
			ot.tryitEditor.opt.hide_ind = true;
			ot.tryitEditor.opt.after_toolbar += "<br /> ";
			ot.tryitEditor.opt.after_toolbar += "<div id='contact_toolbar'>";
			ot.tryitEditor.opt.after_toolbar += "<select id='contact_item'><option value=''>--</option><option value='text'>text</option><option value='radio'>radio</option><option value='checkbox'>checkbox</option><option value='textarea'>textarea</option><option value='tel'>tel</option><option value='email'>email</option><option value='number'>number</option><option value='select'>select</option><option value='file'>file</option></select>";
			ot.tryitEditor.opt.after_toolbar += " <a href='javascript:void(ot.inpage_write_contact_move(-1))'  title='項目を上に移動' class='onethird-button mini' >{$ut->icon('up')}</a>";
			ot.tryitEditor.opt.after_toolbar += " <a href='javascript:void(ot.inpage_write_contact_move(1))' title='項目を下に移動' class='onethird-button mini' >{$ut->icon('dn')}</a>";
			ot.tryitEditor.opt.after_toolbar += " <a href='javascript:void(ot.inpage_write_contact_copy())' title='項目をコピー' class='onethird-button mini' >copy</a>";
			ot.tryitEditor.opt.after_toolbar += " <a href='javascript:void(ot.inpage_write_contact_del())' title='項目を削除' class='onethird-button mini' >remove</a>";
			ot.tryitEditor.opt.after_toolbar += "<label><input type='checkbox' id='contact_must' onclick='ot.adj_contact_must(\"toggle\")' />must<label>";
			ot.tryitEditor.opt.after_toolbar += "</div>";

			ot.tryitEditor.opt.onready = function() {
				\$('#contact_toolbar a,#contact_toolbar input').prop('disable',true);
				ot.adj_contact_toolbar(false);
				ot.adj_contact_hnd();
				\$('#_tryitEditor_contents').click(function(e){
					if (e.target) {
						var o = e.target;
						if (o.tagName == 'LABEL') {
							\$(o).parent().find('.checkbox_txt').val(o.innerText);
							return;
						}
						ot._contact_obj = false;
						\$('#_tryitEditor_contents .item').removeClass('sel_item');
						while (o.parentNode) {
							var x = \$(o);
							if (x.hasClass('item')) {
								ot._contact_obj = o;
								var val = '';
								if (x.find('div .contact_input[type=text]').length) {
									val = 'text';
								} else if (x.find('div .contact_input[type=email]').length) {
									val = 'email';
								} else if (x.find('div .contact_input[type=tel]').length) {
									val = 'tel';
								} else if (x.find('div .contact_input[type=number]').length) {
									val = 'number';
								} else if (x.find('div .contact_input[type=checkbox]').length) {
									val = 'checkbox';
								} else if (x.find('div .contact_input[type=radio]').length) {
									val = 'radio';
								} else if (x.find('div .contact_input[type=file]').length) {
									val = 'file';
								} else if (x.find('div textarea.contact_input').length) {
									val = 'textarea';
								} else if (x.find('div select.contact_input').length) {
									val = 'select';
								}
								if (\$('#contact_item').val() != val) {
									\$('#contact_item').val(val);
								}
								break;
							}
							o = o.parentNode;
						}
						ot.adj_contact_toolbar(ot._contact_obj);
					}
				});
				\$('#contact_item').change(function(e){
					var o = \$(this);
					var x = \$(ot._contact_obj).find('div');
					x.html('');
					if (o.val() == 'text') {
						x.append("<input type='text' class='contact_input' />");
					} else if (o.val() == 'email') {
						x.append("<input type='email' class='contact_input' />");
					} else if (o.val() == 'tel') {
						x.append("<input type='tel' class='contact_input' />");
					} else if (o.val() == 'number') {
						x.append("<input type='number' class='contact_input' />");
					} else if (o.val() == 'textarea') {
						x.append("<textarea class='contact_input'></textarea>");
					} else if (o.val() == 'file') {
						x.append("<input type='file' class='contact_input' />");
					} else if (o.val() == 'select') {
						x.append("<select class='contact_input'><option value=''>---</option></select>");
						ot.adj_contact_hnd();
					} else if (o.val() == 'checkbox') {
						x.append("<label><input type='checkbox' class='contact_input' />checkbox</label>");
						ot.adj_contact_hnd();
					} else if (o.val() == 'radio') {
						var a = new Date();
						x.append("<label><input type='radio' class='contact_input' name='contact_"+a.getTime()+"' />radio</label>");
						ot.adj_contact_hnd();
					}
				});
				\$(document).on('click','#_tryitEditor_contents #onethird-contactform .checkbox_add',function(){
					var o = \$(this);
					var z = o.prev();
					var x = z.prev();
					if (x.prop('tagName') == 'LABEL') {
						var y = x.find('input');
						var h = '';
						z = z.val();
						if (!z) { z = 'option'; }
						if (y.prop('type') == 'radio') {
							h = "<label><input type='radio' class='contact_input' name='"+y.prop('name')+"' />"+z+"</label>";
						} else {
							h = "<label><input type='checkbox' class='contact_input' />"+z+"</label>";
						}
						x.after(h);
					} else {
						var x = o.parents('.item');
						y = x.find('select');
						z = x.find('input[type=text]');
						if (y.length==1 && z.length==1) {
							y.append("<option value='"+z.val()+"'>"+z.val()+"</option>");
							y.val(z.val());
						}
					}
				});
				\$(document).on('click','#_tryitEditor_contents #onethird-contactform .checkbox_del',function(){
					var o = \$(this);
					var x = o.parents('.item');
					if (x.length) {
						var y = x.find('input:checked').parent('label');
						if (y.length==1) {
							y.remove();
						} else {
							y = x.find('option:selected')
							if (y.length==1) {
								y.remove();
							}
						}
					}
				});
				\$(document).on('change','#_tryitEditor_contents #onethird-contactform select',function(){
					var o = \$(this);
					var z = o.parent().find('.checkbox_txt');
					if (z.length) {
						z.val(o.val());
					}
				});
				\$(document).on('keyup','#_tryitEditor_contents #onethird-contactform .checkbox_txt',function(){
					var o = \$(this);
					var x = o.parents('.item');
					if (x.length) {
						var y = x.find('input:checked').parent('label');
						if (y.length==1) {
							y = y[0];
							y.innerHTML = y.firstChild.outerHTML + o.val();
							y.firstChild.checked=true;
						} else {
							y = x.find('option:selected')
							if (y.length==1 && y.val()) {
								y.text(o.val()).val(o.val());
							}
						}
					}
				});
				if (!ot.tryitEditor.org_html) {
					ot.tryitEditor.org_html = ot.tryitEditor.html;
					ot.tryitEditor.html = function(a) {
						ot.adj_contact_toolbar(false);
						\$("#_tryitEditor_contents #onethird-contactform .checkbox_add").remove();
						\$("#_tryitEditor_contents #onethird-contactform .checkbox_del").remove();
						\$("#_tryitEditor_contents #onethird-contactform .checkbox_txt").remove();
						\$("#_tryitEditor_contents #onethird-contactform input").prop('readonly',false);
						\$("#_tryitEditor_contents #onethird-contactform input").each(function(){
							var o = \$(this);
							try {
								var t = o.parents('.item').find('.title').text().trim();
								if (t) {
									o.prop('name',t);
									ar[t] = true;
								}
							} catch (e) {
							}
						});
						return ot.tryitEditor.org_html(a);
					}
				}
			}
		};
		ot.inpage_write_contact_move = function(dx){
			if (!ot._contact_obj) { return; }
			if (ot.tryitEditor.ie9over) { document.execCommand("ms-beginUndoUnit"); }
			var o = ot._contact_obj;
			var obj = \$(o);
			if (dx == -1 && o.previousElementSibling) {
				\$(o.previousElementSibling).before(o);
			}
			if (dx == 1 && o.nextElementSibling) {
				\$(o.nextElementSibling).after(o);
			}
			if (ot.tryitEditor.ie9over) { document.execCommand("ms-endUndoUnit"); }
		};
		ot.inpage_write_contact_copy = function(){
			if (!ot._contact_obj) { return; }
			if (ot.tryitEditor.ie9over) { document.execCommand("ms-beginUndoUnit"); }
			var o = ot._contact_obj;
			\$(o).after(o.outerHTML.toString());
			\$('#_tryitEditor_contents .item').removeClass('sel_item');
			\$(o).addClass('sel_item');
			if (ot.tryitEditor.ie9over) { document.execCommand("ms-endUndoUnit"); }
		};
		ot.inpage_write_contact_del = function(){
			if (!ot._contact_obj) { return; }
			if (ot.tryitEditor.ie9over) { document.execCommand("ms-beginUndoUnit"); }
			var o = ot._contact_obj;
			\$(o).remove();
			ot.adj_contact_toolbar(ot._contact_obj = false);
			if (ot.tryitEditor.ie9over) { document.execCommand("ms-endUndoUnit"); }
		};
		ot.adj_contact_toolbar = function(a){
			if (a) {
				\$('#contact_toolbar select,#contact_toolbar input').prop('disabled',false);
				\$('#contact_toolbar a,#contact_toolbar label').removeClass('disabled');
				\$(a).addClass('sel_item');
			} else {
				\$('#contact_item').val('');
				\$('#contact_toolbar select,#contact_toolbar input').prop('disabled',true);
				\$('#contact_toolbar a,#contact_toolbar label').addClass('disabled');
				\$('#_tryitEditor_contents .item').removeClass('sel_item');
			}
			ot.adj_contact_must();
		};
		ot.adj_contact_must = function(a){
			if (!ot._contact_obj) { return; }
			if (a == 'toggle') {
				\$(ot._contact_obj).toggleClass('must');
			} else {
				if (\$(ot._contact_obj).hasClass('must')) {
					\$("#contact_must").prop('checked',true)
				} else {
					\$("#contact_must").prop('checked',false)
				}
			}
		};
		ot.adj_contact_hnd = function(){
			\$("#_tryitEditor_contents #onethird-contactform .checkbox_add").remove();
			\$("#_tryitEditor_contents #onethird-contactform .checkbox_del").remove();
			\$("#_tryitEditor_contents #onethird-contactform .checkbox_txt").remove();
			\$("#_tryitEditor_contents #onethird-contactform label:last-child").after(" <input type='text' style='margin:2px 2px 0 0;width:80px;' class='checkbox_txt' /><span class='checkbox_add'></span><span class='checkbox_del'></span>");
			\$("#_tryitEditor_contents #onethird-contactform select").attr("contenteditable","false").after(" <input type='text' style='margin:2px 2px 0 0' class='checkbox_txt' /><span class='checkbox_add'></span><span class='checkbox_del'></span>");
		};
	});
</script>
EOT;
	}
	if (!check_rights()) {
		provide_onethird_object();
	}
$html['meta']['write_contact_guest'] = <<<EOT
<script>
	\$(function(){
	ot.contact_next = function() {
		\$("#form_contact_ex #contents").val('');
		\$('#onethird-contactform .must_alert').remove();
		\$("#form_contact_ex .send_data").remove();
		\$('#onethird-contactform .ng').removeClass('ng');
		\$('#onethird-contactform .contact_input').each(function(){
			var obj = \$(this);
			var tag = this.tagName;
			var type = this.type;
			var o = obj.parents('.item');
			var label = '';
			if (type=='radio' || type=='checkbox') {
				if (o.hasClass('ok')) {
					if (this.checked) {
						label = obj.parents('label').text();
						var x = \$("#form_contact_ex #contents");
						x.val(x.val() + "\\n["+o.find('.title').text()+"]\\n" + obj.parents('label').text() + "\\n");
						\$("#form_contact_ex ").append(\$("<input type='hidden' class='send_data' />").val('YES').attr('name','post_'+label));
					}
					return;
				}
				if (!this.checked) {
					if (o.find('.contact_input:checked').length) {
						o.addClass('ok').removeClass('ng');
						o.find('.must_alert').remove();
						return;
					}
				}
			} else {
				label = o.children('.title').text();
			}
			var ok_one = false;
			var x = \$("#form_contact_ex #contents");
			if (o.hasClass('item')) {
				if (type=='radio' || type=='checkbox' || !obj.val()) {
					if (o.hasClass('must')) {
						if (!o.hasClass('ng')) {
							o.addClass('ng');
							o.append("<p class='must_alert'>※必須入力項目です</p>");
						}
					} else {
						ok_one = true;
					}
				} else {
					ok_one = true;
					var v = obj.val();
					if (type!='file') {
						x.val(x.val() + "\\n["+label+"]\\n" + v + "\\n");
						\$("#form_contact_ex ").append(\$("<input type='hidden' class='send_data' />").val(v).attr('name','post_'+label));
					}
				}
			}
			if (ok_one) {
				o.addClass('ok').removeClass('ng');
			}
		});
		if (!\$('#onethird-contactform .item.ng').length) {
			\$('#onethird-contactform .contact_input').prop("disabled", true);
			\$('#contact_back').show();
			\$('#contact_send').show();
			\$('#contact_next').hide();
		}
	};
	ot.contact_back = function() {
		\$('#onethird-contactform .contact_input').removeAttr('disabled');
		\$('#contact_back').hide();
		\$('#contact_send').hide();
		\$('#contact_next').show();
	};
	ot.contact_send = function() {
		if (confirm('メールアドレスに間違いがないことをご確認ください \\n送信しますか?')) {
			\$('#form_contact_ex').append("<input type='hidden' name='xtoken' value='"+ot.magic_str+"' />");
			\$('#onethird-contactform .contact_input').each(function(){
				if (this.type=='file') {
					var o = \$(this);
					var x = o.val();
					if (x) {
						o.after(x.match(/[^\\/\\\\]*\$/));
						\$("#form_contact_ex ").append(o.removeAttr('disabled')).attr('enctype','multipart/form-data');
					}
				}
			});
			\$('#form_contact_ex').submit();
		}
	};
	});
</script>
EOT;

	//旧版との互換
	if (isset($params['page']['meta']['flexedit']['contact'][0]['text'])) {
		$page_ar['meta']['flexedit']['contact'][0]['text'] = $params['page']['meta']['flexedit']['contact'][0]['text'];
		unset($params['page']['meta']['flexedit']['contact'][0]['text']);
		$params['page']['metadata'] = serialize64($params['page']['meta']);
		mod_data_items($params['page']);
		mod_data_items($page_ar);
	}
	//コンタクトフォーム部分
	if (check_rights('edit') && !isset($page_ar['meta']['flexedit']['contact'][0]['text'])) {
		//データがないので初期化
$tmp = <<<EOT
		<div id='onethird-contactform'>
			<div class='item'>
				<span class='title'>お名前</span>
				<div><input type="text" class='contact_input'  /></div>
			</div>
			<div class='item must'>
				<span class='title'>メール</span>
				<div><input type="email" class='contact_input'  /></div>
			</div>
			<div class='item'>
				<span class='title '>電話</span>
				<div><input type="tel" class='contact_input' /></div>
			</div>
			<div class='item'>
				<span class='title '>性別</span>
				<div>
					<label><input type="radio" name='contact_1' class='contact_input'  />男性</label>
					<label><input type="radio" name='contact_1' class='contact_input'  />女性</label>
				</div>
			</div>
			<div class='item must'>
				<span class='title '>内容</span>
				<div><textarea class='contact_input' ></textarea></div>
			</div>
			<div class='item'>
				<span class='title '>その他</span>
				<div>
					<label><input type="checkbox" class='contact_input' />メールマガジンを希望する</label>
				</div>
			</div>
		</div>
EOT;
		$page_ar['meta']['flexedit']['contact'][0]['text'] = $tmp;
		mod_data_items($page_ar);
	}
	
	//表示
	$buff = '';
	if (check_rights('edit') && empty($page_ar['meta']['contact_form']['adr_page'])) {
$buff .= <<<EOT
		<p style='background-color: #FFE2E2;padding: 20px;'>
			{$ut->icon('caution')} Mail address not set.
		</p>
EOT;
	}
	$buff .= edit_proc(array('page'=>$page_ar['id'],'name'=>'contact','mode'=>'inline','no-edit'=>'true'));

	//オプション
	if (check_rights('owner')) {
$buff .= <<<EOT
		<div class='edit_pointer'>
EOT;
			$buff .= std_blockmenu_renderer($page_ar);
			if (!empty($page_ar['meta']['contact_form']['manager'])) {
$buff .= <<<EOT
				<a href='{$ut->link($page_ar['link'],'&:mode=contact_manager')}' class='onethird-button mini'>Manager</a>
EOT;
			}
$buff .= <<<EOT
			<a href='javascript:void(ot.contact_form_setting())' class='onethird-blockmenu'>{$ut->icon('setting')}</a>
			<a href='javascript:void(ot.inpage_edit({"page":{$page_ar['id']}, "name":"contact_send_mess", "idx":"0"}))' class='onethird-button mini'>送信時メッセージ</a>
			<span onclick="ot.inpage_edit({'page':{$page_ar['id']},'mode':this,'name':'contact' ,'idx':'0','onedit':function(){ot.set_contact_option()} })">{$ut->icon('edit',' class="onethird-blockmenu" ')}</span>
		</div>
EOT;
		snippet_std_setting('Contact form setting','contact_form_setting');
		if (isset($_POST['ajax']) && $_POST['ajax'] == 'contact_form_setting')  {
			$r = array();
			$r['result'] = true;
			$p_adr = '';
			if (isset($page_ar['meta']['contact_form']['adr_page'])) {
				$p_adr = $page_ar['meta']['contact_form']['adr_page'];
			}
$r['html'] = <<<EOT
			<table>
				<tr>
					<td>送信先メールアドレス</td>
					<td><input type='text' data-input='p_adr' value='{$p_adr}' />
				</tr>
				<tr>
					<td>Option</td>
					<td><label><input type='checkbox' data-input='p_manager' {$ut->check(!empty($page_ar['meta']['contact_form']['manager']),' checked ')}  />Use Manager</label>
				</tr>
			</table>
EOT;
			echo(json_encode($r));
			exit();
		}
		 
		if (isset($_POST['ajax']) && $_POST['ajax'] == 'save_contact_form_setting')  {
			$r = array();
			$r['id'] = $page_ar['id'];
			$page_ar['meta']['contact_form'] = array();
			$r['meta']['contact_form']['adr_page'] = sanitize_str($_POST['p_adr']);
			if (!empty($_POST['p_manager']) && $_POST['p_manager'] == 'true') {
				$r['meta']['contact_form']['manager'] = true;
			} else {
				$r['meta']['contact_form']['manager'] = '';
			}
			$r['result'] = mod_data_items($r);
			echo(json_encode($r));
			exit();
		}
	}

$buff .= <<<EOT
		<form id='form_contact_ex' method='post' action='{$params['safe_request']}' >
			<input type='hidden' name='post' value='*send_contact_ex' />
			<textarea name='contents' id='contents' style='display:none' />
			</textarea>
			<div class='action-panel'>
				<input type='button' id='contact_back' onclick='ot.contact_back()' style='display:none' class="onethird-button" value='修正する' />
				<input type='button' id='contact_next' onclick='ot.contact_next()' class="onethird-button default" value='内容を確認して送信する &raquo;' />
				<input type='button' id='contact_send' onclick='ot.contact_send()' style='display:none' class="onethird-button default" value='送信する &raquo;' />
			</div>
		</form>
EOT;

	return $buff;

}

function contact_page(&$page_ar)
{
	global $html,$params,$database,$config,$ut;

	$p_page = $page_ar['id'];

$html['css']['contact_page'] = <<<EOT
	<style>
		.contact_post {
			font-family:'Open Sans','Helvetica Neue',Helvetica,Meiryo,Arial,sans-serif;
			padding: 10px 20px;
			background-color: rgba(192, 192, 192, 0.18);
		}
	</style>
EOT;

	if (isset($_GET['mode']) && $_GET['mode']=='edit') {
		set_func_rights('page_property');
		provide_edit_module();
		snippet_page_property();
		snippet_breadcrumb($p_page, 'Page Edit');
		$buff = page_edit_renderer($params['page']);
		return frame_renderer($buff);
	}

	$buff ='';
	if (check_rights('edit')) {
		unset($params['breadcrumb']);
		$params['breadcrumb'][] = array( 'link'=>'', 'text'=>"<a href='{$ut->link($params['page']['link'],'&:mode=contact_manager')}'>Manager</a>" );
		$params['breadcrumb'][] = array( 'link'=>'', 'text'=>"View data" );
		$page_ar['title'] = '';
		if (!empty($page_ar['meta']['post_contents'])) {
			$buff .= frame_renderer("<pre class='contact_post'>{$ut->safe_echo($page_ar['meta']['post_contents'])}}</pre>");	//JPCERT#97053126 対DB
		}
		$buff .= frame_renderer(body_renderer($page_ar));
		$buff .= innerpage_renderer($page_ar['id']);
		if (!empty($page_ar['meta']['plugin_contact']['file'])) {
			foreach ($page_ar['meta']['plugin_contact']['file'] as $v) {
$buff .= <<<EOT
				<p>
					<a href='img.php?p={$page_ar['id']}&amp;i={$v}'>{$v}</a>
				</p>
EOT;
			}
		}

	} else {
		snippet_breadcrumb($page_ar['link'], "error");
		//ページは表示しない
	}
	return $buff;

}

function contact_manager(&$page_ar)
{
	global $params, $ut, $html, $database;

	$params['rendering'] = false;
	$p_page = $page_ar['id'];
	
	unset($html['article']);
	$buff = '';
$buff .= <<<EOT
	<h1>Manager</h1>
EOT;
	
	$sql = array();
	$sql[0] = "select id,type,metadata,title,mode,user,date from ".DBX."data_items ";
	$sql[1] = array(" where type=? and (block_type=0 or block_type=10 or block_type=20) ",CONTACT_ID);
	$sql[2] = array(" order by date desc");
	$database->debug();
	$ar = $database->sql_select_all($sql);

	if ($ar) {
		$column = array();
		foreach ($ar as &$v) {
			$m = $v['meta'] = unserialize64($v['metadata']);
			unset($v['metadata']);
			if (!empty($m['plugin_embed'])) {
				foreach ($m['plugin_embed'] as $k=>$vv) {
					$column[$k] = 0;
				}
			}
		}
		unset($v);
$buff .= <<<EOT
		<table class='onethird-table'>
			<tr>
				<th>date</th>
EOT;
				foreach ($column as $k=>$v) {
$buff .= <<<EOT
					<th>$k</th>
EOT;
				}
$buff .= <<<EOT
			</tr>
EOT;
			foreach ($ar as $v) {
				$m = $v['meta'];
				$ico = '';
				$u = $ut->link($v['id']);
				if ($v['type'] == PAGE_FOLDER_ID) { $ico = $ut->icon('folder',"width='16'"); }
				$d = substr($v['date'],0,10);
$buff .= <<<EOT
				<tr>
					<td><a href='{$ut->link($v['id'])}'>$d</a></td>
EOT;
					foreach ($column as $k=>$vv) {
						if (!empty($m['plugin_embed'][$k]) && $m['plugin_embed'][$k]) {
							$m['plugin_embed'][$k] = adjust_mstring($m['plugin_embed'][$k],30);
$buff .= <<<EOT
							<td>{$m['plugin_embed'][$k]}</td>
EOT;
						} else {
$buff .= <<<EOT
							<td></td>
EOT;
						}
					}
$buff .= <<<EOT
				</tr>
EOT;
			}
$buff .= <<<EOT
		</table>
EOT;
	}


	$html['article'][] = $buff;

}


?>