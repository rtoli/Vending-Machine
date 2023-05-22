<?php
class Auth {

    private $host = "localhost";
    private $db_name = "vending_machine";
    private $username = "root";
    private $password = "";
    public $connection;

    // get the database connection
    public function connect(){

        $this->connection = null;

        try{
            $this->connection = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }catch(PDOException $e){
            echo "Connection Error: " . $e->getMessage();
        }

        return $this->connection;
    }
}