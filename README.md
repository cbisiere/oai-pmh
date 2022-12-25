
# oai-pmh

A simple OAI-PMH 2.0 server written in PHP. 

Highlights:

* [OAI v2](http://www.openarchives.org/OAI/openarchivesprotocol.html) compliant
* Multi-repositories
* Per-repository deleted record policy, harvesting granularity, incomplete list size, and resumption token duration
* Provides a set of classes to help setup the metadata updating process
* Access and update logging

TODO:

- [ ] Clean-up and import the update part of the project
- [ ] Fix `oai2.xsl` (line 530): should display "No more results" when `oai:resumptionToken` is present but empty
- [ ] Support (per-repository) log levels: `access`, `error`, `no`

## Prerequisites

* MySQL 5.7+
* http server w/ PHP 5.4+

## Installation

### Create the MySQL database

First, create the MySQL database `oai_repo` and setup a small demo repository. This repository is the one that illustrates the [OAI-PMH Version 2 specification document](https://www.openarchives.org/OAI/openarchivesprotocol.html) itself.

```
$ cd install
$ mysql -u root -p
...
mysql> source setup_database.sql
...
mysql> source setup_demo_repo.sql
mysql> exit

```

If the database `oai_repo` already exists, the SQL scripts `setup_database.sql` and `setup_demo_repo.sql` will do nothing. Thus, if you want to completely reinstall the database (wiping out all the repositories you might have defined), you must first delete it. See below. To only factory reset the demo repository, you must delete it first. See also below.

### Configure your HTTP server

Add a new site with `public` as document root folder.

Assuming the base URL of the site is `http://localhost/oai-pmh`, point your browser to `http://localhost/oai-pmh/oai2.php?verb=Identify`. 

You should get:

![Screenshot of the demo OAI repository](install.png)

Of course, the base URL and request URL are wrong, and as such the demo repository is not fully OAI compliant.


## Uninstallation

The following SQL statements wipes out the entire database and delete the associated user: 

```sql
DROP DATABASE IF EXISTS `oai_repo`;
DROP USER IF EXISTS 'oai_user'@'localhost';
```

Be careful, since all the repositories that you might have set up will be lost.

Then, reconfigure your http server to remove the associated web site. 

## Usage
### Adding a new repository

There is no dedicated GUI to manage the repositories. To do so you have to use SQL directly, or through a GUI like, e.g., phpmyadmin. The database schema is simple, and quite self-explanatory provided you have a basic knowledge of the OAI-PMH protocol.

Since OAI 2.0 compliant repositories must disseminate, at least, Dublin Core, a minimal repository could be created with something like:

```sql
INSERT INTO `oai_repo` (`id`, `repositoryName`, `baseURL`, `protocolVersion`, `adminEmails`, `earliestDatestamp`, `deletedRecord`, `granularity`) 
VALUES ('myrepo', 'My Repo', 'http://example.com/oai2.php', '2.0', 'me@example.com', '2019-01-01', 'no', 'YYYY-MM-DD');

INSERT IGNORE INTO `oai_meta` (`repo`, `metadataPrefix`, `schema`, `metadataNamespace`) 
VALUES ('myrepo', 'oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
```

The identifier of this repository in the database is `myrepo`. The repository does not support deleted records or incomplete lists, has day granularity, no repository description, no sets, and is empty.

To be able to browse this repository using a web browser, you need a custom PHP script. 

The script `public/oai2.php` is customised for the `demo` repository. If you want to reuse it for your new repository, edit it and and change the line:

```php
define('REPO_ID', 'demo');
```

to

```php
define('REPO_ID', 'myrepo');
```

Each repository must have its own base URL. Therefore, if you want to keep the existing repositories instead, make a copy of `public/oai2.php` under a new name before updating `REPO_ID`. Update the repository definition in the database (table `oai_repo`) to reflect the new base URL for this repository.  

You may also want to tweak your http server to get a nice base URL, without the `.php` part.


### Deleting a repository

To delete a repository, just delete the corresponding record in the table `oai_repo`. Foreign key constraints ensure that all data associated with this repository will be properly deleted, including log data.

For instance, to delete the repository `demo`, execute the following SQL statement:

```sql
DELETE FROM oai_repo.oai_repo WHERE id='demo';
```

If you want to keep log data, you should take the repository offline instead, by modifying its PHP script. You may also want to save storage space by emptying the repository, that is, deleting all associated metadata and set membership data: 

```sql
DELETE FROM oai_repo.oai_item_meta WHERE repo='demo';
DELETE FROM oai_repo.oai_item_set WHERE repo='demo';
```
### Adding metadata to a repository


## Contributing

Before submitting a pull request, please check your code with:

```sh
php-cs-fixer fix . --dry-run --diff --rules=@Symfony
``` 

You might also want to test that your changes do not break OAI compliance, using a validator, e.g.:

* [http://validator.oaipmh.com](http://validator.oaipmh.com)
* [OAI Repository Explorer](http://www.purl.org/NET/oai_explorer)


## Author

* **Christophe Bisière** 

## License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE.md](LICENSE.md) file for details




