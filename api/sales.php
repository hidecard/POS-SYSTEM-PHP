<?php
header('Content-Type: application/json');
$dbfile = __DIR__ . '/../database.db';
$pdo = new PDO('sqlite:' . $dbfile);

switch($_SERVER['REQUEST_METHOD']) {
  case 'GET':
    if (isset($_GET['report'])) {
      $type = $_GET['type'] ?? 'monthly';
      $period = $_GET['period'] ?? date('Y-m');
      $where = '';
      if ($type === 'monthly') {
        $where = "WHERE sale_date LIKE '".addslashes($period)."%'";
      } else if ($type === 'yearly') {
        $where = "WHERE sale_date LIKE '".addslashes($period)."%'";
      }
      $sql = "SELECT * FROM sales $where ORDER BY id DESC";
      $sales = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
      $total = 0; $profit = 0; $byProduct = [];
      foreach ($sales as $sale) {
        $stmt2 = $pdo->prepare("SELECT si.*, p.cost FROM sales_items si LEFT JOIN products p ON si.product_id = p.id WHERE sale_id=?");
        $stmt2->execute([$sale['id']]);
        $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $item) {
          $total += $item['subtotal'];
          $itemProfit = ($item['price'] - ($item['cost'] ?? 0)) * $item['quantity'];
          $profit += $itemProfit;
          if (!isset($byProduct[$item['product_id']])) $byProduct[$item['product_id']] = ['qty'=>0,'total'=>0,'profit'=>0];
          $byProduct[$item['product_id']]['qty'] += $item['quantity'];
          $byProduct[$item['product_id']]['total'] += $item['subtotal'];
          $byProduct[$item['product_id']]['profit'] += $itemProfit;
          $byProduct[$item['product_id']]['name'] = $item['productname'] ?? '';
        }
      }
      echo json_encode(['total'=>$total, 'profit'=>$profit, 'byProduct'=>array_values($byProduct)]);
      break;
    }
    // Get all sales with items
    $stmt = $pdo->query("SELECT * FROM sales ORDER BY id DESC");
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sales as &$sale) {
      $stmt2 = $pdo->prepare("SELECT si.*, p.productname FROM sales_items si LEFT JOIN products p ON si.product_id = p.id WHERE sale_id=?");
      $stmt2->execute([$sale['id']]);
      $sale['items'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode($sales);
    break;
  case 'POST':
    // Create new sale with items
    $data = json_decode(file_get_contents('php://input'), true);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO sales (sale_date, customer_id, staff_id, total, note, payment) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
      $data['sale_date'] ?? date('Y-m-d H:i:s'),
      $data['customer_id'],
      $data['staff_id'],
      $data['total'],
      $data['note'],
      $data['payment'] ?? 0
    ]);
    $sale_id = $pdo->lastInsertId();
    foreach ($data['items'] as $item) {
      $stmt2 = $pdo->prepare("INSERT INTO sales_items (sale_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
      $stmt2->execute([
        $sale_id,
        $item['product_id'],
        $item['quantity'],
        $item['price'],
        $item['subtotal']
      ]);
      // Optionally: update product stock
      $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id=?")->execute([$item['quantity'], $item['product_id']]);
    }
    $pdo->commit();
    echo json_encode(['success'=>true, 'id'=>$sale_id]);
    break;
}
?>
