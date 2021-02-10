<?php
/*	manager : standard theme manager based on v1.6
	name : theme manager
	author: team1/3
*/

	global $html,$params,$database,$config,$ut,$plugin_ar;

	if (!isset($config)) { die(); }
	
	if (!check_rights('admin')) {
		exit_proc(505, 'Access denied');
	}

	$params['manager'] = true;
	//$params['template'] = 'admin.tpl';
	draw();
	$params['rendering'] = false;

function draw()
{
	global $html,$params,$database,$ut;
	
	$p_page = $params['page']['id'];

	if (isset($_GET['mode']) && $_GET['mode'] == 'edit') {
		provide_edit_module();
		snippet_breadcrumb($p_page, 'Page Edit');
		$buff = page_edit_renderer($params['page']);
		$html['article'][] = frame_renderer($buff);
		return;
	}

	snippet_colorpicker($params['page']);

	$buff ='';
	$buff .= basic_renderer($p_page);
	$html['article'][] = $buff;

	$tab_ar=array();
	$tab_ar[] = array('name'=>'Global Menu',	'proc'=>'link_menu_tab', 'arg'=>'global_menu' );
	$tab_ar[] = array('name'=>'Main Visual',	'proc'=>'style_option_tab' );
	$tab_ar[] = array('name'=>'Color Set',	'proc'=>'style_option_tab3' );
	$tab_ar[] = array('name'=>'System Menu',	'proc'=>'style_option_sm' );

	if (isset($_GET['tab'])) {
		$p_tab=(int)($_GET['tab']);
	} else {
		$p_tab=0;
	}

	if (!isset($tab_ar[$p_tab])) {
		return;
	}

$buff = <<<EOT
	<div >
		<div class='onethird-tab'>
			<ul class='tab-head clearfix'>
EOT;
			$i=0;
			for($i=0;$i<count($tab_ar);++$i) {
				$v = $tab_ar[$i];
				($i==$p_tab)?$act=" class='active' ":$act='';
				$buff.=("<li $act ><a href='{$ut->link($p_page,'&:tab='.$i)}' >{$v['name']}</a></li>");
			}
$buff.= <<<EOT
			</ul>

			<div class='tab-body'>
EOT;
			if ( function_exists($tab_ar[$p_tab]['proc'])) {
				$arg = false;
				if (isset($tab_ar[$p_tab]['arg'])) {
					$arg = $tab_ar[$p_tab]['arg'];
				}
				$buff .= $tab_ar[$p_tab]['proc']($arg);
			}
$buff.= <<<EOT
			</div>
		</div> 
	</div> 
EOT;

	$html['article'][] = frame_renderer($buff);
}

function link_menu_tab($tag_name)
{
	global $html,$params,$database,$ut,$config,$p_circle;

	$top_page = $params['circle']['meta']['top_page'];
	$meta = get_theme_metadata();

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'theme_links_save')  {
		$r = array();
		$r['result'] = false;
		$r['tag'] = $tag_name;
		if (isset($_POST['data'])) { $r['data'] = sanitize_str($_POST['data']); }
		$m = array();
		$c = '';
		$dx = explode("\n",$r['data']);
		reset($dx);
		$fst = 1;
		while (($v1 = current($dx)) !== false) {
			$v2 = next($dx);
			$v3 = next($dx);
			next($dx);
			if ($v1) {
				$m[] = array('text'=>$v1, 'link'=>$v2, 'mobi'=>$v3 || $fst);
				$fst = false;
			}
		}
		$ar = array();
		$ar['id'] = $top_page;
		$ar['meta'] = $meta;
		$ar['meta']['theme'][$r['tag']] = $m;
		$r['nopage'] = '';
		foreach ($m as $v) {
			if (!is_numeric($v['link']) && ctype_alnum($v['link'])) {
				if (!isset($params['circle']['meta']['alias'][$v['link']])) {
					if ($r['nopage']) { $r['nopage'] .=','; }
					$r['nopage'] .= $v['text'];
				}
			}
		}
		$r['result'] = mod_data_items($ar);
		echo(json_encode($r));
		exit();
	}
	if (isset($_POST['ajax']) && $_POST['ajax'] == 'theme_links_create')  {
		$r = array();
		$r['result'] = false;
		$meta = get_theme_metadata();
		$ar = array();
		$m = $meta['theme'][$tag_name];
		$ok = true;
		foreach ($m as $v) {
			if (!is_numeric($v['link']) && ctype_alnum($v['link'])) {
				if (!isset($params['circle']['meta']['alias'][$v['link']])) {
					$rx = array();
					$rx['mode'] = 1;
					$rx['type'] = 1;
					$rx['title'] = $v['text'];
					$ok |= create_page( $rx );
					$params['circle']['meta']['alias'][$v['link']] = $rx['id'];
					if ($ok && !$database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($params['circle']['meta']), $p_circle)) {
						$ok = false;
					}
					if (!$ok) { break; }
				}
			}
			$r['result'] = $ok;
		}
		echo(json_encode($r));
		exit();
	}

$tmp = <<<EOT
	<script>
		\$(function(){
			\$(document).on('click','.theme_links .del',function(){
				\$(this).parent().remove();
			});
			\$(document).on('click','.theme_links .up',function(){
				var t = \$(this).parent();
				var n = t.prev();
				if (n) { n.before(t); }
			});
			\$(document).on('click','.theme_links .dn',function(){
				var t = \$(this).parent();
				var n = t.next();
				if (n) { n.after(t); }
			});
EOT;
			if (isset($meta['theme'][$tag_name])) {
				$tmp .= "var data=".json_encode($meta['theme'][$tag_name]).";";;
			} else {
				$tmp .= "var data=[];";
			}
$tmp .= <<<EOT
			for (var i=0; i < data.length; ++i) {
				ot.theme_links_add_item(data[i]);
			}
		});
		ot.theme_links_add_item = function (tag) {
			var a = "<div><input type='text' value='"+tag['text']+"' style='width:150px;' /> ";
			a += "<input type='text' value='"+tag['link']+"' style='width:200px;' />";
			a += "<label><input type='checkbox' ";
			if (tag['mobi']) {
				a += " checked ";
			}
			a += " />mobile</label>";
			a += " {$ut->icon('up'," class='up' style='width:25px' ")}";
			a += " {$ut->icon('dn'," class='dn' style='width:25px' ")}";
			a += " {$ut->icon('delete'," class='del' style='width:25px' ")}";
			a += "</div>";
			\$('.theme_links').append(a);
		};
		ot.theme_links_save = function () {
			var d = '';
			\$('.theme_links input').each(function(){
				if (this.type == 'checkbox') {
					if (this.checked) { d+='1'; }
					d += "\\n";
				} else {
					d += \$(this).val()+"\\n";
				}
			});
			ot.ajax({
				type: "POST"
				, url: '{$params['safe_request']}'
				, data: "ajax=theme_links_save&data="+encodeURIComponent(d)
				, dataType:'json'
				, success: function(data){
					if ( data && data['result'] ) {
						if (data['nopage']) {
							if (confirm('Page '+data['nopage']+' is not found, make this page?')) {
							var a = 
							ot.ajax({
								type: "POST"
								, url: '{$params['safe_request']}'
								, data: "ajax=theme_links_create&tag="+\$('.theme_links').attr('data-tag')
								, dataType:'json'
								, success: function(data){
									if ( data && data['result'] ) {
										location.reload(true);
									}
								}
							});
							}
						} else {
							location.reload(true);
						}
					}
				}
			});
		};
	</script>
EOT;

	$html['meta'][] = $tmp;

$tmp = <<<EOT
	<div class='onethird-setting'>
		<div class='theme_links' data-tag='$tag_name'  >
		</div>
		<div class='actions' >
			<input type='button' class='onethird-button' value='Update' onclick='ot.theme_links_save(1)' />
			<input type='button' class='onethird-button' value='Add' onclick="ot.theme_links_add_item({link:'',text:''})" />
		</div>
	</div>
EOT;
	return $tmp;
}

function style_option_tab()
{
	global $params,$html,$ut;
	
	$meta = get_theme_metadata();
	$img = img_proc(array('page'=>'top','group'=>'.img', 'name'=>'main_visual','style'=>'width:120%',"first_mess:Add image"));

	$c = '#000000';
	if (!empty($meta['flexcolor']['main_font_color'])) {
		$c = $meta['flexcolor']['main_font_color'];
	}

$html['meta'][] = <<<EOT
	<script>
		\$(function(){
			\$('#main_visual_preview,#main_visual_preview h1').css('color',"$c");
			emm();
			\$('.emm').click(function(){
				emm(\$(this).val());
			});
		});
		function emm(o) {
			if (o == 'tablet') {
				\$('#main_visual_img').width(491).height(258);
				\$('#main_visual_img img:first').css('width','100%');
				\$('#main_visual_txt').width(435);
				\$('#main_visual_txt').css({
					'font-size':'15px'
					,'left':'3%'
					,'top':'3%'
				});
				\$('#main_visual_txt a').css({
					'font-size':'11px'
				});
			} else {
				\$('#main_visual_img').width(330).height(230);
				\$('#main_visual_img img:first').css('width','120%');
				\$('#main_visual_txt').width(300);
				\$('#main_visual_txt').css({
					'font-size':'15px'
					,'left':'2%'
					,'top':'1%'
				});
				\$('#main_visual_txt a').css({
					'font-size':'15px'
				});
				\$('#main_visual_txt h1').css({
					'font-size':'28px'
				});
			}
		}
	</script>
EOT;

	$out = '';
	if (function_exists('get_catch_html')) {
		$out = get_catch_html(true);
	}

	if (empty($meta['flexfile']['main_visual'])) {
$buff = <<<EOT
		<p>$img</p>
EOT;
	} else {
$buff = <<<EOT
		<div style='margin:10px 0'>
			<input type='button' value='text color' onclick="ot.open_color_dialog({page:'top',name:'main_font_color', selector:'#main_visual_preview,#main_visual_preview h1', value:'{$c}'})" class='onethird-button' />
			<input type='button' value='background image' onclick="ot.inpage_img({page:{$ut->get_home_id()} ,group:'.img'  ,name:'main_visual',resize:'auto,1200/800/1,0/0'})" class='onethird-button' />
		</div>
		<p>
			<label><input type='radio' value='phone' name='emm' class='emm' checked /> phone</label>
			<label><input type='radio' value='tablet' name='emm' class='emm' /> tablet</label>
		</p>
		<div id='main_visual_preview' style='position: relative;color:$c;margin-bottom:100px'>
			<div id='main_visual_img' style='overflow: hidden;'>
				$img
			</div>
			<div id='main_visual_txt' style='position: absolute;top: 2%;left: 3%;'>
				$out
			</div>
		</div>
EOT;
	}
	expand_buff($buff);
	return $buff;
}


function style_option_tab3()
{
	global $params,$html,$ut;
	
	$meta = get_theme_metadata();
	
	if (isset($meta['flexcolor']['nav_cl'])) {
		unset($html['css']['adjust_theme_color_class']);
$html['meta'][] = <<<EOT
	<script>
		\$(function() {
			\$('#navbar').addClass('{$meta['flexcolor']['nav_cl']}');
		});
	</script>
EOT;
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'preset_save')  {
		$r = array();
		$r['result'] = false;
		$ar = array();
		$ar['id'] = $ut->get_home_id();
		
		if (!empty($_POST['nav_bk']) && $_POST['nav_bk'] != 'rgb(0, 0, 0)') {
			$r['nav_bk'] = $meta['flexcolor']['nav_bk'] = $ut->safe_str($_POST['nav_bk']);
		} else {
			unset($meta['flexcolor']['nav_bk']);
		}
		if (!empty($_POST['nav_ln']) && $_POST['nav_ln'] != 'rgb(0, 0, 0)') {
			$r['nav_ln'] = $meta['flexcolor']['nav_ln'] = $ut->safe_str($_POST['nav_ln']);
		} else {
			unset($meta['flexcolor']['nav_ln']);
		}
		if (!empty($_POST['nav_br']) && $_POST['nav_br'] != 'rgb(0, 0, 0)') {
			$r['nav_br'] = $meta['flexcolor']['nav_br'] = $ut->safe_str($_POST['nav_br']);
		} else {
			unset($meta['flexcolor']['nav_br']);
		}
		if (!empty($_POST['nav_cl']) && $_POST['nav_cl'] != 'rgb(0, 0, 0)') {
			$r['nav_cl'] = $meta['flexcolor']['nav_cl'] = $ut->safe_str($_POST['nav_cl']);
		} else {
			unset($meta['flexcolor']['nav_cl']);
		}
		
		if (!empty($_POST['body_bk']) && $_POST['body_bk'] != 'rgb(0, 0, 0)') {
			$r['body_bk'] = $meta['flexcolor']['body_bk'] = $ut->safe_str($_POST['body_bk']);
		} else {
			unset($meta['flexcolor']['body_bk']);
		}
		if (!empty($_POST['body_ln']) && $_POST['body_ln'] != 'rgb(0, 0, 0)') {
			$r['body_ln'] = $meta['flexcolor']['body_ln'] = $ut->safe_str($_POST['body_ln']);
		} else {
			unset($meta['flexcolor']['body_ln']);
		}
		if (!empty($_POST['body_fr']) && $_POST['body_fr'] != 'rgb(0, 0, 0)') {
			$r['body_fr'] = $meta['flexcolor']['body_fr'] = $ut->safe_str($_POST['body_fr']);
		} else {
			unset($meta['flexcolor']['body_fr']);
		}
		
		if (!empty($_POST['pribtn_bk']) && !empty($_POST['pribtn_ln'])) {
			$r['pribtn_bk'] = $meta['flexcolor']['pribtn_bk'] = $ut->safe_str($_POST['pribtn_bk']);
			$r['pribtn_ln'] = $meta['flexcolor']['pribtn_ln'] = $ut->safe_str($_POST['pribtn_ln']);
		} else {
			unset($meta['flexcolor']['pribtn_bk']);
			unset($meta['flexcolor']['pribtn_ln']);
		}
		if (!empty($_POST['defbtn_bk']) && !empty($_POST['defbtn_ln'])) {
			$r['defbtn_bk'] = $meta['flexcolor']['defbtn_bk'] = $ut->safe_str($_POST['defbtn_bk']);
			$r['defbtn_ln'] = $meta['flexcolor']['defbtn_ln'] = $ut->safe_str($_POST['defbtn_ln']);
		} else {
			unset($meta['flexcolor']['defbtn_bk']);
			unset($meta['flexcolor']['defbtn_ln']);
		}
		if (!empty($_POST['defbtn_br']) && $_POST['defbtn_br'] != 'rgb(0, 0, 0)') {
			$r['defbtn_br'] = $meta['flexcolor']['defbtn_br'] = $ut->safe_str($_POST['defbtn_br']);
		} else {
			unset($meta['flexcolor']['defbtn_br']);
		}
		if (!empty($_POST['bread_bk']) && $_POST['bread_bk'] != 'rgb(0, 0, 0)') {
			$r['bread_bk'] = $meta['flexcolor']['bread_bk'] = $ut->safe_str($_POST['bread_bk']);
		} else {
			unset($meta['flexcolor']['bread_bk']);
		}
		
		if (!empty($_POST['footer_bk'])) {
			$r['footer_bk'] = $meta['flexcolor']['footer_bk'] = $ut->safe_str($_POST['footer_bk']);
		} else {
			unset($meta['flexcolor']['footer_bk']);
		}

		if (!empty($_POST['footer_tx'])) {
			$r['footer_tx'] = $meta['flexcolor']['footer_tx'] = $ut->safe_str($_POST['footer_tx']);
		} else {
			unset($meta['flexcolor']['footer_tx']);
		}

		$ar['metadata'] = serialize64($meta);
		$r['result'] = mod_data_items($ar);
		echo(json_encode($r));
		exit();
	}

$html['css'][] = <<<EOT
	<style>
		.slate_navbar {
			{$ut->str(get_theme_class('slate_navbar'))}
		}
		.spacelab_navbar {
			{$ut->str(get_theme_class('spacelab_navbar'))}
		}
		.onethird-tab .tab-body {
			background: none;
			color: inherit;
		}
		#color_dialog input:first-child {
			display:none;
		}
	</style>
EOT;

$html['meta'][] = <<<EOT
	<script>
		function preset(k) {
			var ar = {
				Cosmo:{nav_bk:'#222222',nav_br:'#121212',color:'#333333',link:'#2780e3',nav_ln:'#ffffff',pribtn_ln:'#fff',pribtn_bk:'#111',bread_bk:'#f5f5f5'}
				,Cyborg:{nav_bk:'#060606',nav_br:'#060606',color:'#888888',bk:'#060606',nav_ln:'#ffffff',pribtn_ln:'#eee',pribtn_bk:'#555',defbtn_ln:'#222',defbtn_bk:'#eee',bread_bk:'#222222'}
				,Darkly:{nav_bk:'#375a7f',nav_br:'#375a7f',nav_ln:'#ffffff',link:'#0ce3ac',color:'#ffffff',bk:'#222222',pribtn_ln:'#fff',pribtn_bk:'#464545',bread_bk:'#464545',footer_bk:'#1b1b1b'}
				,Flatly:{nav_bk:'#2c3e50',nav_br:'#2c3e50',nav_ln:'#ffffff',link:'#18bc9c',color:'#2c3e50',pribtn_ln:'#fff',pribtn_bk:'#2c3e50',defbtn_ln:'#222',defbtn_bk:'#95a5a6',defbtn_br:'rgb(140, 140, 140)',bread_bk:'#ecf0f1'}
				,Sandstone:{nav_bk:'#3e3f3a',nav_br:'#3e3f3a',nav_ln:'#98978b', link:'#93c54b',color:'#3e3f3a',pribtn_ln:'#fff',pribtn_bk:'#325d88',defbtn_ln:'#fff',defbtn_bk:'#393a35',bread_bk:'#fff'}
				,Simplex:{nav_bk:'#ffffff',nav_br:'#eeeeee',nav_ln:'#777777',link:'#d9230f',color:'#777777',bk:'#fcfcfc',pribtn_ln:'#fff',pribtn_bk:'#a91b0c',defbtn_ln:'#fff',defbtn_bk:'#2e2f2f',bread_bk:'#fff'}
				,Slate:{nav_class:'slate_navbar',nav_br:'rgb(132, 132, 132)',nav_ln:'#ffffff',link:'#18bc9c',color:'#c8c8c8',bk:'#272b30',pribtn_ln:'#fff',pribtn_bk:'#7C8489',defbtn_ln:'#fff',defbtn_bk:'#3C4147',defbtn_br:'rgb(97, 95, 95)',bread_bk:'rgb(60, 65, 70)',footer_bk:'#1b1b1b',footer_tx:'#fff'}
				,Spacelab:{nav_class:'spacelab_navbar',nav_br:'#d5d5d5',nav_ln:'#777777',color:'#666666',link:'#3399f3',pribtn_ln:'#fff',pribtn_bk:'#436D9A',defbtn_ln:'#fff',defbtn_bk:'#454747',bread_bk:'#f5f5f5',footer_bk:'#1b1b1b',footer_tx:'#fff'}
				,Superhero:{nav_bk:'#4e5d6c',nav_br:'#4e5d6c',color:'#ebebeb',link:'#df691a',bk:'#2b3e50',pribtn_ln:'#fff',pribtn_bk:'#df691a',defbtn_ln:'#eee',defbtn_bk:'#4E5D6C',defbtn_br:'rgb(42, 55, 68)',bread_bk:'#4e5d6c',footer_bk:'#1b1b1b',footer_tx:'#fff'}
				,United:{nav_bk:'#dd4814',nav_br:'#bf3e11',color:'#333333',link:'#dd4814',nav_ln:'#ffffff',pribtn_ln:'#fff',pribtn_bk:'#DD4814',defbtn_ln:'#fff',defbtn_bk:'#AEA79F',defbtn_br:'rgb(200, 200, 200)',bread_bk:'#f5f5f5'}
				,Yeti:{nav_bk:'#222222',nav_br:'#121212',color:'#222222',link:'#008cba',nav_ln:'#ffffff',pribtn_ln:'#E7E7E7',pribtn_bk:'#008cba',defbtn_ln:'#333333',defbtn_bk:'#E7E7E7',defbtn_br:'rgb(162, 161, 161)',bread_bk:'#f5f5f5'}
			};
			if (ar[k]) {
				\$('#navbar').css('background','');
				\$('#navbar').css('text-shadow','none');
				\$('#navbar').css('border-color','');
				\$('#navbar').css('border','');
				\$('#navbar a').css('color','');
				\$('#navbar').removeClass('slate_navbar').removeClass('spacelab_navbar');
				\$('body,#preview').css('background-color','#fff');
				\$('body,#preview').css('color','');
				\$('#preview a').css('color','');

				\$('.button').css('background-color','');
				\$('.button').css('color','');
				\$('.button-primary').css('background-color','');
				\$('.button-primary').css('color','');
				\$('.breadcrumb,.link-panel').css('border','none');
				
				\$('.footer').css('background','');
				\$('.footer').css('color','');
				ot.preset = ar[k];

				if (ot.preset.bk) { \$('body,#preview').css('background-color',ot.preset.bk); }
				if (ot.preset.color) { \$('body,#preview').css('color',ot.preset.color); }
				if (ot.preset.link) { \$('body a').css('color',ot.preset.link); }
				if (ot.preset.nav_ln) { \$('#navbar a').css('color',ot.preset.nav_ln); }
				if (ot.preset.nav_bk) { \$('#navbar').css('background-color',ot.preset.nav_bk); }
				if (ot.preset.nav_br) { \$('#navbar').css('border-color',ot.preset.nav_br); }
				if (ot.preset.nav_class) { \$('#navbar').addClass(ot.preset.nav_class); }
				if (ot.preset.bread_bk) { \$('.breadcrumb,.link-panel').css('background-color',ot.preset.bread_bk); }
				if (ot.preset.footer_bk) { \$('.footer').css('background-color',ot.preset.footer_bk); }
				if (ot.preset.footer_tx) { \$('.footer').css('color',ot.preset.footer_tx); }

				if (ot.preset.pribtn_bk && ot.preset.pribtn_ln) {
					\$('.button.button-primary').css('background-color',ot.preset.pribtn_bk);
					\$('.button.button-primary').css('color',ot.preset.pribtn_ln); 
					\$('.button.button-primary').css('border-color',ot.preset.pribtn_bk); 
					\$('.pagination a').css('color',ot.preset.pribtn_bk); 
					\$('.pagination a').css('background-color',ot.preset.pribtn_ln); 
					\$('.pagination').css('border-color',ot.preset.pribtn_bk); 
					\$('.pagination .active a').css('color',ot.preset.pribtn_ln); 
					\$('.pagination .active a').css('background-color',ot.preset.pribtn_bk); 
					\$('.pagination .active a').css('border-color',ot.preset.pribtn_bk); 
					if (ot.preset.defbtn_bk && ot.preset.defbtn_ln) {
						\$('.button.default').css('background-color',ot.preset.defbtn_bk);
						\$('.button.default').css('color',ot.preset.defbtn_ln);
						\$('.button.default').css('border-color',ot.preset.defbtn_ln); 
						\$('.pagination a').css('background-color',ot.preset.defbtn_bk); 
						\$('.pagination a').css('color',ot.preset.defbtn_ln); 
						\$('.pagination .active a').css('background-color',ot.preset.pribtn_bk); 
						\$('.pagination .active a').css('color',ot.preset.pribtn_ln); 
						\$('.pagination a').css('border-color',ot.preset.defbtn_ln); 
						\$('.pagination .active a').css('border-color',ot.preset.defbtn_ln); 
						if (ot.preset.defbtn_br) {
							\$('.button.default').css('border-color',ot.preset.defbtn_br); 
							\$('.pagination a').css('border-color',ot.preset.defbtn_br); 
							\$('.pagination .active a').css('border-color',ot.preset.defbtn_br); 
							\$('.breadcrumb,.link-panel').css({'border-color':ot.preset.defbtn_br,'border-style':'solid','border-width':'1px'});
						}
					} else {
						\$('.button.default').css('background-color',ot.preset.pribtn_ln);
						\$('.button.default').css('color',ot.preset.pribtn_bk);
						\$('.button.default').css('border-color',ot.preset.pribtn_bk); 
					}
				}

				\$('.onethird-tab .tab-head a').css('color','#fff');
				\$('.onethird-tab .tab-head .active a').css('color','#000');

			}
		}
		function preset_save(a) {
			var opt = '';
			if (a) {
				opt += "&nav_bk="+encodeURIComponent(\$('#navbar').css('background-color'));
				opt += "&nav_ln="+encodeURIComponent(\$('#navbar a').css('color'));
				opt += "&nav_br="+encodeURIComponent(\$('#navbar').css('border-top-color'));
				if (\$('#navbar').hasClass('slate_navbar')) {
					opt += "&nav_cl=slate_navbar";
				}
				if (\$('#navbar').hasClass('spacelab_navbar')) {
					opt += "&nav_cl=spacelab_navbar";
				}

				opt += "&body_bk="+encodeURIComponent(\$('#preview').css('background-color'));
				opt += "&body_ln="+encodeURIComponent(\$('#link_color').css('color'));
				opt += "&body_fr="+encodeURIComponent(\$('#preview').css('color'));

				opt += "&pribtn_bk="+encodeURIComponent(\$('.button.button-primary').css('background-color'));
				opt += "&pribtn_ln="+encodeURIComponent(\$('.button.button-primary').css('color'));
				opt += "&defbtn_bk="+encodeURIComponent(\$('.button.default').css('background-color'));
				opt += "&defbtn_ln="+encodeURIComponent(\$('.button.default').css('color'));
				if (ot.preset && ot.preset.defbtn_br) {
					opt += "&defbtn_br="+encodeURIComponent(\$('.button.default').css('border-top-color'));
				}
				opt += "&bread_bk="+encodeURIComponent(\$('.breadcrumb').css('background-color'));
				opt += "&footer_bk="+encodeURIComponent(\$('.footer').css('background-color'));
				opt += "&footer_tx="+encodeURIComponent(\$('.footer').css('color'));
				
			}
			ot.ajax({
				type: "POST"
				, url: '{$params['safe_request']}'
				, data: "ajax=preset_save"+opt
				, dataType:'json'
				, success: function(data){
					if ( data && data['result'] ) {
						location.reload(true);
					}
				}
			});
		};
		function adj_color(a,t) {
			ot.color_dialog_close();
			if (t=='background') {
				ot.open_color_dialog({page:'top',name:'dmy-color', selector:a ,type:'background', value:\$(a).css('background-color')});
			} else {
				ot.open_color_dialog({page:'top',name:'dmy-color', selector:a, value:\$(a).css('color')});
			}
		}
		function toggle_dock() {
			var o = \$('#dock');
			if (o.css('position') != 'fixed') {
				\$('#dock').css({"position":"fixed","top": 0,"right": 0,"width":"317px","z-index":99999});
			} else {
				\$('#dock').css({"position":"inherit","top": 0,"right": 0,"width":"inherit"});
			}
		}
	</script>
EOT;

$buff = <<<EOT
	<div id='preview' style='padding:30px'>
		<h1>Heading 1</h1>
		<h2>Heading 2</h2>
		
		<ul class="breadcrumb ">
			<li ><a href="">breadcrumb</a> </li>
		</ul>

		<p> contents contents <a href='' id='link_color' class='link_color'>link jump</a> contents contents contents contents contents 
		</p>
		<p>
			<a href="#" class="button button-primary">button</a> <a href="#" class="button default">button</a>
		</p>

		<p>
			<ul class="pagination">
				<li class="disabled"><a href="#">&laquo;</a></li>
				<li class="active"><a href="#">1</a></li>
				<li><a href="#">2</a></li>
				<li><a href="#">3</a></li>
				<li><a href="#">4</a></li>
				<li><a href="#">5</a></li>
				<li><a href="#">&raquo;</a></li>
			</ul>		
		</p>
		
	</div>
	<div id='dock' style='background-color: #D4D4D4; padding: 10px;'>
		<p> preset from <a href='http://bootswatch.com/' class='link_color'>http://bootswatch.com/</a></p>
		<p>
			<a href='javascript:void(preset("Cosmo"))' class='onethird-button mini'>Cosmo</a> 
			<a href='javascript:void(preset("Cyborg"))' class='onethird-button mini'>Cyborg</a> 
			<a href='javascript:void(preset("Darkly"))' class='onethird-button mini'>Darkly</a> 
			<a href='javascript:void(preset("Flatly"))' class='onethird-button mini'>Flatly</a> 
			<a href='javascript:void(preset("Sandstone"))' class='onethird-button mini'>Sandstone</a> 
			<a href='javascript:void(preset("Simplex"))' class='onethird-button mini'>Simplex</a> 
			<a href='javascript:void(preset("Slate"))' class='onethird-button mini'>Slate</a> 
			<a href='javascript:void(preset("Spacelab"))' class='onethird-button mini'>Spacelab</a> 
			<a href='javascript:void(preset("Superhero"))' class='onethird-button mini'>Superhero</a> 
			<a href='javascript:void(preset("United"))' class='onethird-button mini'>United</a> 
			<a href='javascript:void(preset("Yeti"))' class='onethird-button mini'>Yeti</a> 
		</p> 
		<p>
EOT;
		$ar = array(
			array('body text','body,#preview','char')
			, array('body a','.link_color, .footer a, .breadcrumb a','char')
			, array('background','body,#preview','background')
			, array('navbar','#navbar','background')
			, array('navbar a','#navbar a','char')
			, array('button','.button.default, .pagination li:not(.active) a','background')
			, array('button a','.button.default, .pagination li:not(.active) a','char')
			, array('primary-button','.button.button-primary, .pagination .active a','background')
			, array('primary-button a','.button.button-primary, .pagination .active a','char')
			, array('footer','.footer','background')
			, array('footer text','.footer','char')
			, array('breadcrumb','.breadcrumb','background')
		);
		foreach ($ar as $v) {
$buff .= <<<EOT
			<input type='button' value='{$v[0]}' onclick="adj_color('{$v[1]}','{$v[2]}')" class='onethird-button mini' />
EOT;
		}
$buff .= <<<EOT
		</p>
		<a href='javascript:void(preset_save(1))' class='onethird-button large'>Save</a>
		<a href='javascript:void(preset_save(0))' class='onethird-button mini'>clear</a> 
		<a href='javascript:void(toggle_dock())' class='onethird-button mini'>toggle dock</a> 
	</div>
EOT;
	return $buff;
}

function style_option_sm()
{
	global $p_circle,$params,$html,$database;
	
	if ( isset($_POST['ajax']) && $_POST['ajax'] == 'del_menus' ) {
		$r = array();
		$r['result'] = false;
		$t = sanitize_str($_POST['data']);
		$m = get_circle_meta('system_menus');
		unset($m[$t]);
		$r['result'] = set_circle_meta('system_menus',$m);
		echo( json_encode($r) );
		exit();
	}
	if ( isset($_POST['ajax']) && $_POST['ajax'] == 'add_menus' ) {
		$r = array();
		$m = get_circle_meta('system_menus');
		$k = "t".sprintf('%05d',mt_rand(0,1000));
		if (isset($m['$k'])) {
			$r['result'] = false;
			echo( json_encode($r) );
			exit();
		}
		$r['title'] = 'new menu '.$k;
		$r['rights'] = '';
		$m[$k] = array('title'=>$r['title'], 'url'=>'', 'rights'=>$r['rights']);
		$r['result'] = set_circle_meta('system_menus',$m);
		$r['id'] = $k;
		echo( json_encode($r) );
		exit();
	}
	
	if ( isset($_POST['ajax']) && $_POST['ajax'] == 'mod_menus' ) {
		$r = array();
		$m = get_circle_meta('system_menus');
		$k = sanitize_str($_POST['id']);
		$r['title'] = sanitize_str($_POST['title']);
		$r['url'] = sanitize_str($_POST['url']);
		$r['rights'] = sanitize_str($_POST['rights']);
		$r['opt'] = sanitize_str($_POST['opt']);
		$r['id'] = $k;
		$m[$k] = array('title'=>$r['title'], 'url'=>$r['url'], 'rights'=>$r['rights'], 'opt'=>$r['opt']);
		$r['result'] = set_circle_meta('system_menus',$m);
		$r['m'] = $m;
		echo( json_encode($r) );
		exit();
	}

$html['meta'][] = <<<EOT
	<script>
		sel_menu_item();
		function del_menus() {
			var a = \$('#p_menus option:selected');
			if (a.length) {
				ot.ajax({
					type: "POST"
					, url: '{$params['safe_request']}'
					, data: "ajax=del_menus&data="+a.val()
					, dataType:'json'
					, success: function(data){
						if ( data && data['result'] ) {
							\$('#p_menus option:selected').remove();
							sel_menu_item();
						}
					}
				});
			}
		}

		function add_menus() {
			ot.ajax({
				type: "POST"
				, url: '{$params['safe_request']}'
				, data: "ajax=add_menus"
				, dataType:'json'
				, success: function(data){
					if ( data && data['result'] ) {
						\$('#p_menus')
						.append("<option value='"+data['id']+"' data-url='' data-rights='"+data['rights']+"'>"+data['title']+"</option>")
						.val(data['id']);
						sel_menu_item();
					}
				}
			});
		}

		function mod_menus() {
			var t = \$('#menus_title').val();
			var r = \$('#menus_rights').val();
			var u = \$('#menus_url').val();
			var id = \$('#menus_id').val();
			var opt = \$('#menus_opt').val();
			ot.ajax({
				type: "POST"
				, url: '{$params['safe_request']}'
				, data: "ajax=mod_menus&title="+encodeURIComponent(t)+"&url="+encodeURIComponent(u)+"&rights="+encodeURIComponent(r)+"&id="+encodeURIComponent(id)+"&opt="+encodeURIComponent(opt)
				, dataType:'json'
				, success: function(data){
					if ( data && data['result'] ) {
						var o = \$('#p_menus option:selected');
						o.val(data['id']);
						o.text(data['title']);
						o.attr('data-url',data['url']);
						o.attr('data-rights',data['rights']);
						o.attr('data-opt',data['opt']);
					}
				}
			});
		}

		function sel_menu_item() {
			var o = \$('#p_menus option:selected');
			var i = o.val();
			var t = o.text();
			var r = o.attr('data-rights');
			var u = o.attr('data-url');
			var op = o.attr('data-opt');
			if (o.length) {
				\$('#menus_id').val(i);
				\$('#menus_title').val(t);
				\$('#menus_rights').val(r);
				\$('#menus_opt').val(op);
				\$('#menus_url').val(decodeURIComponent(u));
				\$('#submit_update,#submit_del').prop('disabled',false);
			} else {
				\$('#submit_update,#submit_del').prop('disabled',true);
			}
		}

	</script>
EOT;
	
	$m = get_circle_meta('system_menus');
	$buff = '';

$buff.= <<<EOT
	<div class='onethird-setting'>
		<table>
			<tr>
				<td>System menu</td>
				<td>
					<select id='p_menus' name='p_menus' size=4 onclick='sel_menu_item()' onselect='sel_menu_item()' >
EOT;
					if (!empty($m)) {
						foreach ($m as $k=>$v) {
							if (!is_array($v)) { continue; }
							$t = $v['title'];
							$u = urlencode($v['url']);
							$r = $v['rights'];
							$o = (!empty($v['opt']))? $v['opt']:'';
$buff.= <<<EOT
							<option value='{$k}' data-url='{$u}' data-rights='{$r}' data-opt='{$o}'>$t</option>
EOT;
						}
					}
$buff.= <<<EOT
					</select> 
					<p >
						id <input type='text' id='menus_id' style='width:100px' readonly />
					</p >
					<p >
						title <input type='text' id='menus_title' style='width:200px' />
					</p >
					<p >
						rights 
						<select id='menus_rights' style='width:100px' />
							<option value=''>all user</option>
							<option value='edit'>edit</option>
							<option value='admin'>admin</option>
						</select>
					</p >
					<p >
						opt <input type='text' id='menus_opt' style='width:200px'  />
					</p>
					<p >
						url, page mumber or page alias
						<input type='text' id='menus_url' />
					</p>
					<p>
						<input type='button' id='submit_update' value='Update' onclick='mod_menus()' class='onethird-button mini' />
						<input type='button' id='submit_del' value='Remove' onclick='del_menus()' class='onethird-button mini' />
						<input type='button' id='submit_add' value='Add' onclick='add_menus()' class='onethird-button mini' />
					</p>
				</td>
			</tr>
		</table>
	</div>
EOT;
	return $buff;

}
?>