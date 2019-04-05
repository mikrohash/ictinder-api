<?php if(!function_exists("incl_rel_once")) include_once "../src/include.php";

incl_rel_once("../src/DataBase.php", __FILE__);

class TransparentDataBase extends DataBase {

    public function _get_row($query) {
        return $this->get_row($query);
    }

    public function _get_rows($query) {
        return $this->get_rows($query);
    }

    public function _delete_table($table) {
        $this->delete_table($table);
    }
}

$db = new TransparentDataBase();

empty_database();
create_random_data();

function empty_database() {
    global $db;
    $db->_delete_table("stats");
    $db->_delete_table("unpeering");
    $db->_delete_table("peering");
    $db->_delete_table("node");
    $db->_delete_table("account");
}

function create_random_data() {
    global $db;

    for($i = 0; $i < 3; $i++)
        create_random_account();

    $row = $db->_get_row("SELECT MIN(id) AS min_id, MAX(id) AS max_id FROM account");
    $account_min = $row['min_id'];
    $account_max = $row['max_id'];
    for($i = 0; $i < 5; $i++)
        create_random_node(rand($account_min, $account_max));

    $peerings = $db->_get_rows("SELECT node1, node2 FROM peering");
    for($i = 0; $i < 20; $i++) {
        $peering = $peerings[rand(0, sizeof($peerings)-1)];
        create_random_stats($peering['node1'], $peering['node2']);
    }
}

function create_random_account() {
    global $db;
    $discord_id = rand(1000000000000000, 9000000000000000000);
    $password = random_bytes(100);
    $db->create_account($discord_id, $password);
    echo "created account #$discord_id<br/>";
}

function create_random_node($account) {
    global $db;
    $address = "example-".rand(0,1000000).".org:".rand(0, 99999);
    $db->create_node($account, $address);
    $node = $db->find_node_by_address($address);
    $db->create_api_call($node);
    $db->peering_insert_middleman($node);
    $db->make_peers($node);
    echo "created node #$node<br/>";
}

function create_random_stats($by_node, $of_node) {
    global $db;
    $stats = array("all" => rand(0, rand(0, 100)));
    $db->create_stats($by_node, $of_node, $stats);
    echo "created stats $by_node -> $of_node<br/>";
}