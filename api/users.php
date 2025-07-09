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
    $where = $search ? "WHERE username LIKE :search OR role LIKE :search" : '';
    $stmt = $pdo->prepare("SELECT id, username, role, staff_id, created_at FROM users $where ORDER BY id DESC LIMIT :offset, :perPage");
    if ($search) $stmt->bindValue(':search', "%$search%");
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    break;
  case 'POST':
    $data = json_decode(file_get_contents('php://input'), true);
    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, staff_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([
      $data['username'], $hash, $data['role'], $data['staff_id']
    ]);
    echo json_encode(['success'=>true, 'id'=>$pdo->lastInsertId()]);
    break;
  case 'PUT':
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    $data = json_decode(file_get_contents('php://input'), true);
    if (!empty($data['password'])) {
      $hash = password_hash($data['password'], PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, role=?, staff_id=? WHERE id=?");
      $stmt->execute([
        $data['username'], $hash, $data['role'], $data['staff_id'], $id
      ]);
    } else {
      $stmt = $pdo->prepare("UPDATE users SET username=?, role=?, staff_id=? WHERE id=?");
      $stmt->execute([
        $data['username'], $data['role'], $data['staff_id'], $id
      ]);
    }
    echo json_encode(['success'=>true]);
    break;
  case 'DELETE':
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
    break;
} 