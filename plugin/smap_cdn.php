<?php
/*	open street map
	name : open street map
	author: team1/3
*/

function map_renderer(&$page_ar)
{
	global $database,$p_circle,$params,$html,$config,$ut;
	$buff = '';
	
	/*
$html['meta'][] = <<<EOT
 <link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet/v0.7.7/leaflet.css" />
 <script src="http://cdn.leafletjs.com/leaflet/v0.7.7/leaflet.js"></script>
EOT;
	*/

$html['meta'][] = <<<EOT
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/leaflet.css" />
 <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/leaflet.js"></script>
EOT;

	$r = array('page'=>$page_ar['id'],'name'=>'map','no-edit'=>true);
	$edit = flexedit($r);
	$edit = str_replace("\n", '', $edit);
	$edit = str_replace("\r", '', $edit);
	$edit = str_replace("\"", "'", $edit);
	$p_map_center = $p_map_title = $p_map_marker = 0;
	$p_map_zoom = 14;

	if (check_rights('edit')) {
		snippet_dialog();
		snippet_inpage_edit();
	}
	
	if (isset($page_ar['meta']['map_page']['p_map_title'])) { $p_map_title = $page_ar['meta']['map_page']['p_map_title']; }
	if (isset($page_ar['meta']['map_page']['p_map_center'])){
		$p_map_center = $page_ar['meta']['map_page']['p_map_center'];
	} else {
		$p_map_center = '33.59169,130.398508';
	}
	if (isset($page_ar['meta']['map_page']['p_map_marker'])) {
		$p_map_marker = $page_ar['meta']['map_page']['p_map_marker']; 
	} else {
		$p_map_marker = '33.589196,130.399452';
	}
	if (isset($page_ar['meta']['map_page']['p_map_zoom'])) { $p_map_zoom = $page_ar['meta']['map_page']['p_map_zoom']; }
	if (isset($page_ar['meta']['map_page']['p_map_x'])) {
		$p_map_x = $page_ar['meta']['map_page']['p_map_x'];
	} else {
		$p_map_x = '100%';
	}
	if (isset($page_ar['meta']['map_page']['p_map_y'])) {
		$p_map_y = $page_ar['meta']['map_page']['p_map_y'];
	} else {
		$p_map_y = '400px';
	}

$tmp = <<<EOT
	<script>
		\$(function(){
			var map = L.map('map',{
				center:[{$p_map_center}]
				,zoom:$p_map_zoom
				, scrollWheelZoom:false
			});

			L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
			    attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
			}).addTo(map);
			L.marker([{$p_map_marker}]
					,{
						title:"$p_map_title"
					}
				).addTo(map)
EOT;
				if ($edit) {
$tmp .= <<<EOT
			    .bindPopup("{$edit}")
			    .openPopup()
EOT;
				}
$tmp .= <<<EOT
				;
		});
	</script>
EOT;
$html['meta'][] = $tmp;


	if (check_rights('edit')) {
$buff .= <<<EOT
		<div class='edit_pointer'>
EOT;
			$buff .= std_blockmenu_renderer($page_ar);
$buff .= <<<EOT
			<a href='javascript:void(ot.inpage_edit({"page":{$page_ar['id']}, "name":"map", "idx":""}))' class='onethird-button mini'>マーカーの編集</a>
			<a href='javascript:void(map_setting())' >{$ut->icon('setting')}</a>
		</div>
EOT;
	}

$buff .= <<<EOT
	<div id="map" class='map' style="width:$p_map_x; height:$p_map_y"></div>
	<a href='http://maps.google.co.jp/maps?f=q&amp;source=s_q&amp;hl=ja&amp;geocode=&amp;q={$p_map_marker}&amp;sll={$p_map_marker}'>
		 [ 大きい地図を開く ]
	</a>
EOT;

	if (check_rights('edit')) {
$tmp = <<<EOT
	<div id="map_setting" class='onethird-dialog' >
		<div class='title'>Setting</div>
		<div class='onethird-setting'>
			<table>
				<tr>
					<td>マップタイトル</td>
					<td><input type='text' name='p_map_title' id='p_map_title'  />
				</tr>
				<tr>
					<td>マップサイズ(x,y)</td>
					<td><input type='text' name='p_map_x' id='p_map_x' value='$p_map_x'  /><input type='text' name='p_map_y' id='p_map_y' value='$p_map_y' /> </td>
				</tr>
				<tr>
					<td>マップ中央の座標</td>
					<td><input type='text' name='p_map_center' id='p_map_center' />
						<div>ex. 33.588396,130.400091</div>
					</td>
				</tr>
				<tr>
					<td>マップマーカーの座標</td>
					<td><input type='text' name='p_map_marker' id='p_map_marker'  />
						<div>ex. 33.588396,130.400091</div>
					</td>
				</tr>
				<tr>
					<td>ズーム</td>
					<td><input type='number' name='p_map_zoom' id='p_map_zoom' value='$p_map_zoom' /> </td>
				</tr>
			</table>
		</div>
		<div class='actions' >
			<input type='button' class='onethird-button' value='OK' onclick='save_map_setting(this)' />
			<input type='button' class='onethird-button' value='キャンセル' onclick='ot.close_dialog(this)' />
		</div>
	</div>
<script>
	function map_setting() {
		ot.open_dialog(\$('#map_setting'));
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=map_setting"
			, dataType:'json'
			, success: function(data){
				if ( data && data['result'] ) {
					\$('#p_map_title').val(data['map_page']['p_map_title']);
					\$('#p_map_x').val(data['map_page']['p_map_x']);
					\$('#p_map_y').val(data['map_page']['p_map_y']);
					\$('#p_map_center').val(data['map_page']['p_map_center']);
					\$('#p_map_marker').val(data['map_page']['p_map_marker']);
					\$('#p_map_zoom').val(data['map_page']['p_map_zoom']);
				}
			}
		});
	}
	function save_map_setting(obj) {
		var a = '&p_map_title='+\$('#p_map_title').val();
		a += '&p_map_x='+\$('#p_map_x').val();
		a += '&p_map_y='+\$('#p_map_y').val();
		a += '&p_map_center='+\$('#p_map_center').val();
		a += '&p_map_marker='+\$('#p_map_marker').val();
		a += '&p_map_zoom='+\$('#p_map_zoom').val();
		ot.ajax({
			type: "POST"
			, url: '{$params['request_name']}'
			, data: "ajax=save_map_setting"+a
			, dataType:'json'
			, obj:obj
			, success: function(data){
				if ( data && data['result'] ) {
					ot.close_dialog('#map_setting');
				}
			}
		});
	}
</script>
EOT;
		$html['meta'][] = $tmp;
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'map_setting')  {
		$r = array();
		$r['result'] = false;
		$ar = $database->sql_select_all("select metadata from ".DBX."data_items where id=?", $page_ar['id']);
		if ($ar && $ar[0]['metadata']) {
			$m = unserialize64($ar[0]['metadata']);
			if (isset($m['map_page'])) {
				$r['result'] = true;
				$r['map_page'] = $m['map_page'];
			}
		}
		echo(json_encode($r));
		exit();
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] == 'save_map_setting')  {
		$r = array();
		$r['result'] = false;
		$ar = $database->sql_select_all("select metadata from ".DBX."data_items where id=?", $page_ar['id']);
		if ($ar && $ar[0]['metadata']) {
			$m = unserialize64($ar[0]['metadata']);
			if (isset($m['map_page'])) {
				unset($m['map_page']);
			}
			if (isset($_POST['p_map_title'])) { $m['map_page']['p_map_title'] = sanitize_str($_POST['p_map_title']); }
			if (isset($_POST['p_map_x'])) { $m['map_page']['p_map_x'] = sanitize_str($_POST['p_map_x']); }
			if (isset($_POST['p_map_y'])) { $m['map_page']['p_map_y'] = sanitize_str($_POST['p_map_y']); }
			if (isset($_POST['p_map_center'])) { $m['map_page']['p_map_center'] = sanitize_str($_POST['p_map_center']); }
			if (isset($_POST['p_map_marker'])) { $m['map_page']['p_map_marker'] = sanitize_str($_POST['p_map_marker']); }
			if (isset($_POST['p_map_zoom'])) { $m['map_page']['p_map_zoom'] = sanitize_str($_POST['p_map_zoom']); }

			if ($database->sql_update( "update ".DBX."data_items set metadata=? where id=?", serialize64($m), $page_ar['id'])) {
				$r['result'] = true;
			}
		}
		echo(json_encode($r));
		exit();
	}

	return frame_renderer($buff);
}


?>