# OpenPaaS ESN Frontend for SabreDAV

Welcome to the OpenPaaS frontend for [SabreDAV](http://sabre.io/). This frontend adds calendaring and address book capabilities to your OpenPaaS instance and allows you to access them via standard CalDAV and CardDAV clients like Lightning.

## Setting up the Environment

Those are the steps needed on an [Ubuntu](http://ubuntu.com/) distribution, but the equivalent can be found for any Linux flavor.

### Install esn-sabre

```bash
cd /var/www/html
git clone https://ci.open-paas.org/stash/scm/or/esn-sabre.git
```

The OpenPaaS frontend is managed through [composer](https://getcomposer.org/), all requirements can easily be set up using:

```bash
cd esn-sabre
composer update
```

This command can be repeated to update package versions.

### create the configuration file

The configuration file can be created from the example file.

```bash
cp config.json.default config.json
```

or by running the generation script:

```bash
sh ./scripts/generate_config.sh > config.json
```

You then have to modify the configuration to match your setup.

-	**webserver.baseUri**

The local part of the url that bring the esn.php file. For example, if you reach esn.php through http://some.example.com/esn-sabre/esn.php then your baseUri is **/esn-sabre/esn.php**.

-	**webserver.allowOrigin**

This setting is used to configure the headers returned by the CalDAV server. It's usefull to configure CORS. Either set the hostname of your ESN server, or leave "*".

-	**database.esn**

This is the configuration to access the ESN datastore

-	**database.sabre**

This is the configuration where the CalDAV server will store its data.

-	**esn.apiRoot**

This is the URL the Caldav server will use to access the OpenPaaS ESN API.

-	**mail**

This is the configuration the Caldav server will use to send emails.

### System & Apache environment

esn-sabre requires an Apache server to work.

-	Install PHP5 support into Apache

```bash
apt-get install libapache2-mod-php5
```

-	Install mongodb & curl support in PHP

```bash
apt-get install php5-mongo php5-curl
```

If **composer update** throws an error, you might want to use PECL

```bash
apt-get install php5-dev php-pear && pecl install mongo
```

-	Restart Apache

```bash
/etc/init.d/apache2 restart
```

-	Test your environment by pointing a browser to the "esn.php" file

	http://your.server.example.com/esn-sabre/esn.php

### Enable the Caldav support in OpenPaaS ESN

The caldav support in OpenPaaS ESN is enabled by telling the system where to find the caldav server. To do so, create a document in the configuration collection, having this structure:

```json
{
        "_id" : "davserver",
        "backend" : {
                "url" : "http://192.168.7.6/esn-sabre/esn.php"
        },
        "frontend" : {
                "url" : "http://my-caldav-server.example.com/esn-sabre/esn.php"
        }
}
```

The backend url is used by the ESN SERVER to access the caldav server. The frontend url is used by the user's browser to access the caldav server.

## Unit tests and codestyle

A simple Makefile exists to make it easier to run the tests and check code style. The following commands are available:

```bash
make test                        # Run all tests
make test TARGET=tests/CalDAV/   # Run only certain tests
make test-report                 # Run tests and create a coverage report

make lint                        # Check code style for the whole project
make lint TARGET=lib/            # Check only certain files for lint
```

The coverage report will be placed in the `tests/report` directory, a clickable link will be presented when done.

## Docker

### Build

```
docker build -t linagora/esn-sabre .
```

### Run

In order to run, the ESN sabre instance must access to the ESN, and mongo instances. They can be configured from the run command using these optional environment variables:

- SABRE_MONGO_HOST: Mongodb instance used to store sabre data, defaults to 'sabre_mongo'
- SABRE_MONGO_PORT: Port used by the Mongodb instance defined above, defaults to '27017'
- ESN_MONGO_HOST: Mongodb instance of the ESN, used to create principals, defaults to 'esn_mongo'
- ESN_MONGO_PORT: Port of the ESN Mongodb instance, defaults to '27017'
- ESN_MONGO_DBNAME: Database name of the ESN Mongodb instance, defaults to 'esn'
- ESN_HOST: Hostname of the ESN API, defaults to 'esn_host'
- ESN_PORT: Port of the ESN API, defaults to '8080'
- REDIS_HOST: Redis instance used by Sabre and the ESN, defaults to 'redis_host'
- REDIS_PORT: Port of the instance defined just above, defaults to '6379'
- MONGO_TIMEOUT: Timeout to connect to Mongodb, defaults to 10000 ms

For example:

```
docker run -d -p 8001:80 -e "SABRE_MONGO_HOST=192.168.0.1" -e "ESN_MONGO_HOST=192.168.0.1" linagora/esn-sabre
```

This will launch the Sabre container, create its configuration, launch Sabre and expose on port 8001 on your host.
