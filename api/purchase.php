<?php
header('Content-Type: application/json');
$dbfile = __DIR__ . '/../database.db';
$pdo = new PDO('sqlite:' . $dbfile);

switch($_SERVER['REQUEST_METHOD']) {
  case 'GET':
    if (isset($_GET['report']) && $_GET['report'] == 1) {
      $supplier_id = intval($_GET['supplier_id'] ?? 0);
      $where = $supplier_id ? 'WHERE p.supplier_id='.$supplier_id : '';
      $stmt = $pdo->query("SELECT p.*, pr.productname, s.name as supplier_name FROM purchase p LEFT JOIN products pr ON p.product_id=pr.id LEFT JOIN supplier s ON p.supplier_id=s.id $where ORDER BY date DESC");
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $total = 0;
      foreach ($rows as $row) $total += floatval($row['cost']) * intval($row['qty']);
      // Product-wise summary
      $summary = [];
      foreach ($rows as $row) {
        $pid = $row['product_id'];
        if (!isset($summary[$pid])) {
          $summary[$pid] = [
            'product' => $row['productname'],
            'qty' => 0,
            'sales' => 0,
            'profit' => 0
          ];
        }
        $summary[$pid]['qty'] += intval($row['qty']);
        $summary[$pid]['sales'] += floatval($row['cost']) * intval($row['qty']);
        // Profit = (product.price - product.cost) * qty
        $product = $pdo->query("SELECT price, cost FROM products WHERE id=".$pid)->fetch(PDO::FETCH_ASSOC);
        if ($product) {
          $summary[$pid]['profit'] += (floatval($product['price']) - floatval($product['cost'])) * intval($row['qty']);
        }
      }
      echo json_encode(['purchases'=>$rows, 'total'=>$total, 'byProduct'=>array_values($summary)]);
      break;
    }
    $stmt = $pdo->query("SELECT * FROM purchase ORDER BY date DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    break;
  case 'POST':
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO purchase (product_id, supplier_id, qty, cost, date, note) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
      $data['product_id'], $data['supplier_id'], $data['qty'], $data['cost'], $data['date'] ?? date('Y-m-d H:i:s'), $data['note']
    ]);
    echo json_encode(['success'=>true, 'id'=>$pdo->lastInsertId()]);
    break;
  case 'DELETE':
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM purchase WHERE id=?');
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
    break;
}
?> 