<?php

namespace Illuminate\Foundation\Bootstrap {
    /** Las pruebas nunca leen .env ni reutilizan configuración compilada de la aplicación. */
    class LoadEnvironmentVariables
    {
        public function bootstrap($app): void
        {
            $app->afterBootstrapping(LoadConfiguration::class, function ($app): void {
                $sqlite = $app['config']->get('database.default') === 'sqlite'
                    && $app['config']->get('database.connections.sqlite.database') === ':memory:'
                    && ! $app['config']->get('database.connections.sqlite.url');
                $mysql = getenv('RAVEN_ISOLATED_MYSQL_TEST') === '1'
                    && $app['config']->get('database.default') === 'mysql'
                    && $app['config']->get('database.connections.mysql.host') === '127.0.0.1'
                    && (int) $app['config']->get('database.connections.mysql.port') === 13329
                    && $app['config']->get('database.connections.mysql.database') === 'raven_round1_isolated_test'
                    && ! $app['config']->get('database.connections.mysql.url');
                if (! $sqlite && ! $mysql) {
                    throw new \RuntimeException('Tests require explicitly isolated SQLite or MySQL.');
                }
            });
        }
    }
}

namespace {
    $testRoot = dirname(__DIR__);
    $mysql = getenv('RAVEN_ISOLATED_MYSQL_TEST') === '1';
    $testStorage = $testRoot.'/storage/framework/testing/'.($mysql ? 'mysql' : 'sqlite');
    $values = [
        'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'BOOST_ENABLED' => 'false',
        'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)), 'APP_URL' => 'http://localhost',
        'APP_BASE_PATH' => $testRoot, 'DB_URL' => '', 'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        'LOG_CHANNEL' => 'stderr', 'BCRYPT_ROUNDS' => '4',
        'APP_CONFIG_CACHE' => 'bootstrap/cache/test-absent-config.php',
        'APP_ROUTES_CACHE' => 'bootstrap/cache/test-absent-routes.php',
        'APP_PACKAGES_CACHE' => 'bootstrap/cache/test-packages.php',
        'APP_SERVICES_CACHE' => 'bootstrap/cache/test-services.php',
        'LARAVEL_STORAGE_PATH' => $testStorage, 'VIEW_COMPILED_PATH' => $testStorage.'/views',
    ];
    if ($mysql) {
        if (getenv('DB_HOST') !== '127.0.0.1' || getenv('DB_PORT') !== '13329'
            || getenv('DB_DATABASE') !== 'raven_round1_isolated_test' || getenv('DB_URL')) {
            throw new RuntimeException('Refusing an unverified MySQL connection.');
        }
        try {
            $probe = new PDO('mysql:host=127.0.0.1;port=13329;dbname=raven_round1_isolated_test;charset=utf8mb4', getenv('DB_USERNAME') ?: '', getenv('DB_PASSWORD') ?: '');
            $actualDirectory = strtolower(rtrim(str_replace('\\', '/', $probe->query('SELECT @@datadir')->fetchColumn()), '/'));
        } catch (Throwable) {
            throw new RuntimeException('The isolated MySQL identity could not be verified.');
        }
        $ci = getenv('GITHUB_ACTIONS') === 'true' && getenv('RAVEN_CI_MYSQL_TEST') === '1';
        $configuredDirectory = getenv('RAVEN_TEST_MYSQL_DATA_DIR');
        $expectedDirectory = $ci ? '/var/lib/mysql' : ($configuredDirectory ? realpath($configuredDirectory) : false);
        if (! $expectedDirectory || $actualDirectory !== strtolower(rtrim(str_replace('\\', '/', $expectedDirectory), '/'))) {
            throw new RuntimeException('The MySQL server is not the explicitly isolated test instance.');
        }
        unset($probe);
        $values['DB_CONNECTION'] = 'mysql';
    } else {
        $values += ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1', 'DB_USERNAME' => 'disabled-in-sqlite-tests', 'DB_PASSWORD' => ''];
    }
    foreach ($values as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
    if (! is_dir($values['VIEW_COMPILED_PATH'])) {
        mkdir($values['VIEW_COMPILED_PATH'], 0700, true);
    }
    require $testRoot.'/vendor/autoload.php';
}
