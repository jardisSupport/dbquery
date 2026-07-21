<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Builder\Condition;

use InvalidArgumentException;
use JardisSupport\DbQuery\Query\builder\Condition\ValueValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for ValueValidator
 *
 * Tests SQL injection prevention for raw SQL values while allowing legitimate SQL constructs.
 */
class ValueValidatorTest extends TestCase
{
    private ValueValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ValueValidator();
    }

    // ==================== SQL Comment Detection Tests ====================

    public function testBlocksSqlLineCommentWithSpace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL line comment (--) not allowed');

        ($this->validator)('name-- AND 1=1');
    }

    public function testBlocksSqlLineCommentWithMultipleSpaces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL line comment (--) not allowed');

        ($this->validator)('field--    DROP TABLE users');
    }

    public function testBlocksSqlBlockComment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL block comment (/* */) not allowed');

        ($this->validator)('name /* malicious */ = 1');
    }

    public function testBlocksSqlBlockCommentMultiline(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL block comment (/* */) not allowed');

        ($this->validator)("name /* malicious\ncode */ = 1");
    }

    public function testBlocksMySqlHashComment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MySQL hash comment (#) not allowed');

        ($this->validator)('name# DROP TABLE users');
    }

    public function testBlocksMySqlHashCommentWithNewline(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MySQL hash comment (#) not allowed');

        ($this->validator)("field#comment\nAND 1=1");
    }

    public function testAllowsDoubleHyphenWithoutSpace(): void
    {
        // No space after -- means it's not a comment
        ($this->validator)('field--value');
        $this->addToAssertionCount(1);
    }

    // ==================== File Operations Tests ====================

    public function testBlocksLoadFileFunction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File operations not allowed');

        ($this->validator)('LOAD_FILE("/etc/passwd")');
    }

    public function testBlocksLoadFileLowercase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File operations not allowed');

        ($this->validator)('load_file("/etc/passwd")');
    }

    public function testBlocksIntoOutfile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File operations not allowed');

        ($this->validator)('SELECT * INTO OUTFILE "/tmp/data.txt"');
    }

    public function testBlocksIntoDumpfile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File operations not allowed');

        ($this->validator)('SELECT * INTO DUMPFILE "/tmp/shell.php"');
    }

    // ==================== Multiple Statement Tests ====================

    public function testBlocksMultipleStatementsWithSelect(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple SQL statements not allowed');

        ($this->validator)('field; SELECT * FROM users');
    }

    public function testBlocksMultipleStatementsWithInsert(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple SQL statements not allowed');

        ($this->validator)('field; INSERT INTO logs VALUES (1)');
    }

    public function testBlocksMultipleStatementsWithUpdate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple SQL statements not allowed');

        ($this->validator)('field; UPDATE users SET active=0');
    }

    public function testBlocksMultipleStatementsWithDelete(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple SQL statements not allowed');

        ($this->validator)('field; DELETE FROM users');
    }

    public function testBlocksMultipleStatementsWithDrop(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple SQL statements not allowed');

        ($this->validator)('field; DROP TABLE users');
    }

    public function testBlocksMultipleStatementsWithCreate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple SQL statements not allowed');

        ($this->validator)('field; CREATE TABLE test (id INT)');
    }

    public function testBlocksMultipleStatementsWithAlter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple SQL statements not allowed');

        ($this->validator)('field; ALTER TABLE users ADD COLUMN test INT');
    }

    public function testBlocksMultipleStatementsWithGrant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple SQL statements not allowed');

        ($this->validator)('field; GRANT ALL ON *.* TO user');
    }

    public function testBlocksMultipleStatementsWithRevoke(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple SQL statements not allowed');

        ($this->validator)('field; REVOKE ALL ON *.* FROM user');
    }

    public function testBlocksMultipleStatementsWithWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple SQL statements not allowed');

        ($this->validator)('field;   SELECT * FROM users');
    }

    public function testBlocksMultipleStatementsWithNewline(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple SQL statements not allowed');

        ($this->validator)("field;\nSELECT * FROM users");
    }

    // ==================== Legitimate SQL Tests ====================

    public function testAllowsSimpleColumnName(): void
    {
        ($this->validator)('users.name');
        $this->addToAssertionCount(1);
    }

    public function testAllowsSchemaQualifiedName(): void
    {
        ($this->validator)('public.users.name');
        $this->addToAssertionCount(1);
    }

    public function testAllowsSqlFunctionNow(): void
    {
        ($this->validator)('NOW()');
        $this->addToAssertionCount(1);
    }

    public function testAllowsSqlFunctionCount(): void
    {
        ($this->validator)('COUNT(*)');
        $this->addToAssertionCount(1);
    }

    public function testAllowsSqlFunctionSum(): void
    {
        ($this->validator)('SUM(price)');
        $this->addToAssertionCount(1);
    }

    public function testAllowsCaseExpression(): void
    {
        ($this->validator)('CASE WHEN age > 18 THEN "adult" ELSE "minor" END');
        $this->addToAssertionCount(1);
    }

    public function testAllowsArithmeticOperations(): void
    {
        ($this->validator)('price * 1.19');
        $this->addToAssertionCount(1);
    }

    public function testAllowsSubquery(): void
    {
        ($this->validator)('(SELECT MAX(id) FROM users)');
        $this->addToAssertionCount(1);
    }

    public function testAllowsStringLiteralInFunction(): void
    {
        ($this->validator)('CONCAT(first_name, " ", last_name)');
        $this->addToAssertionCount(1);
    }

    public function testAllowsComplexExpression(): void
    {
        ($this->validator)('COALESCE(discount_price, regular_price * 0.9)');
        $this->addToAssertionCount(1);
    }

    public function testAllowsCastExpression(): void
    {
        ($this->validator)('CAST(created_at AS DATE)');
        $this->addToAssertionCount(1);
    }

    public function testAllowsExtractExpression(): void
    {
        ($this->validator)('EXTRACT(YEAR FROM created_at)');
        $this->addToAssertionCount(1);
    }

    public function testAllowsInOperator(): void
    {
        ($this->validator)('status IN ("active", "pending")');
        $this->addToAssertionCount(1);
    }

    public function testAllowsBetweenOperator(): void
    {
        ($this->validator)('age BETWEEN 18 AND 65');
        $this->addToAssertionCount(1);
    }

    public function testAllowsLikeOperator(): void
    {
        ($this->validator)('name LIKE "%test%"');
        $this->addToAssertionCount(1);
    }

    public function testAllowsIsNullCheck(): void
    {
        ($this->validator)('deleted_at IS NULL');
        $this->addToAssertionCount(1);
    }

    public function testAllowsComplexCaseWithMultipleConditions(): void
    {
        ($this->validator)(
            'CASE ' .
            'WHEN status = "active" THEN 1 ' .
            'WHEN status = "pending" THEN 2 ' .
            'ELSE 0 END'
        );
        $this->addToAssertionCount(1);
    }

    public function testAllowsDateFunctions(): void
    {
        ($this->validator)('DATE_FORMAT(created_at, "%Y-%m-%d")');
        $this->addToAssertionCount(1);
    }

    public function testAllowsStringFunctions(): void
    {
        ($this->validator)('UPPER(TRIM(name))');
        $this->addToAssertionCount(1);
    }

    // ==================== Edge Cases ====================

    public function testAllowsSemicolonInStringLiteral(): void
    {
        // Semicolon inside string literal should be allowed
        ($this->validator)('CONCAT(name, "; ")');
        $this->addToAssertionCount(1);
    }

    public function testAllowsHashInStringLiteral(): void
    {
        // Hash inside quotes is OK
        ($this->validator)('COLOR = "#FF0000"');
        $this->addToAssertionCount(1);
    }

    public function testAllowsEmptyValue(): void
    {
        ($this->validator)('');
        $this->addToAssertionCount(1);
    }

    public function testAllowsNumericValue(): void
    {
        ($this->validator)('42');
        $this->addToAssertionCount(1);
    }

    public function testAllowsNegativeNumber(): void
    {
        ($this->validator)('-42.5');
        $this->addToAssertionCount(1);
    }

    public function testAllowsBooleanKeywords(): void
    {
        ($this->validator)('TRUE');
        ($this->validator)('FALSE');
        ($this->validator)('NULL');
        $this->addToAssertionCount(1);
    }

    // ==================== Real World Examples ====================

    public function testAllowsComplexWindowFunction(): void
    {
        ($this->validator)(
            'ROW_NUMBER() OVER (PARTITION BY category_id ORDER BY created_at DESC)'
        );
        $this->addToAssertionCount(1);
    }

    public function testAllowsJsonExtraction(): void
    {
        ($this->validator)('JSON_EXTRACT(data, "$.user.name")');
        $this->addToAssertionCount(1);
    }

    public function testAllowsRegexpOperator(): void
    {
        ($this->validator)('email REGEXP "^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Z|a-z]{2,}$"');
        $this->addToAssertionCount(1);
    }

    public function testAllowsCoalesceWithMultipleFields(): void
    {
        ($this->validator)('COALESCE(mobile_phone, home_phone, work_phone, "N/A")');
        $this->addToAssertionCount(1);
    }
}
