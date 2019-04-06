<?php if(!function_exists("incl_rel_once")) include_once "../../src/include.php";

incl_rel_once("../../src/DataBase.php", __FILE__);

cron();

function cron() {
    $db = new DataBase();
    $deactivated_nodes = $db->update_inactive_nodes();
    echo "deactivated or prolonged timeout of $deactivated_nodes inactive nodes<br/>";
    $peered = $db->peer_random();
    echo "peered $peered nodes<br/>";
}