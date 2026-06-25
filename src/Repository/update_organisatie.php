<?php

require_once('functions.php');

// Test of er op de wijzig-knop is gedrukt 
if (isset($_POST['btn_wzg'])) {

    // test of update gelukt is
    if (updateOrganisatie($_POST) == true) {
        echo "<script>alert('Organisatie is gewijzigd')</script>";
    } else {
        echo '<script>alert("Organisatie is NIET gewijzigd")</script>';
    }
}

// Test of id is meegegeven in de URL
if (isset($_GET['id'])) {  

    $id = $_GET['id'];
    $row = getOrganisatie($id);

?>

<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <title>Organisatie wijzigen</title>
</head>
<body>

  <h2>Organisatie wijzigen</h2>

  <form method="post">

    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">

    <label for="naam">Naam organisatie:</label>
    <input type="text" id="naam" name="naam" required 
           value="<?php echo $row['naam']; ?>"><br>

    <label for="adres">Adres:</label>
    <input type="text" id="adres" name="adres" required 
           value="<?php echo $row['adres']; ?>"><br>

    <label for="stad">Stad:</label>
    <input type="text" id="stad" name="stad" required 
           value="<?php echo $row['stad']; ?>"><br>

    <input type="submit" name="btn_wzg" value="Wijzig">

  </form>

  <br><br>
  <a href='crud_organisaties.php'>Home</a>

</body>
</html>

<?php
} else {
    echo "Geen id opgegeven<br>";
}
?>