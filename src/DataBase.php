<?php if(!function_exists("incl_rel_once")) include_once "include.php";

class GeneralDataBase {

    public $timeout_inactive = 600;
    public $cron_interval_sec = 300;
    public $report_interval_sec = 180;
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

    protected function query($query, $print = false) {

        if($print) echo "<code>$query</code><br/>";

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

    protected function find_id_by_key($table, $key_attribute, $key_value) {
        return $this->find_value_by_key($table, $key_attribute, $key_value, "id", -1);
    }

    protected function find_value_by_key($table, $key_attribute, $key_value, $value_attribute, $not_found_value) {
        $row = $this->get_row("SELECT $value_attribute FROM $table WHERE $key_attribute = '$key_value'");
        return $row ? $row[$value_attribute] : $not_found_value;
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

    protected function get_attribute_from_rows($query, $attribute, $is_numberic = false) {
        $rows = $this->get_rows($query);
        $values = array();
        foreach($rows as $row) {
            array_push($values, $is_numberic ? (int)$row[$attribute] : $row[$attribute]);
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

    public function find_account_by_discord_id($discord_id) {
        return $this->find_id_by_key("account", "discord_id", $discord_id);
    }

    // ***** NODES *****

    public function create_node($account, $address) {
        $this->query("INSERT INTO node (account, address) VALUES ('$account', '$address')");
    }

    public function delete_node($id) {
        $this->query("DELETE FROM node WHERE id = '$id'");
    }

    public function find_node_by_address($address) {
        return $this->find_id_by_key("node", "address", $address);
    }

    public function find_nodes_by_account($account, $field = "id") {
        return $this->get_attribute_from_rows("SELECT $field FROM node WHERE account = '$account'", $field);
    }

    public function count_free_slots($account) {
        return $this->find_value_by_key("slots_free", "account", $account, "free", -1);

    }

    public function get_node_timeout($id) {
        $node = $this->get_row("SELECT UNIX_TIMESTAMP(timeout) AS timeout FROM node WHERE id = '$id'");
        return $node ? $node['timeout'] : -1;
    }

    protected function get_total_nbs($node) {
        return $this->find_value_by_key("total_nbs", "node", $node, "total_nbs", -1);
    }

    public function update_inactive_nodes() {
        $max_last_active = time() - 1.2 * $this->report_interval_sec; // these nodes haven't been online for too long
        $max_timeout = time() + 1.2 * $this->cron_interval_sec; // those entries with a higher timeout will be checked by a later cron-job call
        $new_timeout = time() + $this->timeout_inactive;
        $this->query("UPDATE node SET timeout = GREATEST(timeout, FROM_UNIXTIME('$new_timeout')) WHERE id IN "
            ."(SELECT id FROM (SELECT * FROM node) X LEFT JOIN last_active ON X.id = last_active.node WHERE timeout < FROM_UNIXTIME('$max_timeout') AND (last_active IS NULL OR last_active < FROM_UNIXTIME('$max_last_active')))");
        return $this->mysqli->affected_rows;
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
        return $this->get_attribute_from_rows("CALL PEERS($node)", "id", true);
    }

    public function get_unpeered($node) {
        return $this->get_attribute_from_rows("CALL UNPEERS($node)", "id", true);
    }

    public function get_peer_seeking_nodes($amount) {
        return $this->get_attribute_from_rows("SELECT * FROM peer_seeking_nodes ORDER BY RAND() LIMIT $amount", "id", true);
    }

    public function peer_random() {
        $peered = 0;
        do {
            $peer_seeking_nodes = $this->get_peer_seeking_nodes(7);
            if(sizeof($peer_seeking_nodes) < 2)
                break;
            $new_peers = 2 * $this->make_peers($peer_seeking_nodes[0], $peer_seeking_nodes);
            $peered += $new_peers;
        } while ($new_peers > 0);
        return $peered;
    }

    public function make_peers($node, $peer_seeking_nodes = null) {
        $new_peers = $this->find_new_peers($node, $peer_seeking_nodes);
        foreach($new_peers AS $new_peer)
            $this->create_peering($node, $new_peer);
        return sizeof($new_peers);
    }

    protected function find_new_peers($node, $peer_seeking_nodes = null) {
        $current_peers = $this->get_peers($node);
        $unpeered = $this->get_unpeered($node);
        $peers_needed = 3-$this->get_total_nbs($node);
        $not_interested = $current_peers;
        $not_interested = array_merge($not_interested, $unpeered);
        array_push($not_interested, $node);

        if(!$peer_seeking_nodes)
            $peer_seeking_nodes = $this->get_peer_seeking_nodes($peers_needed + sizeof($not_interested));

        $potential_peers = array_diff($peer_seeking_nodes, $not_interested);
        return array_slice($potential_peers, 0, $peers_needed);
    }

    public function peering_insert_middleman($node) {
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