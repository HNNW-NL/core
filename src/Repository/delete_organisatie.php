<?php
include 'functions.php';

// Haal organisatie-id op uit de URL
if (isset($_GET['id'])) {

    // Test of verwijderen gelukt is
    if (deleteOrganisatie($_GET['id']) == true) {
        echo '<script>alert("Organisatie met ID ' . $_GET['id'] . ' is verwijderd")</script>';
        echo "<script> location.replace('crud_organisaties.php'); </script>";
    } else {
        echo '<script>alert("Organisatie is NIET verwijderd")</script>';
    }
}
?>