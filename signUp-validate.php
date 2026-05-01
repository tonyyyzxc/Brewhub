<?php

session_start();
include 'config.php';


if($_SERVER["REQUEST_METHOD"] == "POST"){
    $firstname = $_POST['Firstname'];
    $lastname = $_POST['Lastname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $username = $_POST['username'];

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn -> prepare ("SELECT * FROM users WHERE email = ?");

    if ($stmt === false ){
        die("Prepared Failed: " . $conn -> error);
    }

    $stmt -> bind_param("s", $email);
    $stmt -> execute();
    $stmt -> store_result();

    
    
    if($stmt -> num_rows > 0){

        echo json_encode(['status' => 'exists', 'message' => 'Email already exists!']);
   
     } else {
       
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO users (FirstName, LastName, email, password, username, role) VALUES (?, ?, ?, ?, ?, ?)");
        $role = 'buyer';
        $stmt->bind_param("ssssss", $firstname, $lastname, $email, $hashed_password, $username, $role);

        if($stmt->execute()){
            echo json_encode(['status' => 'success', 'message' => 'Account created successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Something went wrong. Try again!']);
        }
    } 

     $stmt->close();
     $conn->close();

} else {
         echo json_encode(['status' => 'error', 'message' => 'Invalid request!']);
    }
