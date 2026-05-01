<?php

session_start();
include 'config.php';

$servername = 'localhost';
$username = 'root';
$password = '';
$dbname = 'brewhub';

$conn = new mysqli($servername,$username,$password,$dbname);


if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    if(isset($_POST['email']) && isset($_POST['password']) && !empty($_POST['email']) && !empty($_POST['password'])){

    $sql = "SELECT user_id, email, FirstName, LastName, password, username, role FROM users where email = ?";

    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("s", $param_email);

        $param_email = $_POST['email'];

        if($stmt->execute()){

            $stmt->store_result();

            if($stmt->num_rows == 1){

                $stmt->bind_result($ID, $email, $firstname, $lastname, $hashed_password, $username, $role);

                if($stmt->fetch()){
                    
                    if(password_verify($_POST['password'], $hashed_password)){

                        if(empty($role)) $role = 'buyer';
                        $_SESSION['loggedin'] = true;
                        $_SESSION['user_id'] = $ID;
                        $_SESSION['email'] = $email;
                        $_SESSION['fullName'] = $firstname;
                        $_SESSION['userName'] = $username;
                        $_SESSION['role'] = $role;


                        if($role == 'admin'){
                            echo json_encode(['status' => 'success', 'redirect' => 'admin/admin.php']);
                        } else if ($role == 'seller'){
                            echo json_encode(['status' => 'success', 'redirect' => 'Seller/SellerDashboard.php']);
                        } else {
                            echo json_encode(['status' => 'success', 'redirect' => 'Buyer/DashBoard.php']);
                        }
                        exit();
                
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
                    }
                } 
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
            }
        } else {
           echo json_encode(['status' => 'error', 'message' => 'Something went wrong!']);
        }
          $stmt->close();
    } 
    }
    $conn->close();
}