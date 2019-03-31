<?php

function incl_rel_once($rel) { include_once(dirname(__FILE__).DIRECTORY_SEPARATOR.$rel); }

class Database {

    private $mysqli;

    /**
     * @throws Exception
     */
    public function __construct() {
        incl_rel_once("mysql.php");
        $this->mysqli = connect_to_db();
    }

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

    public function create_account($discord_id, $password) {
        $pw_bcrypt = password_hash($password, PASSWORD_BCRYPT);
        $this->query("INSERT INTO account (discord_id, pw_bcrypt) VALUES ('$discord_id', '$pw_bcrypt')");
    }

    public function authenticate_account($discord_id, $password) {
        $account = $this->get_row("SELECT pw_bcrypt FROM account WHERE discord_id = '$discord_id'");
        return $account !== null && password_verify($password, $account['pw_bcrypt']);
    }
}