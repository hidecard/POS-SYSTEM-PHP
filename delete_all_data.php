<?php
header('Content-Type: application/json');
$dbfile = __DIR__ . '/database.db';
$pdo = new PDO('sqlite:' . $dbfile);
try {
  $pdo->beginTransaction();
  $pdo->exec('DELETE FROM sales_items');
  $pdo->exec('DELETE FROM sales');
  $pdo->exec('DELETE FROM products');
  $pdo->exec('DELETE FROM customers');
  $pdo->exec('DELETE FROM categories');
  $pdo->exec('DELETE FROM unit');
  $pdo->exec('DELETE FROM supplier');
  $pdo->exec('DELETE FROM staff');
  $pdo->commit();
  echo json_encode(['success'=>true]);
} catch (Exception $e) {
  $pdo->rollBack();
  echo json_encode(['success'=>false]);
} 