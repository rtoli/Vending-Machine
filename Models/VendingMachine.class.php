<?php
include_once("../config/Auth.php");
class VendingMachine {


    function getProductPrice($product) {
        $auth = new Auth();
        $conn = $auth->connect();

        $sqlQuery = "SELECT price FROM products WHERE name=:name";
        $query = $conn->prepare($sqlQuery);
        $query->bindParam(':name', $product);
        $query->execute();
        $price = intval($query->fetchColumn(0));
        return $price;
    }

    function getOrderPrice($order) {
        $price = 0;

        foreach($order as $o) {
            $price += $this->getProductPrice($o['product']) * $o["productAmount"];
        }
        return $price;
    }

    function enoughMoney($order, $moneyInserted) {
        $price = $this->getOrderPrice($order);

        if((intval($moneyInserted) - $price) < 0) {
            return false;
        }
        return true;
    }

    /*
    Take the order details as well as the money insterted and proccess the data to see if there's enough ingredients for the order to go through
    If no then don't execute the order otherwise use the ingredients required for the order and update the new values as well as other compontents
    related to them such as orders left for each product and the amount of money left inside the machine after preparing the order.
    */
    function prepareOrder($data, $moneyInserted) {
        session_start();
        $auth = new Auth();
        $conn = $auth->connect();
        $price = $this->getOrderPrice($data);
        $products = $this->getProducts($conn);
        $recipees = $this->getRecipees($conn);
        foreach($data as &$d) {
            $d['product'] = $products[$d['product']];
        }
        $ingredient_amount = $this->getOrderIngredients($data, $recipees, $conn);
        $ing_current_amount = $this->getAllIngredients($conn);
        if(!$this->enoughIngredients($ing_current_amount, $ingredient_amount)) return "Not enough ingredients!";
        $this->updateComponents($ing_current_amount, $moneyInserted, $recipees, $price, $conn);
        return "Your order has been prepared";
    }

    function updateComponents($ing_current_amount, $moneyInserted, $recipees, $price, $conn) {
        $this->useIngredients($ing_current_amount, $conn);

        $ordersLeft = $this->getOrdersLeft($recipees, $ing_current_amount);

        $ordersLeftWithSugar = $ing_current_amount[$this->getSugarId($conn)];
        if($ordersLeftWithSugar > $ordersLeft['1']) {
            $ordersLeftWithSugar = $ordersLeft['1'];
        }
        
        $_SESSION["sum"] = $moneyInserted - $price;
        $_SESSION["ordersLeft"] = (int)$ordersLeft["1"];  
        $_SESSION["ordersLeftWithSugar"] = (int)$ordersLeftWithSugar;
    }

    /*
    Create and return a map of each product with their name as key and id as value
    */
    function getProducts($conn) {
        $products = array();
        $sqlQuery = "SELECT name, id FROM products";
        $query = $conn->prepare($sqlQuery);
        $query->execute();
        $results = $query->fetchAll(PDO::FETCH_ASSOC);

        foreach($results as $product) {
            $products[$product['name']] = $product['id'];
        }
        return $products;
    }

    /*
    Create a recipee map which contains the product id as key and whose value is another map
    which contains the key->value pairs of ingredient_id->quanty
    eg. {
        (int)product_id : {
            "ingredient_id":"quantity",
            "ingredient_id":"quantity"
        }
    }
    */
    function getRecipees($conn) {
        $recipees = array();
        $sqlQuery = "SELECT   P.id, R.ingredient_id, R.quantity
        FROM        products P
        INNER JOIN  recipe  R
            ON      R.product_id = P.id";

        $query = $conn->prepare($sqlQuery);
        $query->execute();
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        $tempArr = array();
        foreach($result as $recipee) {
	        $tempArr += [$recipee['ingredient_id'] => $recipee['quantity']];
            $recipees[$recipee['id']] = $tempArr;
        }
        return $recipees;
    }

    /*
    Using the available recipees build and array that contains the id's of the ingredients to be used
    as well as the total quantity.
    Return the array as a map with ingredient id as key and total ingredient amount for the order as value
    */
    function getOrderIngredients($data, $recipees, $conn) {
        $sugarCount = 0;
        $ingredient_amount = array();
        
        foreach($data as $order) {
            //Since sugar is a checkbox if it's included it will increase the order size whereas otherwise it would'nt appear
            if(sizeof($order) == 3) {
                $sugarCount += $order['productAmount'];
            }
            //Iterate through the recipee for the current product to get the corresponding ingredient id's and amount
            foreach($recipees[$order['product']] as $ingredientId=>$quantity) {
                //Check if the map is initialized first so that undefined key warning doesn't ocur
                if(!isset($ingredient_amount[$ingredientId])){
                    $ingredient_amount[$ingredientId] = intval($quantity) * $order['productAmount'];;
                }else {
                    $ingredient_amount[$ingredientId] += intval($quantity) * $order['productAmount'];
                }
            }
        }
        $ingredient_amount[$this->getSugarId($conn)] = $sugarCount;
        return $ingredient_amount;
    }

    /*
    Get all available ingredients and their id's insert them into a map with id as key and amount as value 
    and return that map
    */
    function getAllIngredients($conn) {
        $ing_current_amount = array();
        $sqlQuery = "SELECT id, quantity FROM ingredients";
        $query = $conn->prepare($sqlQuery);
        $query->execute();
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        foreach($result as $res) {
            $ing_current_amount[intval($res['id'])] = $res['quantity'];
        }
        return $ing_current_amount;
    }

    /*
    Check if there's enough ingredients for the order and if there is deduct the ordered amount from the current one
    and return true. Otherwise only return false
    */
    function enoughIngredients(&$ing_current_amount, $ingredient_amount) {
        foreach($ingredient_amount as $key => $value) {
            if($value - $ing_current_amount[$key] > 0) {
                return false;
            }
            $ing_current_amount[$key] -= $value;
        }
        return true;
    }


    /*
    Update the new ingredient amount in the SQL Table
    */
    function useIngredients($ing_current_amount, $conn) {
        $ids = implode(',', array_keys($ing_current_amount));
        $sqlQuery = "UPDATE ingredients SET quantity = CASE id ";
        //The loop is used to build a single SQL query that updates all the ingredients instead of making multiple SQL calls
        foreach ($ing_current_amount as $id => $amount) { 
            $sqlQuery .= "WHEN $id THEN $amount ";
        }
        $sqlQuery .= "END WHERE id IN ($ids)";
        $query = $conn->prepare($sqlQuery);
        $query->execute();
    }

    /*
    Using the recipees calculate the amount of orders left for each product and return the value
    */
    function getOrdersLeft($recipees, $ing_current_amount) {
        $ordersLeft = array();
        foreach($recipees as $key=>$value) {
            $min = PHP_INT_MAX;
            foreach($value as $id=>$amount) {
                $temp = $ing_current_amount[intval($id)]/$amount;
                if($temp < $min) {
                    $min = $temp;
                }
            }
            $ordersLeft[$key] = $min;
        }
        return $ordersLeft;
    }

    function getSugarId($conn){
        $sqlQuery = "SELECT id FROM ingredients WHERE name='sugar'";
        $query = $conn->prepare($sqlQuery);
        $query->execute();
        return $query->fetchColumn();
    }
}