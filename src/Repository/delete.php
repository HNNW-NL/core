<?php
include 'functions.php';

// Haal user uit de database
if(isset($_GET['id'])){

    // test of insert gelukt is
    if(deleteUser($_GET['id']) == true){
        echo '<script>alert("User: ' . $_GET['id'] . ' is verwijderd")</script>';
        echo "<script> location.replace('crud_users.php'); </script>";
    } else {
        echo '<script>alert("User is NIET verwijderd")</script>';
    }
}
?>