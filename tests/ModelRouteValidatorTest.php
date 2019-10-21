<?php

namespace Illuminatech\ModelRoute\Test;

use Illuminate\Http\Request;
use Illuminatech\ModelRoute\Test\Support\Item;
use Illuminatech\ModelRoute\ModelRouteValidator;
use Illuminate\Routing\Middleware\SubstituteBindings;

class ModelRouteValidatorTest extends TestCase
{
    public function testMatch()
    {
        (new ModelRouteValidator())
            ->setBinders([
                'item' => Item::class.'@slug',
            ])
            ->register();

        $item = Item::query()->create([
            'name' => 'foo',
            'slug' => 'foo',
        ]);

        $router = $this->createRouter();

        $router->get('{item}', ['middleware' => SubstituteBindings::class, 'uses' => function (Item $item) {
            return $item->id;
        }]);

        $router->fallback(function () {
            return 'fallback';
        });

        $this->assertEquals($item->id, $router->dispatch(Request::create('foo', 'GET'))->getContent());
        $this->assertEquals('fallback', $router->dispatch(Request::create('no-item', 'GET'))->getContent());
    }

    /**
     * @depends testMatch
     */
    public function testFindParameterBinding()
    {
        $validator = (new ModelRouteValidator())
            ->register();

        $item = Item::query()->create([
            'name' => 'item-name',
            'slug' => 'item-slug',
        ]);

        $router = $this->createRouter();

        $router->get('{item}', function (Item $item) {
            return 'match';
        });
        $router->fallback(function () {
            return 'fallback';
        });

        $validator->setBinders([
            'item' => Item::class,
        ]);
        $this->assertEquals('match', $router->dispatch(Request::create($item->id, 'GET'))->getContent());
        $this->assertEquals('fallback', $router->dispatch(Request::create($item->id + 1, 'GET'))->getContent());

        $validator->setBinders([
            'item' => Item::class.'@slug',
        ]);
        $this->assertEquals('match', $router->dispatch(Request::create('item-slug', 'GET'))->getContent());
        $this->assertEquals('fallback', $router->dispatch(Request::create('item-name', 'GET'))->getContent());

        $validator->setBinders([
            'item' => function ($value) {
                return Item::query()->where('name', '=', $value)->first();
            },
        ]);
        $this->assertEquals('match', $router->dispatch(Request::create('item-name', 'GET'))->getContent());
        $this->assertEquals('fallback', $router->dispatch(Request::create('item-slug', 'GET'))->getContent());
    }

    /**
     * @depends testMatch
     */
    public function testSingleBindingQueryOnly()
    {
        $queryCount = 0;

        Item::getEventDispatcher()->listen('eloquent.retrieved: *', function($event) use (&$queryCount) {
            $queryCount++;
        });

        (new ModelRouteValidator())
            ->setBinders([
                'item' => Item::class.'@slug',
            ])
            ->register();

        $item = Item::query()->create([
            'name' => 'foo',
            'slug' => 'foo',
        ]);

        $router = $this->createRouter();

        $router->get('{item}', ['middleware' => SubstituteBindings::class, 'uses' => function (Item $item) {
            return $item->id;
        }]);

        $router->dispatch(Request::create($item->slug, 'GET'));

        $this->assertSame(1, $queryCount);
    }

    /**
     * @depends testMatch
     */
    public function testIgnoredUrlPaths()
    {
        $item = Item::query()->create([
            'name' => 'item-name',
            'slug' => 'item-slug',
        ]);

        (new ModelRouteValidator())
            ->setBinders([
                'item' => Item::class.'@slug',
            ])
            ->setIgnoredUrlPaths([
                $item->slug,
            ])
            ->register();

        $router = $this->createRouter();

        $router->get('{item}', function (Item $item) {
            return 'match';
        });
        $router->fallback(function () {
            return 'fallback';
        });

        $this->assertEquals('fallback', $router->dispatch(Request::create($item->slug, 'GET'))->getContent());
    }
}
