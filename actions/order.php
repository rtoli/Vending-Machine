<?php
include_once('../Models/VendingMachine.class.php');
$order = $_POST['p'];
$money = $_POST['amount'];

$machine = new VendingMachine();
$result = "Not enough money";
if($machine->enoughMoney($order, $money)) {
    $result = $machine->prepareOrder($order, $money);
}

echo $result;
?>