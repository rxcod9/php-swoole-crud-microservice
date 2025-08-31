<?php
use PHPUnit\Framework\TestCase;
use App\Core\DbContext;
use App\Repositories\UserRepository;
final class UserRepositoryTest extends TestCase {
  public function test_create_and_find(): void {
    $pdo = new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT UNIQUE, created_at TEXT, updated_at TEXT)");
    $ctx = new DbContext($pdo); $repo = new UserRepository($ctx);
    $id = $repo->create(['name'=>'a','email'=>'a@b.c']);
    $u = $repo->find($id);
    $this->assertSame('a',$u['name']);
  }
}
