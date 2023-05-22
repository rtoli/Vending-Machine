<!DOCTYPE html>
<html>

<head>
    <title>Vending Machine</title>
    <style type="text/css">
        .image {
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        input[type='number'] {
            width: 50px;
        }
    </style>
    <script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        
        async function postData(url = "", data = {}) {
            // Default options are marked with *
            const response = await fetch(url, {
                method: "POST", // *GET, POST, PUT, DELETE, etc.
                mode: "cors", // no-cors, *cors, same-origin
                cache: "no-cache", // *default, no-cache, reload, force-cache, only-if-cached
                credentials: "same-origin", // include, *same-origin, omit
                headers: {
                    "Content-Type": "application/json",
                    // 'Content-Type': 'application/x-www-form-urlencoded',
                },
                redirect: "follow", // manual, *follow, error
                referrerPolicy: "no-referrer", // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
                body: JSON.stringify(data), // body data type must match "Content-Type" header
            });
            return response.json(); // parses JSON response into native JavaScript objects
        }

        const submitForm = (e) => {
            //Doesn't allow the page to be refreshed because the form doesn't submit
            //e.preventDefault();

            const form = document.querySelector(".order");
            const inputs = form.querySelectorAll("input");
       
            let data = {};

            Array.from(inputs).forEach((input) => {
                data[input.name] = input.value;
            })

            postData("order.php", data).then((response) => {
                console.log(response); // JSON data parsed by `data.json()` call
            });
        }
        

    </script>

</head>

<?php
include_once("config/Auth.php");
session_start();
$sessionStart = true;   

if (!isset($_SESSION['sum'])) {
    $_SESSION['sum'] = 0;
}

$auth = new Auth();
$conn = $auth->connect();
$ingredient_requirement = array();
$ingredient_amount = array();
$ids = array(1, 2, 3);
$ordersPerIngredient = array();

$sqlQuery = "SELECT quantity FROM recipe WHERE product_id = 1";
$query = $conn->prepare($sqlQuery);
$query->execute();
$recipees = $query->fetchAll(PDO::FETCH_ASSOC);

foreach ($recipees as $recipee) {
    array_push($ingredient_requirement, $recipee["quantity"]);
}

array_push($ingredient_requirement, 1);


foreach ($ids as $id) {
    $sqlQuery = "SELECT quantity FROM ingredients WHERE id=:id";
    $query = $conn->prepare($sqlQuery);
    $query->bindParam(':id', $id);
    $query->execute();
    $amount = intval($query->fetchColumn());
    array_push($ingredient_amount, $amount);
}

for ($i = 0; $i < sizeof($ingredient_amount); $i++) {
    array_push($ordersPerIngredient, $ingredient_amount[$i] / $ingredient_requirement[$i]);
}


$ordersLeft = $ordersPerIngredient[0];
$ordersLeftWithSugar = $ordersPerIngredient[2];

for ($i = 1; $i < sizeof($ordersPerIngredient)-1; $i++) {
    if ($ordersLeft > $ordersPerIngredient[$i]) {
        $ordersLeft = $ordersPerIngredient[$i];
    }
}

if($ordersLeftWithSugar > $ordersLeft) {
    $ordersLeftWithSugar = $ordersLeft;
}

$_SESSION["ordersLeft"] = (int)$ordersLeft;
$_SESSION["ordersLeftWithSugar"] = (int)$ordersLeftWithSugar;


if (isset($_POST['submit'])) {
    // Get the value of the input field
    $number = $_POST['number'];
    // Check if the value is valid
    if ($number == 1 || $number == 5 || $number == 10) {
        // Add the value to the session variable
        if (!isset($_SESSION['sum'])) {
            $_SESSION['sum'] = 0;
        }
        $_SESSION['sum'] += $number;
    }
}
?>

<body>
    <h1 style="text-align:center">Vending Machine</h1>
    <?php
        echo "<p style='text-align:center;'> Amount of coffee's left:" . $_SESSION["ordersLeft"];
        echo "<p style='text-align:center;'> Amount of coffee's left with sugar:" . $_SESSION["ordersLeftWithSugar"];
    ?>
    <img src="images\MarketOne-Coffee-Vending-Machine-1.png" height="550" class="image">

    <form method="post" style="text-align:center">
        <label for="number">Insert money (1$, 5$, or 10$ banknote): </label>
        <input type="number" name="number" id="number" min="1" step="1" required>
        <button type="submit" name="submit">Add</button>
    </form>

    <?php
    // Display the current sum
    echo "<p style='text-align:center;'>  Current amount: " . (isset($_SESSION['sum']) ? $_SESSION['sum'] : 0);
    ?>

    <form class="order" action="actions/order.php" style="text-align:center" method="post">
        <input type="text" name="p[0][product]">
        <input type="number" width="10" name="p[0][productAmount]" min="1" required>
        <input type="checkbox" name="p[0][extraSugar]" value="true">Extra sugar</input><br>
        <input type="text" name="p[1][product]">
        <input type="number" width="10" name="p[1][productAmount]" min="1" required>
        <input type="checkbox" name="p[1][extraSugar]" value="true">Extra sugar</input><br>
        <input type="hidden" name="amount" value="<?php echo $_SESSION['sum']; ?>">
        <input type="submit" onclick="submitForm" value="Order">
    </form>


</body>


</body>

</html>