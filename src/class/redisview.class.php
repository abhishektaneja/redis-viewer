<?php
include 'config.php';
class RedisView{
    public function __construct(){
        
        global $redis_ip, $redis_port, $default_selected_db;
        $this -> pagerLimit = 5;
        $this -> redis = new Redis;
        $this -> redis -> connect($redis_ip, $redis_port);
        $this -> selected_db = $default_selected_db;
        $this -> pagerFlow = 0;
    }
    private function start_session(){
        start_session();
    }
    private function clear_session(){
            session_unset();
            session_destroy();
            session_start();       
    }
    public function selectDb($db){
        $this -> redis -> select($db);
        $this -> selected_db = $db;
    }
    private function get_valid_type($type, $key){
        if($type ==  Redis::REDIS_STRING){
            $type = 'string';

            if(str_word_count($this -> redis -> get($key)) == 0 && $this -> redis -> strlen($key) > 0){
                $bitCount = $this -> redis -> bitcount($key);
                $type = 'bitCount';
            }
        } 
        else if($type ==  Redis::REDIS_SET){
            $type = 'set';
        }
        else if($type ==  Redis::REDIS_LIST){
            $type = 'list';
        }
        else if($type ==  Redis::REDIS_ZSET){
            $type = 'zset';
        }
        else if($type ==  Redis::REDIS_HASH){
            $type = 'hash';
        }
        return $type;
    }
    private function get_key_size($type, $key){
       if( $type == "string" ) {
            $size = $this -> redis->strlen( $key );
        } else if( $type == "hash" ) {
            $size = $this -> redis->hlen( $key );
        } else if( $type == "set" ) {
            $size = $this -> redis->scard($key );
        } else if ($type == 'zset') {
            $size = $this -> redis -> zcard( $key );
        } else if ($type == "list" ) {
            $size = $this -> redis -> llen( $key );
        }
        else if ( $type == "bitCount" ) {
            $size = $this -> redis->bitcount( $key );
        }
        else {
            $size = -103;
        }
        return $size;
}
private function get_key_data($type, $key){
   if( $type == "string" ) {
    $data = $this -> redis->get( $key );
} else if( $type == "hash" ) {
    $data = $this -> redis->hgetall( $key );
} else if( $type == "set" ) {
    $data = $this -> redis->smembers( $key );
} else if ($type == 'zset') {
    $data = $this -> redis -> zrange( $key , 0 , -1 , 'withscores' );
} else if ($type == "list" ) {
    $data = $this -> redis -> lrange( $key , 0 , -1 );
}
else if ($type == "bitCount" ) {
    $data = $this -> redis -> bitCount( $key );
}
return $data;
}
public function show_info(){
    $html = '';
    $html .= "<br>";
    $html .= "<a href='?ref=home' style='float:right;margin-right:1%;'> Home </a>"; 
    $html .= "<a style='float:right;margin-right:1%;' href='?ref=back' onclick='history.go(-1)' > Back </a>";
    $html .= "<br>";
    $html .= "<div class='infoViewer' > ";
    $html .= "<pre>";
    $html .= $this -> show_data($this -> redis -> info());
    $html .= "</div>";
    return $html;
}
public function get_database_header(){
    $db = $this -> selected_db;
    $info = $this -> redis->info();
    $res = array();
    foreach( $info as $key => $value ) {
        if( substr( $key, 0, 2 ) == "db" ) {
            $db_num = substr( $key, 2, strlen( $key ) );
            if( is_numeric( $db_num ) ) {
                $res[ (int)$db_num ] = $value;
            }
        }
    }
    $dbs = $res;
    $this -> selectDb($db);

    return $this -> get_database_html($dbs, $db);
}
public function get_database_html($dbs, $currentdb){
    $db_html = '';
    $db_html .= "<div class='selectDataBase'> Select Databse : ";
    foreach( $dbs as $n => $db_info ) {
        $db_html .= "<a id=".$n." class='selectDb' href='?db=".$n."'>  " . $n ;
        if($n == $currentdb){
            $db_html .=  "<input id='radio".$n."' checked='checked' type='radio' for='#".$n."' name='selectDb' > ";
        }
        else{
            $db_html .=  "<input id='radio".$n."' onclick='document.location ='?db=".$n."';' type='radio' for='#".$n."' name='selectDb' > ";
        }
        $db_html .=  "</a> &nbsp;&nbsp;&nbsp; ";
    }
    $db_html .= "</div> <br> <br> <div class='currentDb'> Current Database  : "  .$currentdb . " <br> Size: ".$this ->redis -> dbsize()."</div>";
    return $db_html;
}
private function print_key($data){
    global $type;
    $chk_flag = 0;
    $html = '';
    if(isset($this -> sessions['single_key_type'])){
        if($this -> sessions['single_key_type'] == 'string' || $this -> sessions['single_key_type'] == 'bitCount' ){
            if(!is_array($data) && !is_array(json_decode($data, true))){
                $html = "<table cellspacing='2' cellpadding='4' class='single_key_viewer'>";
                $html .= "<thead> <th> Key </th> <th> Value </th>";
                $chk_flag = 1;
                $html .= "<tr>";
                $html .= "<td> 0 </td>";
                $html .= "<td>" . $data. "</td>";
                $html .= "</tr>" ;
                $html .= "</table>";
            }
            else{
                if(!is_array($data)){
                    $data = json_decode($data, true);
                }
                $chk = 0;
            }
        }
    }
    if($chk_flag == 0 && count($data) > 0 ){
            $html = "<table cellspacing='2' cellpadding='4' class='single_key_viewer'>";
            $html .= "<thead> <th> Key </th> <th> Value </th>";
        foreach ($data as $key => $value) {
            $html .= "<tr>";
            $html .= "<td>" . $key. "</td>";
            $html .= "<td>" ;

            if( is_array($value) || is_array(json_decode($value, true)) ) {

                $html .= $this -> show_table($value);
            }
            else{    

                $html .= $value. "</td>";
            }
            $html .= "</tr>";    
        }
        $html .= "</table>";
    }   
    return $html;
}

private function show_data($data){


    if(!is_array($data)){
        $jsonD = json_decode($data, true);
    }
    $html =  "<pre>";  
    $html .= $this -> print_key($data);   
    $html .= "</pre>";
    return $html;
}

private function show_table($data){

    if(!is_array($data)){
        $jsonD = json_decode($data, true);
    }
    if(is_array($data)){
      $html =  $this -> print_key($data);
    }
    else if(is_array($jsonD)){
       $html = $this -> print_key($jsonD);   
    }
    else{
        $html =  $data;
    }

    return $html;
}
public function enable_pagination(){
    global $pageViewLimit;
    $totalPage = intval($this -> keys_count / $pageViewLimit) + 1 ;
    $_SESSION['totalPage'] = $totalPage;
    $html = "<div class='pageView' > Select Page: "; 
    if($totalPage > $this -> pagerLimit){
        $totalExtraPage = $totalPage - $this -> pagerLimit;
    }else{
        $totalExtraPage = 0;
    }
    $starPageView = isset($_SESSION['pageView']) ? $_SESSION['pageView'] : 1 ;
    if($this -> pagerFlow == -1){
        $starPageView = ($starPageView - $this -> pagerLimit / 2) >= 1 ? intval($starPageView - ( $this -> pagerLimit / 2 ) + 1 )  : 1 ;
    }
    else if($this -> pagerFlow == 1){
        $starPageView = ($starPageView + $this -> pagerLimit / 2) <= $totalPage ? intval($starPageView + ( $this -> pagerLimit / 2 ) - ($this -> pagerLimit / 2) - 1  )  : $_SESSION['pageView'] ;
    }
    if($starPageView != 1){
        $html .= "<a id='#prev' class='pager_prev' href='?pageView=prev'> " . "prev" ."</a>&nbsp;&nbsp;&nbsp;";
    }
    $starPageView = $starPageView + $this -> pagerLimit > $totalPage ? $starPageView - ( ($starPageView + $this -> pagerLimit ) - $totalPage) +1 : $starPageView ;
    $starPageView = $starPageView < 1 ? 1 : $starPageView;
    $currentPage = isset($_SESSION['pageView']) ? $_SESSION['pageView'] : 1 ;
    for( $i = $starPageView ; $i < $starPageView + $this -> pagerLimit; $i++) {
        if($i > $totalPage)
            break;
        if($i == $currentPage) {
            $html .= "<a id=".$i." class='pager currentPage' href='?pageView=".$i."'> " . $i . "</a>&nbsp;&nbsp;&nbsp; ";
        }
        else{
            $html .= "<a id='#next' class='pager_next' href='?pageView=".$i."'> " . $i  . "</a>&nbsp;&nbsp;&nbsp; ";   
        }
    }
    if(isset($_SESSION['pageView'])){
        if($_SESSION['pageView'] < $totalPage){
            $html .= "<a id=".$i." class='selectDb' href='?pageView=next'> " . "next" . "</a>" ;
        }
    }
    $html .= "</div>";
    if(!isset($_SESSION['pageView'])){
        $_SESSION['pageView'] = 1;
        $_SESSION['start'] = 0;
        $_SESSION['end'] = $pageViewLimit -1 ;
    }   
    return $html;
}
public function main_header(){
    global $pageViewLimit;
    $this -> getallkeys(); // initialize all keys
    $html = "<br>";
    $html .= "<a href='?ref=info' style='float:right;margin-right:10%;'> Info </a>"; 
    $html .= "<a href='?ref=home' style='float:right;margin-right:1%;'> Home </a>"; 
    $html .= "<br>";
    $html .= "<div class='searchForm' > <form action='' method='get' >  <label  for='#search'> Search Redis </label>";
    $html .= "<input id='search' type='text' name='search' placeholder=' Enter a pattern ' value='". substr_replace($this -> pattern, '', -1)."'  >";
    $html .= "<input type='submit' value='Search' for='#search' > </form>";
    $html .= "</div>";
    $html .= "<div class='totalMemory' >";
    $info = $this -> redis -> info();
    $html .= "Total Memory : " ;
    if((($info['used_memory'] / 1000 )/ 1000) > 1000) {
        $html .= "<lable class='highMemory'> ";
    }
    else if((($info['used_memory'] / 1000 )/ 1000) > 500){
        $html .= "<lable class='alertMemory'> ";
    }
    else {
        $html .= "<lable class='okMemory'> ";
    }

    $html .=  $info['used_memory_human'] . "</lable>";
    $html .= "</div>";
    if( $this -> keys_count > $pageViewLimit  ){
        $html .= $this -> enable_pagination();   
    }
    return $html;
}
public function display_redis_keys(){

    $html = "<div class='waiting' id='wait_messsage'  style='visibility:visible' > Please wait, Loading ... </div>";
    $html .= "<div class='table_holder' id='table_wrapper' style='visibility:visible' > <table id='displayKeys' cellspacing='2' cellpadding='4' cell class='table tablesorter'> <thead> <th> S.No. </th> <th> Key </th> <th> Type </th> <th> Size </th> <th> TTL </th> <tbody>" ;
    $start = isset($_SESSION['start']) ? $_SESSION['start'] : 0 ;
    $end = isset($_SESSION['end']) ? $_SESSION['end'] : count($this -> keys)-1 ;
    $end = $end >= $this -> keys_count ? $this -> keys_count -1 : $end ;

    for ( $i=$start; $i <= $end ; $i++ ) {
        $type = $this -> redis -> type($this -> keys[$i]);
        $ttl = $this -> redis -> ttl($this -> keys[$i]);
        $size = 0;
        
        $type = $this -> get_valid_type($type, $this -> keys[$i]);
        $size = $this -> get_key_size($type, $this -> keys[$i]);
        $html .= "<tr>";
        $html .= "<td>" . ($i+1); $html .= "</td>";
        $html .= "<td> <a class='keyList' href='?key=".$this -> keys[$i]."&key_type=".$type."&s=".$size."&tl=".$ttl."' >" . $this -> keys[$i]   . "</a></td>";
        $html .= "<td>" . $type . "</td>";
        $html .= "<td>".   $size   . "</td>";
        $html .= "<td>".   $ttl   . "</td>";
        $html .= "</tr>";
    }
     if($i == 0){
        $html .= "<div class='nokeys'> Sorry!  No key Found! </div>";
    }
    $html .= "</table></div>";
    $html .= '<script type="text/javascript" >document.getElementById("wait_messsage").style.visibility = "hidden"; </script>';
    return $html;
}
public function getallkeys(){
    $keys = $this -> redis -> keys($this -> pattern);
    $this -> keys = $keys;
    $this -> keys_count = count($keys);
    return ;
}
public function display_redis_key(){
    $type = $this -> sessions['single_key_type'];
    $key = $this -> sessions['single_key'];
    $this -> sessions['single_key_size'] = $_REQUEST['s'];
    $this -> sessions['single_key_ttl'] = $_REQUEST['tl'];
    $data = $this -> get_key_data($type, $key);
    $html =  "<br>";
    $html .= "&nbsp;<a href='?ref=home'> Home </a>"; 
    $html .= "&nbsp;&nbsp;&nbsp;&nbsp;<a href='?ref=back' onclick='history.go(-1)' > Back </a>";
    $html .= "<br>";
    $html .= "<div class='keyViewerName' > ";
    $html .= $key;
    $html .= "<div class='sizeViewer'>";
    $html .= "Size : " . $this -> sessions['single_key_size'] . ",";
    $html .= "</div>";
    $html .= "<div class='ttlViewer'>";
    $html .= "TTl : " . $this -> sessions['single_key_ttl'];
    $html .= "</div>";
    $html .= "</div>";
    $html .= "<div class='keyViewer' > ";
    if($type == 'zset'){

        $html .= $this -> print_zset($data);
    }
    else{

        $html .= $this -> show_data($data);
    }
    $html .= "</div>";
    return $html;
}

function print_zset($data){

    $html = "<table cellspacing='2' cellpadding='4' class='single_key_viewer'>";
    $html .= "<thead> <th> Key </th> <th> Value </th> <th>  Score </th>";
    $i = 0;
    $html .= "<pre>";

    foreach ($data as $key => $score) {

        $html .= "<tr>";
        $html .= "<td>" . ++$i. "</td>";
        $html .= "<td>" ;
        $html .= $this -> show_table($key);
        $html .= "<td>" . $score. "</td>";
        $html .= "</tr>";

    }
    $html .= "</table>";
    return $html;
}
public function manage_session(){
    global $pageViewLimit;
    $show_info = FALSE;
    if(isset($_REQUEST['ref'])){
        if($_REQUEST['ref'] == 'home'){
            $this -> clear_session();
        }
        else if($_REQUEST['ref'] == 'info'){
            $show_info = TRUE;
        }
    }
    if(isset($_REQUEST['pageView'])){
        $page = $_REQUEST['pageView'];
        if($page == 'next'){
            $page = isset($_SESSION['pageView']) ? $_SESSION['pageView'] + 1 : 2 ;
            if(isset($_SESSION['totalPage'])){
                if($page > $_SESSION['totalPage']){
                    $page = $_SESSION['totalPage'];
                }
            } 
        }
        else if($page == 'prev'){

            $page = isset($_SESSION['pageView']) ? $_SESSION['pageView'] - 1 : 1 ;
            $page =  $page < 1 ? 1 : $page;
        }
        if($page < $_SESSION['pageView']){
            $this -> pagerFlow = -1;
        }
        else{
            $this -> pagerFlow = 1;
        }
        $_SESSION['pageView'] = $page;
        $_SESSION['start'] = $page-$page + ($pageViewLimit * ($page-1));
        $_SESSION['end'] = ($pageViewLimit * $page) -1;

    }
    else if(isset($_SESSION['pageView'])){
        $page = $_SESSION['pageView'];
        $_SESSION['start'] = $page-$page + ($pageViewLimit * ($page-1));
        $_SESSION['end'] = $pageViewLimit * $page;
    }

    if ( isset($_REQUEST['db'] ) ) {
        $db =$_REQUEST['db'];
        $_SESSION['db'] = $db;
        $_SESSION['search'] = '';
        $this -> selectDb($db);
    } 
    else if ( isset($_SESSION['db']) ){
     $db =$_SESSION['db'];
     $this ->  selectDb($db);
 }
 else{
     $db = 0;
 }
 if(isset($_REQUEST['key'])) {
    $show_key = $_REQUEST['key'] ;
    $_SESSION['single_key'] = $show_key;
}
else{
    $show_key = '';
} 
if( isset($_REQUEST['key_type'])) {
    $show_key_type = $_REQUEST['key_type'] ;
    $_SESSION['single_key_type'] = $show_key_type;
} else{
 $show_key_type = ''; 
}

if(isset($_REQUEST['search'])){
    $_SESSION['search'] = $_REQUEST['search'];
    $_SESSION['pageView'] = 1;
    $_SESSION['start'] = 0;
    $search = $_REQUEST['search'];
}
else if(isset($_SESSION['search'])){

 $search = $_SESSION['search'];
}
else{
    $search = '';
}
$this -> pattern = $search . '*';
$this -> sessions = $_SESSION;
$sessions =  array('show_key' => $show_key, 'show_key_type' => $show_key_type, 'show_info' => $show_info);
return $sessions;
}

}

?>