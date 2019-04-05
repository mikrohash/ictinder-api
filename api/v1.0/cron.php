<?php if(!function_exists("incl_rel_once")) include_once "../../src/include.php";

incl_rel_once("../../src/DataBase.php", __FILE__);

$db = new DataBase();

while ($db->peer_random())
    echo "peered<br/>";