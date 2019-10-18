<?php
/**
 * @link https://github.com/illuminatech
 * @copyright Copyright (c) 2019 Illuminatech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace Illuminatech\ModelRoute;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Matching\ValidatorInterface;

/**
 * ModelRouteValidator
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
 *             ]);
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
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class ModelRouteValidator implements ValidatorInterface
{
    /**
     * @var array
     */
    private $binders = [];

    /**
     * @return array
     */
    public function getBinders(): array
    {
        return $this->binders;
    }

    /**
     * @param  array  $binders
     * @return $this self reference.
     */
    public function setBinders(array $binders): self
    {
        $this->binders = $binders;

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
                $route->bind($request);

                $model = $this->findParameterBinding($route->parameter($parameterName), $binder);

                if ($model === null) {
                    return false;
                }

                $route->setParameter($parameterName, $model);

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
            /* @var $model \Illuminate\Database\Eloquent\Model */
            if (strpos($binder, '@') == false) {
                $model = $binder;

                return $model::query()->whereKey($value)->first();
            }

            [$model, $attribute] = explode('@', $binder);

            return $model::query()->where($attribute, '=', $value)->first();
        }

        return call_user_func($binder, $value);
    }
}
