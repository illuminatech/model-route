<?php

namespace Illuminatech\ModelRoute\Test;

use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Events\Dispatcher;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\CallableDispatcher;
use Illuminate\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;

/**
 * Base class for the test cases.
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Illuminate\Contracts\Container\Container test application instance.
     */
    protected $app;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->createApplication();

        $db = new Manager;

        $db->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        $db->bootEloquent();
        $db->setAsGlobal();

        Model::clearBootedModels();
        Model::setEventDispatcher($this->app->make('events'));

        $this->createSchema();

        Route::$validators = null;
    }

    /**
     * Get a database connection instance.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function getConnection()
    {
        return Model::getConnectionResolver()->connection();
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    protected function getSchemaBuilder()
    {
        return $this->getConnection()->getSchemaBuilder();
    }

    /**
     * Setup the database schema.
     *
     * @return void
     */
    protected function createSchema(): void
    {
        $this->getSchemaBuilder()->create('items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('slug');
        });
    }

    /**
     * @return Router test router instance.
     */
    protected function createRouter()
    {
        $router = new Router($this->app->make('events'), $this->app);

        $this->app->singleton(Registrar::class, function () use ($router) {
            return $router;
        });

        if (class_exists(CallableDispatcher::class)) {
            $this->app->singleton(CallableDispatcherContract::class, function ($app) {
                return new CallableDispatcher($app);
            });
        }

        return $router;
    }

    /**
     * Creates dummy application instance, ensuring facades functioning.
     */
    protected function createApplication()
    {
        $this->app = Container::getInstance();

        Facade::setFacadeApplication($this->app);

        $this->app->singleton('events', function ($app) {
            return new Dispatcher($app);
        });
    }
}
