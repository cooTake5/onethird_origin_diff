<?php

define('MAX_ATOM_ITEM',20);

function atom_xml_page(&$page_ar)
{
	global $database,$params,$config,$p_circle,$html,$ut, $plugin_ar;

	if (!read_pagedata($params['circle']['meta']['top_page'], $params['page'])) {
		exit_proc(404, 'Top page not found');
	}

	$buff = '';
	$description = '';
	if (isset($params['page']['meta']['description'])) {
		$description = $params['page']['meta']['description'];
	}
	$type_opt = " and type=".TOPIC_ITEM_ID." ";;
	if (isset($plugin_ar[RSS_ID]['type'])) {
		$ar = $plugin_ar[RSS_ID]['type'];
		if (is_array($ar)) {
			$type_opt = "";
			foreach ($ar as $v) {
				if ($type_opt) { $type_opt .= " or "; }
				$type_opt .= " type = ".(int)$v." ";
			}
			$type_opt = " and ($type_opt) ";
		} else {
			$type_opt = " and type=".(int)$ar." ";
		}
	}
	
	$limit = MAX_ATOM_ITEM;
	if (isset($plugin_ar[RSS_ID]['limit'])) {
		$limit = (int)$plugin_ar[RSS_ID]['limit'];
	}
	
	$author = 'admin';
	if (isset($plugin_ar[RSS_ID]['author'])) {
		$author = safe_echo($plugin_ar[RSS_ID]['author']);
	}
	$sql = array();
	$sql[] = "select contents,id,link,title,type,block_type,mod_date,date from ".DBX."data_items ";
	$sql[] = array(" where circle=? ", $p_circle);
	$sql[] = array(" and mode<>2 and mode=1 and block_type<>5 and block_type < 19 and block_type <> 0 and type<> ?", HIDDEN_ID);
	$sql[] = array(" order by mod_date desc limit ?", $limit);
	$ar = $database->sql_select_all( $sql );

	header("Content-Type: text/xml; charset=utf-8"); 

$buff .= <<<EOT
<?xml version="1.0" encoding="utf-8" ?>
<feed
 xmlns="http://www.w3.org/2005/Atom"
 xmlns:thr="http://purl.org/syndication/thread/1.0"
 xml:lang="ja"
 >
<title>{$params['circle']['name']}</title>
<link rel="alternate" type="text/html" href="{$ut->link()}"/>
<link rel="self" type="application/atom+xml" href="{$params['request']}" />
<link rel="hub" href="http://pubsubhubbub.appspot.com" />
EOT;
	if ($description) {
$buff .= <<<EOT

<subtitle>$description</subtitle>
EOT;
	}
	$update = 0;
    foreach ($ar as $v) {
		if ($update < strtotime($v['mod_date'])) {
			$update = strtotime($v['mod_date']);
		}
	}
	$date = date("Y-m-d\\TH:i:sP", $update);
	$url = parse_url($config['site_url']);
$buff .= <<<EOT

<updated>$date</updated>
<author>
  <name>$author</name>
</author>
<id>{$ut->link()}</id>
<link href="{$ut->link()}" />

EOT;

    foreach ($ar as $v) {
		$title = $v['title'];
		if (!$title) {
			continue;
		}
		$u = $ut->link($v['id']);
		$content = $v['contents'];
		$content = preg_replace("/\\x7B(\\$.*?)\\x7D/su",'',$content);
		expand_buff($content);
		$content = adjust_mstring( strip_tags($content), 1000 );
		$mod_date = date("Y-m-d\\TH:i:sP", strtotime($v['mod_date']));
		$date = date("Y-m-d\\TH:i:sP", strtotime($v['date']));
		$date_id = date("Y-m-d", strtotime($v['mod_date']));
$buff .= <<<EOT
<entry>
<title>{$title}</title>
<id>{$u}</id>
<link rel="alternate" type="text/html" href="{$u}" />
<content type='text/html'>
<![CDATA[
{$content}
]]>
</content>
<published>$date</published>
<updated>$mod_date</updated>
</entry>\r\n
EOT;

	}

$buff .= <<<EOT
</feed>
EOT;

	echo $buff;
	exit();

}


?>