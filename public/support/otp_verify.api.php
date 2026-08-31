<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/config.inc.php";
require_once __DIR__ . "/../includes/dbh.inc.php";
require_once __DIR__ . "/../includes/users.class.php";
require_once __DIR__ . "/../email/mailer.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['otp'])) {
    $otp = trim($_POST['otp'] ?? '');

    if($otp == $_SESSION['otp']['key'] && time() <= $_SESSION['otp']['expiry']){
    
        $_SESSION['verified_email'] = $_SESSION['otp']['email'];
        
        unset($_SESSION['otp']);
        
        $order_data = users::getUserOrdersByEmail($pdo,  $_SESSION['verified_email']);
        $customer = [
            'firstname' => '',
            'lastname' => ''
        ];
        
        if (!empty($order_data)) {
            $customer = [
                'firstname' => $order_data[0]['first_name'],
                'lastname' => $order_data[0]['last_name']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'orders' => $order_data,
            'customer' => $customer
            ]);
        
    } else {
        echo json_encode([
            'success' => false
        ]);
    }
    
    exit;
}

