<?php

define('VISITOR_DSIPLAY_MAX', 100);

function visitor_ip_renderer( &$page_ar )
{
	return frame_renderer(visitor_ip($page_ar));
}

function visitor_ip_page(&$page_ar )
{
	global $params,$database,$config,$html;
	global $p_circle,$html,$ut,$agent;
	
	if (!check_rights('edit')) {
		return '';
	}
	$buff = '';

$buff .= <<<EOT
	<div class='visitor_ip'>
		<h1> Access log</h1>
EOT;
		$t = DBX."user_log";
		$sql = "select count($t.id) as c, t2.id as link, $t.metadata as metadata
					, data, att, $t.id as id
					, t2.title as title, t2.mode as mode
					, {$ut->date_format("$t.date","'%m/%d %H:%i'")} as date
					from $t 
					left join ".DBX."data_items as t1 on t1.id=$t.link 
					left join ".DBX."data_items as t2 on t2.id=t1.link 
					";
		$sql .= " where $t.type=? and $t.status=0 ";
		$sql .= ' group by link ';
		$ar = $database->sql_select_all($sql." order by date desc limit ".VISITOR_DSIPLAY_MAX, VISITOR_IP_ID);
		if ($ar) {
			$r['result'] = true;
			foreach ($ar as $v) {
				if ($v['mode'] == 2) { $v['title'] = '[top page]'; }
$buff .= <<<EOT
				<p >
					<span>#<a href='{$ut->link($v['link'])}'>{$v['link']}</a> - {$v['title']} - {$v['c']} </span> 
				</p>
EOT;
			}
		}
$buff .= <<<EOT
	</div>
EOT;

	return frame_renderer($buff);
}

function visitor_ip(&$page_ar )
{
	global $params,$database,$config,$html;
	global $p_circle,$html,$ut,$agent,$plugin_ar;
	
	$parent_page = $params['page']['id'];
	$the_page = $page_ar['id'];
	$ip_addr = sanitize_asc($_SERVER["REMOTE_ADDR"]);
	$user = 0;
	$limit_mess = '';
	if (isset($_SESSION['login_id'])) {
		$user = $_SESSION['login_id'];
	}
	$ar = $database->sql_select_all("select count(id) as c from ".DBX."user_log where link=? and type=? ", $the_page, VISITOR_IP_ID );
	if ($ar && $ar[0]['c'] > 1000) {
		$limit_mess = '<p> - over limit - </p>';
	} else {
		$ar = $database->sql_select_all("select id,user,att,data,metadata from ".DBX."user_log where link=? and type=? and data=? and user=? limit 1", $the_page, VISITOR_IP_ID, $ip_addr, $user );
		if ($ar) {
			++$ar[0]['att'];
			$ar[0]['meta'] = unserialize64($ar[0]['metadata']);
			unset($ar[0]['metadata']);
			$ar[0]['date'] = $params['now'];
			if (isset($_SERVER["HTTP_REFERER"])) {
				$ar[0]['meta']['referer'] = adjust_mstring($_SERVER["HTTP_REFERER"],200);
			}
			mod_user_log($ar[0]);
			//if ($database->sql_update("update ".DBX."user_log set att=?, date=? where id=?", ++$ar[0]['att'], $params['now'] ,$ar[0]['id'])) {
			//}
		} else {
			$x = array();
			$x['link'] = $the_page;
			$x['user'] = $user;
			$x['type'] = VISITOR_IP_ID;
			$x['data'] = $ip_addr;	
			$x['att'] = 1;				// pv countとして利用
			if (substr($agent,0,3) == 'bot') {
				$x['status'] = 1;	// botはカウントしない仕様に変更 < v1.23b
			} else {
				if ($agent) {
					$x['metadata']['agent_type'] = $agent;
				}
				if (isset($_SERVER["HTTP_REFERER"])) {
					$x['metadata']['referer'] = adjust_mstring($_SERVER["HTTP_REFERER"],200);
				}
				$x['metadata']['agent'] = adjust_mstring($_SERVER['HTTP_USER_AGENT'],200);
				if (add_user_log($x)) {
				}
			}
		}
	}
	if (!check_rights('edit')) {
		return '';
	}
	
	$buff = '';

$buff .= <<<EOT
	<div class='visitor_ip'>
		<p style='font-weight:bold;border-bottom: 1px solid #929292;'> <a href='{$ut->link($plugin_ar[VISITOR_IP_ID]['url'])}'>Access log</a></p>
		$limit_mess
EOT;
		$t = DBX."user_log";
		$ar = $database->sql_select_all("select $t.metadata as metadata, data, att, $t.id as id, t1.name as name, t1.nickname as nickname, $t.user as user, {$ut->date_format("$t.date","'%m/%d %H:%i'")} as date,t1.img as img from $t left join ".DBX."users as t1 on t1.id=$t.user where $t.link=? and $t.type=? order by date desc limit ".VISITOR_DSIPLAY_MAX, $the_page, VISITOR_IP_ID);
		if ($ar) {
			$r['result'] = true;
			foreach ($ar as $v) {
				$color_style = $base_color_style = '';
				$name = $v['name'];
				$m = unserialize64($v['metadata']);
				if ($v['user'] == 0) {
					if (isset($m['agent_type'])) {
						$name = $m['agent_type'];
						if (substr($name,0,3) == 'bot') {
							$base_color_style = " color:#8D8D8D;";
						} else {
							$color_style = "color:#703A3A;font-weight:bold;";
						}
					} else {
						$name = "guest";
						$color_style = "color:#703A3A;font-weight:bold;";
					}
				}
				$agent = '';
				$referer = '';
				if (isset($m['agent'])) {
					$agent = "<span class='agent' title='{$ut->safe_echo($m['agent'])}'>[?]</span> ";
				}
				if (isset($m['referer'])) {
					$z = $ut->safe_echo($m['referer']);
					$z = urldecode($z);
					$z = adjust_mstring($z,100);
					$referer = "<span class='referer' style='$color_style' >-- $z</span> ";
				}
				
$buff .= <<<EOT
				<p style='$base_color_style'>
					<span>[{$v['date']}] $name - {$v['data']} ({$v['att']}) </span> 
					$referer
					$agent
				</p>
EOT;
			}
		}
$buff .= <<<EOT
	</div>
EOT;

	if ( check_rights('owner')) {
$buff .= <<<EOT
		<div class='edit_pointer'>
EOT;
			$buff .= std_blockmenu_renderer($page_ar);
$buff .= <<<EOT
		</div>
EOT;
	}

	if (!isset($html['css']['plugin-visitor_ip'])) {
$html['css']['plugin-visitor_ip'] = <<<EOT
		<style>
			.visitor_ip {
				font-size: 13px;
			}
			.visitor_ip .agent {
				font-size: 10px;
				color: #8D8D8D;
			}
			.visitor_ip .referer {
				font-size: 13px;
			}
		</style>
EOT;
	}
	return $buff;

}
?>