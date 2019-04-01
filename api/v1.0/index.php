<?php if(!function_exists("incl_rel_once")) include_once "../../src/include.php";

header('Content-Type: application/json');

incl_rel_once("../../src/DataBase.php", __FILE__);

echo process_request();

// ***** PROCESSES *****

function process_request() {
    try {
        return try_to_process_request();
    } catch(Exception $exception) {
        return error($exception->getMessage());
    }
}

/**
 * @throws Exception If anything goes wrong
 */
function try_to_process_request() {
    global $db;
    if(!isset($db))
        $db = new Database();

    $_POST['discord_id'] = "092348283430234";
    $_POST['password'] = "test";
    $_POST['address'] = "ict-example.org:1337";

    $node = determine_node();

    // TODO process stats
    $peers = $db->get_peer_addresses($node);
    return success(array("neighbors" => $peers));
}

// ***** OPERATIONS *****

/**
 * @throws Exception If account authentication fails.
 */
function determine_node() {
    global $db;
    $account = determine_account();
    $address = get_POST("address", '/^[a-zA-Z0-9.\-:]*:\d{1,5}$/');
    $node = $db->find_node_by_address($address);
    if($node == -1) {
        throw new Exception("Node does not exist.");
        // TODO
    }
    return $node;
}

/**
 * @throws Exception If account authentication fails.
 */
function determine_account() {
    global $db;
    $discord_id = get_POST("discord_id", '/^[0-9]{14,20}$/');
    $password = get_POST("password", '/^.+$/');

    $account = $db->authenticate_account($discord_id, $password);
    if($account == -1)
        throw new Exception("Authentication failed.");
    return $account;
}

// ***** HELPERS *****

/**
 * @throws Exception If POST argument value does not match expected pattern.
 */
function get_POST($name, $pattern) {
    $value = $_POST[$name];
    if(!preg_match($pattern, $value))
        throw new Exception("POST parameter '$name' does not match pattern '$pattern'.");
    return $value;
}

function success($data) {
    $data['success'] = true;
    return json_encode($data);
}

function error($message) {
    $data['success'] = false;
    $data['error'] = $message;
    return json_encode($data);
}