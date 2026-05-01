<?php

session_start();
include 'config.php';


//admin credentials
$adminEmail     = 'admin@gmail.com';
$adminPassword  = 'adminpass';  
$adminFirstname = 'admin';
$adminLastname  = 'admin';
$adminUsername  = 'admin';
$adminRole      = 'admin';

$check = $conn->prepare("SELECT user_id, role FROM users WHERE email = ?");
$check->bind_param("s", $adminEmail);
$check->execute();
$check->store_result();
$check->bind_result($adminId, $existingRole);
$check->fetch();

if ($check->num_rows === 0) {
    $check -> close();
    $hashed = password_hash($adminPassword, PASSWORD_DEFAULT);
    $insert = $conn->prepare(
        "INSERT INTO users (email, FirstName, LastName, password, username, role)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $insert->bind_param("ssssss", $adminEmail, $adminFirstname, $adminLastname, $hashed, $adminUsername, $adminRole);
    $insert->execute();
    $insert->close();
} else if (is_null($existingRole) || $existingRole === '' || $existingRole !== 'admin') {
    $check->close();
    $fix = $conn->prepare("UPDATE users SET role = ? WHERE email = ?");
    $fix->bind_param("ss", $adminRole, $adminEmail);
    $fix->execute();
    $fix->close();
} else {
    $check->close();
}

// ============ //



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
                            echo json_encode(['status' => 'success', 'redirect' => 'Buyer/Dashboard.php']);
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