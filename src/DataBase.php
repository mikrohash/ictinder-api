<?php if(!function_exists("incl_rel_once")) include_once "include.php";

class Database {

    private $mysqli;

    /**
     * @throws Exception If connection to database fails.
     */
    public function __construct() {
        incl_rel_once("mysql.php", __FILE__);
        $this->mysqli = connect_to_db();
    }

    // ***** OPERATIONS *****

    private function query($query) {
        return $this->mysqli->query($query);
    }

    private function get_row($query) {
        try {
            $res = $this->query($query);
            $row = $res->fetch_object();
            return $row ? get_object_vars($row) : null;
        } finally {
            $res->close();
        }
    }

    private function get_rows($query) {
        $rows = array();
        try {
            $res = $this->query($query);
            while($row = $res->fetch_object()) {
                array_push($rows, get_object_vars($row));
            }
        } finally {
            $res->close();
        }
        return $rows;
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

    public function delete_peering($node_complaining, $node_issue) {
        // When adding an unpeering row, a SQL trigger will automatically delete the corresponding peering row from the database.
        $this->query("INSERT INTO unpeering (node_complaining, node_issue) VALUES ('$node_complaining', '$node_issue')");
    }

    public function get_peer_addresses($node) {
        $peering_rows = $this->get_rows("SELECT address FROM peering LEFT JOIN node ON node.id = node1+node2-'$node' WHERE node1 = '$node' || node2 = '$node'");
        $peer_addresses = array();
        foreach($peering_rows as $peering_row) {
            array_push($peer_addresses, $peering_row['address']);
        }
        return $peer_addresses;
    }

    // ***** STATS *****

    public function create_stats($by_node, $of_node, $stats) {
        $this->query("INSERT INTO stats (of_node, by_node, txs_all) VALUES ('$of_node', '$by_node', '$stats[all]')");
    }
}