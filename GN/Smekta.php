<?php


class GN_Smekta
{
    public static function smektuj($txt, $vars, $add_globals = false, $filedir = null, $filename = null)
    {
	
	self::$level++;
        $debug_str = $filename ? $filename : (strstr($txt, "\n") ? 'text(' . strlen($txt) . ')' : $txt);
        	
	$txt = self::_general_replace($txt, $filedir);
        self::clearvars($vars);
	
        $ret = self::_replace_tokens($txt, $vars, $add_globals, $debug_str);
        self::$level--;
	
	
	self::_clear_cache();
	return $ret;
    }

    private static $level=0;
    
    private static $general_cache;
    private static $replace_cache;    
    
    private static function _clear_cache()
    {
	if (self::$level!=0) return;
	
	$max_buffer=1000;
	
	while (is_array(self::$general_cache) && count(self::$general_cache)>$max_buffer ) unset(self::$general_cache[current(array_keys(self::$general_cache))]); 
	while (is_array(self::$replace_cache) && count(self::$replace_cache)>$max_buffer ) unset(self::$replace_cache[current(array_keys(self::$replace_cache))]); 
	
    }
    
    private static function _general_replace($txt, $filedir = null)
    {
        static $dir;
	if ($filedir) $dir = $filedir;

        $token = md5($txt);
	
        if (isset(self::$general_cache[$token])) return self::$general_cache[$token];
	
	
        if (!strstr($txt, "\n") && strlen($txt)) {
            $path = '';
            if ($dir) {
                if (!is_array($dir)) $dir = array($dir);
                foreach ($dir AS $d) {
                    if ($d && substr($txt, 0, 1) != '/') $path = "$d/$txt"; else $path = $txt;

                    if (file_exists($path)) {
                        $txt = file_get_contents($path);
                        break;
                    }
                }
            } else {
                $path = $txt;
                if ($path[0]=='/' && file_exists($path) ) {
                    $dir = dirname($path);
                    $txt = file_get_contents($path);
                }
            }
        }
	
        $txt = preg_replace("#<!--[ ]*loop:([^> -]+)[ ]*-->#", "{loop:\\1}", $txt);
        $txt = preg_replace("#<!--[ ]*with:([^> -]+)[ ]*-->#", "{with:\\1}", $txt);
        $txt = preg_replace("#<!--[ ]*if:([^> -]+)[ ]*-->#", "{if:\\1}", $txt);
        $txt = preg_replace("#<\!--[ ]*end([a-z]+):([^> \-]+)[ ]*-->#", "{end\\1:\\2}", $txt);
        $txt = preg_replace("#<!--[ ]*([a-z_]+)[ ]*-->#", "{\\1}", $txt);
        //$txt=preg_replace("#%7B([^%]*)%7D#","{\\1}",$txt);
        $txt = str_replace('%7B', '{', $txt);
        $txt = str_replace('%7D', '}', $txt);
        $txt = str_replace('%7C', '|', $txt);

        while (1) {
            $__p = $txt;
            $txt = preg_replace("#\[\!(.*)\!\]#", "{\\1}", $__p);
            if (strlen($txt) == strlen($__p)) break;
        }
        self::$general_cache[$token] = $txt;

        return $txt;
    }

    private static function _post_parse_token($token, &$vars, $fun = array(), $param = array(), $default_value = null)
    {
        $is_obj = is_object($token);

        if (!$token && !is_null($default_value)) $token = $default_value;

        for ($f = 0; $f < count($fun); $f++) {
            $method = false;
	    
	    
            if ($is_obj && method_exists($token, $fun[$f])) $method = true;
	    
	    
	    if (!$method && strlen($fun[$f])>1 && !count($param[$f]) && in_array($fun[$f][0],array('*','+','-','.','/','%','&')))
	    {
		$param[$f]=explode(',',substr($fun[$f],1));
		$fun[$f]=$fun[$f][0];
	    }
	    
	    if (!$method && strlen($fun[$f])==1 && count($param[$f]))
	    {
		
		$jest=true;
		switch ($fun[$f])
		{
		    case '*':
			$token = $token *  $param[$f][0];
			break;
		    case '+':
			$token = $token +  $param[$f][0];
			break;
		    case '-':
			$token = $token - $param[$f][0];
			break;
		    case '.':
			$token = $token . $param[$f][0];
			break;
		    case '/':
			if ($param[$f][0]) $token = $token / $param[$f][0];
			else $jest=false;
			break;

		    case '%':
			if ($param[$f][0]) $token = $token % $param[$f][0];
			else $jest=false;
			break;

		    case '&':
			$token = $token & $param[$f][0];
			break;
		    
		    default:
			$jest=false;
		}
		
	    
		
		if ($jest) continue;
	    }
	    
            if (!$method) {
                if (strpos($fun[$f], '.')) {
                    $of = explode('.', $fun[$f]);
		    
                    if (isset($vars[$of[0]]) && is_object($vars[$of[0]]) && method_exists($vars[$of[0]], $of[1])) {
                        
			if (is_array($param[$f])) {
                            $param[$f] = array_merge(array($token), $param[$f]);
                        } elseif (strlen(trim($param[$f]))) {
                            $param[$f] = array($token, $param[$f]);
                        } else $param[$f] = array($token);
			
                        $token = call_user_func_array(array($vars[$of[0]], $of[1]), $param[$f]);
                        continue;
                    }
                }
                if (!function_exists($fun[$f])) continue;
            }

            if (!$method) {
                if (is_array($param[$f])) {
                    $param[$f] = array_merge(array($token), $param[$f]);
                } elseif (strlen(trim($param[$f]))) {
                    $param[$f] = array($token, $param[$f]);
                } else $param[$f] = array($token);

                $token = call_user_func_array($fun[$f], $param[$f]);
            } else {
                $token = call_user_func_array(array($token, $fun[$f]), $param[$f]);
            }
        }

        if (is_array($token)) return self::array_to_string($token);
        if (is_object($token)) return print_r($token, 1);

        return $token;
    }

    /**
     * @param array $array
     * @param string $separator
     * @return string
     */
    private static function array_to_string(array $array, $separator = ',')
    {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $array[$k] = self::array_to_string($v, $separator);
            }
            if (is_object($v)) {
                $array[$k] = '{' . get_class($v) . '}';
            }
        }
        if (is_object($array)) return '{' . get_class($array) . '}';
        if (is_array($array)) return implode($separator, $array);

        return $array;
    }

    private static function _dig_deep_in_array($vars_array, $key_array)
    {
        if (!is_array($key_array)) $key_array = array($key_array);
        $wynik = $vars_array;
        foreach ($key_array AS $key) {
            if (isset($wynik[$key])) $wynik = $wynik[$key];
	    elseif (substr($key,0,1)=='$' && isset($vars_array[substr($key,1)]) ) {
		$key2=$vars_array[substr($key,1)];
		if (isset($wynik[$key2])) $wynik = $wynik[$key2];
		else return null;
	    }
	    else return null;
	    
        }

        return $wynik;
    }

    private static function _dig_deep_in_obj($vars_array, $obj)
    {
        $class = $obj[0];
        $pole = $obj[1];

        return $vars_array[$class]->$pole;
    }

    private static function _assign_variable($txt, &$vars)
    {

        $key = substr($txt, 1);
        foreach ($vars AS $k => $v) if ($k == $key) return $v;

        $txt = preg_replace('/\$([a-z_]+[a-z0-9_]*)/i', '${\\1}', $txt);

        foreach ($vars AS $k => $v) {
            if (!is_array($v) && !is_object($v)) {
                $txt = str_replace('${' . $k . '}', $v, $txt);
            }
        }

        return $txt;

    }

    private static function clearvars(&$vars)
    {
        if (isset($vars['__clear__']) && $vars['__clear__']) return;

        if (!count($vars)) return;

        foreach ($vars AS $k => $v) {
            if (is_null($v)) $vars[$k] = '';
            if (is_string($v)) $vars[$k] = trim($vars[$k]);
            if (is_array($vars[$k]) && "$k" != 'GLOBALS') self::clearvars($vars[$k]);
        }
    }
    
    
    private static function _get_object($parser_token, &$vars)
    {
	$va=$vars;
	
	foreach (explode('.',$parser_token) AS $token) {
	    $found=false;
	    if (!is_array($va) && !is_object($va)) return null;
	    foreach($va AS $k=>$v) {
		if ($k==$token) {
		    $va=$v;
		    $found=true;
		    break;
		}
	    }
	    if (!$found) return null;
	}
	return $va;
    }

    private static function _get_value($parser_token, &$vars, $add_brackets_if_not_found = true)
    {
        $fun = array();
        $param = array();
        $default_value = null;
        $wynik = '';

        $first_check = explode(':', $parser_token);
        if (count($first_check) > 1 && in_array($first_check[0], array('endwith', 'endif', 'endloop'))) return $wynik;

        if (strstr($parser_token, '?') && !strstr($parser_token, "\n")) {
            $_parser_token = explode('?', $parser_token);
            $default_value = $_parser_token[1];
            $parser_token = $_parser_token[0];
        }

        if (strstr($parser_token, '|') && !strstr($parser_token, "\n")) {
            $_parser_token = explode('|', $parser_token);
            $parser_token = $_parser_token[0];

            for ($f = 1; $f < count($_parser_token); $f++) {
                $_parser_token[$f] = str_replace("\\:", '__dwukropek__', $_parser_token[$f]);
                $_parser_token_fun = explode(':', $_parser_token[$f]);
                $_fun = $_parser_token_fun[0];

                if (isset($_parser_token_fun[1])) {
                    $_parser_token_fun[1] = str_replace("\\,", '__przcinek__', $_parser_token_fun[1]);
                    $_param = explode(',', $_parser_token_fun[1]);
                } else $_param = array();
                $_param = str_replace('__przcinek__', ',', $_param);
                $_param = str_replace('__dwukropek__', ':', $_param);

                foreach ($_param AS $i => $_p) {
                    if (strstr($_p, '$')) {
                        $_param[$i] = self::_assign_variable($_p, $vars);
                    }
                }

                $fun[] = $_fun;
                $param[] = $_param;
            }
        }

        if (strstr($parser_token, '.') && !strstr($parser_token, "\n")) {
            $_parser_token = explode('.', $parser_token);
            $parser_token = $_parser_token[0];

            if ($parser_token == 'debug' && !isset($vars['debug'])) {
                if (strstr(implode('.', $_parser_token), '$')) {
                    $_parser_token = explode('.', self::_assign_variable(implode('.', $_parser_token), $vars));
                }
                $wynik .= self::debug($vars, $_parser_token);

            }
            if (isset($vars[$parser_token]) && is_array($vars[$parser_token])) {
                if (isset($vars[$parser_token][$_parser_token[1]])) {
                    $wynik .= self::_post_parse_token(self::_dig_deep_in_array($vars, $_parser_token), $vars, $fun, $param, $default_value);
                } elseif ($default_value) {
                    $wynik .= $default_value;
                }
            }

            if (isset($vars[$parser_token]) && is_object($vars[$parser_token])) {
                $wynik .= self::_post_parse_token(self::_dig_deep_in_obj($vars, $_parser_token), $vars, $fun, $param, $default_value);

            }

        } elseif (isset($vars[$parser_token])) {
            $wynik .= self::_post_parse_token($vars[$parser_token], $vars, $fun, $param);
        } elseif (strstr($parser_token, "\n")) {
            $wynik .= '{' . $parser_token . '}';
        } elseif (!is_null($default_value)) {
            $wynik .= self::_post_parse_token($default_value, $vars, $fun, $param);
        } elseif ($parser_token == 'debug') {
            $wynik .= self::debug($vars);
        } elseif (substr($parser_token, 0, 9) == '__count__' && isset($vars[substr($parser_token, 9)]) && is_array($vars[substr($parser_token, 9)])) {
            $wynik .= count($vars[substr($parser_token, 9)]);
        } elseif (isset($vars['_TOKEN_BLANK']) && $vars['_TOKEN_BLANK']) {
            $wynik .= '';
        } elseif (substr($parser_token,0,3)=='end' && strstr($parser_token,':') ) {
            $wynik .= '';	    
        } elseif ($add_brackets_if_not_found) {
            $wynik .= '{' . $parser_token . '}';
        }

        return $wynik;

    }



    private static function _replace_tokens_simple($token, &$vars, $debug_str)
    {

        $debug_id = self::_debugger(null, 'simple_tokens-' . $debug_str);

        $parser_content = self::$replace_cache[$token]['parser_content'];

        $pc = $parser_content;

        foreach (self::$replace_cache[$token]['tokens'] AS $t) {
            $parser_content = str_replace('{' . $t . '}', self::_get_value($t, $vars), $parser_content);
        }

        self::_debugger($debug_id);

        return $parser_content;
    }

    private static function _replace_tokens($parser_content, &$vars, $add_globals = false, $debug_str = '')
    {
        $token = md5($parser_content);

        if ($add_globals) {
            foreach ($_SERVER AS $k => $v) if (!isset($vars[$k]) && !isset($vars->$k)) @$vars[$k] = $v;
            foreach ($_REQUEST AS $k => $v) if (!isset($vars[$k]) && !isset($vars->$k)) @$vars[$k] = $v;
        } else {
	    $vars['_GET'] = $_GET;
	    $vars['_POST'] = $_POST;
	    $vars['_SERVER'] = $_SERVER;
	    $vars['_COOKIE'] = $_COOKIE;
	}

        if (isset(self::$replace_cache[$token]) && self::$replace_cache[$token]['simple']) return self::_replace_tokens_simple($token, $vars, $debug_str);

        self::$replace_cache[$token]['simple'] = true;
        self::$replace_cache[$token]['parser_content'] = $parser_content;
        self::$replace_cache[$token]['tokens'] = array();

        $parser_startpos = 0;

        $debug_id = self::_debugger(null, 'replace_tokens-' . $debug_str);

        $wynik = '';

        while (1) {
            $parser_content = substr($parser_content, $parser_startpos);
            $parser_proc1 = strpos($parser_content, "{");
            $parser_proc2 = strpos(substr($parser_content, $parser_proc1 + 1), "}");
            $parser_proc3 = strpos(substr($parser_content, $parser_proc1 + 1), "{");

            if (!strlen($parser_proc1) || !strlen($parser_proc2)) {
                $wynik .= $parser_content;
                break;
            }

            if (strlen($parser_proc3) && $parser_proc3 < $parser_proc2) {
                $wynik .= substr($parser_content, 0, $parser_proc1 + 1);
                $parser_startpos = $parser_proc1 + 1;
                continue;
            }

            $parser_token = substr($parser_content, $parser_proc1 + 1, $parser_proc2);
            $parser_startpos = $parser_proc1 + $parser_proc2 + 2;
            $wynik .= substr($parser_content, 0, $parser_proc1);

            if (substr(strtolower($parser_token), 0, 8) == 'include:') {
                $include = substr($parser_token, 8);
                $wynik .= self::smektuj($include, $vars, $add_globals);
                self::$replace_cache[$token]['simple'] = false;
		
	    } elseif (substr(strtolower($parser_token), 0, 7) == 'define:') {
		$var = explode('=',substr($parser_token, 7));
		
		if (count($var)==2) {
		    $vars[$var[0]] = $var[1];
		} else {
		    self::$replace_cache[$token]['simple'] = false;
		    $definename = substr($parser_token, 7);
		    $end_token = strtolower("{enddefine:$definename}");
		    $pos = strpos(strtolower($parser_content), $end_token);

		    if ($pos) {
			$inside_content = substr($parser_content, $parser_startpos, $pos - $parser_startpos);
			$parser_startpos = $pos + strlen($end_token);
    
			$vars[$var[0]]= self::_replace_tokens($inside_content, $vars, $add_globals, 'define (' . $definename . ')');
    
		    }
		    
		}

            } elseif (substr(strtolower($parser_token), 0, 5) == 'with:') {
                self::$replace_cache[$token]['simple'] = false;
                $arrayname = substr($parser_token, 5);

                $end_token = strtolower("{endwith:$arrayname}");
                $pos = strpos(strtolower($parser_content), $end_token);
                if ($pos) {
                    $inside_content = substr($parser_content, $parser_startpos, $pos - $parser_startpos);
                    $parser_startpos = $pos + strlen($end_token);

                    $arrayname_array = explode(':', $arrayname);
                    $arrayname = $arrayname_array[0];

		    $varset=self::_get_object($arrayname,$vars);

		
		    if (is_array($varset)) {    
			foreach ($vars AS $k => $v) {
			    //if (!is_object($v) && !is_array($v) && !isset($varset[$k])) $varset[$k] = $v;
			    if (!isset($varset[$k])) $varset[$k] = $v;
			}
			$varset['__with__'] = $arrayname;
			$wynik .= self::_replace_tokens($inside_content, $varset, $add_globals, 'with (' . $arrayname . ')');
		    }

                }
            } elseif (substr(strtolower($parser_token), 0, 5) == 'loop:') {
                $arrayname = substr($parser_token, 5);

                self::$replace_cache[$token]['simple'] = false;

                $end_token = strtolower("{endloop:$arrayname}");
                $pos = strpos(strtolower($parser_content), $end_token);

                if ($pos) {
                    $inside_content = substr($parser_content, $parser_startpos, $pos - $parser_startpos);
                    $parser_startpos = $pos + strlen($end_token);

                    $arrayname_array = explode(':', $arrayname);

                    $_loop_var = explode('.', $arrayname_array[0]);

                    if (isset($vars[$_loop_var[0]])) {
			if (count($_loop_var) == 1)
			{
			    $loop_var = $vars[$_loop_var[0]];
			}
			
			elseif (count($_loop_var) == 2 && is_array($vars[$_loop_var[0]])) {
			    $loop_var = isset($vars[$_loop_var[0]][$_loop_var[1]]) ? $vars[$_loop_var[0]][$_loop_var[1]] : null; 
			} elseif (count($_loop_var) == 3 && is_array($vars[$_loop_var[0]])) {
			    $loop_var = isset($vars[$_loop_var[0]][$_loop_var[1]][$_loop_var[2]]) ? $vars[$_loop_var[0]][$_loop_var[1]][$_loop_var[2]] : null;
			} else {
			    $loop_var = null;
			    $loop_var=self::_get_value($arrayname_array[0],$vars,false);
			}
		    
			
                    } else {
                        $loop_var = null;
                    }

                    $loop_i = 0;
                    $loop_index = 1;
		    if (!is_array($loop_var) && is_string($loop_var) && strlen($loop_var)) $loop_var=explode(',',$loop_var);
                    if (is_array($loop_var)) {
                        foreach ($loop_var AS $__k__ => $varset) {

                            if (!is_array($varset)) {
                                $varset = array('loop' => $varset);
                                $varset[$arrayname_array[0]] = $varset['loop'];
                            }
                            $varset['__loop__'] = $__k__;
                            $varset['__item__'] = $varset;
                            foreach ($vars AS $k => $v) {
                                //if (!is_array($v) && !isset($varset[$k])) $varset[$k] = $v;
                                if (!isset($varset[$k]) && $k != 'first' && $k != 'last') $varset[$k] = $v;
                            }

                            $loop_i++;

                            if (isset($arrayname_array[1]) && preg_match('/^[0-9\-]+$/', $arrayname_array[1])) {
                                $fromto = explode('-', $arrayname_array[1]);
                                if (!isset($fromto[1])) $fromto[1] = $fromto[0];
                                if ($loop_i < $fromto[0] || $loop_i > $fromto[1]) continue;
                            }

                            if (!isset($varset['first']) && $loop_index == 1) $varset['first'] = 1;
                            if (!isset($varset['last']) && $loop_index == count($loop_var)) $varset['last'] = 1;

                            $varset['__index__'] = $loop_index++;

                            $wynik .= self::_replace_tokens($inside_content, $varset, $add_globals, 'loop(' . $arrayname . ')[' . $__k__ . ']');
                        }

                    }
                }

            } elseif (substr(strtolower($parser_token), 0, 3) == 'if:') {
                $ifname = substr($parser_token, 3);

                $end_token = strtolower("{endif:$ifname}");
                $pos = strpos(strtolower($parser_content), $end_token);

                self::$replace_cache[$token]['simple'] = false;

                $NOT = false;
                if ($ifname[0] == '!') {
                    $NOT = true;
                    $ifname = substr($ifname, 1);
                }

                if ($pos) {
                    if (!strstr($ifname,'|')) $ifname_array = explode(':', $ifname);
		    else $ifname_array=array($ifname);
		    
                    $_zmienna = explode('=', $ifname_array[0]);
                    $__zmienna = explode('.', $_zmienna[0]);

                    $test_zmienna = self::_get_value($_zmienna[0], $vars, false);

                    if (count($_zmienna) == 1) {

                        if (!$test_zmienna && !$NOT) $parser_startpos = $pos + strlen($end_token);
                        if ($test_zmienna && $NOT) $parser_startpos = $pos + strlen($end_token);

                    } else {
			if (strlen($_zmienna[1]) && $_zmienna[1][0]=='(' && $_zmienna[1][strlen($_zmienna[1])-1]==')')
			{
			    $_zmienna[1] = substr($_zmienna[1],1,strlen($_zmienna[1])-2);
			    $_zmienna[1] = str_replace("\\,", '__przcinek__', $_zmienna[1]);
			    
			    $zmienne=explode(',',$_zmienna[1]);
			    foreach($zmienne AS $i=>$z)
			    {
				$zmienne[$i] = str_replace('__przcinek__',',',$z);
				if (strstr($zmienne[$i], '$')) $zmienne[$i] = self::_assign_variable($zmienne[$i], $vars);
				$zmienne[$i]=trim($zmienne[$i]);
			    }
			}
			else
			{
			    if (strstr($_zmienna[1], '$')) $_zmienna[1] = self::_assign_variable($_zmienna[1], $vars);
			    $zmienne=array(trim($_zmienna[1]));
			}
			$test_zmienna=trim($test_zmienna);
		    
                        if ( !in_array($test_zmienna,$zmienne) && !$NOT) $parser_startpos = $pos + strlen($end_token);
                        if ( in_array($test_zmienna,$zmienne) && $NOT) $parser_startpos = $pos + strlen($end_token);

                    }
                }
            } elseif (substr(strtolower($parser_token), 0, 1) == '%') {
		$wynik.='{'.$parser_token.'}';
	    } else {
                if (!in_array($parser_token, self::$replace_cache[$token]['tokens'])) self::$replace_cache[$token]['tokens'][] = $parser_token;

                $wynik .= self::_get_value($parser_token, $vars);

            }

        }

        self::_debugger($debug_id);

        return $wynik;
    }

    public static function struktura($parser_content, $token_ereg, $filedir=null)
    {
        $parser_content = self::_general_replace($parser_content);
        $parser_startpos = 0;
        $wynik = array();

        while (1) {
            $parser_content = substr($parser_content, $parser_startpos);
            $parser_proc1 = strpos($parser_content, "{");
            $parser_proc2 = strpos(substr($parser_content, $parser_proc1 + 1), "}");
            $parser_proc3 = strpos(substr($parser_content, $parser_proc1 + 1), "{");

            if (!strlen($parser_proc1) || !strlen($parser_proc2)) {
                break;
            }

            if (strlen($parser_proc3) && $parser_proc3 < $parser_proc2) {
                $parser_startpos = $parser_proc1 + 1;
                continue;
            }

            $parser_token = substr($parser_content, $parser_proc1 + 1, $parser_proc2);
            $parser_startpos = $parser_proc1 + $parser_proc2 + 2;

            if (substr(strtolower($parser_token), 0, 5) == 'with:') {
                $arrayname = substr($parser_token, 5);

                $end_token = strtolower("{endwith:$arrayname}");
		$pos = strpos(strtolower($parser_content), $end_token);
                if ($pos) {
                    $inside_content = substr($parser_content, $parser_startpos, $pos - $parser_startpos);
                    $parser_startpos = $pos + strlen($end_token);

                    $wynik[$arrayname] = self::struktura($inside_content, $token_ereg, $filedir);
                }
            } elseif (substr(strtolower($parser_token), 0, 8) == 'include:') {
                $include = substr($parser_token, 8);
                
		
		if ($filedir && file_exists("$filedir/$include")) {
		    $sub=file_get_contents("$filedir/$include");
		    $substruktura=self::struktura($sub, $token_ereg, $filedir);
		    foreach ($substruktura AS $k=>$v) $wynik[$k]=$v;
		}
		
		
	    }
	    
	    elseif (preg_match("#$token_ereg#", $parser_token)) {
                $wynik[$parser_token] = false;
            }
        }

        return $wynik;
    }

    protected static $unique_debug_id;

    public static function debug($vars, $parts = null)
    {
        static $include_stuff_puked;

        if (!self::$unique_debug_id) self::$unique_debug_id = time() - rand(1, 10000); else self::$unique_debug_id++;
        $id = md5(self::$unique_debug_id);

	
        $part = is_array($parts) ? $parts[1] : null;
        $hash = is_array($parts) && isset($parts[2]) ? $parts[2] : '#';
        $color = is_array($parts) && isset($parts[3]) ? $parts[3] : 'red';

        if ($hash[0] == '/') {
            unset($parts[0]);
            unset($parts[1]);
            $src = preg_replace('#/+#', '/', implode('.', $parts));

            $hash = '<img src="' . $src . '" border="0" title="' . $part . '"/>';
        }

        $what = $part ? $part : 'ALL';
	if (is_array($parts) && isset($parts[4]))
	{
	    $what=$parts[4];
	    if ($what[0]=='$')
	    {
		$what=substr($what,1);
		if (isset($vars[$what])) $what=$vars[$what];
	    }
	}
        $ret = '
		<a class="debugger" href="javascript:" onclick="smekta_show_debug(\'content_' . $id . '\')" id="hash_' . $id . '" style="color:' . $color . '" title="' . $what . '">' . $hash . '</a>';

        $ret .= '
		<div style="display:none; font-size:10px; font-family: Courier" title="' . $what . '" id="content_' . $id . '">';

        if ($part && isset($vars[$part])) {
            $ret .= '<pre style="font-size:12px; font-family: Courier">' . print_r($vars[$part], 1) . '</pre>';

        } else {
            foreach ($vars AS $k => $v) {
                $ret .= '<p><b>{' . $k . '}</b> ';
                $ret .= gettype($v);

                if (is_array($v)) $ret .= ' [count=' . count($v) . ']';
                if (is_string($v)) $ret .= ' [len=' . strlen($v) . ']';
                if (is_bool($v)) $ret .= ' [value=' . ($v ? 'TRUE' : 'FALSE') . ']';
                if (is_numeric($v)) $ret .= ' [value=' . $v . ']';

                $ret .= '</p>';
            }
        }

        $ret .= '<hr size="1">';
        if (!$part || !isset($vars[$part])) $ret .= '<p><b>{debug.variable}</b> = full {variable} contents</p>';
        $ret .= '<b>{loop:xxx} additional variables:</b><ul><li><b>{__loop__}</b> = key in array</li>';
        $ret .= '<li><b>{__index__}</b> = incremental index starting from 1</li>';
        $ret .= '<li><b>{__count__<i>variable</i>}</b> = number of items in array <i>variable</i></li></ul>';

        $ret .= '
		</div>';

        if (!$include_stuff_puked) {
            $ret .= '
		<link rel="stylesheet" href="//code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css" />
		<script type="text/javascript">

		    function smekta_show_debug(div_id)
		    {
                document.getElementById(div_id).style.display = "block";

                $("#" + div_id).dialog({ width : 800, height : 500 });
		    }

		    function smekta_load_jquery_ui()
		    {
			if (typeof $.ui == "undefined") {
			    var script = document.createElement("script");
			    script.type = "text/javascript";
			    script.src = "//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js";
			    document.getElementsByTagName("head")[0].appendChild(script);
			}
		    }

		    function smekta_load_jquery()
		    {
                if (typeof $ == "undefined") {
                    var script = document.createElement("script");
                    script.type = "text/javascript";
                    script.src = "//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js";
                    script.onload = smekta_load_jquery_ui;
                    document.getElementsByTagName("head")[0].appendChild(script);
                } else {
                    smekta_load_jquery_ui();
                }
		    }

		    smekta_load_jquery();

		</script>';
        }

        $include_stuff_puked = true;

        return $ret;
    }

    protected static $debug_fun;

    public static function set_debug_fun($df)
    {
        self::$debug_fun = $df;
    }

    protected static function _debugger($debug_id, $txt = null)
    {
        if (!self::$debug_fun) return;

        if (is_array(self::$debug_fun)) {
            return call_user_func_array(self::$debug_fun, array($debug_id, $txt));
        }

    }

}


