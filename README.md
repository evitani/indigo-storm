# Indigo Storm

Indigo Storm is a PHP framework that allows you to rapidly develop and deploy complex APIs in a microservice 
infrastructure. It includes the basic tools you need to manage and store objects, configure endpoints, and secure and
access across domains with API keys.

## Getting Started

These instructions will get you a copy of Indigo Storm installed and ready. Services can be added from repositories 
or developed directly.

### Prerequisites

Indigo Storm requires Composer to install. Visit https://getcomposer.org/doc/00-intro.md to get Composer if you do not
already have it installed.

Indigo Storm stores data in a MySQL database Access must be possible from the server running Indigo Storm to the MySQL 
database by either public or private IP (App Engine VPCs are supported), or socket. In a development environment, have
a MySQL server set up with a root user/password combination (you'll be asked for these later).

PHP 7+ is required, and 7.4 is recommended. Any development environments should have the pecl yaml extension installed
and available from the command line as well as the PHP server. All requests should be routed to `index.php`.

### Installing

Once cloned, install all dependencies:

```
composer install
```

Then run the command to initialise the development tools.

```
./storm init
```

The `init` command will allow you to add a CLI shortcut, as well as configuring your MySQL db server to automatically
provision new databases for development environments.

## Versioning

Releases are identified by [year number].[week in year / 2]. A pre-release of the next version is maintained on a 
separate branch which is merged with the release branch at launch.

*NOTE: Releases are currently sporadic, but naming will continue to follow the naming pattern expected.*

For information on changes, see the [WHATSNEW.md](WHATSNEW.md) file, and check the same file in the pre-release branch 
for breaking changes before updating.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.
