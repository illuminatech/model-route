<p align="center">
    <a href="https://github.com/illuminatech" target="_blank">
        <img src="https://avatars1.githubusercontent.com/u/47185924" height="100px">
    </a>
    <h1 align="center">Laravel Model Route</h1>
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

Most likely in your project users and organizations will be stored in different database tables, so the Laravel routes
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

However this does not works correctly. The problem is the second route ("organizations.show") will never be matched as
any organization name will be compared against first route ("users.show") only triggering 404 error on attempt to
access organization's page.

This extension solves this problem via extra URL matching validator - `\Illuminatech\ModelRoute\ModelRouteValidator`.
