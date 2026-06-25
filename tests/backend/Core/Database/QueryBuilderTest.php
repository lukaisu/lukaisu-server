<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Core\Database;

use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Database\QueryBuilder class.
 *
 * Tests fluent interface for building SQL queries, including
 * SELECT, INSERT, UPDATE, DELETE operations with various clauses.
 */
class QueryBuilderTest extends TestCase
{
    private static bool $dbConnected = false;

    public static function setUpBeforeClass(): void
    {
        self::$dbConnected = defined('LUKAISU_TEST_DB_AVAILABLE') && LUKAISU_TEST_DB_AVAILABLE;
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }
    }

    protected function tearDown(): void
    {
        if (!self::$dbConnected) {
            return;
        }

        // Clean up test data
        $prefix = '';
        Connection::query("DELETE FROM {$prefix}tags WHERE text LIKE 'test_qb_%'");
        Connection::query("DELETE FROM {$prefix}settings WHERE name LIKE 'test_qb_%'");
    }

    // ===== table() and constructor tests =====

    public function testTableReturnsQueryBuilder(): void
    {
        $qb = QueryBuilder::table('tags');
        $this->assertInstanceOf(QueryBuilder::class, $qb);
    }

    public function testConstructorAddsPrefix(): void
    {

        $qb = new QueryBuilder('tags');
        $sql = $qb->toSql();

        // Should contain table prefix
        $prefix = '';
        $this->assertStringContainsString("{$prefix}tags", $sql);
    }

    // ===== select() tests =====

    public function testSelectDefaultsToAsterisk(): void
    {
        $sql = QueryBuilder::table('tags')->toSql();
        $this->assertStringContainsString('SELECT *', $sql);
    }

    public function testSelectWithSingleColumn(): void
    {
        $sql = QueryBuilder::table('tags')->select('text')->toSql();
        $this->assertStringContainsString('SELECT text', $sql);
    }

    public function testSelectWithMultipleColumns(): void
    {
        $sql = QueryBuilder::table('tags')->select(['id', 'text'])->toSql();
        $this->assertStringContainsString('SELECT id, text', $sql);
    }

    // ===== distinct() tests =====

    public function testDistinct(): void
    {
        $sql = QueryBuilder::table('tags')->select('text')->distinct()->toSql();
        $this->assertStringContainsString('SELECT DISTINCT text', $sql);
    }

    // ===== where() tests =====

    public function testWhereWithEquality(): void
    {
        $sql = QueryBuilder::table('tags')->where('id', '=', 1)->toSql();
        $this->assertStringContainsString("WHERE id = 1", $sql);
    }

    public function testWhereWithImplicitEquality(): void
    {
        $sql = QueryBuilder::table('tags')->where('id', 1)->toSql();
        $this->assertStringContainsString("WHERE id = 1", $sql);
    }

    public function testWhereWithNotEquals(): void
    {
        $sql = QueryBuilder::table('tags')->where('id', '!=', 1)->toSql();
        $this->assertStringContainsString("WHERE id != 1", $sql);
    }

    public function testWhereWithGreaterThan(): void
    {
        $sql = QueryBuilder::table('tags')->where('id', '>', 5)->toSql();
        $this->assertStringContainsString("WHERE id > 5", $sql);
    }

    public function testWhereWithLessThan(): void
    {
        $sql = QueryBuilder::table('tags')->where('id', '<', 10)->toSql();
        $this->assertStringContainsString("WHERE id < 10", $sql);
    }

    public function testWhereWithLike(): void
    {
        $sql = QueryBuilder::table('tags')->where('text', 'LIKE', 'test%')->toSql();
        $this->assertStringContainsString("WHERE text LIKE 'test%'", $sql);
    }

    public function testMultipleWheres(): void
    {
        $sql = QueryBuilder::table('tags')
            ->where('id', '>', 1)
            ->where('text', 'LIKE', 'test%')
            ->toSql();

        $this->assertStringContainsString("WHERE id > 1 AND text LIKE 'test%'", $sql);
    }

    public function testWhereWithBooleanTrue(): void
    {

        $sql = QueryBuilder::table('tags')->where('id', '=', true)->toSql();
        $this->assertStringContainsString("WHERE id = 1", $sql);
    }

    public function testWhereWithBooleanFalse(): void
    {

        $sql = QueryBuilder::table('tags')->where('id', '=', false)->toSql();
        $this->assertStringContainsString("WHERE id = 0", $sql);
    }

    public function testWhereWithFloat(): void
    {

        $sql = QueryBuilder::table('tags')->where('id', '>', 3.14)->toSql();
        $this->assertStringContainsString("WHERE id > 3.14", $sql);
    }

    public function testWhereWithNull(): void
    {

        // When using null with =, it should produce IS NULL
        $sql = QueryBuilder::table('tags')->whereNull('comment')->toSql();
        $this->assertStringContainsString("WHERE comment IS NULL", $sql);
    }

    // ===== orWhere() tests =====

    public function testOrWhere(): void
    {
        $sql = QueryBuilder::table('tags')
            ->where('id', 1)
            ->orWhere('id', 2)
            ->toSql();

        $this->assertStringContainsString("WHERE id = 1 OR id = 2", $sql);
    }

    // ===== whereIn() tests =====

    public function testWhereIn(): void
    {
        $sql = QueryBuilder::table('tags')->whereIn('id', [1, 2, 3])->toSql();
        $this->assertStringContainsString("WHERE id IN (1, 2, 3)", $sql);
    }

    public function testWhereNotIn(): void
    {
        $sql = QueryBuilder::table('tags')->whereNotIn('id', [1, 2])->toSql();
        $this->assertStringContainsString("WHERE id NOT IN (1, 2)", $sql);
    }

    // ===== whereNull() tests =====

    public function testWhereNull(): void
    {
        $sql = QueryBuilder::table('tags')->whereNull('comment')->toSql();
        $this->assertStringContainsString("WHERE comment IS NULL", $sql);
    }

    public function testWhereNotNull(): void
    {
        $sql = QueryBuilder::table('tags')->whereNotNull('comment')->toSql();
        $this->assertStringContainsString("WHERE comment IS NOT NULL", $sql);
    }

    // ===== whereRaw() tests =====

    public function testWhereRaw(): void
    {
        $sql = QueryBuilder::table('tags')->whereRaw("id > 5 AND text != ''")->toSql();
        $this->assertStringContainsString("WHERE id > 5 AND text != ''", $sql);
    }

    // ===== join() tests =====

    public function testJoinWithExplicitOperator(): void
    {
        $prefix = '';
        $sql = QueryBuilder::table('tags')
            ->join('word_tag_map', 'tags.id', '=', 'word_tag_map.tag_id')
            ->toSql();

        $this->assertStringContainsString("INNER JOIN {$prefix}word_tag_map", $sql);
        $this->assertStringContainsString("ON tags.id = word_tag_map.tag_id", $sql);
    }

    public function testJoinWithImplicitEquality(): void
    {
        $prefix = '';
        $sql = QueryBuilder::table('tags')
            ->join('word_tag_map', 'tags.id', 'word_tag_map.tag_id')
            ->toSql();

        $this->assertStringContainsString("INNER JOIN {$prefix}word_tag_map", $sql);
        $this->assertStringContainsString("ON tags.id = word_tag_map.tag_id", $sql);
    }

    public function testLeftJoin(): void
    {
        $prefix = '';
        $sql = QueryBuilder::table('tags')
            ->leftJoin('word_tag_map', 'tags.id', 'word_tag_map.tag_id')
            ->toSql();

        $this->assertStringContainsString("LEFT JOIN {$prefix}word_tag_map", $sql);
    }

    public function testRightJoin(): void
    {
        $prefix = '';
        $sql = QueryBuilder::table('tags')
            ->rightJoin('word_tag_map', 'tags.id', 'word_tag_map.tag_id')
            ->toSql();

        $this->assertStringContainsString("RIGHT JOIN {$prefix}word_tag_map", $sql);
    }

    public function testJoinWithNonEqualityOperator(): void
    {
        $prefix = '';
        $sql = QueryBuilder::table('tags')
            ->join('word_tag_map', 'tags.id', '!=', 'word_tag_map.tag_id')
            ->toSql();

        $this->assertStringContainsString("INNER JOIN {$prefix}word_tag_map", $sql);
        $this->assertStringContainsString("ON tags.id != word_tag_map.tag_id", $sql);
    }

    // ===== orderBy() tests =====

    public function testOrderByAscending(): void
    {
        $sql = QueryBuilder::table('tags')->orderBy('text')->toSql();
        $this->assertStringContainsString("ORDER BY text ASC", $sql);
    }

    public function testOrderByDescending(): void
    {
        $sql = QueryBuilder::table('tags')->orderBy('text', 'DESC')->toSql();
        $this->assertStringContainsString("ORDER BY text DESC", $sql);
    }

    public function testOrderByDesc(): void
    {
        $sql = QueryBuilder::table('tags')->orderByDesc('text')->toSql();
        $this->assertStringContainsString("ORDER BY text DESC", $sql);
    }

    public function testMultipleOrderBys(): void
    {
        $sql = QueryBuilder::table('tags')
            ->orderBy('text')
            ->orderBy('id', 'DESC')
            ->toSql();

        $this->assertStringContainsString("ORDER BY text ASC, id DESC", $sql);
    }

    // ===== groupBy() tests =====

    public function testGroupBySingleColumn(): void
    {
        $sql = QueryBuilder::table('tags')->groupBy('text')->toSql();
        $this->assertStringContainsString("GROUP BY text", $sql);
    }

    public function testGroupByMultipleColumns(): void
    {
        $sql = QueryBuilder::table('tags')->groupBy(['text', 'comment'])->toSql();
        $this->assertStringContainsString("GROUP BY text, comment", $sql);
    }

    // ===== limit() and offset() tests =====

    public function testLimit(): void
    {
        $sql = QueryBuilder::table('tags')->limit(10)->toSql();
        $this->assertStringContainsString("LIMIT 10", $sql);
    }

    public function testOffset(): void
    {
        $sql = QueryBuilder::table('tags')->offset(5)->toSql();
        $this->assertStringContainsString("OFFSET 5", $sql);
    }

    public function testLimitAndOffset(): void
    {
        $sql = QueryBuilder::table('tags')->limit(10)->offset(20)->toSql();
        $this->assertStringContainsString("LIMIT 10 OFFSET 20", $sql);
    }

    // ===== get() tests =====

    public function testGetReturnsArray(): void
    {

        $results = QueryBuilder::table('tags')->limit(5)->get();
        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(5, count($results));
    }

    public function testGetWithWhere(): void
    {

        // Insert test data
        QueryBuilder::table('tags')->insert(['text' => 'test_qb_get']);

        $results = QueryBuilder::table('tags')->where('text', 'test_qb_get')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('test_qb_get', $results[0]['text']);
    }

    // ===== first() tests =====

    public function testFirstReturnsRow(): void
    {

        // Insert test data
        QueryBuilder::table('tags')->insert(['text' => 'test_qb_first']);

        $row = QueryBuilder::table('tags')->where('text', 'test_qb_first')->first();

        $this->assertIsArray($row);
        $this->assertEquals('test_qb_first', $row['text']);
    }

    public function testFirstReturnsNull(): void
    {

        $row = QueryBuilder::table('tags')->where('text', 'nonexistent_xyz')->first();
        $this->assertNull($row);
    }

    // ===== value() tests =====

    public function testValueReturnsValue(): void
    {

        // Insert test data
        QueryBuilder::table('tags')->insert(['text' => 'test_qb_value']);

        $value = QueryBuilder::table('tags')->where('text', 'test_qb_value')->value('text');

        $this->assertEquals('test_qb_value', $value);
    }

    public function testValueReturnsNullWhenNoMatch(): void
    {

        $value = QueryBuilder::table('tags')->where('text', 'nonexistent_xyz_123')->value('text');

        $this->assertNull($value);
    }

    // ===== count() tests =====

    public function testCountReturnsInteger(): void
    {

        $count = QueryBuilder::table('tags')->count();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testCountWithWhere(): void
    {

        // Insert test data
        QueryBuilder::table('tags')->insert(['text' => 'test_qb_count1']);
        QueryBuilder::table('tags')->insert(['text' => 'test_qb_count2']);

        $count = QueryBuilder::table('tags')->where('text', 'LIKE', 'test_qb_count%')->count();

        $this->assertEquals(2, $count);
    }

    // ===== exists() tests =====

    public function testExistsReturnsTrue(): void
    {

        // Insert test data
        QueryBuilder::table('tags')->insert(['text' => 'test_qb_exists']);

        $exists = QueryBuilder::table('tags')->where('text', 'test_qb_exists')->exists();

        $this->assertTrue($exists);
    }

    public function testExistsReturnsFalse(): void
    {

        $exists = QueryBuilder::table('tags')->where('text', 'nonexistent_xyz')->exists();
        $this->assertFalse($exists);
    }

    // ===== insert() tests =====

    public function testInsertReturnsId(): void
    {

        $id = QueryBuilder::table('tags')->insert(['text' => 'test_qb_insert']);

        $this->assertGreaterThan(0, $id);

        // Verify inserted
        $row = QueryBuilder::table('tags')->where('id', $id)->first();
        $this->assertEquals('test_qb_insert', $row['text']);
    }

    public function testInsertWithMultipleColumns(): void
    {

        $id = QueryBuilder::table('tags')->insert([
            'text' => 'test_qb_multi',
            'comment' => 'test comment'
        ]);

        $row = QueryBuilder::table('tags')->where('id', $id)->first();
        $this->assertEquals('test_qb_multi', $row['text']);
        $this->assertEquals('test comment', $row['comment']);
    }

    // ===== insertMany() tests =====

    public function testInsertManyReturnsAffectedRows(): void
    {

        $affected = QueryBuilder::table('tags')->insertMany([
            ['text' => 'test_qb_many1'],
            ['text' => 'test_qb_many2'],
            ['text' => 'test_qb_many3']
        ]);

        $this->assertEquals(3, $affected);

        // Verify all inserted
        $count = QueryBuilder::table('tags')->where('text', 'LIKE', 'test_qb_many%')->count();
        $this->assertEquals(3, $count);
    }

    public function testInsertManyWithEmptyArray(): void
    {

        $affected = QueryBuilder::table('tags')->insertMany([]);
        $this->assertEquals(0, $affected);
    }

    // ===== update() tests =====

    public function testUpdateReturnsAffectedRows(): void
    {

        // Insert test data
        $id = QueryBuilder::table('tags')->insert(['text' => 'test_qb_update_old']);

        // Update
        $affected = QueryBuilder::table('tags')
            ->where('id', $id)
            ->update(['text' => 'test_qb_update_new']);

        $this->assertEquals(1, $affected);

        // Verify updated
        $row = QueryBuilder::table('tags')->where('id', $id)->first();
        $this->assertEquals('test_qb_update_new', $row['text']);
    }

    public function testUpdateWithMultipleColumns(): void
    {

        // Insert test data with unique suffix
        $suffix = substr(uniqid(), -6);
        $id = QueryBuilder::table('tags')->insert(['text' => "test_qb_upd_m_{$suffix}"]);

        // Update
        QueryBuilder::table('tags')->where('id', $id)->update([
            'text' => "upd_txt_{$suffix}",
            'comment' => 'updated_comment'
        ]);

        // Verify
        $row = QueryBuilder::table('tags')->where('id', $id)->first();
        $this->assertEquals("upd_txt_{$suffix}", $row['text']);
        $this->assertEquals('updated_comment', $row['comment']);
    }

    // ===== delete() tests =====

    public function testDeleteReturnsAffectedRows(): void
    {

        // Insert test data
        $id = QueryBuilder::table('tags')->insert(['text' => 'test_qb_delete']);

        // Delete
        $affected = QueryBuilder::table('tags')->where('id', $id)->delete();

        $this->assertEquals(1, $affected);

        // Verify deleted
        $row = QueryBuilder::table('tags')->where('id', $id)->first();
        $this->assertNull($row);
    }

    public function testDeleteMultipleRows(): void
    {

        // Use random suffix to avoid conflicts (text is varchar(20))
        $rand = substr(uniqid(), -6);
        $tag1 = "qb_{$rand}_1";
        $tag2 = "qb_{$rand}_2";
        $tag3 = "qb_{$rand}_3";

        // Clean up any potential leftovers
        try {
            QueryBuilder::table('tags')->where('text', 'LIKE', "qb_{$rand}%")->delete();
        } catch (\Exception $e) {
            // Ignore if nothing to delete
        }

        // Insert test data
        QueryBuilder::table('tags')->insertMany([
            ['text' => $tag1],
            ['text' => $tag2],
            ['text' => $tag3]
        ]);

        // Delete
        $affected = QueryBuilder::table('tags')->where('text', 'LIKE', "qb_{$rand}%")->delete();

        $this->assertEquals(3, $affected);
    }

    // ===== truncate() tests =====

    public function testTruncateEmptiesTable(): void
    {

        // Create a temporary test table
        $prefix = '';
        Connection::query(
            "CREATE TEMPORARY TABLE {$prefix}test_truncate " .
            "(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))"
        );

        // Insert data
        QueryBuilder::table('test_truncate')->insert(['name' => 'test1']);
        QueryBuilder::table('test_truncate')->insert(['name' => 'test2']);

        // Verify data exists
        $count = QueryBuilder::table('test_truncate')->count();
        $this->assertEquals(2, $count);

        // Truncate
        QueryBuilder::table('test_truncate')->truncate();

        // Verify empty
        $count = QueryBuilder::table('test_truncate')->count();
        $this->assertEquals(0, $count);
    }

    // ===== Complex query tests =====

    public function testComplexQueryWithMultipleClauses(): void
    {

        // Insert test data
        QueryBuilder::table('tags')->insertMany([
            ['text' => 'test_qb_complex_a'],
            ['text' => 'test_qb_complex_b'],
            ['text' => 'test_qb_complex_c'],
            ['text' => 'test_qb_other']
        ]);

        // Complex query
        $results = QueryBuilder::table('tags')
            ->select(['id', 'text'])
            ->where('text', 'LIKE', 'test_qb_complex%')
            ->orderBy('text')
            ->limit(2)
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals('test_qb_complex_a', $results[0]['text']);
        $this->assertEquals('test_qb_complex_b', $results[1]['text']);
    }

    public function testFluentInterfaceChaining(): void
    {

        // Insert test data
        QueryBuilder::table('tags')->insert(['text' => 'test_qb_fluent']);

        // Test fluent chaining
        $result = QueryBuilder::table('tags')
            ->select('text')
            ->where('text', 'test_qb_fluent')
            ->orderBy('id')
            ->limit(1)
            ->first();

        $this->assertIsArray($result);
        $this->assertEquals('test_qb_fluent', $result['text']);
    }
}
