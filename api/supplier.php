<?php
header('Content-Type: application/json');
$dbfile = __DIR__ . '/../database.db';
$pdo = new PDO('sqlite:' . $dbfile);

switch($_SERVER['REQUEST_METHOD']) {
  case 'GET':
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 8;
    $offset = ($page-1)*$perPage;
    $where = $search ? "WHERE name LIKE :search" : '';
    $stmt = $pdo->prepare("SELECT * FROM supplier $where ORDER BY id DESC LIMIT :offset, :perPage");
    if ($search) $stmt->bindValue(':search', "%$search%");
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    break;
  case 'POST':
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO supplier (name, adress, phonenumber, note) VALUES (?, ?, ?, ?)");
    $stmt->execute([
      $data['name'], $data['adress'], $data['phonenumber'], $data['note']
    ]);
    echo json_encode(['success'=>true, 'id'=>$pdo->lastInsertId()]);
    break;
  case 'PUT':
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE supplier SET name=?, adress=?, phonenumber=?, note=? WHERE id=?");
    $stmt->execute([
      $data['name'], $data['adress'], $data['phonenumber'], $data['note'], $id
    ]);
    echo json_encode(['success'=>true]);
    break;
  case 'DELETE':
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM supplier WHERE id=?");
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
    break;
}
?>
