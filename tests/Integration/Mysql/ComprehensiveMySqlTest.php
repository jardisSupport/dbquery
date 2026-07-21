<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Integration\Mysql;

use JardisSupport\DbQuery\DbDelete;
use JardisSupport\DbQuery\DbInsert;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\DbUpdate;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\DbQuery\Tests\Integration\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive MySQL Integration Tests
 *
 * Tests all builder features against a real MySQL database with rich test data.
 * From simple queries to complex multi-table JOINs, CTEs, subqueries, and JSON.
 */
class ComprehensiveMySqlTest extends TestCase
{
    private const DIALECT = 'mysql';
    private DatabaseConnection $db;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->db = new DatabaseConnection();
        $this->pdo = $this->db->getMysqlConnection();

        $this->db->createUsersWithJsonTable($this->pdo, self::DIALECT);
        $this->db->createOrdersWithStatusTable($this->pdo, self::DIALECT);
        $this->db->createCategoriesTable($this->pdo, self::DIALECT);
        $this->db->createProductsTable($this->pdo, self::DIALECT);
        $this->db->insertComprehensiveTestData($this->pdo, self::DIALECT);
    }

    protected function tearDown(): void
    {
        $this->db->dropTestTable($this->pdo, 'products');
        $this->db->dropTestTable($this->pdo, 'orders');
        $this->db->dropTestTable($this->pdo, 'categories');
        $this->db->dropTestTable($this->pdo, 'users');
    }

    /**
     * Execute a prepared query and return all rows
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetch(DbQuery $query): array
    {
        $prepared = $query->sql(self::DIALECT);
        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        return $stmt->fetchAll();
    }

    /**
     * Execute a prepared query and return first row
     *
     * @return array<string, mixed>|false
     */
    private function fetchOne(DbQuery $query): array|false
    {
        $prepared = $query->sql(self::DIALECT);
        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        return $stmt->fetch();
    }

    // ==================== SELECT Basic ====================

    public function testSelectAll(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('*')->from('users')
        );

        $this->assertCount(6, $results);
    }

    public function testSelectSpecificFields(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name, email')->from('users')
        );

        $this->assertCount(6, $results);
        $this->assertArrayHasKey('name', $results[0]);
        $this->assertArrayHasKey('email', $results[0]);
        $this->assertArrayNotHasKey('age', $results[0]);
    }

    public function testSelectDistinct(): void
    {
        $results = $this->fetch(
            (new DbQuery())->distinct()->select('status')->from('users')
        );

        $this->assertCount(3, $results); // active, inactive, deleted
    }

    // ==================== WHERE Conditions ====================

    public function testWhereEquals(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')->where('status')->equals('active')
        );

        $this->assertCount(4, $results);
    }

    public function testWhereNotEquals(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')->where('status')->notEquals('active')
        );

        $this->assertCount(2, $results);
    }

    public function testWhereGreater(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name, age')->from('users')->where('age')->greater(30)
        );

        $this->assertCount(2, $results); // Bob 35, O'Brien 40
    }

    public function testWhereLower(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')->where('age')->lower(25)
        );

        $this->assertCount(1, $results); // Charlie 22
    }

    public function testWhereGreaterEquals(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')->where('age')->greaterEquals(35)
        );

        $this->assertCount(2, $results); // Bob 35, O'Brien 40
    }

    public function testWhereLowerEquals(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')->where('age')->lowerEquals(25)
        );

        $this->assertCount(2, $results); // Jane 25, Charlie 22
    }

    public function testWhereLike(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')->where('name')->like('J%')
        );

        $this->assertCount(2, $results); // John, Jane
    }

    public function testWhereNotLike(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')->where('name')->notLike('J%')
        );

        $this->assertCount(4, $results);
    }

    public function testWhereIn(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')
                ->where('status')->in(['active', 'deleted'])
        );

        $this->assertCount(5, $results);
    }

    public function testWhereNotIn(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')
                ->where('status')->notIn(['active', 'deleted'])
        );

        $this->assertCount(1, $results); // Bob (inactive)
    }

    public function testWhereBetween(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name, age')->from('users')
                ->where('age')->between(25, 30)
        );

        $this->assertCount(3, $results); // Jane 25, Alice 28, John 30
    }

    public function testWhereNotBetween(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')
                ->where('age')->notBetween(25, 35)
        );

        $this->assertCount(2, $results); // O'Brien 40, Charlie 22
    }

    public function testWhereIsNull(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')
                ->where('metadata')->isNull()
        );

        $this->assertCount(1, $results); // Charlie
    }

    public function testWhereIsNotNull(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')
                ->where('metadata')->isNotNull()
        );

        $this->assertCount(5, $results);
    }

    public function testWhereAndCondition(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')
                ->where('status')->equals('active')
                ->and('age')->greater(28)
        );

        $this->assertCount(2, $results); // John 30, O'Brien 40
    }

    public function testWhereOrCondition(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')
                ->where('status')->equals('deleted')
                ->or('status')->equals('inactive')
        );

        $this->assertCount(2, $results); // Bob, Charlie
    }

    public function testWhereWithBrackets(): void
    {
        // (status = 'active' AND age > 30) OR (status = 'inactive')
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')
                ->where('status', '(')->equals('active')
                ->and('age')->greater(30, ')')
                ->or('status', '(')->equals('inactive', ')')
        );

        $this->assertCount(2, $results); // O'Brien (active, 40) + Bob (inactive)
    }

    public function testWhereSpecialCharacterQuotes(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name, age')->from('users')
                ->where('name')->equals("O'Brien")
        );

        $this->assertCount(1, $results);
        $this->assertEquals(40, (int) $results[0]['age']);
    }

    public function testWhereSpecialCharacterBackslash(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name, age')->from('users')
                ->where('name')->equals('Charlie\\Path')
        );

        $this->assertCount(1, $results);
        $this->assertEquals(22, (int) $results[0]['age']);
    }

    // ==================== ORDER BY, LIMIT, OFFSET ====================

    public function testOrderByAsc(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name, age')->from('users')->orderBy('age', 'ASC')
        );

        $this->assertEquals('Charlie\\Path', $results[0]['name']);
        $this->assertEquals("O'Brien", $results[5]['name']);
    }

    public function testOrderByDesc(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name, age')->from('users')->orderBy('age', 'DESC')
        );

        $this->assertEquals("O'Brien", $results[0]['name']);
    }

    public function testLimit(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')
                ->orderBy('age', 'ASC')->limit(3)
        );

        $this->assertCount(3, $results);
    }

    public function testLimitWithOffset(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('name, age')->from('users')
                ->orderBy('age', 'ASC')->limit(2, 2)
        );

        $this->assertCount(2, $results);
        $this->assertEquals(28, (int) $results[0]['age']); // Alice (3rd)
        $this->assertEquals(30, (int) $results[1]['age']); // John (4th)
    }

    // ==================== GROUP BY + HAVING ====================

    public function testGroupByWithCount(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('status, COUNT(*) as cnt')->from('users')
                ->groupBy('status')->orderBy('cnt', 'DESC')
        );

        $this->assertCount(3, $results);
        $this->assertEquals('active', $results[0]['status']);
        $this->assertEquals(4, (int) $results[0]['cnt']);
    }

    public function testGroupByWithHaving(): void
    {
        $results = $this->fetch(
            (new DbQuery())->select('status, COUNT(*) as cnt')->from('users')
                ->groupBy('status')
                ->having('COUNT(*)')->greaterEquals(2)
        );

        $this->assertCount(1, $results); // only 'active' has >= 2
        $this->assertEquals('active', $results[0]['status']);
    }

    public function testGroupByWithAggregates(): void
    {
        $row = $this->fetchOne(
            (new DbQuery())
                ->select('COUNT(*) as total, SUM(amount) as total_amount, AVG(amount) as avg_amount, MIN(amount) as min_amount, MAX(amount) as max_amount')
                ->from('orders')
                ->where('status')->equals('completed')
        );

        $this->assertNotFalse($row);
        $this->assertEquals(4, (int) $row['total']);
        $this->assertEquals(25.50, (float) $row['min_amount']);
        $this->assertEquals(1200.00, (float) $row['max_amount']);
    }

    public function testGroupByMultipleColumnsWithHaving(): void
    {
        // Orders per user and status, only groups with total > 100
        $results = $this->fetch(
            (new DbQuery())
                ->select('user_id, status, SUM(amount) as total')
                ->from('orders')
                ->groupBy('user_id, status')
                ->having('SUM(amount)')->greater(100)
                ->orderBy('total', 'DESC')
        );

        $this->assertGreaterThan(0, count($results));
        $this->assertGreaterThan(100, (float) $results[0]['total']);
    }

    // ==================== JOINs ====================

    public function testInnerJoin(): void
    {
        $results = $this->fetch(
            (new DbQuery())
                ->select('u.name, o.product, o.amount')
                ->from('users', 'u')
                ->innerJoin('orders', 'u.id = o.user_id', 'o')
                ->orderBy('o.amount', 'DESC')
        );

        $this->assertCount(8, $results);
        $this->assertEquals('Laptop', $results[0]['product']);
    }

    public function testLeftJoin(): void
    {
        $results = $this->fetch(
            (new DbQuery())
                ->select('u.name, COUNT(o.id) as order_count')
                ->from('users', 'u')
                ->leftJoin('orders', 'u.id = o.user_id', 'o')
                ->groupBy('u.id, u.name')
                ->orderBy('order_count', 'DESC')
        );

        $this->assertCount(6, $results); // all users, even without orders
        // Charlie has 0 orders
        $lastUser = end($results);
        $this->assertEquals(0, (int) $lastUser['order_count']);
    }

    public function testRightJoin(): void
    {
        $results = $this->fetch(
            (new DbQuery())
                ->select('o.product, u.name')
                ->from('users', 'u')
                ->rightJoin('orders', 'u.id = o.user_id', 'o')
                ->orderBy('o.id', 'ASC')
        );

        $this->assertCount(8, $results);
    }

    public function testMultipleJoins(): void
    {
        // users -> orders, products -> categories (through category_id matching)
        $results = $this->fetch(
            (new DbQuery())
                ->select('u.name, o.product, o.amount')
                ->from('users', 'u')
                ->innerJoin('orders', 'u.id = o.user_id', 'o')
                ->where('u.status')->equals('active')
                ->and('o.status')->equals('completed')
                ->orderBy('o.amount', 'DESC')
        );

        $this->assertGreaterThan(0, count($results));
        foreach ($results as $row) {
            $this->assertNotEmpty($row['name']);
        }
    }

    public function testCrossJoin(): void
    {
        $results = $this->fetch(
            (new DbQuery())
                ->select('u.name, c.name as category')
                ->from('users', 'u')
                ->crossJoin('categories', 'c')
                ->where('c.parent_id')->isNull()
                ->orderBy('u.name', 'ASC')
        );

        // 6 users * 2 root categories = 12
        $this->assertCount(12, $results);
    }

    public function testJoinWithSubquery(): void
    {
        $subquery = (new DbQuery())
            ->select('user_id, COUNT(*) as order_count, SUM(amount) as total')
            ->from('orders')
            ->where('status')->equals('completed')
            ->groupBy('user_id');

        $results = $this->fetch(
            (new DbQuery())
                ->select('u.name, COALESCE(o.order_count, 0) as orders, COALESCE(o.total, 0) as spent')
                ->from('users', 'u')
                ->leftJoin($subquery, 'u.id = o.user_id', 'o')
                ->orderBy('spent', 'DESC')
        );

        $this->assertCount(6, $results);
        $this->assertEquals('John Doe', $results[0]['name']); // highest spender
    }

    // ==================== Subqueries ====================

    public function testSubqueryInWhere(): void
    {
        // Users who have completed orders
        $subquery = (new DbQuery())
            ->select('DISTINCT user_id')
            ->from('orders')
            ->where('status')->equals('completed');

        $results = $this->fetch(
            (new DbQuery())
                ->select('name')
                ->from('users')
                ->where('id')->in($subquery)
                ->orderBy('name', 'ASC')
        );

        $this->assertGreaterThan(0, count($results));
    }

    public function testSubqueryInFrom(): void
    {
        // Derived table: average order amount per user
        $subquery = (new DbQuery())
            ->select('user_id, AVG(amount) as avg_amount')
            ->from('orders')
            ->groupBy('user_id');

        $results = $this->fetch(
            (new DbQuery())
                ->select('u.name, sub.avg_amount')
                ->from($subquery, 'sub')
                ->innerJoin('users', 'sub.user_id = u.id', 'u')
                ->where('sub.avg_amount')->greater(100)
                ->orderBy('sub.avg_amount', 'DESC')
        );

        $this->assertGreaterThan(0, count($results));
        $this->assertGreaterThan(100, (float) $results[0]['avg_amount']);
    }

    public function testExistsSubquery(): void
    {
        // Users who have at least one pending order
        $subquery = (new DbQuery())
            ->select('1')
            ->from('orders')
            ->where('orders.user_id')->equals(Expression::raw('users.id'))
            ->and('orders.status')->equals('pending');

        $results = $this->fetch(
            (new DbQuery())
                ->select('name')
                ->from('users')
                ->where()->exists($subquery)
                ->orderBy('name', 'ASC')
        );

        $this->assertGreaterThan(0, count($results));
    }

    public function testNotExistsSubquery(): void
    {
        // Users who have NO orders at all
        $subquery = (new DbQuery())
            ->select('1')
            ->from('orders')
            ->where('orders.user_id')->equals(Expression::raw('users.id'));

        $results = $this->fetch(
            (new DbQuery())
                ->select('name')
                ->from('users')
                ->where()->notExists($subquery)
        );

        $this->assertCount(1, $results); // Charlie has no orders
        $this->assertEquals('Charlie\\Path', $results[0]['name']);
    }

    public function testSelectSubquery(): void
    {
        $orderCountSub = (new DbQuery())
            ->select('COUNT(*)')
            ->from('orders')
            ->where('orders.user_id')->equals(Expression::raw('u.id'));

        $results = $this->fetch(
            (new DbQuery())
                ->select('u.name')
                ->selectSubquery($orderCountSub, 'order_count')
                ->from('users', 'u')
                ->where('u.status')->equals('active')
                ->orderBy('order_count', 'DESC')
        );

        $this->assertCount(4, $results);
        $this->assertArrayHasKey('order_count', $results[0]);
    }

    // ==================== UNION ====================

    public function testUnion(): void
    {
        $activeUsers = (new DbQuery())
            ->select("name, 'user' as source")
            ->from('users')
            ->where('status')->equals('active');

        $completedProducts = (new DbQuery())
            ->select("product as name, 'order' as source")
            ->from('orders')
            ->where('status')->equals('completed');

        $results = $this->fetch(
            $activeUsers->union($completedProducts)
        );

        $this->assertGreaterThan(4, count($results)); // at least 4 active users + some products
    }

    public function testUnionAll(): void
    {
        $query1 = (new DbQuery())->select('name')->from('users')->where('status')->equals('active');
        $query2 = (new DbQuery())->select('name')->from('users')->where('age')->greater(30);

        $results = $this->fetch($query1->unionAll($query2));

        // UNION ALL keeps duplicates - O'Brien is in both sets
        $this->assertGreaterThan(4, count($results));
    }

    // ==================== CTEs ====================

    public function testCteSimple(): void
    {
        $results = $this->fetch(
            (new DbQuery())
                ->with('active_users', (new DbQuery())
                    ->select('id, name, age')
                    ->from('users')
                    ->where('status')->equals('active'))
                ->select('name, age')
                ->from('active_users')
                ->where('age')->greater(28)
                ->orderBy('age', 'ASC')
        );

        $this->assertGreaterThan(0, count($results));
        foreach ($results as $row) {
            $this->assertGreaterThan(28, (int) $row['age']);
        }
    }

    public function testCteRecursive(): void
    {
        // Recursive CTE to traverse category tree
        $results = $this->fetch(
            (new DbQuery())
                ->withRecursive('category_tree', (new DbQuery())
                    ->select('id, name, parent_id, 0 as depth')
                    ->from('categories')
                    ->where('parent_id')->isNull()
                    ->unionAll(
                        (new DbQuery())
                            ->select('c.id, c.name, c.parent_id, ct.depth + 1')
                            ->from('categories', 'c')
                            ->innerJoin('category_tree', 'c.parent_id = ct.id', 'ct')
                    ))
                ->select('*')
                ->from('category_tree')
                ->orderBy('depth', 'ASC')
        );

        $this->assertCount(6, $results);
        // Root categories have depth 0
        $this->assertEquals(0, (int) $results[0]['depth']);
        // Sub-categories have depth 1
        $lastRow = end($results);
        $this->assertEquals(1, (int) $lastRow['depth']);
    }

    // ==================== Window Functions ====================

    public function testWindowRowNumber(): void
    {
        $query = (new DbQuery())
            ->select('name, age')
            ->selectWindow('ROW_NUMBER', 'rn')
                ->windowOrderBy('age', 'ASC')
                ->endWindow()
            ->from('users')
            ->orderBy('age', 'ASC');

        $results = $this->fetch($query);

        $this->assertCount(6, $results);
        $this->assertEquals(1, (int) $results[0]['rn']);
        $this->assertEquals(6, (int) $results[5]['rn']);
    }

    public function testWindowRankWithPartition(): void
    {
        $query = (new DbQuery())
            ->select('u.name, o.amount')
            ->selectWindow('RANK', 'amount_rank')
                ->partitionBy('o.user_id')
                ->windowOrderBy('o.amount', 'DESC')
                ->endWindow()
            ->from('orders', 'o')
            ->innerJoin('users', 'o.user_id = u.id', 'u');

        $results = $this->fetch($query);

        $this->assertGreaterThan(0, count($results));
        $this->assertArrayHasKey('amount_rank', $results[0]);
    }

    public function testWindowSumOver(): void
    {
        $query = (new DbQuery())
            ->select('product, amount')
            ->selectWindowRef('SUM', 'by_date', 'running_total', 'amount')
            ->from('orders')
            ->window('by_date')
                ->windowOrderBy('order_date', 'ASC')
                ->endWindow()
            ->orderBy('order_date', 'ASC');

        $results = $this->fetch($query);

        $this->assertCount(8, $results);
        $this->assertArrayHasKey('running_total', $results[0]);
        // Last running total should equal total of all orders
        $lastRow = end($results);
        $totalRow = $this->fetchOne(
            (new DbQuery())->select('SUM(amount) as total')->from('orders')
        );
        $this->assertNotFalse($totalRow);
        $this->assertEquals((float) $totalRow['total'], (float) $lastRow['running_total']);
    }

    // ==================== JSON Operations ====================

    public function testJsonExtract(): void
    {
        $results = $this->fetch(
            (new DbQuery())
                ->select('name')
                ->from('users')
                ->whereJson('metadata')->extract('$.country')->equals('DE')
                ->orderBy('name', 'ASC')
        );

        $this->assertCount(2, $results); // John, Bob
    }

    public function testJsonExtractNested(): void
    {
        $results = $this->fetch(
            (new DbQuery())
                ->select('name')
                ->from('users')
                ->whereJson('metadata')->extract('$.settings.theme')->equals('dark')
        );

        $this->assertCount(3, $results); // John, Bob, O'Brien
    }

    public function testJsonContains(): void
    {
        $results = $this->fetch(
            (new DbQuery())
                ->select('name')
                ->from('products')
                ->whereJson('tags')->contains('sale')
                ->orderBy('name', 'ASC')
        );

        $this->assertCount(4, $results); // ThinkPad, Galaxy, Polo Shirt, Gaming Mouse
    }

    public function testJsonLength(): void
    {
        $results = $this->fetch(
            (new DbQuery())
                ->select('name')
                ->from('users')
                ->whereJson('metadata')->extract('$.roles')->isNotNull()
                ->andJson('metadata')->length('$.roles')->greater(1)
        );

        $this->assertGreaterThan(0, count($results)); // John (admin, user), Alice (editor, user)
    }

    // ==================== Complex Combined Queries ====================

    public function testComplexMultiJoinWithAggregateAndHaving(): void
    {
        // Top users by completed order value, only those spending > 100
        $results = $this->fetch(
            (new DbQuery())
                ->select('u.name, COUNT(o.id) as order_count, SUM(o.amount) as total_spent')
                ->from('users', 'u')
                ->innerJoin('orders', 'u.id = o.user_id', 'o')
                ->where('o.status')->equals('completed')
                ->groupBy('u.id, u.name')
                ->having('SUM(o.amount)')->greater(100)
                ->orderBy('total_spent', 'DESC')
        );

        $this->assertGreaterThan(0, count($results));
        $this->assertGreaterThan(100, (float) $results[0]['total_spent']);
    }

    public function testComplexSubqueryWithJoinAndBrackets(): void
    {
        // Users who either (have pending orders AND are active) OR (have > 2 total orders)
        // Use non-prepared mode to avoid subquery binding issues
        $pendingSub = (new DbQuery())
            ->select('1')
            ->from('orders')
            ->where('orders.user_id')->equals(Expression::raw('u.id'))
            ->and('orders.status')->equals('pending');

        $query = (new DbQuery())
            ->select('u.name, u.status')
            ->from('users', 'u')
            ->where('u.status', '(')->equals('active')
            ->and()->exists($pendingSub, ')')
            ->or('u.id', '(')->in(
                (new DbQuery())
                    ->select('user_id')
                    ->from('orders')
                    ->groupBy('user_id')
                    ->having('COUNT(*)')->greater(2)
            , ')')
            ->orderBy('u.name', 'ASC');

        $sql = $query->sql(self::DIALECT, false);
        $stmt = $this->pdo->query($sql);
        $results = $stmt->fetchAll();

        $this->assertGreaterThan(0, count($results));
    }

    public function testComplexCteWithJoinAndWindow(): void
    {
        // CTE: rank users by their total order value, then join back
        $query = (new DbQuery())
            ->with('user_totals', (new DbQuery())
                ->select('user_id, SUM(amount) as total_spent')
                ->from('orders')
                ->where('status')->equals('completed')
                ->groupBy('user_id'))
            ->select('u.name, ut.total_spent')
            ->selectWindow('RANK', 'spending_rank')
                ->windowOrderBy('ut.total_spent', 'DESC')
                ->endWindow()
            ->from('user_totals', 'ut')
            ->innerJoin('users', 'ut.user_id = u.id', 'u');

        $results = $this->fetch($query);

        $this->assertGreaterThan(0, count($results));
        $this->assertEquals(1, (int) $results[0]['spending_rank']);
    }

    // ==================== INSERT ====================

    public function testInsertSingleRow(): void
    {
        $insert = new DbInsert();
        $prepared = $insert
            ->into('users')
            ->fields('name', 'email', 'status', 'age')
            ->values('Test User', 'test@example.com', 'active', 99)
            ->sql(self::DIALECT);

        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $row = $this->fetchOne(
            (new DbQuery())->select('name, age')->from('users')->where('email')->equals('test@example.com')
        );

        $this->assertNotFalse($row);
        $this->assertEquals('Test User', $row['name']);
        $this->assertEquals(99, (int) $row['age']);
    }

    public function testInsertMultipleRows(): void
    {
        $insert = new DbInsert();
        $prepared = $insert
            ->into('categories')
            ->fields('name', 'parent_id')
            ->values('Books', null)
            ->values('Fiction', 7)
            ->values('Non-Fiction', 7)
            ->sql(self::DIALECT);

        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $results = $this->fetch(
            (new DbQuery())->select('name')->from('categories')->where('parent_id')->equals(7)
        );

        $this->assertCount(2, $results);
    }

    public function testInsertFromSelect(): void
    {
        // Create a temp table for archived users
        $this->pdo->exec("CREATE TABLE `archived_users` LIKE `users`");

        $selectQuery = (new DbQuery())
            ->select('name, email, status, age, metadata')
            ->from('users')
            ->where('status')->equals('deleted');

        $insert = new DbInsert();
        $prepared = $insert
            ->into('archived_users')
            ->fields('name', 'email', 'status', 'age', 'metadata')
            ->fromSelect($selectQuery)
            ->sql(self::DIALECT);

        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $count = $this->db->countRows($this->pdo, 'archived_users');
        $this->assertEquals(1, $count); // Only Charlie is deleted

        $this->db->dropTestTable($this->pdo, 'archived_users');
    }

    public function testInsertOnDuplicateKeyUpdate(): void
    {
        $insert = new DbInsert();
        $prepared = $insert
            ->into('users')
            ->fields('name', 'email', 'status', 'age')
            ->values('Updated John', 'john@example.com', 'vip', 31)
            ->onDuplicateKeyUpdate('name', 'Updated John')
            ->onDuplicateKeyUpdate('status', 'vip')
            ->onDuplicateKeyUpdate('age', 31)
            ->sql(self::DIALECT);

        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $row = $this->fetchOne(
            (new DbQuery())->select('name, status, age')->from('users')->where('email')->equals('john@example.com')
        );

        $this->assertNotFalse($row);
        $this->assertEquals('Updated John', $row['name']);
        $this->assertEquals('vip', $row['status']);
        $this->assertEquals(31, (int) $row['age']);
    }

    // ==================== UPDATE ====================

    public function testUpdateSimple(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->set('status', 'suspended')
            ->where('name')->equals('Bob Johnson')
            ->sql(self::DIALECT);

        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $row = $this->fetchOne(
            (new DbQuery())->select('status')->from('users')->where('name')->equals('Bob Johnson')
        );

        $this->assertNotFalse($row);
        $this->assertEquals('suspended', $row['status']);
    }

    public function testUpdateWithMultipleSet(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'premium', 'age' => 31])
            ->where('email')->equals('john@example.com')
            ->sql(self::DIALECT);

        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $row = $this->fetchOne(
            (new DbQuery())->select('status, age')->from('users')->where('email')->equals('john@example.com')
        );

        $this->assertNotFalse($row);
        $this->assertEquals('premium', $row['status']);
        $this->assertEquals(31, (int) $row['age']);
    }

    public function testUpdateWithSubquery(): void
    {
        // Set each user's age to their total order count (contrived but tests subquery in SET)
        $subquery = (new DbQuery())
            ->select('COUNT(*)')
            ->from('orders')
            ->where('orders.user_id')->equals(Expression::raw('users.id'));

        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->set('age', $subquery)
            ->where('status')->equals('active')
            ->sql(self::DIALECT);

        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        // John has 3 orders
        $row = $this->fetchOne(
            (new DbQuery())->select('age')->from('users')->where('email')->equals('john@example.com')
        );

        $this->assertNotFalse($row);
        $this->assertEquals(3, (int) $row['age']);
    }

    public function testUpdateWithJoin(): void
    {
        // Update order notes via JOIN with users
        $update = new DbUpdate();
        $prepared = $update
            ->table('orders', 'o')
            ->set('notes', 'VIP customer')
            ->innerJoin('users', 'o.user_id = u.id', 'u')
            ->where('u.name')->equals("O'Brien")
            ->sql(self::DIALECT);

        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $row = $this->fetchOne(
            (new DbQuery())->select('notes')->from('orders')->where('user_id')->equals(5)
        );

        $this->assertNotFalse($row);
        $this->assertEquals('VIP customer', $row['notes']);
    }

    public function testUpdateWithOrderByAndLimit(): void
    {
        // Update only the oldest 2 users
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->set('status', 'senior')
            ->where('status')->equals('active')
            ->orderBy('age', 'DESC')
            ->limit(2)
            ->sql(self::DIALECT);

        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $results = $this->fetch(
            (new DbQuery())->select('name')->from('users')
                ->where('status')->equals('senior')
                ->orderBy('age', 'DESC')
        );

        $this->assertCount(2, $results);
    }

    // ==================== DELETE ====================

    public function testDeleteSimple(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('orders')
            ->where('status')->equals('cancelled')
            ->sql(self::DIALECT);

        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $count = $this->db->countRows($this->pdo, 'orders');
        $this->assertEquals(7, $count); // 8 - 1 cancelled
    }

    public function testDeleteWithComplexWhere(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('orders')
            ->where('status', '(')->equals('pending')
            ->and('amount')->lower(50, ')')
            ->or('status', '(')->equals('cancelled', ')')
            ->sql(self::DIALECT);

        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $remaining = $this->db->countRows($this->pdo, 'orders');
        $this->assertLessThan(8, $remaining);
    }

    public function testDeleteWithExists(): void
    {
        // Delete orders for inactive users
        // Use non-prepared mode to avoid subquery binding issues in DELETE
        $subquery = (new DbQuery())
            ->select('1')
            ->from('users')
            ->where('users.id')->equals(Expression::raw('orders.user_id'))
            ->and('users.status')->equals('inactive');

        $delete = new DbDelete();
        $sql = $delete
            ->from('orders')
            ->where()->exists($subquery)
            ->sql(self::DIALECT, false);

        $this->pdo->exec($sql);

        // Bob (inactive) had 1 order (Headphones)
        $remaining = $this->db->countRows($this->pdo, 'orders');
        $this->assertEquals(7, $remaining);
    }

    public function testDeleteWithSubqueryInWhere(): void
    {
        // Delete orders for deleted users via subquery
        // Use non-prepared mode to avoid subquery binding issues in DELETE
        $delete = new DbDelete();
        $sql = $delete
            ->from('orders')
            ->where('user_id')->in(
                (new DbQuery())
                    ->select('id')
                    ->from('users')
                    ->where('status')->equals('deleted')
            )
            ->sql(self::DIALECT, false);

        $this->pdo->exec($sql);

        // Charlie (deleted) has 0 orders anyway, so no change
        $remaining = $this->db->countRows($this->pdo, 'orders');
        $this->assertEquals(8, $remaining);
    }

    public function testDeleteWithOrderByAndLimit(): void
    {
        // Delete the 2 cheapest pending orders
        $delete = new DbDelete();
        $prepared = $delete
            ->from('orders')
            ->where('status')->equals('pending')
            ->orderBy('amount', 'ASC')
            ->limit(2)
            ->sql(self::DIALECT);

        $stmt = $this->pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $remaining = $this->db->countRows($this->pdo, 'orders');
        $this->assertEquals(6, $remaining); // 8 - 2
    }
}
