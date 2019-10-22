<p align="center">
    <a href="https://github.com/illuminatech" target="_blank">
        <img src="https://avatars1.githubusercontent.com/u/47185924" height="100px">
    </a>
    <h1 align="center">Real Laravel Model Route Matching</h1>
    <br>
</p>

This extension allows continuing route matching in case bound model does not exist.

For license information check the [LICENSE](LICENSE.md)-file.

[![Latest Stable Version](https://img.shields.io/packagist/v/illuminatech/model-route.svg)](https://packagist.org/packages/illuminatech/model-route)
[![Total Downloads](https://img.shields.io/packagist/dt/illuminatech/model-route.svg)](https://packagist.org/packages/illuminatech/model-route)
[![Build Status](https://travis-ci.org/illuminatech/model-route.svg?branch=master)](https://travis-ci.org/illuminatech/model-route)


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist illuminatech/model-route
```

or add

```json
"illuminatech/model-route": "*"
```

to the require section of your composer.json.


Usage
-----

This extension allows continuing route matching in case bound model does not exist.

Imagine we need to create URL structure like the one GitHub has. There are individual users and organizations, each of which
has its own page responding the URL starting from their name:

- [https://github.com/klimov-paul](https://github.com/klimov-paul) - user's page, where "klimov-paul" - name of the user.
- [https://github.com/illuminatech](https://github.com/illuminatech) - organization's page, where "illuminatech" - name of the organization. 

Most likely, in your project users and organizations will be stored in different database tables, so the Laravel routes
configuration for this case will look like following:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;

Route::get('{user}', UserController::class.'@show')->name('users.show');
Route::get('{organization}', OrganizationController::class.'@show')->name('organizations.show');
```

And the controllers code will look like following:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserController extends Controller
{
    public function show(User $user)
    {
        // ...
    }
}

use App\Models\Organization;

class OrganizationController extends Controller
{
    public function show(Organization $organization)
    {
        // ...
    }
}
```

However, this does not work correctly. The problem is the second route ("organizations.show") will never be matched as
any organization name will be compared against first route ("users.show") only, triggering 404 error on attempt to
access organization's page.

This extension solves this problem via extra URL matching validator - `\Illuminatech\ModelRoute\ModelRouteValidator`.
Being registered it adds particular model existence as a matching condition for the routes. This allows to pass matching
to the next route, in case model, bound to the current one, does not exist. The best place to register this validator will
be your route service provider. For example:

```php
<?php

namespace App\Providers;

use Illuminatech\ModelRoute\ModelRouteValidator;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot()
    {
        (new ModelRouteValidator)
            ->setBinders([
                'user' => \App\Models\User::class.'@username',
                'organization' => \App\Models\Organization::class.'@name',
            ])
            ->register();

        parent::boot();
    }

    // ...
}
```

Once it is set, the routes specified above will be parsed correctly. In case there is no User record matching the requested URL
route 'users.show' will be considered as 'not matched' and routing will continue to 'organizations.show'.  

`\Illuminatech\ModelRoute\ModelRouteValidator` allows setup of the route parameter binding in the similar way to the [standard explicit binding](https://laravel.com/docs/6.x/routing#explicit-binding).
Binders are set via `\Illuminatech\ModelRoute\ModelRouteValidator::setBinders()` as an array, which key is the route parameter name
and value is a binder specification. Each binder can be specified as:

- string, Eloquent model class name, for example: 'App\Models\User'; in this case parameter binding will be searched in this
  class using a its route key field.
  
- string, pair of Eloquent model class name and search field separated by `@` symbol, for example: 'App\Models\Item@slug';
  in this case parameter binding will be searched in the specified model using specified field.

- callable, a PHP callback, which should accept parameter raw value and return binding for it; in case no binding is found -
  `null` should be returned.
  
For example:

```php
<?php

use Illuminatech\ModelRoute\ModelRouteValidator;

(new ModelRouteValidator)
    ->setBinders([
        'blog' => \App\Models\BlogPost::class, // search using `\App\Models\BlogPost::getRouteKeyName()`
        'item' => \App\Models\Item::class.'@slug', // search using `\App\Models\Item::$slug`
        'project' => function ($value) {
            return \App\Models\Project::query()->where('name', $value)->first(); // if not found - `null` will be returned
        },
    ])
    ->register();
```

> Note: do not specify standard explicit route parameter binding for the parameter covered by `\Illuminatech\ModelRoute\ModelRouteValidator::setBinders()`,
  as it will cause extra redundant database query. Parameter binding will be setup by `\Illuminatech\ModelRoute\ModelRouteValidator` automatically.


### Performance Tuning <span id="performance-Tuning"></span>

Remember that you should specify routes for any static pages **before** you write the route with model binding. While this
extension allows routes matching to continue, if binding does not exist, matching check comes with the cost of a database query.
Thus in our 'GitHub' example routes to any predefined site sections like static pages, contact page or blog, should be
described beforehand:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;

// predefined site sections should be described beforehand:
Route::view('about', 'pages/about')->name('about');
Route::view('privacy-policy', 'pages/privacy-policy')->name('privacy-policy');

Route::get('blog', BlogController::class.'@index')->name('blog.index');
Route::get('blog/{blogArticle}', BlogController::class.'@show')->name('blog.show');

// only once all other routes are defined, we can use dynamic binding:
Route::get('{user}', UserController::class.'@show')->name('users.show'); // matching check will cause a DB query against model `App\Models\User`
Route::get('{organization}', OrganizationController::class.'@show')->name('organizations.show'); // matching check will cause a DB query against model `App\Models\Organization`
```

Unfortunally, you can not always control the order of all your routes definition. Some packages like [Telescope](https://laravel.com/docs/6.x/telescope),
[Horizon](https://laravel.com/docs/6.x/horizon) and [Nova](https://nova.laravel.com) register their own routes via separated service provider.
Those routes may appear to be registered after our "users.show" and "organizations.show" ones.
You may manually exclude particular URL paths from the matching using `\Illuminatech\ModelRoute\ModelRouteValidator::setIgnoredUrlPaths()`.
For example:

```php
<?php

use Illuminatech\ModelRoute\ModelRouteValidator;

(new ModelRouteValidator)
    ->setBinders([
        'user' => \App\Models\User::class.'@username',
        'organization' => \App\Models\Organization::class.'@name',
    ])
    ->setIgnoredUrlPaths([
        config('telescope.path'), // exclude Telescope URLs
        config('horizon.path'), // exclude Horizon URLs
        config('nova.path'), // exclude Nova URLs
        'nova-api', // exclude Nova API URLs
    ])
    ->register();
```

With such configuration parsing of the URLs starting from '/telescope', '/horizon' or '/nova' will never trigger a database
query around "users.show" and "organizations.show" routes.
