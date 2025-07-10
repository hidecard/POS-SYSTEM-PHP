<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['dbfile'])) {
  $target = __DIR__ . '/database.db';
  if (move_uploaded_file($_FILES['dbfile']['tmp_name'], $target)) {
    echo json_encode(['success'=>true]);
  } else {
    echo json_encode(['success'=>false]);
  }
} else {
  echo json_encode(['success'=>false]);
} 