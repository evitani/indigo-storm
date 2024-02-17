# Indigo Storm

Indigo Storm is a PHP framework that allows you to rapidly develop and deploy complex APIs in a microservice 
infrastructure. It includes the basic tools you need to manage and store objects, configure endpoints, and secure and
access across domains with API keys.

## Getting Started

These instructions will get you a copy of Indigo Storm Core installed and ready. Services can be added from repositories 
or developed directly.

### Prerequisites

Indigo Storm requires Composer to install. Visit https://getcomposer.org/doc/00-intro.md to get Composer if you do not
already have it installed.

Indigo Storm stores data in a MySQL database, and version 5.7 is recommended. Access must be possible from the server 
running Indigo Storm to the MySQL database by either IP or socket.

### Installing

Once cloned, install all dependencies:

```
composer install
```

Create the configuration directory and generate an environment using the dev tools:

```
tools/developer create-environment
```

If your environment is pointing to a new (empty) database, use the `-db` flag to enter connection details and 
automatically prepare the database.

If setting up a new Indigo Storm database separately, run the following SQL statement to allow Indigo Storm to manage 
the instance automatically:

```
CREATE TABLE `_Config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `objectName` varchar(64) NOT NULL DEFAULT '',
  `objectVersion` varchar(128) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectName` (`objectName`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
```

If you do not want to pass environment details into the framework using headers, add the details to `config/domains.php` 
and Indigo Storm will use this to correctly identify which environment and tier to use.

### Development

If you are developing, set `_DEVMODE_` to `true` in `config/service.config.php` and Indigo Storm will reference any 
interface classes in their original folder rather than expecting them to be abstracted from their services.

## Versioning

New versions of Indigo Storm are released every 14 days. Releases are identified by [year number].[release in year]. A 
pre-release of the next version is maintained on a separate branch which is merged with the release branch at launch.

For information on changes, see the [WHATSNEW.md](WHATSNEW.md) file, and check the same file in the pre-release branch 
for breaking changes before updating.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.
