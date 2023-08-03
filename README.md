# CLI tool for managing Let's Encrypt certificates

This command-line tool allows you to request, renew and revoke SSL certificates provided by Let's Encrypt. This tool relies on the [rogierw/rw-acme-client](https://github.com/RogierW/rw-acme-client) package.

## Requirements
- PHP ^8.2
- OpenSSL >= 1.0.1
- cURL extension
- JSON extension

## Installation
You can install the package via composer:

```php
composer global require rogierw/rw-acme-cli
```

## Setup environment
Copy the .env.example to .env and changes the default values.
```text
EMAIL=john@doe.com
STORAGE_PATH=storage
```

## Usage

#### Creating an account
The first step would be creating an account.
```php
rw-acme account:create <e-mail address>
```

View your account details:
```php
rw-acme account:details
```

The output will be something like this:
```php
Account details.

ID: 1000222333
Status: valid
E-mail: mailto:john@doe.com
Initial IP: 127.0.0.1
Created at: 2023-05-19T10:00:25Z
```

#### Creating an order
```php
rw-acme certificate:order <domain>
```
There are various options that you could add to the command. Run `rw-acme certificate:order --help` to view all the available options.
