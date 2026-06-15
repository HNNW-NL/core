<?php

echo "<h1>Organisatie toevoegen</h1>";

require_once('functions.php');

// Test of er op de insert-knop is gedrukt 
if (isset($_POST['btn_ins'])) {

    // test of insert gelukt is
    if (insertOrganisatie($_POST) == true) {
        echo "<script>alert('Organisatie is toegevoegd')</script>";
    } else {
        echo '<script>alert("Organisatie is NIET toegevoegd")</script>';
    }
}
?>

<html>
    <body>

        <form method="post">

            <label for="naam">Naam organisatie:</label>
            <input type="text" id="naam" name="naam" required><br>

            <label for="adres">Adres:</label>
            <input type="text" id="adres" name="adres" required><br>

            <label for="stad">Stad:</label>
            <input type="text" id="stad" name="stad" required><br>

            <input type="submit" name="btn_ins" value="Toevoegen">

        </form>

        <br><br>
        <a href='crud_organisaties.php'>Home</a>

    </body>
</html>