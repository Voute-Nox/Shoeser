<?php
$host = "postgresql-shoeser.alwaysdata.net";
$port = 5432;
$dbname = "shoeser_base"; // ou le nom que tu as choisi
$user = "shoeser"; // ton identifiant Alwaysdata
$password = "mycy234"; // ton mot de passe

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Erreur de connexion : " . $e->getMessage();
}
?>