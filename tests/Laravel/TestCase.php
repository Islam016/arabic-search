<?php

declare(strict_types=1);

namespace IslamGaber\ArabicSearch\Tests\Laravel;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IslamGaber\ArabicSearch\Laravel\ArabicSearchServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ArabicSearchServiceProvider::class];
    }

    /**
     * SQLite in memory by default. CI also runs the suite on MySQL and PostgreSQL
     * by setting DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD.
     */
    protected function defineEnvironment($app): void
    {
        $driver = getenv('DB_CONNECTION') ?: 'sqlite';

        $app['config']->set('database.default', 'testing');

        $app['config']->set('database.connections.testing', match ($driver) {
            'mysql' => [
                'driver' => 'mysql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '3306',
                'database' => getenv('DB_DATABASE') ?: 'testing',
                'username' => getenv('DB_USERNAME') ?: 'root',
                'password' => getenv('DB_PASSWORD') ?: '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '5432',
                'database' => getenv('DB_DATABASE') ?: 'testing',
                'username' => getenv('DB_USERNAME') ?: 'postgres',
                'password' => getenv('DB_PASSWORD') ?: '',
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
            ],
            default => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('customers');

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->arabicSearchColumn('name');
            $table->string('city')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('customers');

        parent::tearDown();
    }
}
