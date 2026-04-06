<?php
require_once '../connection/connect.php'; // adjust if needed

$id = $_GET['id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM blotters WHERE id=?");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        echo "No record found";
        exit;
    }

    echo "<pre>";
    print_r($data);
    echo "</pre>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}