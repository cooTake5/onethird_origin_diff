<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.

	global $p_registration,$request_uri,$otoken,$login_id,$params;

	define('MAX_LIST',100);

	require_once(dirname(__FILE__).'/../config.php');
	require_once(dirname(__FILE__).'/../module/utility.basic.php');

	basic_initialize();

	if (!check_rights('admin')) {
		exit_proc(403, 'Need administrator rights');
	}
	
	snippet_overlay();
	avoid_attack();
	snippet_avoid_robots();

	$params['manager'] = 'actionlog';
	$params['template'] = 'admin.tpl';

	if (!isset($params['circle'])) {
		system_error( __FILE__, __LINE__ );
	}

	// 標準プラグイン/テーマプラグイン読み込み
	load_site_plugin();

	//パンくず表示
	$u = "{$params['request_name']}?circle=$p_circle";
	$params['breadcrumb'][] = array( 'link'=>"$u", 'text'=>'Action log' );

	$html['article'][] = draw_circle_action_log();

	snippet_header();
	snippet_system_nav();
	snippet_footer();

	expand_circle_html();

function draw_circle_action_log()
{
	global $p_circle ;
	global $database,$params,$config,$html,$ut;

	snippet_avoid_robots();

	$params['template'] = 'admin.tpl';

	$total = $offset = 0;
	if (isset($_GET['offset'])) {
		$offset = (int)$_GET['offset'];
	}
	if (!empty($_POST['keyword'])) {
		$keyword = sanitize_str($_POST['keyword']);
	} else {
		$keyword = '';
	}

	$buff = '';
	
	if ( !check_rights('admin') ) {
		return;
	}

	if ( !isset($params['circle']) ) {
		system_error( __FILE__, __LINE__ );
	}

$html['css']['actionlog'] = <<<EOT
	<style>
		#action_list {
			background-color: #fff;
			color: #222;
			padding:10px 10px 20px 10px;
		}
		#action_list form {
			margin-bottom:10px;
		}
		#action_list input[type='text'] {
			width:100%;
		}
		#action_list pre {
			border:none;
			word-break: break-all;
		}
		.onethird-frame {
			margin-bottom:20px;
		}
	</style>
EOT;

$html['meta']['actionlog'] = <<<EOT
	<script>
		function remove_action(id) {
			if (confirm('Are you sure you want to delete this?')) {
				ot.ajax({
					type: "POST"
					, url: '{$params['request_name']}'
					, data: "ajax=remove_action&id="+id
					, dataType:'json'
					, success: function(data){
						if (data && data['result'] ) {
							\$('#item_'+data['id']).fadeOut(500);
						} else {
							alert('Failed to load');
						}
					}
				});
			}
		}
	</script>
EOT;

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'remove_action')  {
		$r = array();
		$r['result'] = false;
		if (isset($_POST['id'])) {
			$r['id'] = (int)$_POST['id'];
			$r['result'] = $database->sql_update("delete from ".DBX."action_log where circle=? and id=?", $p_circle, (int)$r['id']);
		}
		echo( json_encode($r) );
		exit();
	}

	$sql = array();
	$sql[0] = "select count(id) as c from ".DBX."action_log";	

	$opt = '';
	if (!empty($_POST['submit']) && !empty($_POST['keyword'])) {
		if (!empty($_POST['keyword'])) {
			$sql[1] = array(" where circle=? and data like ? ",$p_circle, '%'.sanitize_str($_POST['keyword']).'%');
		}
		if ($_POST['submit'] == 'remove all filtered') {
			$database->sql_update("delete from ".DBX."action_log where circle=? and data like ?", $p_circle, '%'.sanitize_str($_POST['keyword']).'%');
		}
	}
	
	$ar = $database->sql_select_all($sql);

	if ($ar) {
		$total = $ar[0]['c'];
	}
	
	$option = array();
	$option['total'] = $total;
	$option['offset'] = $offset;
	$option['page_size'] = MAX_LIST;
	$option['url'] = $params['request'];

	$sql[0] = "select id,date,read_date,data from ".DBX."action_log ";
	$sql[] = array("order by date desc {$ut->limit($offset*$option['page_size'],MAX_LIST)} ");

	$ar = $database->sql_select_all( $sql );

	$buff .= std_pagination_renderer($option);

$buff .= <<<EOT
	<div id='action_list'>
		<p class='item_count'>
			$total items
		</p>

		<form method='post' action='{$params['safe_request']}'  >
			<input type='text' name='keyword' value='{$ut->safe_echo($keyword)}' />
			<input type='submit' value='filter' name='submit' class='onethird-button mini' />
			<a href='{$config['site_url']}{$config['admin_dir']}/actionlog.php?circle=$p_circle' class='onethird-button mini'>clear filter</a>
			<input type='submit' value='remove all filtered' name='submit' class='onethird-button mini' />
		</form>

		<table  class='onethird-table ' >
			<tr>
				<th>Date</th><th>Contents</th>
			</tr>
EOT;
			if ($ar) {
				foreach ($ar as $v) {
					if ( $v['read_date'] == '0000-00-00 00:00:00' ) {
						$v['read_date'] = '';
					}
					$m = $v['data'];
					$m = preg_replace("/(\r\n){2,}|\r{2,}|\n{2,}/","\n",$m);
					$m = $ut->safe_echo(adjust_mstring($m, 300));

					if ($v['read_date']) {
						$m = "<br />read date{$v['read_date']}";
					}
$buff .=  <<<EOT
					<tr data-id='{$v['id']}' id='item_{$v['id']}'>
						<td >{$v['date']}<a href='javascript:void(remove_action({$v['id']}))' >{$ut->icon('delete')}</a></td>
						<td><pre>$m</pre>
							
						</td>
					</tr>
EOT;
				}
			}
$buff .= <<<EOT
		</table>
	</div>
EOT;

	return frame_renderer($buff);
}

?>