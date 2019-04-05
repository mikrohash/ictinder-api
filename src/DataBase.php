<?php if(!function_exists("incl_rel_once")) include_once "include.php";

class GeneralDataBase {

    protected $mysqli;
    protected $last_query = "UNDEFINED";

    /**
     * @throws Exception If connection to database fails.
     */
    public function __construct() {
        incl_rel_once("mysql.php", __FILE__);
        $this->mysqli = connect_to_db();
    }

    // ***** OPERATIONS *****

    protected function query($query) {
        $res = $this->mysqli->query($query);
        if(!$res) {
            $query_escaped = $this->mysqli->escape_string($query);
            $error = $this->mysqli->escape_string($this->mysqli->error);
            $this->mysqli->query("INSERT INTO error (query, error) VALUES ('$query_escaped', '$error')");
            die("<h1>MySQL Exception:</h1><br/><code>$query</code><br/><br/>" . $error ."<br/><hr/><br/>Last Query:<br/><br/><code>$this->last_query</code>");
        }
        $this->last_query = $query;
        return $res;
    }

    protected function get_row($query) {
        try {
            $res = $this->query($query);
            $row = $res->fetch_object();
            return $row ? get_object_vars($row) : null;
        } finally {
            $res->close();
        }
    }

    protected function get_rows($query) {
        $rows = array();
        try {
            $res = $this->query($query);
            while($row = $res->fetch_object()) {
                array_push($rows, get_object_vars($row));
            }
        } finally {
            while(mysqli_more_results($this->mysqli)) {
                mysqli_next_result($this->mysqli);
            }
            $res->close();
        }
        return $rows;
    }

    protected function get_attribute_from_rows($query, $attribute) {
        $rows = $this->get_rows($query);
        $values = array();
        foreach($rows as $row) {
            array_push($values, $row[$attribute]);
        }
        return $values;
    }

    protected function num_rows($query) {
        try {
            $res = $this->query($query);
            return $res->num_rows;
        } finally {
            $res->close();
        }
    }

    protected function delete_table($table) {
        $this->query("DELETE FROM $table");
    }
}

class DataBase extends GeneralDataBase{

    // ***** GENERAL *****

    public function create_api_call($node) {
        $this->query("INSERT INTO api_call(node) VALUES ('$node')");
    }

    // ***** ACCOUNTS *****

    public function create_account($discord_id, $password) {
        $pw_bcrypt = password_hash($password, PASSWORD_BCRYPT);
        $this->query("INSERT INTO account (discord_id, pw_bcrypt) VALUES ('$discord_id', '$pw_bcrypt')");
    }

    public function authenticate_account($discord_id, $password) {
        $account = $this->get_row("SELECT id, pw_bcrypt FROM account WHERE discord_id = '$discord_id'");
        return $account !== null && password_verify($password, $account['pw_bcrypt']) ? $account['id'] : -1;
    }

    public function delete_account($id) {
        $this->query("DELETE FROM account WHERE id = '$id'");
    }

    // ***** NODES *****

    public function create_node($account, $address) {
        $this->query("INSERT INTO node (account, address) VALUES ('$account', '$address')");
    }

    public function delete_node($id) {
        $this->query("DELETE FROM node WHERE id = '$id'");
    }

    public function find_node_by_address($address) {
        $node = $this->get_row("SELECT id FROM node WHERE address = '$address'");
        return $node ? $node['id'] : -1;
    }

    public function count_free_slots($account) {
        return $this->get_row("SELECT free FROM slots_free WHERE account = '$account'")['free'];

    }

    // ***** PEERING *****

    public function create_peering($node_a, $node_b) {
        $node1 = min($node_a, $node_b);
        $node2 = max($node_a, $node_b);
        $this->query("INSERT INTO peering (node1, node2) VALUES ('$node1', '$node2')");
    }

    public function find_peering($node_a, $node_b) {
        $node1 = min($node_a, $node_b);
        $node2 = max($node_a, $node_b);
        $row = $this->get_row("SELECT id FROM peering where $node1 = '$node1' && $node2 = '$node2'");
        return $row !== null ? $row['id'] : -1;
    }

    public function delete_peering($peering) {
        $this->query("DELETE FROM peering WHERE id = '$peering'");
    }

    public function unpeer($node_complaining, $node_issue) {
        // When adding an unpeering row, a SQL trigger will automatically delete the corresponding peering row from the database.
        $this->query("INSERT INTO unpeering (node_complaining, node_issue) VALUES ('$node_complaining', '$node_issue')");
    }

    public function get_peer_addresses($node) {
        return $this->get_attribute_from_rows("SELECT node.address FROM peering LEFT JOIN node ON node.id = node1+node2-'$node' WHERE node1 = '$node' || node2 = '$node'", "address");
    }

    public function get_peers($node) {
        return $this->get_attribute_from_rows("CALL PEERS($node)", "id");
    }

    public function get_unpeered($node) {
        return $this->get_attribute_from_rows("CALL UNPEERS($node)", "id");
    }

    public function find_new_peers($node) {
        $current_peers = $this->get_peers($node);
        $unpeered = $this->get_unpeered($node);
        $peers_needed = 3-sizeof($current_peers);
        $not_interested = $current_peers;
        $not_interested = array_merge($not_interested, $unpeered);
        array_push($not_interested, $node);
        return $this->get_rows("SELECT * FROM node "
            ."LEFT JOIN total_nbs ON node.id = total_nbs.node "
            ."WHERE total_nbs.total_nbs < 3 && id NOT IN(".join(",", $not_interested).") "
            ."AND IS_NODE_ACTIVE(node.id) AND timeout < CURRENT_TIMESTAMP() "
            ."ORDER BY RAND() LIMIT $peers_needed");
    }

    function make_peers($node) {
        $new_peers = $this->find_new_peers($node);
        foreach($new_peers AS $new_peer)
            $this->create_peering($node, $new_peer['id']);
    }

    function peering_insert_middleman($node) {
        if($old = $this->get_row("SELECT id, node1, node2 FROM peering ORDER BY RAND() LIMIT 1")) {
            $this->delete_peering($old['id']);
            $this->create_peering($old['node1'], $node);
            $this->create_peering($old['node2'], $node);
        }
    }

    // ***** STATS *****

    public function create_stats($by_node, $of_node, $stats) {
        $this->query("INSERT INTO stats (of_node, by_node, txs_all) VALUES ('$of_node', '$by_node', '$stats[all]')");
    }
}