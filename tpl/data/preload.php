<?php
// theme module type-B based on v1.6
// 
// by team1/3

	global $params,$html,$ut,$database;
	$params['pagination_renderer'] = "my_pagination_renderer";
	//$params['page']['meta']['hide-title'] = true;
	
	adjust_theme_color();

// ツールバーのカスタマイズ
function custom_toolbar()
{
	global $html,$ut,$params,$config;

	if (check_rights('edit')) {
		if (!check_rights('admin')) {
			unset($html['system_toolbar']['10']);
			unset($html['system_toolbar']['11']);
		} else {
			$html['system_toolbar']['MENU'][1][] = "<a href='{$ut->link('manager')}' >Theme Manager</a>";
			if (!empty($params['page']['id'])) {
				snippet_inpage_file($params['page']);
				$html['system_toolbar']['MENU'][2][] = "<a href='javascript:void(ot.inpage_img({page:{$params['page']['id']},name:\"main_v\",resize:\"auto,1200/800/1,0/0\"}))' >Main Visual</a>";
			}
		}
	}
}

function get_catch_html($readonly=false)
{
	global $params,$ut;

	$meta = &get_theme_metadata();
	if (!isset($meta['flexedit']['footer'][0]['text'])) {
$meta['flexedit']['footer'][0]['text'] = <<<EOT
		<h4>skeleton Top Nav </h4>
		<ul>
			<li>Color set customizer from bootswatch.com. </li>
			<li>Main visual customizer. </li>
			<li>Include Blog template. </li>
			<li>System Menu Manager. </li>
		</ul>
EOT;
	}

$def = <<<EOT
	<h1>{$params['circle']['name']}</h1>
	<p>Lightweight CMS for Small website, Web application framework.</p>
EOT;
	$x = false;
	if (!empty($params['page']['meta']['flexedit']['catch_text'])) {
		$x = &$params['page']['meta']['flexedit']['catch_text'][0]['text'];
	} 
	if (!$x && !empty($meta['flexedit']['catch_text'])) {
		$x = &$meta['flexedit']['catch_text'][0]['text'];
	}
	if (!$x) {
		if (!empty($params['page']['meta']['plugin_embed']['catch_text'])) {	//旧版互換のため
			$x = &$params['page']['meta']['plugin_embed']['catch_text'];
		} else {
			$x = $def;
		}
	}
	if (!$readonly && check_rights('owner')) {
		$params['flexedit'][$params['page']['id']]['id']=$params['page']['id'];
		$params['flexedit'][$params['page']['id']]['data']['catch_text'][0]['text'] = $x;
		return edit_proc('name:catch_text','page:self','mode:inline');
	}
	expand_buff($x);
	return $x;
}

function jumbotron()
{
	global $html,$params,$database,$ut,$config;
	$meta = get_theme_metadata();
	$img = '';
	if (isset($params['page']['meta']['flexfile']['main_v'])) {
		$img = str_replace("&amp;","&",$params['page']['meta']['flexfile']['main_v']);
	} else if (isset($params['page']['meta']['flexfile']['main_visual'])) {
		$img = str_replace("&amp;","&",$params['page']['meta']['flexfile']['main_visual']);
	} else if (isset($meta['flexfile']['main_visual'] )) {
		$img = str_replace("&amp;","&",$meta['flexfile']['main_visual']);
	}
	$c = '#000000';
	if (!empty($meta['flexcolor']['main_font_color'])) {
		$c = $meta['flexcolor']['main_font_color'];
	}
	if ($img) {
$img = <<<EOT
		background-image:url({$img});
		background-size:100%;
EOT;
	}
$html['css']['jumbotron'] = <<<EOT
		<style>
			.jumbotron {
				$img
				text-shadow: 2px 2px 6px rgba(111, 111, 111, 0.5);
				color:$c;
			}
			@media screen and (max-width: 600px) {
				.jumbotron {
					background-size:120%;
					max-height:220px;
					overflow: hidden;
					background-position: center center; 
				}
			}
		</style>
EOT;
	return get_catch_html();
}

function adjust_theme_color()
{

	global $html,$params,$database,$ut,$config;
	$meta = get_theme_metadata();

	if (!empty($meta['flexcolor']['nav_cl'])) {
		$t = get_theme_class($meta['flexcolor']['nav_cl']);
		if ($t) {
$html['css']['adjust_theme_color_class'] = <<<EOT
			<style>
			#navbar {
				$t
			}
			</style>
EOT;
		}
	}

$tmp = <<<EOT
	<style>
EOT;
	if (!empty($meta['flexcolor']['nav_bk'])) {
$tmp .= <<<EOT
		#navbar {
			background-color:{$meta['flexcolor']['nav_bk']};
EOT;
			if (!empty($meta['flexcolor']['nav_br'])) {
				$tmp .= "border-color:{$meta['flexcolor']['nav_br']};";
			}
$tmp .= <<<EOT
		}
		#navbar .home a, #navbar .links ul li a {
			color:{$meta['flexcolor']['nav_ln']};
		}
EOT;
	}
	if (!empty($meta['flexcolor']['footer_bk']) && !empty($meta['flexcolor']['footer_tx']) ) {
$tmp .= <<<EOT
		.footer {
			background-color:{$meta['flexcolor']['footer_bk']};
			color:{$meta['flexcolor']['footer_tx']};
		}
EOT;
	}
	if (!empty($meta['flexcolor']['body_bk']) && !empty($meta['flexcolor']['body_ln']) && !empty($meta['flexcolor']['body_fr'])) {
$tmp .= <<<EOT
		body {
			background-color:{$meta['flexcolor']['body_bk']};
			color:{$meta['flexcolor']['body_fr']};
		}
		body a {
			color:{$meta['flexcolor']['body_ln']};
		}
EOT;
	}
	if (!empty($meta['flexcolor']['maincx_bk']) && !empty($meta['flexcolor']['maincx_ln'])) {
$tmp .= <<<EOT
		.container .button {
			background-color:{$meta['flexcolor']['maincx_bk']};
			color:{$meta['flexcolor']['maincx_ln']};
		}
EOT;
	}
	if (!empty($meta['flexcolor']['defbtn_bk']) && !empty($meta['flexcolor']['defbtn_ln'])) {
$tmp .= <<<EOT
		.container .button {
			background-color:{$meta['flexcolor']['defbtn_bk']};
			color:{$meta['flexcolor']['defbtn_ln']};
			border-color:{$meta['flexcolor']['defbtn_ln']};
		}
		.container .pagination > li a, .container .pagination > li span {
			background-color:{$meta['flexcolor']['defbtn_bk']};
			color:{$meta['flexcolor']['defbtn_ln']};
			border-color:{$meta['flexcolor']['defbtn_ln']};
		}
EOT;
	}

	if (!empty($meta['flexcolor']['pribtn_bk']) && !empty($meta['flexcolor']['pribtn_ln'])) {
$tmp .= <<<EOT
		.container .button-primary {
			background-color:{$meta['flexcolor']['pribtn_bk']};
			color:{$meta['flexcolor']['pribtn_ln']};
			border-color:{$meta['flexcolor']['pribtn_bk']};
		}
		.container .pagination > .active a {
			color:{$meta['flexcolor']['pribtn_ln']};
			background-color:{$meta['flexcolor']['pribtn_bk']};
		}
EOT;
	}
	if (!empty($meta['flexcolor']['defbtn_br'])) {
$tmp .= <<<EOT
		.container .button {
			border-color:{$meta['flexcolor']['defbtn_br']};
		}
		.container .pagination > li a, .container .pagination > li span {
			border-color:{$meta['flexcolor']['defbtn_br']};
		}
		.container .pagination > .active a {
			border-color:{$meta['flexcolor']['defbtn_br']};
		}
EOT;
		if (!empty($meta['flexcolor']['pribtn_bk']) && !empty($meta['flexcolor']['pribtn_bk'])) {
$tmp .= <<<EOT
			.breadcrumb,.link-panel {
				border:1px solid {$meta['flexcolor']['defbtn_br']};
			}
EOT;
		}
	}
	if (!empty($meta['flexcolor']['bread_bk'])) {
$tmp .= <<<EOT
		.container .breadcrumb,.container .link-panel {
			background-color:{$meta['flexcolor']['bread_bk']};
		}
EOT;
	}

	
$tmp .= <<<EOT
	</style>
EOT;
	$html['css']['adjust_theme_color'] = $tmp;
}

function get_theme_class($t)
{
	global $html,$params,$database,$ut,$config;

	if ($t == 'slate_navbar') {
$tmp = <<<EOT
		background-image: -webkit-linear-gradient(#484e55, #3a3f44 60%, #313539);
		background-image: -o-linear-gradient(#484e55, #3a3f44 60%, #313539);
		background-image: -webkit-gradient(linear, left top, left bottom, from(#484e55), color-stop(60%, #3a3f44), to(#313539));
		background-image: linear-gradient(#484e55, #3a3f44 60%, #313539);
		background-repeat: no-repeat;
		filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff484e55', endColorstr='#ff313539', GradientType=0);
		-webkit-filter: none;
		filter: none;
		border: 1px solid rgba(0, 0, 0, 0.6);
		text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.3);
EOT;
		return $tmp;
	}

	if ($t == 'spacelab_navbar') {
$tmp = <<<EOT
		background-image: -webkit-linear-gradient(#ffffff, #eeeeee 50%, #e4e4e4);
		background-image: -o-linear-gradient(#ffffff, #eeeeee 50%, #e4e4e4);
		background-image: -webkit-gradient(linear, left top, left bottom, from(#ffffff), color-stop(50%, #eeeeee), to(#e4e4e4));
		background-image: linear-gradient(#ffffff, #eeeeee 50%, #e4e4e4);
		background-repeat: no-repeat;
		filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#ffffffff', endColorstr='#ffe4e4e4', GradientType=0);
		-webkit-filter: none;
		filter: none;
		border: 1px solid #d5d5d5;
		text-shadow: 0 1px 0 rgba(255, 255, 255, 0.3);
EOT;
		return $tmp;
	}

}

//各ページからのログインが有効でかつ非ログイン状態の場合は、ログインプログラムをロード
if (!check_rights() && empty($params['circle']['meta']['hide_login'])) {
	snippet_loginform(1);
}

// グローバルナビ(テーマに合わせてカスタマイズ、get_gnavがtplで使われていなければ削除可能)
function get_gnav()
{
	global $html,$params,$database,$ut,$config;

	$link = "link_proc";

	$top_page = $params['circle']['meta']['top_page'];
	$meta = get_theme_metadata();
	
	$nav0 = $nav1 = '';
	if (isset($meta['theme']['global_menu'])) {
		foreach ($meta['theme']['global_menu'] as $v) {
			$u = ex_theme_link($v['link']);
			$n = $v['text'];
			$s = "";
			$u0 = trim($u,'/');
			if ($params['request'] == $u0) {
				$s = " active ";
			} else {
				if (!empty($params['breadcrumb']) && is_array($params['breadcrumb'])) {
					foreach ($params['breadcrumb'] as $vv) {
						if (trim($vv['link'],'/') == $u0) {
							$s = " active ";
							break;
						}
					}
				}
			}
			if (!empty($v['mobi']) || !$nav0) {
				$nav0 .= "<a href='$u' >$n</a>";
				$nav1 .= "<li class='$s mobi' ><a href='$u' >$n</a></li>";
			} else {
				$nav1 .= "<li class='$s' ><a href='$u' >$n</a></li>";
			}
		}
	} else {
$nav0 = <<<EOT
		<a href="{$ut->link()}" >{$params['circle']['name']}</a>
EOT;
$nav1 = <<<EOT
		<li><a href="{$ut->link()}"  >HOME</a></li>
		<li><a href="{$ut->link('list')}"  >Page list</a></li>
EOT;
	}
	if (!check_rights() && empty($params['circle']['meta']['hide_login'])) {
$nav1 .= <<<EOT
		<li><a href='javascript:void(ot.show_login_panel())'  >Login</a></li>
EOT;
	}

$buff = <<<EOT
	<div class="home">
		{$nav0}
	</div>
	<div class="links" >
		<ul>
		{$nav1}
		</ul>
	</div>
	<button class="toggle" type="button" data-toggle="collapse" data-target="#navbar-main" onclick='\$("#navbar .links").toggle(200)'>
		<span class="icon-bar"></span>
		<span class="icon-bar"></span>
		<span class="icon-bar"></span>
	</button>
EOT;

	return $buff;
}

// for breadcrumb (テーマに合わせてカスタマイズ)
function breadcrumb()
{
	global $params,$ut;
	if ($ut->is_home()) {
		return '';
	}
	if ( isset($params['circle']) && isset($params['circle']['name']) ) {
		$name = $params['circle']['name'];
		if ( isset($params['breadcrumb']) ) {
			//パンくずの作成
			$a = '';
$a .= <<<EOT
			<li ><a href="{$ut->link()}">{$name}</a> </li>
EOT;
			if ( is_array($params['breadcrumb']) ) {
				$c = count($params['breadcrumb']);
				for ( $i=0; $i < $c; ++$i ) {
					$v = $params['breadcrumb'][$i];
					if ( $i != $c-1 ) {
						$d1 = '';
					} else {
						$d1 = ' class="active" ';
					}
					if ( $v['link'] ) {
$a .= <<<EOT
						<li $d1><a href="{$v['link']}">{$v['text']}</a> </li>
EOT;
					} else {
$a .= <<<EOT
						<li $d1>{$v['text']} </li>
EOT;
					}
				}
			}
			return '<ul class="breadcrumb " >'.$a.'</ul>';
		}
	}
	return '';
}

// 絶対パスのURLは、そのままそれ以外はパス調整する
function ex_theme_link($u)
{
	global $ut;
	if (preg_match("/(https?:)?\/\//",$u)) {
		return $u;
	}
	return $ut->link($u);
}

// for system toolbar (通常は変更不可)
function system_toolbar()
{
	global $html,$params,$config,$ut,$p_circle;
	
	if (!check_rights()) {
		return '';
	}

	custom_toolbar();

	//system_toolbarの整形
	$x = array();
	foreach ($html['system_toolbar'] as $k=>$v) {
		if (!is_array($v)) {
			$x['opt'][] = $v;
		} else {
			foreach ($v as $vv) {
				if (is_array($vv)) {
					if (!isset($vv['caption'])) {
						foreach ($vv as $vvv) {
							$x['opt'][] = $vvv;
						}
					} else {
						$x[$k][] = $vv;
					}
				} else {
					$x[$k][] = $vv;
				}
			}
		}
	}
	$html['system_toolbar'] = $x;

	$opacity = "0.5";
	$b = 'rgba(0, 0, 0, 0.8)';
	if (isset($params['manager'])) {
		$b = 'rgba(125, 2, 2, 0.8)';
	}
	if (isset($params['page']['block_type']) && $params['page']['block_type']>=15) {
		$b = 'rgba(4, 119, 162, 0.8)';
	}
	// system nav css
$buff = <<<EOT
	<style>
	.ot-show {
	  opacity: 1 !important;
	}
	.onethird-system-nav-typeB {
	  position: fixed;
	  top: 0;
	  left: 0;
	  padding: 5px;
	  text-align: left;
	  font-size: 14px;
	  background-color: $b;
	  background-color: #222\9;
	  border: 2px solid #fff;
	  color: #e0e0e0;
	  z-index: 99999;
	  opacity: $opacity;
	  box-shadow: 0px 0px 6px #000000;
	  font-family: 'Lucida Console',Verdana, Roboto, 'Droid Sans', Meiryo, 'Hiragino Kaku Gothic ProN';
	  font-size:16px;
	  line-height:150%;
	}
	.onethird-system-nav-typeB:hover {
	  opacity: 1;
	}
	.onethird-system-nav-typeB a,
	.onethird-system-nav-typeB a:hover {
	  text-decoration: none;
	}
	.onethird-system-nav-typeB a,
	.onethird-system-nav-typeB a:link,
	.onethird-system-nav-typeB a:visited {
	  color: #e0e0e0;
	  text-decoration: none;
	}
	.onethird-system-nav-typeB a:hover {
	  color: #08C;
	}
	.onethird-system-nav-typeB img {
	  width: 25px;
	  margin: 2px;
	  -webkit-box-sizing: initial; // for bootstrap
	  -moz-box-sizing: initial;
	  box-sizing: initial;
	}
	.onethird-system-nav-typeB .inner {
	  width: 30px;
	  padding: 0;
	}
	.onethird-system-nav-typeB img {
	  vertical-align: middle;
	}
	.onethird-system-nav-typeB .dropdown_item {
	  float: left;
	  position: relative;
	  cursor: pointer;
	}
	.onethird-system-nav-typeB .dropdown_item .dropdown_box {
	  background-color: rgba(0, 0, 0, 0.9);
	  background-color: #222\9;
	  padding: 9px;
	  position: absolute;
	  top: 15px;
	  left: 30px;
	  width: 250px;
	  -webkit-border-radius: 3px;
	  -moz-border-radius: 3px;
	  border-radius: 3px;
	  -webkit-box-shadow: 0px 0px 6px #8d8b8b;
	  -moz-box-shadow: 0px 0px 6px #8d8b8b;
	  box-shadow: 0px 0px 6px #8d8b8b;
	}
	.onethird-system-nav-typeB .dropdown_item .dropdown_box div :hover {
	  color: #08C;
	}
	.onethird-system-nav-typeB .dropdown_item .dropdown_box .item {
	  padding: 2px;
	}
	.onethird-system-nav-typeB .dropdown_item .dropdown_box .item a {
	  border: none;
	}
	.onethird-system-nav-typeB .dropdown_item .dropdown_box .dropdown_box_sub {
		left: 7px;
		position: relative;
		margin-bottom: 13px;
		top: 4px;
	}
	.ot-hidden {
		opacity: 0;
		-webkit-transition: opacity linear 0.5s;
		-moz-transition: opacity linear 0.5s;
		-ms-transition: opacity linear 0.5s;
		-o-transition: opacity linear 0.5s;
		transition: opacity linear 0.5s;
	}
	.onethird-system-nav-typeB .dropdown_item .dropdown_box.ot-hidden:hover {
	  opacity: 1;
	}
	.onethird-system-nav-typeB .dropdown_item hr {
	  border: 0;
	  border-bottom: 1px dashed #858585;
	  background: #000;
	}
	.onethird-system-nav-typeB.ot-hidden:hover {
	  opacity: 1;
	}
	@media (max-width: 767px) {
	  .onethird-system-nav-typeB {
		font-size: 120%;
		line-height: 160%;
		top: initial;
		bottom: 0;
	  }
	  .onethird-system-nav-typeB img {
		width: 35px;
		height: 35px;
	  }
	  .onethird-system-nav-typeB.ot-hidden {
		opacity: 0.3;
	  }
	  .onethird-system-nav-typeB .inner {
		width: initial;
		padding: 0;
	  }
	  .onethird-system-nav-typeB .dropdown_item .dropdown_box {
		top: initial;
		bottom: 47px;
	  }
	}
	</style>
EOT;
	$html['css']['system_toolbar'] = $buff;

	$buff = '';
	if (!function_exists('sub_item')) {
		function sub_item($ar) {
$buff = <<<EOT
			<div style='position:relative;'>
				<div class='dropdown_hnd_sub '>
					<div class='sub_menu'>{$ar['caption']}</div>
					<div class="dropdown_box_sub dropdown_box" style='display:none;z-index:1000;'>
EOT;
						foreach ($ar as $k=>$v) {
							if (!is_numeric($k)) { continue; }
$buff .= <<<EOT
							<div class="item">
								$v
							</div> 
EOT;
						}
$buff .= <<<EOT
					</div> 
				</div> 
			</div> 
EOT;
			return $buff;
		}
	} else {
		if (check_rights()) {
			$html['alert'][] = 'system_toolbar-tag is duplicated.';
		}
	}
	$avatar = get_user_avatar_ex(array('alt'=>'Account setting','size'=>0));
$buff .= <<<EOT
<div class="onethird-system-nav-typeB" >
	<div class="inner">
		<div class="dropdown_item">
			<div style='padding:2px;display: inline-block;'>$avatar</div>
EOT;
			if ( isset($html['system_toolbar']) && $html['system_toolbar'] ) {
				foreach ($html['system_toolbar'] as $k=>$v) {
					if (!is_array($v) || count($v) == 0) {
						continue;
					}
					if ($k === 0) {
						$icon = $ut->icon('system');
					} else if ($k === 10) {
						$icon = $ut->icon('lock');
					} else if ($k == 'opt') {
						$icon = $ut->icon('folder',"width='32'");
					} else {
						$icon = $ut->icon('folder2',"width='32'");
					}
					$tmp = '';
					foreach ($v as $kk=>$vv) {
						if (!$vv) { continue; }
						if (is_array($vv)) {
							$tmp .= sub_item($vv);
							continue;
						}
$tmp .= <<<EOT
						<div class="item">
							$vv
						</div> 
EOT;
					}
					if ($tmp) {
$buff .= <<<EOT
						<span class='dropdown_hnd'>$icon</span>
						<div class="dropdown_box_root dropdown_box" style='display:none;'>
							$tmp
						</div> 
EOT;
					}
				}

$buff .= <<<EOT
EOT;
			}

$buff .= <<<EOT

		</div>
	</div>
</div>
EOT;

$buff .= <<<EOT
		<script>
		\$(function(){
			\$("body").bind("click", function (e) {
				\$('.dropdown_hnd').next().hide(100);
				\$(".onethird-system-nav-typeB").removeClass("ot-show");
				\$('.dropdown_hnd').next().removeClass('ot-show').hide(200);
				var o = \$('.edit_pointer');
				if (o.css('opacity') && o.css('opacity')!=0) {
					o.css('opacity','');
				} else {
					o.css('opacity','0.8');
				}
			});
			\$(".dropdown_hnd").click(function (e) {
				var obj = \$(this);
				var top = (e.clientY-8)+'px';
				if (\$(window).scrollTop()+100 < \$('.onethird-system-nav-typeB').offset().top) {
					top = 'inherit';
				}
				var n = obj.next();
				if (n.hasClass('ot-show')) {
					n.removeClass('ot-show').hide(100);
				} else {
					\$('.dropdown_hnd').next().removeClass('ot-show').hide(100);
					n.css('top',top);
					n.addClass('ot-show').show(200);
				}
				\$(".onethird-system-nav-typeB").addClass("ot-show");
				return false;
			}); 
			\$(".onethird-system-nav-typeB .sub_menu").click(
				function (e) {
					var n = \$(this).next();
					if (n.hasClass("ot-show")) {
						n.removeClass('ot-show').hide(100);
						return false;
					} else {
						\$(".dropdown_box_sub ").hide();
						n.addClass('ot-show').show(200);
						return false;
					}
				}
			);
			
EOT;
			if ( isset($params['circle']['meta']['hide_nav']) ) {
$buff .= <<<EOT
				\$('.onethird-system-nav-typeB').addClass('ot-hidden');
EOT;
			} else {
$buff .= <<<EOT
				\$('.dropdown_box').addClass('ot-hidden');
				\$('.dropdown_box').hover(null,function(){
					\$('.dropdown_box').addClass('ot-hidden');
				});
EOT;
			}

$buff .= <<<EOT
			ot.alert = function(mess) {
				oj = \$('.onethird-alert p');
				if (!oj.length) {
					var str = "<div class='onethird-alert' ><p>"+mess+"</p><input type='button' class='onethird-button' value='OK' onclick='ot.overlay(0)' /></div>";
					ot.overlay(1, str,{fadeout:5000});
				} else {
					oj.html(oj.html()+'<p>'+mess+'</p>');
				}
			};
			ot.waring = function(mess) {
				oj = \$('.onethird-waring p');
				if (!oj.length) {
					var str = "<div class='onethird-waring' ><p>"+mess+"</p><input type='button' class='onethird-button' value='OK' onclick='\$(this).parent().remove()' /></div>";
					\$("body").append(str);
					oj = \$('.onethird-waring p');
				} else {
					oj.html(oj.html()+'<p>'+mess+'</p>');
				}
				oj.parent().css('left',parseInt(\$(window).width()/2)+'px').fadeOut(2000,function(){\$(this).remove()});
			};
			\$('.onethird-information, .onethird-alert, .onethird-waring').click(function(){\$(this).remove()});
		});
		</script>
EOT;

	snippet_overlay();

	if (isset($html['information'])) {
		$buff .= "<div class='onethird-information' onclick='\$(this).remove()' >";
		foreach ($html['information'] as $v) {
			$buff .= "<div>$v</div>";
		}
		$buff .= "</div>";
	}
	
	if (isset($html['alert'])) {
$buff .= <<<EOT
		<script>
			\$(function(){
EOT;
				foreach ($html['alert'] as $v) {
					$buff .= "ot.alert(\"$v\");";
				}
$buff .= <<<EOT
			});
		</script>
EOT;
	}

	return $buff;

}

function my_pagination_renderer( $option )
{
	global $config,$params;
	
	if (!isset($option['total'])) { return 'pagination-error'; }
	if (!isset($option['offset'])) { $option['offset'] = 0; }
	if (!isset($option['page_size'])) { $option['page_size'] = 100; }
	if (!isset($option['max_pagination'])) { $option['max_pagination'] = 10; }
	if (!isset($option['previous'])) { $option['previous'] = ''; }
	if (!isset($option['next'])) { $option['next'] = ''; }
	
	$buff = '';
	if ($option['total'] && $option['total'] > $option['page_size']) {
		$ar = array();
		pagination_urls( $ar, $option );

		$buff.='<div ><ul class="pagination">';
		if (isset($ar['prev'])) {
			$buff.="<li class='prev'><a href='{$ar['prev']}'>&larr; {$option['previous']}</a></li>";
		} else {
			$buff.="<li class='prev disabled'><a>&larr; {$option['previous']}</a></li>";
		}
		
		foreach($ar['mid'] as $k=>$v) {
			if ($v == '...') {
				$buff .= "<li style=''><a style='padding:0;'><span style='background-color: rgba(255, 255, 255, 0.62); padding: 6px 12px; display: inline-block;'>...</span></a></li>";
				continue;
			}
			$buff.="<li ";
			if ( $option['offset'] == $k ) {
				$buff.=" class='active' ";
			}
			$buff.=" ><a href='{$v}'>".((int)$k+1)."</a></li>";
		}
		
		if (isset($ar['next'])) {
			$buff.="<li class='next'><a href='{$ar['next']}'>{$option['next']} &rarr;</a></li>";
		} else {
			$buff.="<li class='next disabled'><a>{$option['next']} &rarr;</a></li>";
		}
		$buff .= '</ul></div>';
	}

	return $buff;
}


// Grid12
$plugin_ar[164] = array(
	'selector' => "grid12"
	, 'title' => "Grid12"
	, 'php' => false
	, 'add_inner' => true
	, 'renderer' => true
);

function grid12()
{
	global $params,$ut;
	$arg = func_get_args();
	$ut->get_arg($arg);
	if (empty($arg['name'])) {
		$arg['name'] = 'grid12';
	}
	return _grid12_renderer($params['page'], $arg['name']);
}

function grid12_renderer(&$page_ar)
{
	return _grid12_renderer($page_ar,'grid12');
}

function _grid12_renderer(&$page_ar,$name)
{
	global $html,$params,$database,$ut;

	$meta = &$page_ar['meta']['plugin_grid12'][$name];
	if (empty($meta['item'])) {
		$meta['item'] = array();
	}

	if (check_rights('edit') && isset($_POST['ajax']) && isset($_POST['page']) && isset($_POST['name'])) {
		$r = array();
		$r['result'] = false;
		$r['name'] = sanitize_str($_POST['name']) ;
		if ($r['name'] == $name && $page_ar['id'] == (int)$_POST['page']) {
			if ($_POST['ajax'] == 'plugin_grid12_add') {
				if (!empty($_POST['img'])) {
					$r['img'] = sanitize_str($_POST['img'],false);
					$meta['item'][] = array('type'=>'img', 'grid'=>4, 'img'=>$r['img']);
				} else {
					$meta['item'][] = array('type'=>'txt', 'grid'=>4);
				}
				$r['result'] = mod_data_items($page_ar);
				echo( json_encode($r) );
				exit();
			}
			if ($_POST['ajax'] == 'plugin_grid12_remove') {
				$r = array();
				$r['result'] = false;
				$r['id'] = sanitize_num($_POST['id']) ;
				$r['name'] = sanitize_str($_POST['name']) ;
				for ($i=0; $i < count($meta['item']); ++$i) {
					if ($r['id'] == $i) {
						unset($meta['item'][$i]);
						$meta['item'] = array_merge($meta['item']);
						break;
					}
				}
				$page_ar['metadata'] = serialize64($page_ar['meta']);
				$r['result'] = mod_data_items($page_ar);
				echo( json_encode($r) );
				exit();
			}
			if ($_POST['ajax'] == 'plugin_grid12_chg') {
				$r = array();
				$r['result'] = false;
				$r['id'] = sanitize_num($_POST['id']);
				$r['name'] = sanitize_str($_POST['name']);
				$r['g'] = (int)$_POST['g'];
				for ($i=0; $i < count($meta['item']); ++$i) {
					if ($r['id'] == $i) {
						$meta['item'][$i]['grid'] = $r['g'];
						break;
					}
				}
				$page_ar['metadata'] = serialize64($page_ar['meta']);
				$r['result'] = mod_data_items($page_ar);
				echo( json_encode($r) );
				exit();
			}
		}
	}
	
	if (check_rights('edit')) {
		_grid12_renderer_inpage_edit($page_ar,$meta,$name);
$html['meta']['grid12_renderer'] = <<<EOT
		<script>
			\$(function() {
				ot.plugin_grid12_add_text = function (name,page) {
					ot.ajax({
						type: "POST"
						, url: '{$params['request_name']}'
						, data: "ajax=plugin_grid12_add&name="+encodeURIComponent(name)+"&page="+page
						, dataType:'json'
						, success: function(data){
							if ( data && data['result'] ) {
								location.reload(true);
							}
						}
					});
				};
				ot.plugin_grid12_add_image = function (name,page) {
					opt = {};
					opt.title = 'Add image';
					opt.select = function(obj){
						var u = \$(obj).attr('src');
						ot.ajax({
							type: "POST"
							, url: '{$params['request_name']}'
							, data: "ajax=plugin_grid12_add&img="+encodeURIComponent(u)+"&name="+encodeURIComponent(name)+"&page="+page
							, dataType:'json'
							, success: function(data){
								if ( data && data['result'] ) {
									location.reload(true);
								}
							}
						});
					}
					ot.open_uploader(opt);
				};
				ot.plugin_grid12_remove = function (name,id,page) {
					if (!confirm('Are you sure you want to delete this?')) { return; }
					ot.ajax({
						type: "POST"
						, url: '{$params['request_name']}'
						, data: "ajax=plugin_grid12_remove&id="+id+"&name="+encodeURIComponent(name)+"&page="+page
						, dataType:'json'
						, success: function(data){
							if ( data && data['result'] ) {
								location.reload(true);
							}
						}
					});
				};
				ot.plugin_grid12_chg = function (obj,name,id,page) {
					var g = \$(obj).val();
					ot.ajax({
						type: "POST"
						, url: '{$params['request_name']}'
						, data: "ajax=plugin_grid12_chg&id="+id+"&name="+encodeURIComponent(name)+"&g="+encodeURIComponent(g)+"&page="+page
						, dataType:'json'
						, success: function(data){
							if ( data && data['result'] ) {
								location.reload(true);
							}
						}
					});
				};
			})
		</script>
EOT;
	}

$buff = '';
	if (empty($meta['item'])) {
		$buff .= '<p>no items</p>';
	} else {
$buff .= <<<EOT
		<div class="row">
EOT;
		foreach ($meta['item'] as $k=>$v) {
			$g_ar = array('1'=>'one columns','2'=>'two columns','3'=>'three columns'
				,'4'=>'four columns','5'=>'five columns','6'=>'six columns','7'=>'seven columns','8'=>'eight columns'
				,'9'=>'nine columns','10'=>'ten columns','11'=>'eleven columns','12'=>'twelve columns'
			);
			if (!isset($g_ar[$v['grid']])) { $v['grid'] = 1; }
			if ($v['type'] == 'img') {
				$a = "<img src='{$v['img']}' alt='' style='width: 100%;' />";
			} else {
				if (empty($v['txt'])) {	$v['txt'] = '...';	}
				expand_buff($v['txt']);
				$a = "<div class='g_{$name}_{$k}_{$page_ar['id']}'>{$v['txt']}</div>";
			}
			if (check_rights('edit')) {
$buff .= <<<EOT
			<div class="{$g_ar[$v['grid']]} onethird-edit-pointer" >
				$a
				<div class="edit_pointer ">	
					{$ut->icon('edit', "onclick='ot.grid12_inpage_edit(\"{$name}\",$k,{$page_ar['id']})'")}
					<select onchange='ot.plugin_grid12_chg(this,"$name",$k,{$page_ar['id']})'>
EOT;
					foreach ($g_ar as $kk=>$vv) {
$buff .= <<<EOT
						<option value='$kk' {$ut->check($v['grid']==$kk,' selected ')}>{$kk} grid</option>
EOT;
					}
$buff .= <<<EOT
					</select>
					{$ut->icon('delete', "onclick='ot.plugin_grid12_remove(\"{$name}\",$k,{$page_ar['id']})'")}
				</div>
			</div>
EOT;
			} else {
$buff .= <<<EOT
				<div class="{$g_ar[$v['grid']]} onethird-edit-pointer" >$a</div>
EOT;
			}
		}
$buff .= <<<EOT
		</div>
EOT;
	}
	if (check_rights('edit')) {
$buff .= <<<EOT
		<div class="edit_pointer upper">	
			<a href='javascript:void(ot.plugin_grid12_add_text("$name",{$page_ar['id']}))' class='onethird-button mini'>Add text</a>
			<a href='javascript:void(ot.plugin_grid12_add_image("$name",{$page_ar['id']}))' class='onethird-button mini'>Add image</a>
EOT;
			$buff .= std_blockmenu_renderer($page_ar);
$buff .= <<<EOT
		</div>
EOT;
	}
	

	return frame_renderer($buff);
}

function _grid12_renderer_inpage_edit(&$page_ar,&$meta,$name)
{
	global $html,$params,$database,$ut,$config;

$buff = <<<EOT
<script>
	ot.grid12_inpage_edit = function (name,id,page) {
		\$('#dialog_grid12_inpage_edit').remove();
		ot.editor.mode =".g_"+name+"_"+id+"_"+page;
		ot.editor.option = {};
		ot.editor.option.idx = id;
		ot.editor.option.name = name;
		ot.editor.option.page = page;
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=grid12_inpage_edit_init&id="+id+"&name="+encodeURIComponent(name)+"&page="+page
			, dataType:'json'
			, success: function(data){
				if (data && data['result']) {
					if (data.editor_toolbar) {
						ot.editor.editor_toolbar = data.editor_toolbar;
					}
					var obj = \$( "#dialog_grid12_inpage_edit" ).width(ot.editor.width+60);
					if (ot.tryitEditor && ot.tryitEditor.check_instance()) {
						return;
					}
					ot.editor.org_html = \$(ot.editor.mode).html();
					if (data['html']) {
						ot.editor.org_html = data['html'];
					}
					ot.editor.obj = \$(ot.editor.mode);
					var obj = ot.editor.obj;
					\$('.onethird-edit-pointer .edit_pointer').hide();
					ot.editor.quit = function() {
						ot.tryitEditor.quit();
						\$('.onethird-edit-pointer .edit_pointer').show();
					};
					var load = [];
					load.push({type:'script', src:'{$config['site_url']}js/tryitEditor.js' });
					delayedload(load
						,function() {
							var opt = ot.editor.option || {};
							var param = {};
							param.idx = ot.editor.option.idx;
							param.name = ot.editor.option.name;
							param.page = ot.editor.option.page;
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
							opt.after_toolbar += "<input type='button' class='onethird-button mini' value='Update' onclick='ot.grid12_inpage_edit_save("+JSON.stringify(param)+")' />";
							opt.after_toolbar += "<input type='button' class='onethird-button mini' value='Image' onclick='ot.open_local_filer()' />";
							
							if (tmp_toolbar === undefined) {
								if (ot.editor_toolbar) {
									opt.after_toolbar += ot.editor_toolbar;
								}
							} else {
								opt.after_toolbar += tmp_toolbar;
							}
							if (ot.grid12_inpage_edit_option) {
								var a = ot.grid12_inpage_edit_option('inline',opt);
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
		});
	};

	ot.grid12_inpage_edit_save = function(option) {
		var html = '';
		var opt = '';
		var mode = 0;
		
		opt +="&idx="+option.idx;
		opt +="&name="+option.name;
		opt +="&page="+option.page;
		html = ot.tryitEditor.html();
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=grid12_inpage_edit_save"+opt+"&data="+encodeURIComponent(html)
			, dataType:'json'
			, success: function(data){
				if (data && data['result']) {
					ot.tryitEditor.quit();
					\$('.onethird-edit-pointer .edit_pointer').show();
					\$(ot.editor.mode).html(data['data']);
				} else {
					alert('error');
				}
			}
			, error: function(data){
				alert('error');
			}
		});
	};

</script>
EOT;

	if (check_rights('edit') && isset($_POST['ajax']) && isset($_POST['page']) && isset($_POST['name'])) {
		$r = array();
		$r['result'] = false;
		$r['name'] = sanitize_str($_POST['name']) ;
		if ($r['name'] == $name && $page_ar['id'] == (int)$_POST['page']) {
			if ($_POST['ajax'] == 'grid12_inpage_edit_save')  {
				$r = array();
				$r['result'] = false;
				$r['id'] = (int)$_POST['idx'];
				$r['name'] = sanitize_str($_POST['name']) ;
				$r['data'] = sanitize_html($_POST['data']) ;
				if ($r['name'] == $name) {
					for ($i=0; $i < count($meta['item']); ++$i) {
						if ($r['id'] == $i) {
							$meta['item'][$i]['txt'] = $r['data'];
							break;
						}
					}
					$page_ar['metadata'] = serialize64($page_ar['meta']);
					$r['result'] = mod_data_items($page_ar);
					expand_buff($r['data']);
					echo( json_encode($r) );
					exit();
				}
			}
			
			if ($_POST['ajax'] == 'grid12_inpage_edit_init')  {
				$r = array();
				$r['result'] = false;
				$r['id'] = (int)$_POST['id'];
				$r['name'] = sanitize_str($_POST['name']) ;
				if ($r['name'] == $name) {
					for ($i=0; $i < count($meta['item']); ++$i) {
						if ($r['id'] == $i) {
							if (isset($meta['item'][$i]['txt'])) {
								$r['html'] = $meta['item'][$i]['txt'];
							} else {
								$r['html'] = '';
							}
							break;
						}
					}
					$page_ar['metadata'] = serialize64($page_ar['meta']);
					$r['result'] = mod_data_items($page_ar);
					echo( json_encode($r) );
					exit();
				}
			}
		}
	}
	
	$html['meta']['_grid12_renderer_inpage_edit'] = $buff;

}

?>