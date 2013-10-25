<html>
<head> 
    <title> Redis Viewer </title>
    <link rel="stylesheet" href="css/index.css" > 
</head>
<body>
    <?php
    session_start();
    ini_set("display_errors","On");
    include 'class/redisview.class.php';
    $redview = new RedisView;
    $sessions = $redview -> manage_session();
    echo  $redview -> get_database_header();
    if($sessions['show_info'] === TRUE){
       echo  $redview -> show_info();
    }
    else if(!$sessions['show_key'] && !$sessions['show_key_type']){
        $keys = $redview -> getallkeys();
        echo $redview -> main_header();
        echo $redview -> display_redis_keys($keys);
    }
    else{
       echo $redview -> display_redis_key();
    }
    ?>
</body>
</html>

