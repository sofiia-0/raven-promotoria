<?php

namespace Tests\Concerns;

trait RequiresIsolatedPromoterDatabase
{
    public function beforeRefreshingDatabase(): void
    {
        $sqlite = config('database.default') === 'sqlite' && config('database.connections.sqlite.database') === ':memory:';
        $mysql = getenv('RAVEN_ISOLATED_MYSQL_TEST') === '1' && config('database.default') === 'mysql'
            && config('database.connections.mysql.host') === '127.0.0.1'
            && (int) config('database.connections.mysql.port') === 13329
            && config('database.connections.mysql.database') === 'raven_round1_isolated_test';
        if (! $sqlite && ! $mysql) {
            throw new \RuntimeException('These tests require an explicitly isolated database.');
        }
    }
}
