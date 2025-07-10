<?php
header('Content-Type: application/json');
$dbfile = __DIR__ . '/../database.db';
$pdo = new PDO('sqlite:' . $dbfile);

switch($_SERVER['REQUEST_METHOD']) {
  case 'GET':
    if (isset($_GET['stock_in_history'])) {
      $product_id = intval($_GET['product_id'] ?? 0);
      $where = $product_id ? 'WHERE product_id='.$product_id : '';
      $stmt = $pdo->query("SELECT * FROM stock_in_history $where ORDER BY created_at DESC");
      echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
      break;
    }
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 8;
    $offset = ($page-1)*$perPage;
    $where = $search ? "WHERE productname LIKE :search" : '';
    $stmt = $pdo->prepare("SELECT * FROM products $where ORDER BY id DESC LIMIT :offset, :perPage");
    if ($search) $stmt->bindValue(':search', "%$search%");
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    break;
  case 'POST':
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO products (productname, categories, barcode, cost, price, stock, unit, note, stock_noti) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
      $data['productname'], $data['categories'], $data['barcode'], $data['cost'], $data['price'], $data['stock'], $data['unit'], $data['note'], $data['stock_noti']
    ]);
    $product_id = $pdo->lastInsertId();
    if ($data['stock'] > 0) {
      $pdo->prepare("INSERT INTO stock_in_history (product_id, qty, note, created_at) VALUES (?, ?, ?, ?)")
        ->execute([$product_id, $data['stock'], 'Initial stock', date('Y-m-d H:i:s')]);
    }
    echo json_encode(['success'=>true, 'id'=>$pdo->lastInsertId()]);
    break;
  case 'PUT':
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    $data = json_decode(file_get_contents('php://input'), true);
    // Get old stock
    $old = $pdo->query("SELECT stock FROM products WHERE id=".$id)->fetch(PDO::FETCH_ASSOC);
    $oldStock = $old ? intval($old['stock']) : 0;
    $stmt = $pdo->prepare("UPDATE products SET productname=?, categories=?, barcode=?, cost=?, price=?, stock=?, unit=?, note=?, stock_noti=? WHERE id=?");
    $stmt->execute([
      $data['productname'], $data['categories'], $data['barcode'], $data['cost'], $data['price'], $data['stock'], $data['unit'], $data['note'], $data['stock_noti'], $id
    ]);
    // Log stock in if increased
    $diff = intval($data['stock']) - $oldStock;
    if ($diff > 0) {
      $pdo->prepare("INSERT INTO stock_in_history (product_id, qty, note, created_at) VALUES (?, ?, ?, ?)")
        ->execute([$id, $diff, 'Stock increased', date('Y-m-d H:i:s')]);
    }
    echo json_encode(['success'=>true]);
    break;
  case 'DELETE':
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
    break;
}
?>
