<?php

include_once "config.php";

function connectDb(){
    $servername = SERVERNAME;
    $username = USERNAME;
    $password = PASSWORD;
    $dbname = DATABASE;

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    } 
    catch(PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
    }
}

/**
 * Haal alle organisaties op
 */
function getData($table){
    $conn = connectDb();

    $sql = "SELECT * FROM $table";
    $query = $conn->prepare($sql);
    $query->execute();
    return $query->fetchAll();
}

/**
 * Haal 1 organisatie op
 */
function getOrganisatie($id){
    $conn = connectDb();

    $sql = "SELECT * FROM " . CRUD_TABLE . " WHERE id = :id";
    $query = $conn->prepare($sql);
    $query->execute([':id' => $id]);

    return $query->fetch();
}

/**
 * Overzicht organisaties
 */
function ovzOrganisaties(){
    $result = getData(CRUD_TABLE);
    printTable($result);
}

/**
 * Algemene tabel print functie
 */
function printTable($result){

    $table = "<table>";

    $headers = array_keys($result[0]);
    $table .= "<tr>";

    foreach($headers as $header){
        $table .= "<th>" . $header . "</th>";   
    }

    $table .= "</tr>";

    foreach ($result as $row) {
        $table .= "<tr>";

        foreach ($row as $cell) {
            $table .= "<td>" . $cell . "</td>";
        }

        $table .= "</tr>";
    }

    $table .= "</table>";

    echo $table;
}

/**
 * CRUD overzicht organisaties
 */
function crudOrganisatie(){

    echo "
    <h1>Organisaties beheren</h1>
    <nav>
        <a href='insert_organisatie.php'>Nieuwe organisatie toevoegen</a>
    </nav><br>";

    $result = getData(CRUD_TABLE);

    printCrudOrganisatie($result);
}

/**
 * Tabel met acties (wijzigen/verwijderen)
 */
function printCrudOrganisatie($result){

    $table = "<table>";

    $headers = array_keys($result[0]);
    $table .= "<tr>";

    foreach($headers as $header){
        $table .= "<th>" . $header . "</th>";   
    }

    $table .= "<th colspan='2'>Actie</th>";
    $table .= "</tr>";

    foreach ($result as $row) {

        $table .= "<tr>";

        foreach ($row as $cell) {
            $table .= "<td>" . $cell . "</td>";  
        }

        // Wijzigen
        $table .= "<td>
            <form method='post' action='update_organisatie.php?id=$row[id]'>       
                <button>Wijzig</button>	 
            </form>
        </td>";

        // Verwijderen
        $table .= "<td>
            <form method='post' action='delete_organisatie.php?id=$row[id]'>       
                <button>Verwijder</button>	 
            </form>
        </td>";

        $table .= "</tr>";
    }

    $table .= "</table>";

    echo $table;
}

/**
 * Update organisatie
 */
function updateOrganisatie($row){

    $conn = connectDb();

    $sql = "UPDATE " . CRUD_TABLE . "
    SET 
        naam = :naam,
        adres = :adres,
        stad = :stad
    WHERE id = :id";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':naam' => $row['naam'],
        ':adres' => $row['adres'],
        ':stad' => $row['stad'],
        ':id' => $row['id']
    ]);

    return ($stmt->rowCount() == 1);
}

/**
 * Insert organisatie
 */
function insertOrganisatie($post){

    $conn = connectDb();

    $sql = "INSERT INTO " . CRUD_TABLE . " (naam, adres, stad)
            VALUES (:naam, :adres, :stad)";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':naam' => $_POST['naam'],
        ':adres' => $_POST['adres'],
        ':stad' => $_POST['stad']
    ]);

    return ($stmt->rowCount() == 1);
}

/**
 * Delete organisatie
 */
function deleteOrganisatie($id){

    $conn = connectDb();

    $sql = "DELETE FROM " . CRUD_TABLE . " WHERE id = :id";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':id' => $id
    ]);

    return ($stmt->rowCount() == 1);
}

?>