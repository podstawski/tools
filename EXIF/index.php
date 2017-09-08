<?php

    

    function gps($gps) {
        if (strstr($gps,',')) {
            $g=[];
            foreach(explode(',',$gps) AS $gg) $g[]=gps(trim($gg));
            return implode(',',$g);
        }
        
        
        $g=explode(' ',$gps);
        $deg = (substr($gps,-1)=='S' || substr($gps,-1)=='W') ? -1*$g[0] : $g[0];
        
        $min=preg_replace('/[^0-9.]*/','',$g[2]);
        $sec=preg_replace('/[^0-9.]*/','',$g[3]);
        
        $ret=$deg+(($min/60) + ($sec/3600));
        
    
        
        return $ret;
    }

    foreach (scandir(__DIR__.'/cache') AS $cache) {
        if ($cache[0]=='.' || is_dir(__DIR__.'/cache/'.$cache)) continue;
        
        if (time()-filemtime(__DIR__.'/cache/'.$cache) > 3600*24) unlink(__DIR__.'/cache/'.$cache);
    }

    $exif=false;
    if (isset($_GET['exif'])) {
        file_put_contents(__DIR__.'/log.txt',date('Y-m-d H:i:s|').$_GET['exif']."\n",FILE_APPEND);
        $cache=__DIR__.'/cache/'.md5($_GET['exif']);
        if (!file_exists($cache)) {
            if (strtolower(substr($_GET['exif'],0,5))=='gs://') {
                $cmd='gsutil cp '.$_GET['exif'].' '.$cache;
                system($cmd);
        
            } else {
                $blob=file_get_contents($_GET['exif']);
                if ($blob) file_put_contents($cache,$blob);
                $blob=null;
            }
            if (file_exists($cache)) $exif=$cache;
        } else {
            $exif=$cache;
        }
        
    }
    
    $result=[];
    
    if ($exif) {
        ob_start();
        system('exiftool '.$exif);
        $exif=explode("\n",ob_get_contents());
        ob_end_clean();
        
        
        foreach($exif AS $e) {
            $ee=explode(':',$e);
            
            $k=str_replace(' ','',$ee[0]);
            unset($ee[0]);
            
            if (in_array($k,['Directory','FileName','FileModificationDate/Time','FileAccessDate/Time','FileInodeChangeDate/Time','FilePermissions','ThumbnailImage'])) continue;
            
            if (!strlen($k)) continue;
            $kk=strtolower($k);
            if (isset($_GET['fields']) || isset($_POST['fields'])) {
                $f=explode(',',isset($_POST['fields'])?$_POST['fields']:$_GET['fields']);
                $found=false;
                foreach($f AS $ff) {
                    if (strstr($kk,$ff)) $found=true;
                }
                if (!$found) continue;
            }
            $v=trim(implode(':',$ee));
            
            if ($kk=='gpslatitude'||$kk=='gpslongitude'|| $kk=='gpsposition') $v=gps($v);
            
            if (strstr($kk,'date')) $v[4]=$v[7]='-';
            
            
            $result[$k]=$v;
            
        }
        
    }

    header('Content-type:application/json');

    echo json_encode($result);

    
//system('gsutil cat gs://kiedymsza.appspot.com/images/fb.901764699865315/24f618f30273b86f37c880d52b7ab4a6.jpg | exiftool -gpsposition -');
