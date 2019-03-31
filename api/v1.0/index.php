<?php if(!function_exists("incl_rel_once")) include_once "../../src/include.php";

header('Content-Type: application/json');

incl_rel_once("../../src/DataBase.php", __FILE__);

function success($data) {
    $data['success'] = true;
    die(json_encode($data));
}

function error($message) {
    $data['success'] = false;
    $data['error'] = $message;
    die(json_encode($data));
}


try {
    // TODO process stats
    $db = new Database();
    $address = "...";
    $node = $db->find_node_by_address($address);
    $neighbors = $db->get_peer_addresses($node);
    success(array("neighbors" => $neighbors));
} catch(Exception $exception) {
    error($exception->getMessage());
}