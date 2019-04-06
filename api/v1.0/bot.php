<?php if(!function_exists("incl_rel_once")) include_once "../../src/include.php";

incl_rel_once("../../src/api.php", __FILE__);

// ***** PROCESSES *****

/**
 * @throws Exception If anything goes wrong
 */
function process_request() {
    $_POST['action'] = "get_nodes";
    $_POST['password'] = "test";
    $_POST['discord_id'] = "3696933634015482844";
    $_POST['address'] = "ict-example.org:1340";
    $_POST['stats'] = json_encode(array("ict-example.org:1340" => array("all" => 13)));

    $password = get_POST('password');
    //if(!password_verify($password, '$2y$10$En1DJdKlxID0Z3cZSCRK2eCX6kZp9tbijSxWWzplTSMn92d9sBDjW'))
    //    throw new Exception("Bot authentication failed.");

    $action = get_POST('action', '/^(signup|get_nodes|remove_node)$/');
    return $action();
}

/***** ACTIONS *****/

function signup() {
    global $db;
    $discord_id = get_POST_discord_id();
    $password = random_password(16);
    $db->create_account($discord_id, $password);
    return success(array("password" => $password));
}

function get_nodes() {
    global $db;
    $discord_id = get_POST_discord_id();
    $account = $db->find_account_by_discord_id($discord_id);
    $nodes = $db->find_nodes_by_account($account, "address");
    return success(array("nodes" => $nodes));
}

function remove_node() {
    global $db;
    $address = get_POST_address();
    $node = $db->find_node_by_address($address);
    $db->delete_node($node);
    return success();
}

/***** HELPERS *****/

function random_password($length) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!$%&/()=?#+*~';
    $password = '';
    while (strlen($password) < $length) {
        $password .= $chars[random_int(0, strlen($chars)-1)];
    }
    return $password;
}

?>