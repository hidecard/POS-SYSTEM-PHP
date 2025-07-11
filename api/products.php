<?php
header('Content-Type: application/json');
$dbfile = __DIR__ . '/../database.db';
$pdo = new PDO('sqlite:' . $dbfile);

// Add error handler to always return JSON
set_exception_handler(function($e) {
  http_response_code(500);
  echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
  exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
  http_response_code(500);
  echo json_encode(['success'=>false, 'error'=>"$errstr in $errfile on line $errline"]);
  exit;
});

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
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as &$row) {
      if (isset($row['image']) && $row['image']) {
        $row['image_url'] = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/../' . $row['image'];
      }
    }
    echo json_encode($result);
    break;
  case 'POST':
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'multipart/form-data') !== false) {
      $fields = $_POST;
      $imagePath = null;
      if (isset($_FILES['image']) && is_array($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('prod_', true) . '.' . $ext;
        $target = __DIR__ . '/../uploads/' . $filename;
        if (!is_dir(__DIR__ . '/../uploads/')) mkdir(__DIR__ . '/../uploads/', 0777, true);
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
        $imagePath = 'uploads/' . $filename;
      }
      $stmt = $pdo->prepare("INSERT INTO products (productname, categories, barcode, cost, price, stock, unit, note, stock_noti, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->execute([
        $fields['productname'], $fields['categories'], $fields['barcode'], $fields['cost'], $fields['price'], $fields['stock'], $fields['unit'], $fields['note'], $fields['stock_noti'], $imagePath
      ]);
      $product_id = $pdo->lastInsertId();
      if ($fields['stock'] > 0) {
        $pdo->prepare("INSERT INTO stock_in_history (product_id, qty, note, created_at) VALUES (?, ?, ?, ?)")
          ->execute([$product_id, $fields['stock'], 'Initial stock', date('Y-m-d H:i:s')]);
      }
      echo json_encode(['success'=>true, 'id'=>$product_id, 'image'=>$imagePath]);
      break;
    }
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
    echo json_encode(['success'=>true, 'id'=>$product_id]);
    break;
  case 'PUT':
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'multipart/form-data') !== false) {
      $fields = $_POST;
      $imagePath = null;
      if (isset($_FILES['image']) && is_array($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('prod_', true) . '.' . $ext;
        $target = __DIR__ . '/../uploads/' . $filename;
        if (!is_dir(__DIR__ . '/../uploads/')) mkdir(__DIR__ . '/../uploads/', 0777, true);
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
        $imagePath = 'uploads/' . $filename;
      }
      $old = $pdo->query("SELECT stock FROM products WHERE id=".$id)->fetch(PDO::FETCH_ASSOC);
      $oldStock = $old ? intval($old['stock']) : 0;
      $sql = "UPDATE products SET productname=?, categories=?, barcode=?, cost=?, price=?, stock=?, unit=?, note=?, stock_noti=?";
      $paramsArr = [
        $fields['productname'] ?? '', $fields['categories'] ?? '', $fields['barcode'] ?? '', $fields['cost'] ?? 0, $fields['price'] ?? 0, $fields['stock'] ?? 0, $fields['unit'] ?? '', $fields['note'] ?? '', $fields['stock_noti'] ?? 0
      ];
      if ($imagePath) {
        $sql .= ", image=?";
        $paramsArr[] = $imagePath;
      }
      $sql .= " WHERE id=?";
      $paramsArr[] = $id;
      $stmt = $pdo->prepare($sql);
      $stmt->execute($paramsArr);
      $diff = intval($fields['stock'] ?? 0) - $oldStock;
      if ($diff > 0) {
        $pdo->prepare("INSERT INTO stock_in_history (product_id, qty, note, created_at) VALUES (?, ?, ?, ?)")
          ->execute([$id, $diff, 'Stock increased', date('Y-m-d H:i:s')]);
      }
      echo json_encode(['success'=>true, 'image'=>$imagePath]);
      break;
    } else {
      $data = json_decode(file_get_contents('php://input'), true);
      $old = $pdo->query("SELECT stock FROM products WHERE id=".$id)->fetch(PDO::FETCH_ASSOC);
      $oldStock = $old ? intval($old['stock']) : 0;
      $stmt = $pdo->prepare("UPDATE products SET productname=?, categories=?, barcode=?, cost=?, price=?, stock=?, unit=?, note=?, stock_noti=? WHERE id=?");
      $stmt->execute([
        $data['productname'] ?? '', $data['categories'] ?? '', $data['barcode'] ?? '', $data['cost'] ?? 0, $data['price'] ?? 0, $data['stock'] ?? 0, $data['unit'] ?? '', $data['note'] ?? '', $data['stock_noti'] ?? 0, $id
      ]);
      $diff = intval($data['stock'] ?? 0) - $oldStock;
      if ($diff > 0) {
        $pdo->prepare("INSERT INTO stock_in_history (product_id, qty, note, created_at) VALUES (?, ?, ?, ?)")
          ->execute([$id, $diff, 'Stock increased', date('Y-m-d H:i:s')]);
      }
      echo json_encode(['success'=>true]);
      break;
    }
  case 'DELETE':
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
    break;
}
?>
