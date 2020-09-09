<?php
/**
 * @link https://github.com/illuminatech
 * @copyright Copyright (c) 2019 Illuminatech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace Illuminatech\ModelRoute;

use Illuminate\Http\Request;
use Illuminate\Routing\Matching\ValidatorInterface;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * ModelRouteValidator allows check for the particular model binding existence, while matching routing.
 *
 * Being registered this validator allows to pass matching to the next route in case model bound to the current one does not exist.
 *
 * ```php
 * namespace App\Providers;
 *
 * use Illuminatech\ModelRoute\ModelRouteValidator;
 * use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
 *
 * class RouteServiceProvider extends ServiceProvider
 * {
 *     public function boot()
 *     {
 *         (new ModelRouteValidator)
 *             ->setBinders([
 *                 'blog' => \App\Models\BlogPost::class,
 *                 'item' => \App\Models\Item::class.'@slug',
 *                 'project' => function ($value) {
 *                      return \App\Models\Project::query()->where('name', $value)->first();
 *                  },
 *             ])
 *             ->register();
 *
 *         parent::boot();
 *     }
 *
 *     // ...
 * }
 * ```
 *
 * @see \Illuminate\Routing\Route::$validators
 * @see \Illuminate\Routing\Middleware\SubstituteBindings
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class ModelRouteValidator implements ValidatorInterface
{
    /**
     * @var array route parameter binders in format: `[parameterName => binder]`.
     * @see setBinders()
     */
    private $binders = [];

    /**
     * @var array list of URL paths, which should be skipped from matching.
     */
    private $ignoredUrlPaths = [];

    /**
     * @return array route parameter binders in format: `[parameterName => binder]`.
     */
    public function getBinders(): array
    {
        return $this->binders;
    }

    /**
     * Sets up route parameter binders to be used while route matching.
     * Each binder can be specified as:
     *
     * - string, Eloquent model class name, for example: 'App\Models\User'; in this case parameter binding will be searched in this
     *   class using a its route key field.
     *
     * - string, pair of Eloquent model class name and search field separated by `@` symbol, for example: 'App\Models\Item@slug';
     *   in this case parameter binding will be searched in the specified model using specified field.
     *
     * - callable, a PHP callback, which should accept parameter raw value and return binding for it; in case no binding is found -
     *   `null` should be returned.
     *
     * For example:
     *
     * ```php
     * [
     *     'blog' => \App\Models\BlogPost::class,
     *     'item' => \App\Models\Item::class.'@slug',
     *     'project' => function ($value) {
     *         return \App\Models\Project::query()->where('name', $value)->first();
     *     },
     * ]
     * ```
     *
     * @param  array  $binders route parameter binders in format: `[parameterName => binder]`.
     * @return $this self reference.
     */
    public function setBinders(array $binders): self
    {
        $this->binders = $binders;

        return $this;
    }

    /**
     * @return array list of URL paths, which should be skipped from matching.
     */
    public function getIgnoredUrlPaths(): array
    {
        return $this->ignoredUrlPaths;
    }

    /**
     * Sets up URL path, for which parameter binding should not be performed.
     * For example:
     * ```php
     * [
     *     config('telescope.path'),
     *     config('horizon.path'),
     *     config('nova.path'),
     *     'nova-api',
     * ]
     * ```
     *
     * @param  array  $ignoredUrlPaths list of URL paths, which should be skipped from matching.
     * @return $this self reference.
     */
    public function setIgnoredUrlPaths(array $ignoredUrlPaths): self
    {
        $this->ignoredUrlPaths = $ignoredUrlPaths;

        return $this;
    }

    /**
     * Appends this instance to the route validators.
     *
     * @return $this self reference.
     */
    public function register(): self
    {
        $validators = Route::getValidators();
        $validators[] = $this;

        Route::$validators = $validators;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(Route $route, Request $request): bool
    {
        $routeVariables = $route->getCompiled()->getVariables();

        foreach ($this->getBinders() as $parameterName => $binder) {
            if (in_array($parameterName, $routeVariables)) {
                foreach ($this->getIgnoredUrlPaths() as $ignoredUrlPath) {
                    if (Str::startsWith(trim($request->path(), '/').'/', trim($ignoredUrlPath, '/').'/')) {
                        return false;
                    }
                }

                $route = clone $route;
                $route->bind($request);

                $model = $this->findParameterBinding($route->parameter($parameterName), $binder);

                if ($model === null) {
                    return false;
                }

                $router = $this->getRouter($route);

                $router->bind($parameterName, function() use ($model) {
                    return $model;
                });

                return true;
            }
        }

        return true;
    }

    /**
     * Finds the actual value for route parameter binding.
     *
     * @param  mixed  $value route parameter value.
     * @param  callable|string  $binder parameter binding resolver.
     * @return mixed|null bound parameter value, `null` - if no binding found.
     */
    protected function findParameterBinding($value, $binder)
    {
        if (is_string($binder)) {
            /** @var $model \Illuminate\Database\Eloquent\Model */
            if (strpos($binder, '@') === false) {

                $model = $binder;

                return $model::query()->newModelInstance()->resolveRouteBinding($value);
            }

            [$model, $attribute] = explode('@', $binder);

            return $model::query()->where($attribute, '=', $value)->first();
        }

        return call_user_func($binder, $value);
    }

    /**
     * Extracts router instance for the specified route.
     *
     * @param  Route  $route route instance.
     * @return \Illuminate\Routing\Router router related to the given route.
     */
    protected function getRouter(Route $route)
    {
        try {
            $reflection = new \ReflectionObject($route);
            $property = $reflection->getProperty('router');
            $property->setAccessible(true);

            return $property->getValue($route);
        } catch (\Throwable $e) {
            return \Illuminate\Support\Facades\Route::getFacadeRoot();
        }
    }
}
