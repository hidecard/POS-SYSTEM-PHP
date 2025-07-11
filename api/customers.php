<?php
header('Content-Type: application/json');
$dbfile = __DIR__ . '/../database.db';
$pdo = new PDO('sqlite:' . $dbfile);

switch($_SERVER['REQUEST_METHOD']) {
  case 'GET':
    if (isset($_GET['debts']) && $_GET['debts'] == 1) {
      $sql = "SELECT c.id, c.name, SUM(d.amount) as total_debt FROM debts d LEFT JOIN customers c ON d.customer_id = c.id GROUP BY d.customer_id HAVING total_debt > 0 ORDER BY total_debt DESC";
      $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
      echo json_encode($rows);
      break;
    }
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 8;
    $offset = ($page-1)*$perPage;
    $where = $search ? "WHERE name LIKE :search" : '';
    $stmt = $pdo->prepare("SELECT * FROM customers $where ORDER BY id DESC LIMIT :offset, :perPage");
    if ($search) $stmt->bindValue(':search', "%$search%");
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    break;
  case 'POST':
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO customers (name, adress, category, note, phonenumber) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
      $data['name'], $data['adress'], $data['category'], $data['note'], $data['phonenumber']
    ]);
    echo json_encode(['success'=>true, 'id'=>$pdo->lastInsertId()]);
    break;
  case 'PUT':
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE customers SET name=?, adress=?, category=?, note=?, phonenumber=? WHERE id=?");
    $stmt->execute([
      $data['name'], $data['adress'], $data['category'], $data['note'], $data['phonenumber'], $id
    ]);
    echo json_encode(['success'=>true]);
    break;
  case 'DELETE':
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM customers WHERE id=?");
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
    break;
}
?>
