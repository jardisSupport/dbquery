<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command;

use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\DbQuery\DbDelete;
use JardisSupport\DbQuery\DbUpdate;
use PHPUnit\Framework\TestCase;

/**
 * JOIN ON condition from column pairs in UPDATE and DELETE (MySQL: the only dialect with JOIN there).
 *
 * Invariant: both sides of every pair are dialect-quoted, raw text stays byte-identical.
 */
class JoinOnPairsUpdateDeleteTest extends TestCase
{
    public function testUpdateJoinQuotesPairs(): void
    {
        $sql = (new DbUpdate())
            ->table('groups', 'group')
            ->set('total', 0)
            ->innerJoin(
                'users',
                Expression::joinOn([['group.tenantId', 'user.tenantId'], ['group.id', 'user.groupId']]),
                'user'
            )
            ->where('user.status')->equals('gone')
            ->sql('mysql', false);

        $this->assertSame(
            "UPDATE `groups` `group` INNER JOIN `users` `user` ON "
            . "`group`.`tenantId` = `user`.`tenantId` AND `group`.`id` = `user`.`groupId` "
            . "SET `total` = 0 WHERE `user`.`status` = 'gone'",
            $sql
        );
    }

    public function testUpdateJoinQuotesPairsInPreparedMode(): void
    {
        $prepared = (new DbUpdate())
            ->table('groups', 'group')
            ->set('total', 0)
            ->leftJoin('users', Expression::joinOn([['group.id', 'user.groupId']]), 'user')
            ->where('user.id')->isNull()
            ->sql('mysql', true);

        $this->assertSame(
            'UPDATE `groups` `group` LEFT JOIN `users` `user` ON `group`.`id` = `user`.`groupId` '
            . 'SET `total` = ? WHERE `user`.`id` IS NULL',
            $prepared->sql()
        );
        $this->assertSame([0], $prepared->bindings());
    }

    public function testUpdateJoinRawTextStaysByteIdentical(): void
    {
        $sql = (new DbUpdate())
            ->table('users', 'u')
            ->set('total', 0)
            ->innerJoin('orders', 'orders.user_id = u.id', 'o')
            ->sql('mysql', false);

        $this->assertSame(
            'UPDATE `users` `u` INNER JOIN `orders` `o` ON orders.user_id = u.id SET `total` = 0',
            $sql
        );
    }

    public function testDeleteJoinQuotesPairs(): void
    {
        $sql = (new DbDelete())
            ->from('groups', 'group')
            ->leftJoin(
                'users',
                Expression::joinOn([['group.tenantId', 'user.tenantId'], ['group.id', 'user.groupId']]),
                'user'
            )
            ->where('user.id')->isNull()
            ->sql('mysql', false);

        $this->assertSame(
            'DELETE `group` FROM `groups` `group` LEFT JOIN `users` `user` ON '
            . '`group`.`tenantId` = `user`.`tenantId` AND `group`.`id` = `user`.`groupId` '
            . 'WHERE `user`.`id` IS NULL',
            $sql
        );
    }

    public function testDeleteJoinRawTextStaysByteIdentical(): void
    {
        $sql = (new DbDelete())
            ->from('user_sessions')
            ->innerJoin('users', 'user_sessions.user_id = users.id')
            ->sql('mysql', false);

        $this->assertSame(
            'DELETE FROM `user_sessions` INNER JOIN `users` ON user_sessions.user_id = users.id',
            $sql
        );
    }
}
