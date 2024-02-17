# Indigo Storm Core
## Release 20.19

There's a lot new in this release, and a lot of breaking changes. Read these notes carefully before updating any
environment.

### What's New?
* Indigo Storm now uses a custom router instead of Slim, resulting in up to 46% faster response times.
* The codebase is now XX% smaller, making releases faster.
* A new CLI provides advanced tools for managing new or existing services and environments:
  * The `init` command allows you to configure a command-line shortcut, making development faster, as well as a
  development database server to avoid typing in credentials regularly.
  * Generating new local environments when you've defined a development database server automatically creates the
  database and provisions a user with the correct and limited access.
  * Single-letter commands are supported for faster development.
  * A new `--verbose` flag allows you to see more information about CLI activity, with standard mode emitting fewer
  lines while running common tasks.
  * The `--beta` release flag supports PHP 7.4 preloading, creating a faster end-user experience.
  * You can now add new tiers to existing environments.
  * You can now add new methods to existing routes (previously endpoints).
  * Route arguments can be defined using the CLI instead of having to edit the configuration manually.
* Database connections are closed wherever possible, including in the event of an exception to free up resources.
* Configuration now uses streamlined yaml files, with significantly more granular controls.
* A new lite mode allows Indigo Storm to run without a database for simple tasks.
* Error responses are now json-formatted, and include a unique reference to the associated RequestTree where this is 
possible. In dev mode, the file, line number, and trace are also included.
* Far less configuration is required, with the `service.config.php` file removed and the `global.yaml` file being
generated automatically for development work.
* Complex configuration calculation tasks are now offloaded from end-users and completed during the release, saving
time during requests.
* The new Task object allows you to create and run tasks independently of the infrastructure (non App-Engine 
deployments require a custom runner), as well as scheduling cron tasks in code.
* Asynchronous tasks are run with the same authority as the user that scheduled them and have their own RequestTrees,
 linked to the RequestTrees of the actions that created them, creating clearer audit trails.
* A number of issues that caused warnings to be logged have been resolved.

### Breaking Changes
* Support for PHP 5 has been dropped, with the minimum version now being 7.3.
  * The dropping of PHP 5 means PushTasks are no longer supported in App Engine deployments.
* The yaml PHP extension is required in all environments.
* Slim is no longer used as the route handler, so Slim-specific methods will no longer work on `$request` or
`$response`. Inspect the `Core\Routing\Request` and `Core\Routing\Response` classes for supported methods.
* `$Application` is no longer globally defined. Its content have either been deprecated or are available as part of
either `$indigoStorm` or `$response` as detailed below:
  * `db2`: direct access to Db2 has been deprecated. Use `SearchQuery` to find objects instead.
  * `tree`: Use `$request->getTree()` instead.
  * `key`: Use `$request->getKey()` instead.
  * `calledByInterface`: Use the `$indigoStorm->calledByInterface()` method instead.
  * `getMiddleware()` and `getServices()`: Access to services and middleware lists has been deprecated, no alternative
  is provided.
  * `getEnvironmentDetails()` and `getEnvironmentVariable()`: Use the `$indigoStorm->getConfig()` method instead.
* The `tools/developer` CLI has been deprecated and replaced with `./storm`. Commands have different default behaviour
and may require different options. Use `./storm help` for more information.
* Existing services and environment details require updating to use `.yaml` files instead of `.config.php` and/or
`.definition.php` files. Use `./storm upgrade service` or `./storm upgrade env` to do this automatically.
* Handling of Middleware has been changed:
  * Logic must now be defined inside a public `handleMiddleware($request, $response)` function.
  * Triggering the next step must now use `$response = $this->next($request, $response)`.
  * Continuity, CORS, Key, and Access Middleware are invoked automatically on all requests without needing to be
  included in any config files (they are suppressed when running in lite mode).
* Calls to interface-only routes running on this version of Indigo Storm (or newer) will fail if the call was made from
a service running an older version.
* _NOTE:_ Although `$args` continues to be defined and populated inside controller functions, it is deprecated and
`$request->getArgs()` should instead be used. It will be removed in a future release.
