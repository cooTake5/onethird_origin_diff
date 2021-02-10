<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.

	if (!function_exists('check_func_rights') || !check_func_rights('edit.modules')) {
		die('error-utility.std');
	}

function _snippet_inpage_edit()
{
	global $html,$database,$params,$config,$p_circle,$plugin_ar,$ut;

	//２重書き込み禁止
	if (isset($html['meta']['inpage_edit'])) {
		return;
	}

	snippet_dialog();
	snippet_delayedload();

	/*$opt = array();
	if (isset($page_ar['block_type']) && $page_ar['block_type'] == 5) {
		$opt['id'] = $page_ar['link'];
	} else {
		$opt['id'] = $page_ar['id'];
	}
	if (isset($page_ar['meta'])) {
		if (!isset($page_ar['meta']['og_image'])) {
			$opt['og_image'] = false;
		} else {
			$opt['og_image'] = $page_ar['meta']['og_image'];
		}
	}*/
	snippet_image_uploader();
	
	$buff ='';

	//inpage_edit
	$css_file = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'style.css';
	if (!is_file($css_file)) {
		$css_file = "{$config['site_url']}css".DIRECTORY_SEPARATOR."onethird.css";
	} else {
		$css_file = $params['circle']['files_url'].'style.css';
	}

	$insert_image_opt = " alt='' class='onethird-article-image' ";
	if (!empty($params['insert_image_opt'])) {
		$insert_image_opt = $params['insert_image_opt'];
	}
$buff .= <<<EOT
<script>
	if (!ot.editor) { ot.editor = {}; }
	ot.editor.menu = false;
	ot.editor.css = '$css_file';
	
	ot.editor.insertimg = function (obj) {
		ot.tryitEditor.recov_cursor();
		if (obj.tagName == 'P') {
			var a = \$(obj).attr('data-url').replace( /&/g , "&amp;" );
			var b = \$(obj).attr('data-name');
			ot.tryitEditor.insert("<a href='"+a+"' />"+b+"</a>");
		} else {
			var a = \$(obj).attr('src').replace( /&/g , "&amp;" );
			ot.tryitEditor.insert("<img src='"+a+"' $insert_image_opt />");
		}
		ot.close_dialog(\$('#onethird-uploader-dialog').hide(),true);
	};

EOT;
	
	if (isset($params['page']['id'])) {
$buff .= <<<EOT
		ot.open_local_filer = function() {
			ot.open_uploader({select:function(obj){ot.editor.insertimg(obj)}});
		};
EOT;
	}
		
$buff .= <<<EOT
	ot.inpage_edit = function (option) {
		var opt = "ajax=inpage_edit_init";
		ot.editor.height = ot.editor.body_id = ot.editor.body_css = ot.editor.menu = ot.editor.cr = false;

		ot.editor.option = option;
		if (typeof option['mode'] == 'object') {
			opt +="&mode=obj";
			ot.editor.mode = option['mode'];
		} else {
			if (option['mode'] !== undefined) { opt +="&mode="+option['mode']; }
		}
		if (option['idx'] !== undefined) { opt +="&idx="+option['idx'];	}
		if (option['name'] !== undefined) {	opt +="&name="+option['name'];	}

		if (option['page'] !== undefined) {
			ot.editor.page = option['page'];
			opt +="&page="+option['page'];
		}

		if (option['body_id'] !== undefined) { opt +="&body_id="+option['body_id']; }
		if (option['body_css'] !== undefined) { opt +="&body_css="+option['body_css']; }
		if (option['css'] !== undefined) { opt +="&css="+option['css']; }
		if (option['width'] !== undefined) { opt +="&width="+option['width']; }
		if (option['height'] !== undefined) { opt +="&height="+option['height']; }
		
		if (option['cr'] !== undefined) { ot.editor.cr = option['cr']; }
		if (option['menu'] !== undefined) { ot.editor.menu = true; }

		\$('#dialog_inpage_edit').remove();
		
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: opt
			, dataType:'json'
			, success: function(data){
				if (data && data['result']) {
					\$("#dialog_inpage_edit").remove();
					if (data['mode'] != 'obj') {
						if (!data['html']) { return; }
						\$("body").append(data['html']);
					}
					if (option['mode'] == 1) {
						ot.editor.width = 600;
					} else {
						ot.editor.width = 570;
					}
					if (data.editor_toolbar) {
						ot.editor.editor_toolbar = data.editor_toolbar;
					}
					if (data['width']) { ot.editor.width = data['width']; }
					if (data['css']) { ot.editor.css = data['css']; }
					if (data['body_id']) { ot.editor.body_id = data['body_id']; }
					if (data['body_css']) { ot.editor.body_css = data['body_css']; }

					var obj = \$( "#dialog_inpage_edit" ).width(ot.editor.width+60);
					if (data['mode'] == 1) {
						//text
						if (data['height']) {
							ot.editor.height = data['height'];
						} else {
							ot.editor.height = 30;
						}
						\$('#contents_inpage_text').height(ot.editor.height+100);
					} else {
						if (data['height']) {
							ot.editor.height = data['height'];
						} else {
							ot.editor.height = 200;
						}
					}
					if (data['mode'] != 'obj') {
						ot.open_dialog(obj,{top:70});
						if (data['mode'] != 1) {
							//popup
							var load = [];
							load.push({type:'script', src:'{$config['site_url']}js/tryitEditor.js' });
							delayedload(load
								,function() {
									var opt = ot.editor.option || {};
									if (ot.editor.css) { opt.content_css = ot.editor.css; }
									if (ot.editor.body_id) { opt.body_id = ot.editor.body_id; }
									if (ot.editor.body_css) {	
										opt.body_css = ot.editor.body_css;
									}
									opt.after_toolbar = '';
									if (ot.editor_toolbar) {
										opt.after_toolbar += ot.editor_toolbar;
									}
									if (opt.editor_toolbar === undefined && ot.editor.editor_toolbar) {
										opt.after_toolbar += ot.editor.editor_toolbar;
									}
									if (ot.inpage_edit_option) {
										var a = ot.inpage_edit_option('popup',opt);
										if (a) {
											opt = a;
										}
									}
									if (opt.onedit === undefined && ot.editor.onedit) {
										opt.onedit = ot.editor.onedit;
									}
									if (opt.onclose === undefined && ot.editor.onclose) {
										opt.onclose = ot.editor.onclose;
									}
									opt.basepath = "{$ut->str(addslashes($config['site_url']))}";
									ot.tryitEditor.create('#contents_inpage_edit',opt);
								}
							);
						}

					} else {
						// inline
						if (ot.tryitEditor && ot.tryitEditor.check_instance()) {
							return;
						}
						ot.editor.org_html = data['html'];
						ot.editor.obj = \$(ot.editor.mode).parents('.onethird-edit-pointer')[0];
						var obj = \$(ot.editor.mode).parent().hide();
						\$(ot.editor.obj).after(obj);
						ot.editor.quit = function() {
							var obj = \$(ot.editor.mode).parent().show();
							\$(ot.editor.obj).append(obj);
							ot.tryitEditor.quit();
						};
						var load = [];
						load.push({type:'script', src:'{$config['site_url']}js/tryitEditor.js' });
						delayedload(load
							,function() {
								var opt = ot.editor.option || {};
								var param = {};
								param.idx = ot.editor.option.idx;
								param.mode = 'obj';
								param.page = ot.editor.option.page;
								param.name = ot.editor.option.name;
								if (ot.editor.ctox) {
									param.ctox = ot.editor.ctox;
								}
								if (opt.onedit === undefined && ot.editor.onedit) {
									opt.onedit = ot.editor.onedit;
								}
								if (opt.onclose === undefined && ot.editor.onclose) {
									opt.onclose = ot.editor.onclose;
								}
								opt.empty_str = '...';
								opt.html = ot.editor.org_html;
								if (opt.width === undefined) { opt.width = ot.editor.option.width; }
								if (opt.body_css === undefined) { opt.body_css = ot.editor.option.body_css; }

								var tmp_toolbar = opt.after_toolbar;
								opt.after_toolbar = "<br 'clear:both' />";

								opt.after_toolbar += "<input type='button' class='onethird-button mini' value='Quit' onclick='ot.editor.quit()' />";
								opt.after_toolbar += "<input type='button' class='onethird-button mini' value='更新' onclick='ot.inpage_edit_save("+JSON.stringify(param)+")' />";
								opt.after_toolbar += "<input type='button' class='onethird-button mini' value='画像挿入' onclick='ot.open_local_filer()' />";
								param.mode='-';
								opt.after_toolbar += "<input type='button' class='onethird-button mini' value='削除' onclick='ot.inpage_edit_save("+JSON.stringify(param)+")' />";
								
								if (tmp_toolbar === undefined) {
									if (ot.editor_toolbar) {
										opt.after_toolbar += ot.editor_toolbar;
									}
								} else {
									opt.after_toolbar += tmp_toolbar;
								}
								if (ot.inpage_edit_option) {
									var a = ot.inpage_edit_option('inline',opt);
									if (a) {
										option = a;
									}
								}
								opt.basepath = "{$ut->str(addslashes($config['site_url']))}";
								ot.tryitEditor.create(ot.editor.obj,opt);
							}
						);
					}
				}
			}
		});
	};

	ot.inpage_edit_save = function(option) {
		var html = '';
		var opt = '';
		var mode = 0;
		
		if (option['mode'] == '-') {
			if (!confirm('データを削除しますか?')) {
				return;
			}
		}

		if (option['mode'] !== undefined) {
			mode = option['mode'];
			opt +="&mode="+encodeURIComponent(mode);
		}
		if (option['page'] !== undefined) { opt +="&page="+option['page']; }
		if (option['name'] !== undefined) { opt +="&name="+option['name']; }
		if (option['ctox'] !== undefined) { opt +="&ctox="+option['ctox']; }
		if (option['idx'] !== undefined) { opt +="&idx="+option['idx']; }
		if (option['tpl'] !== undefined) { opt +="&tpl="+option['tpl']; }

		if (mode === '0' || mode == 'obj') {
			html = ot.tryitEditor.html();

		} else if (mode === '1') {
			html = \$('#contents_inpage_text').val();
		}

		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=inpage_edit_save"+opt+"&data="+encodeURIComponent(html)
			, dataType:'json'
			, success: function(data){
				if (data && data['result']) {
					location.reload(true);
				}
			}
		});
	};

</script>
EOT;

	// inpage_edit
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'inpage_edit_save')  {
		$r = array();
		$r['result'] = false;

		$mode = 0;
		$ctox = $name = $idx = false;
		$data = $tpl = '';
		
		if (isset($_POST['page'])) {
			$r['id'] = (int)$_POST['page'];
		} else {
			echo( json_encode($r) );
			exit();
		}

		if (isset($_POST['mode'])) { $mode = sanitize_asc($_POST['mode']); }
		if (isset($_POST['name'])) { $name = sanitize_asc($_POST['name']); }
		if (isset($_POST['idx'])) { $idx = sanitize_asc($_POST['idx']); } else {$idx=0;}
		if (isset($_POST['tpl'])) { $tpl = sanitize_asc($_POST['tpl']); }
		if (isset($_POST['ctox'])) { $ctox = true; }

		if (isset($_POST['data'])) {
			$data = save_contents_script(sanitize_html($_POST['data']));
		}

		$meta = array();
		if ($mode == '-') {
			if ($name) {
				$ar = $database->sql_select_all("select metadata from ".DBX."data_items where id=?", $r['id']);
				if ($ar) {
					if ($ar[0]['metadata']) {
						$meta = unserialize64($ar[0]['metadata']);
						if ($idx !== false) {
							unset($meta['flexedit'][$name][$idx]);
						} else {
							unset($meta['flextext'][$name]);
						}
						if ($database->sql_update( "update ".DBX."data_items set metadata=?,mod_date=? where id=?", serialize64($meta), $params['now'], (int)$r['id'])) {
							$r['result'] = true;
						}
					}
				}
			} else {
				if ($database->sql_update("update ".DBX."data_items set contents='' where id=?", (int)$r['id'])) {
					$r['result'] = true;
				}
			}

		} else if ($mode == '+' || $mode == '#') {
			$template = '';
			if (isset($params['circle']['meta']['def_template'][$tpl]) && $name != 'data') {
				$template = $params['circle']['meta']['def_template'][$tpl];
				$r['template'] = $template;
			}
			$r['mode'] = $name;
			function _expand_template($a) {
				if (strstr($a, '{new_page}') !== false) {
					// ページ新規作成
					$r = array();
					$r['id'] = 0;
					$r['result'] = false;
					$r['type'] = 1;
					$r['link'] = 0;
					if (create_page($r)) {
						$a = str_replace('{new_page}', $r['id'], $a);
					}
				}
				return sanitize_html($a);
			}
			if ($name) {
				$ar = $database->sql_select_all("select metadata from ".DBX."data_items where id=?", $r['id']);
				if ($ar) {
					if ($ar[0]['metadata']) {
						$meta = unserialize64($ar[0]['metadata']);
						if ($idx !== false) {
							$n = array();
							if (isset($meta['flexedit'][$name])) {
								if ($mode == '#') {
									$n[] = array('text'=>_expand_template($template));
									$idx = -1;
								}
								foreach ($meta['flexedit'][$name] as $k => $v) {
									$n[] = $v;
									if ($idx == $k) {
										$n[] = array('text'=>_expand_template($template));
									}
								}
								if (count($n) == 0) {
									$n[] = array('text'=>_expand_template($template));
								}
							}
							$meta['flexedit'][$name] = $n;
						} else {
							$meta['flexedit'][$name][] = array('text'=>_expand_template($template));
						}
						if ($database->sql_update( "update ".DBX."data_items set metadata=?,mod_date=? where id=?", serialize64($meta), $params['now'], $r['id'])) {
							$r['result'] = true;
						}
					}
				}
			} else {
				if ($database->sql_update( "update ".DBX."data_items set contents=?, mod_date=? where id=?", save_contents_script($data), $params['now'], $r['id'])) {
					$r['result'] = true;
				}
			}

		} else if ($mode == '^' || $mode == 'v') {
			$r['mode'] = $name;
			if ($name) {
				$ar = $database->sql_select_all("select metadata from ".DBX."data_items where id=?", $r['id']);
				if ($ar) {
					if ($ar[0]['metadata']) {
						$meta = unserialize64($ar[0]['metadata']);
						if ($idx !== false) {
							$n = array();
							if (isset($meta['flexedit'][$name])) {
								$n_v = false;
								foreach ($meta['flexedit'][$name] as $k => $v) {
									if ($idx == $k) {
										if ($mode == '^') {
											$t = array_pop($n);
											if ($t !== null) {
												$n[] = $v;
												$n[] = $t;
											} else {
												$n[] = $v;
											}
										} else {
											$n_v = $v;
											continue;
										}
									} else {
										$n[] = $v;
									}
									if ($n_v) {
										$n[] = $n_v;
										$n_v = false;
									}
								}
								if (count($n) == 0) {
									$n[] = array('text'=>$template);
								}
							}
							$meta['flexedit'][$name] = $n;
							if ($database->sql_update( "update ".DBX."data_items set metadata=?,mod_date=? where id=?", serialize64($meta), $params['now'], $r['id'])) {
								$r['result'] = true;
							}
						}
					}
				}
			}

		} else if ($data) {
			if ($name && $name != 'contents') {
				$ar = $database->sql_select_all("select metadata from ".DBX."data_items where id=?", $r['id']);
				if ($ar) {
					if (!$ar[0]['metadata']) {
						$meta = array();
					} else {
						$meta = unserialize64($ar[0]['metadata']);
					}
					if ($idx !== false) {
						$meta['flexedit'][$name][$idx]['text'] = $data;
						$r['flexedit'] = $data;
						if ($ctox) {
							$meta['flextext_ctox'][$name] = true;
						} else {
							unset($meta['flextext_ctox'][$name]);
						}
						if ($database->sql_update( "update ".DBX."data_items set metadata=?, mod_date=? where id=?", serialize64($meta), $params['now'], $r['id'])) {
							$r['result'] = true;
						}
						if (isset($meta['flextext_ctox'])) {
							$c = '';
							foreach ($meta['flextext_ctox'] as $k=>$v) {
								if (isset($meta['flexedit'][$k])) {
									foreach ($meta['flexedit'][$k] as $vv) {
										if (is_array($vv)) {
											if (isset($vv['text'])) {
												$c .= $vv['text'];
											}
										} else {
											$c .= $vv;
										}
										$c .= ' ';
									}
								}
							}
							if ($database->sql_update( "update ".DBX."data_items set contents=?, mod_date=? where id=?", save_contents_script($c), $params['now'],$r['id'])) {
							}
						}
					} else {
						//$r['idx'] = $idx;
					}
				}
			} else {
				if ($database->sql_update( "update ".DBX."data_items set contents=?, mod_date=? where id=?", save_contents_script($data), $params['now'], $r['id'])) {
					$r['result'] = true;
				}
			}
		}
		echo( json_encode($r) );
		exit();
	}
	
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'inpage_edit_init')  {
		$r = array();
		$r['result'] = false;
		$mode = 0;
		$name = $idx = false;
		
		if (isset($_POST['page'])) {
			if ($_POST['page'] == 'top') {
				$r['id'] = (int)$params['circle']['meta']['top_page'];
			} else {
				$r['id'] = (int)$_POST['page'];
			}
		} else {
			echo( json_encode($r) );
			exit();
		}
		
		if (isset($_POST['mode'])) { $mode = sanitize_asc($_POST['mode']); }
		if (isset($_POST['name'])) { $name = sanitize_asc($_POST['name']); }
		if (isset($_POST['idx'])) { $idx = sanitize_asc($_POST['idx']); } else {$idx=0;}

		if (isset($_POST['body_id'])) { $r['body_id'] = sanitize_asc($_POST['body_id']); }
		if (isset($_POST['body_css'])) { $r['body_css'] = sanitize_asc($_POST['body_css']); }
		if (isset($_POST['css'])) { $r['css'] = sanitize_str($_POST['css']); }
		if (isset($_POST['width'])) { $r['width'] = (int)$_POST['width']; }
		if (isset($_POST['height'])) { $r['height'] = (int)$_POST['height']; }

		$ar = $database->sql_select_all("select contents,metadata from ".DBX."data_items where id=?", $r['id']);
		if ($ar) {
			$r['result'] = true;
			$r['mode'] = $mode;
			if ($ar[0]['contents']) {
				$ar[0]['contents'] = $ar[0]['contents'];			
			}
			$v = '';
			if ($name != 'contents' && $name !== false && $ar[0]['metadata']) {
				$m = unserialize64($ar[0]['metadata']);
				if ($idx !== false) {
					if (isset($m['flexedit'][$name][$idx])) {
						$v = $m['flexedit'][$name][$idx]['text'];
					}
				} else {
					if (isset($m['flextext'][$name])) {
						$v = $m['flextext'][$name];
					}
				}
			} else {
				$v = $ar[0]['contents'];
			}
			$v = echo_contents_script($v);
        	$v = str_replace('\\\$', '\\$' , $v);
			$v = str_replace('\$','$', $v);
			if ($mode == 1) {
$r['html'] = <<<EOT
				<div id="dialog_inpage_edit" class='onethird-dialog' >
					<p class='title'>編集</p>
					<div style='margin:20px'>
						<textarea id='contents_inpage_text' style='width:100%;height:350px;box-sizing: border-box;-moz-box-sizing: border-box;'>{$v}</textarea>
						<div class='actions'>
							<input type='button' class='onethird-button' value='更新' onclick="ot.inpage_edit_save({'page':'{$r['id']}','mode':'$mode','name':'{$name}','idx':'$idx'})" />
							<input type='button' class='onethird-button' value='削除' onclick="ot.inpage_edit_save({'page':'{$r['id']}','mode':'-','name':'{$name}','idx':'$idx'})" />
							<input type='button' class='onethird-button' value='Close' onclick='ot.close_dialog(this,true)' />
						</div>
					</div>
				</div>
EOT;
			} else if ($mode === 'obj') {

				if (!$v || $v=='...') {$v='<p></p>';}
				$r['html'] = $v;

			} else {
				if (!$v) {$v='<p></p>';}
				$tmp = '';
$tmp .= <<<EOT
				<div id="dialog_inpage_edit" class='onethird-dialog' >
					<p class='title'>編集</p>
					<div style='margin:10px'>
						<textarea  id='contents_inpage_edit' style='width:100%'>{$v}</textarea >
					</div>
				</div>
EOT;
				$r['html'] = $tmp;
$tmp .= <<<EOT
				<input type='button' class='onethird-button mini' value='Close' onclick='ot.close_dialog(this,true)' />
				<input type='button' class='onethird-button mini' value='更新' onclick="ot.inpage_edit_save({'page':'{$r['id']}','mode':'$mode','name':'{$name}','idx':'$idx'})" />
				<input type='button' class='onethird-button mini' value='画像挿入' onclick='ot.open_local_filer()' />
				<input type='button' class='onethird-button mini' value='削除' onclick="ot.inpage_edit_save({'page':'{$r['id']}','mode':'-','name':'{$name}','idx':'$idx'})" />
EOT;
				$r['editor_toolbar'] = $tmp;
			}

		}
		echo( json_encode($r) );
		exit();
	}
	
	$html['meta']['inpage_edit'] = $buff;
}

function _snippet_image_uploader()
{
	global $html,$p_circle,$database,$config,$params,$ut;
	
	if (isset($params['page']['id'])) {
		$p_page = $params['page']['id'];
	} else {
		$p_page = 0;
	}
	$gallery_id = $p_page;

	$og_image = null;
	if (!empty($params['page']['block_type']) && $params['page']['block_type'] == 5) {
		$id = $params['page']['link'];
		if (read_pagedata($params['page']['link'], $t)) {
			if (!empty($t['meta']['og_image'])) {
				$og_image = $t['meta']['og_image'];
			}
		}
	} else {
		if (!empty($params['page']['meta']['og_image'])) {
			$og_image = $params['page']['meta']['og_image'];
		}
	}
	$thumb_img = null;
	if (!empty($params['page']['block_type']) && $params['page']['block_type'] == 5) {
		$id = $params['page']['link'];
		if (read_pagedata($params['page']['link'], $t)) {
			if (!empty($t['meta']['thumb_img'])) {
				$thumb_img = $t['meta']['thumb_img'];
			}
		}
	} else {
		if (!empty($params['page']['meta']['thumb_img'])) {
			$thumb_img = $params['page']['meta']['thumb_img'];
		}
	}
	
	if (!empty($params['img_quality'])) {
		$quality = $params['img_quality']/100;

	} else if (!empty($config['img_quality'])) {
		$quality = $config['img_quality']/100;

	} else {
		$quality = 90/100;
	}

	$a = '';
$a = <<<EOT
	<script>
	ot.uploader = {};
	ot.open_uploader = function (opt) {
		if(ot.tryitEditor) { ot.tryitEditor.save_cursor(); }
		var id = {$gallery_id};
		ot.uploader['gallery_id'] = id;
		opt = opt || {};
		var u = "ajax=open_uploader&id="+id;
		if (opt.page) {
			u += '&page='+opt.page;
			ot.uploader['page'] = opt.page;
		}
		if (typeof(opt.group) !== "undefined") {
			u += '&group='+opt.group;
			ot.uploader['group'] = opt.group;
		} else {
			ot.uploader.group = '';
		}
		if (opt.name) {
			u += '&name='+opt.name;
			ot.uploader['name'] = opt.name;
		}
		if (opt['add-script']) {
			ot.uploader['add-script'] = opt['add-script'];
		}
		if (opt.info) {
			u += '&info='+encodeURIComponent(opt.info);
		}
		if (opt.title) {
			u += '&title='+encodeURIComponent(opt.title);
		}
		if (ot.otoken) {
			u += '&otoken='+ot.otoken;
		}
		if (opt.resize) {
			u += '&resize='+encodeURIComponent(opt.resize);
			ot.uploader.resize = opt.resize;
		}
		if (opt.thumb) {
			ot.uploader.thumb = true;
			u += '&thumb=true';
		}
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: u
			, dataType:'json'
			, opt:opt
			, success: function(data){
				if (data && data['result'] && data['html']) {
					\$("#onethird-uploader-dialog").remove();
					\$('body').append('<div id="onethird-uploader-dialog" class="onethird-dialog " >'+data['html']+'</div>');
					ot.open_dialog(\$("#onethird-uploader-dialog").width(800));
					ot.read_image_list(data['img_list'],data['img_url']);
					ot.uploader.group = data['group'];
					if (ot.uploader['add-script']) {
						ot.uploader['add-script']();
					}
					if (this.opt['resize']) {
						var resize = this.opt['resize'];
						\$('.onethird-uploader-unit input[name=resize]').each(function(){
							if (parseInt(\$(this).attr('data-x')) >= resize) {
								\$(this).attr('checked','checked');
								return false;
							}
						});
					}
				} else {
				}
			}
		});
		if (opt['select']) {
			ot.uploader['select'] = opt['select'];
		}
	};
	ot.uploader.on = function(ev,obj) {
		if (ot.uploader[ev]) {
			ot.uploader[ev](obj);
		}
	};
	ot.uploader.place = function (place) {
		var u = "ajax=set_uploader_place";
		u += '&group='+place;
		ot.uploader['group'] = place;
		u += '&title='+\$('#onethird-uploader-dialog .title').text();
		if (ot.uploader.thumb) {
			u += '&thumb=true';
		}
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: u
			, dataType:'json'
			, success: function(data){
				if (data && data['result'] && data['html']) {
					var o = \$("#onethird-uploader-dialog");
					o.html(data['html']);
					if (ot.uploader['add-script']) {
						ot.uploader['add-script']();
					}
					ot.read_image_list(data['img_list'],data['img_url']);
				}
			}
		});
	};
EOT;
$a .= <<<EOT
	</script>
EOT;
	$html['meta']['snippet_uploader']['main'] = $a;
	
	$uploader_resize = array();
	$uploader_resize[] = array(320,240);
	$uploader_resize[] = array(640,480);
	$uploader_resize[] = array(1200,630);
	$uploader_resize[] = array(0,0);
	if (isset($_COOKIE['uploader_resize'])) {
		$uploader_resize[$_COOKIE['uploader_resize']][2] = 'default';
	}
	if (isset($params['uploader']['uploader_resize'])) {
		$uploader_resize = $params['uploader']['uploader_resize'];
	}
	if (isset($params['circle']['uploader_resize'])) {
		$params['uploader']['uploader_resize'] = $params['circle']['uploader_resize'];
		$uploader_resize = $params['circle']['uploader_resize'];
	}
	if (!isset($params['uploader']['uploader_resize'])) {
		$params['uploader']['uploader_resize'] = $uploader_resize;
	}

	if (isset($_POST['ajax']) && ($_POST['ajax'] == 'open_uploader'||$_POST['ajax'] == 'set_uploader_place'))  {
		$r = array();
		$r['result'] = true;
		$group = false;
		$group_path_info = '';
		$group_lock = false;
		$thumb = false;
		if (isset($_POST['thumb'])) {
			$thumb = true;
		}
		if (isset($_POST['id'])) {
			$id =  (int)($_POST['id']);
		}
		if (isset($_POST['page'])) {
			$id =  (int)($_POST['page']);
		} else {
			if (!$thumb &&$_POST['ajax'] == 'open_uploader' && isset($_COOKIE['upr_cache'])) {
				$group = $_COOKIE['upr_cache'];
			}
		}
		if (isset($_POST['group'])) {
			$group = sanitize_asc($_POST['group']);
			if ($group == 'false') { $group = ''; }
			if ($_POST['ajax'] == 'open_uploader') {
				$group_lock = true;
			}
		}
		if (!empty($params['page']['block_type']) && $params['page']['block_type'] == 5) {
			$id = $params['page']['link'];
		}
		$r['group'] = $group;
		if ($group) {
			$f = $config['files_path'].DIRECTORY_SEPARATOR.'img';
			$u = "{$config['files_url']}/img";
			$group_path_info = $config['files_url'].'img';
			if (substr($group,0,1) == '.') {
				$name = substr($group,1);
				$f = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'data';
				if (substr($params['circle']['files_url'],0,strlen($params['circle']['url'])) == $params['circle']['url']) {
					$x = substr($params['circle']['files_url'],strlen($params['circle']['url']));
					$u = 'timg.php?p='.$name.'&amp;i=';
					$group_path_info = "timg.php (files/{$p_circle}/data/$name)";
				} else {
					$u = $params['circle']['files_url'].'data/'.$name.'/';
					$group_path_info = $u;
				}
			} else {
				$name = $group;
				$u = 'img.php?p='.$name.'&amp;i=';
				$group_path_info = "img.php (files/img/$name)";
			}
			$f .= DIRECTORY_SEPARATOR.$name;
			$r['img_url'] = $u;
		} else {
			$f = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$id;
			$image_url = 'img.php?p='.$id.'&amp;i=';
			$group_path_info = "img.php (files/img/$id)";
			$r['img_url'] = $image_url;
			$group = $id;
		}

		$image_ar = array();
		if (is_dir($f)) {
			$ar = @scandir( $f );
			foreach ($ar as $v) {
				if (preg_match("/\.(jpeg|jpg|gif|png|zip|pdf|doc|docx|xls|xlsx|ppt|pptx|csv|webp)$/i",$v)) {
					if (is_file($f.DIRECTORY_SEPARATOR.$v)) {
						$image_ar[] = $v;
					}
				}
			}
		}
		$r['img_list'] = $image_ar;
		$info ='';
		$title = 'ファイル選択';
		if (isset($_POST['info'])) {
			$info = sanitize_str($_POST['info']);
		}
		if (isset($_POST['title'])) {
			$title = sanitize_str($_POST['title']);
		}
		$resize_mode = 'Jpeg';
		if (isset($_POST['resize'])) {
			$t = explode(',',$_POST['resize']);
			if (count($t) > 0) {
				$t2 = array();
				foreach($t as $v) {
					if ($v=='auto') {
						$resize_mode = 'Auto';
						continue;
					}
					$v = explode('/',$v);
					if (count($v) >= 2 || count($v) <= 4) {
						$t2[] = $v;
					}
				}
				if (count($t2) > 0) {$uploader_resize=$t2;}
			}
		}
$a = <<<EOT
	<div class='title'>{$title}</div>
	<div class='onethird-uploader-unit'  >
		<div id="onethird-uploader-list" class='list clearfix' ondragover="ot.upfile_dragover()" ondrop="ot.upfile_drop()" >
		</div>
		<div class='actions'>
			<span title='resize' > $resize_mode resize </span> : 
EOT;
			foreach ($uploader_resize as $k=>$v) {
				if (!empty($v[2])) {
					$c = ' checked ';
				} else {
					$c = '';
				}
				$q = '';
				if (isset($v[3])) {
					$c .= " data-quality='{$v[3]}'  ";
					$q = "({$ut->str($v[3]*100)}%)";
				}
				if ($v[0]) {
$a .= <<<EOT
					<label ><input type='radio' name='resize' data-x='{$v[0]}' data-y='{$v[1]}' data-z='{$k}' $c />{$v[0]}/{$v[1]}px{$q}</label>
EOT;
				} else {
$a .= <<<EOT
					<label ><input type='radio' name='resize' data-x='{$v[0]}' data-y='{$v[1]}' data-z='' $c />no-resize</label>
EOT;
				}
			}
			if ($info) {
$a .= <<<EOT
				<div class='message'>$info</div>
EOT;
			}
			$next_group = '';
			if (!empty($params['page']['id'])) {
				if (!is_numeric($group)) {
					if (substr($group,0,1) == '.') {
						$next_group = 'd'.date("Y_m");
					} else {
						$next_group = $params['page']['id'];
					}
				} else {
					$next_group = ".image";
					if (!empty($params['uploader']['theme_place'])) {
						$next_group = $params['uploader']['theme_place'];
					}
				}
			}
			if (!$group_lock) {
$a .= <<<EOT
				<div style='margin:10px 0 15px 0;'>
					<img title='theme image' src='{$config['site_url']}img/folder.png' onclick='ot.uploader.place("$next_group")' style='width:24px;vertical-align: middle;cursor:pointer' />
					$group_path_info
				</div>
EOT;
			} else {
$a .= <<<EOT
				<div style='margin:10px 0 15px 0;' >
					folder : $group_path_info
				</div>
EOT;
			}
$a .= <<<EOT
			<div class='btn-panel'>
				<input type='button' class='onethird-button' value='Close' onclick='ot.close_dialog("#onethird-uploader-dialog")' />
				<span class='upload-button onethird-button'>
					<span class='inner'>
						<span>アップロード</span>
						<input type="file" id="files" name="files[]" multiple onchange='ot.upload(event)' />
					</span>
				</span>
			</div>
		</div>
	</div>
EOT;
		$r['html'] = $a;
		echo( json_encode($r) );
		exit();
	}


$a = <<<EOT
	<script>
	ot.upfile_dragover = function() {
		event.stopPropagation();
		event.preventDefault();
		event.dataTransfer.dropEffect = 'copy';
    };
	ot.upfile_drop = function() {
		event.stopPropagation();
		event.preventDefault();
		ot.upload(event);
    };
	ot.upload = function(event) {
		var files;
		if (event.target && event.target.files) {
			files = event.target.files;
		} else if (event.dataTransfer && event.dataTransfer.files) {
			files = event.dataTransfer.files;
		} else {
			return;
		}
		reader_ar = [];
		image_ar = [];
		for (var i = 0; i < files.length; i++) {
			var file = files[i];
			var type = file.type;
			if (!type) {
				var tmp = file.name.split('.'); 
				if (tmp.length>1) {
					type = tmp[tmp.length-1];
				}
			}
			var is_resize = (/jpeg/i).test(type);
			if ((/png/i).test(type) && file.size > 1024*50) {
				is_resize = true;
			}
			var max_width = \$('input[name="resize"]:checked').attr('data-x');
			var max_height = \$('input[name="resize"]:checked').attr('data-y');
			if(is_resize && max_width>0 && max_height>0) {
				reader_ar[i] = new FileReader();
				reader_ar[i].readAsDataURL(file);
				reader_ar[i].fname = file.name;
				\$('#onethird-uploader-list').append("<div class='onethird-loading item' ><p><img src='{$config['site_url']}/img/loading.gif' /><br /><span>resizing...</span></p><div>");
				var a = \$('#onethird-uploader-list .onethird-loading');
				a.attr('data-count',a.attr('data-count')+1);
				reader_ar[i].onload = function (event) {
					image_ar[i] = new Image();
					image_ar[i].src = event.target.result;
					image_ar[i].fname = this.fname;
					image_ar[i].onload = function() {
						ot.resize_img(this);
					}
				};
				continue;

			} else if((/image/i).test(type) || (/(zip|pdf|word|docx|excel|xlsx|powerpoint|pptx|csv|webp)/i).test(type)) {
				reader_ar[i] = new FileReader();
				reader_ar[i].readAsDataURL(file);
				reader_ar[i].fname = file.name;
				reader_ar[i].onload = function (event) {
					var data = event.target.result;
					var u = "ajax=set_uploaded&data="+encodeURIComponent(data)+'&id='+ot.uploader['gallery_id'];
					if (ot.uploader.name) {
						u += '&name='+ot.uploader.name;
					}
					if (ot.uploader.page) {
						u += '&page='+ot.uploader.page;
					}
					if (ot.uploader.group) {
						u += '&group='+ot.uploader.group;
					}
					if (this.fname) {
						u += '&fname='+encodeURIComponent(this.fname);
					}
					if (ot.otoken) {
						u += '&otoken='+ot.otoken;
					}
					if ((/image/i).test(type) && ot.uploader.resize) {
						var resize_no = \$('input[name="resize"]:checked').attr('data-z');
						if (resize_no !== '' || parseInt(resize_no) > 0 ) {
							u += '&resize='+encodeURIComponent(ot.uploader.resize);
							u += '&resize_no='+resize_no;
						}
					}
					ot.ajax({
						type: "POST"
						, url: '{$params['request_name']}'
						, data: u
						, dataType:'json'
						, success: function(data){
							if ( data && data['result'] ) {
								ot.draw_image( data['url'], data['name'] );
							} else {
								alert('file type error.');
							}
							var a = \$('#onethird-uploader-list .onethird-loading');
							if (a.attr('data-count') >0) {
								a.attr('data-count',a.attr('data-count')-1);
							} else {
								a.remove();
								\$('.onethird-uploader-unit #files').val('');
							}
						}
					});
					\$('#onethird-uploader-list').append("<img src='{$config['site_url']}/img/loading.gif' class='onethird-loading' />");
					var a = \$('#onethird-uploader-list .onethird-loading');
					a.attr('data-count',a.attr('data-count')+1);
				};
			} else {
				alert('file type error ('+type+')');
			}
		}
	};
	ot.resize_img = function(obj) {
		var canvas = document.createElement('canvas');

		var width = obj.width;
		var height = obj.height;
		var max_width = \$('input[name="resize"]:checked').attr('data-x');
		var max_height = \$('input[name="resize"]:checked').attr('data-y');
		var resize_no = \$('input[name="resize"]:checked').attr('data-z');

		if (width > height) {
			if (width > max_width) {
				height = Math.round(height *= max_width / width);
				width = max_width;
			}
		} else {
			if (height > max_height) {
				width = Math.round(width *= max_height / height);
				height = max_height;
			}
		}
	  
		canvas.width = width;
		canvas.height = height;
		var ctx = canvas.getContext("2d");
		ctx.drawImage(obj, 0, 0, width, height);
		quality = $quality;
		if (\$('input[name="resize"]:checked').attr('data-quality')) {
			quality = \$('input[name="resize"]:checked').attr('data-quality');
		}
		var data = canvas.toDataURL("image/jpeg", parseFloat(quality));
		var u = "ajax=set_uploaded&data="+encodeURIComponent(data)+'&id='+ot.uploader['gallery_id'];
		if (obj.fname) {
			u += '&fname='+obj.fname;
		}
		if (ot.uploader.name) {
			u += '&name='+ot.uploader.name;
		}
		if (ot.uploader.page) {
			u += '&page='+ot.uploader.page;
		}
		if (ot.uploader.group) {
			u += '&group='+ot.uploader.group;
		}
		if (resize_no !==''  || parseInt(resize_no) > 0 ) {
			u += '&resize_no='+resize_no;
		}
		if (ot.otoken) {
			u += '&otoken='+ot.otoken;
		}
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: u
			, dataType:'json'
			, success: function(data){
				var a = \$('#onethird-uploader-list .onethird-loading');
				if ( data && data['result'] ) {
					ot.draw_image( data['url'], data['name'] );
					if (a.attr('data-count') >0) {
						a.attr('data-count',a.attr('data-count')-1);
					} else {
						a.remove();
						\$('.onethird-uploader-unit #files').val('');
					}
				} else {	
					alert('error');
					a.remove();
					\$('.onethird-uploader-unit #files').val('');
				}
			}
			, error: function() {
				var a = \$('#onethird-uploader-list .onethird-loading');
				alert('error');
				a.remove();
				\$('.onethird-uploader-unit #files').val('');
			}
		});
	};

	ot.read_image_list = function(img_list,image_url) {
		var i;
		for (i in img_list) {
			var image_name = img_list[i];
			ot.draw_image(image_url+image_name,image_name);
		}
	};
	ot.og_image = '{$og_image}';
	ot.thumb_img = '{$thumb_img}';
	ot.draw_image = function( file_url, image_name ) {
		if (!image_name) { return; }
		var img,delhnd,ogphnd,thumbhnd;
		var ok = false;
		\$('#onethird-uploader-list .item').each(function(){
			if (\$(this).attr('data-src')==image_name) {
				\$(this).remove();
			}
		});
		if (image_name.match(/(png|gif|jpg|jpeg)$/i)) {
			img = \$("<img src='"+file_url+"' class='picture' onclick='ot.uploader.on(\"select\",this)' >").load(function(e){
				\$(this).attr('title','image : '+this.naturalWidth+' x '+this.naturalHeight);
				if (this.naturalWidth < 301) {
					\$(this).css('padding','15px');
				} else if (this.naturalWidth < 600) {
					\$(this).css('padding','5px');
				}
			});
			thumbhnd = \$("<div class='thumbhnd hnd' style='top:0;right:4px' >{$ut->icon('thumbnail'," title='Thumbnail'")}</div>").click(function(event){
				event.stopPropagation();
				ot.set_ogimage(this,'thumb');
			});
			ogphnd = \$("<div class='ogphnd hnd' style='top:0;left:0;' >{$ut->icon('star',"  title='OGP image'")}</div>").click(function(event){
				event.stopPropagation();
				ot.set_ogimage(this);
			});
		} else {
			img = \$("<p data-url='"+file_url+"' data-name='"+image_name+"' onclick='ot.uploader.on(\"select\",this)'>{$ut->icon('zip'," width='20' ")}<br /><span >"+image_name+"</span></p>");
		}

		delhnd = \$("{$ut->icon('delete'," class='hnd' style='bottom:2px;left:2px;width:15px;height:15px' title='Remove'")}").click(function(event){
			event.stopPropagation();
			ot.remove_picture(this);
		});

		var o = \$("<div class='item' data-src='"+image_name+"'></div>").append(img);
		if (ot.og_image && image_name == ot.og_image.substr(-image_name.length) ) {
			o.addClass('ogp');
			if (o.attr('data-src') == '_ogp.jpg') {
				var a = o.find('.picture');
				var u = a.attr('src');
				u=u.replace('img.php?','img.php?tx='+Math.floor( Math.random() * 1000 )+'&')
				a.attr('src',u);
			}
		}
		if (ot.thumb_img && image_name == ot.thumb_img.substr(-image_name.length) ) {
			o.addClass('thumb');
			if (o.attr('data-src') == '_thumb.jpg') {
				var a = o.find('.picture');
				var u = a.attr('src');
				u=u.replace('img.php?','img.php?tx='+Math.floor( Math.random() * 1000 )+'&')
				a.attr('src',u);
			}
		}
		o.append(delhnd);
		o.append(ogphnd);
		o.append(thumbhnd);
		\$('#onethird-uploader-list').append(o);
	};
	ot.remove_picture = function ( obj ){
		var o = \$(obj).parent();
		var a = o.attr('data-src');
		if (!confirm("データを削除します\\n実行しますか？")) {
			event.preventDefault();
			return;
		}
		var id = ot.uploader['gallery_id'];
		var opt = '';
		if (ot.uploader['name']) {
			id = ot.uploader['name'];
		}
		if (ot.uploader['page']) {
			id = ot.uploader['page'];
		}
		if (ot.uploader['group']) {
			opt += "&group="+encodeURIComponent(ot.uploader['group']);
		}
		if (o.hasClass('ogp')) {
			opt += '&thumb=true';
		}
		if (ot.otoken) {
			opt += '&otoken='+ot.otoken;
		}
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=remove_picture&src="+a+"&id="+id+opt
			, dataType:'json'
			, obj:obj
			, success: function(data){
				if ( data && data['result'] ) {
					\$(this.obj).parent().removeClass('ogp');
					if (!data['remain']) {
						\$(this.obj).parent().fadeOut('slow'
							, function(){
								\$(this).remove();
								if (ot.uploader['show_information']) {
									ot.uploader.show_information();
								}
							}
						);
					}
				} else {
					alert("削除できませんでした");
				}
			}
		});
	};
	ot.set_ogimage = function(obj , mode) {
		var o = \$(obj);
		var p = o.parent();
		var a = p.attr('data-src');
		var u = "ajax=set_ogimage&src="+a+"&page="+$gallery_id
		if (ot.uploader.group) {
			u += '&group='+ot.uploader.group;
		}
		if (o.hasClass('thumbhnd') && p.hasClass('thumb')) {
			u += '&mode=unset_thumb';
		} else if (o.hasClass('ogphnd') && p.hasClass('ogp')) {
			u += '&mode=unset_ogp';
		} else if (mode) {
			u += '&mode='+mode;
		}
		
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: u
			, dataType:'json'
			, c_obj:obj
			, success: function(data){
				if ( data && data['result'] ) {
					if (data['close']) { ot.close_dialog();	}
					if (data['remove']) { \$(this.c_obj).parent().remove();	}
					if (data['mode']=='unset_ogp') { \$('#onethird-uploader-list .item').removeClass('ogp'); ot.og_image = '';	}
					if (data['mode']=='unset_thumb') { \$('#onethird-uploader-list .item').removeClass('thumb');ot.thumb_img = ''}
					if (data['og_image']) {
						\$('#onethird-uploader-list .item').removeClass('ogp');
						ot.og_image = data['og_image'];
					}
					if (data['thumb_img']) {
						\$('#onethird-uploader-list .item').removeClass('thumb');
						ot.thumb_img = data['thumb_img'];
					}
					if (data['create']) {
						ot.draw_image( data['url'], data['name'] );
					} else {
						if (data['og_image']) {
							\$(this.c_obj).parent().addClass('ogp');
						}
						if (data['thumb_img']) {
							\$(this.c_obj).parent().addClass('thumb');
						}
					}
				} else {
					alert("OGP/Thumbnail error");
				}
			}
		});
	};
EOT;

	if (isset($option['js'])) {
$a .= <<<EOT
		{$option['js']}
EOT;
	}

$a .= <<<EOT
	</script>
EOT;

	$html['meta']['snippet_uploader']['js'] = $a;

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'set_uploaded' && isset($_POST['data']))  {
		$r = array();
		$r['result'] = false;

		$id = (int)$_POST['id'];
		if (isset($_POST['resize_no'])) {
			set_cookie("uploader_resize", (int)$_POST['resize_no']);
		}
		if (isset($_POST['page'])) {
			$id =  (int)($_POST['page']);
		}
		if (!empty($params['page']['block_type']) && $params['page']['block_type'] == 5) {
			$id = $params['page']['link'];
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

		$group = '';
		if (isset($_POST['group'])) {
			$group = sanitize_asc(sanitize_path($_POST['group']));
			if (!is_numeric($group) && $id) {
				set_cookie('upr_cache', $group, $_SERVER['REQUEST_TIME']+60*60*24*7);
			} else {
				set_cookie('upr_cache', '', $_SERVER['REQUEST_TIME']-3600);
			}
		} else {
			set_cookie('upr_cache', '', $_SERVER['REQUEST_TIME']-3600);
		}

		if ($group) {
			$f = $config['files_path'].DIRECTORY_SEPARATOR.'img';
			$u = $config['files_url'].'img';
			if ($group) {
				if (substr($group,0,1) == '.') {
					$name = substr($group,1);
					$f = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'data';
					if (substr($params['circle']['files_url'],0,strlen($params['circle']['url'])) == $params['circle']['url']) {
						$x = substr($params['circle']['files_url'],strlen($params['circle']['url']));
						$u = 'timg.php?p='.$name.'&amp;i=';
					} else {
						$u = $params['circle']['files_url'].'data/'.$name;
					}
				} else {
					$name = $group;
					$u = 'img.php?p='.$group.'&amp;i=';
				}
				$f .= DIRECTORY_SEPARATOR.$name;

			} else {
				if (isset($_POST['name'])) {
					$name = sanitize_asc(sanitize_path($_POST['name']));
					$f .= DIRECTORY_SEPARATOR.$name;
					$u .= '/'.$name.'/';
				}
			}
			$f .= DIRECTORY_SEPARATOR;
		} else {
			$f = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR;
			$u = 'img.php?p='.$id.'&amp;i=';
		}
		if (!is_dir($f)) {
			if (!mkdir($f)) {
				$r['error2'] = $f;
				echo( json_encode($r) );
				exit();
			}
			chmod($f, $config['permission']);  
		}

		$type_ar = array('jpeg'=>1,'jpg'=>1,'gif'=>1,'png'=>1,'zip'=>1,'pdf'=>1,
		'doc'=>1,'docx'=>1,'ppt'=>1,'pptx'=>1,'xls'=>1,'xlsx'=>1,'csv'=>1,'webp'=>1);
		
		$pos = strpos($_POST['data'],';base64,');
		$r['pos'] = $pos;
		$path_parts = pathinfo(sanitize_path($_POST['fname']));
		$r['ext'] = $ext = strtolower($path_parts['extension']);
		$safe_name = $path_parts['filename'].'.'.$r['ext'];
		
		if ($pos > 1 && isset($type_ar[$ext])) {
			$mine = substr($_POST['data'],0,$pos);
			$r['type'] = false;
			$r['mine'] = $mine;
			if (strstr($mine,"word") || strstr($mine,"officedocument") || strstr($mine,"zip") || strstr($mine,"pdf")|| $mine=="data:") {
				$r['type'] = 'file';
			}
			if (strstr($mine,"image")) {
				$r['type'] = 'img';
			}
			$r['fname'] = sanitize_path($_POST['data']);
			if ($r['type']) {
				$b = base64_decode(substr($_POST['data'], $pos+7));
				$name = '';
				if ($r['type'] == 'img') {
					$r['org_name'] = $name;
					$name = md5($b);
					$name .= '.'.$ext;
					$name = sanitize_path($name);
				} else {
					$name = sanitize_path($safe_name);
				}
				if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && strlen($name) != mb_strlen($name)) {
					$name = md5($b);
					$name .= '.'.$ext;
				} else {
					$name = preg_replace("/[ ()]/","",$name);
				}
				$r['name'] = $name;
				$r['path'] = $f;
				if (is_file($f.$name)) {
					@unlink($f.$name);
				}
				if (@file_put_contents($f.$name, $b)) {
					@chmod($f.$name,$config['permission']);
					if (!check_rights('edit')) {
						add_actionlog("[login user upload] ({$_SESSION['login_id']} : {$_SESSION['login_name']} : {$_SERVER['REMOTE_ADDR']})");
					}
					$r['result'] = true;
					$r['url'] = $u.$name;
					$r['name'] = $name;
					
					if (isset($_POST['resize']) && strstr($_POST['resize'],'auto')!==false) {
						if (isset($_POST['resize_no']) && $params['uploader']['uploader_resize'][(int)$_POST['resize_no']]) {
							$w = $params['uploader']['uploader_resize'][(int)$_POST['resize_no']][0];
							$h = $params['uploader']['uploader_resize'][(int)$_POST['resize_no']][1];
							$r['resize_mode'] = 'Auto';
							$path_parts = pathinfo($f.$name);
							$new_img_name = $path_parts['dirname'].DIRECTORY_SEPARATOR.'r_'.$path_parts['basename'];
							$r['new_img_name'] = $new_img_name;
							if (save_resize($f.$name, $new_img_name, $w, $h)) {
								@unlink($f.$name);
								@chmod($new_img_name,$config['permission']);
								$name = basename($new_img_name);
								$r['result'] = true;
								$r['url'] = $u.$name;
								$r['name'] = $name;
								$r['resize_mode_ok'] = 'true';
							} else {
								$r['resize_mode_ok'] = 'false';
							}
						}
					}
				}
			}
		}

		echo( json_encode($r) );
		exit();
	}
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'remove_picture')  {
		$r = array();
		$p_src = sanitize_path($_POST['src']);
		$p_id = sanitize_str($_POST['id']);	
		$r['p_id'] = $p_id;
		$r['p_src'] = $p_src;
		if (!empty($params['page']['block_type']) && $params['page']['block_type'] == 5) {
			$p_id = $params['page']['link'];
		}
		$p_group = false;
		if (isset($_POST['group'])) {
			$p_group = sanitize_asc($_POST['group']);	
		}
		$r['p_group'] = $p_group;
		if ($p_group) {
			if (substr($p_group,0,1) == '.') {
				$file = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.substr($p_group,1).DIRECTORY_SEPARATOR.$p_src;
			} else {
				$file = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$p_group.DIRECTORY_SEPARATOR.$p_src;
			}
		} else if ($p_id != $p_page) {
			$p = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$p_id;
			$file = $p.DIRECTORY_SEPARATOR.$p_src;
		} else {
			$file = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$p_page.DIRECTORY_SEPARATOR.$p_src;
		}
		
		$r['result'] = @unlink($file);

		if ($r['result'] && isset($params['page']['meta']['og_image']) && $params['page']['meta']['og_image']==$p_src) {
			unset($params['page']['meta']['og_image']);
			$params['page']['metadata'] = serialize64($params['page']['meta']);
			mod_data_items($params['page']);
		}
		if ($r['result'] && isset($params['page']['meta']['thumb_img']) && $params['page']['meta']['thumb_img']==$p_src) {
			unset($params['page']['meta']['thumb_img']);
			$params['page']['metadata'] = serialize64($params['page']['meta']);
			mod_data_items($params['page']);
		}

		$r['file'] = $file;
		echo( json_encode($r) );
		exit();
	}
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'set_ogimage')  {
		$r = array();
		$p_src = sanitize_str($_POST['src']);
		if (!preg_match("/\.(jpeg|jpg|gif|png)$/i",$p_src)) {
			echo( json_encode($r) );
			exit();
		}
		$p_page = (int)$_POST['page'];
		if (!empty($params['page']['block_type']) && $params['page']['block_type'] == 5) {
			$p_page = $params['page']['link'];
		}
		$p_group = false;
		if (isset($_POST['group'])) {
			$p_group = sanitize_asc(sanitize_path($_POST['group']));	
		}
		$p_mode = false;
		if (isset($_POST['mode'])) {
			if ($_POST['mode'] == 'auto') { $p_mode = 'auto'; }
			if ($_POST['mode'] == 'thumb') { $p_mode = 'thumb'; }
			if ($_POST['mode'] == 'unset_thumb' || $_POST['mode'] == 'unset_ogp')  {
				$r = array();
				$mode = sanitize_asc($_POST['mode']);
				$p_src = sanitize_path($_POST['src']);
				$r['id'] = $p_page;
				if ($r['result']=read_pagedata( $r['id'], $r )) {
					if ($mode == 'unset_ogp') {
						unset($r['meta']['og_image']);
						if (isset($r['meta']['og_path'])) { unset($r['meta']['og_path']); }
					} else {
						unset($r['meta']['thumb_img']);
					}
					$r['metadata'] = serialize64($r['meta']);
					if ($r['result'] = mod_data_items($r)) {
						$file = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$p_page.DIRECTORY_SEPARATOR.$p_src;
						if ($mode == 'unset_ogp') {
							if ($p_src=='_ogp.jpg') {
								$r['remove'] = @unlink($file);
							}
						} else {
							if ($p_src=='_thumb.jpg') {
								$r['remove'] = @unlink($file);
							}
						}
						$r['p_src'] = $p_src;
						$r['mode'] = $mode;
						echo( json_encode($r) );
						exit();
					}
				}
				echo( json_encode($r) );
				exit();
			}
		}
		if ($p_group == $p_page) { $p_group = false; }
		$r['result'] = false;
		$ar = $database->sql_select_all("select metadata from ".DBX."data_items where id=? ",$p_page);
		if ( $ar ) {
			if (isset($ar[0]['metadata']) && $ar[0]['metadata']) {
				$metadata = unserialize64($ar[0]['metadata']);
			} else {
				$metadata = array();
			}
			$thumb_name = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$p_page.DIRECTORY_SEPARATOR;
			if ($p_mode != 'thumb') {
				$thumb_width = 1200;
				$thumb_height = 630;
				$dst = &$metadata['og_image'];
				$thumb_name.='_ogp.jpg';
			} else {
				$thumb_width = 200;
				$thumb_height = 200;
				if (!empty($params['thumb_width'])) {
					$thumb_width = $params['thumb_width'];
				}
				if (!empty($params['thumb_height'])) {
					$thumb_height = $params['thumb_height'];
				}
				$dst = &$metadata['thumb_img'];
				$thumb_name.='_thumb.jpg';
			}
			if (!$p_group) {
				$file_name = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$p_page.DIRECTORY_SEPARATOR.$p_src;
				if (save_resize($file_name, $thumb_name, $thumb_width, $thumb_height)) {
					$dst = basename($thumb_name);
					$r['create']=true;
					$r['name']=$dst;
					$r['url']=$config['files_url'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$p_page.DIRECTORY_SEPARATOR.$r['name'];
				} else {
					$dst = basename($file_name);
				}
			} else {
				if (substr($p_group,0,1) == '.') {
					$file = $params['circle']['files_path'].DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.substr($p_group,1).DIRECTORY_SEPARATOR.$p_src;
				} else {
					$file = $config['files_path'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$p_group.DIRECTORY_SEPARATOR.$p_src;
				}
				if (save_resize($file, $thumb_name, $thumb_width, $thumb_height, true)) {
					$dst = basename($thumb_name);
					$r['close']=true;
				} else {
					$dst = false;
				}
			}
			if ($dst !== false && $database->sql_update( "update ".DBX."data_items set metadata=?, mod_date=? where id=?", serialize64($metadata),$params['now'], $p_page) ) {
				if (isset($metadata['og_image']) && $dst == $metadata['og_image']) {
					$r['og_image'] = $metadata['og_image'];
				}
				if (isset($metadata['thumb_img']) && $dst == $metadata['thumb_img']) {
					$r['thumb_img'] = $metadata['thumb_img'];
				}
				$r['result'] = true;
			}
		}
		echo( json_encode($r) );
		exit();
	}
}

function save_resize($name, $new_img_name, $new_img_width, $new_img_height, $force=false)
{
	global $params;
	
	if (!function_exists('getimagesize') || !function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng') || !function_exists('imagecreatefromgif')) {
		return false;
	}
	if (!is_file($name)) {
		return false;
	}
	$info = getimagesize($name);

	if (!$info) {
		return false;
	}
	
	$width= $info[0];
	$height = $info[1];
	$x = $height/$width;

	if (!$force && ($width <= $new_img_width || $height <= $new_img_height)) {
		return false;
	}

	if ($info['mime'] == 'image/gif') {
		$img = imagecreatefromgif($name);
		
	} else if ($info['mime'] == 'image/png') {
		$img = imagecreatefrompng($name);

	} else if ($info['mime'] == 'image/jpeg') {
		$img = imagecreatefromjpeg($name);

	} else {
		return false;
	}

	// サムネイルを作成
	if (!is_dir(dirname($new_img_name))) {
		mkdir(dirname($new_img_name));
	}
	$new_img = imagecreatetruecolor($new_img_width, $new_img_width*$x);
	if ($info['mime'] == 'image/png') {
		imagealphablending($new_img, false);
		imagesavealpha($new_img, true);
	}
	imagecopyresampled($new_img, $img, 0, 0, 0, 0, $new_img_width, $new_img_width*$x, $width, $height);

	if ($info['mime'] == 'image/png') {
		if (imagepng($new_img, $new_img_name)) {
			return true;
		}
	}
	if (!@imagejpeg($new_img, $new_img_name)) {
		return false;
	}

	return true;
}

?>