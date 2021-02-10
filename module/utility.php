<?php
// OneThird CMS
// coyright(c) SpiQe Software,team1/3 All Rights Reserved.

class DataBase
{
	public $db, $user, $pass, $type, $dir, $begin_flag, $sql_debug, $write_lock;

	function __construct( $t, $u="", $p="", $h="", $d="" ) {
		$this->type = $t;
		$this->user = $u;
		$this->pass = $p;
		$this->dir = $d;
		$this->sql_debug = false;
		$this->st = null;
		if ($h) {
			$this->host = $h;
		} else {
			$this->host = 'localhost';
		}
		$this->begin_flag = 0;
		$this->write_lock = false;
	}

	function open( $name ) {
		$this->db = null;
		try {
			if ($this->type == 'mysql') {
		    	$this->db = new PDO("mysql:host={$this->host}; dbname=$name",$this->user, $this->pass);

				$sql=" SET NAMES utf8 ";
				$stmt = $this->db->query($sql);
				if ($stmt && $stmt->closeCursor()) {
				}
				if (!$this->db) {
				    //die("datadase system error - 1");
				    return false;
				}

		    } else {
		    	$this->db = new PDO("sqlite:".$this->dir.DIRECTORY_SEPARATOR.$name.".db");
		    }

		} catch (PDOException $e) {
			if ($this->sql_debug !== false) {
		    	$this->sql_debug[] = $e->getMessage();
			}
		    //die("datadase system error - 2");
		    return false;
		}
		return $this->db;
	}

	function sql_select_all() {
		$numargs = func_num_args();
		if (!$numargs) {
			return false;
		}
		$arg_list = func_get_args();
		$w_mode = false;
		if (is_array($arg_list[0])) {	// 配列指定
			$arg_list = $arg_list[0];
			$numargs = count($arg_list);
		}
		$sql = $arg_list[0];
		$arr_record = false;
		for ($i = 1; $i < $numargs; ++$i) {
			//引数に配列が入っていた時には配列モード
			if (is_array($arg_list[$i])) {
				$w_mode = true;
				break;
			}
		}
		if ($w_mode) {
			$opt = false;
			$arg_new = array();
			$arg_new[0] = '';
			$len = 1;
			for ($i = 1; $i < $numargs; ++$i) {
				if (empty($arg_list[$i])) {
					continue;
				}
				if (is_array($arg_list[$i])) {
					$fst = true;
					foreach ($arg_list[$i] as $k=>$v) {
						if (is_numeric($k)) {
							if ($fst) {
								$sql .= $v;	//配列の先頭はSQL
							} else {
								$arg_new[] = $v;
								++$len;
							}
						} else {
							//配列のキーが文字列の場合はキーがSQL
							$sql .= $k;
							$arg_new[] = $v;
							++$len;
						}
						$fst = false;
					}
				} else {
					$sql .= $arg_list[$i];
				}
			}
			$arg_list = $arg_new;
			$arg_list[0] = $sql;
			$numargs = $len;
		}
		if ($this->sql_debug !== false) {
			$this->sql_debug[] = $arg_list;
		}
		$stmt = $this->db->prepare($sql);
		if ($stmt) {
			for ($i = 1; $i < $numargs; $i++) {
				if ($this->type == 'mysql' && is_int($arg_list[$i])) {
					$stmt->bindParam($i,$arg_list[$i],PDO::PARAM_INT);	// v1.22
				} else {
					$stmt->bindParam($i,$arg_list[$i]);
				}
			}
			if ($stmt->execute()) {
				$arr_record=$stmt->fetchAll(PDO::FETCH_ASSOC);
				$stmt->closeCursor();
			} else {
				if ($this->sql_debug !== false) {
			    	$this->sql_debug[] = $stmt->errorInfo();
			    }
			}
		} else {
			if ($this->sql_debug !== false) {
		    	$this->sql_debug[] = $this->db->errorInfo();
		    	$this->sql_debug[] = $arg_list;
		    }
		}
		return $arr_record;
	}

	function sql_select_one() {
		$numargs = func_num_args();
		if (!$numargs) {
			return false;
		}
		$arg_list = func_get_args();
		$sql = $arg_list[0];
		$arr_record = false;
		if ($this->sql_debug !== false) {
			$this->sql_debug[] = $arg_list;
		}
		$this->st = $this->db->prepare($sql);
		if ($this->st) {
			for ($i = 1; $i < $numargs; $i++) {
				$this->st->bindParam($i,$arg_list[$i]);
			}
			return $this->st->execute();
		} else {
			if ($this->sql_debug !== false) {
		    	$this->sql_debug[] = $this->db->errorInfo();
		    	$this->sql_debug[] = $arg_list;
		    }
		}
		return false;
	}
	function sql_select_take() {
		$arr_record = $this->st->fetch(PDO::FETCH_ASSOC);
		if ($arr_record !== false) {
			return $arr_record;
		}
		//$this->st->closeCursor();
		return false;
	}

	function sql_begin() {
		if ($this->begin_flag === 0) {
			if ($this->type == 'sqlite') {
				if (!$this->db->query('begin deferred')) {
					die(0);
				}
			} else {
				if (!$this->db->query('begin')) {
					die(0);
				}
			}
			if ($this->sql_debug !== false) {
				$this->sql_debug[] = 'begin';
			}
		}
		++$this->begin_flag;
	}

	function sql_commit( $f = true ) {
		if ($f) { $this->begin_flag = 0; }
		if ($this->begin_flag > 0) {
			--$this->begin_flag;
		}
		if ($this->begin_flag === 0) {
			$this->db->query('commit');
			if ($this->sql_debug !== false) {
				$this->sql_debug[] = 'commit';
			}
		}
	}

	function sql_rollback() {
		$this->begin_flag = 0;
		$this->db->query('rollback');
		if ($this->sql_debug !== false) {
			$this->sql_debug[] = 'rollback';
		}
	}

	function sql_update() {
		$numargs = func_num_args();
		if ($this->write_lock) { return false; }
		if (!$numargs) {
			return false;
		}
		if ($this->type == 'sqlite') {
			$this->sql_begin();
		}
		$r = false;
		$arg_list = func_get_args();
		$sql = $arg_list[0];
		if (is_array($sql)) {
			// バックアップデータのリストア等、配列で受けれる仕様のほうが便利
			$arg_list = $sql;
			$sql = $arg_list[0];
			$numargs = count($arg_list);
		}
		$arr_record=false;
		if ($this->sql_debug !== false) {
			$this->sql_debug[] = $arg_list;
		}
		$stmt = $this->db->prepare($sql);
		if ($stmt) {
			for ($i = 1; $i < $numargs; $i++) {
				$stmt->bindParam($i, $arg_list[$i]);
			}
			$r = $stmt->execute();
			if ($r) {
				$stmt->closeCursor();
			} else {
				if ($this->sql_debug !== false) {
			    	$this->sql_debug[] = $stmt->errorInfo();
			    	$this->sql_debug[] = $arg_list;
			    }
			}
		} else {
			if ($this->sql_debug !== false) {
		    	$this->sql_debug[] = $this->db->errorInfo();
		    	$this->sql_debug[] = $arg_list;
		    }
		}
		if ($this->type == 'sqlite') {
			$this->sql_commit(false);
		}
		return $r;
	}

	function lastInsertId() {
		if ($this->type == 'mysql') {
			$a = $this->sql_select_all("SELECT LAST_INSERT_ID() as c");
			if ($a && $a[0]) {
				return $a[0]['c'];
			}
		} else {
			return $this->db->lastInsertId();
		}
	}

	function quote( $v ) {
		return $this->db->quote($v);
	}

	function getType() {
		return $this->type;
	}

	function debug() {
		if (!$this->sql_debug) {
			$this->sql_debug = array();
			return 'debug start';
		}
		return $this->sql_debug;
	}

}

class Ut
{
	protected $database;
	public $p_circle, $params, $config, $html;

	function __construct( &$html, &$database, &$p_circle, &$params, &$config ) {
		$this->html = &$html;
		$this->database = &$database;
		$this->circle = &$p_circle;
		$this->params = &$params;
		$this->config = &$config;
	}

    public function expand( $id, $delayed_flag = false ) {

    	if ($delayed_flag) {
    		return $this->delayed_expand( $id, false );
    	}

    	$buff='';
		$html = $this->html;

		if (isset($html[$id])) {
			if (is_array($html[$id])) {
				foreach ($html[$id] as $v) {
					if (!$v) { continue; }
					if (is_array($v)) {
						$buff .= implode("\n",$v);
					} else {
						$buff .= $v;
					}
				}

			} else {
				$v = $html[$id];
				$buff .= $v;
			}
		}
		return $buff;
    }

    public function delayed_expand( $id, $sort=false ) {
		$this->params['delayed_expand'][] = $id;
		if ($sort) {
			$this->params['delayed_expand_sort'][$id]=true;
		}
    	return "{{delayed_{$id}}}";
    }

    public function expand_sorted( $a, $delayed_flag = false ) {

    	if (isset($this->html[$a]) && is_array($this->html[$a])) {
			uksort($this->html[$a], array($this, "cmp"));
		}

    	if ($delayed_flag) {
    		return $this->delayed_expand( $a, true );
    	}

		return $this->expand($a);
	}

	static function cmp( $b, $a ) {
		// 柔軟性を持たせるためユーザーソートを導入
		// タイプが違う場合は文字列キーを優先
		// 文字列キーは短い順(昇順)でソート
		// 数字キーは早い順(昇順)でソート
		$num_a = is_numeric($a);
		$num_b = is_numeric($b);
		if ($num_a && !$num_b) {
			return false;
		}
		if (!$num_a && $num_b) {
			return true;
		}
		if ($num_a) {
		    return $b > $a;
		}
	    return strcasecmp($b, $a);
	}

	function link() {
		//パラメータ分析
		$arg = func_get_args();
		$this->get_arg($arg);

		// (num,'mode:xxx') => site/?page=num&mode=xxx
		// ('xxx') => site/xxx

		if (!isset($arg['id']) && isset($arg[0])) {
			$arg['id'] = sanitize_str($arg[0]);
		}
		$link = $this->config['site_ssl'];
		if (isset($this->params['circle']['link'])) {
			$link = $this->params['circle']['link'];
		} else if (isset($this->params['circle']['url'])) {
			$link = $this->params['circle']['url'];
		}
		$permalink = $this->config['permalink'];
		if (isset($arg['permalink'])) {
			$permalink = $arg['permalink'];
		}
		$prefix = '';
		if (isset($this->params['url_prefix']) && $this->params['url_prefix']) {
			$prefix = $this->params['url_prefix'];
		}
		if (isset($arg['prefix'])) {
			$prefix = $arg['prefix'];
		}
		if ($prefix == $this->config['admin_dir']) {
			$prefix = '';	// admin以外の中間ディレクトリはそのまま残す
		}
		if (isset($arg['id']) && $arg['id']) {
			if (is_numeric($arg['id'])) {
				if (isset($this->params['circle']['meta']['top_page']) && $this->params['circle']['meta']['top_page'] == $arg['id']) {
					$u = "{$this->params['circle']['url']}{$prefix}";
				} else {
					// エイリアス検索
					if ($prefix) { $prefix = trim($prefix,'/').'/'; }
					if (isset($this->params['circle']['meta']['f_alias'][$arg['id']])) {
						$u = $this->params['circle']['meta']['f_alias'][$arg['id']];
						$u = $this->params['circle']['url'].$prefix.$u;
					} else {
						$f = $permalink;
						$f = str_replace('{page}', $arg['id'], $f);
						$u = "$link{$prefix}{$f}";
					}
				}
			} else {
				if ($prefix) { $prefix = trim($prefix,'/').'/'; }
				$u = "$link{$prefix}{$arg['id']}";
			}
		} else {
			$u = "$link{$prefix}";
		}
		if (!empty($this->params['force_exthtml'])) {
			if (substr($u,-4) != '.html') {
				$u .= '.html';
			}
		}
		if (isset($arg['mode'])) {
			if (strstr($u,'?') !== false) {
				$u .= '&amp;mode='.$arg['mode'];
			} else {
				$u .= '?mode='.$arg['mode'];
			}
		}
		if (isset($arg['&'])) {
			if (strstr($u,'?') !== false) {
				$u .= '&amp;'.$arg['&'];
			} else {
				$u .= '?'.$arg['&'];
			}
		}
		if (isset($this->params['hook']['ut_link']) && is_array($this->params['hook']['ut_link'])) {
			foreach ($this->params['hook']['ut_link'] as $v) {
				if (function_exists($v)) {
					$v($u);
				}
			}
		}
		return safe_echo($u);
	}

	function icon( $arg, $ar = null ) {
		$cl = " width='16' ";
		if (is_string($ar)) {
			$cl = " $ar ";
		} else {
			if (isset($ar['width'])) {
				$cl = " width='{$ar['width']}' ";
			}
			if (isset($ar['size'])) {
				$cl = " width='{$ar['size']}' ";
			}
			if (isset($ar['class'])) {
				$cl .= " class='{$ar['class']}' ";
			}
			if (isset($ar['title'])) {
				$cl .= " title='{$ar['title']}' ";
			}
		}
		if ($arg[0] == '.') {
			$a = substr($arg,1);
			if ($a[0]=='/') {
				$a = substr($a,1);
			}
			return "<img src='{$this->params['data_url']}$a.png' alt='edit' $cl />";
		}
		$alt = '';
		if ($arg == 'edit') {
			return "<img src='{$this->config['site_ssl']}img/edit.png' alt='edit' $cl />";
		}
		if ($arg == 'admin' || $arg == 'lock') {
			return "<img src='{$this->config['site_ssl']}img/lock.png' alt='$arg' $cl />";
		}
		if ($arg == 'alert') {
			return "<img src='{$this->config['site_ssl']}img/caution.png' alt='' $cl />";
		}
		if ($arg == 'setting') {
			return "<img src='{$this->config['site_ssl']}img/system.png' alt='setting' $cl />";
		}
		if ($arg == 'save' || $arg == 'add' || $arg == 'delete' || $arg == 'remove' || $arg == 'ok' || $arg == 'ng' ) { $alt = $arg; }
		return "<img src='{$this->config['site_ssl']}img/$arg.png' alt='$alt' $cl />";
	}

	function storage($name, $value=null, $circle=false) {
		if ($value == null) {
			return $this->get_storage($name, $circle);
		}
		return $this->set_storage($name, $value, $circle);;
	}

	function get_storage($name, $circle=false) {
		global $p_circle;
		if ($circle === false) {
			$circle = $p_circle;
		}
		$ar = $this->database->sql_select_all("select data from ".DBX."storage where name=? and circle=?", $name, $circle);
		if (!$ar) {
			return null;
		}
		$m = @unserialize64($ar[0]['data']);
		return $m;
	}

	function set_storage($name, $value, $circle=false) {
		global $p_circle;
		if ($circle === false) {
			$circle = $p_circle;
		}
		$sql = "select data,id from ".DBX."storage where name=? and circle=? limit 1";
		$ar = $this->database->sql_select_all($sql, $name, $circle);
		if (!$ar) {
			$this->database->sql_update("insert into ".DBX."storage (name,circle) values(?,?)", $name, $circle);		//
			$ar = $this->database->sql_select_all($sql, $name, $circle);
			if (!$ar) {
				return false;
			}
		}
		$id = $ar[0]['id'];
		$back_d = $ar[0]['data'];
		$m = @serialize64($value);
		if (!$this->database->sql_update( "update ".DBX."storage set data=? where id=?",$m,$id)) {
			return false;
		}
		$ar = $this->database->sql_select_all("select data,id from ".DBX."storage where id=?",$id);
		if ($m == $ar[0]['data']) {
			return true;
		}
		$this->database->sql_update( "update ".DBX."storage set data=? where id=?", $back_d, $id);
		return false;
	}

	function rm_storage( $name, $key=null, $circle = 0 ) {
		$ar = $this->database->sql_select_all("select data,id from ".DBX."storage where id=?", $id);
		if (!$ar) {
			return false;
		}
		if ($key === null) {
			if (!$this->database->sql_update( "delete from ".DBX."storage where id=?", $ar[0]['id'])) {
				return false;
			}
		} else {
			if (isset($ar[0]['data']) && $ar[0]['data']) {
				$value = unserialize64($ar[0]['data']);
				foreach ($value as $k=>$v) {
					if ($k == $key) {
						unset($value[$k]);
						break;
					}
				}
				if (!$this->database->sql_update( "update ".DBX."storage set data=? where id=?", serialize64($value), $ar[0]['id'])) {
					return false;
				}
			}
		}
		return true;
	}

	function get_arg( &$arg ) {
		if (isset($arg[0]) && is_array($arg[0])) {
			// 第一引数が配列の場合は call関数から呼ばれた組み込み関数と判断
			$arg = $arg[0];
		}
		$arg_list = $arg;
		//引数解析
		foreach ( $arg_list as $i=>$item ) {
			if (is_string($item)) {
				if ( ($p=strpos ( $item , ':' )) !== false ) {
					$val = substr( $item, $p+1 );
					$item = substr( $item, 0, $p );
				} else {
					$val = false;
				}
				$arg[$item] = $val;
			}
		}
	}

	function input() {
		$arg = func_get_args();
		$this->get_arg($arg);
		$buff = '';
		if (isset($arg['type'])) {
			$t = $arg['type'];
			if ($t == 'select' && isset($arg['option'])) {
				$buff .= '<select ';
				if (isset($arg['class'])) {
					$buff .= " class='".safe_echo($arg['class'])."' ";
				}
				if (isset($arg['id'])) {
					$buff .= " id='".safe_echo($arg['id'])."' ";
				}
				if (isset($arg['name'])) {
					$buff .= " name='".safe_echo($arg['name'])."' ";
				}
				if (isset($arg['data-input'])) {	// for snippet_std_setting
					$buff .= " data-input='".safe_echo($arg['data-input'])."' ";
				}
				$buff .= '>';
				foreach ($arg['option'] as $k=>$v) {
					$buff .= "<option value='".safe_echo($k)."' ";
					if (isset($arg['value']) && $arg['value'] && $k == $arg['value']) {
						$buff .= " selected ";
					}
					$buff .= ">";
					$buff .= safe_echo($v);
					$buff .= "</option>";
				}
				$buff .= '</select>';

			} else if ($t == 'checkbox') {
				$buff .= "<label>";
				$buff .= "<input type='checkbox' ";
				if (isset($arg['class'])) {
					$buff .= " class='".safe_echo($arg['class'])."' ";
				}
				if (isset($arg['id'])) {
					$buff .= " id='".safe_echo($arg['id'])."' ";
				}
				if (isset($arg['name'])) {
					$buff .= " name='".safe_echo($arg['name'])."' ";
				}
				if (isset($arg['data-input'])) {	// for snippet_std_setting
					$buff .= " data-input='".safe_echo($arg['data-input'])."' ";
				}
				if (isset($arg['checked']) && (bool)$arg['checked']) {
					$buff .= " checked ";
				}
				$buff .= "/>{$arg['label']}</label>";
			}
		}
		return $buff;
	}

	function date_format( $field, $fmt ) {
		if ($this->database->getType() == 'sqlite') {
			$fmt = str_replace('%i', '%M', $fmt);
			return " strftime($fmt,$field) ";
		}
		return " date_format($field,$fmt) ";
	}

	function sql_substr( $field, $pos, $len ) {
		if ($this->database->getType() == 'sqlite') {
			return " substr($field,$pos,$len) ";
		}
		return " substring($field,$pos,$len) ";
	}

	function limit( $ofs, $limit ) {
		if ($this->database->getType() == 'sqlite') {
			if (!$ofs) {
				return " limit ".(int)$limit." ";
			}
			return " limit ".(int)$limit." offset ".(int)$ofs."  ";
		}
		if (!$ofs) {
			return " limit ".(int)$limit."  ";
		}
		return " limit ".(int)$ofs.", ".(int)$limit."  ";
	}

	function time_cmp( $a, $b ,$c ) {	//将来的に削除
		if ($this->database->getType() == 'sqlite') {
			return " julianday($a) $b julianday($c) ";
		} else {
			return " UNIX_TIMESTAMP($a) $b UNIX_TIMESTAMP($c) ";
		}
	}

	function sql_timestamp($a=0) {
		// $a = field name or unix timestamp
		if (is_numeric($a)) {
			if ($a < 60*60*24*365*10 || $a <= 0) {	//10年以下の日数は、差分とみなす
				$a = time()+$a;
			}
		}
		if ($this->database->getType() == 'sqlite') {
			if (is_numeric($a)) {
				return " ".unixtojd($a)." ";
			}
			$a = trim($a,"\"'");
			return " julianday(\"$a\") ";
		} else {
			if (is_numeric($a)) {
				return " $a ";
			}
			$a = trim($a,"\"'");
			if (is_numeric(substr($a,0,1))) {
				return " UNIX_TIMESTAMP(\"$a\") ";
			}
			return " UNIX_TIMESTAMP($a) ";
		}
	}

	function time($a = 0) {
		return time()+$a;
	}

	function str() {
		$ar = func_get_args();
		$str = '';
		foreach ($ar as $v) {
			if (is_bool($v)) {
				if ($v) {
					continue;
				} else {
					return $str;
				}
			}
			$str .= $v;
		}
		return $str;
	}

	function date() {
		$x = func_get_args();
		return call_user_func_array("date", $x);
	}
	function substr() {
		$x = func_get_args();
		return call_user_func_array("mb_substr", $x);
	}
	function replace() {
		$x = func_get_args();
		return call_user_func_array("str_replace", $x);
	}

	function check($a, $b='', $c='') {
		if ($a) {
			return $b;
		}
		return $c;
	}

	function safe_echo($a, $del_quotes = true) {
		return safe_echo($a, $del_quotes);
	}
	function safe_int($a) {
		return (int)$a;
	}
	function safe_asc($a) {
		return sanitize_asc($a);
	}
	function safe_num($a) {
		return sanitize_num($a);
	}
	function safe_price($a) {
		return number_format(sanitize_num($a));
	}
	function safe_dir($a) {
		return sanitize_path($a);
	}
	function safe_str($a) {
		return sanitize_str($a);
	}
	function safe_html($a) {
		return sanitize_html($a);
	}
	function css($a) {
		global $html;
		$html['css'][] = "<style>{$this->safe_echo($a)}</style>";
	}
	function compress($mode,$v) {
		if (isset($this->params['code_compressor'])) {
			return $this->params['code_compressor']($mode,$v);
		}
		$v = trim($v);
		if ($mode =='css') {
			$v = preg_replace('/[\n\r\t]/', '', $v);
			$v = preg_replace('/\s(?=\s)/', '', $v);
			$v = preg_replace('/ *([{:;]) */', '${1}', $v);
		}
		return $v;
	}
	function tag($t,$h,$opt='') {
		if ($h) {
			return "<$t {$this->safe_echo($opt)}>{$this->safe_echo($h)}</$t>";
		}
		return "<$t {$this->safe_echo($opt)} />";
	}
	function is_home() {
		if (!isset($this->params['page']) || !isset($this->params['page']['mode'])) {
			return false;
		}
		return $this->params['page']['mode'] == 2;
	}
	function get_home_id() {
		return $this->params['circle']['meta']['top_page'];
	}
	function page($a,$b=null) {
		if (isset($this->params['inner_page'])) {
			if (isset($this->params['inner_page'][$a])) {
				if ($b !== null) { $this->params['inner_page'][$a]=$b; return ''; }
				return $this->params['inner_page'][$a];
			}
			return '';
		}
		if (!isset($this->params['page']) || !isset($this->params['page'][$a])) {
			return '';
		}
		if ($b !== null) { $this->params['page'][$a]=$b; return ''; }
		return $this->params['page'][$a];
	}
	function check_rights($a='') {
		return check_rights($a);
	}
	function get_timestamp($a='now',$diff=0,$disp=false,$format='Y-m-d') {
		if (is_numeric($a)) {
			$t = strtotime(" +{$a} day");
		} else {
			$t = strtotime($a);
		}
		if ($diff > 0) {
			$t = $t + 60*60*24+$diff;
		} else if ($diff < 0) {
			$t = $t - 60*60*24+$diff;
		}
		if (!$disp) { return $t; }
		return date($format,$t);
	}
	function cookie($key, $v=null, $exp_day=0, $path='/') {
		if ($v !== null) {
			if (!empty($_COOKIE[$key])) {
				return $this->safe_echo($_COOKIE[$key]);
			}
			return false;
		}
		return set_cookie($key, $this->safe_echo($v), $exp_day, $path);
	}
	function number_format($a,$b=0) {
		return number_format($a,$b);
	}
}

function sanitize_post( $v, $remove_tag = true )
{
	//最低限のサニタイズを行う
	$v = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
	return trim($v,"\r\n\t");
}

function sanitize_asc( $v )
{
	if (is_array($v)) {
		die('sanitize error');
	}
	$v = str_replace("'","",$v);
	$v = str_replace("\"","",$v);
	$v = str_replace("\\","",$v);
	$v = filter_var($v, FILTER_SANITIZE_EMAIL);
	//$v = filter_var($v, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
	//$v = filter_var($v, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_HIGH);
	return $v;
}

function sanitize_path( $v )
{
	if (is_array($v)) {
		die('sanitize error');
	}
	$v = str_replace("'","",$v);
	$v = str_replace("..","",$v);
	$v = str_replace("\"","",$v);
	$v = str_replace("'","",$v);
	$v = str_replace("<","",$v);
	$v = str_replace(">","",$v);
	$v = str_replace("&","",$v);
	return $v;
}

function sanitize_str( $v, $remove_tag = true )
{
	global $database;
	//UTF8使用が前提
	if (is_array($v)) {
		die('sanitize error');
	}
	$v = str_replace("\\","",$v);
	$v = str_replace("\"","",$v);
	$v = str_replace("'","",$v);
	if ($remove_tag) {
		$v = str_replace("<","",$v);
		$v = str_replace(">","",$v);
		$v = str_replace("&","",$v);
	} else {
		$v = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
	}
	/*
	if ($database && method_exists($database,'quote')) {
		$v = $database->quote($v);
	}*/
	return $v;
}
function sanitize_num( $v )
{
	if (is_array($v)) {
		die('sanitize error');
	}
	$v = str_replace(",", "",$v);
	$v = str_replace("、", "",$v);
	return (int)mb_convert_kana(sanitize_str($v), 'a');
}

function sanitize_date( $v )
{
	if (is_array($v)) {
		die('sanitize error');
	}
	$v = mb_convert_kana(sanitize_str($v), 'a');
	return date('Y-m-d H:i:s', strtotime($v));
}

function sanitize_html( $v )
{

	if (is_array($v)) {
		die('sanitize error');
	}
	$v = str_replace('$', "\\$", $v);		//  $ はエスケープする
	$v = preg_replace_callback(
			"/\\x7B(\\\\\\$.*?)\\x7D/mu"
			,function ($matches) {
				$v = str_replace('&amp;','&',$matches[0]);
				$v = str_replace('&gt;','>',$v);
				$v = str_replace('&#39;',"'",$v);
				return str_replace("\\$",'$',$v);
			}
		, $v
	);
	$v = str_replace("\\\\$", "\\\\\\$", $v);		//

	return $v;
}

function reject_func(&$v)
{
	$v = preg_replace_callback(
		"/\\x7B(\\$.*?)\\x7D/su"
		, "_reject_func"
		, $v
	);
}

function _reject_func($matches)
{
	/*
	$matches[0] = str_replace('=', ',', $matches[0]);	// テンプレート関数内では＝は使用できない
														// 使用する場合は\x3dを使用->linkが使用できなくなるおそれがあるので変更
	*/

	$matches[0] = str_replace("DATABASE_PASS", 'database_pass', $matches[0]);
	$matches[0] = str_replace('$GLOBALS', 'GLOBALS', $matches[0]);
	$matches[0] = preg_replace("/\\$[A-Za-z0-9_]+ *(=)/mu", ',', $matches[0]);

	return preg_replace_callback("/(->|\\$)?[A-Za-z0-9_]+ *\\(/mu"
		, "__reject_func"
		, $matches[0]
	);
}

function __reject_func($matches)
{
	$a = $matches[0];
	if (substr($a,0,1) == '$' || substr($a,0,2) == '->' || $a == 'array(') {
		return $a;
	}
	return 'ot_'.$a;
}

function safe_echo( $v, $del_quotes = true )
{
	if ($del_quotes) {
		$v = str_replace(array('"',"'"), "", $v);
	}
	return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/*function execute_script( $s )
{
	global $script_name,$script_dir;
	$script_name=$s;
	$script_dir=dirname($s).'/';
	require($s);
}*/

function adjust_mstring( $str, $size=20 )
{
	if (function_exists('mb_strimwidth')) {
		return mb_strimwidth($str, 0, $size, "...");
	}
	if (mb_strlen($str)>$size) {
		$str= mb_substr($str,0,$size).'…';
	}
	return $str;
}

function serialize64( $array )
{
	return base64_encode(serialize($array));
}

function unserialize64( $data )
{
	return unserialize(base64_decode($data));
}

function shutdown_handler()
{
	global $params, $ut, $html, $config;
	if ($error = error_get_last()) {
		if (check_rights('admin')) {
			if (isset($_POST['ajax'])) {
				echo( "\n".json_encode($error) );	// for debug
				//add_actionlog("php error dump \n".print_r($error,true));
			} else {
				if (!empty($params['page']['id'])) {
					$a = " <a href='{$config['site_url']}{$config['admin_dir']}/pmanager.php?page={$params['page']['id']}'>[Page manager]</a>";
				}
				// for debug
				if (isset($error['message']) && isset($error['file']) && isset($error['line'])) {
echo <<<EOT
					<div id='onethird-error' onclick="this.style.display='none'"
						style='border: 10px solid #fff;position: fixed; top: 300px; right: 30px;z-index: 1000000000;font-size:16px;margin-left:50px'>
						<div style='background-color: #FF0000;border: 5px solid #FF0000;  border-radius: 2px; '>
							<div style='padding: 5px 5px 12px 5px;font-weight:bold;line-height:1.5em;color:#fff'>OneThird CMS Error  </div>
							<div style='padding:10px;background-color: #fff;color:#222;'>
								<p>{$error['message']}</p>
								<p>{$error['file']} ( <b style='font-size:120%'>{$error['line']}</b> )</p>
								<p>$a</p>
							</div>
						</div>
					</div>
EOT;
				} else {
					echo dump($error);
				}
			}
		}
		if (!empty($params['_hnd_eval']) && $params['_hnd_eval'] != 0 && check_rights('edit')) {
			$a = "Can not be displayed error occurred, the page content, please return to the edit mode({$params['_hnd_eval']})<br />";
			$opt = array();
			if (isset($params['page']['id'])) {
				$opt['caption'] = 'edit';
				if ($params['_hnd_eval'] == 1) {
					$opt['href'] = $ut->link($params['page']['id'],'mode:edit');
				} else {
					$opt['href'] = $ut->link($params['page']['id'],'&:x');
				}
			}
			exit_proc(0, $a, $opt);
		}
	}
}

function get_template_values()
{
$buff = <<<EOT
	\$plugin = 'plugin_proc';
	\$call = 'call_proc';
	\$exec = 'exec_proc';
	\$load = 'load_proc';
	\$dump = 'dump';
	\$span = 'span_proc';
	\$edit = 'edit_proc';
	\$img = 'img_proc';
	\$file = 'file_proc';
	\$color = 'color_proc';
	\$div = 'div_proc';
	\$link = 'link_proc';
	\$dl = 'dl_proc';
	\$pre = 'pre_proc';
	\$ul = 'ul_proc';
	\$append = 'append_proc';
EOT;
	return $buff;
}

function expand_html( $tpl, $ret = false, $check_ext_function = true, $body=false )
{
	global $params,$config,$html,$ut;

	if (!$body) {
		$body = @file_get_contents($tpl);
		if ( $body === false ) {
			return false;
		}
	}

	if (!$ret) {
		if (isset($params['hook']['before_expand']) && is_array($params['hook']['before_expand'])) {
			// 表示バッファ展開前のhook関数
			foreach ($params['hook']['before_expand'] as $v) {
				if (function_exists($v)) {
					$v($body);
				}
			}
			//unset($params['hook']['before_expand']);
		}
	}

	$r = $params['magic_number'];
	$p = get_template_values();
	$p.= '$a = <<<EOT'.$r."\n";
	$p.= $body;
	$p.= "\nEOT$r;\n";
	try {
		if ($check_ext_function) { reject_func($p); }
		@eval($p);
	} catch (Exception $e) {
	}

	if ( $r === false ) {
		$a = 'エラーが発生しました、ページ内容を表示できません、編集画面に戻ってください(2)<br />';
		if (isset($params['page']['id'])) {
			$a .= "<a href='{$ut->link($params['page']['id'],'mode:edit')}'>編集</a>";
		}
	}

	// delayed_expand v0.07から追加 プラグイン内でcssを追加した場合展開されない問題に対応
	if (isset($params['delayed_expand'])) {
		foreach ($params['delayed_expand'] as $v) {
			if (isset($params['delayed_expand_sort'][$v])) {
				$a = str_replace("{{delayed_{$v}}}", $ut->expand_sorted($v), $a);
			} else {
				$a = str_replace("{{delayed_{$v}}}", $ut->expand($v), $a);
			}
		}
	}

	if ($ret) {
		return $a;
	}

	if (isset($params['hook']['after_expand']) && is_array($params['hook']['after_expand'])) {
		// 表示バッファ展開後のhook関数
		foreach ($params['hook']['after_expand'] as $v) {
			if (function_exists($v)) {
				$v($a);
			}
		}
		//unset($params['hook']['after_expand']);
	}

	if (isset($_POST['ajax']) && $_POST['ajax'] != 'css_compile')  {
		$r = array('result'=>false, 'error'=>'ajax');
		echo(json_encode($r));
		exit();
	}

	echo($a);
	return true;
}

function expand_buff( &$buff )
{
	global $params,$config,$ut;

	$r = $params['magic_number'];
	$p = get_template_values();
	$p.= '$buff = <<<EOT'.$r."\n";
	$p.= $buff;
	$p.= "\nEOT$r;\n";

	if (isset($_GET['debug'])) { return; }
	try {
		if (check_rights('edit')) {
			if (!isset($params['_hnd_sd'])) {
				register_shutdown_function( 'shutdown_handler' );
				$params['_hnd_sd'] = 1;
			}
			$params['_hnd_eval'] = 2;
		}
		if (!isset($_GET['x'])) {
			reject_func($p);
			if (empty($config['disable_expand'])) {
				@eval($p);
			}
		}
		$params['_hnd_eval'] = 0;
	} catch (Exception $e) {
	}

	return;
}

function dump( $r, $key='', $name='', $count=1, $frame=1 )
{
	$buff = '';
	if (!check_rights()) {
		return;
	}
	if (!$r) {
		if ($name) {
			if (is_array($r)) { $r = '[empty]';};
			return "<div><span style='color:#808080'>$name</span> : $r</div>";
		} else {
			return '<div>none</div>';
		}
	}

	$buff.= "<div style='color:#000;'>";
	if (is_object($r)) {
		if ( !is_numeric($key) && $key ) {
			$buff.= "<div style='border: solid 1px #8496A8;margin-bottom:5px;background-color:#FFFAF0'>";
			$buff.= "<div style='border-bottom: solid 1px #8496A8;padding:2px 2px 2px 10px;margin-bottom:2px;;background-color:#F0F8FF '>$name</div>";
			$buff.= "<div style='padding:5px 5px 5px 10px;margin-bottom:5px;'>";
		} else {
			$buff.= "<div style='border: solid 1px #8496A8;margin-bottom:5px;background-color:#FFFAF0'>";
			$buff.= "<div style='padding:5px 5px 5px 10px;margin-bottom:5px;'>";
		}
		foreach( $r as $k => $v ) {
			$buff.= dump( $v, $k, $name.'->'.$k, 1, 0 );
		}
		$buff.= "</div>";
		$buff.= "</div>";

	} else if (is_array($r)) {
		$f = $count > 1;
		$c = count($r);
		if ($f) {
			if (!is_numeric($key) && $key) {
				$buff.= "<div style='border: solid 1px #8496A8;margin-bottom:5px;background-color:#FFFFF0'>";
				$buff.= "<div style='border-bottom: solid 1px #8496A8;padding:2px 2px 2px 10px;margin-bottom:2px;;background-color:#E6E6FA '>$name</div>";
				$buff.= "<div style='padding:5px 5px 5px 10px;margin-bottom:5px;'>";
			} else {
				$buff.= "<div style='border: solid 1px #8496A8;margin-bottom:5px;background-color:#FFFFF0'>";
				$buff.= "<div style='border-bottom: solid 1px #8496A8;padding:2px 2px 2px 10px;margin-bottom:2px;;background-color:#E6E6FA '>$name</div>";
				$buff.= "<div style='padding:5px 5px 5px 10px;margin-bottom:5px;'>";
			}
		} else {
			if ($key) {
				$buff.= "<div style='border: solid 1px #8496A8;margin-bottom:5px;background-color:#FFFFF0'>";
				$buff.= "<div style='border-bottom: solid 1px #8496A8;padding:2px 2px 2px 10px;margin-bottom:2px;;background-color:#E6E6FA '>$key</div>";
				$buff.= "<div style='padding:5px 5px 5px 10px;margin-bottom:5px;'>";
				$f = true;
			}
		}
		foreach ($r as $k => $v) {
			$n = "[$k]";
			if ($c == 1) { $n =''; }
			$buff .= dump( $v, $k, $n, $c, 0 );
		}
		if ($f) {
			$buff.= "</div>";
			$buff.= "</div>";
		}

	} else if (is_numeric($r)) {
		if (!is_numeric($key) || $key) {
			$buff.= "<span style='color:#808080'>[$key]</span> : $r<br>";
		} else {
			$buff.= safe_echo($r).'<br>';
		}

	} else {
		$buff.= "<span style='color:#808080'>[$key]</span> : ".safe_echo($r)."<br>";
	}
	$buff.= '</div>';
	if ($frame) {
		return "<div style='border:2px solid #B8B8B8;padding:5px;margin:5px;background-color: #FFF;'>$buff</div>";
	}
	return $buff;
}

function system_error( $file, $line )
{
	if (check_rights('edit')) {
		exit_proc(0, "system error : ($file) - $line");
	}
	exit_proc(0, "system error : $line ");
}

function basic_initialize()
{
	global $params,$database,$p_circle,$ut;
	global $config,$html;

	check_agent();

	if (!isset($config['permission'])) {
		$config['permission'] = 0777;
	}

	if (DATABASE_TYPE == 'mysql') {
		$database = new DataBase(DATABASE_TYPE,DATABASE_UESR,DATABASE_PASS,DATABASE_HOST);
	} else {
		$database = new DataBase(DATABASE_TYPE,'','','',DATABASE_DIR);
	}
	if (isset($config['write-lock'])) {
		$database->write_lock = true;
	}

	if (!$database) {
		exit_proc(400, "database support error");
	}

	if (!$database->open(DBName)) {
		exit_proc(400, "database open error");
	}

	if (isset($config['sql_debug'])) {
		$database->debug();
	}

	$params['now'] = date('Y-m-d H:i:s', time());
	if (isset($config['folder-systag'])) {
		$params['folder-systag'] = $config['folder-systag'];
	}

	$html = array();

	// ページパラメーター１次チェック（URL解析/分解） -->
	$request = $params['url_prefix'] = $params['request_tail'] = '';
	$request .= $_SERVER['SERVER_NAME'];
	if ($_SERVER["SERVER_PORT"] != 80 && $_SERVER["SERVER_PORT"] != 443) {
		$request .= ':'.$_SERVER['SERVER_PORT'];
	}

	if ($request != $_SERVER['HTTP_HOST'] && $_SERVER['SERVER_NAME'] == 'localhost') {
		$request = $_SERVER['HTTP_HOST'];	// for OneThird Web Server
	}
	if ($request != $_SERVER['HTTP_HOST']) {
		if (substr($_SERVER['HTTP_HOST'],-3) != ':80' && substr($_SERVER['HTTP_HOST'],0,strlen($_SERVER['HTTP_HOST'])-3) != $request) {
			$ut = new Ut($html, $database, $p_circle, $params, $config);
			exit_proc(403, "URL mismatch error, $request : ".$_SERVER['HTTP_HOST']);
		}
	}

	if (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] != 'off') {
		$request = 'https://'.$request;
		$u = $config['site_ssl'];
	} else {
		$request = 'http://'.$request;
		$u = $config['site_url'];
	}

	$request .= $params['safe_request'] = $_SERVER['REQUEST_URI'];
	if ($request != $config["site_url"]) {	//サーバーがurl正規化の場合ajax通信が失敗する件
		$request = rtrim($request,'/');
	}
	$params['safe_request'] = safe_echo($request);
	if (!isset($config['check_request_uri'])) {
		if (strstr($_SERVER['REQUEST_URI'],'<')!==false || stristr($_SERVER['REQUEST_URI'],'%')!==false) {
			$params['safe_request'] = 'error check_request_uri';
		}
	} else {
		if ($config['check_request_uri']===false) {
			$params['safe_request'] = $_SERVER['REQUEST_URI'];
		}
	}
	$params['request'] = $request;
	$params['request_name'] = strtok($params['safe_request'],'?');

	$request = preg_replace("/[?#].*$/","",$request);

	$params['request_top'] = $params['request_mid'] = '';
	if (isset($_GET['circle'])) {
		$params['request_top'] = (int)$_GET['circle'];
		$params['request_mid'] = $params['request_tail'] = '';
	} else {
		$params['request_tail'] = mb_substr($request,mb_strlen($u));
		if (!$params['request_tail']) {
			$params['request_tail'] = '';
		}
		if (preg_match('/([^\/]*)(.*\/)([^\/]*$)/mu', $params['request_tail'], $m)) {
			$params['request_top'] = $m[1];
			$params['request_mid'] = $m[2];
			$params['request_tail'] = $m[3];
			if ($params['request_mid'] == '/') {
				$params['request_mid'] = '';
			}
			if (!$params['request_tail']) {
				if ($params['request_mid']) {
					$params['request_tail'] = trim($params['request_mid'],'/');
					$params['request_mid'] = '';
				} else {
					if ($params['request_top']) {
						$params['request_tail'] = trim($params['request_top'],'/');
						$params['request_top'] = '';
					}
				}
			}
		}
	}

	if (!$params['request_tail'] && !$params['request_top']) {
		if (!isset($config['default_circle'])) {
			exit_proc(403, "default site error");
		}
		$p_circle = $config['default_circle'];
		$ar = $database->sql_select_all("select metadata,owner,name,join_flag,id,cid,public_flag from ".DBX."circles where id=? ", (int)$p_circle);

	} else {
		if ($params['request_top']) {
			$p_circle = $params['request_top'];
		} else {
			$p_circle = $params['request_tail'];
		}
		$ar = $database->sql_select_all("select metadata,owner,name,join_flag,id,cid,public_flag from ".DBX."circles where id=? or cid=? ",(int)$p_circle, $p_circle);
		if ($ar) {
			$p_circle = $ar[0]['id'];
			if ($params['request_top']) {
				$params['request_top'] = '';
				if ($params['request_mid'] == '/') { $params['request_mid'] = ''; }
			} else {
				$params['request_tail'] = '';
			}
		} else {
			$p_circle = $config['default_circle'];
			$ar = $database->sql_select_all("select metadata,owner,name,join_flag,id,cid,public_flag from ".DBX."circles where id=? ", (int)$p_circle);
		}
	}

	$params['url_prefix'] = sanitize_str(urldecode($params['request_top'].$params['request_mid']));
	$params['request_tail'] = urldecode($params['request_tail']);	// 漢字URL対応

	if (isset($params['request_tail'])) {
		//画像リクエストは並列で来るのでログインシステムを通さない
		if ($params['request_tail'] == 'img.php') {
			$u = $config['files_url']."img/{$_GET['p']}/{$_GET['i']}";
			header("Location:".$u);
			exit();
		}
	}

	if ($ar) {
		$ut = new Ut($html, $database, $p_circle, $params, $config);

		$params['circle']['owner'] = $ar[0]['owner'];
		$params['circle']['name'] = $ar[0]['name'];
		$params['circle']['meta'] = unserialize64($ar[0]['metadata']);

		$params['circle']['join_flag'] = $ar[0]['join_flag'];
		$params['circle']['public_flag'] = $ar[0]['public_flag'];
		if (isset($params['circle']['meta']['owner_name'])) {
			$params['circle']['owner_name'] = $params['circle']['meta']['owner_name'];
		}
		$params['circle']['hide'] = true;


		$params['circle']['files_url'] = $config['files_url'].$ar[0]['id'].'/';
		$params['circle']['files_path'] = $config['files_path'].DIRECTORY_SEPARATOR.$ar[0]['id'];
		if (!isset($params['data_url'])) {
			$params['data_url'] = $params['circle']['files_url'].'data/';
		}
		if (!isset($params['files_url'])) {
			$params['files_url'] = $config['site_url'].'files/'.$p_circle.'/';
		}
		if (!isset($params['image_url'])) {
			$params['image_url'] = $config['site_url'].'files/img/';
		}
		if (!isset($params['files_path'])) {
			$params['files_path'] = $config['site_path'].DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$p_circle;
		}
		if (!isset($params['image_path'])) {
			$params['image_path'] = $config['site_path'].DIRECTORY_SEPARATOR.'img';
		}

		if (isset($params['request_tail'])) {
			//画像リクエストは同時に来るのでログインシステムを通さない
			if ($params['request_tail'] == 'timg.php') {
				$u = $params['data_url']."{$_GET['p']}/{$_GET['i']}";
				header("Location:".$u);
				exit();
			}
		}

		if ($ar[0]['cid']) {
			$params['circle']['url'] = $config['site_url'].$ar[0]['cid'].'/';
			$p_circle = $ar[0]['id'];
			$params['circle']['cid'] = $ar[0]['cid'];
		} else {
			$params['circle']['url'] = $config['site_url'].$p_circle.'/';
			$params['circle']['cid'] = $p_circle;
		}
		if ($config['default_circle'] == $ar[0]['id']) {
			$params['circle']['url'] = $config['site_url'];
		}
		$params['func_right'] = array();

		login_user_ctrl();

		if (isset($_SESSION['login_id'])) {
			if ($_SESSION['login_id'] == $params['circle']['owner']) {
				$params['circle']['is_owner'] = true;
				provide_edit_rights();			// 編集を可能にする

			} else {
				if ($config['admin_user'] == $_SESSION['login_id']) {
					provide_edit_rights();			// 編集を可能にする
				}
			}

			$ar = $database->sql_select_all("select circle,acc_right from ".DBX."joined_circle where user=? and circle=? ", $_SESSION['login_id'], $p_circle);
			if (!$ar) {
				$right = 0x8;		// guest
				if (isset($params['circle']['is_owner']) && $params['circle']['is_owner']) {
					$right = 0x3;		// admin + edit
				}
				$database->sql_update("insert into ".DBX."joined_circle (user,circle,acc_right) values(?,?,?)", $_SESSION['login_id'], $p_circle, $right);		//
				$ar = $database->sql_select_all("select circle,acc_right from ".DBX."joined_circle where user=? and circle=? ", $_SESSION['login_id'], $p_circle);
			}

			if ($ar) {
				$params['circle']['is_joined'] = true;
				$params['circle']['hide'] = false;
				$params['circle']['acc_right'] = $ar[0]['acc_right'];		// admin = 0x01(bit0) , edit = 0x02(bit1), sys = 0x4(bit2), guest = 0x8(bit3)

				if (check_rights('edit')) {
					error_reporting(E_ALL|E_WARNING);
					ini_set("display_errors", 1);
					provide_edit_rights();		// 編集を可能にする

				} else if (check_rights()) {
					provide_onethird_object();
				}
			}
			if (!check_rights('edit')) {	// 一般ユーザーにはエラー表示させない
				error_reporting(0);
				ini_set("display_errors", 0);
			}
		}

		if ($params['circle']['join_flag'] != 0) {
			$params['circle']['hide'] = false;
		}

	} else {
		exit_proc(0, "Not Found (site $p_circle)");
	}

}

function provide_edit_rights()
{
	global $config;
	require_once($config['site_path'].'/module/utility.edit.php');
	provide_onethird_object();
}

function provide_onethird_object()
{
	global $params, $html;
	if (isset($html['head']['provide_edit_rights'])) { return; }
	if (!isset($params['login_user']['meta']['magic_str'])) {
		// とりあえずダミーを設定する
		$params['login_user']['meta']['magic_str'] = md5($params['magic_number']);
	}

$html['meta']['provide_edit_rights'] = <<<EOT
	<script>
		if (!window.ot) { window.ot = {}; }
		ot.magic_str = '{$params['login_user']['meta']['magic_str']}';
		ot.ajax = function(a) {
			a.data += '&xtoken='+ot.magic_str;
			a.success2 = a.success;
			a.success = undefined;
			if (a.error) {
				a.error2 = a.error;
				a.error = undefined;
			}
			\$.ajax(a).then(
				function(x){
					if (x && x['xtoken-error']) {
						alert('token error');
						return;
					}
					this['success2'](x);
				}
				, function(xhr){
					if (this['error2']) {
						this['error2'](xhr);
					} else {
						alert('ajax error');
					}
					return;
				}
			);
		};
	</script>
EOT;
}

function login_user_ctrl()
{
	global $params, $database, $config;

	$params['login_user'] = false;

	if (isset($_SESSION['login_id'])) {
		// パラメータチェック＆GET
		$ar = $database->sql_select_all("select mailadr,id,name,nickname,login_mode,img,metadata from ".DBX."users where id=?  ",$_SESSION['login_id']);
		if ($ar && $ar[0]) {
			set_user_params($ar);
			if (!isset($_COOKIE['otx0']) || !isset($params['login_user']['meta']['tokens2'][$_COOKIE['otx0']])) {
				if (!isset($params['login_user']['meta']['https_otoken']) || !isset($_COOKIE['otx2']) || $params['login_user']['meta']['https_otoken'] != $_COOKIE['otx2']) {
					system_logout();
				}
				return false;
			}
			if (isset($params['login_user']['meta']['magic_str'])) {
				return true;
			}
		}
	}
	$login_ok = false;

	// ハッシュ値より自動ログイン
	if (isset($params['circle']['meta']['notsave_cookie'])) {
		return false;
	}

	if (!isset($_COOKIE['otx0']) || !isset($_COOKIE['otx1'])) {
		return false;
	}
	$sns_crypt = $_COOKIE['otx0'];
	$sns_crypt2 = $_COOKIE['otx1'];
	$magic_str = md5($params['magic_number']);

	if (isset($params['circle']['meta']['use_loginip'])) {
		if ($sns_crypt2 != hash('sha1',$_SERVER["REMOTE_ADDR"])) {
			if (isset($params['circle']['meta']['login_write'])) {
				add_actionlog("[login failed] ".__LINE__ ." : {$_SERVER['REMOTE_ADDR']}");
			}
			system_logout();
			return false;
		}
	}

	if (!isset($params['circle']['meta']['mult_login'])) {
		$ar = $database->sql_select_all("select id,name,nickname,img,login_mode,metadata,mailadr from ".DBX."users where sns_crypt=? ",$sns_crypt);
		if ($ar && $ar[0]) {
			set_user_params($ar);
			$login_ok = true;

		} else {
			// 他のPCなどからログインしたか、過去にログインしていない
			// データベースからの読み込みができない
			system_logout();
			return false;
		}

	} else {
		if (isset($_COOKIE['otx_id'])) {
			$ar = $database->sql_select_all("select id,name,nickname,img,login_mode,metadata,mailadr from ".DBX."users where id=? ", $_COOKIE['otx_id']);
			if ($ar && $ar[0]) {
				$m = unserialize($ar[0]['metadata']);
				if (isset($m['tokens2']) && isset($m['tokens2'][$sns_crypt])) {
					set_user_params($ar);
					$login_ok = true;
				}
			}
		}
		if (!$login_ok) {
			system_logout();
			return false;
		}
	}

	if ($login_ok) {
		if (isset($params['circle']['meta']['login_write'])) {
			add_actionlog("[cookie-login] {$_SERVER['REMOTE_ADDR']}");
		}
		// ログイン時間の記録
		$sns_crypt_old = $sns_crypt;
		$sns_crypt = hash('sha1', $_SESSION['login_id'].$_SERVER['REQUEST_TIME']);
		$d = $params['now'];

		if (isset($params['circle']['meta']['mult_login'])) {
			set_user_token($sns_crypt, $sns_crypt_old);
			set_cookie("otx0", $sns_crypt);
			set_cookie("otx_id", $_SESSION['login_id']);
		}
		$params['login_user']['meta']['magic_str'] = $magic_str;
		$database->sql_update("update ".DBX."users set login_date=?,sns_crypt=?,metadata=? where id=?",$d,$sns_crypt,serialize($params['login_user']['meta']),$_SESSION['login_id']);
		set_cookie("otx1", $sns_crypt2);
		return true;
	} else {
		if (isset($params['circle']['meta']['login_write'])) {
			add_actionlog("[cookie-login failed] ".__LINE__ ." : {$_SERVER['REMOTE_ADDR']}");
		}
	}

	return false;
}

function set_user_token($sns_crypt, $old = false)
{
	global $params,$agent;
	if (isset($params['login_user']['meta']['tokens'])) {
		unset($params['login_user']['meta']['tokens']);
	}
	if ($old && isset($params['login_user']['meta']['tokens2'][$old])) {
		unset($params['login_user']['meta']['tokens2'][$old]);
	}
	if (isset($params['login_user']['meta']['tokens2'][$sns_crypt])) {
		unset($params['login_user']['meta']['tokens2'][$sns_crypt]);
	}
	if (!isset($params['login_user']['meta']['tokens2'])) {
		$params['login_user']['meta']['tokens2'] = array();
	}
	while (count($params['login_user']['meta']['tokens2']) > 5) {
		array_shift($params['login_user']['meta']['tokens2']);
	}
	$a = $agent;
	$ua = (isset($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : '';
	if (!$a) {
		$a = "---";
		if (preg_match("/Firefox/i", $ua)) {
			$a = "firefox";
		} else if (preg_match("/MSIE|Trident/i", $ua)) {
			$a = "ie";
		} else if (preg_match("/chrome/i", $ua)) {
			$a = "chrome";
		}
	}
	$params['login_user']['meta']['tokens2'][$sns_crypt] = array('ip'=>$_SERVER["REMOTE_ADDR"], 'ag'=>$a, 'date'=>$params['now']);
}

function set_user_params( &$ar )
{

	global $params, $config, $ut;

	if (isset($ar[0]['metadata'])) {
		$params['login_user']['meta'] = unserialize($ar[0]['metadata']);
	} else {
		$params['login_user']['meta'] = array();
	}

	$_SESSION['login_id'] = $ar[0]['id'];
	$_SESSION['login_mode'] = $ar[0]['login_mode'];
	$_SESSION['login_home'] = $config['site_url'];
	$_SESSION['login_name'] = $ar[0]['name'];

	$params['login_user']['id'] = $ar[0]['id'];
	$params['login_user']['name'] = $ar[0]['name'];
	if (!empty($ar[0]['nickname'])) {
		$params['login_user']['nickname'] = $ar[0]['nickname'];
	} else {
		$params['login_user']['nickname'] = $ar[0]['name'];
	}
	$params['login_user']['mailadr'] = $ar[0]['mailadr'];

	if ($ar[0]['login_mode'] != 2 && (!$ar[0]['mailadr'] || isset($params['login_user']['meta']['not_auth']))) {
		$params['login_user']['none_mailadr'] = true;
	}

	$_SESSION['login_img'] =  get_user_image($ar[0]);

}

function system_logout()
{
	global $config,$database,$params;

	if (isset($_SESSION['login_id'])) {
		$ar = $database->sql_select_all("select mailadr,id,name,nickname,login_mode,img,metadata,sns_crypt from ".DBX."users where id=?  ",$_SESSION['login_id']);
		if ($ar && $ar[0]) {
			set_user_params($ar);
			$old = $ar[0]['sns_crypt'];
			$d = $params['now'];
			if (isset($params['circle']['meta']['mult_login'])) {
				if ($old && isset($params['login_user']['meta']['tokens2'][$old])) {
					unset($params['login_user']['meta']['tokens2'][$old]);
				}
			}
			unset($params['login_user']['meta']['https_otoken']);
			$sns_crypt = hash('sha1', $_SESSION['login_id'].$_SERVER['REQUEST_TIME']);
			$database->sql_update("update ".DBX."users set login_date=?,sns_crypt=?,metadata=? where id=?",$d,$sns_crypt,serialize($params['login_user']['meta']),$_SESSION['login_id']);
		}
	}
	$path='/';
	if (isset($config['site']['cookie_path'])) {
		$path = $config['site']['cookie_path'];
	}

	$exp = $_SERVER['REQUEST_TIME']-3600;
	setcookie("otx0", "", $exp, $path, $_SERVER["HTTP_HOST"], 1);
	setcookie("otx_id", "", $exp, $path, $_SERVER["HTTP_HOST"], 1);
	setcookie("otx1", "", $exp, $path, $_SERVER["HTTP_HOST"], 1);
	setcookie("otx_i", "", $exp, $path, $_SERVER["HTTP_HOST"], 1);
	setcookie("otx_m", "", $exp, $path, $_SERVER["HTTP_HOST"], 1);
	setcookie("show_circle", "", $exp, $path, $_SERVER["HTTP_HOST"], 1);

	setcookie("otx0", "", $exp, $path);
	setcookie("otx_id", "", $exp, $path);
	setcookie("otx1", "", $exp, $path);
	setcookie("otx_i", "", $exp, $path);
	setcookie("otx_m", "", $exp, $path);
	setcookie("show_circle", "", $exp, $path);

	if ($path=='/') {
		$x = parse_url($_SERVER['REQUEST_URI']);
		if (!empty($x['path'])) {
			$x = rtrim($x['path'],'/');
			setcookie("otx0", "", $exp, $x);
			setcookie("otx_id", "", $exp, $x);
			setcookie("otx1", "", $exp, $x);
			setcookie("otx_i", "", $exp, $x);
			setcookie("otx_m", "", $exp, $x);
			setcookie("show_circle", "", $exp, $x);
		}
	}

	if (isset($_COOKIE[session_name()])) {
	    setcookie(session_name(), '', time()-42000, '/');
	}
	session_destroy();
}

function save_loginmode( $a )
{
	global $params;

	if (!isset($_COOKIE['otx_m'])) {
		$otx_m = 0;
	} else {
		$otx_m = $_COOKIE['otx_m'];
	}

	$otx_m |= 1<<$a;
	set_cookie("otx_m", $otx_m, $_SERVER['REQUEST_TIME']+60*60*24*365);					// 1年
	//set_cookie("otx_i", $_SESSION['login_id'], $_SERVER['REQUEST_TIME']+60*60*24*365);	// 1年
}

function check_right( $mode='' )
{
	global $html;
	$html['information'][]='check_right error';
	return check_rights($mode);
}

function check_rights( $mode='' )
{
	// パラメータなしはログイン中を意味
	global $params, $config;

	if (!isset($_SESSION['login_id'])) {
		return false;
	}

	if ($mode == 'super') {
		if ($config['admin_user'] == $_SESSION['login_id']) {
			return true;
		}
		return false;
	}

	if (isset($params['circle']['acc_right'])) {
		$acc = $params['circle']['acc_right'];
	} else {
		$acc = 0;
	}
	if ($mode == 'owner') {
		$r = !empty($params['page']['user']) && $_SESSION['login_id'] == $params['page']['user'];
		if ($r) {
			return true;
		}
		if (!empty($config['admin-rights'])) {
			//admin
			if ($acc & 0x01) {
				return strpos($config['admin-rights'],'owner') !== false;
			}
		}
		return false;
	}
	if ($mode == 'admin') {
		return $acc & 0x01;
	}
	if ($mode == 'edit') {
		return $acc & 0x01 || $acc & 0x02;
	}
	if ($mode == 'guest') {
		return $acc & 0x8;
	}
	return true;
}

function check_user( $id )
{
	if (!isset($_SESSION['login_id'])) {
		return false;
	}
	return $_SESSION['login_id'] == $id;
}

function check_agent()
{
	global $agent;

	if (!isset($_SERVER['HTTP_USER_AGENT'])) {
		$agent = '';
		return;
	}

	$a = $_SERVER['HTTP_USER_AGENT'];

	if (strpos($a,'Googlebot') !== false) {
		$agent = 'bot google';

	} else if (strpos($a,'FeedFetcher') !== false) {
		$agent = 'bot FeedFetcher';

	} else if (strpos($a,'+http') !== false) {
		//その他のロボット
		$agent = 'bot+';

	} else if (strpos($a,'bot') !== false) {
		$agent = 'bot';

	} else if (stripos($a,'spider') !== false) {
		$agent = 'bot';

	} else if (strpos($a,'crawler') !== false) {
		$agent = 'bot';

	} else if (strpos($a,'Android') !== false) {
		$agent = 'Android';

	} else if (strpos($a,'iPhone') !== false) {
		$agent = 'iPhone';

	} else if (strpos($a,'iPad') !== false) {
		$agent = 'iPad';

	} else if (strpos($a,'MSIE') !== false) {
		$agent = 'ie';

	} else  {
		//その他
		$agent = '';

	}
}

function set_cookie($key, $v, $exp=null, $path='/')
{
	global $config;
	if (isset($config['site']['cookie_path'])) {
		$path = $config['site']['cookie_path'];
	}
	if (!$exp) {
		$exp = $_SERVER['REQUEST_TIME']+60*60*24*365;	// 1週間 -> 1年間 2013/01/26変更
	} else {
		if (is_numeric($exp) && $exp <= 365) {
			$exp = $_SERVER['REQUEST_TIME']+60*60*24*$exp;
		}
	}
	if (substr($config['site_ssl'],0,6) == 'https:') {
		@setcookie($key, $v, $exp, $path, $_SERVER["HTTP_HOST"], 1);
		return;
	}
	$_COOKIE[$key]=$v;
	@setcookie($key, $v, $exp, $path);
}

function remove_http($s)
{
	return preg_replace("/^(http|https):/", '', $s);
}

function add_actionlog( $mess )
{
	global $html, $database, $p_circle, $params;
	if (!$database->sql_update("insert into ".DBX."action_log (date,circle,type,data) values('{$params['now']}',?,1,?)", $p_circle, adjust_mstring($mess,500))) {
		return false;
	}
	return $database->lastInsertId();
}

function exit_proc( $code = 404, $mess = '', $home_option = true )
{
	global  $params, $config, $ut, $html;

	if (isset($params['static_outmode'])) {
		$params['static_outmode_stop'] = true;
		return;
	}

	$params['exit_code']=$code;
	$p = "exit{$code}.html";
	$p = $params['files_path'].DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.$p;
	if (is_file($p)) {
		@include_once($p);
		exit();
	}
	$p = "exit.php";
	$p = $params['files_path'].DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.$p;
	if (is_file($p)) {
		@include_once($p);
		exit();
	}

	if ($code == 404) {
		header("HTTP/1.0 404 Not Found");
		if (!$mess) {
			$mess = 'ページがみつかりません - 404';
		}

	} else if ($code == 403) {
		header("HTTP/1.0 403 Forbidden");
		if (!$mess) {
			$mess = '閲覧権限がありません - 403';
		}

	} else if ($code == 0) {
		if (isset($params['circle']['meta']['site_error'])) {
			$a = $params['circle']['meta']['site_error'];
			if ($a == 0) {
				header("Location:{$config['site_url']} ");

			} else if ($a == 2) {
				if (!$mess) {
					$mess = 'ページがみつかりません (200)';
				}

			} else if ($a == 3) {
				Header( "HTTP/1.1 301 Moved Permanently" );
				header("Location:{$params['circle']['url']} ");
				if (!$mess) {
					$mess = 'ページは移動しました (301)';
				}

			} else if ($a == 4) {
				header("HTTP/1.0 404 Not Found");
				if (!$mess) {
					$mess = 'ページがみつかりません (404)';
				}

			} else {
				if (!$mess) {
					$mess = 'Not Found';
				}
			}
		}
	} else {
		header("HTTP/1.0 $code");
		if (!$mess) {
			$mess = "Page Error ($code)";
		}
	}
	$icon = "<img src='{$config['site_url']}img/caution.png' alr='alert' />";
	if ($home_option) {
		if ($ut) {
			$u = $ut->link();
		} else {
			$u = '';
		}
		$btn = 'OK';
		if (is_array($home_option) === true) {
			if (isset($home_option['caption'])) {
				$btn = $home_option['caption'];
			}
			if (isset($home_option['href'])) {
				$u = $home_option['href'];
			}
			if (isset($home_option['icon'])) {
				if (!$home_option['icon'] || $home_option['icon'] == 'none') {
					$icon = '';
				} else {
					$icon = $home_option['icon'];
				}
			}
		}
		$home = "<div class='home ' ><input type='button' onclick='location.href=\"{$u}\"' value='$btn' /></div>";
	} else {
		$home = '';
	}

	$robots = '';
	if (!empty($html['head']['robots'])) {
		$robots = $html['head']['robots'];
	}

echo <<<EOT
<!DOCTYPE html>
<html lang="ja">
	<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	$robots
	<title>OneThird-CMS</title>
	<style>
		body {
			  background-color: #CACACA;
		}
		.main {
			max-width:500px;
			margin:50px auto 0 auto;
			text-align: center;
		}
		.panel {
			margin:0 auto 0 auto;
			background-color:#fff;
			border: 7px solid #555454;
			-webkit-border-radius: 15px;
			-moz-border-radius: 15px;
			border-radius: 15px;
			-webkit-box-shadow: 0px 0px 6px #272727;
			-moz-box-shadow: 0px 0px 6px #272727;
			box-shadow: 0px 0px 6px #272727;
			text-align: center;
			display: inline-block;
		}
		.icon {
			padding:15px 0 10px 0;
		}
		.mess {
			font-family: 'Meiryo','Helvetica','Verdana';
			padding:2px 20px 26px 20px;
			line-height: 150%;
			text-align: left;
		}
		.home {
			padding:2px 20px 26px 20px;
			text-align: center;
		}
		.home input {
			font-family: 'Meiryo','Helvetica','Verdana';
			padding: 5px 19px;
		}
	</style>
	</head>
	<body>
		<div class='main'>
			<div class='panel'>
				<div class='icon'>$icon</div>
				<div class='mess'>$mess</div>
				$home
			</div>
		</div>
	<body>
<html>
EOT;

	exit();
}

function copy_dir10($src_dir, $dst_dir, $over_write=true, $nest=0 )
{
	global $config;
	if (++$nest > 10) {	// magic number
		--$nest;
		return true;	// エラーを出さずに中断する
	}
	$src_dir = rtrim($src_dir,' /');
	$dst_dir = rtrim($dst_dir,' /');
	if (!is_dir($dst_dir)) {
		mkdir($dst_dir, $config['permission'], true);
	}
	if (is_dir($src_dir)) {
		if ($h = opendir($src_dir)) {
			while (($f = readdir($h)) !== false) {
				if ($f == "." || $f == "..") {
					continue;
				}
				if ($over_write || !file_exists($dst_dir.DIRECTORY_SEPARATOR.$f)) {
					if (is_dir($src_dir.DIRECTORY_SEPARATOR.$f)) {
						copy_dir10($src_dir.DIRECTORY_SEPARATOR.$f, $dst_dir.DIRECTORY_SEPARATOR.$f, $over_write, $nest);
					} else {
						copy($src_dir.DIRECTORY_SEPARATOR.$f, $dst_dir.DIRECTORY_SEPARATOR.$f);
					}
				}
			}
			closedir($h);
		}
	}
		--$nest;
	return true;
}

function scan_dir10($src_dir, &$dir_ar, $nest=0)
{
	global $config;
	if ($nest === 0) {
		$dir_ar = array();
	}
	if (++$nest > 10) {	// magic number
		--$nest;
		return true;	// エラーを出さずに中断する
	}
	$src_dir = rtrim($src_dir,' /'.DIRECTORY_SEPARATOR);
	if (is_dir($src_dir)) {
		if ($h = opendir($src_dir)) {
			while (($f = readdir($h)) !== false) {
				if ($f == "." || $f == "..") {
					continue;
				}
				if (is_dir($src_dir.DIRECTORY_SEPARATOR.$f)) {
					scan_dir10($src_dir.DIRECTORY_SEPARATOR.$f, $dir_ar, $nest);
				} else {
					$dir_ar[] = $src_dir.DIRECTORY_SEPARATOR.$f;
				}
			}
			closedir($h);
		}
	}
	--$nest;
	return true;
}

function get_user_image($user_id = false)
{
	global $database, $ut, $config;
	if (!$user_id) {
		return $_SESSION['login_img'];
	}
	if (is_array($user_id)) {
		$ar = $user_id;
	} else {
		$ar = $database->sql_select_all("select img from ".DBX."users where id=?", $user_id);
		if (!$ar) {return '';}
		$ar = $ar[0];
	}
	if ($ar) {
		if (isset($ar['img']) && $ar['img']) {
			if (substr($ar['img'],0,10) == 'img.php?p=') {
				return remove_http($config['site_ssl'].$ar['img']);
			}
			return $ar['img'];
		} else {
			return remove_http($config['site_ssl']."img/personal.png");
		}
	}
	return '';
}

function get_user_avatar()
{
	global $database, $ut, $config;

	$opt = array();
	$opt['login_mode'] = $_SESSION['login_mode'];
	$opt['id'] = $_SESSION['login_id'];
	$opt['size'] = 25;
	$opt['class'] = 'profimg';
	$opt['img'] = get_user_image();
	return "<img src='{$opt['img']}' class='{$opt['class']}' width={$opt['size']} height={$opt['size']}  /> ";

}

function get_user_avatar_ex($opt = false)
{
	global $database, $ut, $config, $p_circle;

	if (!isset($opt['size'])) { $opt['size'] = 25; }
	$opt['size'] = (int)$opt['size'];
	if (empty($opt['class'])) {
		$opt['class'] = 'profimg';
	}
	if (empty($opt['id'])) {
		$opt['id'] = $_SESSION['login_id'];
		$opt['img'] = $_SESSION['login_img'];
	} else {
		$opt['img'] = get_user_image($opt);
	}
	if (empty($opt['name'])) {
		$opt['name'] = get_user_name($opt['id']);
	}
	if (empty($opt['login_mode'])) {
		$opt['login_mode'] = $_SESSION['login_mode'];
	}
	if (empty($opt['alt'])) {
		$opt['alt'] = $opt['name'];
	}
	$t = $opt['login_mode'];
	$opt_str = '';
	if (!empty($opt['opt_str'])) {
		$opt_str = $opt['opt_str'];
	} else {
		if ($t == 2 || $t == 3) {
			$opt_str = " target='_blank' ";
		} else {
			$opt_str = "";
		}
	}

	$img = $opt['img'];
	$name = '';
	$style = " padding:0; margin:0; ";
	if ($opt['size']>0) {
		$style .= "width:{$opt['size']}px ; height:{$opt['size']}px; ";
	}
	if ($t == 2 && $img) {
		$name = "<a href='http://twitter.com/{$opt['name']}' $opt_str style='$style' >";
		$name .= "<img src='$img' class='{$opt['class']}' alt='{$opt['alt']}' width={$opt['size']} height={$opt['size']} alt='{$opt['alt']}' /> ";
		$name .= "</a>";

	} else if ($t == 3 && $img) {
		$name = "<a href='http://www.facebook.com/profile.php?id={$opt['fb_name']}' $opt_str style='$style' >";
		$name .= "<img src='$img' class='{$opt['class']}' alt='{$opt['alt']}' width={$opt['size']} height={$opt['size']} alt='{$opt['alt']}' /> ";
		$name .= ("</a>");

	} else {
		if (substr($img,-12)=='personal.png') {
			if ((isset($opt['acc_right']))?$opt['acc_right']:check_rights('edit')) {
				$style .= " background-color: #006CB5; ";
			} else {
				$style .= " background-color: #222; ";
			}
		}
		$a = "<img src='$img' class='{$opt['class']}' alt='{$opt['alt']}'  style='$style' alt='{$opt['alt']}' />";
		if (check_rights('admin')) {
			$name = "<a href='{$config['site_ssl']}{$config['admin_dir']}/account.php?circle=$p_circle&user={$opt['id']}' $opt_str style='$style' >{$a}</a>";
		} else {
			$name = "<a href='{$config['site_ssl']}{$config['admin_dir']}/account.php?circle=$p_circle' $opt_str style='$style' >{$a}</a>";
		}
	}
	return $name;

}

function avoid_attack($option = null)
{
	global $params, $ut, $config;

	$max_attack_c = 100;	// この回数を超えたら、次の日まで操作できない

	if (is_numeric($option)) {
		$max_attack_c = $option;
	}

	if (check_rights('edit')) {	//ログイン状態だとカウントしない
		return 0;
	}

	$ar = $ut->get_storage('avoid_attack',0);

	if (!$ar) {
		$ar = array();
	}
	if (isset($ar['list'])) {
		$ar = $ar['list'];
	}
	$m = array();

	$ip = $_SERVER["REMOTE_ADDR"];
	$d  = substr($params['now'],0,10);

	$i = 30;	//30 IPまで記録
	foreach ($ar as $k=>$v) {
		if (isset($v['d']) && $v['d'] == $d && !empty($v['c'])) {
			$m[$k] = $v;
		}
		if (--$i < 1) { break; }
	}

	if (isset($m[$ip])) {
		if ($m[$ip]['c'] >= $max_attack_c) {
			// IPアドレスをシャットアウト
			if ($m[$ip]['c'] == $max_attack_c) {
				$ua = (isset($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : '';
				$ua = adjust_mstring(sanitize_str($ua),90);
				$u = $_SERVER['REQUEST_URI'];
				chg_infomail($config['admin_user'], "Illegal access ($ip) \n{$u} \n {$ua}");
				++$m[$ip]['c'];
				$ut->set_storage('avoid_attack',$m,0);
			}
			exit_proc(505, 'Access denied');
		}
		++$m[$ip]['c'];
	} else {
		$m[$ip] = array('d'=>$d, 'c'=>1);
	}
	if (!empty($option['check'])) {
		return $m[$ip];
	}
	if (!empty($option['lock'])) {
		$m[$ip]['c']=$max_attack_c;
	}
	$ut->set_storage('avoid_attack',$m,0);
}

function reset_attack()
{
	global $params, $ut, $config;

	$ar = $ut->get_storage('avoid_attack',0);

	if (!$ar) {
		$ar = array();
	}
	if (isset($ar['list'])) {
		$ar = $ar['list'];
	}
	$m = array();

	$ip = $_SERVER["REMOTE_ADDR"];
	$d  = substr($params['now'],0,10);

	$i = 30;	//30 IPまで記録
	foreach ($ar as $k=>$v) {
		if (isset($v['d']) && $v['d'] == $d && !empty($v['c'])) {
			$m[$k] = $v;
		}
		if (--$i < 1) { break; }
	}

	if (isset($m[$ip])) {
		$m[$ip]['c']=0;
	}
	$ut->set_storage('avoid_attack',$m,0);
}


?>