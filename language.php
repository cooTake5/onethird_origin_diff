<?php

	global $lang_ar, $console_html,$ver_ar,$mode,$safe_request;
	

	if (is_file(dirname(__FILE__).'/config.php')) {
		$mode = 0;
		require_once(dirname(__FILE__).'/config.php');
		require_once(dirname(__FILE__).'/module/utility.basic.php');

		basic_initialize();
		
		if (!check_rights('admin')) {
			exit_proc(403);
		}

	} else if (is_file(dirname(__FILE__).'/module/utility.php')) {

		$mode = 1;
		require_once(dirname(__FILE__).'/module/utility.php');
		require_once(dirname(__FILE__).'/plugin/plugin.php');

		$safe_request = $_SERVER['REQUEST_URI'];
		$safe_request = htmlspecialchars($safe_request, ENT_QUOTES, 'UTF-8');
		$safe_request = str_replace(array('"',"'","<",">","%"), "", $safe_request);

	} else {
		echo('system error');
		die();
	}
	
	language();
	

function language()
{

	global $lang_ar, $console_html,$ver_ar, $mode, $ut, $config, $safe_request;

	$ver_ar = get_version_ar();
	
	$ready_ver = '';
	foreach ($ver_ar as $k=>$v) {
		$ready_ver .= $k.',';
	}
	$ready_ver = trim($ready_ver, ',');
	
	if (!isset($config['site_url'])) {
		$config['site_url'] = '';
	}

$buff = '';
	$console_html = '';
	$lang_ar = array();
	$lang_ar['en'] = 'english';
	$lang_ar['jp'] = 'japanese';
	
$console_html .=  <<<EOT
	<form method='post' action='{$safe_request}' class='onethird-setting' >
		<input type='hidden' name='update' value='update_1' />
		<div>
			from
			<select id='from_lang' name='from_lang' style='width:150px'>
EOT;
				foreach ($lang_ar as $k=>$v) {
					$s = '';
					if (isset($_POST['from_lang'])) {
						if ($_POST['from_lang'] == $k) {
							$s = 'selected';
						}
					}
$console_html .=  <<<EOT
					<option value='$k' $s>$v</option>
EOT;
				}
$console_html .=  <<<EOT
			</select>
			to
			<select id='to_lang' name='to_lang' style='width:150px'>
EOT;
				foreach ($lang_ar as $k=>$v) {
					$s = '';
					if (isset($_POST['to_lang'])) {
						if ($_POST['to_lang'] == $k) {
							$s = 'selected';
						}
					}
$console_html .=  <<<EOT
					<option value='$k' $s>$v</option>
EOT;
				}
$console_html .=  <<<EOT
			</select>
			<input type='submit' name='submit' value='check' class='onethird-button' onclick='' />
			<input type='submit' value='execute patch' class='onethird-button' onclick='' />
EOT;
			if ($mode == 1) {
$console_html .=  <<<EOT
				<input type='button' value='back to install' class='onethird-button' onclick='back()' />
EOT;
			} else {
$console_html .=  <<<EOT
				<input type='button' value='back to Home' class='onethird-button' onclick="location.href='{$ut->link()}';" />
EOT;
			}
$console_html .=  <<<EOT
		</div>
	</form>
EOT;
	if (isset($_POST['update']) && isset($_POST['from_lang']) && isset($_POST['to_lang'])) {
		$from = sanitize_asc($_POST['from_lang']);
		$to = sanitize_asc($_POST['to_lang']);
		$buff .= exec_language_table($from, $to, isset($_POST['submit']));
	}

echo <<<EOT
<!DOCTYPE html>
<html lang="ja">
	<head>
	<meta charset="utf-8">
	<title>OneThird-CMS Language switcher</title>
	<link href="{$config['site_url']}css/onethird.8.css" rel="stylesheet">
	<style>
	.container {
		padding:20px;
	}
	.box {
	  border: 1px solid #ddd;
	  margin-bottom:1px;
	}
	.error{
	  color:red;
	}
	.info {
		color:#DB7777;
		padding:0 0 10px 0;
	}
	.mess {
		padding:0 0 10px 0;
	}
	.title {
		background-color: #286593;
		font-size: 25px;
		padding: 15px;
		color: white;
		position: absolute;
		width: 820px;
		margin: 5px;
		top: 0;
		left: 0;
		height: 69px;
	}
	.main-unit {
		position: relative;
		background-color: #f0f0f0;
		width:800px;
		margin:50px auto 30px auto;
		margin-bottom: 2px;
		line-height:200%;
		border: 5px solid #AAA7A7;
		padding:150px 30px 150px 30px;
		min-height:350px;
	}
	.main-unit table {
		font-size:12px;
	}
	ul {
	  list-style: decimal;
	  list-style-position:inside;
	}
	table {
		border-collapse: collapse;
		background-color: #fff;
	}
	td, th {
		border: 1px solid #808080;
		vertical-align: top;
		padding:0 10px 0 10px;
	}
	.onethird-setting {
		margin:0;
	}
	</style>
	<script>
		function back() {
			location.href='install.php';
		}
	</script>
	</head>
	<body class='c_body'>
		<div class='main-unit'>
			<div class='title'>
				<img src='{$config['site_url']}css/onethird121.png' style='width:70px;vertical-align: middle;'/>
				<span style="font-family:'Meiryo','Verdana'">OneThird </span>
				<span style="font-family:'Verdana'; color:#FDDF00;">CMS</span>
			</div>
			<h2>OneThird-CMS Language Tool</h2>
			<span style='font-size:12px'>
				Ready for $ready_ver
			</span>
			<div style='line-height:120%;margin-bottom:20px;'>
				$console_html
			</div>
			<div>
				$buff
			</div>
		</div>
	</body>
</html>
EOT;

}

function exec_language_table($from, $to, $check = true)
{
	global $config, $console_html,$mode,$params,$p_circle;
	
	$config['site_path'] = dirname(__FILE__);
	
	$buff = @file_get_contents($config['site_path'].DIRECTORY_SEPARATOR."language.dat");
	$ar = @glob($config['site_path'].DIRECTORY_SEPARATOR.'language*.dat');
	foreach ($ar as $v) {
		if (basename($v) == 'language.dat') {
			continue;
		}
		$buff .= @file_get_contents($v);
	}

	$ar = explode( "\n", $buff );
	$file = '';	// file
	$cmd = '';	// command
	$from_str = array();
	$to_str = '';
	$contents = '';
	$result = '';
	$file_error = false;
	$file_skip = false;
	$replace_count = 1;
	if ($check) {
		$console_html .= '<b>check</b>';
	} else {
		$console_html .= '<b> execute</b>';
	}
	$result .= "<table>";
	$result .= "<tr><th>target file</th><th>current</th><th>to</th><th>change</th></tr>";
	$lx = 0;
	$ok = false;
	$rep_c = 0;
	$name = '';
	foreach ($ar as $v) {
		++$lx;
		$cx = substr($v,0,2);
		$v = trim($v);
		if ($file_skip && $cx != "*f") {
			continue;
		}
		if (!$v) {
			if (count($from_str) > 0 && $to_str) {
				foreach ($from_str as $v) {
					// この場合は非mb系でもエラーは起きない
					$count = substr_count($contents, $v);
					if ($count != $replace_count) {
						$count = mb_substr_count($contents, $to_str);
						if ($count != $replace_count) {
							$console_html .= "<br /><span style='color:red'><b>Error</b></span> in $lx ".safe_echo(adjust_mstring($v,100))." : $count:$replace_count";
							$ok = false;
							$file_error = true;
						}
					} else {
						$contents = str_replace($v, $to_str, $contents, $count);
						$rep_c += $count;
					}
				}
			}
			
			$from_str = array();
			$to_str = '';
			$replace_count = 1;
			
		} else if ($cx == "*f") {
			//データ書き込み
			if ($file_skip) {
				$result .= data_write_skip($file);
			
			} else if ($file_error) {
				$contents_org_md5 = check_md5($contents_org, $file);
				$contents_md5 = check_md5($contents, $file);
				$result .= "<tr><td>$file $name</td><td>".$contents_org_md5."</td><td>".$contents_md5."</td><td>$rep_c ... <span style='color:red'><b>save error</b></span></td></tr>";
				
			} else if ($ok) {
				data_write($file, $result, $contents, $contents_org, $rep_c, $check, $to, $from, $name);
			}
			$file_skip = false;
			$ok = false;
			
			//ファイル読み込み
			$file = substr($v,3);
			//安全のため、相対パスは使わない
			if (substr($file,0,1)=='.') {
				if ($mode === 0) {
					$file_name = $config['files_path'].DIRECTORY_SEPARATOR.$p_circle.DIRECTORY_SEPARATOR.substr($file,1);
				} else {
					$file_skip = true;
					continue;
				}
			} else {
				$file_name = $config['site_path'].DIRECTORY_SEPARATOR."$file";
			}
			/*
			if ($mode == 3) {
				$base_path = $config['files_path'].DIRECTORY_SEPARATOR.$p_circle.DIRECTORY_SEPARATOR;
				$file_name = $base_path.$file;
			}
			*/
			if (is_file($file_name)) {
				$ok = true;
				$file_error = false;
				$rep_c = 0;
				$contents_org = $contents = @file_get_contents($file_name);
				if (!$contents) {
					$console_html .= "<br /><span style='color:red'><b>Write Error</b></span>($file_name)";
				}
			} else {
				$file_skip = true;
			}
			
		} else if ($cx == "*n") {
			//データ書き込み
			if ($file_skip) {
				$result .= data_write_skip($file);
			
			} else if ($file_error) {
				$contents_org_md5 = check_md5($contents_org, $file);
				$contents_md5 = check_md5($contents, $file);
				$result .= "<tr><td>$file $name</td><td>".$contents_org_md5."</td><td>".$contents_md5."</td><td>$rep_c ... <span style='color:red'><b>save error</b></span></td></tr>";
				
			} else if ($ok) {
				data_write($file, $result, $contents, $contents_org, $rep_c, $check, $to, $from, $name);
			}
			$file_skip = false;
			$ok = false;
			
			//
			$name = " (".substr($v,3).")";
		
		} else if ($cx == "*x") {
			$cmd = substr($v,3);
		
		} else if ($cx == "*c") {
			$replace_count = (int)substr($v,3);
		
		} else if ($cx == $from) {
			$from_str[] = substr($v,3);
			
		} else if ($cx == $to) {
			$to_str = substr($v,3);
			
		}
	}

	if ($file_skip) {
		$result .= data_write_skip($file);

	} else if ($ok && $contents) {
		data_write($file, $result, $contents, $contents_org, $rep_c, $check, $to, $from, $name);

	}
	$result .= '<table>';
	return $result;
}

function data_write_skip($file)
{
$buff =  <<<EOT
<tr>
	<td>$file</td>
	<td>-</td>
	<td>-</td>
	<td>
	skip
	</td>
</tr>

EOT;

	return $buff;
}

function data_write($file, &$result, &$contents, &$contents_org, $rep_c, $check, $to, $from, $name)
{
	global $config,$mode,$p_circle;
	
	$contents_org_md5 = check_md5($contents_org, $file);
	$contents_md5 = check_md5($contents, $file);
	if ($contents_org != $contents) {
		if (substr($file,0,1)=='.') {
			if ($mode === 0) {
				$file_name = $config['files_path'].DIRECTORY_SEPARATOR.$p_circle.DIRECTORY_SEPARATOR.substr($file,1);
			} else {
				$file_skip = true;
				return;
			}
		} else {
			$file_name = $config['site_path'].DIRECTORY_SEPARATOR."$file";
		}
		/*if ($mode == 3) {
			$base_path = $config['files_path'].DIRECTORY_SEPARATOR.$p_circle.DIRECTORY_SEPARATOR;
			$file_name = $base_path.$file;
		}*/
		if ($check || @file_put_contents($file_name,$contents)) {
			$t =  "<tr><td>$file $name</td><td>".$contents_org_md5."</td><td>".$contents_md5."</td><td>$rep_c line";
			if (!$check) {
				$t .="... write <span style='color:red'><b>OK</b></span>";
			}
			$t .= "</td></tr>";
		} else {
			$t = "<tr><td>$file $name </td><td>".$contents_org_md5."</td><td>".$contents_md5."</td><td>$rep_c ... <span style='color:red'><b>save error</b></span></td></tr>";
		}
	} else {
		$t = "<tr><td>$file $name</td><td>".$contents_org_md5."</td><td>".$contents_md5."</td><td>no change</td></tr>";
	}
	if (isset($_GET['debug'])) {
		$z = md5($contents_org);
		if ($z == check_md5($contents_org,$file)) {
			echo "<pre style='margin:0'>	\$r['$z'] = '$file($from)';</pre>";
		}
		$z = md5($contents);
		if ($z == check_md5($contents,$file)) {
			echo "<pre style='margin:0'>	\$r['$z'] = '$file($to)';</pre>";
		}
	}
	$result .= $t;
}

function check_md5($md5, $file)
{
	global $lang_ar,$ver_ar;

	$md5 = md5($md5);

	foreach ($ver_ar as $k=>$v) {
		if (isset($v[$md5])) {
			$x = $v[$md5];
			$len = strlen($file);
			if (substr($x, 0, $len) == substr($file, 0, $len)) {
				$x = trim(substr($x, $len),"() ");
				/*if (isset($lang_ar[$x])) {
					return $lang_ar[$x];
				}*/
				return "$k ($x)";
			}
			return $k.':'.$x;
		}
	}

	return $md5;
}

function get_version_ar()
{
	$ar = array();

	$r = &$ar['v1.96'];
	$r['0b894b4e9e79c5ad9af9da2459e1b044'] = 'module/utility.basic.php(en)';
	$r['640fff2d2fd7aa251f6460bb7d7f53b3'] = 'module/utility.basic.php(jp)';
	$r['109a083b979e4a45c5990fd1f6882626'] = 'module/edit.std.php(en)';
	$r['39522a65e66dc3360d5bed6c08e85d17'] = 'module/edit.std.php(jp)';
	$r['99e3ef5832bfd610fb0fdf72edd80c6f'] = 'admin/member.php(en)';
	$r['83296812c14300fe0cea77921a321eab'] = 'admin/member.php(jp)';
	$r['42ba0d3195fbb868392c9b5c4a2be273'] = 'module/utility.edit.php(en)';
	$r['2d5b632605e4a9396895fe321e49e22e'] = 'module/utility.edit.php(jp)';
	$r['9c7113292d7924a2cf3b8e424b99a1c0'] = 'module/utility.php(en)';
	$r['deb8dca451fbab326caf41690f757bb3'] = 'module/utility.php(jp)';
	$r['5732fb5ab8041885a5c64bc442250488'] = 'module/utility.std.php(en)';
	$r['650c25f79a108f867aff03d2c60e2ead'] = 'module/utility.std.php(jp)';
	$r['f48234942ed1fcde5898a88e52082043'] = 'admin/account.php(en)';
	$r['c38ff6540e7c4aab9bb7137130e24423'] = 'admin/account.php(jp)';
	$r['d79854f3eb018df192ed4b93d2bf5b2c'] = 'admin/restore.php(en)';
	$r['218925163845cdcb9cb8055905b4759a'] = 'admin/restore.php(jp)';
	$r['c28a221bdfcd46868659169e44e8aa41'] = 'admin/online.php(en)';
	$r['55a6af63132648f40da408e3f3a275d6'] = 'admin/online.php(jp)';
	$r['72dc44ad312079545fa2b68bff0ba068'] = 'admin/setting.php(en)';
	$r['c4a91df109f239a6b2f4f86a02bd0075'] = 'admin/setting.php(jp)';
	$r['c3d91c26e91978703faf7a838ccf6820'] = 'plugin/smap_cdn.php(en)';
	$r['ab313b96453759892c67698a4c53c106'] = 'plugin/smap_cdn.php(jp)';
	$r['ff23ecfc91c43d446be25a7f94d71501'] = 'plugin/pagelist.php(en)';
	$r['e5c2a9714ac76204b0991bf75573df42'] = 'plugin/pagelist.php(jp)';
	$r['c3694911119a0d5669befd71ff47a44e'] = 'plugin/plugin.php(en)';
	$r['313ea3d1bc804327bd9cbe8d85ad92a2'] = 'plugin/plugin.php(jp)';
	$r['a04bea31c708fa9b3eed98a0b40a389f'] = 'plugin/uploader.php(en)';
	$r['b5114db1dbfb4d5d3f15260510926794'] = 'plugin/uploader.php(jp)';
	$r['e84595a5412846a4a05e317e18b5df15'] = 'install.php(en)';
	$r['7be875be6d4985d69d4c1068faaac1a6'] = 'install.php(jp)';
	$r['c9ed1ecc88826c565a2d25a4fd29458e'] = 'plugin/login.php(en)';
	$r['9680c4a346f3dd24053c92c2f60003ae'] = 'plugin/login.php(jp)';
	$r['abd1686a18ab987681d67a98edc26ace'] = 'plugin/bbs2.php(en)';
	$r['9b5aff8e549d741280c40383961a930d'] = 'plugin/bbs2.php(jp)';
	$r['30f295644cd7a6c958dcf7ff0f071fdd'] = 'plugin/page_folder.php(en)';
	$r['c477b79c6c9ac5d4671ce3488974cb9a'] = 'plugin/page_folder.php(jp)';
	$r['0a3f3908489562cf1de5a5388c3f051a'] = 'plugin/search.php(en)';
	$r['6e234cab83d2c2d97df80afb70b3d194'] = 'plugin/search.php(jp)';
	$r['109b8fd7921155442375bcf534396fe0'] = 'plugin/contact.php(en)';
	$r['d636128b2e249f212a33bb380c28e66f'] = 'plugin/contact.php(jp)';
	
	return $ar;

}	
?>