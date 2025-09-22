<?php
/**
 * UserRepositoryTest
 *
 * Tests for the UserRepository class using a mock MySQLPool connection.
 */

use PHPUnit\Framework\TestCase;
use App\Core\Contexts\DbContext;
use App\Repositories\UserRepository;

class UserRepositoryTest extends TestCase
{
  private $mockConn;
  private $mockCtx;
  private UserRepository $repo;
  private array $users;
  private int $autoIncrement = 1;

  protected function setUp(): void
  {
    // In-memory users table simulation
    $this->users = [];
    $this->autoIncrement = 1;

    // Mock MySQLPool connection
    $this->mockConn = new class($this) {
      private $test;
      public $error = '';
      public $insert_id = 0;
      public $affected_rows = 0;
      public $queryString = '';
      public function __construct($test) { $this->test = $test; }
      public function prepare($sql) {
        $self = $this;
        $this->queryString = $sql;
        return new class($self, $sql) {
          private $conn;
          private $sql;
          public $error = '';
          public $affected_rows = 0;
          public $queryString = '';
          public function __construct($conn, $sql) {
            $this->conn = $conn;
            $this->sql = $sql;
            $this->queryString = $sql;
          }
          public function execute($params) {
            $users = &$this->conn->test->users;
            $autoIncrement = &$this->conn->test->autoIncrement;
            $this->affected_rows = 0;
            if (stripos($this->sql, 'INSERT') === 0) {
              $id = $autoIncrement++;
              $users[$id] = [
                'id' => $id,
                'name' => $params[0],
                'email' => $params[1],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
              ];
              $this->conn->insert_id = $id;
              $this->affected_rows = 1;
              return true;
            }
            if (stripos($this->sql, 'SELECT') === 0) {
              if (strpos($this->sql, 'WHERE id=') !== false) {
                $id = $params[0];
                if (isset($users[$id])) {
                  return [ $users[$id] ];
                }
                return [];
              }
              // List users
              $offset = $params[0];
              $limit = $params[1];
              $all = array_values($users);
              usort($all, fn($a, $b) => $b['id'] <=> $a['id']);
              return array_slice($all, $offset, $limit);
            }
            if (stripos($this->sql, 'UPDATE') === 0) {
              $id = $params[2];
              if (isset($users[$id])) {
                $users[$id]['name'] = $params[0];
                $users[$id]['email'] = $params[1];
                $users[$id]['updated_at'] = date('Y-m-d H:i:s');
                $this->affected_rows = 1;
                return true;
              }
              return false;
            }
            if (stripos($this->sql, 'DELETE') === 0) {
              $id = $params[0];
              if (isset($users[$id])) {
                unset($users[$id]);
                $this->affected_rows = 1;
                return true;
              }
              return false;
            }
            return false;
          }
          public function __get($name) {
            if ($name === 'error') return $this->error;
            if ($name === 'affected_rows') return $this->affected_rows;
            return null;
          }
        };
      }
      public function __get($name) {
        if ($name === 'error') return $this->error;
        if ($name === 'insert_id') return $this->insert_id;
        return null;
      }
    };

    // Mock DbContext
    $this->mockCtx = new class($this->mockConn) extends DbContext {
      private $conn;
      public function __construct($conn) { $this->conn = $conn; }
      public function conn() { return $this->conn; }
    };

    $this->repo = new UserRepository($this->mockCtx);
  }

  public function test_create_and_find()
  {
    $id = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $user = $this->repo->find($id);
    $this->assertNotNull($user);
    $this->assertSame('Alice', $user['name']);
    $this->assertSame('alice@example.com', $user['email']);
  }

  public function test_list_users()
  {
    $this->repo->create(['name' => 'A', 'email' => 'a@b.c']);
    $this->repo->create(['name' => 'B', 'email' => 'b@b.c']);
    $users = $this->repo->list(2, 0);
    $this->assertCount(2, $users);
    $this->assertSame('B', $users[0]['name']);
    $this->assertSame('A', $users[1]['name']);
  }

  public function test_update_user()
  {
    $id = $this->repo->create(['name' => 'Old', 'email' => 'old@b.c']);
    $updated = $this->repo->update($id, ['name' => 'New', 'email' => 'new@b.c']);
    $this->assertTrue($updated);
    $user = $this->repo->find($id);
    $this->assertSame('New', $user['name']);
    $this->assertSame('new@b.c', $user['email']);
  }

  public function test_delete_user()
  {
    $id = $this->repo->create(['name' => 'Del', 'email' => 'del@b.c']);
    $deleted = $this->repo->delete($id);
    $this->assertTrue($deleted);
    $user = $this->repo->find($id);
    $this->assertNull($user);
  }
}
