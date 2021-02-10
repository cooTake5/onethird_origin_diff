<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.

	require_once(dirname(__FILE__).'/../config.php');
	require_once(dirname(__FILE__).'/../module/utility.basic.php');

	basic_initialize();
	avoid_attack();
	snippet_avoid_robots();

	$params['manager'] = 'setting';
	$params['template'] = 'admin.tpl';

	if (!check_rights('admin')) {
		exit_proc(403, '管理権限がありません');
	}

	if (!$p_circle) {
		exit_proc(0, 'Not Found');
	}

	// 標準プラグイン/テーマプラグイン読み込み
	load_site_plugin();

	if (isset($_GET['mode'])) {
		$p_mode = $_GET['mode'];
	} else {
		$p_mode = '';
	}
	
	//パンくず表示
	$params['breadcrumb'][] = array( 'link'=>'', 'text'=>'Site settings' );

	$html['article'][] = draw_setting();

	snippet_header();
	snippet_footer();
	snippet_system_nav();
	snippet_inpage_edit();
	

	expand_circle_html($params['page']['meta']);

function draw_setting()
{
	global $p_circle ;
	global $config;
	global $params,$database;
	global $html;
	
	if ( !check_rights('admin')) {
		return '管理権限がありません';
	}
	
	$buff = '';

	$tab_ar=array();
	$tab_ar[] = array('name'=>'basic setting',		'proc'=>'tab_setting' );
	$tab_ar[] = array('name'=>'option1',		'proc'=>'tab_option1' );
	$tab_ar[] = array('name'=>'option2',		'proc'=>'tab_option2' );
	$tab_ar[] = array('name'=>'option3',		'proc'=>'tab_option3' );
	$tab_ar[] = array('name'=>'option4',		'proc'=>'tab_option4' );

	if (isset($_GET['tab'])) {
		$p_tab=(int)($_GET['tab']);
	} else {
		$p_tab=0;
	}
	if (!isset($tab_ar[$p_tab])) {
		return;
	}


$buff.= <<<EOT
	<div >
		<div class='onethird-tab'>
			<ul class='tab-head clearfix'>
EOT;
			$i=0;
			for($i=0;$i<count($tab_ar);++$i) {
				$v = $tab_ar[$i];
				($i==$p_tab)?$act=" class='active' ":$act='';
				$buff.=("<li $act ><a href='{$config['site_url']}{$config['admin_dir']}/setting.php?circle={$p_circle}&tab=$i' >{$v['name']}</a></li>");
			}
$buff.= <<<EOT
			</ul>

			<div class='tab-body'>
EOT;
			
			if ( function_exists($tab_ar[$p_tab]['proc'])) {
				$ar = array();
				$buff .= $tab_ar[$p_tab]['proc']( $ar, $tab_ar[$p_tab], $p_tab );
			} else {
			}
			
			
$buff.= <<<EOT
			</div>
		</div> 
	</div> 
	<p><br/></p>
EOT;
	
	return frame_renderer($buff);

}
function read_circledata( &$ar )
{
	global $database, $p_circle;

	$ar = $database->sql_select_all("select * from ".DBX."circles where id=? ",$p_circle);
	if ($ar && $ar[0]) {
	} else {
		system_error( __FILE__, __LINE__ );
	}
}
function tab_setting( $ar, $m ,$p_tab )
{
	global $p_circle, $params, $html, $database, $ut;
	$public_flag0 = $public_flag2 = "";
	$join_flag0 = $join_flag2 = "";
	
	if (isset($_POST['mdx']) && $_POST['mdx']=='mod_circle') {
	
		if (!isset($_POST['p_public'])) { $_POST['p_public'] = 0; }
		if (!isset($_POST['p_join'])) { $_POST['p_join'] = 0; }
		if (!isset($_POST['p_public'])) { $_POST['p_public'] = 0; }
		if (!isset($_POST['p_url'])) { $_POST['p_url'] = ''; }
	
		$id = $p_circle;
		$name = sanitize_str($_POST['p_name']);
		$public_flag = (int)($_POST['p_public']);
		$join_flag = (int)($_POST['p_join']);
		$cid = sanitize_str($_POST['p_cid']);

		$ar2 = $database->sql_select_all("select id from ".DBX."circles where name=? and id<>?",$name,$id);
		if ($ar2 && $ar2[0]) {
			$html['alert'][] = "サイト名($name)は既に使用しています、他の名前を指定してください";
			return;
		}

		if ($cid) {
			$ar2 = $database->sql_select_all("select id from ".DBX."circles where cid=? and id<>?",$cid,$id);
			if ($ar2 && $ar2[0]) {
				$html['alert'][]="サイトID($cid)は既に使用しています、他の名前を指定してください";
				return;
			} else {
			}
		}
		if ($database->sql_update("update ".DBX."circles set name=?, public_flag=?, join_flag=?, cid=?
			 where id=?", $name, $public_flag, $join_flag, $cid, $id)) {
		}

		if (isset($_POST['p_hide_login'])) {
			$params['circle']['meta']['hide_login']=1;
		} else {
			unset( $params['circle']['meta']['hide_login'] );
		}
		if (isset($_POST['p_hide_nav'])) {
			$params['circle']['meta']['hide_nav']=1;
		} else {
			unset( $params['circle']['meta']['hide_nav'] );
		}
		if (isset($_POST['p_pv_logging'])) {
			$params['circle']['meta']['pv_logging']=1;
		} else {
			unset( $params['circle']['meta']['pv_logging'] );
		}
		if (isset($_POST['p_folder_systag'])) {
			$params['circle']['meta']['folder_systag']=1;
		} else {
			unset( $params['circle']['meta']['folder_systag'] );
		}

		if ($database->sql_update( "update ".DBX."circles set metadata=? where id=?", serialize64($params['circle']['meta']), $p_circle)) {
		}
	}
	
	read_circledata($ar);

	$buff = '';

	if ($ar[0]['public_flag'] == 2) {
		$public_flag2 = " checked ";
		
	} else {
		$public_flag0 = " checked ";
	}

	if ($ar[0]['join_flag'] == 2) {
		$join_flag2 = " checked ";
	} else {
		$join_flag0 = " checked ";
	}

	$ar2 = $database->sql_select_all("select id from ".DBX."storage limit 1");
	if ($ar2 && !$ar2[0]['id']) {
		if (!empty($_GET['sqlite_error_mod'])) {
$sql = <<<EOT
			BEGIN TRANSACTION;
			CREATE TEMP TABLE 'tksqlite_temp_storage_1424990867' (
			    'id', 'type', 'data', 'name', 'circle'
			);
			INSERT INTO 'temp'.'tksqlite_temp_storage_1424990867'
			SELECT "id", "type", "data", "name", "circle" FROM 'main'.'storage';
			DROP TABLE 'main'.'storage';
			CREATE TABLE 'main'.'storage' (
			    'id'  PRIMARY KEY AUTOINCREMENT UNIQUE,
			    'type' INTEGER DEFAULT 0,
			    'data' TEXT,
			    'name' TEXT,
			    'circle' INTEGER DEFAULT 0
			);
			INSERT INTO 'main'.'storage' 
			SELECT * FROM 'tksqlite_temp_storage_1424990867';
			DROP TABLE 'tksqlite_temp_storage_1424990867';
			COMMIT;
EOT;
			$ar2 = explode( ';', $sql);
			foreach ($ar2 as $v) {
				$database->sql_update($v);
			}
$buff.= <<<EOT
			<div style='background-color: #FFE9A4;padding: 10px;'>
				<p>
					SQLITE table updated.
				</p>
			</div>
EOT;
		} else {
$buff.= <<<EOT
			<div style='background-color: #FCD8D8;padding: 10px;'>
				<p>
					SQLITE table error, 
					<a href='{$ut->link("{$config['admin_dir']}/setting.php",'&:sqlite_error_mod=true')}'>Click to modify this problem.</a>
				</p>
			</div>
EOT;
		}
	}
	
	if (isset($params['circle']['meta']['hide_login'])) {
		$hide_login = ' checked ';
	} else {
		$hide_login = '';
	}
	if (isset($params['circle']['meta']['pv_logging'])) {
		$pv_logging = ' checked ';
	} else {
		$pv_logging = '';
	}
	if (isset($params['circle']['meta']['folder_systag'])) {
		$folder_systag = ' checked ';
	} else {
		$folder_systag = '';
	}
	if (isset($params['circle']['meta']['hide_nav'])) {
		$hide_nav = ' checked ';
	} else {
		$hide_nav = '';
	}

$buff.= <<<EOT
	<form method='post' id='form0' action='{$params['safe_request']}' >
		<input type='hidden' name='mdx' id='mdx' value='mod_circle' />
		<div class='onethird-setting'>
			<table>
				<tr>
					<td>サイト名</td> 
					<td>
						<input type='text' name='p_name' id='p_name' value='{$ar[0]['name']}' />
					</td> 
				</tr>
				<tr>
					<td>サイトID</td> 
					<td>
						<input type='text' name='p_cid' id='p_cid' value='{$ar[0]['cid']}' />
					</td> 
				</tr>
				<tr>
					<td>公開オプション
					</td> 
					<td>
						<ul class='inputs-list'>
							<li><label><input type='radio' name='p_join' value='2' $join_flag2 >誰でも自由に閲覧できる (公開サイト)</label></li>
							<li><label><input type='radio' name='p_join' value='0' $join_flag0 >登録ユーザー以外閲覧できない（登録制サイト）</label></li>
						</ul>
					</td> 
				</tr>
				<tr>
					<td>状態</td> 
					<td>
						<ul class='inputs-list'>
							<li><label><input type='radio' name='p_public' value='0' $public_flag0 > 公開中</label></li>
							<li><label><input type='radio' name='p_public' value='2' $public_flag2 > メンテナンス中</label></li>
						</ul>
					</td> 
				</tr>
				<tr>
					<td>オプション</td>
					<td>
						<ul>
							<li ><label><input type='checkbox' id='p_hide_nav' name='p_hide_nav' $hide_nav  />システムメニューを隠す</label></li>
							<li ><label><input type='checkbox' id='p_hide_login' name='p_hide_login' $hide_login  />ログイン機能を隠す</label></li>
							<li ><label><input type='checkbox' id='p_pv_logging' name='p_pv_logging' $pv_logging  />全ページでPVカウントする</label></li>
							<li ><label><input type='checkbox' id='p_folder_systag' name='p_folder_systag' $folder_systag  />folder systag オプションを使う</label></li>
						</ul>
					</td>
				</tr>
			</table>
			<div class='actions'>
				<button type="submit" class="onethird-button">更新</button>
			</div>
		</div>
	</form>
EOT;
	return $buff;

}

function tab_option1( $ar, $m ,$p_tab )
{
	global $p_circle,$params,$html,$database,$config,$ut;
	
	tab_option1_ctrl();
	
	read_circledata($ar);

	$buff = '';

	//トップページの取得
	$toppage = '';
	$p_taglist='';
	$ar2 = $database->sql_select_all("select id from ".DBX."data_items where mode=2 and circle=? ",$p_circle);
	if ($ar2) {
		$toppage = $ar2[0]['id'];
	}
	
	if (isset($params['circle']['meta']['taglist'])) {
		$p_taglist = $params['circle']['meta']['taglist'];
	} else {
		$p_taglist = '';
	}
	if (isset($params['circle']['meta']['toppage_tpl'])) {
		$toppage_tpl = $params['circle']['meta']['toppage_tpl'];
	} else {
		$toppage_tpl = '';
	}
	if (!empty($params['circle']['meta']['def_tmplate'])) {
		$p_deftmp = $params['circle']['meta']['def_tmplate'];
	} else {
		$p_deftmp = '';
	}
	if (!empty($params['circle']['meta']['jquery_url'])) {
		$p_jquery_url = $params['circle']['meta']['jquery_url'];
	} else {
		$p_jquery_url = '';
	}
	if (!empty($params['circle']['meta']['jqueryui_url'])) {
		$p_jqueryui_url = $params['circle']['meta']['jqueryui_url'];
	} else {
		$p_jqueryui_url = '';
	}
	if (!empty($params['circle']['meta']['jqueryuicss_url'])) {
		$p_jqueryuicss_url = $params['circle']['meta']['jqueryuicss_url'];
	} else {
		$p_jqueryuicss_url = '';
	}

$buff.= <<<EOT
	<form method='post' id='form0' action='{$params['safe_request']}' >
		<input type='hidden' name='mdx' id='mdx' value='mod_option1' />
		<div class='onethird-setting'>
			<table>
				<tr>
					<td>サイトナンバー</td>
					<td>
						<input type='text' id='p_sitenum' name='p_sitenum' value='$p_circle' />
					</td>
				</tr>
				<tr>
					<td>トップページ</td>
					<td>
						<input type='text' id='p_toppage' name='p_toppage' value='$toppage' />
					</td>
				</tr>
				<tr>
					<td>タグセレクター</td>
					<td>
						<input type='text' id='p_taglist' name='p_taglist' value='$p_taglist' />
					</td>
				</tr>

EOT;
				$op = '';
				$path = $config["files_path"].DIRECTORY_SEPARATOR.$p_circle.DIRECTORY_SEPARATOR.'data';
				$ar = @glob($path.DIRECTORY_SEPARATOR.'*.tpl');
				$n = false;
				foreach ($ar as $v) {
					$path_parts = pathinfo($v);
					$l = $path_parts['filename'];
					if (addslashes($l) != $l) { continue; }
					$hnd = @fopen($v, "r");
					if ($hnd) {
						for ($i=0; $i < 5;++$i) {
							$ln = fgets($hnd, 100);
							if ( $ln === false) { break; }
							if (preg_match("/[ \\t]name[ :]*([^-]*)[-]*>$/mu", $ln, $m)) {
								if (isset($m[1])) {
									$n = trim($m[1]);
								}
								break;
							}
						}
						fclose($hnd);
					}
					if ($n) {
						$op.="<option value='{$l}'>[{$n}]</option>";
						$n = false;
					} else {
						$op.="<option value='{$l}'>{$l}.tpl</option>";
					}
				}
$buff.= <<<EOT

				<tr>
					<td>トップページテンプレート</td>
					<td>
						<input type='text' id='p_toppage_tpl' name='p_toppage_tpl' value='$toppage_tpl' style='width:43%' />
						<select  style='width:43%' onchange='sel_template(this)' />
							<option value=''>--</option>
							$op
						</select>
					</td>
				</tr>
				<tr>
					<td>Default template</td>
					<td>
						<input type='text' id='p_deftmp' name='p_deftmp' value='$p_deftmp' placeholder='default.tpl' style='width:43%' />
						<select  style='width:43%' onchange='sel_template(this)' />
							<option value=''>--</option>
							$op
						</select>
					</td>
				</tr>
				<tr>
					<td>jquery url</td>
					<td>
						<input type='text' id='p_deftmp' name='p_jquery_url' value='$p_jquery_url' placeholder="ex. js/jquery.js , .plugin/jquery.js" />
					</td>
				</tr>
				<tr>
					<td>jquery ui url</td>
					<td>
						<input type='text' id='p_deftmp' name='p_jqueryui_url' value='$p_jqueryui_url' placeholder="ex. js/jquery-ui.js , .plugin/jquery-ui.js" />
					</td>
				</tr>
				<tr>
					<td>jquery ui css url</td>
					<td>
						<input type='text' id='p_deftmp' name='p_jqueryuicss_url' value='$p_jqueryuicss_url' placeholder="ex. js/jquery-ui.css , .plugin/jquery-ui.css" />
					</td>
				</tr>
			</table>
			<div class="actions"> 
				<button type="submit" class="onethird-button">更新</button>
			</div> 
		</div>
	</form>
EOT;

$html['meta']['tab_option1'] = <<<EOT
	<script>
		function sel_template(obj) {
			var o = \$(obj);
			o.prev().val(o.val());
		}
	</script>
EOT;

	return $buff;

}
function tab_option1_ctrl()
{
	global $p_circle;
	global $params,$database,$config,$html;
	
	if (!check_rights('admin')) {
		return;
	}
	if (isset($_POST['mdx']) && $_POST['mdx']=='mod_option1' && isset($_POST['p_toppage'])) {
	
		$p_toppage = (int)$_POST['p_toppage'];
		$p_sitenum = (int)$_POST['p_sitenum'];
		if ($p_sitenum != $p_circle) {
			$ok = true;
			$ar2 = $database->sql_select_all("select id from ".DBX."circles where id=? ", $p_sitenum);
			if ($ar2) {
				$ok = false;
			}
			$ar2 = $database->sql_select_all("select id from ".DBX."joined_circle where circle=? ", $p_sitenum);
			if ($ar2) {
				$ok = false;
			}
			if ($ok) {
				$path_now = $config['files_path'].DIRECTORY_SEPARATOR.$p_circle;
				$path_new = $config['files_path'].DIRECTORY_SEPARATOR.$p_sitenum;
				if (@rename($path_now, $path_new)) {
					$database->sql_begin();
					$ok = true;
					if (!$database->sql_update( "update ".DBX."circles set id=? where id=? ",$p_sitenum, $p_circle)) {
						$ok = false;
					}
					if (!$database->sql_update( "update ".DBX."data_items set circle=? where circle=? ",$p_sitenum, $p_circle)) {
						//$ok = false;
					}
					if (!$database->sql_update( "update ".DBX."joined_circle set circle=? where circle=? ",$p_sitenum, $p_circle)) {
						$ok = false;
					}
					if (!$database->sql_update( "update ".DBX."storage set circle=? where circle=? ",$p_sitenum, $p_circle)) {
						//$ok = false;
					}
					if (!$database->sql_update( "update ".DBX."user_log set circle=? where circle=? ",$p_sitenum, $p_circle)) {
						//$ok = false;
					}
					if (!$database->sql_update( "update ".DBX."user_log set circle=? where circle=? ",$p_sitenum, $p_circle)) {
						//$ok = false;
					}
					if (!$database->sql_update( "update ".DBX."action_log set circle=? where circle=? ",$p_sitenum, $p_circle)) {
						//$ok = false;
					}
					if (!$ok) {
						$database->sql_rollback();
						@rename($path_new, $path_now);
						header("Location:{$params['safe_request']}");
					} else {
						$database->sql_commit();
						if ($config['default_circle'] == $p_circle) {
$html['meta'][] = <<<EOT
							<script>
								\$(function(){
									alert('デフォルトサイトのサイト番号を変更しました、config.php内の default_circle値を{$p_sitenum}に変更してください');
									location.href='{$config['site_url']}$p_sitenum';
								});
							</script>
EOT;
						} else {
$html['meta'][] = <<<EOT
							<script>
								\$(function(){
									alert('サイト番号を変更しました、トップページに移動します');
									location.href='{$config['site_url']}$p_sitenum';
								});
							</script>
EOT;
						}
						$config['default_circle'] = $p_circle = $p_sitenum;
						$params['circle']['files_url'] = $config['files_url'].$p_circle;
						$params['circle']['files_path'] = $config['files_path'].DIRECTORY_SEPARATOR.$p_circle;
						
					}
				} else {
					$html['alert'][] = "Failure to create a folder that already exists.";
				}
			} else {
				$html['alert'][] = "Site ID {$p_sitenum} is already in use.";
			}
		}

		if ($database->sql_update( "update ".DBX."data_items set mode=1 where mode=2 and circle=?", $p_circle)) {
		}
		if ($database->sql_update( "update ".DBX."data_items set mode=2 where id=?", $p_toppage)) {
		}

		if (!empty($_POST['p_toppage_tpl']) && $_POST['p_toppage_tpl']) {
			$params['circle']['meta']['toppage_tpl'] = sanitize_asc($_POST['p_toppage_tpl']);
		} else {
			unset( $params['circle']['meta']['toppage_tpl'] );
		}
		if (!empty($_POST['p_deftmp'])) {
			$params['circle']['meta']['def_tmplate']=sanitize_path($_POST['p_deftmp']);
		} else {
			unset( $params['circle']['meta']['def_tmplate']);
		}
		if (!empty($_POST['p_jquery_url'])) {
			$params['circle']['meta']['jquery_url']=sanitize_path($_POST['p_jquery_url']);
		} else {
			unset( $params['circle']['meta']['jquery_url']);
		}
		if (!empty($_POST['p_jqueryui_url'])) {
			$params['circle']['meta']['jqueryui_url']=sanitize_path($_POST['p_jqueryui_url']);
		} else {
			unset( $params['circle']['meta']['jqueryui_url']);
		}
		if (!empty($_POST['p_jqueryuicss_url'])) {
			$params['circle']['meta']['jqueryuicss_url']=sanitize_path($_POST['p_jqueryuicss_url']);
		} else {
			unset( $params['circle']['meta']['jqueryuicss_url']);
		}
		if (isset($_POST['p_taglist'])) {
			$v = sanitize_str($_POST['p_taglist']);
			$v = preg_replace('/(,|、|　|，| )+/mu', ',', $v);
			//$v = explode(',', $v);
			$params['circle']['meta']['taglist'] = $v;
		} else {
			unset( $params['circle']['meta']['taglist'] );
		}
		$params['top_page'] = $p_toppage;
		$params['circle']['meta']['top_page']=$p_toppage;
		
		if ($database->sql_update( "update ".DBX."circles set metadata=? where id=?", serialize64($params['circle']['meta']), $p_circle)) {
		}
	}

}
function tab_option2( $ar, $m ,$p_tab )
{
	global $p_circle,$params,$html,$database,$ut;
	
	if (isset($_POST['mdx']) && $_POST['mdx'] == 'mod_option2') {
	
		if (isset($_POST['submit_add']) && isset($_POST['alias_name']) && isset($_POST['alias_id']) && $_POST['alias_name'] && (int)$_POST['alias_id']) {

			$n = sanitize_str($_POST['alias_name']);
			$i = (int)$_POST['alias_id'];
			
			$params['circle']['meta']['alias'][$n] = $i;
			
		} else if (isset($_POST['submit_del']) && isset($_POST['p_alias'])) {
			unset($params['circle']['meta']['alias'][sanitize_asc($_POST['p_alias'])]);
			
		} else {
			if (isset($_POST['p_site_error']) && (int)$_POST['p_site_error']) {
				$params['circle']['meta']['site_error'] = (int)$_POST['p_site_error'];
			} else {
				unset($params['circle']['meta']['site_error']);
			}
			
		}

		if ($database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($params['circle']['meta']), $p_circle)) {
		}
	}

	if ( isset($_POST['ajax']) && $_POST['ajax'] == 'del_alias' ) {
		$r = array();
		$r['result'] = false;
		$n = sanitize_str($_POST['data']);
		$r['n'] = explode(':', $n);
		if (isset($r['n'][0])) {
			unset($params['circle']['meta']['alias'][$r['n'][0]]);
			if (isset($r['n'][1])) {
				foreach ($params['circle']['meta']['alias'] as $k=>$v) {
					if ($r['n'][1] == $v) {
						unset($params['circle']['meta']['alias'][$k]);
					}
				}
			}
			if ($database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($params['circle']['meta']), $p_circle)) {
				$r['result'] = true;
			}
		}
		echo( json_encode($r) );
		exit();
	}
	
	if ( isset($_POST['ajax']) && $_POST['ajax'] == 'del_startup_script' ) {
		$r = array();
		$r['result'] = false;
		$n = sanitize_str($_POST['data']);
		$r['n'] = explode(':', $n);
		if (isset($r['n'][0])) {
			$m = get_circle_meta('startup_script');
			unset($m[$r['n'][0]]);
			$r['result'] = set_circle_meta('startup_script',$m);
		}
		echo( json_encode($r) );
		exit();
	}

$html['meta'][] = <<<EOT
<script>
function del_alias() {
	var a = \$('#p_alias option:selected').text();
	if (a) {
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=del_alias&data="+a
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					\$('#p_alias option:selected').remove();
				}
			}
		});
	} else {
		alert("アイテムを選択してください");
	}
}

function del_startup_script() {
	var a = \$('#p_startup option:selected').text();
	if (a) {
		ot.ajax({
			type: "POST"
			, url: '{$params['safe_request']}'
			, data: "ajax=del_startup_script&data="+a
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					\$('#p_startup option:selected').remove();
				}
			}
		});
	} else {
		alert("アイテムを選択してください");
	}
}
</script>
EOT;
	
	read_circledata($ar);

	$buff = '';

	if (isset($params['circle']['meta']['site_error'])) {
		$p_site_error = $params['circle']['meta']['site_error'];
	} else {
		$p_site_error = 0;
	}

	

$buff.= <<<EOT
	<form method='post' id='form0' action='{$params['safe_request']}' >
		<input type='hidden' name='mdx' id='mdx' value='mod_option2' />
		<div class='onethird-setting'>
			<table>
				<tr>
					<td>ページエラー</td>
					<td>
						<select id='p_site_error' name='p_site_error' >
EOT;
							$tmp = array();
							$tmp[] = array(0, 'トップ表示');
							$tmp[] = array(2, '200 not found');
							$tmp[] = array(3, '301 リダイレクト');
							$tmp[] = array(4, '404 not found');
							foreach ($tmp as $v) {
								if ($v[0] == $p_site_error) {
									$c = ' selected ';
								} else {
									$c = '';
								}
$buff.= <<<EOT
								<option value='{$v[0]}' $c>{$v[1]}</option>
EOT;
							}
$buff.= <<<EOT
						</select>
					</td>
				</tr>
				<tr>
					<td>URLエイリアス</td>
					<td>
						<select id='p_alias' name='p_alias' size=4 >
EOT;
						if (isset($params['circle']['meta']['alias'])) {
							foreach ($params['circle']['meta']['alias'] as $k=>$v) {
								if (is_array($v)) { continue; }
$buff.= <<<EOT
								<option value='$k'>$k:$v</option>
EOT;
							}
						}
$buff.= <<<EOT
						</select> 
						<p>
							<input type='text' name='alias_name' style='width:120px' />
							<input type='text' name='alias_id' style='width:120px' />
						</p>
						<p>
							<input type='submit' name='submit_add' value='Add' class='onethird-button mini' />
							<input type='button' name='submit_del' value='Remove' onclick='del_alias()' class='onethird-button mini' />
						</p>
					</td>
				</tr>
				<tr>
					<td>Startup script</td>
					<td>
						<select id='p_startup' name='p_startup' size=4 >
EOT;
						if (isset($params['circle']['meta']['data']['startup_script'])) {
							foreach ($params['circle']['meta']['data']['startup_script'] as $k=>$v) {
$buff.= <<<EOT
								<option value='$k'>$k:$v</option>
EOT;
							}
						}
$buff.= <<<EOT
						</select> 
						<p>
							<input type='button' name='submit_del' value='Remove' onclick='del_startup_script()' class='onethird-button mini' />
						</p>
					</td>
				</tr>
			</table>
			<div class="actions"> 
				<button type="submit" class="onethird-button">更新</button>
			</div> 
		</div>
	</form>
EOT;
	return $buff;

}

function tab_option3( $ar, $m ,$p_tab )
{
	global $p_circle,$params,$html,$database,$ut;
	
	if (isset($_POST['mdx']) && $_POST['mdx'] == 'mod_option3') {
	
		if (isset($_POST['p_under_construction']) && $_POST['p_under_construction']) {
			$params['circle']['meta']['under_construction'] = sanitize_str($_POST['p_under_construction']);
		} else {
			unset($params['circle']['meta']['under_construction']);
		}

		if ($database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($params['circle']['meta']), $p_circle)) {
		}
	}
	
	read_circledata($ar);

	$buff = '';
	if (isset($params['circle']['meta']['under_construction'])) {
		$p_under_construction = $params['circle']['meta']['under_construction'];
	} else {
		$p_under_construction = get_under_construction_defmess();
	}

$buff.= <<<EOT
	<form method='post' id='form0' action='{$params['safe_request']}' >
		<input type='hidden' name='mdx' id='mdx' value='mod_option3' />
		<div class='onethird-setting'>
			<table>
				<tr>
					<td>メンテナンス表示</td>
					<td>
						<textarea id='p_under_construction' name='p_under_construction' rows='10' cols='40' >$p_under_construction</textarea>
					</td>
				</tr>
			</table>
			<div class="actions"> 
				<button type="submit" class="onethird-button">更新</button>
			</div> 
		</div>
	</form>
EOT;
	return $buff;

}

function tab_option4( $ar, $m ,$p_tab )
{
	global $p_circle,$params,$html,$database,$ut;
	
	if (isset($_POST['mdx']) && $_POST['mdx'] == 'mod_option3') {
	
		if (isset($_POST['p_login_write'])) {
			$params['circle']['meta']['login_write']=1;
		} else {
			unset( $params['circle']['meta']['login_write'] );
		}

		if (isset($_POST['p_login_alert'])) {
			$params['circle']['meta']['login_alert']=1;
		} else {
			unset( $params['circle']['meta']['login_alert'] );
		}

		if (isset($_POST['p_login_all'])) {
			$params['circle']['meta']['login_all']=1;
		} else {
			unset( $params['circle']['meta']['login_all'] );
		}

		if (isset($_POST['p_https_force'])) {
			$params['circle']['meta']['https_force'] = 1;
		} else {
			unset( $params['circle']['meta']['https_force'] );
		}

		if (isset($_POST['p_notsave_cookie'])) {
			$params['circle']['meta']['notsave_cookie']=1;
		} else {
			unset( $params['circle']['meta']['notsave_cookie'] );
		}

		if (isset($_POST['p_use_loginip'])) {
			$params['circle']['meta']['use_loginip']=1;
		} else {
			unset( $params['circle']['meta']['use_loginip'] );
		}

		if (isset($_POST['p_allow_upload'])) {
			$params['circle']['meta']['allow_upload']=1;
		} else {
			unset( $params['circle']['meta']['allow_upload'] );
		}

		if (isset($_POST['p_upload_nonfilter'])) {
			$params['circle']['meta']['upload_nonfilter']=1;
		} else {
			unset( $params['circle']['meta']['upload_nonfilter'] );
		}

		if (isset($_POST['p_upload_plugin'])) {
			$params['circle']['meta']['upload_plugin']=1;
		} else {
			unset( $params['circle']['meta']['upload_plugin'] );
		}

		if (isset($_POST['p_upload_site'])) {
			$params['circle']['meta']['upload_site']=1;
		} else {
			unset( $params['circle']['meta']['upload_site'] );
		}

		if (isset($_POST['p_mult_login'])) {
			$params['circle']['meta']['mult_login']=1;
		} else {
			unset( $params['circle']['meta']['mult_login'] );
		}

		if (isset($_POST['p_dis_newacc'])) {
			$params['circle']['meta']['dis_newacc']=1;
		} else {
			unset( $params['circle']['meta']['dis_newacc'] );
		}

		if ($database->sql_update("update ".DBX."circles set metadata=? where id=?", serialize64($params['circle']['meta']), $p_circle)) {
		}
	}
	
	read_circledata($ar);
	$buff = '';

	if (isset($params['circle']['meta']['login_write'])) {
		$login_write = ' checked ';
	} else {
		$login_write = '';
	}

	if (isset($params['circle']['meta']['login_alert'])) {
		$login_alert = ' checked ';
	} else {
		$login_alert = '';
	}

	if (isset($params['circle']['meta']['login_all'])) {
		$login_all = ' checked ';
	} else {
		$login_all = '';
	}

	if (isset($params['circle']['meta']['https_force'])) {
		$https_force = ' checked ';
	} else {
		$https_force = '';
	}

	if (isset($params['circle']['meta']['notsave_cookie'])) {
		$notsave_cookie = ' checked ';
	} else {
		$notsave_cookie = '';
	}

	if (isset($params['circle']['meta']['use_loginip'])) {
		$use_loginip = ' checked ';
	} else {
		$use_loginip = '';
	}

	if (isset($params['circle']['meta']['allow_upload'])) {
		$allow_upload = ' checked ';
	} else {
		$allow_upload = '';
	}

	if (isset($params['circle']['meta']['upload_nonfilter'])) {
		$upload_nonfilter = ' checked ';
	} else {
		$upload_nonfilter = '';
	}

	if (isset($params['circle']['meta']['upload_plugin'])) {
		$upload_plugin = ' checked ';
	} else {
		$upload_plugin = '';
	}

	if (isset($params['circle']['meta']['upload_site'])) {
		$upload_site = ' checked ';
	} else {
		$upload_site = '';
	}

	if (isset($params['circle']['meta']['mult_login'])) {
		$mult_login = ' checked ';
	} else {
		$mult_login = '';
	}

	if (isset($params['circle']['meta']['dis_newacc'])) {
		$dis_newacc = ' checked ';
	} else {
		$dis_newacc = '';
	}

$buff.= <<<EOT
	<form method='post' id='form0' action='{$params['safe_request']}' >
		<input type='hidden' name='mdx' id='mdx' value='mod_option3' />
		<div class='onethird-setting'>
			<table>
				<tr>
					<td>セキュリティオプション</td>
					<td>
						<ul>
							<li><label><input type='checkbox' id='p_allow_upload' name='p_allow_upload' $allow_upload  />1.一般ユーザーのアップローダー利用を許可する</label></li>
							<li><label><input type='checkbox' id='p_upload_nonfilter' name='p_upload_nonfilter' $upload_nonfilter  />2.アップロード可能フィルタを使用しない</label></li>
							<li><label><input type='checkbox' id='p_upload_plugin' name='p_upload_plugin' $upload_plugin  />3.アップローダーでpluginフォルダを表示する</label></li>
							<li><label><input type='checkbox' id='p_upload_site' name='p_upload_site' $upload_site  />4.アップローダーのシステムファイル書き換えを許可する</label></li>
							<li><label><input type='checkbox' id='p_mult_login' name='p_mult_login' $mult_login  />5.複数PCからのログインを許可する</label></li>
							<li><label><input type='checkbox' id='p_https_force' name='p_https_force' $https_force  />6.管理画面は全てHTTPSで通信する (要site_ssl設定) </label></li>
						</ul>
					</td>
				</tr>

				<tr>
					<td>ログインオプション</td>
					<td>
						<ul>
							<li><label><input type='checkbox' id='p_dis_newacc' name='p_dis_newacc' $dis_newacc  />10.新規アカウント作成を禁止する</label></li>
							<li><label><input type='checkbox' id='p_login_write' name='p_login_write' $login_write  />11.ログインユーザーを全てログに残す</label></li>
							<li><label><input type='checkbox' id='p_login_all' name='p_login_all' $login_all  />12.ログイン記録を全て管理者に送信する</label></li>
							<li><label><input type='checkbox' id='p_notsave_cookie' name='p_notsave_cookie' $notsave_cookie  />13.クッキーを再ログインに使わない</label></li>
							<li><label><input type='checkbox' id='p_use_loginip' name='p_use_loginip' $use_loginip  />14.クッキーにIPアドレスを記録する</label></li>
							<li><label><input type='checkbox' id='p_login_alert' name='p_login_alert' $login_alert  />15.アカウントロックをお知らせする</label></li>
						</ul>
					</td>
				</tr>

			</table>
			<div class="actions"> 
				<button type="submit" class="onethird-button">更新</button>
			</div> 
		</div>
	</form>
EOT;
	return $buff;

}


?>