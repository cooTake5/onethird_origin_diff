<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.

if (!function_exists('check_func_rights') || !check_func_rights('edit.modules')) {
	die('error-edit.std');
}

function page_edit_renderer(&$page_ar, $option = null)
{
	global $p_circle, $config, $params, $database, $html, $ut;

	$p_page = $page_ar['id'];
	
	snippet_avoid_robots();
	snippet_heartbeat();
	page_edit_post(isset($params['top_page']));

	snippet_delayedload();
	snippet_alertmoving(array('force'=>true));
	
	/*
	$opt = array();
	if (isset($page_ar['block_type']) && $page_ar['block_type'] == 5) {
		$opt['id'] = $page_ar['link'];
	} else {
		$opt['id'] = $page_ar['id'];
	}*/
	snippet_image_uploader();

	$upper_contents = '';
	if (isset($option['upper_contents'])) {
		$upper_contents = $option['upper_contents'];
	}

$buff = <<<EOT
<form method="post" id='form_page' action='{$params['safe_request']}' > 
	<input type='hidden' name='mdx' id='mdx' value='mod_page' />
	<input type='hidden' name='p_page' id='p_page' value='$p_page' />
EOT;

	if (isset($option['contents'])) {
		$contents = $option['contents'];
		
	} else if (isset($page_ar['meta']['draft'])) {
		$contents = $page_ar['meta']['draft'];
		
	} else {
		$contents = $page_ar['contents'];
	}
	$contents = str_replace('\\\$', '\\$' , $contents);
	$contents = str_replace('\$', '$' , $contents);

$tmp = "<br style='clear:both' />";
	if (isset($params['top_page'])) {
$tmp .= <<<EOT
		<input type='button' onclick='ot.move_page("{$ut->link($page_ar['link'])}")' class='onethird-button mini' value='戻る' />
EOT;
	} else {
		if ($page_ar['block_type'] == 5) {
$tmp .= <<<EOT
			<input type='button' onclick='ot.move_page("{$ut->link($page_ar['link'])}")' class='onethird-button mini' value='戻る' />
EOT;
		} else {
$tmp .= <<<EOT
			<input type='button' onclick='ot.move_page("{$ut->link($p_page)}")' class='onethird-button mini' value='戻る' />
EOT;
		}
	}

	if (!isset($params['hide-editmenu'])) {
$tmp .= <<<EOT
		<input type='button' onclick='ot.save_editdata(1)' class='onethird-button mini'  value='保存' />
		<input type='button' onclick='ot.save_editdata(0)' class='onethird-button mini'  value='下書' />
		<input type='button' onclick='ot.open_uploader({fav:true, resize:"auto", select:function(obj){ot.editor.insertimg(obj)}})' class='onethird-button mini' value='画像' />
EOT;
		unset($params['hide-editmenu']);
	}

	if (isset($params['add-editmenu'])) {
		$tmp .= implode($params['add-editmenu']);
		unset($params['add-editmenu']);
	}

	$tmp = str_replace('"','\\"',$tmp);
	$tmp = str_replace("\n","",$tmp);
	$tmp = str_replace("\r","",$tmp);

	if (!isset($params['top_page'])) {
$buff .= <<<EOT
		<div class='onethird-setting' style='margin:0;border: 1px solid #C0C0C0; padding: 5px;background-color: #FFF;color: #222;height:1.5em;box-sizing: initial;' contenteditable="true" id='edit_title'>
			{$page_ar['title']}
		</div>
EOT;
	}

	$t = event_plugin_page('onedit', $page_ar);
	if ($t) {
		$buff .= $t;
	}
	$buff .= $upper_contents;
	
	$buff .= "<div id='edit_contents' name='edit_contents' class='edit_contents' style='width:100%;overflow-y:scroll;height:400px;'>$contents</div>";
	
$buff .= <<<EOT
</form>
EOT;
	$insert_image_opt = " alt='' class='onethird-article-image' ";
	if (!empty($params['insert_image_opt'])) {
		$insert_image_opt = $params['insert_image_opt'];
	}
$html['meta']['page_edit_renderer'] = <<<EOT
	<script>
		ot.editor = ot.editor || {};
		\$(function(){
			var load = [];
			load.push({type:'script', src:'{$config['site_url']}js/tryitEditor.js' });
			delayedload(load
				,function() {
					ot.editor.create();
				}
			);
		});
		ot.editor.insertimg = function(obj) {
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
		ot.editor.create = function() {
			ot.editor.after_toolbar = "$tmp";
			ot.editor.keydown = function(e) {
				if (e.ctrlKey && e.keyCode==83) {
					ot.save_editdata(0);
					return false;
				}
			};
			if (ot.editor_toolbar) {
				ot.editor.after_toolbar += ot.editor_toolbar;
			}
			ot.editor.basepath = "{$ut->str(addslashes($config['site_url']))}";
			ot.tryitEditor.create('#edit_contents',ot.editor);
		};
		ot.save_editdata = function (end) {
			var html = ot.tryitEditor.html();
			if (!end) {
				ot.ajax({
					type: "POST"
					, url: '{$params['safe_request']}'
					, data: "ajax=editdata_save&p_page="+$p_page+"&draft=true&editdata="+encodeURIComponent(html)
					, dataType:'json'
					, end:end
					, success: function(data){
						if (data && data['result']) {
							if (this.end) {
								location.href="{$ut->link($page_ar['id'])}";
								return;
							} else {
								if (ot.waring) {ot.waring('Saved');} else {alert('保存しました');}
							}
						} else {
							alert('保存できませんでした');
						}
					}
				});
			} else {
				ot.reset_moving();
				\$('#edit_contents').remove();
				\$('#form_page').append("<input type='hidden' id='edit_contents' name='edit_contents' style='display:none'>");
				\$('#form_page').append("<input type='hidden' name='xtoken' value='"+ot.magic_str+"' />");
				\$('#form_page').append("<input type='hidden' name='edit_title' id='edit_title' />");
				\$('#form_page #edit_title').val(\$('#edit_title').text());
				\$('#edit_contents').val(html);
				\$('#form_page').submit();
			}
		};
	</script>
EOT;

	return $buff;
}

?>