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
}

$db = new Database();